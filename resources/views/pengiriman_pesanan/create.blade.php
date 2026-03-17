@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Form Pengiriman Pesanan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
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
                            <div class="bg-white p-3 rounded border border-blue-200 mt-3 shadow-inner">
                                <p class="text-sm font-medium text-gray-800 flex items-center justify-between">
                                    <span>Status Pemenuhan Sales Order:</span>
                                    <span id="scan-status-text"
                                        class="text-xl font-bold text-blue-600 px-3 py-1 bg-blue-100 rounded">0 / 0
                                        Kuantitas Terpenuhi</span>
                                </p>
                                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                                    <div id="scan-progress-bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%">
                                    </div>
                                </div>
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
                                            <td class="border border-gray-400 px-2 py-3 text-left align-top">
                                                ≡
                                            </td>
                                            <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="7">
                                                Belum ada data
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="flex justify-end mt-2">
                            <button type="button" id="btn-save-pengiriman-pesanan"
                                class="bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50"
                                disabled>
                                <i class="fas fa-lock mr-2"></i>Save Terkunci (Selesaikan Scan)
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
                const input = document.getElementById('scan_barcode_input');
                const barcode = input.value.trim();
                if (!barcode) return;

                // Jika ada serial number cache dari Accurate, gunakan itu
                if (serialNumberCache.length > 0) {
                    input.value = '';
                    input.focus();
                    handleCachedScan(barcode);
                } else {
                    // Fallback: scan via local DB
                    input.disabled = true;
                    handleLocalDbScan(barcode, input);
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

        function handleLocalDbScan(barcode, input) {
            fetch('{{ route("temp_scan.scan_outbound") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ barcode: barcode })
            })
            .then(res => res.json())
            .then(data => {
                input.disabled = false;
                input.value = '';
                input.focus();

                if (data.status === 'success') {
                    let mcf = (data.data.material_code_formatted || '').trim();
                    let matchedItemIndex = detailItems.findIndex(i => {
                        if (!i.item) return false;
                        const itemNo = (i.item.no || '').trim();
                        const itemName = (i.item.name || '').trim();
                        return (
                            itemNo === data.data.kode_barang ||
                            itemNo === data.data.alias_nama ||
                            itemNo === mcf ||
                            (mcf && itemNo.startsWith(mcf)) ||
                            (mcf && mcf.startsWith(itemNo)) ||
                            itemName === data.data.keterangan
                        );
                    });

                    if (matchedItemIndex !== -1) {
                        const qtyFromDb = (data.data.panjang_mlc ?? data.data.length ?? 0);
                        processScanResult(matchedItemIndex, qtyFromDb, barcode, detailItems[matchedItemIndex].item.name);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Tidak Cocok', text: `Barang (${data.data.kode_barang}) tidak ada di dalam detail pesanan SO!` });
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error Scan', text: data.message });
                }
            })
            .catch(err => {
                input.disabled = false;
                input.focus();
                Swal.fire({ icon: 'error', title: 'System Error', text: err.toString() });
            });
        }

        function processScanResult(matchedItemIndex, barcodeQty, barcode, itemName) {
            let requiredQty = parseFloat(detailItems[matchedItemIndex].quantity || 0);
            let kodeUnik = detailItems[matchedItemIndex].item.no;
            let currentScanned = scannedItemsQuantities[kodeUnik] || 0;

            let newQty = currentScanned + barcodeQty;
            newQty = Math.round(newQty * 1000) / 1000;
            requiredQty = Math.round(requiredQty * 1000) / 1000;

            if (newQty > requiredQty) {
                Swal.fire({ icon: 'warning', title: 'Kelebihan Kuantitas', text: `Scan barcode ini menghasilkan ${newQty} yang melebihi qty dipesan SO (${requiredQty})!` });
            } else {
                scannedItemsQuantities[kodeUnik] = newQty;
                totalScannedQuantity += barcodeQty;
                totalScannedQuantity = Math.round(totalScannedQuantity * 1000) / 1000;
                updateScanProgress();

                // Simpan detail serial yang discan (per itemNo)
                if (!scannedSerialMap[kodeUnik]) scannedSerialMap[kodeUnik] = {};
                const prev = scannedSerialMap[kodeUnik][barcode] || 0;
                let next = prev + (parseFloat(barcodeQty) || 0);
                next = Math.round(next * 1000) / 1000;
                scannedSerialMap[kodeUnik][barcode] = next;

                Swal.fire({
                    icon: 'success',
                    title: 'Barcode Valid',
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
        }

        function updateScanProgress() {
            totalTargetQuantity = detailItems.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);
            totalTargetQuantity = Math.round(totalTargetQuantity * 1000) / 1000;

            let perc = 0;
            if (totalTargetQuantity > 0) {
                perc = (totalScannedQuantity / totalTargetQuantity) * 100;
                if (perc > 100) perc = 100;
            }

            document.getElementById('scan-status-text').innerText = `${totalScannedQuantity} / ${totalTargetQuantity} Kuantitas`;
            document.getElementById('scan-progress-bar').style.width = perc + '%';

            if (perc >= 100 && totalTargetQuantity > 0) {
                document.getElementById('scan-success-message').classList.remove('hidden');
                let btnSave = document.getElementById('btn-save-pengiriman-pesanan');
                btnSave.disabled = false;
                btnSave.className = 'bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm';
                btnSave.innerHTML = '<i class="fas fa-save mr-2"></i>Save Pengiriman Pesanan';
            } else {
                document.getElementById('scan-success-message').classList.add('hidden');
                let btnSave = document.getElementById('btn-save-pengiriman-pesanan');
                btnSave.disabled = true;
                btnSave.className = 'bg-gray-500 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed opacity-50';
                btnSave.innerHTML = '<i class="fas fa-lock mr-2"></i>Save Terkunci (Selesaikan Scan)';
            }
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
                    <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="7">
                        Belum ada data barang
                    </td>
                `;
                tableBody.appendChild(emptyRow);
                console.log('Table filled with empty state');
                return;
            }

            // Add rows for each item
            items.forEach((item, index) => {
                const row = document.createElement('tr');

                // Format currency values
                const unitPrice = formatCurrency(item.unitPrice || 0);
                const discount = formatCurrency(item.itemCashDiscount || 0);
                const totalPrice = formatCurrency(item.totalPrice || 0);

                row.innerHTML = `
                    <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
                    <td class="border border-gray-400 px-2 py-3 text-left align-top">
                        ${item.item?.name || 'N/A'}
                    </td>
                    <td class="border border-gray-400 px-2 py-3 align-top">
                        ${item.item?.no || 'N/A'}
                    </td>
                    <td class="border border-gray-400 px-2 py-3 align-top">
                        ${item.quantity || 0}
                    </td>
                    <td class="border border-gray-400 px-2 py-3 align-top">
                        ${item.itemUnit?.name || 'N/A'}
                    </td>
                    <td class="border border-gray-400 px-2 py-3 align-top">
                        ${unitPrice}
                    </td>
                    <td class="border border-gray-400 px-2 py-3 align-top">
                        ${discount}
                    </td>
                    <td class="border border-gray-400 px-2 py-3 align-top">
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

        // Function untuk format detail items sesuai kebutuhan controller
        function formatDetailItemsForSubmission(items) {
            if (!items || items.length === 0) {
                return [];
            }

            return items.map(item => {
                return {
                    kode: item.item?.no || '',
                    kuantitas: item.quantity || 0,
                    harga: item.unitPrice !== undefined && item.unitPrice !== null ? item.unitPrice : 0,
                    diskon: item.itemCashDiscount !== undefined && item.itemCashDiscount !== null ? item.itemCashDiscount : 0
                };
            });
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

        // Function untuk handle submit form
        function handleSubmitForm() {
            const saveButton = document.getElementById('btn-save-pengiriman-pesanan');
            const form = document.getElementById('pengirimanPesananForm');
            const formSubmittedInput = document.getElementById('form_submitted');

            console.log('Starting form submission process...');

            // Validasi form
            const validationErrors = validateFormData();
            if (validationErrors.length > 0) {
                let errorMessages = '';
                validationErrors.forEach(error => {
                    errorMessages += `<li>${error}</li>`;
                });

                Swal.fire({
                    icon: 'warning',
                    title: 'Validasi Gagal!',
                    html: `<ul class="text-left list-disc list-inside">${errorMessages}</ul>`,
                    timer: 6000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                console.error('Validation errors:', validationErrors);
                return;
            }

            // Show loading state
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Menyimpan...';

            try {
                // Format detail items untuk dikirim ke controller
                const formattedDetailItems = formatDetailItemsForSubmission(detailItems);

                console.log('Form data to be submitted:', {
                    formData: formData,
                    detailItems: formattedDetailItems
                });

                // Buat hidden inputs untuk detail items
                const existingDetailInputs = form.querySelectorAll('input[name^="detailItems"]');
                existingDetailInputs.forEach(input => input.remove());

                // Tambahkan detail items sebagai hidden inputs dengan nama field yang benar
                formattedDetailItems.forEach((item, index) => {
                    const fields = [
                        'kode',
                        'kuantitas',
                        'harga',
                        'diskon'
                    ];

                    fields.forEach(field => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `detailItems[${index}][${field}]`;
                        // Pastikan nilai 0 tetap dikirim
                        input.value = item[field] !== undefined && item[field] !== null ? item[field] : '';
                        form.appendChild(input);
                    });
                });

                // Kirim juga detail serial/barcode yang discan (untuk item manageSN=BATCH)
                const existingSerialMapInputs = form.querySelectorAll('input[name="serials_json"]');
                existingSerialMapInputs.forEach(input => input.remove());
                const serialMapInput = document.createElement('input');
                serialMapInput.type = 'hidden';
                serialMapInput.name = 'serials_json';
                serialMapInput.value = JSON.stringify(scannedSerialMap || {});
                form.appendChild(serialMapInput);

                // Set form_submitted flag
                formSubmittedInput.value = '1';

                // Show success notification before submit
                Swal.fire({
                    icon: 'success',
                    title: 'Data Valid!',
                    text: 'Sedang menyimpan data pengiriman pesanan...',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });

                // Submit form
                console.log('Submitting form to:', form.action);
                form.submit();

            } catch (error) {
                console.error('Error during form submission:', error);

                // Reset button state
                saveButton.disabled = false;
                saveButton.innerHTML = 'Save Pengiriman Pesanan';

                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menyimpan!',
                    text: 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
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

            // Setup event listener untuk button Save
            if (btnSave) {
                btnSave.addEventListener('click', handleSubmitForm);
                console.log('Button Save event listener added');
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