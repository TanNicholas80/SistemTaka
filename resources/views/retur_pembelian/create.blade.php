@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <style>
        .retur-modal-tabs { display:flex; border-bottom:2px solid #e5e7eb; margin-bottom:1rem; }
        .retur-modal-tab { padding:.75rem 1rem; cursor:pointer; font-weight:600; color:#6b7280; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .2s; }
        .retur-modal-tab.active { color:#d32f2f; border-bottom-color:#d32f2f; }
        .retur-modal-tab:hover:not(.active) { color:#374151; border-bottom-color:#d1d5db; }
        #modalItemDetail .modal-content { border-radius:.75rem; overflow:hidden; }
        #modalItemDetail .retur-modal-footer { display:flex; justify-content:space-between; align-items:center; padding:.5rem 1.5rem 1rem; border-top:none; }
        #modalItemDetail .btn-retur-modal-simpan { background:#1a4b8c; color:white; padding:8px 30px; border-radius:6px; font-weight:600; border:1px solid #1a4b8c; }
        #modalItemDetail .btn-retur-modal-simpan:hover { background:#1e3a8a; }
        #modalItemDetail .btn-retur-modal-tutup { background:#fff; color:#dc2626; border:1px solid #dc2626; padding:8px 30px; border-radius:6px; font-weight:600; }
        #modalItemDetail .btn-retur-modal-tutup:hover { background:#fef2f2; color:#b91c1c; }
    </style>

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Form Retur Pembelian</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('retur_pembelian.index') }}">Retur Pembelian</a></li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </div>
            </div>

            <div class="card">
                <form id="returPembelianForm" method="POST" action="{{ route('retur_pembelian.store') }}" class="p-3 space-y-3">
                    <div id="laravel-errors"
                        @if(session('error')) data-error="{{ session('error') }}" @endif
                        @if($errors->any()) data-validation-errors="{{ json_encode($errors->all()) }}" @endif
                    ></div>
                    @csrf
                    <input type="hidden" id="form_submitted" name="form_submitted" value="0">
                    <input type="hidden" id="url_purchase_orders" value="{{ route('retur_pembelian.purchase_orders') }}">
                    <input type="hidden" id="url_purchase_invoices" value="{{ route('retur_pembelian.invoices') }}">
                    <input type="hidden" id="url_serials_from_receive_item" value="{{ route('retur_pembelian.serials_from_receive_item') }}">
                    <input type="hidden" id="url_referensi_detail" value="{{ route('retur_pembelian.referensi_detail') }}">
                    <input type="hidden" id="vendor_no_hidden" name="vendor" value="">
                    <input type="hidden" id="faktur_pembelian_id" name="faktur_pembelian_id" value="">
                    <input type="hidden" id="return_type" name="return_type" value="invoice">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                {{-- Retur Dari --}}
                                <label for="return_referensi_search" class="text-gray-800 font-medium flex items-center">
                                    Retur Dari <span class="text-red-600 ml-1">*</span>
                                    <span class="ml-1" data-toggle="tooltip" data-placement="top" title="Klik pada 'Pesanan Pembelian' untuk mengganti tipe referensi" style="cursor:help"><i class="fas fa-info-circle"></i></span>
                                </label>
                                <div class="flex flex-nowrap items-center gap-2 w-full">
                                    <div class="border border-gray-300 rounded px-2 py-1 text-sm shrink-0 w-[120px] bg-gray-100 text-gray-700 select-none">
                                        Faktur
                                    </div>
                                    <div id="return-referensi-wrapper" class="relative flex-1 min-w-0 max-w-[230px]">
                                        <div class="flex items-center border border-gray-300 rounded overflow-hidden">
                                            <input id="return_referensi_search" type="search" class="flex-grow outline-none px-2 py-1 text-sm" placeholder="Cari/Pilih Faktur..." />
                                            <button type="button" id="return-referensi-search-btn" class="px-2 text-gray-600 hover:text-gray-900"><i class="fas fa-search"></i></button>
                                        </div>
                                        <input type="hidden" id="return_referensi_id" value="" />
                                        <div id="dropdown-return-referensi" class="absolute left-0 right-0 z-10 bg-white border border-gray-300 rounded shadow mt-1 hidden max-h-40 overflow-y-auto text-sm"></div>
                                    </div>
                                </div>

                                {{-- Vendor (Readonly like Pelanggan in Sales Return) --}}
                                <label for="vendor_display" class="text-gray-800 font-medium flex items-center">
                                    Vendor <span class="text-red-600 ml-1">*</span>
                                    <span class="ml-1" data-toggle="tooltip" data-placement="top" title="Vendor akan terisi otomatis setelah memilih referensi dan klik Lanjut" style="cursor:help"><i class="fas fa-info-circle"></i></span>
                                </label>
                                <div class="relative max-w-md w-full">
                                    <div class="flex items-center border border-gray-300 rounded overflow-hidden max-w-[300px]">
                                        <input id="vendor_display" type="text" placeholder="Akan terisi otomatis..." class="flex-grow px-2 py-1 outline-none text-sm bg-gray-100" readonly required />
                                    </div>
                                </div>

                                {{-- Tanggal --}}
                                <label for="tanggal_retur" class="text-gray-800 font-medium flex items-center">
                                    Tanggal <span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="tanggal_retur" name="tanggal_retur" type="date" value="{{ $selectedTanggal }}"
                                    class="border border-gray-300 rounded px-2 py-1 max-w-[300px] w-full {{ $formReadonly ? 'bg-gray-200 text-gray-500' : '' }}"
                                    required {{ $formReadonly ? 'readonly' : '' }} />
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="grid grid-cols-[150px_1fr] gap-y-4 gap-x-4 text-sm items-start">
                                {{-- Nomor Retur --}}
                                <label for="no_retur" class="text-gray-800 font-medium flex items-center">
                                    Nomor Retur <span class="text-red-600 ml-1">*</span>
                                </label>
                                <input id="no_retur" name="no_retur" type="text" value="{{ $no_retur }}"
                                    class="border border-gray-300 rounded px-2 py-1 w-full max-w-[300px] bg-[#f3f4f6]" readonly />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        @if(!$formReadonly)
                        <button type="button" id="btn-lanjut" class="bg-blue-500 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded text-xs">
                            Lanjut
                        </button>
                        @endif
                    </div>
                </form>

                {{-- Table --}}
                <div class="p-2 flex flex-col gap-2">
                    <div class="border border-gray-300 rounded overflow-hidden text-sm">
                        <div class="flex justify-between items-center border border-gray-300 rounded-t bg-[#f9f9f9] px-2 py-2 text-sm">
                            <div class="flex items-center border rounded px-2 py-1 w-[280px]">
                                <input id="search-barang" class="flex-grow outline-none placeholder-gray-400" placeholder="Cari barang..." type="text" />
                                <i class="fas fa-search text-gray-500 ml-2"></i>
                            </div>
                            <div class="text-gray-800 font-semibold text-base whitespace-nowrap">
                                Rincian Barang <span class="text-red-600">*</span>
                            </div>
                        </div>
                        <table class="w-full border-collapse border border-gray-400 text-xs text-center">
                            <thead class="bg-[#607d8b] text-white">
                                <tr>
                                    <th class="border border-gray-400 w-6"><i class="fas fa-sort"></i></th>
                                    <th class="border border-gray-400 px-2 py-1 text-left">Nama Barang</th>
                                    <th class="border border-gray-400 px-2 py-1">Kode #</th>
                                    <th class="border border-gray-400 px-2 py-1">Kuantitas</th>
                                    <th class="border border-gray-400 px-2 py-1">Satuan</th>
                                    <th class="border border-gray-400 px-2 py-1">@Harga</th>
                                    <th class="border border-gray-400 px-2 py-1">Diskon</th>
                                    <th class="border border-gray-400 px-2 py-1">Total Harga</th>
                                </tr>
                            </thead>
                            <tbody id="table-barang-body" class="bg-white">
                                <tr>
                                    <td class="border border-gray-400 px-2 py-3 text-left align-top">≡</td>
                                    <td class="border border-gray-400 px-2 py-3 text-center align-top" colspan="7">
                                        Klik "Lanjut" setelah memilih Vendor dan Referensi untuk memuat barang.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Ringkasan --}}
                    <div class="border border-gray-300 rounded bg-white px-3 py-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="border border-gray-200 rounded px-3 py-2 bg-[#f9fafb]">
                                <div class="text-gray-600 text-xs font-semibold">Diskon</div>
                                <div class="mt-1 flex items-center gap-2">
                                    <input id="diskon_keseluruhan" name="diskon_keseluruhan" type="number" min="0" step="0.01" value="0"
                                        class="border border-gray-200 rounded px-2 py-1 w-full bg-gray-100 text-gray-700 font-semibold text-right outline-none" />
                                </div>
                            </div>
                            <div class="border border-gray-200 rounded px-3 py-2 bg-[#f9fafb]">
                                <div class="text-gray-600 text-xs font-semibold text-right">Total</div>
                                <div class="mt-1 text-right text-gray-900 font-bold" id="total_keseluruhan_display">Rp 0</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end mt-2">
                        <button type="button" id="btn-save-retur-pembelian"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Save Retur Pembelian
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Detail Item --}}
<div class="modal fade" id="modalItemDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width:700px">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-0">
                <div class="flex items-center justify-between px-6 pt-5 pb-3">
                    <h2 class="text-base font-semibold text-gray-800 m-0">Rincian Barang</h2>
                    <button type="button" class="text-gray-400 hover:text-gray-700 bg-transparent border-0 p-0" data-dismiss="modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="retur-modal-tabs px-6">
                    <div class="retur-modal-tab active" data-tab="rincian">Rincian Barang</div>
                    <div class="retur-modal-tab" data-tab="seri">No Seri/Produksi</div>
                </div>

                <div class="px-6 pb-2">
                    <div id="tab-content-rincian" class="retur-tab-content space-y-4">
                        <div class="grid grid-cols-[120px_1fr] gap-4 items-center text-sm">
                            <label class="text-gray-600">Kode #</label>
                            <div id="modal_item_kode" class="font-semibold text-blue-700">—</div>
                            <label class="text-gray-600">Nama Barang</label>
                            <div id="modal_item_nama" class="font-semibold text-gray-800">—</div>
                            <label class="text-gray-600">Kuantitas</label>
                            <input id="modal_item_qty" type="number" min="0" step="0.01" class="border border-gray-300 rounded px-2 py-1 w-full max-w-xs">
                            <label class="text-gray-600">@Harga</label>
                            <input id="modal_item_harga" type="number" min="0" step="0.01" class="border border-gray-300 rounded px-2 py-1 w-full max-w-xs">
                            <label class="text-gray-600">Diskon</label>
                            <input id="modal_item_diskon" type="number" min="0" step="0.01" class="border border-gray-300 rounded px-2 py-1 w-full max-w-xs">
                            <label class="text-gray-600">Gudang</label>
                            <input id="modal_item_gudang" type="text" readonly class="border border-gray-200 rounded px-2 py-1 w-full max-w-md bg-gray-100 text-gray-700">
                            <label class="text-gray-600">Total Harga</label>
                            <input id="modal_item_total" type="text" readonly class="border border-gray-200 rounded px-2 py-1 w-full max-w-md bg-gray-100 text-gray-700 font-semibold">
                        </div>
                    </div>

                    <div id="tab-content-seri" class="retur-tab-content hidden space-y-4">
                        <div class="grid grid-cols-[120px_1fr] gap-2 items-center text-sm">
                            <label class="text-gray-600 font-bold">Nomor #</label>
                            <input type="text" id="modal_serial_filter_input" placeholder="Filter nomor serial..."
                                class="border border-gray-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded">
                            <table class="w-full text-xs">
                                <thead class="text-white sticky top-0" style="background-color:#166534">
                                    <tr>
                                        <th class="p-2 border border-gray-300 text-left">Nomor #</th>
                                        <th class="p-2 border border-gray-300 text-center w-28">Kuantitas</th>
                                    </tr>
                                </thead>
                                <tbody id="modal_serial_tbody"></tbody>
                            </table>
                        </div>
                        <div id="modal_serial_summary" class="text-xs font-semibold text-gray-700">0 No Seri/Produksi, Jumlah 0</div>
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

<script>
(function() {
    let purchaseOrders  = [];
    let purchaseInvoices = [];
    let detailItems = [];
    let editingIndex = -1;

    // ── Elements ──────────────────────────────────────────────────────────
    const returnTypeHidden      = document.getElementById('return_type');
    const returnReferensiSearch = document.getElementById('return_referensi_search');
    const returnReferensiId     = document.getElementById('return_referensi_id');
    const returnReferensiWrapper = document.getElementById('return-referensi-wrapper');
    const dropdownReturnReferensi = document.getElementById('dropdown-return-referensi');
    const vendorDisplay         = document.getElementById('vendor_display');
    const vendorNoHidden        = document.getElementById('vendor_no_hidden');
    const returnReferensiSearchBtn = document.getElementById('return-referensi-search-btn');
    const btnLanjut             = document.getElementById('btn-lanjut');

    // ── Helper ────────────────────────────────────────────────────────────
    function safeNumber(v, fb) { const n = parseFloat(v); return isNaN(n) ? (fb != null ? fb : 0) : n; }
    function formatCurrency(num) {
        const n = parseFloat(num); if (isNaN(n)) return '0';
        return n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }
    function getReferensiDisplayName(row) {
        const num = row.number || row.no || '';
        const vend = row.vendor;
        const vName = (vend && typeof vend === 'object' && vend.name) ? vend.name : (typeof vend === 'string' ? vend : '');
        return vName ? num + ' - ' + vName : num;
    }

    function setFakturReadonly(readonly) {
        if (returnReferensiSearch) {
            returnReferensiSearch.readOnly = readonly;
            if (readonly) returnReferensiSearch.classList.add('bg-gray-200', 'text-gray-500');
            else returnReferensiSearch.classList.remove('bg-gray-200', 'text-gray-500');
        }
        if (returnReferensiSearchBtn) {
            returnReferensiSearchBtn.disabled = readonly;
            if (readonly) {
                returnReferensiSearchBtn.classList.add('opacity-50', 'cursor-not-allowed', 'hidden');
            } else {
                returnReferensiSearchBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'hidden');
            }
        }
        if (dropdownReturnReferensi) dropdownReturnReferensi.classList.add('hidden');
    }

    // ── Fetch Logic ───────────────────────────────────────────────────────
    async function ensureDataFetched() {
        if (purchaseInvoices.length > 0 && purchaseOrders.length > 0) return purchaseInvoices;
        const urlPi = document.getElementById('url_purchase_invoices').value;
        const urlPo = document.getElementById('url_purchase_orders').value;
        try {
            const [rPi, rPo] = await Promise.all([
                fetch(urlPi, { headers: { Accept: 'application/json' } }),
                fetch(urlPo, { headers: { Accept: 'application/json' } })
            ]);
            const dPi = await rPi.json();
            const dPo = await rPo.json();
            purchaseInvoices = dPi.purchaseInvoices || dPi.d || [];
            purchaseOrders = dPo.purchaseOrders || dPo.d || [];
        } catch (e) { console.error('Fetch error:', e); }
        return purchaseInvoices;
    }

    async function showDropdownReferensi(input) {
        if (!dropdownReturnReferensi) return;
        const data = await ensureDataFetched();
        const q = input && input.value ? input.value.toLowerCase() : '';
        const filtered = q ? data.filter(r => getReferensiDisplayName(r).toLowerCase().includes(q)) : data;
        dropdownReturnReferensi.innerHTML = '';
        if (!filtered.length) dropdownReturnReferensi.innerHTML = '<div class="px-3 py-2 text-gray-500">Tidak ada Faktur</div>';
        else {
            const hdr = document.createElement('div'); hdr.className = 'px-3 py-2 bg-blue-50 border-b font-semibold text-xs text-blue-700';
            hdr.innerHTML = '<i class="fas fa-search mr-1"></i>Hasil: ' + filtered.length + ' Faktur';
            dropdownReturnReferensi.appendChild(hdr);
            filtered.slice(0, 50).forEach(r => {
                const el = document.createElement('div');
                el.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b text-sm';
                el.textContent = getReferensiDisplayName(r);
                el.onclick = () => {
                    returnReferensiSearch.value = getReferensiDisplayName(r);
                    returnReferensiId.value = r.number || r.no || '';
                    dropdownReturnReferensi.classList.add('hidden');
                    const v = r.vendor;
                    if (v) {
                        vendorDisplay.value = (typeof v === 'object' ? v.name : v);
                        vendorNoHidden.value = (typeof v === 'object' ? v.vendorNo : v);
                    }
                };
                dropdownReturnReferensi.appendChild(el);
            });
        }
        dropdownReturnReferensi.classList.remove('hidden');
    }

    if (returnReferensiSearchBtn) returnReferensiSearchBtn.onclick = () => showDropdownReferensi(returnReferensiSearch);
    if (returnReferensiSearch) {
        returnReferensiSearch.onfocus = () => showDropdownReferensi(returnReferensiSearch);
        returnReferensiSearch.oninput = () => showDropdownReferensi(returnReferensiSearch);
    }
    document.addEventListener('click', (e) => {
        if (returnReferensiWrapper && !returnReferensiWrapper.contains(e.target)) dropdownReturnReferensi.classList.add('hidden');
    });

    // ── Lanjut ────────────────────────────────────────────────────────────
    if (btnLanjut) {
        btnLanjut.onclick = async () => {
            const refId = returnReferensiId.value;
            if (!refId) return Swal.fire('Peringatan', 'Pilih Faktur terlebih dahulu', 'warning');
            btnLanjut.disabled = true; btnLanjut.textContent = '...';
            try {
                const url = document.getElementById('url_referensi_detail').value;
                const r = await fetch(url + '?return_type=invoice&number=' + refId);
                const res = await r.json();
                if (res.success) {
                    detailItems = res.detailItems || [];
                    renderItemsTable();
                    updateSummary();
                    if (res.vendor) {
                        vendorNoHidden.value = res.vendor.vendorNo || '';
                        vendorDisplay.value = res.vendor.name || res.vendor.vendorNo || '';
                    }
                    document.getElementById('faktur_pembelian_id').value = refId;
                    setFakturReadonly(true);
                } else {
                    Swal.fire('Error', res.message || 'Gagal ambil detail', 'error');
                    setFakturReadonly(false);
                }
            } catch (e) { Swal.fire('Error', 'Sistem error', 'error'); }
            finally { btnLanjut.disabled = false; btnLanjut.textContent = 'Lanjut'; }
        };
    }

    // ── Table & Summary ───────────────────────────────────────────────────
    function renderItemsTable() {
        const tbody = document.getElementById('table-barang-body');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!detailItems.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="border border-gray-400 p-3 text-center text-gray-500">Klik "Lanjut" setelah memilih Faktur</td></tr>';
            return;
        }
        detailItems.forEach((it, idx) => {
            const row = document.createElement('tr'); row.className = 'cursor-pointer hover:bg-gray-50';
            row.ondblclick = () => openItemDetailModal(idx);
            row.innerHTML = `<td class="border border-gray-400 px-2 py-3 text-left">≡</td>
                <td class="border border-gray-400 px-2 py-3 text-left">${it.item?.name || '—'}</td>
                <td class="border border-gray-400 px-2 py-3">${it.item?.no || '—'}</td>
                <td class="border border-gray-400 px-2 py-3">${it.quantity || 0}</td>
                <td class="border border-gray-400 px-2 py-3">${it.itemUnit?.name || '—'}</td>
                <td class="border border-gray-400 px-2 py-3">${formatCurrency(it.unitPrice || 0)}</td>
                <td class="border border-gray-400 px-2 py-3">${formatCurrency(getItemDiskon(it))}</td>
                <td class="border border-gray-400 px-2 py-3">${formatCurrency(computeTotal(it))}</td>`;
            tbody.appendChild(row);
        });
    }

    function getItemDiskon(it) { return it.itemDiscPercent != null ? safeNumber(it.itemDiscPercent) : safeNumber(it.itemCashDiscount); }
    function computeTotal(it) {
        const q = safeNumber(it.quantity), h = safeNumber(it.unitPrice), d = getItemDiskon(it), gross = q * h;
        return d <= 100 ? gross - (gross * d / 100) : Math.max(0, gross - d);
    }

    function updateSummary() {
        const base = detailItems.reduce((s, it) => s + computeTotal(it), 0);
        const overallDisc = safeNumber(document.getElementById('diskon_keseluruhan')?.value, 0);
        let total = base;
        if (overallDisc > 0) total = overallDisc <= 100 ? base - (base * overallDisc / 100) : Math.max(0, base - overallDisc);
        const el = document.getElementById('total_keseluruhan_display');
        if (el) el.textContent = 'Rp ' + formatCurrency(total);
    }
    const oDisc = document.getElementById('diskon_keseluruhan');
    if (oDisc) oDisc.oninput = updateSummary;

    // ── Modal Item ────────────────────────────────────────────────────────
    function openItemDetailModal(idx) {
        editingIndex = idx; const it = detailItems[idx]; if (!it) return;
        document.getElementById('modalItemDetail').setAttribute('data-editing-row', idx);
        document.getElementById('modal_item_kode').textContent = it.item?.no || '—';
        document.getElementById('modal_item_nama').textContent = it.item?.name || '—';
        document.getElementById('modal_item_qty').value = it.quantity || 0;
        document.getElementById('modal_item_harga').value = it.unitPrice || 0;
        document.getElementById('modal_item_diskon').value = getItemDiskon(it);
        document.getElementById('modal_item_gudang').value = it.warehouse?.name || it.warehouseName || '';
        document.getElementById('modal_item_total').value = formatCurrency(computeTotal(it));
        switchTab('rincian'); renderSerialTable();
        const m = document.getElementById('modalItemDetail');
        if (typeof $ !== 'undefined') $(m).modal('show');
    }

    function switchTab(tab) {
        document.querySelectorAll('.retur-modal-tab').forEach(t => t.classList.toggle('active', t.getAttribute('data-tab') === tab));
        document.querySelectorAll('.retur-tab-content').forEach(c => c.classList.toggle('hidden', c.id !== 'tab-content-' + tab));
    }
    document.querySelectorAll('.retur-modal-tab').forEach(t => t.onclick = () => switchTab(t.getAttribute('data-tab')));

    function renderSerialTable() {
        const it = detailItems[editingIndex]; if (!it) return;
        const tbody = document.getElementById('modal_serial_tbody');
        const serials = Array.isArray(it.detailSerialNumber) ? it.detailSerialNumber : [];
        const filterStr = document.getElementById('modal_serial_filter_input')?.value.toLowerCase() || '';
        const filtered = filterStr ? serials.filter(s => (s.serialNumberNo || '').toLowerCase().includes(filterStr)) : serials;
        
        tbody.innerHTML = '';
        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="2" class="p-3 text-center text-gray-500 text-xs">Tidak ada No Seri</td></tr>';
        } else {
            filtered.forEach((s, fIdx) => {
                // Find original index in it.detailSerialNumber
                const origIdx = serials.indexOf(s);
                const tr = document.createElement('tr'); 
                tr.className = 'border-b hover:bg-gray-50';
                tr.innerHTML = `
                    <td class="p-2 border border-gray-200">${s.serialNumberNo || '—'}</td>
                    <td class="p-1 border border-gray-200 text-center">
                        <input type="number" step="any" value="${s.quantity || 0}" 
                            class="modal-sn-qty-input w-full border border-gray-300 rounded px-2 py-0.5 text-center text-xs"
                            data-orig-idx="${origIdx}">
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        // Add event listeners to SN qty inputs
        tbody.querySelectorAll('.modal-sn-qty-input').forEach(input => {
            input.onchange = (e) => {
                const idx = parseInt(e.target.getAttribute('data-orig-idx'));
                const newQty = safeNumber(e.target.value);
                if (it.detailSerialNumber[idx]) {
                    it.detailSerialNumber[idx].quantity = newQty;
                    updateSerialSummary();
                }
            };
        });
        updateSerialSummary();
    }

    function updateSerialSummary() {
        const it = detailItems[editingIndex]; if (!it) return;
        const serials = Array.isArray(it.detailSerialNumber) ? it.detailSerialNumber : [];
        const qSum = serials.reduce((s, x) => s + safeNumber(x.quantity), 0);
        const sm = document.getElementById('modal_serial_summary');
        if (sm) sm.textContent = `${serials.length} No Seri/Produksi, Jumlah ${formatCurrency(qSum)}`;
        
        // Sync item quantity with SN sum if manageSN is true
        if (it.item?.manageSN) {
            document.getElementById('modal_item_qty').value = qSum;
        }
    }
    const sFilt = document.getElementById('modal_serial_filter_input');
    if (sFilt) sFilt.oninput = renderSerialTable;

    document.getElementById('modalItemDetailSave').onclick = () => {
        const it = detailItems[editingIndex]; if (!it) return;
        it.quantity = safeNumber(document.getElementById('modal_item_qty').value);
        it.unitPrice = safeNumber(document.getElementById('modal_item_harga').value);
        const d = safeNumber(document.getElementById('modal_item_diskon').value);
        if (d <= 100) { it.itemDiscPercent = d; it.itemCashDiscount = null; } else { it.itemCashDiscount = d; it.itemDiscPercent = null; }
        renderItemsTable(); updateSummary();
        if (typeof $ !== 'undefined') $('#modalItemDetail').modal('hide');
    };

    // ── Save Form ─────────────────────────────────────────────────────────
    document.getElementById('btn-save-retur-pembelian').onclick = () => {
        const f = document.getElementById('returPembelianForm');
        if (!vendorNoHidden.value) return Swal.fire('Peringatan', 'Vendor wajib ada', 'warning');
        if (!returnReferensiId.value) return Swal.fire('Peringatan', 'Faktur wajib ada', 'warning');
        if (!detailItems.length) return Swal.fire('Peringatan', 'Muat barang dulu', 'warning');
        
        f.querySelectorAll('input[name^="detailItems"]').forEach(i => i.remove());
        detailItems.forEach((it, idx) => {
            const data = {
                kode: it.item?.no || '', kuantitas: it.quantity, harga: it.unitPrice, diskon: getItemDiskon(it)
            };
            Object.entries(data).forEach(([k, v]) => {
                const i = document.createElement('input'); i.type = 'hidden'; 
                i.name = `detailItems[${idx}][${k}]`; i.value = v; f.appendChild(i);
            });
            if (Array.isArray(it.detailSerialNumber)) {
                it.detailSerialNumber.forEach((sn, sidx) => {
                    const sn_no = document.createElement('input'); sn_no.type = 'hidden';
                    sn_no.name = `detailItems[${idx}][detailSerialNumber][${sidx}][serialNumberNo]`;
                    sn_no.value = sn.serialNumberNo || ''; f.appendChild(sn_no);
                    const sn_qty = document.createElement('input'); sn_qty.type = 'hidden';
                    sn_qty.name = `detailItems[${idx}][detailSerialNumber][${sidx}][quantity]`;
                    sn_qty.value = sn.quantity || 0; f.appendChild(sn_qty);
                });
            }
        });
        document.getElementById('form_submitted').value = '1'; f.submit();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof $ !== 'undefined') $('[data-toggle="tooltip"]').tooltip();
        const errs = document.getElementById('laravel-errors');
        if (errs) {
            const sv = errs.getAttribute('data-error'), vl = errs.getAttribute('data-validation-errors');
            if (sv) Swal.fire('Error', sv, 'error');
            if (vl) try { const arr = JSON.parse(vl); if (arr.length) Swal.fire('Validasi', arr.join('<br>'), 'error'); } catch(e){}
        }
    });

})();
</script>
@endsection
