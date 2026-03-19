<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesananPembelianController extends Controller
{
    private function buildApiurl($branch, $endpoint)
    {
        $baseUrl = $branch->url_accurate ?? 'https://iris.accurate.id';
        $baseUrl = rtrim($baseUrl, '/');
        $apiPath = '/accurate/api';

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

        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $cacheKey = 'accurate_pesanan_pembelian_list_' . $activeBranchId;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
            Log::info('Cache pesanan pembelian dihapus karena force_refresh');
        }

        $errorMessage = null;

        if (Cache::has($cacheKey) && !$request->has('force_refresh')) {
            $cachedData = Cache::get($cacheKey);
            $pesananPembelian = $cachedData['pesananPembelian'] ?? [];
            $errorMessage = $cachedData['errorMessage'] ?? null;
            Log::info('Data pesanan pembelian diambil dari cache');
            return view('pesanan_pembelian.index', compact('pesananPembelian', 'errorMessage'));
        }

        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $apiUrl = $this->buildApiUrl($branch, 'purchase-order/list.do');
        $fields = 'transDate,number,statusName,vendor,totalAmount,id';

        $pesananPembelian = [];
        $apiSuccess = false;
        $hasApiError = false;

        try {
            $firstPageResponse = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($apiUrl, ['sp.page' => 1, 'sp.pageSize' => 20, 'fields' => $fields]);

            if ($firstPageResponse->successful()) {
                $responseData = $firstPageResponse->json();
                if (isset($responseData['d']) && is_array($responseData['d'])) {
                    $pesananPembelian = $responseData['d'];
                    $totalItems = $responseData['sp']['rowCount'] ?? 0;
                    $totalPages = ceil($totalItems / 20);

                    if ($totalPages > 1) {
                        $promises = [];
                        $client = new \GuzzleHttp\Client();
                        for ($page = 2; $page <= $totalPages; $page++) {
                            $promises[$page] = $client->getAsync($apiUrl, [
                                'headers' => ['Authorization' => 'Bearer ' . $apiToken, 'X-Api-Signature' => $signature, 'X-Api-Timestamp' => $timestamp],
                                'query' => ['sp.page' => $page, 'sp.pageSize' => 20, 'fields' => $fields]
                            ]);
                        }
                        $results = Utils::settle($promises)->wait();

                        foreach ($results as $page => $result) {
                            if ($result['state'] === 'fulfilled') {
                                $pageResponse = json_decode($result['value']->getBody(), true);
                                if (isset($pageResponse['d'])) {
                                    $pesananPembelian = array_merge($pesananPembelian, $pageResponse['d']);
                                }
                            } else {
                                $reason = $result['reason'];
                                Log::error("Gagal mengambil halaman {$page} pesanan pembelian: " . $reason->getMessage());
                                if ($reason instanceof ClientException && $reason->getResponse()->getStatusCode() == 429) {
                                    $hasApiError = true;
                                }
                            }
                        }
                    }
                    $apiSuccess = true;
                }
            } else {
                Log::error('Gagal mengambil daftar pesanan pembelian', ['response' => $firstPageResponse->body()]);
                if ($firstPageResponse->status() == 429)
                    $hasApiError = true;
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil pesanan pembelian: ' . $e->getMessage());
        }

        if ($hasApiError) {
            $errorMessage = 'Gagal memuat semua data karena terlalu banyak permintaan ke server. Data yang ditampilkan mungkin tidak lengkap. Silakan coba lagi dengan menekan tombol "Refresh Data".';
        }

        if (!$apiSuccess && empty($pesananPembelian)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $pesananPembelian = $cachedData['pesananPembelian'] ?? [];
                if (is_null($errorMessage))
                    $errorMessage = $cachedData['errorMessage'] ?? null;
            } else {
                if (is_null($errorMessage))
                    $errorMessage = 'Gagal terhubung ke server Accurate dan tidak ada data cache.';
            }
        }

        Cache::put($cacheKey, ['pesananPembelian' => $pesananPembelian, 'errorMessage' => $errorMessage], $cacheDuration * 60);
        return view('pesanan_pembelian.index', compact('pesananPembelian', 'errorMessage'));
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

        if (!$branch->accurate_api_token || !$branch->accurate_signature_secret) {
            return back()->with('error', 'Kredensial Accurate untuk cabang ini belum dikonfigurasi.');
        }

        $cacheKey = 'accurate_pesanan_pembelian_detail_' . $activeBranchId . '_' . $number;
        $cacheDuration = 10;

        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        $errorMessage = null;
        $detail = null;

        $apiToken = $branch->accurate_api_token;
        $signatureSecret = $branch->accurate_signature_secret;
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);
        $detailApiUrl = $this->buildApiUrl($branch, 'purchase-order/detail.do?number=' . $number);

        try {
            $response = Http::withoutVerifying()->withHeaders([
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
                Log::error('Gagal mengambil detail pesanan pembelian', ['status' => $response->status(), 'body' => $response->body()]);
                if ($response->status() == 404) {
                    $errorMessage = "Pesanan pembelian dengan nomor {$number} tidak ditemukan.";
                } else {
                    $errorMessage = "Gagal mengambil data dari server. Silakan coba lagi.";
                }
            }
        } catch (Exception $e) {
            Log::error('Exception saat mengambil detail pesanan pembelian: ' . $e->getMessage());
            $errorMessage = "Terjadi kesalahan koneksi. Silakan periksa jaringan Anda.";
        }

        if (is_null($detail)) {
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $detail = $cachedData['detail'] ?? null;
                if (is_null($errorMessage))
                    $errorMessage = $cachedData['errorMessage'] ?? null;
                Log::info("Menampilkan detail {$number} dari cache karena API gagal.");
            }
        }

        return view('pesanan_pembelian.detail', compact('detail', 'errorMessage'));
    }
}
