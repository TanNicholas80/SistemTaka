@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="card">
                <form id="penerimaanForm" action="{{ route('penerimaan-barang.store') }}" class="p-3 space-y-3" method="post">
                    <div id="laravel-errors"
                        @if(session('error'))
                        data-error="{{ session('error') }}"
                        @endif
                        @if($errors->any())
                        data-validation-errors="{{ json_encode($errors->all()) }}"
                        @endif
                        ></div>
                    @csrf
                    <!-- Hidden input untuk menyimpan status form -->
                    <input type="hidden" id="form_submitted" name="form_submitted" value="0">

                    <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                        <!-- Form Nomor PO -->
                        <label for="no_po" class="text-gray-800 font-medium flex items-center">
                            Nomor PO <span class="text-red-600 ml-1">*</span>
                            <span class="ml-1"
                                data-toggle="tooltip"
                                data-placement="top"
                                title="Silakan cari & pilih nomor purchase order dari dropdown"
                                style="cursor: help;">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        </label>

                        <div class="relative max-w-md w-full">
                            <div class="flex items-center border border-gray-300 rounded overflow-hidden max-w-[300px]">
                                <input
                                    id="no_po"
                                    name="no_po"
                                    type="search"
                                    placeholder="Cari/Pilih Nomor Purchase Order..."
                                    class="flex-grow px-2 py-1 outline-none text-sm"
                                    required />
                                <button type="button" id="no-po-search-btn" class="px-2 text-gray-600 hover:text-gray-900">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <!-- Dropdown akan muncul di sini -->
                            <div id="dropdown-no-po" class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded shadow mt-1 hidden max-h-40 overflow-y-auto text-sm max-w-[300px]">

                            </div>
                        </div>

                        <!-- Form Nomor Pemesanan Barang -->
                        <label for="npb" class="text-gray-800 font-medium flex items-center">
                            Nomor Form <span class="text-red-600 ml-1">*</span>
                        </label>
                        <input
                            id="npb"
                            name="npb"
                            type="text"
                            value="{{ $npb }}"
                            class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6]"
                            required readonly />

                        <!-- Form No Terima -->
                        <label for="no_terima" class="text-gray-800 font-medium flex items-center">
                            No Terima <span class="text-red-600 ml-1">*</span>
                        </label>
                        <input
                            id="no_terima"
                            name="no_terima"
                            type="text"
                            value="{{ $noTerima }}"
                            class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6]"
                            required readonly />

                        <!-- Form Kode Customer -->
                        <label for="kode_customer" class="text-gray-800 font-medium flex items-center">
                            Kode Customer <span class="text-red-600 ml-1">*</span>
                        </label>
                        <input
                            id="kode_customer"
                            name="kode_customer"
                            type="text"
                            value="{{ $kodeCustomer ?? '' }}"
                            class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6]"
                            required readonly />

                        <!-- Form Pemasok -->
                        <label for="vendor" class="text-gray-800 font-medium flex items-center">
                            Terima dari <span class="text-red-600 ml-1">*</span>
                        </label>
                        <input
                            id="vendor"
                            name="vendor"
                            type="text"
                            placeholder="Vendor Terisi Otomatis"
                            value="{{ $vendor ?? '' }}"
                            class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6]"
                            required readonly />

                        <!-- Form Tanggal -->
                        <label for="tanggal" class="text-gray-800 font-medium flex items-center">
                            Tanggal <span class="text-red-600 ml-1">*</span>
                        </label>
                        <input
                            id="tanggal"
                            name="tanggal"
                            type="date"
                            value="{{ date('Y-m-d') }}"
                            class="border border-gray-300 rounded px-2 py-1 max-w-[300px] w-full"
                            required />

                        <!-- Form Packing List (Multiple) -->
                        <label class="text-gray-800 font-medium flex items-center">
                            Packing List <span class="text-red-600 ml-1">*</span>
                            <span class="ml-1"
                                data-toggle="tooltip"
                                data-placement="top"
                                title="Pilih packing list yang sudah berstatus approved"
                                style="cursor: help;">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        </label>
                        <div class="max-w-md border border-gray-300 rounded px-2 py-2 bg-white max-h-40 overflow-y-auto">
                            @php $plList = $packingLists ?? collect(); @endphp
                            @forelse($plList as $pl)
                                <label class="flex items-center gap-2 py-1 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="packing_list_ids[]" value="{{ $pl->id }}" class="packing-list-cb rounded">
                                    <span>{{ $pl->npl }}</span>
                                    <span class="text-xs text-gray-500">({{ \Carbon\Carbon::parse($pl->tanggal)->format('d-m-Y') }})</span>
                                </label>
                            @empty
                                <p class="text-gray-500 text-sm py-2">Tidak ada packing list dengan status approved.</p>
                            @endforelse
                        </div>

                        <!-- Buttons -->
                        <label class="text-gray-800 font-medium"></label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="lanjut-btn"
                                onclick="handleLanjutClick(event)"
                                class="bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 text-sm">
                                Lanjut
                            </button>
                            <button
                                type="button"
                                id="save-btn"
                                onclick="handleSaveClick(event)"
                                class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 text-sm font-medium hidden">
                                <i class="fas fa-save mr-2"></i>Save
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ERROR ALERT (Hanya muncul jika ada kesalahan dari Controller setelah Form Submit) -->
                @if(session('error'))
                    <div class="p-2">
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                <!-- Bagian Upload & Validasi Barcode (Sembunyi by Default) -->
                <div id="barcode-validation-section" class="m-2 p-4 border border-blue-300 bg-blue-50 rounded hidden">
                    <h3 class="font-bold text-blue-800 mb-2"><i class="fas fa-barcode mr-2"></i>Tahap Validasi Barcode Fisik</h3>
                    
                    <!-- Upload TXT -->
                    <div id="upload-txt-container" class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">1. Upload File Master Barcode (TXT)</label>
                        <div class="flex items-center gap-2">
                            <input type="file" id="txt_file" name="txt_file" accept=".txt,.csv" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white w-full max-w-md">
                            <button type="button" id="btn-upload-txt" onclick="uploadMasterTxt()" class="bg-indigo-600 text-white px-4 py-1.5 rounded hover:bg-indigo-700 text-sm font-medium shadow-sm transition-all"><i class="fas fa-upload mr-1"></i> Upload & Proses</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Atau biarkan kosong lalu tekan Upload untuk menggunakan file TXT default jika tersedia di server.</p>
                    </div>

                    <!-- Scan Fisik -->
                    <div id="scan-physical-container" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">2. Scan Barcode Fisik</label>
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" id="scan_barcode_input" class="border border-green-400 rounded px-3 py-2 text-sm bg-white w-full max-w-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Arahkan kursor kesini, lalu Scan Barcode..." autocomplete="off">
                            <button type="button" id="btn-reset-scan" onclick="resetScan()" class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 text-sm font-medium shadow-sm transition-all"><i class="fas fa-trash-alt mr-1"></i> Reset Data Temporary</button>
                        </div>
                        <div class="bg-white p-3 rounded border border-blue-200 mt-3 shadow-inner">
                            <p class="text-sm font-medium text-gray-800 flex items-center justify-between">
                                <span>Status Validasi Master & Fisik:</span>
                                <span id="scan-status-text" class="text-xl font-bold text-blue-600 px-3 py-1 bg-blue-100 rounded">0 / 0 Barcode Cocok</span>
                            </p>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                                <div id="scan-progress-bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <p id="scan-success-message" class="text-sm font-bold text-green-600 mt-2 hidden"><i class="fas fa-check-circle mr-1"></i> Validasi 100% Selesai! Silakan tekan tombol Save di atas.</p>
                    </div>
                </div>

                <!-- Table container -->
                <div class="p-2 flex flex-col gap-4">
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
                                    <th class="border border-gray-400 w-8 px-1 py-1">No</th>
                                    <th class="border border-gray-400 px-2 py-1 text-left">Nama Barang</th>
                                    <th class="border border-gray-400 px-2 py-1">Kode Barang</th>
                                    <th class="border border-gray-400 px-2 py-1">Barcode</th>
                                    <th class="border border-gray-400 px-2 py-1">Kuantitas</th>
                                </tr>
                            </thead>
                            <tbody id="table-barang-body" class="bg-white">
                                <tr>
                                    <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="5">
                                        Belum ada data
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const nomor_po = JSON.parse(`{!! addslashes(json_encode($purchase_order)) !!}`);

    console.log('Nomor PO data:', nomor_po);

    // Variabel untuk menyimpan data form
    let formData = {
        no_po: '',
        npb: '',
        no_terima: '',
        vendor: '',
        tanggal: '',
        packing_list_ids: []
    };

    // Flag untuk mencegah form submit tidak diinginkan
    let isFormLocked = false;
    let isDetailFormReady = false;

    // Function untuk handle tombol Lanjut
    function handleLanjutClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const noPOInput = document.getElementById('no_po');
        const npbInput = document.getElementById('npb');
        const noTerimaInput = document.getElementById('no_terima');
        const vendorInput = document.getElementById('vendor');
        const tanggalInput = document.getElementById('tanggal');
        const packingListCheckboxes = document.querySelectorAll('.packing-list-cb:checked');

        const packingListIds = Array.from(packingListCheckboxes).map(cb => cb.value);

        console.log('Lanjut button clicked');
        console.log('No PO:', noPOInput.value);
        console.log('NPB:', npbInput.value);
        console.log('No Terima:', noTerimaInput?.value);
        console.log('Vendor:', vendorInput.value);
        console.log('Tanggal:', tanggalInput.value);
        console.log('Packing List IDs:', packingListIds);

        // Validasi form
        if (!noPOInput.value.trim() || !npbInput.value.trim() || !tanggalInput.value.trim() || packingListIds.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Field Tidak Lengkap',
                text: 'Harap lengkapi No PO, Tanggal, dan pilih minimal satu packing list!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        // Simpan data form sementara
        formData.no_po = noPOInput.value;
        formData.npb = npbInput.value;
        formData.no_terima = noTerimaInput.value;
        formData.vendor = vendorInput.value;
        formData.tanggal = tanggalInput.value;
        formData.packing_list_ids = packingListIds;

        console.log('Form data saved temporarily:', formData);

        // Tampilkan loading state pada tombol
        const lanjutBtn = document.getElementById('lanjut-btn');
        const originalText = lanjutBtn.textContent;
        lanjutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
        lanjutBtn.disabled = true;

        // Set form menjadi readonly
        setFormReadonly(true);
        isFormLocked = true;

        // Panggil function untuk mengambil detail PO
        fetchDetailPO(formData.no_po, formData.npb, formData.packing_list_ids)
            .then(() => {
                console.log('Detail PO berhasil dimuat');
                // Setelah berhasil, tombol Save akan ditampilkan di setFormReadonly(true)
            })
            .catch(error => {
                console.error('Error loading detail PO:', error);
                // Jika error, kembalikan form ke state editable
                setFormReadonly(false);
                isFormLocked = false;
                lanjutBtn.textContent = originalText;
                lanjutBtn.disabled = false;
            });

        return false;
    }

    // Function untuk mengambil detail PO dari packing list dengan AJAX
    function fetchDetailPO(noPO, npb, packingListIds) {
        return new Promise((resolve, reject) => {
            // Tampilkan loading indicator
            const tableBody = document.getElementById('table-barang-body');
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading data...</td></tr>';

            // Siapkan data untuk request
            const data = new FormData();
            data.append('no_po', noPO);
            data.append('npb', npb);
            packingListIds.forEach(id => data.append('packing_list_ids[]', id));

            // Tambahkan CSRF token
            data.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            // Kirim AJAX request
            fetch('/purchase-orders/detail', {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    // Cek jika response tidak ok (status 4xx atau 5xx)
                    if (!response.ok) {
                        return response.json().then(errorData => {
                            throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Detail PO data received:', data);
                    console.log('Vendor data structure:', data.vendor);

                    // Update vendor jika ada
                    if (data.vendor && data.vendor.vendorNo) {
                        const vendorInput = document.getElementById('vendor');
                        vendorInput.value = data.vendor.vendorNo;

                        // PENTING: Update formData.vendor setelah mendapat data dari server
                        formData.vendor = data.vendor.vendorNo;
                        console.log('Vendor updated in formData:', formData.vendor);
                    }

                    // Tampilkan data barang di tabel
                    updateBarangTable(data.barang);

                    // Tampilkan bagian Validasi Barcode BUKAN tombol Save
                    document.getElementById('barcode-validation-section').classList.remove('hidden');

                    resolve(data);
                })
                .catch(error => {
                    console.error('Error fetching detail PO:', error);

                    // Tampilkan error dengan SweetAlert
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: error.message || 'Terjadi kesalahan saat mengambil data',
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });

                    // Update tabel dengan pesan error
                    tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Error: ${error.message || 'Terjadi kesalahan'}
                </td></tr>`;

                    reject(error);
                });
        });
    }

    // Function untuk update tabel barang (per barcode)
    function updateBarangTable(items) {
        const tableBody = document.getElementById('table-barang-body');
        tableBody.innerHTML = '';

        if (items && items.length > 0) {
            const sortedItems = items.sort((a, b) => {
                const namaA = (a.nama_barang || '').toLowerCase();
                const namaB = (b.nama_barang || '').toLowerCase();
                return namaA.localeCompare(namaB);
            });

            sortedItems.forEach((item, index) => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';

                const noCell = document.createElement('td');
                noCell.className = 'border border-gray-400 px-1 py-3 text-center align-top';
                noCell.textContent = index + 1;
                row.appendChild(noCell);

                const namaCell = document.createElement('td');
                namaCell.className = 'border border-gray-400 px-2 py-3 text-left align-top';
                namaCell.textContent = item.nama_barang || '-';
                row.appendChild(namaCell);

                const kodeCell = document.createElement('td');
                kodeCell.className = 'border border-gray-400 px-2 py-3 align-top';
                kodeCell.textContent = item.kode_barang || '-';
                row.appendChild(kodeCell);

                const barcodeCell = document.createElement('td');
                barcodeCell.className = 'border border-gray-400 px-2 py-3 align-top font-mono';
                barcodeCell.textContent = item.barcode || '-';
                row.appendChild(barcodeCell);

                const qtyCell = document.createElement('td');
                qtyCell.className = 'border border-gray-400 px-2 py-3 align-top font-semibold text-right';
                const qty = parseFloat(item.kuantitas) || 0;
                qtyCell.textContent = qty % 1 === 0 ? qty.toFixed(0) : qty.toFixed(2);
                row.appendChild(qtyCell);

                tableBody.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.className = 'border border-gray-400 px-2 py-3 text-center align-top';
            cell.colSpan = 5;
            cell.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Tidak ada data barang dari packing list yang dipilih';
            row.appendChild(cell);
            tableBody.appendChild(row);
        }
    }

    // Function untuk handle tombol Save (menggunakan form submit biasa)
    function handleSaveClick(event) {
        event.preventDefault();
        event.stopPropagation();

        // Pastikan data form tersedia
        if (!formData.npb || !formData.no_po) {
            Swal.fire({
                icon: 'warning',
                title: 'Data Tidak Lengkap',
                text: 'Data form tidak lengkap. Silakan klik tombol Lanjut terlebih dahulu.',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        console.log('Submitting form with data:', formData);

        // Tampilkan konfirmasi sebelum save
        Swal.fire({
            title: 'Konfirmasi Simpan',
            text: 'Apakah Anda yakin ingin menyimpan data penerimaan barang ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Tampilkan loading indicator
                const saveBtn = document.getElementById('save-btn');
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Menyimpan...';
                saveBtn.disabled = true;

                // Pastikan semua input memiliki nilai yang benar sebelum submit
                document.getElementById('no_po').value = formData.no_po;
                document.getElementById('npb').value = formData.npb;
                document.getElementById('no_terima').value = formData.no_terima;
                document.getElementById('vendor').value = formData.vendor;
                document.getElementById('tanggal').value = formData.tanggal;

                const form = document.getElementById('penerimaanForm');
                if (form) {
                    // Hapus packing_list_ids hidden lama (jika ada), tambahkan yang terpilih
                    form.querySelectorAll('input[name="packing_list_ids[]"][type="hidden"]').forEach(el => el.remove());
                    formData.packing_list_ids.forEach(id => {
                        const inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'packing_list_ids[]';
                        inp.value = id;
                        form.appendChild(inp);
                    });
                }

                // Set hidden input untuk menandai bahwa form sudah siap disubmit
                document.getElementById('form_submitted').value = '1';

                if (form) {
                    // Unlock form untuk submit
                    isFormLocked = false;

                    console.log('Submitting form to:', form.action);

                    // Submit form
                    form.submit();
                } else {
                    console.error('Form element not found');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Form tidak ditemukan. Silakan reload halaman.',
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });

                    // Restore tombol save jika error
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }
        });

        return false;
    }

    // Function untuk show dropdown PO
    function showDropdownPO(input) {
        const dropdownPO = document.getElementById('dropdown-no-po');
        const query = input.value.toLowerCase().trim();

        console.log('Searching PO with query:', query);

        // Jika query kosong, panggil showAllPO
        if (query === '') {
            showAllPO();
            return;
        }

        const resultPO = nomor_po.filter(po =>
            po.number_po.toLowerCase().includes(query) ||
            po.date_po.toLowerCase().includes(query)
        );

        dropdownPO.innerHTML = '';

        if (resultPO.length === 0) {
            const noResultItem = document.createElement('div');
            noResultItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noResultItem.innerHTML = `<i class="fas fa-search mr-2"></i>Tidak ada PO yang cocok dengan "${query}"`;
            dropdownPO.appendChild(noResultItem);
        } else {
            // Tambahkan header hasil pencarian
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-sm text-blue-700';
            headerItem.innerHTML = `<i class="fas fa-search mr-2"></i>Hasil Pencarian: ${resultPO.length} PO`;
            dropdownPO.appendChild(headerItem);

            resultPO.forEach(po => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const number = document.createElement('div');
                number.className = 'font-semibold text-sm text-gray-800';
                // Highlight matching text
                const highlightedNumber = po.number_po.replace(
                    new RegExp(`(${query})`, 'gi'),
                    '<mark class="bg-yellow-200">$1</mark>'
                );
                number.innerHTML = highlightedNumber;

                const date = document.createElement('div');
                date.className = 'text-sm text-gray-500';
                date.textContent = po.date_po;

                item.appendChild(number);
                item.appendChild(date);

                item.onclick = () => {
                    input.value = po.number_po;
                    dropdownPO.classList.add('hidden');

                    // Update formData jika diperlukan
                    formData.no_po = po.number_po;

                    console.log('PO selected from search:', po.number_po);
                };

                dropdownPO.appendChild(item);
            });
        }

        dropdownPO.classList.remove('hidden');
        console.log('PO dropdown shown with', resultPO.length, 'results');
    }

    // Function untuk mengatur readonly pada form
    function setFormReadonly(readonly = true) {
        const noPOInput = document.getElementById('no_po');
        const vendorInput = document.getElementById('vendor');
        const tanggalInput = document.getElementById('tanggal');
        const packingListCbs = document.querySelectorAll('.packing-list-cb');
        const dropdownNoPO = document.getElementById('dropdown-no-po');
        const searchBtnNoPO = document.getElementById('no-po-search-btn');
        const lanjutBtn = document.getElementById('lanjut-btn');
        const saveBtn = document.getElementById('save-btn');
        const validationSection = document.getElementById('barcode-validation-section');

        console.log('Setting readonly:', readonly);

        if (readonly) {
            noPOInput.setAttribute('readonly', 'readonly');
            noPOInput.readOnly = true;

            vendorInput.setAttribute('readonly', 'readonly');
            vendorInput.readOnly = true;

            tanggalInput.setAttribute('readonly', 'readonly');
            tanggalInput.readOnly = true;

            packingListCbs.forEach(cb => { cb.disabled = true; });

            if (dropdownNoPO) dropdownNoPO.classList.add('hidden');
            if (searchBtnNoPO) searchBtnNoPO.style.display = 'none';

            noPOInput.style.backgroundColor = '#f3f4f6';
            noPOInput.style.cursor = 'not-allowed';
            vendorInput.style.backgroundColor = '#f3f4f6';
            vendorInput.style.cursor = 'not-allowed';
            tanggalInput.style.backgroundColor = '#f3f4f6';
            tanggalInput.style.cursor = 'not-allowed';

            if (lanjutBtn) lanjutBtn.style.display = 'none';

            isDetailFormReady = true;
            document.getElementById('form_submitted').value = '1';

            console.log('Form set to readonly');
        } else {
            noPOInput.removeAttribute('readonly');
            noPOInput.readOnly = false;

            vendorInput.removeAttribute('readonly');
            vendorInput.readOnly = false;

            tanggalInput.removeAttribute('readonly');
            tanggalInput.readOnly = false;

            packingListCbs.forEach(cb => { cb.disabled = false; });

            if (searchBtnNoPO) searchBtnNoPO.style.display = 'block';

            noPOInput.style.backgroundColor = '';
            noPOInput.style.cursor = '';
            vendorInput.style.backgroundColor = '';
            vendorInput.style.cursor = '';
            tanggalInput.style.backgroundColor = '';
            tanggalInput.style.cursor = '';

            if (lanjutBtn) lanjutBtn.style.display = 'inline-block';
            if (saveBtn) saveBtn.classList.add('hidden');
            if (validationSection) validationSection.classList.add('hidden');

            isDetailFormReady = false;
            document.getElementById('form_submitted').value = '0';

            console.log('Form set to editable');
        }
    }

    // Klik di luar dropdown - hanya untuk PO
    document.addEventListener('click', function(e) {
        const noPOWrapper = document.getElementById('no_po')?.closest('.relative');

        if (noPOWrapper && !noPOWrapper.contains(e.target)) {
            document.getElementById('dropdown-no-po')?.classList.add('hidden');
        }
    });

    // Initialize form on page load
    document.addEventListener('DOMContentLoaded', () => {
        const noPOInput = document.getElementById('no_po');
        const searchBtnPO = document.getElementById('no-po-search-btn');
        const vendorInput = document.getElementById('vendor');
        const tanggalInput = document.getElementById('tanggal');
        const form = document.getElementById('penerimaanForm');

        console.log('Initializing form...');
        console.log('No PO Input:', noPOInput);
        console.log('Vendor Input:', vendorInput);
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        if (searchBtnPO) {
            searchBtnPO.addEventListener('click', handleSearchPOClick);
            console.log('Search PO button event listener added');
        }

        // Setup event listener untuk no_po input
        if (noPOInput) {
            noPOInput.addEventListener('input', () => {
                console.log('PO input changed:', noPOInput.value);
                if (!noPOInput.readOnly && !noPOInput.hasAttribute('readonly')) {
                    showDropdownPO(noPOInput);
                }
            });

            // Event listener untuk focus - tampilkan semua jika kosong
            noPOInput.addEventListener('focus', () => {
                console.log('PO input focused');
                if (!noPOInput.readOnly && !noPOInput.hasAttribute('readonly')) {
                    if (noPOInput.value.trim() === '') {
                        showAllPO();
                    } else {
                        showDropdownPO(noPOInput);
                    }
                }
            });

            // Event listener untuk keydown - ESC untuk menutup dropdown
            noPOInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const dropdownPO = document.getElementById('dropdown-no-po');
                    dropdownPO.classList.add('hidden');
                }
            });
        }

        // Set tanggal default
        if (tanggalInput) {
            const today = new Date().toISOString().split('T')[0];
            tanggalInput.value = today;
            formData.tanggal = today;
        }

        // Prevent normal form submission, hanya biarkan melalui button Lanjut
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!isFormLocked) {
                    // Jika form belum di-lock, prevent submit
                    e.preventDefault();
                    return false;
                }
                // Jika sudah locked via button Lanjut, biarkan submit normal
                console.log('Form submitted to server');
            });
        }

        // Check if form was previously submitted (after page refresh or reload)
        const noPofromServer = '{{ request("no_po") }}';
        const npbFromServer = '{{ request("npb") }}';
        const noTerimaFromServer = '{{ request("no_terima") }}';
        const vendorFromServer = '{{ request("vendor") }}';
        const tanggalFromServer = '{{ request("tanggal") }}';
        const formSubmittedFromServer = '{{ request("form_submitted") }}';

        if (formSubmittedFromServer === '1' && noPofromServer && npbFromServer && noTerimaFromServer && vendorFromServer && tanggalFromServer) {
            // Restore form data (packing_list_ids dari session/old input jika perlu)
            formData.no_po = noPofromServer;
            formData.npb = npbFromServer;
            formData.no_terima = noTerimaFromServer;
            formData.vendor = vendorFromServer;
            formData.tanggal = tanggalFromServer;

            // Set form values
            if (document.getElementById('npb')) document.getElementById('npb').value = npbFromServer;
            if (document.getElementById('no_terima')) document.getElementById('no_terima').value = noTerimaFromServer;
            if (vendorInput) vendorInput.value = vendorFromServer;
            if (tanggalInput) tanggalInput.value = tanggalFromServer;
            if (noPOInput) noPOInput.value = noPofromServer;

            // Set form to readonly
            setFormReadonly(true);
            isFormLocked = true;

            console.log('Form restored from server with values:', formData);
        }

        console.log('DOM Content Loaded, form initialized');
    });

    // Fungsi untuk filter table barang berdasarkan input
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-barang');
        const tableBody = document.getElementById('table-barang-body');
        const errorContainer = document.getElementById('laravel-errors');
        const scanInput = document.getElementById('scan_barcode_input');

        if (scanInput) {
            scanInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handlePhysicalScan(this.value);
                }
            });
        }

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
                    errorMessages += `${error}`;
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

        if (searchInput && tableBody) {
            searchInput.addEventListener('input', function() {
                const keyword = searchInput.value.toLowerCase();
                const rows = tableBody.querySelectorAll('tr');

                rows.forEach(row => {
                    const columns = row.querySelectorAll('td');
                    const textContent = Array.from(columns)
                        .map(td => td.textContent.toLowerCase())
                        .join(' ');

                    if (textContent.includes(keyword)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });

    // Function untuk handle tombol search PO
    function handleSearchPOClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const noPOInput = document.getElementById('no_po');
        const dropdownPO = document.getElementById('dropdown-no-po');

        console.log('Search PO button clicked');
        console.log('Current input value:', noPOInput.value);

        // Jika input kosong atau hanya whitespace, tampilkan semua PO
        const query = noPOInput.value.trim();

        if (query === '') {
            console.log('Input kosong, menampilkan semua PO');
            showAllPO();
        } else {
            console.log('Input tidak kosong, melakukan pencarian dengan query:', query);
            showDropdownPO(noPOInput);
        }
    }

    // Function untuk menampilkan semua PO
    function showAllPO() {
        const dropdownPO = document.getElementById('dropdown-no-po');

        console.log('Menampilkan semua PO, total:', nomor_po.length);

        dropdownPO.innerHTML = '';

        if (nomor_po.length === 0) {
            const noDataItem = document.createElement('div');
            noDataItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noDataItem.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Tidak ada data PO';
            dropdownPO.appendChild(noDataItem);
        } else {
            // Tambahkan header info
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-gray-50 border-b font-semibold text-sm text-gray-700';
            headerItem.innerHTML = `<i class="fas fa-list mr-2"></i>Semua Nomor PO (${nomor_po.length})`;
            dropdownPO.appendChild(headerItem);

            // Tampilkan semua PO (batas maksimal untuk performa)
            const maxShow = 50; // Batasi tampilan untuk performa
            const poToShow = nomor_po.slice(0, maxShow);

            poToShow.forEach(po => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const number = document.createElement('div');
                number.className = 'font-semibold text-sm text-gray-800';
                number.textContent = po.number_po;

                const date = document.createElement('div');
                date.className = 'text-sm text-gray-500';
                date.textContent = po.date_po;

                item.appendChild(number);
                item.appendChild(date);

                item.onclick = () => {
                    const noPOInput = document.getElementById('no_po');
                    noPOInput.value = po.number_po;
                    dropdownPO.classList.add('hidden');

                    // Update formData jika diperlukan
                    formData.no_po = po.number_po;

                    console.log('PO selected:', po.number_po);
                };

                dropdownPO.appendChild(item);
            });

            // Jika ada lebih banyak data, tampilkan info
            if (nomor_po.length > maxShow) {
                const moreInfoItem = document.createElement('div');
                moreInfoItem.className = 'px-3 py-2 bg-blue-50 border-b text-sm text-blue-600 text-center';
                moreInfoItem.innerHTML = `<i class="fas fa-info-circle mr-2"></i>Menampilkan ${maxShow} dari ${nomor_po.length} PO. Ketik untuk pencarian spesifik.`;
                dropdownPO.appendChild(moreInfoItem);
            }
        }

        dropdownPO.classList.remove('hidden');
        console.log('All PO dropdown shown');
    }

    // --- FUNGSI VALIDASI BARCODE (TEMP) ---

    // 1. Upload Master TXT
    function uploadMasterTxt() {
        if (!formData.no_po) {
            Swal.fire('Error', 'Nomor PO belum dipilih.', 'error');
            return;
        }

        const fileInput = document.getElementById('txt_file');
        const btnUpload = document.getElementById('btn-upload-txt');
        const originText = btnUpload.innerHTML;

        const data = new FormData();
        data.append('no_po', formData.no_po);
        data.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        if (fileInput.files.length > 0) {
            data.append('txt_file', fileInput.files[0]);
        }

        btnUpload.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...';
        btnUpload.disabled = true;

        fetch('{{ route("temp_scan.upload_txt") }}', {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            btnUpload.innerHTML = originText;
            btnUpload.disabled = false;

            if (result.success) {
                Swal.fire('Berhasil', result.message, 'success');
                
                // Switch view to Scanning Input
                document.getElementById('upload-txt-container').classList.add('hidden');
                document.getElementById('scan-physical-container').classList.remove('hidden');
                document.getElementById('scan_barcode_input').focus();
                
                // Fetch initial scan state
                refreshScanStats();
            } else {
                Swal.fire('Gagal', result.message || 'Gagal memproses file.', 'error');
            }
        })
        .catch(error => {
            btnUpload.innerHTML = originText;
            btnUpload.disabled = false;
            console.error(error);
            Swal.fire('Error', 'Terjadi kesalahan jaringan/server.', 'error');
        });
    }

    // 2. Physical Scan Check
    function handlePhysicalScan(barcodeVal) {
        if (!barcodeVal.trim()) return;
        
        const scanInput = document.getElementById('scan_barcode_input');
        
        // Block until request done to prevent double scanning
        scanInput.disabled = true;

        const data = new FormData();
        data.append('no_po', formData.no_po);
        data.append('barcode', barcodeVal);
        data.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        fetch('{{ route("temp_scan.scan_physical") }}', {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            scanInput.value = '';
            scanInput.disabled = false;
            scanInput.focus();

            if (result.success) {
                // Update text & progress
                updateScanUI(result.scanned_count, result.master_count, result.is_perfect_match);
                
                // SweetAlert toast for quick feedback
                Swal.fire({
                    icon: 'success',
                    title: 'Cocok!',
                    text: result.message,
                    timer: 1000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });

                if (result.is_perfect_match) {
                    onPerfectMatch();
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Tidak Cocok / Gagal',
                    text: result.message,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                
                // Play error sound could be added here
            }
        })
        .catch(error => {
            scanInput.value = '';
            scanInput.disabled = false;
            scanInput.focus();
            console.error(error);
            Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
        });
    }

    // 3. Reset Scan
    function resetScan() {
        if (!formData.no_po) return;

        Swal.fire({
            title: 'Reset Validasi?',
            text: 'Ini akan menghapus semua Master Data dan Riwayat Scan yang belum disimpan. Anda yakin?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Reset',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const data = new FormData();
                data.append('no_po', formData.no_po);
                data.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                fetch('{{ route("temp_scan.flush") }}', {
                    method: 'POST',
                    body: data,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Tereset!', 'Silakan mulai dari awal (Upload TXT).', 'success');
                        
                        document.getElementById('scan-physical-container').classList.add('hidden');
                        document.getElementById('upload-txt-container').classList.remove('hidden');
                        document.getElementById('txt_file').value = '';
                        document.getElementById('save-btn').classList.add('hidden');
                        document.getElementById('scan-success-message').classList.add('hidden');
                        updateScanUI(0, 0, false);
                    }
                });
            }
        });
    }

    // Helper UI Updates
    function refreshScanStats() {
        fetch(`{{ url('penerimaan-barang/temp/scanned-items') }}?no_po=${encodeURIComponent(formData.no_po)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const isPerfect = (res.scanned_count === res.master_count) && (res.master_count > 0);
                updateScanUI(res.scanned_count, res.master_count, isPerfect);
                
                if (isPerfect) onPerfectMatch();
            }
        });
    }

    function updateScanUI(scanned, master, isPerfect) {
        document.getElementById('scan-status-text').textContent = `${scanned} / ${master} Barcode Cocok`;
        
        let percentage = master > 0 ? (scanned / master) * 100 : 0;
        const progress = document.getElementById('scan-progress-bar');
        progress.style.width = percentage + '%';
        
        if (percentage >= 100) {
            progress.classList.replace('bg-blue-600', 'bg-green-500');
            document.getElementById('scan-status-text').classList.replace('text-blue-600', 'text-green-600');
            document.getElementById('scan-status-text').classList.replace('bg-blue-100', 'bg-green-100');
        } else {
            progress.classList.replace('bg-green-500', 'bg-blue-600');
            document.getElementById('scan-status-text').classList.replace('text-green-600', 'text-blue-600');
            document.getElementById('scan-status-text').classList.replace('bg-green-100', 'bg-blue-100');
        }
    }

    function onPerfectMatch() {
        document.getElementById('scan_barcode_input').disabled = true;
        document.getElementById('scan_barcode_input').placeholder = "Validasi selesai...";
        document.getElementById('scan-success-message').classList.remove('hidden');
        document.getElementById('save-btn').classList.remove('hidden');
    }
</script>
@endsection