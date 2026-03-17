<?php

namespace App\Http\Controllers;

use App\Models\BarangMasuk;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\PackingList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BarcodeController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'owner') {
            abort(403, 'Anda tidak memiliki hak akses ke halaman ini.');
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        $barcodes = Barcode::where('kode_customer', $branch->customer_id)->get();
        $lastUpdated = $barcodes->max('updated_at');

        return view('barcode.index', compact('barcodes', 'lastUpdated'));
    }


    public function updateFromCSV()
    {
        $path = public_path('EXPORT_BARCODE_TAKA_NEW.txt');

        if (!file_exists($path)) {
            return redirect()->route('barcode.index')->with('error', 'File TXT tidak ditemukan.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!$lines || count($lines) <= 1) {
            return back()->with('error', 'File TXT kosong atau tidak valid.');
        }

        $header = str_getcsv(array_shift($lines), ';');
        $totalUpdated = 0;

        $user = Auth::user();

        if ($user->role === 'owner') {
            abort(403, 'Anda tidak memiliki hak akses untuk update data.');
        }

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $data = str_getcsv($line, ';');

            $get = function ($key) use ($header, $data) {
                $idx = array_search($key, $header);
                return $idx !== false && isset($data[$idx]) ? trim($data[$idx]) : null;
            };

            // Header TXT: "KODE CUSTOMER" (spasi), fallback "KODE_CUSTOMER" (underscore)
            $kodeCustomer = $get('KODE CUSTOMER') ?? $get('KODE_CUSTOMER');

            if (!$kodeCustomer || $kodeCustomer !== $branch->customer_id) {
                continue;
            }

            $barcodeData = [
                'barcode'         => $get('BARCODE'),
                'status'          => Barcode::STATUS_TEMPORARY,
                'no_packing_list' => $get('NO_PACKING_LIST'),
                'no_billing'      => $get('BILLING'),
                'kode_barang'     => $get('SALESTEXT') . '*#' . $get('WARNA') . ' PL:' . $get('NO_PACKING_LIST'),
                'keterangan'      => $get('SALESTEXT') . ' ' . $get('JOBORDER') . '/*#' . $get('WARNA'),
                'nomor_seri'      => $get('BATCHNO'),
                'length'          => $get('LENGTH'),
                'uom'             => $get('BASE_UOM'),
                'material_code'   => $get('MATERIALCODE'),
                'pcs'             => (int) $get('PCS'),
                'berat_kg'        => (float) str_replace(',', '.', $get('WEIGHT')),
                'panjang_mlc'     => round((float) str_replace(',', '.', $get('LENGTH')) * 0.9, 3),
                'kode_warna'      => $get('KODE_WARNA'),
                'warna'           => $get('WARNA'),
                'bale'            => round((float) str_replace(',', '.', $get('WEIGHT')) / 181.44, 3),
                'harga_ppn'       => (int) str_replace('.', '', explode(',', $get('HARGA_PPN'))[0] ?? 0),
                'harga_jual'      => (int) str_replace('.', '', explode(',', $get('HARGA_JUAL'))[0] ?? 0),
                'pemasok'         => $get('PEMASOK'),
                'customer'        => $get('CUSTOMER'),
                'kontrak'         => $get('CONTRACT'),
                'subtotal'        => (int) str_replace('.', '', explode(',', $get('SUB_TOTAL'))[0] ?? 0),
                'tanggal'         => $get('DATE') ? Carbon::createFromFormat('d.m.Y', $get('DATE'))->format('Y-m-d') : null,
                'jatuh'           => $get('JATUH_TEMPO') ? Carbon::createFromFormat('d.m.Y', $get('JATUH_TEMPO'))->format('Y-m-d') : null,
                'no_vehicle'      => $get('VEHICLE_NUMBER'),
                'kode_customer'   => $kodeCustomer,
            ];

            $barcode = Barcode::updateOrCreate(
                [
                    'barcode'       => $barcodeData['barcode'],
                    'kode_customer' => $barcodeData['kode_customer'],
                ],
                $barcodeData
            );

            if ($barcode->wasRecentlyCreated) {
                $totalUpdated++;
            }
        }

        // Jalankan matching packing list vs barang masuk setelah load TXT
        $matchingResult = $this->runPackingListMatching($branch);

        $message = "Load TXT selesai.";
        if ($totalUpdated > 0) {
            $message .= " $totalUpdated data barcode baru diperbarui.";
        }
        $message .= " Matching: {$matchingResult['approved']} packing list lolos, {$matchingResult['rejected']} packing list dihapus.";

        return redirect()->route('barcode.index')
            ->with('success', $message);
    }

    /**
     * Matching Packing List vs Barang Masuk setelah update barcode dari TXT.
     * Per packing list: jika semua barcode punya BarangMasuk yang sesuai → status approved.
     * Jika ada yang tidak sesuai → hapus PackingList, BarangMasuk terkait, dan Barcode terkait.
     */
    protected function runPackingListMatching(Branch $branch): array
    {
        $approved = 0;
        $rejected = 0;
        $kodeCustomer = $branch->customer_id;

        $packingLists = PackingList::where('kode_customer', $kodeCustomer)->get();

        foreach ($packingLists as $pl) {
            $barcodesInPL = Barcode::where('no_packing_list', $pl->npl)
                ->where('kode_customer', $kodeCustomer)
                ->get();

            if ($barcodesInPL->isEmpty()) {
                continue;
            }

            $barcodeValues = $barcodesInPL->pluck('barcode')->toArray();
            $barangMasuks = BarangMasuk::whereIn('nbrg', $barcodeValues)
                ->where('kode_customer', $kodeCustomer)
                ->get();

            // Cek: setiap barcode harus punya tepat 1 BarangMasuk, jumlah harus sama
            $barangMasukByNbrg = $barangMasuks->groupBy('nbrg');
            $allMatch = $barangMasuks->count() === count($barcodeValues);

            if ($allMatch) {
                foreach ($barcodeValues as $bc) {
                    if (!isset($barangMasukByNbrg[$bc]) || $barangMasukByNbrg[$bc]->count() !== 1) {
                        $allMatch = false;
                        break;
                    }
                }
            }

            if ($allMatch) {
                Barcode::whereIn('id', $barcodesInPL->pluck('id'))
                    ->update(['status' => Barcode::STATUS_APPROVED]);
                $pl->update(['status' => PackingList::STATUS_APPROVED]);
                $approved++;
            } else {
                DB::transaction(function () use ($barcodeValues, $kodeCustomer, $barcodesInPL, $pl) {
                    BarangMasuk::whereIn('nbrg', $barcodeValues)
                        ->where('kode_customer', $kodeCustomer)
                        ->delete();
                    Barcode::whereIn('id', $barcodesInPL->pluck('id'))->delete();
                    $pl->delete();
                });
                $rejected++;
            }
        }

        return ['approved' => $approved, 'rejected' => $rejected];
    }
}
