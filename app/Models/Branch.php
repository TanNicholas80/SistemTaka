<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'customer_id',
        'photo',
        'url_accurate',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user');
    }

    /**
     * Base URL untuk API Accurate (berdasarkan column url_accurate).
     * Digunakan oleh model-model yang memanggil API Accurate agar URL konsisten per cabang.
     */
    public function getAccurateApiBaseUrl(): string
    {
        return rtrim($this->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
    }
}
