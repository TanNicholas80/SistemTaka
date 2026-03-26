<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PrintBarcodeController extends Controller
{
    private function branch(): ?Branch
    {
        $id = session('active_branch');
        return $id ? Branch::find($id) : null;
    }

    private function apiHeaders(): array
    {
        $user = Auth::user();
        $timestamp = Carbon::now()->toIsoString();
        return [
            'Authorization' => 'Bearer ' . $user->accurate_api_token,
            'X-Api-Signature' => hash_hmac('sha256', $timestamp, $user->accurate_signature_secret),
            'X-Api-Timestamp' => $timestamp,
        ];
    }

    private function buildApiUrl(Branch $branch, string $endpoint): string
    {
        $base = rtrim($branch->url_accurate ?? 'https://iris.accurate.id', '/');
        if (str_contains($base, '/accurate/api')) {
            return $base . '/' . ltrim($endpoint, '/');
        }
        return $base . '/accurate/api/' . ltrim($endpoint, '/');
    }

    // ─── Pages ───────────────────────────────────────────────

    public function index()
    {
        return view('penerimaan_barang.print_barcode.index');
    }

    // ─── AJAX: autocomplete item search ──────────────────────

    public function searchItems(Request $request)
    {
        $branch = $this->branch();
        if (!$branch)
            return response()->json(['error' => 'Cabang belum dipilih.'], 400);

        $keyword = $request->string('q', '')->trim()->toString();
        if ($keyword === '')
            return response()->json([]);

        try {
            $res = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders($this->apiHeaders())
                ->get($this->buildApiUrl($branch, 'item/list.do'), [
                    'keywords' => $keyword,
                    'sp.pageSize' => 5,
                    'fields' => 'no,name',
                ]);

            if (!$res->successful())
                return response()->json([]);

            $items = $res->json('d') ?? [];
            return response()->json(array_values(array_slice($items, 0, 5)));
        } catch (\Throwable $e) {
            Log::error('PrintBarcode searchItems error', ['msg' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    // ─── AJAX: serial numbers per warehouse for an item ──────

    public function getSerials(Request $request)
    {
        $itemNo = $request->string('itemNo')->trim()->toString();
        if ($itemNo === '')
            return response()->json(['error' => 'itemNo wajib diisi.'], 422);

        $branch = $this->branch();
        if (!$branch)
            return response()->json(['error' => 'Cabang belum dipilih.'], 400);

        try {
            $headers = $this->apiHeaders();

            // Fetch item detail (name, brand, charFields, unit) and serial numbers in parallel-ish
            $snRes = Http::withoutVerifying()->timeout(30)->withHeaders($headers)
                ->get($this->buildApiUrl($branch, 'report/serial-number-per-warehouse.do'), [
                    'itemNo' => $itemNo,
                ]);

            if (!$snRes->successful()) {
                Log::error('PrintBarcode getSerials HTTP failed', ['status' => $snRes->status(), 'body' => mb_substr($snRes->body(), 0, 500)]);
                return response()->json(['error' => 'Gagal mengambil data dari Accurate.'], 500);
            }

            // Fetch item detail for name, brand, charFields, unit
            $itemRes = Http::withoutVerifying()->timeout(20)->withHeaders($headers)
                ->get($this->buildApiUrl($branch, 'item/detail.do'), ['no' => $itemNo]);

            $itemDetail = [];
            if ($itemRes->successful()) {
                $itemDetail = $itemRes->json('d') ?? [];
            }

            $itemName = $itemDetail['name'] ?? '';
            $brand = $itemDetail['itemBrand']['nameWithIndentStrip'] ?? '';
            $motif = $itemDetail['charField6'] ?? '';
            $warna = $itemDetail['charField4'] ?? '';
            $specialTreat = $itemDetail['charField5'] ?? '';
            $unit = $itemDetail['unit1']['name'] ?? '';

            // Actual structure: d[] = { warehouse:{}, serialNumber:{number, id, ...}, quantity:float }
            $raw = $snRes->json('d') ?? [];
            $rows = [];
            foreach ($raw as $entry) {
                $sn = $entry['serialNumber'] ?? null;
                if (!$sn)
                    continue;
                $rows[] = [
                    'serialNo' => $sn['number'] ?? '',
                    'itemName' => $itemName,
                    'brand' => $brand,
                    'motif' => $motif,
                    'warna' => $warna,
                    'specialTreat' => $specialTreat,
                    'qty' => floatval($entry['quantity'] ?? 0),
                    'unit' => $unit,
                ];
            }

            return response()->json($rows);
        } catch (\Throwable $e) {
            Log::error('PrintBarcode getSerials error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── PDF helpers ─────────────────────────────────────────

    private function buildLabelData(string $serialNo, array $itemData, bool $isReprint): array
    {
        $barcode = $serialNo;
        $type = strtoupper(trim($itemData['charField1'] ?? ''));
        if ($type === '')
            $type = 'DEFAULT';

        $printLayouts = [
            'JARIK' => ['heightMm' => 25, 'qrSizePx' => 190],
            'SARUNG' => ['heightMm' => 25, 'qrSizePx' => 190],
            'PRINTING' => ['heightMm' => 25, 'qrSizePx' => 190],
            'KNITTING' => ['heightMm' => 25, 'qrSizePx' => 190],
            'DENIM' => ['heightMm' => 25, 'qrSizePx' => 190],
            'DYEING' => ['heightMm' => 25, 'qrSizePx' => 190],
            'DEFAULT' => ['heightMm' => 25, 'qrSizePx' => 190],
        ];
        $layout = $printLayouts[$type] ?? $printLayouts['DEFAULT'];

        $qrSvg = QrCode::format('svg')->size((int) $layout['qrSizePx'])->generate($barcode);
        $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        $formatQty = static function ($qty): string {
            $n = (float) $qty;
            return abs($n - round($n)) < 1e-9
                ? (string) (int) round($n)
                : rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
        };

        $qty = $itemData['qty'] ?? 0;
        $unit = $itemData['unit'] ?? '';
        $qtyUnit = trim($formatQty($qty) . ' ' . $unit);
        $qtyAndUnit = $qtyUnit !== '' ? [$qtyUnit] : [];

        $brand = (string) ($itemData['brand'] ?? '');
        $name = (string) ($itemData['itemName'] ?? '');
        $cf2 = (string) ($itemData['charField2'] ?? '');
        $cf4 = (string) ($itemData['charField4'] ?? '');
        $cf5 = (string) ($itemData['charField5'] ?? '');
        $cf6 = (string) ($itemData['charField6'] ?? '');

        $rightLines = match ($type) {
            'JARIK' => array_merge([$brand, $cf6], $qtyAndUnit),
            'SARUNG' => array_merge([$name], $qtyAndUnit),
            'PRINTING' => array_merge([$brand, $cf6, $cf4, $cf5], $qtyAndUnit),
            'KNITTING' => array_merge([$name, $cf4, $cf5], $qtyAndUnit),
            'DENIM' => array_merge([$brand, $cf5], $qtyAndUnit),
            'DYEING' => array_merge([$brand, $cf2, $cf4, $cf5], $qtyAndUnit),
            default => array_merge([$brand ?: $name], $qtyAndUnit),
        };

        return [
            'barcode' => $barcode,
            'isReprint' => $isReprint,
            'rightLines' => array_values(array_filter($rightLines, fn($v) => trim($v) !== '')),
            'qrSrc' => $qrSrc,
            'type' => $type,
            'layout' => $layout,
        ];
    }

    private function fetchItemDataFromAccurate(string $itemNo, string $serialNo, Branch $branch): array
    {
        $headers = $this->apiHeaders();
        $itemData = ['itemName' => '', 'brand' => '', 'charField1' => '', 'charField2' => '', 'charField4' => '', 'charField5' => '', 'charField6' => '', 'qty' => 0, 'unit' => ''];

        try {
            // item/detail.do for charFields and brand
            $detailRes = Http::withoutVerifying()->timeout(20)->withHeaders($headers)
                ->get($this->buildApiUrl($branch, 'item/detail.do'), ['no' => $itemNo]);

            if ($detailRes->successful()) {
                $d = $detailRes->json('d') ?? [];
                $itemData['itemName'] = $d['name'] ?? '';
                $itemData['brand'] = $d['itemBrand']['nameWithIndentStrip'] ?? '';
                $itemData['charField1'] = trim($d['charField1'] ?? '');
                $itemData['charField2'] = trim($d['charField2'] ?? '');
                $itemData['charField4'] = trim($d['charField4'] ?? '');
                $itemData['charField5'] = trim($d['charField5'] ?? '');
                $itemData['charField6'] = trim($d['charField6'] ?? '');
            }

            // serial-number-per-warehouse.do for qty and unit
            $snRes = Http::withoutVerifying()->timeout(20)->withHeaders($headers)
                ->get($this->buildApiUrl($branch, 'report/serial-number-per-warehouse.do'), ['itemNo' => $itemNo]);

            if ($snRes->successful()) {
                foreach ($snRes->json('d') ?? [] as $entry) {
                    $sn = $entry['serialNumber'] ?? null;
                    if ($sn && ($sn['number'] ?? '') === $serialNo) {
                        $itemData['qty'] = floatval($entry['quantity'] ?? 0);
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('PrintBarcode fetchItemData error', ['msg' => $e->getMessage()]);
        }

        return $itemData;
    }

    private function renderPdf(array $labels, float $heightMm): \Illuminate\Http\Response
    {
        $mmToPoints = static fn(float $mm): float => $mm * 72 / 25.4;
        $w = $mmToPoints(65);
        $h = $mmToPoints($heightMm);

        $pdf = Pdf::loadView('penerimaan_barang.print_non_pl_labels', [
            'type' => $labels[0]['type'] ?? 'DEFAULT',
            'layout' => $labels[0]['layout'] ?? ['heightMm' => $heightMm],
            'labels' => $labels,
            'labelHeightMm' => $heightMm,
            'isReprint' => $labels[0]['isReprint'] ?? false,
        ])->setPaper([0, 0, $w, $h])->setOptions(['isRemoteEnabled' => true]);

        return $pdf->stream('reprint-barcode.pdf');
    }

    // ─── Single reprint ───────────────────────────────────────

    public function reprint(Request $request, string $serialNo)
    {
        $itemNo = $request->string('itemNo')->trim()->toString();
        $branch = $this->branch();
        if (!$branch)
            abort(400, 'Cabang belum dipilih.');

        $itemData = $this->fetchItemDataFromAccurate($itemNo, $serialNo, $branch);
        $label = $this->buildLabelData($serialNo, $itemData, true);

        return $this->renderPdf([$label], $label['layout']['heightMm']);
    }

    // ─── Bulk reprint ─────────────────────────────────────────

    public function bulkReprint(Request $request)
    {
        $items = $request->input('items', []); // [{serialNo, itemNo}, ...]
        if (empty($items))
            abort(422, 'Tidak ada barcode yang dipilih.');

        $branch = $this->branch();
        if (!$branch)
            abort(400, 'Cabang belum dipilih.');

        $labels = [];
        $heightMm = 25;

        foreach ($items as $row) {
            $serialNo = trim($row['serialNo'] ?? '');
            $itemNo = trim($row['itemNo'] ?? '');
            if ($serialNo === '')
                continue;

            $itemData = $this->fetchItemDataFromAccurate($itemNo, $serialNo, $branch);
            $label = $this->buildLabelData($serialNo, $itemData, true);
            $heightMm = $label['layout']['heightMm'];
            $labels[] = $label;
        }

        if (empty($labels))
            abort(422, 'Data barcode tidak valid.');

        return $this->renderPdf($labels, $heightMm);
    }
}
