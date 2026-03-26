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
        Schema::create('retur_penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('kode_customer', 50)->index();
            $table->string('no_retur')->unique();
            $table->date('tanggal_retur');
            $table->string('pelanggan_id');
            $table->string('return_type')->default('invoice');
            $table->enum('return_status_type', ['not_returned', 'partially_returned', 'returned'])->default('not_returned');
            $table->string('no_faktur_penjualan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retur_penjualans');
    }
};
