<?php

namespace App\Models;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class HasilStockOpname extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'nop',
        'no_perintah_opname',
        'tanggal',
        'kode_customer',
    ];

        /**
     * Konfigurasi activity log untuk model HasilStockOpname
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nop', 'no_perintah_opname', 'tanggal', 'kode_customer']) // Log field yang ada di HasilStockOpname
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Manajemen Hasil Stock Opname') // Set log name sesuai permintaan
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
        $activity->description = "Hasil Stock Opname baru dengan NOP '{$this->nop}' untuk perintah '{$this->no_perintah_opname}' telah dibuat";
        $activity->properties = $activity->properties->merge([
            'event_type' => 'created',
            'created_data' => [
                'nop' => $this->nop,
                'kode_customer' => $this->kode_customer,
                'no_perintah_opname' => $this->no_perintah_opname,
                'tanggal' => $this->tanggal,
                'created_at' => $this->created_at->format('Y-m-d H:i:s')
            ],
            'causer_info' => $causerInfo,
            'timestamp_info' => $timestampInfo
        ]);
    }

    /**
     * Relasi One-to-Many dengan model HasilStockOpnameBarcode
     * Satu HasilStockOpname memiliki banyak HasilStockOpnameBarcode
     */
    public function hasilStockOpnameBarcodes()
    {
        return $this->hasMany(HasilStockOpnameBarcode::class, 'nop', 'nop');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Generate NOP only if it's empty
            if (empty($model->nop)) {
                $model->nop = self::generateNop($model->kode_customer ?? null);
            }
        });
    }

    protected static function generateNop($kodeCustomer = null)
    {
        $prefix = "OPR.";
    
        try {
            // Validasi active_branch session
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                Log::warning('Tidak ada cabang yang aktif saat generate NOP, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil data Branch
            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                Log::warning('Data cabang tidak ditemukan saat generate NOP, menggunakan default');
                return "{$prefix}00001";
            }

            // Validasi credentials API Accurate dari user aktif (via UserAccurateAPI)
            $user = Auth::user();
            if (!$user || !(\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_signature_secret'] ?? null)) {
                Log::warning('Kredensial API Accurate user belum diatur untuk branch aktif saat generate NOP, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil API credentials (auto-decrypted via accessor di User model)
            $apiToken = (\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            // Get the last entry from the local database filtered by kode_customer if available
            $query = self::orderBy('id', 'desc');
            
            // Jika kode_customer tersedia, filter berdasarkan kode_customer
            // Jika tidak, gunakan customer_id dari branch sebagai fallback
            $customerId = $kodeCustomer ?? $branch->customer_id;
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }
            
            $lastEntry = $query->first();
            
            if ($lastEntry && !empty($lastEntry->nop)) {
                // Extract the number from the last NOP and increment it
                $lastNop = $lastEntry->nop;
                $lastIter = (int)substr($lastNop, strrpos($lastNop, '.') + 1);
                $newIter = $lastIter + 1;
                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);
                
                Log::info('NOP generated from local database', [
                    'last_nop' => $lastNop,
                    'new_nop' => "{$prefix}{$formattedIter}",
                    'kode_customer' => $customerId
                ]);
                
                return "{$prefix}{$formattedIter}";
            }

            // If no local entry exists, fall back to API call (URL dari Branch.url_accurate)
            $baseUrl = $branch->getAccurateApiBaseUrl();
            $listApiUrl = $baseUrl . '/stock-opname-result/list.do';
            
            // If no local entry exists, fall back to API call
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($listApiUrl);
    
            if ($response->successful()) {
                $responseData = $response->json();
    
                // Check if the response contains the 'd' key and is an array
                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    // Get the last stock opname result ID
                    $lastResult = collect($responseData['d'])->sortByDesc('id')->first();
    
                    if ($lastResult) {
                        $detailApiUrl = $baseUrl . '/stock-opname-result/detail.do?id=' . $lastResult['id'];
                        
                        // Generate new timestamp and signature for detail request
                        $detailTimestamp = Carbon::now()->toIso8601String();
                        $detailSignature = hash_hmac('sha256', $detailTimestamp, $signatureSecret);
                        
                        $detailResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $apiToken,
                            'X-Api-Signature' => $detailSignature,
                            'X-Api-Timestamp' => $detailTimestamp,
                        ])->get($detailApiUrl);
    
                        if ($detailResponse->successful()) {
                            $detailData = $detailResponse->json();
    
                            // Check if the detail response contains the 'd' key
                            if (isset($detailData['d'])) {
                                $lastNop = $detailData['d']['number'];
    
                                // Extract the last iteration number and increment it
                                $lastIter = (int)substr($lastNop, strrpos($lastNop, '.') + 1);
                                $newIter = $lastIter + 1;
                                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);
    
                                Log::info('NOP generated from Accurate API', [
                                    'last_nop' => $lastNop,
                                    'new_nop' => "{$prefix}{$formattedIter}",
                                    'kode_customer' => $customerId
                                ]);
    
                                return "{$prefix}{$formattedIter}";
                            } else {
                                Log::warning('API detail response does not contain expected data structure', [
                                    'responseData' => $detailData
                                ]);
                            }
                        } else {
                            Log::error('API detail request failed', [
                                'status' => $detailResponse->status(),
                                'body' => $detailResponse->body(),
                            ]);
                        }
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
        } catch (Exception $e) {
            Log::error('Exception occurred while generating NOP: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    
        // If any error occurred or no data was found, return a default NOP
        Log::warning('NOP generation failed, using default NOP', [
            'kode_customer' => $kodeCustomer ?? null
        ]);
        return "{$prefix}00001";
    }
}
