<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengiriman_pesanans', function (Blueprint $table) {
            $table->boolean('is_partial')->default(false)->after('diskon_keseluruhan');
        });
    }

    public function down(): void
    {
        Schema::table('pengiriman_pesanans', function (Blueprint $table) {
            $table->dropColumn('is_partial');
        });
    }
};
