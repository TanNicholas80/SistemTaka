<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Branch;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Barcode extends Model
{
    use HasFactory, LogsActivity;

    const STATUS_TEMPORARY = 'temporary';
    const STATUS_APPROVED = 'approved';
    const STATUS_UPLOADED = 'uploaded';

    protected $fillable = [
        'barcode',
        'status',
        'no_packing_list',
        'no_billing',
        'kode_barang',
        'keterangan',
        'nomor_seri',
        'pcs',
        'berat_kg',
        'panjang_mlc',
        'length',
        'uom',
        'warna',
        'material_code',
        'kode_warna',
        'bale',
        'harga_ppn',
        'harga_jual',
        'pemasok',
        'customer',
        'kontrak',
        'subtotal',
        'tanggal',
        'jatuh',
        'no_vehicle',
        'kode_customer',
    ];

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $branchId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForBranch($query, $branchId = null)
    {
        if (!$branchId) {
            $branchId = session('active_branch') ?? session('active_branch_id') ?? null;
        }

        $branch = null;

        if ($branchId instanceof Branch) {
            $branch = $branchId;
        } elseif ($branchId) {
            $branch = Branch::find($branchId);

            if (!$branch) {
                $branch = Branch::where('customer_id', $branchId)->first();
            }
        }

        if ($branch && !empty($branch->customer_id)) {
            return $query->where('kode_customer', $branch->customer_id);
        }

        if (Auth::check() && optional(Auth::user())->role === 'super_admin') {
            return $query;
        }

        return $query->whereRaw('1=0');
    }

    /**
     * Alias panjang_mlc untuk kompatibilitas dengan ApprovalStock->panjang.
     */
    public function getPanjangAttribute()
    {
        return $this->panjang_mlc;
    }

    /**
     * Alias harga_jual untuk kompatibilitas dengan ApprovalStock->harga_unit.
     */
    public function getHargaUnitAttribute()
    {
        return $this->harga_jual;
    }

    /**
     * Alias no_billing untuk kompatibilitas dengan ApprovalStock->no_invoice.
     */
    public function getNoInvoiceAttribute()
    {
        return $this->no_billing;
    }

    /**
     * Alias no_packing_list untuk kompatibilitas dengan ApprovalStock->npl.
     */
    public function getNplAttribute()
    {
        return $this->no_packing_list;
    }

        /**
     * Konfigurasi activity log untuk model Barcode
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'barcode', 'no_packing_list', 'no_billing', 'kode_barang', 'keterangan', 
                'nomor_seri', 'pcs', 'berat_kg', 'panjang_mlc', 'warna', 'bale', 
                'harga_ppn', 'harga_jual', 'pemasok', 'customer', 'kontrak', 
                'subtotal', 'tanggal', 'jatuh', 'no_vehicle', 'kode_customer'
            ])
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Pembaruan Data Barcode'); // Set log name sesuai permintaan
    }

    /**
     * Customize what gets logged for different events
     */
    public function tapActivity($activity, string $eventName)
    {
        // Tambahkan informasi waktu yang detail
        $timestampInfo = [
            'action_date' => now()->format('Y-m-d'),
            'action_time' => now()->format('H:i:s'),
            'action_datetime' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone', 'UTC')
        ];

        // Hanya handle event updated (karena hanya ada update dari CSV)
        if ($eventName === 'updated') {
            // Untuk updated, tampilkan data setelah update (data yang ditambahkan dari CSV)
            $changes = $this->getChanges();
            
            $activity->description = "Data Barcode '{$this->barcode}' telah diperbarui dari import CSV";
            $activity->properties = $activity->properties->merge([
                'event_type' => 'updated',
                'data_after_csv_import' => [
                    'barcode' => $this->barcode,
                    'kode_customer' => $this->kode_customer,    
                    'no_packing_list' => $this->no_packing_list,
                    'no_billing' => $this->no_billing,
                    'kode_barang' => $this->kode_barang,
                    'keterangan' => $this->keterangan,
                    'nomor_seri' => $this->nomor_seri,
                    'pcs' => $this->pcs,
                    'berat_kg' => $this->berat_kg,
                    'panjang_mlc' => $this->panjang_mlc,
                    'warna' => $this->warna,
                    'bale' => $this->bale,
                    'harga_ppn' => $this->harga_ppn,
                    'harga_jual' => $this->harga_jual,
                    'pemasok' => $this->pemasok,
                    'customer' => $this->customer,
                    'kontrak' => $this->kontrak,
                    'subtotal' => $this->subtotal,
                    'tanggal' => $this->tanggal,
                    'jatuh' => $this->jatuh,
                    'no_vehicle' => $this->no_vehicle,
                    'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
                ],
                'updated_fields' => array_keys($changes),
                'import_source' => 'CSV File',
                'timestamp_info' => $timestampInfo,
                'kode_customer' => $this->kode_customer
            ]);
        }
    }
}
