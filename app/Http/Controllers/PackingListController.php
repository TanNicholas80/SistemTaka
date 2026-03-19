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

        // Jika packing list masih pending, tampilkan data dari raw_barcodes (hasil tarik dari SAP).
        // Jika sudah approved, tampilkan data dari barcodes (data final/ter-approve).
        if (($packingList->status ?? PackingList::STATUS_PENDING) === PackingList::STATUS_APPROVED) {
            $barcodes = $packingList->barcodes()
                ->forBranch($branchId)
                ->get();
        } else {
            // RawBarcode tidak punya scopeForBranch, jadi filter pakai kode_customer dari packing list.
            $barcodes = $packingList->rawBarcodes()
                ->where('kode_customer', $packingList->kode_customer)
                ->get();
        }

        return view('packing_list.detail', [
            'data' => $packingList,
            'barcodes' => $barcodes
        ]);
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
