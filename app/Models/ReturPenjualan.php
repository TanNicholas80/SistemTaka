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

class ReturPenjualan extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_retur',
        'tanggal_retur',
        'kode_customer',
        'pelanggan_id',
        'return_type',
        'return_status_type',
        'no_faktur_penjualan',
    ];

    protected $casts = [
        'tanggal_retur' => 'date',
    ];

    /**
     * Konfigurasi activity log untuk model ReturPenjualan
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Retur Penjualan')
            ->logFillable();
    }

    public function tapActivity($activity, string $eventName)
    {
        if ($eventName === 'created') {
            $causer = Auth::user();
            $causerInfo = $causer ? [
                'causer_id' => $causer->id,
                'causer_type' => get_class($causer),
                'causer_name' => $causer->name,
                'causer_username' => $causer->username ?? null,
                'causer_role' => $causer->role ?? null,
            ] : null;

            $activity->description = "Retur Penjualan baru '{$this->no_retur}' telah dibuat dengan tanggal '{$this->tanggal_retur}'";
            $activity->properties = $activity->properties->merge([
                'event_type' => 'created',
                'created_data' => $this->only($this->fillable) + ['created_at' => $this->created_at?->toIso8601String()],
                'causer_info' => $causerInfo,
                'timestamp_info' => [
                    'action_date' => now()->format('Y-m-d'),
                    'action_time' => now()->format('H:i:s'),
                    'timezone' => config('app.timezone', 'UTC'),
                ],
            ]);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->no_retur)) {
                $model->no_retur = self::generateNoRetur($model->kode_customer ?? null);
            }
        });
    }

    /**
     * Build URL API list retur penjualan (metode seperti FakturPenjualanController).
     * Endpoint: sales-return/list.do
     */
    public static function getListApiUrl(Branch $branch): string
    {
        return $branch->getAccurateApiBaseUrl() . '/sales-return/list.do';
    }

    /**
     * Generate nomor retur penjualan format: SRT.{tahun}.00001
     */
    public static function generateNoRetur(?string $kodeCustomer = null): string
    {
        $now = Carbon::now();
        $year = $now->format('Y');
        $prefix = "SRT.{$year}.";

        try {
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                Log::warning('Tidak ada cabang yang aktif saat generate no_retur retur penjualan, menggunakan default');
                return $prefix . '00001';
            }

            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                Log::warning('Data cabang tidak ditemukan saat generate no_retur retur penjualan, menggunakan default');
                return $prefix . '00001';
            }

            // Ambil API credentials dari User (mengikuti pola PenerimaanBarang::generateNpb)
            $user = Auth::user();
            if (
                !$user ||
                !$user->accurate_api_token ||
                !$user->accurate_signature_secret
            ) {
                Log::warning('Kredensial API Accurate user belum diatur saat generate no_retur retur penjualan, menggunakan default');
                return $prefix . '00001';
            }

            $apiToken = $user->accurate_api_token;
            $signatureSecret = $user->accurate_signature_secret;

            $maxIter = 0;

            // 1) Cek nomor terakhir dari Accurate API
            $lastNoReturFromAPI = self::getLastNoReturFromAPI(
                $apiToken,
                $signatureSecret,
                $prefix,
                $branch
            );
            if ($lastNoReturFromAPI) {
                $iterVal = (int) substr($lastNoReturFromAPI, strrpos($lastNoReturFromAPI, '.') + 1);
                $maxIter = max($maxIter, $iterVal);
            }

            // 2) Cek nomor terakhir dari DB lokal (jika ada transaksi belum tersinkronisasi)
            $query = self::where('no_retur', 'like', $prefix . '%');
            $customerId = $kodeCustomer ?? $branch->customer_id ?? null;
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }
            $lastEntry = $query->orderBy('no_retur', 'desc')->first();
            if ($lastEntry && !empty($lastEntry->no_retur)) {
                $lastNoRetur = $lastEntry->no_retur;
                $iterVal = (int) substr($lastNoRetur, strrpos($lastNoRetur, '.') + 1);
                $maxIter = max($maxIter, $iterVal);
            }

            if ($maxIter > 0) {
                $newIter = $maxIter + 1;
                $formattedIter = str_pad($newIter, 5, '0', STR_PAD_LEFT);
                Log::info('Generated no_retur retur penjualan from combined check (Local + API)', [
                    'new_no_retur' => $prefix . $formattedIter,
                    'kode_customer' => $customerId,
                    'max_iter' => $maxIter,
                ]);
                return $prefix . $formattedIter;
            }
        } catch (\Exception $e) {
            Log::error('Exception generating no_retur retur penjualan: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        Log::warning('Using fallback no_retur retur penjualan: ' . $prefix . '00001');
        return $prefix . '00001';
    }

    /**
     * Ambil nomor terakhir no_retur dari Accurate API berdasarkan prefix.
     */
    private static function getLastNoReturFromAPI(
        string $apiToken,
        string $signatureSecret,
        string $prefix,
        Branch $branch
    ): ?string {
        $listApiUrl = self::getListApiUrl($branch);

        $page = 1;
        $pageSize = 100;
        $all = [];
        $maxPages = 10;

        do {
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $response = Http::timeout(60)->withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($listApiUrl, [
                'fields' => 'number',
                'sp.page' => $page,
                'sp.pageSize' => $pageSize,
                'sp.sort' => 'number|desc',
            ]);

            if (!$response->successful()) {
                Log::error('API request failed saat getLastNoReturFromAPI', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'page' => $page,
                ]);
                break;
            }

            $responseData = $response->json();
            if (!isset($responseData['d']) || !is_array($responseData['d'])) {
                break;
            }

            $pageData = $responseData['d'];
            $all = array_merge($all, $pageData);

            $totalItems = $responseData['sp']['rowCount'] ?? 0;
            $hasMore = count($pageData) === $pageSize && (($page * $pageSize) < $totalItems);
            $page++;
        } while ($hasMore && $page <= $maxPages);

        if (empty($all)) {
            return null;
        }

        $filtered = array_filter($all, function ($item) use ($prefix) {
            return isset($item['number']) && strpos((string) $item['number'], $prefix) === 0;
        });

        if (empty($filtered)) {
            return null;
        }

        usort($filtered, function ($a, $b) {
            return strcmp((string) ($b['number'] ?? ''), (string) ($a['number'] ?? ''));
        });

        return (string) ($filtered[0]['number'] ?? null);
    }
}
