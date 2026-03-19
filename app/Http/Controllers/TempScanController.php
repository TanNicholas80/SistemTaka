<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\TempMasterBarcode;
use App\Models\TempScannedBarcode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TempScanController extends Controller
{
    /**
     * Upload and parse Master Barcode TXT to temporary table.
     */
    public function uploadTxt(Request $request)
    {
        $request->validate([
            'no_po' => 'required|string',
            'txt_file' => 'nullable|file|mimes:txt,csv',
        ]);

        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return response()->json(['success' => false, 'message' => 'Cabang belum dipilih.'], 400);
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'Cabang tidak valid.'], 400);
        }

        $noPo = $request->input('no_po');
        $userId = Auth::id();

        // Check if user provided file, else fallback to SFTP default location
        if ($request->hasFile('txt_file')) {
            $file = $request->file('txt_file');
            $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        } else {
            $path = storage_path('app/sftp-uploads/EXPORT_BARCODE_TAKA.txt');
            if (file_exists($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            } else {
                return response()->json(['success' => false, 'message' => 'File TXT tidak ditemukan dan tidak ada file yang diunggah.'], 400);
            }
        }

        if (!$lines || count($lines) <= 1) {
            return response()->json(['success' => false, 'message' => 'File TXT kosong atau tidak valid.'], 400);
        }

        $header = str_getcsv(array_shift($lines), ';');
        $insertedCount = 0;

        DB::beginTransaction();

        try {
            // Optional: flush previous master barcodes for this PO/User session?
            // User might re-upload, so let's clear existing first
            TempMasterBarcode::where('no_po', $noPo)->where('user_id', $userId)->delete();
            TempScannedBarcode::where('no_po', $noPo)->where('user_id', $userId)->delete();

            $insertData = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;

                $data = str_getcsv($line, ';');

                $get = function ($key) use ($header, $data) {
                    $idx = array_search($key, $header);
                    return $idx !== false && isset($data[$idx]) ? trim($data[$idx]) : null;
                };

                $kodeCustomer = $get('KODE_CUSTOMER');

                // Filter valid branch
                if ($kodeCustomer !== $branch->customer_id) {
                    continue;
                }

                $barcodeItem = $get('BARCODE');
                if (!$barcodeItem) continue;

                // Validate uniqueness in array just in case
                if (isset($insertData[$barcodeItem])) continue;

                $insertData[$barcodeItem] = [
                    'no_po'           => $noPo,
                    'user_id'         => $userId,
                    'kode_customer'   => $kodeCustomer,
                    'barcode'         => $barcodeItem,
                    'no_packing_list' => $get('NO_PACKING_LIST'),
                    'no_billing'      => $get('BILLING'),
                    'kode_barang'     => $get('SALESTEXT') . '*#' . $get('WARNA') . ' PL:' . $get('NO_PACKING_LIST'),
                    'keterangan'      => $get('SALESTEXT') . ' ' . $get('JOBORDER') . '/*#' . $get('WARNA'),
                    'nomor_seri'      => $get('BATCHNO'),
                    'pcs'             => $get('PCS'),
                    'berat_kg'        => str_replace(',', '.', $get('WEIGHT')),
                    'panjang_mlc'     => round((float) str_replace(',', '.', $get('LENGTH')) * 0.9, 3),
                    'warna'           => $get('WARNA'),
                    'bale'            => round((float) str_replace(',', '.', $get('WEIGHT')) / 181.44, 3),
                    'harga_ppn'       => str_replace('.', '', explode(',', $get('HARGA_PPN'))[0] ?? 0),
                    'harga_jual'      => str_replace('.', '', explode(',', $get('HARGA_JUAL'))[0] ?? 0),
                    'pemasok'         => $get('PEMASOK'),
                    'customer'        => $get('CUSTOMER'),
                    'kontrak'         => $get('CONTRACT'),
                    'subtotal'        => str_replace('.', '', explode(',', $get('SUB_TOTAL'))[0] ?? 0),
                    'tanggal'         => $get('DATE') ? Carbon::createFromFormat('d.m.Y', $get('DATE'))->format('Y-m-d') : null,
                    'jatuh'           => $get('JATUH_TEMPO') ? Carbon::createFromFormat('d.m.Y', $get('JATUH_TEMPO'))->format('Y-m-d') : null,
                    'no_vehicle'      => $get('VEHICLE_NUMBER'),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
                $insertedCount++;
            }

            // Chunk insert
            $chunks = array_chunk(array_values($insertData), 500);
            foreach ($chunks as $chunk) {
                TempMasterBarcode::insert($chunk);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil memproses {$insertedCount} baris Master Barcode.",
                'count' => $insertedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Scan physical barcode
     */
    public function scanPhysical(Request $request)
    {
        $request->validate([
            'no_po' => 'required|string',
            'barcode' => 'required|string',
        ]);

        $noPo = $request->input('no_po');
        $barcode = trim($request->input('barcode'));
        $userId = Auth::id();

        // Ensure 10-char length if needed, match with BarangMasukController behavior
        $barcode = explode(';', $barcode)[0];
        if (strlen($barcode) > 10) {
            $barcode = substr($barcode, 0, 10);
        }

        // Check if exists in TempMasterBarcode
        $master = TempMasterBarcode::where('no_po', $noPo)
            ->where('user_id', $userId)
            ->where('barcode', $barcode)
            ->first();

        if (!$master) {
            return response()->json([
                'success' => false, 
                'message' => "Barcode {$barcode} tidak ditemukan di Master Barcode (TXT) untuk PO ini."
            ], 404);
        }

        // Check if already scanned
        $alreadyScanned = TempScannedBarcode::where('no_po', $noPo)
            ->where('user_id', $userId)
            ->where('barcode', $barcode)
            ->exists();

        if ($alreadyScanned) {
            return response()->json([
                'success' => false, 
                'message' => "Barcode {$barcode} ini sudah di-scan sebelumnya."
            ], 400);
        }

        // Insert to Scanned
        TempScannedBarcode::create([
            'no_po' => $noPo,
            'user_id' => $userId,
            'kode_customer' => $master->kode_customer,
            'barcode' => $barcode,
        ]);

        // Get updated count
        $scannedCount = TempScannedBarcode::where('no_po', $noPo)->where('user_id', $userId)->count();
        $masterCount = TempMasterBarcode::where('no_po', $noPo)->where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'message' => "Barcode {$barcode} berhasil divalidasi.",
            'barcode_detail' => $master,
            'scanned_count' => $scannedCount,
            'master_count' => $masterCount,
            'is_perfect_match' => ($scannedCount === $masterCount) && ($masterCount > 0)
        ]);
    }

    /**
     * Get Scanned Items
     */
    public function getScannedItems(Request $request) {
        $noPo = $request->query('no_po');
        $userId = Auth::id();

        $scannedList = TempScannedBarcode::where('no_po', $noPo)
            ->where('user_id', $userId)
            ->pluck('barcode')
            ->toArray();
            
        $masterCount = TempMasterBarcode::where('no_po', $noPo)->where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'scanned_list' => $scannedList,
            'master_count' => $masterCount,
            'scanned_count' => count($scannedList),
        ]);
    }

    /**
     * Flush temp data
     */
    public function flush(Request $request)
    {
        $noPo = $request->input('no_po');
        $userId = Auth::id();

        TempMasterBarcode::where('no_po', $noPo)->where('user_id', $userId)->delete();
        TempScannedBarcode::where('no_po', $noPo)->where('user_id', $userId)->delete();

        return response()->json(['success' => true, 'message' => 'Data temporary berhasil dihapus.']);
    }

    /**
     * Scan physical barcode for Outbound (Pengiriman Pesanan)
     * Validates against local Barcode database.
     */
    public function scanOutbound(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $rawBarcode = trim($request->input('barcode'));

        // Handle possible concatenated string (e.g. from scanner)
        $barcodeStr = explode(';', $rawBarcode)[0];
        if (strlen($barcodeStr) > 10) {
            $barcodeStr = substr($barcodeStr, 0, 10);
        }

        // Cari di tabel Barcode
        $barcode = \App\Models\Barcode::where('barcode', $barcodeStr)->first();

        if (!$barcode) {
            return response()->json([
                'status' => 'error',
                'message' => "Barcode {$barcodeStr} tidak ditemukan di database master barang."
            ]);
        }
        
        $keteranganParts = explode('/', $barcode->keterangan);
        $aliasName = trim($keteranganParts[0] ?? '');

        // Format material_code: 12 digit terakhir - kode_warna (untuk matching dengan item Accurate)
        $rawMc = preg_replace('/\D/', '', $barcode->material_code ?? '');
        $mc12 = $rawMc !== '' ? substr($rawMc, -12) : '';
        $kw = trim($barcode->kode_warna ?? '');
        $materialCodeFormatted = ($mc12 && $kw) ? $mc12 . ' - ' . $kw : $mc12;

        $length = (float) str_replace(',', '.', $barcode->length ?? 0);

        return response()->json([
            'status' => 'success',
            'message' => "Barcode berhasil divalidasi.",
            'data' => [
                'barcode' => $barcode->barcode,
                'kode_barang' => $barcode->kode_barang,
                'keterangan' => $barcode->keterangan,
                'alias_nama' => $aliasName,
                'material_code_formatted' => $materialCodeFormatted,
                'length' => $length,
                'panjang_mlc' => (float) $barcode->panjang_mlc
            ]
        ]);
    }
}
