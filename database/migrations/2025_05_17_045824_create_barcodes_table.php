<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique();
            $table->string('kode_customer', 50)->index();
            $table->string('no_packing_list');
            $table->string('no_billing');
            $table->string('kode_barang');
            $table->string('material_code')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('nomor_seri')->nullable();
            $table->string('pcs')->nullable();
            $table->string('berat_kg')->nullable();
            $table->string('panjang_mlc')->nullable();
            $table->string('length')->nullable();
            $table->string('uom')->nullable();
            $table->string('warna')->nullable();
            $table->string('kode_warna')->nullable();
            $table->string('bale')->nullable();
            $table->string('harga_ppn')->nullable();
            $table->string('harga_jual')->nullable();
            $table->string('pemasok')->nullable();
            $table->string('customer')->nullable();
            $table->string('kontrak')->nullable();
            $table->string('subtotal')->nullable();
            $table->date('tanggal')->nullable();
            $table->date('jatuh')->nullable();
            $table->string('no_vehicle')->nullable();
            $table->enum('status', ['temporary', 'approved', 'uploaded'])->default('temporary');
            $table->string('id_pb')->nullable();
            $table->enum('item_flag', ['pembelian', 'retur_pembelian', 'penjualan', 'retur_penjualan', 'stock_opname', 'penyesuaian_stock'])->default('pembelian');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcodes');
    }
};
