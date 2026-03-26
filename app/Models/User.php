<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'accurate_api_token',
        'accurate_signature_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

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

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_user', 'user_id', 'branch_id');
    }

        /**
     * Konfigurasi activity log untuk model User
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'role', 'username', 'accurate_api_token', 'accurate_signature_secret']) // Tidak log password untuk keamanan
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Manajemen User') // Set log name sesuai permintaan
            ->logFillable() // Log semua fillable attributes
            ->logUnguarded(); // Log unguarded attributes juga
    }

    /**
     * Customize what gets logged for different events
     */
    public function tapActivity($activity, string $eventName)
    {
        // Dapatkan informasi user yang sedang login (causer)
        $causer = Auth::user();
        $causerInfo = null;
        
        if ($causer) {
            $causerInfo = [
                'causer_id' => $causer->id,
                'causer_type' => get_class($causer),
                'causer_name' => $causer->name,
                'causer_username' => $causer->username,
                'causer_role' => $causer->role
            ];
        }

        // Tambahkan informasi waktu yang detail
        $timestampInfo = [
            'action_date' => now()->format('Y-m-d'),
            'action_time' => now()->format('H:i:s'),
            'action_datetime' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone', 'UTC')
        ];

        switch ($eventName) {
            case 'created':
                // Untuk created, tampilkan data yang diisi
                $activity->description = "User baru '{$this->name}' telah dibuat dengan role '{$this->role}' dan username '{$this->username}'";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'created',
                    'created_data' => [
                        'name' => $this->name,
                        'role' => $this->role,
                        'username' => $this->username,
                        'accurate_api_token' => $this->accurate_api_token,
                        'accurate_signature_secret' => $this->accurate_signature_secret,
                        'created_at' => $this->created_at
                    ],
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo
                ]);
                break;

            case 'updated':
                // Untuk updated, tampilkan before dan after data
                $changes = $this->getChanges();
                $original = array_intersect_key($this->getOriginal(), $changes);
                $original['accurate_api_token'] = $this->accurate_api_token;
                $original['accurate_signature_secret'] = $this->accurate_signature_secret;
                $changes['accurate_api_token'] = $this->accurate_api_token;
                $changes['accurate_signature_secret'] = $this->accurate_signature_secret;
                
                $activity->description = "Data user '{$this->name}' telah diupdate";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'updated',
                    'before_update' => $original,
                    'after_update' => $changes,
                    'updated_fields' => array_keys($changes),
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo
                ]);
                break;

            case 'deleted':
                // Untuk deleted, tampilkan data yang dihapus
                $activity->description = "User '{$this->name}' dengan role '{$this->role}' telah dihapus";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'deleted',
                    'deleted_data' => [
                        'name' => $this->name,
                        'role' => $this->role,
                        'username' => $this->username,
                        'accurate_api_token' => $this->accurate_api_token,
                        'accurate_signature_secret' => $this->accurate_signature_secret,
                        'deleted_at' => now()
                    ],
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo
                ]);
                break;
        }
    }
}
