<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawBarcode extends Model
{
    protected $table = 'raw_barcodes';

    protected $fillable = [
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
        'special_treatment',
    ];

    protected $casts = [
        'harga_ppn' => 'float',
        'harga_jual' => 'float',
        'length' => 'float',
        'weight' => 'float',
        'lebar_kain' => 'float',
        'pcs' => 'integer',
    ];

    public function packingList()
    {
        return $this->belongsTo(PackingList::class, 'no_packing_list', 'npl');
    }

    // Alias legacy (opsional, untuk kompatibilitas pemanggil lama)
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
}

