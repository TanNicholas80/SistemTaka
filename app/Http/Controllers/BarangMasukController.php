<?php

namespace App\Http\Controllers;

use App\Models\BarangMasuk;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\PackingList;
use App\Models\RawBarcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BarangMasukController extends Controller
{
    public function index()
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        $branch = Branch::find(session('active_branch'));

        $barangMasuk = BarangMasuk::with(['barcode' => function ($query) use ($branch) {
            if ($branch) {
                $query->where('kode_customer', $branch->customer_id);
            }
        }])
            ->forBranch()
            ->orderByDesc('tanggal')
            ->get();

        return view('barang_masuk.index', compact('barangMasuk', 'branch'));
    }

    public function create()
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        $packingLists = PackingList::query()
            ->where('kode_customer', $branch->customer_id)
            ->where('status', '!=', PackingList::STATUS_CLOSED)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get(['id', 'npl', 'tanggal', 'status']);

        return view('barang_masuk.create', compact('packingLists'));
    }

    /**
     * Ambil item packing list (master) dari RawBarcode untuk preview tabel.
     * Dipakai oleh halaman create via AJAX.
     */
    public function getPackingListItems(Request $request)
    {
        $request->validate([
            'npl' => 'required|string',
        ]);

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['success' => false, 'message' => 'Cabang belum dipilih.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'Cabang tidak valid.'], 400);
        }

        $npl = trim($request->query('npl'));

        $packingList = PackingList::query()
            ->where('npl', $npl)
            ->where('kode_customer', $branch->customer_id)
            ->first();

        if (!$packingList) {
            return response()->json(['success' => false, 'message' => "Packing List {$npl} tidak ditemukan untuk cabang ini."], 404);
        }

        $items = RawBarcode::query()
            ->where('no_packing_list', $npl)
            ->where('kode_customer', $branch->customer_id)
            ->orderBy('batch_no')
            ->get();

        $header = $items->first();

        $scannedList = $this->getScannedListFromSession($npl);

        return response()->json([
            'success' => true,
            'packing_list' => [
                'npl' => $packingList->npl,
                'tanggal' => $packingList->tanggal,
                'status' => $packingList->status,
            ],
            'header' => [
                'pemasok' => $header->pemasok ?? null,
                'customer' => $header->customer ?? null,
                'vehicle_number' => $header->vehicle_number ?? null,
            ],
            'items' => $items->map(function ($row) {
                return [
                    'barcode' => $row->barcode,
                    'salestext' => $row->salestext,
                    'batch_no' => $row->batch_no,
                    'pcs' => $row->pcs,
                    'weight' => $row->weight,
                    'length' => $row->length,
                    'berat_kg' => $row->berat_kg,
                    'panjang_mlc' => $row->panjang_mlc,
                ];
            })->values(),
            'scanned_list' => $scannedList,
            'master_count' => $items->count(),
            'scanned_count' => count($scannedList),
        ]);
    }

    /**
     * Scan barcode fisik: cocokkan dengan RawBarcode pada NPL terpilih.
     * Menyimpan hasil scan per-user ke session agar tombol submit bisa dikontrol.
     */
    public function scanBarcode(Request $request)
    {
        $request->validate([
            'npl' => 'required|string',
            'barcode' => 'required|string',
        ]);

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['success' => false, 'message' => 'Cabang belum dipilih.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'Cabang tidak valid.'], 400);
        }

        $npl = trim($request->input('npl'));

        $rawBarcode = trim($request->input('barcode'));
        $barcodeStr = explode(';', $rawBarcode)[0];
        if (strlen($barcodeStr) > 10) {
            $barcodeStr = substr($barcodeStr, 0, 10);
        }
        $barcodeStr = trim($barcodeStr);

        // Pastikan packing list milik cabang aktif
        $packingList = PackingList::query()
            ->where('npl', $npl)
            ->where('kode_customer', $branch->customer_id)
            ->first();

        if (!$packingList) {
            return response()->json(['success' => false, 'message' => "Packing List {$npl} tidak ditemukan untuk cabang ini."], 404);
        }

        $master = RawBarcode::query()
            ->where('no_packing_list', $npl)
            ->where('kode_customer', $branch->customer_id)
            ->where('barcode', $barcodeStr)
            ->first();

        if (!$master) {
            return response()->json([
                'success' => false,
                'message' => "Barcode {$barcodeStr} tidak ada di Packing List {$npl}.",
                'type' => 'not_match',
            ], 404);
        }

        $scannedList = $this->getScannedListFromSession($npl);
        if (in_array($barcodeStr, $scannedList, true)) {
            return response()->json([
                'success' => false,
                'message' => "Barcode {$barcodeStr} sudah pernah di-scan.",
                'type' => 'duplicate',
            ], 400);
        }

        $scannedList[] = $barcodeStr;
        $this->putScannedListToSession($npl, $scannedList);

        $masterCount = RawBarcode::query()
            ->where('no_packing_list', $npl)
            ->where('kode_customer', $branch->customer_id)
            ->count();
        $scannedCount = count($scannedList);

        return response()->json([
            'success' => true,
            'message' => "Barcode {$barcodeStr} cocok dengan Packing List.",
            'barcode_detail' => [
                'barcode' => $master->barcode,
                'salestext' => $master->salestext,
                'batch_no' => $master->batch_no,
                'pcs' => $master->pcs,
                'weight' => $master->weight,
                'length' => $master->length,
            ],
            'scanned_list' => $scannedList,
            'scanned_count' => $scannedCount,
            'master_count' => $masterCount,
            'is_perfect_match' => ($masterCount > 0) && ($scannedCount === $masterCount),
        ]);
    }

    public function getScannedItems(Request $request)
    {
        $request->validate([
            'npl' => 'required|string',
        ]);

        $npl = trim($request->query('npl'));
        $scannedList = $this->getScannedListFromSession($npl);

        return response()->json([
            'success' => true,
            'scanned_list' => $scannedList,
            'scanned_count' => count($scannedList),
        ]);
    }

    public function flushScannedItems(Request $request)
    {
        $request->validate([
            'npl' => 'required|string',
        ]);

        $npl = trim($request->input('npl'));
        $this->putScannedListToSession($npl, []);

        return response()->json(['success' => true, 'message' => 'Data scan berhasil direset.']);
    }

    public function store(Request $request)
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'npl' => ['required', 'string'],
        ], [
            'npl.required' => 'Packing List wajib dipilih.',
        ]);

        $npl = trim($validated['npl']);

        $packingList = PackingList::query()
            ->where('npl', $npl)
            ->where('kode_customer', $branch->customer_id)
            ->first();

        if (!$packingList) {
            return back()->withInput()->with('error', "Packing List {$npl} tidak ditemukan untuk cabang ini.");
        }

        $master = RawBarcode::query()
            ->where('no_packing_list', $npl)
            ->where('kode_customer', $branch->customer_id)
            ->get();

        $masterCount = $master->count();
        if ($masterCount === 0) {
            return back()->withInput()->with('error', "Data barcode untuk Packing List {$npl} belum tersedia (raw).");
        }

        $scannedList = $this->getScannedListFromSession($npl);
        $scannedCount = count($scannedList);

        if ($scannedCount !== $masterCount) {
            return back()->withInput()->with('error', "Belum semua barcode di Packing List {$npl} terscan. ({$scannedCount}/{$masterCount})");
        }

        // Pastikan semua scanned memang ada di master (guard tambahan)
        $masterBarcodes = $master->pluck('barcode')->toArray();
        $diff = array_values(array_diff($masterBarcodes, $scannedList));
        if (!empty($diff)) {
            return back()->withInput()->with('error', 'Terdapat barcode master yang belum terscan. Silakan scan semua item terlebih dahulu.');
        }

        DB::beginTransaction();
        try {
            foreach ($master as $raw) {
                // Ambil attribute RawBarcode yang relevan untuk tabel Barcode (berdasarkan fillable Barcode)
                $rawAttrs = method_exists($raw, 'getAttributes') ? $raw->getAttributes() : [];
                $barcodeFillable = (new Barcode())->getFillable();
                $filteredRaw = array_intersect_key($rawAttrs, array_flip($barcodeFillable));

                // Insert/Update ke Barcode final
                Barcode::updateOrCreate(
                    [
                        'barcode' => $raw->barcode,
                        'kode_customer' => $raw->kode_customer,
                    ],
                    array_merge(
                        $filteredRaw,
                        [
                            'barcode' => $raw->barcode,
                            'kode_customer' => $raw->kode_customer,
                            'status' => Barcode::STATUS_APPROVED,
                            'item_flag' => 'pembelian',
                        ]
                    )
                );

                // Catat ke barang_masuk sebagai log scan penerimaan
                BarangMasuk::firstOrCreate(
                    [
                        'tanggal' => $validated['tanggal'],
                        'npl' => $npl,
                        'nbrg' => $raw->barcode,
                        'kode_customer' => $branch->customer_id,
                    ],
                    [
                        'tanggal' => $validated['tanggal'],
                        'npl' => $npl,
                        'nbrg' => $raw->barcode,
                        'kode_customer' => $branch->customer_id,
                    ]
                );
            }

            // Update status packing list jadi approved
            $packingList->update(['status' => PackingList::STATUS_APPROVED]);

            // Transfer: hapus raw setelah berhasil masuk Barcode
            RawBarcode::query()
                ->where('no_packing_list', $npl)
                ->where('kode_customer', $branch->customer_id)
                ->delete();

            // Reset session scanned list
            $this->putScannedListToSession($npl, []);

            DB::commit();
            return redirect()->route('barang-masuk.create')->with('success', "Packing List {$npl} berhasil di-approve dan dipindahkan ke master Barcode.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal memproses submit: ' . $e->getMessage());
        }
    }

    private function scannedSessionKey(string $npl): string
    {
        $userId = Auth::id() ?? 'guest';
        return "barang_masuk_scanned.{$userId}.{$npl}";
    }

    private function getScannedListFromSession(string $npl): array
    {
        $key = $this->scannedSessionKey($npl);
        $list = session()->get($key, []);
        if (!is_array($list)) {
            return [];
        }
        // Pastikan unik dan string
        $list = array_values(array_unique(array_map('strval', $list)));
        return $list;
    }

    private function putScannedListToSession(string $npl, array $list): void
    {
        $key = $this->scannedSessionKey($npl);
        $list = array_values(array_unique(array_map('strval', $list)));
        session()->put($key, $list);
    }
}
