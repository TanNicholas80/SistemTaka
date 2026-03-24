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
    
    const STATUS_APPROVED = 'approved';
    const STATUS_UPLOADED = 'uploaded';

    protected $fillable = [
        // Kolom sesuai migration `create_barcodes_table`
        'barcode',
        'kode_customer',
        'customer',
        'no_packing_list',
        'contract',
        'no_billing',
        'date',
        'jatuh_tempo',
        'plant',
        'pemasok',
        'harga_ppn',
        'harga_jual',
        'material_code',
        'batch_no',
        'length',
        'weight',
        'base_uom',
        'kategori_warna',
        'kode_warna',
        'warna',
        'date_kain',
        'job_order',
        'incoterms',
        'ekspeditor',
        'vehicle_number',
        'production_order',
        'order_type',
        'unit',
        'longtext',
        'salestext',
        'konstruksi_akhir',
        'nojo',
        'zno',
        'lebar_kain',
        'kode',
        'grade',
        'pcs',
        'sample',
        'kodisi_kain',
        'status',
        'id_pb',
        'item_flag',
        'special_treatment',
    ];

    protected $casts = [
        'harga_ppn' => 'float',
        'harga_jual' => 'float',
        'length' => 'float',
        'weight' => 'float',
        'pcs' => 'integer',
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

    // ---- Alias atribut (legacy compatibility) ----
    // Banyak controller lama masih memakai field seperti `kode_barang`, `keterangan`, dll.
    // Model ini memetakan ke kolom baru sesuai migration.

    public function getKodeBarangAttribute()
    {
        return $this->material_code;
    }

    public function setKodeBarangAttribute($value): void
    {
        $this->attributes['material_code'] = $value;
    }

    public function getKeteranganAttribute()
    {
        return $this->longtext;
    }

    public function setKeteranganAttribute($value): void
    {
        $this->attributes['longtext'] = $value;
    }

    public function getNomorSeriAttribute()
    {
        return $this->batch_no;
    }

    public function setNomorSeriAttribute($value): void
    {
        $this->attributes['batch_no'] = $value;
    }

    public function getBeratKgAttribute()
    {
        return $this->weight;
    }

    public function setBeratKgAttribute($value): void
    {
        $this->attributes['weight'] = $value;
    }

    public function getPanjangMlcAttribute()
    {
        return $this->length;
    }

    public function setPanjangMlcAttribute($value): void
    {
        $this->attributes['length'] = $value;
    }

    public function getUomAttribute()
    {
        return $this->base_uom;
    }

    public function setUomAttribute($value): void
    {
        $this->attributes['base_uom'] = $value;
    }

    public function getKontrakAttribute()
    {
        return $this->contract;
    }

    public function setKontrakAttribute($value): void
    {
        $this->attributes['contract'] = $value;
    }

    public function getTanggalAttribute()
    {
        return $this->date;
    }

    public function setTanggalAttribute($value): void
    {
        $this->attributes['date'] = $value;
    }

    public function getJatuhAttribute()
    {
        return $this->jatuh_tempo;
    }

    public function setJatuhAttribute($value): void
    {
        $this->attributes['jatuh_tempo'] = $value;
    }

    public function getNoVehicleAttribute()
    {
        return $this->vehicle_number;
    }

    public function setNoVehicleAttribute($value): void
    {
        $this->attributes['vehicle_number'] = $value;
    }

    public function getPanjangAttribute()
    {
        return $this->length;
    }

    public function getHargaUnitAttribute()
    {
        return $this->harga_jual;
    }

    public function getNoInvoiceAttribute()
    {
        return $this->no_billing;
    }

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
                'barcode',
                'kode_customer',
                'customer',
                'no_packing_list',
                'contract',
                'no_billing',
                'date',
                'jatuh_tempo',
                'plant',
                'pemasok',
                'harga_ppn',
                'harga_jual',
                'material_code',
                'batch_no',
                'length',
                'weight',
                'base_uom',
                'kategori_warna',
                'kode_warna',
                'warna',
                'date_kain',
                'job_order',
                'incoterms',
                'ekspeditor',
                'vehicle_number',
                'production_order',
                'order_type',
                'unit',
                'longtext',
                'salestext',
                'konstruksi_akhir',
                'nojo',
                'zno',
                'lebar_kain',
                'kode',
                'grade',
                'pcs',
                'sample',
                'kodisi_kain',
                'status',
                'id_pb',
                'item_flag',
                'special_treatment',
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
                    'material_code' => $this->material_code,
                    'batch_no' => $this->batch_no,
                    'pcs' => $this->pcs,
                    'weight' => $this->weight,
                    'length' => $this->length,
                    'warna' => $this->warna,
                    'harga_ppn' => $this->harga_ppn,
                    'harga_jual' => $this->harga_jual,
                    'pemasok' => $this->pemasok,
                    'customer' => $this->customer,
                    'contract' => $this->contract,
                    'date' => $this->date,
                    'jatuh_tempo' => $this->jatuh_tempo,
                    'vehicle_number' => $this->vehicle_number,
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
