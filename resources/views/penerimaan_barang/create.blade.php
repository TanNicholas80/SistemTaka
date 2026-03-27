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
                        <!-- Mode Selector -->
                        <label class="text-gray-800 font-medium flex items-center">
                            Mode <span class="text-red-600 ml-1">*</span>
                        </label>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="mode" value="packing_list" class="mode-radio accent-blue-600" checked>
                                <span class="font-medium text-gray-700">Packing List</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="mode" value="non_packing_list" class="mode-radio accent-blue-600">
                                <span class="font-medium text-gray-700">Non Packing List</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="mode" value="antar_toko" class="mode-radio accent-blue-600">
                                <span class="font-medium text-gray-700">Antar Toko</span>
                            </label>
                        </div>

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

                        <!-- Form Nomor DO - Hanya tampil saat mode Antar Toko -->
                        <div id="do-section" class="contents" style="display:none;">
                            <label for="no_do" class="text-gray-800 font-medium flex items-center">
                                Nomor DO <span class="text-red-600 ml-1">*</span>
                                <span class="ml-1"
                                    data-toggle="tooltip"
                                    data-placement="top"
                                    title="Silakan cari & pilih nomor delivery order dari dropdown"
                                    style="cursor: help;">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </label>

                            <div class="relative max-w-md w-full">
                                <div class="flex items-center border border-gray-300 rounded overflow-hidden max-w-[300px]">
                                    <input
                                        id="no_do"
                                        name="no_do"
                                        type="search"
                                        placeholder="Cari/Pilih Nomor Delivery Order..."
                                        class="flex-grow px-2 py-1 outline-none text-sm" />
                                    <button type="button" id="no-do-search-btn" class="px-2 text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div id="dropdown-no-do" class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded shadow mt-1 hidden max-h-40 overflow-y-auto text-sm max-w-[300px]">
                                </div>
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

                        <!-- Form Pemasok -->
                        <label for="vendor" class="text-gray-800 font-medium flex items-center">
                            Terima dari <span class="text-red-600 ml-1">*</span>
                        </label>
                        <div class="w-full max-w-[300px]">
                            <input
                                id="vendor_display"
                                type="text"
                                placeholder="Vendor Terisi Otomatis"
                                value="{{ $vendorName ?? '' }}"
                                class="border border-gray-300 rounded px-2 py-1 w-full bg-[#f3f4f6]"
                                readonly />
                            <input
                                id="vendor"
                                name="vendor"
                                type="hidden"
                                value="{{ $vendor ?? '' }}" />
                        </div>

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

                        <!-- Form Packing List (Multiple) - Hanya tampil saat mode Packing List -->
                        <div id="packing-list-section" class="contents">
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

                <!-- Tahap validasi barcode fisik (upload TXT + scan) dihapus -->

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
                        <div class="max-h-[360px] overflow-y-auto overflow-x-auto">
                            <table class="w-full border-collapse border border-gray-400 text-xs text-center">
                                <thead class="bg-[#607d8b] text-white sticky top-0 z-10">
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
</div>

<!-- Modal Non Packing List - Detail Item -->
<div id="non-pl-modal" class="fixed inset-0 z-50 hidden">
    <!-- Overlay dibuat lebih ringan agar form di belakang masih terlihat -->
    <div class="fixed inset-0 bg-white/30 backdrop-blur-[1px]" onclick="closeNonPlModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg pointer-events-auto max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="bg-[#2c3e50] text-white px-4 py-3 rounded-t-lg flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-edit"></i>
                    <span class="font-semibold text-sm">Rincian Barang</span>
                </div>
                <button type="button" onclick="closeNonPlModal()" class="text-white hover:text-gray-300 text-lg leading-none">&times;</button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b">
                <div id="tab-rincian" class="px-4 py-2 text-sm cursor-pointer text-red-600 border-b-2 border-red-600 font-semibold" onclick="switchModalTab('rincian')">Rincian Barang</div>
                <div id="tab-seri" class="px-4 py-2 text-sm cursor-pointer text-gray-500 hover:text-gray-700" onclick="switchModalTab('seri')">No Seri/Produksi</div>
            </div>

            <!-- Tab Content: Rincian Barang -->
            <div id="content-rincian" class="p-4 overflow-y-auto flex-1">
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <label class="w-28 text-gray-600 font-medium pt-1">Kode #</label>
                        <span id="modal-kode-barang" class="text-red-600 font-semibold"></span>
                    </div>
                    <div class="flex items-start gap-3">
                        <label class="w-28 text-gray-600 font-medium pt-1">Nama Barang *</label>
                        <input id="modal-nama-barang" type="text" class="flex-1 border rounded px-2 py-1 bg-gray-50" readonly>
                    </div>
                    <div class="flex items-start gap-3">
                        <label class="w-28 text-gray-600 font-medium pt-1">Kuantitas *</label>
                        <div class="flex items-center gap-2">
                            <input id="modal-kuantitas" type="text" class="w-24 border rounded px-2 py-1 bg-gray-50 text-right" readonly>
                            <span id="modal-unit" class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-medium">METER</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <label class="w-28 text-gray-600 font-medium pt-1">Gudang *</label>
                        <span id="modal-gudang" class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-medium">GUDANG STOK</span>
                    </div>
                </div>
            </div>

            <!-- Tab Content: No Seri/Produksi -->
            <div id="content-seri" class="p-4 overflow-y-auto flex-1 hidden">
                <div class="space-y-3 text-sm">
                    <!-- Input kuantitas per barcode -->
                    <div id="nonpl-generate-row" class="flex items-center gap-3">
                        <label class="w-20 text-gray-600 font-medium">Kuantitas</label>
                        <input id="modal-sn-qty-input" type="number" step="0.001" min="0.001" placeholder="Qty per barcode" class="w-24 border rounded px-2 py-1 text-right" onkeypress="if(event.key==='Enter'){event.preventDefault();addSerialNumber();}">
                    </div>
                    <div id="interstore-scan-row" class="hidden flex items-center gap-3">
                        <label class="w-20 text-gray-600 font-medium">Scan</label>
                        <div class="flex-1 flex items-center gap-2">
                            <input id="modal-scan-barcode-input" type="text" placeholder="Scan barcode serial number..." class="flex-1 border rounded px-2 py-1" onkeypress="if(event.key==='Enter'){event.preventDefault();scanInterStoreSerial();}">
                            <button type="button" id="btn-scan-interstore" onclick="scanInterStoreSerial()" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm whitespace-nowrap">
                                <i class="fas fa-barcode mr-1"></i>Scan
                            </button>
                        </div>
                    </div>
                    <!-- Input barcode (auto-generate) -->
                    <div id="nonpl-number-row" class="flex items-center gap-3">
                        <label class="w-20 text-gray-600 font-medium">Nomor #</label>
                        <div class="flex-1 flex items-center gap-2">
                            <input type="text" class="flex-1 border rounded px-2 py-1 bg-gray-100 text-gray-500" placeholder="Auto-generate dari sistem" readonly>
                            <button type="button" id="btn-add-sn" onclick="addSerialNumber()" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm whitespace-nowrap">
                                <i class="fas fa-plus mr-1"></i>Generate
                            </button>
                        </div>
                    </div>

                    <!-- Table serial numbers -->
                    <div class="border rounded max-h-52 overflow-y-auto overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-[#607d8b] text-white sticky top-0 z-10">
                                <tr>
                                    <th class="px-2 py-1.5 w-10"></th>
                                    <th id="modal-sn-print-header" class="px-2 py-1.5 w-10 text-center">Print</th>
                                    <th class="px-2 py-1.5 text-left">Nomor #</th>
                                    <th class="px-2 py-1.5 text-right">Kuantitas</th>
                                </tr>
                            </thead>
                            <tbody id="modal-sn-tbody">
                                <tr><td colspan="4" class="px-2 py-4 text-center text-gray-400 border-b">Belum ada serial number</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="bulk-print-row" class="flex justify-end pt-2">
                        <button
                            type="button"
                            onclick="bulkPrintNonPlSerial()"
                            class="bg-purple-600 text-white px-4 py-1.5 rounded hover:bg-purple-700 text-sm font-medium whitespace-nowrap"
                        >
                            <i class="fas fa-print mr-2"></i>Bulk Print
                        </button>
                    </div>

                    <!-- Summary -->
                    <p id="modal-sn-summary" class="text-sm text-gray-600">0 No Seri/Produksi, Jumlah 0</p>
                </div>
            </div>

            <!-- Footer buttons -->
            <div class="px-4 py-3 border-t flex items-center justify-between">
                <button type="button" onclick="clearItemSerialNumbers()" class="border border-gray-300 text-gray-700 px-4 py-1.5 rounded hover:bg-gray-100 text-sm">Hapus</button>
                <button type="button" onclick="closeNonPlModal()" class="bg-blue-600 text-white px-6 py-1.5 rounded hover:bg-blue-700 text-sm font-medium">Lanjut</button>
            </div>
        </div>
    </div>
</div>

<script>
    const nomor_po = JSON.parse(`{!! addslashes(json_encode($purchase_order)) !!}`);
    const nomor_do = JSON.parse(`{!! addslashes(json_encode($delivery_orders ?? [])) !!}`);
    const printNonPlPdfEndpoint = "{{ route('penerimaan-barang.non-pl.print-pdf') }}";

    console.log('Nomor PO data:', nomor_po);

    // Variabel untuk menyimpan data form
    let formData = {
        no_po: '',
        npb: '',
        no_terima: '',
        vendor: '',
        vendor_name: '',
        tanggal: '',
        mode: 'packing_list',
        no_do: '',
        packing_list_ids: []
    };

    // Non Packing List state
    let nonPlItems = [];
    let nonPlKodeCustomer = '';
    let interStoreItems = [];
    let currentModalMode = 'non_packing_list';
    let currentModalItemIndex = -1;
    let interStoreAutoScanLock = false;

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
        const vendorDisplayInput = document.getElementById('vendor_display');
        const tanggalInput = document.getElementById('tanggal');
        const noDOInput = document.getElementById('no_do');
        const selectedMode = document.querySelector('input[name="mode"]:checked')?.value || 'packing_list';
        const packingListCheckboxes = document.querySelectorAll('.packing-list-cb:checked');

        const packingListIds = Array.from(packingListCheckboxes).map(cb => cb.value);

        console.log('Lanjut button clicked');
        console.log('Mode:', selectedMode);
        console.log('No PO:', noPOInput.value);
        console.log('NPB:', npbInput.value);
        console.log('No Terima:', noTerimaInput?.value);
        console.log('Vendor:', vendorInput.value);
        console.log('Tanggal:', tanggalInput.value);
        console.log('No DO:', noDOInput?.value);
        console.log('Packing List IDs:', packingListIds);

        // Validasi form - packing list hanya wajib jika mode = packing_list
        const baseFieldsMissing = !noPOInput.value.trim() || !npbInput.value.trim() || !tanggalInput.value.trim();
        const packingListMissing = selectedMode === 'packing_list' && packingListIds.length === 0;
        const doMissing = selectedMode === 'antar_toko' && !(noDOInput?.value || '').trim();

        if (baseFieldsMissing || packingListMissing || doMissing) {
            const msg = packingListMissing
                ? 'Harap lengkapi No PO, Tanggal, dan pilih minimal satu packing list!'
                : doMissing
                ? 'Harap lengkapi No PO, Tanggal, dan pilih Nomor DO!'
                : 'Harap lengkapi No PO dan Tanggal!';
            Swal.fire({
                icon: 'warning',
                title: 'Field Tidak Lengkap',
                text: msg,
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
        formData.vendor_name = vendorDisplayInput?.value || '';
        formData.tanggal = tanggalInput.value;
        formData.mode = selectedMode;
        formData.no_do = selectedMode === 'antar_toko' ? (noDOInput?.value || '') : '';
        formData.packing_list_ids = selectedMode === 'packing_list' ? packingListIds : [];

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
        fetchDetailPO(formData.no_po, formData.npb, formData.packing_list_ids, formData.mode, formData.no_do)
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
    function fetchDetailPO(noPO, npb, packingListIds, mode, noDO = '') {
        return new Promise((resolve, reject) => {
            // Tampilkan loading indicator
            const tableBody = document.getElementById('table-barang-body');
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading data...</td></tr>';

            // Siapkan data untuk request
            const data = new FormData();
            data.append('no_po', noPO);
            data.append('npb', npb);
            data.append('mode', mode || 'packing_list');
            if (mode === 'packing_list') {
                packingListIds.forEach(id => data.append('packing_list_ids[]', id));
            } else if (mode === 'antar_toko') {
                data.append('no_do', noDO);
            }

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
                        const vendorDisplayInput = document.getElementById('vendor_display');
                        vendorInput.value = data.vendor.vendorNo;
                        if (vendorDisplayInput) {
                            vendorDisplayInput.value = data.vendor.vendorName || data.vendor.vendorNo;
                        }
                        formData.vendor = data.vendor.vendorNo;
                        formData.vendor_name = data.vendor.vendorName || data.vendor.vendorNo || '';
                        console.log('Vendor updated in formData:', formData.vendor, formData.vendor_name);
                    }

                    if (data.mode === 'non_packing_list') {
                        // Simpan ke nonPlItems state
                        nonPlKodeCustomer = data.kode_customer || '';
                        nonPlItems = (data.barang || []).map(item => ({
                            kode_barang: item.kode_barang,
                            nama_barang: item.nama_barang,
                            name: item.name || item.nama_barang || '',
                            charField1: item.charField1 || '',
                            charField2: item.charField2 || '',
                            charField4: item.charField4 || '',
                            charField5: item.charField5 || '',
                            charField6: item.charField6 || '',
                            nameWithIndentStrip: item.nameWithIndentStrip || '',
                            kuantitas: item.kuantitas || 0,
                            unit_price: item.unit_price || 0,
                            uom: item.uom || 'METER',
                            serial_numbers: [],
                        }));
                        updateNonPlTable();
                        // Tombol Save tampil setelah semua item complete
                        checkNonPlComplete();
                    } else if (data.mode === 'antar_toko') {
                        interStoreItems = (data.barang || []).map(item => ({
                            kode_barang: item.kode_barang,
                            nama_barang: item.nama_barang,
                            kuantitas: item.kuantitas || 0,
                            unit_price: item.unit_price || 0,
                            uom: item.uom || 'METER',
                            expected_serial_numbers: Array.isArray(item.expected_serial_numbers) ? item.expected_serial_numbers : [],
                            serial_numbers: [],
                        }));
                        updateInterStoreTable();
                        checkInterStoreComplete();
                    } else {
                        // Mode packing_list: tidak lagi butuh upload TXT / scan fisik.
                        // Setelah detail barang dimuat, tombol Save langsung bisa dipakai.
                        updateBarangTable(data.barang);
                        document.getElementById('save-btn').classList.remove('hidden');
                    }

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

    // ===================== NON PACKING LIST FUNCTIONS =====================

    function isInterStoreItemComplete(item) {
        const expected = Array.isArray(item.expected_serial_numbers) ? item.expected_serial_numbers : [];
        if (expected.length === 0) return true;
        const scannedMap = {};
        (item.serial_numbers || []).forEach(sn => {
            scannedMap[(sn.barcode || '').trim()] = true;
        });
        return expected.every(sn => scannedMap[(sn.barcode || '').trim()] === true);
    }

    function updateInterStoreTable() {
        const tableBody = document.getElementById('table-barang-body');
        tableBody.innerHTML = '';

        if (interStoreItems.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="border border-gray-400 px-2 py-3 text-center"><i class="fas fa-info-circle mr-2"></i>Tidak ada item dari Delivery Order</td></tr>';
            return;
        }

        interStoreItems.forEach((item, index) => {
            const expectedCount = (item.expected_serial_numbers || []).length;
            const scannedCount = (item.serial_numbers || []).length;
            const isComplete = isInterStoreItemComplete(item);

            const row = document.createElement('tr');
            row.className = 'hover:bg-blue-50 cursor-pointer transition-colors';
            row.title = 'Double-click untuk scan serial number';
            row.addEventListener('dblclick', () => openItemSerialModal(index, 'antar_toko'));

            row.innerHTML = `
                <td class="border border-gray-400 px-1 py-3 text-center align-top">${index + 1}</td>
                <td class="border border-gray-400 px-2 py-3 text-left align-top">${item.nama_barang || '-'}</td>
                <td class="border border-gray-400 px-2 py-3 align-top">${item.kode_barang || '-'}</td>
                <td class="border border-gray-400 px-2 py-3 align-top text-center">
                    ${isComplete
                        ? '<span class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-1"></i>' + scannedCount + '/' + expectedCount + ' SN</span>'
                        : '<span class="text-orange-500"><i class="fas fa-exclamation-circle mr-1"></i>' + scannedCount + '/' + expectedCount + ' SN</span>'}
                </td>
                <td class="border border-gray-400 px-2 py-3 align-top font-semibold text-right">${item.kuantitas}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    function checkInterStoreComplete() {
        const allComplete = interStoreItems.length > 0 && interStoreItems.every(item => isInterStoreItemComplete(item));
        const saveBtn = document.getElementById('save-btn');
        if (allComplete) {
            saveBtn.classList.remove('hidden');
        } else {
            saveBtn.classList.add('hidden');
        }
    }

    function updateNonPlTable() {
        const tableBody = document.getElementById('table-barang-body');
        tableBody.innerHTML = '';

        if (nonPlItems.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="border border-gray-400 px-2 py-3 text-center"><i class="fas fa-info-circle mr-2"></i>Tidak ada item yang memenuhi syarat</td></tr>';
            return;
        }

        nonPlItems.forEach((item, index) => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-blue-50 cursor-pointer transition-colors';
            row.title = 'Double-click untuk atur serial number';
            row.addEventListener('dblclick', () => openItemSerialModal(index, 'non_packing_list'));

            const totalAssigned = item.serial_numbers.reduce((s, sn) => s + (parseFloat(sn.quantity) || 0), 0);
            const isComplete = totalAssigned >= item.kuantitas && item.serial_numbers.length > 0;

            row.innerHTML = `
                <td class="border border-gray-400 px-1 py-3 text-center align-top">${index + 1}</td>
                <td class="border border-gray-400 px-2 py-3 text-left align-top">${item.nama_barang || '-'}</td>
                <td class="border border-gray-400 px-2 py-3 align-top">${item.kode_barang || '-'}</td>
                <td class="border border-gray-400 px-2 py-3 align-top text-center">
                    ${isComplete
                        ? '<span class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-1"></i>' + item.serial_numbers.length + ' SN</span>'
                        : '<span class="text-orange-500"><i class="fas fa-exclamation-circle mr-1"></i>Belum</span>'}
                </td>
                <td class="border border-gray-400 px-2 py-3 align-top font-semibold text-right">${item.kuantitas}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    function checkNonPlComplete() {
        const allComplete = nonPlItems.length > 0 && nonPlItems.every(item => {
            const totalAssigned = item.serial_numbers.reduce((s, sn) => s + (parseFloat(sn.quantity) || 0), 0);
            return totalAssigned >= item.kuantitas && item.serial_numbers.length > 0;
        });

        const saveBtn = document.getElementById('save-btn');
        if (allComplete) {
            saveBtn.classList.remove('hidden');
        } else {
            saveBtn.classList.add('hidden');
        }
    }

    // --- MODAL FUNCTIONS ---

    function getCurrentModalItems() {
        return currentModalMode === 'antar_toko' ? interStoreItems : nonPlItems;
    }

    function openItemSerialModal(itemIndex, mode = 'non_packing_list') {
        currentModalMode = mode;
        currentModalItemIndex = itemIndex;
        const item = getCurrentModalItems()[itemIndex];
        if (!item) return;

        document.getElementById('modal-kode-barang').textContent = item.kode_barang + ' - ' + (item.nama_barang || '');
        document.getElementById('modal-nama-barang').value = item.nama_barang || '';
        document.getElementById('modal-kuantitas').value = item.kuantitas;
        document.getElementById('modal-unit').textContent = item.uom || 'METER';
        document.getElementById('modal-gudang').textContent = 'GUDANG STOK';

        const nonPlGenerateRow = document.getElementById('nonpl-generate-row');
        const nonPlNumberRow = document.getElementById('nonpl-number-row');
        const interStoreScanRow = document.getElementById('interstore-scan-row');
        const bulkPrintRow = document.getElementById('bulk-print-row');
        const printHeader = document.getElementById('modal-sn-print-header');
        if (mode === 'antar_toko') {
            nonPlGenerateRow?.classList.add('hidden');
            nonPlNumberRow?.classList.add('hidden');
            interStoreScanRow?.classList.remove('hidden');
            bulkPrintRow?.classList.add('hidden');
            if (printHeader) printHeader.classList.add('hidden');
            const scanInput = document.getElementById('modal-scan-barcode-input');
            if (scanInput) {
                scanInput.value = '';
            }
        } else {
            nonPlGenerateRow?.classList.remove('hidden');
            nonPlNumberRow?.classList.remove('hidden');
            interStoreScanRow?.classList.add('hidden');
            bulkPrintRow?.classList.remove('hidden');
            if (printHeader) printHeader.classList.remove('hidden');
        }

        switchModalTab('rincian');
        renderModalSerialNumbers();
        document.getElementById('non-pl-modal').classList.remove('hidden');
    }

    function closeNonPlModal() {
        document.getElementById('non-pl-modal').classList.add('hidden');
        currentModalItemIndex = -1;
        if (currentModalMode === 'antar_toko') {
            updateInterStoreTable();
            checkInterStoreComplete();
        } else {
            updateNonPlTable();
            checkNonPlComplete();
        }
    }

    function switchModalTab(tab) {
        const rincianTab = document.getElementById('tab-rincian');
        const seriTab = document.getElementById('tab-seri');
        const rincianContent = document.getElementById('content-rincian');
        const seriContent = document.getElementById('content-seri');

        const activeClass = 'text-red-600 border-b-2 border-red-600 font-semibold';
        const inactiveClass = 'text-gray-500 hover:text-gray-700';

        if (tab === 'rincian') {
            rincianTab.className = 'px-4 py-2 text-sm cursor-pointer ' + activeClass;
            seriTab.className = 'px-4 py-2 text-sm cursor-pointer ' + inactiveClass;
            rincianContent.classList.remove('hidden');
            seriContent.classList.add('hidden');
        } else {
            seriTab.className = 'px-4 py-2 text-sm cursor-pointer ' + activeClass;
            rincianTab.className = 'px-4 py-2 text-sm cursor-pointer ' + inactiveClass;
            seriContent.classList.remove('hidden');
            rincianContent.classList.add('hidden');
            if (currentModalMode === 'antar_toko') {
                document.getElementById('modal-scan-barcode-input')?.focus();
            } else {
                document.getElementById('modal-sn-qty-input')?.focus();
            }
        }
    }

    function renderModalSerialNumbers() {
        if (currentModalItemIndex < 0) return;
        const item = getCurrentModalItems()[currentModalItemIndex];
        const tbody = document.getElementById('modal-sn-tbody');
        tbody.innerHTML = '';
        const printHeader = document.getElementById('modal-sn-print-header');
        const isInterStore = currentModalMode === 'antar_toko';
        if (printHeader) {
            if (isInterStore) {
                printHeader.classList.add('hidden');
            } else {
                printHeader.classList.remove('hidden');
            }
        }

        if (item.serial_numbers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${isInterStore ? 3 : 4}" class="px-2 py-4 text-center text-gray-400 border-b">Belum ada serial number</td></tr>`;
        } else {
            item.serial_numbers.forEach((sn, snIdx) => {
                const tr = document.createElement('tr');
                tr.className = 'border-b hover:bg-gray-50';
                if (isInterStore) {
                    tr.innerHTML = `
                        <td class="px-2 py-2 text-center w-10">
                            <button type="button" onclick="removeSerialNumber(${snIdx})" class="bg-red-700 text-white text-xs px-2 py-1 rounded hover:bg-red-800">X</button>
                        </td>
                        <td class="px-2 py-2 font-mono">${sn.barcode}</td>
                        <td class="px-2 py-2 text-right">
                            <span class="inline-block w-20 text-right">${(parseFloat(sn.quantity) || 0)}</span>
                        </td>
                    `;
                } else {
                    tr.innerHTML = `
                        <td class="px-2 py-2 text-center w-10">
                            <button type="button" onclick="removeSerialNumber(${snIdx})" class="bg-red-700 text-white text-xs px-2 py-1 rounded hover:bg-red-800">X</button>
                        </td>
                        <td class="px-2 py-2 text-center w-10">
                            <button type="button" onclick="printNonPlSerial(${snIdx})" class="bg-gray-800 text-white text-xs px-2 py-1 rounded hover:bg-gray-900" title="Print label">
                                <i class="fas fa-print"></i>
                            </button>
                        </td>
                        <td class="px-2 py-2 font-mono">${sn.barcode}</td>
                        <td class="px-2 py-2 text-right">
                            <input type="number" step="0.001" min="0.001" value="${(parseFloat(sn.quantity) || 0)}" class="w-20 text-right border rounded px-1 py-0.5 text-sm" onchange="updateSnQty(${snIdx}, this.value)">
                        </td>
                    `;
                }
                tbody.appendChild(tr);
            });
        }

        const totalQty = item.serial_numbers.reduce((s, sn) => s + (parseFloat(sn.quantity) || 0), 0);
        if (currentModalMode === 'antar_toko') {
            const expectedCount = (item.expected_serial_numbers || []).length;
            document.getElementById('modal-sn-summary').textContent = `${item.serial_numbers.length}/${expectedCount} serial ter-scan, Jumlah ${totalQty % 1 === 0 ? totalQty : totalQty.toFixed(3)}`;
        } else {
            document.getElementById('modal-sn-summary').textContent = `${item.serial_numbers.length} No Seri/Produksi, Jumlah ${totalQty % 1 === 0 ? totalQty : totalQty.toFixed(3)}`;
        }
    }

    function scanInterStoreSerial() {
        if (currentModalMode !== 'antar_toko' || currentModalItemIndex < 0) return;
        const item = interStoreItems[currentModalItemIndex];
        const scanInput = document.getElementById('modal-scan-barcode-input');
        const scanned = (scanInput?.value || '').trim();
        if (!scanned) return;

        const expected = item.expected_serial_numbers || [];
        const expectedMatch = expected.find(sn => (sn.barcode || '').trim() === scanned);
        if (!expectedMatch) {
            Swal.fire({ icon: 'error', title: 'Barcode tidak cocok', text: 'Barcode ini tidak ada pada detail DO item terkait.', timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
            scanInput.value = '';
            return;
        }

        const already = (item.serial_numbers || []).some(sn => (sn.barcode || '').trim() === scanned);
        if (already) {
            Swal.fire({ icon: 'warning', title: 'Barcode sudah discan', text: 'Serial number ini sudah pernah diinput.', timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
            scanInput.value = '';
            return;
        }

        item.serial_numbers.push({
            barcode: scanned,
            quantity: parseFloat(expectedMatch.quantity) || 0,
        });
        renderModalSerialNumbers();
        scanInput.value = '';
        scanInput.focus();
    }

    function printNonPlSerial(snIdx) {
        if (currentModalItemIndex < 0) return;
        const item = nonPlItems[currentModalItemIndex];
        if (!item?.serial_numbers?.[snIdx]) return;

        const sn = item.serial_numbers[snIdx];
        const itemPayload = {
            charField1: item.charField1 || '',
            charField2: item.charField2 || '',
            charField4: item.charField4 || '',
            charField5: item.charField5 || '',
            charField6: item.charField6 || '',
            name: item.name || item.nama_barang || '',
            nameWithIndentStrip: item.nameWithIndentStrip || '',
            no: item.kode_barang || '',
            itemUnitName: item.uom || '',
        };

        const labelPayload = [{
            barcode: sn.barcode,
            quantity: sn.quantity
        }];

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch(printNonPlPdfEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                item: itemPayload,
                labels: labelPayload
            })
        })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(t => { throw new Error(t || 'Gagal generate PDF'); });
                }
                return res.blob();
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                window.open(url, '_blank');
            })
            .catch(err => {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Error Print', text: err.message || 'Gagal generate PDF', timer: 3500, showConfirmButton: false, toast: true, position: 'top-end' });
            });
    }

    function bulkPrintNonPlSerial() {
        if (currentModalItemIndex < 0) return;
        const item = nonPlItems[currentModalItemIndex];
        if (!item?.serial_numbers || item.serial_numbers.length === 0) return;

        const itemPayload = {
            charField1: item.charField1 || '',
            charField2: item.charField2 || '',
            charField4: item.charField4 || '',
            charField5: item.charField5 || '',
            charField6: item.charField6 || '',
            name: item.name || item.nama_barang || '',
            nameWithIndentStrip: item.nameWithIndentStrip || '',
            no: item.kode_barang || '',
            itemUnitName: item.uom || '',
        };

        const labelPayload = item.serial_numbers.map(sn => ({
            barcode: sn.barcode,
            quantity: sn.quantity
        }));

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch(printNonPlPdfEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                item: itemPayload,
                labels: labelPayload
            })
        })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(t => { throw new Error(t || 'Gagal generate PDF'); });
                }
                return res.blob();
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                window.open(url, '_blank');
            })
            .catch(err => {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Error Bulk Print', text: err.message || 'Gagal generate PDF', timer: 3500, showConfirmButton: false, toast: true, position: 'top-end' });
            });
    }

    function addSerialNumber() {
        if (currentModalItemIndex < 0) return;
        const item = nonPlItems[currentModalItemIndex];
        const qtyInput = document.getElementById('modal-sn-qty-input');
        const qty = parseFloat(qtyInput.value);

        if (!qty || qty <= 0) {
            Swal.fire({ icon: 'warning', title: 'Kuantitas tidak valid', text: 'Masukkan kuantitas > 0', timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
            return;
        }

        const assigned = item.serial_numbers.reduce((s, sn) => s + (parseFloat(sn.quantity) || 0), 0);
        const itemQty = parseFloat(item.kuantitas) || 0;
        const nextTotal = assigned + qty;
        // toleransi kecil untuk floating point
        if (itemQty > 0 && nextTotal > itemQty + 1e-9) {
            Swal.fire({
                icon: 'error',
                title: 'Kuantitas Melebihi',
                text: `Total kuantitas serial (${nextTotal.toFixed(3)}) melebihi kuantitas item (${itemQty}).`,
                timer: 3500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return;
        }

        const btn = document.getElementById('btn-add-sn');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        const data = new FormData();
        data.append('kode_customer', nonPlKodeCustomer);
        data.append('quantity', qty);
        data.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        fetch('{{ route("penerimaan-barang.generate-barcode-non-pl") }}', {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(result => {
            btn.innerHTML = origText;
            btn.disabled = false;

            if (result.success) {
                item.serial_numbers.push({
                    barcode: result.barcode,
                    quantity: result.quantity,
                    reservation_token: result.token,
                });
                renderModalSerialNumbers();
                qtyInput.value = '';
                qtyInput.focus();
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: result.message || 'Gagal generate barcode', timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
            }
        })
        .catch(err => {
            btn.innerHTML = origText;
            btn.disabled = false;
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.', timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
        });
    }

    function removeSerialNumber(snIdx) {
        if (currentModalItemIndex < 0) return;
        const items = getCurrentModalItems();
        items[currentModalItemIndex].serial_numbers.splice(snIdx, 1);
        renderModalSerialNumbers();
    }

    function updateSnQty(snIdx, newVal) {
        if (currentModalMode === 'antar_toko') return;
        if (currentModalItemIndex < 0) return;
        const item = getCurrentModalItems()[currentModalItemIndex];
        const qty = parseFloat(newVal) || 0;
        if (qty <= 0) return;

        const prevQty = parseFloat(item.serial_numbers[snIdx]?.quantity) || 0;
        const assignedOther = item.serial_numbers.reduce((s, sn, i) => {
            if (i === snIdx) return s;
            return s + (parseFloat(sn.quantity) || 0);
        }, 0);

        const itemQty = parseFloat(item.kuantitas) || 0;
        const nextTotal = assignedOther + qty;
        if (itemQty > 0 && nextTotal > itemQty + 1e-9) {
            Swal.fire({
                icon: 'error',
                title: 'Kuantitas Melebihi',
                text: `Total kuantitas serial (${nextTotal.toFixed(3)}) melebihi kuantitas item (${itemQty}).`,
                timer: 3500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });

            // Revert nilai di state dan UI
            item.serial_numbers[snIdx].quantity = prevQty;
            renderModalSerialNumbers();
            return;
        }

        item.serial_numbers[snIdx].quantity = qty;
        renderModalSerialNumbers();
    }

    function clearItemSerialNumbers() {
        if (currentModalItemIndex < 0) return;
        Swal.fire({
            title: 'Hapus semua serial number?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => {
            if (r.isConfirmed) {
                const items = getCurrentModalItems();
                items[currentModalItemIndex].serial_numbers = [];
                renderModalSerialNumbers();
            }
        });
    }

    // ===================== END NON PACKING LIST FUNCTIONS =====================

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
        if (formData.mode === 'antar_toko') {
            const allComplete = interStoreItems.length > 0 && interStoreItems.every(item => isInterStoreItemComplete(item));
            if (!allComplete) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Serial Number Belum Lengkap',
                    text: 'Lengkapi scan serial number semua item terlebih dahulu.',
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return false;
            }
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
                if (document.getElementById('vendor_display')) {
                    document.getElementById('vendor_display').value = formData.vendor_name || formData.vendor;
                }
                document.getElementById('tanggal').value = formData.tanggal;

                const form = document.getElementById('penerimaanForm');
                if (form) {
                    // Tambahkan hidden input mode
                    let modeInput = form.querySelector('input[name="mode"][type="hidden"]');
                    if (!modeInput) {
                        modeInput = document.createElement('input');
                        modeInput.type = 'hidden';
                        modeInput.name = 'mode';
                        form.appendChild(modeInput);
                    }
                    modeInput.value = formData.mode;

                    // Hapus packing_list_ids hidden lama (jika ada), tambahkan yang terpilih
                    form.querySelectorAll('input[name="packing_list_ids[]"][type="hidden"]').forEach(el => el.remove());
                    // Hapus non_pl_items hidden lama
                    form.querySelectorAll('input[name="non_pl_items"][type="hidden"]').forEach(el => el.remove());
                    // Hapus antar_toko_items hidden lama
                    form.querySelectorAll('input[name="antar_toko_items"][type="hidden"]').forEach(el => el.remove());
                    // Hapus no_do hidden lama
                    form.querySelectorAll('input[name="no_do"][type="hidden"]').forEach(el => el.remove());

                    if (formData.mode === 'packing_list') {
                        formData.packing_list_ids.forEach(id => {
                            const inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'packing_list_ids[]';
                            inp.value = id;
                            form.appendChild(inp);
                        });
                    } else if (formData.mode === 'non_packing_list') {
                        const nonPlInput = document.createElement('input');
                        nonPlInput.type = 'hidden';
                        nonPlInput.name = 'non_pl_items';
                        nonPlInput.value = JSON.stringify(nonPlItems);
                        form.appendChild(nonPlInput);
                    } else if (formData.mode === 'antar_toko') {
                        const doInput = document.createElement('input');
                        doInput.type = 'hidden';
                        doInput.name = 'no_do';
                        doInput.value = formData.no_do || '';
                        form.appendChild(doInput);

                        const antarTokoInput = document.createElement('input');
                        antarTokoInput.type = 'hidden';
                        antarTokoInput.name = 'antar_toko_items';
                        antarTokoInput.value = JSON.stringify(interStoreItems);
                        form.appendChild(antarTokoInput);
                    }
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
        const vendorDisplayInput = document.getElementById('vendor_display');
        const tanggalInput = document.getElementById('tanggal');
        const noDOInput = document.getElementById('no_do');
        const packingListCbs = document.querySelectorAll('.packing-list-cb');
        const modeRadios = document.querySelectorAll('.mode-radio');
        const dropdownNoPO = document.getElementById('dropdown-no-po');
        const dropdownNoDO = document.getElementById('dropdown-no-do');
        const searchBtnNoPO = document.getElementById('no-po-search-btn');
        const searchBtnNoDO = document.getElementById('no-do-search-btn');
        const lanjutBtn = document.getElementById('lanjut-btn');
        const saveBtn = document.getElementById('save-btn');
        const validationSection = document.getElementById('barcode-validation-section');

        console.log('Setting readonly:', readonly);

        if (readonly) {
            noPOInput.setAttribute('readonly', 'readonly');
            noPOInput.readOnly = true;

            tanggalInput.setAttribute('readonly', 'readonly');
            tanggalInput.readOnly = true;
            if (noDOInput) {
                noDOInput.setAttribute('readonly', 'readonly');
                noDOInput.readOnly = true;
            }

            packingListCbs.forEach(cb => { cb.disabled = true; });
            modeRadios.forEach(r => { r.disabled = true; });

            if (dropdownNoPO) dropdownNoPO.classList.add('hidden');
            if (dropdownNoDO) dropdownNoDO.classList.add('hidden');
            if (searchBtnNoPO) searchBtnNoPO.style.display = 'none';
            if (searchBtnNoDO) searchBtnNoDO.style.display = 'none';

            noPOInput.style.backgroundColor = '#f3f4f6';
            noPOInput.style.cursor = 'not-allowed';
            if (vendorDisplayInput) {
                vendorDisplayInput.style.backgroundColor = '#f3f4f6';
                vendorDisplayInput.style.cursor = 'not-allowed';
            }
            tanggalInput.style.backgroundColor = '#f3f4f6';
            tanggalInput.style.cursor = 'not-allowed';
            if (noDOInput) {
                noDOInput.style.backgroundColor = '#f3f4f6';
                noDOInput.style.cursor = 'not-allowed';
            }

            if (lanjutBtn) lanjutBtn.style.display = 'none';

            isDetailFormReady = true;
            document.getElementById('form_submitted').value = '1';

            console.log('Form set to readonly');
        } else {
            noPOInput.removeAttribute('readonly');
            noPOInput.readOnly = false;

            tanggalInput.removeAttribute('readonly');
            tanggalInput.readOnly = false;
            if (noDOInput) {
                noDOInput.removeAttribute('readonly');
                noDOInput.readOnly = false;
            }

            packingListCbs.forEach(cb => { cb.disabled = false; });
            modeRadios.forEach(r => { r.disabled = false; });

            if (searchBtnNoPO) searchBtnNoPO.style.display = 'block';
            if (searchBtnNoDO) searchBtnNoDO.style.display = 'block';

            noPOInput.style.backgroundColor = '';
            noPOInput.style.cursor = '';
            if (vendorDisplayInput) {
                vendorDisplayInput.style.backgroundColor = '';
                vendorDisplayInput.style.cursor = '';
            }
            tanggalInput.style.backgroundColor = '';
            tanggalInput.style.cursor = '';
            if (noDOInput) {
                noDOInput.style.backgroundColor = '';
                noDOInput.style.cursor = '';
            }

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
        const noDOWrapper = document.getElementById('no_do')?.closest('.relative');

        if (noPOWrapper && !noPOWrapper.contains(e.target)) {
            document.getElementById('dropdown-no-po')?.classList.add('hidden');
        }
        if (noDOWrapper && !noDOWrapper.contains(e.target)) {
            document.getElementById('dropdown-no-do')?.classList.add('hidden');
        }
    });

    // Toggle packing list section berdasarkan mode
    function toggleModeSections(mode) {
        const packingListSection = document.getElementById('packing-list-section');
        const doSection = document.getElementById('do-section');
        const noDOInput = document.getElementById('no_do');
        const saveBtn = document.getElementById('save-btn');

        if (mode === 'packing_list') {
            packingListSection.style.display = 'contents';
            doSection.style.display = 'none';
            if (noDOInput) noDOInput.value = '';
            saveBtn?.classList.add('hidden');
        } else if (mode === 'antar_toko') {
            packingListSection.style.display = 'none';
            document.querySelectorAll('.packing-list-cb').forEach(cb => { cb.checked = false; });
            doSection.style.display = 'contents';
            saveBtn?.classList.add('hidden');
        } else {
            packingListSection.style.display = 'none';
            document.querySelectorAll('.packing-list-cb').forEach(cb => { cb.checked = false; });
            doSection.style.display = 'none';
            if (noDOInput) noDOInput.value = '';
            saveBtn?.classList.add('hidden');
        }
    }

    // Initialize form on page load
    document.addEventListener('DOMContentLoaded', () => {
        const noPOInput = document.getElementById('no_po');
        const searchBtnPO = document.getElementById('no-po-search-btn');
        const noDOInput = document.getElementById('no_do');
        const searchBtnDO = document.getElementById('no-do-search-btn');
        const vendorInput = document.getElementById('vendor');
        const vendorDisplayInput = document.getElementById('vendor_display');
        const tanggalInput = document.getElementById('tanggal');
        const form = document.getElementById('penerimaanForm');

        console.log('Initializing form...');
        console.log('No PO Input:', noPOInput);
        console.log('Vendor Input:', vendorInput);
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Event listener untuk mode radio buttons
        document.querySelectorAll('.mode-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                toggleModeSections(this.value);
                formData.mode = this.value;
                console.log('Mode changed to:', this.value);
            });
        });

        // Initialize mode dari state awal
        const initialMode = document.querySelector('input[name="mode"]:checked')?.value || 'packing_list';
        toggleModeSections(initialMode);

        if (searchBtnPO) {
            searchBtnPO.addEventListener('click', handleSearchPOClick);
            console.log('Search PO button event listener added');
        }
        if (searchBtnDO) {
            searchBtnDO.addEventListener('click', handleSearchDOClick);
            console.log('Search DO button event listener added');
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

        if (noDOInput) {
            noDOInput.addEventListener('input', () => {
                if (!noDOInput.readOnly && !noDOInput.hasAttribute('readonly')) {
                    showDropdownDO(noDOInput);
                }
            });

            noDOInput.addEventListener('focus', () => {
                if (!noDOInput.readOnly && !noDOInput.hasAttribute('readonly')) {
                    if (noDOInput.value.trim() === '') {
                        showAllDO();
                    } else {
                        showDropdownDO(noDOInput);
                    }
                }
            });

            noDOInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const dropdownDO = document.getElementById('dropdown-no-do');
                    dropdownDO.classList.add('hidden');
                }
            });
        }

        const modalScanInput = document.getElementById('modal-scan-barcode-input');
        if (modalScanInput) {
            modalScanInput.addEventListener('input', function() {
                if (currentModalMode !== 'antar_toko') return;
                const val = (this.value || '').trim();
                if (val.length >= 10 && !interStoreAutoScanLock) {
                    interStoreAutoScanLock = true;
                    scanInterStoreSerial();
                    setTimeout(() => {
                        interStoreAutoScanLock = false;
                    }, 150);
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
        const vendorNameFromServer = '{{ request("vendor_name") }}';
        const tanggalFromServer = '{{ request("tanggal") }}';
        const noDoFromServer = '{{ request("no_do") }}';
        const formSubmittedFromServer = '{{ request("form_submitted") }}';

        if (formSubmittedFromServer === '1' && noPofromServer && npbFromServer && noTerimaFromServer && vendorFromServer && tanggalFromServer) {
            // Restore form data (packing_list_ids dari session/old input jika perlu)
            formData.no_po = noPofromServer;
            formData.npb = npbFromServer;
            formData.no_terima = noTerimaFromServer;
            formData.vendor = vendorFromServer;
            formData.vendor_name = vendorNameFromServer || vendorFromServer;
            formData.tanggal = tanggalFromServer;
            formData.no_do = noDoFromServer;

            // Set form values
            if (document.getElementById('npb')) document.getElementById('npb').value = npbFromServer;
            if (document.getElementById('no_terima')) document.getElementById('no_terima').value = noTerimaFromServer;
            if (vendorInput) vendorInput.value = vendorFromServer;
            if (vendorDisplayInput) vendorDisplayInput.value = vendorNameFromServer || vendorFromServer;
            if (tanggalInput) tanggalInput.value = tanggalFromServer;
            if (noPOInput) noPOInput.value = noPofromServer;
            if (noDOInput) noDOInput.value = noDoFromServer;

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

    function showDropdownDO(input) {
        const dropdownDO = document.getElementById('dropdown-no-do');
        const query = input.value.toLowerCase().trim();

        if (query === '') {
            showAllDO();
            return;
        }

        const resultDO = nomor_do.filter(doItem => {
            const no = (doItem.number || '').toLowerCase();
            const date = (doItem.transDate || '').toLowerCase();
            return no.includes(query) || date.includes(query);
        });

        dropdownDO.innerHTML = '';
        if (resultDO.length === 0) {
            const noResultItem = document.createElement('div');
            noResultItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noResultItem.innerHTML = `<i class="fas fa-search mr-2"></i>Tidak ada DO yang cocok dengan "${query}"`;
            dropdownDO.appendChild(noResultItem);
        } else {
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-sm text-blue-700';
            headerItem.innerHTML = `<i class="fas fa-search mr-2"></i>Hasil Pencarian: ${resultDO.length} DO`;
            dropdownDO.appendChild(headerItem);

            resultDO.forEach(doItem => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const number = document.createElement('div');
                number.className = 'font-semibold text-sm text-gray-800';
                number.textContent = doItem.number || '-';

                const date = document.createElement('div');
                date.className = 'text-sm text-gray-500';
                date.textContent = doItem.transDate || '-';

                item.appendChild(number);
                item.appendChild(date);
                item.onclick = () => {
                    input.value = doItem.number || '';
                    formData.no_do = doItem.number || '';
                    dropdownDO.classList.add('hidden');
                };
                dropdownDO.appendChild(item);
            });
        }

        dropdownDO.classList.remove('hidden');
    }

    function showAllDO() {
        const dropdownDO = document.getElementById('dropdown-no-do');
        dropdownDO.innerHTML = '';

        if (nomor_do.length === 0) {
            const noDataItem = document.createElement('div');
            noDataItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noDataItem.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Tidak ada data DO';
            dropdownDO.appendChild(noDataItem);
        } else {
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-gray-50 border-b font-semibold text-sm text-gray-700';
            headerItem.innerHTML = `<i class="fas fa-list mr-2"></i>Semua Nomor DO (${nomor_do.length})`;
            dropdownDO.appendChild(headerItem);

            const maxShow = 50;
            const doToShow = nomor_do.slice(0, maxShow);
            doToShow.forEach(doItem => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const number = document.createElement('div');
                number.className = 'font-semibold text-sm text-gray-800';
                number.textContent = doItem.number || '-';

                const date = document.createElement('div');
                date.className = 'text-sm text-gray-500';
                date.textContent = doItem.transDate || '-';

                item.appendChild(number);
                item.appendChild(date);
                item.onclick = () => {
                    const noDOInput = document.getElementById('no_do');
                    noDOInput.value = doItem.number || '';
                    formData.no_do = doItem.number || '';
                    dropdownDO.classList.add('hidden');
                };
                dropdownDO.appendChild(item);
            });
        }

        dropdownDO.classList.remove('hidden');
    }

    function handleSearchDOClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const noDOInput = document.getElementById('no_do');
        const query = noDOInput.value.trim();
        if (query === '') {
            showAllDO();
        } else {
            showDropdownDO(noDOInput);
        }
    }
</script>
@endsection