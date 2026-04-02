@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <style>
            .modal-tabs {
                display: flex;
                border-bottom: 2px solid #e5e7eb;
                margin-bottom: 1rem;
            }

            .modal-tab {
                padding: 0.75rem 1rem;
                cursor: pointer;
                font-weight: 600;
                color: #6b7280;
                border-bottom: 2px solid transparent;
                margin-bottom: -2px;
                transition: all 0.2s;
            }

            .modal-tab.active {
                color: #d32f2f;
                border-bottom-color: #d32f2f;
            }

            .modal-tab:hover:not(.active) {
                color: #374151;
                border-bottom-color: #d1d5db;
            }

            .swal2-popup.swal2-modal.item-detail-modal {
                width: 700px !important;
                padding: 0;
                border-radius: 8px;
                overflow: hidden;
            }

            .item-detail-modal-vcentered .swal2-header {
                background: transparent;
                border: none;
                padding: 0.5rem 1rem;
                position: relative;
            }

            .item-detail-modal-vcentered .swal2-close {
                color: #6b7280 !important;
                top: 10px !important;
                right: 10px !important;
                transition: color 0.2s;
            }

            .item-detail-modal-vcentered .swal2-close:hover {
                color: #1f2937 !important;
            }

            .item-detail-modal-vcentered .item-detail-modal-actions {
                display: flex !important;
                justify-content: space-between !important;
                width: 100% !important;
                padding: 0.5rem 1.5rem 1rem 1.5rem !important;
                border-top: none !important;
                margin-top: 0 !important;
                flex-row-reverse: false;
            }

            .item-detail-modal-vcentered .btn-lanjut-modal {
                background: #1a4b8c;
                color: white;
                padding: 8px 30px;
                border-radius: 6px;
                font-weight: 600;
                border: 1px solid #1a4b8c;
                order: 2;
            }

            .item-detail-modal-vcentered .btn-hapus-modal {
                background: #fff;
                color: #dc2626;
                border: 1px solid #dc2626;
                padding: 8px 30px;
                border-radius: 6px;
                font-weight: 600;
                order: 1;
            }

            .item-detail-modal-vcentered .btn-lanjut-modal:hover {
                background: #1e3a8a;
            }

            .item-detail-modal-vcentered .btn-hapus-modal:hover {
                background: #fef2f2;
            }

            .item-detail-modal-vcentered .swal2-html-container {
                margin: 0 !important;
                padding: 0 1.5rem 1.5rem 1.5rem !important;
                width: 100%;
            }

            .item-detail-modal-vcentered {
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                margin: auto !important;
            }

            #table-barang-body tr:hover {
                background-color: #f3f4f6 !important;
                cursor: pointer;
            }
        </style>
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Form Pengiriman Pesanan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('pengiriman_pesanan.index') }}">Pengiriman
                                    Pesanan</a></li>
                            <li class="breadcrumb-item active">Create</li>
                        </ol>
                    </div>
                </div>

                <div class="card">
                    <form id="pengirimanPesananForm" method="POST" action="{{ route('pengiriman_pesanan.store') }}"
                        class="p-3 space-y-3">
                        <div id="laravel-errors" @if(session('error')) data-error="{{ session('error') }}" @endif
                            @if($errors->any()) data-validation-errors="{{ json_encode($errors->all()) }}" @endif></div>
                        @csrf
                        <input type="hidden" id="form_submitted" name="form_submitted" value="0">
                        <input type="hidden" id="pelanggan_id_hidden" name="pelanggan_id" value="">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                    <!-- Tanggal -->
                                    <label for="tanggal_pengiriman" class="text-gray-800 font-medium flex items-center">
                                        Tanggal Pengiriman<span class="text-red-600 ml-1">*</span>
                                    </label>
                                    <input id="tanggal_pengiriman" name="tanggal_pengiriman" type="date"
                                        value="{{ $selectedTanggal }}"
                                        class="border border-gray-300 rounded px-2 py-1 max-w-[300px] w-full {{ $formReadonly ? 'bg-gray-200 text-gray-500' : '' }}"
                                        required {{ $formReadonly ? 'readonly' : '' }} />

                                    <!-- Sales Order -->
                                    <label for="penjualan_id" class="text-gray-800 font-medium flex items-center">
                                        Pesan Penjualan <span class="text-red-600 ml-1">*</span>
                                        <span class="ml-1" data-toggle="tooltip" data-placement="top"
                                            title="Silakan cari & pilih nomor pesanan penjualan dari dropdown"
                                            style="cursor: help;">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                    <div class="relative max-w-[300px] w-full">
                                        <div class="flex items-center border border-gray-300 rounded overflow-hidden">
                                            <input id="penjualan_id" name="penjualan_id" type="search"
                                                class="flex-grow outline-none px-2 py-1 text-sm"
                                                placeholder="Cari/Pilih Sales Order..." required />
                                            <button type="button" id="sales-search-btn"
                                                class="px-2 text-gray-600 hover:text-gray-900">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Dropdown akan muncul di sini -->
                                        <div id="dropdown-sales"
                                            class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded shadow mt-1 hidden max-h-40 overflow-y-auto text-sm">
                                        </div>
                                    </div>

                                    <!-- Pelanggan (Auto-filled) -->
                                    <label for="pelanggan_display" class="text-gray-800 font-medium flex items-center">
                                        Pelanggan<span class="text-red-600 ml-1">*</span>
                                    </label>
                                    <input id="pelanggan_display" type="text"
                                        class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6]"
                                        placeholder="Pelanggan Terisi Otomatis" readonly />
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                    <!-- Nomor Pengiriman -->
                                    <label for="no_pengiriman" class="text-gray-800 font-medium flex items-center">
                                        Nomor Pengiriman<span class="text-red-600 ml-1">*</span>
                                    </label>
                                    <input id="no_pengiriman" name="no_pengiriman" type="text" value="{{ $no_pengiriman }}"
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

                    <!-- Bagian Scan Barcode (Sembunyi by Default) -->
                    <div id="barcode-scan-section" class="m-2 p-4 border border-blue-300 bg-blue-50 rounded hidden">
                        <h3 class="font-bold text-blue-800 mb-2"><i class="fas fa-barcode mr-2"></i>Validasi Scan Barcode
                            Fisik</h3>

                        <div id="scan-physical-container">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Scan Barcode Fisik</label>
                            <div class="flex items-center gap-2 mb-2">
                                <input type="text" id="scan_barcode_input"
                                    class="border border-green-400 rounded px-3 py-2 text-sm bg-white w-full max-w-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="Arahkan kursor kesini, lalu Scan Barcode..." autocomplete="off"
                                    onkeypress="handleOutboundScan(event)">
                                <a href="{{ route('pengiriman_pesanan.index') }}"
                                    class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 text-sm font-medium shadow-sm transition-all"
                                    style="background-color: #dc3545 !important; color: white !important;"><i
                                        class="fas fa-arrow-left mr-1"></i> Kembali</a>
                            </div>
                            <div class="hidden">
                                <span id="scan-status-text">0 / 0 Kuantitas Terpenuhi</span>
                                <div id="scan-progress-bar" style="width: 0%"></div>
                            </div>
                            <p id="scan-success-message" class="text-sm font-bold text-green-600 mt-2 hidden"><i
                                    class="fas fa-check-circle mr-1"></i> Validasi Spesifikasi 100% Cocok! Silakan tekan
                                tombol Save Pengiriman Pesanan.</p>
                        </div>
                    </div>

                    <!-- Table container -->
                    <div class="p-2 flex flex-col gap-2">
                        <div class="border border-gray-300 rounded overflow-hidden text-sm">
                            <div
                                class="flex justify-between items-center border border-gray-300 rounded-t bg-[#f9f9f9] px-2 py-2 text-sm">
                                <!-- Search kiri -->
                                <div class="flex items-center border rounded px-2 py-1 w-[280px]">
                                    <input id="search-barang" class="flex-grow outline-none placeholder-gray-400"
                                        placeholder="Cari/Pilih Barang & Jasa..." type="text" />
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
                                        <th class="border border-gray-400 px-2 py-1 bg-green-800">
                                            Qty Scan
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
                                                <td class="border border-gray-400 px-2 py-3 align-top text-gray-400" data-scan-qty>
                                                    <span class="scan-text">0 / {{ $item['quantity'] }}</span>
                                                </td>
                                                <td class="border border-gray-400 px-2 py-3 align-top">
                                                    {{ $item['itemUnit']['name'] }}
                                                </td>
                                                <td class="border border-gray-400 px-2 py-3 align-top text-right">
                                                    Rp. {{ number_format($item['unitPrice'], 0, ',', '.') }}
                                                </td>
                                                <td class="border border-gray-400 px-2 py-3 align-top text-right">
                                                    {{ $item['itemCashDiscount'] }}
                                                </td>
                                                <td class="border border-gray-400 px-2 py-3 align-top text-right font-semibold">
                                                    Rp. {{ number_format($item['totalPrice'], 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td class="border border-gray-400 px-2 py-3 text-left align-top">
                                                ≡
                                            </td>
                                            <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="8">
                                                Belum ada data
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="flex justify-end mt-2 gap-2">
                            <input type="hidden" id="is_partial_input" name="is_partial" value="0">
                            <button type="button" id="btn-save-partial"
                                class="bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50"
                                disabled>
                                <i class="fas fa-shipping-fast mr-2"></i>Kirim Sebagian
                            </button>
                            <button type="button" id="btn-save-pengiriman-pesanan"
                                class="bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50"
                                disabled>
                                <i class="fas fa-lock mr-2"></i>Kirim Semua (Selesaikan Scan)
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Data sales orders dari controller
        const salesOrdersData = JSON.parse(`{!! addslashes(json_encode($salesOrders)) !!}`);
        console.log('Sales Orders data:', salesOrdersData);

        // Variabel untuk menyimpan data form
        let formData = {
            penjualan_id: '',
            pelanggan_id: '',
            pelanggan_display: '', // Untuk display di UI
            tanggal_pengiriman: '',
            no_pengiriman: '',
            so_trans_date: '', // To store SO date for validation
            detailItems: []
        };

        // Variabel untuk menyimpan detail items
        let detailItems = [];

        // Cache serial numbers dari Accurate (diisi saat klik Lanjut)
        let serialNumberCache = [];

        // Variabel validasi barcode
        let scannedItemsQuantities = {};
        // Menyimpan detail scan per item (barcode -> qty) untuk dikirim saat Save
        // Struktur: { [itemNo]: { [barcode]: qty } }
        let scannedSerialMap = {};
        let totalTargetQuantity = 0;
        let totalScannedQuantity = 0;
        let isScanning = false;

        /**
         * Selaras dengan server: bagian sebelum ';', trim, maks. 10 karakter pertama.
         * Contoh: YB00315819;32,222;9,3 → YB00315819
         */
        function normalizeScannedBarcode(raw) {
            let s = String(raw ?? '').trim();
            if (!s) return '';
            const semi = s.indexOf(';');
            if (semi !== -1) {
                s = s.slice(0, semi).trim();
            }
            if (s.length > 10) {
                s = s.slice(0, 10);
            }
            return s;
        }

        function roundQty2(v) {
            const n = Number(v);
            if (!isFinite(n)) return 0;
            return Math.round(n * 100) / 100;
        }

        function formatQty2(v) {
            const n = Number(v);
            if (!isFinite(n)) return '0,00';
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(n);
        }

        // Function untuk show dropdown sales order
        function showDropdownSalesOrder(input) {
            const dropdownSalesOrder = document.getElementById('dropdown-sales');
            const query = input.value.toLowerCase().trim();

            console.log('Searching Sales Order with query:', query);

            // Jika query kosong, panggil showAllSalesOrders
            if (query === '') {
                showAllSalesOrders();
                return;
            }

            const resultSalesOrders = salesOrdersData.filter(so => {
                const matchNumber = so.number.toLowerCase().includes(query);
                const matchCustomerName = so.customer && so.customer.name &&
                    so.customer.name.toLowerCase().includes(query);
                const matchCustomerNo = so.customer && so.customer.customerNo &&
                    so.customer.customerNo.toLowerCase().includes(query);

                return matchNumber || matchCustomerName || matchCustomerNo;
            });

            dropdownSalesOrder.innerHTML = '';

            if (resultSalesOrders.length === 0) {
                const noResultItem = document.createElement('div');
                noResultItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
                noResultItem.innerHTML = `<i class="fas fa-search mr-2"></i>Tidak ada sales order yang cocok dengan "${query}"`;
                dropdownSalesOrder.appendChild(noResultItem);
            } else {
                // Tambahkan header hasil pencarian
                const headerItem = document.createElement('div');
                headerItem.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-sm text-blue-700';
                headerItem.innerHTML = `<i class="fas fa-search mr-2"></i>Hasil Pencarian: ${resultSalesOrders.length} Sales Order`;
                dropdownSalesOrder.appendChild(headerItem);

                resultSalesOrders.forEach(so => {
                    const item = document.createElement('div');
                    item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                    const number = document.createElement('div');
                    number.className = 'font-semibold text-sm text-gray-800';
                    // Highlight matching text untuk number
                    const highlightedNumber = so.number.replace(
                        new RegExp(`(${query})`, 'gi'),
                        '<mark class="bg-yellow-200">$1</mark>'
                    );
                    number.innerHTML = highlightedNumber;

                    const customerInfo = document.createElement('div');
                    customerInfo.className = 'text-sm text-gray-500';

                    // Format customer information dengan highlight
                    if (so.customer && so.customer.name && so.customer.customerNo) {
                        let customerText = `${so.customer.name} (${so.customer.customerNo})`;

                        // Highlight matching text pada customer name dan customerNo
                        customerText = customerText.replace(
                            new RegExp(`(${query})`, 'gi'),
                            '<mark class="bg-yellow-200">$1</mark>'
                        );

                        customerInfo.innerHTML = customerText;
                    } else if (so.customer && so.customer.name) {
                        let customerText = so.customer.name;
                        customerText = customerText.replace(
                            new RegExp(`(${query})`, 'gi'),
                            '<mark class="bg-yellow-200">$1</mark>'
                        );
                        customerInfo.innerHTML = customerText;
                    } else {
                        customerInfo.textContent = 'Customer tidak tersedia';
                    }

                    item.appendChild(number);
                    item.appendChild(customerInfo);

                    item.onclick = () => {
                        input.value = so.number;
                        dropdownSalesOrder.classList.add('hidden');

                        // Update formData
                        formData.penjualan_id = so.number;

                        // Get customer data via AJAX (hanya update customer)
                        getCustomerByAjax(so.number, true);

                        console.log('Sales Order selected:', so.number);
                    };

                    dropdownSalesOrder.appendChild(item);
                });
            }

            dropdownSalesOrder.classList.remove('hidden');
            console.log('Sales Order dropdown shown with', resultSalesOrders.length, 'results');
        }

        // Function untuk menampilkan semua sales orders
        function showAllSalesOrders() {
            const dropdownSalesOrder = document.getElementById('dropdown-sales');

            console.log('Menampilkan semua Sales Orders, total:', salesOrdersData.length);

            dropdownSalesOrder.innerHTML = '';

            if (salesOrdersData.length === 0) {
                const noDataItem = document.createElement('div');
                noDataItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
                noDataItem.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Tidak ada data sales order';
                dropdownSalesOrder.appendChild(noDataItem);
            } else {
                // Tambahkan header info
                const headerItem = document.createElement('div');
                headerItem.className = 'px-3 py-2 bg-gray-50 border-b font-semibold text-sm text-gray-700';
                headerItem.innerHTML = `<i class="fas fa-list mr-2"></i>Semua Sales Order (${salesOrdersData.length})`;
                dropdownSalesOrder.appendChild(headerItem);

                // Tampilkan semua sales orders (batas maksimal untuk performa)
                const maxShow = 50;
                const soToShow = salesOrdersData.slice(0, maxShow);

                soToShow.forEach(so => {
                    const item = document.createElement('div');
                    item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                    const number = document.createElement('div');
                    number.className = 'font-semibold text-sm text-gray-800';
                    number.textContent = so.number;

                    const customerInfo = document.createElement('div');
                    customerInfo.className = 'text-sm text-gray-500';

                    // Format customer information
                    if (so.customer && so.customer.name && so.customer.customerNo) {
                        customerInfo.textContent = `${so.customer.name} (${so.customer.customerNo})`;
                    } else if (so.customer && so.customer.name) {
                        customerInfo.textContent = so.customer.name;
                    } else {
                        customerInfo.textContent = 'Customer tidak tersedia';
                    }

                    item.appendChild(number);
                    item.appendChild(customerInfo);

                    item.onclick = () => {
                        const penjualanInput = document.getElementById('penjualan_id');
                        penjualanInput.value = so.number;
                        dropdownSalesOrder.classList.add('hidden');

                        // Update formData
                        formData.penjualan_id = so.number;

                        // Get customer data via AJAX (hanya update customer)
                        getCustomerByAjax(so.number, true);

                        console.log('Sales Order selected:', so.number);
                    };

                    dropdownSalesOrder.appendChild(item);
                });

                // Jika ada lebih banyak data, tampilkan info
                if (salesOrdersData.length > maxShow) {
                    const moreInfoItem = document.createElement('div');
                    moreInfoItem.className = 'px-3 py-2 bg-blue-50 border-b text-sm text-blue-600 text-center';
                    moreInfoItem.innerHTML = `<i class="fas fa-info-circle mr-2"></i>Menampilkan ${maxShow} dari ${salesOrdersData.length} sales order. Ketik untuk pencarian spesifik.`;
                    dropdownSalesOrder.appendChild(moreInfoItem);
                }
            }

            dropdownSalesOrder.classList.remove('hidden');
            console.log('All Sales Orders dropdown shown');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const errorContainer = document.getElementById('laravel-errors');
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

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

        // Function untuk handle tombol search sales order
        function handleSearchSalesOrderClick(event) {
            event.preventDefault();
            event.stopPropagation();

            const penjualanInput = document.getElementById('penjualan_id');
            const dropdownSalesOrder = document.getElementById('dropdown-sales');

            console.log('Search Sales Order button clicked');
            console.log('Current input value:', penjualanInput.value);

            // Jika input kosong atau hanya whitespace, tampilkan semua sales orders
            const query = penjualanInput.value.trim();

            if (query === '') {
                console.log('Input kosong, menampilkan semua Sales Orders');
                showAllSalesOrders();
            } else {
                console.log('Input tidak kosong, melakukan pencarian dengan query:', query);
                showDropdownSalesOrder(penjualanInput);
            }
        }

        function handleOutboundScan(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                
                if (isScanning) {
                    console.warn('Scan ignored: processing previous scan...');
                    return;
                }

                const input = document.getElementById('scan_barcode_input');
                const raw = input.value;
                const barcode = normalizeScannedBarcode(raw);
                if (!barcode) {
                    input.value = '';
                    return;
                }
                // Supaya input tidak sempat menyisakan format panjang seperti ";32,222;9,3"
                input.value = barcode;
                input.value = '';

                // Immediate clear for fast scanners
                input.value = '';
                input.focus();

                // Check in local cache first
                const cachedEntry = serialNumberCache.find(sn => sn.barcode === barcode);
                if (cachedEntry) {
                    input.focus();
                    handleCachedScan(barcode);
                } else {
                    // Fallback: scan via Accurate API in real-time
                    isScanning = true;
                    input.disabled = true;
                    handleAccurateScan(barcode, input);
                }
            }
        }

        function handleCachedScan(barcode) {
            const cachedEntry = serialNumberCache.find(sn => sn.barcode === barcode);

            if (!cachedEntry) {
                Swal.fire({ icon: 'error', title: 'Tidak Ditemukan', text: `Barcode (${barcode}) tidak ada di dalam daftar serial number item SO!` });
                return;
            }

            const matchedItemIndex = detailItems.findIndex(i =>
                i.item && i.item.no === cachedEntry.itemNo
            );

            if (matchedItemIndex === -1) {
                Swal.fire({ icon: 'error', title: 'Tidak Cocok', text: `Item (${cachedEntry.itemName}) tidak ditemukan di detail SO!` });
                return;
            }

            processScanResult(matchedItemIndex, cachedEntry.quantity, barcode, cachedEntry.itemName);
        }

        function handleAccurateScan(barcode, input) {
            const itemNos = detailItems.map(i => i.item?.no).filter(n => n);
            
            fetch('{{ route("pengiriman_pesanan.scan_accurate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ 
                    barcode: barcode,
                    itemNos: itemNos
                })
            })
                .then(res => res.json())
                .then(data => {
                    isScanning = false;
                    input.disabled = false;
                    input.focus();

                    if (data.success) {
                        const matchedItemIndex = detailItems.findIndex(i => 
                            i.item && i.item.no === data.data.itemNo
                        );

                        if (matchedItemIndex !== -1) {
                            processScanResult(matchedItemIndex, data.data.quantity, barcode, data.data.itemName);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Tidak Cocok', text: `Barang (${data.data.itemName}) tidak ada di dalam detail pesanan SO!` });
                        }
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error Scan', text: data.message });
                    }
                })
                .catch(err => {
                    isScanning = false;
                    input.disabled = false;
                    input.focus();
                    Swal.fire({ icon: 'error', title: 'System Error', text: err.toString() });
                });
        }

        function processScanResult(matchedItemIndex, barcodeQty, barcode, itemName) {
            let requiredQty = parseFloat(detailItems[matchedItemIndex].quantity || 0);
            let kodeUnik = (detailItems[matchedItemIndex].item.no || '').trim();
            let currentScanned = scannedItemsQuantities[kodeUnik] || 0;

            // Simpan detail serial yang discan (per itemNo)
            if (!scannedSerialMap[kodeUnik]) scannedSerialMap[kodeUnik] = {};

            let existingQty = scannedSerialMap[kodeUnik][barcode] || 0;
            let addedQty = 0;

            if (existingQty > 0) {
                // Barcode sudah pernah discan
                if (existingQty >= barcodeQty) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Quantity sudah max',
                        text: `Barcode ${barcode} sudah memiliki kuantitas maksimal (${barcodeQty}).`,
                        timer: 2000,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false
                    });
                    return;
                } else {
                    // Top-up behavior: Kembalikan ke qty aslinya
                    addedQty = barcodeQty - existingQty;
                    scannedSerialMap[kodeUnik][barcode] = barcodeQty; // Set to full
                }
            } else {
                // Barcode baru
                addedQty = barcodeQty;
                scannedSerialMap[kodeUnik][barcode] = barcodeQty;
            }

            let newQty = currentScanned + addedQty;
            newQty = Math.round(newQty * 100) / 100;
            requiredQty = Math.round(requiredQty * 100) / 100;

            if (newQty > requiredQty) {
                // Set maksimal sesuai sisa yang bisa dikirim
                const maxAllowed = requiredQty - (currentScanned - (existingQty > 0 ? existingQty : 0));

                if (maxAllowed <= 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sudah Penuh',
                        text: `Item ini sudah mencapai limit SO (${requiredQty}).`,
                        timer: 2000,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false
                    });
                    // Revert perubahan ke scannedSerialMap karena entry barcode sempat diset penuh
                    // tapi seharusnya tidak masuk (total sudah mencapai limit).
                    if (existingQty > 0) {
                        scannedSerialMap[kodeUnik][barcode] = existingQty;
                    } else {
                        delete scannedSerialMap[kodeUnik][barcode];
                        if (Object.keys(scannedSerialMap[kodeUnik]).length === 0) {
                            delete scannedSerialMap[kodeUnik];
                        }
                    }
                    return;
                }

                // Set ke maksimal yang diperbolehkan
                scannedSerialMap[kodeUnik][barcode] = maxAllowed;
                addedQty = maxAllowed - (existingQty > 0 ? existingQty : 0);
                newQty = requiredQty;
            }

            scannedItemsQuantities[kodeUnik] = newQty;
            totalScannedQuantity = Object.values(scannedItemsQuantities).reduce((a, b) => a + b, 0);
            totalScannedQuantity = Math.round(totalScannedQuantity * 100) / 100;

            updateScanProgress();

            Swal.fire({
                icon: 'success',
                title: addedQty > 0 && existingQty > 0 ? 'Quantity di-Update' : 'Barcode Valid',
                text: `${barcode} → ${itemName} (${barcodeQty})`,
                timer: 1500,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });

            let rows = document.getElementById('table-barang-body').getElementsByTagName('tr');
            if (rows[matchedItemIndex]) {
                rows[matchedItemIndex].classList.add('bg-green-100');
                setTimeout(() => rows[matchedItemIndex].classList.remove('bg-green-100'), 1500);
            }
        }

        function updateScanProgress() {
            totalTargetQuantity = detailItems.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);
            totalTargetQuantity = Math.round(totalTargetQuantity * 100) / 100;

            let perc = 0;
            if (totalTargetQuantity > 0) {
                perc = (totalScannedQuantity / totalTargetQuantity) * 100;
                if (perc > 100) perc = 100;
            }

            document.getElementById('scan-status-text').innerText = `${formatQty2(totalScannedQuantity)} / ${formatQty2(totalTargetQuantity)} Kuantitas`;
            document.getElementById('scan-progress-bar').style.width = perc + '%';

            const btnAll = document.getElementById('btn-save-pengiriman-pesanan');
            const btnPart = document.getElementById('btn-save-partial');

            if (perc >= 100 && totalTargetQuantity > 0) {
                // 100% — aktifkan Kirim Semua, nonaktifkan Kirim Sebagian
                document.getElementById('scan-success-message').classList.remove('hidden');
                btnAll.disabled = false;
                btnAll.className = 'bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm';
                btnAll.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Kirim Semua';

                btnPart.disabled = true;
                btnPart.className = 'bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50';
                btnPart.innerHTML = '<i class="fas fa-shipping-fast mr-2"></i>Kirim Sebagian';

            } else if (perc > 0 && totalTargetQuantity > 0) {
                // Ada yang discan tapi belum 100% — aktifkan Kirim Sebagian
                document.getElementById('scan-success-message').classList.add('hidden');
                btnAll.disabled = true;
                btnAll.className = 'bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50';
                btnAll.innerHTML = '<i class="fas fa-lock mr-2"></i>Kirim Semua (Selesaikan Scan)';

                btnPart.disabled = false;
                btnPart.className = 'bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded text-sm';
                btnPart.innerHTML = '<i class="fas fa-shipping-fast mr-2"></i>Kirim Sebagian (' + Math.round(perc) + '%)';

            } else {
                // Belum scan sama sekali — dua tombol terkunci
                document.getElementById('scan-success-message').classList.add('hidden');
                btnAll.disabled = true;
                btnAll.className = 'bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50';
                btnAll.innerHTML = '<i class="fas fa-lock mr-2"></i>Kirim Semua (Selesaikan Scan)';

                btnPart.disabled = true;
                btnPart.className = 'bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50';
                btnPart.innerHTML = '<i class="fas fa-shipping-fast mr-2"></i>Kirim Sebagian';
            }

            // Update tampilan qty scan per baris di tabel
            updateTableScanQty();
        }

        function updateTableScanQty() {
            const rows = document.getElementById('table-barang-body').getElementsByTagName('tr');
            console.log(`Updating table scan qty. Detail items: ${detailItems.length}, TR rows: ${rows.length}`);

            detailItems.forEach((item, index) => {
                if (!rows[index]) return;
                const kode = (item.item?.no || '').trim();
                const scanned = scannedItemsQuantities[kode] || 0;
                const total = parseFloat(item.quantity || 0);
                const scanCell = rows[index].querySelector('[data-scan-qty]');

                if (scanCell) {
                    const scanSpan = scanCell.querySelector('.scan-text');
                    const text = `${formatQty2(scanned)} / ${formatQty2(total)}`;
                    if (scanSpan) {
                        scanSpan.textContent = text;
                    } else {
                        scanCell.textContent = text;
                    }

                    // Highlight
                    rows[index].classList.remove('bg-green-50', 'bg-yellow-50');
                    if (scanned > 0 && scanned >= total) {
                        rows[index].classList.add('bg-green-50');
                        scanCell.classList.add('text-green-700');
                        scanCell.classList.remove('text-orange-600', 'text-gray-400');
                        if (scanSpan) scanSpan.className = 'scan-text text-green-700';
                    } else if (scanned > 0) {
                        rows[index].classList.add('bg-yellow-50');
                        scanCell.classList.add('text-orange-600');
                        scanCell.classList.remove('text-green-700', 'text-gray-400');
                        if (scanSpan) scanSpan.className = 'scan-text text-orange-600';
                    } else {
                        scanCell.classList.add('text-gray-400');
                        scanCell.classList.remove('text-green-700', 'text-orange-600');
                        if (scanSpan) scanSpan.className = 'scan-text text-gray-400';
                    }
                }
            });
        }

        function resetOutboundScan() {
            scannedItemsQuantities = {};
            totalScannedQuantity = 0;
            updateScanProgress();
            document.getElementById('scan_barcode_input').focus();
        }

        // Function untuk get customer data by AJAX
        function getCustomerByAjax(salesOrderNumber, updateCustomerOnly = true) {
            const pelangganInput = document.getElementById('pelanggan_display');
            const pelangganHiddenInput = document.getElementById('pelanggan_id_hidden');

            // Cari data sales order lokal terlebih dahulu
            const selectedSalesOrder = salesOrdersData.find(so => so.number === salesOrderNumber);

            if (selectedSalesOrder && selectedSalesOrder.customer && selectedSalesOrder.customer.name && updateCustomerOnly) {
                // Gunakan data lokal jika tersedia dan lengkap untuk update customer saja
                const customerDisplay = selectedSalesOrder.customer.customerNo ?
                    `${selectedSalesOrder.customer.name} (${selectedSalesOrder.customer.customerNo})` :
                    selectedSalesOrder.customer.name;

                const customerNo = selectedSalesOrder.customer.customerNo || selectedSalesOrder.customer.name;

                pelangganInput.value = customerDisplay;
                pelangganHiddenInput.value = customerNo; // Update hidden input dengan customer number
                formData.pelanggan_id = customerNo; // Hanya customer number untuk controller
                formData.pelanggan_display = customerDisplay; // Full info untuk display

                console.log('Customer updated from local data:', customerDisplay);
                return Promise.resolve({
                    success: true,
                    customerDisplay: customerDisplay,
                    customerNo: customerNo,
                    detailItems: []
                });
            }

            // Jika data lokal tidak lengkap atau butuh detail items, lakukan AJAX call
            if (updateCustomerOnly) {
                pelangganInput.value = 'Loading...';
            }
            console.log('Getting customer data via AJAX for sales order:', salesOrderNumber);

            // AJAX call ke controller
            return fetch(`/pengiriman-pesanan/customer/${salesOrderNumber}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Customer data received from AJAX:', data);

                    if (data.success) {
                        // Update customer field
                        let customerDisplay;
                        let customerNo;

                        if (data.customerName && data.customerNo) {
                            customerDisplay = `${data.customerName} (${data.customerNo})`;
                            customerNo = data.customerNo;
                        } else if (data.customerName) {
                            customerDisplay = data.customerName;
                            customerNo = data.customerName;
                        } else if (data.customerNo) {
                            customerDisplay = data.customerNo;
                            customerNo = data.customerNo;
                        } else {
                            customerDisplay = 'Customer tidak ditemukan';
                            customerNo = '';
                        }

                        if (updateCustomerOnly) {
                            pelangganInput.value = customerDisplay;
                            pelangganHiddenInput.value = customerNo; // Update hidden input dengan customer number

                            // Show customer loaded notification for AJAX
                            Swal.fire({
                                icon: 'success',
                                title: 'Customer Data Dimuat!',
                                text: `Data pelanggan ${customerDisplay} berhasil dimuat dari server`,
                                timer: 2500,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end'
                            });
                        }
                        formData.pelanggan_id = customerNo; // Hanya customer number untuk controller
                        formData.pelanggan_display = customerDisplay; // Full info untuk display

                        console.log('Customer updated from AJAX:', customerDisplay);

                        return {
                            success: true,
                            customerDisplay: customerDisplay,
                            customerNo: customerNo,
                            detailItems: data.detailItems || [],
                            serialNumberCache: data.serialNumberCache || [],
                            transDate: data.transDate || ''
                        };
                    } else {
                        if (updateCustomerOnly) {
                            pelangganInput.value = 'Error: ' + (data.message || 'Gagal mengambil data customer');
                            pelangganHiddenInput.value = ''; // Clear hidden input on error
                        }
                        console.error('Error from server:', data.message);

                        return {
                            success: false,
                            message: data.message || 'Gagal mengambil data customer',
                            customerDisplay: '',
                            customerNo: '',
                            detailItems: []
                        };
                    }
                })
                .catch(error => {
                    console.error('Error fetching customer data:', error);
                    if (updateCustomerOnly) {
                        pelangganInput.value = 'Error: Gagal mengambil data customer';
                        pelangganHiddenInput.value = ''; // Clear hidden input on error
                    }

                    return {
                        success: false,
                        message: 'Error: Gagal mengambil data customer',
                        customerDisplay: '',
                        customerNo: '',
                        detailItems: []
                    };
                });
        }

        // Function untuk handle button Lanjut
        function handleLanjutButton() {
            const penjualanInput = document.getElementById('penjualan_id');
            const pelangganInput = document.getElementById('pelanggan_display');
            const pelangganHiddenInput = document.getElementById('pelanggan_id_hidden');
            const tanggalInput = document.getElementById('tanggal_pengiriman');
            const noPengirimanInput = document.getElementById('no_pengiriman');
            const btnLanjut = document.getElementById('btn-lanjut');

            // Validasi input
            if (!penjualanInput.value.trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan!',
                    text: 'Silakan pilih Sales Order terlebih dahulu!',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return;
            }

            if (!tanggalInput.value.trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan!',
                    text: 'Silakan isi Tanggal Pengiriman terlebih dahulu!',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return;
            }

            // Validasi: DO date cannot be before SO date
            // Perform this validation AFTER getCustomerByAjax returns (handled inside then)
            // But we can also check if we already have the date if they changed the date after clicking lanjut once

            // Update formData
            formData.penjualan_id = penjualanInput.value;
            formData.tanggal_pengiriman = tanggalInput.value;
            formData.no_pengiriman = noPengirimanInput.value;

            console.log('Button Lanjut clicked, form data:', formData);

            // Show loading state
            btnLanjut.disabled = true;
            btnLanjut.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';

            // Get customer data and detail items via AJAX
            getCustomerByAjax(formData.penjualan_id, false)
                .then(response => {
                    console.log('Lanjut button response:', response);

                    if (response.success) {
                        // VALIDASI TANGGAL: Cek jika tanggal pengiriman < tanggal SO
                        if (response.transDate) {
                            const soDateStr = response.transDate; // Format DD/MM/YYYY dari Accurate
                            const [day, month, year] = soDateStr.split('/');
                            const soDate = new Date(year, month - 1, day);

                            const doDateParts = formData.tanggal_pengiriman.split('-'); // YYYY-MM-DD from input
                            const doDate = new Date(doDateParts[0], doDateParts[1] - 1, doDateParts[2]);

                            if (doDate < soDate) {
                                btnLanjut.disabled = false;
                                btnLanjut.innerHTML = 'Lanjut';

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Validasi Gagal!',
                                    text: `Tanggal Pengiriman Pesanan tidak dapat lebih kecil dari tanggal Pesanan Penjualan ${formData.penjualan_id}.`,
                                    confirmButtonText: 'Oke'
                                });
                                return;
                            }
                        }

                        // Update customer field
                        pelangganInput.value = response.customerDisplay;
                        pelangganHiddenInput.value = response.customerNo; // Update hidden input dengan customer number
                        formData.pelanggan_id = response.customerNo;
                        formData.pelanggan_display = response.customerDisplay;

                        // Store detail items
                        detailItems = response.detailItems;

                        // Store serial number cache dari Accurate
                        serialNumberCache = response.serialNumberCache || [];
                        console.log('Serial number cache loaded:', serialNumberCache.length, 'barcodes');

                        // Store SO trans date for validation
                        formData.so_trans_date = response.transDate;

                        // Fill table with detail items
                        fillTableWithDetailItems(detailItems);

                        // Set form inputs to readonly
                        setFormReadonly(true);

                        // Reset Scan State to fresh start
                        scannedItemsQuantities = {};
                        scannedSerialMap = {};
                        totalScannedQuantity = 0;
                        isScanning = false;

                        // Hide button Lanjut
                        btnLanjut.style.display = 'none';

                        // Show scan section & initial check
                        document.getElementById('barcode-scan-section').classList.remove('hidden');
                        updateScanProgress();

                        // Show success notification
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: `Data sales order berhasil dimuat dengan ${detailItems.length} item barang`,
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });

                        console.log('Form successfully processed with', detailItems.length, 'items');
                    } else {
                        // Reset button state on error
                        btnLanjut.disabled = false;
                        btnLanjut.innerHTML = 'Lanjut';

                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Memproses Data!',
                            text: response.message || 'Gagal memproses data',
                            timer: 4000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error in handleLanjutButton:', error);

                    // Reset button state on error
                    btnLanjut.disabled = false;
                    btnLanjut.innerHTML = 'Lanjut';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error Sistem!',
                        text: 'Terjadi error saat memproses data. Silakan coba lagi.',
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                });
        }

        // Function untuk mengisi table dengan detail items
        function fillTableWithDetailItems(items) {
            const tableBody = document.getElementById('table-barang-body');

            if (!tableBody) {
                console.error('Table body not found');
                return;
            }

            // Clear existing rows
            tableBody.innerHTML = '';

            if (!items || items.length === 0) {
                // Show empty state
                const emptyRow = document.createElement('tr');
                emptyRow.innerHTML = `
                                                                <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
                                                                <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="8">
                                                                    Belum ada data barang
                                                                </td>
                                                            `;
                tableBody.appendChild(emptyRow);
                console.log('Table filled with empty state');
                return;
            }

            items.forEach((item, index) => {
                const row = document.createElement('tr');
                row.onclick = () => openItemDetailModal(index);

                // Format currency values
                const unitPrice = formatCurrency(item.unitPrice || 0);
                const discount = formatCurrency(item.itemCashDiscount || 0);
                const totalPrice = formatCurrency(item.totalPrice || 0);

                row.innerHTML = `
                                                                <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
                                                                <td class="border border-gray-400 px-2 py-3 text-left align-top font-medium">
                                                                    ${item.item?.name || 'N/A'}
                                                                </td>
                                                                <td class="border border-gray-400 px-2 py-3 align-top">
                                                                    ${item.item?.no || 'N/A'}
                                                                </td>
                                                                <td class="border border-gray-400 px-2 py-3 align-top">
                                                                    ${formatQty2(item.quantity || 0)}
                                                                </td>
                                                                <td class="border border-gray-400 px-2 py-3 align-top text-gray-400" data-scan-qty id="scan-qty-${index}">
                                                                    <div class="flex items-center justify-center">
                                                                        <span class="scan-text">0,00 / ${formatQty2(item.quantity || 0)}</span>
                                                                    </div>
                                                                </td>
                                                                <td class="border border-gray-400 px-2 py-3 align-top">
                                                                    ${item.itemUnit?.name || 'N/A'}
                                                                </td>
                                                                <td class="border border-gray-400 px-2 py-3 align-top text-right">
                                                                    ${unitPrice}
                                                                </td>
                                                                <td class="border border-gray-400 px-2 py-3 align-top text-right">
                                                                    ${discount}
                                                                </td>
                                                                <td class="border border-gray-400 px-2 py-3 align-top text-right font-semibold">
                                                                    ${totalPrice}
                                                                </td>
                                                            `;

                tableBody.appendChild(row);
            });

            console.log('Table filled with', items.length, 'items');
        }

        // Function untuk set form menjadi readonly
        function setFormReadonly(readonly) {
            const inputs = [
                document.getElementById('tanggal_pengiriman'),
                document.getElementById('penjualan_id'),
                document.getElementById('pelanggan_display'),
                document.getElementById('no_pengiriman')
            ];

            inputs.forEach(input => {
                if (input) {
                    input.readOnly = readonly;
                    if (readonly) {
                        input.classList.add('bg-gray-200', 'text-gray-500');
                        input.classList.remove('bg-white');
                    } else {
                        input.classList.remove('bg-gray-200', 'text-gray-500');
                        input.classList.add('bg-white');
                    }
                }
            });

            // Disable search button
            const searchBtn = document.getElementById('sales-search-btn');
            if (searchBtn) {
                searchBtn.disabled = readonly;
                if (readonly) {
                    searchBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    searchBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            console.log('Form readonly state set to:', readonly);
        }

        // Function untuk update progress scan secara visual
        function updateScanProgress() {
            totalScannedQuantity = 0;
            totalTargetQuantity = 0;

            detailItems.forEach(item => {
                const kode = (item.item?.no || '').trim();
                const scannedQty = scannedItemsQuantities[kode] || 0;
                const targetQty = item.quantity || 0;

                totalScannedQuantity += scannedQty;
                totalTargetQuantity += targetQty;
            });

            const btnSave = document.getElementById('btn-save-pengiriman-pesanan');
            const btnSavePartial = document.getElementById('btn-save-partial');

            if (totalTargetQuantity > 0) {
                // Aktifkan tombol Kirim Sebagian jika ada yang discan (> 0)
                if (totalScannedQuantity > 0) {
                    btnSavePartial.disabled = false;
                    btnSavePartial.classList.remove('bg-gray-500', 'opacity-50', 'cursor-not-allowed');
                    btnSavePartial.classList.add('bg-orange-500', 'hover:bg-orange-600');
                } else {
                    btnSavePartial.disabled = true;
                    btnSavePartial.classList.add('bg-gray-500', 'opacity-50', 'cursor-not-allowed');
                    btnSavePartial.classList.remove('bg-orange-500', 'hover:bg-orange-600');
                }

                // Aktifkan tombol Kirim Semua jika scan lengkap (100%)
                if (totalScannedQuantity >= totalTargetQuantity) {
                    btnSave.disabled = false;
                    btnSave.classList.remove('bg-gray-500', 'opacity-50', 'cursor-not-allowed');
                    btnSave.classList.add('bg-green-600', 'hover:bg-green-700');
                    btnSave.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Kirim Semua';

                    btnSavePartial.disabled = true;
                    btnSavePartial.classList.add('bg-gray-500', 'opacity-50', 'cursor-not-allowed');
                    btnSavePartial.classList.remove('bg-orange-500', 'hover:bg-orange-600');
                    btnSavePartial.innerHTML = '<i class="fas fa-shipping-fast mr-2"></i>Kirim Sebagian';
                } else {
                    btnSave.disabled = true;
                    btnSave.classList.add('bg-gray-500', 'opacity-50', 'cursor-not-allowed');
                    btnSave.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btnSave.innerHTML = '<i class="fas fa-lock mr-2"></i>Kirim Semua (Selesaikan Scan)';
                }
            }

            updateTableScanQty();
        }

        // Function untuk membuka modal detail barang dengan 2 Tab
        function openItemDetailModal(index) {
            const item = detailItems[index];
            if (!item) return;

            const itemNo = (item.item?.no || '').trim();
            const itemName = item.item?.name || 'Barang';
            const maxQty = item.quantity || 0;

            Swal.fire({
                title: '',
                customClass: {
                    popup: 'item-detail-modal-vcentered !rounded-xl',
                    confirmButton: 'btn-lanjut-modal',
                    denyButton: 'btn-hapus-modal',
                    actions: 'item-detail-modal-actions'
                },
                showCloseButton: false,
                showConfirmButton: true,
                showDenyButton: true,
                confirmButtonText: 'Lanjut',
                denyButtonText: 'Hapus',
                buttonsStyling: false,
                reverseButtons: false,
                html: `
                                                        <div>
                                                            <div class="flex items-center justify-between px-6 pt-5 pb-3">
                                                                <h2 class="text-base font-semibold text-gray-800">Rincian Barang</h2>
                                                                <button type="button" id="close-modal-btn"
                                                                    class="text-gray-400 hover:text-gray-700 focus:outline-none transition-colors">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M6 18L18 6M6 6l12 12" />
                                                                    </svg>
                                                                </button>
                                                            </div>

                                                            <div class="flex gap-6 px-6 mb-4" id="modal-tabs">
                                                                <div class="modal-tab active cursor-pointer pb-2 border-b-2 border-red-600 text-red-600 font-bold"
                                                                    data-tab="rincian">Rincian Barang</div>
                                                                <div class="modal-tab cursor-pointer pb-2 text-gray-500 font-semibold"
                                                                    data-tab="seri">No Seri/Produksi</div>
                                                            </div>

                                                            <div class="px-6 pb-2">
                                                                <div id="tab-content-rincian" class="tab-content space-y-4">
                                                                    <div class="grid grid-cols-[120px_1fr] gap-4 items-center text-sm">
                                                                        <label class="text-gray-600">Kode #</label>
                                                                        <div class="font-semibold text-blue-700">${itemNo}</div>

                                                                        <label class="text-gray-600">Nama Barang</label>
                                                                        <div class="font-semibold">${itemName}</div>
                                                                    </div>
                                                                </div>

                                                                <div id="tab-content-seri" class="tab-content hidden space-y-4">
                                                                    <div class="grid grid-cols-[120px_1fr] gap-2 items-center text-sm">
                                                                        <label class="text-gray-600 font-bold">Nomor #</label>
                                                                        <div class="flex gap-2">
                                                                            <input type="text" id="modal-barcode-input" placeholder="Cari Barcode..."
                                                                                class="border border-gray-300 rounded px-2 py-1 flex-grow focus:outline-none focus:ring-1 focus:ring-blue-500">
                                                                        </div>
                                                                    </div>
                                                                    <div class="max-h-60 overflow-y-auto border border-gray-200 mt-4">
                                                                        <table class="w-full text-xs">
                                                                            <thead class="bg-[#166534] text-white sticky top-0">
                                                                                <tr>
                                                                                    <th class="p-2 border border-gray-300 text-center w-10"></th>
                                                                                    <th class="p-2 border border-gray-300 text-left">Nomor #</th>
                                                                                    <th class="p-2 border border-gray-300 text-center w-24">Kuantitas</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="modal-serial-table-body"></tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div id="modal-serial-summary" class="text-xs font-semibold text-gray-700 mt-2">
                                                                        0 No Seri/Produksi, Jumlah 0
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    `,
                didOpen: () => {
                    // ← Tambah listener untuk custom close button
                    document.getElementById('close-modal-btn').addEventListener('click', () => {
                        Swal.close();
                    });

                    const tabs = document.querySelectorAll('.modal-tab[data-tab]');
                    const contents = document.querySelectorAll('.tab-content');
                    const barcodeInput = document.getElementById('modal-barcode-input');

                    tabs.forEach(tab => {
                        tab.addEventListener('click', () => {
                            tabs.forEach(t => {
                                t.classList.remove('active', 'border-b-2', 'border-red-600', 'text-red-600', 'font-bold');
                                t.classList.add('text-gray-500');
                            });
                            tab.classList.add('active', 'border-b-2', 'border-red-600', 'text-red-600', 'font-bold');
                            tab.classList.remove('text-gray-500');

                            const target = tab.getAttribute('data-tab');
                            contents.forEach(c => c.classList.add('hidden'));
                            document.getElementById(`tab-content-${target}`).classList.remove('hidden');

                            if (target === 'seri') {
                                barcodeInput.focus();
                                renderSerialTable(itemNo, maxQty);
                            }
                        });
                    });

                    barcodeInput.addEventListener('input', () => {
                        renderSerialTable(itemNo, maxQty, barcodeInput.value.trim().toLowerCase());
                    });

                    renderSerialTable(itemNo, maxQty);
                }
            }).then((result) => {
                if (result.isDenied) {
                    // Logika Hapus: Reset semua scan untuk item ini
                    Swal.fire({
                        title: 'Hapus Semua Scan?',
                        text: `Seluruh hasil scan untuk item ${itemName} akan dihapus.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal'
                    }).then((delResult) => {
                        if (delResult.isConfirmed) {
                            delete scannedItemsQuantities[itemNo];
                            delete scannedSerialMap[itemNo];
                            refreshMainUI();
                            Swal.fire('Terhapus!', 'Data scan telah dibersihkan.', 'success');
                        }
                    });
                }
            });
        }

        // Helper untuk me-render tabel seri di dalam modal
        function renderSerialTable(itemNo, maxQty, filter = '') {
            const tbody = document.getElementById('modal-serial-table-body');
            const summary = document.getElementById('modal-serial-summary');
            if (!tbody) return;

            const map = scannedSerialMap[itemNo] || {};
            let barcodes = Object.keys(map);

            if (filter) {
                barcodes = barcodes.filter(bc => bc.toLowerCase().includes(filter));
            }

            tbody.innerHTML = '';

            if (barcodes.length === 0) {
                tbody.innerHTML = `<tr><td colspan="3" class="text-center p-4 text-gray-400">${filter ? 'Tidak ada barcode yang cocok' : 'Belum ada barcode discan'}</td></tr>`;
            } else {
                barcodes.forEach(barcode => {
                    const qty = map[barcode];
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-gray-100 transition-colors';
                    row.innerHTML = `
                                                                    <td class="p-2 border border-gray-200 text-center">
                                                                        <button type="button" class="btn-delete-barcode text-red-600 hover:text-red-800" data-barcode="${barcode}">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </td>
                                                                    <td class="p-2 border border-gray-200">${barcode}</td>
                                                                    <td class="p-1 border border-gray-200 text-center">
                                                                        <input type="number" class="w-16 text-center border-none bg-transparent focus:bg-white focus:ring-1 focus:ring-blue-500 qty-edit-input" 
                                                                            data-barcode="${barcode}" value="${roundQty2(qty).toFixed(2)}" step="any" min="1">
                                                                    </td>
                                                                `;
                    tbody.appendChild(row);
                });
            }

            // Bind event listener to delete buttons
            tbody.querySelectorAll('.btn-delete-barcode').forEach(btn => {
                btn.onclick = () => {
                    const bc = btn.getAttribute('data-barcode');
                    Swal.fire({
                        title: 'Hapus Barcode?',
                        text: `Anda yakin ingin menghapus barcode ${bc}?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            delete scannedSerialMap[itemNo][bc];
                            syncTotalQty(itemNo);
                            renderSerialTable(itemNo, maxQty, filter);
                            refreshMainUI();
                        }
                    });
                };
            });

            // Bind event listener to qty inputs
            tbody.querySelectorAll('.qty-edit-input').forEach(input => {
                input.onchange = (e) => {
                    const bc = input.getAttribute('data-barcode');
                    const newQty = parseFloat(String(input.value).replace(',', '.')) || 0;

                    if (newQty < 1) {
                        input.value = 1;
                        return;
                    }

                    const otherQtys = Object.keys(scannedSerialMap[itemNo])
                        .filter(k => k !== bc)
                        .reduce((sum, k) => sum + scannedSerialMap[itemNo][k], 0);

                    if (otherQtys + newQty > maxQty) {
                        Swal.showValidationMessage(`Total tidak boleh melebihi ${maxQty}`);
                        input.value = map[bc];
                        return;
                    }

                    scannedSerialMap[itemNo][bc] = newQty;
                    syncTotalQty(itemNo);
                    renderSerialTable(itemNo, maxQty, filter);
                    refreshMainUI();
                };
            });

            const actualTotalQty = Object.values(map).reduce((a, b) => a + b, 0);
            const actualCount = Object.keys(map).length;
            summary.textContent = `${actualCount} No Seri/Produksi, Jumlah ${formatQty2(actualTotalQty)}`;
        }

        // Helper sinkronisasi total qty per item
        function syncTotalQty(itemNo) {
            const cleanKey = (itemNo || '').trim();
            const map = scannedSerialMap[cleanKey] || {};
            scannedItemsQuantities[cleanKey] = Object.values(map).reduce((a, b) => a + b, 0);
            console.log(`Synced total qty for ${cleanKey}: ${scannedItemsQuantities[cleanKey]}`);
        }

        // Helper update UI utama dari modal
        function refreshMainUI() {
            updateTableScanQty();
            updateScanProgress();
        }


        // Function untuk format currency
        function formatCurrency(value) {
            if (value === null || value === undefined) return 'Rp. 0';

            const numValue = parseFloat(value);
            if (isNaN(numValue)) return 'Rp. 0';

            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(numValue);
        }

        // Format detail items sesuai kebutuhan controller
        // isPartial: jika true, submit HANYA item yang discan (qty = qty scan)
        //            jika false (kirim semua), qty = qty SO penuh
        function formatDetailItemsForSubmission(items, isPartial) {
            if (!items || items.length === 0) return [];

            const result = [];
            items.forEach(item => {
                const kode = item.item?.no || '';
                const scannedQty = scannedItemsQuantities[kode] || 0;

                if (isPartial) {
                    // Partial: hanya sertakan item yang memang discan
                    if (scannedQty <= 0) return;
                    result.push({
                        kode: kode,
                        kuantitas: scannedQty,
                        harga: item.unitPrice !== undefined && item.unitPrice !== null ? item.unitPrice : 0,
                        diskon: item.itemCashDiscount !== undefined && item.itemCashDiscount !== null ? item.itemCashDiscount : 0
                    });
                } else {
                    // Kirim semua: gunakan qty penuh dari SO
                    result.push({
                        kode: kode,
                        kuantitas: item.quantity || 0,
                        harga: item.unitPrice !== undefined && item.unitPrice !== null ? item.unitPrice : 0,
                        diskon: item.itemCashDiscount !== undefined && item.itemCashDiscount !== null ? item.itemCashDiscount : 0
                    });
                }
            });
            return result;
        }

        // Function untuk validasi form sebelum submit
        function validateFormData() {
            const errors = [];

            // Validasi data form utama
            if (!formData.penjualan_id || formData.penjualan_id.trim() === '') {
                errors.push('Sales Order harus dipilih');
            }

            if (!formData.tanggal_pengiriman || formData.tanggal_pengiriman.trim() === '') {
                errors.push('Tanggal Pengiriman harus diisi');
            }

            if (!formData.no_pengiriman || formData.no_pengiriman.trim() === '') {
                errors.push('Nomor Pengiriman harus diisi');
            }

            if (!formData.pelanggan_id || formData.pelanggan_id.trim() === '') {
                errors.push('Pelanggan harus dipilih');
            }

            // Validasi detail items
            if (!detailItems || detailItems.length === 0) {
                errors.push('Minimal harus ada 1 barang/jasa');
            }

            // Validasi setiap detail item
            detailItems.forEach((item, index) => {
                if (!item.item?.no) {
                    errors.push(`Barang ke-${index + 1}: Kode barang tidak valid`);
                }
                if (!item.item?.name) {
                    errors.push(`Barang ke-${index + 1}: Nama barang tidak valid`);
                }
            });

            return errors;
        }

        // Fungsi inti submit form
        function doSubmitForm(isPartial, saveButton) {
            const form = document.getElementById('pengirimanPesananForm');
            const formSubmittedInput = document.getElementById('form_submitted');

            // Validasi form
            const validationErrors = validateFormData();
            if (validationErrors.length > 0) {
                let errorMessages = '';
                validationErrors.forEach(error => { errorMessages += `<li>${error}</li>`; });
                Swal.fire({ icon: 'warning', title: 'Validasi Gagal!', html: `<ul class="text-left list-disc list-inside">${errorMessages}</ul>`, timer: 6000, timerProgressBar: true, showConfirmButton: false, toast: true, position: 'top-end' });
                return;
            }

            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Menyimpan...';

            try {
                const formattedDetailItems = formatDetailItemsForSubmission(detailItems, isPartial);

                if (formattedDetailItems.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Tidak Ada Item!', text: 'Tidak ada item yang telah discan untuk dikirim.', timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
                    saveButton.disabled = false;
                    saveButton.innerHTML = isPartial ? '<i class="fas fa-shipping-fast mr-2"></i>Kirim Sebagian' : '<i class="fas fa-check-circle mr-2"></i>Kirim Semua';
                    return;
                }

                // Set flag is_partial
                document.getElementById('is_partial_input').value = isPartial ? '1' : '0';

                // Buat hidden inputs untuk detail items
                form.querySelectorAll('input[name^="detailItems"]').forEach(i => i.remove());
                formattedDetailItems.forEach((item, index) => {
                    ['kode', 'kuantitas', 'harga', 'diskon'].forEach(field => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `detailItems[${index}][${field}]`;
                        input.value = item[field] !== undefined && item[field] !== null ? item[field] : '';
                        form.appendChild(input);
                    });
                });

                // Kirim serial map
                form.querySelectorAll('input[name="serials_json"]').forEach(i => i.remove());
                const serialMapInput = document.createElement('input');
                serialMapInput.type = 'hidden';
                serialMapInput.name = 'serials_json';
                serialMapInput.value = JSON.stringify(scannedSerialMap || {});
                form.appendChild(serialMapInput);

                formSubmittedInput.value = '1';
                form.submit();

            } catch (error) {
                console.error('Error during form submission:', error);
                saveButton.disabled = false;
                saveButton.innerHTML = isPartial ? '<i class="fas fa-shipping-fast mr-2"></i>Kirim Sebagian' : '<i class="fas fa-check-circle mr-2"></i>Kirim Semua';
                Swal.fire({ icon: 'error', title: 'Gagal Menyimpan!', text: 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.', timer: 4000, showConfirmButton: false, toast: true, position: 'top-end' });
            }
        }

        // Handle tombol Kirim Semua (100%)
        function handleSubmitForm() {
            const btnAll = document.getElementById('btn-save-pengiriman-pesanan');
            Swal.fire({
                title: 'Konfirmasi Simpan',
                text: 'Apakah Anda yakin ingin menyimpan data pengiriman pesanan ini ke Accurate?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Simpan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    doSubmitForm(false, btnAll);
                }
            });
        }

        // Handle tombol Kirim Sebagian
        function handleSubmitPartial() {
            const scannedCount = Object.keys(scannedItemsQuantities).filter(k => scannedItemsQuantities[k] > 0).length;
            const totalItems = detailItems.length;

            Swal.fire({
                icon: 'question',
                title: 'Konfirmasi Pengiriman Sebagian',
                html: `Anda akan mengirimkan <strong>${scannedCount} dari ${totalItems} jenis item</strong> dengan total <strong>${totalScannedQuantity} kuantitas</strong> yang telah discan.<br><br>Sisa item yang belum discan <strong>tidak akan dikirim</strong> dalam pengiriman ini.<br><br>Apakah Anda yakin?`,
                showCancelButton: true,
                confirmButtonColor: '#f97316',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Kirim Sebagian',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    doSubmitForm(true, document.getElementById('btn-save-partial'));
                }
            });
        }

        // Klik di luar dropdown
        document.addEventListener('click', function (e) {
            const penjualanWrapper = document.getElementById('penjualan_id')?.closest('.relative');

            if (penjualanWrapper && !penjualanWrapper.contains(e.target)) {
                document.getElementById('dropdown-sales')?.classList.add('hidden');
            }
        });

        // Initialize form on page load
        document.addEventListener('DOMContentLoaded', () => {
            const penjualanInput = document.getElementById('penjualan_id');
            const searchBtnSalesOrder = document.getElementById('sales-search-btn');
            const tanggalInput = document.getElementById('tanggal_pengiriman');
            const btnLanjut = document.getElementById('btn-lanjut');
            const btnSave = document.getElementById('btn-save-pengiriman-pesanan');

            console.log('Initializing pengiriman pesanan form...');

            if (searchBtnSalesOrder) {
                searchBtnSalesOrder.addEventListener('click', handleSearchSalesOrderClick);
                console.log('Search Sales Order button event listener added');
            }

            // Setup event listener untuk button Lanjut
            if (btnLanjut) {
                btnLanjut.addEventListener('click', handleLanjutButton);
                console.log('Button Lanjut event listener added');
            }

            // Setup event listener untuk button Save (Kirim Semua)
            if (btnSave) {
                btnSave.addEventListener('click', handleSubmitForm);
            }

            // Setup event listener untuk button Kirim Sebagian
            const btnSavePartial = document.getElementById('btn-save-partial');
            if (btnSavePartial) {
                btnSavePartial.addEventListener('click', handleSubmitPartial);
            }

            // Setup event listener untuk penjualan_id input
            if (penjualanInput) {
                penjualanInput.addEventListener('input', () => {
                    console.log('Sales Order input changed:', penjualanInput.value);
                    showDropdownSalesOrder(penjualanInput);
                });

                // Event listener untuk focus - tampilkan semua jika kosong
                penjualanInput.addEventListener('focus', () => {
                    console.log('Sales Order input focused');
                    if (penjualanInput.value.trim() === '') {
                        showAllSalesOrders();
                    } else {
                        showDropdownSalesOrder(penjualanInput);
                    }
                });

                // Event listener untuk keydown - ESC untuk menutup dropdown
                penjualanInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        const dropdownSalesOrder = document.getElementById('dropdown-sales');
                        dropdownSalesOrder.classList.add('hidden');
                    }
                });
            }

            // Set tanggal default - Removed as per user request to start with empty date

            console.log('Pengiriman Pesanan form initialized');
        });

        // Fungsi untuk mengubah judul berdasarkan halaman
        function updateTitle(pageTitle) {
            document.title = pageTitle;
        }

        // Panggil fungsi ini saat halaman "Buat Pengiriman Pesanan" dimuat
        updateTitle('Buat Pengiriman Pesanan');
    </script>
@endsection
