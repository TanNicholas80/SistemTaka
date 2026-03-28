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

class FakturPenjualan extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_faktur',
        'tanggal_faktur',
        'pelanggan_id',
        'pengiriman_id',
        'alamat',
        'keterangan',
        'syarat_bayar',
        'kena_pajak',
        'total_termasuk_pajak',
        'diskon_keseluruhan',
        'kode_customer',
    ];

    /**
     * Konfigurasi activity log untuk model FakturPenjualan
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['no_faktur', 'tanggal_faktur', 'pelanggan_id', 'pengiriman_id', 'alamat', 'keterangan', 'syarat_bayar', 'kena_pajak', 'total_termasuk_pajak', 'diskon_keseluruhan', 'kode_customer'])
            ->logOnlyDirty() // Hanya log perubahan yang benar-benar terjadi
            ->dontSubmitEmptyLogs() // Jangan submit log kosong
            ->useLogName('Faktur Penjualan') // Set log name sesuai permintaan
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
            $activity->description = "Faktur Penjualan baru '{$this->no_faktur}' telah dibuat dengan tanggal '{$this->tanggal_faktur}'";
            $activity->properties = $activity->properties->merge([
                'event_type' => 'created',
                'created_data' => [
                    'no_faktur' => $this->no_faktur,
                    'tanggal_faktur' => $this->tanggal_faktur,
                    'pelanggan_id' => $this->pelanggan_id,
                    'pengiriman_id' => $this->pengiriman_id,
                    'syarat_bayar' => $this->syarat_bayar,
                    'kena_pajak' => $this->kena_pajak,
                    'total_termasuk_pajak' => $this->total_termasuk_pajak,
                    'diskon_keseluruhan' => $this->diskon_keseluruhan,
                    'kode_customer' => $this->kode_customer,
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
            // Generate no_faktur only if it's empty
            if (empty($model->no_faktur)) {
                $model->no_faktur = self::generateNoFaktur($model->kode_customer ?? null);
            }
        });
    }

    protected static function generateNoFaktur($kodeCustomer = null) 
    {
        $now = Carbon::now();
        $year = $now->format('Y');
        $prefix = "SI.{$year}.";

        try {
            // Validasi active_branch session
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                Log::warning('Tidak ada cabang yang aktif saat generate no_faktur, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil data Branch
            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                Log::warning('Data cabang tidak ditemukan saat generate no_faktur, menggunakan default');
                return "{$prefix}00001";
            }

            // Validasi credentials API Accurate dari user aktif (diambil via UserAccurateAPI)
            $user = Auth::user();
            if (!$user || !(\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_signature_secret'] ?? null)) {
                Log::warning('Kredensial API Accurate user belum diatur untuk branch aktif saat generate no_faktur, menggunakan default');
                return "{$prefix}00001";
            }

            // Ambil API credentials (auto-decrypted via accessor di User model)
            $apiToken = (\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentials($user->id ?? null, session('active_branch'))['accurate_signature_secret'] ?? null);

            // Get the last entry from the local database filtered by kode_customer
            $query = self::where('no_faktur', 'like', $prefix . '%');
            
            // Jika kode_customer tersedia, filter berdasarkan kode_customer
            // Jika tidak, gunakan customer_id dari branch sebagai fallback
            $customerId = $kodeCustomer ?? $branch->customer_id;
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }
            
            $lastEntry = $query->orderBy('no_faktur', 'desc')->first();

            if ($lastEntry && !empty($lastEntry->no_faktur)) {
                // Extract the number from the last no_faktur and increment it
                $lastNoFaktur = $lastEntry->no_faktur;
                $lastIter = (int)substr($lastNoFaktur, strrpos($lastNoFaktur, '.') + 1);
                $newIter = $lastIter + 1;
                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);

                Log::info('Generated no_faktur from local database', [
                    'last_no_faktur' => $lastNoFaktur,
                    'new_no_faktur' => "{$prefix}{$formattedIter}",
                    'kode_customer' => $customerId
                ]);

                return "{$prefix}{$formattedIter}";
            }

            // If no local entry exists, fall back to API call (URL dari Branch.url_accurate)
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
            $listApiUrl = $branch->getAccurateApiBaseUrl() . '/sales-invoice/list.do';

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
                    $filteredInvoices = array_filter($responseData['d'], function ($invoice) use ($prefix) {
                        return isset($invoice['number']) && strpos($invoice['number'], $prefix) === 0;
                    });

                    if (!empty($filteredInvoices)) {
                        // Get the last sales invoice number
                        $lastResult = collect($filteredInvoices)->sortByDesc('number')->first();

                        if ($lastResult) {
                            $lastNoFaktur = $lastResult['number'];

                            // Extract the last iteration number and increment it
                            $lastIter = (int)substr($lastNoFaktur, strrpos($lastNoFaktur, '.') + 1);
                            $newIter = $lastIter + 1;
                            $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);

                            Log::info('Generated no_faktur from API', [
                                'last_no_faktur' => $lastNoFaktur,
                                'new_no_faktur' => "{$prefix}{$formattedIter}",
                                'kode_customer' => $customerId
                            ]);

                            return "{$prefix}{$formattedIter}";
                        }
                    } else {
                        Log::info('No sales invoices found for current year prefix', ['prefix' => $prefix]);
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
            Log::error('Exception occurred while generating no_faktur: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // If any error occurred or no data was found, return a default no_faktur
        Log::warning('Using fallback no_faktur: ' . $prefix . '00001', [
            'kode_customer' => $kodeCustomer ?? null
        ]);
        return "{$prefix}00001";
    }
}
