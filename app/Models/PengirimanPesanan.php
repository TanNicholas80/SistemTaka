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

class PengirimanPesanan extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_pengiriman',
        'tanggal_pengiriman',
        'pelanggan_id',
        'penjualan_id',
        'alamat',
        'keterangan',
        'syarat_bayar',
        'kena_pajak',
        'total_termasuk_pajak',
        'diskon_keseluruhan',
        'is_partial',
        'kode_customer',
    ];

    /**
     * Konfigurasi activity log untuk model FakturPenjualan
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['no_pengiriman', 'tanggal_pengiriman', 'pelanggan_id', 'penjualan_id', 'alamat', 'keterangan', 'syarat_bayar', 'kena_pajak', 'total_termasuk_pajak', 'diskon_keseluruhan', 'kode_customer'])
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Pengiriman Pesanan') // Set log name sesuai permintaan
            ->logFillable(); // Log semua fillable attributes
    }

    /**
     * Customize what gets logged for different events
     */
    public function tapActivity($activity, string $eventName)
    {
        // Hanya handle event created sesuai permintaan user
        if ($eventName === 'created') {
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
            $activity->description = "Pengiriman Pesanan baru '{$this->no_pengiriman}' telah dibuat dengan tanggal '{$this->tanggal_pengiriman}'";
            $activity->properties = $activity->properties->merge([
                'event_type' => 'created',
                'created_data' => [
                    'no_pengiriman' => $this->no_pengiriman,
                    'tanggal_pengiriman' => $this->tanggal_pengiriman,
                    'kode_customer' => $this->kode_customer,
                    'pelanggan_id' => $this->pelanggan_id,
                    'penjualan_id' => $this->penjualan_id,
                    'syarat_bayar' => $this->syarat_bayar,
                    'kena_pajak' => $this->kena_pajak,
                    'total_termasuk_pajak' => $this->total_termasuk_pajak,
                    'diskon_keseluruhan' => $this->diskon_keseluruhan,
                    'created_at' => $this->created_at
                ],
                'causer_info' => $causerInfo,
                'timestamp_info' => $timestampInfo
            ]);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Generate no_pengiriman only if it's empty
            if (empty($model->no_pengiriman)) {
                $model->no_pengiriman = self::generateNoPengiriman($model->kode_customer ?? null);
            }
        });
    }

    protected static function generateNoPengiriman($kodeCustomer = null)
    {
        $now = Carbon::now();
        $year = $now->format('Y');
        $prefix = "DO.{$year}.";

        try {
            // Validasi active_branch session
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                Log::warning('Tidak ada cabang yang aktif saat generate no_pengiriman, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil data Branch
            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                Log::warning('Data cabang tidak ditemukan saat generate no_pengiriman, menggunakan default');
                return "{$prefix}00001";
            }

            // Validasi credentials API Accurate dari Branch
            if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
                Log::warning('Kredensial API Accurate untuk cabang belum diatur saat generate no_pengiriman, menggunakan default');
                return "{$prefix}00001";
            }

            // Get API credentials from branch (auto-decrypted by model accessors)
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;

            // 1. PRIORITAS UTAMA: Cek database lokal terlebih dahulu filtered by kode_customer
            $query = self::where('no_pengiriman', 'like', $prefix . '%');

            // Jika kode_customer tersedia, filter berdasarkan kode_customer
            // Jika tidak, gunakan customer_id dari branch sebagai fallback
            $customerId = $kodeCustomer ?? $branch->customer_id;
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }

            $lastEntry = $query->orderBy('no_pengiriman', 'desc')->first();

            if ($lastEntry && !empty($lastEntry->no_pengiriman)) {
                // Extract nomor dari no_pengiriman terakhir dan increment
                $lastNoPengiriman = $lastEntry->no_pengiriman;
                $lastIter = (int) substr($lastNoPengiriman, strrpos($lastNoPengiriman, '.') + 1);
                $newIter = $lastIter + 1;
                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);

                Log::info('Generated no_pengiriman from local database', [
                    'last_no_pengiriman' => $lastNoPengiriman,
                    'new_no_pengiriman' => "{$prefix}{$formattedIter}",
                    'kode_customer' => $customerId
                ]);

                return "{$prefix}{$formattedIter}";
            }

            // 2. Jika tidak ada di database lokal, cek API dengan pagination
            $lastNoPengirimanFromAPI = self::getLastNoPengirimanFromAPI($apiToken, $signatureSecret, $prefix, $branch);

            if ($lastNoPengirimanFromAPI) {
                $lastIter = (int) substr($lastNoPengirimanFromAPI, strrpos($lastNoPengirimanFromAPI, '.') + 1);
                $newIter = $lastIter + 1;
                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);

                Log::info('Generated no_pengiriman from API', [
                    'last_no_pengiriman' => $lastNoPengirimanFromAPI,
                    'new_no_pengiriman' => "{$prefix}{$formattedIter}",
                    'kode_customer' => $customerId
                ]);

                return "{$prefix}{$formattedIter}";
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while generating no_pengiriman: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Fallback: return default nomor
        Log::warning('Using fallback no_pengiriman: ' . $prefix . '00001', [
            'kode_customer' => $kodeCustomer ?? null
        ]);
        return "{$prefix}00001";
    }

    /**
     * Get last no_pengiriman from API dengan proper pagination dan sorting.
     * URL dibangun dari Branch.url_accurate.
     */
    private static function getLastNoPengirimanFromAPI($apiToken, $signatureSecret, $prefix, Branch $branch)
    {
        $baseUrl = $branch->getAccurateApiBaseUrl() . '/delivery-order/list.do';
        $currentYear = Carbon::now()->format('Y');
        $allDeliveryOrders = [];
        $page = 1;
        $pageSize = 100; // Gunakan page size yang lebih besar untuk efisiensi

        do {
            // Generate timestamp and signature for each request
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $response = Http::withHeaders([
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
            $allDeliveryOrders = array_merge($allDeliveryOrders, $pageData);

            // Check jika sudah mendapatkan semua data
            $totalItems = $responseData['sp']['rowCount'] ?? 0;
            $hasMore = count($pageData) === $pageSize && (($page * $pageSize) < $totalItems);

            $page++;
        } while ($hasMore && $page <= 10); // Batasi maksimal 10 halaman untuk safety

        if (empty($allDeliveryOrders)) {
            Log::warning('No delivery orders found from API');
            return null;
        }

        // Filter hanya untuk tahun ini dan prefix yang sesuai
        $filteredOrders = array_filter($allDeliveryOrders, function ($order) use ($prefix) {
            return isset($order['number']) && strpos($order['number'], $prefix) === 0;
        });

        if (empty($filteredOrders)) {
            Log::info('No delivery orders found for current year prefix', ['prefix' => $prefix]);
            return null;
        }

        // Sort descending berdasarkan nomor
        usort($filteredOrders, function ($a, $b) {
            return strcmp($b['number'], $a['number']);
        });

        $lastOrder = $filteredOrders[0];

        Log::info('Found last delivery order from API', [
            'number' => $lastOrder['number'],
            'total_orders' => count($allDeliveryOrders),
            'filtered_orders' => count($filteredOrders)
        ]);

        return $lastOrder['number'];
    }
}
