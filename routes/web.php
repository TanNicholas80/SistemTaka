<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BarangAccurateController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BarangMasukController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FakturController;
use App\Http\Controllers\FakturPenjualanController;
use App\Http\Controllers\HasilStockOpnameController;
use App\Http\Controllers\PackingListController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PrintBarcodeReturController;
use App\Http\Controllers\PengirimanPesananController;
use App\Http\Controllers\PerintahStockOpnameController;
use App\Http\Controllers\PesananPembelianController;
use App\Http\Controllers\ReturPembelianController;
use App\Http\Controllers\ReturPenjualanController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SuratJalanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TempScanController;
use App\Http\Controllers\PrintBarcodeController;

Route::get('/', [AuthController::class, 'login'])->name('login');
Route::post('/login-proses', [AuthController::class, 'login_proses'])->name('login-proses');

Route::middleware(['auth'])->group(function () {
    // Route untuk memilih toko - bisa diakses oleh semua user yang login
    Route::get('/branch/select', [BranchController::class, 'select'])->name('branch.select');
    Route::post('/branch/choose', [BranchController::class, 'choose'])->name('branch.choose');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');

    // Stock Opname - Full Access
    Route::get('/perintah-stock-opname', [PerintahStockOpnameController::class, 'index'])->name('perintah_stock_opname.index');
    Route::get('/perintah-stock-opname/detail/{number}', [PerintahStockOpnameController::class, 'show'])->name('perintah_stock_opname.detail');

    // Hasil Stock Opname - Full Access
    Route::get('/hasil-stock-opname', [HasilStockOpnameController::class, 'index'])->name('hasil_stock_opname.index');
    Route::get('/hasil-stock-opname/detail/{number}', [HasilStockOpnameController::class, 'show'])->name('hasil_stock_opname.detail');
    Route::get('/hasil-stock-opname/detail/{number}/{namaBarang}', [HasilStockOpnameController::class, 'showApproval'])->name('hasil_stock_opname.showApproval');

    // Barang Master - Full Access
    Route::get('/barang-master', [BarangAccurateController::class, 'index'])->name('barang_master.index');

    // Pesanan Pembelian - Full Access
    Route::get('/pesanan-pembelian', [PesananPembelianController::class, 'index'])->name('pesanan_pembelian.index');
    Route::get('/pesanan-pembelian/detail/{number}', [PesananPembelianController::class, 'show'])->name('pesanan_pembelian.detail');

    // Penerimaan Barang - Full Access
    Route::get('/penerimaan-barang', [PenerimaanBarangController::class, 'index'])->name('penerimaan-barang.index');
    Route::get('/penerimaan-barang/create', [PenerimaanBarangController::class, 'create'])->name('penerimaan-barang.create');
    Route::get('/penerimaan-barang/{npb}', [PenerimaanBarangController::class, 'show'])->name('penerimaan-barang.show');

    // Pelanggan - Full Access
    Route::get('/pelanggan', [PelangganController::class, 'index'])->name('pelanggan.index');

    // Cashier - Full Access
    Route::get('/cashier', [SalesController::class, 'index'])->name('cashier.index');
    Route::get('/cashier/detail/{npj}', [SalesController::class, 'show'])->name('cashier.detail');

    // Pengiriman Pesanan - Full Access
    Route::get('/pengiriman-pesanan', [PengirimanPesananController::class, 'index'])->name('pengiriman_pesanan.index');
    Route::get('/pengiriman-pesanan/detail/{no_pengiriman}', [PengirimanPesananController::class, 'show'])->name('pengiriman_pesanan.detail');

    // Faktur Penjualan - Full Access
    Route::get('/faktur-penjualan', [FakturPenjualanController::class, 'index'])->name('faktur_penjualan.index');
    Route::get('/faktur-penjualan/detail/{no_faktur}', [FakturPenjualanController::class, 'show'])->name('faktur_penjualan.detail');

    // Activity Log - For Non-Owner
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity_logs.index');

    // Retur Penjualan - Full Access
    Route::get('/retur-penjualan', [ReturPenjualanController::class, 'index'])->name('retur_penjualan.index');
    Route::get('/retur-penjualan/detail/{no_retur}', [ReturPenjualanController::class, 'show'])->name('retur_penjualan.detail');

    // Retur Pembelian - Full Access
    Route::get('/retur-pembelian', [ReturPembelianController::class, 'index'])->name('retur_pembelian.index');
    Route::get('/retur-pembelian/detail/{no_retur}', [ReturPembelianController::class, 'show'])->name('retur_pembelian.detail');

    // Route yang hanya bisa diakses oleh admin
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/user', [UserController::class, 'index'])->name('user.index');
        Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
        Route::post('/user/store', [UserController::class, 'store'])->name('user.store');
        Route::get('/user/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
        Route::post('/user/update/{id}', [UserController::class, 'update'])->name('user.update');
        Route::delete('/user/delete/{id}', [UserController::class, 'destroy'])->name('user.delete');

        // Route untuk branch management - hanya untuk admin
        Route::post('/branch', [BranchController::class, 'store'])->name('branch.store');
        Route::put('/branch/{branch}', [BranchController::class, 'update'])->name('branch.update');
        Route::delete('/branch/{branch}', [BranchController::class, 'destroy'])->name('branch.destroy');
    });

    Route::middleware(['role:toko,super_admin'])->group(function () {
        Route::get('/barcode', [BarcodeController::class, 'index'])->name('barcode.index');

        // Barang Masuk (Scan Barang) - hanya fitur yang dipakai
        Route::get('/barang-masuk', [BarangMasukController::class, 'index'])->name('barang-masuk.index');
        Route::get('/barang-masuk/create', [BarangMasukController::class, 'create'])->name('barang-masuk.create');
        Route::post('/barang-masuk', [BarangMasukController::class, 'store'])->name('barang-masuk.store');

        // AJAX endpoints untuk scan Packing List
        Route::get('/barang-masuk/packing-list-items', [BarangMasukController::class, 'getPackingListItems'])->name('barang-masuk.packing-list-items');
        Route::post('/barang-masuk/scan', [BarangMasukController::class, 'scanBarcode'])->name('barang-masuk.scan');
        Route::get('/barang-masuk/scanned-items', [BarangMasukController::class, 'getScannedItems'])->name('barang-masuk.scanned-items');
        Route::post('/barang-masuk/flush', [BarangMasukController::class, 'flushScannedItems'])->name('barang-masuk.flush');

        // Stock Opname - For Non-Owner
        Route::get('/hasil-stock-opname/create', [HasilStockOpnameController::class, 'create'])->name('hasil_stock_opname.create');
        Route::post('/hasil-stock-opname/barcode', [HasilStockOpnameController::class, 'lanjut'])->name('hasil_stock_opname.lanjut');
        Route::post('/hasil-stock-opname/barcode/match', [HasilStockOpnameController::class, 'matchApprovalUsingAjax']);
        Route::post('/hasil-stock-opname/barcode/individual', [HasilStockOpnameController::class, 'getIndividualBarcodeData']);
        Route::post('/hasil-stock-opname/barcode/bulk', [HasilStockOpnameController::class, 'getBulkBarcodeData']);
        Route::post('/hasil-stock-opname/store', [HasilStockOpnameController::class, 'store'])->name('hasil_stock_opname.store');

        // Penerimaan Barang - For Non-Owner
        Route::post('/penerimaan-barang', [PenerimaanBarangController::class, 'store'])->name('penerimaan-barang.store');
        Route::post('/purchase-orders/detail', [PenerimaanBarangController::class, 'getDetailPo']);
        Route::post('/penerimaan-barang/generate-barcode-non-pl', [PenerimaanBarangController::class, 'generateBarcodeNonPL'])->name('penerimaan-barang.generate-barcode-non-pl');
        Route::get('/penerimaan-barang/qrcode-non-pl', [PenerimaanBarangController::class, 'qrcodeNonPl'])->name('penerimaan-barang.qrcode-non-pl');
        Route::post('/penerimaan-barang/non-pl/print-pdf', [PenerimaanBarangController::class, 'printNonPlLabelsPdf'])->name('penerimaan-barang.non-pl.print-pdf');

        // Print Barcode
        Route::get('/print-barcode', [PrintBarcodeController::class, 'index'])->name('print_barcode.index');
        Route::get('/print-barcode/search-items', [PrintBarcodeController::class, 'searchItems'])->name('print_barcode.search_items');
        Route::get('/print-barcode/serials', [PrintBarcodeController::class, 'getSerials'])->name('print_barcode.serials');
        Route::get('/print-barcode/reprint/{serialNo}', [PrintBarcodeController::class, 'reprint'])->name('print_barcode.reprint')->where('serialNo', '.+');
        Route::post('/print-barcode/bulk-reprint', [PrintBarcodeController::class, 'bulkReprint'])->name('print_barcode.bulk_reprint');

        // Point Of Sales (POS) - For Non-Owner
        Route::get('/cashier/create', [SalesController::class, 'create'])->name('cashier.create');
        Route::post('/cashier/store', [SalesController::class, 'store'])->name('cashier.store');
        Route::post('/cashier/barcode/information', [SalesController::class, 'getBarcodeAjax']);
        Route::post('/cashier/customer/information', [SalesController::class, 'getCustomerInfo']);

        // Pengiriman Pesanan - For Non-Owner
        Route::get('/pengiriman-pesanan/create', [PengirimanPesananController::class, 'create'])->name('pengiriman_pesanan.create');
        Route::get('/pengiriman-pesanan/customer/{number}', [PengirimanPesananController::class, 'getCustomerByAjax'])->name('pengiriman_pesanan.getCustomer');
        Route::post('/pengiriman-pesanan/store', [PengirimanPesananController::class, 'store'])->name('pengiriman_pesanan.store');

        // Faktur Penjualan - Full CRUD
        Route::get('/faktur-penjualan/create', [FakturPenjualanController::class, 'create'])->name('faktur_penjualan.create');
        Route::get('/faktur-penjualan/customer/{number}', [FakturPenjualanController::class, 'getCustomerByAjax'])->name('faktur_penjualan.getCustomer');
        Route::post('/faktur-penjualan/store', [FakturPenjualanController::class, 'store'])->name('faktur_penjualan.store');

        // Retur Penjualan - For Non-Owner
        Route::get('/retur-penjualan/create', [ReturPenjualanController::class, 'create'])->name('retur_penjualan.create');
        Route::get('/retur-penjualan/delivery-orders', [ReturPenjualanController::class, 'getDeliveryOrdersAjax'])->name('retur_penjualan.delivery_orders');
        Route::get('/retur-penjualan/sales-invoices', [ReturPenjualanController::class, 'getSalesInvoicesAjax'])->name('retur_penjualan.sales_invoices');
        Route::get('/retur-penjualan/referensi-detail', [ReturPenjualanController::class, 'getReferensiDetailAjax'])->name('retur_penjualan.referensi_detail');
        Route::post('/retur-penjualan/store', [ReturPenjualanController::class, 'store'])->name('retur_penjualan.store');

        // Print Barcode Retur
        Route::get('/print-barcode-retur', [PrintBarcodeReturController::class, 'index'])->name('print_barcode_retur.index');
        Route::get('/print-barcode-retur/sales-invoices', [PrintBarcodeReturController::class, 'getSalesInvoicesForPrintAjax'])->name('print_barcode_retur.sales_invoices');
        Route::post('/print-barcode-retur/resolve', [PrintBarcodeReturController::class, 'resolveFromInvoice'])->name('print_barcode_retur.resolve');

        // Retur Pembelian - For Non-Owner
        Route::get('/retur-pembelian/create', [ReturPembelianController::class, 'create'])->name('retur_pembelian.create');
        Route::get('/retur-pembelian/receive-items', [ReturPembelianController::class, 'getReceiveItemsAjax'])->name('retur_pembelian.receive_items');
        Route::get('/retur-pembelian/invoices', [ReturPembelianController::class, 'getInvoicesAjax'])->name('retur_pembelian.invoices');
        Route::get('/retur-pembelian/purchase-orders', [ReturPembelianController::class, 'getPurchaseOrdersAjax'])->name('retur_pembelian.purchase_orders');
        Route::get('/retur-pembelian/serials-from-receive-item', [ReturPembelianController::class, 'getSerialsFromReceiveItemAjax'])->name('retur_pembelian.serials_from_receive_item');
        Route::get('/retur-pembelian/referensi-detail', [ReturPembelianController::class, 'getReferensiDetailAjax'])->name('retur_pembelian.referensi_detail');
        Route::post('/retur-pembelian/store', [ReturPembelianController::class, 'store'])->name('retur_pembelian.store');
    });

    Route::get('/profile', [UserController::class, 'editProfile'])->name('user.profile');
    Route::post('/profile', [UserController::class, 'updateProfile'])->name('user.profile.update');

    Route::resource('packing-list', PackingListController::class);
    Route::get('/packing-list/detail/{id}', [PackingListController::class, 'show'])->name('packing_list_detail');
});
