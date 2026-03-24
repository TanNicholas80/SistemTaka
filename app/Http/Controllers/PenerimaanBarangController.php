<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\PackingList;
use App\Models\PenerimaanBarang;
use App\Models\Branch;
use Carbon\Carbon;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PenerimaanBarangController extends Controller
{
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
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
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
                $apiToken = $branch->accurate_api_token;
                $signatureSecret = $branch->accurate_signature_secret;
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
        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            throw new \Exception('Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
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
            'fields' => 'transDate,number,status'
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
                                'fields' => 'transDate,number,status'
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
            ];
        }

        Log::info('Successfully fetched all purchase orders from Accurate', [
            'total_count' => count($allPurchaseOrders),
            'filtered_count' => count($purchase_orders),
            'filter' => 'ONPROCESS & WAITING'
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

        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        // Get data directly from API (sudah menggunakan kredensial dari cabang di dalam fungsi)
        $purchase_order = $this->getPurchaseOrdersFromAccurate();

        // Generate NPB & No Terima (preview untuk form)
        $npb = PenerimaanBarang::generateNpb($branch->customer_id);
        $noTerima = PenerimaanBarang::generateNoTerima($branch->customer_id);
        $kodeCustomer = $branch->customer_id;

        // Packing list dengan status approved untuk dipilih
        $packingLists = PackingList::where('kode_customer', $branch->customer_id)
            ->where('status', PackingList::STATUS_APPROVED)
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('penerimaan_barang.create', compact('purchase_order', 'npb', 'noTerima', 'packingLists', 'kodeCustomer'));
    }

    public function getDetailPo(Request $request)
    {
        $validated = $request->validate([
            'no_po' => 'required|string',
            'npb' => 'required|string',
            'packing_list_ids' => 'required|array',
            'packing_list_ids.*' => 'integer|exists:packing_list,id',
        ]);

        $activeBranchId = session('active_branch');
        $branch = Branch::find($activeBranchId);

        if (!$branch || !$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return response()->json(['error' => true, 'message' => 'Konfigurasi cabang tidak valid.'], 400);
        }

        try {
            // 1. Ambil vendor + detail item dari Accurate PO
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;
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
                    $vendorData = ['vendorNo' => $resData['d']['vendor']['vendorNo'] ?? null];
                }
                foreach ($resData['d']['detailItem'] ?? [] as $detail) {
                    $itemNo = $detail['item']['no'] ?? null;
                    $itemName = $detail['item']['name'] ?? null;
                    if ($itemNo) {
                        $accurateItems[] = [
                            'no' => $itemNo,
                            'name' => $itemName,
                            'no_numeric' => preg_replace('/\D/', '', $itemNo),
                        ];
                    }
                }
            }

            Log::info('Accurate items for PO matching', [
                'no_po' => $validated['no_po'],
                'items' => collect($accurateItems)->map(fn($i) => $i['no'] . ' => ' . $i['name'])->toArray(),
            ]);

            // 2. Ambil barcode dari packing list yang dipilih
            $packingLists = PackingList::whereIn('id', $validated['packing_list_ids'])
                ->where('kode_customer', $branch->customer_id)
                ->whereIn('status', [PackingList::STATUS_APPROVED, PackingList::STATUS_USED])
                ->get();

            if ($packingLists->isEmpty()) {
                throw new \Exception('Packing list tidak ditemukan atau status belum approved.');
            }

            $nplList = $packingLists->pluck('npl')->toArray();
            $barcodes = Barcode::whereIn('no_packing_list', $nplList)
                ->where('kode_customer', $branch->customer_id)
                ->whereIn('status', [Barcode::STATUS_APPROVED, Barcode::STATUS_UPLOADED])
                ->orderBy('keterangan', 'asc')
                ->get();

            if ($barcodes->isEmpty()) {
                throw new \Exception('Tidak ada barcode di packing list yang dipilih.');
            }

            // 3. Map barcode data per item (tanpa merge)
            $barangList = [];

            foreach ($barcodes as $barcode) {
                $length = (float) str_replace(',', '.', $barcode->length ?? 0);
                $uom = strtoupper(trim($barcode->uom ?? ''));
                $kuantitas = ($uom === 'YD') ? round($length * 0.9) : $length;

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

                // Matching nama_barang dari Accurate via beberapa strategi
                $namaBarang = null;

                if (!empty($accurateItems)) {
                    if (!$namaBarang && $materialCode12) {
                        foreach ($accurateItems as $item) {
                            if ($item['no'] === $materialCode12) {
                                $namaBarang = $item['name'];
                                break;
                            }
                        }
                    }
                    if (!$namaBarang && $materialCode12) {
                        foreach ($accurateItems as $item) {
                            $accNumeric = $item['no_numeric'];
                            if ($accNumeric !== '' && substr($accNumeric, -12) === $materialCode12) {
                                $namaBarang = $item['name'];
                                break;
                            }
                        }
                    }
                    if (!$namaBarang && $materialCode12) {
                        foreach ($accurateItems as $item) {
                            if (str_contains($item['no'], $materialCode12) || str_contains($materialCode12, $item['no'])) {
                                $namaBarang = $item['name'];
                                break;
                            }
                        }
                    }
                    if (!$namaBarang) {
                        $barcodeNama = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace('KC', '', $barcode->nama ?? '')));
                        foreach ($accurateItems as $item) {
                            $accNama = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $item['name'] ?? ''));
                            if ($accNama !== '' && $accNama === $barcodeNama) {
                                $namaBarang = $item['name'];
                                break;
                            }
                        }
                    }
                }

                if (!$namaBarang) {
                    $namaBarang = $barcode->keterangan;
                }

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
    public function mapItemDetailsFromPackingLists($noPo, $npb, array $packingListIds, $updateIdPb = false, $includeVendor = false)
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            throw new \Exception('Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            throw new \Exception('Cabang tidak valid.');
        }

        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            throw new \Exception('Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
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
                $itemDetails[] = [
                    'nama_barang' => $detail['item']['name'] ?? null,
                    'kode_barang' => $detail['item']['no'] ?? null,
                    'unit' => $detail['item']['unit1']['name'] ?? null,
                    'panjang_total' => 0,
                    'availableToSell' => 0,
                    'unit_price' => 0,
                ];
            }
            if ($includeVendor && isset($resData['d']['vendor'])) {
                $vendorData = ['vendorNo' => $resData['d']['vendor']['vendorNo'] ?? null];
            }
        }

        // Ambil packing list (approved atau used) dan barcodes-nya
        $packingLists = PackingList::whereIn('id', $packingListIds)
            ->where('kode_customer', $branch->customer_id)
            ->whereIn('status', [PackingList::STATUS_APPROVED, PackingList::STATUS_USED])
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
            throw new \Exception('Packing list tidak ditemukan atau status belum approved/used.');
        }

        $nplList = $packingLists->pluck('npl')->toArray();
        $barcodes = Barcode::whereIn('no_packing_list', $nplList)
            ->where('kode_customer', $branch->customer_id)
            ->whereIn('status', [Barcode::STATUS_APPROVED, Barcode::STATUS_UPLOADED])
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

            if ($updateIdPb) {
                $barcode->update(['id_pb' => $npb]);
            }

            $matchedBarcodeIds[] = $barcode->id;

            $length = (float) str_replace(',', '.', $barcode->length ?? 0);
            $uom = strtoupper(trim($barcode->uom ?? ''));
            $barcodeQty = ($uom === 'YD') ? round($length * 0.9) : $length;

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

        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        Log::info('Received form data:', $request->all());

        $validator = Validator::make($request->all(), [
            'no_po' => 'required|string|max:255',
            'vendor' => 'required|string|max:255',
            'no_terima' => 'required|string|max:255|unique:penerimaan_barangs,no_terima',
            'npb' => 'required|string|max:255|unique:penerimaan_barangs,npb',
            'tanggal' => 'required|date',
            'packing_list_ids' => 'required|array',
            'packing_list_ids.*' => 'integer|exists:packing_list,id',
        ], [
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

        // Database transaction
        DB::beginTransaction();

        try {
            $validatedData = $validator->validated();
            $userId = Auth::id();

            // 1. Verify Temp Barcodes first
            $masterBarcodes = \App\Models\TempMasterBarcode::where('no_po', $validatedData['no_po'])->where('user_id', $userId)->get();
            $scannedCount = \App\Models\TempScannedBarcode::where('no_po', $validatedData['no_po'])->where('user_id', $userId)->count();

            if ($masterBarcodes->isEmpty() || $masterBarcodes->count() !== $scannedCount) {
                DB::rollBack();
                return back()->with('error', 'Validasi fisik belum 100% selesai atau data master kedaluwarsa. Silakan upload dan scan ulang.');
            }

            // 2. Commit Temp to Permanent Tables
            foreach ($masterBarcodes as $master) {
                // Barcode
                Barcode::updateOrCreate(
                    ['barcode' => $master->barcode, 'kode_customer' => $master->kode_customer],
                    [
                        'no_packing_list' => $master->no_packing_list,
                        'no_billing' => $master->no_billing,
                        'kode_barang' => $master->kode_barang,
                        'keterangan' => $master->keterangan,
                        'nomor_seri' => $master->nomor_seri,
                        'pcs' => $master->pcs,
                        'berat_kg' => $master->berat_kg,
                        'panjang_mlc' => $master->panjang_mlc,
                        'warna' => $master->warna,
                        'bale' => $master->bale,
                        'harga_ppn' => $master->harga_ppn,
                        'harga_jual' => $master->harga_jual,
                        'pemasok' => $master->pemasok,
                        'customer' => $master->customer,
                        'kontrak' => $master->kontrak,
                        'subtotal' => $master->subtotal,
                        'tanggal' => $master->tanggal,
                        'jatuh' => $master->jatuh,
                        'no_vehicle' => $master->no_vehicle,
                    ]
                );

                // Barang Masuk
                \App\Models\BarangMasuk::firstOrCreate([
                    'nbrg' => $master->barcode,
                    'kode_customer' => $master->kode_customer,
                ], [
                    'tanggal' => $validatedData['tanggal'],
                ]);
            }

            // Clear temporary data after successful commit
            \App\Models\TempMasterBarcode::where('no_po', $validatedData['no_po'])->where('user_id', $userId)->delete();
            \App\Models\TempScannedBarcode::where('no_po', $validatedData['no_po'])->where('user_id', $userId)->delete();

            // Simpan penerimaan barang dengan kode_customer dari cabang aktif
            $penerimaan = PenerimaanBarang::create([
                'no_po' => $validatedData['no_po'],
                'vendor' => $validatedData['vendor'],
                'no_terima' => $validatedData['no_terima'],
                'npb' => $validatedData['npb'],
                'tanggal' => $validatedData['tanggal'],
                'kode_customer' => $branch->customer_id,
            ]);

            // Attach packing lists (many-to-many)
            $penerimaan->packingLists()->attach($validatedData['packing_list_ids']);

            Log::info('Penerimaan barang created:', $penerimaan->toArray());

            // Ambil detail item dari packing list
            $result = $this->mapItemDetailsFromPackingLists(
                $validatedData['no_po'],
                $validatedData['npb'],
                $validatedData['packing_list_ids'],
                true,  // Update id_pb pada barcode
                true   // Include vendor
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

            $detailItems = [];
            foreach ($itemDetails as $item) {
                if ($item['panjang_total'] > 0) {
                    $detailItems[] = [
                        'itemNo' => $item['item_no_pl'] ?? $item['kode_barang'],
                        // 'detailName' => $item['nama_barang'],
                        'quantity' => (float) $item['panjang_total'],
                        'unitPrice' => (float) $item['unit_price'],
                        'itemUnitName' => 'METER',
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

            // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
            $apiToken = $branch->accurate_api_token;
            $signatureSecret = $branch->accurate_signature_secret;
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
                // Update status packing list: closed jika semua barcode ter-match, used jika parsial
                foreach ($validatedData['packing_list_ids'] as $plId) {
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
                        $pl->update(['status' => PackingList::STATUS_USED]);
                    }
                }

                DB::commit();

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
                    'status_code' => $response->status(),
                    'response' => $responseData,
                    'request_data' => $postData,
                    'error_message' => $errorMessage
                ]);

                foreach ($barcodes as $barcode) {
                    $barcode->status = Barcode::STATUS_APPROVED;
                    $barcode->save();
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

            if (isset($barcodes)) {
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
            // Penghapusan throw exception jika empty agar detail PO yang kuantitasnya 0 tetap tampil

            $itemDetailsData = $this->mapItemDetailsFromPackingLists(
                $penerimaanBarang->no_po,
                $penerimaanBarang->npb,
                $packingListIds,
                false,
                false
            );

            $matchedItems = $itemDetailsData['items'];

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
