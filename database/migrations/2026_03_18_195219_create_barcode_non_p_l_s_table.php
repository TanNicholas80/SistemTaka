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
        Schema::create('barcode_non_p_l_s', function (Blueprint $table) {
            $table->id();
            $table->string('kode_customer', 50)->index();
            $table->string('barcode')->unique();
            $table->decimal('quantity', 15, 3);
            $table->string('npb');
            $table->string('item_no')->index();
            $table->string('item_name')->nullable();

            $table->foreign('npb')->references('npb')->on('penerimaan_barangs');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_non_p_l_s');
    }
};
