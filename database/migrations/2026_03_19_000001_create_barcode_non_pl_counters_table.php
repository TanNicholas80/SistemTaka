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
        Schema::create('barcode_non_pl_counters', function (Blueprint $table) {
            $table->id();
            $table->string('kode_customer', 50)->unique();
            $table->string('prefix', 10);
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_non_pl_counters');
    }
};

