<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PenerimaanBarang extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_po',
        'vendor',
        'no_terima',
        'npb',
        'tanggal',
        'kode_customer',
    ];

    /**
     * Packing list yang terhubung (many-to-many).
     */
    public function packingLists()
    {
        return $this->belongsToMany(\App\Models\PackingList::class, 'penerimaan_barang_packing_list')
            ->withTimestamps();
    }

    /**
     * Barcode dari packing list yang terhubung (untuk kompatibilitas showApproval dll).
     */
    public function barcodes()
    {
        $npls = $this->packingLists()->pluck('npl');
        return \App\Models\Barcode::whereIn('no_packing_list', $npls)->get();
    }

    /**
     * Konfigurasi activity log untuk model PenerimaanBarang
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['no_po', 'vendor', 'no_terima', 'npb', 'tanggal', 'kode_customer']) // Log field yang ada di PenerimaanBarang
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Pembaruan Data Penerimaan Barang') // Set log name sesuai permintaan
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
        $activity->description = "Penerimaan Barang baru dengan NPB '{$this->npb}' dari vendor '{$this->vendor}' telah dibuat";
        $activity->properties = $activity->properties->merge([
            'event_type' => 'created',
            'created_data' => [
                'no_po' => $this->no_po,
                'vendor' => $this->vendor,
                'no_terima' => $this->no_terima,
                'kode_customer' => $this->kode_customer,
                'npb' => $this->npb,
                'tanggal' => $this->tanggal,
                'created_at' => $this->created_at->format('Y-m-d H:i:s')
            ],
            'causer_info' => $causerInfo,
            'timestamp_info' => $timestampInfo
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->npb)) {
                $model->npb = self::generateNpb($model->kode_customer ?? null);
            }
            if (empty($model->no_terima)) {
                $model->no_terima = self::generateNoTerima($model->kode_customer ?? null);
            }
        });
    }

    /**
     * Generate No Terima otomatis dengan format NTB.YYYY.MM.DD.001 (running number 3 digit per hari).
     */
    public static function generateNoTerima($kodeCustomer = null)
    {
        $now = Carbon::now();
        $prefix = 'NTB.' . $now->format('Y.m.d') . '.';

        try {
            $activeBranchId = session('active_branch');
            $branch = $activeBranchId ? Branch::find($activeBranchId) : null;
            $customerId = $kodeCustomer ?? ($branch->customer_id ?? null);

            $query = self::where('no_terima', 'like', $prefix . '%');
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }

            $lastEntry = $query->orderBy('no_terima', 'desc')->first();

            if ($lastEntry && !empty($lastEntry->no_terima)) {
                $lastNumber = (int) substr($lastEntry->no_terima, strrpos($lastEntry->no_terima, '.') + 1);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            $formatted = $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            Log::info('Generated no_terima', [
                'no_terima' => $formatted,
                'kode_customer' => $customerId,
            ]);

            return $formatted;
        } catch (\Exception $e) {
            Log::error('Exception saat generate no_terima: ' . $e->getMessage());
        }

        return $prefix . '001';
    }

    public static function generateNpb($kodeCustomer = null)
    {
        $now = Carbon::now();
        $year = $now->format('Y');
        $prefix = "RI.{$year}.";

        try {
            // Validasi active_branch session
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                Log::warning('Tidak ada cabang yang aktif saat generate npb, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil data Branch
            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                Log::warning('Data cabang tidak ditemukan saat generate npb, menggunakan default');
                return "{$prefix}00001";
            }

            // Validasi credentials API Accurate dari user login
            $user = Auth::user();
            if (
                !$user ||
                !$user->accurate_api_token ||
                !$user->accurate_signature_secret
            ) {
                Log::warning('Kredensial API Accurate user belum diatur saat generate npb, menggunakan default');
                return "{$prefix}00001";
            }

            // Get API credentials from user
            $apiToken = $user->accurate_api_token;
            $signatureSecret = $user->accurate_signature_secret;

            $maxIter = 0;

            // 1. PRIORITAS TERTINGGI: Cek API Accurate untuk mendapatkan nomor terakhir
            $lastNpbFromAPI = self::getLastNpbFromAPI($apiToken, $signatureSecret, $prefix, $branch);
            if ($lastNpbFromAPI) {
                $iterVal = (int) substr($lastNpbFromAPI, strrpos($lastNpbFromAPI, '.') + 1);
                if ($iterVal > $maxIter) {
                    $maxIter = $iterVal;
                }
            }

            // 2. Cek database lokal untuk nomor terakhir (jika ada transaksi belum terikirim)
            $query = self::where('npb', 'like', $prefix . '%');
            $customerId = $kodeCustomer ?? $branch->customer_id;
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }
            $lastEntry = $query->orderBy('npb', 'desc')->first();

            if ($lastEntry && !empty($lastEntry->npb)) {
                $lastNpbFromDB = $lastEntry->npb;
                // Pastikan formatnya RI.YYYY.XXXXX sebelum di-extract iter-nya
                // Jika NPB lama menggunakan format RI.YYYY.MM.XXXXX, kita harus berhati-hati.
                // Tapi secara umum strrpos('.') akan mengambil XXXXX setelah titik terakhir.
                $parts = explode('.', $lastNpbFromDB);
                $iterVal = (int) end($parts);
                if ($iterVal > $maxIter) {
                    $maxIter = $iterVal;
                }
            }

            // 3. Generate nomor baru
            if ($maxIter > 0) {
                $newIter = $maxIter + 1;
                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);
                
                Log::info('Generated npb from combined check (Local + API)', [
                    'new_npb' => "{$prefix}{$formattedIter}",
                    'kode_customer' => $customerId
                ]);

                return "{$prefix}{$formattedIter}";
            }

        } catch (\Exception $e) {
            Log::error('Exception occurred while generating npb: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Fallback: return default nomor
        Log::warning('Using fallback npb: ' . $prefix . '00001', [
            'kode_customer' => $kodeCustomer ?? null
        ]);
        return "{$prefix}00001";
    }

    /**
     * Get last npb from API dengan proper pagination dan sorting.
     * URL dibangun dari Branch.url_accurate.
     */
    private static function getLastNpbFromAPI($apiToken, $signatureSecret, $prefix, Branch $branch)
    {
        $baseUrl = $branch->getAccurateApiBaseUrl() . '/receive-item/list.do';
        $currentYear = Carbon::now()->format('Y');
        $currentMonth = Carbon::now()->format('m');
        $allReceiveItems = [];
        $page = 1;
        $pageSize = 100; // Gunakan page size yang lebih besar untuk efisiensi

        do {
            // Generate timestamp and signature for each request
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($baseUrl, [
                        'fields' => 'number',
                        'sp.page' => $page,
                        'sp.pageSize' => $pageSize,
                        'sp.sort' => 'number|desc', // Coba sorting descending
                    ]);

            if (!$response->successful()) {
                Log::error('API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'page' => $page
                ]);
                break;
            }

            $responseData = $response->json();

            if (!isset($responseData['d']) || !is_array($responseData['d'])) {
                Log::warning('API response does not contain expected data structure', [
                    'responseData' => $responseData,
                    'page' => $page
                ]);
                break;
            }

            $pageData = $responseData['d'];
            $allReceiveItems = array_merge($allReceiveItems, $pageData);

            // Check jika sudah mendapatkan semua data
            $totalItems = $responseData['sp']['rowCount'] ?? 0;
            $hasMore = count($pageData) === $pageSize && (($page * $pageSize) < $totalItems);

            $page++;
        } while ($hasMore && $page <= 10); // Batasi maksimal 10 halaman untuk safety

        if (empty($allReceiveItems)) {
            Log::warning('No receive items found from API');
            return null;
        }

        // Filter hanya untuk bulan dan tahun ini serta prefix yang sesuai
        $filteredItems = array_filter($allReceiveItems, function ($item) use ($prefix) {
            return isset($item['number']) && strpos($item['number'], $prefix) === 0;
        });

        if (empty($filteredItems)) {
            Log::info('No receive items found for current month prefix', ['prefix' => $prefix]);
            return null;
        }

        // Sort descending berdasarkan nomor
        usort($filteredItems, function ($a, $b) {
            return strcmp($b['number'], $a['number']);
        });

        $lastItem = $filteredItems[0];

        Log::info('Found last receive item from API', [
            'number' => $lastItem['number'],
            'total_items' => count($allReceiveItems),
            'filtered_items' => count($filteredItems)
        ]);

        return $lastItem['number'];
    }
}
