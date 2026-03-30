@extends('layout.main')

@section('content')
<style>
    .custom-file-input:focus~.custom-file-label {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .custom-file-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .custom-file-label::after {
        content: "Browse";
    }
</style>
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Form Stock Opname</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('hasil_stock_opname.index') }}">Hasil Stock
                                Opname</a></li>
                        <li class="breadcrumb-item active">Stock Opname</li>
                    </ol>
                </div>
            </div>

            <div class="card">
                <form id="penerimaanForm" method="POST" action="/hasil-stock-opname/store" class="p-3 space-y-3">
                    <div id="laravel-errors"
                        @if(session('error'))
                        data-error="{{ session('error') }}"
                        @endif
                        @if($errors->any())
                        data-validation-errors="{{ json_encode($errors->all()) }}"
                        @endif
                        ></div>
                    @csrf
                    <input type="hidden" id="form_submitted" name="form_submitted" value="0">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                <!-- Tanggal -->
                                <label for="tanggal" class="text-gray-800 font-medium flex items-center">
                                    Tanggal Opname<span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="tanggal" name="tanggal" type="date" value="{{ $selectedTanggal }}"
                                    class="border border-gray-300 rounded px-2 py-1 max-w-[300px] w-full {{ $formReadonly ? 'bg-gray-200 text-gray-500' : '' }}"
                                    required {{ $formReadonly ? 'readonly' : '' }} />

                                <!-- Perintah Stock Opname -->
                                <label for="no_perintah_opname" class="text-gray-800 font-medium flex items-center">
                                    Perintah Opname<span class="text-red-600 ml-1">*</span>
                                    <span class="ml-1"
                                        data-toggle="tooltip"
                                        data-placement="top"
                                        title="Silakan pilih nomor perintah opname dari dropdown"
                                        style="cursor: help;">
                                        <i class="fas fa-info-circle"></i>
                                    </span>
                                </label>
                                <div class="relative max-w-[300px] w-full">
                                    <div
                                        class="flex items-center border border-gray-300 rounded px-2 py-1 {{ $formReadonly ? 'bg-gray-200' : 'bg-white' }}">
                                        <input id="no_perintah_opname" name="no_perintah_opname" type="text"
                                            class="flex-grow outline-none w-full {{ $formReadonly ? 'text-gray-500' : '' }}"
                                            placeholder="Pilih nomor perintah..." value="{{ $selectedStockOpname }}"
                                            required {{ $formReadonly ? 'readonly' : '' }} />
                                        @if (!$formReadonly)
                                        <button type="button" id="btn-show-dropdown">
                                            <i class="fas fa-search text-gray-500 ml-2 cursor-pointer"></i>
                                        </button>
                                        @endif
                                    </div>

                                    @if (!$formReadonly)
                                    <div id="dropdown-stock-opname"
                                        class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded shadow mt-1 max-h-40 overflow-y-auto text-sm hidden">

                                        @if (count($stockOpnameOrders) > 0)
                                        @foreach ($stockOpnameOrders as $order)
                                        <a href="#" data-value="{{ $order['number'] }}"
                                            class="dropdown-item block px-4 py-2 hover:bg-gray-100 text-gray-800 cursor-pointer border-b border-gray-200"
                                            onclick="event.preventDefault(); document.getElementById('no_perintah_opname').value = this.getAttribute('data-value'); document.getElementById('dropdown-stock-opname').classList.add('hidden');">

                                            <div class="flex flex-col">
                                                <span
                                                    class="font-semibold text-base">{{ $order['number'] }}</span>
                                                <span
                                                    class="text-xs text-gray-500 text-right">{{ $order['transDate'] }}</span>
                                            </div>
                                        </a>
                                        @endforeach
                                        @else
                                        <div class="block px-4 py-2 text-gray-800">
                                            <span class="text-gray-500">Tidak ada data</span>
                                        </div>
                                        @endif

                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                <!-- Nomor Form -->
                                <label for="nop" class="text-gray-800 font-medium flex items-center">
                                    Nomor Form<span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="nop" name="nop" type="text" value="{{ $nop }}"
                                    class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6] {{ $formReadonly ? 'bg-gray-200 text-gray-500' : '' }}"
                                    readonly />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        @if (!$formReadonly)
                        <button type="button" id="btn-lanjut"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-xs">
                            Lanjut
                        </button>
                        @endif

                        {{-- Selalu render tombol scan, tapi sembunyikan saat belum readonly --}}
                        <button type="button" id="btn-scan"
                            class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded text-xs {{ !$formReadonly ? 'hidden' : '' }}"
                            data-toggle="modal" data-target="#scanModal">
                            Scan
                        </button>
                    </div>
                </form>

                <!-- Table container -->
                <div class="p-2 flex flex-col gap-2">
                    <div class="flex flex-row gap-4">
                        <!-- Table 1: Full Details -->
                        <div class="w-1/2 border border-gray-300 rounded overflow-hidden text-sm">
                            <div
                                class="flex justify-between items-center border border-gray-300 rounded-t bg-[#f9f9f9] px-2 py-2 text-sm">
                                <div class="text-gray-800 font-semibold text-base whitespace-nowrap">
                                    Rincian Barang
                                </div>
                            </div>
                            <table class="w-full border-collapse border border-gray-400 text-xs text-center">
                                <thead class="bg-[#607d8b] text-white">
                                    <tr>
                                        <th class="border border-gray-400 w-6" style="height: 40px;">≡</th>
                                        <th class="border border-gray-400 px-2 py-1 w-20" style="height: 40px;">Kode #
                                        </th>
                                        <th class="border border-gray-400 px-2 py-1 text-left w-48"
                                            style="height: 40px;">Nama Barang</th>
                                        <th class="border border-gray-200 px-2 py-1 w-24" style="height: 40px;">
                                            Kuantitas Gudang</th>
                                        <th class="border border-gray-400 px-2 py-1 w-16" style="height: 40px;">Satuan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="table-barang-body" class="bg-white">
                                    @if (isset($barang) && count($barang) > 0)
                                    @foreach ($barang as $item)
                                    <tr>
                                        <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
                                        <td class="border border-gray-400 px-2 py-3 align-top">
                                            {{ $item['item']['no'] }}
                                        </td>
                                        <td class="border border-gray-400 px-2 py-3 text-left align-top">
                                            {{ $item['item']['name'] }}
                                        </td>
                                        <td class="border border-gray-200 px-2 py-3 align-top">
                                            {{ $item['quantity'] }}
                                        </td>
                                        <td class="border border-gray-400 px-2 py-3 align-top">
                                            {{ $item['itemUnit']['name'] }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    @else
                                    <tr>
                                        <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
                                        <td class="border border-gray-400 px-2 py-3 text-center align-top"
                                            colspan="6">Belum ada data</td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Table 2: Name + Quantity only -->
                        <div class="w-1/2 border border-gray-300 rounded overflow-hidden text-sm">
                            <div
                                class="flex justify-between items-center border border-gray-300 rounded-t bg-[#f9f9f9] px-2 py-2 text-sm">
                                <div class="text-gray-800 font-semibold text-base whitespace-nowrap">
                                    Hasil Scanning Barang
                                </div>
                                <div class="flex items-center">
                                    <button type="button" id="btn-clear-scan"
                                        class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs">
                                        Hapus Semua
                                    </button>
                                </div>
                            </div>
                            <table class="w-full border-collapse border border-gray-400 text-xs text-center">
                                <thead class="bg-[#607d8b] text-white">
                                    <tr>
                                        <th class="border border-gray-400 w-6" style="height: 40px;">≡</th>
                                        <th class="border border-gray-400 px-2 py-1 text-left w-48"
                                            style="height: 40px;">Nama Barang</th>
                                        <th class="border border-gray-200 px-2 py-1 w-24" style="height: 40px;">
                                            Kuantitas Data</th>
                                        <th class="border border-gray-400 px-2 py-1 w-16" style="height: 40px;">Satuan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="hasilScanningTbody" class="bg-white">
                                    @if (isset($barang) && count($barang) > 0)
                                    @foreach ($barang as $item)
                                    <tr>
                                        <td class="border border-gray-400 px-2 py-3 text-center align-top">≡
                                        </td>
                                        <td class="border border-gray-400 px-2 py-3 text-left align-top">
                                            {{ $item['item']['name'] }}
                                        </td>
                                        <td class="border border-gray-200 px-2 py-3 align-top">-</td>
                                        <td class="border border-gray-400 px-2 py-3 align-top">
                                            {{ $item['itemUnit']['name'] }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    @else
                                    <tr>
                                        <td class="border border-gray-400 px-2 py-3 text-center align-top"
                                            colspan="6">Belum ada data</td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex justify-end mt-2">
                        <button type="button" id="btn-save-hasil-scan"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Save Hasil Scan
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Scan Modal -->
<div class="modal fade" id="scanModal" tabindex="-1" role="dialog" aria-labelledby="scanModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scanModalLabel">Scan Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Status Bar -->
                <div class="mb-3">
                    <div class="alert alert-info" id="statusAlert" style="display: none;">
                        <span id="statusMessage"></span>
                    </div>
                </div>

                <!-- Barcode Input -->
                <div class="mb-3">
                    <label for="barcodeInput" class="form-label">Barcode:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="barcodeInput"
                            placeholder="Masukkan barcode format: KODE;BERAT;Panjang (MLC)" autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="clearInputBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">
                        Format: KODE;BERAT;Panjang (MLC) (contoh: YB00315043;8.700;30.000)
                    </small>
                </div>

                <!-- Tambahkan di dalam modal scan, setelah bulk input -->
                <div class="mb-3">
                    <label for="barcodeFileInput" class="form-label">Upload File Notepad (.txt):</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="barcodeFileInput" accept=".txt">
                        <label class="custom-file-label" for="barcodeFileInput" id="barcodeFileLabel">Pilih file...</label>
                    </div>
                    <small class="form-text text-muted">
                        File harus berformat .txt dengan isi barcode (satu barcode per baris)
                    </small>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-info" id="processFileInputBtn" disabled>
                            <i class="fas fa-play"></i> Proses File
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="clearFileInputBtn" disabled>
                            <i class="fas fa-times"></i> Bersihkan
                        </button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Data Scan: <span class="badge badge-primary" id="itemCount">0</span>
                            item(s)</h6>
                        <button type="button" class="btn btn-sm btn-warning" onclick="clearAllScanData()">
                            <i class="fas fa-trash-alt"></i> Clear All
                        </button>
                    </div>
                </div>

                <!-- Scan Table -->
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="thead-dark sticky-top">
                            <tr>
                                <th width="5%">#</th>
                                <th width="40%">Kode Barang</th>
                                <th width="20%">Berat</th>
                                <th width="20%">Panjang (MLC)</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="scanTableBody">
                            <tr id="emptyRow">
                                <td colspan="5" class="text-center text-muted">Belum ada data scan</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Instructions -->
                <div class="mt-3">
                    <small class="text-muted">
                        <strong>Petunjuk:</strong>
                        <ul class="mb-0">
                            <li>Scan barcode atau ketik manual dengan format: KODE;BERAT;Panjang (MLC)</li>
                            <li>Data akan otomatis diproses dan ditambahkan ke table</li>
                            <li>Tekan tombol X untuk menghapus input saat ini</li>
                            <li>Gunakan tombol Clear All untuk menghapus semua data</li>
                        </ul>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="processScanData()">
                    <i class="fas fa-save"></i> Simpan Data (<span id="footerItemCount">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // ========================================
    // GLOBAL VARIABLES
    // ========================================
    let scanDataStorage = [];
    let scanCounter = 0;
    let previousScanDataStorage = [];
    let processedDataStorage = [];
    let sentBarcodeHistory = []; // NEW: Array untuk menyimpan semua barcode yang sudah dikirim ke server
    let lastScanSource = 'manual'; // NEW: penanda sumber scan ('manual' | 'file')

    // ========================================
    // DOCUMENT READY INITIALIZATION
    // ========================================
    document.addEventListener('DOMContentLoaded', function() {
        // Setup global CSRF untuk AJAX (ambil dari meta atau hidden input form)
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const formToken = document.querySelector('#penerimaanForm input[name="_token"]')?.value;
        const csrfToken = metaToken || formToken || '';

        if (typeof $ !== 'undefined' && csrfToken) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }

        const tanggalInput = document.getElementById('tanggal');
        const stockOpnameInput = document.getElementById('no_perintah_opname');
        const scanBtn = document.getElementById('btn-scan');
        const errorContainer = document.getElementById('laravel-errors');

        if (scanBtn) {
            scanBtn.classList.add('hidden'); // Default hidden jika belum readonly
        }
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Setup dropdown functionality
        setupDropdown();

        // Setup scan modal functionality
        setupScanModal();

        // Initialize hasil scanning table
        initializeHasilScanningTable();

        // Setup save button
        setupSaveButton();

        // Add custom CSS for visual feedback
        addCustomStyles();

        // NEW: Initialize barcode history from localStorage (optional untuk persistence)
        initializeBarcodeHistory();

        setupFileUpload();

        // Setup clear scan button (TAMBAHAN BARU)
        setupClearScanButton();

        setupSaveHasilScanButton();

        setupLanjutButton();

        if (errorContainer) {
            // Cek untuk session 'error'
            const sessionError = errorContainer.dataset.error;
            if (sessionError) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: sessionError, // Ambil teks langsung dari atribut data
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }

            // Cek untuk error validasi
            const validationErrors = errorContainer.dataset.validationErrors;
            if (validationErrors) {
                // Ubah string JSON kembali menjadi array JavaScript
                const errorList = JSON.parse(validationErrors);
                let errorMessages = '';
                errorList.forEach(error => {
                    errorMessages += `<li>${error}</li>`;
                });

                Swal.fire({
                    icon: 'warning',
                    title: 'Validasi Gagal',
                    html: `<ul class="text-left list-disc list-inside">${errorMessages}</ul>`,
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        }
    });

    // ========================================
    // NEW: BARCODE HISTORY MANAGEMENT
    // ========================================
    function initializeBarcodeHistory() {
        // Optional: Load dari localStorage jika ingin persist antar session
        try {
            const savedHistory = localStorage.getItem('sentBarcodeHistory');
            if (savedHistory) {
                sentBarcodeHistory = JSON.parse(savedHistory);
                console.log('Loaded barcode history:', sentBarcodeHistory.length, 'items');
            }
        } catch (error) {
            console.log('Could not load barcode history from localStorage');
            sentBarcodeHistory = [];
        }
    }

    // ========================================
    // MODIFIED: LANJUT BUTTON AJAX HANDLER
    // ========================================
    function setupLanjutButton() {
        const lanjutBtn = document.getElementById('btn-lanjut');

        if (lanjutBtn) {
            lanjutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                handleLanjutSubmission();
            });
            console.log('Lanjut button AJAX handler initialized');
        }
    }

    // ========================================
    // NEW: HANDLE LANJUT FORM SUBMISSION WITH AJAX
    // ========================================
    function handleLanjutSubmission() {
        // Validate required fields
        const tanggal = document.getElementById('tanggal')?.value?.trim();
        const noPerintahOpname = document.getElementById('no_perintah_opname')?.value?.trim();
        const nop = document.getElementById('nop')?.value?.trim();

        if (!tanggal || !noPerintahOpname || !nop) {
            Swal.fire({
                title: 'Data Tidak Lengkap',
                text: 'Pastikan semua field wajib sudah terisi',
                icon: 'warning',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return;
        }

        // Show loading
        Swal.fire({
            title: 'Memproses...',
            text: 'Sedang memvalidasi data',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Disable button
        const lanjutBtn = document.getElementById('btn-lanjut');
        if (lanjutBtn) {
            lanjutBtn.disabled = true;
            lanjutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }

        // Send AJAX request
        $.ajax({
            url: '/hasil-stock-opname/barcode',
            method: 'POST',
            data: {
                tanggal: tanggal,
                stock_opname: noPerintahOpname,
                nop: nop,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            success: function(response) {
                console.log('Lanjut Response:', response);

                // Close loading
                Swal.close();

                if (response.success) {
                    // Update table with barang data
                    updateBarangTable(response.data.barang);

                    // Make form readonly
                    makeFormReadonly();

                    const scanBtn = document.getElementById('btn-scan');
                    if (scanBtn) {
                        scanBtn.classList.remove('hidden');
                    }

                    // Show success message
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil divalidasi dan barang dimuat',
                        icon: 'success',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });

                    // Store data for later use
                    window.currentStockOpnameData = response.data;

                } else {
                    Swal.fire({
                        title: 'Gagal',
                        text: response.message || 'Terjadi kesalahan saat memproses data',
                        icon: 'error',
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Lanjut Error:', xhr.responseText);

                // Close loading
                Swal.close();

                let errorMessage = 'Terjadi kesalahan saat memproses data';

                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    } else if (errorResponse.errors) {
                        const validationErrors = [];
                        Object.keys(errorResponse.errors).forEach(field => {
                            if (Array.isArray(errorResponse.errors[field])) {
                                validationErrors.push(...errorResponse.errors[field]);
                            }
                        });
                        errorMessage = validationErrors.join(', ');
                    }
                } catch (e) {
                    if (xhr.status === 422) {
                        errorMessage = 'Data tidak valid, periksa kembali input';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Terjadi kesalahan server';
                    }
                }

                Swal.fire({
                    title: 'Error',
                    text: errorMessage,
                    icon: 'error',
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            },
            complete: function() {
                // Re-enable button
                if (lanjutBtn) {
                    lanjutBtn.disabled = false;
                    lanjutBtn.innerHTML = 'Lanjut';
                }
            }
        });
    }

    // ========================================
    // NEW: UPDATE BARANG TABLE
    // ========================================
    function updateBarangTable(barangData) {
        const tableBody1 = document.getElementById('table-barang-body');
        const tableBody2 = document.getElementById('hasilScanningTbody');

        if (!tableBody1 || !tableBody2) {
            console.error('One or both table bodies not found');
            return;
        }

        // Clear existing rows in both tables
        tableBody1.innerHTML = '';
        tableBody2.innerHTML = '';

        if (!barangData || barangData.length === 0) {
            const emptyRow1 = document.createElement('tr');
            emptyRow1.innerHTML = `
            <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
            <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="4">Belum ada data</td>
        `;
            tableBody1.appendChild(emptyRow1);

            const emptyRow2 = document.createElement('tr');
            emptyRow2.innerHTML = `
            <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
            <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="3">Belum ada data</td>
        `;
            tableBody2.appendChild(emptyRow2);
            return;
        }

        // Populate table 1
        barangData.forEach((item) => {
            const row1 = document.createElement('tr');
            row1.innerHTML = `
            <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
            <td class="border border-gray-400 px-2 py-3 align-top">${item.item?.no || ''}</td>
            <td class="border border-gray-400 px-2 py-3 text-left align-top">${item.item?.name || ''}</td>
            <td class="border border-gray-200 px-2 py-3 align-top">${item.quantity || 0}</td>
            <td class="border border-gray-400 px-2 py-3 align-top">${item.itemUnit?.name || ''}</td>
        `;
            tableBody1.appendChild(row1);
        });

        // Populate table 2
        barangData.forEach((item) => {
            const row2 = document.createElement('tr');
            row2.innerHTML = `
            <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
            <td class="border border-gray-400 px-2 py-3 text-left align-top">${item.item?.name || ''}</td>
            <td class="border border-gray-400 px-2 py-3 text-center align-top">-</td>
            <td class="border border-gray-400 px-2 py-3 align-top">${item.itemUnit?.name || ''}</td>
        `;
            tableBody2.appendChild(row2);
        });

        console.log(`Updated both tables with ${barangData.length} items`);
    }

    // ========================================
    // NEW: MAKE FORM READONLY
    // ========================================
    function makeFormReadonly() {
        const tanggalInput = document.getElementById('tanggal');
        const stockOpnameInput = document.getElementById('no_perintah_opname');
        const nopInput = document.getElementById('nop');
        const lanjutBtn = document.getElementById('btn-lanjut');
        const searchBtn = document.getElementById('btn-show-dropdown');

        // Make inputs readonly
        if (tanggalInput) {
            tanggalInput.readOnly = true;
            tanggalInput.classList.add('bg-gray-100');
        }

        if (stockOpnameInput) {
            stockOpnameInput.readOnly = true;
            stockOpnameInput.classList.add('bg-gray-100');
        }

        if (nopInput) {
            nopInput.readOnly = true;
            nopInput.classList.add('bg-gray-100');
        }

        // Hide/disable buttons
        if (lanjutBtn) {
            lanjutBtn.style.display = 'none';
        }

        if (searchBtn) {
            searchBtn.disabled = true;
            searchBtn.classList.add('bg-gray-300', 'cursor-not-allowed');
        }
    }

    function setupClearScanButton() {
        const clearScanBtn = document.getElementById('btn-clear-scan');

        if (clearScanBtn) {
            clearScanBtn.addEventListener('click', function(e) {
                e.preventDefault();
                clearBarcodeHistoryAndTable();
            });
            console.log('Clear scan button initialized');
        } else {
            console.warn('Button btn-clear-scan tidak ditemukan');
        }
    }

    // ========================================
    // MODIFIKASI: FUNGSI CLEAR BARCODE HISTORY
    // ========================================
    function clearBarcodeHistoryAndTable() {
        Swal.fire({
            title: 'Konfirmasi Penghapusan',
            text: 'Apakah Anda yakin ingin menghapus semua riwayat barcode yang sudah dikirim? Ini akan memungkinkan input ulang barcode yang sama dan menghapus semua data di tabel hasil scanning.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                // 1. Clear barcode history
                sentBarcodeHistory = [];
                saveBarcodeHistory();

                // 2. Clear current scan data
                scanDataStorage = [];
                scanCounter = 0;

                // 3. Clear processed data storage
                processedDataStorage = [];
                previousScanDataStorage = [];

                // 4. Update scan table (modal table)
                updateScanTable();

                // 5. Clear hasil scanning table (main table)
                clearHasilScanningTable();

                // 6. Update counters
                updateItemCount();

                // 7. Reset visual feedback
                resetTableVisualFeedback();

                // 8. Show success message with SweetAlert
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Semua riwayat barcode dan data tabel berhasil dihapus',
                    icon: 'success',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });

                console.log('All data cleared successfully');
            }
        });
    }

    // ========================================
    // FUNGSI BARU: CLEAR HASIL SCANNING TABLE
    // ========================================
    function clearHasilScanningTable() {
        const hasilTbody = document.getElementById('hasilScanningTbody');

        if (hasilTbody) {
            const rows = hasilTbody.querySelectorAll('tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');

                // Reset kuantitas data ke "-" (kolom ke-3, index 2)
                if (cells.length >= 3) {
                    const kuantitasCell = cells[2]; // Kolom "Kuantitas Data"
                    kuantitasCell.textContent = '-';

                    // Remove any styling classes
                    kuantitasCell.classList.remove('updated-quantity');
                    kuantitasCell.style.backgroundColor = '';
                    kuantitasCell.style.color = '';
                    kuantitasCell.style.fontWeight = '';
                }

                // Remove row styling
                row.classList.remove('updated-row');
                row.style.backgroundColor = '';
            });

            console.log('Hasil scanning table cleared');
        } else {
            console.warn('Tabel hasil scanning tidak ditemukan');
        }
    }

    function saveBarcodeHistory() {
        // Optional: Save ke localStorage untuk persistence
        try {
            localStorage.setItem('sentBarcodeHistory', JSON.stringify(sentBarcodeHistory));
        } catch (error) {
            console.log('Could not save barcode history to localStorage');
        }
    }

    function addToSentHistory(barcodeArray) {
        // Tambahkan barcode baru ke history dengan timestamp
        const timestamp = new Date().toISOString();

        barcodeArray.forEach(barcode => {
            // Cek apakah barcode sudah ada di history
            const existingIndex = sentBarcodeHistory.findIndex(item => item.barcode === barcode);

            if (existingIndex >= 0) {
                // Update timestamp jika sudah ada
                sentBarcodeHistory[existingIndex].lastSent = timestamp;
                sentBarcodeHistory[existingIndex].count += 1;
            } else {
                // Tambah baru jika belum ada
                sentBarcodeHistory.push({
                    barcode: barcode,
                    firstSent: timestamp,
                    lastSent: timestamp,
                    count: 1
                });
            }
        });

        // Save to localStorage
        saveBarcodeHistory();

        console.log('Updated barcode history:', sentBarcodeHistory.length, 'unique barcodes');
    }

    function checkBarcodeInHistory(barcode) {
        return sentBarcodeHistory.some(item => item.barcode === barcode);
    }

    function getAllSentBarcodes() {
        return sentBarcodeHistory.map(item => item.barcode);
    }

    // ========================================
    // DROPDOWN FUNCTIONALITY
    // ========================================
    function setupDropdown() {
        const dropdown = document.getElementById('dropdown-stock-opname');
        const input = document.getElementById('stock_opname');
        const searchBtn = document.getElementById('btn-show-dropdown');
        const dropdownItems = dropdown ? dropdown.querySelectorAll('.dropdown-item') : [];

        // Tampilkan dropdown saat tombol search diklik
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                dropdown.classList.toggle('hidden');
            });
        }

        // Tutup dropdown jika klik di luar area
        document.addEventListener('click', function(event) {
            if (dropdown && searchBtn && !dropdown.contains(event.target) &&
                !searchBtn.contains(event.target) && event.target !== input) {
                dropdown.classList.add('hidden');
            }
        });

        // Filter dropdown saat mengetik
        if (input) {
            input.addEventListener('input', function() {
                const query = input.value.trim().toLowerCase();
                let hasMatch = false;

                dropdownItems.forEach(item => {
                    const value = item.dataset.value.toLowerCase();
                    const show = value.includes(query);
                    item.style.display = show ? 'block' : 'none';
                    if (show) hasMatch = true;
                });

                if (query.length > 0 && hasMatch) {
                    dropdown.classList.remove('hidden');
                } else {
                    dropdown.classList.add('hidden');
                }
            });
        }
    }

    // ========================================
    // SCAN MODAL FUNCTIONALITY
    // ========================================
    function setupScanModal() {
        const barcodeInput = document.getElementById('barcodeInput');
        const clearInputBtn = document.getElementById('clearInputBtn');

        // Focus pada input saat modal dibuka
        $('#scanModal').on('shown.bs.modal', function() {
            barcodeInput.focus();
            resetScanModal();
        });

        // Setup clear input button
        if (clearInputBtn) {
            clearInputBtn.addEventListener('click', function() {
                barcodeInput.value = '';
                barcodeInput.focus();
                showStatus('Input dikosongkan', 'info');
            });
        }

        // Setup barcode input event listeners
        if (barcodeInput) {
            // Handle Enter key untuk manual processing
            barcodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const rawInput = this.value.trim();

                    if (rawInput.length > 0) {
                        lastScanSource = 'manual';
                        processCompleteBarcode(rawInput);
                        this.value = ''; // Clear input
                        this.focus(); // Refocus
                    }
                }
            });

            // Handle paste event
            barcodeInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    const rawInput = this.value.trim();
                    if (rawInput.includes(';')) {
                        lastScanSource = 'manual';
                        processCompleteBarcode(rawInput);
                        this.value = ''; // Clear input
                        this.focus(); // Refocus
                    }
                }, 10);
            });
        }
    }

    // ========================================
    // BARCODE PROCESSING (MODIFIED)
    // ========================================
    function processCompleteBarcode(rawInput) {
        try {
            // Parse barcode data
            const parsedItem = parseBarcodeData(rawInput);

            if (parsedItem) {
                // MODIFIED: Cek duplikasi di current session
                const existingInCurrentSession = scanDataStorage.findIndex(item => item.kode === parsedItem.kode);

                if (existingInCurrentSession >= 0) {
                    showStatus(
                        `Item dengan barcode ${parsedItem.kode} sudah ada di session saat ini. Tidak bisa memasukkan barcode yang sama.`,
                        'error');
                    return;
                }

                // NEW: Cek apakah barcode sudah pernah dikirim ke server sebelumnya
                if (checkBarcodeInHistory(parsedItem.kode)) {
                    const historyItem = sentBarcodeHistory.find(item => item.barcode === parsedItem.kode);
                    const lastSentDate = new Date(historyItem.lastSent).toLocaleString('id-ID');

                    showStatus(
                        `Barcode ${parsedItem.kode} sudah pernah dikirim ke server sebelumnya pada ${lastSentDate}. ` +
                        `Tidak dapat menginput barcode yang sama lagi. (Total dikirim: ${historyItem.count}x)`,
                        'error'
                    );
                    return;
                }

                // Item baru dan belum pernah dikirim, tambahkan langsung
                addItemToStorage(parsedItem);
            }
        } catch (error) {
            console.error('Error processing barcode:', error);
            showStatus('Error memproses barcode: ' + error.message, 'error');
        }
    }

    function parseBarcodeData(rawInput) {
        try {
            // Trim whitespace dari seluruh input
            const cleanInput = rawInput.trim();

            // Validasi: Input tidak boleh kosong
            if (!cleanInput) {
                throw new Error('Input barcode tidak boleh kosong');
            }

            // Validasi: Hitung jumlah titik koma - harus tepat 2
            const semicolonCount = (cleanInput.match(/;/g) || []).length;
            if (semicolonCount !== 2) {
                throw new Error(
                    `Format tidak valid. Harus memiliki tepat 2 titik koma (;). Ditemukan ${semicolonCount} titik koma.`
                );
            }

            // Split by semicolon
            const parts = cleanInput.split(';');

            // Validate format: should have exactly 3 parts
            if (parts.length !== 3) {
                throw new Error('Format tidak valid. Gunakan format: KODE;BERAT;Panjang (MLC)');
            }

            // Trim whitespace from each part
            const kode = parts[0].trim();
            const beratStr = parts[1].trim();
            const yardStr = parts[2].trim();

            // Validate that none of the parts are empty
            if (!kode || !beratStr || !yardStr) {
                throw new Error('Salah satu bagian barcode kosong');
            }

            // Parse numeric values
            const berat = parseFloat(beratStr);
            const yard = parseFloat(yardStr);

            // Validate numeric values
            if (isNaN(berat) || isNaN(yard)) {
                throw new Error('Berat dan Panjang (MLC) harus berupa angka');
            }

            if (berat <= 0 || yard <= 0) {
                throw new Error('Berat dan Panjang (MLC) harus lebih besar dari 0');
            }

            return {
                kode: kode,
                berat: berat,
                yard: yard,
                beratFormatted: berat.toFixed(3),
                yardFormatted: yard.toFixed(3)
            };
        } catch (error) {
            throw error;
        }
    }

    function addItemToStorage(item, options = {}) {
        const {
            silent = false
        } = options;

        // Add to storage array
        scanCounter++;
        const itemWithId = {
            id: scanCounter,
            ...item,
            timestamp: new Date().toISOString()
        };

        scanDataStorage.push(itemWithId);

        if (!silent) {
            // Update table display
            updateScanTable();

            // Show success message
            showStatus(`Item ${item.kode} berhasil ditambahkan`, 'success');

            // Update counters
            updateItemCount();
        }
    }

    // ========================================
    // SCAN TABLE MANAGEMENT
    // ========================================
    function updateScanTable() {
        const tbody = document.getElementById('scanTableBody');
        const emptyRow = document.getElementById('emptyRow');

        if (!tbody) return;

        // Clear existing rows except empty row
        const existingRows = tbody.querySelectorAll('tr:not(#emptyRow)');
        existingRows.forEach(row => row.remove());

        if (scanDataStorage.length === 0) {
            if (emptyRow) emptyRow.style.display = 'table-row';
        } else {
            if (emptyRow) emptyRow.style.display = 'none';

            // Get barcode array untuk AJAX call
            const barcodeArray = scanDataStorage.map(item => item.kode);

            // Show loading state
            scanDataStorage.forEach((item, index) => {
                const row = document.createElement('tr');
                row.setAttribute('data-item-id', item.id);

                row.innerHTML = `
                <td>${index + 1}</td>
                <td class="text-left">${item.kode}</td>
                <td class="text-right">${item.beratFormatted}</td>
                <td class="text-right"><i class="fas fa-spinner fa-spin"></i> Loading...</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeItemFromStorage(${item.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

                tbody.appendChild(row);
            });

            // AJAX call: pilih endpoint berdasarkan sumber scan
            if (lastScanSource === 'file') {
                // Gunakan BULK untuk data dari file - kirim JSON agar tidak kena max_input_vars
                $.ajax({
                    url: '/hasil-stock-opname/barcode/bulk',
                    method: 'POST',
                    contentType: 'application/json; charset=utf-8',
                    data: JSON.stringify({
                        barcode: barcodeArray
                    }),
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || document.querySelector('#penerimaanForm input[name="_token"]').value
                    },
                    success: function(response) {
                        console.log('AJAX Response for updateScanTable (bulk):', response);

                        if (response.success && response.data && response.data.length > 0) {
                            const mapped = response.data.map(item => ({
                                total_panjang: item.total_panjang
                            }));
                            updateTableWithAjaxData(mapped);
                        } else {
                            updateTableWithFallbackData();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error in updateScanTable (bulk):', error);
                        updateTableWithFallbackData();
                    }
                });
            } else {
                // Gunakan INDIVIDUAL untuk input manual
                $.ajax({
                    url: '/hasil-stock-opname/barcode/individual',
                    method: 'POST',
                    data: {
                        barcode: barcodeArray
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || document.querySelector('#penerimaanForm input[name="_token"]').value
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('AJAX Response for updateScanTable (individual):', response);

                        if (response.success && response.data && response.data.length > 0) {
                            updateTableWithAjaxData(response.data);
                        } else {
                            updateTableWithFallbackData();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error in updateScanTable (individual):', error);
                        updateTableWithFallbackData();
                    }
                });
            }
        }
    }

    // Function helper untuk update table dengan data AJAX
    function updateTableWithAjaxData(ajaxData) {
        const tbody = document.getElementById('scanTableBody');
        if (!tbody) return;

        // Clear existing rows except empty row
        const existingRows = tbody.querySelectorAll('tr:not(#emptyRow)');
        existingRows.forEach(row => row.remove());

        // Rebuild table dengan data dari AJAX response
        scanDataStorage.forEach((item, index) => {
            const row = document.createElement('tr');
            row.setAttribute('data-item-id', item.id);

            // Ambil data panjang dari AJAX response berdasarkan index atau barcode
            let yardFormatted = 'N/A';
            if (ajaxData[index] && ajaxData[index].total_panjang) {
                yardFormatted = formatYard(ajaxData[index].total_panjang);
            }

            row.innerHTML = `
            <td>${index + 1}</td>
            <td class="text-left">${item.kode}</td>
            <td class="text-right">${item.beratFormatted}</td>
            <td class="text-right">${yardFormatted}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItemFromStorage(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

            tbody.appendChild(row);
        });
    }

    // Function helper untuk update table dengan data fallback
    function updateTableWithFallbackData() {
        const tbody = document.getElementById('scanTableBody');
        if (!tbody) return;

        // Clear existing rows except empty row
        const existingRows = tbody.querySelectorAll('tr:not(#emptyRow)');
        existingRows.forEach(row => row.remove());

        // Rebuild table dengan data fallback
        scanDataStorage.forEach((item, index) => {
            const row = document.createElement('tr');
            row.setAttribute('data-item-id', item.id);

            row.innerHTML = `
            <td>${index + 1}</td>
            <td class="text-left">${item.kode}</td>
            <td class="text-right">${item.beratFormatted}</td>
            <td class="text-right">${item.yardFormatted || 'N/A'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItemFromStorage(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

            tbody.appendChild(row);
        });
    }

    // Function helper untuk format yard
    function formatYard(panjang) {
        if (!panjang || panjang === 0) return '0.00';
        return parseFloat(panjang).toFixed(2);
    }

    function removeItemFromStorage(itemId) {
        // Remove from storage array
        scanDataStorage = scanDataStorage.filter(item => item.id !== itemId);

        // Update table display
        updateScanTable();

        // Update counters
        updateItemCount();

        showStatus('Item berhasil dihapus', 'info');
    }

    function clearAllScanData() {
        if (scanDataStorage.length === 0) {
            showStatus('Tidak ada data untuk dihapus', 'info');
            return;
        }

        // Clear storage
        scanDataStorage = [];
        scanCounter = 0;

        // Update table display
        updateScanTable();

        // Update counters
        updateItemCount();

        showStatus('Semua data berhasil dihapus', 'info');
    }

    // ========================================
    // HASIL SCANNING TABLE MANAGEMENT (NEW)
    // ========================================
    function initializeHasilScanningTable() {
        // Set ID untuk tabel hasil scanning jika belum ada
        let hasilTbody = document.getElementById('hasilScanningTbody');

        if (!hasilTbody) {
            // Cari tabel berdasarkan struktur yang ada
            const tables = document.querySelectorAll('table');

            for (let table of tables) {
                const headers = table.querySelectorAll('thead th');
                let isTargetTable = false;

                // Cek apakah tabel memiliki header "Nama Barang" dan "Kuantitas Data"
                for (let header of headers) {
                    if (header.textContent.includes('Nama Barang') &&
                        table.querySelector('th')?.textContent.includes('Kuantitas Data')) {
                        isTargetTable = true;
                        break;
                    }
                }

                if (isTargetTable) {
                    const tbody = table.querySelector('tbody');
                    if (tbody) {
                        tbody.id = 'hasilScanningTbody';
                        table.id = 'hasilScanningTable';
                        console.log('Hasil Scanning Table initialized with ID');
                        break;
                    }
                }
            }
        }
    }

    // ========================================
    // TABLE UPDATE FUNCTIONS (CORE FUNCTIONALITY)
    // ========================================

    // METHOD 2: Update dengan akumulasi (untuk multiple scan sessions)
    function updateHasilScanningTableWithAccumulation(responseData) {
        console.log('Updating hasil scanning table with accumulation:', responseData);

        const hasilTbody = document.getElementById('hasilScanningTbody');
        if (!hasilTbody) {
            console.error('Tabel Hasil Scanning tidak ditemukan');
            return;
        }

        if (!responseData || responseData.length === 0) {
            console.log('No data to update');
            return;
        }

        // Reset visual feedback
        resetTableVisualFeedback();

        // Buat mapping dari response data - KONVERSI KE NUMBER tapi pertahankan format 2 desimal
        const responseDataMap = {};
        responseData.forEach(item => {
            const namaBarang = getItemName(item);
            let kuantitas = getItemQuantity(item);

            // Pastikan kuantitas sebagai number dengan 2 desimal
            const kuantitasNumber = typeof kuantitas === 'string' ?
                parseFloat(kuantitas) : // Parse to float
                Number(kuantitas);

            // Simpan sebagai number dengan presisi 2 desimal
            responseDataMap[namaBarang] = parseFloat(kuantitasNumber.toFixed(2));

            console.log(`Mapped: ${namaBarang} = ${responseDataMap[namaBarang]} (type: ${typeof responseDataMap[namaBarang]})`);
        });

        const rows = hasilTbody.querySelectorAll('tr');
        let updatedCount = 0;

        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('td');

            if (cells.length >= 2) {
                const iconCell = cells[0];
                const namaBarangCell = cells[1];
                const kuantitasCell = cells[2];
                const satuanCell = cells[3];

                const namaBarangInTable = namaBarangCell.textContent.trim();

                if (responseDataMap.hasOwnProperty(namaBarangInTable)) {
                    // Ambil kuantitas yang sudah ada (jika bukan "-")
                    const currentQuantity = kuantitasCell.textContent.trim();
                    let existingQuantity = 0;

                    if (currentQuantity !== "-" && !isNaN(parseFloat(currentQuantity))) {
                        existingQuantity = parseFloat(currentQuantity);
                    }

                    // Tambahkan dengan kuantitas baru (SUDAH NUMBER)
                    const newQuantity = existingQuantity + responseDataMap[namaBarangInTable];

                    // Format dengan 2 desimal tanpa leading zero, tapi pertahankan .00 jika perlu
                    let formattedQuantity = newQuantity.toFixed(2);
                    // Hilangkan leading zero hanya di depan angka (bukan di belakang koma)
                    formattedQuantity = formattedQuantity.replace(/^0+(?=\d)/, '');

                    kuantitasCell.textContent = formattedQuantity;
                    kuantitasCell.classList.add('updated-quantity');
                    row.classList.add('updated-row');

                    updatedCount++;
                    console.log(
                        `Accumulated ${namaBarangInTable}: ${existingQuantity} + ${responseDataMap[namaBarangInTable]} = ${newQuantity} -> ${formattedQuantity}`
                    );
                }
            }
        });

        if (updatedCount > 0) {
            showStatus(`${updatedCount} item berhasil diupdate dengan akumulasi`, 'success');
            setTimeout(() => {
                applyVisualFeedback();
            }, 100);
        }
    }

    // ========================================
    // VISUAL FEEDBACK FUNCTIONS
    // ========================================
    function applyVisualFeedback() {
        const updatedCells = document.querySelectorAll('.updated-quantity');
        const updatedRows = document.querySelectorAll('.updated-row');

        updatedCells.forEach(cell => {
            cell.style.backgroundColor = '#d4edda';
            cell.style.color = '#155724';
            cell.style.fontWeight = 'bold';
            cell.style.transition = 'all 0.3s ease';
        });

        updatedRows.forEach(row => {
            row.style.backgroundColor = '#f8f9fa';
            row.style.transition = 'all 0.3s ease';
        });

        // Auto remove highlighting after 5 seconds
        setTimeout(() => {
            resetTableVisualFeedback();
        }, 5000);
    }

    function resetTableVisualFeedback() {
        const updatedCells = document.querySelectorAll('.updated-quantity');
        const updatedRows = document.querySelectorAll('.updated-row');

        updatedCells.forEach(cell => {
            cell.classList.remove('updated-quantity');
            cell.style.backgroundColor = '';
            cell.style.color = '';
            cell.style.fontWeight = '';
            cell.style.transition = '';
        });

        updatedRows.forEach(row => {
            row.classList.remove('updated-row');
            row.style.backgroundColor = '';
            row.style.transition = '';
        });
    }

    function addCustomStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .updated-quantity {
                background-color: #d4edda !important;
                color: #155724 !important;
                font-weight: bold !important;
                transition: all 0.3s ease !important;
            }
            
            .updated-row {
                background-color: #f8f9fa !important;
                transition: all 0.3s ease !important;
            }
            
            .fade-in {
                animation: fadeIn 0.5s ease-in;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }

    // ========================================
    // HELPER FUNCTIONS
    // ========================================
    function getItemName(item) {
        if (item.item && item.item.name) {
            return item.item.name;
        } else if (item.nama) {
            return item.nama;
        } else if (item.name) {
            return item.name;
        } else {
            return item.kode || 'Unknown Item';
        }
    }

    function getItemQuantity(item) {
        return item.quantity || item.total_panjang || item.kuantitas || 0;
    }

    function getItemIdentifier(item) {
        return getItemName(item);
    }

    // ========================================
    // AJAX PROCESSING (MODIFIED)
    // ========================================
    function processScanData() {
        if (scanDataStorage.length === 0) {
            showStatus('Tidak ada data untuk diproses', 'warning');
            return;
        }

        // Show loading status
        showStatus('Sedang memproses data...', 'info');

        // Disable save button to prevent double submission
        const saveButton = document.querySelector('#scanModal .btn-primary');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }

        // Get barcode array only (kode field from scan data)
        const barcodeArray = scanDataStorage.map(item => item.kode);

        console.log('Sending barcode array:', barcodeArray);
        console.log('Current barcode history:', sentBarcodeHistory.length, 'items');

        // Send AJAX request
        $.ajax({
            url: '/hasil-stock-opname/barcode/match',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            data: JSON.stringify({
                barcode: barcodeArray
            }),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || document.querySelector('#penerimaanForm input[name="_token"]').value
            },
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Response:', response);

                if (!response.success) {
                    showStatus(response.message || 'Barcode tidak ditemukan.', 'error');
                    return;
                }

                // MODIFIED: Tambahkan barcode ke history setelah sukses dikirim
                addToSentHistory(barcodeArray);

                // NEW: Simpan processed data untuk pengiriman ke server nanti
                if (response.data && response.data.length > 0) {
                    // Update processedDataStorage dengan data yang baru diproses
                    processedDataStorage = [...processedDataStorage, ...response.data];

                    // Simpan juga scanDataStorage yang baru saja diproses ke previousScanDataStorage
                    previousScanDataStorage = [...scanDataStorage];

                    console.log('Updated processedDataStorage:', processedDataStorage.length, 'items');
                    console.log('Updated previousScanDataStorage:', previousScanDataStorage.length, 'items');
                }

                // Show success message
                showStatus(`Data berhasil diproses. ${barcodeArray.length} barcode ditambahkan ke riwayat.`,
                    'success');

                // Update the scanning table with matched data
                if (response.success && response.data) {
                    // Method 2: Accumulate quantities
                    updateHasilScanningTableWithAccumulation(response.data);
                } else {
                    console.log('No data in response');
                    showStatus('Tidak ada data yang cocok ditemukan', 'warning');
                }

                // Optional: Clear scan data after successful save
                clearAllScanData();

                // Close modal after short delay
                setTimeout(() => {
                    $('#scanModal').modal('hide');
                }, 2000);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:');
                console.error('Status:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                console.error('Error:', error);

                let errorMessage = 'Error memproses data';

                try {
                    const errorResponse = JSON.parse(xhr.responseText);

                    if (errorResponse.errors && errorResponse.errors['barcode.*']) {
                        errorMessage = errorResponse.errors['barcode.*'][0];
                    } else if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    } else {
                        errorMessage = 'Terjadi kesalahan, coba lagi.';
                    }
                } catch (e) {
                    if (xhr.status === 422) {
                        errorMessage = 'Data tidak valid, periksa kembali barcode';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Terjadi kesalahan server';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Tidak dapat terhubung ke server';
                    } else {
                        errorMessage = `HTTP Error ${xhr.status}: ${xhr.statusText}`;
                    }
                }

                showStatus(errorMessage, 'error');

                // MODIFIED: Jangan tambahkan ke history jika error
                console.log('Data not added to history due to error');
            },
            complete: function() {
                // Re-enable save button
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML =
                        '<i class="fas fa-save"></i> Simpan Data (<span id="footerItemCount">' +
                        scanDataStorage.length + '</span>)';
                }
            }
        });
    }

    // ========================================
    // UTILITY FUNCTIONS
    // ========================================
    function updateItemCount() {
        const count = scanDataStorage.length;
        const itemCountElement = document.getElementById('itemCount');
        const footerItemCountElement = document.getElementById('footerItemCount');

        if (itemCountElement) {
            itemCountElement.textContent = count;
        }

        if (footerItemCountElement) {
            footerItemCountElement.textContent = count;
        }
    }

    function showStatus(message, type = 'info') {
        const statusAlert = document.getElementById('statusAlert');
        const statusMessage = document.getElementById('statusMessage');

        if (!statusAlert || !statusMessage) {
            // Fallback to console if status elements don't exist
            console.log(`Status [${type}]: ${message}`);
            return;
        }

        // Set alert class based on type
        statusAlert.className = 'alert ';
        switch (type) {
            case 'success':
                statusAlert.className += 'alert-success';
                break;
            case 'error':
            case 'danger':
                statusAlert.className += 'alert-danger';
                break;
            case 'warning':
                statusAlert.className += 'alert-warning';
                break;
            default:
                statusAlert.className += 'alert-info';
        }

        // Set message and show
        statusMessage.textContent = message;
        statusAlert.style.display = 'block';
        statusAlert.classList.add('fade-in');

        // Auto-hide after 3 seconds
        setTimeout(() => {
            statusAlert.style.display = 'none';
            statusAlert.classList.remove('fade-in');
        }, 3000);
    }

    function resetScanModal() {
        // Clear input
        const barcodeInput = document.getElementById('barcodeInput');
        if (barcodeInput) {
            barcodeInput.value = '';
        }

        // Hide status
        const statusAlert = document.getElementById('statusAlert');
        if (statusAlert) {
            statusAlert.style.display = 'none';
        }

        // Update counters
        updateItemCount();
    }

    function getScanData() {
        // Return current scan data (useful for external access)
        return scanDataStorage.map(item => ({
            kode: item.kode,
            berat: item.berat,
            yard: item.yard
        }));
    }

    function getBarcodeArray() {
        return scanDataStorage.map(item => item.kode);
    }

    function getUpdateSummary() {
        const hasilTbody = document.getElementById('hasilScanningTbody');
        if (!hasilTbody) return {};

        const summary = {};
        const rows = hasilTbody.querySelectorAll('tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 2) {
                const namaBarang = cells[0].textContent.trim();
                const kuantitas = cells[1].textContent.trim();

                if (kuantitas !== '-' && !isNaN(parseFloat(kuantitas))) {
                    summary[namaBarang] = parseFloat(kuantitas);
                }
            }
        });

        return summary;
    }

    // ========================================
    // SETUP FUNCTIONS
    // ========================================
    function setupSaveButton() {
        // Setup save button click event
        $(document).on('click', '#scanModal .btn-primary', function(e) {
            e.preventDefault();
            processScanData();
        });
    }

    // Clear processed data function
    function clearAllProcessedData() {
        if (confirm('Apakah Anda yakin ingin menghapus semua data hasil scanning?')) {
            // Reset semua kuantitas ke "-"
            const hasilTbody = document.getElementById('hasilScanningTbody');
            if (hasilTbody) {
                const rows = hasilTbody.querySelectorAll('tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 2) {
                        cells[1].textContent = '-'; // Reset kuantitas ke "-"
                    }
                });
            }

            processedDataStorage = [];
            previousScanDataStorage = [];
            resetTableVisualFeedback();
            showStatus('Semua data hasil scanning berhasil dihapus', 'info');
        }
    }

    // ========================================
    // SETUP SAVE HASIL SCAN BUTTON (NEW)
    // ========================================
    function setupSaveHasilScanButton() {
        const saveHasilScanBtn = document.getElementById('btn-save-hasil-scan');

        if (saveHasilScanBtn) {
            saveHasilScanBtn.addEventListener('click', function(e) {
                e.preventDefault();
                saveHasilScanToServer();
            });
            console.log('Save Hasil Scan button initialized');
        } else {
            console.warn('Button btn-save-hasil-scan tidak ditemukan');
        }
    }

    // ========================================
    // SAVE HASIL SCAN TO SERVER (NEW)
    // ========================================
    function saveHasilScanToServer() {
        // Collect data yang diperlukan
        const nop = document.getElementById('nop')?.value?.trim() || '';
        const tanggal = document.getElementById('tanggal')?.value?.trim() || '';
        const noPerintahOpname = document.getElementById('no_perintah_opname')?.value?.trim() || '';

        // Validate required fields
        if (!nop || !tanggal || !noPerintahOpname) {
            Swal.fire({
                title: 'Data Tidak Lengkap',
                text: 'Pastikan semua field (NOP, Tanggal, Stock Opname) sudah terisi',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // NEW: Validate kuantitas between both tables
        const validationResult = validateQuantitiesBetweenTables();

        if (!validationResult.isValid) {
            // Show error message with details
            showQuantityValidationError(validationResult.errors);
            return; // Stop execution, don't proceed to save
        }

        // Collect scan data from hasil scanning table
        const scanBarangArray = collectScanBarangArray();

        if (scanBarangArray.length === 0) {
            Swal.fire({
                title: 'Tidak Ada Data Scan',
                text: 'Belum ada data hasil scanning untuk disimpan',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // NEW: Collect barcode information
        const barcodeArray = collectBarcodeArrayFromHistory();
        const barcodeInfo = barcodeArray.length > 0 ?
            `<p><strong>Total Barcode:</strong> ${barcodeArray.length} barcode</p>` :
            `<p class="text-orange-600"><strong>⚠ Belum ada barcode yang di-scan</strong></p>`;

        // Show confirmation dialog
        Swal.fire({
            title: 'Konfirmasi Penyimpanan',
            html: `
            <div class="text-left">
                <p><strong>NOP:</strong> ${nop}</p>
                <p><strong>Tanggal:</strong> ${tanggal}</p>
                <p><strong>Nomor Perintah Opname:</strong> ${noPerintahOpname}</p>
                <p><strong>Total Item Scan:</strong> ${scanBarangArray.length} barang</p>
                ${barcodeInfo}
                <p class="text-green-600 mt-2"><strong>✓ Validasi kuantitas berhasil</strong></p>
            </div>
        `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Simpan Data',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                performSaveWithFormSubmission(nop, tanggal, noPerintahOpname, scanBarangArray);
            }
        });
    }

    // ========================================
    // NEW: SHOW QUANTITY VALIDATION ERROR
    // ========================================
    function showQuantityValidationError(errors) {
        const errorList = errors.map(error => `<li class="text-left">${error}</li>`).join('');

        Swal.fire({
            title: 'Validasi Kuantitas Gagal',
            html: `
            <div class="text-left">
                <p class="mb-3 font-semibold text-red-600">Kuantitas antara kedua tabel tidak sesuai:</p>
                <ul class="space-y-1 text-sm max-h-60 overflow-y-auto">
                    ${errorList}
                </ul>
                <p class="mt-4 text-sm text-gray-600">
                    <strong>Pastikan:</strong><br>
                    • Semua barang yang diharapkan sudah discan<br>
                    • Kuantitas hasil scan sesuai dengan yang diharapkan<br>
                    • Tidak ada barang extra yang tidak seharusnya ada
                </p>
            </div>
        `,
            icon: 'error',
            confirmButtonText: 'OK, Periksa Kembali',
            confirmButtonColor: '#d33',
            customClass: {
                popup: 'swal-wide'
            }
        });

        // Add custom CSS for wider popup if not exists
        if (!document.getElementById('swal-custom-style')) {
            const style = document.createElement('style');
            style.id = 'swal-custom-style';
            style.textContent = `
            .swal-wide {
                width: 600px !important;
                max-width: 90vw !important;
            }
        `;
            document.head.appendChild(style);
        }
    }

    // ========================================
    // NEW: VALIDATE QUANTITIES BETWEEN TABLES
    // ========================================
    function validateQuantitiesBetweenTables() {
        const barangTableBody = document.getElementById('table-barang-body');
        const hasilScanTableBody = document.getElementById('hasilScanningTbody');

        if (!barangTableBody || !hasilScanTableBody) {
            return {
                isValid: false,
                errors: ['Tabel tidak ditemukan. Pastikan kedua tabel sudah dimuat dengan benar.']
            };
        }

        // Get data from first table (Expected quantities)
        const expectedQuantities = getExpectedQuantitiesFromBarangTable();

        // Get data from second table (Scanned quantities)
        const scannedQuantities = getScannedQuantitiesFromHasilTable();

        console.log('Expected quantities:', expectedQuantities);
        console.log('Scanned quantities:', scannedQuantities);

        const errors = [];
        const mismatches = [];

        // Check each item in expected quantities
        Object.keys(expectedQuantities).forEach(itemName => {
            const expected = expectedQuantities[itemName];
            const scanned = scannedQuantities[itemName] || 0;

            if (expected !== scanned) {
                mismatches.push({
                    itemName: itemName,
                    expected: expected,
                    scanned: scanned,
                    difference: scanned - expected
                });
            }
        });

        // Check for items that were scanned but not expected
        Object.keys(scannedQuantities).forEach(itemName => {
            if (!expectedQuantities.hasOwnProperty(itemName) && scannedQuantities[itemName] > 0) {
                mismatches.push({
                    itemName: itemName,
                    expected: 0,
                    scanned: scannedQuantities[itemName],
                    difference: scannedQuantities[itemName],
                    isExtra: true
                });
            }
        });

        if (mismatches.length > 0) {
            errors.push(`Ditemukan ${mismatches.length} item dengan kuantitas tidak sesuai:`);

            mismatches.forEach(mismatch => {
                if (mismatch.isExtra) {
                    errors.push(
                        `• ${mismatch.itemName}: Tidak ada di daftar barang, tapi terscan ${mismatch.scanned}`
                    );
                } else if (mismatch.scanned === 0) {
                    errors.push(`• ${mismatch.itemName}: Diharapkan ${mismatch.expected}, belum discan (0)`);
                } else if (mismatch.scanned < mismatch.expected) {
                    errors.push(
                        `• ${mismatch.itemName}: Diharapkan ${mismatch.expected}, terscan ${mismatch.scanned} (kurang ${Math.abs(mismatch.difference)})`
                    );
                } else {
                    errors.push(
                        `• ${mismatch.itemName}: Diharapkan ${mismatch.expected}, terscan ${mismatch.scanned} (lebih ${mismatch.difference})`
                    );
                }
            });
        }

        return {
            isValid: errors.length === 0,
            errors: errors,
            mismatches: mismatches
        };
    }

    // ========================================
    // NEW: GET EXPECTED QUANTITIES FROM BARANG TABLE
    // ========================================
    function getExpectedQuantitiesFromBarangTable() {
        const tableBody = document.getElementById('table-barang-body');
        const quantities = {};

        if (!tableBody) {
            console.error('Table barang body not found');
            return quantities;
        }

        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');

            // Skip if not enough cells or if it's empty row
            if (cells.length < 4) return;

            const namaBarang = cells[2]?.textContent?.trim(); // Column index 2 for item name
            const kuantitasText = cells[3]?.textContent?.trim(); // Column index 3 for quantity

            // Skip if nama barang is placeholder or empty
            if (!namaBarang || namaBarang === 'Belum ada data' || namaBarang === '-') {
                return;
            }

            // Parse quantity
            const quantity = parseFloat(kuantitasText) || 0;
            quantities[namaBarang] = quantity;
        });

        return quantities;
    }

    // ========================================
    // NEW: GET SCANNED QUANTITIES FROM HASIL TABLE
    // ========================================
    function getScannedQuantitiesFromHasilTable() {
        const tableBody = document.getElementById('hasilScanningTbody');
        const quantities = {};

        if (!tableBody) {
            console.error('Hasil scan table body not found');
            return quantities;
        }

        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');

            // Skip if not enough cells
            if (cells.length < 4) return;

            const namaBarang = cells[1]?.textContent?.trim(); // Column index 1 for item name
            const kuantitasText = cells[2]?.textContent?.trim(); // Column index 2 for quantity

            // Skip if nama barang is placeholder or empty
            if (!namaBarang || namaBarang === 'Belum ada data' || namaBarang === '-') {
                return;
            }

            // Parse quantity: if it's "-" or empty, use 0; otherwise parse as float
            let quantity = 0;
            if (kuantitasText && kuantitasText !== '-' && kuantitasText !== '') {
                const parsedQuantity = parseFloat(kuantitasText);
                quantity = isNaN(parsedQuantity) ? 0 : parsedQuantity;
            }

            quantities[namaBarang] = quantity;
        });

        return quantities;
    }

    // ========================================
    // COLLECT SCAN BARANG ARRAY (NEW)
    // ========================================
    function collectScanBarangArray() {
        const hasilTbody = document.getElementById('hasilScanningTbody');
        const scanBarangArray = [];

        if (!hasilTbody) {
            console.error('Tabel Hasil Scanning tidak ditemukan');
            return scanBarangArray;
        }

        const rows = hasilTbody.querySelectorAll('tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');

            // Skip if not enough cells
            if (cells.length < 4) return;

            const namaBarang = cells[1]?.textContent?.trim();
            const kuantitasText = cells[2]?.textContent?.trim();

            // Skip if nama barang is placeholder or empty
            if (!namaBarang || namaBarang === 'Belum ada data' || namaBarang === '-') {
                return;
            }

            // Convert quantity: if it's "-" or empty, use 0; otherwise parse as float
            let quantity = 0;
            if (kuantitasText && kuantitasText !== '-' && kuantitasText !== '') {
                const parsedQuantity = parseFloat(kuantitasText);
                quantity = isNaN(parsedQuantity) ? 0 : parsedQuantity;
            }

            // Only add items that have been scanned (quantity > 0)
            if (quantity > 0) {
                scanBarangArray.push({
                    nama_barang: namaBarang,
                    quantity: quantity,
                });
            }
        });

        console.log('Collected scan barang array:', scanBarangArray);
        return scanBarangArray;
    }

    // ========================================
    // COLLECT BARCODE ARRAY FROM SENT HISTORY (NEW)
    // ========================================
    function collectBarcodeArrayFromHistory() {
        // Mengumpulkan semua barcode yang sudah dikirim ke server (dari sentBarcodeHistory)
        const barcodeArray = sentBarcodeHistory.map(item => item.barcode);
        console.log('Collected barcode array from history:', barcodeArray);
        return barcodeArray;
    }

    // ========================================
    // COLLECT CURRENT SESSION BARCODE ARRAY (NEW)
    // ========================================
    function collectCurrentSessionBarcodeArray() {
        // Mengumpulkan barcode dari processedDataStorage atau dari data yang baru saja diproses
        const currentSessionBarcodes = [];

        // Ambil dari processedDataStorage jika ada
        if (processedDataStorage && processedDataStorage.length > 0) {
            processedDataStorage.forEach(item => {
                if (item.kode || item.barcode) {
                    currentSessionBarcodes.push(item.kode || item.barcode);
                }
            });
        }

        // Jika tidak ada di processedDataStorage, ambil dari scanDataStorage yang terakhir diproses
        if (currentSessionBarcodes.length === 0 && previousScanDataStorage && previousScanDataStorage.length > 0) {
            previousScanDataStorage.forEach(item => {
                if (item.kode) {
                    currentSessionBarcodes.push(item.kode);
                }
            });
        }

        console.log('Collected current session barcode array:', currentSessionBarcodes);
        return currentSessionBarcodes;
    }

    // ========================================
    // COLLECT HASIL SCANNING DATA (NEW)
    // ========================================
    function collectHasilScanningData() {
        const hasilTbody = document.getElementById('hasilScanningTbody');
        const scanBarangData = [];
        if (!hasilTbody) {
            console.error('Tabel Hasil Scanning tidak ditemukan');
            return scanBarangData;
        }
        const rows = hasilTbody.querySelectorAll('tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            // Skip if not enough cells
            if (cells.length < 4) return;
            const namaBarang = cells[1]?.textContent?.trim();
            const kuantitasText = cells[2]?.textContent?.trim();

            // Skip only if nama barang is the placeholder text
            if (namaBarang === 'Belum ada data') {
                return;
            }

            let quantity = 0;
            // Convert quantity: if it's "-" or empty string, use 0; otherwise parse as float
            if (kuantitasText === '-' || kuantitasText === '') {
                quantity = 0;
            } else {
                const parsedQuantity = parseFloat(kuantitasText);
                quantity = isNaN(parsedQuantity) ? 0 : parsedQuantity;
            }

            scanBarangData.push({
                nama_barang: namaBarang,
                quantity: quantity
            });
        });
        console.log('Collected scan data:', scanBarangData);
        return scanBarangData;
    }

    function setupFileUpload() {
        const fileInput = document.getElementById('barcodeFileInput');
        const fileLabel = document.getElementById('barcodeFileLabel');
        const processBtn = document.getElementById('processFileInputBtn');
        const clearBtn = document.getElementById('clearFileInputBtn');

        if (fileInput && fileLabel) {
            // Update label dengan nama file yang dipilih
            fileInput.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'Pilih file...';
                fileLabel.textContent = fileName;

                // Enable/disable buttons berdasarkan ada/tidaknya file
                const hasFile = !!e.target.files[0];
                processBtn.disabled = !hasFile;
                clearBtn.disabled = !hasFile;
            });
        }

        // Setup process button
        if (processBtn) {
            processBtn.addEventListener('click', function() {
                processBarcodeFile();
            });
        }

        // Setup clear button
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                clearFileInput();
            });
        }
    }

    function processBarcodeFile() {
        const fileInput = document.getElementById('barcodeFileInput');
        const file = fileInput.files[0];

        if (!file) {
            showStatus('Tidak ada file yang dipilih', 'warning');
            return;
        }

        // Validasi ekstensi file
        if (!file.name.toLowerCase().endsWith('.txt')) {
            showStatus('Hanya file .txt yang diperbolehkan', 'error');
            return;
        }

        // Show loading
        showStatus('Memproses file...', 'info');

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const content = e.target.result;
                processBarcodeFileContent(content);
            } catch (error) {
                console.error('Error processing file:', error);
                showStatus('Error memproses file: ' + error.message, 'error');
            }
        };

        reader.onerror = function() {
            showStatus('Error membaca file', 'error');
        };

        reader.readAsText(file);
    }

    function processBarcodeFileContent(content) {
        // Split content by lines
        const lines = content.split(/\r?\n/).filter(line => line.trim().length > 0);

        if (lines.length === 0) {
            showStatus('File kosong atau tidak berisi data', 'warning');
            return;
        }

        let processedCount = 0;
        let errorCount = 0;
        let duplicateInSessionCount = 0;
        let duplicateInHistoryCount = 0;

        // Tandai sumber sebagai file sebelum proses
        lastScanSource = 'file';

        // Process each line
        lines.forEach((line, index) => {
            try {
                const rawInput = line.trim();

                if (rawInput.length === 0) return;

                // Parse barcode data
                const parsedItem = parseBarcodeData(rawInput);

                if (parsedItem) {
                    // Cek duplikasi di current session
                    const existingInCurrentSession = scanDataStorage.findIndex(item => item.kode === parsedItem.kode);

                    if (existingInCurrentSession >= 0) {
                        duplicateInSessionCount++;
                        return;
                    }

                    // Cek apakah barcode sudah pernah dikirim ke server sebelumnya
                    if (checkBarcodeInHistory(parsedItem.kode)) {
                        duplicateInHistoryCount++;
                        return;
                    }

                    // Item baru dan belum pernah dikirim, tambahkan silent (tunda render)
                    addItemToStorage(parsedItem, {
                        silent: true
                    });
                    processedCount++;
                }
            } catch (error) {
                console.error(`Error processing line ${index + 1}:`, error);
                errorCount++;
            }
        });

        // Setelah loop selesai, render sekali dan update counter
        updateScanTable();
        updateItemCount();

        // Show summary
        let summaryMessage = `File diproses: ${processedCount} barcode berhasil ditambahkan`;

        if (errorCount > 0) {
            summaryMessage += `, ${errorCount} error`;
        }

        if (duplicateInSessionCount > 0) {
            summaryMessage += `, ${duplicateInSessionCount} duplikat di session ini`;
        }

        if (duplicateInHistoryCount > 0) {
            summaryMessage += `, ${duplicateInHistoryCount} sudah pernah dikirim sebelumnya`;
        }

        showStatus(summaryMessage, 'success');

        // Clear file input setelah diproses
        clearFileInput();
    }

    function clearFileInput() {
        const fileInput = document.getElementById('barcodeFileInput');
        const fileLabel = document.getElementById('barcodeFileLabel');
        const processBtn = document.getElementById('processFileInputBtn');
        const clearBtn = document.getElementById('clearFileInputBtn');

        if (fileInput) fileInput.value = '';
        if (fileLabel) fileLabel.textContent = 'Pilih file...';
        if (processBtn) processBtn.disabled = true;
        if (clearBtn) clearBtn.disabled = true;
    }

    // ========================================
    // ALTERNATIVE: SAVE WITH FORM SUBMISSION (FALLBACK)
    // ========================================
    function performSaveWithFormSubmission(nop, tanggal, noPerintahOpname, scanBarangArray) {
        const form = document.getElementById('penerimaanForm');

        // Bersihkan input hasil scan lama (jika ada)
        const oldInputs = form.querySelectorAll('[data-dynamic]');
        oldInputs.forEach(el => el.remove());

        // Convert scan_barang ke JSON string
        const scanBarangJson = JSON.stringify(scanBarangArray);
        const scanInput = document.createElement('input');
        scanInput.type = 'hidden';
        scanInput.name = 'scan_barang_json';
        scanInput.value = scanBarangJson;
        form.appendChild(scanInput);

        // Convert barcodes ke JSON string (HANYA 1 VARIABLE!)
        const barcodeArray = collectBarcodeArrayFromHistory();
        const barcodesJson = JSON.stringify(barcodeArray);
        const barcodeInput = document.createElement('input');
        barcodeInput.type = 'hidden';
        barcodeInput.name = 'barcodes_json';
        barcodeInput.value = barcodesJson;
        form.appendChild(barcodeInput);

        // Log data yang akan dikirim
        console.log('Data yang akan dikirim ke server:', {
            nop: nop,
            tanggal: tanggal,
            no_perintah_opname: noPerintahOpname,
            scan_barang_count: scanBarangArray.length,
            barcodes_count: barcodeArray.length
        });

        // Atur form_submitted menjadi 1 sebelum submit
        const formSubmittedInput = document.getElementById('form_submitted');
        if (formSubmittedInput) {
            formSubmittedInput.value = '1';
        }

        // Tampilkan loading dengan informasi tambahan
        Swal.fire({
            title: 'Menyimpan Data...',
            html: `
                <p>Mohon tunggu, sedang memproses data</p>
                <div class="text-sm text-gray-600 mt-2">
                    <p>• ${scanBarangArray.length} item barang</p>
                    <p>• ${barcodeArray.length} barcode</p>
                </div>
            `,
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Submit form setelah delay kecil agar loading sempat tampil
        setTimeout(() => form.submit(), 500);
    }

    // NEW: Function untuk mendapatkan informasi barcode history
    function getBarcodeHistoryInfo() {
        const historyInfo = {
            totalUniqueBarcodes: sentBarcodeHistory.length,
            totalSentCount: sentBarcodeHistory.reduce((sum, item) => sum + item.count, 0),
            latestBarcodes: sentBarcodeHistory
                .sort((a, b) => new Date(b.lastSent) - new Date(a.lastSent))
                .slice(0, 10)
                .map(item => ({
                    barcode: item.barcode,
                    lastSent: item.lastSent,
                    count: item.count
                }))
        };

        console.log('Barcode History Info:', historyInfo);
        return historyInfo;
    }

    // ========================================
    // JQUERY READY FUNCTION
    // ========================================
    $(document).ready(function() {
        // Initialize table IDs
        initializeHasilScanningTable();

        // Setup save button
        setupSaveButton();

        setupSaveHasilScanButton();

        console.log('Stock Opname JavaScript initialized successfully');
        console.log('Barcode history loaded:', sentBarcodeHistory.length, 'items');
    });

    // Fungsi untuk mengubah judul berdasarkan halaman
    function updateTitle(pageTitle) {
        document.title = pageTitle;
    }

    // Panggil fungsi ini saat halaman "Buat Hasil Stock Opname" dimuat
    updateTitle('Buat Hasil Stock Opname');

    // ========================================
    // EXPOSE FUNCTIONS TO GLOBAL SCOPE
    // ========================================
    window.removeItemFromStorage = removeItemFromStorage;
    window.clearAllScanData = clearAllScanData;
    window.clearAllProcessedData = clearAllProcessedData;
    window.processScanData = processScanData;
    window.getScanData = getScanData;
    window.getBarcodeArray = getBarcodeArray;
    window.updateHasilScanningTable = updateHasilScanningTable;
    window.getUpdateSummary = getUpdateSummary;
    window.resetTableVisualFeedback = resetTableVisualFeedback;

    window.clearBarcodeHistoryAndTable = clearBarcodeHistoryAndTable;
    window.setupClearScanButton = setupClearScanButton;
    window.clearHasilScanningTable = clearHasilScanningTable;

    window.saveHasilScanToServer = saveHasilScanToServer;
    window.collectHasilScanningData = collectHasilScanningData;
    window.setupSaveHasilScanButton = setupSaveHasilScanButton;
    window.performSaveWithFormSubmission = performSaveWithFormSubmission;
</script>
@endsection
