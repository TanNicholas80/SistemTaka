<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\PackingList;
use App\Models\PenerimaanBarang;
use App\Models\Branch;
use App\Models\BarcodeNonPL;
use App\Models\UserAccurateAPI;
use Illuminate\Support\Str;
use Carbon\Carbon;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;

class PenerimaanBarangController extends Controller
{
    private const NONPL_RESERVATION_TTL_SECONDS = 1800; // 30 menit

    private function nonPlReservationCacheKey(string $token): string
    {
        return 'nonpl_reservation:' . $token;
    }
    /**
     * Normalisasi UOM dari Accurate / Barcode menjadi "YD" atau "M".
     */
    private function normalizeUom(?string $uom): string
    {
        $u = strtoupper(trim((string) $uom));
        $u = preg_replace('/\s+/', ' ', $u);

        if ($u === 'YD' || $u === 'YARD' || $u === 'YARDS') {
            return 'YD';
        }
        if ($u === 'M' || $u === 'MTR' || $u === 'METER' || $u === 'METRE') {
            return 'M';
        }

        // fallback: kalau format aneh, kembalikan as-is agar tidak konversi sembarangan
        return $u;
    }

    /**
     * Konversi length berdasarkan UOM target (Accurate) vs UOM sumber (barcode).
     * Aturan bisnis:
     * - Jika target M tapi barcode YD => length * 0.9
     * - Jika target YD tapi barcode M => length / 0.9
     * - Jika sama => no conversion
     */
    private function convertLengthByUom(float $length, string $targetUom, string $sourceUom): float
    {
        $t = $this->normalizeUom($targetUom);
        $s = $this->normalizeUom($sourceUom);

        if ($t === $s) {
            return $length;
        }
        if ($t === 'M' && $s === 'YD') {
            return $length * 0.9;
        }
        if ($t === 'YD' && $s === 'M') {
            return $length / 0.9;
        }
        return $length;
    }

    /**
     * Pasangan vendor/customer yang diizinkan untuk antar toko (Bandung ↔ Magelang).
     * null = cabang lain, tidak difilter nama TOKO.
     *
     * @return array{po_vendor: string, do_customer: string}|null
     */
    private function interStorePartnerNames(Branch $branch): ?array
    {
        $n = mb_strtoupper(trim((string) $branch->name), 'UTF-8');
        if ($n === 'BANDUNG') {
            return [
                'po_vendor' => 'TOKO MAGELANG',
                'do_customer' => 'TOKO BANDUNG',
            ];
        }
        if ($n === 'MAGELANG') {
            return [
                'po_vendor' => 'TOKO BANDUNG',
                'do_customer' => 'TOKO MAGELANG',
            ];
        }

        return null;
    }

    /**
     * Cocokkan label vendor/customer Accurate dengan label bisnis (trim, case-insensitive; izinkan substring).
     */
    private function interStoreLabelMatches(?string $actual, string $expectedLabel): bool
    {
        $a = mb_strtoupper(trim((string) $actual), 'UTF-8');
        $e = mb_strtoupper(trim($expectedLabel), 'UTF-8');
        if ($a === '' || $e === '') {
            return false;
        }
        if ($a === $e) {
            return true;
        }

        return str_contains($a, $e) || str_contains($e, $a);
    }

    /**
     * Validasi PO/DO antar toko sesuai cabang Bandung/Magelang (vendor PO + customer DO).
     *
     * @throws \Exception
     */
    private function assertInterStorePartnersForBranch(Branch $branch, ?string $poVendorName, ?string $doCustomerName): void
    {
        $partner = $this->interStorePartnerNames($branch);
        if ($partner === null) {
            return;
        }
        if (!$this->interStoreLabelMatches($poVendorName, $partner['po_vendor'])) {
            throw new \Exception(
                'Untuk antar toko cabang ini, No PO harus dari vendor "' . $partner['po_vendor'] . '".'
            );
        }
        if (!$this->interStoreLabelMatches($doCustomerName, $partner['do_customer'])) {
            throw new \Exception(
                'Untuk antar toko cabang ini, No DO harus untuk customer "' . $partner['do_customer'] . '".'
            );
        }
    }

    /**
     * Ambil nama vendor dari PO & customer dari DO lalu validasi aturan antar toko.
     *
     * @throws \Exception
     */
    private function validateInterStorePoDoForBranch(Branch $branch, string $noPo, string $noDo): void
    {
        $partner = $this->interStorePartnerNames($branch);
        if ($partner === null) {
            return;
        }

        $activeCred = UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'));
        $apiToken = $activeCred['accurate_api_token'] ?? null;
        $signatureSecret = $activeCred['accurate_signature_secret'] ?? null;
        if (!$apiToken || !$signatureSecret) {
            throw new \Exception('Kredensial Accurate cabang aktif tidak tersedia untuk validasi PO.');
        }

        $ts = Carbon::now()->toIso8601String();
        $sig = hash_hmac('sha256', $ts, $signatureSecret);

        $poResp = Http::timeout(30)->withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $sig,
            'X-Api-Timestamp' => $ts,
        ])->get($this->buildApiUrl($branch, 'purchase-order/detail.do'), [
            'number' => $noPo,
        ]);

        if (!$poResp->successful()) {
            throw new \Exception('Gagal mengambil detail PO untuk validasi antar toko.');
        }

        $poVendorName = data_get($poResp->json(), 'd.vendor.name');

        $doResult = $this->fetchDeliveryOrderDetailWithFallback($branch, $noDo);
        $doCustomerName = data_get($doResult['data'] ?? [], 'd.customer.name');

        $this->assertInterStorePartnersForBranch($branch, $poVendorName, $doCustomerName);
    }

    /**
     * Membangun URL API dari url_accurate branch
     * 
     * @param Branch $branch Branch yang aktif
     * @param string $endpoint Endpoint API (contoh: 'purchase-order/detail.do')
     * @return string URL lengkap untuk API
     */
    private function buildApiUrl($branch, $endpoint)
    {
        // Gunakan url_accurate dari branch, jika tidak ada gunakan default
        $baseUrl = $branch->url_accurate ?? 'https://iris.accurate.id';
        $baseUrl = rtrim($baseUrl, '/');
        $apiPath = '/accurate/api';

        // Jika url_accurate sudah termasuk path /accurate/api, gunakan langsung
        if (strpos($baseUrl, '/accurate/api') !== false) {
            return $baseUrl . '/' . ltrim($endpoint, '/');
        }

        return $baseUrl . $apiPath . '/' . ltrim($endpoint, '/');
    }

    /**
     * Ambil kredensial Accurate untuk mode antar toko:
     * - primary: cabang aktif
     * - secondary: customer_id lain milik user login
     */
    private function getInterStoreCredentials(Branch $branch): array
    {
        $primary = UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'));

        $secondary = null;
        if (Auth::check()) {
            $secondaryRecord = UserAccurateAPI::query()
                ->where('user_id', Auth::id())
                ->where('customer_id', '!=', $branch->customer_id)
                ->whereNotNull('accurate_api_token')
                ->whereNotNull('accurate_signature_secret')
                ->orderBy('id')
                ->first();

            if ($secondaryRecord) {
                $secondary = [
                    'customer_id' => $secondaryRecord->customer_id,
                    'accurate_api_token' => $secondaryRecord->accurate_api_token,
                    'accurate_signature_secret' => $secondaryRecord->accurate_signature_secret,
                ];
            }
        }

        return [
            'primary' => $primary,
            'secondary' => $secondary,
        ];
    }

    /**
     * Ambil detail DO dengan fallback kredensial (primary -> secondary).
     */
    private function fetchDeliveryOrderDetailWithFallback(Branch $branch, string $doNumber): array
    {
        $credentials = $this->getInterStoreCredentials($branch);
        $attempts = [];

        // Konsistensi mode antar_toko:
        // gunakan secondary (customer berbeda) sebagai sumber utama detail DO.
        if (!empty($credentials['secondary'])) {
            $attempts[] = ['source' => 'secondary', 'cred' => $credentials['secondary']];
        } elseif (!empty($credentials['primary'])) {
            // Fallback terakhir hanya jika secondary tidak tersedia sama sekali.
            $attempts[] = ['source' => 'primary', 'cred' => $credentials['primary']];
        }

        if (empty($attempts)) {
            throw new \Exception('Kredensial Accurate antar toko tidak ditemukan.');
        }

        $lastError = null;
        foreach ($attempts as $attempt) {
            $apiToken = $attempt['cred']['accurate_api_token'] ?? null;
            $signatureSecret = $attempt['cred']['accurate_signature_secret'] ?? null;
            $customerId = $attempt['cred']['customer_id'] ?? null;
            if (!$apiToken || !$signatureSecret) {
                continue;
            }

            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            try {
                $response = Http::timeout(30)->withoutVerifying()->withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'X-Api-Signature' => $signature,
                    'X-Api-Timestamp' => $timestamp,
                ])->get($this->buildApiUrl($branch, 'delivery-order/detail.do'), [
                            'number' => $doNumber,
                        ]);

                if ($response->successful() && isset($response->json()['d'])) {
                    $detailCount = count($response->json('d.detailItem') ?? []);
                    Log::info('Berhasil ambil DO detail mode antar_toko', [
                        'number' => $doNumber,
                        'credential_source' => $attempt['source'],
                        'credential_customer_id' => $customerId,
                        'detail_item_count' => $detailCount,
                    ]);

                    return [
                        'data' => $response->json(),
                        'credential_source' => $attempt['source'],
                        'credential_customer_id' => $customerId,
                    ];
                }

                $lastError = 'HTTP ' . $response->status() . ' - ' . $response->body();
                Log::warning('Gagal ambil DO detail, mencoba kredensial berikutnya', [
                    'number' => $doNumber,
                    'credential_source' => $attempt['source'],
                    'credential_customer_id' => $customerId,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('Exception ambil DO detail, mencoba kredensial berikutnya', [
                    'number' => $doNumber,
                    'credential_source' => $attempt['source'],
                    'credential_customer_id' => $customerId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        throw new \Exception('Gagal mengambil detail Delivery Order. ' . ($lastError ? ('Detail: ' . $lastError) : ''));
    }

    /**
     * Ambil list DO untuk dropdown mode antar_toko.
     * Prioritas: kredensial customer berbeda (secondary), lalu cabang aktif (primary).
     */
    private function fetchInterStoreDeliveryOrdersForCreate(Branch $branch): array
    {
        $partner = $this->interStorePartnerNames($branch);

        $credentials = $this->getInterStoreCredentials($branch);
        $attempts = [];

        if (!empty($credentials['secondary'])) {
            $attempts[] = ['source' => 'secondary', 'cred' => $credentials['secondary']];
        }
        if (!empty($credentials['primary'])) {
            $attempts[] = ['source' => 'primary', 'cred' => $credentials['primary']];
        }

        $deliveryOrders = [];
        $lastError = null;
        foreach ($attempts as $attempt) {
            $apiToken = $attempt['cred']['accurate_api_token'] ?? null;
            $signatureSecret = $attempt['cred']['accurate_signature_secret'] ?? null;
            $credentialCustomerId = $attempt['cred']['customer_id'] ?? null;
            if (!$apiToken || !$signatureSecret) {
                continue;
            }

            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            Log::info('PB create - request DO list antar_toko', [
                'active_branch_id' => session('active_branch'),
                'active_customer_id' => $branch->customer_id,
                'credential_source' => $attempt['source'],
                'credential_customer_id' => $credentialCustomerId,
            ]);

            try {
                $doResponse = Http::timeout(30)->withoutVerifying()->withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'X-Api-Signature' => $signature,
                    'X-Api-Timestamp' => $timestamp,
                ])->get($this->buildApiUrl($branch, 'delivery-order/list.do'), [
                            'sp.page' => 1,
                            'sp.pageSize' => 200,
                            'fields' => 'number,transDate,customer,status',
                        ]);

                $rowCount = (int) data_get($doResponse->json(), 'sp.rowCount', 0);
                Log::info('PB create - response DO list antar_toko', [
                    'credential_source' => $attempt['source'],
                    'credential_customer_id' => $credentialCustomerId,
                    'status' => $doResponse->status(),
                    'row_count' => $rowCount,
                ]);

                if (!$doResponse->successful()) {
                    $lastError = 'HTTP ' . $doResponse->status() . ' - ' . $doResponse->body();
                    continue;
                }

                $rows = $doResponse->json('d') ?? [];
                if (!is_array($rows) || empty($rows)) {
                    Log::warning('PB create - DO list kosong untuk kredensial ini', [
                        'credential_source' => $attempt['source'],
                        'credential_customer_id' => $credentialCustomerId,
                    ]);
                    continue;
                }

                foreach ($rows as $row) {
                    $customerName = data_get($row, 'customer.name');
                    if ($partner !== null && !$this->interStoreLabelMatches($customerName, $partner['do_customer'])) {
                        continue;
                    }

                    $deliveryOrders[] = [
                        'number' => $row['number'] ?? '',
                        'transDate' => $row['transDate'] ?? '',
                        'status' => $row['status'] ?? '',
                        'customer_name' => $customerName,
                    ];
                }

                Log::info('PB create - DO list terisi dari kredensial', [
                    'credential_source' => $attempt['source'],
                    'credential_customer_id' => $credentialCustomerId,
                    'total_orders' => count($deliveryOrders),
                ]);
                break;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('PB create - exception ambil DO list antar_toko', [
                    'credential_source' => $attempt['source'],
                    'credential_customer_id' => $credentialCustomerId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (empty($deliveryOrders)) {
            Log::warning('PB create - semua percobaan DO list antar_toko kosong/gagal', [
                'active_branch_id' => session('active_branch'),
                'active_customer_id' => $branch->customer_id,
                'last_error' => $lastError,
            ]);
        }

        return $deliveryOrders;
    }

    public function index(Request $request)
    {
        // Validasi cabang aktif
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        // Validasi kredensial Accurate
        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        // Cache key yang unik
        $cacheKey = 'accurate_penerimaan_barang_list_' . $activeBranchId;
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache penerimaan barang dihapus karena force_refresh');
        }

        $errorMessage = null;

        // Periksa apakah cache sudah ada
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $penerimaanBarang = $cachedData['penerimaanBarang'] ?? [];
            $detailPOs = $cachedData['detailPOs'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data penerimaan barang diambil dari cache');
            return view('penerimaan_barang.index', compact('penerimaanBarang', 'detailPOs', 'errorMessage'));
        }

        // Data penerimaan barang per cabang (berdasarkan kode_customer)
        $penerimaanBarang = PenerimaanBarang::with('packingLists')
            ->where('kode_customer', $branch->customer_id)
            ->get();
        $detailPOs = [];
        $apiSuccess = false;
        $hasApiError = false;

        if ($penerimaanBarang->isNotEmpty()) {
            try {
                // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
                $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
                $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
                $timestamp = Carbon::now()->toIso8601String();
                $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

                // Fetch PO details in batches untuk efisiensi
                $detailsResult = $this->fetchPurchaseOrderDetailsInBatches($penerimaanBarang, $branch, $apiToken, $signature, $timestamp);
                $detailPOs = $detailsResult['details']; // Data final

                // Cek jika ada error dari proses fetch detail
                if ($detailsResult['has_error']) {
                    $hasApiError = true;
                }

                $apiSuccess = true;
                Log::info('Data penerimaan barang dari API berhasil diambil');
            } catch (\Exception $e) {
                // Log error API
                Log::error('Error fetching data from API: ' . $e->getMessage());
                $hasApiError = true;
            }
        } else {
            $apiSuccess = true; // Tidak ada data untuk diproses, anggap sukses
        }

        // Set error message berdasarkan kondisi
        if ($hasApiError) {
            $errorMessage = 'Gagal memuat detail data dari server Accurate. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi dengan menekan tombol "Refresh Data".';
        }

        // Jika API gagal dan tidak ada data, coba gunakan cache sebagai fallback
        if (!$apiSuccess && empty($detailPOs)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $penerimaanBarang = $cachedData['penerimaanBarang'] ?? $penerimaanBarang;
                $detailPOs = $cachedData['detailPOs'] ?? [];
                if (is_null($errorMessage))
                    $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info('Data penerimaan barang diambil dari cache karena API error');
            } else {
                if (is_null($errorMessage))
                    $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache tersedia.';
                Log::warning('Tidak ada cache tersedia, menampilkan data kosong');
            }
        }

        // Simpan data ke cache
        $dataToCache = [
            'penerimaanBarang' => $penerimaanBarang,
            'detailPOs' => $detailPOs,
            'errorMessage' => $errorMessage
        ];

        Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
        Log::info('Data penerimaan barang disimpan ke cache');

        return view('penerimaan_barang.index', compact('penerimaanBarang', 'detailPOs', 'errorMessage'));
    }

    /**
     * Fetch purchase order details in batches untuk mengoptimalkan performa
     */
    private function fetchPurchaseOrderDetailsInBatches($penerimaanBarang, $branch, $apiToken, $signature, $timestamp, $batchSize = 5)
    {
        $detailPOs = [];
        $batches = array_chunk($penerimaanBarang->toArray(), $batchSize);
        $hasApiError = false; // Flag error untuk fungsi ini

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client(['verify' => false]);

            foreach ($batch as $item) {
                if (!$item['no_po']) {
                    continue; // skip jika tidak ada no_po
                }

                $promises[$item['no_po']] = $client->getAsync($this->buildApiUrl($branch, 'purchase-order/detail.do'), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ],
                    'query' => [
                        'number' => $item['no_po']
                    ]
                ]);
            }

            if (empty($promises))
                continue;

            // Jalankan batch promise secara paralel
            $results = Utils::settle($promises)->wait();

            // Proses hasil dari setiap promise
            foreach ($results as $noPo => $result) {
                if ($result['state'] === 'fulfilled') {
                    $response = json_decode($result['value']->getBody(), true);
                    if (isset($response['d']['vendor']['name']) && isset($response['d']['status'])) {
                        $detailPOs[$noPo] = [
                            'vendor_name' => $response['d']['vendor']['name'],
                            'status' => $response['d']['status'],
                            'description' => $response['d']['description'],
                        ];
                    } else {
                        $detailPOs[$noPo] = [
                            'vendor_name' => null,
                            'status' => null,
                            'description' => null
                        ];
                    }
                    Log::info("PO detail fetched for: {$noPo}");
                } else {
                    $reason = $result['reason'];
                    Log::error("Gagal mengambil detail PO untuk {$noPo}: " . $reason->getMessage());

                    // Check if it's a rate limiting error
                    if ($reason instanceof \GuzzleHttp\Exception\ClientException && $reason->getResponse()->getStatusCode() == 429) {
                        $hasApiError = true;
                    }

                    $detailPOs[$noPo] = [
                        'vendor_name' => null,
                        'status' => null,
                        'description' => null
                    ];
                }
            }

            // Tambahkan delay kecil antara batch untuk menghindari rate limiting
            usleep(200000); // 200ms
        }

        return [
            'details' => $detailPOs,
            'has_error' => $hasApiError
        ];
    }

    /**
     * Get purchase orders data from Accurate API with caching and parallel processing
     */
    private function getPurchaseOrdersFromAccurate()
    {
        // Validasi cabang aktif
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            throw new \Exception('Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            throw new \Exception('Cabang tidak valid.');
        }

        // Validasi kredensial Accurate
        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            throw new \Exception('Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        try {
            // Ambil semua purchase orders dengan pagination handling
            $purchaseOrders = $this->fetchAllPurchaseOrders($branch, $apiToken, $signature, $timestamp);

            return $purchaseOrders;
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching purchase orders from Accurate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Fetch all purchase orders with parallel processing dan pagination handling
     */
    private function fetchAllPurchaseOrders($branch, $apiToken, $signature, $timestamp)
    {
        $poApiUrl = $this->buildApiUrl($branch, 'purchase-order/list.do');
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
            'fields' => 'number,transDate,vendor,status',
        ];

        $firstPageResponse = Http::timeout(30)->withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($poApiUrl, $data);

        $allPurchaseOrders = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allPurchaseOrders = $responseData['d'];

                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);

                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client(['verify' => false]);

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $promises[$page] = $client->getAsync($poApiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ],
                            'query' => [
                                'sp.page' => $page,
                                'sp.pageSize' => 20,
                                'fields' => 'number,transDate,vendor,status',
                            ]
                        ]);
                    }

                    $results = Utils::settle($promises)->wait();

                    foreach ($results as $page => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $pageResponse = json_decode($result['value']->getBody(), true);
                            if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                $allPurchaseOrders = array_merge($allPurchaseOrders, $pageResponse['d']);
                            }
                        } else {
                            Log::error("Failed to fetch purchase orders page {$page}: " . $result['reason']);
                        }
                    }
                }
            }
        } else {
            Log::error('Failed to fetch purchase orders from Accurate API', [
                'status' => $firstPageResponse->status(),
                'body' => $firstPageResponse->body(),
            ]);
            return [];
        }

        $purchase_orders = [];
        foreach ($allPurchaseOrders as $po) {
            $numberPo = $po['number'] ?? '';
            $poStatus = strtoupper(trim($po['status'] ?? ''));

            if ($poStatus !== 'ONPROCESS' && $poStatus !== 'WAITING') {
                continue;
            }

            $purchase_orders[] = [
                'number_po' => $numberPo,
                'date_po' => $po['transDate'] ?? '',
                'vendor_name' => data_get($po, 'vendor.name'),
            ];
        }

        Log::info('Successfully fetched all purchase orders from Accurate', [
            'total_count' => count($allPurchaseOrders),
            'filtered_count' => count($purchase_orders),
            'filter' => 'ONPROCESS & WAITING',
        ]);

        return $purchase_orders;
    }

    public function create(Request $request)
    {
        // Pastikan cabang aktif valid sebelum memanggil API
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        // Get data directly from API (sudah menggunakan kredensial dari cabang di dalam fungsi)
        $purchase_order = $this->getPurchaseOrdersFromAccurate();
        $partnerFilter = $this->interStorePartnerNames($branch);
        $purchase_order_antar_toko = $purchase_order;
        if ($partnerFilter !== null) {
            $purchase_order_antar_toko = array_values(array_filter($purchase_order, function ($po) use ($partnerFilter) {
                return $this->interStoreLabelMatches($po['vendor_name'] ?? null, $partnerFilter['po_vendor']);
            }));
        }

        $delivery_orders = $this->fetchInterStoreDeliveryOrdersForCreate($branch);

        // Generate NPB & No Terima (preview untuk form)
        $npb = PenerimaanBarang::generateNpb($branch->customer_id);
        $noTerima = PenerimaanBarang::generateNoTerima($branch->customer_id);
        $kodeCustomer = $branch->customer_id;

        // Packing list dengan status approved untuk dipilih
        $packingLists = PackingList::where('kode_customer', $branch->customer_id)
            ->where('status', PackingList::STATUS_APPROVED)
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('penerimaan_barang.create', compact(
            'purchase_order',
            'purchase_order_antar_toko',
            'delivery_orders',
            'npb',
            'noTerima',
            'packingLists',
            'kodeCustomer'
        ));
    }

    public function getDetailPo(Request $request)
    {
        $mode = $request->input('mode', 'packing_list');

        $rules = [
            'no_po' => 'required|string',
            'npb' => 'required|string',
            'mode' => 'sometimes|in:packing_list,non_packing_list,antar_toko',
        ];

        if ($mode === 'packing_list') {
            $rules['packing_list_ids'] = 'required|array';
            $rules['packing_list_ids.*'] = 'integer|exists:packing_list,id';
        } elseif ($mode === 'antar_toko') {
            $rules['no_do'] = 'required|string';
        }

        $validated = $request->validate($rules);

        $activeBranchId = session('active_branch');
        $branch = Branch::find($activeBranchId);

        $activeCred = UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'));
        $hasActiveCred = !empty($activeCred['accurate_api_token']) && !empty($activeCred['accurate_signature_secret']);
        $hasInterStoreCred = false;
        if ($mode === 'antar_toko' && $branch && Auth::check()) {
            $interStoreCred = $this->getInterStoreCredentials($branch);
            $hasInterStoreCred = !empty($interStoreCred['primary']) || !empty($interStoreCred['secondary']);
        }

        if (
            !$branch
            || !Auth::check()
            || ($mode !== 'antar_toko' && !$hasActiveCred)
            || ($mode === 'antar_toko' && !$hasInterStoreCred)
        ) {
            return response()->json(['error' => true, 'message' => 'Konfigurasi cabang tidak valid.'], 400);
        }

        try {
            if ($mode === 'antar_toko') {
                $doNumber = (string) ($validated['no_do'] ?? '');
                $doResult = $this->fetchDeliveryOrderDetailWithFallback($branch, $doNumber);
                $doData = $doResult['data']['d'] ?? [];
                $doDetails = $doData['detailItem'] ?? [];

                // Untuk mode antar_toko, field "Terima Dari" wajib mengikuti PO
                // agar konsisten dengan mode packing_list & non_packing_list.
                $vendorNo = null;
                $vendorName = null;
                $poVendorResponse = null;
                if (!empty($activeCred['accurate_api_token']) && !empty($activeCred['accurate_signature_secret'])) {
                    $tsPoVendor = Carbon::now()->toIso8601String();
                    $sigPoVendor = hash_hmac('sha256', $tsPoVendor, $activeCred['accurate_signature_secret']);

                    $poVendorResponse = Http::timeout(30)->withoutVerifying()->withHeaders([
                        'Authorization' => 'Bearer ' . $activeCred['accurate_api_token'],
                        'X-Api-Signature' => $sigPoVendor,
                        'X-Api-Timestamp' => $tsPoVendor,
                    ])->get($this->buildApiUrl($branch, 'purchase-order/detail.do'), [
                                'number' => $validated['no_po'],
                            ]);

                    if ($poVendorResponse->successful()) {
                        $vendorNo = data_get($poVendorResponse->json(), 'd.vendor.vendorNo');
                        $vendorName = data_get($poVendorResponse->json(), 'd.vendor.name');
                    } else {
                        Log::warning('Mode antar_toko: gagal mengambil vendor dari PO detail', [
                            'no_po' => $validated['no_po'],
                            'status' => $poVendorResponse->status(),
                        ]);
                    }
                }

                $poVendorNameForRule = ($poVendorResponse && $poVendorResponse->successful())
                    ? data_get($poVendorResponse->json(), 'd.vendor.name')
                    : null;
                $doCustomerNameForRule = data_get($doData, 'customer.name');
                $this->assertInterStorePartnersForBranch($branch, $poVendorNameForRule, $doCustomerNameForRule);

                // Fallback terakhir agar tidak null jika PO gagal diambil
                $vendorNo = $vendorNo
                    ?? data_get($doData, 'vendor.vendorNo')
                    ?? data_get($doData, 'customer.customerNo')
                    ?? data_get($doData, 'customer.no');
                $vendorName = $vendorName
                    ?? data_get($doData, 'vendor.name')
                    ?? data_get($doData, 'customer.name');
                $vendorData = [
                    'vendorNo' => $vendorNo,
                    'vendorName' => $vendorName,
                ];

                $barangList = [];
                foreach ($doDetails as $detail) {
                    $itemNo = $detail['item']['no'] ?? null;
                    $itemName = $detail['item']['name'] ?? null;
                    if (!$itemNo) {
                        continue;
                    }

                    $qty = (float) ($detail['quantity'] ?? 0);
                    $detailSerial = $detail['detailSerialNumber'] ?? [];
                    $firstBarcode = null;
                    $expectedSerials = [];
                    if (is_array($detailSerial) && !empty($detailSerial)) {
                        $firstBarcode = data_get($detailSerial[0], 'serialNumber.number');
                        foreach ($detailSerial as $sn) {
                            $snNumber = trim((string) data_get($sn, 'serialNumber.number', ''));
                            $snQty = (float) data_get($sn, 'quantity', 0);
                            if ($snNumber !== '') {
                                $expectedSerials[] = [
                                    'barcode' => $snNumber,
                                    'quantity' => $snQty,
                                ];
                            }
                        }
                    }
                    $unitName = $detail['itemUnit']['name'] ?? ($detail['item']['unit1']['name'] ?? 'METER');
                    $unitPrice = (float) ($detail['unitPrice'] ?? 0);

                    $barangList[] = [
                        'nama_barang' => $itemName,
                        'kode_barang' => $itemNo,
                        'barcode' => $firstBarcode,
                        'kuantitas' => $qty,
                        'uom' => $unitName,
                        'unit_price' => $unitPrice,
                        'expected_serial_numbers' => $expectedSerials,
                    ];
                }

                if (empty($barangList)) {
                    throw new \Exception('Detail Delivery Order tidak memiliki item.');
                }

                return response()->json([
                    'barang' => $barangList,
                    'vendor' => $vendorData,
                    'mode' => 'antar_toko',
                    'kode_customer' => $branch->customer_id,
                ]);
            }

            // 1. Ambil vendor + detail item dari Accurate PO
            $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $vendorData = null;
            $accurateItems = [];

            $poResponse = Http::timeout(30)->withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($this->buildApiUrl($branch, 'purchase-order/detail.do'), [
                        'number' => $validated['no_po'],
                    ]);

            if ($poResponse->successful()) {
                $resData = $poResponse->json();
                if (isset($resData['d']['vendor'])) {
                    $vendorData = [
                        'vendorNo' => $resData['d']['vendor']['vendorNo'] ?? null,
                        'vendorName' => $resData['d']['vendor']['name'] ?? null,
                    ];
                }
                foreach ($resData['d']['detailItem'] ?? [] as $detail) {
                    $itemNo = $detail['item']['no'] ?? null;
                    $itemName = $detail['item']['name'] ?? null;
                    $uomAcc = $detail['itemUnit']['name'] ?? ($detail['item']['unit1']['name'] ?? null);
                    if ($itemNo) {
                        $accurateItems[] = [
                            'no' => $itemNo,
                            'name' => $itemName,
                            'no_numeric' => preg_replace('/\D/', '', $itemNo),
                            'uom' => $this->normalizeUom($uomAcc),
                        ];
                    }
                }
            }

            Log::info('Accurate items for PO matching', [
                'no_po' => $validated['no_po'],
                'mode' => $mode,
                'items' => collect($accurateItems)->map(fn($i) => $i['no'] . ' => ' . $i['name'])->toArray(),
            ]);

            // Mode Non Packing List: filter berdasarkan charField1 item atau vendor category
            if ($mode === 'non_packing_list') {
                $rawDetails = $resData['d']['detailItem'] ?? [];
                $allowedCharField1 = ['JARIK', 'SARUNG', 'PRINTING', 'KNITTING', 'DENIM', 'DYEING'];

                // Cek apakah ada item dengan charField1 SARUNG/JARIK
                $hasMatchingCharField = false;
                foreach ($rawDetails as $detail) {
                    $cf1 = strtoupper(trim($detail['item']['charField1'] ?? ''));
                    if (in_array($cf1, $allowedCharField1)) {
                        $hasMatchingCharField = true;
                        break;
                    }
                }

                // Jika tidak ada item yang match charField1, cek vendor category
                $vendorCategoryAllowed = false;
                if (!$hasMatchingCharField && $vendorData && $vendorData['vendorNo']) {
                    $vendorDetailResponse = Http::timeout(30)->withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($this->buildApiUrl($branch, 'vendor/detail.do'), [
                                'vendorNo' => $vendorData['vendorNo'],
                            ]);

                    if ($vendorDetailResponse->successful()) {
                        $vendorResData = $vendorDetailResponse->json();
                        $categoryName = strtoupper(trim($vendorResData['d']['category']['name'] ?? ''));
                        $allowedCategories = ['GRUP', 'LOKAL'];
                        $vendorCategoryAllowed = in_array($categoryName, $allowedCategories);

                        Log::info('Vendor category check for Non PL', [
                            'vendorNo' => $vendorData['vendorNo'],
                            'category_name' => $categoryName,
                            'allowed' => $vendorCategoryAllowed,
                        ]);
                    }
                }

                // Filter item: charField1 match ATAU vendor category match (semua item)
                $barangList = [];
                foreach ($rawDetails as $detail) {
                    $itemNo = $detail['item']['no'] ?? null;
                    $itemName = $detail['item']['name'] ?? null;
                    $cf1 = strtoupper(trim($detail['item']['charField1'] ?? ''));
                    $qty = (float) ($detail['quantity'] ?? 0);
                    $unitPrice = (float) ($detail['unitPrice'] ?? 0);
                    $uomAcc = $detail['itemUnit']['name'] ?? ($detail['item']['unit1']['name'] ?? 'METER');

                    if (!$itemNo)
                        continue;

                    $itemAllowed = in_array($cf1, $allowedCharField1) || $vendorCategoryAllowed;
                    if (!$itemAllowed)
                        continue;

                    $nameWithIndentStrip = $detail['item']['nameWithIndentStrip'] ?? null;

                    $barangList[] = [
                        // Data untuk tampilan tabel/non-PL header
                        'nama_barang' => $itemName,
                        'name' => $itemName,
                        'kode_barang' => $itemNo,
                        'kuantitas' => $qty,
                        'unit_price' => $unitPrice,
                        'uom' => $uomAcc,
                        // Data untuk format print label (berdasarkan charField1)
                        'charField1' => $cf1,
                        'charField2' => trim((string) ($detail['item']['charField2'] ?? '')),
                        'charField4' => trim((string) ($detail['item']['charField4'] ?? '')),
                        'charField5' => trim((string) ($detail['item']['charField5'] ?? '')),
                        'charField6' => trim((string) ($detail['item']['charField6'] ?? '')),
                        'nameWithIndentStrip' => is_string($nameWithIndentStrip)
                            ? $nameWithIndentStrip
                            : (string) ($nameWithIndentStrip ?? $itemName ?? ''),
                    ];
                }

                if (empty($barangList)) {
                    throw new \Exception('Tidak ada item yang memenuhi syarat (charField1 atau vendor category GRUP/LOKAL).');
                }

                return response()->json([
                    'barang' => $barangList,
                    'vendor' => $vendorData,
                    'mode' => 'non_packing_list',
                    'kode_customer' => $branch->customer_id,
                ]);
            }

            // 2. Ambil barcode dari packing list yang dipilih (mode packing_list)
            $packingLists = PackingList::whereIn('id', $validated['packing_list_ids'])
                ->where('kode_customer', $branch->customer_id)
                ->where('status', PackingList::STATUS_APPROVED)
                ->get();

            if ($packingLists->isEmpty()) {
                throw new \Exception('Packing list tidak ditemukan atau status belum approved.');
            }

            $nplList = $packingLists->pluck('npl')->toArray();
            $barcodes = Barcode::whereIn('no_packing_list', $nplList)
                ->where('kode_customer', $branch->customer_id)
                ->whereIn('status', [Barcode::STATUS_APPROVED, Barcode::STATUS_UPLOADED])
                ->orderBy('longtext', 'asc')
                ->get();

            if ($barcodes->isEmpty()) {
                throw new \Exception('Tidak ada barcode di packing list yang dipilih.');
            }

            // 3. Map barcode data per item (tanpa merge)
            $barangList = [];

            foreach ($barcodes as $barcode) {
                $length = (float) str_replace(',', '.', $barcode->length ?? 0);
                $barcodeUom = $this->normalizeUom($barcode->uom ?? '');

                $rawMaterialCode = preg_replace('/\D/', '', $barcode->material_code ?? '');
                $materialCode12 = $rawMaterialCode !== '' ? substr($rawMaterialCode, -12) : '';

                $kodeWarna = trim($barcode->kode_warna ?? '');
                if ($materialCode12 && $kodeWarna) {
                    $kodeBarang = $materialCode12 . ' - ' . $kodeWarna;
                } elseif ($materialCode12) {
                    $kodeBarang = $materialCode12;
                } else {
                    $kodeBarang = $barcode->kode_barang;
                }

                // Cocokkan ke baris PO Accurate (sama seperti mapItemDetailsFromPackingLists):
                // ambil nama + UOM target agar kuantitas dikonversi ke unit PO.
                $matchedAccItem = null;

                if (!empty($accurateItems)) {
                    if ($materialCode12) {
                        foreach ($accurateItems as $item) {
                            if ($item['no'] === $materialCode12) {
                                $matchedAccItem = $item;
                                break;
                            }
                        }
                    }
                    if (!$matchedAccItem && $materialCode12) {
                        foreach ($accurateItems as $item) {
                            $accNumeric = $item['no_numeric'];
                            if ($accNumeric !== '' && substr($accNumeric, -12) === $materialCode12) {
                                $matchedAccItem = $item;
                                break;
                            }
                        }
                    }
                    if (!$matchedAccItem && $materialCode12) {
                        foreach ($accurateItems as $item) {
                            if (str_contains($item['no'], $materialCode12) || str_contains($materialCode12, $item['no'])) {
                                $matchedAccItem = $item;
                                break;
                            }
                        }
                    }
                    if (!$matchedAccItem) {
                        $barcodeNama = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace('KC', '', $barcode->nama ?? '')));
                        foreach ($accurateItems as $item) {
                            $accNama = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $item['name'] ?? ''));
                            if ($accNama !== '' && $accNama === $barcodeNama) {
                                $matchedAccItem = $item;
                                break;
                            }
                        }
                    }
                }

                $namaBarang = $matchedAccItem['name'] ?? null;
                $targetUom = $matchedAccItem
                    ? $this->normalizeUom($matchedAccItem['uom'] ?? '')
                    : '';

                if (!$namaBarang) {
                    $namaBarang = $barcode->keterangan;
                }

                // Kuantitas: panjang barcode (base_uom / uom lokal) dikonversi ke unit baris PO Accurate bila M/YD.
                $effectiveLength = $length;
                if ($targetUom === 'YD' || $targetUom === 'M') {
                    $effectiveLength = $this->convertLengthByUom($length, $targetUom, $barcodeUom);
                }
                $kuantitas = round($effectiveLength, 2);

                $barangList[] = [
                    'nama_barang' => $namaBarang,
                    'kode_barang' => $kodeBarang,
                    'barcode' => $barcode->barcode,
                    'kuantitas' => $kuantitas,
                ];
            }

            return response()->json([
                'barang' => $barangList,
                'vendor' => $vendorData,
                'mode' => 'packing_list',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Ambil item detail dari packing list (barcode) + match dengan PO Accurate.
     */
    public function mapItemDetailsFromPackingLists(
        $noPo,
        $npb,
        array $packingListIds,
        $updateIdPb = false,
        $includeVendor = false,
        string $packingListStatus = PackingList::STATUS_APPROVED
    ) {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            throw new \Exception('Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            throw new \Exception('Cabang tidak valid.');
        }

        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            throw new \Exception('Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        $itemDetails = [];
        $vendorData = null;

        // Ambil detail PO dari Accurate
        $detailPurchaseOrderResponse = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($this->buildApiUrl($branch, 'purchase-order/detail.do'), ['number' => $noPo]);

        if ($detailPurchaseOrderResponse->successful()) {
            $resData = $detailPurchaseOrderResponse->json();
            foreach ($resData['d']['detailItem'] ?? [] as $detail) {
                $uomAcc = $detail['itemUnit']['name'] ?? ($detail['item']['unit1']['name'] ?? null);
                $itemDetails[] = [
                    'nama_barang' => $detail['item']['name'] ?? null,
                    'kode_barang' => $detail['item']['no'] ?? null,
                    'unit' => $detail['item']['unit1']['name'] ?? null,
                    'uom_acc' => $this->normalizeUom($uomAcc),
                    'panjang_total' => 0,
                    'availableToSell' => 0,
                    'unit_price' => 0,
                ];
            }
            if ($includeVendor && isset($resData['d']['vendor'])) {
                $vendorData = ['vendorNo' => $resData['d']['vendor']['vendorNo'] ?? null];
            }
        }

        // Ambil packing list sesuai status yang diminta dan barcodes-nya
        $packingLists = PackingList::whereIn('id', $packingListIds)
            ->where('kode_customer', $branch->customer_id)
            ->where('status', $packingListStatus)
            ->get();

        if (empty($packingListIds)) {
            // Jika tidak ada packing list, coba ambil detail aktual dari Penerimaan Barang (receive-item) di Accurate
            $detailReceiveItemResponse = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($this->buildApiUrl($branch, 'receive-item/detail.do'), ['number' => $npb]);

            if ($detailReceiveItemResponse->successful() && isset($detailReceiveItemResponse->json()['d'])) {
                $resData = $detailReceiveItemResponse->json();
                $receiveItemDetails = [];

                foreach ($resData['d']['detailItem'] ?? [] as $detail) {
                    $serialNumbers = [];
                    foreach ($detail['detailSerialNumber'] ?? [] as $sn) {
                        $serialNumbers[] = [
                            'serialNumberNo' => $sn['serialNumber']['number'] ?? '',
                            'quantity' => $sn['quantity'] ?? 0,
                        ];
                    }

                    $receiveItemDetails[] = [
                        'nama_barang' => $detail['item']['name'] ?? null,
                        'kode_barang' => $detail['item']['no'] ?? null,
                        'unit' => $detail['item']['unit1']['name'] ?? null,
                        'panjang_total' => $detail['quantity'] ?? 0,
                        'karyawan' => '-',
                        'grade' => '-',
                        'availableToSell' => 0,
                        'unit_price' => 0,
                        'keterangan' => '-',
                        'serial_numbers' => $serialNumbers
                    ];
                }

                // Jika berhasil mendapat detail item, kembalikan ini alih-alih fallback ke kuantitas 0
                if (!empty($receiveItemDetails)) {
                    return [
                        'items' => $receiveItemDetails,
                        'packingListIds' => [],
                        'vendorData' => $vendorData
                    ];
                }
            }

            // Fallback kembali ke PO dengan kuantitas 0 jika API gagal
            return [
                'items' => $itemDetails,
                'packingListIds' => [],
                'vendorData' => $vendorData
            ];
        }

        if ($packingLists->isEmpty()) {
            throw new \Exception('Packing list tidak ditemukan atau status tidak sesuai.');
        }

        $nplList = $packingLists->pluck('npl')->toArray();
        $barcodes = Barcode::whereIn('no_packing_list', $nplList)
            ->where('kode_customer', $branch->customer_id)
            ->where(function ($q) use ($npb) {
                $q->whereIn('status', [Barcode::STATUS_APPROVED, Barcode::STATUS_UPLOADED])
                    ->orWhere('id_pb', $npb);
            })
            ->get();

        if ($barcodes->isEmpty()) {
            throw new \Exception('Tidak ada barcode di packing list yang dipilih.');
        }

        $matchedItems = [];
        $matchedBarcodeIds = [];

        // Siapkan data Accurate items untuk multi-strategy matching
        $accurateItemsForMatch = [];
        foreach ($itemDetails as $idx => $item) {
            $itemNo = $item['kode_barang'] ?? '';
            $accurateItemsForMatch[$idx] = [
                'no' => $itemNo,
                'name' => $item['nama_barang'] ?? '',
                'no_numeric' => preg_replace('/\D/', '', $itemNo),
                'uom_acc' => $item['uom_acc'] ?? '',
            ];
        }

        foreach ($barcodes as $barcode) {
            $rawMc = preg_replace('/\D/', '', $barcode->material_code ?? '');
            $mc12 = $rawMc !== '' ? substr($rawMc, -12) : '';

            // Multi-strategy matching (sama seperti getDetailPo)
            $matchedIdx = null;

            // Strategi 1: Exact match kode_barang Accurate === materialCode12
            if ($matchedIdx === null && $mc12) {
                foreach ($accurateItemsForMatch as $idx => $acc) {
                    if ($acc['no'] === $mc12) {
                        $matchedIdx = $idx;
                        break;
                    }
                }
            }

            // Strategi 2: Numeric suffix match (12 digit terakhir)
            if ($matchedIdx === null && $mc12) {
                foreach ($accurateItemsForMatch as $idx => $acc) {
                    if ($acc['no_numeric'] !== '' && substr($acc['no_numeric'], -12) === $mc12) {
                        $matchedIdx = $idx;
                        break;
                    }
                }
            }

            // Strategi 3: Contains match
            if ($matchedIdx === null && $mc12) {
                foreach ($accurateItemsForMatch as $idx => $acc) {
                    if (str_contains($acc['no'], $mc12) || str_contains($mc12, $acc['no'])) {
                        $matchedIdx = $idx;
                        break;
                    }
                }
            }

            // Strategi 4: Name-based match
            if ($matchedIdx === null) {
                $namaBarcode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace('KC', '', $barcode->nama ?? '')));
                foreach ($accurateItemsForMatch as $idx => $acc) {
                    $accNama = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $acc['name']));
                    if ($accNama !== '' && $accNama === $namaBarcode) {
                        $matchedIdx = $idx;
                        break;
                    }
                }
            }

            if ($matchedIdx === null) {
                Log::warning('Barcode tidak match dengan item Accurate', [
                    'barcode' => $barcode->barcode,
                    'material_code' => $barcode->material_code,
                    'nama' => $barcode->nama,
                ]);
                continue;
            }

            $item = $itemDetails[$matchedIdx];
            $targetUom = $accurateItemsForMatch[$matchedIdx]['uom_acc'] ?? ($item['uom_acc'] ?? '');

            if ($updateIdPb) {
                $barcode->update(['id_pb' => $npb]);
            }

            $matchedBarcodeIds[] = $barcode->id;

            $length = (float) str_replace(',', '.', $barcode->length ?? 0);
            $barcodeUom = $this->normalizeUom($barcode->uom ?? '');
            $effectiveLength = $length;
            if ($targetUom === 'YD' || $targetUom === 'M') {
                $effectiveLength = $this->convertLengthByUom($length, $targetUom, $barcodeUom);
            }
            $barcodeQty = round($effectiveLength, 2);

            $kw = trim($barcode->kode_warna ?? '');
            $itemNoPl = ($mc12 && $kw) ? $mc12 . ' - ' . $kw : ($mc12 ?: $barcode->kode_barang);

            $serialEntry = [
                'serialNumberNo' => $barcode->barcode,
                'quantity' => $barcodeQty,
            ];

            $existingItemIndex = array_search($item['nama_barang'], array_column($matchedItems, 'nama_barang'));

            if ($existingItemIndex !== false) {
                $matchedItems[$existingItemIndex]['panjang_total'] = bcadd(
                    (string) $matchedItems[$existingItemIndex]['panjang_total'],
                    (string) $barcodeQty,
                    2
                );
                $matchedItems[$existingItemIndex]['availableToSell'] = $matchedItems[$existingItemIndex]['panjang_total'];
                $matchedItems[$existingItemIndex]['unit_price'] += $barcode->harga_unit ?? 0;
                $matchedItems[$existingItemIndex]['serial_numbers'][] = $serialEntry;
                if (!isset($matchedItems[$existingItemIndex]['keterangan']) || empty($matchedItems[$existingItemIndex]['keterangan'])) {
                    $matchedItems[$existingItemIndex]['keterangan'] = ltrim($barcode->keterangan ?? '', '*');
                }
            } else {
                $newItem = $item;
                $newItem['panjang_total'] = $barcodeQty;
                $newItem['availableToSell'] = $newItem['panjang_total'];
                $newItem['unit_price'] = $barcode->harga_unit ?? 0;
                $newItem['keterangan'] = ltrim($barcode->keterangan ?? '', '*');
                $newItem['serial_numbers'] = [$serialEntry];
                $newItem['item_no_pl'] = $itemNoPl;
                $matchedItems[] = $newItem;
            }
        }

        return [
            'items' => $matchedItems,
            'vendor' => $vendorData,
            // Gunakan penamaan "barcodes" untuk logika baru,
            // tapi tetap sediakan "approvalStocks" untuk kompatibilitas pemanggil lama.
            'barcodes' => $barcodes,
            'approvalStocks' => $barcodes,
            'matchedBarcodeIds' => $matchedBarcodeIds,
        ];
    }

    /**
     * AJAX endpoint: reserve barcode baru untuk mode Non Packing List (tanpa insert ke DB).
     */
    public function generateBarcodeNonPL(Request $request)
    {
        $request->validate([
            'kode_customer' => 'required|string',
            'quantity' => 'required|numeric|min:0.001',
            'count' => 'sometimes|integer|min:1|max:50',
        ]);

        try {
            $count = (int) ($request->input('count', 1));
            $qty = (float) $request->quantity;

            $reserved = DB::transaction(function () use ($request, $count) {
                $kodeCustomer = (string) $request->kode_customer;
                $prefix = BarcodeNonPL::prefixForCustomer($kodeCustomer);

                // Gunakan tabel counter khusus agar tidak perlu insert placeholder ke barcode_non_p_l_s
                $row = DB::table('barcode_non_pl_counters')
                    ->where('kode_customer', $kodeCustomer)
                    ->lockForUpdate()
                    ->first();

                $lastSeq = 0;
                if ($row) {
                    $lastSeq = (int) ($row->last_seq ?? 0);
                } else {
                    DB::table('barcode_non_pl_counters')->insert([
                        'kode_customer' => $kodeCustomer,
                        'prefix' => $prefix,
                        'last_seq' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $start = $lastSeq + 1;
                $end = $lastSeq + $count;

                DB::table('barcode_non_pl_counters')
                    ->where('kode_customer', $kodeCustomer)
                    ->update([
                        'prefix' => $prefix,
                        'last_seq' => $end,
                        'updated_at' => now(),
                    ]);

                $barcodes = [];
                for ($seq = $start; $seq <= $end; $seq++) {
                    $barcodes[] = BarcodeNonPL::formatBarcode($kodeCustomer, $seq);
                }

                return [
                    'kode_customer' => $kodeCustomer,
                    'prefix' => $prefix,
                    'barcodes' => $barcodes,
                ];
            });

            $token = (string) Str::uuid();
            Cache::put(
                $this->nonPlReservationCacheKey($token),
                [
                    'kode_customer' => $reserved['kode_customer'],
                    'prefix' => $reserved['prefix'],
                    'barcodes' => $reserved['barcodes'],
                    'user_id' => Auth::id(),
                    'issued_at' => now()->toIso8601String(),
                ],
                self::NONPL_RESERVATION_TTL_SECONDS
            );

            return response()->json([
                'success' => true,
                'token' => $token,
                // kompatibilitas: frontend saat ini ambil field `barcode`
                'barcode' => $reserved['barcodes'][0] ?? null,
                'barcodes' => $reserved['barcodes'],
                'quantity' => $qty,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Endpoint: generate QR code untuk Non Packing List.
     * QR meng-encode teks hanya `barcode` (bukan qty), agar sesuai kebutuhan print.
     */
    public function qrcodeNonPl(Request $request)
    {
        $barcode = (string) $request->query('barcode', '');
        $size = (int) $request->query('size', 180);

        if ($barcode === '') {
            abort(400, 'Barcode is required.');
        }

        // Batasi ukuran agar tidak terlalu berat saat bulk print
        $size = max(120, min(500, $size));

        $cacheKey = 'qrcode_non_pl:' . md5($barcode . ':' . $size);

        $qrSvg = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($barcode, $size) {
            // Gunakan format SVG agar tidak butuh extension `imagick`.
            return QrCode::format('svg')->size($size)->generate($barcode);
        });

        return response($qrSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            // Cache browser juga untuk mempercepat bulk print
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Endpoint: print label Non PL (server-side QR + server-side PDF).
     *
     * Frontend mengirim payload:
     * - item: data charField1/2/4/5/6 + name/nameWithIndentStrip + no/kode_barang + itemUnitName/uom (opsional)
     * - labels: array berisi barcode + quantity (hasil input user)
     */
    public function printNonPlLabelsPdf(Request $request)
    {
        $validated = $request->validate([
            'item' => 'required|array',
            'item.charField1' => 'nullable|string',
            'item.charField2' => 'nullable|string',
            'item.charField4' => 'nullable|string',
            'item.charField5' => 'nullable|string',
            'item.charField6' => 'nullable|string',
            'item.name' => 'nullable|string',
            'item.nameWithIndentStrip' => 'nullable|string',
            'item.no' => 'nullable|string',
            'item.kode_barang' => 'nullable|string',
            'item.itemUnitName' => 'nullable|string',
            'item.uom' => 'nullable|string',
            'labels' => 'required|array|min:1|max:200',
            'labels.*.barcode' => 'required|string',
            'labels.*.quantity' => 'required|numeric|min:0.001',
        ]);

        $item = $validated['item'];
        $labels = $validated['labels'];
        $itemForLabel = $item;

        // Prioritaskan brand dari Accurate item/detail.do berdasarkan no item.
        $itemNo = trim((string) ($item['no'] ?? $item['kode_barang'] ?? ''));
        if ($itemNo !== '') {
            $activeBranchId = session('active_branch');
            $branch = Branch::find($activeBranchId);

            if ($branch && Auth::check() && (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) && (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
                try {
                    $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
                    $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
                    $timestamp = Carbon::now()->toIso8601String();
                    $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

                    $headers = [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ];

                    // Endpoint item/detail.do menerima body form: no=<itemNo>
                    $itemDetailResponse = Http::timeout(30)
                        ->withHeaders($headers)
                        ->asForm()
                        ->post($this->buildApiUrl($branch, 'item/detail.do'), [
                            'no' => $itemNo,
                        ]);

                    // Fallback jika endpoint dikonfigurasi sebagai query parameter (GET).
                    if (!$itemDetailResponse->successful()) {
                        $itemDetailResponse = Http::timeout(30)
                            ->withHeaders($headers)
                            ->get($this->buildApiUrl($branch, 'item/detail.do'), [
                                'no' => $itemNo,
                            ]);
                    }

                    if ($itemDetailResponse->successful()) {
                        $itemDetail = $itemDetailResponse->json();
                        $brandFromApi = trim((string) data_get($itemDetail, 'd.itemBrand.nameWithIndentStrip', ''));
                        if ($brandFromApi !== '') {
                            $itemForLabel['nameWithIndentStrip'] = $brandFromApi;
                        }
                    } else {
                        Log::warning('Gagal ambil brand dari item/detail.do untuk print label Non PL', [
                            'item_no' => $itemNo,
                            'status' => $itemDetailResponse->status(),
                            'body' => $itemDetailResponse->body(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Exception saat ambil brand dari item/detail.do untuk print label Non PL', [
                        'item_no' => $itemNo,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $type = strtoupper(trim((string) ($item['charField1'] ?? '')));

        $printLayouts = [
            'JARIK' => ['heightMm' => 25, 'qrSizePx' => 190],
            'SARUNG' => ['heightMm' => 25, 'qrSizePx' => 190],
            'PRINTING' => ['heightMm' => 25, 'qrSizePx' => 190],
            'KNITTING' => ['heightMm' => 25, 'qrSizePx' => 190],
            'DENIM' => ['heightMm' => 25, 'qrSizePx' => 190],
            'DYEING' => ['heightMm' => 25, 'qrSizePx' => 190],
            'DEFAULT' => ['heightMm' => 25, 'qrSizePx' => 190],
        ];

        $layout = $printLayouts[$type] ?? $printLayouts['DEFAULT'];

        $paperWidthMm = 65;
        $labelHeightMm = (float) $layout['heightMm'];
        // Tinggi kertas per label (jangan dikalikan), agar dompdf mem-break halaman otomatis.
        $paperHeightMm = $labelHeightMm;
        $mmToPoints = static fn(float $mm): float => $mm * 72 / 25.4;
        $paperWidthPt = $mmToPoints($paperWidthMm);
        $paperHeightPt = $mmToPoints($paperHeightMm);

        $token = (string) Str::uuid();

        $formatQty = function ($qty): string {
            $n = (float) $qty;
            if (abs($n - round($n)) < 1e-9) {
                return (string) (int) round($n);
            }
            return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
        };

        $buildRightLines = function (array $itemData, array $sn) use ($type, $formatQty): array {
            $qtyText = $formatQty($sn['quantity'] ?? 0);
            $unitText = trim((string) ($itemData['itemUnitName'] ?? $itemData['uom'] ?? ''));
            $name = (string) ($itemData['name'] ?? '');
            $brand = (string) ($itemData['nameWithIndentStrip'] ?? '');
            $cf2 = (string) ($itemData['charField2'] ?? '');
            $cf4 = (string) ($itemData['charField4'] ?? '');
            $cf5 = (string) ($itemData['charField5'] ?? '');
            $cf6 = (string) ($itemData['charField6'] ?? '');
            // Jadikan quantity + unit dalam satu baris agar tampil sejajar di label.
            $qtyUnitLine = trim($qtyText . ' ' . $unitText);
            $qtyAndUnit = $qtyUnitLine !== '' ? [$qtyUnitLine] : [];

            switch ($type) {
                case 'JARIK':
                    return array_merge([$brand, $cf6], $qtyAndUnit);
                case 'SARUNG':
                    return array_merge([$name], $qtyAndUnit);
                case 'PRINTING':
                    return array_merge([$brand, $cf6, $cf4, $cf5], $qtyAndUnit);
                case 'KNITTING':
                    return array_merge([$name, $cf4, $cf5], $qtyAndUnit);
                case 'DENIM':
                    return array_merge([$brand, $cf5], $qtyAndUnit);
                case 'DYEING':
                    return array_merge([$brand, $cf2, $cf4, $cf5], $qtyAndUnit);
                default:
                    return array_merge([$brand ?: $name], $qtyAndUnit);
            }
        };

        $labelsView = [];

        foreach ($labels as $sn) {
            $barcode = (string) ($sn['barcode'] ?? '');

            // Gunakan format SVG agar tidak butuh extension `imagick`.
            $qrSvg = QrCode::format('svg')->size((int) $layout['qrSizePx'])->generate($barcode);
            // Gunakan data URL agar dompdf bisa langsung render tanpa akses file lokal.
            $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

            $labelsView[] = [
                'barcode' => $barcode,
                'rightLines' => $buildRightLines($itemForLabel, $sn),
                'qrSrc' => $qrSrc,
            ];
        }

        $data = [
            'type' => $type,
            'layout' => $layout,
            'labels' => $labelsView,
            'labelHeightMm' => $labelHeightMm,
        ];

        $pdf = Pdf::loadView('penerimaan_barang.print_non_pl_labels', $data)
            ->setPaper([0, 0, $paperWidthPt, $paperHeightPt])
            ->setOptions([
                'isRemoteEnabled' => true,
            ]);

        return $pdf->stream('print-non-pl-' . $token . '.pdf');
    }

    public function store(Request $request)
    {
        // Validasi cabang aktif
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        Log::info('Received form data:', $request->all());

        $mode = $request->input('mode', 'packing_list');

        $activeCred = UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'));
        $hasActiveCred = Auth::check() && !empty($activeCred['accurate_api_token']) && !empty($activeCred['accurate_signature_secret']);
        $hasInterStoreCred = false;
        if (Auth::check()) {
            $interStoreCred = $this->getInterStoreCredentials($branch);
            $hasInterStoreCred = !empty($interStoreCred['primary']) || !empty($interStoreCred['secondary']);
        }
        if (!$hasActiveCred && !($mode === 'antar_toko' && $hasInterStoreCred)) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $rules = [
            'no_po' => 'required|string|max:255',
            'vendor' => 'required|string|max:255',
            'no_terima' => 'required|string|max:255|unique:penerimaan_barangs,no_terima',
            'npb' => 'required|string|max:255|unique:penerimaan_barangs,npb',
            'tanggal' => 'required|date',
            'mode' => 'sometimes|in:packing_list,non_packing_list,antar_toko',
        ];

        if ($mode === 'packing_list') {
            $rules['packing_list_ids'] = 'required|array';
            $rules['packing_list_ids.*'] = 'integer|exists:packing_list,id';
        } elseif ($mode === 'non_packing_list') {
            $rules['non_pl_items'] = 'required|string';
        } elseif ($mode === 'antar_toko') {
            $rules['no_do'] = 'required|string|max:255';
            $rules['antar_toko_items'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules, [
            'no_po.required' => 'No PO harus diisi',
            'vendor.required' => 'Vendor harus diisi',
            'no_terima.required' => 'No Terima harus diisi (wajib untuk Accurate).',
            'no_terima.unique' => 'No Terima sudah pernah digunakan. Gunakan nomor yang berbeda.',
            'tanggal.required' => 'Tanggal harus diisi',
            'tanggal.date' => 'Format tanggal tidak valid',
            'npb.unique' => 'Nomor Form ini sudah pernah digunakan.',
            'packing_list_ids.required' => 'Pilih minimal satu packing list.',
        ]);

        if ($validator->fails()) {
            Log::debug('Validasi Gagal:', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput()->with('error', 'Data yang dikirim tidak valid.');
        }

        if ($mode === 'antar_toko') {
            try {
                $v = $validator->validated();
                $this->validateInterStorePoDoForBranch($branch, $v['no_po'], (string) ($v['no_do'] ?? ''));
            } catch (\Exception $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

        // Database transaction
        DB::beginTransaction();

        try {
            $validatedData = $validator->validated();
            // NOTE:
            // Temporary table (TempMasterBarcode/TempScannedBarcode) sudah tidak dipakai.
            // Mode packing_list sekarang langsung memakai data Barcode permanen yang sudah ada,
            // dan validasi kecocokan dilakukan melalui mapping dari packing list.

            // Simpan penerimaan barang dengan kode_customer dari cabang aktif
            $penerimaan = PenerimaanBarang::create([
                'no_po' => $validatedData['no_po'],
                'vendor' => $validatedData['vendor'],
                'no_terima' => $validatedData['no_terima'],
                'npb' => $validatedData['npb'],
                'tanggal' => $validatedData['tanggal'],
                'kode_customer' => $branch->customer_id,
            ]);

            // Attach packing lists (many-to-many) - hanya jika mode packing_list
            $packingListIds = $validatedData['packing_list_ids'] ?? [];
            if ($mode === 'packing_list' && !empty($packingListIds)) {
                $penerimaan->packingLists()->attach($packingListIds);
            }

            Log::info('Penerimaan barang created:', array_merge($penerimaan->toArray(), ['mode' => $mode]));

            $itemDetails = [];
            $barcodes = collect();
            $vendorData = null;
            $matchedBarcodeIds = [];
            $detailItems = [];

            if ($mode === 'packing_list') {
                // Mode Packing List: ambil detail item dari packing list
                $result = $this->mapItemDetailsFromPackingLists(
                    $validatedData['no_po'],
                    $validatedData['npb'],
                    $packingListIds,
                    true,  // Update id_pb pada barcode
                    true,  // Include vendor
                    PackingList::STATUS_APPROVED
                );

                $itemDetails = $result['items'];
                $barcodes = $result['barcodes'] ?? ($result['approvalStocks'] ?? collect());
                $vendorData = $result['vendor'];
                $matchedBarcodeIds = $result['matchedBarcodeIds'] ?? [];

                Log::info('Mapped items and vendor data:', [
                    'items_count' => count($itemDetails),
                    'barcodes_count' => $barcodes->count(),
                    'vendor_data' => $vendorData
                ]);

                if ($barcodes->isEmpty()) {
                    Log::warning("Barcode kosong saat simpan penerimaan barang", [
                        'no_po' => $validatedData['no_po'],
                        'npb' => $validatedData['npb']
                    ]);

                    $penerimaan->delete();
                    DB::rollBack();
                    return redirect()->route('penerimaan-barang.index')
                        ->with('error', 'Tidak ada barang yang disetujui untuk penerimaan barang ini');
                }

                // Update status barcode ke uploaded
                foreach ($barcodes as $barcode) {
                    $barcode->status = Barcode::STATUS_UPLOADED;
                    $barcode->save();
                }

                foreach ($itemDetails as $item) {
                    if ($item['panjang_total'] > 0) {
                        $uomAcc = $this->normalizeUom($item['uom_acc'] ?? 'M');
                        $detailItems[] = [
                            'itemNo' => $item['item_no_pl'] ?? $item['kode_barang'],
                            'quantity' => (float) $item['panjang_total'],
                            'unitPrice' => (float) $item['unit_price'],
                            'itemUnitName' => $uomAcc === 'YD' ? 'YD' : 'METER',
                            'purchaseOrderNumber' => $validatedData['no_po'],
                            'warehouseName' => 'GUDANG STOK',
                            'detailSerialNumber' => $item['serial_numbers'] ?? [],
                        ];
                    }
                }

                if (empty($detailItems)) {
                    Log::warning("Tidak ada item yang cocok untuk dikirim ke Accurate", [
                        'npb' => $validatedData['npb'],
                        'items' => $itemDetails
                    ]);

                    foreach ($barcodes as $barcode) {
                        $barcode->status = Barcode::STATUS_APPROVED;
                        $barcode->save();
                    }

                    $penerimaan->delete();
                    DB::rollBack();

                    return redirect()->route('penerimaan-barang.index')
                        ->with('error', 'Tidak ada barang yang cocok dengan data Accurate');
                }
            } elseif ($mode === 'non_packing_list') {
                // Mode Non Packing List: ambil data dari non_pl_items (JSON dari frontend)
                $nonPlItemsRaw = json_decode($request->input('non_pl_items', '[]'), true);

                if (empty($nonPlItemsRaw) || !is_array($nonPlItemsRaw)) {
                    $penerimaan->delete();
                    DB::rollBack();
                    return back()->with('error', 'Data item Non Packing List tidak valid atau kosong.');
                }

                // Ambil vendor dari Accurate PO
                $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
                $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
                $tsVendor = Carbon::now()->toIso8601String();
                $sigVendor = hash_hmac('sha256', $tsVendor, $signatureSecret);

                $poResponse = Http::timeout(30)->withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'X-Api-Signature' => $sigVendor,
                    'X-Api-Timestamp' => $tsVendor,
                ])->get($this->buildApiUrl($branch, 'purchase-order/detail.do'), [
                            'number' => $validatedData['no_po'],
                        ]);

                if ($poResponse->successful()) {
                    $resData = $poResponse->json();
                    if (isset($resData['d']['vendor'])) {
                        $vendorData = ['vendorNo' => $resData['d']['vendor']['vendorNo'] ?? null];
                    }
                }

                // Build detailItems dari non_pl_items + simpan BarcodeNonPL
                foreach ($nonPlItemsRaw as $nlItem) {
                    $itemNo = $nlItem['kode_barang'] ?? null;
                    $itemName = $nlItem['nama_barang'] ?? null;
                    $serialNumbers = $nlItem['serial_numbers'] ?? [];
                    $uom = $nlItem['uom'] ?? 'METER';
                    $unitPrice = (float) ($nlItem['unit_price'] ?? 0);
                    $itemQtyLimit = (float) ($nlItem['kuantitas'] ?? 0);

                    if (!$itemNo || empty($serialNumbers))
                        continue;

                    $totalQty = 0;
                    $detailSerials = [];
                    foreach ($serialNumbers as $sn) {
                        $snQty = (float) ($sn['quantity'] ?? 0);
                        $snBarcode = $sn['barcode'] ?? '';
                        $snToken = (string) ($sn['reservation_token'] ?? '');
                        if ($snBarcode && $snQty > 0) {
                            if ($snToken === '') {
                                throw new \Exception("Token reservasi tidak ditemukan untuk barcode {$snBarcode}.");
                            }

                            $reservation = Cache::get($this->nonPlReservationCacheKey($snToken));
                            if (!$reservation || !is_array($reservation)) {
                                throw new \Exception("Reservasi barcode sudah kedaluwarsa atau tidak valid untuk barcode {$snBarcode}.");
                            }

                            $reservedCustomer = (string) ($reservation['kode_customer'] ?? '');
                            if ($reservedCustomer !== $branch->customer_id) {
                                throw new \Exception("Reservasi barcode tidak sesuai customer untuk barcode {$snBarcode}.");
                            }

                            $reservedBarcodes = $reservation['barcodes'] ?? [];
                            if (!is_array($reservedBarcodes) || !in_array($snBarcode, $reservedBarcodes, true)) {
                                throw new \Exception("Barcode {$snBarcode} tidak termasuk dalam reservasi token yang diberikan.");
                            }

                            // Pastikan tidak pernah dipakai sebelumnya
                            if (BarcodeNonPL::where('barcode', $snBarcode)->exists()) {
                                throw new \Exception("Barcode {$snBarcode} sudah pernah digunakan.");
                            }

                            $detailSerials[] = [
                                'serialNumberNo' => $snBarcode,
                                'quantity' => $snQty,
                            ];
                            $totalQty += $snQty;

                            if ($itemQtyLimit > 0 && $totalQty > $itemQtyLimit + 1e-9) {
                                throw new \Exception("Total kuantitas serial untuk item {$itemNo} melebihi kuantitas item ({$itemQtyLimit}).");
                            }

                            BarcodeNonPL::updateOrCreate(
                                ['barcode' => $snBarcode],
                                [
                                    'kode_customer' => $branch->customer_id,
                                    'quantity' => $snQty,
                                    'npb' => $penerimaan->npb,
                                    'item_no' => $itemNo,
                                    'item_name' => $itemName,
                                ]
                            );
                        }
                    }

                    if ($totalQty > 0) {
                        $detailItems[] = [
                            'itemNo' => $itemNo,
                            'quantity' => $totalQty,
                            'unitPrice' => $unitPrice,
                            'itemUnitName' => $uom,
                            'purchaseOrderNumber' => $validatedData['no_po'],
                            'warehouseName' => 'GUDANG STOK',
                            'detailSerialNumber' => $detailSerials,
                        ];
                    }
                }

                if (empty($detailItems)) {
                    $penerimaan->delete();
                    DB::rollBack();
                    return redirect()->route('penerimaan-barang.index')
                        ->with('error', 'Tidak ada item Non Packing List yang valid untuk dikirim.');
                }

                Log::info('Non Packing List mode - items with serial numbers:', [
                    'items_count' => count($detailItems),
                    'vendor_data' => $vendorData,
                    'total_barcodes' => \App\Models\BarcodeNonPL::where('npb', $penerimaan->npb)->count(),
                ]);
            } elseif ($mode === 'antar_toko') {
                $doNumber = (string) ($validatedData['no_do'] ?? '');
                $doResult = $this->fetchDeliveryOrderDetailWithFallback($branch, $doNumber);
                $doData = $doResult['data']['d'] ?? [];
                $antarTokoItemsRaw = json_decode((string) ($request->input('antar_toko_items', '[]')), true);
                if (!is_array($antarTokoItemsRaw) || empty($antarTokoItemsRaw)) {
                    $penerimaan->delete();
                    DB::rollBack();
                    return back()->with('error', 'Data scan serial mode antar toko tidak valid atau kosong.');
                }
                // Untuk mode antar_toko, vendorNo harus mengikuti field form `vendor`
                // (sudah dipopulasi dari purchase-order/detail.do pada tahap getDetailPo).
                $vendorNoFromForm = trim((string) ($validatedData['vendor'] ?? ''));
                $vendorNoFallback = data_get($doData, 'vendor.vendorNo')
                    ?? data_get($doData, 'customer.customerNo')
                    ?? data_get($doData, 'customer.no');
                $vendorData = ['vendorNo' => $vendorNoFromForm !== '' ? $vendorNoFromForm : $vendorNoFallback];

                $doDetailItems = $doData['detailItem'] ?? [];
                $submittedByItemNo = [];
                foreach ($antarTokoItemsRaw as $item) {
                    $itemNoKey = trim((string) ($item['kode_barang'] ?? ''));
                    if ($itemNoKey !== '') {
                        $submittedByItemNo[$itemNoKey] = $item;
                    }
                }

                foreach ($doDetailItems as $detail) {
                    $itemNo = $detail['item']['no'] ?? null;
                    if (!$itemNo) {
                        continue;
                    }

                    $unitName = $detail['itemUnit']['name'] ?? ($detail['item']['unit1']['name'] ?? 'METER');
                    $unitPrice = (float) ($detail['unitPrice'] ?? 0);
                    $quantity = (float) ($detail['quantity'] ?? 0);
                    $detailSerialNumber = [];
                    $expectedSerialMap = [];

                    foreach (($detail['detailSerialNumber'] ?? []) as $sn) {
                        $serialNo = data_get($sn, 'serialNumber.number');
                        $serialQty = (float) data_get($sn, 'quantity', 0);
                        if ($serialNo && $serialQty > 0) {
                            $detailSerialNumber[] = [
                                'serialNumberNo' => $serialNo,
                                'quantity' => $serialQty,
                            ];
                            $expectedSerialMap[$serialNo] = $serialQty;
                        }
                    }

                    $submittedItem = $submittedByItemNo[$itemNo] ?? null;
                    $submittedSerials = is_array($submittedItem['serial_numbers'] ?? null) ? $submittedItem['serial_numbers'] : [];

                    if (!empty($expectedSerialMap)) {
                        $scannedSerialMap = [];
                        foreach ($submittedSerials as $sn) {
                            $snNo = trim((string) ($sn['barcode'] ?? ''));
                            if ($snNo === '') {
                                continue;
                            }
                            $snQty = (float) ($sn['quantity'] ?? 0);
                            $scannedSerialMap[$snNo] = $snQty;
                        }

                        $expectedKeys = array_keys($expectedSerialMap);
                        $scannedKeys = array_keys($scannedSerialMap);
                        sort($expectedKeys);
                        sort($scannedKeys);
                        if ($expectedKeys !== $scannedKeys) {
                            throw new \Exception("Serial number item {$itemNo} belum lengkap atau tidak sesuai detail Delivery Order.");
                        }

                        foreach ($expectedSerialMap as $snNo => $expectedQty) {
                            $actualQty = (float) ($scannedSerialMap[$snNo] ?? 0);
                            if (abs($actualQty - $expectedQty) > 1e-6) {
                                throw new \Exception("Kuantitas serial {$snNo} untuk item {$itemNo} tidak sesuai detail Delivery Order.");
                            }
                        }
                    }

                    if ($quantity <= 0 && !empty($detailSerialNumber)) {
                        $quantity = array_sum(array_column($detailSerialNumber, 'quantity'));
                    }
                    if ($quantity <= 0) {
                        continue;
                    }

                    $detailItems[] = [
                        'itemNo' => $itemNo,
                        'quantity' => $quantity,
                        'unitPrice' => $unitPrice,
                        'itemUnitName' => $unitName,
                        'purchaseOrderNumber' => $validatedData['no_po'],
                        'warehouseName' => 'GUDANG STOK',
                        'detailSerialNumber' => $detailSerialNumber,
                    ];
                }

                if (empty($detailItems)) {
                    $penerimaan->delete();
                    DB::rollBack();
                    return redirect()->route('penerimaan-barang.index')
                        ->with('error', 'Tidak ada item Delivery Order yang valid untuk mode antar toko.');
                }

                Log::info('Antar toko mode - detail items prepared:', [
                    'items_count' => count($detailItems),
                    'no_do' => $doNumber,
                    'vendor_no_form' => $validatedData['vendor'] ?? null,
                    'vendor_no_selected' => $vendorData['vendorNo'] ?? null,
                    'credential_source' => $doResult['credential_source'] ?? null,
                    'credential_customer_id' => $doResult['credential_customer_id'] ?? null,
                    'save_target_credential' => 'primary',
                ]);
            }

            // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
            // Catatan:
            // - Mode antar_toko: secondary hanya untuk ambil data DO (list/detail).
            // - Eksekusi receive-item/save.do WAJIB ke company/cabang primary (active branch).
            $apiToken = ($activeCred['accurate_api_token'] ?? null);
            $signatureSecret = ($activeCred['accurate_signature_secret'] ?? null);
            if (!$apiToken || !$signatureSecret) {
                throw new \Exception('Kredensial Accurate tidak tersedia untuk proses simpan.');
            }
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            // Log API credentials status
            Log::info('API credentials check:', [
                'api_token_exists' => !empty($apiToken),
                'signature_secret_exists' => !empty($signatureSecret),
                'timestamp' => $timestamp
            ]);

            // Prepare data for Accurate API
            $vendorNo = $vendorData['vendorNo'] ?? $validatedData['vendor'];
            $transDate = Carbon::parse($validatedData['tanggal'])->format('d/m/Y');

            $postData = [
                'detailItem' => $detailItems,
                'receiveNumber' => $penerimaan->no_terima,
                'vendorNo' => $vendorNo,
                'transDate' => $transDate,
            ];

            Log::info('Data yang akan dikirim ke Accurate API:', [
                'endpoint' => $this->buildApiUrl($branch, 'receive-item/save.do'),
                'post_data' => $postData,
                'headers' => [
                    'Authorization' => 'Bearer [HIDDEN]',
                    'X-Api-Signature' => '[HIDDEN]',
                    'X-Api-Timestamp' => $timestamp,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            // Send to Accurate API dengan timeout
            $response = Http::timeout(60)->withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->buildApiUrl($branch, 'receive-item/save.do'), $postData);

            Log::info('Response dari Accurate API:', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'is_successful' => $response->successful()
            ]);

            if ($response->successful()) {
                // Update status packing list: closed jika semua barcode ter-match (hanya mode packing_list)
                if ($mode === 'packing_list' && !empty($packingListIds)) {
                    foreach ($packingListIds as $plId) {
                        $pl = PackingList::find($plId);
                        if (!$pl)
                            continue;

                        $allBarcodeIds = Barcode::where('no_packing_list', $pl->npl)
                            ->where('kode_customer', $branch->customer_id)
                            ->pluck('id')
                            ->toArray();

                        $matchedInPl = array_intersect($allBarcodeIds, $matchedBarcodeIds);

                        if (count($allBarcodeIds) > 0 && count($matchedInPl) === count($allBarcodeIds)) {
                            $pl->update(['status' => PackingList::STATUS_CLOSED]);
                        } else {
                            $pl->update(['status' => PackingList::STATUS_APPROVED]);
                        }
                    }
                }

                DB::commit();

                // Bersihkan token reservasi Non-PL yang dipakai (best-effort)
                if ($mode === 'non_packing_list') {
                    $tokens = [];
                    $nonPlItemsRaw = json_decode($request->input('non_pl_items', '[]'), true);
                    if (is_array($nonPlItemsRaw)) {
                        foreach ($nonPlItemsRaw as $nlItem) {
                            foreach (($nlItem['serial_numbers'] ?? []) as $sn) {
                                $t = (string) ($sn['reservation_token'] ?? '');
                                if ($t !== '')
                                    $tokens[$t] = true;
                            }
                        }
                    }
                    foreach (array_keys($tokens) as $t) {
                        Cache::forget($this->nonPlReservationCacheKey($t));
                    }
                }

                // Clear related cache (global dan per cabang aktif)
                Cache::forget('accurate_penerimaan_barang_list');
                Cache::forget('accurate_pesanan_pembelian_list');
                Cache::forget('accurate_barang_list');
                Cache::forget('accurate_penerimaan_barang_list_' . $activeBranchId);
                Cache::forget('accurate_pesanan_pembelian_list_' . $activeBranchId);
                Cache::forget('accurate_barang_list_' . $activeBranchId);

                Log::info("Berhasil mengirim data ke Accurate untuk penerimaan barang {$penerimaan->npb}");

                return redirect()->route('penerimaan-barang.index')
                    ->with('success', "Berhasil mengupload item ke Accurate untuk penerimaan barang No. {$penerimaan->no_terima}");
            } else {
                $responseData = $response->json();
                $errorMessage = $responseData['message'] ?? $responseData['d']['message'] ?? 'Gagal mengirim data ke Accurate';

                // Extract more detailed error if available
                if (isset($responseData['d']['errorList']) && is_array($responseData['d']['errorList'])) {
                    $errorDetails = [];
                    foreach ($responseData['d']['errorList'] as $error) {
                        $errorDetails[] = $error['message'] ?? $error;
                    }
                    $errorMessage .= ' - Detail: ' . implode(', ', $errorDetails);
                }

                Log::error("Gagal mengirim data ke Accurate", [
                    'npb' => $penerimaan->npb,
                    'mode' => $mode,
                    'status_code' => $response->status(),
                    'response' => $responseData,
                    'request_data' => $postData,
                    'error_message' => $errorMessage
                ]);

                if ($mode === 'packing_list' && $barcodes->isNotEmpty()) {
                    foreach ($barcodes as $barcode) {
                        $barcode->status = Barcode::STATUS_APPROVED;
                        $barcode->save();
                    }
                }

                $penerimaan->delete();
                DB::rollBack();

                return redirect()->route('penerimaan-barang.index')
                    ->with('error', 'Gagal mengirim data ke Accurate: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Terjadi exception saat store penerimaan barang", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $validatedData ?? $request->all(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            if (isset($barcodes) && $barcodes->isNotEmpty()) {
                foreach ($barcodes as $barcode) {
                    $barcode->status = Barcode::STATUS_APPROVED;
                    $barcode->save();
                }
            }

            if (isset($penerimaan)) {
                $penerimaan->delete();
            }

            return redirect()->route('penerimaan-barang.index')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show($npb, Request $request)
    {
        // Cache key yang unik
        $cacheKey = 'penerimaan_barang_detail_' . $npb;
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $penerimaanBarang = null;
        $matchedItems = [];

        try {
            $penerimaanBarang = PenerimaanBarang::where('npb', $npb)->firstOrFail();

            // Ambil packing list IDs dari penerimaan barang
            $packingListIds = $penerimaanBarang->packingLists()->pluck('packing_list.id')->toArray();
            if (!empty($packingListIds)) {
                // Mode packing_list: gunakan matching barcode permanen dari packing list
                $itemDetailsData = $this->mapItemDetailsFromPackingLists(
                    $penerimaanBarang->no_po,
                    $penerimaanBarang->npb,
                    $packingListIds,
                    false,
                    false,
                    PackingList::STATUS_CLOSED
                );

                $matchedItems = $itemDetailsData['items'];
            } else {
                // Mode non_packing_list: ambil dari BarcodeNonPL berdasarkan npb
                $rows = BarcodeNonPL::where('npb', $penerimaanBarang->npb)->get();
                if ($rows->isEmpty()) {
                    throw new \Exception('Tidak ada data barcode Non Packing List untuk penerimaan barang ini.');
                }

                // Ambil UOM item dari Accurate (optional, untuk kolom satuan)
                $uomMap = [];
                try {
                    $activeBranchId = session('active_branch');
                    $branch = $activeBranchId ? Branch::find($activeBranchId) : null;
                    if (!$branch) {
                        $branch = Branch::where('customer_id', $penerimaanBarang->kode_customer)->first();
                    }

                    if ($branch && Auth::check() && (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) && (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
                        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
                        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
                        $timestamp = Carbon::now()->toIso8601String();
                        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

                        $poResponse = Http::timeout(30)->withHeaders([
                            'Authorization' => 'Bearer ' . $apiToken,
                            'X-Api-Signature' => $signature,
                            'X-Api-Timestamp' => $timestamp,
                        ])->get($this->buildApiUrl($branch, 'purchase-order/detail.do'), [
                                    'number' => $penerimaanBarang->no_po,
                                ]);

                        if ($poResponse->successful()) {
                            $resData = $poResponse->json();
                            foreach ($resData['d']['detailItem'] ?? [] as $detail) {
                                $itemNo = $detail['item']['no'] ?? null;
                                $uomAcc = $detail['itemUnit']['name'] ?? ($detail['item']['unit1']['name'] ?? null);
                                if ($itemNo) {
                                    $uomMap[$itemNo] = $this->normalizeUom($uomAcc);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Gagal mengambil UOM Accurate untuk Non PL detail', ['npb' => $npb, 'error' => $e->getMessage()]);
                }

                $matchedItems = $rows
                    ->groupBy('item_no')
                    ->map(function ($grp, $itemNo) use ($uomMap) {
                        $sumQty = $grp->sum('quantity');
                        $itemName = $grp->first()->item_name ?? '-';
                        $uomAcc = $uomMap[$itemNo] ?? 'M';

                        $serialNumbers = $grp->map(fn($row) => [
                            'serialNumberNo' => $row->barcode,
                            'quantity' => round((float) $row->quantity, 2),
                        ])->values()->all();

                        return [
                            'nama_barang' => $itemName,
                            'kode_barang' => $itemNo,
                            'panjang_total' => round((float) $sumQty, 2),
                            'uom_acc' => $uomAcc,
                            'unit' => $uomAcc === 'YD' ? 'YD' : 'METER',
                            'serial_numbers' => $serialNumbers,
                        ];
                    })
                    ->values()
                    ->all();
            }

            $dataToCache = [
                'penerimaanBarang' => $penerimaanBarang,
                'matchedItems' => $matchedItems,
                'errorMessage' => null
            ];

            // Simpan data ke cache setelah berhasil dari API
            Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
            Log::info("Data detail penerimaan barang {$npb} dari API berhasil disimpan ke cache");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $errorMessage = "Penerimaan barang dengan NPB {$npb} tidak ditemukan.";
            Log::error('Penerimaan barang tidak ditemukan: ' . $e->getMessage(), ['npb' => $npb]);
        } catch (\Exception $e) {
            // Log error untuk debugging
            Log::error('Error in show method: ' . $e->getMessage(), [
                'npb' => $npb,
                'penerimaan_barang' => $penerimaanBarang ? $penerimaanBarang->toArray() : null
            ]);

            if ($penerimaanBarang) {
                $errorMessage = "Gagal mengambil detail barang dari server. Silakan coba lagi.";
            } else {
                $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
            }

            // Gunakan cache sebagai fallback jika API error
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $penerimaanBarang = $cachedData['penerimaanBarang'] ?? null;
                $matchedItems = $cachedData['matchedItems'] ?? [];
                if (is_null($errorMessage))
                    $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menggunakan data cached untuk {$npb} karena error pada API");
            }
        }

        return view('penerimaan_barang.detail', compact('penerimaanBarang', 'matchedItems', 'errorMessage'));
    }
}
