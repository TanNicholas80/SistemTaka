<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('raw_barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique();
            $table->string('kode_customer', 50)->index();
            $table->string('customer');
            $table->string('no_packing_list');
            $table->string('contract');
            $table->string('no_billing')->nullable();
            $table->string('date');
            $table->string('jatuh_tempo')->nullable();
            $table->string('plant');
            $table->string('pemasok');
            $table->decimal('harga_ppn', 15, 2)->nullable();
            $table->decimal('harga_jual', 15, 2)->nullable();
            $table->string('material_code')->nullable();
            $table->string('batch_no')->nullable();
            $table->decimal('length', 15, 3)->nullable();
            $table->decimal('weight', 15, 3)->nullable();
            $table->string('base_uom')->nullable();
            $table->string('kategori_warna')->nullable();
            $table->string('kode_warna')->nullable();
            $table->string('warna')->nullable();
            $table->string('date_kain')->nullable();
            $table->string('job_order')->nullable();
            $table->string('incoterms')->nullable();
            $table->string('ekspeditor')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('production_order')->nullable();
            $table->string('order_type')->nullable();
            $table->string('unit')->nullable();
            $table->string('longtext')->nullable();
            $table->string('salestext')->nullable();
            $table->string('konstruksi_akhir');
            $table->string('nojo')->nullable();
            $table->string('zno')->nullable();
            $table->unsignedSmallInteger('lebar_kain')->nullable();
            $table->string('kode')->nullable();
            $table->string('grade')->nullable();
            $table->string('pcs')->nullable();
            $table->string('sample')->nullable();
            $table->string('kodisi_kain')->nullable();
            $table->string('special_treatment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_barcodes');
    }
};
