<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('role', ['super_admin', 'owner', 'kepala_toko', 'akunting', 'marketing'])->default('marketing');
            $table->string('username')->unique();
            $table->string('password');
            $table->text('accurate_api_token')->nullable();
            $table->text('accurate_signature_secret')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
