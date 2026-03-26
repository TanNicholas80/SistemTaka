<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BarangMasuk extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'barang_masuk';

    protected $fillable = [
        'tanggal',
        'npl',
        'nbrg',
        'kode_customer',
    ];

    public function packingList()
    {
        return $this->belongsTo(PackingList::class, 'npl', 'npl');
    }

    public function barcode()
    {
        return $this->hasOne(Barcode::class, 'barcode', 'nbrg');
    }


    /**
     * Scope filter berdasarkan cabang aktif.
     */
    public function scopeForBranch($query)
    {
        if (session()->has('active_branch')) {
            $branch = \App\Models\Branch::find(session('active_branch'));
            if ($branch) {
                return $query->where('kode_customer', $branch->customer_id);
            }
        }
        return $query;
    }

    /**
     * Konfigurasi activity log untuk model BarangMasuk
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tanggal', 'npl', 'nbrg', 'kode_customer']) // Log field yang ada di BarangMasuk
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Manajemen Barang Masuk') // Set log name sesuai permintaan
            ->logFillable() // Log semua fillable attributes
            ->logUnguarded(); // Log unguarded attributes juga
    }

    /**
     * Customize what gets logged for created, updated, and deleted events
     */
    public function tapActivity($activity, string $eventName)
    {
        // Handle semua events: created, updated, dan deleted
        if (!in_array($eventName, ['created', 'updated', 'deleted'])) {
            return; // Skip jika bukan event yang didukung
        }

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
                $activity->description = "Barang Masuk baru dengan NBRG '{$this->nbrg}' telah dibuat pada tanggal '{$this->tanggal}'";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'created',
                    'created_data' => [
                        'nbrg' => $this->nbrg,
                        'tanggal' => $this->tanggal,
                        'created_at' => $this->created_at->format('Y-m-d H:i:s'),
                        'kode_customer' => $this->kode_customer
                    ],
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo
                ]);
                break;

            case 'updated':
                // Untuk updated, tampilkan before dan after data
                $changes = $this->getChanges();
                $original = array_intersect_key($this->getOriginal(), $changes);

                $activity->description = "Data Barang Masuk dengan NBRG '{$this->nbrg}' telah diupdate";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'updated',
                    'before_update' => $original,
                    'after_update' => $changes,
                    'updated_fields' => array_keys($changes),
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                    'kode_customer' => $this->kode_customer
                ]);
                break;

            case 'deleted':
                // Untuk deleted, tampilkan data yang dihapus
                $activity->description = "Barang Masuk dengan NBRG '{$this->nbrg}' telah dihapus";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'deleted',
                    'deleted_data' => [
                        'nbrg' => $this->nbrg,
                        'tanggal' => $this->tanggal,
                        'deleted_at' => now()->format('Y-m-d H:i:s')
                    ],
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                    'kode_customer' => $this->kode_customer
                ]);
                break;
        }
    }
}
