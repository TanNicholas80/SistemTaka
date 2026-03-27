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

class KasirPenjualan extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'npj',
        'tanggal',
        'customer',
        'alamat',
        'keterangan',
        'kena_pajak',
        'syarat_bayar',
        'total_termasuk_pajak',
        'diskon_keseluruhan',
        'kode_customer',

    ];

    /**
     * Konfigurasi activity log untuk model KasirPenjualan
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['npj', 'tanggal', 'customer', 'alamat', 'keterangan', 'kena_pajak', 'syarat_bayar', 'total_termasuk_pajak', 'diskon_keseluruhan', 'kode_customer']) // Log field yang ada di KasirPenjualan
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Pembaruan Data Kasir Penjualan') // Set log name sesuai permintaan
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
        $activity->description = "Kasir Penjualan baru dengan NPJ '{$this->npj}' untuk customer '{$this->customer}' telah dibuat";
        $activity->properties = $activity->properties->merge([
            'event_type' => 'created',
            'created_data' => [
                'npj' => $this->npj,
                'tanggal' => $this->tanggal,
                'customer' => $this->customer,
                'alamat' => $this->alamat,
                'kena_pajak' => $this->kena_pajak,
                'syarat_bayar' => $this->syarat_bayar,
                'total_termasuk_pajak' => $this->total_termasuk_pajak,
                'diskon_keseluruhan' => $this->diskon_keseluruhan,
                'kode_customer' => $this->kode_customer,
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
            // Generate npj only if it's empty
            if (empty($model->npj)) {
                $model->npj = self::generateNpj($model->kode_customer ?? null);
            }
        });
    }

    protected static function generateNpj($kodeCustomer = null)
    {
        $now = Carbon::now();
        $year = $now->format('Y');
        $prefix = "SO.{$year}.";

        try {
            // Validasi active_branch session
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                Log::warning('Tidak ada cabang yang aktif saat generate npj, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil data Branch
            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                Log::warning('Data cabang tidak ditemukan saat generate npj, menggunakan default');
                return "{$prefix}00001";
            }

            // Validasi credentials API Accurate dari user aktif (via UserAccurateAPI)
            $user = Auth::user();
            if (!$user || !(\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_signature_secret'] ?? null)) {
                Log::warning('Kredensial API Accurate user belum diatur untuk branch aktif saat generate npj, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil API credentials (auto-decrypted via accessor di User model)
            $apiToken = (\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_signature_secret'] ?? null);

            // Get the last entry from the local database filtered by kode_customer
            $query = self::where('npj', 'like', $prefix . '%');
            
            // Jika kode_customer tersedia, filter berdasarkan kode_customer
            // Jika tidak, gunakan customer_id dari branch sebagai fallback
            $customerId = $kodeCustomer ?? $branch->customer_id;
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }
            
            $lastEntry = $query->orderBy('npj', 'desc')->first();

            if ($lastEntry && !empty($lastEntry->npj)) {
                // Extract the number from the last npj and increment it
                $lastNpj = $lastEntry->npj;
                $lastIter = (int)substr($lastNpj, strrpos($lastNpj, '.') + 1);
                $newIter = $lastIter + 1;
                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);

                Log::info('Generated npj from local database', [
                    'last_npj' => $lastNpj,
                    'new_npj' => "{$prefix}{$formattedIter}",
                    'kode_customer' => $customerId
                ]);

                return "{$prefix}{$formattedIter}";
            }

            // If no local entry exists, fall back to API call (URL dari Branch.url_accurate)
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
            $listApiUrl = $branch->getAccurateApiBaseUrl() . '/sales-order/list.do';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($listApiUrl, [
                'fields' => 'number',
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // Check if the response contains the 'd' key and is an array
                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    // Filter hanya untuk prefix tahun ini
                    $filteredOrders = array_filter($responseData['d'], function ($order) use ($prefix) {
                        return isset($order['number']) && strpos($order['number'], $prefix) === 0;
                    });

                    if (!empty($filteredOrders)) {
                        // Get the last sales order number
                        $lastResult = collect($filteredOrders)->sortByDesc('number')->first();

                        if ($lastResult) {
                            $lastNpj = $lastResult['number'];

                            // Extract the last iteration number and increment it
                            $lastIter = (int)substr($lastNpj, strrpos($lastNpj, '.') + 1);
                            $newIter = $lastIter + 1;
                            $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);

                            Log::info('Generated npj from API', [
                                'last_npj' => $lastNpj,
                                'new_npj' => "{$prefix}{$formattedIter}",
                                'kode_customer' => $customerId
                            ]);

                            return "{$prefix}{$formattedIter}";
                        }
                    } else {
                        Log::info('No sales orders found for current year prefix', ['prefix' => $prefix]);
                    }
                } else {
                    Log::warning('API list response does not contain expected data structure', [
                        'responseData' => $responseData
                    ]);
                }
            } else {
                Log::error('API list request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while generating npj: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // If any error occurred or no data was found, return a default npj
        Log::warning('Using fallback npj: ' . $prefix . '00001', [
            'kode_customer' => $kodeCustomer ?? null
        ]);
        return "{$prefix}00001";
    }

    public function detailItems()
    {
        return $this->hasMany(DetailItemPenjualan::class, 'npj', 'npj');
    }
}
