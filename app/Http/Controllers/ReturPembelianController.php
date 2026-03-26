<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\PenerimaanBarang;
use App\Models\ReturPembelian;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Http;

class ReturPembelianController extends Controller
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
        $cacheKey = 'accurate_retur_pembelian_list_branch_' . $activeBranchId;
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache retur pembelian dihapus karena force_refresh');
        }

        $errorMessage = null;

        // Periksa apakah cache sudah ada
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $returPembelian = $cachedData['returPembelian'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data retur pembelian diambil dari cache');
            return view('retur_pembelian.index', compact('returPembelian', 'errorMessage'));
        }

        // Get API credentials from branch (auto-decrypted by model accessors)
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Define the API URL for listing sales returns
        $listApiUrl = $baseUrl . '/purchase-return/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20
        ];

        // Initialize an empty array for purchase returns
        $returPembelian = [];
        $allPurchaseReturns = [];
        $apiSuccess = false;
        $hasApiError = false;

        // Selalu coba ambil data dari API terlebih dahulu
        try {
            // Fetch purchase return IDs from the API
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

                // Logging data list retur pembelian mentah dari Accurate
                Log::info('Accurate Retur pembelian list first page response:', $responseData);

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allPurchaseReturns = $responseData['d'];

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
                                    $allPurchaseReturns = array_merge($allPurchaseReturns, $pageResponse['d']);
                                    Log::info("Accurate Retur pembelian list page {$page} response processed");
                                }
                            } else {
                                Log::error("Failed to fetch page {$page}: " . $result['reason']);
                            }
                        }
                    }

                    // Setelah mendapatkan semua ID retur pembelian, ambil detail untuk masing-masing secara batch
                    $detailsResult = $this->fetchPurchaseReturnDetailsInBatches($allPurchaseReturns, $apiToken, $signature, $timestamp, $baseUrl);
                    $returPembelian = $detailsResult['details'];
                    
                    // Cek jika ada error dari proses fetch detail
                    if ($detailsResult['has_error']) {
                        $hasApiError = true;
                    }
                    
                    $apiSuccess = true;
                    Log::info('Data retur pembelian dari API berhasil diambil');
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
        if (!$apiSuccess && empty($returPembelian)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $returPembelian = $cachedData['returPembelian'] ?? [];
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info('Data retur pembelian diambil dari cache karena API error');
            } else {
                if (is_null($errorMessage)) $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache tersedia.';
                Log::warning('Tidak ada cache tersedia, menampilkan data kosong');
            }
        }

        // Simpan data ke cache
        $dataToCache = [
            'returPembelian' => $returPembelian,
            'errorMessage' => $errorMessage
        ];

        Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
        Log::info('Data retur pembelian disimpan ke cache');

        return view('retur_pembelian.index', compact('returPembelian', 'errorMessage'));
    }

        /**
     * Mengambil detail retur pembelian dalam batch untuk mengoptimalkan performa
     */
    private function fetchPurchaseReturnDetailsInBatches($purchaseReturns, $apiToken, $signature, $timestamp, $baseUrl, $batchSize = 5)
    {
        $purchaseReturnDetails = [];
        $batches = array_chunk($purchaseReturns, $batchSize);
        $hasApiError = false; // Flag error untuk fungsi ini

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $return) {
                $detailUrl = $baseUrl . '/purchase-return/detail.do?id=' . $return['id'];
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
                        $purchaseReturnDetails[] = $detailResponse['d'];
                        Log::info("Retur pembelian detail fetched for ID: {$return['id']}");
                    }
                } else {
                    $reason = $result['reason'];
                    Log::error("Failed to fetch retur pembelian detail for ID {$invoiceId}: " . $reason->getMessage());
                    
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
            'details' => $purchaseReturnDetails,
            'has_error' => $hasApiError
        ];
    }

    public function create()
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

        $baseUrl = $branch->getAccurateApiBaseUrl();
        $vendors = $this->fetchVendorsFromAccurate($branch, $baseUrl);
        $receiveItems = [];
        $purchaseInvoices = [];

        $selectedTanggal = date('Y-m-d');
        $formReadonly = false;
        $no_retur = ReturPembelian::generateNoRetur();

        return view('retur_pembelian.create', compact('selectedTanggal', 'formReadonly', 'no_retur', 'vendors', 'receiveItems', 'purchaseInvoices'));
    }

    /**
     * AJAX: Get receive items (receive-item/list.do) filtered by vendor (filter.vendorNo)
     */
    public function getReceiveItemsAjax(Request $request)
    {
        $vendorNo = $request->query('filter.vendorNo') ?? $request->query('filter_vendorNo');
        if (empty($vendorNo)) {
            return response()->json(['receiveItems' => [], 'message' => 'vendorNo wajib diisi']);
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['receiveItems' => [], 'error' => 'Tidak ada cabang yang aktif.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch || !Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return response()->json(['receiveItems' => [], 'error' => 'Kredensial API tidak tersedia.'], 400);
        }

        $baseUrl = $branch->getAccurateApiBaseUrl();
        $receiveItems = $this->getReceiveItemsFromAccurate($branch, $baseUrl, $vendorNo);

        return response()->json(['receiveItems' => $receiveItems]);
    }

    /**
     * AJAX: Get purchase invoices (purchase-invoice/list.do) filtered by vendor (filter.vendorNo)
     */
    public function getInvoicesAjax(Request $request)
    {
        $vendorNo = $request->query('filter.vendorNo') ?? $request->query('filter_vendorNo');
        if (empty($vendorNo)) {
            return response()->json(['purchaseInvoices' => [], 'message' => 'vendorNo wajib diisi']);
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['purchaseInvoices' => [], 'error' => 'Tidak ada cabang yang aktif.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch || !Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return response()->json(['purchaseInvoices' => [], 'error' => 'Kredensial API tidak tersedia.'], 400);
        }

        $baseUrl = $branch->getAccurateApiBaseUrl();
        $purchaseInvoices = $this->getPurchaseInvoicesFromAccurate($branch, $baseUrl, $vendorNo);

        return response()->json(['purchaseInvoices' => $purchaseInvoices]);
    }

    /**
     * AJAX: Get detail items dari referensi (receive item atau purchase invoice) untuk form retur pembelian
     * return_type: RECEIVE -> receive-item/detail.do, INVOICE/INVOICE_DP -> purchase-invoice/detail.do, NO_INVOICE -> tidak ada referensi
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

        if (strtoupper($returnType) === 'NO_INVOICE') {
            return response()->json([
                'success' => true,
                'detailItems' => [],
            ]);
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['success' => false, 'message' => 'Tidak ada cabang yang aktif.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch || !Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return response()->json(['success' => false, 'message' => 'Kredensial API tidak tersedia.'], 400);
        }

        $baseUrl = $branch->getAccurateApiBaseUrl();
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        $detailApiUrl = null;
        if (strtoupper($returnType) === 'RECEIVE') {
            $detailApiUrl = $baseUrl . '/receive-item/detail.do';
        } elseif (in_array(strtoupper($returnType), ['INVOICE', 'INVOICE_DP'])) {
            $detailApiUrl = $baseUrl . '/purchase-invoice/detail.do';
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
                Log::warning('Referensi detail API retur pembelian gagal', [
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
            Log::error('Exception getReferensiDetailAjax retur pembelian: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail referensi.',
            ], 500);
        }
    }

    /**
     * Tampilkan detail retur pembelian
     */
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

        $cacheKey = 'retur_pembelian_detail_' . $no_retur . '_branch_' . $activeBranchId;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $returPembelian = null;
        $accurateDetail = null;
        $accurateDetailItems = [];
        $accurateReferenceDetail = null;
        $referenceType = null;
        $penerimaanBarang = null;
        $apiSuccess = false;

        try {
            $apiToken = Auth::user()->accurate_api_token;
            $signatureSecret = Auth::user()->accurate_signature_secret;
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
            $baseUrl = $branch->getAccurateApiBaseUrl();

            $httpClient = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ]);

            $returPembelian = ReturPembelian::where('no_retur', $no_retur)->firstOrFail();
            $returnType = $returPembelian->return_type;

            if ($returnType === 'receive' && $returPembelian->penerimaan_barang_id) {
                $penerimaanBarang = PenerimaanBarang::where('npb', $returPembelian->penerimaan_barang_id)
                    ->orWhere('no_terima', $returPembelian->penerimaan_barang_id)
                    ->first();
                $referenceType = 'receive';
            } elseif (in_array($returnType, ['invoice', 'invoice_dp']) && $returPembelian->faktur_pembelian_id) {
                $referenceType = 'invoice';
            }

            $response = $httpClient->get($baseUrl . '/purchase-return/detail.do', [
                'number' => $returPembelian->no_retur,
            ]);

            if ($response->successful() && isset($response->json()['d'])) {
                $accurateDetail = $response->json()['d'];
                $accurateDetailItems = $accurateDetail['detailItem'] ?? [];
                $apiSuccess = true;
            } else {
                if ($response->status() == 404) {
                    $errorMessage = "Retur pembelian dengan nomor {$no_retur} tidak ditemukan di Accurate.";
                } else {
                    $errorMessage = "Gagal mengambil data retur pembelian dari server. Silakan coba lagi.";
                }
                Log::warning('Gagal fetch detail purchase return dari Accurate', [
                    'no_retur' => $no_retur,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            if ($referenceType === 'receive' && $returPembelian->penerimaan_barang_id) {
                $receiveResponse = $httpClient->get($baseUrl . '/receive-item/detail.do', [
                    'number' => $returPembelian->penerimaan_barang_id,
                ]);
                if ($receiveResponse->successful() && isset($receiveResponse->json()['d'])) {
                    $accurateReferenceDetail = $receiveResponse->json()['d'];
                }
            } elseif ($referenceType === 'invoice' && $returPembelian->faktur_pembelian_id) {
                $invoiceResponse = $httpClient->get($baseUrl . '/purchase-invoice/detail.do', [
                    'number' => $returPembelian->faktur_pembelian_id,
                ]);
                if ($invoiceResponse->successful() && isset($invoiceResponse->json()['d'])) {
                    $accurateReferenceDetail = $invoiceResponse->json()['d'];
                }
            }

            $dataToCache = [
                'returPembelian'          => $returPembelian,
                'accurateDetail'          => $accurateDetail,
                'accurateDetailItems'     => $accurateDetailItems,
                'accurateReferenceDetail' => $accurateReferenceDetail,
                'referenceType'           => $referenceType,
                'penerimaanBarang'        => $penerimaanBarang,
                'errorMessage'            => $errorMessage,
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $errorMessage = "Data retur pembelian dengan nomor {$no_retur} tidak ditemukan.";
            Log::error('Retur pembelian tidak ditemukan: ' . $e->getMessage(), ['no_retur' => $no_retur]);
            $dataToCache = [
                'returPembelian'          => null,
                'accurateDetail'          => null,
                'accurateDetailItems'     => [],
                'accurateReferenceDetail' => null,
                'referenceType'           => null,
                'penerimaanBarang'        => null,
                'errorMessage'            => $errorMessage,
            ];
        } catch (Exception $e) {
            Log::error('Error saat mengambil data retur pembelian: ' . $e->getMessage(), ['no_retur' => $no_retur]);
            $errorMessage = 'Terjadi kesalahan: ' . $e->getMessage();
            $dataToCache = [
                'returPembelian'          => $returPembelian ?? null,
                'accurateDetail'          => $accurateDetail ?? null,
                'accurateDetailItems'     => $accurateDetailItems ?? [],
                'accurateReferenceDetail' => $accurateReferenceDetail ?? null,
                'referenceType'           => $referenceType ?? null,
                'penerimaanBarang'        => $penerimaanBarang ?? null,
                'errorMessage'            => $errorMessage,
            ];
        }

        if ($apiSuccess && isset($dataToCache)) {
            Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
        }

        return view('retur_pembelian.detail', $dataToCache ?? [
            'returPembelian'          => $returPembelian ?? null,
            'accurateDetail'          => $accurateDetail ?? null,
            'accurateDetailItems'     => $accurateDetailItems ?? [],
            'accurateReferenceDetail' => $accurateReferenceDetail ?? null,
            'referenceType'           => $referenceType ?? null,
            'penerimaanBarang'        => $penerimaanBarang ?? null,
            'errorMessage'            => $errorMessage,
        ]);
    }

    /**
     * Simpan retur pembelian (validasi: INVOICE/INVOICE_DP -> faktur_pembelian_id wajib, RECEIVE -> penerimaan_barang_id wajib, NO_INVOICE -> tidak perlu keduanya)
     */
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

        $rules = [
            'no_retur'                => 'required|string|max:255|unique:retur_pembelians,no_retur',
            'tanggal_retur'           => 'required|date',
            'vendor'                  => 'required|string|max:255',
            'return_type'             => 'required|in:invoice,invoice_dp,no_invoice,receive',
            'detailItems'             => 'required|array|min:1',
            'detailItems.*.kode'      => 'required|string',
            'detailItems.*.kuantitas' => 'required|string',
            'detailItems.*.harga'     => 'required|numeric|min:0',
            'detailItems.*.diskon'    => 'nullable|numeric|min:0',
        ];

        if (in_array($returnType, ['invoice', 'invoice_dp'])) {
            $rules['faktur_pembelian_id'] = 'required|string|max:255';
        } elseif ($returnType === 'receive') {
            $rules['penerimaan_barang_id'] = 'required|string|max:255';
        }

        $messages = [
            'no_retur.required'                => 'Nomor Retur wajib diisi.',
            'no_retur.unique'                  => 'Nomor Retur sudah digunakan.',
            'tanggal_retur.required'           => 'Tanggal Retur wajib diisi.',
            'tanggal_retur.date'               => 'Format tanggal tidak valid.',
            'vendor.required'                  => 'Vendor wajib diisi.',
            'return_type.required'             => 'Tipe retur wajib dipilih.',
            'return_type.in'                   => 'Tipe retur tidak valid.',
            'faktur_pembelian_id.required'     => 'Nomor Faktur Pembelian wajib diisi untuk tipe retur Invoice / Invoice DP.',
            'penerimaan_barang_id.required'    => 'Nomor Penerimaan Barang wajib diisi untuk tipe retur Receive.',
            'detailItems.required'             => 'Detail item wajib diisi.',
            'detailItems.min'                  => 'Minimal harus ada 1 item yang diinputkan.',
            'detailItems.*.kode.required'      => 'Kode item wajib diisi.',
            'detailItems.*.kuantitas.required' => 'Kuantitas item wajib diisi.',
            'detailItems.*.harga.required'     => 'Harga item wajib diisi.',
            'detailItems.*.harga.min'          => 'Harga item tidak boleh kurang dari 0.',
            'detailItems.*.diskon.numeric'    => 'Diskon item harus berupa angka.',
            'detailItems.*.diskon.min'        => 'Diskon item tidak boleh kurang dari 0.',
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
            $baseUrl = $branch->getAccurateApiBaseUrl();
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $alamat = null;
            $keterangan = null;
            $syaratBayar = 'C.O.D';
            $kenaPajak = null;
            $totalTermasukPajak = null;
            $diskonKeseluruhan = null;
            $receiveItemNumber = null;
            $invoiceNumber = null;

            if ($returnType === 'receive') {
                $penerimaanBarang = PenerimaanBarang::where('npb', $validatedData['penerimaan_barang_id'])
                    ->orWhere('no_terima', $validatedData['penerimaan_barang_id'])
                    ->first();

                if ($penerimaanBarang) {
                    $keterangan = $penerimaanBarang->keterangan ?? null;
                }
                // Data alamat, syarat_bayar, pajak, diskon bisa diambil dari API receive-item/detail.do jika diperlukan
                try {
                    $riResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($baseUrl . '/receive-item/detail.do', ['number' => $validatedData['penerimaan_barang_id']]);
                    if ($riResponse->successful() && isset($riResponse->json()['d'])) {
                        $riDetail = $riResponse->json()['d'];
                        $alamat = $riDetail['toAddress'] ?? null;
                        $syaratBayar = !empty($riDetail['paymentTermName']) ? $riDetail['paymentTermName'] : 'C.O.D';
                        $kenaPajak = $riDetail['taxable'] ?? null;
                        $totalTermasukPajak = $riDetail['inclusiveTax'] ?? null;
                        $diskonKeseluruhan = $riDetail['cashDiscPercent'] ?? $riDetail['cashDiscount'] ?? null;
                    }
                } catch (\Exception $e) {
                    Log::warning('Fallback receive-item detail: ' . $e->getMessage());
                }

                $receiveItemNumber = $validatedData['penerimaan_barang_id'];
            } elseif (in_array($returnType, ['invoice', 'invoice_dp'])) {
                try {
                    $invoiceResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($baseUrl . '/purchase-invoice/detail.do', [
                        'number' => $validatedData['faktur_pembelian_id'],
                    ]);

                    if ($invoiceResponse->successful() && isset($invoiceResponse->json()['d'])) {
                        $invDetail = $invoiceResponse->json()['d'];
                        $alamat = $invDetail['toAddress'] ?? null;
                        $keterangan = $invDetail['description'] ?? null;
                        $syaratBayar = !empty($invDetail['paymentTermName']) ? $invDetail['paymentTermName'] : 'C.O.D';
                        $kenaPajak = $invDetail['taxable'] ?? null;
                        $totalTermasukPajak = $invDetail['inclusiveTax'] ?? null;
                        $diskonKeseluruhan = $invDetail['cashDiscPercent'] ?? $invDetail['cashDiscount'] ?? null;
                    }
                } catch (\Exception $e) {
                    Log::error('Exception purchase-invoice/detail.do: ' . $e->getMessage());
                }
                $invoiceNumber = $validatedData['faktur_pembelian_id'];
            }

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
                $detailItemsForAccurate[] = $accurateItem;
            }

            $postDataForAccurate = [
                'vendorNo'     => $validatedData['vendor'],
                'transDate'    => date('d/m/Y', strtotime($validatedData['tanggal_retur'])),
                'number'       => $validatedData['no_retur'],
                'detailItem'   => $detailItemsForAccurate,
                'returnType'   => strtoupper($returnType),
                'paymentTermName' => $syaratBayar,
            ];

            if (!empty($alamat)) {
                $postDataForAccurate['toAddress'] = $alamat;
            }
            if (!empty($keterangan)) {
                $postDataForAccurate['description'] = $keterangan;
            }
            if (!empty($receiveItemNumber)) {
                $postDataForAccurate['receiveItemNumber'] = $receiveItemNumber;
            }
            if (!empty($invoiceNumber)) {
                $postDataForAccurate['invoiceNumber'] = $invoiceNumber;
            }
            if (!empty($diskonKeseluruhan) && $diskonKeseluruhan > 0) {
                $diskonFloat = (float) $diskonKeseluruhan;
                if ($diskonFloat > 0 && $diskonFloat <= 100) {
                    $postDataForAccurate['cashDiscPercent'] = $diskonFloat;
                } else {
                    $postDataForAccurate['cashDiscount'] = $diskonFloat;
                }
            }
            if (isset($kenaPajak)) {
                $postDataForAccurate['taxable'] = $kenaPajak;
            }
            if (isset($totalTermasukPajak)) {
                $postDataForAccurate['inclusiveTax'] = $totalTermasukPajak;
            }

            Log::info('PostDataForAccurate retur pembelian prepared:', $postDataForAccurate);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
                'Content-Type'  => 'application/json',
            ])->post($baseUrl . '/purchase-return/save.do', $postDataForAccurate);

            if (!$response->successful()) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Gagal mengirim data ke Accurate API. HTTP Status: ' . $response->status());
            }

            $responseData = $response->json();
            if (isset($responseData['s']) && $responseData['s'] === false) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Accurate API mengembalikan error: ' . ($responseData['m'] ?? 'Unknown error'));
            }

            $returPembelian = ReturPembelian::create([
                'no_retur'               => $validatedData['no_retur'],
                'tanggal_retur'          => $validatedData['tanggal_retur'],
                'kode_customer'          => '',
                'vendor'                 => $validatedData['vendor'],
                'return_type'            => $returnType,
                'faktur_pembelian_id'    => $validatedData['faktur_pembelian_id'] ?? null,
                'penerimaan_barang_id'   => $validatedData['penerimaan_barang_id'] ?? null,
                'alamat'                 => $alamat,
                'keterangan'             => $keterangan,
                'syarat_bayar'           => $syaratBayar,
                'kena_pajak'             => $kenaPajak,
                'total_termasuk_pajak'   => $totalTermasukPajak,
                'diskon_keseluruhan'     => $diskonKeseluruhan,
            ]);

            DB::commit();
            Cache::forget('accurate_retur_pembelian_list_branch_' . $activeBranchId);

            return redirect()->route('retur_pembelian.index')
                ->with('success', 'Data retur pembelian berhasil disimpan ke Accurate dan database lokal.')
                ->with('retur_pembelian', $returPembelian);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('ReturPembelian store exception: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    /**
     * Fetch vendors from Accurate API untuk dropdown
     */
    private function fetchVendorsFromAccurate(Branch $branch, string $baseUrl): array
    {
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $vendorApiUrl = $baseUrl . '/vendor/list.do';
        $data = ['sp.page' => 1, 'sp.pageSize' => 20];

        try {
            $firstPageResponse = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($vendorApiUrl, $data);

            if (!$firstPageResponse->successful()) {
                return [];
            }
            $responseData = $firstPageResponse->json();
            $allVendors = $responseData['d'] ?? [];
            if (!is_array($allVendors)) {
                return [];
            }
            $totalItems = $responseData['sp']['rowCount'] ?? 0;
            $totalPages = (int) ceil($totalItems / 20);
            if ($totalPages > 1) {
                $client = new \GuzzleHttp\Client();
                $promises = [];
                for ($page = 2; $page <= $totalPages; $page++) {
                    $promises[$page] = $client->getAsync($vendorApiUrl, [
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
                            $allVendors = array_merge($allVendors, $pageResponse['d']);
                        }
                    }
                }
            }
            $vendors = [];
            foreach ($allVendors as $v) {
                $vendors[] = [
                    'id' => $v['id'] ?? null,
                    'vendorNo' => $v['vendorNo'] ?? $v['number'] ?? '',
                    'name' => $v['name'] ?? $v['vendorNo'] ?? '',
                ];
            }
            return $vendors;
        } catch (\Exception $e) {
            Log::error('Exception fetching vendors in ReturPembelian: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get receive items from Accurate API (receive-item/list.do) dengan filter.vendorNo
     */
    private function getReceiveItemsFromAccurate(Branch $branch, string $baseUrl, ?string $vendorNo = null): array
    {
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $receiveItemApiUrl = $baseUrl . '/receive-item/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
            'fields' => 'number,vendor',
        ];
        if (!empty($vendorNo)) {
            $data['filter.vendorNo'] = $vendorNo;
        }

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($receiveItemApiUrl, $data);

        $allItems = [];
        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();
            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allItems = $responseData['d'];
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);
                if ($totalPages > 1) {
                    $client = new \GuzzleHttp\Client();
                    $promises = [];
                    for ($page = 2; $page <= $totalPages; $page++) {
                        $queryParams = ['sp.page' => $page, 'sp.pageSize' => 20, 'fields' => 'number,vendor'];
                        if (!empty($vendorNo)) {
                            $queryParams['filter.vendorNo'] = $vendorNo;
                        }
                        $promises[$page] = $client->getAsync($receiveItemApiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ],
                            'query' => $queryParams,
                        ]);
                    }
                    $results = Utils::settle($promises)->wait();
                    foreach ($results as $page => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $pageResponse = json_decode($result['value']->getBody(), true);
                            if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                $allItems = array_merge($allItems, $pageResponse['d']);
                            }
                        }
                    }
                }
                $existingReceiveIds = ReturPembelian::where('vendor', $vendorNo)->pluck('penerimaan_barang_id')->filter()->toArray();
                $allItems = array_values(array_filter($allItems, function ($item) use ($existingReceiveIds) {
                    $num = $item['number'] ?? null;
                    return $num && !in_array($num, $existingReceiveIds);
                }));
            }
        }
        return $allItems;
    }

    /**
     * Get purchase invoices from Accurate API (purchase-invoice/list.do) dengan filter.vendorNo
     */
    private function getPurchaseInvoicesFromAccurate(Branch $branch, string $baseUrl, ?string $vendorNo = null): array
    {
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $purchaseInvoiceApiUrl = $baseUrl . '/purchase-invoice/list.do';
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
            'fields' => 'number,vendor',
        ];
        if (!empty($vendorNo)) {
            $data['filter.vendorNo'] = $vendorNo;
        }

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($purchaseInvoiceApiUrl, $data);

        $allInvoices = [];
        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();
            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allInvoices = $responseData['d'];
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);
                if ($totalPages > 1) {
                    $client = new \GuzzleHttp\Client();
                    $promises = [];
                    for ($page = 2; $page <= $totalPages; $page++) {
                        $queryParams = ['sp.page' => $page, 'sp.pageSize' => 20, 'fields' => 'number,vendor'];
                        if (!empty($vendorNo)) {
                            $queryParams['filter.vendorNo'] = $vendorNo;
                        }
                        $promises[$page] = $client->getAsync($purchaseInvoiceApiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ],
                            'query' => $queryParams,
                        ]);
                    }
                    $results = Utils::settle($promises)->wait();
                    foreach ($results as $page => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $pageResponse = json_decode($result['value']->getBody(), true);
                            if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                $allInvoices = array_merge($allInvoices, $pageResponse['d']);
                            }
                        }
                    }
                }
                $existingInvoiceIds = ReturPembelian::where('vendor', $vendorNo)->pluck('faktur_pembelian_id')->filter()->toArray();
                $allInvoices = array_values(array_filter($allInvoices, function ($item) use ($existingInvoiceIds) {
                    $num = $item['number'] ?? null;
                    return $num && !in_array($num, $existingInvoiceIds);
                }));
            }
        }
        return $allInvoices;
    }
}
