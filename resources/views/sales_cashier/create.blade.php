@extends('layout.main')

@section('content')
<style>
    .dropdown-result {
        border: 1px solid #ccc;
        max-height: 150px;
        overflow-y: auto;
        position: absolute;
        background: white;
        width: 100%;
        z-index: 10;
    }

    .dropdown-item {
        padding: 8px;
        cursor: pointer;
    }

    .dropdown-item:hover {
        background-color: #eee;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Cashier</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('cashier.index') }}">Cashier</a></li>
                        <li class="breadcrumb-item active"><a>Tambah Transaksi</a></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <form action="{{ route('cashier.store') }}" id="salesForm" method="POST" enctype="multipart/form-data">
                <div id="laravel-errors"
                    @if(session('error'))
                    data-error="{{ session('error') }}"
                    @endif
                    @if($errors->any())
                    data-validation-errors="{{ json_encode($errors->all()) }}"
                    @endif
                    ></div>
                @csrf
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Form Tambah Penjualan</h3>
                            </div>
                            <div class="card-body flex flex-col md:flex-row md:space-x-4">
                                <div class="left-section w-full md:w-1/2 pr-4">
                                    <div class="form-group">
                                        <label for="customer">Customer <span class="text-red-600 ml-1">*</span>
                                            <span class="ml-1"
                                                data-toggle="tooltip"
                                                data-placement="top"
                                                title="Silakan pilih customer dari dropdown"
                                                style="cursor: help;">
                                                <i class="fas fa-info-circle"></i>
                                            </span>
                                        </label>
                                        <div class="relative">
                                            <div class="flex items-center border border-gray-500 rounded-lg">
                                                <input type="search" id="customer" name="customer" class="form-control block w-full pl-3 pr-10 py-2 text-base leading-6 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg" style="border: none;" placeholder="Pilih Customer" required>
                                                <button type="button" id="customer-search-btn" class="px-3 py-2 text-gray-500 hover:text-gray-900">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <!-- Dropdown will appear here -->
                                            <div id="dropdown-customer" class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded-lg shadow mt-1 hidden max-h-40 overflow-y-auto"></div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="tanggal">Tanggal Penjualan <span class="text-red-600 ml-1">*</span></label>
                                        <input type="date" name="tanggal" id="tanggal" value="{{ date('d-m-Y') }}" class="form-control" required>
                                    </div>
                                </div>
                                <div class="right-section w-full md:w-1/2 flex flex-col justify-between">
                                    <div class="form-group">
                                        <label for="npj">Nomor Penjualan <span class="text-red-600 ml-1">*</span></label>
                                        <input type="text" id="npj" name="npj" class="form-control" value="{{ $npj }}" required readonly>
                                    </div>

                                    <div id="continue-button-container" class="mb-3">
                                        <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg" id="continue-btn" onclick="handleLanjutClick(event)">
                                            Lanjut
                                        </button>
                                    </div>


                                </div>
                            </div>

                            <div class="card px-0 pt-0 pb-2">
                                <div class="table-responsive p-0">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-xs font-weight-bolder">Barcode</th>
                                                <th class="text-uppercase text-xs font-weight-bolder">Nama Barang</th>
                                                <th class="text-uppercase text-xs font-weight-bolder w-28">Kode #</th>
                                                <th class="text-uppercase text-xs font-weight-bolder w-24">Kuantitas</th>
                                                <th class="text-uppercase text-xs font-weight-bolder w-30">Satuan</th>
                                                <th class="text-uppercase text-xs font-weight-bolder w-28">@Harga</th>
                                                <th class="text-uppercase text-xs font-weight-bolder w-24">Diskon</th>
                                                <th class="text-uppercase text-xs font-weight-bolder w-34">Total Harga</th>
                                                <th class="text-uppercase text-xs font-weight-bolder w-20">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="transaksi-body"></tbody>
                                    </table>
                                </div>

                                <!-- Summary Section with Horizontal Layout -->
                                <div class="mt-4 p-3 bg-gray-50 border rounded">
                                    <div class="flex flex-wrap justify-end items-center gap-4 md:gap-6">
                                        <!-- Sub Total -->
                                        <div class="flex flex-col gap-2 border-r-2 border-gray-400 pr-4">
                                            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Sub Total</label>
                                            <div class="bg-white border border-gray-300 rounded px-3 py-2 min-w-[160px] text-right shadow-sm">
                                                <span id="subtotal-display" class="font-semibold text-gray-800">Rp 0</span>
                                            </div>
                                        </div>

                                        <!-- Diskon -->
                                        <div class="flex flex-col gap-2 border-r-2 border-gray-400 pr-4">
                                            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Diskon (%)</label>
                                            <input type="number"
                                                id="diskon-keseluruhan"
                                                name="diskon_keseluruhan"
                                                class="form-control shadow-sm"
                                                style="width: 160px;"
                                                placeholder="0"
                                                min="0"
                                                step="0.01"
                                                onchange="calculateGrandTotal()"
                                                oninput="calculateGrandTotal()">
                                        </div>

                                        <!-- Total -->
                                        <div class="flex flex-col gap-2">
                                            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Total</label>
                                            <div class="bg-white border border-gray-300 rounded px-3 py-2 min-w-[160px] text-right shadow-sm">
                                                <span id="grand-total-display" class="font-bold text-gray-900">Rp 0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group row px-4">
                                <!-- Discount Field -->
                                <div class="col-md-4">
                                    <label for="pay-term">Syarat Pembayaran:
                                        <span class="ml-1"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title="Silakan pilih syarat pembayaran dari dropdown"
                                            style="cursor: help;">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                    <div class="relative">
                                        <div class="flex items-center border border-gray-500 rounded-lg">
                                            <input type="search" id="pay-term" name="pay_term" class="form-control block w-full pl-3 pr-10 py-2 text-base leading-6 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg" style="border: none;" placeholder="Pilih Syarat Pembayaran">
                                            <button type="button" id="pay-term-search-btn" class="px-3 py-2 text-gray-500 hover:text-gray-900">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <!-- Dropdown will appear here -->
                                        <div id="dropdown-pay-term" class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded-lg shadow mt-1 hidden max-h-40 overflow-y-auto"></div>
                                    </div>
                                </div>

                                <!-- Description Field -->
                                <div class="col-md-4">
                                    <label for="keterangan">Keterangan:
                                        <span class="ml-1"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title="Silakan isi keterangan transaksi penjualan jika diperlukan"
                                            style="cursor: help;">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                    <textarea id="keterangan" name="keterangan" class="form-control" rows="3" placeholder="Masukkan Keterangan"></textarea>
                                </div>

                                <!-- Address Field -->
                                <div class="col-md-4">
                                    <label for="alamat">Alamat:
                                        <span class="ml-1"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title="Silakan isi alamat pengiriman untuk pelanggan"
                                            style="cursor: help;">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                    <textarea id="alamat" name="alamat" class="form-control" rows="3" placeholder="Masukkan alamat pelanggan"></textarea>
                                </div>

                                <!-- Order Tax Field -->
                                <div class="col-md-4">
                                    <label>Info Pajak:
                                        <span class="ml-1"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title="Silahkan isi jika PPN berlaku di Accurate untuk barang & customer."
                                            style="cursor: help;">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                    <div class="form-check">
                                        <!-- Hidden input untuk memastikan nilai false dikirim jika checkbox tidak dicek -->
                                        <input type="hidden" name="kena_pajak" value="false">
                                        <input class="form-check-input" type="checkbox" id="kena-pajak" name="kena_pajak" value="true">
                                        <label class="form-check-label" for="kena-pajak">
                                            Kena Pajak
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <!-- Hidden input untuk memastikan nilai false dikirim jika checkbox tidak dicek -->
                                        <input type="hidden" name="total_termasuk_pajak" value="false">
                                        <input class="form-check-input" type="checkbox" id="total-termasuk-pajak" name="total_termasuk_pajak" value="true">
                                        <label class="form-check-label" for="total-termasuk-pajak">
                                            Total Termasuk Pajak
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer text-right">
                                <button type="submit" onclick="submitForm()" class="btn btn-primary">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    const customer = JSON.parse(`{!! addslashes(json_encode($pelanggan)) !!}`);
    const paymentTerms = JSON.parse(`{!! addslashes(json_encode($paymentTerms)) !!}`);
    // Variabel untuk menyimpan barcode yang sudah digunakan
    let usedBarcodes = new Set();

    // Variabel untuk menyimpan data form
    let formData = {
        customer: '',
        npj: '',
        tanggal: '',
        pay_term: '',
        alamat: '',
        keterangan: '',
        kena_pajak: false,
        total_termasuk_pajak: false,
        diskon_keseluruhan: 0,
        detailItems: []
    };

    let detailItems = [];

    // Flag untuk mencegah form submit tidak diinginkan
    let isFormLocked = false;
    let isDetailFormReady = false;

    function handleLanjutClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const npjInput = document.getElementById('npj');
        const customerInput = document.getElementById('customer');
        const tanggalInput = document.getElementById('tanggal');

        console.log('Lanjut button clicked');
        console.log('NPJ:', npjInput ? npjInput.value : 'NPJ input not found');
        console.log('Customer:', customerInput ? customerInput.value : 'Customer input not found');
        console.log('Tanggal:', tanggalInput ? tanggalInput.value : 'Tanggal input not found');

        // Validasi form - skip NPJ karena readonly
        if (!customerInput || !customerInput.value.trim() || !tanggalInput || !tanggalInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Field Tidak Lengkap',
                text: 'Harap lengkapi semua field yang wajib diisi!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        // Simpan data form sementara
        formData.npj = npjInput ? npjInput.value : '';
        formData.customer = customerInput.value;
        formData.tanggal = tanggalInput.value;

        console.log('Form data saved temporarily:', formData);

        // Tampilkan loading state pada tombol
        const continueBtn = document.getElementById('continue-btn');
        if (continueBtn) {
            const originalText = continueBtn.textContent;
            continueBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            continueBtn.disabled = true;
        }

        // Set form menjadi readonly
        setFormReadonly(true);
        isFormLocked = true;

        // NEW: Make the payment terms, address, and tax fields editable
        enablePaymentTermsAndTaxFields();

        // --- PENGHAPUSAN FETCH ---
        // Data payment terms sudah tersedia di variabel `paymentTerms`.
        // Tidak perlu fetch ulang, cukup set flag bahwa form detail siap digunakan.
        isDetailFormReady = true;
        console.log('Payment terms data already available. isDetailFormReady set to true.');
        console.log('Payment terms loaded from variable:', paymentTerms.length, 'items');

        // Tampilkan input barcode dalam table
        addTransactionRow();

        // Simulasi proses loading (opsional, bisa dihapus atau disesuaikan)
        setTimeout(() => {
            console.log('Detail berhasil dimuat (simulasi)');
            // Tombol 'Lanjut' sudah disembunyikan oleh setFormReadonly(true),
            // jadi tidak perlu mereset teks atau state-nya.
        }, 500); // Mengurangi delay karena tidak ada network request

        return false;
    }

    function submitForm() {
        // Kumpulkan data dari baris transaksi terlebih dahulu
        collectTransactionData();

        console.log('Detail items before validation:', formData.detailItems);

        // Validasi data sebelum submit
        if (!validateFormData()) {
            return false;
        }

        const form = document.getElementById('salesForm');

        // Hapus semua input array yang ada untuk menghindari konflik
        const arrayInputs = form.querySelectorAll('input[name$="[]"]');
        arrayInputs.forEach(input => input.remove());

        // Hapus hidden inputs detailItems yang mungkin sudah ada sebelumnya
        const existingDetailInputs = form.querySelectorAll('input[name^="detailItems["]');
        existingDetailInputs.forEach(input => input.remove());

        // Hapus hidden input diskon_keseluruhan yang mungkin sudah ada sebelumnya
        const existingDiskonInput = form.querySelector('input[name="diskon_keseluruhan_hidden"]');
        if (existingDiskonInput) {
            existingDiskonInput.remove();
        }

        // Tambahkan hidden input untuk diskon keseluruhan
        const diskonKeseluruhanHidden = document.createElement('input');
        diskonKeseluruhanHidden.type = 'hidden';
        diskonKeseluruhanHidden.name = 'diskon_keseluruhan_hidden';
        diskonKeseluruhanHidden.value = formData.diskon_keseluruhan || 0;
        form.appendChild(diskonKeseluruhanHidden);

        // Tambahkan hidden inputs untuk detailItems
        if (formData.detailItems && formData.detailItems.length > 0) {
            formData.detailItems.forEach((item, index) => {
                // Barcode - pastikan tidak lebih dari 10 karakter dan tidak kosong
                const barcodeInput = document.createElement('input');
                barcodeInput.type = 'hidden';
                barcodeInput.name = `detailItems[${index}][barcode]`;
                barcodeInput.value = item.barcode || '';
                form.appendChild(barcodeInput);

                // Kode
                const kodeInput = document.createElement('input');
                kodeInput.type = 'hidden';
                kodeInput.name = `detailItems[${index}][kode]`;
                kodeInput.value = item.kode || '';
                form.appendChild(kodeInput);

                // Kuantitas
                const kuantitasInput = document.createElement('input');
                kuantitasInput.type = 'hidden';
                kuantitasInput.name = `detailItems[${index}][kuantitas]`;
                kuantitasInput.value = item.kuantitas || 0;
                form.appendChild(kuantitasInput);

                // Harga
                const hargaInput = document.createElement('input');
                hargaInput.type = 'hidden';
                hargaInput.name = `detailItems[${index}][harga]`;
                hargaInput.value = item.harga || 0;
                form.appendChild(hargaInput);

                // Diskon
                const diskonInput = document.createElement('input');
                diskonInput.type = 'hidden';
                diskonInput.name = `detailItems[${index}][diskon]`;
                diskonInput.value = item.diskon || 0;
                form.appendChild(diskonInput);
            });

            console.log('Hidden inputs added for detailItems:', formData.detailItems.length);
            form.submit();
        } else {
            console.error('No detail items to submit!');
            Swal.fire({
                icon: 'error',
                title: 'Tidak Ada Item Transaksi',
                text: 'Tidak ada item transaksi yang akan dikirim. Harap tambahkan minimal 1 item.',
                timer: 4000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }
    }

    function collectTransactionData() {
        // Reset array detailItems
        formData.detailItems = [];

        // Array untuk melacak barcode yang sudah digunakan
        const usedBarcodes = new Set();

        // Ambil semua baris transaksi
        const transactionRows = document.querySelectorAll('.transaction-row');

        // Loop melalui setiap baris
        transactionRows.forEach(row => {
            // Ambil ID baris dari atribut id
            const rowId = row.id.split('-').pop();

            // Ambil nilai dari input di baris ini
            const barcodeInput = document.getElementById(`barcode-${rowId}`);
            const kodeInput = document.getElementById(`kode-${rowId}`);
            const namaInput = document.getElementById(`nama-${rowId}`);
            const kuantitasInput = document.getElementById(`kuantitas-${rowId}`);
            const satuanInput = document.getElementById(`satuan-${rowId}`);
            const hargaInput = document.getElementById(`harga-${rowId}`);
            const diskonInput = document.getElementById(`diskon-${rowId}`);
            const totalInput = document.getElementById(`total-${rowId}`);

            // Jika baris ini memiliki kode dan kuantitas, tambahkan ke detailItems
            if (kodeInput && kodeInput.value.trim() &&
                kuantitasInput && parseFloat(kuantitasInput.value) > 0) {

                // Ambil dan validasi barcode
                let barcodeValue = barcodeInput ? barcodeInput.value.trim() : '';

                // Pastikan barcode tidak lebih dari 10 karakter
                if (barcodeValue.length > 10) {
                    barcodeValue = barcodeValue.substring(0, 10);
                }

                // Cek apakah barcode sudah digunakan
                if (barcodeValue && usedBarcodes.has(barcodeValue)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Barcode Duplikat',
                        text: `Barcode "${barcodeValue}" sudah digunakan di baris lain. Setiap barcode harus unik.`,
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    return false;
                }

                // Tambahkan barcode ke set jika tidak kosong
                if (barcodeValue) {
                    usedBarcodes.add(barcodeValue);
                }

                // Hapus format currency dari harga jika ada
                const hargaValue = hargaInput.value.replace(/[^\d.-]/g, '');
                const harga = parseFloat(hargaValue) || 0;

                // Tambahkan item ke array detailItems
                formData.detailItems.push({
                    barcode: barcodeValue,
                    nama: namaInput ? namaInput.value : '',
                    kode: kodeInput.value,
                    kuantitas: parseFloat(kuantitasInput.value) || 0,
                    satuan: satuanInput ? satuanInput.value : '',
                    harga: harga,
                    diskon: parseFloat(diskonInput.value) || 0
                });
            }
        });

        console.log('Collected transaction data:', formData.detailItems);
        return true;
    }

    function validateFormData() {
        // Validasi NPJ
        const npjInput = document.querySelector('input[name="npj"]');
        if (!npjInput || !npjInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Field Wajib Kosong',
                text: 'NPJ harus diisi!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        // Validasi tanggal
        const tanggalInput = document.querySelector('input[name="tanggal"]');
        if (!tanggalInput || !tanggalInput.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Field Wajib Kosong',
                text: 'Tanggal harus diisi!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        // Validasi customer
        const customerInput = document.querySelector('input[name="customer"]');
        if (!customerInput || !customerInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Field Wajib Kosong',
                text: 'Customer harus diisi!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        // Validasi detail items
        if (!formData.detailItems || formData.detailItems.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Item Transaksi Kosong',
                text: 'Minimal harus ada 1 item transaksi!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        // Validasi setiap item
        for (let i = 0; i < formData.detailItems.length; i++) {
            const item = formData.detailItems[i];

            // Validasi barcode tidak boleh kosong
            if (!item.barcode || item.barcode.trim() === '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Barcode Kosong',
                    text: `Barcode pada item ke-${i + 1} tidak boleh kosong!`,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return false;
            }

            // Validasi kode tidak boleh kosong
            if (!item.kode || item.kode.trim() === '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Kode Barang Kosong',
                    text: `Kode barang pada item ke-${i + 1} tidak boleh kosong!`,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return false;
            }

            // Validasi kuantitas harus lebih dari 0
            if (!item.kuantitas || item.kuantitas <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Kuantitas Tidak Valid',
                    text: `Kuantitas pada item ke-${i + 1} harus lebih dari 0!`,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return false;
            }

            // Validasi harga harus >= 0
            if (item.harga < 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Harga Tidak Valid',
                    text: `Harga pada item ke-${i + 1} tidak boleh negatif!`,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return false;
            }

            // Validasi diskon harus >= 0
            if (item.diskon < 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Diskon Tidak Valid',
                    text: `Diskon pada item ke-${i + 1} tidak boleh negatif!`,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return false;
            }
        }

        return true;
    }

    // NEW FUNCTION: Enable payment terms, address, and tax fields
    function enablePaymentTermsAndTaxFields() {
        const payTermInput = document.getElementById('pay-term');
        const payTermSearchBtn = document.getElementById('pay-term-search-btn');
        const alamatTextarea = document.getElementById('alamat');
        const keteranganTextarea = document.getElementById('keterangan');
        const kenaPajakCheckbox = document.getElementById('kena-pajak');
        const totalTermasukPajakCheckbox = document.getElementById('total-termasuk-pajak');

        if (payTermInput) {
            payTermInput.removeAttribute('readonly');
            payTermInput.readOnly = false;
            payTermInput.style.backgroundColor = '';
            payTermInput.style.cursor = '';
        }
        if (payTermSearchBtn) {
            payTermSearchBtn.style.display = 'block'; // Or 'inline-block'
            payTermSearchBtn.disabled = false;
        }
        if (alamatTextarea) {
            alamatTextarea.removeAttribute('readonly');
            alamatTextarea.readOnly = false;
            alamatTextarea.style.backgroundColor = '';
            alamatTextarea.style.cursor = '';
        }
        if (keteranganTextarea) {
            keteranganTextarea.removeAttribute('readonly');
            keteranganTextarea.readOnly = false;
            keteranganTextarea.style.backgroundColor = '';
            keteranganTextarea.style.cursor = '';
        }
        if (kenaPajakCheckbox) {
            kenaPajakCheckbox.disabled = false;
            kenaPajakCheckbox.parentElement.style.cursor = '';
        }
        if (totalTermasukPajakCheckbox) {
            totalTermasukPajakCheckbox.disabled = false;
            totalTermasukPajakCheckbox.parentElement.style.cursor = '';
        }
        console.log('Payment terms, address, and tax fields enabled.');
    }

    // NEW FUNCTION: Disable payment terms, address, and tax fields
    function disablePaymentTermsAndTaxFields() {
        const payTermInput = document.getElementById('pay-term');
        const payTermSearchBtn = document.getElementById('pay-term-search-btn');
        const alamatTextarea = document.getElementById('alamat');
        const keteranganTextarea = document.getElementById('keterangan');
        const kenaPajakCheckbox = document.getElementById('kena-pajak');
        const totalTermasukPajakCheckbox = document.getElementById('total-termasuk-pajak');

        if (payTermInput) {
            payTermInput.setAttribute('readonly', 'readonly');
            payTermInput.readOnly = true;
            payTermInput.style.backgroundColor = '#f3f4f6';
            payTermInput.style.cursor = 'not-allowed';
            const dropdownPayTerm = document.getElementById('dropdown-pay-term');
            if (dropdownPayTerm) dropdownPayTerm.classList.add('hidden'); // Hide dropdown if active
        }
        if (payTermSearchBtn) {
            payTermSearchBtn.style.display = 'none';
            payTermSearchBtn.disabled = true;
        }
        if (alamatTextarea) {
            alamatTextarea.setAttribute('readonly', 'readonly');
            alamatTextarea.readOnly = true;
            alamatTextarea.style.backgroundColor = '#f3f4f6';
            alamatTextarea.style.cursor = 'not-allowed';
        }
        if (keteranganTextarea) {
            keteranganTextarea.setAttribute('readonly', 'readonly');
            keteranganTextarea.readOnly = true;
            keteranganTextarea.style.backgroundColor = '#f3f4f6';
            keteranganTextarea.style.cursor = 'not-allowed';
        }
        if (kenaPajakCheckbox) {
            kenaPajakCheckbox.disabled = true;
            kenaPajakCheckbox.parentElement.style.cursor = 'not-allowed';
        }
        if (totalTermasukPajakCheckbox) {
            totalTermasukPajakCheckbox.disabled = true;
            totalTermasukPajakCheckbox.parentElement.style.cursor = 'not-allowed';
        }
        console.log('Payment terms, address, and tax fields disabled.');
    }

    // Function untuk menampilkan dropdown payment terms
    function showDropdownPaymentTerms(input) {
        const dropdownPaymentTerms = document.getElementById('dropdown-pay-term');
        if (!dropdownPaymentTerms) return;

        const query = input.value.toLowerCase().trim();

        // Jika query kosong, panggil showAllPaymentTerms
        if (query === '') {
            showAllPaymentTerms();
            return;
        }

        const resultPaymentTerms = paymentTerms.filter(pt =>
            pt.name.toLowerCase().includes(query) || pt.id.toLowerCase().includes(query)
        );

        dropdownPaymentTerms.innerHTML = '';

        if (resultPaymentTerms.length === 0) {
            const noResultItem = document.createElement('div');
            noResultItem.className = 'px-3 py-2 text-center text-gray-500 border-b'
            noResultItem.innerHTML = `<i class="fas fa-search mr-2"></i>Tidak ada Payment Terms yang cocok dengan "${query}"`;
            dropdownPaymentTerms.appendChild(noResultItem);
        } else {
            // Tambahkan header hasil pencarian
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-sm text-blue-700';
            headerItem.innerHTML = `<i class="fas fa-search mr-2"></i>Hasil Pencarian: ${resultPaymentTerms.length} Payment Terms`;
            dropdownPaymentTerms.appendChild(headerItem);

            resultPaymentTerms.forEach(pt => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const name = document.createElement('div');
                name.className = 'font-semibold text-sm text-gray-800';
                // Highlight matching text
                const highlightedName = pt.name.replace(
                    new RegExp(`(${query})`, 'gi'),
                    '<mark class="bg-yellow-200">$1</mark>'
                );
                name.innerHTML = highlightedName;

                const id = document.createElement('div');
                id.className = 'text-sm text-gray-500';
                id.textContent = pt.id;

                item.appendChild(name);
                item.appendChild(id);

                item.onclick = () => {
                    input.value = pt.name;
                    dropdownPaymentTerms.classList.add('hidden');

                    // Update formData
                    formData.pay_term = pt.name;

                    console.log('Payment Terms selected from search:', pt.name);
                };

                dropdownPaymentTerms.appendChild(item);
            });
        }

        dropdownPaymentTerms.classList.remove('hidden');
        console.log('Payment Terms dropdown shown with', resultPaymentTerms.length, 'results');
    }

    // Function untuk menampilkan semua Payment Terms
    function showAllPaymentTerms() {
        const dropdownPaymentTerms = document.getElementById('dropdown-pay-term');
        if (!dropdownPaymentTerms) return;

        console.log('Menampilkan semua Payment Terms, total:', paymentTerms.length);

        dropdownPaymentTerms.innerHTML = '';

        if (paymentTerms.length === 0) {
            const noDataItem = document.createElement('div');
            noDataItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noDataItem.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Tidak ada data Payment Terms';
            dropdownPaymentTerms.appendChild(noDataItem);
        } else {
            // Tambahkan header info
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-gray-50 border-b font-semibold text-sm text-gray-700';
            headerItem.innerHTML = `<i class="fas fa-list mr-2"></i>Semua Payment Terms (${paymentTerms.length})`;
            dropdownPaymentTerms.appendChild(headerItem);

            // Tampilkan semua Payment Terms (batas maksimal untuk performa)
            const maxShow = 50;
            const paymentTermsToShow = paymentTerms.slice(0, maxShow);

            paymentTermsToShow.forEach(pt => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const name = document.createElement('div');
                name.className = 'font-semibold text-sm text-gray-800';
                name.textContent = pt.name;

                const id = document.createElement('div');
                id.className = 'text-sm text-gray-500';
                id.textContent = pt.id;

                item.appendChild(name);
                item.appendChild(id);

                item.onclick = () => {
                    const paymentTermsInput = document.getElementById('pay-term');
                    if (paymentTermsInput) {
                        paymentTermsInput.value = pt.name;
                        dropdownPaymentTerms.classList.add('hidden');

                        // Update formData
                        formData.pay_term = pt.name;

                        console.log('Payment Terms selected:', pt.name);
                    }
                };

                dropdownPaymentTerms.appendChild(item);
            });

            // Jika ada lebih banyak data, tampilkan info
            if (paymentTerms.length > maxShow) {
                const moreInfoItem = document.createElement('div');
                moreInfoItem.className = 'px-3 py-2 bg-blue-50 border-b text-sm text-blue-600 text-center';
                moreInfoItem.innerHTML = `<i class="fas fa-info-circle mr-2"></i>Menampilkan ${maxShow} dari ${paymentTerms.length} Payment Terms. Ketik untuk pencarian spesifik.`;
                dropdownPaymentTerms.appendChild(moreInfoItem);
            }
        }

        dropdownPaymentTerms.classList.remove('hidden');
        console.log('All Payment Terms dropdown shown');
    }

    // Function untuk handle tombol search Payment Terms
    function handleSearchPaymentTermsClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const paymentTermsInput = document.getElementById('pay-term');

        console.log('Search Payment Terms button clicked');
        console.log('Current input value:', paymentTermsInput ? paymentTermsInput.value : 'Input not found');

        if (!paymentTermsInput) return;

        // Jika input kosong atau hanya whitespace, tampilkan semua Payment Terms
        const query = paymentTermsInput.value.trim();

        if (query === '') {
            console.log('Input kosong, menampilkan semua Payment Terms');
            showAllPaymentTerms();
        } else {
            console.log('Input tidak kosong, melakukan pencarian dengan query:', query);
            showDropdownPaymentTerms(paymentTermsInput);
        }
    }

    // Function untuk validasi payment terms sebelum submit
    function validatePaymentTerms() {
        const paymentTermsInput = document.getElementById('pay-term');

        if (!paymentTermsInput || !paymentTermsInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Payment Terms Belum Dipilih',
                text: 'Silakan pilih Payment Terms terlebih dahulu!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        // Cek apakah payment terms yang dipilih valid
        const selectedId = paymentTermsInput.dataset.selectedId;
        if (!selectedId) {
            Swal.fire({
                icon: 'warning',
                title: 'Payment Terms Tidak Valid',
                text: 'Silakan pilih Payment Terms dari dropdown yang tersedia!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            return false;
        }

        return true;
    }

    function showDropdownCustomer(input) {
        const dropdownCustomer = document.getElementById('dropdown-customer');
        if (!dropdownCustomer) return;

        const query = input.value.toLowerCase().trim();

        // Jika query kosong, panggil showAllCustomer
        if (query === '') {
            showAllCustomer();
            return;
        }

        const resultCustomer = customer.filter(cs =>
            cs.name.toLowerCase().includes(query) ||
            cs.customerNo.toLowerCase().includes(query)
        );

        dropdownCustomer.innerHTML = '';

        if (resultCustomer.length === 0) {
            const noResultItem = document.createElement('div');
            noResultItem.className = 'px-3 py-2 text-center text-gray-500 border-b'
            noResultItem.innerHTML = `<i class="fas fa-search mr-2"></i>Tidak ada Customer yang cocok dengan "${query}"`;
            dropdownCustomer.appendChild(noResultItem);
        } else {
            // Tambahkan header hasil pencarian
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-sm text-blue-700';
            headerItem.innerHTML = `<i class="fas fa-search mr-2"></i>Hasil Pencarian: ${resultCustomer.length} Customer`;
            dropdownCustomer.appendChild(headerItem);

            resultCustomer.forEach(cs => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const name = document.createElement('div');
                name.className = 'font-semibold text-sm text-gray-800';
                // Highlight matching text
                const highlightedName = cs.name.replace(
                    new RegExp(`(${query})`, 'gi'),
                    '<mark class="bg-yellow-200">$1</mark>'
                );
                name.innerHTML = highlightedName;

                const number = document.createElement('div');
                number.className = 'text-sm text-gray-500';
                number.textContent = cs.customerNo;

                item.appendChild(name);
                item.appendChild(number);

                item.onclick = () => {
                    input.value = cs.customerNo;
                    dropdownCustomer.classList.add('hidden');

                    // Update formData jika diperlukan
                    formData.customer = cs.customerNo;

                    console.log('Customer selected from search:', cs.customerNo);

                    // Set flag untuk mencegah AJAX call ganda
                    isCustomerFromDropdown = true;

                    // Fetch customer information via AJAX
                    fetchCustomerInformation(cs.customerNo);

                    // Reset flag setelah delay
                    setTimeout(() => {
                        isCustomerFromDropdown = false;
                    }, 1000);
                };

                dropdownCustomer.appendChild(item);
            });
        }

        dropdownCustomer.classList.remove('hidden');
        console.log('Customer dropdown shown with', resultCustomer.length, 'results');
    }

    // Variable untuk debouncing
    let customerInfoTimeout = null;
    let isCustomerFromDropdown = false;

    // Function untuk mengambil informasi customer via AJAX
    function fetchCustomerInformation(customerNo) {
        if (!customerNo || customerNo.trim() === '') {
            console.log('Customer number is empty, skipping AJAX call');
            return;
        }

        // Clear timeout sebelumnya jika ada
        if (customerInfoTimeout) {
            clearTimeout(customerInfoTimeout);
        }

        // Set timeout untuk debouncing (500ms)
        customerInfoTimeout = setTimeout(() => {
            console.log('Fetching customer information for:', customerNo);

            // Tampilkan loading state
            const customerInput = document.getElementById('customer');
            const payTermInput = document.getElementById('pay-term');
            const alamatTextarea = document.getElementById('alamat');

            if (customerInput) {
                customerInput.classList.add('bg-gray-100');
            }

            // Kirim AJAX request
            fetch('/cashier/customer/information', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        customer_no: customerNo
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            if (response.status === 422) {
                                throw new ValidationError('Validasi gagal', data.errors || {});
                            } else if (response.status === 404) {
                                throw new Error(data.message || 'Data customer tidak ditemukan');
                            } else {
                                throw new Error(data.message || `Server error: ${response.status}`);
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    // Hapus loading state
                    if (customerInput) {
                        customerInput.classList.remove('bg-gray-100');
                    }

                    if (data.success) {
                        const customerData = data.data;

                        // Update payment terms field
                        if (payTermInput && customerData.customer_pay_term) {
                            payTermInput.value = customerData.customer_pay_term;
                            formData.pay_term = customerData.customer_pay_term;
                            console.log('Payment terms updated:', customerData.customer_pay_term);
                        }

                        // Update address field
                        if (alamatTextarea && customerData.customer_address) {
                            alamatTextarea.value = customerData.customer_address;
                            formData.alamat = customerData.customer_address;
                            console.log('Address updated:', customerData.customer_address);
                        }

                        console.log('Customer information fetched successfully:', customerData);
                    } else {
                        console.warn('Customer information fetch failed:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching customer information:', error);

                    // Hapus loading state saat error
                    if (customerInput) {
                        customerInput.classList.remove('bg-gray-100');
                    }
                });
        }, 500); // Close timeout function
    }

    // Initialize form on page load
    document.addEventListener('DOMContentLoaded', () => {
        const npjInput = document.getElementById('npj');
        const customerInput = document.getElementById('customer');
        const tanggalInput = document.getElementById('tanggal');
        const paymentTermsInput = document.getElementById('pay-term');
        const searchBtnCustomer = document.getElementById('customer-search-btn');
        const searchBtnPaymentTerms = document.getElementById('pay-term-search-btn'); // ID diperbaiki
        const form = document.getElementById('salesForm');
        const continueBtn = document.getElementById('continue-btn');
        const kenaPajakCheckbox = document.getElementById('kena-pajak');
        const totalTermasukPajakCheckbox = document.getElementById('total-termasuk-pajak');
        const alamatTextarea = document.getElementById('alamat');
        const keteranganTextarea = document.getElementById('keterangan');
        const diskonKeseluruhanInput = document.getElementById('diskon-keseluruhan');

        console.log('Initializing form...');
        console.log('NPJ Input:', npjInput);
        console.log('Customer Input:', customerInput);
        console.log('Tanggal Input:', tanggalInput);
        console.log('Continue Button:', continueBtn);
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // CLEAR USED BARCODES ON PAGE LOAD/REFRESH
        usedBarcodes.clear();
        console.log('Used barcodes cleared on page load');

        // Clear all oldBarcode data attributes to prevent false duplicates
        clearAllBarcodeReferences();

        console.log('All barcode references cleared on refresh');

        // Event listener untuk menutup dropdown saat klik di luar
        document.addEventListener('click', function(e) {
            // Tutup semua dropdown barcode yang terbuka
            const dropdowns = document.querySelectorAll('[id^="barcode-dropdown-"]');
            dropdowns.forEach(dropdown => {
                const input = dropdown.previousElementSibling;
                if (input && !input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        });

        // Pastikan tombol continue terlihat saat inisialisasi
        if (continueBtn) {
            continueBtn.style.display = 'inline-block';
            continueBtn.style.visibility = 'visible';
            console.log('Continue button initialized and made visible');
        }

        if (searchBtnCustomer) {
            searchBtnCustomer.addEventListener('click', handleSearchCustomerClick);
            console.log('Search Customer button event listener added');
        }

        // Setup event listener untuk search payment terms button
        if (searchBtnPaymentTerms) {
            searchBtnPaymentTerms.addEventListener('click', handleSearchPaymentTermsClick);
            console.log('Search Payment Terms button event listener added');
        }

        // Setup event listener untuk payment terms input
        if (paymentTermsInput) {
            paymentTermsInput.addEventListener('input', () => {
                console.log('Payment Terms input changed:', paymentTermsInput.value);
                // Kondisi ini penting: hanya tampilkan dropdown jika tidak readonly DAN isDetailFormReady
                if (!paymentTermsInput.readOnly && !paymentTermsInput.hasAttribute('readonly') && isDetailFormReady) {
                    showDropdownPaymentTerms(paymentTermsInput);
                } else {
                    console.log('Dropdown not shown: readonly or not ready.');
                }
            });

            // Event listener untuk focus - tampilkan semua jika kosong
            paymentTermsInput.addEventListener('focus', () => {
                console.log('Payment Terms input focused');
                // Kondisi ini penting: hanya tampilkan dropdown jika tidak readonly DAN isDetailFormReady
                if (!paymentTermsInput.readOnly && !paymentTermsInput.hasAttribute('readonly') && isDetailFormReady) {
                    if (paymentTermsInput.value.trim() === '') {
                        showAllPaymentTerms();
                    } else {
                        showDropdownPaymentTerms(paymentTermsInput);
                    }
                } else {
                    console.log('Dropdown not shown on focus: readonly or not ready.');
                }
            });

            // Event listener untuk keydown - ESC untuk menutup dropdown
            paymentTermsInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const dropdownPaymentTerms = document.getElementById('dropdown-pay-term');
                    if (dropdownPaymentTerms) {
                        dropdownPaymentTerms.classList.add('hidden');
                    }
                }
            });

            if (kenaPajakCheckbox) {
                kenaPajakCheckbox.addEventListener('change', function() {
                    formData.kena_pajak = this.checked;
                    console.log('Kena pajak updated:', formData.kena_pajak);

                    // Update hidden input value berdasarkan status checkbox
                    const hiddenInput = document.querySelector('input[type="hidden"][name="kena_pajak"]');
                    if (hiddenInput) {
                        hiddenInput.value = this.checked ? 'true' : 'false';
                    }
                });
            }

            if (totalTermasukPajakCheckbox) {
                totalTermasukPajakCheckbox.addEventListener('change', function() {
                    formData.total_termasuk_pajak = this.checked;
                    console.log('Total termasuk pajak updated:', formData.total_termasuk_pajak);

                    // Update hidden input value berdasarkan status checkbox
                    const hiddenInput = document.querySelector('input[type="hidden"][name="total_termasuk_pajak"]');
                    if (hiddenInput) {
                        hiddenInput.value = this.checked ? 'true' : 'false';
                    }
                });
            }

            if (alamatTextarea) {
                alamatTextarea.addEventListener('change', function() {
                    formData.alamat = this.value;
                    console.log('Alamat updated:', formData.alamat);
                });
            }

            if (keteranganTextarea) {
                keteranganTextarea.addEventListener('change', function() {
                    formData.keterangan = this.value;
                    console.log('Keterangan updated:', formData.keterangan);
                });
            }

            // Setup event listener untuk diskon keseluruhan
            if (diskonKeseluruhanInput) {
                diskonKeseluruhanInput.addEventListener('input', function() {
                    const diskonValue = parseFloat(this.value) || 0;
                    formData.diskon_keseluruhan = diskonValue;
                    console.log('Diskon keseluruhan updated:', formData.diskon_keseluruhan);
                    calculateGrandTotal();
                });

                diskonKeseluruhanInput.addEventListener('change', function() {
                    const diskonValue = parseFloat(this.value) || 0;
                    formData.diskon_keseluruhan = diskonValue;
                    console.log('Diskon keseluruhan changed:', formData.diskon_keseluruhan);
                    calculateGrandTotal();
                });
            }

            // Clear selection ketika user mengetik manual
            paymentTermsInput.addEventListener('keyup', (e) => {
                // Jika user mengetik dan bukan dari dropdown selection
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter' && e.key !== 'Escape') {
                    // Clear selected data jika input berubah manual
                    const currentValue = paymentTermsInput.value;
                    const selectedName = paymentTerms.find(pt => pt.id == paymentTermsInput.dataset.selectedId)?.name;

                    if (currentValue !== selectedName) {
                        paymentTermsInput.dataset.selectedId = '';
                        paymentTermsInput.dataset.selectedCode = '';
                        formData.pay_term = '';
                        console.log('Payment terms selection cleared due to manual input');
                    }
                }
            });
        }

        // Setup event listener untuk customer input
        if (customerInput) {
            customerInput.addEventListener('input', () => {
                console.log('Customer input changed:', customerInput.value);
                if (!customerInput.readOnly && !customerInput.hasAttribute('readonly')) {
                    showDropdownCustomer(customerInput);
                }
            });

            // Event listener untuk change - fetch customer information ketika customer berubah
            customerInput.addEventListener('change', () => {
                console.log('Customer input changed (change event):', customerInput.value);
                if (!customerInput.readOnly && !customerInput.hasAttribute('readonly') && customerInput.value.trim()) {
                    // Hanya fetch jika customer berubah manual (bukan dari dropdown)
                    const currentValue = customerInput.value.trim();
                    if (currentValue !== formData.customer && !isCustomerFromDropdown) {
                        // Fetch customer information via AJAX
                        fetchCustomerInformation(currentValue);
                    }
                }
            });

            // Event listener untuk focus - tampilkan semua jika kosong
            customerInput.addEventListener('focus', () => {
                console.log('Customer input focused');
                if (!customerInput.readOnly && !customerInput.hasAttribute('readonly')) {
                    if (customerInput.value.trim() === '') {
                        showAllCustomer();
                    } else {
                        showDropdownCustomer(customerInput);
                    }
                }
            });

            // Event listener untuk keydown - ESC untuk menutup dropdown
            customerInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const dropdownCustomer = document.getElementById('dropdown-customer');
                    if (dropdownCustomer) {
                        dropdownCustomer.classList.add('hidden');
                    }
                }
            });
        }

        // Set tanggal default
        if (tanggalInput) {
            const today = new Date().toISOString().split('T')[0];
            if (!tanggalInput.value) {
                tanggalInput.value = today;
            }
            formData.tanggal = tanggalInput.value;
            tanggalInput.addEventListener('change', function() {
                formData.tanggal = this.value;
            });
        }

        // Initialize checkbox states dan hidden inputs
        if (kenaPajakCheckbox) {
            const hiddenInput = document.querySelector('input[type="hidden"][name="kena_pajak"]');
            if (hiddenInput) {
                hiddenInput.value = kenaPajakCheckbox.checked ? 'true' : 'false';
            }
            formData.kena_pajak = kenaPajakCheckbox.checked;
        }

        if (totalTermasukPajakCheckbox) {
            const hiddenInput = document.querySelector('input[type="hidden"][name="total_termasuk_pajak"]');
            if (hiddenInput) {
                hiddenInput.value = totalTermasukPajakCheckbox.checked ? 'true' : 'false';
            }
            formData.total_termasuk_pajak = totalTermasukPajakCheckbox.checked;
        }

        // Initialize diskon keseluruhan
        if (diskonKeseluruhanInput) {
            diskonKeseluruhanInput.value = 0;
            formData.diskon_keseluruhan = 0;
        }

        // MODIFIKASI: Mencegah form submission saat menekan Enter di manapun di dalam form,
        // kecuali pada tombol submit yang sebenarnya.
        if (form) {
            form.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const activeElement = document.activeElement;
                    // Hanya mencegah default jika elemen aktif bukan textarea atau button submit
                    // atau jika elemen aktif adalah input barcode
                    if (activeElement.tagName !== 'TEXTAREA' &&
                        activeElement.type !== 'submit' &&
                        activeElement.classList.contains('barcode-input')) { // Tambahkan kondisi ini
                        e.preventDefault();
                        // Panggil handleBarcodeChange secara eksplisit untuk input barcode
                        if (activeElement.classList.contains('barcode-input')) {
                            const rowId = activeElement.id.split('-')[1];
                            handleBarcodeChange(activeElement, rowId);
                        }
                    } else if (activeElement.tagName !== 'TEXTAREA' && activeElement.type !== 'submit' && activeElement.type !== 'button') {
                        // Ini untuk mencegah enter pada input non-barcode lain yang bisa submit form
                        e.preventDefault();
                    }
                }
            });

            // Prevent normal form submission, hanya biarkan melalui button Lanjut
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
        // Hanya restore jika semua parameter ada
        const customerFromServer = document.querySelector('input[name="customer"]')?.value || '';
        const tanggalFromServer = document.querySelector('input[name="tanggal"]')?.value || '';
        const npjFromServer = document.querySelector('input[name="npj"]')?.value || '';
        const diskonKeseluruhanFromServer = document.querySelector('input[name="diskon_keseluruhan"]')?.value || '';

        if (customerFromServer && tanggalFromServer && npjFromServer) {
            // Restore form data
            formData.customer = customerFromServer;
            formData.npj = npjFromServer;
            formData.tanggal = tanggalFromServer;

            // Restore diskon keseluruhan jika ada
            if (diskonKeseluruhanFromServer && diskonKeseluruhanInput) {
                diskonKeseluruhanInput.value = diskonKeseluruhanFromServer;
                formData.diskon_keseluruhan = parseFloat(diskonKeseluruhanFromServer) || 0;
            }

            // set form values
            if (npjInput) npjInput.value = npjFromServer;
            if (customerInput) customerInput.value = customerFromServer;
            if (tanggalInput) tanggalInput.value = tanggalFromServer;

            // Lock form
            setFormReadonly(true);
            enablePaymentTermsAndTaxFields(); // Enable these fields if form is restored
            isDetailFormReady = true; // Assume details are ready if main form is restored

            // Calculate grand total dengan diskon yang di-restore
            setTimeout(() => {
                calculateGrandTotal();
            }, 100);

            console.log('Form restored from server with values:', formData);
        } else {
            // Pastikan form dalam keadaan editable dan tombol continue terlihat
            setFormReadonly(false);
            // NEW: Ensure payment terms, address, and tax fields are disabled initially
            disablePaymentTermsAndTaxFields();
            console.log('Form initialized in editable state');
        }

        // Initialize grand total display dengan nilai default
        calculateGrandTotal();

        console.log('DOM Content Loaded, form initialized');
    });

    document.addEventListener('DOMContentLoaded', function() {
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

    // NEW FUNCTION: Clear all barcode references from DOM elements
    function clearAllBarcodeReferences() {
        console.log('Clearing all barcode references from DOM elements...');

        // Find all existing barcode inputs
        const barcodeInputs = document.querySelectorAll('input[name="barcode[]"]');

        barcodeInputs.forEach((input, index) => {
            // Clear the old barcode data attribute
            input.dataset.oldBarcode = '';

            // Optional: Clear the input value if you want to reset everything
            // input.value = '';

            console.log(`Cleared barcode reference for input ${index + 1}`);
        });

        console.log('All barcode references cleared');
    }

    // NEW FUNCTION: Rebuild used barcodes from existing DOM elements
    function rebuildUsedBarcodesFromDOM() {
        console.log('Rebuilding used barcodes from existing transaction rows...');

        // Clear the set first
        usedBarcodes.clear();

        // Find all existing barcode inputs
        const barcodeInputs = document.querySelectorAll('input[name="barcode[]"]');

        barcodeInputs.forEach(input => {
            const barcodeValue = input.value.trim();
            if (barcodeValue && barcodeValue.length >= 10) {
                // Take only first 10 digits
                const barcode10Digit = barcodeValue.substring(0, 10);
                usedBarcodes.add(barcode10Digit);

                // Set the old barcode data attribute for reference
                input.dataset.oldBarcode = barcode10Digit;

                console.log('Added existing barcode to usedBarcodes:', barcode10Digit);
            }
        });

        console.log('Total used barcodes after rebuild:', usedBarcodes.size);
        console.log('Used barcodes:', Array.from(usedBarcodes));
    }

    // Function untuk mengatur readonly pada form
    function setFormReadonly(readonly = true) {
        const customerInput = document.getElementById('customer');
        const tanggalInput = document.getElementById('tanggal');
        const dropdownCustomer = document.getElementById('dropdown-customer');
        const searchBtnCustomer = document.getElementById('customer-search-btn');
        const continueBtn = document.getElementById('continue-btn');
        const transactionButtonsContainer = document.getElementById('transaction-buttons-container');

        console.log('setFormReadonly called with readonly:', readonly);

        if (readonly) {
            // set readonly
            if (customerInput) {
                customerInput.setAttribute('readonly', 'readonly');
                customerInput.readOnly = true;
                customerInput.style.backgroundColor = '#f3f4f6';
                customerInput.style.cursor = 'not-allowed';
            }

            if (tanggalInput) {
                tanggalInput.setAttribute('readonly', 'readonly');
                tanggalInput.readOnly = true;
                tanggalInput.style.backgroundColor = '#f3f4f6';
                tanggalInput.style.cursor = 'not-allowed';
            }

            // Sembunyikan dropdown dan disable search untuk Customer
            if (dropdownCustomer) dropdownCustomer.classList.add('hidden');
            if (searchBtnCustomer) searchBtnCustomer.style.display = 'none';

            // Sembunyikan button Lanjut setelah diklik
            if (continueBtn) {
                continueBtn.style.display = 'none';
                console.log('Continue button hidden');
            }

            if (transactionButtonsContainer) {
                transactionButtonsContainer.classList.remove('hidden');
                console.log('Transaction buttons shown');
            }
        } else {
            // Remove readonly
            if (customerInput) {
                customerInput.removeAttribute('readonly');
                customerInput.readOnly = false;
                customerInput.style.backgroundColor = '';
                customerInput.style.cursor = '';
            }

            if (tanggalInput) {
                tanggalInput.removeAttribute('readonly');
                tanggalInput.readOnly = false;
                tanggalInput.style.backgroundColor = '';
                tanggalInput.style.cursor = '';
            }

            // Enable search untuk Customer
            if (searchBtnCustomer) searchBtnCustomer.style.display = 'block';

            if (continueBtn) {
                continueBtn.style.display = 'inline-block';
                continueBtn.style.visibility = 'visible';
                continueBtn.disabled = false;
                continueBtn.innerHTML = 'Lanjut'; // Reset text jika ada loading
                console.log('Continue button shown and enabled');
            }

            if (transactionButtonsContainer) {
                transactionButtonsContainer.classList.add('hidden');
                console.log('Transaction buttons hidden');
            }

            console.log('Form set to editable');
        }
    }

    // Klik di luar dropdown - hanya untuk Customer
    document.addEventListener('click', function(e) {
        const customerWrapper = document.getElementById('customer')?.closest('.relative');
        const dropdownCustomer = document.getElementById('dropdown-customer');
        const paymentTermsWrapper = document.getElementById('pay-term')?.closest('.relative');
        const dropdownPaymentTerms = document.getElementById('dropdown-pay-term');

        if (customerWrapper && dropdownCustomer && !customerWrapper.contains(e.target)) {
            dropdownCustomer.classList.add('hidden');
        }

        if (paymentTermsWrapper && dropdownPaymentTerms && !paymentTermsWrapper.contains(e.target)) {
            dropdownPaymentTerms.classList.add('hidden');
        }
    });

    // Function untuk handle tombol search Customer
    function handleSearchCustomerClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const customerInput = document.getElementById('customer');

        console.log('Search Customer button clicked');
        console.log('Current input value:', customerInput ? customerInput.value : 'Input not found');

        if (!customerInput) return;

        // Jika input kosong atau hanya whitespace, tampilkan semua Customer
        const query = customerInput.value.trim();

        if (query === '') {
            console.log('Input kosong, menampilkan semua Customer');
            showAllCustomer();
        } else {
            console.log('Input tidak kosong, melakukan pencarian dengan query:', query);
            showDropdownCustomer(customerInput);
        }
    }

    // Function untuk menampilkan semua Customer
    function showAllCustomer() {
        const dropdownCustomer = document.getElementById('dropdown-customer');
        if (!dropdownCustomer) return;

        console.log('Menampilkan semua Customer, total:', customer.length);

        dropdownCustomer.innerHTML = '';

        if (customer.length === 0) {
            const noDataItem = document.createElement('div');
            noDataItem.className = 'px-3 py-2 text-center text-gray-500 border-b';
            noDataItem.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Tidak ada data Customer';
            dropdownCustomer.appendChild(noDataItem);
        } else {
            // Tambahkan header info
            const headerItem = document.createElement('div');
            headerItem.className = 'px-3 py-2 bg-gray-50 border-b font-semibold text-sm text-gray-700';
            headerItem.innerHTML = `<i class="fas fa-list mr-2"></i>Semua Customer (${customer.length})`;
            dropdownCustomer.appendChild(headerItem);

            // Tampilkan semua Customer (batas maksimal untuk performa)
            const maxShow = 50; // Batasi tampilan untuk performa
            const customerToShow = customer.slice(0, maxShow);

            customerToShow.forEach(cs => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b transition-colors duration-150';

                const name = document.createElement('div');
                name.className = 'font-semibold text-sm text-gray-800';
                name.textContent = cs.name;

                const number = document.createElement('div');
                number.className = 'text-sm text-gray-500';
                number.textContent = cs.customerNo;

                item.appendChild(name);
                item.appendChild(number);

                item.onclick = () => {
                    const customerInput = document.getElementById('customer');
                    if (customerInput) {
                        customerInput.value = cs.customerNo; // Menggunakan name, bukan customerNo
                        dropdownCustomer.classList.add('hidden');

                        // Update formData
                        formData.customer = cs.customerNo;

                        console.log('Customer selected:', cs.customerNo);

                        // Set flag untuk mencegah AJAX call ganda
                        isCustomerFromDropdown = true;

                        // Fetch customer information via AJAX
                        fetchCustomerInformation(cs.customerNo);

                        // Reset flag setelah delay
                        setTimeout(() => {
                            isCustomerFromDropdown = false;
                        }, 1000);
                    }
                };

                dropdownCustomer.appendChild(item);
            });

            // Jika ada lebih banyak data, tampilkan info
            if (customer.length > maxShow) {
                const moreInfoItem = document.createElement('div');
                moreInfoItem.className = 'px-3 py-2 bg-blue-50 border-b text-sm text-blue-600 text-center';
                moreInfoItem.innerHTML = `<i class="fas fa-info-circle mr-2"></i>Menampilkan ${maxShow} dari ${customer.length} Customer. Ketik untuk pencarian spesifik.`;
                dropdownCustomer.appendChild(moreInfoItem);
            }
        }

        dropdownCustomer.classList.remove('hidden');
        console.log('All Customer dropdown shown');
    }

    // Counter untuk ID unik setiap row transaksi
    let transactionRowCounter = 0;

    // Function untuk menambah row transaksi baru
    function addTransactionRow() {
        transactionRowCounter++;
        const tbody = document.getElementById('transaksi-body');

        if (!tbody) {
            console.error('Element transaksi-body tidak ditemukan');
            return;
        }

        const newRow = document.createElement('tr');
        newRow.id = `transaction-row-${transactionRowCounter}`;
        newRow.className = 'transaction-row';

        // MODIFIKASI: Hapus name attribute atau ubah menjadi name yang tidak konflik
        newRow.innerHTML = `
    <td class="text-center w-36">
        <div class="relative">
            <input type="text"
                   id="barcode-${transactionRowCounter}"
                   class="form-control barcode-input"
                   placeholder="Scan (10 digit)">
        </div>
    </td>
    <td class="text-center w-42">
        <input type="text"
               id="nama-${transactionRowCounter}"
               class="form-control"
               readonly
               style="background-color: #f3f4f6; cursor: not-allowed;">
    </td>
    <td class="text-center w-28">
        <input type="text"
               id="kode-${transactionRowCounter}"
               class="form-control"
               readonly
               style="background-color: #f3f4f6; cursor: not-allowed;">
    </td>
    <td class="text-center w-24">
        <input type="number"
               id="kuantitas-${transactionRowCounter}"
               class="form-control kuantitas-input"
               min="0"
               step="0.01"
               onchange="calculateRowTotal(${transactionRowCounter})"
               oninput="calculateRowTotal(${transactionRowCounter})">
    </td>
    <td class="text-center">
        <input type="text"
               id="satuan-${transactionRowCounter}"
               class="form-control"
               readonly
               style="background-color: #f3f4f6; cursor: not-allowed;">
    </td>
    <td class="text-center w-28">
        <input type="text"
               id="harga-${transactionRowCounter}"
               class="form-control harga-input"
               onchange="calculateRowTotal(${transactionRowCounter})"
               oninput="calculateRowTotal(${transactionRowCounter})">
    </td>
    <td class="text-center w-28">
        <input type="number"
               id="diskon-${transactionRowCounter}"
               class="form-control"
               placeholder="0"
               min="0"
               step="0.01"
               onchange="calculateRowTotal(${transactionRowCounter})"
               oninput="calculateRowTotal(${transactionRowCounter})">
    </td>
    <td class="text-center w-32">
            <input type="text"
                   id="total-${transactionRowCounter}"
                   class="form-control"
                   readonly
                   style="background-color: #f3f4f6; cursor: not-allowed; flex: 1;">
    </td>
    <td class="text-center w-20">
        <button type="button"
            class="w-full py-2 bg-red-500 hover:bg-red-600 text-white rounded text-sm"
            onclick="removeTransactionRow(${transactionRowCounter})"
            title="Hapus baris">
            <i class="fas fa-trash"></i>
        </button>
    </td>
`;

        tbody.appendChild(newRow);

        // Event listener untuk barcode input
        const newBarcodeInput = newRow.querySelector('.barcode-input');
        if (newBarcodeInput) {
            newBarcodeInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const rowIdFromId = this.id.split('-')[1];
                    handleBarcodeChange(this, rowIdFromId);
                }
            });
        }

        // Focus ke input barcode yang baru ditambahkan
        setTimeout(() => {
            const barcodeInput = document.getElementById(`barcode-${transactionRowCounter}`);
            if (barcodeInput) {
                barcodeInput.focus();
            }
        }, 100);

        // Update grand total display saat row baru ditambahkan
        calculateGrandTotal();

        console.log(`Row transaksi ${transactionRowCounter} berhasil ditambahkan`);
    }

    // Function untuk menghapus row transaksi
    // Function untuk menghapus row transaksi
    function removeTransactionRow(rowId) {
        const row = document.getElementById(`transaction-row-${rowId}`);
        if (row) {
            // Konfirmasi dengan SweetAlert sebelum menghapus
            Swal.fire({
                title: 'Hapus Transaksi?',
                text: 'Apakah Anda yakin ingin menghapus baris transaksi ini? Tindakan ini tidak dapat dibatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Simpan informasi barcode sebelum dihapus untuk notifikasi
                    const barcodeInput = document.getElementById(`barcode-${rowId}`);
                    let removedBarcode = '';

                    // Hapus barcode dari set barcode yang sudah digunakan
                    if (barcodeInput && barcodeInput.value) {
                        removedBarcode = barcodeInput.value.substring(0, 10);
                        usedBarcodes.delete(removedBarcode);
                        console.log('Removed barcode from usedBarcodes:', removedBarcode);
                    }

                    // Hapus row dari DOM
                    row.remove();
                    console.log(`Row transaksi ${rowId} berhasil dihapus`);

                    // Recalculate total jika diperlukan
                    calculateGrandTotal();

                    // Tampilkan notifikasi sukses
                    Swal.fire({
                        title: 'Berhasil Dihapus!',
                        text: removedBarcode ?
                            `Transaksi dengan barcode ${removedBarcode} telah dihapus.` : 'Baris transaksi telah dihapus.',
                        icon: 'success',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            });
        } else {
            // Tampilkan error jika row tidak ditemukan
            Swal.fire({
                title: 'Error!',
                text: 'Baris transaksi tidak ditemukan.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            console.error(`Row transaksi dengan ID ${rowId} tidak ditemukan`);
        }
    }

    // MODIFIKASI: handleBarcodeInput tidak lagi memanggil fetchBarcodeData secara langsung
    // karena Enter akan ditangkap oleh keydown listener yang baru.
    function handleBarcodeInput(input, rowId) {
        // Logika ini sekarang hanya untuk visual/pemotongan input
        let rawValue = input.value.trim();

        // Ambil hanya 10 digit awal dari barcode
        const barcode10Digit = rawValue.substring(0, 10);

        // Update input value dengan 10 digit
        if (rawValue.length > 10) {
            input.value = barcode10Digit;
        }
        // Dropdown logic (jika ada) bisa dipertahankan di sini jika Anda ingin pencarian real-time
        // tanpa perlu enter. Namun untuk kasus scanner, handleBarcodeChange lebih relevan
    }

    // Function untuk handle perubahan barcode - MODIFIED
    function handleBarcodeChange(input, rowId) {
        let rawBarcode = input.value.trim();

        // Ambil hanya 10 digit awal dari barcode
        const barcode = rawBarcode.substring(0, 10);

        // Update input value dengan 10 digit (penting jika scanner memasukkan lebih dari 10 digit + Enter)
        if (rawBarcode.length > 10) {
            input.value = barcode;
        }

        if (!barcode) {
            // Jika barcode dikosongkan, hapus dari usedBarcodes
            const oldBarcode = input.dataset.oldBarcode;
            if (oldBarcode && usedBarcodes.has(oldBarcode)) {
                usedBarcodes.delete(oldBarcode);
                console.log('Removed old barcode from usedBarcodes:', oldBarcode);
            }
            input.dataset.oldBarcode = '';
            clearRowData(rowId);
            return;
        }

        // Jika belum 10 digit, jangan proses lebih lanjut
        if (barcode.length < 10) {
            Swal.fire({ // Tambahkan SweetAlert untuk barcode tidak lengkap
                icon: 'warning',
                title: 'Barcode Tidak Lengkap',
                text: 'Barcode harus 10 digit!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            clearRowData(rowId); // Kosongkan data jika barcode tidak lengkap
            input.value = ''; // Kosongkan input barcode
            return;
        }

        // Cek apakah barcode sudah digunakan di baris lain
        const currentRowOldBarcode = input.dataset.oldBarcode || '';

        // *** VALIDASI DUPLICATE - MENCEGAH AJAX DAN ROW BARU ***
        if (usedBarcodes.has(barcode) && currentRowOldBarcode !== barcode) {
            // Double check: make sure this barcode is not from the same input field
            let isDuplicateInOtherRow = false;
            const allBarcodeInputs = document.querySelectorAll('input[name="barcode[]"]');

            allBarcodeInputs.forEach(otherInput => {
                // Pastikan bukan input saat ini
                if (otherInput !== input && otherInput.value.substring(0, 10) === barcode) {
                    isDuplicateInOtherRow = true;
                }
            });

            if (isDuplicateInOtherRow) {
                handleBarcodeError(rowId, input, `Barcode "${barcode}" sudah digunakan pada transaksi ini!`, 'Barcode Duplikat');
                // *** RETURN EARLY - TIDAK LANJUT KE AJAX ***
                return;
            }
        }

        // Continue with normal processing...
        // Jika ada barcode lama, hapus dari set usedBarcodes
        if (currentRowOldBarcode && usedBarcodes.has(currentRowOldBarcode)) {
            usedBarcodes.delete(currentRowOldBarcode);
            console.log('Removed old barcode from usedBarcodes:', currentRowOldBarcode);
        }

        // Tambahkan barcode baru ke set usedBarcodes (akan ditambahkan setelah sukses fetch juga)
        // input.dataset.oldBarcode = barcode; // Ini akan diupdate di fetchBarcodeData setelah sukses

        // Try to find the item in barangData first, if not found, fetch from server
        // Note: barangData tidak didefinisikan di kode yang diberikan.
        // Asumsi ini adalah cache lokal, jika tidak ada, fetch dari server.
        // Jika barangData tidak ada, ini akan selalu memanggil fetchBarcodeData
        const barang = window.barangData ? window.barangData.find(item => { // Menggunakan window.barangData
            const stored10Digit = item.barcode.substring(0, 10);
            return stored10Digit === barcode;
        }) : null;

        if (barang) {
            // Isi data barang ke row
            fillRowData(rowId, barang);

            // Sembunyikan dropdown (jika ada)
            const dropdown = document.getElementById(`barcode-dropdown-${rowId}`);
            if (dropdown) {
                dropdown.classList.add('hidden');
            }

            // Tambahkan barcode ke usedBarcodes dan set oldBarcode data attribute
            usedBarcodes.add(barcode);
            input.dataset.oldBarcode = barcode;
            console.log('Added new barcode to usedBarcodes (local cache hit):', barcode);


            // Focus ke kuantitas
            const kuantitasInput = document.getElementById(`kuantitas-${rowId}`);
            if (kuantitasInput) {
                kuantitasInput.focus();
            }

            // *** TAMBAH ROW BARU HANYA JIKA BERHASIL ***
            addNewRowIfNeeded(rowId);
        } else {
            // Barcode tidak ditemukan di local data, fetch dari server
            fetchBarcodeData(barcode, rowId);
        }
    }

    // Function untuk mengisi data row
    function fillRowData(rowId, barang) {
        const namaInput = document.getElementById(`nama-${rowId}`);
        const kodeInput = document.getElementById(`kode-${rowId}`);
        const satuanInput = document.getElementById(`satuan-${rowId}`);
        const kuantitasInput = document.getElementById(`kuantitas-${rowId}`);
        const hargaInput = document.getElementById(`harga-${rowId}`); // Ambil hargaInput juga

        if (namaInput) namaInput.value = barang.nama;

        // Gunakan kode_barang dari accurate_data jika tersedia
        if (kodeInput) {
            if (barang.accurate_data && barang.accurate_data.kode_barang) {
                kodeInput.value = barang.accurate_data.kode_barang;
            } else {
                kodeInput.value = barang.kode || '-';
            }
        }

        // Gunakan satuan_barang dari accurate_data jika tersedia
        if (satuanInput) {
            if (barang.accurate_data && barang.accurate_data.satuan_barang) {
                satuanInput.value = barang.accurate_data.satuan_barang;
            } else {
                satuanInput.value = barang.satuan || 'PCS';
            }
        }

        if (kuantitasInput) kuantitasInput.value = barang.kuantitas || '1'; // Selalu set kuantitas default ke 1

        // Pastikan harga juga diisi dari data barang
        if (hargaInput) {
            // Asumsi barang.harga_barang ada, sesuaikan dengan struktur data barang Anda
            if (barang.accurate_data && barang.accurate_data.harga_barang) {
                hargaInput.value = barang.accurate_data.harga_barang.toString();
            } else {
                hargaInput.value = barang.harga_barang || '0';
            }
        }

        // Hitung total jika kuantitas sudah diisi
        calculateRowTotal(rowId);
    }

    // Function untuk clear data row
    function clearRowData(rowId) {
        const fields = ['nama', 'kode', 'kuantitas', 'satuan', 'harga', 'diskon', 'total'];

        fields.forEach(field => {
            const input = document.getElementById(`${field}-${rowId}`);
            if (input && field !== 'kuantitas' && field !== 'diskon') {
                // Jangan clear kuantitas dan diskon
                input.value = '';
            } else if (input && field === 'kuantitas') {
                input.value = 1; // Reset kuantitas ke 1
            } else if (input && field === 'diskon') {
                input.value = 0; // Reset diskon ke 0
            }
        });

        // Handle barcode input untuk clear old barcode reference
        const barcodeInput = document.getElementById(`barcode-${rowId}`);
        if (barcodeInput) {
            const oldBarcode = barcodeInput.dataset.oldBarcode;
            if (oldBarcode && usedBarcodes.has(oldBarcode)) {
                usedBarcodes.delete(oldBarcode);
                console.log('Removed cleared barcode from usedBarcodes:', oldBarcode);
            }
            barcodeInput.dataset.oldBarcode = '';
        }

        // Recalculate grand total after clearing a row
        calculateGrandTotal();
    }

    // Function untuk menghitung total per row
    function calculateRowTotal(rowId) {
        const kuantitasInput = document.getElementById(`kuantitas-${rowId}`);
        const hargaInput = document.getElementById(`harga-${rowId}`);
        const diskonInput = document.getElementById(`diskon-${rowId}`);
        const totalInput = document.getElementById(`total-${rowId}`);

        if (!kuantitasInput || !hargaInput || !totalInput) return;

        const kuantitas = parseFloat(kuantitasInput.value) || 0;
        // Gunakan parseCurrency untuk parsing harga yang mungkin dalam format currency
        const harga = parseCurrency(hargaInput.value);
        const diskon = parseFloat(diskonInput?.value) || 0;

        // Hitung subtotal sebelum diskon
        const subtotal = kuantitas * harga;

        // Hitung total setelah diskon
        let total = subtotal;
        if (diskon > 0) {
            // Jika diskon dalam bentuk persentase (0-100)
            if (diskon <= 100) {
                total = subtotal - (subtotal * diskon / 100);
            } else {
                // Jika diskon dalam bentuk nominal
                total = subtotal - diskon;
            }
        }

        // Pastikan total tidak negatif
        total = Math.max(0, total);

        // Update field total dengan format currency
        totalInput.value = formatCurrency(total);

        console.log(`Row ${rowId} - Kuantitas: ${kuantitas}, Harga: ${harga}, Diskon: ${diskon}, Subtotal: ${subtotal}, Total: ${total}`);

        // Recalculate grand total
        calculateGrandTotal();
    }

    // Function untuk format currency
    function formatCurrency(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value);
    }

    // Function helper untuk parsing currency Indonesia
    function parseCurrency(currencyString) {
        if (!currencyString || currencyString.trim() === '') return 0;

        // Dari format "Rp 123.456" menjadi "123456"
        let cleanValue = currencyString.toString();
        cleanValue = cleanValue.replace(/Rp\s*/g, ''); // Hapus "Rp" dan spasi
        cleanValue = cleanValue.replace(/\./g, ''); // Hapus titik (pemisah ribuan)
        cleanValue = cleanValue.replace(/,/g, '.'); // Ganti koma dengan titik untuk desimal jika ada
        cleanValue = cleanValue.trim();

        return parseFloat(cleanValue) || 0;
    }

    // Function untuk mengambil data barcode dari server
    function fetchBarcodeData(barcode, rowId) {
        // Pastikan barcode hanya 10 digit
        const barcode10Digit = barcode.substring(0, 10);

        // Tampilkan loading state
        const barcodeInput = document.getElementById(`barcode-${rowId}`);
        if (barcodeInput) {
            barcodeInput.classList.add('bg-gray-100');
        }

        // Cek apakah barcode sudah digunakan dalam form ini sebelum fetch ke server
        const currentRowOldBarcode = barcodeInput.dataset.oldBarcode || '';
        let isDuplicateInOtherRow = false;
        const allBarcodeInputs = document.querySelectorAll('input[name="barcode[]"]');

        allBarcodeInputs.forEach(otherInput => {
            if (otherInput !== barcodeInput && otherInput.value.substring(0, 10) === barcode10Digit) {
                isDuplicateInOtherRow = true;
            }
        });

        if (usedBarcodes.has(barcode10Digit) && currentRowOldBarcode !== barcode10Digit || isDuplicateInOtherRow) {
            handleBarcodeError(rowId, barcodeInput, `Barcode "${barcode10Digit}" sudah digunakan pada transaksi ini!`, 'Barcode Duplikat');
            return; // Hentikan AJAX call
        }

        // Kirim AJAX request dengan 10 digit barcode
        fetch('/cashier/barcode/information', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    barcode: barcode10Digit
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        if (response.status === 422) {
                            // Validasi error (termasuk unique constraint)
                            throw new ValidationError('Validasi gagal', data.errors || {});
                        } else if (response.status === 404) {
                            throw new Error(data.message || 'Barcode tidak ditemukan');
                        } else {
                            throw new Error(data.message || `Server error: ${response.status}`);
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                // Hapus loading state
                if (barcodeInput) {
                    barcodeInput.classList.remove('bg-gray-100');
                }

                if (data.success) {
                    const barangData = data.data;

                    // Jika ada barcode lama yang dihapus dari set, maka tambahkan yang baru
                    if (currentRowOldBarcode && usedBarcodes.has(currentRowOldBarcode)) {
                        usedBarcodes.delete(currentRowOldBarcode);
                    }
                    usedBarcodes.add(barcode10Digit);
                    barcodeInput.dataset.oldBarcode = barcode10Digit; // Perbarui data-attribute

                    const itemData = {
                        nama: barangData.nama || 'Tidak ada nama',
                        kode: barangData.kode_barang || '-',
                        kuantitas: barangData.panjang || 1, // atau barangData.stock, sesuaikan
                        satuan: barangData.satuan || 'PCS',
                        harga: barangData.harga || 0, // Pastikan harga diambil dari data
                        barcode: barcode10Digit,
                        accurate_data: barangData.accurate_data || null
                    };

                    fillRowData(rowId, itemData);
                    calculateRowTotal(rowId);
                    addNewRowIfNeeded(rowId);
                } else {
                    handleBarcodeError(rowId, barcodeInput, data.message || `Barang dengan barcode "${barcode10Digit}" tidak ditemukan!`);
                }
            })
            .catch(error => {
                console.error('Error fetching barcode data:', error);
                if (barcodeInput) {
                    barcodeInput.classList.remove('bg-gray-100'); // Hapus loading state saat error
                }

                if (error instanceof ValidationError) {
                    // Handle validation errors (termasuk unique constraint dari database)
                    let errorMessage = '';

                    // Cek apakah error adalah karena unique constraint pada barcode
                    if (error.errors && error.errors.barcode) {
                        const barcodeErrors = error.errors.barcode;
                        if (Array.isArray(barcodeErrors)) {
                            // Cari error yang mengandung kata "unique" atau "sudah digunakan"
                            const uniqueError = barcodeErrors.find(err =>
                                err.toLowerCase().includes('unique') ||
                                err.toLowerCase().includes('sudah digunakan') ||
                                err.toLowerCase().includes('has already been taken')
                            );

                            if (uniqueError) {
                                errorMessage = `Barcode "${barcode10Digit}" sudah pernah digunakan dalam transaksi sebelumnya!`;
                            } else {
                                errorMessage = barcodeErrors.join('\n');
                            }
                        } else {
                            errorMessage = barcodeErrors;
                        }
                    } else {
                        // Handle error validasi lainnya
                        errorMessage = 'Validasi gagal:\n';
                        for (const field in error.errors) {
                            if (Array.isArray(error.errors[field])) {
                                errorMessage += `- ${error.errors[field].join('\n- ')}\n`;
                            } else {
                                errorMessage += `- ${error.errors[field]}\n`;
                            }
                        }
                    }

                    handleBarcodeError(rowId, barcodeInput, errorMessage, 'Error Validasi');
                } else {
                    // Handle error lainnya (network, server error, dll)
                    handleBarcodeError(rowId, barcodeInput, error.message || 'Terjadi kesalahan saat mengambil data barcode. Silakan coba lagi.');
                }
            });
    }

    // FUNGSI BARU - HANDLE ERROR BARCODE
    function handleBarcodeError(rowId, barcodeInput, message, title = 'Error') {
        // Hapus loading state jika ada
        if (barcodeInput) {
            barcodeInput.classList.remove('bg-gray-100');

            // Tambahkan class error visual
            barcodeInput.classList.add('border-red-500', 'bg-red-50');

            // Reset input barcode
            barcodeInput.value = '';

            // Hapus dari usedBarcodes jika sudah ditambahkan sebelumnya
            const oldBarcode = barcodeInput.dataset.oldBarcode;
            if (oldBarcode && usedBarcodes.has(oldBarcode)) {
                usedBarcodes.delete(oldBarcode);
                console.log('Removed error barcode from usedBarcodes:', oldBarcode);
            }
            barcodeInput.dataset.oldBarcode = ''; // Pastikan data-attribute juga direset

            // Hapus class error setelah beberapa detik
            setTimeout(() => {
                barcodeInput.classList.remove('border-red-500', 'bg-red-50');
            }, 3000);
        }

        // Tampilkan pesan error
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });

        // Focus kembali ke input barcode
        if (barcodeInput) {
            barcodeInput.focus();
            barcodeInput.select();
        }

        // Clear data row jika diperlukan
        clearRowData(rowId);
    }

    // FUNGSI BARU - TAMBAH ROW BARU HANYA JIKA DIPERLUKAN
    function addNewRowIfNeeded(rowId) {
        // Tambahkan row baru jika ini adalah row terakhir DAN berhasil diisi
        const allRows = document.querySelectorAll('.transaction-row');
        const currentRow = document.getElementById(`transaction-row-${rowId}`);

        if (allRows.length > 0 &&
            allRows[allRows.length - 1] === currentRow &&
            isRowDataFilled(rowId)) {
            addTransactionRow();
            console.log('New row added after successful barcode entry');
        }
    }

    // FUNGSI BARU - CEK APAKAH ROW SUDAH TERISI DATA
    function isRowDataFilled(rowId) {
        const namaInput = document.getElementById(`nama-${rowId}`);
        const barcodeInput = document.getElementById(`barcode-${rowId}`);

        return namaInput && namaInput.value.trim() !== '' &&
            barcodeInput && barcodeInput.value.trim() !== '';
    }

    // Custom error class untuk validasi
    class ValidationError extends Error {
        constructor(message, errors) {
            super(message);
            this.name = 'ValidationError';
            this.errors = errors;
        }
    }

    // Function untuk menghitung grand total
    function calculateGrandTotal() {
        let subtotal = 0;
        let grandTotal = 0;
        let diskonAmount = 0;

        // Loop semua row transaksi untuk mendapatkan subtotal
        const transactionRows = document.querySelectorAll('.transaction-row');

        transactionRows.forEach(row => {
            const totalInput = row.querySelector(`input[id^="total-"]`);

            if (totalInput && totalInput.value && totalInput.value.trim() !== '') {
                const total = parseCurrency(totalInput.value);
                subtotal += total;
            }
        });

        // Update tampilan subtotal
        const subtotalDisplay = document.getElementById('subtotal-display');
        if (subtotalDisplay) {
            subtotalDisplay.textContent = formatCurrency(subtotal);
        }

        // Ambil nilai diskon keseluruhan
        const diskonKeseluruhanInput = document.getElementById('diskon-keseluruhan');
        const diskonKeseluruhan = parseFloat(diskonKeseluruhanInput?.value) || 0;

        // Hitung diskon berdasarkan input
        if (diskonKeseluruhan > 0) {
            if (diskonKeseluruhan <= 100) {
                // Jika diskon dalam bentuk persentase (0-100)
                diskonAmount = subtotal * (diskonKeseluruhan / 100);
            } else {
                // Jika diskon dalam bentuk nominal
                diskonAmount = diskonKeseluruhan;
            }
        }

        // Pastikan diskon tidak melebihi subtotal
        diskonAmount = Math.min(diskonAmount, subtotal);

        // Hitung grand total setelah diskon
        grandTotal = subtotal - diskonAmount;

        // Pastikan grand total tidak negatif
        grandTotal = Math.max(0, grandTotal);

        // Note: diskon-display tidak lagi digunakan dalam layout horizontal baru

        // Update tampilan grand total
        const grandTotalDisplay = document.getElementById('grand-total-display');
        if (grandTotalDisplay) {
            grandTotalDisplay.textContent = formatCurrency(grandTotal);
        }

        // Update formData
        formData.diskon_keseluruhan = diskonKeseluruhan;

        console.log('Subtotal:', subtotal);
        console.log('Diskon Keseluruhan Input:', diskonKeseluruhan);
        console.log('Diskon Amount:', diskonAmount);
        console.log('Grand Total:', grandTotal);

        return {
            subtotal: subtotal,
            diskonKeseluruhan: diskonKeseluruhan,
            diskonAmount: diskonAmount,
            grandTotal: grandTotal
        };
    }

    // Fungsi untuk mengubah judul berdasarkan halaman
    function updateTitle(pageTitle) {
        document.title = pageTitle;
    }

    // Panggil fungsi ini saat halaman "Kasir Penjualan" dimuat
    updateTitle('Kasir Penjualan');
</script>
@endsection
