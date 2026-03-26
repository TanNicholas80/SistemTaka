@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <style>
        /* Mengikuti pola tab modal di pengiriman_pesanan/create.blade.php */
        .retur-modal-tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1rem;
        }
        .retur-modal-tab {
            padding: 0.75rem 1rem;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .retur-modal-tab.active {
            color: #d32f2f;
            border-bottom-color: #d32f2f;
        }
        .retur-modal-tab:hover:not(.active) {
            color: #374151;
            border-bottom-color: #d1d5db;
        }
        #modalItemDetail .modal-content {
            border-radius: 0.75rem;
            overflow: hidden;
        }
        #modalItemDetail .retur-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1.5rem 1rem 1.5rem;
            border-top: none;
        }
        #modalItemDetail .btn-retur-modal-simpan {
            background: #1a4b8c;
            color: white;
            padding: 8px 30px;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid #1a4b8c;
        }
        #modalItemDetail .btn-retur-modal-simpan:hover {
            background: #1e3a8a;
            color: white;
        }
        #modalItemDetail .btn-retur-modal-tutup {
            background: #fff;
            color: #dc2626;
            border: 1px solid #dc2626;
            padding: 8px 30px;
            border-radius: 6px;
            font-weight: 600;
        }
        #modalItemDetail .btn-retur-modal-tutup:hover {
            background: #fef2f2;
            color: #b91c1c;
        }
    </style>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Form Retur Penjualan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('retur_penjualan.index') }}">Retur Penjualan</a></li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </div>
            </div>

            <div class="card">
                <form id="returPenjualanForm" method="POST" action="{{ route('retur_penjualan.store') }}" class="p-3 space-y-3">
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
                    <input type="hidden" id="url_sales_invoices" value="{{ route('retur_penjualan.sales_invoices') }}">
                    <input type="hidden" id="url_referensi_detail" value="{{ route('retur_penjualan.referensi_detail') }}">
                    <input type="hidden" id="pelanggan_id_hidden" name="pelanggan_id" value="">
                    <input type="hidden" id="pengiriman_pesanan_id" name="pengiriman_pesanan_id" value="">
                    <input type="hidden" id="faktur_penjualan_id" name="faktur_penjualan_id" value="">
                    <input type="hidden" id="return_type" name="return_type" value="invoice">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                <!-- Retur Dari: dikunci ke Faktur (Invoice) -->
                                <label for="retur_dari_tipe" class="text-gray-800 font-medium flex items-center">
                                    Retur Dari <span class="text-red-600 ml-1">*</span>
                                    <span class="ml-1"
                                        data-toggle="tooltip"
                                        data-placement="top"
                                        title="Tipe retur dikunci ke Faktur (Invoice). Pilih faktur lalu klik Lanjut."
                                        style="cursor: help;">
                                        <i class="fas fa-info-circle"></i>
                                    </span>
                                </label>
                                <div class="flex flex-nowrap items-center gap-2 w-full">
                                    <div class="border border-gray-300 rounded px-2 py-1 text-sm shrink-0 w-[120px] bg-gray-100 text-gray-700">
                                        Faktur
                                    </div>
                                    <!-- Input Cari/Pilih Faktur -->
                                    <div id="return-referensi-wrapper" class="relative flex-1 min-w-0 max-w-[230px]">
                                        <div class="flex items-center border border-gray-300 rounded overflow-hidden">
                                            <input id="return_referensi_search" name="return_referensi_search" type="search"
                                                class="flex-grow outline-none px-2 py-1 text-sm"
                                                placeholder="Cari/Pilih Faktur..." />
                                            <button type="button" id="return-referensi-search-btn" class="px-2 text-gray-600 hover:text-gray-900">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" id="return_referensi_id" name="return_referensi_id" value="" />
                                        <!-- Dropdown akan muncul di sini -->
                                        <div id="dropdown-return-referensi"
                                            class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded shadow mt-1 hidden max-h-40 overflow-y-auto text-sm">
                                        </div>
                                    </div>
                                </div>
                            
                                <!-- Pelanggan -->
                                <label for="pelanggan_id" class="text-gray-800 font-medium flex items-center">
                                    Pelanggan <span class="text-red-600 ml-1">*</span>
                                    <span class="ml-1"
                                        data-toggle="tooltip"
                                        data-placement="top"
                                        title="Pelanggan akan terisi otomatis setelah pilih Faktur/Pengiriman dan klik Lanjut"
                                        style="cursor: help;">
                                        <i class="fas fa-info-circle"></i>
                                    </span>
                                </label>

                                <div class="relative max-w-md w-full">
                                    <input type="hidden" id="pelanggan_customer_no" name="pelanggan_customer_no" value="" />
                                    <div class="flex items-center border border-gray-300 rounded overflow-hidden max-w-[300px]">
                                        <input
                                            id="pelanggan_id"
                                            type="text"
                                            placeholder="Akan terisi otomatis..."
                                            class="flex-grow px-2 py-1 outline-none text-sm bg-gray-100"
                                            readonly
                                            required />
                                    </div>
                                </div>

                                <!-- Tanggal -->
                                <label for="tanggal_retur" class="text-gray-800 font-medium flex items-center">
                                    Tanggal<span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="tanggal_retur" name="tanggal_retur" type="date" value="{{ $selectedTanggal }}"
                                    class="border border-gray-300 rounded px-2 py-1 max-w-[300px] w-full {{ $formReadonly ? 'bg-gray-200 text-gray-500' : '' }}"
                                    required {{ $formReadonly ? 'readonly' : '' }} />

                                <!-- Pengembalian -->
                                <label for="return_status_type" class="text-gray-800 font-medium flex items-center">
                                    Pengembalian <span class="text-red-600 ml-1">*</span>
                                    <span class="ml-1" data-toggle="tooltip" data-placement="top"
                                        title="Not Returned: belum dikembalikan; Partially Returned: sebagian dikembalikan (atur per item); Returned: sudah dikembalikan semua"
                                        style="cursor: help;"><i class="fas fa-info-circle"></i></span>
                                </label>
                                <div class="max-w-[300px]">
                                    <select id="return_status_type" name="return_status_type"
                                        class="border border-gray-300 rounded px-2 py-1 text-sm w-full">
                                        <option value="not_returned">Not Returned (Tidak Dikembalikan)</option>
                                        <option value="partially_returned">Partially Returned (Sebagian Dikembalikan)</option>
                                        <option value="returned">Returned (Dikembalikan)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                <!-- Nomor Retur -->
                                <label for="no_retur" class="text-gray-800 font-medium flex items-center">
                                    Nomor Retur<span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="no_retur" name="no_retur" type="text" value="{{ $no_retur }}"
                                    class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6] {{ $formReadonly ? 'bg-gray-200 text-gray-500' : '' }}"
                                    readonly />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        @if (!$formReadonly)
                        <button type="button" id="btn-lanjut"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded text-xs">
                            Lanjut
                        </button>
                        @endif
                    </div>
                </form>

                <!-- Table container -->
                <div class="p-2 flex flex-col gap-2">
                    <div class="border border-gray-300 rounded overflow-hidden text-sm">
                        <div class="flex justify-between items-center border border-gray-300 rounded-t bg-[#f9f9f9] px-2 py-2 text-sm">
                            <!-- Search kiri -->
                            <div class="flex items-center border rounded px-2 py-1 w-[280px]">
                                <input
                                    id="search-barang"
                                    class="flex-grow outline-none placeholder-gray-400"
                                    placeholder="Cari/Pilih Barang & Jasa..."
                                    type="text" />
                                <i class="fas fa-search text-gray-500 ml-2"></i>
                            </div>
                            <div class="flex items-center gap-4">
                                <!-- Label kanan -->
                                <div class="text-gray-800 font-semibold text-base whitespace-nowrap">
                                    Rincian Barang <span class="text-red-600">*</span>
                                </div>
                            </div>
                        </div>
                        <table class="w-full border-collapse border border-gray-400 text-xs text-center">
                            <thead class="bg-[#607d8b] text-white">
                                <tr>
                                    <th class="border border-gray-400 w-6">
                                        <i class="fas fa-sort">
                                        </i>
                                    </th>
                                    <th class="border border-gray-400 px-2 py-1 text-left">
                                        Nama Barang
                                    </th>
                                    <th class="border border-gray-400 px-2 py-1">
                                        Kode #
                                    </th>
                                    <th class="border border-gray-400 px-2 py-1">
                                        Kuantitas
                                    </th>
                                    <th class="border border-gray-400 px-2 py-1">
                                        Satuan
                                    </th>
                                    <th class="border border-gray-400 px-2 py-1">
                                        @Harga
                                    </th>
                                    <th class="border border-gray-400 px-2 py-1">
                                        Diskon
                                    </th>
                                    <th class="border border-gray-400 px-2 py-1">
                                        Total Harga
                                    </th>
                                    <th id="th-status-retur" class="border border-gray-400 px-2 py-1" style="display: none;">
                                        Status Retur
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="table-barang-body" class="bg-white">
                                @if (isset($detailItems) && count($detailItems) > 0)
                                @foreach ($detailItems as $item)
                                <tr>
                                    <td class="border border-gray-400 px-2 py-3 text-left align-top">
                                        ≡
                                    </td>
                                    <td class="border border-gray-400 px-2 py-3 text-left align-top">
                                        {{ $item['item']['name'] }}
                                    </td>
                                    <td class="border border-gray-400 px-2 py-3 align-top">
                                        {{ $item['item']['no'] }}
                                    </td>
                                    <td class="border border-gray-400 px-2 py-3 align-top">
                                        {{ $item['quantity'] }}
                                    </td>
                                    <td class="border border-gray-400 px-2 py-3 align-top">
                                        {{ $item['itemUnit']['name'] }}
                                    </td>
                                    <td class="border border-gray-400 px-2 py-3 align-top">
                                        {{ $item['unitPrice'] }}
                                    </td>
                                    <td class="border border-gray-400 px-2 py-3 align-top">
                                        {{ $item['itemCashDiscount'] }}
                                    </td>
                                    <td class="border border-gray-400 px-2 py-3 align-top">
                                        {{ $item['totalPrice'] }}
                                    </td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
                                    <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="8">
                                        Klik "Lanjut" setelah memilih Pelanggan dan Referensi (Faktur/Pengiriman) untuk memuat barang.
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <!-- Ringkasan Diskon & Total -->
                    <div class="border border-gray-300 rounded bg-white px-3 py-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-stretch">
                            <div class="border border-gray-200 rounded px-3 py-2 bg-[#f9fafb]">
                                <div class="text-gray-600 text-xs font-semibold">Diskon</div>
                                <div class="mt-1 flex items-center gap-2">
                                    <input
                                        id="diskon_keseluruhan"
                                        name="diskon_keseluruhan"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value="0"
                                        class="border border-gray-200 rounded px-2 py-1 w-full bg-gray-100 text-gray-700 font-semibold text-right outline-none"
                                    />
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded px-3 py-2 bg-[#f9fafb]">
                                <div class="text-gray-600 text-xs font-semibold text-right">Total</div>
                                <div class="mt-1 text-right text-gray-900 font-bold" id="total_keseluruhan_display">
                                    Rp 0
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end mt-2">
                        <button type="button" id="btn-save-retur-penjualan"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Save Retur Penjualan
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Item (double click row) — tampilan mengikuti pengiriman_pesanan/create -->
<div class="modal fade" id="modalItemDetail" tabindex="-1" role="dialog" aria-labelledby="modalItemDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 700px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-0">
                <div class="flex items-center justify-between px-6 pt-5 pb-3">
                    <h2 class="text-base font-semibold text-gray-800 m-0" id="modalItemDetailLabel">Rincian Barang</h2>
                    <button type="button" id="retur_modal_close_btn" class="text-gray-400 hover:text-gray-700 focus:outline-none transition-colors bg-transparent border-0 p-0" data-dismiss="modal" aria-label="Tutup">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="retur-modal-tabs px-6" id="retur_modal_tabs">
                    <div class="retur-modal-tab active cursor-pointer pb-2 border-b-2 border-red-600 text-red-600 font-bold" data-tab="rincian">Rincian Barang</div>
                    <div class="retur-modal-tab cursor-pointer pb-2 text-gray-500 font-semibold" data-tab="seri">No Seri/Produksi</div>
                </div>

                <div class="px-6 pb-2">
                    <div id="tab-content-rincian" class="retur-tab-content space-y-4">
                        <div class="grid grid-cols-[120px_1fr] gap-4 items-center text-sm">
                            <label class="text-gray-600">Kode #</label>
                            <div id="modal_item_kode" class="font-semibold text-blue-700">—</div>

                            <label class="text-gray-600">Nama Barang</label>
                            <div id="modal_item_nama" class="font-semibold text-gray-800">—</div>

                            <label class="text-gray-600">Kuantitas</label>
                            <input id="modal_item_qty" type="number" min="0" step="0.01" class="border border-gray-300 rounded px-2 py-1 w-full max-w-xs focus:outline-none focus:ring-1 focus:ring-blue-500">

                            <label class="text-gray-600">@Harga</label>
                            <input id="modal_item_harga" type="number" min="0" step="0.01" class="border border-gray-300 rounded px-2 py-1 w-full max-w-xs focus:outline-none focus:ring-1 focus:ring-blue-500">

                            <label class="text-gray-600">Diskon</label>
                            <input id="modal_item_diskon" type="number" min="0" step="0.01" class="border border-gray-300 rounded px-2 py-1 w-full max-w-xs focus:outline-none focus:ring-1 focus:ring-blue-500">

                            <label class="text-gray-600">Gudang</label>
                            <input id="modal_item_gudang" type="text" readonly class="border border-gray-200 rounded px-2 py-1 w-full max-w-md bg-gray-100 text-gray-700">

                            <label class="text-gray-600">Total Harga</label>
                            <input id="modal_item_total" type="text" readonly class="border border-gray-200 rounded px-2 py-1 w-full max-w-md bg-gray-100 text-gray-700 font-semibold">
                        </div>

                        <div id="modal_return_status_wrap" class="border border-gray-200 rounded p-3 mt-2 bg-gray-50" style="display:none;">
                            <div class="text-sm font-semibold text-gray-700 mb-2">Status Retur</div>
                            <div class="d-flex flex-wrap gap-3">
                                <label class="mb-0 d-flex align-items-center">
                                    <input type="radio" name="modal_return_detail_status" value="NOT_RETURNED" class="mr-2">
                                    NOT_RETURNED
                                </label>
                                <label class="mb-0 d-flex align-items-center">
                                    <input type="radio" name="modal_return_detail_status" value="RETURNED" class="mr-2">
                                    RETURNED
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="tab-content-seri" class="retur-tab-content hidden space-y-4">
                        <div class="grid grid-cols-[120px_1fr] gap-2 items-center text-sm">
                            <label class="text-gray-600 font-bold">Nomor #</label>
                            <div class="flex gap-2">
                                <input type="text" id="modal_serial_filter_input" placeholder="Cari nomor serial..."
                                    class="border border-gray-300 rounded px-2 py-1 flex-grow focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm">
                            </div>
                        </div>
                        <div class="max-h-60 overflow-y-auto border border-gray-200 mt-2 rounded">
                            <table class="w-full text-xs">
                                <thead class="text-white sticky top-0" style="background-color: #166534;">
                                    <tr>
                                        <th class="p-2 border border-gray-300 text-left">Nomor #</th>
                                        <th class="p-2 border border-gray-300 text-center w-28">Kuantitas</th>
                                    </tr>
                                </thead>
                                <tbody id="modal_serial_tbody"></tbody>
                            </table>
                        </div>
                        <div id="modal_serial_summary" class="text-xs font-semibold text-gray-700 mt-2">
                            0 No Seri/Produksi, Jumlah 0
                        </div>
                    </div>
                </div>
            </div>

            <div class="retur-modal-footer border-0">
                <button type="button" class="btn-retur-modal-tutup" data-dismiss="modal">Tutup</button>
                <button type="button" id="modalItemDetailSave" class="btn-retur-modal-simpan">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script id="initial-sales-invoices" type="application/json">{!! json_encode($salesInvoices ?? []) !!}</script>
<script>
(function() {
    // Data awal dari backend (optional). Untuk menghindari parsing linter di file .blade.php,
    // JSON disimpan di <script type="application/json"> lalu diparse di sini.
    const initialSalesInvoicesEl = document.getElementById('initial-sales-invoices');
    let salesInvoices = JSON.parse((initialSalesInvoicesEl && initialSalesInvoicesEl.textContent) ? initialSalesInvoicesEl.textContent : '[]');

    // return_type dikunci ke invoice (input hidden)
    const returnTypeSelect = document.getElementById('return_type');
    const returnReferensiSearch = document.getElementById('return_referensi_search');
    const returnReferensiId = document.getElementById('return_referensi_id');
    const returnReferensiWrapper = document.getElementById('return-referensi-wrapper');
    const dropdownReturnReferensi = document.getElementById('dropdown-return-referensi');
    const pelangganInput = document.getElementById('pelanggan_id');
    const pelangganCustomerNoInput = document.getElementById('pelanggan_customer_no');
    const pelangganFetchStatus = document.getElementById('pelanggan-fetch-status');
    const returnReferensiSearchBtn = document.getElementById('return-referensi-search-btn');

    // Ambil list faktur (Belum Lunas + Lunas) tanpa perlu pilih pelanggan dulu
    function preloadSalesInvoices() {
        const urlSiEl = document.getElementById('url_sales_invoices');
        const urlSi = (urlSiEl && urlSiEl.value) ? urlSiEl.value : '/retur-penjualan/sales-invoices';
        if (pelangganFetchStatus) {
            pelangganFetchStatus.textContent = 'Memuat daftar Faktur Penjualan...';
            pelangganFetchStatus.classList.remove('hidden');
        }
        fetch(urlSi, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(res => {
                salesInvoices = res.salesInvoices || [];
                if (pelangganFetchStatus) {
                    pelangganFetchStatus.textContent = 'Berhasil memuat: ' + salesInvoices.length + ' faktur.';
                    pelangganFetchStatus.classList.remove('hidden');
                }
            })
            .catch(err => {
                console.error('Error preload sales invoices:', err);
                if (pelangganFetchStatus) {
                    pelangganFetchStatus.textContent = 'Gagal memuat faktur. Silakan refresh halaman.';
                    pelangganFetchStatus.classList.remove('hidden');
                }
            });
    }

    function updateTitle(pageTitle) {
        document.title = pageTitle;
    }

    updateTitle('Create Retur Penjualan');

    // Helper: sumber data referensi & placeholder berdasarkan tipe
    function getReferensiSource() {
        return { data: salesInvoices, label: 'Faktur' };
    }
    function getReferensiPlaceholder() {
        const src = getReferensiSource();
        return src.label ? 'Cari/Pilih ' + src.label + '...' : '';
    }
    function toggleReferensiVisibility() {
        if (returnReferensiWrapper) returnReferensiWrapper.style.display = '';
        if (returnReferensiSearch) returnReferensiSearch.placeholder = getReferensiPlaceholder();
    }

    // ---- Dropdown Referensi (Faktur / Pengiriman) ----
    function getReferensiDisplayName(row) {
        const num = row.number || row.no || '';
        const cust = row.customer;
        const custName = (cust && typeof cust === 'object' && cust.name) ? cust.name : (typeof cust === 'string' ? cust : '');
        return custName ? num + ' - ' + custName : num;
    }
    function showDropdownReferensi(input) {
        if (!dropdownReturnReferensi || !returnReferensiWrapper || returnReferensiWrapper.style.display === 'none') return;
        const src = getReferensiSource();
        const data = src.data || [];
        const query = (input && input.value) ? input.value.toLowerCase().trim() : '';
        const filtered = query === ''
            ? data
            : data.filter(r => {
                const name = getReferensiDisplayName(r).toLowerCase();
                const num = (r.number || r.no || '').toLowerCase();
                return name.includes(query) || num.includes(query);
            });
        dropdownReturnReferensi.innerHTML = '';
        if (filtered.length === 0) {
            const noResult = document.createElement('div');
            noResult.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noResult.textContent = query ? 'Tidak ada yang cocok dengan "' + query + '"' : 'Tidak ada data ' + src.label;
            dropdownReturnReferensi.appendChild(noResult);
        } else {
            const header = document.createElement('div');
            header.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-sm text-blue-700';
            header.innerHTML = '<i class="fas fa-search mr-2"></i>Hasil: ' + filtered.length + ' ' + src.label;
            dropdownReturnReferensi.appendChild(header);
            filtered.forEach(row => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b';
                item.textContent = getReferensiDisplayName(row);
                const id = row.id != null ? row.id : (row.number || row.no);
                item.onclick = function() {
                    if (returnReferensiSearch) returnReferensiSearch.value = getReferensiDisplayName(row);
                    if (returnReferensiId) returnReferensiId.value = id;
                    // Pelanggan diisi saat user memilih faktur (tanpa menunggu tombol Lanjut)
                    const cust = row.customer;
                    const custName = (cust && typeof cust === 'object' && cust.name) ? cust.name : '';
                    const custNo = (cust && typeof cust === 'object' && (cust.customerNo || cust.no)) ? (cust.customerNo || cust.no) : '';
                    const pelangganIdHidden = document.getElementById('pelanggan_id_hidden');
                    if (pelangganInput) pelangganInput.value = custName || custNo || '';
                    if (pelangganCustomerNoInput) pelangganCustomerNoInput.value = custNo || '';
                    if (pelangganIdHidden) pelangganIdHidden.value = custNo || '';
                    dropdownReturnReferensi.classList.add('hidden');
                };
                dropdownReturnReferensi.appendChild(item);
            });
        }
        dropdownReturnReferensi.classList.remove('hidden');
    }
    function showAllReferensi() {
        if (!dropdownReturnReferensi || !returnReferensiWrapper || returnReferensiWrapper.style.display === 'none') return;
        const src = getReferensiSource();
        const data = src.data || [];
        dropdownReturnReferensi.innerHTML = '';
        if (data.length === 0) {
            const noData = document.createElement('div');
            noData.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noData.textContent = 'Tidak ada data ' + src.label;
            dropdownReturnReferensi.appendChild(noData);
        } else {
            const header = document.createElement('div');
            header.className = 'px-3 py-2 bg-gray-50 border-b font-semibold text-sm text-gray-700';
            header.innerHTML = '<i class="fas fa-list mr-2"></i>Semua ' + src.label + ' (' + data.length + ')';
            dropdownReturnReferensi.appendChild(header);
            const maxShow = 50;
            data.slice(0, maxShow).forEach(row => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b';
                item.textContent = getReferensiDisplayName(row);
                const id = row.id != null ? row.id : (row.number || row.no);
                item.onclick = function() {
                    if (returnReferensiSearch) returnReferensiSearch.value = getReferensiDisplayName(row);
                    if (returnReferensiId) returnReferensiId.value = id;
                    // Pelanggan diisi saat user memilih faktur (tanpa menunggu tombol Lanjut)
                    const cust = row.customer;
                    const custName = (cust && typeof cust === 'object' && cust.name) ? cust.name : '';
                    const custNo = (cust && typeof cust === 'object' && (cust.customerNo || cust.no)) ? (cust.customerNo || cust.no) : '';
                    const pelangganIdHidden = document.getElementById('pelanggan_id_hidden');
                    if (pelangganInput) pelangganInput.value = custName || custNo || '';
                    if (pelangganCustomerNoInput) pelangganCustomerNoInput.value = custNo || '';
                    if (pelangganIdHidden) pelangganIdHidden.value = custNo || '';
                    dropdownReturnReferensi.classList.add('hidden');
                };
                dropdownReturnReferensi.appendChild(item);
            });
            if (data.length > maxShow) {
                const more = document.createElement('div');
                more.className = 'px-3 py-2 bg-blue-50 border-b text-sm text-blue-600 text-center';
                more.textContent = 'Menampilkan ' + maxShow + ' dari ' + data.length + '. Ketik untuk mencari.';
                dropdownReturnReferensi.appendChild(more);
            }
        }
        dropdownReturnReferensi.classList.remove('hidden');
    }

    // Tipe retur dikunci, jadi tidak ada event change

    // ---- Event: Referensi (Faktur/Pengiriman) ----
    if (returnReferensiSearchBtn) {
        returnReferensiSearchBtn.addEventListener('click', function() {
            if (returnReferensiSearch && returnReferensiSearch.readOnly) return;
            if (!returnReferensiWrapper || returnReferensiWrapper.style.display === 'none') return;
            if (returnReferensiSearch.value.trim() === '') showAllReferensi();
            else showDropdownReferensi(returnReferensiSearch);
        });
    }
    if (returnReferensiSearch) {
        returnReferensiSearch.addEventListener('input', function() {
            if (returnReferensiSearch.readOnly) return;
            showDropdownReferensi(returnReferensiSearch);
        });
        returnReferensiSearch.addEventListener('focus', function() {
            if (returnReferensiSearch.readOnly) return;
            if (returnReferensiWrapper && returnReferensiWrapper.style.display === 'none') return;
            if (returnReferensiSearch.value.trim() === '') showAllReferensi();
            else showDropdownReferensi(returnReferensiSearch);
        });
        returnReferensiSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && dropdownReturnReferensi) dropdownReturnReferensi.classList.add('hidden');
        });
    }

    // Klik di luar untuk menutup dropdown
    document.addEventListener('click', function(e) {
        if (returnReferensiWrapper && dropdownReturnReferensi && returnReferensiWrapper.contains(e.target) === false && dropdownReturnReferensi.contains(e.target) === false) {
            dropdownReturnReferensi.classList.add('hidden');
        }
    });

    // --- Data detail items (diisi setelah Lanjut) ---
    let detailItems = [];

    function setFakturReadonly(readonly) {
        if (returnReferensiSearch) {
            returnReferensiSearch.readOnly = readonly;
            if (readonly) {
                returnReferensiSearch.classList.add('bg-gray-200', 'text-gray-500');
            } else {
                returnReferensiSearch.classList.remove('bg-gray-200', 'text-gray-500');
            }
        }
        if (returnReferensiSearchBtn) {
            returnReferensiSearchBtn.disabled = readonly;
            if (readonly) {
                returnReferensiSearchBtn.classList.add('opacity-50', 'cursor-not-allowed');
                returnReferensiSearchBtn.classList.add('hidden');
            } else {
                returnReferensiSearchBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                returnReferensiSearchBtn.classList.remove('hidden');
            }
        }
        if (dropdownReturnReferensi) dropdownReturnReferensi.classList.add('hidden');
    }

    function getReturnStatusType() {
        const el = document.getElementById('return_status_type');
        return el ? el.value : 'not_returned';
    }

    function formatCurrency(num) {
        const n = parseFloat(num);
        if (isNaN(n)) return '0';
        return n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function fillTableWithDetailItems(items) {
        const tableBody = document.getElementById('table-barang-body');
        const thStatusRetur = document.getElementById('th-status-retur');
        const isPartially = getReturnStatusType() === 'partially_returned';

        if (!tableBody) return;
        tableBody.innerHTML = '';

        if (thStatusRetur) thStatusRetur.style.display = isPartially ? '' : 'none';

        if (!items || items.length === 0) {
            const colspan = 8;
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td><td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="' + colspan + '">Belum ada data barang</td>';
            tableBody.appendChild(emptyRow);
            return;
        }

        items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.setAttribute('data-row-index', index);
            const unitPrice = formatCurrency(item.unitPrice || 0);
            const diskonInput = getItemDiskonInput(item);
            const discount = formatCurrency(diskonInput || 0);
            // Pastikan total konsisten dengan aturan diskon: 0-100% vs nominal (>100)
            item.totalPrice = computeTotal(item);
            const totalPrice = formatCurrency(item.totalPrice || 0);
            const statusRetur = (item.return_detail_status || 'NOT_RETURNED');
            const statusLabel = statusRetur === 'RETURNED' ? 'RETURNED' : 'NOT_RETURNED';

            let statusCell = '';
            if (isPartially) {
                statusCell = '<td class="border border-gray-400 px-2 py-3 align-top status-retur-cell" data-row-index="' + index + '">' + statusLabel + '</td>';
            }

            row.innerHTML = '<td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>' +
                '<td class="border border-gray-400 px-2 py-3 text-left align-top">' + (item.item && item.item.name ? item.item.name : 'N/A') + '</td>' +
                '<td class="border border-gray-400 px-2 py-3 align-top">' + (item.item && item.item.no ? item.item.no : 'N/A') + '</td>' +
                '<td class="border border-gray-400 px-2 py-3 align-top">' + (item.quantity || 0) + '</td>' +
                '<td class="border border-gray-400 px-2 py-3 align-top">' + (item.itemUnit && item.itemUnit.name ? item.itemUnit.name : 'N/A') + '</td>' +
                '<td class="border border-gray-400 px-2 py-3 align-top">' + unitPrice + '</td>' +
                '<td class="border border-gray-400 px-2 py-3 align-top">' + discount + '</td>' +
                '<td class="border border-gray-400 px-2 py-3 align-top">' + totalPrice + '</td>' + statusCell;

            row.style.cursor = 'pointer';
            row.title = 'Double klik untuk membuka rincian barang';
            row.addEventListener('dblclick', function() {
                openItemDetailModal(index);
            });
            tableBody.appendChild(row);
        });
    }

    function safeNumber(val, fallback) {
        const n = parseFloat(val);
        return isNaN(n) ? (fallback != null ? fallback : 0) : n;
    }

    function getItemDiskonInput(item) {
        if (!item) return 0;
        // Accurate bisa mengembalikan diskon dalam bentuk percent (itemDiscPercent) atau nominal (itemCashDiscount)
        if (item.itemDiscPercent !== undefined && item.itemDiscPercent !== null && item.itemDiscPercent !== '') {
            return safeNumber(item.itemDiscPercent, 0);
        }
        if (item.itemCashDiscount !== undefined && item.itemCashDiscount !== null && item.itemCashDiscount !== '') {
            return safeNumber(item.itemCashDiscount, 0);
        }
        return 0;
    }

    function computeTotal(item) {
        const qty = safeNumber(item.quantity, 0);
        const harga = safeNumber(item.unitPrice, 0);
        const diskonInput = getItemDiskonInput(item);
        const gross = qty * harga;
        let total = gross;
        // Aturan input user:
        // - 0-100 dianggap percent
        // - >100 dianggap nominal
        if (diskonInput > 0) {
            if (diskonInput <= 100) {
                total = gross - (gross * (diskonInput / 100));
            } else {
                total = gross - diskonInput;
            }
        }
        return total < 0 ? 0 : total;
    }

    function updateDiskonTotalKeseluruhan() {
        const elDiskon = document.getElementById('diskon_keseluruhan');
        const elTotalDisplay = document.getElementById('total_keseluruhan_display');
        if (!elTotalDisplay) return;

        const base = Array.isArray(detailItems)
            ? detailItems.reduce(function(sum, it) {
                if (!it) return sum;
                const v = (it.totalPrice !== undefined && it.totalPrice !== null) ? safeNumber(it.totalPrice, 0) : computeTotal(it);
                return sum + v;
            }, 0)
            : 0;

        const diskonValue = elDiskon ? safeNumber(elDiskon.value, 0) : 0;
        let total = base;
        // Accurate: jika <=100 dianggap percent, jika >100 dianggap nominal
        if (diskonValue > 0) {
            if (diskonValue <= 100) {
                total = base - (base * (diskonValue / 100));
            } else {
                total = base - diskonValue;
            }
        }
        total = total < 0 ? 0 : total;
        elTotalDisplay.textContent = 'Rp ' + formatCurrency(total);
    }

    function getWarehouseName(item) {
        // Heuristic: beberapa response Accurate punya warehouse.name atau warehouseName
        const wh = item.warehouse;
        if (wh && typeof wh === 'object' && wh.name) return wh.name;
        if (item.warehouseName) return item.warehouseName;
        if (item.warehouse && typeof item.warehouse === 'string') return item.warehouse;
        return '';
    }

    function renderSerialTable() {
        const modal = document.getElementById('modalItemDetail');
        const rowIndex = modal ? parseInt(modal.getAttribute('data-editing-row'), 10) : -1;
        const item = (!isNaN(rowIndex) && rowIndex >= 0) ? detailItems[rowIndex] : null;
        const tbody = document.getElementById('modal_serial_tbody');
        const summaryEl = document.getElementById('modal_serial_summary');
        const filterEl = document.getElementById('modal_serial_filter_input');
        const filter = (filterEl && filterEl.value) ? filterEl.value.trim().toLowerCase() : '';
        if (!tbody) return;
        const serialsAll = (item && Array.isArray(item.detailSerialNumber)) ? item.detailSerialNumber : [];
        let rows = serialsAll.map((sn, idx) => ({ sn, idx }));
        if (filter) {
            rows = rows.filter(function(r) {
                const serialNo = (r.sn && r.sn.serialNumber && r.sn.serialNumber.number) ? String(r.sn.serialNumber.number) : (r.sn && r.sn.serialNumberNo ? String(r.sn.serialNumberNo) : '');
                return serialNo.toLowerCase().includes(filter);
            });
        }
        tbody.innerHTML = '';
        let sumQty = 0;
        if (!serialsAll.length) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="2" class="text-center p-4 text-gray-400">Belum ada data</td>';
            tbody.appendChild(tr);
            if (summaryEl) summaryEl.textContent = '0 No Seri/Produksi, Jumlah 0';
            return;
        }
        if (!rows.length) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="2" class="text-center p-4 text-gray-400">Tidak ada nomor serial yang cocok</td>';
            tbody.appendChild(tr);
            serialsAll.forEach(function(sn) { sumQty += safeNumber(sn && sn.quantity, 0); });
            if (summaryEl) summaryEl.textContent = serialsAll.length + ' No Seri/Produksi, Jumlah ' + sumQty.toFixed(2);
            return;
        }
        rows.forEach(function(r) {
            const sn = r.sn;
            const idx = r.idx;
            const serialNo = (sn && sn.serialNumber && sn.serialNumber.number) ? sn.serialNumber.number : (sn && sn.serialNumberNo ? sn.serialNumberNo : '');
            const qty = safeNumber(sn && sn.quantity, 0);
            sumQty += qty;
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-100 transition-colors';
            tr.innerHTML =
                '<td class="p-2 border border-gray-200">' + (serialNo || '') + '</td>' +
                '<td class="p-1 border border-gray-200 text-center">' +
                '<input type="number" min="0" step="0.01" class="w-20 text-center border border-gray-200 rounded px-1 py-0.5 modal-serial-qty focus:outline-none focus:ring-1 focus:ring-blue-500" data-serial-index="' + idx + '" value="' + qty + '">' +
                '</td>';
            tbody.appendChild(tr);
        });
        const totalLines = serialsAll.length;
        let totalAllQty = 0;
        serialsAll.forEach(function(sn) { totalAllQty += safeNumber(sn && sn.quantity, 0); });
        if (summaryEl) summaryEl.textContent = totalLines + ' No Seri/Produksi, Jumlah ' + totalAllQty.toFixed(2);
    }

    function switchReturItemModalTab(tab) {
        const tabs = document.querySelectorAll('#retur_modal_tabs .retur-modal-tab');
        const rincian = document.getElementById('tab-content-rincian');
        const seri = document.getElementById('tab-content-seri');
        tabs.forEach(function(t) {
            const isActive = t.getAttribute('data-tab') === tab;
            t.classList.toggle('active', isActive);
            if (isActive) {
                t.classList.add('border-b-2', 'border-red-600', 'text-red-600', 'font-bold');
                t.classList.remove('text-gray-500', 'font-semibold');
            } else {
                t.classList.remove('border-b-2', 'border-red-600', 'text-red-600', 'font-bold');
                t.classList.add('text-gray-500', 'font-semibold');
            }
        });
        if (rincian) rincian.classList.toggle('hidden', tab !== 'rincian');
        if (seri) seri.classList.toggle('hidden', tab !== 'seri');
        if (tab === 'seri') {
            renderSerialTable();
            const fi = document.getElementById('modal_serial_filter_input');
            if (fi) setTimeout(function() { fi.focus(); }, 100);
        }
    }

    function openItemDetailModal(rowIndex) {
        if (rowIndex < 0 || !detailItems[rowIndex]) return;
        const item = detailItems[rowIndex];
        const modal = document.getElementById('modalItemDetail');
        if (!modal) return;

        modal.setAttribute('data-editing-row', String(rowIndex));

        const kode = (item.item && item.item.no) ? item.item.no : '';
        const nama = (item.item && item.item.name) ? item.item.name : '';
        const gudang = getWarehouseName(item);
        const total = computeTotal(item);

        const elKode = document.getElementById('modal_item_kode');
        const elNama = document.getElementById('modal_item_nama');
        const elQty = document.getElementById('modal_item_qty');
        const elHarga = document.getElementById('modal_item_harga');
        const elDiskon = document.getElementById('modal_item_diskon');
        const elGudang = document.getElementById('modal_item_gudang');
        const elTotal = document.getElementById('modal_item_total');

        if (elKode) elKode.textContent = kode || '—';
        if (elNama) elNama.textContent = nama || '—';
        if (elQty) elQty.value = safeNumber(item.quantity, 0);
        if (elHarga) elHarga.value = safeNumber(item.unitPrice, 0);
        if (elDiskon) elDiskon.value = safeNumber(getItemDiskonInput(item), 0);
        if (elGudang) elGudang.value = gudang;
        if (elTotal) elTotal.value = formatCurrency(total);

        // Toggle status radio only when partially_returned
        const wrap = document.getElementById('modal_return_status_wrap');
        const isPartially = getReturnStatusType() === 'partially_returned';
        if (wrap) wrap.style.display = isPartially ? '' : 'none';
        if (isPartially) {
            const val = item.return_detail_status || 'NOT_RETURNED';
            const radios = modal.querySelectorAll('input[name="modal_return_detail_status"]');
            radios.forEach(r => { r.checked = (r.value === val); });
        }

        const filt = document.getElementById('modal_serial_filter_input');
        if (filt) filt.value = '';
        switchReturItemModalTab('rincian');
        renderSerialTable();

        if (typeof $ !== 'undefined' && $(modal).modal) {
            $(modal).modal('show');
        }
    }

    function saveItemDetailFromModal() {
        const modal = document.getElementById('modalItemDetail');
        const rowIndex = modal ? parseInt(modal.getAttribute('data-editing-row'), 10) : -1;
        if (!modal || isNaN(rowIndex) || rowIndex < 0 || !detailItems[rowIndex]) return;

        const item = detailItems[rowIndex];

        const elQty = document.getElementById('modal_item_qty');
        const elHarga = document.getElementById('modal_item_harga');
        const elDiskon = document.getElementById('modal_item_diskon');
        const qty = elQty ? safeNumber(elQty.value, safeNumber(item.quantity, 0)) : safeNumber(item.quantity, 0);
        const harga = elHarga ? safeNumber(elHarga.value, safeNumber(item.unitPrice, 0)) : safeNumber(item.unitPrice, 0);
        const diskon = elDiskon ? safeNumber(elDiskon.value, safeNumber(getItemDiskonInput(item), 0)) : safeNumber(getItemDiskonInput(item), 0);

        item.quantity = qty;
        item.unitPrice = harga;
        // Simpan input diskon ke itemCashDiscount (nilai angka user)
        // Untuk request ke Accurate, mapping akan dilakukan berdasarkan aturan:
        // - 0-100 => percent (itemDiscPercent)
        // - >100 => nominal (itemCashDiscount)
        item.itemCashDiscount = diskon;
        item.itemDiscPercent = null;
        item.totalPrice = computeTotal(item);

        // Save return status if partially
        if (getReturnStatusType() === 'partially_returned') {
            const selected = modal.querySelector('input[name="modal_return_detail_status"]:checked');
            item.return_detail_status = selected ? selected.value : (item.return_detail_status || 'NOT_RETURNED');
        }

        // Save serial qty edits
        if (Array.isArray(item.detailSerialNumber)) {
            const inputs = modal.querySelectorAll('.modal-serial-qty');
            inputs.forEach(inp => {
                const idx = parseInt(inp.getAttribute('data-serial-index'), 10);
                if (isNaN(idx) || !item.detailSerialNumber[idx]) return;
                item.detailSerialNumber[idx].quantity = safeNumber(inp.value, safeNumber(item.detailSerialNumber[idx].quantity, 0));
            });

            // Jika item berbasis serial, jumlah kuantitas item harus mengikuti total qty serial
            const sumQty = item.detailSerialNumber.reduce(function(sum, sn) {
                return sum + safeNumber(sn && sn.quantity, 0);
            }, 0);
            item.quantity = sumQty;
        }

        fillTableWithDetailItems(detailItems);
        updateDiskonTotalKeseluruhan();
        if (typeof $ !== 'undefined' && $(modal).modal) $(modal).modal('hide');
    }

    // Tombol Lanjut: muat detail barang dari referensi
    const btnLanjut = document.getElementById('btn-lanjut');
    if (btnLanjut) {
        btnLanjut.addEventListener('click', function() {
            const pelangganIdHidden = document.getElementById('pelanggan_id_hidden');
            const returnRefId = document.getElementById('return_referensi_id');
            const returnType = document.getElementById('return_type');
            if (!returnRefId || !returnRefId.value.trim()) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih referensi Retur Dari (Faktur/Pengiriman) terlebih dahulu.' });
                else alert('Pilih referensi Retur Dari terlebih dahulu.');
                return;
            }
            const urlEl = document.getElementById('url_referensi_detail');
            const url = (urlEl && urlEl.value) ? urlEl.value : '';
            if (!url) return;
            const params = new URLSearchParams({ return_type: 'invoice', number: returnRefId.value.trim() });
            btnLanjut.disabled = true;
            btnLanjut.textContent = 'Loading...';
            // Saat tombol Lanjut ditekan, faktur dikunci (readonly)
            setFakturReadonly(true);
            fetch(url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(function(data) {
                    if (data.success && data.detailItems && data.detailItems.length > 0) {
                        // Isi pelanggan otomatis dari response detail.do
                        // Accurate umumnya mengembalikan object customer {name, customerNo, ...}
                        if (data.customer) {
                            const custName = (data.customer && data.customer.name) ? data.customer.name : '';
                            const custNo = (data.customer && (data.customer.customerNo || data.customer.no)) ? (data.customer.customerNo || data.customer.no) : '';
                            if (pelangganInput) pelangganInput.value = custName || custNo || '';
                            if (pelangganCustomerNoInput) pelangganCustomerNoInput.value = custNo || '';
                            if (pelangganIdHidden) pelangganIdHidden.value = custNo || '';
                        }

                        detailItems = data.detailItems;
                        if (getReturnStatusType() === 'partially_returned') {
                            detailItems.forEach(function(it) { it.return_detail_status = it.return_detail_status || 'NOT_RETURNED'; });
                        }
                        fillTableWithDetailItems(detailItems);
                        // Isi diskon keseluruhan dari referensi (jika tersedia)
                        const elDiskon = document.getElementById('diskon_keseluruhan');
                        if (elDiskon) {
                            const cashDiscPercent = (data.cashDiscPercent !== undefined && data.cashDiscPercent !== null) ? safeNumber(data.cashDiscPercent, 0) : null;
                            const cashDiscount = (data.cashDiscount !== undefined && data.cashDiscount !== null) ? safeNumber(data.cashDiscount, 0) : null;
                            if (cashDiscPercent !== null && cashDiscPercent > 0) {
                                elDiskon.value = String(cashDiscPercent);
                            } else if (cashDiscount !== null && cashDiscount > 0) {
                                elDiskon.value = String(cashDiscount);
                            } else {
                                elDiskon.value = '0';
                            }
                        }
                        updateDiskonTotalKeseluruhan();
                        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data barang dimuat: ' + detailItems.length + ' item.', timer: 2000, showConfirmButton: false });
                    } else {
                        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Info', text: data.message || 'Tidak ada detail barang.' });
                        else alert(data.message || 'Tidak ada detail barang.');
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal memuat detail referensi.' });
                    else alert('Gagal memuat detail referensi.');
                })
                .finally(function() {
                    btnLanjut.disabled = false;
                    btnLanjut.textContent = 'Lanjut';
                    // Jika gagal (tidak ada items), user masih boleh ganti faktur
                    // Jika sukses, faktur tetap terkunci.
                    if (!detailItems || detailItems.length === 0) setFakturReadonly(false);
                });
        });
    }

    // Saat return_status_type berubah, jika sudah ada detailItems re-render tabel
    const returnStatusTypeEl = document.getElementById('return_status_type');
    if (returnStatusTypeEl) {
        returnStatusTypeEl.addEventListener('change', function() {
            if (getReturnStatusType() === 'partially_returned' && detailItems.length > 0) {
                detailItems.forEach(function(it) { it.return_detail_status = it.return_detail_status || 'NOT_RETURNED'; });
            }
            fillTableWithDetailItems(detailItems);
            updateDiskonTotalKeseluruhan();
        });
    }

    // Modal Simpan detail item
    const modalItemSaveBtn = document.getElementById('modalItemDetailSave');
    if (modalItemSaveBtn) modalItemSaveBtn.addEventListener('click', saveItemDetailFromModal);

    function formatDetailItemsForSubmission(items) {
        if (!items || items.length === 0) return [];
        const isPartially = getReturnStatusType() === 'partially_returned';
        return items.map(function(item) {
            const diskonInput = getItemDiskonInput(item);
            const normalizedSerials = Array.isArray(item.detailSerialNumber)
                ? item.detailSerialNumber
                    .map(function(sn) {
                        return {
                            serialNumberNo: (sn && sn.serialNumberNo != null) ? String(sn.serialNumberNo) : '',
                            quantity: (sn && sn.quantity != null) ? safeNumber(sn.quantity, 0) : 0
                        };
                    })
                    .filter(function(sn) {
                        return sn.serialNumberNo && sn.quantity > 0;
                    })
                : null;
            const obj = {
                kode: (item.item && item.item.no) ? item.item.no : '',
                kuantitas: item.quantity != null ? item.quantity : 0,
                harga: item.unitPrice !== undefined && item.unitPrice !== null ? item.unitPrice : 0,
                diskon: diskonInput !== undefined && diskonInput !== null ? diskonInput : 0
            };
            if (normalizedSerials && normalizedSerials.length > 0) {
                obj.detailSerialNumber = normalizedSerials;
            }
            if (isPartially) obj.return_detail_status = item.return_detail_status || 'NOT_RETURNED';
            return obj;
        });
    }

    // Tombol Save Retur Penjualan
    const btnSaveRetur = document.getElementById('btn-save-retur-penjualan');
    if (btnSaveRetur) {
        btnSaveRetur.addEventListener('click', function() {
            updateDiskonTotalKeseluruhan();
            const form = document.getElementById('returPenjualanForm');
            // Karena ringkasan diskon berada di luar tag <form>, kita injeksikan nilai diskon ke hidden input.
            const diskonEl = document.getElementById('diskon_keseluruhan');
            if (form && diskonEl) {
                const existingDiskonInput = form.querySelector('input[name="diskon_keseluruhan"]');
                if (existingDiskonInput) existingDiskonInput.remove();
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'diskon_keseluruhan';
                hidden.value = diskonEl.value;
                form.appendChild(hidden);
            }
            const pelangganIdHidden = document.getElementById('pelanggan_id_hidden');
            const returnRefId = document.getElementById('return_referensi_id');
            const returnType = document.getElementById('return_type');
            if (!pelangganIdHidden || !pelangganIdHidden.value.trim()) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Pelanggan wajib dipilih.' });
                else alert('Pelanggan wajib dipilih.');
                return;
            }
            if (!returnRefId || !returnRefId.value.trim()) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Referensi Retur Dari wajib dipilih.' });
                else alert('Referensi Retur Dari wajib dipilih.');
                return;
            }
            if (!detailItems || detailItems.length === 0) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Klik Lanjut untuk memuat barang terlebih dahulu.' });
                else alert('Klik Lanjut untuk memuat barang terlebih dahulu.');
                return;
            }
            if (getReturnStatusType() === 'partially_returned') {
                const missing = detailItems.some(function(it) { return !it.return_detail_status; });
                if (missing) {
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Untuk Partially Returned, atur status pengembalian setiap barang (double klik baris).' });
                    else alert('Atur status pengembalian setiap barang (double klik baris).');
                    return;
                }
            }
            // Set referensi id ke input yang sesuai (invoice)
            const fakturIdEl = document.getElementById('faktur_penjualan_id');
            const refVal = returnRefId.value.trim();
            if (fakturIdEl) fakturIdEl.value = refVal;

            const formatted = formatDetailItemsForSubmission(detailItems);
            const existingDetailInputs = form.querySelectorAll('input[name^="detailItems"]');
            existingDetailInputs.forEach(function(input) { input.remove(); });
            formatted.forEach(function(item, index) {
                ['kode', 'kuantitas', 'harga', 'diskon'].forEach(function(field) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'detailItems[' + index + '][' + field + ']';
                    input.value = item[field] !== undefined && item[field] !== null ? item[field] : '';
                    form.appendChild(input);
                });
                if (Array.isArray(item.detailSerialNumber)) {
                    item.detailSerialNumber.forEach(function(sn, snIndex) {
                        const serialNo = sn && sn.serialNumberNo != null ? String(sn.serialNumberNo) : '';
                        const qty = sn && sn.quantity != null ? safeNumber(sn.quantity, 0) : 0;
                        if (!serialNo || qty <= 0) return;

                        const inputNo = document.createElement('input');
                        inputNo.type = 'hidden';
                        inputNo.name = 'detailItems[' + index + '][detailSerialNumber][' + snIndex + '][serialNumberNo]';
                        inputNo.value = serialNo;
                        form.appendChild(inputNo);

                        const inputQty = document.createElement('input');
                        inputQty.type = 'hidden';
                        inputQty.name = 'detailItems[' + index + '][detailSerialNumber][' + snIndex + '][quantity]';
                        inputQty.value = qty;
                        form.appendChild(inputQty);
                    });
                }
                if (getReturnStatusType() === 'partially_returned' && item.return_detail_status) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'detailItems[' + index + '][return_detail_status]';
                    input.value = item.return_detail_status;
                    form.appendChild(input);
                }
            });
            document.getElementById('form_submitted').value = '1';
            form.submit();
        });
    }

    // Inisialisasi: tampilkan/sembunyikan referensi sesuai tipe default
    document.addEventListener('DOMContentLoaded', function() {
        toggleReferensiVisibility();
        preloadSalesInvoices();
        const elDiskon = document.getElementById('diskon_keseluruhan');
        if (elDiskon) {
            elDiskon.addEventListener('input', function() {
                updateDiskonTotalKeseluruhan();
            });
        }
        // Tampilkan ringkasan awal agar konsisten (0 ketika belum ada barang)
        updateDiskonTotalKeseluruhan();
        if (typeof $ !== 'undefined' && $('[data-toggle="tooltip"]').length) $('[data-toggle="tooltip"]').tooltip();

        document.querySelectorAll('#retur_modal_tabs .retur-modal-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                switchReturItemModalTab(tab.getAttribute('data-tab'));
            });
        });
        const serialFilter = document.getElementById('modal_serial_filter_input');
        if (serialFilter) {
            serialFilter.addEventListener('input', function() {
                renderSerialTable();
            });
        }
    });
})();
</script>
@endsection