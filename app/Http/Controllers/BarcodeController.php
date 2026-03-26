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
use Illuminate\Support\Facades\Cache;
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

        // Cache per cabang agar load lebih cepat, terutama saat data barcode banyak
        $cacheKey = 'barcodes_index_branch_' . $activeBranchId;

        $cached = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($activeBranchId) {
            // Ambil data barcode murni dari model Barcode
            // menggunakan scopeForBranch agar konsisten dengan filtering cabang
            $barcodes = Barcode::forBranch($activeBranchId)->get();
            $lastUpdated = $barcodes->max('updated_at');

            return compact('barcodes', 'lastUpdated');
        });

        $barcodes = $cached['barcodes'];
        $lastUpdated = $cached['lastUpdated'];

        return view('barcode.index', compact('barcodes', 'lastUpdated'));
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
