<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_masuk', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('npl');
            $table->string('nbrg')->unique();
            $table->string('kode_customer')->index();
            $table->timestamps();

            $table->foreign('npl')->references('npl')->on('packing_list');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang_masuk');
    }
};
