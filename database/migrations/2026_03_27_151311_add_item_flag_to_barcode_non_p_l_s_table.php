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
        Schema::table('barcode_non_p_l_s', function (Blueprint $table) {
            $table->enum('item_flag', [
                'pembelian',
                'retur_pembelian',
                'penjualan',
                'retur_penjualan',
                'stock_opname',
                'penyesuaian_stock'
            ])->nullable()->after('item_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barcode_non_p_l_s', function (Blueprint $table) {
            $table->dropColumn('item_flag');
        });
    }
};
