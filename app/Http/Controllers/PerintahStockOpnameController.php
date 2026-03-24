<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerintahStockOpnameController extends Controller
{
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
        if (!Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $cacheKey = 'accurate_perintah_opname_list_' . $activeBranchId;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache Perintah Stock Opname dihapus');
        }

        $errorMessage = null;

        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $perintahstockOpname = $cachedData['perintahstockOpname'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data Perintah Stock Opname diambil dari cache');
            return view('perintah_stock_opname.index', compact('perintahstockOpname', 'errorMessage'));
        }

        // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
        // getAccurateApiTokenAttribute & getAccurateSignatureSecretAttribute akan memanggil Crypt::decryptString()
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $apiUrl = $baseUrl . '/stock-opname-order/list.do';

        $perintahstockOpname = [];
        $allPerintahstockOpname = []; // Untuk menampung hasil dari list.do
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
                    $allPerintahstockOpname = $responseData['d'];
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
                                    $allPerintahstockOpname = array_merge($allPerintahstockOpname, $pageResponse['d']);
                                    Log::info("Accurate Perintah stock opname list page {$page} response processed");
                                }
                            } else {
                                Log::error("Failed to fetch page {$page}: " . $result['reason']);
                            }
                        }
                    }

                    // Langkah 2: Panggil fungsi untuk mengambil detail secara batch
                    $detailsResult = $this->fetchPerintahstockOpnameDetailsInBatches($allPerintahstockOpname, $apiToken, $signature, $timestamp, $baseUrl);
                    $perintahstockOpname = $detailsResult['details']; // Data final

                    // Cek jika ada error dari proses fetch detail
                    if ($detailsResult['has_error']) {
                        $hasApiError = true;
                    }

                    $apiSuccess = true;
                }
            } else {
                Log::error('Gagal mengambil daftar Perintah Stock Opname', ['response' => $firstPageResponse->body()]);
                if ($firstPageResponse->status() == 429) $hasApiError = true;
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil Perintah Stock Opname: ' . $e->getMessage());
        }

        if ($hasApiError) {
            $errorMessage = 'Gagal memuat semua data karena terlalu banyak permintaan ke server. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi.';
        }

        if (!$apiSuccess && empty($perintahstockOpname)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $perintahstockOpname = $cachedData['perintahstockOpname'] ?? [];
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
            } else {
                if (is_null($errorMessage)) $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache.';
            }
        }

        Cache::put($cacheKey, ['perintahstockOpname' => $perintahstockOpname, 'errorMessage' => $errorMessage], $cacheDuration * 60);
        return view('perintah_stock_opname.index', compact('perintahstockOpname', 'errorMessage'));
    }

    /**
     * Mengambil detail perintah stock opname dalam batch untuk mengoptimalkan performa
     */
    private function fetchPerintahstockOpnameDetailsInBatches($listPerintah, $apiToken, $signature, $timestamp, string $baseUrl, $batchSize = 5)
    {
        $perintahstockOpnameDetails = [];
        $batches = array_chunk($listPerintah, $batchSize);
        $hasApiError = false; // Flag error untuk fungsi ini

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $perintah) {
                if (!isset($perintah['id'])) continue;
                $detailUrl = $baseUrl . '/stock-opname-order/detail.do?id=' . $perintah['id'];
                $promises[$perintah['id']] = $client->getAsync($detailUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ]
                ]);
            }
            if (empty($promises)) continue;

            $results = Utils::settle($promises)->wait();

            foreach ($results as $perintahId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $perintahstockOpnameDetails[] = $detailResponse['d'];
                    }
                } else {
                    $reason = $result['reason'];
                    Log::error("Gagal mengambil detail untuk ID {$perintahId}: " . $reason->getMessage());
                    if ($reason instanceof ClientException && $reason->getResponse()->getStatusCode() == 429) {
                        $hasApiError = true;
                    }
                }
            }
            usleep(200000); // 200ms
        }

        return [
            'details' => $perintahstockOpnameDetails,
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
        if (!Auth::check() || !Auth::user()->accurate_api_token || !Auth::user()->accurate_signature_secret) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $cacheKey = 'accurate_perintah_stock_opname_detail_' . $activeBranchId . '_' . $number;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $detail = null;

        // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
        // getAccurateApiTokenAttribute & getAccurateSignatureSecretAttribute akan memanggil Crypt::decryptString()
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $detailApiUrl = $baseUrl . '/stock-opname-order/detail.do?number=' . $number;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($detailApiUrl);

            if ($response->successful()) {
                $detailData = $response->json();
                if (isset($detailData['d'])) {
                    $detail = $detailData['d'];
                    Cache::put($cacheKey, ['detail' => $detail, 'errorMessage' => null], $cacheDuration * 60);
                } else {
                    $errorMessage = "Data detail untuk nomor {$number} tidak ditemukan.";
                }
            } else {
                Log::error('Gagal mengambil detail', ['status' => $response->status(), 'body' => $response->body()]);
                if ($response->status() == 404) {
                    $errorMessage = "Perintah Stock Opname dengan nomor {$number} tidak ditemukan.";
                } else {
                    $errorMessage = "Gagal mengambil data dari server. Silakan coba lagi.";
                }
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil detail: ' . $e->getMessage());
            $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
        }

        if (is_null($detail)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $detail = $cachedData['detail'] ?? null;
                if (is_null($errorMessage)) $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail {$number} dari cache karena API gagal.");
            }
        }

        return view('perintah_stock_opname.detail', compact('detail', 'errorMessage'));
    }
}
