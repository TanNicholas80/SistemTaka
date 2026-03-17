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
        Schema::create('temp_scanned_barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('no_po', 100)->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('kode_customer', 50)->index();
            $table->string('barcode')->index();
            $table->timestamps();
            
            // A barcode should only be scanned once per PO session
            $table->unique(['no_po', 'barcode', 'user_id'], 'temp_scanned_barcodes_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_scanned_barcodes');
    }
};
