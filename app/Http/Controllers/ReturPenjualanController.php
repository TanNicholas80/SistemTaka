<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\FakturPenjualan;
use App\Models\PengirimanPesanan;
use App\Models\ReturPenjualan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReturPenjualanController extends Controller
{
    public function index(Request $request)
    {
        // Validasi active_branch session
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Tidak ada cabang yang aktif. Silakan pilih cabang terlebih dahulu.');
        }

        // Ambil data Branch
        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Data cabang tidak ditemukan.');
        }

        // Validasi credentials API Accurate dari Branch
        if (!Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        // Cache key yang unik per branch
        $cacheKey = 'accurate_retur_penjualan_list_branch_' . $activeBranchId;
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache retur penjualan dihapus karena force_refresh');
        }

        $errorMessage = null;

        // Periksa apakah cache sudah ada
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $returPenjualan = $cachedData['returPenjualan'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data retur penjualan diambil dari cache');
            return view('retur_penjualan.index', compact('returPenjualan', 'errorMessage'));
        }

        // Get API credentials from branch (auto-decrypted by model accessors)
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Define the API URL for listing sales returns
        $listApiUrl = $baseUrl . '/sales-return/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20
        ];

        // Initialize an empty array for sales returns
        $returPenjualan = [];
        $allSalesReturns = [];
        $apiSuccess = false;
        $hasApiError = false;

        // Selalu coba ambil data dari API terlebih dahulu
        try {
            // Fetch sales return IDs from the API
            $firstPageResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($listApiUrl, $data);

            // Log the response for debugging
            Log::info('API List Response:', [
                'status' => $firstPageResponse->status(),
                'body' => $firstPageResponse->body(),
            ]);

            // Check if the response is successful
            if ($firstPageResponse->successful()) {
                $responseData = $firstPageResponse->json();

                // Logging data list retur penjualan mentah dari Accurate
                Log::info('Accurate Retur penjualan list first page response:', $responseData);

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allSalesReturns = $responseData['d'];

                    // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                    $totalItems = $responseData['sp']['rowCount'] ?? 0;
                    $totalPages = ceil($totalItems / 20); // 20 adalah pageSize

                    // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                    if ($totalPages > 1) {
                        // Buat array untuk menyimpan semua promise
                        $promises = [];
                        $client = new \GuzzleHttp\Client();

                        // Buat promise untuk setiap halaman (mulai dari halaman 2)
                        for ($page = 2; $page <= $totalPages; $page++) {
                            $promises[$page] = $client->getAsync($listApiUrl, [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $apiToken,
                                    'X-Api-Signature' => $signature,
                                    'X-Api-Timestamp' => $timestamp,
                                ],
                                'query' => [
                                    'sp.page' => $page,
                                    'sp.pageSize' => 20
                                ]
                            ]);
                        }

                        // Jalankan semua promise secara paralel
                        $results = Utils::settle($promises)->wait();

                        // Proses hasil dari setiap promise
                        foreach ($results as $page => $result) {
                            if ($result['state'] === 'fulfilled') {
                                $pageResponse = json_decode($result['value']->getBody(), true);
                                if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                    // Gabungkan data dari halaman ini
                                    $allSalesReturns = array_merge($allSalesReturns, $pageResponse['d']);
                                    Log::info("Accurate Retur penjualan list page {$page} response processed");
                                }
                            } else {
                                Log::error("Failed to fetch page {$page}: " . $result['reason']);
                            }
                        }
                    }

                    // Setelah mendapatkan semua ID retur penjualan, ambil detail untuk masing-masing secara batch
                    $detailsResult = $this->fetchSalesReturnDetailsInBatches($allSalesReturns, $apiToken, $signature, $timestamp, $baseUrl);
                    $returPenjualan = $detailsResult['details'];
                    
                    // Cek jika ada error dari proses fetch detail
                    if ($detailsResult['has_error']) {
                        $hasApiError = true;
                    }
                    
                    $apiSuccess = true;
                    Log::info('Data retur penjualan dari API berhasil diambil');
                }
            }
        } catch (Exception $e) {
            Log::error('Error saat mengambil data dari API Accurate: ' . $e->getMessage());
            $hasApiError = true;
        }

        // Set error message berdasarkan kondisi
        if ($hasApiError) {
            $errorMessage = 'Gagal memuat data dari server Accurate. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi dengan menekan tombol "Refresh Data".';
        }

        // Jika API gagal dan tidak ada data, coba gunakan cache sebagai fallback
        if (!$apiSuccess && empty($returPenjualan)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $returPenjualan = $cachedData['returPenjualan'] ?? [];
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info('Data retur penjualan diambil dari cache karena API error');
            } else {
                if (is_null($errorMessage)) $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache tersedia.';
                Log::warning('Tidak ada cache tersedia, menampilkan data kosong');
            }
        }

        // Simpan data ke cache
        $dataToCache = [
            'returPenjualan' => $returPenjualan,
            'errorMessage' => $errorMessage
        ];

        Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
        Log::info('Data retur penjualan disimpan ke cache');

        return view('retur_penjualan.index', compact('returPenjualan', 'errorMessage'));
    }

        /**
     * Mengambil detail retur penjualan dalam batch untuk mengoptimalkan performa
     */
    private function fetchSalesReturnDetailsInBatches($salesReturns, $apiToken, $signature, $timestamp, $baseUrl, $batchSize = 5)
    {
        $salesReturnDetails = [];
        $batches = array_chunk($salesReturns, $batchSize);
        $hasApiError = false; // Flag error untuk fungsi ini

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $return) {
                $detailUrl = $baseUrl . '/sales-return/detail.do?id=' . $return['id'];
                $promises[$return['id']] = $client->getAsync($detailUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ]
                ]);
            }

            if (empty($promises)) continue;

            // Jalankan batch promise secara paralel
            $results = Utils::settle($promises)->wait();

            // Proses hasil dari setiap promise
            foreach ($results as $invoiceId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $salesReturnDetails[] = $detailResponse['d'];
                        Log::info("Retur penjualan detail fetched for ID: {$return['id']}");
                    }
                } else {
                    $reason = $result['reason'];
                    Log::error("Failed to fetch retur penjualan detail for ID {$invoiceId}: " . $reason->getMessage());
                    
                    // Check if it's a rate limiting error
                    if ($reason instanceof \GuzzleHttp\Exception\ClientException && $reason->getResponse()->getStatusCode() == 429) {
                        $hasApiError = true;
                    }
                }
            }

            // Tambahkan delay kecil antara batch untuk menghindari rate limiting
            usleep(200000); // 200ms
        }

        return [
            'details' => $salesReturnDetails,
            'has_error' => $hasApiError
        ];
    }

    public function create() {
        // Validasi active_branch session
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Tidak ada cabang yang aktif. Silakan pilih cabang terlebih dahulu.');
        }

        // Ambil data Branch
        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Data cabang tidak ditemukan.');
        }

        // Validasi credentials API Accurate dari Branch
        if (!Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');

        // Delivery orders dan sales invoices akan di-fetch via AJAX saat user memilih Customer
        // Lihat create.blade.php - data diambil dengan filter.customerNo
        $deliveryOrders = [];
        $salesInvoices = [];

        // Get customers (pelanggan) for dropdown
        $pelanggan = $this->fetchCustomersFromAccurate($branch, $baseUrl);

        $selectedTanggal = date('Y-m-d');
        $formReadonly = false;
        $no_retur = ReturPenjualan::generateNoRetur();

        return view('retur_penjualan.create', compact('selectedTanggal', 'formReadonly', 'no_retur', 'pelanggan', 'salesInvoices', 'deliveryOrders'));
    }

    /**
     * AJAX: Get delivery orders filtered by customer (untuk dropdown referensi Retur Dari)
     */
    public function getDeliveryOrdersAjax(Request $request)
    {
        $customerNo = $request->query('filter.customerNo') ?? $request->query('filter_customerNo');
        if (empty($customerNo)) {
            return response()->json(['deliveryOrders' => [], 'message' => 'customerNo wajib diisi']);
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['deliveryOrders' => [], 'error' => 'Tidak ada cabang yang aktif.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch || !Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return response()->json(['deliveryOrders' => [], 'error' => 'Kredensial API tidak tersedia.'], 400);
        }

        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $deliveryOrders = $this->getDeliveryOrdersFromAccurate($branch, $baseUrl, $customerNo);

        return response()->json(['deliveryOrders' => $deliveryOrders]);
    }

    /**
     * AJAX: Get sales invoices filtered by customer (untuk dropdown referensi Retur Dari)
     */
    public function getSalesInvoicesAjax(Request $request)
    {
        $customerNo = $request->query('filter.customerNo') ?? $request->query('filter_customerNo');
        if (empty($customerNo)) {
            return response()->json(['salesInvoices' => [], 'message' => 'customerNo wajib diisi']);
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['salesInvoices' => [], 'error' => 'Tidak ada cabang yang aktif.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch || !Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return response()->json(['salesInvoices' => [], 'error' => 'Kredensial API tidak tersedia.'], 400);
        }

        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $salesInvoices = $this->getSalesInvoicesFromAccurate($branch, $baseUrl, $customerNo);

        return response()->json(['salesInvoices' => $salesInvoices]);
    }

    /**
     * AJAX: Get detail items dari referensi (delivery order atau sales invoice) untuk form retur penjualan
     */
    public function getReferensiDetailAjax(Request $request)
    {
        $returnType = $request->query('return_type') ?? $request->input('return_type');
        $number = $request->query('number') ?? $request->input('number');

        if (empty($number) || empty($returnType)) {
            return response()->json([
                'success' => false,
                'message' => 'return_type dan number referensi wajib diisi.',
            ], 400);
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['success' => false, 'message' => 'Tidak ada cabang yang aktif.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch || !Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return response()->json(['success' => false, 'message' => 'Kredensial API tidak tersedia.'], 400);
        }

        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        $detailApiUrl = null;
        if ($returnType === 'delivery') {
            $detailApiUrl = $baseUrl . '/delivery-order/detail.do';
        } elseif (in_array($returnType, ['invoice', 'invoice_dp'])) {
            $detailApiUrl = $baseUrl . '/sales-invoice/detail.do';
        }

        if (!$detailApiUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Tipe retur tidak valid untuk mengambil detail.',
            ], 400);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($detailApiUrl, ['number' => $number]);

            if (!$response->successful()) {
                Log::warning('Referensi detail API gagal', [
                    'return_type' => $returnType,
                    'number' => $number,
                    'status' => $response->status(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil detail referensi dari Accurate.',
                ], $response->status());
            }

            $responseData = $response->json();
            if (!isset($responseData['d'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Respon API tidak valid.',
                ], 400);
            }

            $detail = $responseData['d'];
            $detailItems = $detail['detailItem'] ?? [];

            return response()->json([
                'success' => true,
                'detailItems' => $detailItems,
            ]);
        } catch (Exception $e) {
            Log::error('Exception getReferensiDetailAjax: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail referensi.',
            ], 500);
        }
    }

    /**
     * Build full API URL for Accurate
     */
    private function buildApiUrl(Branch $branch, string $endpoint): string
    {
        $baseUrl = $branch->url_accurate ?? 'https://iris.accurate.id';
        $baseUrl = rtrim($baseUrl, '/');
        $apiPath = '/accurate/api';
        if (strpos($baseUrl, '/accurate/api') !== false) {
            return $baseUrl . '/' . ltrim($endpoint, '/');
        }
        return $baseUrl . $apiPath . '/' . ltrim($endpoint, '/');
    }

    /**
     * Fetch customers from Accurate API for dropdown (list + detail batch)
     */
    private function fetchCustomersFromAccurate(Branch $branch, string $baseUrl): array
    {
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $customerApiUrl = $this->buildApiUrl($branch, 'customer/list.do');
        $data = ['sp.page' => 1, 'sp.pageSize' => 20];

        try {
            $firstPageResponse = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($customerApiUrl, $data);

            if (!$firstPageResponse->successful()) {
                return [];
            }
            $responseData = $firstPageResponse->json();
            $allCustomers = $responseData['d'] ?? [];
            if (!is_array($allCustomers)) {
                return [];
            }
            $totalItems = $responseData['sp']['rowCount'] ?? 0;
            $totalPages = (int) ceil($totalItems / 20);
            if ($totalPages > 1) {
                $client = new \GuzzleHttp\Client();
                $promises = [];
                for ($page = 2; $page <= $totalPages; $page++) {
                    $promises[$page] = $client->getAsync($customerApiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $apiToken,
                            'X-Api-Signature' => $signature,
                            'X-Api-Timestamp' => $timestamp,
                        ],
                        'query' => ['sp.page' => $page, 'sp.pageSize' => 20],
                    ]);
                }
                $results = Utils::settle($promises)->wait();
                foreach ($results as $result) {
                    if ($result['state'] === 'fulfilled') {
                        $pageResponse = json_decode($result['value']->getBody(), true);
                        if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                            $allCustomers = array_merge($allCustomers, $pageResponse['d']);
                        }
                    }
                }
            }
            return $this->fetchCustomerDetailsInBatches($allCustomers, $branch, $apiToken, $signature, $timestamp);
        } catch (\Exception $e) {
            Log::error('Exception fetching customers in ReturPenjualan: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch customer details in batches for dropdown (name, customerNo)
     */
    private function fetchCustomerDetailsInBatches(array $customerList, Branch $branch, string $apiToken, string $signature, string $timestamp, int $batchSize = 5): array
    {
        $customerDetails = [];
        $batches = array_chunk($customerList, $batchSize);
        foreach ($batches as $batch) {
            $client = new \GuzzleHttp\Client();
            $promises = [];
            foreach ($batch as $customer) {
                $detailUrl = $this->buildApiUrl($branch, 'customer/detail.do?id=' . $customer['id']);
                $promises[$customer['id']] = $client->getAsync($detailUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ],
                ]);
            }
            $results = Utils::settle($promises)->wait();
            foreach ($results as $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $customerDetails[] = $detailResponse['d'];
                    }
                }
            }
            usleep(200000);
        }
        return $customerDetails;
    }

    /**
     * Get delivery orders data from Accurate API with caching and parallel processing
     *
     * @param Branch $branch
     * @param string $baseUrl
     * @param string|null $customerNo Filter by customer number (untuk filter.customerNo)
     */
    private function getDeliveryOrdersFromAccurate(Branch $branch, string $baseUrl, ?string $customerNo = null)
    {
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        try {
            Log::info('Mengambil data delivery orders dari API Accurate secara real-time', ['customerNo' => $customerNo]);

            // Ambil semua delivery orders dengan pagination handling
            $deliveryOrders = $this->fetchAllDeliveryOrders($apiToken, $signature, $timestamp, $branch, $baseUrl, $customerNo);

            if (!empty($deliveryOrders)) {
                Log::info('Data delivery orders berhasil diambil dari API', ['count' => count($deliveryOrders)]);
            } else {
                Log::warning('API Accurate mengembalikan data delivery orders kosong');
            }

            return $deliveryOrders;
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching delivery orders from Accurate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty array jika terjadi error
            return [];
        }
    }

    /**
     * Fetch all delivery orders with parallel processing dan pagination handling
     *
     * @param string|null $customerNo Filter by customer number (untuk filter.customerNo)
     */
    private function fetchAllDeliveryOrders($apiToken, $signature, $timestamp, Branch $branch, string $baseUrl, ?string $customerNo = null)
    {
        $deliveryOrderApiUrl = $baseUrl . '/delivery-order/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
            'fields' => 'number,customer'
        ];
        if (!empty($customerNo)) {
            $data['filter.customerNo'] = $customerNo;
        }

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($deliveryOrderApiUrl, $data);

        $allDeliveryOrders = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allDeliveryOrders = $responseData['d'];

                // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);

                // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client();

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $queryParams = [
                            'sp.page' => $page,
                            'sp.pageSize' => 20,
                            'fields' => 'number,customer'
                        ];
                        if (!empty($customerNo)) {
                            $queryParams['filter.customerNo'] = $customerNo;
                        }
                        $promises[$page] = $client->getAsync($deliveryOrderApiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ],
                            'query' => $queryParams
                        ]);
                    }

                    $results = Utils::settle($promises)->wait();

                    foreach ($results as $page => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $pageResponse = json_decode($result['value']->getBody(), true);
                            if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                $allDeliveryOrders = array_merge($allDeliveryOrders, $pageResponse['d']);
                            }
                        } else {
                            Log::error("Failed to fetch delivery orders page {$page}: " . $result['reason']);
                        }
                    }
                }
            }
        } else {
            Log::error('Failed to fetch delivery orders from Accurate API', [
                'status' => $firstPageResponse->status(),
                'body' => $firstPageResponse->body(),
            ]);
            return [];
        }

        // Get all existing pengiriman_id from local database filtered by kode_customer
        $kodeCustomerFilter = !empty($customerNo) ? $customerNo : ($branch->customer_id ?? null);
        $existingPengirimanIds = $kodeCustomerFilter
            ? ReturPenjualan::where('kode_customer', $kodeCustomerFilter)->pluck('faktur_penjualan_id')->toArray()
            : ReturPenjualan::pluck('faktur_penjualan_id')->toArray();

        // Filter out delivery orders that already exist in local database
        $deliveryOrders = array_filter($allDeliveryOrders, function ($deliveryOrder) use ($existingPengirimanIds) {
            return !in_array($deliveryOrder['number'], $existingPengirimanIds);
        });

        // Reset array indexes after filtering
        $deliveryOrders = array_values($deliveryOrders);

        Log::info('Delivery Orders filtered successfully:', [
            'total_from_api' => count($allDeliveryOrders),
            'existing_in_database' => count($existingPengirimanIds),
            'filtered_available' => count($deliveryOrders)
        ]);

        return $deliveryOrders;
    }

    /**
     * Get sales invoices data from Accurate API with caching and parallel processing
     *
     * @param Branch $branch
     * @param string $baseUrl
     * @param string|null $customerNo Filter by customer number (untuk filter.customerNo)
     */
    private function getSalesInvoicesFromAccurate(Branch $branch, string $baseUrl, ?string $customerNo = null)
    {
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        try {
            Log::info('Mengambil data sales invoices dari API Accurate secara real-time', ['customerNo' => $customerNo]);

            // Ambil semua sales invoices dengan pagination handling
            $salesInvoices = $this->fetchAllSalesInvoices($apiToken, $signature, $timestamp, $branch, $baseUrl, $customerNo);

            if (!empty($salesInvoices)) {
                Log::info('Data sales invoices berhasil diambil dari API', ['count' => count($salesInvoices)]);
            } else {
                Log::warning('API Accurate mengembalikan data sales invoices kosong');
            }

            return $salesInvoices;
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching sales invoices from Accurate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty array jika terjadi error
            return [];
        }
    }

    /**
     * Fetch all sales invoices with parallel processing dan pagination handling
     *
     * @param string|null $customerNo Filter by customer number (untuk filter.customerNo)
     */
    private function fetchAllSalesInvoices($apiToken, $signature, $timestamp, Branch $branch, string $baseUrl, ?string $customerNo = null)
    {
        $salesInvoiceApiUrl = $baseUrl . '/sales-invoice/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
            'fields' => 'number,customer,statusName'
        ];
        if (!empty($customerNo)) {
            $data['filter.customerNo'] = $customerNo;
        }

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($salesInvoiceApiUrl, $data);

        $allSalesInvoices = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allSalesInvoices = $responseData['d'];

                // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);

                // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client();

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $queryParams = [
                            'sp.page' => $page,
                            'sp.pageSize' => 20,
                            'fields' => 'number,customer,statusName'
                        ];
                        if (!empty($customerNo)) {
                            $queryParams['filter.customerNo'] = $customerNo;
                        }
                        $promises[$page] = $client->getAsync($salesInvoiceApiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ],
                            'query' => $queryParams
                        ]);
                    }

                    $results = Utils::settle($promises)->wait();

                    foreach ($results as $page => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $pageResponse = json_decode($result['value']->getBody(), true);
                            if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                $allSalesInvoices = array_merge($allSalesInvoices, $pageResponse['d']);
                            }
                        } else {
                            Log::error("Failed to fetch sales invoices page {$page}: " . $result['reason']);
                        }
                    }
                }
            }
        } else {
            Log::error('Failed to fetch sales invoices from Accurate API', [
                'status' => $firstPageResponse->status(),
                'body' => $firstPageResponse->body(),
            ]);
            return [];
        }

        // Hanya faktur dengan status "Belum Lunas" yang bisa di-retur (sesuai aturan Accurate)
        $statusBelumLunas = 'Belum Lunas';
        $salesInvoicesBelumLunas = array_filter($allSalesInvoices, function ($invoice) use ($statusBelumLunas) {
            $status = trim((string) ($invoice['statusName'] ?? ''));
            return strcasecmp($status, $statusBelumLunas) === 0;
        });
        $salesInvoicesBelumLunas = array_values($salesInvoicesBelumLunas);

        // Get all existing faktur_penjualan_id from local database filtered by kode_customer
        $kodeCustomerFilter = !empty($customerNo) ? $customerNo : ($branch->customer_id ?? null);
        $existingFakturPenjualanIds = $kodeCustomerFilter
            ? ReturPenjualan::where('kode_customer', $kodeCustomerFilter)->pluck('faktur_penjualan_id')->toArray()
            : ReturPenjualan::pluck('faktur_penjualan_id')->toArray();

        // Filter out invoices that already have retur in local database
        $salesInvoices = array_filter($salesInvoicesBelumLunas, function ($salesInvoice) use ($existingFakturPenjualanIds) {
            return !in_array($salesInvoice['number'], $existingFakturPenjualanIds);
        });

        // Reset array indexes after filtering
        $salesInvoices = array_values($salesInvoices);

        Log::info('Sales Invoices filtered successfully (hanya status Belum Lunas):', [
            'total_from_api' => count($allSalesInvoices),
            'belum_lunas' => count($salesInvoicesBelumLunas),
            'existing_in_database' => count($existingFakturPenjualanIds),
            'filtered_available' => count($salesInvoices)
        ]);

        return $salesInvoices;
    }

    public function store(Request $request)
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Tidak ada cabang yang aktif. Silakan pilih cabang terlebih dahulu.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Data cabang tidak ditemukan.');
        }

        if (!Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        $returnType = $request->input('return_type');
        $returnStatusType = $request->input('return_status_type', 'not_returned');

        $rules = [
            'no_retur'                => 'required|string|max:255|unique:retur_penjualans,no_retur',
            'tanggal_retur'           => 'required|date',
            'pelanggan_id'            => 'required|string|max:255',
            'return_type'             => 'required|in:delivery,invoice,invoice_dp,no_invoice',
            'return_status_type'      => 'required|in:not_returned,partially_returned,returned',
            'detailItems'             => 'required|array|min:1',
            'detailItems.*.kode'      => 'required|string',
            'detailItems.*.kuantitas' => 'required|string',
            'detailItems.*.harga'     => 'required|numeric|min:0',
            'detailItems.*.diskon'    => 'nullable|numeric|min:0',
        ];

        if ($returnType === 'delivery') {
            $rules['pengiriman_pesanan_id'] = 'required|string|max:255';
        } elseif (in_array($returnType, ['invoice', 'invoice_dp'])) {
            $rules['faktur_penjualan_id'] = 'required|string|max:255';
        }

        if ($returnStatusType === 'partially_returned') {
            $rules['detailItems.*.return_detail_status'] = 'required|in:NOT_RETURNED,RETURNED';
        }

        $messages = [
            'no_retur.required'                       => 'Nomor Retur wajib diisi.',
            'no_retur.unique'                         => 'Nomor Retur sudah digunakan.',
            'tanggal_retur.required'                  => 'Tanggal Retur wajib diisi.',
            'tanggal_retur.date'                      => 'Format tanggal tidak valid.',
            'pelanggan_id.required'                   => 'Pelanggan wajib diisi.',
            'return_type.required'                    => 'Tipe retur wajib dipilih.',
            'return_type.in'                          => 'Tipe retur tidak valid.',
            'return_status_type.required'             => 'Status pengembalian wajib dipilih.',
            'return_status_type.in'                   => 'Status pengembalian tidak valid.',
            'pengiriman_pesanan_id.required'          => 'Nomor Pengiriman wajib diisi untuk tipe retur Delivery.',
            'faktur_penjualan_id.required'            => 'Nomor Faktur wajib diisi untuk tipe retur Invoice / Invoice DP.',
            'detailItems.required'                    => 'Detail item wajib diisi.',
            'detailItems.min'                         => 'Minimal harus ada 1 item yang diinputkan.',
            'detailItems.*.kode.required'             => 'Kode item wajib diisi.',
            'detailItems.*.kuantitas.required'        => 'Kuantitas item wajib diisi.',
            'detailItems.*.harga.required'            => 'Harga item wajib diisi.',
            'detailItems.*.harga.min'                 => 'Harga item tidak boleh kurang dari 0.',
            'detailItems.*.diskon.numeric'            => 'Diskon item harus berupa angka.',
            'detailItems.*.diskon.min'                => 'Diskon item tidak boleh kurang dari 0.',
            'detailItems.*.return_detail_status.required' => 'Status pengembalian per item wajib diisi jika status retur Partially Returned.',
            'detailItems.*.return_detail_status.in'       => 'Status pengembalian per item tidak valid.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', 'Data yang dikirim tidak valid.');
        }

        DB::beginTransaction();

        try {
            $validatedData = $validator->validated();
            $returnType = $validatedData['return_type'];

            $apiToken = Auth::user()->accurate_api_token;
            $signatureSecret = Auth::user()->accurate_signature_secret;
            $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $alamat = null;
            $keterangan = null;
            $syaratBayar = 'C.O.D';
            $kenaPajak = null;
            $totalTermasukPajak = null;
            $diskonKeseluruhan = null;
            $deliveryOrderNumber = null;
            $invoiceNumber = null;

            // === Ambil data referensi berdasarkan return_type ===
            if ($returnType === 'delivery') {
                $pengirimanPesanan = PengirimanPesanan::where('no_pengiriman', $validatedData['pengiriman_pesanan_id'])
                    ->where('kode_customer', $branch->customer_id)
                    ->first();

                if (!$pengirimanPesanan) {
                    DB::rollBack();
                    return back()->withInput()->with('error', 'Data Pengiriman Pesanan dengan nomor ' . $validatedData['pengiriman_pesanan_id'] . ' tidak ditemukan.');
                }

                Log::info('PengirimanPesanan data found for retur penjualan:', [
                    'no_pengiriman' => $pengirimanPesanan->no_pengiriman,
                    'alamat' => $pengirimanPesanan->alamat,
                    'syarat_bayar' => $pengirimanPesanan->syarat_bayar,
                    'diskon_keseluruhan' => $pengirimanPesanan->diskon_keseluruhan,
                    'kena_pajak' => $pengirimanPesanan->kena_pajak,
                    'total_termasuk_pajak' => $pengirimanPesanan->total_termasuk_pajak,
                ]);

                $alamat = $pengirimanPesanan->alamat;
                $keterangan = $pengirimanPesanan->keterangan;
                $syaratBayar = !empty($pengirimanPesanan->syarat_bayar) ? $pengirimanPesanan->syarat_bayar : 'C.O.D';
                $kenaPajak = $pengirimanPesanan->kena_pajak;
                $totalTermasukPajak = $pengirimanPesanan->total_termasuk_pajak;
                $diskonKeseluruhan = $pengirimanPesanan->diskon_keseluruhan;
                $deliveryOrderNumber = $validatedData['pengiriman_pesanan_id'];

                // Fallback: jika ada field kosong dari lokal, ambil dari API delivery-order/detail.do
                $needFallback = empty($alamat) || empty($keterangan) || $syaratBayar === 'C.O.D' && empty($pengirimanPesanan->syarat_bayar)
                    || $kenaPajak === null || $totalTermasukPajak === null || $diskonKeseluruhan === null;
                if ($needFallback) {
                    Log::info('Data referensi delivery ada yang kosong, fallback ke API delivery-order/detail.do', [
                        'pengiriman_pesanan_id' => $validatedData['pengiriman_pesanan_id'],
                    ]);

                    try {
                        $doResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $apiToken,
                            'X-Api-Signature' => $signature,
                            'X-Api-Timestamp' => $timestamp,
                        ])->get($baseUrl . '/delivery-order/detail.do', [
                            'number' => $validatedData['pengiriman_pesanan_id'],
                        ]);

                        if ($doResponse->successful() && isset($doResponse->json()['d'])) {
                            $doDetail = $doResponse->json()['d'];
                            if (empty($alamat) && !empty($doDetail['toAddress'])) {
                                $alamat = $doDetail['toAddress'];
                                Log::info('Alamat fallback dari delivery-order/detail.do:', ['alamat' => $alamat]);
                            }
                            if (empty($keterangan) && !empty($doDetail['description'])) {
                                $keterangan = $doDetail['description'];
                            }
                            if (empty($pengirimanPesanan->syarat_bayar) && !empty($doDetail['paymentTermName'])) {
                                $syaratBayar = $doDetail['paymentTermName'];
                            }
                            if ($kenaPajak === null && isset($doDetail['taxable'])) {
                                $kenaPajak = $doDetail['taxable'];
                            }
                            if ($totalTermasukPajak === null && isset($doDetail['inclusiveTax'])) {
                                $totalTermasukPajak = $doDetail['inclusiveTax'];
                            }
                            if ($diskonKeseluruhan === null || $diskonKeseluruhan === '') {
                                if (isset($doDetail['cashDiscPercent']) && $doDetail['cashDiscPercent'] > 0) {
                                    $diskonKeseluruhan = $doDetail['cashDiscPercent'];
                                } elseif (isset($doDetail['cashDiscount']) && $doDetail['cashDiscount'] > 0) {
                                    $diskonKeseluruhan = $doDetail['cashDiscount'];
                                }
                            }
                        } else {
                            Log::warning('Fallback delivery-order/detail.do tidak berhasil', [
                                'status' => $doResponse->status(),
                                'body' => $doResponse->body(),
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Exception saat fallback delivery-order/detail.do: ' . $e->getMessage());
                    }
                }
            } elseif (in_array($returnType, ['invoice', 'invoice_dp'])) {
                $fakturPenjualan = FakturPenjualan::where('no_faktur', $validatedData['faktur_penjualan_id'])
                    ->where('kode_customer', $branch->customer_id)
                    ->first();

                if ($fakturPenjualan) {
                    Log::info('FakturPenjualan data found for retur penjualan (local):', [
                        'no_faktur' => $fakturPenjualan->no_faktur,
                        'alamat' => $fakturPenjualan->alamat,
                        'syarat_bayar' => $fakturPenjualan->syarat_bayar,
                        'diskon_keseluruhan' => $fakturPenjualan->diskon_keseluruhan,
                        'kena_pajak' => $fakturPenjualan->kena_pajak,
                        'total_termasuk_pajak' => $fakturPenjualan->total_termasuk_pajak,
                    ]);

                    $alamat = $fakturPenjualan->alamat;
                    $keterangan = $fakturPenjualan->keterangan;
                    $syaratBayar = !empty($fakturPenjualan->syarat_bayar) ? $fakturPenjualan->syarat_bayar : 'C.O.D';
                    $kenaPajak = $fakturPenjualan->kena_pajak;
                    $totalTermasukPajak = $fakturPenjualan->total_termasuk_pajak;
                    $diskonKeseluruhan = $fakturPenjualan->diskon_keseluruhan;
                } else {
                    // Fallback: tidak ada di model lokal, ambil dari API sales-invoice/detail.do
                    Log::info('FakturPenjualan tidak ditemukan di lokal, fallback ke API sales-invoice/detail.do', [
                        'faktur_penjualan_id' => $validatedData['faktur_penjualan_id'],
                    ]);

                    try {
                        $invoiceResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $apiToken,
                            'X-Api-Signature' => $signature,
                            'X-Api-Timestamp' => $timestamp,
                        ])->get($baseUrl . '/sales-invoice/detail.do', [
                            'number' => $validatedData['faktur_penjualan_id'],
                        ]);

                        if ($invoiceResponse->successful() && isset($invoiceResponse->json()['d'])) {
                            $invDetail = $invoiceResponse->json()['d'];
                            $alamat = $invDetail['toAddress'] ?? null;
                            $keterangan = $invDetail['description'] ?? null;
                            $syaratBayar = !empty($invDetail['paymentTermName']) ? $invDetail['paymentTermName'] : 'C.O.D';
                            $kenaPajak = $invDetail['taxable'] ?? null;
                            $totalTermasukPajak = $invDetail['inclusiveTax'] ?? null;
                            if (isset($invDetail['cashDiscPercent']) && $invDetail['cashDiscPercent'] > 0) {
                                $diskonKeseluruhan = $invDetail['cashDiscPercent'];
                            } elseif (isset($invDetail['cashDiscount']) && $invDetail['cashDiscount'] > 0) {
                                $diskonKeseluruhan = $invDetail['cashDiscount'];
                            } else {
                                $diskonKeseluruhan = null;
                            }
                            Log::info('Data referensi invoice diambil dari sales-invoice/detail.do');
                        } else {
                            DB::rollBack();
                            return back()->withInput()->with('error', 'Data Faktur Penjualan dengan nomor ' . $validatedData['faktur_penjualan_id'] . ' tidak ditemukan di database lokal maupun di Accurate.');
                        }
                    } catch (\Exception $e) {
                        Log::error('Exception saat fallback sales-invoice/detail.do: ' . $e->getMessage());
                        DB::rollBack();
                        return back()->withInput()->with('error', 'Data Faktur Penjualan tidak ditemukan dan gagal mengambil dari Accurate: ' . $e->getMessage());
                    }
                }

                $invoiceNumber = $validatedData['faktur_penjualan_id'];
            }
            // no_invoice: gunakan default (C.O.D, tanpa alamat/keterangan dari referensi)

            // === Build detail items untuk Accurate API ===
            $accurateReturnStatusType = strtoupper($validatedData['return_status_type']);

            $detailItemsForAccurate = [];
            foreach ($validatedData['detailItems'] as $item) {
                $accurateItem = [
                    'itemNo'    => $item['kode'],
                    'quantity'  => $item['kuantitas'],
                    'unitPrice' => $item['harga'],
                ];

                if (isset($item['diskon']) && $item['diskon'] > 0) {
                    $diskon = (float) $item['diskon'];
                    if ($diskon > 0 && $diskon <= 100) {
                        $accurateItem['itemDiscPercent'] = $diskon;
                    } else {
                        $accurateItem['itemCashDiscount'] = $diskon;
                    }
                }

                if ($accurateReturnStatusType === 'PARTIALLY_RETURNED') {
                    $accurateItem['returnDetailStatusType'] = $item['return_detail_status'] ?? 'NOT_RETURNED';
                }

                $detailItemsForAccurate[] = $accurateItem;
            }

            // === Siapkan request body untuk Accurate API ===
            $postDataForAccurate = [
                'customerNo'       => $validatedData['pelanggan_id'],
                'transDate'        => date('d/m/Y', strtotime($validatedData['tanggal_retur'])),
                'number'           => $validatedData['no_retur'],
                'detailItem'       => $detailItemsForAccurate,
                'returnType'       => strtoupper($returnType),
                'returnStatusType' => $accurateReturnStatusType,
                'paymentTermName'  => $syaratBayar,
            ];

            if (!empty($alamat)) {
                $postDataForAccurate['toAddress'] = $alamat;
            }

            if (!empty($keterangan)) {
                $postDataForAccurate['description'] = $keterangan;
            }

            if (!empty($deliveryOrderNumber)) {
                $postDataForAccurate['deliveryOrderNumber'] = $deliveryOrderNumber;
            }

            if (!empty($invoiceNumber)) {
                $postDataForAccurate['invoiceNumber'] = $invoiceNumber;
            }

            if (!empty($diskonKeseluruhan) && $diskonKeseluruhan > 0) {
                $diskonKeseluruhanFloat = (float) $diskonKeseluruhan;
                if ($diskonKeseluruhanFloat > 0 && $diskonKeseluruhanFloat <= 100) {
                    $postDataForAccurate['cashDiscPercent'] = $diskonKeseluruhanFloat;
                } else {
                    $postDataForAccurate['cashDiscount'] = $diskonKeseluruhanFloat;
                }
            }

            if (isset($kenaPajak)) {
                $postDataForAccurate['taxable'] = $kenaPajak;
            }

            if (isset($totalTermasukPajak)) {
                $postDataForAccurate['inclusiveTax'] = $totalTermasukPajak;
            }

            Log::info('PostDataForAccurate retur penjualan prepared:', $postDataForAccurate);

            // === Kirim ke Accurate API ===
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type'  => 'application/json',
            ])->post($baseUrl . '/sales-return/save.do', $postDataForAccurate);

            if (!$response->successful()) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Gagal mengirim data ke Accurate API. HTTP Status: ' . $response->status());
            }

            $responseData = $response->json();

            if (isset($responseData['s']) && $responseData['s'] === false) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Accurate API mengembalikan error: ' . ($responseData['m'] ?? 'Unknown error'));
            }

            // === Simpan ke database lokal ===
            $returPenjualan = ReturPenjualan::create([
                'no_retur'              => $validatedData['no_retur'],
                'tanggal_retur'         => $validatedData['tanggal_retur'],
                'pelanggan_id'          => $validatedData['pelanggan_id'],
                'return_type'           => $returnType,
                'return_status_type'    => $validatedData['return_status_type'],
                'faktur_penjualan_id'   => $validatedData['faktur_penjualan_id'] ?? null,
                'pengiriman_pesanan_id' => $validatedData['pengiriman_pesanan_id'] ?? null,
                'alamat'                => $alamat,
                'keterangan'            => $keterangan,
                'syarat_bayar'          => $syaratBayar,
                'kena_pajak'            => $kenaPajak,
                'total_termasuk_pajak'  => $totalTermasukPajak,
                'diskon_keseluruhan'    => $diskonKeseluruhan,
                'kode_customer'         => $branch->customer_id,
            ]);

            DB::commit();

            Cache::forget('accurate_retur_penjualan_list_branch_' . $activeBranchId);

            return redirect()->route('retur_penjualan.index')
                ->with('success', 'Data retur penjualan berhasil disimpan ke Accurate dan database lokal.')
                ->with('retur_penjualan', $returPenjualan);
        } catch (Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function show($no_retur, Request $request)
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Tidak ada cabang yang aktif. Silakan pilih cabang terlebih dahulu.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Data cabang tidak ditemukan.');
        }

        if (!Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        $cacheKey = 'retur_penjualan_detail_' . $no_retur . '_branch_' . $activeBranchId;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $returPenjualan = null;
        $accurateDetail = null;
        $accurateDetailItems = [];
        $accurateReferenceDetail = null;
        $referenceType = null;
        $pengirimanPesanan = null;
        $fakturPenjualanRef = null;
        $apiSuccess = false;

        try {
            $apiToken = Auth::user()->accurate_api_token;
            $signatureSecret = Auth::user()->accurate_signature_secret;
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
            $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');

            $httpClient = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ]);

            // 1. Ambil data retur penjualan dari database lokal
            $returPenjualan = ReturPenjualan::where('no_retur', $no_retur)
                ->where('kode_customer', $branch->customer_id)
                ->firstOrFail();

            $returnType = $returPenjualan->return_type;

            // 2. Ambil data referensi dari database lokal berdasarkan return_type
            if ($returnType === 'delivery' && $returPenjualan->pengiriman_pesanan_id) {
                $pengirimanPesanan = PengirimanPesanan::where('no_pengiriman', $returPenjualan->pengiriman_pesanan_id)
                    ->where('kode_customer', $branch->customer_id)
                    ->first();
                $referenceType = 'delivery';
            } elseif (in_array($returnType, ['invoice', 'invoice_dp']) && $returPenjualan->faktur_penjualan_id) {
                $fakturPenjualanRef = FakturPenjualan::where('no_faktur', $returPenjualan->faktur_penjualan_id)
                    ->where('kode_customer', $branch->customer_id)
                    ->first();
                $referenceType = 'invoice';
            }

            // 3. Ambil detail retur penjualan dari Accurate API
            $response = $httpClient->get($baseUrl . '/sales-return/detail.do', [
                'number' => $returPenjualan->no_retur,
            ]);

            if ($response->successful() && isset($response->json()['d'])) {
                $accurateDetail = $response->json()['d'];
                $accurateDetailItems = $accurateDetail['detailItem'] ?? [];
                $apiSuccess = true;
            } else {
                if ($response->status() == 404) {
                    $errorMessage = "Retur penjualan dengan nomor {$no_retur} tidak ditemukan di Accurate.";
                } else {
                    $errorMessage = "Gagal mengambil data retur penjualan dari server. Silakan coba lagi.";
                }
                Log::warning('Gagal fetch detail sales return dari Accurate', [
                    'no_retur' => $no_retur,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            // 4. Ambil detail dokumen referensi dari Accurate API
            if ($referenceType === 'delivery' && $returPenjualan->pengiriman_pesanan_id) {
                $deliveryResponse = $httpClient->get($baseUrl . '/delivery-order/detail.do', [
                    'number' => $returPenjualan->pengiriman_pesanan_id,
                ]);

                if ($deliveryResponse->successful() && isset($deliveryResponse->json()['d'])) {
                    $accurateReferenceDetail = $deliveryResponse->json()['d'];
                } else {
                    Log::warning('Gagal fetch detail delivery order untuk retur penjualan', [
                        'pengiriman_pesanan_id' => $returPenjualan->pengiriman_pesanan_id,
                        'status' => $deliveryResponse->status(),
                    ]);
                }
            } elseif ($referenceType === 'invoice' && $returPenjualan->faktur_penjualan_id) {
                $invoiceResponse = $httpClient->get($baseUrl . '/sales-invoice/detail.do', [
                    'number' => $returPenjualan->faktur_penjualan_id,
                ]);

                if ($invoiceResponse->successful() && isset($invoiceResponse->json()['d'])) {
                    $accurateReferenceDetail = $invoiceResponse->json()['d'];
                } else {
                    Log::warning('Gagal fetch detail sales invoice untuk retur penjualan', [
                        'faktur_penjualan_id' => $returPenjualan->faktur_penjualan_id,
                        'status' => $invoiceResponse->status(),
                    ]);
                }
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $errorMessage = "Data retur penjualan dengan nomor {$no_retur} tidak ditemukan.";
            Log::error('Retur penjualan tidak ditemukan: ' . $e->getMessage(), ['no_retur' => $no_retur]);

            $dataToCache = [
                'returPenjualan'          => null,
                'accurateDetail'          => null,
                'accurateDetailItems'     => [],
                'accurateReferenceDetail' => null,
                'referenceType'           => null,
                'pengirimanPesanan'       => null,
                'fakturPenjualanRef'      => null,
                'errorMessage'            => $errorMessage,
            ];
        } catch (Exception $e) {
            Log::error('Error saat mengambil data retur penjualan dari API Accurate: ' . $e->getMessage(), [
                'no_retur' => $no_retur,
                'retur_penjualan' => $returPenjualan ? $returPenjualan->toArray() : null,
            ]);

            if ($returPenjualan) {
                $errorMessage = "Gagal mengambil detail dari server Accurate. Silakan coba lagi.";
            } else {
                $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
            }

            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $returPenjualan        = $cachedData['returPenjualan'] ?? $returPenjualan;
                $accurateDetail        = $cachedData['accurateDetail'] ?? null;
                $accurateDetailItems   = $cachedData['accurateDetailItems'] ?? [];
                $accurateReferenceDetail = $cachedData['accurateReferenceDetail'] ?? null;
                $referenceType         = $cachedData['referenceType'] ?? null;
                $pengirimanPesanan     = $cachedData['pengirimanPesanan'] ?? null;
                $fakturPenjualanRef    = $cachedData['fakturPenjualanRef'] ?? null;
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail retur penjualan {$no_retur} dari cache karena API gagal.");
            } else {
                Log::warning("Tidak ada data cache tersedia sebagai fallback untuk no_retur: {$no_retur}");
            }

            $dataToCache = [
                'returPenjualan'          => $returPenjualan,
                'accurateDetail'          => $accurateDetail,
                'accurateDetailItems'     => $accurateDetailItems,
                'accurateReferenceDetail' => $accurateReferenceDetail,
                'referenceType'           => $referenceType,
                'pengirimanPesanan'       => $pengirimanPesanan,
                'fakturPenjualanRef'      => $fakturPenjualanRef,
                'errorMessage'            => $errorMessage,
            ];
        }

        if (!isset($dataToCache)) {
            $dataToCache = [
                'returPenjualan'          => $returPenjualan,
                'accurateDetail'          => $accurateDetail,
                'accurateDetailItems'     => $accurateDetailItems,
                'accurateReferenceDetail' => $accurateReferenceDetail,
                'referenceType'           => $referenceType,
                'pengirimanPesanan'       => $pengirimanPesanan,
                'fakturPenjualanRef'      => $fakturPenjualanRef,
                'errorMessage'            => $errorMessage,
            ];
        }

        if ($apiSuccess) {
            Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
            Log::info("Data detail retur penjualan {$no_retur} berhasil diambil dari API dan disimpan ke cache");
        }

        return view('retur_penjualan.detail', $dataToCache);
    }
}
