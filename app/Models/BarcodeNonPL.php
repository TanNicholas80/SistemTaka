<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BarcodeNonPL extends Model
{
    protected $table = 'barcode_non_p_l_s';

    protected $fillable = [
        'kode_customer',
        'barcode',
        'quantity',
        'npb',
        'item_no',
        'item_name',
        'item_flag',
    ];

    private static $prefixMap = [
        '0100008174' => '11',
        '0100008803' => '12',
    ];

    public static function prefixForCustomer(string $kodeCustomer): string
    {
        $prefix = self::$prefixMap[$kodeCustomer] ?? null;
        if (!$prefix) {
            throw new \InvalidArgumentException("Kode customer '{$kodeCustomer}' tidak memiliki mapping prefix barcode.");
        }
        return $prefix;
    }

    public function penerimaanBarang()
    {
        return $this->belongsTo(PenerimaanBarang::class, 'npb', 'npb');
    }

    /**
     * Generate barcode baru berdasarkan kode_customer TANPA menyimpan ke DB.
     *
     * Catatan:
     * - Ini hanya helper untuk format barcode.
     * - Untuk mekanisme yang robust (anti-duplicate) gunakan reservasi via counter + transaction di Controller.
     * Format running number: prefix 2 digit + sequence 6 digit (total 10 digit)
     * contoh: 11000001, 11000002, dst.
     */
    public static function formatBarcode(string $kodeCustomer, int $seq): string
    {
        $prefix = self::prefixForCustomer($kodeCustomer);
        return $prefix . str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }
}
