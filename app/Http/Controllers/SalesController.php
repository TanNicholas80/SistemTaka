<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\DetailItemPenjualan;
use App\Models\KasirPenjualan;
use Exception;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    /**
     * Membangun URL API dari url_accurate branch
     * 
     * @param Branch $branch Branch yang aktif
     * @param string $endpoint Endpoint API (contoh: 'sales-order/detail.do')
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

        // Cache key yang unik per cabang
        $cacheKey = 'accurate_sales_order_list_' . $activeBranchId;
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache sales order list dihapus karena force_refresh');
        }

        $errorMessage = null;

        // Periksa apakah cache sudah ada
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $salesOrders = $cachedData['salesOrders'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data sales order diambil dari cache');
            return view('sales_cashier.index', compact('salesOrders', 'errorMessage'));
        }

        $salesOrders = [];
        $allSalesOrders = [];
        $hasApiError = false;

        try {
            $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $listApiUrl = $this->buildApiUrl($branch, 'sales-order/list.do');

            // Fetch halaman pertama
            $firstPageResponse = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($listApiUrl, ['sp.page' => 1, 'sp.pageSize' => 20]);

            Log::info('Accurate SO list first page response:', [
                'status' => $firstPageResponse->status(),
                'body' => $firstPageResponse->json(),
            ]);

            if ($firstPageResponse->successful()) {
                $responseData = $firstPageResponse->json();

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allSalesOrderIds = $responseData['d']; // hanya berisi [{id: X}, ...]

                    $totalItems = $responseData['sp']['rowCount'] ?? 0;
                    $totalPages = ceil($totalItems / 20);

                    // Fetch halaman ID berikutnya secara paralel jika lebih dari 1 halaman
                    if ($totalPages > 1) {
                        $promises = [];
                        $client = new \GuzzleHttp\Client(['verify' => false]);

                        for ($page = 2; $page <= $totalPages; $page++) {
                            $promises[$page] = $client->getAsync($listApiUrl, [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $apiToken,
                                    'X-Api-Signature' => $signature,
                                    'X-Api-Timestamp' => $timestamp,
                                ],
                                'query' => ['sp.page' => $page, 'sp.pageSize' => 20]
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
                                Log::error("Failed to fetch SO list page {$page}: " . $result['reason']);
                                $hasApiError = true;
                            }
                        }
                    }

                    // Fetch detail masing-masing SO secara paralel berdasarkan ID
                    if (!empty($allSalesOrderIds)) {
                        $detailClient = new \GuzzleHttp\Client(['verify' => false]);
                        $detailApiUrl = $this->buildApiUrl($branch, 'sales-order/detail.do');
                        $detailPromises = [];

                        foreach ($allSalesOrderIds as $soItem) {
                            $soId = $soItem['id'] ?? null;
                            if (!$soId)
                                continue;

                            $detailPromises[$soId] = $detailClient->getAsync($detailApiUrl, [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $apiToken,
                                    'X-Api-Signature' => $signature,
                                    'X-Api-Timestamp' => $timestamp,
                                ],
                                'query' => ['id' => $soId]
                            ]);
                        }

                        $detailResults = Utils::settle($detailPromises)->wait();

                        foreach ($detailResults as $soId => $result) {
                            if ($result['state'] === 'fulfilled') {
                                $detailData = json_decode($result['value']->getBody(), true);
                                if (isset($detailData['d'])) {
                                    $salesOrders[] = $detailData['d'];
                                }
                            } else {
                                Log::error("Failed to fetch SO detail for id {$soId}: " . $result['reason']);
                                $hasApiError = true;
                            }
                        }
                    }

                    // User ingin menampilkan semua data SO tanpa filter status
                    $salesOrders = array_values($salesOrders);

                    Log::info('Data sales order dari API berhasil diambil dan difilter (ONPROCESS/WAITING), total: ' . count($salesOrders));
                }
            } else {
                Log::error('Gagal fetch SO list dari Accurate', ['status' => $firstPageResponse->status(), 'body' => $firstPageResponse->body()]);
                $hasApiError = true;
            }
        } catch (\Exception $e) {
            Log::error('Exception saat mengambil SO list dari API: ' . $e->getMessage());
            $hasApiError = true;
        }

        if ($hasApiError) {
            $errorMessage = 'Gagal memuat data dari server Accurate. Silakan coba lagi dengan menekan tombol "Refresh Data".';

            // Fallback ke cache lama jika ada
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $salesOrders = $cachedData['salesOrders'] ?? [];
                Log::info('Menggunakan cache lama sebagai fallback');
            }
        }

        foreach ($salesOrders as &$so) {
            $percent = (float) ($so['percentShipped'] ?? 0);
            $so['is_partially_delivered'] = $percent > 0 && $percent < 100;
        }
        unset($so);

        // Simpan ke cache
        Cache::put($cacheKey, [
            'salesOrders' => $salesOrders,
            'errorMessage' => $errorMessage,
        ], $cacheDuration * 60);

        Log::info('Data sales order disimpan ke cache dengan info partial delivery');

        return view('sales_cashier.index', compact('salesOrders', 'errorMessage'));
    }

    public function show($npj, Request $request)
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId)
            return back()->with('error', 'Cabang belum dipilih.');

        $branch = Branch::find($activeBranchId);
        if (!$branch)
            return back()->with('error', 'Cabang tidak valid.');

        // Validasi kredensial Accurate
        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $cacheKey = 'sales_order_detail_' . $activeBranchId . '_' . $npj;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $accurateDetail = null;
        $accurateDetailItems = [];

        // Cek cache dulu
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $accurateDetail = $cachedData['accurateDetail'] ?? null;
            $accurateDetailItems = $cachedData['accurateDetailItems'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            return view('sales_cashier.detail', compact('accurateDetail', 'accurateDetailItems', 'errorMessage'));
        }

        try {
            // Kredensial Accurate mengikuti user login (konsisten dengan method index)
            $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($this->buildApiUrl($branch, 'sales-order/detail.do'), [
                        'number' => $npj
                    ]);

            if ($response->successful() && isset($response->json()['d'])) {
                $accurateDetail = $response->json()['d'];
                $accurateDetailItems = $accurateDetail['detailItem'] ?? [];

                Cache::put($cacheKey, [
                    'accurateDetail' => $accurateDetail,
                    'accurateDetailItems' => $accurateDetailItems,
                    'errorMessage' => null
                ], $cacheDuration * 60);

            } else {
                Log::error('Gagal fetch SO detail dari Accurate', [
                    'npj' => $npj,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $errorMessage = "Gagal mengambil data SO {$npj} dari Accurate.";
            }

        } catch (\Exception $e) {
            Log::error('Exception saat mengambil detail SO: ' . $e->getMessage(), ['npj' => $npj]);
            $errorMessage = "Terjadi kesalahan koneksi. Silakan coba lagi.";
        }

        return view('sales_cashier.detail', compact('accurateDetail', 'accurateDetailItems', 'errorMessage'));
    }

    public function create(Request $request)
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

        // Ambil kredensial Accurate dari branch
        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Initialize variables
        $pelanggan = [];
        $paymentTerms = [];
        $accurateErrors = [];

        try {
            // Fetch data secara parallel untuk mendapatkan data real-time
            $customerData = $this->fetchCustomersFromAccurate($branch, $apiToken, $signature, $timestamp);
            $pelanggan = $customerData['customers'];
            $accurateErrors = array_merge($accurateErrors, $customerData['errors']);

            $paymentTermData = $this->fetchPaymentTermsFromAccurate($branch, $apiToken, $signature, $timestamp);
            $paymentTerms = $paymentTermData['paymentTerms'];
            $accurateErrors = array_merge($accurateErrors, $paymentTermData['errors']);

            Log::info('Data customer dan payment terms untuk create berhasil diambil dari API (real-time)');
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching create data from Accurate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $accurateErrors[] = 'An error occurred while fetching data from Accurate: ' . $e->getMessage();
        }

        // Generate NPJ
        $npj = KasirPenjualan::generateNpj();

        return view('sales_cashier.create', compact('pelanggan', 'npj', 'paymentTerms', 'accurateErrors'));
    }

    /**
     * Fetch customers data from Accurate API with pagination and parallel processing
     */
    private function fetchCustomersFromAccurate($branch, $apiToken, $signature, $timestamp)
    {
        $customers = [];
        $errors = [];

        try {
            $customerApiUrl = $this->buildApiUrl($branch, 'customer/list.do');
            $data = [
                'sp.page' => 1,
                'sp.pageSize' => 20
            ];

            $firstPageResponse = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($customerApiUrl, $data);

            if ($firstPageResponse->successful()) {
                $responseData = $firstPageResponse->json();
                $allCustomers = [];

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allCustomers = $responseData['d'];

                    // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                    $totalItems = $responseData['sp']['rowCount'] ?? 0;
                    $totalPages = ceil($totalItems / 20);

                    // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                    if ($totalPages > 1) {
                        $promises = [];
                        $client = new \GuzzleHttp\Client();

                        for ($page = 2; $page <= $totalPages; $page++) {
                            $promises[$page] = $client->getAsync($customerApiUrl, [
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

                        $results = Utils::settle($promises)->wait();

                        foreach ($results as $page => $result) {
                            if ($result['state'] === 'fulfilled') {
                                $pageResponse = json_decode($result['value']->getBody(), true);
                                if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                    $allCustomers = array_merge($allCustomers, $pageResponse['d']);
                                }
                            } else {
                                $errors[] = "Failed to fetch customers page {$page}: " . $result['reason'];
                            }
                        }
                    }

                    // Fetch customer details dalam batch
                    $customers = $this->fetchCustomerDetailsInBatches($allCustomers, $branch, $apiToken, $signature, $timestamp);
                } else {
                    $errors[] = 'Unexpected customer list response structure from Accurate.';
                }
            } else {
                $errors[] = 'Failed to fetch customer list from Accurate: ' . $firstPageResponse->body();
            }
        } catch (\Exception $e) {
            $errors[] = 'Exception occurred while fetching customers: ' . $e->getMessage();
        }

        return [
            'customers' => $customers,
            'errors' => $errors
        ];
    }

    /**
     * Fetch customer details dalam batch
     */
    private function fetchCustomerDetailsInBatches($customerList, $branch, $apiToken, $signature, $timestamp, $batchSize = 5)
    {
        $customerDetails = [];
        $batches = array_chunk($customerList, $batchSize);

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $customer) {
                $detailUrl = $this->buildApiUrl($branch, 'customer/detail.do?id=' . $customer['id']);
                $promises[$customer['id']] = $client->getAsync($detailUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ]
                ]);
            }

            $results = Utils::settle($promises)->wait();

            foreach ($results as $customerId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $customerDetails[] = $detailResponse['d'];
                    }
                } else {
                    Log::error("Failed to fetch customer detail for ID {$customerId}: " . $result['reason']);
                }
            }

            usleep(200000); // 200ms
        }

        return $customerDetails;
    }

    /**
     * Fetch payment terms data from Accurate API
     */
    private function fetchPaymentTermsFromAccurate($branch, $apiToken, $signature, $timestamp)
    {
        $paymentTerms = [];
        $errors = [];

        try {
            $paymentTermResponse = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($this->buildApiUrl($branch, 'payment-term/list.do'));

            if ($paymentTermResponse->successful()) {
                $responseData = $paymentTermResponse->json();
                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $paymentTerms = $responseData['d'];
                } else {
                    $errors[] = 'Unexpected payment term list response structure from Accurate.';
                }
            } else {
                $errors[] = 'Failed to fetch payment terms from Accurate: ' . $paymentTermResponse->body();
            }
        } catch (\Exception $e) {
            $errors[] = 'Exception occurred while fetching payment terms: ' . $e->getMessage();
        }

        return [
            'paymentTerms' => $paymentTerms,
            'errors' => $errors
        ];
    }

    public function getCustomerInfo(Request $req)
    {
        try {
            // Validasi input customer
            $req->validate([
                'customer_no' => 'required|string|max:255'
            ]);

            $customerNo = $req->input('customer_no');

            // Validasi cabang aktif
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang belum dipilih.'
                ], 400);
            }

            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak valid.'
                ], 400);
            }

            // Validasi kredensial Accurate
            if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.'
                ], 400);
            }

            // Ambil kredensial Accurate dari branch
            $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            try {
                Log::info("getCustomerInfo: Mengambil detail customer dengan customerNo: {$customerNo}");

                // Request ke Accurate API untuk mendapatkan detail customer
                $response = Http::timeout(30)->withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'X-Api-Signature' => $signature,
                    'X-Api-Timestamp' => $timestamp,
                ])->post($this->buildApiUrl($branch, 'customer/detail.do'), [
                            'customerNo' => $customerNo
                        ]);

                // Cek apakah response berhasil
                if (!$response->successful()) {
                    Log::error("getCustomerInfo: Gagal mengambil detail customer. Status: {$response->status()}, Body: {$response->body()}");

                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal mendapatkan detail customer dari Accurate',
                        'error' => $response->status() . ': ' . $response->body()
                    ], 400);
                }

                $customerData = $response->json();

                // Cek struktur response dari API
                if (!isset($customerData['d'])) {
                    Log::warning("getCustomerInfo: Struktur response tidak sesuai untuk customerNo: {$customerNo}");

                    return response()->json([
                        'success' => false,
                        'message' => 'Data customer tidak ditemukan atau struktur response tidak valid',
                        'raw_response' => $customerData
                    ], 404);
                }

                // Ambil data customer dari response
                $customer = $customerData['d'];

                // Format data customer yang akan dikembalikan
                $formattedCustomerData = [
                    'customer_pay_term' => $customer['term']['name'] ?? null,
                    'customer_address' => $customer['shipStreet'] ?? null,
                ];

                Log::info("getCustomerInfo: Berhasil mendapatkan detail customer untuk customerNo: {$customerNo}, Nama: " . ($customer['name'] ?? 'Unknown'));

                return response()->json([
                    'success' => true,
                    'message' => 'Detail customer berhasil diambil',
                    'data' => $formattedCustomerData,
                    'raw_data' => $customer // Opsional: untuk debugging
                ], 200);
            } catch (Exception $e) {
                Log::error("getCustomerInfo: Error saat mengambil data customer: " . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat mengambil data customer: ' . $e->getMessage(),
                    'error' => $e->getMessage()
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error("getCustomerInfo: Error umum: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getBarcodeAjax(Request $req)
    {
        try {
            // Validasi input barcode
            $req->validate([
                'barcode' => 'required|string|max:10'
            ]);

            $barcode = $req->input('barcode');

            // Validasi cabang aktif
            $activeBranchId = session('active_branch');
            if (!$activeBranchId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang belum dipilih.'
                ], 400);
            }

            $branch = Branch::find($activeBranchId);
            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak valid.'
                ], 400);
            }

            // Validasi kredensial Accurate
            if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.'
                ], 400);
            }

            // Cari data Barcode (status uploaded = siap jual)
            $approvalStock = Barcode::where('barcode', $barcode)
                ->where('kode_customer', $branch->customer_id)
                ->where('status', 'uploaded')
                ->first();

            if (!$approvalStock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barcode tidak ditemukan',
                    'data' => null
                ], 404);
            }

            // Pengecekan kuantitas - jika 0 atau null, tolak request
            if (!$approvalStock->panjang || $approvalStock->panjang <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok untuk barcode ini sudah habis atau tidak tersedia',
                    'data' => null,
                    'stock_info' => [
                        'current_quantity' => $approvalStock->panjang ?? 0,
                        'item_name' => $approvalStock->nama
                    ]
                ], 400);
            }

            // Ambil data dari API Accurate dengan pagination menggunakan kredensial dari branch
            $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            try {
                $allAccurateItems = [];
                $totalPages = 2;
                $pageSize = 100;

                // Loop untuk mengambil semua data dari 7 halaman
                for ($page = 1; $page <= $totalPages; $page++) {
                    Log::info("getBarcodeAjax: Mengambil data dari Accurate API halaman {$page}");

                    $response = Http::timeout(30)->withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($this->buildApiUrl($branch, 'item/list.do'), [
                                'fields' => 'name,no,unit1,unitPrice',
                                'sp.pageSize' => $pageSize,
                                'sp.page' => $page,
                            ]);

                    if (!$response->successful()) {
                        Log::error("getBarcodeAjax: Gagal mengambil data dari Accurate API halaman {$page}. Status: {$response->status()}, Body: {$response->body()}");

                        // Jika gagal di halaman pertama, kembalikan error
                        if ($page === 1) {
                            return response()->json([
                                'success' => true,
                                'message' => 'Barcode ditemukan, tetapi gagal mendapatkan data dari Accurate',
                                'data' => $approvalStock,
                                'accurate_error' => $response->status() . ': ' . $response->body()
                            ], 200);
                        }

                        // Jika gagal di halaman selanjutnya, lanjutkan dengan data yang sudah didapat
                        Log::warning("getBarcodeAjax: Melanjutkan proses dengan data yang sudah didapat dari halaman 1-" . ($page - 1));
                        break;
                    }

                    $responseData = $response->json();

                    if (!isset($responseData['d']) || !is_array($responseData['d'])) {
                        Log::warning("getBarcodeAjax: Struktur data tidak sesuai pada halaman {$page}");
                        continue;
                    }

                    // Tambahkan data dari halaman ini ke array keseluruhan
                    $allAccurateItems = array_merge($allAccurateItems, $responseData['d']);

                    Log::info("getBarcodeAjax: Berhasil mengambil " . count($responseData['d']) . " item dari halaman {$page}. Total items sejauh ini: " . count($allAccurateItems));

                    // Jika data kosong, kemungkinan sudah mencapai halaman terakhir
                    if (empty($responseData['d'])) {
                        Log::info("getBarcodeAjax: Tidak ada data lagi pada halaman {$page}, menghentikan pagination");
                        break;
                    }

                    // Delay kecil untuk menghindari rate limiting
                    usleep(100000); // 0.1 detik
                }

                Log::info("getBarcodeAjax: Total items yang berhasil diambil dari Accurate API: " . count($allAccurateItems));

                if (empty($allAccurateItems)) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Barcode ditemukan, tetapi tidak ada data dari Accurate',
                        'data' => $approvalStock
                    ], 200);
                }

                // Lakukan matching nama antara ApprovalStock dan semua data dari Accurate
                $matchedItem = null;
                $approvalNama = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace('KC', '', $approvalStock->nama)));

                foreach ($allAccurateItems as $item) {
                    $itemName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $item['name'] ?? ''));

                    if ($itemName === $approvalNama) {
                        $matchedItem = $item;
                        break;
                    }
                }

                // --- LOGGING UNTUK MATCHED ITEM ---
                if ($matchedItem) {
                    Log::info("getBarcodeAjax: Barcode {$barcode} berhasil dicocokkan. Approval Stock Nama: '{$approvalStock->nama}', Accurate Item Name: '{$matchedItem['name']}', Accurate Item No: '{$matchedItem['no']}'. Total items yang dibandingkan: " . count($allAccurateItems));
                } else {
                    Log::info("getBarcodeAjax: Barcode {$barcode} ditemukan di ApprovalStock ('{$approvalStock->nama}'), tetapi tidak ada item yang cocok di Accurate API dari " . count($allAccurateItems) . " items.");

                    // Log beberapa contoh nama item dari Accurate untuk debugging
                    $sampleItems = array_slice($allAccurateItems, 0, 5);
                    foreach ($sampleItems as $index => $item) {
                        Log::info("getBarcodeAjax: Sample item " . ($index + 1) . " dari Accurate: '{$item['name']}'");
                    }
                }
                // --- AKHIR LOGGING UNTUK MATCHED ITEM ---

                // Gabungkan data ApprovalStock dengan data dari Accurate
                $mergedData = $approvalStock->toArray();

                if ($matchedItem) {
                    $mergedData['accurate_data'] = [
                        'kode_barang' => $matchedItem['no'] ?? null,
                        'satuan_barang' => $matchedItem['unit1']['name'] ?? null,
                        'harga_barang' => $matchedItem['unitPrice'] ?? null,
                    ];
                } else {
                    $mergedData['accurate_data'] = null;
                }

                // Tambahkan informasi tentang total data yang dibandingkan
                $mergedData['total_accurate_items_compared'] = count($allAccurateItems);

                $responseMessage = $matchedItem ?
                    'Barcode ditemukan dan berhasil dicocokkan dengan data Accurate (' . count($allAccurateItems) . ' items dibandingkan)' :
                    'Barcode ditemukan tetapi tidak cocok dengan data Accurate (' . count($allAccurateItems) . ' items dibandingkan)';

                return response()->json([
                    'success' => true,
                    'message' => $responseMessage,
                    'data' => $mergedData
                ], 200);
            } catch (Exception $e) {
                // Jika terjadi error saat mengambil data dari Accurate, tetap kembalikan data ApprovalStock
                Log::error("getBarcodeAjax: Error saat mengambil data dari Accurate: " . $e->getMessage());

                return response()->json([
                    'success' => true,
                    'message' => 'Barcode ditemukan, tetapi gagal mendapatkan data dari Accurate: ' . $e->getMessage(),
                    'data' => $approvalStock
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error("getBarcodeAjax: Error umum: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
                'error' => $e->getMessage()
            ], 500);
        }
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

        // Validasi kredensial Accurate
        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        // SOLUSI 1: Preprocessing data sebelum validasi (REKOMENDASI)
        $requestData = $request->all();

        // Convert checkbox values to boolean - handle string "true"/"false" dan boolean values
        $requestData['kena_pajak'] = isset($requestData['kena_pajak']) ?
            (filter_var($requestData['kena_pajak'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false) : false;
        $requestData['total_termasuk_pajak'] = isset($requestData['total_termasuk_pajak']) ?
            (filter_var($requestData['total_termasuk_pajak'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false) : false;

        $validator = Validator::make($requestData, [
            'npj' => 'required|string|max:255|unique:kasir_penjualans,npj',
            'tanggal' => 'required|date',
            'customer' => 'required|string|max:255',
            'pay_term' => 'nullable|string',
            'alamat' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'kena_pajak' => 'nullable|boolean',
            'total_termasuk_pajak' => 'nullable|boolean',
            'diskon_keseluruhan' => 'nullable|numeric|min:0',
            'detailItems' => 'required|array|min:1',
            'detailItems.*.barcode' => 'required|string|max:10',
            'detailItems.*.kode' => 'required|string',
            'detailItems.*.kuantitas' => 'required|string',
            'detailItems.*.harga' => 'required|numeric|min:0',
            'detailItems.*.diskon' => 'nullable|numeric|min:0',
        ], [
            // NPJ validation messages
            'npj.required' => 'Nomor Penjualan (NPJ) wajib diisi.',
            'npj.unique' => 'Nomor Penjualan (NPJ) sudah digunakan.',

            // Tanggal validation messages
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Format tanggal tidak valid.',

            // Customer validation messages
            'customer.required' => 'Nama customer wajib diisi.',

            // Diskon keseluruhan validation messages
            'diskon_keseluruhan.numeric' => 'Diskon keseluruhan harus berupa angka.',
            'diskon_keseluruhan.min' => 'Diskon keseluruhan tidak boleh kurang dari 0.',

            // Detail items validation messages
            'detailItems.required' => 'Detail item wajib diisi.',
            'detailItems.min' => 'Minimal harus ada 1 item yang Di Inputkan.',

            // Detail items barcode validation messages
            'detailItems.*.barcode.required' => 'Barcode item wajib diisi.',
            'detailItems.*.barcode.max' => 'Barcode item maksimal 10 karakter.',

            // Detail items kode validation messages
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

        try {
            $validatedData = $validator->validated();

            Log::info('Isi validatedData kena_pajak:', ['kena_pajak' => $validatedData['kena_pajak'] ?? null]);
            Log::info('Isi validatedData total_termasuk_pajak:', ['total_termasuk_pajak' => $validatedData['total_termasuk_pajak'] ?? null]);

            // 2. Siapkan payload untuk Accurate API
            $detailItemsForAccurate = [];
            foreach ($validatedData['detailItems'] as $item) {
                $accurateItem = [
                    "itemNo" => $item['kode'],
                    "quantity" => $item['kuantitas'],
                    "unitPrice" => $item['harga'],
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

            // Siapkan data untuk API Accurate dengan mengecek nilai null
            $postDataForAccurate = [
                "customerNo" => $validatedData['customer'],
                "transDate" => date('d/m/Y', strtotime($validatedData['tanggal'])),
                "number" => $validatedData['npj'],
                "detailItem" => $detailItemsForAccurate,
            ];

            // Set syarat bayar: gunakan yang diinput atau default C.O.D jika kosong
            $syaratBayar = !empty($validatedData['pay_term']) ? $validatedData['pay_term'] : 'C.O.D';
            $postDataForAccurate['paymentTermName'] = $syaratBayar;

            if (!empty($validatedData['alamat'])) {
                $postDataForAccurate['toAddress'] = $validatedData['alamat'];
            }

            if (!empty($validatedData['keterangan'])) {
                $postDataForAccurate['description'] = $validatedData['keterangan'];
            }

            if (!empty($validatedData['diskon_keseluruhan'])) {
                if (isset($validatedData['diskon_keseluruhan']) && $validatedData['diskon_keseluruhan'] > 0) {
                    $diskonKeseluruhan = (float) $validatedData['diskon_keseluruhan'];

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

            if (isset($validatedData['kena_pajak'])) {
                $postDataForAccurate['taxable'] = $validatedData['kena_pajak'];
            }

            if (isset($validatedData['total_termasuk_pajak'])) {
                $postDataForAccurate['inclusiveTax'] = $validatedData['total_termasuk_pajak'];
            }

            // 3. Kirim data ke API Accurate menggunakan kredensial dari branch
            $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type' => 'application/json',
            ])->post($this->buildApiUrl($branch, 'sales-order/save.do'), $postDataForAccurate);

            // 4. Validasi response dari API Accurate
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

            // 5. Jika API Accurate berhasil, simpan ke database lokal dengan kode_customer
            $kasirPenjualan = KasirPenjualan::create([
                'npj' => $validatedData['npj'],
                'tanggal' => $validatedData['tanggal'],
                'customer' => $validatedData['customer'],
                'alamat' => $validatedData['alamat'] ?? null,
                'keterangan' => $validatedData['keterangan'] ?? null,
                'kena_pajak' => $validatedData['kena_pajak'] ?? null,
                'syarat_bayar' => $syaratBayar, // Gunakan syarat bayar yang sudah ada default C.O.D
                'total_termasuk_pajak' => $validatedData['total_termasuk_pajak'] ?? null,
                'diskon_keseluruhan' => $validatedData['diskon_keseluruhan'] ?? null,
                'kode_customer' => $branch->customer_id,
            ]);

            // 6. Simpan detail items ke database lokal
            foreach ($validatedData['detailItems'] as $item) {
                DetailItemPenjualan::create([
                    'barcode' => $item['barcode'],
                    'npj' => $validatedData['npj'],
                    'qty' => $item['kuantitas'],
                    'harga' => $item['harga'],
                    'diskon' => $item['diskon'] ?? null, // Gunakan null jika diskon tidak ada
                ]);

                // --- LOGIKA PENGURANGAN STOK ---
                $barcode = $item['barcode'];
                $kuantitas = (float) $item['kuantitas'];

                // Temukan Barcode berdasarkan barcode dan kode_customer, lalu kurangi panjang_mlc
                $stockToUpdate = Barcode::where('barcode', $barcode)
                    ->where('kode_customer', $branch->customer_id)
                    ->first();

                if ($stockToUpdate) {
                    $currentPanjang = (float) ($stockToUpdate->panjang_mlc ?? 0);
                    $stockToUpdate->update(['panjang_mlc' => max(0, $currentPanjang - $kuantitas)]);
                }
            }

            // 8. Clear related cache setelah transaksi berhasil (global dan per cabang)
            // Cache ini perlu di-invalidate karena data telah berubah:
            // - Sales order list (ada penambahan data baru)
            // - Create form data (mungkin ada perubahan customer/payment terms)
            // - Barcode data (stok telah berkurang)
            Cache::forget('accurate_sales_order_details');
            Cache::forget('accurate_barang_list');
            Cache::forget('accurate_sales_order_details_' . $activeBranchId);
            Cache::forget('accurate_barang_list_' . $activeBranchId);

            // 7. Redirect ke view index dengan success message
            return redirect()->route('cashier.index')
                ->with('success', 'Data berhasil disimpan ke Accurate dan database lokal.')
                ->with('kasir_penjualan', $kasirPenjualan);
        } catch (Exception $e) {
            // Handle any exceptions (network issues, database errors, etc.)
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}
