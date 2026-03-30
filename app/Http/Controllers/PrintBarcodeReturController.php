<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrintBarcodeReturController extends Controller
{
    private function buildApiUrl(Branch $branch, string $endpoint): string
    {
        $baseUrl = $branch->url_accurate ?? 'https://iris.accurate.id';
        $baseUrl = rtrim($baseUrl, '/');
        $apiPath = '/accurate/api';

        if (strpos($baseUrl, '/accurate/api') !== false) {
            return $baseUrl . '/' . ltrim($endpoint, '/');
        }

        return $baseUrl . $apiPath . '/' . ltrim($endpoint, '/');
    }

    /**
     * @return array{0: Branch, 1: string, 2: string}
     */
    private function branchUserCredentials(): array
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            throw new \RuntimeException('Tidak ada cabang yang aktif.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            throw new \RuntimeException('Cabang tidak valid.');
        }

        if (!Auth::check() || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null) || !(\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)) {
            throw new \RuntimeException('Kredensial API Accurate belum dikonfigurasi.');
        }

        return [$branch, (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_api_token'] ?? null), (\App\Models\UserAccurateAPI::getCredentialsForAuthUser(session('active_branch'))['accurate_signature_secret'] ?? null)];
    }

    /**
     * Accurate mengembalikan statusName bahasa Indonesia, mis. "Belum Lunas", "Lunas".
     * Harus bandingkan setelah dinormalisasi (uppercase); jangan strtoupper lalu in_array ke string Title Case.
     */
    private function isInvoiceStatusAllowedForPrint(array $invoice): bool
    {
        $statusName = strtoupper(trim((string) ($invoice['statusName'] ?? '')));
        if ($statusName !== '') {
            if (in_array($statusName, ['BELUM LUNAS', 'LUNAS', 'SEBAGIAN'], true)) {
                return true;
            }
            if (str_contains($statusName, 'BELUM') && str_contains($statusName, 'LUNAS')) {
                return true;
            }
        }

        $allowedKeys = ['OUTSTANDING', 'PAID', 'PARTIAL', 'PARTIALLY_PAID', 'OPEN'];
        $raw = $invoice['status'] ?? null;
        if (is_string($raw)) {
            $u = strtoupper(trim($raw));

            return in_array($u, $allowedKeys, true);
        }
        if (is_array($raw)) {
            $u = strtoupper(trim((string) ($raw['name'] ?? $raw['key'] ?? '')));

            return in_array($u, $allowedKeys, true);
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSalesInvoiceListAllPages(
        Branch $branch,
        string $apiToken,
        string $signature,
        string $timestamp
    ): array {
        $salesInvoiceApiUrl = $this->buildApiUrl($branch, 'sales-invoice/list.do');
        $baseQuery = [
            'sp.page' => 1,
            'sp.pageSize' => 20,
            'fields' => 'number,customer,statusName',
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ];

        $first = Http::timeout(60)->withoutVerifying()->withHeaders($headers)->get($salesInvoiceApiUrl, $baseQuery);

        if (!$first->successful()) {
            Log::error('PrintBarcodeRetur: sales-invoice/list.do gagal', [
                'status' => $first->status(),
                'body' => $first->body(),
            ]);

            return [];
        }

        $responseData = $first->json();
        $all = $responseData['d'] ?? [];
        if (!is_array($all)) {
            return [];
        }

        $rowCount = (int) ($responseData['sp']['rowCount'] ?? count($all));
        $pageSize = 20;
        $totalPages = max(1, (int) ceil($rowCount / $pageSize));
        $sample = array_slice($all, 0, 5);

        Log::info('PrintBarcodeRetur: sales-invoice/list.do page 1 response', [
            'branch_id' => $branch->id,
            'sp_rowCount' => $rowCount,
            'd_count' => count($all),
            'totalPages_est' => $totalPages,
            'sample' => array_map(function ($inv) {
                return [
                    'number' => is_array($inv) ? (string) ($inv['number'] ?? '') : '',
                    'statusName' => is_array($inv) ? (string) ($inv['statusName'] ?? '') : '',
                ];
            }, $sample),
        ]);

        for ($page = 2; $page <= $totalPages; $page++) {
            $query = array_merge($baseQuery, ['sp.page' => $page]);
            $r = Http::timeout(60)->withoutVerifying()->withHeaders($headers)->get($salesInvoiceApiUrl, $query);
            if (!$r->successful()) {
                Log::warning('PrintBarcodeRetur: sales-invoice/list.do halaman gagal', ['page' => $page]);

                break;
            }
            $chunk = $r->json()['d'] ?? [];
            if (!is_array($chunk) || $chunk === []) {
                break;
            }

            if ($page <= 3) {
                $chunkSample = array_slice($chunk, 0, 5);
                Log::info('PrintBarcodeRetur: sales-invoice/list.do page response', [
                    'branch_id' => $branch->id,
                    'page' => $page,
                    'd_count' => count($chunk),
                    'sample' => array_map(function ($inv) {
                        return [
                            'number' => is_array($inv) ? (string) ($inv['number'] ?? '') : '',
                            'statusName' => is_array($inv) ? (string) ($inv['statusName'] ?? '') : '',
                        ];
                    }, $chunkSample),
                ]);
            } else {
                Log::info('PrintBarcodeRetur: sales-invoice/list.do page response', [
                    'branch_id' => $branch->id,
                    'page' => $page,
                    'd_count' => count($chunk),
                ]);
            }

            $all = array_merge($all, $chunk);
        }

        return $all;
    }

    /**
     * Daftar sales invoice untuk print retur: fetch API + filter pelanggan/status (tanpa query pencarian q).
     *
     * @return array{0: list<array<string, mixed>>, 1: int} [invoices, rawCount_dari_api]
     */
    private function collectSalesInvoicesForPrint(
        Branch $branch,
        string $apiToken,
        string $signatureSecret
    ): array {
        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        // Ambil sales invoice dari Accurate tanpa filter customerNo.
        $all = $this->fetchSalesInvoiceListAllPages($branch, $apiToken, $signature, $timestamp);
        $rawCount = count($all);

        Log::info('PrintBarcodeRetur: collectSalesInvoicesForPrint after fetch', [
            'branch_id' => $branch->id,
            'raw_api_count' => $rawCount,
        ]);

        $filtered = array_values(array_filter(
            $all,
            fn ($inv) => is_array($inv) && $this->isInvoiceStatusAllowedForPrint($inv)
        ));
        $afterStatusCount = count($filtered);

        $statusSample = array_slice($filtered, 0, 10);
        $statusDistSample = array_map(function ($inv) {
            return [
                'number' => is_array($inv) ? (string) ($inv['number'] ?? '') : '',
                'statusName' => is_array($inv) ? (string) ($inv['statusName'] ?? '') : '',
            ];
        }, $statusSample);

        if ($rawCount > 0 && $afterStatusCount === 0) {
            $sample = $all[0] ?? null;
            Log::warning('PrintBarcodeRetur: semua faktur terbuang filter status', [
                'branch_id' => $branch->id,
                'sample_statusName' => is_array($sample) ? ($sample['statusName'] ?? null) : null,
            ]);
        } else {
            Log::info('PrintBarcodeRetur: collectSalesInvoicesForPrint after status filter', [
                'branch_id' => $branch->id,
                'after_status_filter_count' => $afterStatusCount,
                'sample' => $statusDistSample,
            ]);
        }

        return [$filtered, $rawCount];
    }

    private function itemSnapshotFromDoLine(array $line): array
    {
        $item = $line['item'] ?? [];
        $unitName = trim((string) ($line['itemUnit']['name'] ?? ($item['unit1']['name'] ?? '')));

        $brandStrip = (string) ($item['nameWithIndentStrip'] ?? '');
        if ($brandStrip === '' && isset($item['itemBrand']['nameWithIndentStrip'])) {
            $brandStrip = (string) $item['itemBrand']['nameWithIndentStrip'];
        }

        return [
            'no' => (string) ($item['no'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'nameWithIndentStrip' => $brandStrip,
            'charField1' => (string) ($item['charField1'] ?? ''),
            'charField2' => (string) ($item['charField2'] ?? ''),
            'charField4' => (string) ($item['charField4'] ?? ''),
            'charField5' => (string) ($item['charField5'] ?? ''),
            'charField6' => (string) ($item['charField6'] ?? ''),
            'kode_barang' => (string) ($item['no'] ?? ''),
            'itemUnitName' => $unitName,
            'uom' => $unitName,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTableRowsFromDoDetailItems(array $detailItems): array
    {
        $rows = [];
        $idx = 0;

        foreach ($detailItems as $line) {
            if (!is_array($line)) {
                continue;
            }

            $itemSnap = $this->itemSnapshotFromDoLine($line);
            $lineQty = (float) ($line['quantity'] ?? 0);
            $kode = $itemSnap['no'];
            $nama = $itemSnap['name'];
            $satuan = $itemSnap['itemUnitName'];

            $serialRows = [];
            $detailSerial = $line['detailSerialNumber'] ?? null;

            if (is_array($detailSerial) && $detailSerial !== []) {
                foreach ($detailSerial as $sn) {
                    if (!is_array($sn)) {
                        continue;
                    }
                    $barcode = trim((string) data_get($sn, 'serialNumber.number'));
                    if ($barcode === '') {
                        $barcode = trim((string) ($sn['serialNumberNo'] ?? ''));
                    }
                    $q = (float) ($sn['quantity'] ?? 0);
                    if ($q <= 0) {
                        $q = $lineQty > 0 ? $lineQty : 1.0;
                    }
                    if ($barcode === '') {
                        continue;
                    }
                    $serialRows[] = [
                        'barcode' => $barcode,
                        'quantity' => $q,
                    ];
                }
            }

            if ($serialRows !== []) {
                foreach ($serialRows as $sr) {
                    $rows[] = [
                        'row_id' => 'r' . (++$idx),
                        'barcode' => $sr['barcode'],
                        'kode' => $kode,
                        'qty' => $sr['quantity'],
                        'satuan' => $satuan,
                        'nama_barang' => $nama,
                        'printable' => true,
                        'item' => $itemSnap,
                    ];
                }

                continue;
            }

            $rows[] = [
                'row_id' => 'r' . (++$idx),
                'barcode' => '',
                'kode' => $kode,
                'qty' => $lineQty,
                'satuan' => $satuan,
                'nama_barang' => $nama,
                'printable' => false,
                'item' => $itemSnap,
            ];
        }

        return $rows;
    }

    public function index()
    {
        try {
            [$branch, $apiToken, $signatureSecret] = $this->branchUserCredentials();
        } catch (\RuntimeException $e) {
            return redirect()->route('barang_master.index')->with('error', $e->getMessage());
        }

        // customerNo ini hanya dipakai untuk enable/disable UI di view,
        // bukan untuk filtering query sales-invoice dari Accurate.
        $customerNo = $branch->customer_id ?? 'ALL';

        $faktur_list = [];
        // Daftar faktur diambil dari Accurate berdasarkan status saja (tanpa filter customerNo).
        [$invoices] = $this->collectSalesInvoicesForPrint($branch, $apiToken, $signatureSecret);
        foreach ($invoices as $inv) {
            if (!is_array($inv)) {
                continue;
            }
            $num = (string) ($inv['number'] ?? '');
            if ($num === '') {
                continue;
            }
            $faktur_list[] = [
                'number_faktur' => $num,
                'status_faktur' => (string) ($inv['statusName'] ?? ''),
                'customer_name' => trim((string) data_get($inv, 'customer.name')),
            ];
        }

        Log::info('PrintBarcodeRetur: index faktur_list built', [
            'branch_id' => $branch->id,
            'customerNo' => $customerNo,
            'faktur_list_count' => count($faktur_list),
            'faktur_list_sample' => array_slice($faktur_list, 0, 10),
        ]);

        $urls = [
            'resolve' => route('print_barcode_retur.resolve'),
            'printPdf' => route('penerimaan-barang.non-pl.print-pdf'),
        ];

        return view('print_barcode_retur.index', compact('branch', 'customerNo', 'urls', 'faktur_list'));
    }

    public function getSalesInvoicesForPrintAjax(Request $request)
    {
        try {
            [$branch, $apiToken, $signatureSecret] = $this->branchUserCredentials();
        } catch (\RuntimeException $e) {
            return response()->json(['results' => [], 'message' => $e->getMessage()], 400);
        }

        // Tidak perlu filter customerNo; ambil berdasarkan status saja.
        [$filtered, $rawCount] = $this->collectSalesInvoicesForPrint($branch, $apiToken, $signatureSecret);
        $afterStatusCount = count($filtered);

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $filtered = array_values(array_filter($filtered, function ($inv) use ($needle) {
                $num = mb_strtolower((string) ($inv['number'] ?? ''));

                return $num !== '' && str_contains($num, $needle);
            }));
        }

        $results = [];
        foreach ($filtered as $inv) {
            $num = (string) ($inv['number'] ?? '');
            if ($num === '') {
                continue;
            }
            $custName = trim((string) data_get($inv, 'customer.name'));
            $results[] = [
                'id' => $num,
                'text' => $custName !== '' ? ($num . ' — ' . $custName) : $num,
            ];
        }

        Log::info('PrintBarcodeRetur: sales_invoices ajax', [
            'branch_id' => $branch->id,
            'raw_api_count' => $rawCount,
            'after_status_filter' => $afterStatusCount,
            'results' => count($results),
        ]);

        return response()->json(['results' => $results]);
    }

    public function resolveFromInvoice(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:255',
        ]);

        try {
            [$branch, $apiToken, $signatureSecret] = $this->branchUserCredentials();
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        $timestamp = Carbon::now()->toIso8601String();
        $signature = hash_hmac('sha256', $timestamp, $signatureSecret);

        $headers = [
            'Authorization' => 'Bearer ' . $apiToken,
            'X-Api-Signature' => $signature,
            'X-Api-Timestamp' => $timestamp,
        ];

        $invoiceNumber = $validated['number'];

        $siResponse = Http::timeout(60)->withoutVerifying()->withHeaders($headers)->get(
            $this->buildApiUrl($branch, 'sales-invoice/detail.do'),
            ['number' => $invoiceNumber]
        );

        if (!$siResponse->successful()) {
            Log::warning('PrintBarcodeRetur: sales-invoice/detail.do gagal', [
                'number' => $invoiceNumber,
                'status' => $siResponse->status(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail faktur dari Accurate.',
            ], $siResponse->status() >= 400 ? $siResponse->status() : 502);
        }

        $siBody = $siResponse->json();
        $siDetail = $siBody['d'] ?? null;
        if (!is_array($siDetail)) {
            return response()->json(['success' => false, 'message' => 'Respon faktur tidak valid.'], 400);
        }

        $doNumbers = [];
        foreach ($siDetail['detailItem'] ?? [] as $di) {
            if (!is_array($di)) {
                continue;
            }
            $doNum = data_get($di, 'deliveryOrder.number');
            if (is_string($doNum) && trim($doNum) !== '') {
                $doNumbers[trim($doNum)] = true;
            }
        }

        $uniqueDos = array_keys($doNumbers);

        if ($uniqueDos === []) {
            return response()->json([
                'success' => false,
                'message' => 'Faktur tidak memiliki nomor Delivery Order terkait.',
            ], 422);
        }

        if (count($uniqueDos) > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Faktur memiliki lebih dari satu nomor DO (' . implode(', ', $uniqueDos) . '). Pilih faktur dengan satu DO saja.',
            ], 422);
        }

        $doNumber = $uniqueDos[0];

        $doResponse = Http::timeout(60)->withoutVerifying()->withHeaders($headers)->get(
            $this->buildApiUrl($branch, 'delivery-order/detail.do'),
            ['number' => $doNumber]
        );

        if (!$doResponse->successful()) {
            Log::warning('PrintBarcodeRetur: delivery-order/detail.do gagal', [
                'number' => $doNumber,
                'status' => $doResponse->status(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail Delivery Order dari Accurate.',
            ], $doResponse->status() >= 400 ? $doResponse->status() : 502);
        }

        $doDetail = $doResponse->json()['d'] ?? null;
        if (!is_array($doDetail)) {
            return response()->json(['success' => false, 'message' => 'Respon Delivery Order tidak valid.'], 400);
        }

        $rows = $this->buildTableRowsFromDoDetailItems($doDetail['detailItem'] ?? []);

        return response()->json([
            'success' => true,
            'no_do' => $doNumber,
            'rows' => $rows,
        ]);
    }
}
