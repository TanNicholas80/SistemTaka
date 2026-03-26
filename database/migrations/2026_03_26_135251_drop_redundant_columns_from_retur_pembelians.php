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
        Schema::table('retur_pembelians', function (Blueprint $table) {
            $table->dropColumn([
                'penerimaan_barang_id',
                'alamat',
                'keterangan',
                'syarat_bayar',
                'kena_pajak',
                'total_termasuk_pajak',
                'diskon_keseluruhan'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retur_pembelians', function (Blueprint $table) {
            $table->string('penerimaan_barang_id')->nullable();
            $table->string('alamat')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('syarat_bayar')->nullable();
            $table->boolean('kena_pajak')->nullable();
            $table->boolean('total_termasuk_pajak')->nullable();
            $table->string('diskon_keseluruhan')->nullable();
        });
    }
};
