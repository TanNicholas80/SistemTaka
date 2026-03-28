<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\HasilStockOpname;
use App\Models\HasilStockOpnameBarcode;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HasilStockOpnameController extends Controller
{
    /**
     * Membangun URL API dari url_accurate branch
     * 
     * @param Branch $branch Branch yang aktif
     * @param string $endpoint Endpoint API (contoh: 'stock-opname-result/list.do')
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

        $cacheKey = 'accurate_hasil_stock_opname_list_' . $activeBranchId;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;

        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $hasilstockOpname = $cachedData['hasilstockOpname'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data hasil stock opname diambil dari cache');
            return view('hasil_stock_opname.index', compact('hasilstockOpname', 'errorMessage'));
        }

        // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $apiUrl = $this->buildApiUrl($branch, 'stock-opname-result/list.do');

        $hasilstockOpname = [];
        $allHasilstockOpname = []; // Untuk menampung hasil dari list.do
        $apiSuccess = false;
        $hasApiError = false;

        try {
            // Langkah 1: Ambil daftar data (list.do)
            $firstPageResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($apiUrl, ['sp.page' => 1, 'sp.pageSize' => 20]);

            if ($firstPageResponse->successful()) {
                $responseData = $firstPageResponse->json();
                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allHasilstockOpname = $responseData['d'];
                    $totalItems = $responseData['sp']['rowCount'] ?? 0;
                    $totalPages = ceil($totalItems / 20);

                    if ($totalPages > 1) {
                        // Buat array untuk menyimpan semua promise
                        $promises = [];
                        $client = new \GuzzleHttp\Client();

                        // Buat promise untuk setiap halaman (mulai dari halaman 2)
                        for ($page = 2; $page <= $totalPages; $page++) {
                            $promises[$page] = $client->getAsync($apiUrl, [
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
                                    $allHasilstockOpname = array_merge($allHasilstockOpname, $pageResponse['d']);
                                    Log::info("Accurate Hasil stock opname list page {$page} response processed");
                                }
                            } else {
                                Log::error("Failed to fetch page {$page}: " . $result['reason']);
                            }
                        }
                    }

                    // Langkah 2: PANGGIL FUNGSI DETAIL setelah mendapatkan list
                    $detailsResult = $this->fetchHasilStockOpnameDetailsInBatches($allHasilstockOpname, $branch, $apiToken, $signature, $timestamp);
                    $hasilstockOpname = $detailsResult['details']; // Data final adalah data detail

                    // Cek jika ada error dari proses fetch detail
                    if ($detailsResult['has_error']) {
                        $hasApiError = true;
                    }

                    $apiSuccess = true;
                }
            } else {
                Log::error('Gagal mengambil daftar Hasil Stock Opname', ['response' => $firstPageResponse->body()]);
                if ($firstPageResponse->status() == 429) $hasApiError = true;
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil Hasil Stock Opname: ' . $e->getMessage());
        }

        if ($hasApiError) {
            $errorMessage = 'Gagal memuat semua data karena terlalu banyak permintaan ke server. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi.';
        }

        if (!$apiSuccess && empty($hasilstockOpname)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $hasilstockOpname = $cachedData['hasilstockOpname'] ?? [];
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
            } else {
                if (is_null($errorMessage)) $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache.';
            }
        }

        Cache::put($cacheKey, ['hasilstockOpname' => $hasilstockOpname, 'errorMessage' => $errorMessage], $cacheDuration * 60);
        return view('hasil_stock_opname.index', compact('hasilstockOpname', 'errorMessage'));
    }

    /**
     * Mengambil detail hasil stock opname dalam batch untuk mengoptimalkan performa
     */
    private function fetchHasilStockOpnameDetailsInBatches($listHasil, $branch, $apiToken, $signature, $timestamp, $batchSize = 5)
    {
        $hasilstockOpnameDetails = [];
        $batches = array_chunk($listHasil, $batchSize);
        $hasApiError = false; // Flag error untuk fungsi ini

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $hasil) {
                if (!isset($hasil['id'])) continue;
                $detailUrl = $this->buildApiUrl($branch, 'stock-opname-result/detail.do?id=' . $hasil['id']);
                $promises[$hasil['id']] = $client->getAsync($detailUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ]
                ]);
            }
            if (empty($promises)) continue;

            $results = Utils::settle($promises)->wait();

            foreach ($results as $hasilId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        // Gunakan ID sebagai key untuk memastikan tidak ada duplikat
                        $hasilstockOpnameDetails[$hasilId] = $detailResponse['d'];
                    }
                } else {
                    $reason = $result['reason'];
                    Log::error("Gagal mengambil detail untuk ID {$hasilId}: " . $reason->getMessage());
                    if ($reason instanceof ClientException && $reason->getResponse()->getStatusCode() == 429) {
                        $hasApiError = true;
                    }
                }
            }
            usleep(200000); // 200ms
        }

        return [
            'details' => array_values($hasilstockOpnameDetails), // Kembalikan sebagai array biasa
            'has_error' => $hasApiError
        ];
    }

    public function show($number, Request $request)
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

        // Cache key yang unik per branch
        $cacheKey = 'accurate_hasil_stock_opname_detail_' . $activeBranchId . '_' . $number;
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $detail = null;
        $hasilstockOpname = [];

        // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Fetch detail for specific number - selalu coba dari API terlebih dahulu
        $detailApiUrl = $this->buildApiUrl($branch, 'stock-opname-result/detail.do?number=' . $number);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($detailApiUrl);

            Log::info('API Detail Response for Number ' . $number . ':', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $detailData = $response->json();

                if (isset($detailData['d'])) {
                    $detail = $detailData['d'];

                    // Fetch all stock opname results list dengan parallel processing
                    $hasilstockOpname = $this->getAllHasilStockOpnameList($branch, $apiToken, $signature, $timestamp);

                    $dataToCache = [
                        'detail' => $detail,
                        'hasilstockOpname' => $hasilstockOpname,
                        'errorMessage' => null
                    ];

                    // Simpan data ke cache jika berhasil mendapatkan data dari API
                    Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
                    Log::info("Detail hasil stock opname {$number} berhasil diambil dari API dan disimpan ke cache");
                } else {
                    $errorMessage = "Data detail untuk nomor {$number} tidak ditemukan.";
                }
            } else {
                Log::error('API detail request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($response->status() == 404) {
                    $errorMessage = "Hasil Stock Opname dengan nomor {$number} tidak ditemukan.";
                } else {
                    $errorMessage = "Gagal mengambil data dari server. Silakan coba lagi.";
                }
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil detail: ' . $e->getMessage());
            $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
        }

        // Jika detail masih null, coba ambil dari cache
        if (is_null($detail)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $detail = $cachedData['detail'] ?? null;
                $hasilstockOpname = $cachedData['hasilstockOpname'] ?? [];
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail {$number} dari cache karena API gagal.");
            }
        }

        return view('hasil_stock_opname.detail', compact('detail', 'hasilstockOpname', 'errorMessage'));
    }

    /**
     * Mengambil semua data hasil stock opname list dengan parallel processing
     */
    private function getAllHasilStockOpnameList($branch, $apiToken, $signature, $timestamp)
    {
        $listApiUrl = $this->buildApiUrl($branch, 'stock-opname-result/list.do');
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20
        ];

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($listApiUrl, $data);

        $allHasilstockOpname = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allHasilstockOpname = $responseData['d'];

                // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20); // 20 adalah pageSize

                // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client();

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

                    $results = Utils::settle($promises)->wait();

                    foreach ($results as $page => $result) {
                        if ($result['state'] === 'fulfilled') {
                            $pageResponse = json_decode($result['value']->getBody(), true);
                            if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                $allHasilstockOpname = array_merge($allHasilstockOpname, $pageResponse['d']);
                            }
                        }
                    }
                }

                // Ambil detail untuk masing-masing hasil stock opname secara batch
                return $this->fetchHasilStockOpnameDetailsInBatches($allHasilstockOpname, $branch, $apiToken, $signature, $timestamp);
            }
        }

        return [];
    }

    public function showApproval($number, $namaBarang, Request $request)
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
        $cacheKey = 'accurate_hasil_stock_opname_approval_' . $number . '_' . md5($namaBarang);
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 10; // 10 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $detail = null;
        $approvals = null;
        $search_info = null;

        // Ambil kredensial Accurate dari branch
        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        $detailApiUrl = $this->buildApiUrl($branch, 'stock-opname-result/detail.do?number=' . $number);

        try {
            // Selalu coba ambil data dari API terlebih dahulu
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($detailApiUrl);

            Log::info('API Detail Response for Number ' . $number . ':', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $detailData = $response->json();
                if (isset($detailData['d'])) {
                    $detail = $detailData['d'];

                    // Format nama barang untuk validasi dengan logic yang lebih fleksibel
                    $decodedNamaBarang = urldecode($namaBarang);
                    $namaBarangFormatted = $this->formatNamaBarangForApproval($decodedNamaBarang);

                    Log::info('Nama barang formatting:', [
                        'original' => $decodedNamaBarang,
                        'formatted' => $namaBarangFormatted
                    ]);

                    // Validasi apakah Barcode dengan nama barang tersebut ada (exact match) berdasarkan kode_customer
                    $approvalByName = Barcode::where('kode_customer', $branch->customer_id ?? '')
                        ->get()
                        ->first(fn ($b) => $b->nama === $namaBarangFormatted);

                    if (!$approvalByName) {
                        $errorMessage = "Barcode/barang tidak ditemukan untuk '{$namaBarangFormatted}'.";
                    } else {
                        // Ambil NOP dari detail hasil stock opname
                        $nopFromDetail = $detailData['d']['number'] ?? null;

                        if (!$nopFromDetail) {
                            $errorMessage = 'NOP tidak ditemukan dari detail API.';
                        } else {
                            // Ambil barcode dari tabel lokal berdasarkan NOP
                            $localBarcodes = HasilStockOpnameBarcode::where('nop', $nopFromDetail)
                                ->pluck('barcode')
                                ->toArray();

                            Log::info('Local barcodes found for NOP ' . $nopFromDetail . ':', $localBarcodes);

                            // Hanya cari approval berdasarkan barcode yang ada di tabel lokal
                            if (empty($localBarcodes)) {
                                $errorMessage = "Tidak ada barcode yang ditemukan untuk NOP '{$nopFromDetail}'.";
                            } else {
                                // Cari Barcode yang cocok (barcode + nama) untuk kode_customer
                                $approvalsByBarcode = Barcode::where('kode_customer', $branch->customer_id ?? '')
                                    ->whereIn('barcode', $localBarcodes)
                                    ->get()
                                    ->filter(fn ($b) => $b->nama === $namaBarangFormatted)
                                    ->values();

                                Log::info('Approvals found by barcode and name:', [
                                    'barcode_count' => count($localBarcodes),
                                    'approval_count' => $approvalsByBarcode->count(),
                                    'nop' => $nopFromDetail,
                                    'barcodes' => $localBarcodes,
                                    'nama_barang' => $namaBarangFormatted
                                ]);

                                if ($approvalsByBarcode->isNotEmpty()) {
                                    $approvals = $approvalsByBarcode;
                                    $search_info = [
                                        'barcode_count' => count($localBarcodes),
                                        'approval_count' => $approvalsByBarcode->count(),
                                        'searched_barcodes' => $localBarcodes,
                                        'nop' => $nopFromDetail,
                                        'nama_barang' => $namaBarangFormatted
                                    ];

                                    $dataToCache = [
                                        'detail' => $detail,
                                        'approvals' => $approvals,
                                        'search_info' => $search_info,
                                        'errorMessage' => null
                                    ];

                                    // Simpan data ke cache jika berhasil mendapatkan data dari API
                                    Cache::put($cacheKey, $dataToCache, $cacheDuration * 60);
                                    Log::info("Data approval hasil stock opname {$number} dengan nama barang {$namaBarang} berhasil diambil dari API dan disimpan ke cache");
                                } else {
                                    $errorMessage = "Data approval tidak ditemukan untuk barang '{$namaBarangFormatted}' dengan barcode yang terkait dengan NOP '{$nopFromDetail}'. " .
                                        "Barcode yang dicari: " . implode(', ', $localBarcodes);
                                }
                            }
                        }
                    }
                } else {
                    $errorMessage = "Data detail untuk nomor {$number} tidak ditemukan.";
                }
            } else {
                Log::error('API detail request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($response->status() == 404) {
                    $errorMessage = "Hasil Stock Opname dengan nomor {$number} tidak ditemukan.";
                } else {
                    $errorMessage = "Gagal mengambil data dari server. Silakan coba lagi.";
                }
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil detail approval: ' . $e->getMessage());
            $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
        }

        // Jika detail masih null, coba ambil dari cache
        if (is_null($detail)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $detail = $cachedData['detail'] ?? null;
                $approvals = $cachedData['approvals'] ?? null;
                $search_info = $cachedData['search_info'] ?? null;
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail approval {$number} dengan nama barang {$namaBarang} dari cache karena API gagal.");
            }
        }

        // Pastikan approvals tidak null untuk menghindari error di view
        if (is_null($approvals)) {
            $approvals = collect(); // Empty collection
        }

        return view('hasil_stock_opname.detail-approval', compact('detail', 'approvals', 'search_info', 'errorMessage'));
    }

    /**
     * Format nama barang untuk mencocokkan dengan format di database Approval
     * 
     * @param string $namaBarang Nama barang dari URL decode
     * @return string Nama barang yang sudah diformat
     */
    private function formatNamaBarangForApproval($namaBarang)
    {
        // Trim dan bersihkan input
        $namaBarang = trim($namaBarang);

        // Log untuk debugging
        Log::info('Processing nama barang:', ['input' => $namaBarang]);

        // Check apakah nama barang sudah mengandung "ICHIMURA" atau brand khusus lainnya
        $specialBrands = ['ICHIMURA', 'MIZUNO', 'ADIDAS', 'NIKE']; // Tambahkan brand khusus lainnya sesuai kebutuhan
        $isSpecialBrand = false;

        foreach ($specialBrands as $brand) {
            if (stripos($namaBarang, $brand) !== false) {
                $isSpecialBrand = true;
                break;
            }
        }

        if ($isSpecialBrand) {
            // Format khusus untuk brand tertentu (tanpa prefix KC)
            // Contoh: ICHIMURA JPN 150 079 HTM -> ICHIMURA JPN 150 #079 HTM

            // Split by space
            $parts = explode(' ', $namaBarang);

            // Untuk ICHIMURA dan brand khusus, cari kode warna yang BUKAN ukuran
            // Ukuran biasanya 150, 160, dll (angka bulat)
            // Kode warna biasanya 001, 053, 079, dll (3 digit dengan leading zero atau angka non-bulat)
            $codePosition = -1;
            for ($i = 0; $i < count($parts); $i++) {
                // Skip angka bulat seperti 150, 160, dll (ukuran)
                if (preg_match('/^(150|160|170|180|190|200|210|220)$/', $parts[$i])) {
                    continue;
                }

                // Check untuk kode warna (3 digit dengan possible leading zero)
                if (preg_match('/^\d{3}$/', $parts[$i])) {
                    // Pastikan ini bukan ukuran dengan melihat nilainya
                    $numValue = intval($parts[$i]);
                    // Kode warna biasanya < 100 atau memiliki leading zero
                    if ($numValue < 100 || $parts[$i][0] === '0' || ($numValue > 500 && $numValue < 999)) {
                        $codePosition = $i;
                        break;
                    }
                }
            }

            if ($codePosition !== -1) {
                // Tambahkan # sebelum kode warna
                $parts[$codePosition] = '#' . $parts[$codePosition];
            }

            $formatted = implode(' ', $parts);
        } else {
            // Format standar dengan prefix KC
            // Contoh: VALLETA 150 001 -> KC VALLETA 150 #001

            // Split by space
            $parts = explode(' ', $namaBarang);

            // Pattern matching untuk berbagai format
            if (count($parts) >= 3) {
                $baseParts = [];
                $codeIndex = -1;

                // Identifikasi struktur: [BRAND] [SIZE/MODEL] [COLOR_CODE] [OPTIONAL_SUFFIX]
                for ($i = 0; $i < count($parts); $i++) {
                    // Skip angka bulat ukuran (150, 160, dll)
                    if (preg_match('/^(150|160|170|180|190|200|210|220)$/', $parts[$i])) {
                        $baseParts[] = $parts[$i];
                        continue;
                    }

                    // Check untuk kode warna
                    if (preg_match('/^\d{3}$/', $parts[$i])) {
                        $numValue = intval($parts[$i]);
                        // Kode warna biasanya < 100 atau memiliki leading zero atau > 500
                        if ($numValue < 100 || $parts[$i][0] === '0' || ($numValue > 500 && $numValue < 999)) {
                            $codeIndex = $i;
                            break;
                        }
                    }

                    // Check untuk kode alfanumerik seperti ABU
                    if (preg_match('/^[A-Z]{3,}$/i', $parts[$i]) && $i >= 2) {
                        // Ini kemungkinan kode warna alfanumerik
                        $codeIndex = $i;
                        break;
                    }

                    $baseParts[] = $parts[$i];
                }

                if ($codeIndex !== -1) {
                    // Format dengan # untuk kode warna dan suffix-nya
                    $colorCode = '#' . $parts[$codeIndex];

                    // Gabungkan suffix jika ada (seperti HTM, NAVY, SAT, T, U, dll)
                    $suffix = '';
                    if ($codeIndex + 1 < count($parts)) {
                        $suffixParts = array_slice($parts, $codeIndex + 1);
                        $suffix = ' ' . implode(' ', $suffixParts);
                    }

                    // Gabungkan semua bagian
                    $formatted = 'KC ' . implode(' ', $baseParts) . ' ' . $colorCode . $suffix;
                } else {
                    // Jika tidak ada pola yang cocok, coba format alternatif
                    // Mungkin kode warna adalah bagian terakhir
                    $lastPart = array_pop($parts);
                    $mainName = implode(' ', $parts);

                    // Check apakah lastPart adalah kode yang valid
                    if (preg_match('/^[A-Z0-9]+$/i', $lastPart)) {
                        $formatted = "KC {$mainName} #{$lastPart}";
                    } else {
                        // Jika tidak, kembalikan format standar
                        $formatted = "KC {$namaBarang}";
                    }
                }
            } else {
                // Format default jika struktur tidak dikenali
                $formatted = "KC {$namaBarang}";
            }
        }

        // Bersihkan spasi berlebih
        $formatted = preg_replace('/\s+/', ' ', trim($formatted));

        Log::info('Formatted nama barang:', ['output' => $formatted]);

        return $formatted;
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

            // Ambil daftar stock opname result yang sudah ada dengan parallel processing
            $existingResultNumbers = $this->getExistingResultNumbers($branch, $apiToken, $signature, $timestamp);

            // Ambil daftar order dengan parallel processing
            $stockOpnameOrders = $this->getStockOpnameOrders($branch, $apiToken, $signature, $timestamp, $existingResultNumbers);

        $nop = HasilStockOpname::generateNop();
        $formReadonly = false;
        $barang = [];
        $selectedTanggal = date('Y-m-d');
        $selectedStockOpname = '';

        return view('hasil_stock_opname.create', compact('stockOpnameOrders', 'nop', 'formReadonly', 'barang', 'selectedTanggal', 'selectedStockOpname'));
    }

    /**
     * Mengambil daftar stock opname result yang sudah ada
     */
    private function getExistingResultNumbers($branch, $apiToken, $signature, $timestamp)
    {
        $resultApiUrl = $this->buildApiUrl($branch, 'stock-opname-result/list.do');
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20
        ];

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($resultApiUrl, $data);

        $existingResultNumbers = [];
        $allResults = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allResults = $responseData['d'];

                // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);

                // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client();

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $promises[$page] = $client->getAsync($resultApiUrl, [
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
                                $allResults = array_merge($allResults, $pageResponse['d']);
                            }
                        }
                    }
                }

                // Ambil detail untuk setiap result secara batch
                $resultDetails = $this->fetchResultDetailsInBatches($allResults, $branch, $apiToken, $signature, $timestamp);

                foreach ($resultDetails as $detail) {
                    if (isset($detail['order']['number'])) {
                        $existingResultNumbers[] = $detail['order']['number'];
                    }
                }
            }
        }

        return $existingResultNumbers;
    }

    /**
     * Mengambil detail stock opname result dalam batch untuk mengoptimalkan performa
     */
    private function fetchResultDetailsInBatches($stockOpnameResults, $branch, $apiToken, $signature, $timestamp, $batchSize = 5)
    {
        $resultDetails = [];
        $batches = array_chunk($stockOpnameResults, $batchSize);

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $result) {
                $detailUrl = $this->buildApiUrl($branch, 'stock-opname-result/detail.do?id=' . $result['id']);
                $promises[$result['id']] = $client->getAsync($detailUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ]
                ]);
            }

            // Jalankan batch promise secara paralel
            $results = Utils::settle($promises)->wait();

            // Proses hasil dari setiap promise
            foreach ($results as $resultId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $resultDetails[$resultId] = $detailResponse['d'];
                        Log::info("Stock opname result detail fetched for ID: {$resultId}");
                    }
                } else {
                    Log::error("Failed to fetch stock opname result detail for ID {$resultId}: " . $result['reason']);
                }
            }

            // Tambahkan delay kecil antara batch untuk menghindari rate limiting
            usleep(200000); // 200ms
        }

        return $resultDetails;
    }

    /**
     * Mengambil daftar stock opname orders dengan parallel processing
     * dan memfilter yang belum memiliki result
     */
    private function getStockOpnameOrders($branch, $apiToken, $signature, $timestamp, $existingResultNumbers = [])
    {
        $orderApiUrl = $this->buildApiUrl($branch, 'stock-opname-order/list.do');
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20
        ];

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($orderApiUrl, $data);

        $stockOpnameOrders = [];
        $allOrders = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();

            Log::info('Accurate Stock opname orders list first page response:', $responseData);

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allOrders = $responseData['d'];

                // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20); // 20 adalah pageSize

                // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client();

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $promises[$page] = $client->getAsync($orderApiUrl, [
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
                                $allOrders = array_merge($allOrders, $pageResponse['d']);
                                Log::info("Accurate Stock opname orders list page {$page} response processed");
                            }
                        } else {
                            Log::error("Failed to fetch stock opname orders page {$page}: " . $result['reason']);
                        }
                    }
                }

                // Ambil detail untuk setiap order secara batch
                $orderDetails = $this->fetchOrderDetailsInBatches($allOrders, $branch, $apiToken, $signature, $timestamp);

                // Filter orders yang belum memiliki result
                foreach ($orderDetails as $orderDetail) {
                    if (isset($orderDetail['number']) && !in_array($orderDetail['number'], $existingResultNumbers)) {
                        $stockOpnameOrders[] = [
                            'id' => $orderDetail['id'],
                            'number' => $orderDetail['number'],
                            'transDate' => $orderDetail['transDate'] ?? null,
                            'warehouse' => $orderDetail['warehouse'] ?? null,
                            'detailItem' => $orderDetail['detailItem'] ?? [],
                            'memo' => $orderDetail['memo'] ?? null,
                            'status' => $orderDetail['status'] ?? null
                        ];
                    }
                }

                Log::info('Stock opname orders filtered successfully', [
                    'total_orders' => count($allOrders),
                    'filtered_orders' => count($stockOpnameOrders),
                    'existing_results' => count($existingResultNumbers)
                ]);
            }
        } else {
            Log::error('Gagal mengambil daftar stock opname orders dari Accurate.', [
                'response' => $firstPageResponse->body()
            ]);
        }

        return $stockOpnameOrders;
    }

    /**
     * Mengambil detail stock opname orders dalam batch untuk mengoptimalkan performa
     */
    private function fetchOrderDetailsInBatches($orders, $branch, $apiToken, $signature, $timestamp, $batchSize = 5)
    {
        $orderDetails = [];
        $batches = array_chunk($orders, $batchSize);

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $order) {
                $detailUrl = $this->buildApiUrl($branch, 'stock-opname-order/detail.do?id=' . $order['id']);
                $promises[$order['id']] = $client->getAsync($detailUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ]
                ]);
            }

            // Jalankan batch promise secara paralel
            $results = Utils::settle($promises)->wait();

            // Proses hasil dari setiap promise
            foreach ($results as $orderId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $orderDetails[$orderId] = $detailResponse['d'];
                        Log::info("Stock opname order detail fetched for ID: {$orderId}");
                    }
                } else {
                    Log::error("Failed to fetch stock opname order detail for ID {$orderId}: " . $result['reason']);
                }
            }

            // Tambahkan delay kecil antara batch untuk menghindari rate limiting
            usleep(200000); // 200ms
        }

        return $orderDetails;
    }

    /**
     * Helper function to fetch all stock opname orders with parallel processing
     */
    private function fetchAllStockOpnameOrders($branch, $apiToken, $signature, $timestamp)
    {
        $orderApiUrl = $this->buildApiUrl($branch, 'stock-opname-order/list.do');
        $data = [
            'sp.page' => 1,
            'sp.pageSize' => 20
        ];

        $firstPageResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ])->get($orderApiUrl, $data);

        $allOrders = [];

        if ($firstPageResponse->successful()) {
            $responseData = $firstPageResponse->json();

            if (isset($responseData['d']) && is_array($responseData['d'])) {
                $allOrders = $responseData['d'];

                // Hitung total halaman berdasarkan sp.rowCount jika tersedia
                $totalItems = $responseData['sp']['rowCount'] ?? 0;
                $totalPages = ceil($totalItems / 20);

                Log::info('Stock opname orders pagination info', [
                    'total_items' => $totalItems,
                    'total_pages' => $totalPages,
                    'current_page_items' => count($allOrders)
                ]);

                // Jika lebih dari 1 halaman, ambil halaman lainnya secara paralel
                if ($totalPages > 1) {
                    $promises = [];
                    $client = new \GuzzleHttp\Client();

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $promises[$page] = $client->getAsync($orderApiUrl, [
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
                                $allOrders = array_merge($allOrders, $pageResponse['d']);
                                Log::info("Stock opname orders page {$page} processed successfully");
                            }
                        } else {
                            Log::error("Failed to fetch stock opname orders page {$page}: " . $result['reason']);
                        }
                    }
                }

                // Ambil detail untuk setiap order secara batch
                $orderDetails = $this->fetchOrderDetailsInBatches($allOrders, $branch, $apiToken, $signature, $timestamp);

                Log::info('All stock opname orders fetched successfully', [
                    'total_orders' => count($allOrders),
                    'detailed_orders' => count($orderDetails)
                ]);

                return array_values($orderDetails);
            }
        } else {
            Log::error('Failed to fetch stock opname orders first page', [
                'status' => $firstPageResponse->status(),
                'response' => $firstPageResponse->body()
            ]);
        }

        return [];
    }

    /**
     * Modified lanjut function with caching and parallel processing
     */
    public function lanjut(Request $request)
    {
        try {
            $request->validate([
                'tanggal' => 'required|date',
                'stock_opname' => 'required',
                'nop' => 'required',
            ]);

            $stockOpnameNumber = $request->input('stock_opname');

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

            // Fetch all stock opname orders with parallel processing
            $stockOpnameOrders = $this->fetchAllStockOpnameOrders($branch, $apiToken, $signature, $timestamp);

            $stockOpnameId = null;
            $barang = [];

            // Find the specific order
            foreach ($stockOpnameOrders as $order) {
                if (isset($order['number']) && $order['number'] == $stockOpnameNumber) {
                    $stockOpnameId = $order['id'];
                    $barang = $order['detailItem'] ?? [];
                    break;
                }
            }

            if ($stockOpnameId === null) {
                Log::error('Stock Opname Order tidak ditemukan', [
                    'stock_opname_number' => $stockOpnameNumber,
                    'available_orders' => count($stockOpnameOrders)
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Stock Opname Order tidak ditemukan.'
                ], 404);
            }

            $responseData = [
                'barang' => $barang,
                'stock_opname_id' => $stockOpnameId,
                'stock_opname_number' => $stockOpnameNumber,
            ];

            // Return JSON response for AJAX
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil divalidasi',
                'data' => array_merge($responseData, [
                    'tanggal' => $request->input('tanggal'),
                    'nop' => $request->input('nop')
                ])
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Lanjut method error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modified store function with caching and parallel processing
     */
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

        // Terima Data Json
        $barcodesJson = $request->input('barcodes_json');
        $scanBarangJson = $request->input('scan_barang_json');

        // Validasi bahwa JSON tersedia dan valid
        if (!$scanBarangJson) {
            return back()->with('error', 'Data scan barang tidak ditemukan');
        }

        // Decode JSON dengan error handling
        try {
            $barcodes = json_decode($barcodesJson, true, 512, JSON_THROW_ON_ERROR) ?? [];
            $scanBarang = json_decode($scanBarangJson, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (\JsonException $e) {
            Log::error('JSON decode error:', ['error' => $e->getMessage()]);
            return back()->with('error', 'Format data tidak valid: ' . $e->getMessage());
        }

        // Buat array validated manual
        $validatedData = [
            'nop' => $request->input('nop'),
            'tanggal' => $request->input('tanggal'),
            'no_perintah_opname' => $request->input('no_perintah_opname'),
            'scan_barang' => $scanBarang,
            'barcodes' => $barcodes
        ];

        $validator = Validator::make($validatedData, [
            'nop' => 'required|string|max:255|unique:hasil_stock_opnames,nop',
            'tanggal' => 'required|date',
            'no_perintah_opname' => 'required|string|max:255|unique:hasil_stock_opnames,no_perintah_opname',
            'scan_barang' => 'required|array|min:1',
            'scan_barang.*.nama_barang' => 'required|string',
            'scan_barang.*.quantity' => 'required|numeric|min:0',
            'barcodes' => 'sometimes|array',
            'barcodes.*' => 'required_with:barcodes|string|max:255|distinct|unique:hasil_stock_opname_barcode,barcode'
        ], [
            // Validasi messages (same as before)
            'nop.required' => 'Nomor Form (NOP) harus diisi',
            'nop.unique' => 'Nomor Form (NOP) ini sudah pernah digunakan',
            'tanggal.required' => 'Tanggal harus diisi',
            'tanggal.date' => 'Format tanggal tidak valid',
            'no_perintah_opname.required' => 'Nomor Perintah Opname harus diisi',
            'no_perintah_opname.unique' => 'Nomor Perintah Opname ini sudah pernah digunakan',
            'scan_barang.required' => 'Data scan barang harus diisi',
            'scan_barang.array' => 'Data scan barang harus berupa array',
            'scan_barang.min' => 'Minimal harus ada 1 barang yang di-scan',
            'scan_barang.*.nama_barang.required' => 'Nama barang harus diisi untuk setiap item',
            'scan_barang.*.quantity.required' => 'Kuantitas harus diisi untuk setiap item',
            'scan_barang.*.quantity.numeric' => 'Kuantitas harus berupa angka',
            'scan_barang.*.quantity.min' => 'Kuantitas tidak boleh kurang dari 0',
            'barcodes.array' => 'Data barcode harus berupa array',
            'barcodes.*.required_with' => 'Barcode tidak boleh kosong jika ada data barcode',
            'barcodes.*.distinct' => 'Barcode tidak boleh ada yang sama dalam satu input',
            'barcodes.*.unique' => 'Barcode sudah pernah digunakan sebelumnya'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', 'Data tidak valid');
        }

        $validated = $validator->validated();
        $scanBarang = $validated['scan_barang'];
        $barcodes = $validated['barcodes'] ?? [];
        $stockOpnameNumber = $request->input('no_perintah_opname');

        // Log informasi tentang data yang diterima
        Log::info('Data yang divalidasi:', [
            'nop' => $validated['nop'],
            'tanggal' => $validated['tanggal'],
            'no_perintah_opname' => $validated['no_perintah_opname'],
            'scan_barang_count' => count($scanBarang),
            'barcodes_count' => count($barcodes),
            'barcodes_sample' => !empty($barcodes) ? array_slice($barcodes, 0, 5) : [] // Sample 5 barcodes pertama
        ]);

        try {
            // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
            $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
            $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
            $timestamp = Carbon::now()->toIso8601String();
            $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

            $stockOpnameId = null;
            $barang = [];

            // Fetch stock opname orders langsung dari API (untuk logging saja)
            $stockOpnameOrders = $this->fetchAllStockOpnameOrders($branch, $apiToken, $signature, $timestamp);

            foreach ($stockOpnameOrders as $order) {
                if (isset($order['number']) && $order['number'] == $stockOpnameNumber) {
                    $stockOpnameId = $order['id'];
                    $barang = $order['detailItem'] ?? [];
                    break;
                }
            }

            if ($stockOpnameId === null) {
                Log::error('Stock Opname Order tidak ditemukan', [
                    'no_perintah_opname' => $stockOpnameNumber,
                    'nop' => $validated['nop']
                ]);

                return redirect()->back()->withInput()->with('error', "Stock Opname Order dengan nomor '{$stockOpnameNumber}' tidak ditemukan di sistem Accurate.");
            }

            // Proses matching antara scanBarang dan barang dari API
            $detailScannedItems = [];
            $isMatchingValid = false;
            foreach ($scanBarang as $scan) {
                foreach ($barang as $item) {
                    if (
                        strtolower($scan['nama_barang']) === strtolower($item['item']['name']) &&
                        (float)$scan['quantity'] == (float)$item['quantity']
                    ) {
                        $detailScannedItems[] = [
                            'itemNo' => $item['item']['no'],
                            'quantity' => $scan['quantity'],
                        ];
                        $isMatchingValid = true;
                        break;
                    }
                }
            }

            if (!$isMatchingValid) {
                Log::warning('Tidak ada item yang cocok untuk scan data', [
                    'nop' => $validated['nop'],
                    'no_perintah_opname' => $validated['no_perintah_opname'],
                    'scan_barang_count' => count($scanBarang),
                    'api_barang_count' => count($barang)
                ]);

                return redirect()->back()->withInput()->with('error', 'Tidak ada item yang cocok antara data scan dengan data Stock Opname dari API Accurate. Pastikan nama barang dan kuantitas sesuai.');
            }

            // Database transaction
            DB::beginTransaction();

            try {
                // Simpan data hasil stock opname dengan kode_customer dari branch
                $hasilStockOpname = HasilStockOpname::create([
                    'nop' => $validated['nop'],
                    'tanggal' => $validated['tanggal'],
                    'no_perintah_opname' => $validated['no_perintah_opname'],
                    'kode_customer' => $branch->customer_id,
                ]);

                Log::info('HasilStockOpname berhasil disimpan', [
                    'id' => $hasilStockOpname->id,
                    'nop' => $hasilStockOpname->nop
                ]);

                // LOGGING INTENSIF UNTUK BARCODE
                Log::info('=== BARCODE PROCESSING START ===');
                Log::info('Total barcodes yang diterima: ' . count($barcodes));
                Log::debug('Daftar barcodes lengkap:', $barcodes);

                $savedBarcodeCount = 0;

                if (!empty($barcodes)) {
                    Log::info('Memproses penyimpanan barcode...');

                    $barcodeData = [];
                    foreach ($barcodes as $barcode) {
                        $trimmedBarcode = trim($barcode);
                        if (!empty($trimmedBarcode)) {
                            $barcodeData[] = [
                                'nop' => $validated['nop'],
                                'barcode' => $trimmedBarcode,
                                'created_at' => now(),
                                'updated_at' => now()
                            ];
                        }
                    }

                    // Batch insert untuk performa
                    try {
                        if (!empty($barcodeData)) {
                            try {
                                // Gunakan insertOrIgnore untuk skip duplicates
                                $chunks = array_chunk($barcodeData, 500);
                                foreach ($chunks as $chunk) {
                                    DB::table('hasil_stock_opname_barcode')->insertOrIgnore($chunk);
                                }

                                // Hitung yang benar-benar tersimpan
                                $savedBarcodeCount = DB::table('hasil_stock_opname_barcode')
                                    ->where('nop', $validated['nop'])
                                    ->count();
                            } catch (Exception $e) {
                                Log::error('Batch insert barcode failed:', ['error' => $e->getMessage()]);
                            }
                        }
                    } catch (Exception $e) {
                        Log::error('Batch insert barcode failed:', ['error' => $e->getMessage()]);
                        $failedBarcodeCount = count($barcodes);
                    }
                }

                $detailItems = [];
                foreach ($detailScannedItems as $scannedItems) {
                    $detailItems[] = [
                        "itemNo" => $scannedItems['itemNo'],
                        "quantity" => $scannedItems['quantity'],
                    ];
                }

                $postData = [
                    'detailItem' => $detailItems,
                    "orderNumber" => $validated['no_perintah_opname'],
                    "transDate" => date('d/m/Y', strtotime($validated['tanggal'])),
                    "number" => $validated['nop']
                ];

                $response = Http::timeout(30)->withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'X-Api-Signature' => $signature,
                    'X-Api-Timestamp' => $timestamp,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])->post($this->buildApiUrl($branch, 'stock-opname-result/save.do'), $postData);

                if ($response->successful()) {
                    // Commit transaction jika semua berhasil
                    DB::commit();

                    // Clear related cache
                    Cache::forget('accurate_hasil_stock_opname_list');
                    Cache::forget('accurate_perintah_opname_list');
                    Cache::forget('accurate_barang_list');

                    Log::info('Berhasil menyimpan stock opname ke Accurate', [
                        'nop' => $validated['nop'],
                        'response' => $response->json()
                    ]);

                    $successMessage = "Berhasil menyimpan hasil stock opname '{$validated['nop']}' ke Accurate";
                    if ($savedBarcodeCount > 0) {
                        $successMessage .= " dengan {$savedBarcodeCount} barcode";
                    }

                    return redirect()->route('hasil_stock_opname.index')
                        ->with('success', $successMessage);
                } else {
                    $errorMessage = $response->json()['message'] ?? 'Gagal mengirim data ke Accurate';

                    Log::error('Gagal mengirim data ke API Accurate', [
                        'nop' => $validated['nop'],
                        'status' => $response->status(),
                        'response' => $response->json()
                    ]);

                    DB::rollback();

                    return redirect()->route('hasil_stock_opname.index')
                        ->with('error', 'Gagal mengirim data ke API Accurate: ' . $errorMessage);
                }
            } catch (Exception $e) {
                DB::rollback();

                Log::error('Terjadi exception saat menyimpan hasil stock opname', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return redirect()->route('hasil_stock_opname.index')
                    ->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            Log::error('Store method error: ' . $e->getMessage());

            return redirect()->route('hasil_stock_opname.index')
                ->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    /**
     * Modified matchApprovalUsingAjax function with caching and fallback
     */
    public function matchApprovalUsingAjax(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|array',
            'barcode.*' => 'required|string|max:255'
        ]);

        // Normalisasi & deduplikasi
        $inputBarcodes = $validated['barcode'];
        $trimmed = array_map(function ($b) {
            return trim($b);
        }, $inputBarcodes);
        $uniqueBarcodes = array_values(array_unique($trimmed));

        try {
            // Query dalam chunk untuk menangani input sangat besar berdasarkan kode_customer
            $activeBranchId = session('active_branch');
            $branch = Branch::find($activeBranchId);
            $chunkSize = 1000;
            $approvals = collect();
            foreach (array_chunk($uniqueBarcodes, $chunkSize) as $chunk) {
                $chunkResult = Barcode::where('kode_customer', $branch->customer_id ?? '')
                    ->whereIn('barcode', $chunk)
                    ->get();
                $approvals = $approvals->concat($chunkResult);
            }

            if ($approvals->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barcode tidak ditemukan di sistem.',
                    'meta' => [
                        'input_count' => count($inputBarcodes),
                        'unique_count' => count($uniqueBarcodes),
                        'found_count' => 0,
                        'missing_count' => count($uniqueBarcodes),
                    ]
                ], 404);
            }

            // Group by nama barang yang sudah dibersihkan
            // Di dalam method matchApprovalUsingAjax(), setelah grouping
            $groupedApprovals = $approvals->map(function ($item) use (&$barcodeMapping) {
                $parts = preg_split('/\s+/', $item->nama);
                if (count($parts) >= 4 && strtoupper($parts[0]) === 'KC') {
                    $newParts = array_slice($parts, 1);
                } else {
                    $newParts = $parts;
                }
                $cleanNama = preg_replace('/#/', '', implode(' ', $newParts));

                // Simpan mapping barcode ke nama barang
                if (!isset($barcodeMapping[$cleanNama])) {
                    $barcodeMapping[$cleanNama] = [];
                }
                $barcodeMapping[$cleanNama][] = $item->barcode;

                return [
                    'clean_nama' => $cleanNama,
                    'panjang' => $item->panjang,
                    'barcode' => $item->barcode // Simpan barcode asli
                ];
            })->groupBy('clean_nama')->map(function ($items, $nama) use ($barcodeMapping) {
                $totalPanjang = '0';
                foreach ($items as $item) {
                    $panjang = (string) ($item['panjang'] ?? '0');
                    // Convert ke float untuk normalisasi, lalu kembali ke string
                    $normalizedPanjang = (string)(float)$panjang;
                    $totalPanjang = bcadd($totalPanjang, $normalizedPanjang, 2);
                }

                // Pastikan format yang konsisten
                $totalPanjang = number_format((float)$totalPanjang, 2, '.', '');

                // Hilangkan leading zero
                $totalPanjang = ltrim($totalPanjang, '0');
                // Pastikan tidak kosong jika nilai adalah 0
                if ($totalPanjang === '' || $totalPanjang === '.') {
                    $totalPanjang = '0';
                }
                // Pastikan format desimal yang benar
                if (strpos($totalPanjang, '.') === 0) {
                    $totalPanjang = '0' . $totalPanjang;
                }

                return [
                    'nama' => $nama,
                    'total_panjang' => $totalPanjang,
                    'barcodes' => $barcodeMapping[$nama] ?? [], // Kirim semua barcode untuk group ini
                    'item_count' => count($barcodeMapping[$nama] ?? []) // Jumlah barcode dalam group
                ];
            })->values();

            // Hitung missing berdasarkan barcode unik yang tidak ditemukan
            $foundBarcodes = $approvals->pluck('barcode')->unique()->values()->all();
            $foundSet = array_flip($foundBarcodes);
            $missing = array_values(array_filter($uniqueBarcodes, function ($b) use ($foundSet) {
                return !isset($foundSet[$b]);
            }));

            return response()->json([
                'success' => true,
                'data' => $groupedApprovals,
                'meta' => [
                    'input_count' => count($inputBarcodes),
                    'unique_count' => count($uniqueBarcodes),
                    'found_count' => $approvals->count(),
                    'missing_count' => count($missing),
                    'missing_sample' => array_slice($missing, 0, 20),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error dalam matchApprovalUsingAjax', [
                'error' => $e->getMessage(),
                'barcodes_count' => count($uniqueBarcodes)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
            ], 500);
        }
    }

    public function getIndividualBarcodeData(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|array',
            'barcode.*' => 'required|string'
        ]);

        $barcodes = $validated['barcode'];

        try {
            // Query berdasarkan kode_customer dari branch
            $activeBranchId = session('active_branch');
            $branch = Branch::find($activeBranchId);
            $approvals = Barcode::where('kode_customer', $branch->customer_id ?? '')
                ->whereIn('barcode', $barcodes)
                ->get();

            if ($approvals->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barcode tidak ditemukan di sistem.'
                ], 404);
            }

            // Buat mapping berdasarkan barcode untuk mempertahankan urutan
            $barcodeData = [];
            foreach ($barcodes as $barcode) {
                $approval = $approvals->firstWhere('barcode', $barcode);

                if ($approval) {
                    $barcodeData[] = [
                        'barcode' => $barcode,
                        'total_panjang' => $approval->panjang_mlc ?? $approval->panjang, // Data individual
                    ];
                } else {
                    $barcodeData[] = [
                        'barcode' => $barcode,
                        'total_panjang' => null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $barcodeData
            ]);
        } catch (Exception $e) {
            Log::error('Error dalam getIndividualBarcodeData', [
                'error' => $e->getMessage(),
                'barcodes' => $barcodes
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
            ], 500);
        }
    }

    /**
     * Endpoint baru yang lebih robust untuk proses bulk barcode via AJAX
     * - Menerima array barcode
     * - Deduplicate di server
     * - Query dalam batch (aman untuk payload besar)
     * - Mengembalikan data per-barcode dengan urutan mengikuti input
     * - Menyertakan meta: missing, duplicates, counts
     */
    public function getBulkBarcodeData(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|array',
            'barcode.*' => 'required|string|max:255'
        ]);

        $inputBarcodes = $validated['barcode'];

        // Normalisasi & statistik awal
        $trimmedBarcodes = array_map(function ($b) {
            return trim($b);
        }, $inputBarcodes);

        // Catat duplikat berdasarkan input mentah
        $seen = [];
        $duplicates = [];
        foreach ($trimmedBarcodes as $b) {
            if (isset($seen[$b])) {
                $duplicates[$b] = ($duplicates[$b] ?? 1) + 1; // simpan hitungan kemunculan ekstra
            } else {
                $seen[$b] = true;
            }
        }

        // Hapus duplikat untuk query DB
        $uniqueBarcodes = array_values(array_unique($trimmedBarcodes));

        try {
            // Query dalam chunk untuk robustness berdasarkan kode_customer
            $activeBranchId = session('active_branch');
            $branch = Branch::find($activeBranchId);
            $chunkSize = 1000;
            $results = collect();
            foreach (array_chunk($uniqueBarcodes, $chunkSize) as $chunk) {
                $chunkResults = Barcode::where('kode_customer', $branch->customer_id ?? '')
                    ->whereIn('barcode', $chunk)
                    ->get();
                $results = $results->concat($chunkResults);
            }

            // Buat mapping cepat berdasarkan barcode
            $byBarcode = $results->keyBy('barcode');

            $missing = [];
            $responseData = [];

            // Pertahankan urutan sesuai input asli
            foreach ($trimmedBarcodes as $barcode) {
                $approval = $byBarcode->get($barcode);
                if ($approval) {
                    $responseData[] = [
                        'barcode' => $barcode,
                        'total_panjang' => $approval->panjang_mlc ?? $approval->panjang,
                        'nama' => $approval->nama,
                        'exists' => true,
                    ];
                } else {
                    $responseData[] = [
                        'barcode' => $barcode,
                        'total_panjang' => null,
                        'nama' => null,
                        'exists' => false,
                    ];
                    $missing[] = $barcode;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'meta' => [
                    'input_count' => count($inputBarcodes),
                    'unique_count' => count($uniqueBarcodes),
                    'found_count' => $results->count(),
                    'missing_count' => count($missing),
                    'missing' => $missing,
                    'duplicates' => array_keys($duplicates),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error dalam getBulkBarcodeData', [
                'error' => $e->getMessage(),
                'barcodes_count' => count($trimmedBarcodes)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
            ], 500);
        }
    }
}
