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

class PelangganController extends Controller
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

        // Cache key yang unik
        $cacheKey = 'accurate_customer_list_' . $activeBranchId;
        // Tetapkan waktu cache (dalam menit)
        $cacheDuration = 30; // 30 menit

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache pelanggan dihapus karena force_refresh');
        }

        // --- TAMBAHAN: Inisialisasi variabel pesan error ---
        $errorMessage = null;

        // Periksa apakah cache sudah ada
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            // Ambil data pelanggan dan pesan error dari cache
            $pelanggan = $cachedData['pelanggan'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            
            Log::info('Data pelanggan diambil dari cache');
            // Kirim kedua variabel ke view
            return view('pelanggan.index', compact('pelanggan', 'errorMessage'));
        }

        // Get the auth and session tokens from the configuration
        $apiToken = Auth::user()->accurate_api_token;
        $signatureSecret = Auth::user()->accurate_signature_secret;
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Define the API URL
        $apiUrl = $baseUrl . '/customer/list.do';

        // Initialize variables
        $pelanggan = [];
        $allCustomers = [];
        $apiSuccess = false;

        try {
            // Selalu coba ambil data dari API terlebih dahulu
            $firstPageResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($apiUrl, [
                'sp.page' => 1,
                'sp.pageSize' => 20
            ]);

            // Check if the response is successful
            if ($firstPageResponse->successful()) {
                $responseData = $firstPageResponse->json();

                // Logging data list pelanggan mentah dari Accurate
                Log::info('Accurate customer list first page response:', $responseData);

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $allCustomers = $responseData['d'];

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
                                    $allCustomers = array_merge($allCustomers, $pageResponse['d']);
                                    Log::info("Accurate customer list page {$page} response processed");
                                }
                            } else {
                                Log::error("Failed to fetch page {$page}: " . $result['reason']);
                            }
                        }
                    }

                    // Setelah mendapatkan semua ID pelanggan, ambil detail untuk masing-masing secara batch
                    $detailsResult = $this->fetchCustomerDetailsInBatches($allCustomers, $apiToken, $signature, $timestamp, $baseUrl);
                    $pelanggan = $detailsResult['details']; // Ambil data pelanggan
                    $apiSuccess = true;

                    // --- TAMBAHAN: Jika ada error, siapkan pesan ---
                    if ($detailsResult['has_error']) {
                        $errorMessage = 'Gagal memuat semua data pelanggan karena terlalu banyak permintaan ke server. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi dengan menekan tombol "Refresh Data".';
                        Log::warning($errorMessage);
                    }

                    // Simpan data ke cache setelah berhasil mendapatkan data dari API
                    if (!empty($pelanggan)) {
                        Cache::put($cacheKey, ['pelanggan' => $pelanggan, 'errorMessage' => $errorMessage], $cacheDuration * 60);
                        Log::info('Data pelanggan berhasil diambil dari API dan disimpan ke cache');
                    }
                }
            } else {
                Log::error('Gagal mengambil daftar pelanggan dari Accurate.', [
                    'response' => $firstPageResponse->body()
                ]);
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil data dari Accurate API: ' . $e->getMessage());
        }

        // Jika API gagal atau tidak ada data, gunakan cache sebagai fallback
        if (!$apiSuccess || empty($pelanggan)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $pelanggan = $cachedData['pelanggan'] ?? [];
                // Jangan timpa pesan error jika API gagal total
                if (is_null($errorMessage)) {
                    $errorMessage = $cachedData['errorMessage'] ?? null;
                }
                Log::info('Data pelanggan diambil dari cache karena API Accurate gagal atau tidak ada data');
            } else {
                Log::warning('API Accurate gagal dan tidak ada data cache tersedia');
                $pelanggan = [];
                $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache. Silakan coba beberapa saat lagi.';
            }
        }

        // Jika parameter force_refresh ada dan API berhasil, log bahwa refresh berhasil
        // Ini berguna untuk memastikan cache akan diperbarui di request berikutnya
        if ($request->has('force_refresh') && $apiSuccess) {
            Log::info('Cache pelanggan berhasil diperbarui dengan data terbaru dari API');
        }

        return view('pelanggan.index', compact('pelanggan', 'errorMessage'));
    }

    /**
     * Mengambil detail pelanggan dalam batch untuk mengoptimalkan performa
     */
    private function fetchCustomerDetailsInBatches($customers, $apiToken, $signature, $timestamp, string $baseUrl, $batchSize = 5)
    {
        $customerDetails = [];
        $batches = array_chunk($customers, $batchSize);

        // --- TAMBAHAN: Inisialisasi flag error ---
        $hasApiError = false;

        foreach ($batches as $batch) {
            $promises = [];
            $client = new \GuzzleHttp\Client();

            foreach ($batch as $customer) {
                $detailUrl = $baseUrl . '/customer/detail.do?id=' . $customer['id'];
                $promises[$customer['id']] = $client->getAsync($detailUrl, [
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
            foreach ($results as $customerId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $detailResponse = json_decode($result['value']->getBody(), true);
                    if (isset($detailResponse['d'])) {
                        $customerDetails[] = $detailResponse['d'];
                        Log::info("Customer detail fetched for ID: {$customerId}");
                    }
                } else {
                    // --- MODIFIKASI: Deteksi error spesifik ---
                    $reason = $result['reason'];
                    Log::error("Failed to fetch customer detail for ID {$customerId}: " . $reason->getMessage());

                    // Cek jika error adalah ClientException dan status code adalah 429
                    if ($reason instanceof ClientException && $reason->getResponse()->getStatusCode() == 429) {
                        $hasApiError = true; // Set flag error menjadi true
                    }
                }
            }

            // Tambahkan delay kecil antara batch untuk menghindari rate limiting
            usleep(200000); // 200ms
        }

        return [
            'details' => $customerDetails,
            'has_error' => $hasApiError
        ];
    }
}
