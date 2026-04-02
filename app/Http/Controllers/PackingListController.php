<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\PackingList;
use App\Models\RawBarcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PackingListController extends Controller
{
    public function index()
    {
        $branchId = session('active_branch');
        $packingList = PackingList::forBranch($branchId)->get();

        return view('packing_list.index', compact('packingList'));
    }

    public function show($id)
    {
        $branchId = session('active_branch');
        $packingList = PackingList::forBranch($branchId)->findOrFail($id);

        /**
         * Prioritas sumber baris:
         * - approved / closed / used: ambil dari barcodes (final), filter kode_customer = milik PL
         *   (konsisten; menghindari kasus scope forBranch / super_admin membuat query kosong).
         * - Jika kosong: fallback ke raw_barcodes (SAP) agar detail tetap ada baris bila barcode final belum ada / belum sinkron.
         * - pending: langsung dapat data dari fallback raw_barcodes.
         */
        $status = (string) ($packingList->status ?? PackingList::STATUS_PENDING);
        $useFinalBarcodes = in_array($status, [
            PackingList::STATUS_APPROVED,
            PackingList::STATUS_CLOSED,
            'used',
        ], true);

        $barcodes = collect();
        if ($useFinalBarcodes) {
            $barcodes = $packingList->barcodes()
                ->where('kode_customer', $packingList->kode_customer)
                ->get();
        }

        if ($barcodes->isEmpty()) {
            $barcodes = $packingList->rawBarcodes()->get();
        }

        $barcodeRows = $barcodes->map(function ($barcode) {
            return [
                'barcode' => $barcode,
                'display' => $this->formatPackingListDetailDisplay($barcode),
            ];
        });

        return view('packing_list.detail', [
            'data' => $packingList,
            'barcodes' => $barcodes,
            'barcodeRows' => $barcodeRows,
        ]);
    }

    /**
     * Tampilan kolom Berat / Panjang MLC / Panjang Yard per material_type & base_uom.
     *
     * - ZDWN, ZPWN: dari length — yd → MLC = length×0.9, Yard = length; m → MLC = length, Yard = length/0.9; Berat dari weight
     * - ZDKN: Berat KG dari weight
     * - ZDNM: Panjang Yard = length; Berat dari weight
     * - lainnya: perilaku lama (berat + panjang sebagai MLC)
     */
    private function formatPackingListDetailDisplay(object $barcode): array
    {
        $mt = strtoupper(trim((string) ($barcode->material_type ?? '')));
        $length = isset($barcode->length) && $barcode->length !== '' ? (float) $barcode->length : null;
        $weight = isset($barcode->weight) && $barcode->weight !== '' ? (float) $barcode->weight : null;
        $baseUom = strtolower(trim((string) ($barcode->base_uom ?? '')));

        $fmt = static function (?float $v): ?string {
            if ($v === null) {
                return null;
            }

            return number_format($v, 2, '.', '');
        };

        $berat = null;
        $panjangMlc = null;
        $panjangYard = null;

        if (in_array($mt, ['ZDWN', 'ZPWN'], true)) {
            if ($length !== null) {
                $isYd = $baseUom === 'yd'
                    || $baseUom === 'yard'
                    || ($baseUom !== '' && strpos($baseUom, 'yd') !== false);
                $isM = $baseUom === 'm' || $baseUom === 'meter';

                if ($isYd) {
                    $panjangMlc = $fmt($length * 0.9);
                    $panjangYard = $fmt($length);
                } elseif ($isM) {
                    $panjangMlc = $fmt($length);
                    $panjangYard = $fmt($length / 0.9);
                } else {
                    $panjangMlc = $fmt($length);
                    $panjangYard = $fmt($length / 0.9);
                }
            }
            if ($weight !== null) {
                $berat = $fmt($weight);
            }
        } elseif ($mt === 'ZDKN') {
            if ($weight !== null) {
                $berat = $fmt($weight);
            }
        } elseif ($mt === 'ZDNM') {
            if ($length !== null) {
                $panjangYard = $fmt($length);
            }
            if ($weight !== null) {
                $berat = $fmt($weight);
            }
        } else {
            if ($weight !== null) {
                $berat = $fmt($weight);
            }
            if ($length !== null) {
                $panjangMlc = $fmt($length);
            }
        }

        return [
            'berat' => $berat,
            'panjang_mlc' => $panjangMlc,
            'panjang_yard' => $panjangYard,
        ];
    }

    public function create()
    {
        return view('packing_list.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'npl' => 'required|regex:/^\d{10}$/|unique:packing_list,npl',
        ], [
            'npl.regex' => 'No. Packing List harus terdiri dari 10 digit angka.',
            'npl.unique' => 'No. Packing List tersebut sudah terinput.',
        ]);

        $branchId = session('active_branch');
        $branch = Branch::find($branchId);

        if (!$branch) {
            return back()->withInput()->with('error', 'Cabang aktif tidak ditemukan.');
        }

        $kodeCustomer = $branch->customer_id;

        try {
            $sapUrl = config('services.sap.packing_list_url');
            $sapAuthToken = config('services.sap.auth_token');

            if (empty($sapUrl) || empty($sapAuthToken)) {
                Log::error('SAP configuration missing', [
                    'url_set' => !empty($sapUrl),
                    'auth_set' => !empty($sapAuthToken),
                ]);
                return back()->withInput()->with('error', 'Konfigurasi SAP belum diatur. Jalankan: php artisan config:clear');
            }

            $credentials = base64_decode($sapAuthToken);
            $credParts = explode(':', $credentials, 2);

            if (count($credParts) !== 2) {
                Log::error('Invalid SAP auth token format', ['token_length' => strlen($sapAuthToken)]);
                return back()->withInput()->with('error', 'Format token autentikasi SAP tidak valid.');
            }

            $body = '"' . $validated['npl'] . '"';

            Log::info('SAP API request', [
                'url' => $sapUrl,
                'npl' => $validated['npl'],
                'body' => $body,
                'username' => $credParts[0],
            ]);

            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withBasicAuth($credParts[0], $credParts[1])
                ->withBody($body, 'text/plain')
                ->post($sapUrl);

            Log::info('SAP API response', [
                'npl' => $validated['npl'],
                'status' => $response->status(),
                'body_length' => strlen($response->body()),
                'body_preview' => mb_substr($response->body(), 0, 500),
            ]);

            if (!$response->successful()) {
                return back()->withInput()->with('error', 'Gagal mengambil data dari SAP. HTTP Status: ' . $response->status());
            }

            $sapData = $response->json();

            if (!is_array($sapData) || empty($sapData)) {
                Log::warning('SAP API returned empty/invalid data', [
                    'npl' => $validated['npl'],
                    'raw_body' => mb_substr($response->body(), 0, 1000),
                ]);
                return back()->withInput()->with('error', 'Data packing list tidak ditemukan di SAP untuk NPL: ' . $validated['npl']);
            }

            Log::info('SAP API data received', [
                'npl' => $validated['npl'],
                'total_barcodes' => count($sapData),
            ]);

            // Validasi: KODE_CUSTOMER dari SAP harus cocok dengan customer_id cabang aktif
            $sapKodeCustomers = array_unique(array_filter(array_column($sapData, 'KODE_CUSTOMER')));
            $mismatched = array_values(array_filter($sapKodeCustomers, fn($kc) => $kc !== $kodeCustomer));

            if (!empty($mismatched)) {
                Log::warning('SAP KODE_CUSTOMER tidak cocok dengan cabang aktif', [
                    'npl' => $validated['npl'],
                    'branch_customer_id' => $kodeCustomer,
                    'sap_kode_customers' => $sapKodeCustomers,
                ]);
                return back()->withInput()->with(
                    'error',
                    'Packing List ini bukan milik cabang Anda. ' .
                    'Kode Customer di SAP: ' . implode(', ', $mismatched) . ', ' .
                    'Kode Customer cabang: ' . $kodeCustomer . '.'
                );
            }

        } catch (\Exception $e) {
            Log::error('SAP API exception', [
                'npl' => $validated['npl'],
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()->with('error', 'Gagal terhubung ke SAP: ' . $e->getMessage());
        }

        DB::beginTransaction();

        try {
            // Simpan packing list
            $packingList = PackingList::create([
                'tanggal' => $validated['tanggal'],
                'npl' => $validated['npl'],
                'status' => PackingList::STATUS_PENDING,
                'kode_customer' => $kodeCustomer,
            ]);

            // Simpan setiap row dari SAP response ke raw_barcodes
            $insertedCount = 0;
            foreach ($sapData as $row) {
                RawBarcode::updateOrCreate(
                    [
                        'barcode' => $row['BARCODE'] ?? null,
                        'kode_customer' => $row['KODE_CUSTOMER'] ?? $kodeCustomer,
                    ],
                    [
                        'customer' => $row['CUSTOMER'] ?? '',
                        'no_packing_list' => $row['NO_PACKING_LIST'] ?? $validated['npl'],
                        'contract' => $row['CONTRACT'] ?? '',
                        'no_billing' => $row['BILLING'] ?? null,
                        'date' => $row['DATE'] ?? null,
                        'jatuh_tempo' => $row['JATUH_TEMPO'] ?? null,
                        'plant' => $row['PLANT'] ?? '',
                        'pemasok' => $row['PEMASOK'] ?? '',
                        'harga_ppn' => $row['HARGA_PPN'] ?? null,
                        'harga_jual' => $row['HARGA_JUAL'] ?? null,
                        'material_code' => $row['MATERIALCODE'] ?? null,
                        'batch_no' => $row['BATCHNO'] ?? null,
                        'length' => $row['LENGTH'] ?? null,
                        'weight' => $row['WEIGHT'] ?? null,
                        'base_uom' => $row['BASE_UOM'] ?? null,
                        'kategori_warna' => $row['KATEGORIWARNA'] ?? null,
                        'kode_warna' => $row['KODE_WARNA'] ?? null,
                        'warna' => $row['WARNA'] ?? null,
                        'date_kain' => $row['DATEKAIN'] ?? null,
                        'job_order' => $row['JOBORDER'] ?? null,
                        'incoterms' => $row['INCOTERMS'] ?? null,
                        'ekspeditor' => $row['EKSPEDITOR'] ?? null,
                        'vehicle_number' => $row['VEHICLE_NUMBER'] ?? null,
                        'production_order' => $row['PRODUCTIONORDER'] ?? null,
                        'order_type' => $row['ORDERTYPE'] ?? null,
                        'unit' => $row['UNIT'] ?? null,
                        'longtext' => $row['LONGTEXT'] ?? null,
                        'salestext' => $row['SALESTEXT'] ?? null,
                        'konstruksi_akhir' => $row['KONSTRUKSIAKHIR'] ?? '',
                        'nojo' => $row['NOJO'] ?? null,
                        'zno' => $row['ZNO'] ?? null,
                        'lebar_kain' => $row['LEBARKAIN'] ?? null,
                        'kode' => $row['KODE'] ?? null,
                        'grade' => $row['GRADE'] ?? null,
                        'pcs' => $row['PCS'] ?? null,
                        'sample' => $row['SAMPLE'] ?? null,
                        'kodisi_kain' => $row['KONDISI_KAIN'] ?? null,
                        'special_treatment' => $row['SPECIAL_TREATMENT'] ?? null,
                        'material_type' => $row['MATERIAL_TYPE'] ?? null,
                    ]
                );
                $insertedCount++;
            }

            DB::commit();

            Log::info('Packing list created with SAP data', [
                'npl' => $validated['npl'],
                'packing_list_id' => $packingList->id,
                'raw_barcodes_count' => $insertedCount,
            ]);

            return redirect()->route('packing-list.create')
                ->with('success', "Packing List {$validated['npl']} berhasil ditambahkan dengan {$insertedCount} barcode dari SAP.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to store packing list data', [
                'npl' => $validated['npl'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }
}
