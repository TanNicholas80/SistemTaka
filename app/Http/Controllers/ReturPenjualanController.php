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
                if (is_null($errorMessage))
                    $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info('Data retur penjualan diambil dari cache karena API error');
            } else {
                if (is_null($errorMessage))
                    $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache tersedia.';
                Log::warning('Tidak ada cache tersedia, menampilkan data kosong');
            }
        }

        // Simpan data ke cache:
        // - Hindari menimpa cache dengan array kosong bila API gagal (agar tidak "menghilangkan" data lama).
        // - Cache hanya diupdate kalau API sukses, atau minimal ada data yang berhasil didapat.
        $shouldWriteCache = $apiSuccess || (!empty($returPenjualan) && !$hasApiError);
        if ($shouldWriteCache) {
            $dataToCache = [
                'returPenjualan' => $returPenjualan,
                'errorMessage' => $errorMessage,
            ];
            Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
            Log::info('Data retur penjualan disimpan ke cache', [
                'cache_key' => $cacheKey,
                'count' => is_array($returPenjualan) ? count($returPenjualan) : null,
                'hasApiError' => $hasApiError,
            ]);
        } else {
            Log::warning('Skip cache write karena API gagal dan data kosong', [
                'cache_key' => $cacheKey,
                'apiSuccess' => $apiSuccess,
                'hasApiError' => $hasApiError,
            ]);
        }

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

            if (empty($promises))
                continue;

            // Jalankan batch promise secara paralel
            $results = Utils::settle($promises)->wait();

            // Proses hasil dari setiap promise
            foreach ($results as $returnId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $salesReturnDetails[] = $detailResponse['d'];
                        Log::info("Retur penjualan detail fetched for ID: {$returnId}");
                    }
                } else {
                    $reason = $result['reason'];
                    Log::error("Failed to fetch retur penjualan detail for ID {$returnId}: " . $reason->getMessage());

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

    public function create()
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

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['salesInvoices' => [], 'error' => 'Tidak ada cabang yang aktif.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch || !Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return response()->json(['salesInvoices' => [], 'error' => 'Kredensial API tidak tersedia.'], 400);
        }

        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        // customerNo opsional:
        // - jika ada, list faktur difilter per customer (flow lama)
        // - jika kosong, kembalikan semua faktur yang diizinkan (Belum Lunas + Lunas) sesuai logika controller (flow baru)
        $salesInvoices = $this->getSalesInvoicesFromAccurate($branch, $baseUrl, $customerNo ?: null);

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

        // Dikunci hanya untuk invoice
        if ($returnType !== 'invoice') {
            return response()->json([
                'success' => false,
                'message' => 'Tipe retur dikunci ke invoice.',
            ], 400);
        }
        $detailApiUrl = $baseUrl . '/sales-invoice/detail.do';

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

            // --- Tahap 2: ambil info No Seri/Produksi dari Delivery Order (DO) ---
            // Sales invoice detail terkadang tidak mengirim detailSerialNumber.
            // Dari sales invoice detailItem, kita dapat deliveryOrder.number -> DO.XXXX.
            // Lalu kita ambil delivery-order/detail.do untuk mendapatkan detailSerialNumber.
            if (is_array($detailItems) && $detailItems !== []) {
                $doNumbersSet = [];
                foreach ($detailItems as $di) {
                    $doNum = data_get($di, 'deliveryOrder.number');
                    if (is_string($doNum) && trim($doNum) !== '') {
                        $doNumbersSet[trim($doNum)] = true;
                    }
                }

                $doNumbers = array_keys($doNumbersSet);

                if ($doNumbers !== []) {
                    $baseUrlDoDetailApiUrl = $baseUrl . '/delivery-order/detail.do';

                    $allDoDetailLinesByItemNo = [];

                    foreach ($doNumbers as $doNumber) {
                        try {
                            $doResp = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $apiToken,
                                'X-Api-Signature' => $signature,
                                'X-Api-Timestamp' => $timestamp,
                            ])->get($baseUrlDoDetailApiUrl, ['number' => $doNumber]);

                            if (!$doResp->successful()) {
                                Log::warning('DO detail API gagal (untuk enrichment serial)', [
                                    'do_number' => $doNumber,
                                    'status' => $doResp->status(),
                                ]);
                                continue;
                            }

                            $doBody = $doResp->json();
                            $doD = $doBody['d'] ?? null;
                            if (!is_array($doD))
                                continue;

                            $doDetailItems = $doD['detailItem'] ?? [];
                            if (!is_array($doDetailItems))
                                continue;

                            // Flatten semua DO detail lines ke map by itemNo
                            foreach ($doDetailItems as $doLine) {
                                $itemNo = data_get($doLine, 'item.no');
                                if (!is_string($itemNo) || trim($itemNo) === '')
                                    continue;
                                $itemNo = trim($itemNo);

                                $allDoDetailLinesByItemNo[$itemNo][] = $doLine;
                            }
                        } catch (\Exception $e) {
                            Log::error('Exception enrichment serial dari DO detail.do: ' . $e->getMessage(), [
                                'do_number' => $doNumber,
                            ]);
                        }
                    }

                    // Enrich setiap detailItem sales invoice dengan detailSerialNumber dari DO
                    foreach ($detailItems as $idx => $di) {
                        $salesItemNo = data_get($di, 'item.no');
                        if (!is_string($salesItemNo) || trim($salesItemNo) === '')
                            continue;
                        $salesItemNo = trim($salesItemNo);

                        $salesQty = (float) (data_get($di, 'quantity', 0));
                        $candidates = $allDoDetailLinesByItemNo[$salesItemNo] ?? [];
                        if ($candidates === [])
                            continue;

                        // Prefer kandidat yang quantity-nya paling mirip
                        $picked = null;
                        foreach ($candidates as $cand) {
                            $candQty = (float) (data_get($cand, 'quantity', 0));
                            if ($salesQty > 0 && abs($candQty - $salesQty) < 0.0001) {
                                $picked = $cand;
                                break;
                            }
                        }
                        // Kalau tidak ada match qty, ambil kandidat pertama yang punya detailSerialNumber
                        if ($picked === null) {
                            foreach ($candidates as $cand) {
                                $serials = $cand['detailSerialNumber'] ?? null;
                                if (is_array($serials) && $serials !== []) {
                                    $picked = $cand;
                                    break;
                                }
                            }
                        }
                        // Fallback: kandidat pertama
                        if ($picked === null)
                            $picked = $candidates[0] ?? null;
                        if (!is_array($picked))
                            continue;

                        $serials = $picked['detailSerialNumber'] ?? [];
                        if (!is_array($serials))
                            $serials = [];

                        // Normalisasi agar frontend/backend nyaman: tambahkan serialNumberNo jika perlu
                        $normalizedSerials = [];
                        foreach ($serials as $sn) {
                            if (!is_array($sn))
                                continue;

                            $serialNumberNo = $sn['serialNumberNo'] ?? null;
                            if (empty($serialNumberNo) && isset($sn['serialNumber']['number'])) {
                                $serialNumberNo = $sn['serialNumber']['number'];
                            }
                            $serialNumberNo = isset($serialNumberNo) ? trim((string) $serialNumberNo) : '';

                            $qty = (float) ($sn['quantity'] ?? 0);
                            if ($serialNumberNo === '' || $qty <= 0)
                                continue;

                            $sn['serialNumberNo'] = $serialNumberNo;
                            $normalizedSerials[] = $sn;
                        }

                        // Enrich: overwrite detailSerialNumber dari sales invoice dengan DO serial jika ada
                        if ($normalizedSerials !== []) {
                            $detailItems[$idx]['detailSerialNumber'] = $normalizedSerials;
                        } else {
                            // Jika DO serial kosong, biarkan detailSerialNumber dari sales invoice apa adanya
                            // (tapi biasanya memang kosong, jadi serial tetap tidak tampil)
                        }
                    }
                }
            }

            // --- Tahap 3: Kurangi sisa serial/quantity berdasarkan retur yang sudah ada di lokal ---
            // Tujuan: jika faktur sudah pernah diretur, modal harus menampilkan sisa yang masih bisa diretur.
            $localReturNos = ReturPenjualan::where('no_faktur_penjualan', $number)
                ->where('kode_customer', $branch->customer_id ?? null)
                ->pluck('no_retur')
                ->toArray();

            if (is_array($localReturNos) && $localReturNos !== []) {
                $returnedSerialQtyByItemNo = [];
                $returnedQtyByItemNo = [];

                foreach ($localReturNos as $noRetur) {
                    if (empty($noRetur)) continue;

                    try {
                        $srUrl = $baseUrl . '/sales-return/detail.do';
                        $srResp = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $apiToken,
                            'X-Api-Signature' => $signature,
                            'X-Api-Timestamp' => $timestamp,
                        ])->get($srUrl, ['number' => $noRetur]);

                        if (!$srResp->successful() || !isset($srResp->json()['d'])) {
                            Log::warning('Kurangi serial: gagal ambil sales-return/detail.do', [
                                'invoiceNo' => $number,
                                'noRetur' => $noRetur,
                                'status' => $srResp->status(),
                            ]);
                            continue;
                        }

                        $srDetail = $srResp->json()['d'];
                        $srDetailItems = $srDetail['detailItem'] ?? [];
                        if (is_array($srDetailItems) && $srDetailItems !== []) {
                            $this->mergeReturnedSerialMapsFromDetailItems($srDetailItems, $returnedSerialQtyByItemNo, $returnedQtyByItemNo);
                        }
                    } catch (\Exception $e) {
                        Log::error('Exception kurangi serial berdasarkan sales-return detail', [
                            'invoiceNo' => $number,
                            'noRetur' => $noRetur,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                // Terapkan sisa ke detailItems
                foreach ($detailItems as $idx => $di) {
                    $itemNo = data_get($di, 'item.no');
                    if (empty($itemNo)) continue;
                    $itemNo = trim((string) $itemNo);

                    $serials = data_get($di, 'detailSerialNumber', []);
                    if (is_array($serials) && $serials !== []) {
                        $remainingSerials = [];
                        $sumQty = 0.0;
                        foreach ($serials as $sn) {
                            if (!is_array($sn)) continue;
                            $serialNo = $this->normalizeSerialNumberNo($sn);
                            if (empty($serialNo)) continue;
                            $qtyOriginal = (float) ($sn['quantity'] ?? 0);
                            if ($qtyOriginal <= 0) continue;

                            $qtyReturned = (float) ($returnedSerialQtyByItemNo[$itemNo][$serialNo] ?? 0);
                            $remainingQty = $qtyOriginal - $qtyReturned;
                            if ($remainingQty > 0.0000001) {
                                $sn['serialNumberNo'] = $serialNo;
                                $sn['quantity'] = $remainingQty;
                                $remainingSerials[] = $sn;
                                $sumQty += $remainingQty;
                            }
                        }

                        $detailItems[$idx]['detailSerialNumber'] = $remainingSerials;
                        $detailItems[$idx]['quantity'] = $sumQty;
                    } else {
                        // fallback jika item tidak serial-based
                        $qtyOriginal = (float) ($di['quantity'] ?? 0);
                        $qtyReturned = (float) ($returnedQtyByItemNo[$itemNo] ?? 0);
                        $remainingQty = $qtyOriginal - $qtyReturned;
                        if ($remainingQty < 0) $remainingQty = 0;
                        $detailItems[$idx]['quantity'] = $remainingQty;
                        if ($remainingQty <= 0) {
                            $detailItems[$idx]['detailSerialNumber'] = [];
                        }
                    }
                }

                // Filter item yang sudah tersisa 0
                $detailItems = array_values(array_filter($detailItems, function ($di) {
                    if (!is_array($di)) return false;
                    $qty = (float) ($di['quantity'] ?? 0);
                    return $qty > 0.0000001;
                }));
            }

            return response()->json([
                'success' => true,
                'detailItems' => $detailItems,
                // Agar form bisa mengisi pelanggan otomatis setelah klik "Lanjut"
                'customer' => $detail['customer'] ?? null,
                // Agar form bisa menampilkan ringkasan Diskon & Total
                'cashDiscPercent' => $detail['cashDiscPercent'] ?? null,
                'cashDiscount' => $detail['cashDiscount'] ?? null,
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

        // Ambil nomor referensi yang sudah pernah dipakai untuk retur (kolom yang ada di DB: no_faktur_penjualan).
        // Catatan: sebelumnya memakai `faktur_penjualan_id` namun kolom tsb tidak ada di tabel `retur_penjualans`.
        $kodeCustomerFilter = !empty($customerNo) ? $customerNo : ($branch->customer_id ?? null);
        $existingPengirimanIds = $kodeCustomerFilter
            ? ReturPenjualan::where('kode_customer', $kodeCustomerFilter)->pluck('no_faktur_penjualan')->toArray()
            : ReturPenjualan::pluck('no_faktur_penjualan')->toArray();

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

        // Faktur yang boleh di-retur: "Belum Lunas" dan "Lunas"
        // Ikuti normalisasi/heuristic yang dipakai di PrintBarcodeReturController.
        $salesInvoicesAllowed = array_filter($allSalesInvoices, function ($invoice) {
            $statusNameUpper = strtoupper(trim((string) ($invoice['statusName'] ?? '')));
            if ($statusNameUpper === 'BELUM LUNAS')
                return true;
            if ($statusNameUpper === 'LUNAS')
                return true;

            // Heuristic: beberapa variasi bisa mengandung kedua kata (mis. "BELUM ... LUNAS")
            if (str_contains($statusNameUpper, 'BELUM') && str_contains($statusNameUpper, 'LUNAS')) {
                return true;
            }

            return false;
        });
        $salesInvoicesAllowed = array_values($salesInvoicesAllowed);

        // Build map: invoiceNo => list(no_retur) dari database lokal
        $kodeCustomerFilter = !empty($customerNo) ? $customerNo : ($branch->customer_id ?? null);
        $allowedInvoiceNos = array_values(array_unique(array_map(function ($inv) {
            return $inv['number'] ?? null;
        }, $salesInvoicesAllowed)));
        $allowedInvoiceNos = array_values(array_filter($allowedInvoiceNos, function ($x) { return !empty($x); }));

        $localRetursQuery = ReturPenjualan::whereIn('no_faktur_penjualan', $allowedInvoiceNos);
        if (!empty($kodeCustomerFilter)) {
            $localRetursQuery->where('kode_customer', $kodeCustomerFilter);
        }

        $localReturs = $localRetursQuery->get(['no_faktur_penjualan', 'no_retur']);
        $localReturNosByInvoice = [];
        foreach ($localReturs as $r) {
            $invoiceNo = $r->no_faktur_penjualan;
            $noRetur = $r->no_retur;
            if (empty($invoiceNo) || empty($noRetur)) continue;
            if (!isset($localReturNosByInvoice[$invoiceNo])) $localReturNosByInvoice[$invoiceNo] = [];
            $localReturNosByInvoice[$invoiceNo][] = $noRetur;
        }

        // Filter invoices:
        // - tampilkan jika belum pernah diretur di lokal
        // - tampilkan juga jika sudah pernah diretur tapi sisa qty return (serial) masih ada
        $salesInvoices = array_filter($salesInvoicesAllowed, function ($salesInvoice) use ($localReturNosByInvoice, $branch, $baseUrl, $apiToken, $timestamp, $signature) {
            $invoiceNo = $salesInvoice['number'] ?? null;
            if (empty($invoiceNo)) return false;

            if (empty($localReturNosByInvoice[$invoiceNo])) {
                return true;
            }

            $noReturs = $localReturNosByInvoice[$invoiceNo] ?? [];
            if (!is_array($noReturs) || $noReturs === []) return true;

            return $this->invoiceHasRemainingReturnQtyBySerials(
                $branch,
                $baseUrl,
                $invoiceNo,
                $noReturs,
                $apiToken,
                $timestamp,
                $signature
            );
        });

        $salesInvoices = array_values($salesInvoices);

        Log::info('Sales Invoices filtered successfully (Belum Lunas + Lunas):', [
            'total_from_api' => count($allSalesInvoices),
            'allowed_count' => count($salesInvoicesAllowed),
            'used_in_database' => count($localReturs),
            'filtered_available' => count($salesInvoices)
        ]);

        return $salesInvoices;
    }

    private function invoiceHasRemainingReturnQtyBySerials(
        Branch $branch,
        string $baseUrl,
        string $invoiceNo,
        array $noReturs,
        string $apiToken,
        string $timestamp,
        string $signature
    ): bool {
        try {
            $clientHeaders = [
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ];

            // 1) Ambil detail sales invoice untuk menemukan delivery order yang terkait
            $salesInvoiceDetailUrl = $baseUrl . '/sales-invoice/detail.do';
            $invoiceResp = Http::withHeaders($clientHeaders)->get($salesInvoiceDetailUrl, ['number' => $invoiceNo]);
            if (!$invoiceResp->successful() || !isset($invoiceResp->json()['d'])) {
                // Kalau gagal ambil detail, jangan menyembunyikan invoice
                Log::warning('invoiceHasRemainingReturnQtyBySerials: gagal ambil sales-invoice/detail.do', [
                    'invoiceNo' => $invoiceNo,
                    'status' => $invoiceResp->status(),
                ]);
                return true;
            }

            $invoiceDetail = $invoiceResp->json()['d'];
            $detailItems = $invoiceDetail['detailItem'] ?? [];

            $doNumbersSet = [];
            if (is_array($detailItems)) {
                foreach ($detailItems as $di) {
                    $doNum = data_get($di, 'deliveryOrder.number');
                    if (is_string($doNum) && trim($doNum) !== '') {
                        $doNumbersSet[trim($doNum)] = true;
                    }
                }
            }

            $doNumbers = array_keys($doNumbersSet);

            // 2) Ambil original serials dari delivery-order/detail.do
            // Tapi pilih serial yang sesuai dengan baris item pada sales-invoice/detail.do
            // (mengikuti mekanisme di getReferensiDetailAjax yang memakai heuristic quantity match).
            $allDoDetailLinesByItemNo = [];
            if ($doNumbers !== []) {
                foreach ($doNumbers as $doNumber) {
                    $doUrl = $baseUrl . '/delivery-order/detail.do';
                    $doResp = Http::withHeaders($clientHeaders)->get($doUrl, ['number' => $doNumber]);
                    if (!$doResp->successful() || !isset($doResp->json()['d'])) continue;

                    $doDetail = $doResp->json()['d'];
                    $doDetailItems = $doDetail['detailItem'] ?? [];
                    if (!is_array($doDetailItems)) continue;

                    foreach ($doDetailItems as $doLine) {
                        $itemNo = data_get($doLine, 'item.no');
                        if (!is_string($itemNo) || trim($itemNo) === '') continue;
                        $itemNo = trim($itemNo);
                        if (!isset($allDoDetailLinesByItemNo[$itemNo])) $allDoDetailLinesByItemNo[$itemNo] = [];
                        $allDoDetailLinesByItemNo[$itemNo][] = $doLine;
                    }
                }
            }

            $originalSerialQtyByItemNo = [];
            $originalQtyByItemNo = [];
            if (is_array($detailItems)) {
                foreach ($detailItems as $di) {
                    if (!is_array($di)) continue;
                    $salesItemNo = data_get($di, 'item.no');
                    if (!is_string($salesItemNo) || trim($salesItemNo) === '') continue;
                    $salesItemNo = trim($salesItemNo);

                    $salesQty = (float) (data_get($di, 'quantity', 0));
                    $candidates = $allDoDetailLinesByItemNo[$salesItemNo] ?? [];
                    if ($candidates === []) continue;

                    // Prefer kandidat yang quantity paling mirip
                    $picked = null;
                    foreach ($candidates as $cand) {
                        $candQty = (float) data_get($cand, 'quantity', 0);
                        if ($salesQty > 0 && abs($candQty - $salesQty) < 0.0001) {
                            $picked = $cand;
                            break;
                        }
                    }
                    // Jika tidak ada match kuantitas, ambil yang punya serial
                    if ($picked === null) {
                        foreach ($candidates as $cand) {
                            $serials = data_get($cand, 'detailSerialNumber');
                            if (is_array($serials) && $serials !== []) {
                                $picked = $cand;
                                break;
                            }
                        }
                    }
                    // Fallback: kandidat pertama
                    if ($picked === null) $picked = $candidates[0] ?? null;
                    if (!is_array($picked)) continue;

                    $pickedSerials = data_get($picked, 'detailSerialNumber', []);
                    if (is_array($pickedSerials) && $pickedSerials !== []) {
                        foreach ($pickedSerials as $sn) {
                            if (!is_array($sn)) continue;
                            $serialNo = $this->normalizeSerialNumberNo($sn);
                            $qty = (float) ($sn['quantity'] ?? 0);
                            if (empty($serialNo) || $qty <= 0) continue;
                            if (!isset($originalSerialQtyByItemNo[$salesItemNo])) $originalSerialQtyByItemNo[$salesItemNo] = [];
                            if (!isset($originalSerialQtyByItemNo[$salesItemNo][$serialNo])) $originalSerialQtyByItemNo[$salesItemNo][$serialNo] = 0;
                            $originalSerialQtyByItemNo[$salesItemNo][$serialNo] += $qty;
                            $originalQtyByItemNo[$salesItemNo] = ($originalQtyByItemNo[$salesItemNo] ?? 0) + $qty;
                        }
                    } else {
                        $originalQtyByItemNo[$salesItemNo] = ($originalQtyByItemNo[$salesItemNo] ?? 0) + $salesQty;
                    }
                }
            }

            // 3) Ambil serials yang sudah diretur dari sales-return/detail.do (berdasarkan no_retur lokal)
            $returnedSerialQtyByItemNo = [];
            $returnedQtyByItemNo = [];
            foreach ($noReturs as $noRetur) {
                if (empty($noRetur)) continue;
                $srUrl = $baseUrl . '/sales-return/detail.do';
                $srResp = Http::withHeaders($clientHeaders)->get($srUrl, ['number' => $noRetur]);
                if (!$srResp->successful() || !isset($srResp->json()['d'])) {
                    // Jika gagal ambil detail retur, jangan menyembunyikan
                    Log::warning('invoiceHasRemainingReturnQtyBySerials: gagal ambil sales-return/detail.do', [
                        'invoiceNo' => $invoiceNo,
                        'noRetur' => $noRetur,
                        'status' => $srResp->status(),
                    ]);
                    return true;
                }

                $srDetail = $srResp->json()['d'];
                $srDetailItems = $srDetail['detailItem'] ?? [];
                if (!is_array($srDetailItems)) continue;

                $this->mergeReturnedSerialMapsFromDetailItems($srDetailItems, $returnedSerialQtyByItemNo, $returnedQtyByItemNo);
            }

            // 4) Hitung sisa
            $hasAnySerials = !empty($originalSerialQtyByItemNo);
            if ($hasAnySerials) {
                foreach ($originalSerialQtyByItemNo as $itemNo => $serialMap) {
                    foreach ($serialMap as $serialNo => $qtyOriginal) {
                        $qtyReturned = (float) (($returnedSerialQtyByItemNo[$itemNo][$serialNo] ?? 0));
                        $remaining = (float) $qtyOriginal - (float) $qtyReturned;
                        if ($remaining > 0.0000001) return true;
                    }
                }
                return false;
            }

            // fallback jika tidak ada serial detail
            foreach ($originalQtyByItemNo as $itemNo => $qtyOriginal) {
                $qtyReturned = $returnedQtyByItemNo[$itemNo] ?? 0;
                $remaining = (float) $qtyOriginal - (float) $qtyReturned;
                if ($remaining > 0.0000001) return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('invoiceHasRemainingReturnQtyBySerials exception:', [
                'invoiceNo' => $invoiceNo,
                'message' => $e->getMessage(),
            ]);
            return true; // fail-open
        }
    }

    private function mergeOriginalSerialMapsFromDetailItems(array $detailItems, array &$serialQtyByItemNo, array &$qtyByItemNo): void
    {
        foreach ($detailItems as $di) {
            if (!is_array($di)) continue;
            $itemNo = data_get($di, 'item.no');
            if (!is_string($itemNo) || trim($itemNo) === '') continue;
            $itemNo = trim($itemNo);

            $qtyByItemNo[$itemNo] = ($qtyByItemNo[$itemNo] ?? 0) + (float) (data_get($di, 'quantity', 0));

            $serials = data_get($di, 'detailSerialNumber', []);
            if (!is_array($serials) || $serials === []) continue;

            foreach ($serials as $sn) {
                if (!is_array($sn)) continue;
                $serialNo = $this->normalizeSerialNumberNo($sn);
                $qty = (float) ($sn['quantity'] ?? 0);
                if (empty($serialNo) || $qty <= 0) continue;

                if (!isset($serialQtyByItemNo[$itemNo])) $serialQtyByItemNo[$itemNo] = [];
                if (!isset($serialQtyByItemNo[$itemNo][$serialNo])) $serialQtyByItemNo[$itemNo][$serialNo] = 0;
                $serialQtyByItemNo[$itemNo][$serialNo] += $qty;
            }
        }
    }

    private function mergeReturnedSerialMapsFromDetailItems(array $detailItems, array &$serialQtyByItemNo, array &$qtyByItemNo): void
    {
        foreach ($detailItems as $di) {
            if (!is_array($di)) continue;
            $itemNo = data_get($di, 'item.no');
            if (!is_string($itemNo) || trim($itemNo) === '') continue;
            $itemNo = trim($itemNo);

            $qtyByItemNo[$itemNo] = ($qtyByItemNo[$itemNo] ?? 0) + (float) (data_get($di, 'quantity', 0));

            $serials = data_get($di, 'detailSerialNumber', []);
            if (!is_array($serials) || $serials === []) continue;

            foreach ($serials as $sn) {
                if (!is_array($sn)) continue;
                $serialNo = $this->normalizeSerialNumberNo($sn);
                $qty = (float) ($sn['quantity'] ?? 0);
                if (empty($serialNo) || $qty <= 0) continue;

                if (!isset($serialQtyByItemNo[$itemNo])) $serialQtyByItemNo[$itemNo] = [];
                if (!isset($serialQtyByItemNo[$itemNo][$serialNo])) $serialQtyByItemNo[$itemNo][$serialNo] = 0;
                $serialQtyByItemNo[$itemNo][$serialNo] += $qty;
            }
        }
    }

    private function normalizeSerialNumberNo($sn): string
    {
        if (!is_array($sn)) return '';
        $serialNo = $sn['serialNumberNo'] ?? null;
        if (empty($serialNo) && isset($sn['serialNumber']['number'])) {
            $serialNo = $sn['serialNumber']['number'];
        }
        $serialNo = isset($serialNo) ? trim((string) $serialNo) : '';
        return $serialNo;
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

        // Support alias: frontend kirim `no_faktur_penjualan`, backend tetap pakai `faktur_penjualan_id`.
        $noFakturPenjualan = $request->input('no_faktur_penjualan');
        $fakturPenjualanId = $request->input('faktur_penjualan_id');
        if (empty($fakturPenjualanId) && !empty($noFakturPenjualan)) {
            $request->merge(['faktur_penjualan_id' => $noFakturPenjualan]);
        }

        // UI mengunci tipe retur ke invoice, backend tetap enforce untuk keamanan.
        $request->merge(['return_type' => 'invoice']);
        $returnType = 'invoice';
        $returnStatusType = $request->input('return_status_type', 'not_returned');

        $rules = [
            'no_retur' => 'required|string|max:255|unique:retur_penjualans,no_retur',
            'tanggal_retur' => 'required|date',
            'pelanggan_id' => 'required|string|max:255',
            'return_type' => 'required|in:invoice',
            'return_status_type' => 'required|in:not_returned,partially_returned,returned',
            'diskon_keseluruhan' => 'nullable|numeric|min:0',
            'detailItems' => 'required|array|min:1',
            'detailItems.*.kode' => 'required|string',
            'detailItems.*.kuantitas' => 'required|string',
            'detailItems.*.harga' => 'required|numeric|min:0',
            'detailItems.*.diskon' => 'nullable|numeric|min:0',
            // Optional (kompatibilitas bila frontend sudah mengirim serial)
            'detailItems.*.detailSerialNumber' => 'nullable|array',
            'detailItems.*.detailSerialNumber.*.serialNumberNo' => 'nullable|string',
            'detailItems.*.detailSerialNumber.*.quantity' => 'nullable|numeric|min:0',
        ];

        $rules['faktur_penjualan_id'] = 'required|string|max:255';

        if ($returnStatusType === 'partially_returned') {
            $rules['detailItems.*.return_detail_status'] = 'required|in:NOT_RETURNED,RETURNED';
        }

        $messages = [
            'no_retur.required' => 'Nomor Retur wajib diisi.',
            'no_retur.unique' => 'Nomor Retur sudah digunakan.',
            'tanggal_retur.required' => 'Tanggal Retur wajib diisi.',
            'tanggal_retur.date' => 'Format tanggal tidak valid.',
            'pelanggan_id.required' => 'Pelanggan wajib diisi.',
            'return_type.required' => 'Tipe retur wajib dipilih.',
            'return_type.in' => 'Tipe retur tidak valid.',
            'return_status_type.required' => 'Status pengembalian wajib dipilih.',
            'return_status_type.in' => 'Status pengembalian tidak valid.',
            'pengiriman_pesanan_id.required' => 'Nomor Pengiriman wajib diisi untuk tipe retur Delivery.',
            'faktur_penjualan_id.required' => 'Nomor Faktur wajib diisi untuk tipe retur Invoice / Invoice DP.',
            'detailItems.required' => 'Detail item wajib diisi.',
            'detailItems.min' => 'Minimal harus ada 1 item yang diinputkan.',
            'detailItems.*.kode.required' => 'Kode item wajib diisi.',
            'detailItems.*.kuantitas.required' => 'Kuantitas item wajib diisi.',
            'detailItems.*.harga.required' => 'Harga item wajib diisi.',
            'detailItems.*.harga.min' => 'Harga item tidak boleh kurang dari 0.',
            'detailItems.*.diskon.numeric' => 'Diskon item harus berupa angka.',
            'detailItems.*.diskon.min' => 'Diskon item tidak boleh kurang dari 0.',
            'detailItems.*.return_detail_status.required' => 'Status pengembalian per item wajib diisi jika status retur Partially Returned.',
            'detailItems.*.return_detail_status.in' => 'Status pengembalian per item tidak valid.',
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

                $invoiceNumber = $validatedData['faktur_penjualan_id'];

                // Selalu ambil detail invoice dari Accurate untuk validasi transDate.
                // Requirement: validasi tanggal retur tidak boleh sebelum tanggal faktur dari response detail API.
                try {
                    $invoiceResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($baseUrl . '/sales-invoice/detail.do', [
                                'number' => $invoiceNumber,
                            ]);

                    if (!$invoiceResponse->successful() || !isset($invoiceResponse->json()['d'])) {
                        DB::rollBack();
                        return back()->withInput()->with('error', 'Data Faktur Penjualan dengan nomor ' . $invoiceNumber . ' tidak ditemukan di Accurate.');
                    }

                    $invDetail = $invoiceResponse->json()['d'];
                } catch (\Exception $e) {
                    Log::error('Exception saat ambil sales-invoice/detail.do untuk validasi transDate: ' . $e->getMessage());
                    DB::rollBack();
                    return back()->withInput()->with('error', 'Data Faktur Penjualan gagal diambil dari Accurate untuk validasi tanggal: ' . $e->getMessage());
                }

                // Validasi tanggal retur >= transDate faktur.
                $invoiceTransDateStr = $invDetail['transDate'] ?? null;
                if (empty($invoiceTransDateStr)) {
                    DB::rollBack();
                    return back()->withInput()->with('error', 'Response sales-invoice/detail.do tidak memiliki field `transDate` untuk faktur ' . $invoiceNumber . '.');
                }

                $tanggalRetur = Carbon::parse($validatedData['tanggal_retur'])->startOfDay();
                try {
                    $invoiceTransDate = Carbon::createFromFormat('d/m/Y', (string) $invoiceTransDateStr)->startOfDay();
                } catch (\Exception $e) {
                    // Fallback bila format berbeda
                    $invoiceTransDate = Carbon::parse((string) $invoiceTransDateStr)->startOfDay();
                }

                if ($tanggalRetur->lt($invoiceTransDate)) {
                    DB::rollBack();
                    return back()->withInput()->with('error', 'Tanggal retur tidak boleh sebelum tanggal faktur penjualan (' . $invoiceTransDateStr . ').');
                }

                // Isi referensi lain (alamat/keterangan/syarat bayar/pajak/diskon keseluruhan)
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
                    'itemNo' => (string) ($item['kode'] ?? ''),
                    'quantity' => (float) ($item['kuantitas'] ?? 0),
                    'unitPrice' => (float) ($item['harga'] ?? 0),
                    'warehouseName' => 'GUDANG RETUR', // Default statis sesuai requirement
                ];

                // Form mengirim `diskon` sebagai angka input user.
                // Aturan mapping untuk Accurate:
                // - 0-100 => itemDiscPercent
                // - >100  => itemCashDiscount
                if (isset($item['diskon']) && (float) $item['diskon'] > 0) {
                    $diskonInput = (float) $item['diskon'];
                    if ($diskonInput > 0 && $diskonInput <= 100) {
                        $accurateItem['itemDiscPercent'] = $diskonInput;
                    } else {
                        $accurateItem['itemCashDiscount'] = $diskonInput;
                    }
                }

                if ($accurateReturnStatusType === 'PARTIALLY_RETURNED') {
                    $accurateItem['returnDetailStatusType'] = $item['return_detail_status'] ?? 'NOT_RETURNED';
                }

                // Opsional: dukung detailSerialNumber bila dikirim frontend
                // (struktur: detailSerialNumber[] berisi {serialNumberNo, quantity})
                $detailSerials = $item['detailSerialNumber'] ?? null;
                if (is_array($detailSerials) && $detailSerials !== []) {
                    $normalizedSerials = [];
                    foreach ($detailSerials as $sn) {
                        if (!is_array($sn)) {
                            continue;
                        }
                        $serialNumberNo = trim((string) ($sn['serialNumberNo'] ?? ''));
                        $qty = (float) ($sn['quantity'] ?? 0);
                        if ($serialNumberNo === '' || $qty <= 0) {
                            continue;
                        }
                        $normalizedSerials[] = [
                            'serialNumberNo' => $serialNumberNo,
                            'quantity' => $qty,
                        ];
                    }
                    if ($normalizedSerials !== []) {
                        $accurateItem['detailSerialNumber'] = $normalizedSerials;
                    }
                }

                $detailItemsForAccurate[] = $accurateItem;
            }

            // === Siapkan request body untuk Accurate API ===
            $postDataForAccurate = [
                'customerNo' => $validatedData['pelanggan_id'],
                'transDate' => date('d/m/Y', strtotime($validatedData['tanggal_retur'])),
                'number' => $validatedData['no_retur'],
                'detailItem' => $detailItemsForAccurate,
                'returnType' => strtoupper($returnType),
                'returnStatusType' => $accurateReturnStatusType,
                'paymentTermName' => $syaratBayar,
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
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/sales-return/save.do', $postDataForAccurate);

            if (!$response->successful()) {
                DB::rollBack();
                Log::error('Accurate API /sales-return/save.do HTTP failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'postDataForAccurate' => $postDataForAccurate,
                ]);
                return back()->withInput()->with('error', 'Gagal mengirim data ke Accurate API. HTTP Status: ' . $response->status());
            }

            $responseData = null;
            try {
                $responseData = $response->json();
            } catch (\Exception $e) {
                // Jika body bukan JSON, tetap log biar ada petunjuk
                Log::warning('Accurate API /sales-return/save.do non-JSON response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'postDataForAccurate' => $postDataForAccurate,
                ]);
            }

            if (isset($responseData['s']) && $responseData['s'] === false) {
                DB::rollBack();
                Log::error('Accurate API /sales-return/save.do returned s=false', [
                    'responseData' => $responseData,
                    'rawBody' => $response->body(),
                    'postDataForAccurate' => $postDataForAccurate,
                ]);
                return back()->withInput()->with('error', 'Accurate API mengembalikan error: ' . ($responseData['m'] ?? 'Unknown error'));
            }

            // === Simpan ke database lokal ===
            $returPenjualan = ReturPenjualan::create([
                'no_retur' => $validatedData['no_retur'],
                'tanggal_retur' => $validatedData['tanggal_retur'],
                'pelanggan_id' => $validatedData['pelanggan_id'],
                'return_type' => $returnType,
                'return_status_type' => $validatedData['return_status_type'],
                // Kolom DB yang ada: no_faktur_penjualan (lihat migration 2026_02_16_082641_create_retur_penjualans_table.php)
                'no_faktur_penjualan' => $validatedData['faktur_penjualan_id'] ?? null,
                'alamat' => $alamat,
                'keterangan' => $keterangan,
                'syarat_bayar' => $syaratBayar,
                'kena_pajak' => $kenaPajak,
                'total_termasuk_pajak' => $totalTermasukPajak,
                'diskon_keseluruhan' => $diskonKeseluruhan,
                'kode_customer' => $branch->customer_id,
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
            } elseif (in_array($returnType, ['invoice', 'invoice_dp']) && $returPenjualan->no_faktur_penjualan) {
                $fakturPenjualanRef = FakturPenjualan::where('no_faktur', $returPenjualan->no_faktur_penjualan)
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
            } elseif ($referenceType === 'invoice' && $returPenjualan->no_faktur_penjualan) {
                $invoiceResponse = $httpClient->get($baseUrl . '/sales-invoice/detail.do', [
                    'number' => $returPenjualan->no_faktur_penjualan,
                ]);

                if ($invoiceResponse->successful() && isset($invoiceResponse->json()['d'])) {
                    $accurateReferenceDetail = $invoiceResponse->json()['d'];
                } else {
                    Log::warning('Gagal fetch detail sales invoice untuk retur penjualan', [
                        'no_faktur_penjualan' => $returPenjualan->no_faktur_penjualan,
                        'status' => $invoiceResponse->status(),
                    ]);
                }
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $errorMessage = "Data retur penjualan dengan nomor {$no_retur} tidak ditemukan.";
            Log::error('Retur penjualan tidak ditemukan: ' . $e->getMessage(), ['no_retur' => $no_retur]);

            $dataToCache = [
                'returPenjualan' => null,
                'accurateDetail' => null,
                'accurateDetailItems' => [],
                'accurateReferenceDetail' => null,
                'referenceType' => null,
                'pengirimanPesanan' => null,
                'fakturPenjualanRef' => null,
                'errorMessage' => $errorMessage,
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
                $returPenjualan = $cachedData['returPenjualan'] ?? $returPenjualan;
                $accurateDetail = $cachedData['accurateDetail'] ?? null;
                $accurateDetailItems = $cachedData['accurateDetailItems'] ?? [];
                $accurateReferenceDetail = $cachedData['accurateReferenceDetail'] ?? null;
                $referenceType = $cachedData['referenceType'] ?? null;
                $pengirimanPesanan = $cachedData['pengirimanPesanan'] ?? null;
                $fakturPenjualanRef = $cachedData['fakturPenjualanRef'] ?? null;
                if (is_null($errorMessage))
                    $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail retur penjualan {$no_retur} dari cache karena API gagal.");
            } else {
                Log::warning("Tidak ada data cache tersedia sebagai fallback untuk no_retur: {$no_retur}");
            }

            $dataToCache = [
                'returPenjualan' => $returPenjualan,
                'accurateDetail' => $accurateDetail,
                'accurateDetailItems' => $accurateDetailItems,
                'accurateReferenceDetail' => $accurateReferenceDetail,
                'referenceType' => $referenceType,
                'pengirimanPesanan' => $pengirimanPesanan,
                'fakturPenjualanRef' => $fakturPenjualanRef,
                'errorMessage' => $errorMessage,
            ];
        }

        if (!isset($dataToCache)) {
            $dataToCache = [
                'returPenjualan' => $returPenjualan,
                'accurateDetail' => $accurateDetail,
                'accurateDetailItems' => $accurateDetailItems,
                'accurateReferenceDetail' => $accurateReferenceDetail,
                'referenceType' => $referenceType,
                'pengirimanPesanan' => $pengirimanPesanan,
                'fakturPenjualanRef' => $fakturPenjualanRef,
                'errorMessage' => $errorMessage,
            ];
        }

        if ($apiSuccess) {
            Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
            Log::info("Data detail retur penjualan {$no_retur} berhasil diambil dari API dan disimpan ke cache");
        }

        return view('retur_penjualan.detail', $dataToCache);
    }
}
