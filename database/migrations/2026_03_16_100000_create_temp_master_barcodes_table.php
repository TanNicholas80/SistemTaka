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
        Schema::create('temp_master_barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('no_po', 100)->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('kode_customer', 50)->index();
            $table->string('barcode');
            $table->string('no_packing_list')->nullable();
            $table->string('no_billing')->nullable();
            $table->string('kode_barang')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('nomor_seri')->nullable();
            $table->string('pcs')->nullable();
            $table->string('berat_kg')->nullable();
            $table->string('panjang_mlc')->nullable();
            $table->string('warna')->nullable();
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
            $table->timestamps();
            
            // Unique constraint on no_po, barcode, and user_id to prevent duplicate TXT uploads for the same PO session.
            $table->unique(['no_po', 'barcode', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_master_barcodes');
    }
};
