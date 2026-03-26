<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

class UserAccurateAPI extends Model
{
    // Nama tabel tidak mengikuti naming Eloquent standar karena ada underscore di antara huruf.
    protected $table = 'user_accurate_a_p_i_s';

    protected $fillable = [
        'user_id',
        'branch_id',
        'customer_id',
        'accurate_api_token',
        'accurate_signature_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function setAccurateApiTokenAttribute($value)
    {
        $this->attributes['accurate_api_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setAccurateSignatureSecretAttribute($value)
    {
        $this->attributes['accurate_signature_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccurateApiTokenAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getAccurateSignatureSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public static function getCredentials(?int $userId, $branchId): ?array
    {
        if (!$userId || !$branchId) {
            return null;
        }

        static $memo = [];
        $key = $userId . ':' . (string) $branchId;
        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }

        $record = self::query()
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->first();

        if (!$record || !$record->accurate_api_token || !$record->accurate_signature_secret) {
            $memo[$key] = null;
            return null;
        }

        $memo[$key] = [
            'customer_id' => $record->customer_id,
            'accurate_api_token' => $record->accurate_api_token,
            'accurate_signature_secret' => $record->accurate_signature_secret,
        ];

        return $memo[$key];
    }

    public static function getCredentialsForAuthUser($branchId = null): ?array
    {
        return self::getCredentials(Auth::id(), $branchId ?? session('active_branch'));
    }
}
