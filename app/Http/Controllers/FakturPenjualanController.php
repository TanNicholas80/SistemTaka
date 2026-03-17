<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\FakturPenjualan;
use App\Models\KasirPenjualan;
use App\Models\PengirimanPesanan;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FakturPenjualanController extends Controller
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
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        // Cache key yang unik per branch
        $cacheKey = 'accurate_faktur_penjualan_list_branch_' . $activeBranchId;
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache faktur penjualan dihapus karena force_refresh');
        }

        $errorMessage = null;

        // Periksa apakah cache sudah ada
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $fakturPenjualan = $cachedData['fakturPenjualan'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data faktur penjualan diambil dari cache');
            return view('faktur_penjualan.index', compact('fakturPenjualan', 'errorMessage'));
        }

        // Get API credentials from branch (auto-decrypted by model accessors)
        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Define the API URL for listing sales invoices
        $listApiUrl = $baseUrl . '/sales-invoice/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20
        ];

        // Initialize an empty array for sales invoices
        $fakturPenjualan = [];
        $allSalesInvoices = [];
        $apiSuccess = false;
        $hasApiError = false;

        // Selalu coba ambil data dari API terlebih dahulu
        try {
            // Fetch sales invoice IDs from the API
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

                // Logging data list faktur penjualan mentah dari Accurate
                Log::info('Accurate Faktur penjualan list first page response:', $responseData);

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allSalesInvoices = $responseData['d'];

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
                                    $allSalesInvoices = array_merge($allSalesInvoices, $pageResponse['d']);
                                    Log::info("Accurate Faktur penjualan list page {$page} response processed");
                                }
                            } else {
                                Log::error("Failed to fetch page {$page}: " . $result['reason']);
                            }
                        }
                    }

                    // Setelah mendapatkan semua ID faktur penjualan, ambil detail untuk masing-masing secara batch
                    $detailsResult = $this->fetchSalesInvoiceDetailsInBatches($allSalesInvoices, $apiToken, $signature, $timestamp, $baseUrl);
                    $fakturPenjualan = $detailsResult['details'];
                    
                    // Cek jika ada error dari proses fetch detail
                    if ($detailsResult['has_error']) {
                        $hasApiError = true;
                    }
                    
                    $apiSuccess = true;
                    Log::info('Data faktur penjualan dari API berhasil diambil');
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
        if (!$apiSuccess && empty($fakturPenjualan)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $fakturPenjualan = $cachedData['fakturPenjualan'] ?? [];
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info('Data faktur penjualan diambil dari cache karena API error');
            } else {
                if (is_null($errorMessage)) $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache tersedia.';
                Log::warning('Tidak ada cache tersedia, menampilkan data kosong');
            }
        }

        // Simpan data ke cache
        $dataToCache = [
            'fakturPenjualan' => $fakturPenjualan,
            'errorMessage' => $errorMessage
        ];

        Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
        Log::info('Data faktur penjualan disimpan ke cache');

        return view('faktur_penjualan.index', compact('fakturPenjualan', 'errorMessage'));
    }

    /**
     * Mengambil detail faktur penjualan dalam batch untuk mengoptimalkan performa
     */
    private function fetchSalesInvoiceDetailsInBatches($salesInvoices, $apiToken, $signature, $timestamp, $baseUrl, $batchSize = 5)
    {
        $salesInvoiceDetails = [];
        $batches = array_chunk($salesInvoices, $batchSize);
        $hasApiError = false; // Flag error untuk fungsi ini

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $invoice) {
                $detailUrl = $baseUrl . '/sales-invoice/detail.do?id=' . $invoice['id'];
                $promises[$invoice['id']] = $client->getAsync($detailUrl, [
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
                        $salesInvoiceDetails[] = $detailResponse['d'];
                        Log::info("Faktur penjualan detail fetched for ID: {$invoiceId}");
                    }
                } else {
                    $reason = $result['reason'];
                    Log::error("Failed to fetch faktur penjualan detail for ID {$invoiceId}: " . $reason->getMessage());
                    
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
            'details' => $salesInvoiceDetails,
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

        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');

        // Get delivery orders in real-time from Accurate API
        $deliveryOrders = $this->getDeliveryOrdersFromAccurate($branch, $baseUrl);

        // Jika gagal mendapatkan data dari API, log error dan gunakan array kosong
        if (empty($deliveryOrders)) {
            Log::error('Gagal mendapatkan data delivery orders dari API Accurate');
            // Bisa menambahkan flash message untuk memberi tahu user
            session()->flash('warning', 'Gagal memuat data delivery orders. Silakan refresh halaman atau coba lagi.');
        }

        $selectedTanggal = date('Y-m-d');
        $formReadonly = false;
        $no_faktur = FakturPenjualan::generateNoFaktur();

        return view('faktur_penjualan.create', compact('selectedTanggal', 'formReadonly', 'no_faktur', 'deliveryOrders'));
    }

    /**
     * Get delivery orders data from Accurate API with caching and parallel processing
     */
    private function getDeliveryOrdersFromAccurate(Branch $branch, string $baseUrl)
    {
        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        try {
            Log::info('Mengambil data delivery orders dari API Accurate secara real-time');

            // Ambil semua delivery orders dengan pagination handling
            $deliveryOrders = $this->fetchAllDeliveryOrders($apiToken, $signature, $timestamp, $branch, $baseUrl);

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
     */
    private function fetchAllDeliveryOrders($apiToken, $signature, $timestamp, Branch $branch, string $baseUrl)
    {
        $deliveryOrderApiUrl = $baseUrl . '/delivery-order/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
            'fields' => 'number,customer'
        ];

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
                        $promises[$page] = $client->getAsync($deliveryOrderApiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ],
                            'query' => [
                                'sp.page' => $page,
                                'sp.pageSize' => 20,
                                'fields' => 'number,customer'
                            ]
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
        $existingPengirimanIds = FakturPenjualan::where('kode_customer', $branch->customer_id)
            ->pluck('pengiriman_id')
            ->toArray();

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
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Define the API URL for delivery order detail
        $detailApiUrl = $baseUrl . '/delivery-order/detail.do';

        try {
            // Make API request to get delivery order detail
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type' => 'application/json',
            ])->get($detailApiUrl, [
                'number' => $number
            ]);

            // Log the response for debugging
            Log::info('Delivery Order Detail API Response for number ' . $number . ':', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                $responseData = $response->json();

                // Check if the response contains the 'd' key
                if (isset($responseData['d'])) {
                    $deliveryOrderDetail = $responseData['d'];

                    // Extract customer information
                    $customerData = [
                        'success' => true,
                        'detailItems' => $deliveryOrderDetail['detailItem'] ?? [],
                        'customerNo' => $deliveryOrderDetail['customer']['customerNo'] ?? null,
                        'customerName' => $deliveryOrderDetail['customer']['name'] ?? null,
                        'message' => 'Customer data retrieved successfully'
                    ];

                    return response()->json($customerData);
                } else {
                    Log::warning('Delivery Order Detail API response does not contain expected data structure', [
                        'responseData' => $responseData,
                        'number' => $number
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid response structure from API'
                    ], 400);
                }
            } else {
                Log::error('Delivery Order Detail API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'number' => $number
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve customer data from API'
                ], $response->status());
            }
        } catch (Exception $e) {
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
            'pengiriman_id'          => 'required|string|max:255|unique:faktur_penjualans,pengiriman_id',
            'pelanggan_id'          => 'required|string|max:255',
            'tanggal_faktur'        => 'required|date',
            'no_faktur'             => 'required|string|max:255|unique:faktur_penjualans,no_faktur',
            'detailItems'           => 'required|array|min:1',
            'detailItems.*.kode'      => 'required|string',
            'detailItems.*.kuantitas' => 'required|string',
            'detailItems.*.harga'     => 'required|numeric|min:0',
            'detailItems.*.diskon'    => 'nullable|numeric|min:0',
        ], [
            // Penjualan ID validation messages
            'pengiriman_id.required' => 'Nomor Pengiriman (NPJ) wajib diisi.',
            'pengiriman_id.unique' => 'Nomor Pengiriman (NPJ) sudah digunakan.',

            // Pelanggan ID validation messages
            'pelanggan_id.required' => 'Pelanggan wajib diisi.',
            'pelanggan_id.date' => 'Format pelanggan tidak valid.',

            // Tanggal Faktur validation messages
            'tanggal_faktur.required' => 'Tanggal Faktur wajib diisi.',
            'tanggal_faktur.date' => 'Format tanggal tidak valid.',

            // Detail items validation messages
            'detailItems.required' => 'Detail item wajib diisi.',
            'detailItems.min' => 'Minimal harus ada 1 item yang Di Inputkan.',

            // Detail items barcode validation messages
            'detailItems.*.kode.required' => 'Kode item wajib diisi.',

            // Detail items kuantitas validation messages
            'detailItems.*.kuantitas.required' => 'Kuantitas item wajib diisi.',

            // Detail items harga validation messages
            'detailItems.*.harga.required' => 'Harga item wajib diisi.',
            'detailItems.*.harga.min' => 'Harga item tidak boleh kurang dari 0.',

            // Detail items diskon validation messages
            'detailItems.*.diskon.numeric' => 'Diskon item harus berupa angka.',
            'detailItems.*.diskon.min' => 'Diskon item tidak boleh kurang dari 0.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', 'Data yang dikirim tidak valid.');
        }

        // Database transaction
        DB::beginTransaction();

        try {
            $validatedData = $validator->validated();

            // Query PengirimanPesanan berdasarkan pengiriman_id dan kode_customer
            $pengirimanPesanan = PengirimanPesanan::where('no_pengiriman', $validatedData['pengiriman_id'])
                ->where('kode_customer', $branch->customer_id)
                ->first();

            if (!$pengirimanPesanan) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Data Pengiriman Pesanan dengan nomor ' . $validatedData['pengiriman_id'] . ' tidak ditemukan.');
            }

            Log::info('PengirimanPesanan data found:', [
                'no_pengiriman' => $pengirimanPesanan->no_pengiriman,
                'alamat' => $pengirimanPesanan->alamat,
                'syarat_bayar' => $pengirimanPesanan->syarat_bayar,
                'diskon_keseluruhan' => $pengirimanPesanan->diskon_keseluruhan,
                'kena_pajak' => $pengirimanPesanan->kena_pajak,
                'total_termasuk_pajak' => $pengirimanPesanan->total_termasuk_pajak
            ]);

            // Get API credentials from branch (auto-decrypted by model accessors)
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;
            $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            // Fallback untuk mendapatkan alamat jika kosong dari database
            $alamatFallback = $pengirimanPesanan->alamat;
            if (empty($alamatFallback) && $pengirimanPesanan->penjualan_id) {
                Log::info('Alamat kosong dari PengirimanPesanan, melakukan fallback ke API sales-order/detail.do', [
                    'penjualan_id' => $pengirimanPesanan->penjualan_id
                ]);

                try {
                    // Buat instance HTTP client untuk sales order detail
                    $salesOrderResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($baseUrl . '/sales-order/detail.do', [
                        'number' => $pengirimanPesanan->penjualan_id
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
                        'penjualan_id' => $pengirimanPesanan->penjualan_id,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $detailItemsForAccurate = [];
            foreach ($validatedData['detailItems'] as $item) {
                $accurateItem = [
                    "itemNo"    => $item['kode'],
                    "quantity"  => $item['kuantitas'],
                    "unitPrice" => $item['harga'],
                    "deliveryOrderNumber" => $validatedData['pengiriman_id'],
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

            // Siapkan data untuk API Accurate dengan mengecek nilai null (seperti di SalesController.php)
            $postDataForAccurate = [
                "customerNo"        => $validatedData['pelanggan_id'],
                "transDate"         => date('d/m/Y', strtotime($validatedData['tanggal_faktur'])),
                "number"            => $validatedData['no_faktur'],
                "detailItem"        => $detailItemsForAccurate,
            ];

            // Set syarat bayar: gunakan dari PengirimanPesanan atau default C.O.D jika kosong
            $syaratBayar = !empty($pengirimanPesanan->syarat_bayar) ? $pengirimanPesanan->syarat_bayar : 'C.O.D';
            $postDataForAccurate['paymentTermName'] = $syaratBayar;

            // Set alamat dari PengirimanPesanan atau fallback dari sales order detail
            if (!empty($alamatFallback)) {
                $postDataForAccurate['toAddress'] = $alamatFallback;
            }

            if (!empty($pengirimanPesanan->keterangan)) {
                $postDataForAccurate['description'] = $pengirimanPesanan->keterangan;
            }

            // Set diskon keseluruhan dari PengirimanPesanan
            if (!empty($pengirimanPesanan->diskon_keseluruhan)) {
                if (isset($pengirimanPesanan->diskon_keseluruhan) && $pengirimanPesanan->diskon_keseluruhan > 0) {
                    $diskonKeseluruhan = (float) $pengirimanPesanan->diskon_keseluruhan;

                    // Jika diskon antara 0-100, anggap sebagai PERSENTASE
                    if ($diskonKeseluruhan > 0 && $diskonKeseluruhan <= 100) {
                        $postDataForAccurate['cashDiscPercent'] = $diskonKeseluruhan;
                    }
                    // Jika diskon di atas 100, anggap sebagai NOMINAL
                    else {
                        $postDataForAccurate['cashDiscount'] = $diskonKeseluruhan;
                    }
                }
            }

            // Set kena pajak dari PengirimanPesanan
            if (isset($pengirimanPesanan->kena_pajak)) {
                $postDataForAccurate['taxable'] = $pengirimanPesanan->kena_pajak;
            }

            // Set total termasuk pajak dari PengirimanPesanan
            if (isset($pengirimanPesanan->total_termasuk_pajak)) {
                $postDataForAccurate['inclusiveTax'] = $pengirimanPesanan->total_termasuk_pajak;
            }

            Log::info('PostDataForAccurate prepared:', $postDataForAccurate);

            // 3. Kirim data ke API Accurate
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type'  => 'application/json',
            ])->post($baseUrl . '/sales-invoice/save.do', $postDataForAccurate);

            // 4. Validasi response dari API Accurate
            if (!$response->successful()) {
                DB::rollBack();
                // Jika HTTP status tidak 2xx
                return back()->withInput()->with('error', 'Gagal mengirim data ke Accurate API. HTTP Status: ' . $response->status());
            }

            // Decode response body
            $responseData = $response->json();

            // Cek apakah response mengandung error dari Accurate
            if (isset($responseData['s']) && $responseData['s'] === false) {
                DB::rollBack();
                // Jika API Accurate mengembalikan status error
                return back()->withInput()->with('error', 'Accurate API mengembalikan error: ' . ($responseData['m'] ?? 'Unknown error'));
            }

            // 5. Jika API Accurate berhasil, simpan ke database lokal
            $fakturPenjualan = FakturPenjualan::create([
                'pengiriman_id' => $validatedData['pengiriman_id'],
                'pelanggan_id' => $validatedData['pelanggan_id'],
                'tanggal_faktur' => $validatedData['tanggal_faktur'],
                'no_faktur' => $validatedData['no_faktur'],
                'alamat' => $alamatFallback,
                'keterangan' => $pengirimanPesanan->keterangan,
                'syarat_bayar' => $pengirimanPesanan->syarat_bayar,
                'kena_pajak' => $pengirimanPesanan->kena_pajak,
                'total_termasuk_pajak' => $pengirimanPesanan->total_termasuk_pajak,
                'diskon_keseluruhan' => $pengirimanPesanan->diskon_keseluruhan,
                'kode_customer' => $branch->customer_id,
            ]);

            DB::commit();

            // Clear related cache per branch
            Cache::forget('accurate_faktur_penjualan_list_branch_' . $activeBranchId);
            Cache::forget('accurate_delivery_order_list_branch_' . $activeBranchId);

            // 6. Redirect ke view index dengan success message
            return redirect()->route('faktur_penjualan.index')
                ->with('success', 'Data berhasil disimpan ke Accurate dan database lokal.')
                ->with('faktur_penjualan', $fakturPenjualan);
        } catch (Exception $e) {
            DB::rollBack();
            // Handle any exceptions (network issues, database errors, etc.)
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function show($no_faktur, Request $request)
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
        $cacheKey = 'faktur_penjualan_detail_' . $no_faktur . '_branch_' . $activeBranchId;
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $fakturPenjualan = null;
        $accurateDetail = null;
        $accurateDetailItems = [];
        $accurateDeliveryOrderDetail = null;
        $accurateSalesOrderDetail = null;
        $pengirimanPesanan = null;
        $kasirPenjualan = null;
        $mergedItems = [];
        $detailBarcodeMappings = [];
        $apiSuccess = false;

        try {
            // 1. Ambil token dari branch (auto-decrypted by model accessors)
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
            $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');

            // 2. Buat instance HTTP client sekali untuk semua request
            $httpClient = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ]);

            // 3. Ambil data faktur penjualan dari database lokal filtered by kode_customer
            $fakturPenjualan = FakturPenjualan::where('no_faktur', $no_faktur)
                ->where('kode_customer', $branch->customer_id)
                ->firstOrFail();

            // 4. Ambil data pengiriman pesanan filtered by kode_customer
            if ($fakturPenjualan->pengiriman_id) {
                $pengirimanPesanan = PengirimanPesanan::where('no_pengiriman', $fakturPenjualan->pengiriman_id)
                    ->where('kode_customer', $branch->customer_id)
                    ->first();
            }

            // 5. Selalu coba ambil data dari API terlebih dahulu
            // 5.1 Ambil detail faktur penjualan dari API
            if ($fakturPenjualan->no_faktur) {
                $response = $httpClient->get($baseUrl . '/sales-invoice/detail.do', [
                    'number' => $fakturPenjualan->no_faktur,
                ]);

                if ($response->successful() && isset($response->json()['d'])) {
                    $accurateDetail = $response->json()['d'];
                    $accurateDetailItems = $response->json()['d']['detailItem'] ?? [];
                    $apiSuccess = true;
                } else {
                    if ($response->status() == 404) {
                        $errorMessage = "Faktur penjualan dengan nomor {$no_faktur} tidak ditemukan.";
                    } else {
                        $errorMessage = "Gagal mengambil data faktur penjualan dari server. Silakan coba lagi.";
                    }
                    Log::warning('Gagal fetch detail sales invoice dari Accurate', [
                        'no_faktur' => $no_faktur,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            }

            // 5.2 Ambil detail delivery order dari API
            if ($fakturPenjualan->pengiriman_id) {
                $deliveryOrderResponse = $httpClient->get($baseUrl . '/delivery-order/detail.do', [
                    'number' => $fakturPenjualan->pengiriman_id,
                ]);

                if ($deliveryOrderResponse->successful() && isset($deliveryOrderResponse->json()['d'])) {
                    $accurateDeliveryOrderDetail = $deliveryOrderResponse->json()['d'];
                }
            }

            // 5.3 Ambil detail sales order dari API
            if ($pengirimanPesanan && $pengirimanPesanan->penjualan_id) {
                $salesOrderResponse = $httpClient->get($baseUrl . '/sales-order/detail.do', [
                    'number' => $pengirimanPesanan->penjualan_id,
                ]);

                if ($salesOrderResponse->successful() && isset($salesOrderResponse->json()['d'])) {
                    $accurateSalesOrderDetail = $salesOrderResponse->json()['d'];
                }
            }

            // 6. Ambil data kasir penjualan filtered by kode_customer
            $kasirPenjualan = KasirPenjualan::with(['detailItems' => function ($query) {
                $query->with('barcode');
            }])
                ->where('npj', $pengirimanPesanan->penjualan_id ?? null)
                ->where('kode_customer', $branch->customer_id)
                ->first();

            // 8. Proses data untuk merged items dan detail barcode mappings
            if ($kasirPenjualan && $kasirPenjualan->detailItems->count() > 0) {
                // Helper function untuk menghitung total harga dengan diskon
                $calculateTotalWithDiscount = function ($harga, $qty, $diskon) {
                    $subtotal = $harga * $qty;

                    if ($diskon && $diskon > 0) {
                        // Jika diskon antara 0-100, anggap sebagai persentase
                        if ($diskon <= 100) {
                            $diskonAmount = $subtotal * ($diskon / 100);
                            return $subtotal - $diskonAmount;
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
                    // Gunakan relasi yang sudah di-eager load
                    $approvalStock = $detailItem->approvalStock;

                    // Hitung total harga sebelum dan sesudah diskon
                    $subtotalSebelumDiskon = $detailItem->harga * $detailItem->qty;
                    $totalHargaSetelahDiskon = $calculateTotalWithDiscount(
                        $detailItem->harga,
                        $detailItem->qty,
                        $detailItem->diskon
                    );
                    $nominalDiskon = $subtotalSebelumDiskon - $totalHargaSetelahDiskon;

                    // Tentukan nama item
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $errorMessage = "Data faktur penjualan dengan nomor {$no_faktur} tidak ditemukan.";
            Log::error('Faktur penjualan tidak ditemukan: ' . $e->getMessage(), ['no_faktur' => $no_faktur]);
            
            $dataToCache = [
                'fakturPenjualan' => null,
                'pengirimanPesanan' => null,
                'kasirPenjualan' => null,
                'accurateDetail' => null,
                'accurateDetailItems' => [],
                'mergedItems' => [],
                'detailBarcodeMappings' => [],
                'accurateDeliveryOrderDetail' => null,
                'accurateSalesOrderDetail' => null,
                'errorMessage' => $errorMessage
            ];
        } catch (Exception $e) {
            Log::error('Error saat mengambil data dari API Accurate: ' . $e->getMessage(), [
                'no_faktur' => $no_faktur,
                'faktur_penjualan' => $fakturPenjualan ? $fakturPenjualan->toArray() : null
            ]);

            if ($fakturPenjualan) {
                $errorMessage = "Gagal mengambil detail dari server Accurate. Silakan coba lagi.";
            } else {
                $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
            }

            // Jika API error, coba ambil data dari cache sebagai fallback
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $fakturPenjualan = $cachedData['fakturPenjualan'] ?? $fakturPenjualan;
                $pengirimanPesanan = $cachedData['pengirimanPesanan'] ?? null;
                $kasirPenjualan = $cachedData['kasirPenjualan'] ?? null;
                $accurateDetail = $cachedData['accurateDetail'] ?? null;
                $accurateDetailItems = $cachedData['accurateDetailItems'] ?? [];
                $mergedItems = $cachedData['mergedItems'] ?? [];
                $detailBarcodeMappings = $cachedData['detailBarcodeMappings'] ?? [];
                $accurateDeliveryOrderDetail = $cachedData['accurateDeliveryOrderDetail'] ?? null;
                $accurateSalesOrderDetail = $cachedData['accurateSalesOrderDetail'] ?? null;
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail faktur penjualan {$no_faktur} dari cache karena API gagal.");
            } else {
                Log::warning("Tidak ada data cache tersedia sebagai fallback untuk no_faktur: {$no_faktur}");
            }

            $dataToCache = [
                'fakturPenjualan' => $fakturPenjualan,
                'pengirimanPesanan' => $pengirimanPesanan,
                'kasirPenjualan' => $kasirPenjualan,
                'accurateDetail' => $accurateDetail,
                'accurateDetailItems' => $accurateDetailItems,
                'mergedItems' => $mergedItems,
                'detailBarcodeMappings' => $detailBarcodeMappings,
                'accurateDeliveryOrderDetail' => $accurateDeliveryOrderDetail,
                'accurateSalesOrderDetail' => $accurateSalesOrderDetail,
                'errorMessage' => $errorMessage
            ];
        }

        // Siapkan data untuk view
        if (!isset($dataToCache)) {
            $dataToCache = [
                'fakturPenjualan' => $fakturPenjualan,
                'pengirimanPesanan' => $pengirimanPesanan,
                'kasirPenjualan' => $kasirPenjualan,
                'accurateDetail' => $accurateDetail,
                'accurateDetailItems' => $accurateDetailItems,
                'mergedItems' => $mergedItems,
                'detailBarcodeMappings' => $detailBarcodeMappings,
                'accurateDeliveryOrderDetail' => $accurateDeliveryOrderDetail,
                'accurateSalesOrderDetail' => $accurateSalesOrderDetail,
                'errorMessage' => $errorMessage
            ];
        }

        // Jika API berhasil, simpan ke cache dan gunakan data dari API
        if ($apiSuccess) {
            Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
            Log::info("Data detail faktur penjualan {$no_faktur} berhasil diambil dari API dan disimpan ke cache");
        }

        // Return view dengan data yang sudah diproses
        return view('faktur_penjualan.detail', $dataToCache);
    }
}
