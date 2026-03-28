@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Barang Masuk</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('barang-masuk.index') }}">Barang Masuk</a></li>
                        <li class="breadcrumb-item active">Tambah Barang Masuk</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <form id="form_barang_masuk" action="{{ route('barang-masuk.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Form Tambah Barang Masuk</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="tanggal">Tanggal</label>
                                    <input type="date" name="tanggal" class="form-control" id="tanggal"
                                        value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" readonly required>
                                </div>

                                <div class="form-group">
                                    <label for="npl_select">Packing List</label>
                                    <select name="npl" id="npl_select" class="form-control" style="width: 100%;" required>
                                        <option value="">-- Pilih Packing List --</option>
                                        @foreach(($packingLists ?? []) as $pl)
                                            <option value="{{ data_get($pl, 'npl') }}">
                                                {{ data_get($pl, 'npl') }} - {{ !empty(data_get($pl, 'tanggal')) ? \Carbon\Carbon::parse(data_get($pl, 'tanggal'))->format('d-m-Y') : '-' }} ({{ strtoupper(data_get($pl, 'status', 'pending')) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="nbrg">Scan Barcode Barang
                                        <span class="ml-1" data-toggle="tooltip" data-placement="top"
                                            title="Pilih Packing List dahulu, lalu scan barcode. Barcode 10 digit pertama (delimiter ;) akan dicocokkan dengan data packing list."
                                            style="cursor: help;">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                    <input type="text" id="nbrg" class="form-control" placeholder="Scan di sini..." disabled>
                                    <small class="text-muted d-block mt-1">
                                        Progress: <span id="scan_progress">0/0</span>
                                        <span class="ml-2" id="scan_status_badge"></span>
                                    </small>
                                </div>

                                <div id="pl_header" style="display:none;">
                                    <div class="text-center">
                                        <h4><strong><u>PACKING LIST</u></strong></h4>
                                        <p class="mb-1">
                                            <strong>No. : <span id="pl_no">-</span> / Tgl. : <span id="pl_tgl">-</span></strong>
                                            <span class="badge badge-warning ml-2" id="pl_status">PENDING</span>
                                        </p>
                                    </div>
                                    <br>
                                    <table class="table table-bordered text-nowrap" style="table-layout: fixed; width: 100%;">
                                        <tr>
                                            <td style="width: 40%; text-align: left;">
                                                <strong>Pemasok : <span id="pl_pemasok">-</span></strong>
                                            </td>
                                            <td style="width: 33.33%; text-align: center;">
                                                <strong>Pembeli : <span id="pl_customer">-</span></strong>
                                            </td>
                                            <td style="width: 33.33%; text-align: right;">
                                                <strong>Mobil / No. Polisi : <span id="pl_vehicle">-</span></strong>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="mt-3" id="pl_table_wrapper" style="display:none;">
                                    <table id="barang_masuk_preview" class="table table-bordered table-head-fixed text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Barcode</th>
                                                <th>Keterangan</th>
                                                <th>Nomor Seri</th>
                                                <th>Pcs</th>
                                                <th>Berat (KG)</th>
                                                <th>Panjang (MLC)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pl_items_tbody">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-right">
                                <button type="button" id="btn_reset_scan" class="btn btn-outline-secondary mr-2" style="display:none;">
                                    <i class="fas fa-redo mr-1"></i> Reset Scan
                                </button>
                                <button type="submit" id="btn_submit" class="btn btn-primary" style="display:none;">
                                    <i class="fas fa-check mr-1"></i> Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    document.title = 'Tambah Barang Masuk';

    var $nplSelect = $('#npl_select').select2({
        theme: 'bootstrap4',
        placeholder: '-- Pilih Packing List --',
        allowClear: true
    });

    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var $scanInput  = $('#nbrg');
    var $tbody      = $('#pl_items_tbody');
    var $headerBox  = $('#pl_header');
    var $tableWrap  = $('#pl_table_wrapper');
    var $progressEl = $('#scan_progress');
    var $statusBadge= $('#scan_status_badge');
    var $btnSubmit  = $('#btn_submit');
    var $btnReset   = $('#btn_reset_scan');

    var masterItems = [];
    var scannedItems = [];
    var currentNpl  = '';
    var masterCount = 0;

    function toast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, showConfirmButton: false, timer: 1800, timerProgressBar: true });
    }

    function statusClass(s) {
        switch ((s || 'pending').toLowerCase()) {
            case 'approved': return 'badge-success';
            case 'used': return 'badge-info';
            default: return 'badge-warning';
        }
    }

    function updateProgress() {
        var sc = scannedItems.length;
        $progressEl.text(sc + '/' + masterCount);

        if (masterCount > 0 && sc >= masterCount) {
            $statusBadge.html('<span class="badge badge-success">LENGKAP</span>');
            $btnSubmit.show();
        } else if (masterCount > 0) {
            $statusBadge.html('<span class="badge badge-warning">BELUM LENGKAP (' + sc + '/' + masterCount + ')</span>');
            $btnSubmit.hide();
        } else {
            $statusBadge.html('');
            $btnSubmit.hide();
        }
    }

    function renderScannedTable() {
        $tbody.empty();
        scannedItems.forEach(function(item, idx) {
            var berat = (item.weight != null && item.weight !== '') ? Number(item.weight).toFixed(2) : '-';
            var panjang = (item.length != null && item.length !== '') ? Number(item.length).toFixed(2) : '-';

            $tbody.append(
                '<tr class="table-success">' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td><code>' + (item.barcode || '-') + '</code></td>' +
                    '<td>' + (item.salestext || '-') + '</td>' +
                    '<td>' + (item.batch_no || '-') + '</td>' +
                    '<td>' + (item.pcs || '-') + '</td>' +
                    '<td>' + berat + '</td>' +
                    '<td>' + panjang + '</td>' +
                    '<td><span class="badge badge-success"><i class="fas fa-check mr-1"></i>Match</span></td>' +
                '</tr>'
            );
        });

        updateProgress();
    }

    /** Contoh scanner: YB00322662;9.500;33.333 → ambil segmen sebelum ';', lalu max 10 karakter pertama. */
    function extractBarcode(raw) {
        var part = raw.split(';')[0];
        part = part.trim();
        if (part.length > 10) {
            part = part.substring(0, 10);
        }
        return part;
    }

    var scanInFlight = false;

    function runScanAjax(rawVal) {
        if (scanInFlight) return;
        rawVal = (rawVal || '').trim();
        if (!rawVal) return;

        var barcode10 = extractBarcode(rawVal);
        console.log('[Scan] raw:', rawVal, '=> barcode10:', barcode10);

        if (!currentNpl) {
            toast('warning', 'Pilih packing list terlebih dahulu.');
            return;
        }

        scanInFlight = true;
        $.ajax({
            url: '{{ route("barang-masuk.scan") }}',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            contentType: 'application/json',
            data: JSON.stringify({ npl: currentNpl, barcode: barcode10 }),
            dataType: 'json',
            success: function(data) {
                console.log('[Scan] response:', data);

                if (!data.success) {
                    toast(data.type === 'duplicate' ? 'info' : 'error', data.message || 'Barcode tidak valid.');
                    return;
                }

                toast('success', data.message || 'Barcode cocok!');

                if (data.barcode_detail) {
                    scannedItems.push(data.barcode_detail);
                }

                renderScannedTable();
                $scanInput.focus();
            },
            error: function(xhr) {
                try {
                    var err = JSON.parse(xhr.responseText);
                    toast(err.type === 'duplicate' ? 'info' : 'error', err.message || 'Barcode tidak valid.');
                } catch(ex) {
                    toast('error', 'Gagal memvalidasi barcode.');
                }
            },
            complete: function() {
                scanInFlight = false;
            }
        });
    }

    function resetUI() {
        masterItems = [];
        scannedItems = [];
        currentNpl = '';
        masterCount = 0;
        $tbody.empty();
        $headerBox.hide();
        $tableWrap.hide();
        $btnReset.hide();
        $btnSubmit.hide();
        $scanInput.val('').prop('disabled', true);
        updateProgress();
    }

    // --- Saat Packing List dipilih ---
    $nplSelect.on('change', function() {
        var npl = $(this).val();
        console.log('[Select2 change] value:', npl);

        resetUI();

        if (!npl) return;

        currentNpl = npl;

        $.ajax({
            url: '{{ route("barang-masuk.packing-list-items") }}',
            data: { npl: npl },
            dataType: 'json',
            success: function(data) {
                console.log('[loadPL] response:', data);

                if (!data.success) {
                    toast('error', data.message || 'Gagal memuat packing list.');
                    return;
                }

                $('#pl_no').text(data.packing_list?.npl ?? '-');
                $('#pl_tgl').text(data.packing_list?.tanggal ?? '-');
                var st = (data.packing_list?.status ?? 'pending');
                $('#pl_status').attr('class', 'badge ' + statusClass(st) + ' ml-2').text(String(st).toUpperCase());

                $('#pl_pemasok').text(data.header?.pemasok ?? '-');
                $('#pl_customer').text(data.header?.customer ?? '-');
                var veh = data.header?.no_vehicle ?? data.header?.vehicle_number ?? '-';
                $('#pl_vehicle').text(veh);

                masterItems = Array.isArray(data.items) ? data.items : [];
                masterCount = data.master_count || masterItems.length;

                var prevScanned = data.scanned_list || [];
                if (prevScanned.length > 0) {
                    masterItems.forEach(function(item) {
                        if (prevScanned.indexOf(String(item.barcode)) !== -1) {
                            scannedItems.push(item);
                        }
                    });
                }

                $headerBox.show();
                $tableWrap.show();
                $btnReset.show();
                $scanInput.prop('disabled', false).val('').focus();

                renderScannedTable();
                console.log('[loadPL] Input barcode enabled. masterCount:', masterCount, 'scanned:', scannedItems.length);
            },
            error: function(xhr) {
                console.error('[loadPL] AJAX error:', xhr.responseText);
                toast('error', 'Gagal memuat data packing list.');
            }
        });
    });

    // --- Scan barcode: segmen pertama sebelum ';', max 10 karakter. Auto-kirim saat panjang ekstraksi = 10. ---
    $scanInput.on('input', function() {
        if (scanInFlight) return;
        var rawVal = ($scanInput.val() || '').trim();
        if (extractBarcode(rawVal).length !== 10) return;

        $scanInput.val('');
        runScanAjax(rawVal);
    });

    $scanInput.on('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();

        var rawVal = ($scanInput.val() || '').trim();
        $scanInput.val('');
        if (!rawVal) return;

        runScanAjax(rawVal);
    });

    // --- Reset scan ---
    $btnReset.on('click', function() {
        if (!currentNpl) return;

        Swal.fire({
            title: 'Reset Scan?',
            text: 'Semua data scan akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Reset',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: '{{ route("barang-masuk.flush") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                contentType: 'application/json',
                data: JSON.stringify({ npl: currentNpl }),
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        toast('success', data.message || 'Scan direset.');
                        scannedItems = [];
                        renderScannedTable();
                        $scanInput.val('').focus();
                    } else {
                        toast('error', data.message || 'Gagal reset.');
                    }
                },
                error: function() {
                    toast('error', 'Gagal reset scan.');
                }
            });
        });
    });
});
</script>
@endpush
