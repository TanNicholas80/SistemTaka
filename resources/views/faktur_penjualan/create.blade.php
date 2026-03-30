@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Form Faktur Penjualan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('faktur_penjualan.index') }}">Faktur Penjualan</a></li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </div>
            </div>

            <div class="card">
                <form id="fakturPenjualanForm" method="POST" action="{{ route('faktur_penjualan.store') }}" class="p-3 space-y-3">
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
                    <input type="hidden" id="pelanggan_id_hidden" name="pelanggan_id" value="">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                <!-- Tanggal -->
                                <label for="tanggal_faktur" class="text-gray-800 font-medium flex items-center">
                                    Tanggal<span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="tanggal_faktur" name="tanggal_faktur" type="date" value="{{ $selectedTanggal }}"
                                    class="border border-gray-300 rounded px-2 py-1 max-w-[300px] w-full {{ $formReadonly ? 'bg-gray-200 text-gray-500' : '' }}"
                                    required {{ $formReadonly ? 'readonly' : '' }} />

                                <!-- Pengiriman Pesanan -->
                                <label for="pengiriman_id" class="text-gray-800 font-medium flex items-center">
                                    Pengiriman <span class="text-red-600 ml-1">*</span>
                                    <span class="ml-1"
                                        data-toggle="tooltip"
                                        data-placement="top"
                                        title="Silakan cari & pilih nomor pengiriman pesanan dari dropdown"
                                        style="cursor: help;">
                                        <i class="fas fa-info-circle"></i>
                                    </span>
                                </label>
                                <div class="relative max-w-[300px] w-full">
                                    <div class="flex items-center border border-gray-300 rounded overflow-hidden">
                                        <input id="pengiriman_id" name="pengiriman_id" type="search"
                                            class="flex-grow outline-none px-2 py-1 text-sm"
                                            placeholder="Cari/Pilih Pengiriman Pesanan..." required />
                                        <button type="button" id="penjualan-search-btn" class="px-2 text-gray-600 hover:text-gray-900">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>

                                    <!-- Dropdown akan muncul di sini -->
                                    <div id="dropdown-penjualan"
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
                                <!-- Nomor Faktur -->
                                <label for="no_faktur" class="text-gray-800 font-medium flex items-center">
                                    Nomor Faktur<span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="no_faktur" name="no_faktur" type="text" value="{{ $no_faktur }}"
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
                        <button type="button" id="btn-save-faktur-penjualan"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Save Faktur Penjualan
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    // Data delivery orders dari controller
    const deliveryOrdersData = JSON.parse(`{!! addslashes(json_encode($deliveryOrders)) !!}`);
    console.log('Delivery Orders data:', deliveryOrdersData);

    // Variabel untuk menyimpan data form
    let formData = {
        pengiriman_id: '',
        pelanggan_id: '',
        pelanggan_display: '', // Untuk display di UI
        tanggal_faktur: '',
        no_faktur: '',
        detailItems: []
    };

    // Variabel untuk menyimpan detail items
    let detailItems = [];

    // Function untuk show dropdown delivery order
    function showDropdownDeliveryOrder(input) {
        const dropdownDeliveryOrder = document.getElementById('dropdown-penjualan');
        const query = input.value.toLowerCase().trim();

        console.log('Searching Delivery Order with query:', query);

        // Jika query kosong, panggil showAllDeliveryOrders
        if (query === '') {
            showAllDeliveryOrders();
            return;
        }

        const resultDeliveryOrders = deliveryOrdersData.filter(so => {
            const matchNumber = so.number.toLowerCase().includes(query);
            const matchCustomerName = so.customer && so.customer.name &&
                so.customer.name.toLowerCase().includes(query);
            const matchCustomerNo = so.customer && so.customer.customerNo &&
                so.customer.customerNo.toLowerCase().includes(query);

            return matchNumber || matchCustomerName || matchCustomerNo;
        });

        dropdownDeliveryOrder.innerHTML = '';

        if (resultDeliveryOrders.length === 0) {
            const noResultItem = document.createElement('div');
            noResultItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noResultItem.innerHTML = `<i class="fas fa-search mr-2"></i>Tidak ada pengiriman yang cocok dengan "${query}"`;
            dropdownDeliveryOrder.appendChild(noResultItem);
        } else {
            // Tambahkan header hasil pencarian
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-sm text-blue-700';
            headerItem.innerHTML = `<i class="fas fa-search mr-2"></i>Hasil Pencarian: ${resultDeliveryOrders.length} Pengiriman`;
            dropdownDeliveryOrder.appendChild(headerItem);

            resultDeliveryOrders.forEach(so => {
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
                    dropdownDeliveryOrder.classList.add('hidden');

                    // Update formData
                    formData.pengiriman_id = so.number;

                    // Get customer data via AJAX (hanya update customer)
                    getCustomerByAjax(so.number, true);

                    console.log('Delivery Order selected:', so.number);
                };

                dropdownDeliveryOrder.appendChild(item);
            });
        }

        dropdownDeliveryOrder.classList.remove('hidden');
        console.log('Delivery Order dropdown shown with', resultDeliveryOrders.length, 'results');
    }

    // Function untuk menampilkan semua delivery orders
    function showAllDeliveryOrders() {
        const dropdownDeliveryOrder = document.getElementById('dropdown-penjualan');

        console.log('Menampilkan semua Delivery Orders, total:', deliveryOrdersData.length);

        dropdownDeliveryOrder.innerHTML = '';

        if (deliveryOrdersData.length === 0) {
            const noDataItem = document.createElement('div');
            noDataItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noDataItem.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Tidak ada data pengiriman pesanan';
            dropdownDeliveryOrder.appendChild(noDataItem);
        } else {
            // Tambahkan header info
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-gray-50 border-b font-semibold text-sm text-gray-700';
            headerItem.innerHTML = `<i class="fas fa-list mr-2"></i>Semua Pengiriman Pesanan (${deliveryOrdersData.length})`;
            dropdownDeliveryOrder.appendChild(headerItem);

            // Tampilkan semua delivery orders (batas maksimal untuk performa)
            const maxShow = 50;
            const doToShow = deliveryOrdersData.slice(0, maxShow);

            doToShow.forEach(so => {
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
                    const pengirimanInput = document.getElementById('pengiriman_id');
                    pengirimanInput.value = so.number;
                    dropdownDeliveryOrder.classList.add('hidden');

                    // Update formData
                    formData.pengiriman_id = so.number;

                    // Get customer data via AJAX (hanya update customer)
                    getCustomerByAjax(so.number, true);

                    console.log('Delivery Order selected:', so.number);
                };

                dropdownDeliveryOrder.appendChild(item);
            });

            // Jika ada lebih banyak data, tampilkan info
            if (deliveryOrdersData.length > maxShow) {
                const moreInfoItem = document.createElement('div');
                moreInfoItem.className = 'px-3 py-2 bg-blue-50 border-b text-sm text-blue-600 text-center';
                moreInfoItem.innerHTML = `<i class="fas fa-info-circle mr-2"></i>Menampilkan ${maxShow} dari ${deliveryOrdersData.length} pengiriman. Ketik untuk pencarian spesifik.`;
                dropdownDeliveryOrder.appendChild(moreInfoItem);
            }
        }

        dropdownDeliveryOrder.classList.remove('hidden');
        console.log('All Delivery Orders dropdown shown');
    }

    document.addEventListener('DOMContentLoaded', function() {
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

    // Function untuk handle tombol search delivery order
    function handleSearchDeliveryOrderClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const pengirimanInput = document.getElementById('pengiriman_id');
        const dropdownDeliveryOrder = document.getElementById('dropdown-penjualan');

        console.log('Search Delivery Order button clicked');
        console.log('Current input value:', pengirimanInput.value);

        // Jika input kosong atau hanya whitespace, tampilkan semua delivery orders
        const query = pengirimanInput.value.trim();

        if (query === '') {
            console.log('Input kosong, menampilkan semua Delivery Orders');
            showAllDeliveryOrders();
        } else {
            console.log('Input tidak kosong, melakukan pencarian dengan query:', query);
            showDropdownDeliveryOrder(pengirimanInput);
        }
    }

    // Function untuk get customer data by AJAX
    function getCustomerByAjax(deliveryOrderNumber, updateCustomerOnly = true) {
        const pelangganInput = document.getElementById('pelanggan_display');
        const pelangganHiddenInput = document.getElementById('pelanggan_id_hidden');

        // Cari data delivery order lokal terlebih dahulu
        const selectedDeliveryOrder = deliveryOrdersData.find(so => so.number === deliveryOrderNumber);

        if (selectedDeliveryOrder && selectedDeliveryOrder.customer && selectedDeliveryOrder.customer.name && updateCustomerOnly) {
            // Gunakan data lokal jika tersedia dan lengkap untuk update customer saja
            const customerDisplay = selectedDeliveryOrder.customer.customerNo ?
                `${selectedDeliveryOrder.customer.name} (${selectedDeliveryOrder.customer.customerNo})` :
                selectedDeliveryOrder.customer.name;

            const customerNo = selectedDeliveryOrder.customer.customerNo || selectedDeliveryOrder.customer.name;

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
        console.log('Getting customer data via AJAX for delivery order:', deliveryOrderNumber);

        // AJAX call ke controller
        return fetch(`/faktur-penjualan/customer/${deliveryOrderNumber}`, {
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
                    }
                    formData.pelanggan_id = customerNo; // Hanya customer number untuk controller
                    formData.pelanggan_display = customerDisplay; // Full info untuk display

                    console.log('Customer updated from AJAX:', customerDisplay);

                    return {
                        success: true,
                        customerDisplay: customerDisplay,
                        customerNo: customerNo,
                        detailItems: data.detailItems || []
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
        const pengirimanInput = document.getElementById('pengiriman_id');
        const pelangganInput = document.getElementById('pelanggan_display');
        const pelangganHiddenInput = document.getElementById('pelanggan_id_hidden');
        const tanggalInput = document.getElementById('tanggal_faktur');
        const noFakturInput = document.getElementById('no_faktur');
        const btnLanjut = document.getElementById('btn-lanjut');

        // Validasi input
        if (!pengirimanInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan!',
                text: 'Silakan pilih Pengiriman Pesanan terlebih dahulu!',
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
                text: 'Silakan isi Tanggal Faktur terlebih dahulu!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return;
        }

        // Update formData
        formData.pengiriman_id = pengirimanInput.value;
        formData.tanggal_faktur = tanggalInput.value;
        formData.no_faktur = noFakturInput.value;

        console.log('Button Lanjut clicked, form data:', formData);

        // Show loading state
        btnLanjut.disabled = true;
        btnLanjut.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';

        // Get customer data and detail items via AJAX
        getCustomerByAjax(formData.pengiriman_id, false)
            .then(response => {
                console.log('Lanjut button response:', response);

                if (response.success) {
                    // Update customer field
                    pelangganInput.value = response.customerDisplay;
                    pelangganHiddenInput.value = response.customerNo; // Update hidden input dengan customer number
                    formData.pelanggan_id = response.customerNo;
                    formData.pelanggan_display = response.customerDisplay;

                    // Store detail items
                    detailItems = response.detailItems;

                    // Fill table with detail items
                    fillTableWithDetailItems(detailItems);

                    // Set form inputs to readonly
                    setFormReadonly(true);

                    // Hide button Lanjut
                    btnLanjut.style.display = 'none';

                    // Show success notification
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: `Data pengiriman pesanan berhasil dimuat dengan ${detailItems.length} item barang`,
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
            document.getElementById('tanggal_faktur'),
            document.getElementById('pengiriman_id'),
            document.getElementById('pelanggan_display'),
            document.getElementById('no_faktur')
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
        const searchBtn = document.getElementById('penjualan-search-btn');
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
        if (!formData.pengiriman_id || formData.pengiriman_id.trim() === '') {
            errors.push('Pengiriman Pesanan harus dipilih');
        }

        if (!formData.tanggal_faktur || formData.tanggal_faktur.trim() === '') {
            errors.push('Tanggal Faktur harus diisi');
        }

        if (!formData.no_faktur || formData.no_faktur.trim() === '') {
            errors.push('Nomor Faktur harus diisi');
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
        const saveButton = document.getElementById('btn-save-faktur-penjualan');
        const form = document.getElementById('fakturPenjualanForm');
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

            // Set form_submitted flag
            formSubmittedInput.value = '1';

            // Show success notification before submit
            Swal.fire({
                icon: 'success',
                title: 'Data Valid!',
                text: 'Sedang menyimpan data faktur penjualan...',
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
            saveButton.innerHTML = 'Save Faktur Penjualan';

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
    document.addEventListener('click', function(e) {
        const pengirimanWrapper = document.getElementById('pengiriman_id')?.closest('.relative');

        if (pengirimanWrapper && !pengirimanWrapper.contains(e.target)) {
            document.getElementById('dropdown-penjualan')?.classList.add('hidden');
        }
    });

    // Initialize form on page load
    document.addEventListener('DOMContentLoaded', () => {
        const pengirimanInput = document.getElementById('pengiriman_id');
        const searchBtnDeliveryOrder = document.getElementById('penjualan-search-btn');
        const tanggalInput = document.getElementById('tanggal_faktur');
        const btnLanjut = document.getElementById('btn-lanjut');
        const btnSave = document.getElementById('btn-save-faktur-penjualan');

        console.log('Initializing faktur penjualan form...');

        if (searchBtnDeliveryOrder) {
            searchBtnDeliveryOrder.addEventListener('click', handleSearchDeliveryOrderClick);
            console.log('Search Delivery Order button event listener added');
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

        // Setup event listener untuk pengiriman_id input
        if (pengirimanInput) {
            pengirimanInput.addEventListener('input', () => {
                console.log('Delivery Order input changed:', pengirimanInput.value);
                showDropdownDeliveryOrder(pengirimanInput);
            });

            // Event listener untuk focus - tampilkan semua jika kosong
            pengirimanInput.addEventListener('focus', () => {
                console.log('Delivery Order input focused');
                if (pengirimanInput.value.trim() === '') {
                    showAllDeliveryOrders();
                } else {
                    showDropdownDeliveryOrder(pengirimanInput);
                }
            });

            // Event listener untuk keydown - ESC untuk menutup dropdown
            pengirimanInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const dropdownDeliveryOrder = document.getElementById('dropdown-penjualan');
                    dropdownDeliveryOrder.classList.add('hidden');
                }
            });
        }

        // Set tanggal default
        if (tanggalInput) {
            const today = new Date().toISOString().split('T')[0];
            if (!tanggalInput.value) {
                tanggalInput.value = today;
                formData.tanggal_faktur = today;
            }
        }

        console.log('Faktur Penjualan form initialized');
    });

    // Fungsi untuk mengubah judul berdasarkan halaman
    function updateTitle(pageTitle) {
        document.title = pageTitle;
    }

    // Panggil fungsi ini saat halaman "Buat Faktur Penjualan" dimuat
    updateTitle('Buat Faktur Penjualan');
</script>
@endsection
