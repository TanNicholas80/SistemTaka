<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\KasirPenjualan;
use App\Models\PengirimanPesanan;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PengirimanPesananController extends Controller
{
    /**
     * Membangun URL API dari url_accurate branch
     * 
     * @param Branch $branch Branch yang aktif
     * @param string $endpoint Endpoint API (contoh: 'delivery-order/list.do')
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
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        // Cache key yang unik per branch
        $cacheKey = 'accurate_delivery_order_list_branch_' . $activeBranchId;
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache delivery order dihapus karena force_refresh');
        }

        $errorMessage = null;

        // Periksa apakah cache sudah ada
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $pengirimanPesanan = $cachedData['pengirimanPesanan'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data delivery order diambil dari cache');
            return view('pengiriman_pesanan.index', compact('pengirimanPesanan', 'errorMessage'));
        }

        // Initialize an empty array for delivery orders
        $pengirimanPesanan = [];
        $allDeliveryOrders = [];
        $apiSuccess = false;
        $hasApiError = false;

        // Selalu coba ambil dari API terlebih dahulu
        try {
            // Get API credentials from branch (auto-decrypted by model accessors)
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            // Define the API URL for listing delivery orders
            $listApiUrl = $this->buildApiUrl($branch, 'delivery-order/list.do');
            $data = [
                'sp.page' => 1,
                'sp.pageSize' => 20
            ];

            // Fetch delivery order IDs from the API
            $firstPageResponse = Http::withoutVerifying()->withHeaders([
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

                // Logging data list delivery order mentah dari Accurate
                Log::info('Accurate delivery order list first page response:', $responseData);

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allDeliveryOrders = $responseData['d'];

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
                                    $allDeliveryOrders = array_merge($allDeliveryOrders, $pageResponse['d']);
                                    Log::info("Accurate delivery order list page {$page} response processed");
                                }
                            } else {
                                Log::error("Failed to fetch page {$page}: " . $result['reason']);
                            }
                        }
                    }

                    // Setelah mendapatkan semua ID delivery order, ambil detail untuk masing-masing secara batch
                    $detailsResult = $this->fetchDeliveryOrderDetailsInBatches($allDeliveryOrders, $branch, $apiToken, $signature, $timestamp);
                    $pengirimanPesanan = $detailsResult['details'];
                    
                    // Cek jika ada error dari proses fetch detail
                    if ($detailsResult['has_error']) {
                        $hasApiError = true;
                    }
                    
                    $apiSuccess = true;
                    Log::info('Data delivery order dari API berhasil diambil');
                } else {
                    Log::warning('API list response does not contain expected data structure', [
                        'responseData' => $responseData
                    ]);
                    throw new \Exception('API response does not contain expected data structure');
                }
            } else {
                throw new \Exception('API list request failed with status: ' . $firstPageResponse->status());
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching delivery order list from API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $hasApiError = true;
        }

        // Set error message berdasarkan kondisi
        if ($hasApiError) {
            $errorMessage = 'Gagal memuat data dari server Accurate. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi dengan menekan tombol "Refresh Data".';
        }

        // Jika API gagal dan tidak ada data, coba gunakan cache sebagai fallback
        if (!$apiSuccess && empty($pengirimanPesanan)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $pengirimanPesanan = $cachedData['pengirimanPesanan'] ?? [];
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info('Data delivery order diambil dari cache karena API error');
            } else {
                if (is_null($errorMessage)) $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache tersedia.';
                Log::warning('Tidak ada cache tersedia, menampilkan data kosong');
            }
        }

        // Simpan data ke cache
        $dataToCache = [
            'pengirimanPesanan' => $pengirimanPesanan,
            'errorMessage' => $errorMessage
        ];

        Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
        Log::info('Data delivery order disimpan ke cache');

        return view('pengiriman_pesanan.index', compact('pengirimanPesanan', 'errorMessage'));
    }

    /**
     * Mengambil detail delivery order dalam batch untuk mengoptimalkan performa
     */
    private function fetchDeliveryOrderDetailsInBatches($deliveryOrders, $branch, $apiToken, $signature, $timestamp, $batchSize = 5)
    {
        $deliveryOrderDetails = [];
        $batches = array_chunk($deliveryOrders, $batchSize);
        $hasApiError = false; // Flag error untuk fungsi ini

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $order) {
                $detailUrl = $this->buildApiUrl($branch, 'delivery-order/detail.do?id=' . $order['id']);
                $promises[$order['id']] = $client->getAsync($detailUrl, [
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
            foreach ($results as $orderId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $deliveryOrderDetails[] = $detailResponse['d'];
                        Log::info("Delivery order detail fetched for ID: {$orderId}");
                    }
                } else {
                    $reason = $result['reason'];
                    Log::error("Failed to fetch delivery order detail for ID {$orderId}: " . $reason->getMessage());
                    
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
            'details' => $deliveryOrderDetails,
            'has_error' => $hasApiError
        ];
    }

    public function create(Request $request)
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
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        // Get sales orders directly from API without caching
        $salesOrders = $this->getSalesOrdersFromAccurate($branch);

        $selectedTanggal = '';
        $formReadonly = false;
        $no_pengiriman = PengirimanPesanan::generateNoPengiriman();

        return view('pengiriman_pesanan.create', compact('selectedTanggal', 'formReadonly', 'no_pengiriman', 'salesOrders'));
    }

    /**
     * Get sales orders data from Accurate API with caching and parallel processing
     */
    private function getSalesOrdersFromAccurate(Branch $branch)
    {
        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        try {
            // Ambil semua sales orders dengan pagination handling
            $salesOrders = $this->fetchAllSalesOrders($apiToken, $signature, $timestamp, $branch);

            Log::info('Sales orders data diambil dari API', ['count' => count($salesOrders)]);

            return $salesOrders;
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching sales orders from Accurate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Fetch all sales orders with parallel processing dan pagination handling
     */
    /**
     * Fetch all sales orders with parallel processing dan pagination handling
     */
    private function fetchAllSalesOrders($apiToken, $signature, $timestamp, Branch $branch)
    {
        $salesOrderApiUrl = $this->buildApiUrl($branch, 'sales-order/list.do');
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
        ];

        $firstPageResponse = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($salesOrderApiUrl, $data);

        $allSalesOrderIds = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();
            Log::info('Accurate SO list response for DO (IDs only):', ['response' => $responseData]);

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allSalesOrderIds = $responseData['d'];

                // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);

                // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client();

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $promises[$page] = $client->getAsync($salesOrderApiUrl, [
                            'verify' => false,
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ],
                            'query' => [
                                'sp.page' => $page,
                                'sp.pageSize' => 20,
                            ]
                        ]);
                    }

                    $results = Utils::settle($promises)->wait();

                    foreach ($results as $page => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $pageResponse = json_decode($result['value']->getBody(), true);
                            if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                $allSalesOrderIds = array_merge($allSalesOrderIds, $pageResponse['d']);
                            }
                        } else {
                            Log::error("Failed to fetch sales orders page {$page}: " . $result['reason']);
                        }
                    }
                }
            }
        } else {
            Log::error('Failed to fetch sales orders from Accurate API', [
                'status' => $firstPageResponse->status(),
                'body' => $firstPageResponse->body(),
            ]);
            return [];
        }

        // Ambil detail untuk setiap SO ID secara paralel dalam batch
        $batchResult = $this->fetchSalesOrderDetailsInBatches($allSalesOrderIds, $branch, $apiToken, $signature, $timestamp);
        $allSalesOrders = $batchResult['details'];

        // Get all existing penjualan_id from local database
        $existingPenjualanIds = PengirimanPesanan::pluck('penjualan_id')
            ->toArray();

        // Filter out sales orders that already exist in local database
        $salesOrders = array_filter($allSalesOrders, function ($salesOrder) use ($existingPenjualanIds) {
            return !in_array($salesOrder['number'], $existingPenjualanIds);
        });

        // Reset array indexes after filtering
        $salesOrders = array_values($salesOrders);

        Log::info('Sales Orders fully detailed and filtered:', [
            'total_ids_from_api' => count($allSalesOrderIds),
            'total_detailed' => count($allSalesOrders),
            'existing_in_database' => count($existingPenjualanIds),
            'filtered_available' => count($salesOrders)
        ]);

        return $salesOrders;
    }

    /**
     * Mengambil detail sales order dalam batch untuk mengoptimalkan performa
     */
    private function fetchSalesOrderDetailsInBatches($salesOrders, $branch, $apiToken, $signature, $timestamp, $batchSize = 10)
    {
        $salesOrderDetails = [];
        $batches = array_chunk($salesOrders, $batchSize);
        $hasApiError = false;

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $order) {
                if (!isset($order['id'])) continue;
                
                $detailUrl = $this->buildApiUrl($branch, 'sales-order/detail.do?id=' . $order['id']);
                $promises[$order['id']] = $client->getAsync($detailUrl, [
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ]
                ]);
            }

            if (empty($promises)) continue;

            $results = Utils::settle($promises)->wait();

            foreach ($results as $orderId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $salesOrderDetails[] = $detailResponse['d'];
                    }
                } else {
                    Log::error("Failed to fetch sales order detail for ID {$orderId}: " . $result['reason']->getMessage());
                    $hasApiError = true;
                }
            }

            // Small delay to prevent rate limits
            usleep(100000); // 100ms
        }

        return [
            'details' => $salesOrderDetails,
            'has_error' => $hasApiError
        ];
    }

    public function getCustomerByAjax($number)
    {
        // Validasi active_branch session
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada cabang yang aktif. Silakan pilih cabang terlebih dahulu.'
            ], 400);
        }

        // Ambil data Branch
        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Data cabang tidak ditemukan.'
            ], 404);
        }

        // Validasi credentials API Accurate dari Branch
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial API Accurate untuk cabang ini belum diatur.'
            ], 400);
        }

        // Get API credentials from branch (auto-decrypted by model accessors)
        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Define the API URL for sales order detail
        $detailApiUrl = $this->buildApiUrl($branch, 'sales-order/detail.do');

        try {
            // Make API request to get sales order detail
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type' => 'application/json',
            ])->get($detailApiUrl, [
                'number' => $number
            ]);

            // Log the response for debugging
            Log::info('Sales Order Detail API Response for number ' . $number . ':', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                $responseData = $response->json();

                // Check if the response contains the 'd' key
                if (isset($responseData['d'])) {
                    $salesOrderDetail = $responseData['d'];

                    // Extract customer information
                    $customerData = [
                        'success' => true,
                        'detailItems' => $salesOrderDetail['detailItem'] ?? [],
                        'customerNo' => $salesOrderDetail['customer']['customerNo'] ?? null,
                        'customerName' => $salesOrderDetail['customer']['name'] ?? null,
                        'transDate' => $salesOrderDetail['transDate'] ?? null, // Added transDate for validation
                        'message' => 'Customer data retrieved successfully'
                    ];

                    return response()->json($customerData);
                } else {
                    Log::warning('Sales Order Detail API response does not contain expected data structure', [
                        'responseData' => $responseData,
                        'number' => $number
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid response structure from API'
                    ], 400);
                }
            } else {
                Log::error('Sales Order Detail API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'number' => $number
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve customer data from API'
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching customer data: ' . $e->getMessage(), [
                'number' => $number,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving customer data'
            ], 500);
        }
    }

    public function store(Request $request)
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
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        $validator = Validator::make($request->all(), [
            'penjualan_id'          => 'required|string|max:255|unique:pengiriman_pesanans,penjualan_id',
            'pelanggan_id'          => 'required|string|max:255',
            'tanggal_pengiriman'    => 'required|date',
            'no_pengiriman'         => 'required|string|max:255|unique:pengiriman_pesanans,no_pengiriman',
            'detailItems'           => 'required|array|min:1',
            'detailItems.*.kode'      => 'required|string',
            'detailItems.*.kuantitas' => 'required|numeric|min:0',
            'detailItems.*.harga'     => 'required|numeric|min:0',
            'detailItems.*.diskon'    => 'nullable|numeric|min:0',
        ], [
            // Penjualan ID validation messages
            'penjualan_id.required' => 'Nomor Pesanan Penjualan (NPJ) wajib diisi.',
            'penjualan_id.unique' => 'Nomor Pesanan Penjualan (NPJ) sudah digunakan.',

            // Pelanggan ID validation messages
            'pelanggan_id.required' => 'Pelanggan wajib diisi.',

            // Tanggal Pengiriman validation messages
            'tanggal_pengiriman.required' => 'Tanggal Pengiriman wajib diisi.',
            'tanggal_pengiriman.date' => 'Format tanggal tidak valid.',

            // No Pengiriman validation messages
            'no_pengiriman.required' => 'Nomor Pengiriman wajib diisi.',
            'no_pengiriman.unique' => 'Nomor Pengiriman sudah digunakan.',

            // Detail items validation messages
            'detailItems.required' => 'Detail item wajib diisi.',
            'detailItems.min' => 'Minimal harus ada 1 item yang diinputkan.',

            // Detail items field validation messages
            'detailItems.*.kode.required' => 'Kode item wajib diisi.',
            'detailItems.*.kuantitas.required' => 'Kuantitas item wajib diisi.',
            'detailItems.*.kuantitas.min' => 'Kuantitas item tidak boleh kurang dari 0.',
            'detailItems.*.harga.required' => 'Harga item wajib diisi.',
            'detailItems.*.harga.min' => 'Harga item tidak boleh kurang dari 0.',
            'detailItems.*.diskon.numeric' => 'Diskon item harus berupa angka.',
            'detailItems.*.diskon.min' => 'Diskon item tidak boleh kurang dari 0.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', 'Data yang dikirim tidak valid.');
        }

        try {
            $validatedData = $validator->validated();

            // Get API credentials from branch (auto-decrypted by model accessors)
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            // Query KasirPenjualan berdasarkan npj dan kode_customer
            $kasirPenjualan = KasirPenjualan::where('npj', $validatedData['penjualan_id'])
                ->where('kode_customer', $branch->customer_id)
                ->first();

            if (!$kasirPenjualan) {
                return back()->withInput()->with('error', 'Data Kasir Penjualan dengan NPJ ' . $validatedData['penjualan_id'] . ' tidak ditemukan.');
            }

            Log::info('KasirPenjualan data found:', [
                'npj' => $kasirPenjualan->npj,
                'customer' => $kasirPenjualan->customer,
                'alamat' => $kasirPenjualan->alamat,
                'syarat_bayar' => $kasirPenjualan->syarat_bayar,
                'kena_pajak' => $kasirPenjualan->kena_pajak,
                'total_termasuk_pajak' => $kasirPenjualan->total_termasuk_pajak,
                'diskon_keseluruhan' => $kasirPenjualan->diskon_keseluruhan
            ]);

            // Fallback untuk mendapatkan alamat jika kosong dari database
            $alamatFallback = $kasirPenjualan->alamat;
            if (empty($alamatFallback)) {
                Log::info('Alamat kosong dari KasirPenjualan, melakukan fallback ke API sales-order/detail.do', [
                    'penjualan_id' => $validatedData['penjualan_id']
                ]);

                try {
                    // Buat instance HTTP client untuk sales order detail
                    $salesOrderResponse = Http::withoutVerifying()->withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($this->buildApiUrl($branch, 'sales-order/detail.do'), [
                        'number' => $validatedData['penjualan_id']
                    ]);

                    Log::info('Sales Order Detail API Response for fallback:', [
                        'status' => $salesOrderResponse->status(),
                        'successful' => $salesOrderResponse->successful()
                    ]);

                    if ($salesOrderResponse->successful() && isset($salesOrderResponse->json()['d'])) {
                        $salesOrderDetail = $salesOrderResponse->json()['d'];

                        // Ambil alamat dari customer di sales order detail
                        if (isset($salesOrderDetail['toAddress']) && !empty($salesOrderDetail['toAddress'])) {
                            $alamatFallback = $salesOrderDetail['toAddress'];
                            Log::info('Alamat berhasil diambil dari sales order detail:', [
                                'alamat' => $alamatFallback
                            ]);
                        } else {
                            Log::warning('Alamat tidak ditemukan di sales order detail customer');
                        }
                    } else {
                        Log::warning('Gagal mengambil sales order detail untuk fallback alamat', [
                            'status' => $salesOrderResponse->status(),
                            'body' => $salesOrderResponse->body()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Exception saat fallback alamat dari sales order detail: ' . $e->getMessage(), [
                        'penjualan_id' => $validatedData['penjualan_id'],
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // --- VALIDASI TANGGAL (SERVER SIDE) ---
            // Ambil info detail SO jika belum ada (dari fallback alamat di atas mungkin sudah ada, tapi mari pastikan)
            $soTransDate = null;
            try {
                $soDetailResponse = Http::withoutVerifying()->withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'X-Api-Signature' => $signature,
                    'X-Api-Timestamp' => $timestamp,
                ])->get($this->buildApiUrl($branch, 'sales-order/detail.do'), [
                    'number' => $validatedData['penjualan_id']
                ]);

                if ($soDetailResponse->successful() && isset($soDetailResponse->json()['d'])) {
                    $soTransDate = $soDetailResponse->json()['d']['transDate']; // Format DD/MM/YYYY
                }
            } catch (\Exception $e) {
                Log::error('Gagal mengambil tanggal SO untuk validasi: ' . $e->getMessage());
            }

            if ($soTransDate) {
                $soDateObj = Carbon::createFromFormat('d/m/Y', $soTransDate);
                $doDateObj = Carbon::parse($validatedData['tanggal_pengiriman']);

                if ($doDateObj->lt($soDateObj)) {
                    return back()->withInput()->with('error', "Tanggal Pengiriman Pesanan tidak dapat lebih kecil dari tanggal Pesanan Penjualan {$validatedData['penjualan_id']}.");
                }
            }

            // Format detail items untuk API Accurate
            $detailItemsForAccurate = [];
            foreach ($validatedData['detailItems'] as $item) {
                $accurateItem = [
                    "itemNo"    => $item['kode'],
                    "quantity"  => $item['kuantitas'],
                    "unitPrice" => $item['harga'],
                    "salesOrderNumber" => $validatedData['penjualan_id'],
                ];

                // --- LOGIKA UTAMA: Kondisi Diskon ---
                // Cek apakah ada diskon dan nilainya lebih dari 0
                if (isset($item['diskon']) && $item['diskon'] > 0) {
                    $diskon = (float) $item['diskon'];

                    // Jika diskon antara 0-100, anggap sebagai PERSENTASE
                    if ($diskon > 0 && $diskon <= 100) {
                        $accurateItem['itemDiscPercent'] = $diskon;
                    }
                    // Jika diskon di atas 100, anggap sebagai NOMINAL
                    else {
                        $accurateItem['itemCashDiscount'] = $diskon;
                    }
                }

                $detailItemsForAccurate[] = $accurateItem;
            }

            // Siapkan data untuk API Accurate dengan mengambil data dari Sales Order
            $postDataForAccurate = [
                "customerNo"        => $validatedData['pelanggan_id'],
                "transDate"         => date('d/m/Y', strtotime($validatedData['tanggal_pengiriman'])),
                "number"            => $validatedData['no_pengiriman'],
                "detailItem"        => $detailItemsForAccurate,
            ];

            // Set alamat dari KasirPenjualan atau fallback dari sales order detail
            if (!empty($alamatFallback)) {
                $postDataForAccurate['toAddress'] = $alamatFallback;
            }

            if (!empty($kasirPenjualan->keterangan)) {
                $postDataForAccurate['description'] = $kasirPenjualan->keterangan;
            }

            // Set syarat bayar dari KasirPenjualan atau default C.O.D
            $syaratBayar = !empty($kasirPenjualan->syarat_bayar) ? $kasirPenjualan->syarat_bayar : 'C.O.D';
            $postDataForAccurate['paymentTermName'] = $syaratBayar;

            // Set diskon keseluruhan dari KasirPenjualan
            $diskonKeseluruhan = $kasirPenjualan->diskon_keseluruhan ?? 0;
            if ($diskonKeseluruhan > 0) {
                $diskonKeseluruhan = (float) $diskonKeseluruhan;

                // Jika diskon antara 0-100, anggap sebagai PERSENTASE
                if ($diskonKeseluruhan > 0 && $diskonKeseluruhan <= 100) {
                    $postDataForAccurate['cashDiscPercent'] = $diskonKeseluruhan;
                }
                // Jika diskon di atas 100, anggap sebagai NOMINAL
                else {
                    $postDataForAccurate['cashDiscount'] = $diskonKeseluruhan;
                }
            }

            // Set kena pajak dari KasirPenjualan
            if (isset($kasirPenjualan->kena_pajak)) {
                $postDataForAccurate['taxable'] = $kasirPenjualan->kena_pajak;
            }

            // Set total termasuk pajak dari KasirPenjualan
            if (isset($kasirPenjualan->total_termasuk_pajak)) {
                $postDataForAccurate['inclusiveTax'] = $kasirPenjualan->total_termasuk_pajak;
            }

            Log::info('PostDataForAccurate prepared for delivery order:', $postDataForAccurate);

            // Kirim data ke API Accurate (delivery-order endpoint)
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type'  => 'application/json',
            ])->post($this->buildApiUrl($branch, 'delivery-order/save.do'), $postDataForAccurate);

            // Validasi response dari API Accurate
            if (!$response->successful()) {
                // Jika HTTP status tidak 2xx
                return back()->withInput()->with('error', 'Gagal mengirim data ke Accurate API. HTTP Status: ' . $response->status());
            }

            // Decode response body
            $responseData = $response->json();

            // Cek apakah response mengandung error dari Accurate
            if (isset($responseData['s']) && $responseData['s'] === false) {
                // Jika API Accurate mengembalikan status error
                return back()->withInput()->with('error', 'Accurate API mengembalikan error: ' . ($responseData['m'] ?? 'Unknown error'));
            }

            // Jika API Accurate berhasil, simpan ke database lokal
            $pengirimanPesanan = PengirimanPesanan::create([
                'penjualan_id' => $validatedData['penjualan_id'],
                'pelanggan_id' => $validatedData['pelanggan_id'],
                'tanggal_pengiriman' => $validatedData['tanggal_pengiriman'],
                'no_pengiriman' => $validatedData['no_pengiriman'],
                'alamat' => $alamatFallback,
                'keterangan' => $kasirPenjualan->keterangan,
                'syarat_bayar' => $kasirPenjualan->syarat_bayar,
                'kena_pajak' => $kasirPenjualan->kena_pajak,
                'total_termasuk_pajak' => $kasirPenjualan->total_termasuk_pajak,
                'diskon_keseluruhan' => $kasirPenjualan->diskon_keseluruhan,
                'kode_customer' => $branch->customer_id,
            ]);

            // Clear related cache per branch
            Cache::forget('accurate_delivery_order_list_branch_' . $activeBranchId);
            Cache::forget('accurate_sales_order_details_branch_' . $activeBranchId);

            // Redirect ke view index dengan success message
            return redirect()->route('pengiriman_pesanan.index')
                ->with('success', 'Data berhasil disimpan ke Accurate dan database lokal.')
                ->with('pengiriman_pesanan', $pengirimanPesanan);
        } catch (Exception $e) {
            // Handle any exceptions (network issues, database errors, etc.)
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function show($no_pengiriman, Request $request)
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
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        // Cache key yang unik per branch
        $cacheKey = 'pengiriman_pesanan_detail_' . $no_pengiriman . '_branch_' . $activeBranchId;
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $pengirimanPesanan = null;
        $accurateDeliveryOrderDetail = null;
        $accurateDetailItems = [];
        $accurateSalesOrderDetail = null;
        $mergedItems = [];
        $detailBarcodeMappings = [];
        $kasirPenjualan = null;

        try {
            // Ambil data pengiriman pesanan filtered by kode_customer
            $pengirimanPesanan = PengirimanPesanan::where('no_pengiriman', $no_pengiriman)
                ->where('kode_customer', $branch->customer_id)
                ->firstOrFail();

            Log::info("PengirimanPesanan found:", [
                'no_pengiriman' => $pengirimanPesanan->no_pengiriman,
                'penjualan_id' => $pengirimanPesanan->penjualan_id,
                'pelanggan_id' => $pengirimanPesanan->pelanggan_id,
            ]);

            // Selalu coba ambil dari API terlebih dahulu
            // Ambil token dari branch (auto-decrypted by model accessors)
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            // Buat instance HTTP client sekali saja untuk digunakan berulang kali
            $httpClient = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ]);

            // Ambil data delivery order dari Accurate API
            if ($pengirimanPesanan->no_pengiriman) {
                Log::info("Fetching delivery order from Accurate API:", ['no_pengiriman' => $pengirimanPesanan->no_pengiriman]);

                $deliveryOrderResponse = $httpClient->get($this->buildApiUrl($branch, 'delivery-order/detail.do'), [
                    'number' => $pengirimanPesanan->no_pengiriman,
                ]);

                Log::info("Delivery order API response:", [
                    'status' => $deliveryOrderResponse->status(),
                    'successful' => $deliveryOrderResponse->successful(),
                    'has_d_key' => isset($deliveryOrderResponse->json()['d'])
                ]);

                if ($deliveryOrderResponse->successful() && isset($deliveryOrderResponse->json()['d'])) {
                    $accurateDeliveryOrderDetail = $deliveryOrderResponse->json()['d'];
                    $accurateDetailItems = $accurateDeliveryOrderDetail['detailItem'] ?? [];
                    Log::info("Delivery order data retrieved successfully");
                } else {
                    if ($deliveryOrderResponse->status() == 404) {
                        $errorMessage = "Delivery order dengan nomor {$no_pengiriman} tidak ditemukan.";
                    } else {
                        $errorMessage = "Gagal mengambil data delivery order dari server. Silakan coba lagi.";
                    }
                    Log::error('Failed to get delivery order from Accurate API', [
                        'status' => $deliveryOrderResponse->status(),
                        'body' => $deliveryOrderResponse->body()
                    ]);
                }
            }

            // Ambil detail sales order dari Accurate API
            if ($pengirimanPesanan->penjualan_id) {
                Log::info("Fetching sales order from Accurate API:", ['penjualan_id' => $pengirimanPesanan->penjualan_id]);

                $salesOrderResponse = $httpClient->get($this->buildApiUrl($branch, 'sales-order/detail.do'), [
                    'number' => $pengirimanPesanan->penjualan_id,
                ]);

                Log::info("Sales order API response:", [
                    'status' => $salesOrderResponse->status(),
                    'successful' => $salesOrderResponse->successful(),
                    'has_d_key' => isset($salesOrderResponse->json()['d'])
                ]);

                if ($salesOrderResponse->successful() && isset($salesOrderResponse->json()['d'])) {
                    $accurateSalesOrderDetail = $salesOrderResponse->json()['d'];
                    Log::info("Sales order data retrieved successfully");
                } else {
                    Log::warning("Failed to get sales order from Accurate API", [
                        'status' => $salesOrderResponse->status(),
                        'body' => $salesOrderResponse->body()
                    ]);
                }
            }

            // Ambil data kasir penjualan filtered by kode_customer
            if ($pengirimanPesanan->penjualan_id) {
                Log::info("Searching KasirPenjualan with NPJ:", ['npj' => $pengirimanPesanan->penjualan_id]);

                $kasirPenjualan = KasirPenjualan::with(['detailItems' => function ($query) {
                    $query->with('approvalStock'); // Eager load approval stock untuk mengurangi N+1 query
                }])
                    ->where('npj', $pengirimanPesanan->penjualan_id)
                    ->where('kode_customer', $branch->customer_id)
                    ->first();

                if ($kasirPenjualan) {
                    Log::info("KasirPenjualan found:", [
                        'npj' => $kasirPenjualan->npj,
                        'tanggal' => $kasirPenjualan->tanggal,
                        'customer' => $kasirPenjualan->customer,
                        'detail_items_count' => $kasirPenjualan->detailItems->count()
                    ]);
                } else {
                    Log::warning("KasirPenjualan NOT found for NPJ:", ['npj' => $pengirimanPesanan->penjualan_id]);

                    // Coba cari dengan query yang lebih fleksibel
                    $allKasirPenjualan = KasirPenjualan::pluck('npj', 'id');
                    Log::info("Available NPJs in KasirPenjualan:", $allKasirPenjualan->toArray());
                }
            } else {
                Log::warning("penjualan_id is empty in PengirimanPesanan");
            }

            if ($kasirPenjualan && $kasirPenjualan->detailItems->count() > 0) {
                // Helper function untuk menghitung total harga dengan diskon
                $calculateTotalWithDiscount = function ($harga, $qty, $diskon) {
                    $subtotal = $harga * $qty;

                    if ($diskon && $diskon > 0) {
                        // Jika diskon antara 0-100, anggap sebagai persentase
                        if ($diskon <= 100) {
                            return $subtotal - ($subtotal * ($diskon / 100));
                        }
                        // Jika diskon di atas 100, anggap sebagai nominal
                        else {
                            return max(0, $subtotal - $diskon); // Pastikan tidak negatif
                        }
                    }

                    return $subtotal;
                };

                // Proses data untuk merged items dan detail barcode mappings
                foreach ($kasirPenjualan->detailItems as $detailItem) {
                    // Hitung total harga sebelum dan sesudah diskon
                    $subtotalSebelumDiskon = $detailItem->harga * $detailItem->qty;
                    $totalHargaSetelahDiskon = $calculateTotalWithDiscount(
                        $detailItem->harga,
                        $detailItem->qty,
                        $detailItem->diskon
                    );
                    $nominalDiskon = $subtotalSebelumDiskon - $totalHargaSetelahDiskon;

                    // Gunakan relasi yang sudah di-eager load
                    $approvalStock = $detailItem->approvalStock;
                    $itemName = $approvalStock ? $approvalStock->nama : 'Item dengan barcode: ' . $detailItem->barcode;

                    // Simpan mapping untuk detail barcode
                    $detailBarcodeMappings[] = [
                        'barcode' => $detailItem->barcode,
                        'nama' => $itemName,
                        'qty' => $detailItem->qty,
                        'harga' => $detailItem->harga,
                        'diskon' => $detailItem->diskon,
                        'subtotal_sebelum_diskon' => $subtotalSebelumDiskon,
                        'nominal_diskon' => $nominalDiskon,
                        'total_harga' => $totalHargaSetelahDiskon,
                        'approval_stock' => $approvalStock
                    ];

                    // Merge berdasarkan nama yang sama
                    if (isset($mergedItems[$itemName])) {
                        // Jika nama sudah ada, merge kuantitas dan total harga
                        $mergedItems[$itemName]['total_qty'] += $detailItem->qty;
                        $mergedItems[$itemName]['subtotal_sebelum_diskon'] += $subtotalSebelumDiskon;
                        $mergedItems[$itemName]['total_nominal_diskon'] += $nominalDiskon;
                        $mergedItems[$itemName]['total_harga'] += $totalHargaSetelahDiskon;
                        $mergedItems[$itemName]['barcodes'][] = $detailItem->barcode;
                    } else {
                        // Jika nama belum ada, buat entry baru
                        $mergedItems[$itemName] = [
                            'nama' => $itemName,
                            'total_qty' => $detailItem->qty,
                            'harga_satuan' => $detailItem->harga,
                            'diskon' => $detailItem->diskon, // Ambil diskon dari yang pertama
                            'subtotal_sebelum_diskon' => $subtotalSebelumDiskon,
                            'total_nominal_diskon' => $nominalDiskon,
                            'total_harga' => $totalHargaSetelahDiskon,
                            'barcodes' => [$detailItem->barcode],
                            'approval_stock' => $approvalStock
                        ];
                    }
                }

                // Hitung persentase diskon efektif untuk merged items
                foreach ($mergedItems as $key => &$item) {
                    if ($item['subtotal_sebelum_diskon'] > 0) {
                        $item['persentase_diskon_efektif'] = ($item['total_nominal_diskon'] / $item['subtotal_sebelum_diskon']) * 100;
                    } else {
                        $item['persentase_diskon_efektif'] = 0;
                    }
                }
            }

            // Konversi array asosiatif menjadi array numerik untuk view
            $mergedItems = array_values($mergedItems);

            // Log final data before sending to view
            Log::info("Final data summary before sending to view:", [
                'pengirimanPesanan_exists' => $pengirimanPesanan ? true : false,
                'kasirPenjualan_exists' => $kasirPenjualan ? true : false,
                'kasirPenjualan_npj' => $kasirPenjualan ? $kasirPenjualan->npj : null,
                'kasirPenjualan_tanggal' => $kasirPenjualan ? $kasirPenjualan->tanggal : null,
                'accurateDeliveryOrderDetail_exists' => $accurateDeliveryOrderDetail ? true : false,
                'accurateSalesOrderDetail_exists' => $accurateSalesOrderDetail ? true : false,
                'mergedItems_count' => count($mergedItems),
                'detailBarcodeMappings_count' => count($detailBarcodeMappings)
            ]);

            $dataToCache = [
                'pengirimanPesanan' => $pengirimanPesanan,
                'accurateDeliveryOrderDetail' => $accurateDeliveryOrderDetail,
                'accurateDetailItems' => $accurateDetailItems,
                'accurateSalesOrderDetail' => $accurateSalesOrderDetail,
                'mergedItems' => $mergedItems,
                'detailBarcodeMappings' => $detailBarcodeMappings,
                'kasirPenjualan' => $kasirPenjualan,
                'errorMessage' => $errorMessage
            ];

            // Jika berhasil mendapatkan data dari API, simpan ke cache
            Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
            Log::info("Data detail pengiriman pesanan {$no_pengiriman} berhasil diambil dari API dan disimpan ke cache");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $errorMessage = "Data pengiriman pesanan dengan nomor {$no_pengiriman} tidak ditemukan.";
            Log::error('Pengiriman pesanan tidak ditemukan: ' . $e->getMessage(), ['no_pengiriman' => $no_pengiriman]);
            
            $dataToCache = [
                'pengirimanPesanan' => null,
                'accurateDeliveryOrderDetail' => null,
                'accurateDetailItems' => [],
                'accurateSalesOrderDetail' => null,
                'mergedItems' => [],
                'detailBarcodeMappings' => [],
                'kasirPenjualan' => null,
                'errorMessage' => $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching pengiriman pesanan detail from API', [
                'no_pengiriman' => $no_pengiriman,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($pengirimanPesanan) {
                $errorMessage = "Gagal mengambil detail dari server Accurate. Silakan coba lagi.";
            } else {
                $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
            }

            // Jika API error, coba ambil data dari cache sebagai fallback
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $pengirimanPesanan = $cachedData['pengirimanPesanan'] ?? $pengirimanPesanan;
                $accurateDeliveryOrderDetail = $cachedData['accurateDeliveryOrderDetail'] ?? null;
                $accurateDetailItems = $cachedData['accurateDetailItems'] ?? [];
                $accurateSalesOrderDetail = $cachedData['accurateSalesOrderDetail'] ?? null;
                $mergedItems = $cachedData['mergedItems'] ?? [];
                $detailBarcodeMappings = $cachedData['detailBarcodeMappings'] ?? [];
                $kasirPenjualan = $cachedData['kasirPenjualan'] ?? null;
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail pengiriman pesanan {$no_pengiriman} dari cache karena API gagal.");
            } else {
                Log::warning("Tidak ada data cache tersedia sebagai fallback untuk no_pengiriman: {$no_pengiriman}");
            }

            $dataToCache = [
                'pengirimanPesanan' => $pengirimanPesanan,
                'accurateDeliveryOrderDetail' => $accurateDeliveryOrderDetail,
                'accurateDetailItems' => $accurateDetailItems,
                'accurateSalesOrderDetail' => $accurateSalesOrderDetail,
                'mergedItems' => $mergedItems,
                'detailBarcodeMappings' => $detailBarcodeMappings,
                'kasirPenjualan' => $kasirPenjualan,
                'errorMessage' => $errorMessage
            ];
        }

        return view('pengiriman_pesanan.detail', $dataToCache);
    }
}
