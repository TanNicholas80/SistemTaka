@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Reprint Barcode</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item active">Reprint Barcode</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                {{-- ① Search bar Kode Barang — di luar card, di tengah ──────────── --}}
                <div class="row justify-content-center mb-3">
                    <div class="col-md-5">
                        <label class="font-weight-bold mb-1">Kode Barang</label>
                        <div class="position-relative">
                            <input type="text" id="item_search" class="form-control" placeholder="Ketik kode barang..."
                                autocomplete="off">
                            <ul id="item_dropdown" class="list-group position-absolute w-100 shadow"
                                style="z-index:9999; display:none; max-height:220px; overflow-y:auto;"></ul>
                        </div>
                    </div>
                </div>

                {{-- ② Card tabel ────────────────────────────────────────────────── --}}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">Data Barcode berdasarkan Kode Barang</h3>
                            </div>
                            <div class="card-body">

                                {{-- Permanent toolbar: Bulk Reprint (left) + custom filter (right) --}}
                                <div id="dt_toolbar" class="d-flex justify-content-between align-items-center mb-2">
                                    @if(Auth::user()->role !== 'owner')
                                        <div>
                                            <button id="bulk_reprint_btn" class="btn btn-warning btn-sm" disabled
                                                onclick="doBulkReprint()">
                                                <i class="fas fa-print"></i> Bulk Reprint
                                            </button>
                                        </div>
                                    @endif
                                    <div>
                                        <label class="mb-0">Filter data: <input id="dt_filter_input" type="search"
                                                class="form-control form-control-sm d-inline-block ml-1" style="width:auto"
                                                placeholder=""></label>
                                    </div>
                                </div>

                                <table id="print_barcode_table" class="table table-head-fixed text-nowrap"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="check_all"></th>
                                            <th>No Barcode</th>
                                            <th>No Seri</th>
                                            <th>Nama Barang</th>
                                            <th>Merek Barang</th>
                                            <th>Motif</th>
                                            <th>Warna</th>
                                            <th>Special Treat</th>
                                            <th>Qty</th>
                                            <th>Satuan</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="barcode_tbody">
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-4">Pilih Kode Barang terlebih
                                                dahulu.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <script>
        let selectedItemNo = '';
        let dataTable = null;
        let searchTimeout;

        // ── Autocomplete ──────────────────────────────────────────────────────────
        const searchInput = document.getElementById('item_search');
        const dropdown = document.getElementById('item_dropdown');

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            searchTimeout = setTimeout(() => fetchItems(q), 300);
        });

        searchInput.addEventListener('blur', function () {
            setTimeout(() => dropdown.style.display = 'none', 200);
        });

        function fetchItems(q) {
            fetch('{{ route("print_barcode.search_items") }}?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(items => {
                    dropdown.innerHTML = '';
                    if (!items || !items.length) { dropdown.style.display = 'none'; return; }
                    items.slice(0, 5).forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item list-group-item-action py-1 px-2';
                        li.style.cursor = 'pointer';
                        li.textContent = item.no;   // only kode barang
                        li.addEventListener('mousedown', () => selectItem(item));
                        dropdown.appendChild(li);
                    });
                    dropdown.style.display = 'block';
                })
                .catch(() => { dropdown.style.display = 'none'; });
        }

        function selectItem(item) {
            selectedItemNo = item.no;
            searchInput.value = item.no;
            dropdown.style.display = 'none';
            loadSerials(item.no);
        }

        // ── Load serials ──────────────────────────────────────────────────────────
        function loadSerials(itemNo) {
            const tbody = document.getElementById('barcode_tbody');
            tbody.innerHTML = '<tr><td colspan="11" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat data...</td></tr>';

            // Reset semua checkbox & bulk button saat item diganti
            document.getElementById('check_all').checked = false;
            document.querySelectorAll('.row_check').forEach(cb => cb.checked = false);
            updateBulkBtn();

            if (dataTable) { dataTable.destroy(); dataTable = null; }

            fetch('{{ route("print_barcode.serials") }}?itemNo=' + encodeURIComponent(itemNo))
                .then(r => r.json())
                .then(data => {
                    if (data && data.error) {
                        tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">${escapeHtml(data.error)}</td></tr>`;
                        return;
                    }
                    const rows = Array.isArray(data) ? data : [];
                    if (rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Tidak ada data barcode untuk item ini.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = '';
                    rows.forEach(row => {
                        const serial = escapeHtml(row.serialNo ?? '');
                        const noSeriLocal = escapeHtml(row.noSeriLocal ?? '');
                        const reprUrl = '{{ url("print-barcode/reprint") }}/' + encodeURIComponent(row.serialNo ?? '') + '?itemNo=' + encodeURIComponent(itemNo);
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                                            <td><input type="checkbox" class="row_check" data-serial="${serial}" data-item="${escapeHtml(itemNo)}"></td>
                                            <td>${serial}</td>
                                            <td>${noSeriLocal}</td>
                                            <td>${escapeHtml(row.itemName ?? '')}</td>
                                            <td>${escapeHtml(row.brand ?? '')}</td>
                                            <td>${escapeHtml(row.motif ?? '')}</td>
                                            <td>${escapeHtml(row.warna ?? '')}</td>
                                            <td>${escapeHtml(row.specialTreat ?? '')}</td>
                                            <td>${row.qty ?? ''}</td>
                                            <td>${escapeHtml(row.unit ?? '')}</td>
                                            <td class="text-center">
                                                @if(Auth::user()->role !== 'owner')
                                                    <a href="${reprUrl}" target="_blank" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-print"></i> Reprint
                                                    </a>
                                                @else
                                                    <span class="text-muted small">View Only</span>
                                                @endif
                                            </td>
                                        `;
                        tbody.appendChild(tr);
                    });

                    // ② Init DataTable — no built-in filter ('f'), use custom filter input instead
                    dataTable = $('#print_barcode_table').DataTable({
                        ordering: false,
                        paging: rows.length > 25,
                        pageLength: 25,
                        dom: 'rt<"d-flex justify-content-between align-items-center mt-2"ip>',
                        language: { zeroRecords: 'Tidak ada data.' },
                        columnDefs: [{ targets: [0, 10], searchable: false, orderable: false }],
                    });

                    // Connect custom filter input to DataTable search
                    document.getElementById('dt_filter_input').value = '';
                    $('#dt_filter_input').off('input').on('input', function () {
                        dataTable.search(this.value).draw();
                    });

                    bindCheckboxes();
                })
                .catch(err => {
                    tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Gagal mengambil data dari server.</td></tr>';
                    console.error(err);
                });
        }

        // ── Checkboxes ────────────────────────────────────────────────────────────
        function bindCheckboxes() {
            document.querySelectorAll('.row_check').forEach(cb => {
                cb.removeEventListener('change', onCheckChange);
                cb.addEventListener('change', onCheckChange);
            });
        }

        document.getElementById('check_all').addEventListener('change', function () {
            document.querySelectorAll('.row_check').forEach(cb => cb.checked = this.checked);
            updateBulkBtn();
        });

        function onCheckChange() { updateBulkBtn(); }

        function updateBulkBtn() {
            const btn = document.getElementById('bulk_reprint_btn');
            if (!btn) return;
            const checked = document.querySelectorAll('.row_check:checked').length;
            btn.disabled = checked < 2;
        }

        // ── Bulk Reprint ──────────────────────────────────────────────────────────
        function doBulkReprint() {
            const selected = [];
            document.querySelectorAll('.row_check:checked').forEach(cb => {
                selected.push({ serialNo: cb.dataset.serial, itemNo: cb.dataset.item });
            });
            if (selected.length < 2) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("print_barcode.bulk_reprint") }}';
            form.target = '_blank';

            const csrf = document.createElement('input');
            csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            selected.forEach((item, i) => {
                ['serialNo', 'itemNo'].forEach(key => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = `items[${i}][${key}]`;
                    inp.value = item[key];
                    form.appendChild(inp);
                });
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // ── Utils ─────────────────────────────────────────────────────────────────
        function escapeHtml(str) {
            if (str == null) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    </script>
@endsection
