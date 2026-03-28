@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Print Barcode Retur</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Print Barcode Retur</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if (empty($customerNo))
            <div class="alert alert-warning">
                <i class="icon fas fa-exclamation-triangle"></i>
                Cabang ini belum memiliki <code>customer_id</code> (nomor pelanggan Accurate, mis. <code>C.00001</code>). Pilih cabang dengan pelanggan terpasang agar daftar faktur dapat dimuat.
            </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Pilih Faktur</h3>
                </div>
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-5 position-relative">
                            <label for="no_faktur" class="mb-1">
                                Faktur
                                <span class="ml-1" data-toggle="tooltip" data-placement="top" title="Cari &amp; pilih nomor faktur penjualan (sama seperti pemilihan PO di penerimaan barang)" style="cursor: help;">
                                    <i class="fas fa-info-circle text-muted"></i>
                                </span>
                            </label>
                            <div class="input-group">
                                <input
                                    type="search"
                                    id="no_faktur"
                                    class="form-control"
                                    placeholder="Cari/Pilih Nomor Faktur..."
                                    autocomplete="off"
                                    {{ empty($customerNo) ? 'disabled' : '' }}>
                                <div class="input-group-append">
                                    <button type="button" id="faktur-search-btn" class="btn btn-outline-secondary" {{ empty($customerNo) ? 'disabled' : '' }} title="Tampilkan / cari">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="dropdown-faktur" class="position-absolute w-100 bg-white border rounded shadow-sm mt-1 d-none text-sm" style="z-index: 1050; max-height: 11rem; overflow-y: auto;"></div>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="no_do">No. DO</label>
                            <input type="text" id="no_do" class="form-control" readonly placeholder="Diisi setelah Lanjut">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="d-block" style="visibility:hidden;">Lanjut</label>
                            @if(Auth::user()->role !== 'owner')
                            <button type="button" id="btn_lanjut" class="btn btn-primary btn-block" {{ empty($customerNo) ? 'disabled' : '' }}>
                                Lanjut
                            </button>
                            @else
                            <button type="button" class="btn btn-secondary btn-block" disabled>View Only</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Rincian Barcode</h3>
                    <div class="card-tools">
                        @if(Auth::user()->role !== 'owner')
                        <button type="button" id="btn_bulk_print" class="btn btn-secondary btn-sm" disabled>
                            <i class="fas fa-print"></i> Bulk print
                        </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div id="resolve_error" class="alert alert-danger d-none" role="alert"></div>
                    <div class="table-responsive">
                        <table id="tbl_barcode_retur" class="table table-bordered table-striped table-sm w-100">
                            <thead>
                                <tr>
                                    <th style="width: 36px;"><input type="checkbox" id="chk_all" title="Pilih semua (yang bisa print)"></th>
                                    <th>No. Barcode</th>
                                    <th>Kode #</th>
                                    <th>QTY</th>
                                    <th>Satuan</th>
                                    <th>Nama Barang</th>
                                    <th style="width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script type="application/json" id="urls-data">@json($urls)</script>
<script type="application/json" id="daftar-faktur-data">@json($faktur_list ?? [])</script>
<script type="application/json" id="customer-no-data">@json($customerNo ?? '')</script>
<script>
(function () {
    const urls = JSON.parse(document.getElementById('urls-data')?.textContent || '{}');
    const daftar_faktur = JSON.parse(document.getElementById('daftar-faktur-data')?.textContent || '[]');
    const customerNo = JSON.parse(document.getElementById('customer-no-data')?.textContent || '""');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const rowsById = {};

    const $errorBox = $('#resolve_error');
    const $btnBulk = $('#btn_bulk_print');
    const $chkAll = $('#chk_all');
    const $noFaktur = $('#no_faktur');
    const $dropdownFaktur = $('#dropdown-faktur');

    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });

    function showError(msg) {
        $errorBox.text(msg).removeClass('d-none');
    }
    function clearError() {
        $errorBox.addClass('d-none').text('');
    }

    function getSelectedFakturNumber() {
        return ($noFaktur.val() || '').trim();
    }

    function showAllFaktur() {
        const el = $dropdownFaktur;
        el.empty();
        if (!daftar_faktur.length) {
            el.append(
                $('<div class="px-3 py-2 text-center text-muted border-bottom"/>')
                    .html('<i class="fas fa-info-circle mr-1"></i>Tidak ada faktur (periksa customer cabang &amp; status faktur di Accurate)')
            );
            el.removeClass('d-none');
            return;
        }
        const maxShow = 50;
        const header = $('<div class="px-3 py-2 bg-light border-bottom font-weight-bold text-secondary small"/>')
            .html('<i class="fas fa-list mr-1"></i>Semua Faktur (' + daftar_faktur.length + ')');
        el.append(header);
        const slice = daftar_faktur.slice(0, maxShow);
        slice.forEach(function (row) {
            el.append(renderFakturRow(row, ''));
        });
        if (daftar_faktur.length > maxShow) {
            el.append(
                $('<div class="px-3 py-2 bg-info text-white small text-center"/>')
                    .html('Menampilkan ' + maxShow + ' dari ' + daftar_faktur.length + '. Ketik untuk pencarian.')
            );
        }
        el.removeClass('d-none');
    }

    function renderFakturRow(row, query) {
        const num = row.number_faktur || '';
        const status = row.status_faktur || '';
        const cust = row.customer_name || '';
        const item = $('<div class="px-3 py-2 border-bottom"/>');
        item.css('cursor', 'pointer');
        item.on('mouseenter', function () { $(this).addClass('bg-light'); });
        item.on('mouseleave', function () { $(this).removeClass('bg-light'); });

        const line1 = $('<div class="font-weight-bold text-dark"/>');
        if (query) {
            try {
                line1.html(num.replace(new RegExp('(' + escapeRegExp(query) + ')', 'gi'), '<mark class="bg-warning">$1</mark>'));
            } catch (e) {
                line1.text(num);
            }
        } else {
            line1.text(num);
        }
        const line2 = $('<div class="text-muted small"/>');
        const sub = [status, cust].filter(Boolean).join(' — ');
        line2.text(sub || '—');

        item.append(line1).append(line2);
        item.on('click', function () {
            $noFaktur.val(num);
            $dropdownFaktur.addClass('d-none');
        });
        return item;
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function showDropdownFaktur(input) {
        const query = (input.value || '').toLowerCase().trim();
        const el = $dropdownFaktur;
        el.empty();
        if (query === '') {
            showAllFaktur();
            return;
        }
        const result = daftar_faktur.filter(function (row) {
            const num = (row.number_faktur || '').toLowerCase();
            const st = (row.status_faktur || '').toLowerCase();
            const cn = (row.customer_name || '').toLowerCase();
            return num.includes(query) || st.includes(query) || cn.includes(query);
        });
        if (!result.length) {
            el.append(
                $('<div class="px-3 py-2 text-center text-muted border-bottom"/>')
                    .html('<i class="fas fa-search mr-1"></i>Tidak ada faktur yang cocok')
            );
        } else {
            el.append(
                $('<div class="px-3 py-2 border-bottom font-weight-bold small text-primary bg-light"/>')
                    .html('<i class="fas fa-search mr-1"></i>Hasil: ' + result.length)
            );
            result.forEach(function (row) {
                el.append(renderFakturRow(row, query));
            });
        }
        el.removeClass('d-none');
    }

    function handleSearchFakturClick(e) {
        e.preventDefault();
        e.stopPropagation();
        const q = ($noFaktur.val() || '').trim();
        if (q === '') {
            showAllFaktur();
        } else {
            showDropdownFaktur($noFaktur[0]);
        }
    }

    if (customerNo) {
        $('#faktur-search-btn').on('click', handleSearchFakturClick);
        $noFaktur.on('input', function () {
            if (!$noFaktur.prop('disabled')) {
                showDropdownFaktur(this);
            }
        });
        $noFaktur.on('focus', function () {
            if ($noFaktur.prop('disabled')) {
                return;
            }
            if (($(this).val() || '').trim() === '') {
                showAllFaktur();
            } else {
                showDropdownFaktur(this);
            }
        });
        $noFaktur.on('keydown', function (e) {
            if (e.key === 'Escape') {
                $dropdownFaktur.addClass('d-none');
            }
        });
    }

    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('no_faktur')?.closest('.position-relative');
        if (wrap && !wrap.contains(e.target)) {
            $dropdownFaktur.addClass('d-none');
        }
    });

    function postPrintPdf(item, labels) {
        return fetch(urls.printPdf, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/pdf',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ item: item, labels: labels })
        }).then(function (res) {
            if (!res.ok) {
                return res.text().then(function (t) { throw new Error(t || ('HTTP ' + res.status)); });
            }
            return res.blob();
        });
    }

    function openPdfBlob(blob) {
        const u = URL.createObjectURL(blob);
        window.open(u, '_blank');
        setTimeout(function () { URL.revokeObjectURL(u); }, 60_000);
    }

    const dt = $('#tbl_barcode_retur').DataTable({
        data: [],
        searching: true,
        // Bulk print tidak perlu fitur sort Asc/Desc agar kontrol UI tidak tampil.
        ordering: false,
        order: [],
        pageLength: 25,
        columns: [
            {
                data: 'row_id',
                orderable: false,
                className: 'text-center',
                render: function (id, type, row) {
                    if (!row.printable) {
                        return '';
                    }
                    return '<input type="checkbox" class="row-chk" value="' + $('<div>').text(id).html() + '">';
                }
            },
            {
                data: 'barcode',
                render: function (d) {
                    return d ? $('<div>').text(d).html() : '<span class="text-muted">—</span>';
                }
            },
            { data: 'kode' },
            { data: 'qty' },
            { data: 'satuan' },
            { data: 'nama_barang' },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function (_data, _type, row) {
                    if (!row.printable) {
                        return '<span class="text-muted small">No serial</span>';
                    }
                    @if(Auth::user()->role !== 'owner')
                    return '<button type="button" class="btn btn-sm btn-primary btn-print-one" data-id="' + $('<div>').text(row.row_id).html() + '">Print Barcode</button>';
                    @else
                    return '<span class="text-muted small">Ready</span>';
                    @endif
                }
            }
        ]
    });

    function syncRowData(rows) {
        Object.keys(rowsById).forEach(function (k) { delete rowsById[k]; });
        (rows || []).forEach(function (r) {
            rowsById[r.row_id] = r;
        });
    }

    function refreshBulkButton() {
        const n = $('#tbl_barcode_retur tbody input.row-chk:checked').length;
        $btnBulk.prop('disabled', n === 0);
    }

    $('#tbl_barcode_retur tbody').on('change', 'input.row-chk', refreshBulkButton);

    $chkAll.on('change', function () {
        const on = $(this).prop('checked');
        $('#tbl_barcode_retur tbody input.row-chk').prop('checked', on);
        refreshBulkButton();
    });

    $('#btn_lanjut').on('click', function () {
        clearError();
        const num = getSelectedFakturNumber();
        if (!num) {
            showError('Pilih atau isi nomor faktur terlebih dahulu.');
            return;
        }
        $('#no_do').val('');
        $chkAll.prop('checked', false);
        $btnBulk.prop('disabled', true);

        fetch(urls.resolve, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ number: num })
        })
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, status: res.status, body: body }; }); })
            .then(function (r) {
                if (!r.ok || !r.body.success) {
                    const msg = (r.body && r.body.message) ? r.body.message : ('Gagal (' + r.status + ')');
                    showError(msg);
                    dt.clear().draw();
                    syncRowData([]);
                    return;
                }
                $('#no_do').val(r.body.no_do || '');
                syncRowData(r.body.rows || []);
                dt.clear();
                dt.rows.add(r.body.rows || []);
                dt.draw();
                $chkAll.prop('checked', false);
                refreshBulkButton();
            })
            .catch(function (e) {
                showError(e.message || 'Terjadi kesalahan jaringan.');
                dt.clear().draw();
                syncRowData([]);
            });
    });

    $('#tbl_barcode_retur').on('click', '.btn-print-one', function () {
        const id = $(this).data('id');
        const row = rowsById[id];
        if (!row || !row.printable || !row.barcode) {
            return;
        }
        postPrintPdf(row.item, [{ barcode: row.barcode, quantity: Number(row.qty) || 0 }])
            .then(openPdfBlob)
            .catch(function (e) {
                alert('Gagal mencetak: ' + (e.message || e));
            });
    });

    $btnBulk.on('click', function () {
        const ids = $('#tbl_barcode_retur tbody input.row-chk:checked').map(function () { return $(this).val(); }).get();
        const rows = ids.map(function (id) { return rowsById[id]; }).filter(Boolean);
        const printable = rows.filter(function (r) { return r.printable && r.barcode; });
        if (!printable.length) {
            alert('Tidak ada baris terpilih yang memiliki nomor barcode.');
            return;
        }

        const groups = new Map();
        printable.forEach(function (r) {
            const key = JSON.stringify(r.item);
            if (!groups.has(key)) {
                groups.set(key, { item: r.item, labels: [] });
            }
            groups.get(key).labels.push({ barcode: r.barcode, quantity: Number(r.qty) || 0 });
        });

        const run = async function () {
            try {
                for (const g of groups.values()) {
                    const labels = g.labels;
                    for (let i = 0; i < labels.length; i += 200) {
                        const chunk = labels.slice(i, i + 200);
                        const blob = await postPrintPdf(g.item, chunk);
                        openPdfBlob(blob);
                    }
                }
            } catch (e) {
                alert('Gagal bulk print: ' + (e.message || e));
            }
        };
        run();
    });
})();
</script>
@endpush
