@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Packing List Detail</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('packing-list.index') }}">Packing List</a></li>
                            <li class="breadcrumb-item active"><a>Detail</a></li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <section class="content">
            <div class="card">
                <div class="card-header justify-content-between align-items-center">
                    <div>
                        @if ($data && $data->npl != null && isset($barcodeRows) && $barcodeRows->isNotEmpty())
                                        @php
                                            $firstRow = $barcodeRows->first()['barcode'] ?? null;
                                        @endphp
                                        <div class="text-center">
                                            <h4><strong><u>PACKING LIST</u></strong></h4>
                                            <p class="mb-1">
                                                <strong>No. : {{ $data->npl }} / Tgl. :
                                                    {{ $firstRow && $firstRow->tanggal ? \Carbon\Carbon::parse($firstRow->tanggal)->format('d-m-Y') : \Carbon\Carbon::parse($data->tanggal)->format('d-m-Y') }}</strong>
                                                @php
                                                    $statusClass = match ($data->status ?? 'pending') {
                                                        'approved' => 'badge-success',
                                                        'used' => 'badge-info',
                                                        default => 'badge-warning',
                                                    };
                                                @endphp
                            <span
                                                    class="badge {{ $statusClass }} ml-2">{{ strtoupper($data->status ?? 'pending') }}</span>
                                            </p>
                                        </div>
                                        <br>
                                        <table class="table table-bordered text-nowrap" style="table-layout: fixed; width: 100%;">
                                            <tr>
                                                <td style="width: 40%; text-align: left;">
                                                    <strong>Pemasok : {{ $firstRow->pemasok ?? '-' }}</strong>
                                                </td>
                                                <td style="width: 33.33%; text-align: center;">
                                                    <strong>Pembeli : {{ $firstRow->customer ?? '-' }}</strong>
                                                </td>
                                                <td style="width: 33.33%; text-align: right;">
                                                    <strong>Mobil / No. Polisi : {{ $firstRow->no_vehicle ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                        </table>
                        @else
                            <div class="text-center">
                                <h4><strong><u>PACKING LIST</u></strong></h4>
                                <p class="mb-1 text-danger">
                                    <strong>Data tidak tersedia</strong>
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- /.card-header -->
                <div class="card-body">
                    {{-- table-head-fixed bentrok dengan DataTables scrollX (header vs body tidak sejajar) --}}
                    <style>
                        #packing_list_detail_wrapper {
                            width: 100%;
                        }

                        #packing_list_detail_wrapper .dataTables_scrollHeadInner,
                        #packing_list_detail_wrapper .dataTables_scrollHeadInner>.dataTable,
                        #packing_list_detail_wrapper .dataTables_scrollBody table.dataTable {
                            width: auto !important;
                            min-width: 100% !important;
                        }

                        #packing_list_detail_wrapper .dataTables_scrollHead {
                            position: relative;
                            z-index: 2;
                        }

                        #packing_list_detail_wrapper .dataTables_scrollBody {
                            position: relative;
                            z-index: 1;
                        }

                        #packing_list_detail_wrapper th,
                        #packing_list_detail_wrapper td {
                            vertical-align: middle;
                        }
                    </style>
                    <table id="packing_list_detail" class="table table-bordered table-striped text-nowrap">
                        <thead>
                            <tr>
                                <th>Keterangan</th>
                                <th>Kode Warna</th>
                                <th>Partai</th>
                                <th>Nomor Seri</th>
                                <th class="text-right">Pcs</th>
                                <th class="text-right">Berat (KG)</th>
                                <th class="text-right">Panjang (MLC)</th>
                                <th class="text-right">Panjang (Yard)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($barcodeRows as $row)
                                @php
                                    $barcode = $row['barcode'];
                                    $d = $row['display'] ?? [];
                                @endphp
                                <tr>
                                    <td>{{ $barcode->salestext ?? '-' }}</td>
                                    <td>{{ $barcode->kode_warna ?? '-' }}</td>
                                    <td>{{ $barcode->batch_no ?? ($barcode->nomor_seri ?? '-') }}</td>
                                    <td>{{ $barcode->job_order ?? '-' }}</td>
                                    <td class="text-right">{{ $barcode->pcs ?? '-' }}</td>
                                    <td class="text-right">{{ $d['berat'] ?? '-' }}</td>
                                    <td class="text-right">{{ $d['panjang_mlc'] ?? '-' }}</td>
                                    <td class="text-right">{{ $d['panjang_yard'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </section>

    </div>

    <script>
        // Fungsi untuk mengubah judul berdasarkan halaman
        function updateTitle(pageTitle) {
            document.title = pageTitle;
        }

        // Panggil fungsi ini saat halaman "packing_list_detail" dimuat
        updateTitle('Packing List Detail');
    </script>
@endsection