<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\BarangMasuk;
use App\Models\Branch;
use App\Models\KasirPenjualan;
use App\Models\PackingList;
use App\Models\PenerimaanBarang;
use App\Models\HasilStockOpname;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
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

    public function dashboard(Request $request)
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
        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            return back()->with('error', 'Kredensial API Accurate untuk cabang ini belum diatur.');
        }

        // Cache key yang unik per branch untuk dashboard
        $cacheKey = 'accurate_dashboard_data_branch_' . $activeBranchId;
        $cacheDuration = 10; // 10 menit untuk dashboard

        // Jika ada parameter force_refresh, bypass cache
        if ($request->has('force_refresh')) {
            Cache::forget($cacheKey);
        }

        // Get API credentials from branch (auto-decrypted by model accessors)
        $apiToken = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null);
        $signatureSecret = (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null);
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Log konfigurasi untuk debugging
        Log::info('Accurate API Configuration for Dashboard:', [
            'api_token_exists' => !empty($apiToken),
            'signature_secret_exists' => !empty($signatureSecret),
            'timestamp' => $timestamp,
            'branch_id' => $activeBranchId,
            'customer_id' => $branch->customer_id
        ]);

        // Hitung statistik dasar (data lokal - cepat) filtered by kode_customer
        $totalPenjualan = KasirPenjualan::where('kode_customer', $branch->customer_id)->count();
        $barangSiapJual = Barcode::where('status', 'uploaded')
            ->whereNotNull('length')
            ->where('length', '>', 0)
            ->where('kode_customer', $branch->customer_id)
            ->count();
        $totalPackingList = PackingList::where('kode_customer', $branch->customer_id)->count();
        $totalBarangMasuk = BarangMasuk::where('kode_customer', $branch->customer_id)->count();
        $totalPenerimaanBarang = PenerimaanBarang::where('kode_customer', $branch->customer_id)->count();

        // Ambil tanggal stock opname terakhir dari database lokal filtered by kode_customer
        $formattedTanggal = HasilStockOpname::where('kode_customer', $branch->customer_id)->max('tanggal');

        // Jika ada tanggal, format ke bentuk yang lebih rapi
        if ($formattedTanggal) {
            $formattedTanggal = Carbon::parse($formattedTanggal)->format('d M Y');
        }

        // Inisialisasi variabel untuk fallback
        $totalAmountKeseluruhan = 0;
        $totalPanjangKeseluruhan = 0;
        $penjualanPerBulan = [];
        $chartData = [];
        $chartDataPanjang = [];
        $chartLabels = [];
        $persentasePertumbuhan = 0;
        $persentasePertumbuhanPanjang = 0;
        $totalBarangAccurate = 0; // Variabel baru untuk total barang dari Accurate
        $totalAvailableToSell = 0; // Variabel baru untuk total availableToSell
        $errorMessageBarang = null; // Variabel untuk pesan error

        try {
            Log::info('Mencoba mengambil data dashboard dari API Accurate');

            // AMBIL DATA TOTAL BARANG DAN AVAILABLE TO SELL DARI ACCURATE API
            try {
                $barangCacheKey = 'accurate_total_barang_count_branch_' . $activeBranchId;
                $availableToSellCacheKey = 'accurate_total_available_to_sell_branch_' . $activeBranchId;
                $barangCacheDuration = 30; // 30 menit

                if ($request->has('force_refresh')) {
                    Cache::forget($barangCacheKey);
                    Cache::forget($availableToSellCacheKey);
                }

                // Ambil total barang count dari cache atau API
                if (Cache::has($barangCacheKey) && !$request->has('force_refresh')) {
                    $totalBarangAccurate = Cache::get($barangCacheKey);
                    Log::info('Total barang Accurate diambil dari cache: ' . $totalBarangAccurate);
                } else {
                    $apiUrl = $this->buildApiUrl($branch, 'item/list.do');
                    $fields = 'name,no,itemTypeName,unit1,availableToSell';

                    $firstPageResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiToken,
                        'X-Api-Signature' => $signature,
                        'X-Api-Timestamp' => $timestamp,
                    ])->get($apiUrl, ['sp.page' => 1, 'sp.pageSize' => 1, 'fields' => $fields]);

                    if ($firstPageResponse->successful()) {
                        $responseData = $firstPageResponse->json();

                        if (isset($responseData['sp']['rowCount'])) {
                            $totalBarangAccurate = $responseData['sp']['rowCount'];
                            Cache::put($barangCacheKey, $totalBarangAccurate, $barangCacheDuration * 60);
                            Log::info('Total barang Accurate berhasil diambil dari API: ' . $totalBarangAccurate);
                        }
                    } else {
                        Log::error('Gagal mengambil total barang dari Accurate API', [
                            'status' => $firstPageResponse->status(),
                            'response' => $firstPageResponse->body()
                        ]);
                        $errorMessageBarang = 'Gagal mengambil data total barang dari Accurate';
                    }
                }

                // Ambil total availableToSell dari cache atau API
                if (Cache::has($availableToSellCacheKey) && !$request->has('force_refresh')) {
                    $totalAvailableToSell = Cache::get($availableToSellCacheKey);
                    Log::info('Total availableToSell diambil dari cache: ' . $totalAvailableToSell);
                } else {
                    $apiUrl = $this->buildApiUrl($branch, 'item/list.do');
                    $fields = 'availableToSell';

                    // Mengambil semua data barang untuk menghitung total availableToSell
                    $page = 1;
                    $pageSize = 100; // Ambil 100 item per halaman
                    $hasMore = true;
                    $totalAvailableToSell = 0;

                    while ($hasMore) {
                        $response = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $apiToken,
                            'X-Api-Signature' => $signature,
                            'X-Api-Timestamp' => $timestamp,
                        ])->get($apiUrl, [
                            'sp.page' => $page,
                            'sp.pageSize' => $pageSize,
                            'fields' => $fields
                        ]);

                        if ($response->successful()) {
                            $responseData = $response->json();

                            if (isset($responseData['d']) && is_array($responseData['d'])) {
                                foreach ($responseData['d'] as $item) {
                                    if (isset($item['availableToSell']) && is_numeric($item['availableToSell'])) {
                                        $totalAvailableToSell += (float) $item['availableToSell'];
                                    }
                                }

                                // Cek apakah masih ada halaman berikutnya
                                $currentPage = $responseData['sp']['page'] ?? $page;
                                $totalPages = $responseData['sp']['pageCount'] ?? 1;

                                if ($currentPage >= $totalPages) {
                                    $hasMore = false;
                                } else {
                                    $page++;
                                }
                            } else {
                                $hasMore = false;
                            }
                        } else {
                            Log::error('Gagal mengambil data availableToSell dari Accurate API', [
                                'status' => $response->status(),
                                'response' => $response->body()
                            ]);
                            $errorMessageBarang = 'Gagal mengambil data stok barang dari Accurate';
                            $hasMore = false;
                        }

                        // Batasan untuk menghindari infinite loop
                        if ($page > 100) {
                            $hasMore = false;
                            Log::warning('Loop pengambilan data availableToSell dihentikan setelah 100 halaman');
                        }
                    }

                    // Simpan ke cache
                    Cache::put($availableToSellCacheKey, $totalAvailableToSell, $barangCacheDuration * 60);
                    Log::info('Total availableToSell berhasil dihitung: ' . $totalAvailableToSell);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching data from Accurate API', [
                    'error' => $e->getMessage()
                ]);
                $errorMessageBarang = 'Error mengambil data dari Accurate: ' . $e->getMessage();
            }

            // Ambil data KasirPenjualan untuk perhitungan chart dan totals filtered by kode_customer
            $kasirPenjualan = KasirPenjualan::whereNotNull('npj')
                ->where('kode_customer', $branch->customer_id)
                ->get();

            // Proses data penjualan untuk chart (optimized)
            foreach ($kasirPenjualan as $item) {
                if (!$item->npj) {
                    continue;
                }

                $salesData = $this->getSalesOrderData($item->npj, $branch, $apiToken, $signature, $timestamp, $activeBranchId);

                if ($salesData) {
                    $totalAmountKeseluruhan += $salesData['totalAmount'];
                    $totalPanjangKeseluruhan += $salesData['totalQuantity'];

                    $tanggalPenjualan = $item->created_at ? Carbon::parse($item->created_at) : now();
                    $bulanTahun = $tanggalPenjualan->format('Y-m');
                    $bulanNama = $tanggalPenjualan->format('M Y');

                    if (!isset($penjualanPerBulan[$bulanTahun])) {
                        $penjualanPerBulan[$bulanTahun] = [
                            'bulan' => $bulanNama,
                            'total_amount' => 0,
                            'total_panjang' => 0,
                        ];
                    }

                    $penjualanPerBulan[$bulanTahun]['total_amount'] += $salesData['totalAmount'];
                    $penjualanPerBulan[$bulanTahun]['total_panjang'] += $salesData['totalQuantity'];
                }
            }

            // Urutkan penjualan per bulan
            ksort($penjualanPerBulan);

            // Generate chart data untuk 12 bulan terakhir
            $currentDate = now();

            for ($i = 11; $i >= 0; $i--) {
                $date = $currentDate->copy()->subMonths($i);
                $bulanTahun = $date->format('Y-m');
                $bulanNama = $date->format('M');

                $chartLabels[] = $bulanNama;
                $chartData[] = isset($penjualanPerBulan[$bulanTahun])
                    ? $penjualanPerBulan[$bulanTahun]['total_amount']
                    : 0;
                $chartDataPanjang[] = isset($penjualanPerBulan[$bulanTahun])
                    ? $penjualanPerBulan[$bulanTahun]['total_panjang']
                    : 0;
            }

            // Hitung persentase pertumbuhan (amount)
            $bulanIni = now()->format('Y-m');
            $bulanLalu = now()->subMonth()->format('Y-m');

            $totalBulanIni = isset($penjualanPerBulan[$bulanIni]) ? $penjualanPerBulan[$bulanIni]['total_amount'] : 0;
            $totalBulanLalu = isset($penjualanPerBulan[$bulanLalu]) ? $penjualanPerBulan[$bulanLalu]['total_amount'] : 0;

            if ($totalBulanLalu > 0) {
                $persentasePertumbuhan = (($totalBulanIni - $totalBulanLalu) / $totalBulanLalu) * 100;
            }

            // Hitung persentase pertumbuhan (panjang)
            $totalPanjangBulanIni = isset($penjualanPerBulan[$bulanIni]) ? $penjualanPerBulan[$bulanIni]['total_panjang'] : 0;
            $totalPanjangBulanLalu = isset($penjualanPerBulan[$bulanLalu]) ? $penjualanPerBulan[$bulanLalu]['total_panjang'] : 0;

            if ($totalPanjangBulanLalu > 0) {
                $persentasePertumbuhanPanjang = (($totalPanjangBulanIni - $totalPanjangBulanLalu) / $totalPanjangBulanLalu) * 100;
            }

            // Simpan data ke cache setelah berhasil mendapatkan data dari API
            $dashboardData = compact(
                'totalPenjualan',
                'barangSiapJual',
                'totalPackingList',
                'totalBarangMasuk',
                'totalPenerimaanBarang',
                'formattedTanggal',
                'totalAmountKeseluruhan',
                'totalPanjangKeseluruhan',
                'chartData',
                'chartDataPanjang',
                'chartLabels',
                'persentasePertumbuhan',
                'persentasePertumbuhanPanjang',
                'totalBarangAccurate', // Variabel baru
                'totalAvailableToSell', // Variabel baru
                'errorMessageBarang'   // Variabel baru
            );

            if (!empty($dashboardData)) {
                Cache::put($cacheKey, $dashboardData, $cacheDuration * 60);
                Log::info('Data dashboard berhasil diambil dan disimpan ke cache');
            }

            return view('dashboard', $dashboardData);
        } catch (\Exception $e) {
            Log::error('Error fetching dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: coba ambil data dari cache jika error
            if (Cache::has($cacheKey)) {
                $dashboardData = Cache::get($cacheKey);
                Log::info('Menggunakan data dashboard dari cache sebagai fallback');
                return view('dashboard', $dashboardData);
            }

            // Jika tidak ada cache, gunakan data default
            $dashboardData = compact(
                'totalPenjualan',
                'barangSiapJual',
                'totalPackingList',
                'totalBarangMasuk',
                'totalPenerimaanBarang',
                'formattedTanggal',
                'totalAmountKeseluruhan',
                'totalPanjangKeseluruhan',
                'chartData',
                'chartDataPanjang',
                'chartLabels',
                'persentasePertumbuhan',
                'persentasePertumbuhanPanjang',
                'totalBarangAccurate', // Variabel baru
                'totalAvailableToSell', // Variabel baru
                'errorMessageBarang'   // Variabel baru
            );

            return view('dashboard', $dashboardData);
        }
    }

    /**
     * Get sales order data with cache as fallback
     */
    private function getSalesOrderData($npj, $branch, $apiToken, $signature, $timestamp, $activeBranchId = null)
    {
        $cacheKey = 'accurate_sales_order_' . $npj;
        if ($activeBranchId) {
            $cacheKey .= '_branch_' . $activeBranchId;
        }
        $cacheDuration = 10; // 10 menit

        try {
            Log::info("Mencoba mengambil data sales order {$npj} dari API");

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'X-Api-Signature' => $signature,
                'X-Api-Timestamp' => $timestamp,
            ])->get($this->buildApiUrl($branch, 'sales-order/detail.do'), [
                'number' => $npj,
            ]);

            if ($response->successful()) {
                $json = $response->json();

                if (isset($json['d']['totalAmount']) && is_numeric($json['d']['totalAmount'])) {
                    $totalAmount = (float) $json['d']['totalAmount'];

                    $totalQuantity = 0;
                    if (isset($json['d']['detailItem']) && is_array($json['d']['detailItem'])) {
                        foreach ($json['d']['detailItem'] as $detailItem) {
                            if (isset($detailItem['quantity']) && is_numeric($detailItem['quantity'])) {
                                $totalQuantity += (float) $detailItem['quantity'];
                            }
                        }
                    }

                    $salesData = [
                        'totalAmount' => $totalAmount,
                        'totalQuantity' => $totalQuantity
                    ];

                    Cache::put($cacheKey, $salesData, $cacheDuration * 60);
                    Log::info("Data sales order {$npj} berhasil diambil dari API dan disimpan ke cache");

                    return $salesData;
                }
            } else {
                Log::warning('Failed to fetch sales order detail from API', [
                    'npj' => $npj,
                    'status' => $response->status()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching sales order data from API', [
                'npj' => $npj,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: ambil dari cache jika API error
        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            Log::info("API sales order {$npj} gagal, menggunakan data dari cache sebagai fallback");
            return $cachedData;
        }

        return null;
    }
}
