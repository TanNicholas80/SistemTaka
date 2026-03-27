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

class BarangAccurateController extends Controller
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
        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        // Cache per cabang
        $cacheKey = 'accurate_barang_list_' . $activeBranchId;
        $cacheDuration = 30; // 30 menit

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache barang dihapus karena force_refresh');
        }

        // --- TAMBAHAN: Inisialisasi variabel pesan error ---
        $errorMessage = null;

        // --- MODIFIKASI: Baca cache dengan struktur baru ---
        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $barang = $cachedData['barang'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;

            Log::info('Data barang diambil dari cache');
            return view('barang_master.index', compact('barang', 'errorMessage'));
        }

        // Ambil kredensial Accurate dari branch (sudah otomatis didekripsi oleh accessor di model Branch)
        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $baseUrl = rtrim($branch->url_accurate ?? 'https://iris.accurate.id/accurate/api', '/');
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $apiUrl = $baseUrl . '/item/list.do';
        $fields = 'name,no,itemTypeName,unit1,availableToSell';

        $barang = [];
        $apiSuccess = false;
        $hasApiError = false; // Flag untuk error rate limit

        try {
            $firstPageResponse = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($apiUrl, ['sp.page' => 1, 'sp.pageSize' => 20, 'fields' => $fields]);

            if ($firstPageResponse->successful()) {
                $responseData = $firstPageResponse->json();
                Log::info('Accurate item list first page response:', $responseData);

                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $barang = $responseData['d']; // Langsung masukkan halaman pertama
                    $totalItems = $responseData['sp']['rowCount'] ?? 0;
                    $totalPages = ceil($totalItems / 20);

                    if ($totalPages > 1) {
                        $promises = [];
                        $client = new \GuzzleHttp\Client();
                        for ($page = 2; $page <= $totalPages; $page++) {
                            $promises[$page] = $client->getAsync($apiUrl, [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $apiToken,
                                    'X-Api-Signature' => $signature,
                                    'X-Api-Timestamp' => $timestamp,
                                ],
                                'query' => ['sp.page' => $page, 'sp.pageSize' => 20, 'fields' => $fields]
                            ]);
                        }
                        $results = Utils::settle($promises)->wait();

                        foreach ($results as $page => $result) {
                            if ($result['state'] === 'fulfilled') {
                                $pageResponse = json_decode($result['value']->getBody(), true);
                                if (isset($pageResponse['d']) && is_array($pageResponse['d'])) {
                                    $barang = array_merge($barang, $pageResponse['d']);
                                    Log::info("Accurate item list page {$page} response processed");
                                }
                            } else {
                                // --- TAMBAHAN: Deteksi error spesifik ---
                                $reason = $result['reason'];
                                Log::error("Failed to fetch item page {$page}: " . $reason->getMessage());
                                if ($reason instanceof ClientException && $reason->getResponse()->getStatusCode() == 429) {
                                    $hasApiError = true;
                                }
                            }
                        }
                    }

                    $apiSuccess = true;

                    // --- TAMBAHAN: Jika ada error, siapkan pesan ---
                    if ($hasApiError) {
                        $errorMessage = 'Gagal memuat semua data barang karena terlalu banyak permintaan ke server. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi dengan menekan tombol "Refresh Data".';
                        Log::warning($errorMessage);
                    }

                    // --- MODIFIKASI: Simpan ke cache dengan struktur baru ---
                    if (!empty($barang) || $request->has('force_refresh')) {
                        Cache::put($cacheKey, ['barang' => $barang, 'errorMessage' => $errorMessage], $cacheDuration * 60);
                        Log::info('Data barang dan status error berhasil disimpan ke cache');
                    }
                }
            } else {
                Log::error('Gagal mengambil daftar barang dari Accurate.', ['response' => $firstPageResponse->body()]);
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil data dari Accurate API: ' . $e->getMessage());
        }

        // --- MODIFIKASI: Logika fallback jika API gagal ---
        if (!$apiSuccess || empty($barang)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $barang = $cachedData['barang'] ?? [];
                if (is_null($errorMessage)) {
                    $errorMessage = $cachedData['errorMessage'] ?? null;
                }
                Log::info('Data barang diambil dari cache karena API Accurate gagal');
            } else {
                Log::warning('API Accurate gagal dan tidak ada data cache tersedia');
                $barang = [];
                $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache. Silakan coba beberapa saat lagi.';
            }
        }

        if ($request->has('force_refresh') && $apiSuccess) {
            Log::info('Cache barang berhasil diperbarui');
        }

        return view('barang_master.index', compact('barang', 'errorMessage'));
    }
}
