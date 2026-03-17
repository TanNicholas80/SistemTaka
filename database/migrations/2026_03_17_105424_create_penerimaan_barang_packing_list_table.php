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
        if (!Schema::hasTable('penerimaan_barang_packing_list')) {
            Schema::create('penerimaan_barang_packing_list', function (Blueprint $table) {
                $table->id();
                $table->foreignId('penerimaan_barang_id')->constrained('penerimaan_barangs')->onDelete('cascade');
                $table->foreignId('packing_list_id')->constrained('packing_list')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['penerimaan_barang_id', 'packing_list_id'], 'pb_pl_unique');
            });
        } else {
            // Table exists dari run sebelumnya yang gagal di unique - tambahkan constraint jika belum ada
            Schema::table('penerimaan_barang_packing_list', function (Blueprint $table) {
                if (!Schema::hasColumn('penerimaan_barang_packing_list', 'penerimaan_barang_id')) {
                    $table->foreignId('penerimaan_barang_id')->constrained('penerimaan_barangs')->onDelete('cascade');
                }
                if (!Schema::hasColumn('penerimaan_barang_packing_list', 'packing_list_id')) {
                    $table->foreignId('packing_list_id')->constrained('packing_list')->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penerimaan_barang_packing_list');
    }
};
