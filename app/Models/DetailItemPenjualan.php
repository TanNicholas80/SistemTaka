<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DetailItemPenjualan extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'barcode',
        'npj',
        'qty',
        'harga',
        'diskon',
        'kode_customer',
    ];

    /**
     * Konfigurasi activity log untuk model DetailItemPenjualan
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['barcode', 'npj', 'qty', 'harga', 'diskon', 'kode_customer']) // Log field yang ada di DetailItemPenjualan
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Pembaruan Data Detail Item Penjualan') // Set log name sesuai permintaan
            ->logFillable() // Log semua fillable attributes
            ->logUnguarded(); // Log unguarded attributes juga
    }

    /**
     * Customize what gets logged for created event only
     */
    public function tapActivity($activity, string $eventName)
    {
        // Hanya handle event created (tidak ada updated dan deleted)
        if ($eventName !== 'created') {
            return; // Skip jika bukan event created
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

        // Untuk created, tampilkan data yang diisi
        $activity->description = "Detail Item Penjualan baru dengan barcode '{$this->barcode}' untuk NPJ '{$this->npj}' telah ditambahkan";
        $activity->properties = $activity->properties->merge([
            'event_type' => 'created',
            'created_data' => [
                'barcode' => $this->barcode,
                'npj' => $this->npj,
                'qty' => $this->qty,
                'harga' => $this->harga,
                'diskon' => $this->diskon,
                'kode_customer' => $this->kode_customer,
                'created_at' => $this->created_at->format('Y-m-d H:i:s')
            ],
            'causer_info' => $causerInfo,
            'timestamp_info' => $timestampInfo
        ]);
    }

    public function penjualan()
    {
        return $this->belongsTo(KasirPenjualan::class, 'npj', 'npj');
    }
    
    /**
     * Relasi dengan model Barcode berdasarkan barcode
     */
    public function barcode()
    {
        return $this->belongsTo(Barcode::class, 'barcode', 'barcode');
    }

    /**
     * @deprecated Gunakan barcode(). Dipertahankan untuk backward compatibility.
     */
    public function approvalStock()
    {
        return $this->barcode();
    }
}
