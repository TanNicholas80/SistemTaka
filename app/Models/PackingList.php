<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PackingList extends Model
{
    use HasFactory, LogsActivity;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_USED = 'used';
    const STATUS_CLOSED = 'closed';

    protected $table = 'packing_list';

    protected $fillable = [
        'tanggal',
        'npl',
        'status',
        'kode_customer',
    ];

    public function barcodes()
    {
        return $this->hasMany(Barcode::class, 'no_packing_list', 'npl');
    }

    /**
     * Penerimaan barang yang menggunakan packing list ini (many-to-many).
     */
    public function penerimaanBarangs()
    {
        return $this->belongsToMany(\App\Models\PenerimaanBarang::class, 'penerimaan_barang_packing_list')
            ->withTimestamps();
    }


    public function scopeForBranch($query, $branchId)
    {
        if ($branchId) {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                return $query->where('kode_customer', $branch->customer_id);
            }
        }
        return $query;
    }

        /**
     * Konfigurasi activity log untuk model PackingList
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tanggal', 'npl', 'status', 'kode_customer']) // Log field yang ada di PackingList
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Manajemen Packing List') // Set log name sesuai permintaan
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
                $activity->description = "Packing List baru dengan NPL '{$this->npl}' telah dibuat pada tanggal '{$this->tanggal}'";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'created',
                    'created_data' => [
                        'npl' => $this->npl,
                        'tanggal' => $this->tanggal,
                        'created_at' => $this->created_at->format('Y-m-d H:i:s'),
                        'kode_customer' => $this->kode_customer,
                        'status' => $this->status
                    ],
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo
                ]);
                break;

            case 'updated':
                // Untuk updated, tampilkan before dan after data
                $changes = $this->getChanges();
                $original = array_intersect_key($this->getOriginal(), $changes);
                
                $activity->description = "Data Packing List dengan NPL '{$this->npl}' telah diupdate";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'updated',
                    'before_update' => $original,
                    'after_update' => $changes,
                    'updated_fields' => array_keys($changes),
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                    'kode_customer' => $this->kode_customer,
                    'status' => $this->status
                ]);
                break;

            case 'deleted':
                // Untuk deleted, tampilkan data yang dihapus
                $activity->description = "Packing List dengan NPL '{$this->npl}' telah dihapus";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'deleted',
                    'deleted_data' => [
                        'npl' => $this->npl,
                        'tanggal' => $this->tanggal,
                        'deleted_at' => now()->format('Y-m-d H:i:s')
                    ],
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                    'kode_customer' => $this->kode_customer,
                    'status' => $this->status
                ]);
                break;
        }
    }
}
