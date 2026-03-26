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

class ReturPembelian extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_retur',
        'tanggal_retur',
        'kode_customer',
        'vendor',
        'return_type',
        'faktur_pembelian_id',
    ];

    protected $casts = [
        'tanggal_retur' => 'date',
    ];

    /**
     * Konfigurasi activity log untuk model ReturPembelian
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Retur Pembelian')
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

            $activity->description = "Retur Pembelian baru '{$this->no_retur}' telah dibuat dengan tanggal '{$this->tanggal_retur}'";
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
     * Build URL API list retur pembelian (metode seperti FakturPenjualanController).
     * Endpoint: purchase-return/list.do
     */
    public static function getListApiUrl(Branch $branch): string
    {
        return $branch->getAccurateApiBaseUrl() . '/purchase-return/list.do';
    }

    /**
     * Generate nomor retur pembelian format: PRT.{tahun}.{bulan}.00001
     */
    public static function generateNoRetur(?string $kodeCustomer = null): string
    {
        $now = Carbon::now();
        $year = $now->format('Y');
        $month = $now->format('m');
        $prefix = "PRT.{$year}.{$month}.";

        try {
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                Log::warning('Tidak ada cabang yang aktif saat generate no_retur retur pembelian, menggunakan default');
                return $prefix . '00001';
            }

            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                Log::warning('Data cabang tidak ditemukan saat generate no_retur retur pembelian, menggunakan default');
                return $prefix . '00001';
            }

            $user = Auth::user();
            if (!$user || !$user->accurate_api_token || !$user->accurate_signature_secret) {
                Log::warning('Kredensial API Accurate untuk user belum diatur saat generate no_retur retur pembelian, menggunakan default');
                return $prefix . '00001';
            }

            $query = self::where('no_retur', 'like', $prefix . '%');
            $customerId = $kodeCustomer ?? $branch->customer_id;
            if ($customerId) {
                $query->where('kode_customer', $customerId);
            }
            $lastEntry = $query->orderBy('no_retur', 'desc')->first();

            if ($lastEntry && !empty($lastEntry->no_retur)) {
                $lastNoRetur = $lastEntry->no_retur;
                $lastIter = (int) substr($lastNoRetur, strrpos($lastNoRetur, '.') + 1);
                $formattedIter = str_pad($lastIter + 1, 5, '0', STR_PAD_LEFT);
                Log::info('Generated no_retur retur pembelian from local database', [
                    'last_no_retur' => $lastNoRetur,
                    'new_no_retur' => $prefix . $formattedIter,
                ]);
                return $prefix . $formattedIter;
            }

            $apiToken = $user->accurate_api_token;
            $signatureSecret = $user->accurate_signature_secret;
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
            $listApiUrl = self::getListApiUrl($branch);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($listApiUrl, [
                        'fields' => 'number',
                        'sp.page' => 1,
                        'sp.pageSize' => 100,
                    ]);

            if ($response->successful()) {
                $responseData = $response->json();
                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $filtered = array_filter($responseData['d'], function ($item) use ($prefix) {
                        return isset($item['number']) && strpos($item['number'], $prefix) === 0;
                    });
                    if (!empty($filtered)) {
                        usort($filtered, fn($a, $b) => strcmp($b['number'], $a['number']));
                        $lastNoRetur = $filtered[0]['number'];
                        $lastIter = (int) substr($lastNoRetur, strrpos($lastNoRetur, '.') + 1);
                        $formattedIter = str_pad($lastIter + 1, 5, '0', STR_PAD_LEFT);
                        Log::info('Generated no_retur retur pembelian from API', [
                            'last_no_retur' => $lastNoRetur,
                            'new_no_retur' => $prefix . $formattedIter,
                        ]);
                        return $prefix . $formattedIter;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception generating no_retur retur pembelian: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        Log::warning('Using fallback no_retur retur pembelian: ' . $prefix . '00001');
        return $prefix . '00001';
    }
}