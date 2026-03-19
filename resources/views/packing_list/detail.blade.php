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
                        @if ($data && $data->npl != null && $barcodes && $barcodes->first())
                            <div class="text-center">
                                <h4><strong><u>PACKING LIST</u></strong></h4>
                                <p class="mb-1">
                                    <strong>No. : {{ $data->npl }} / Tgl. :
                                        {{ $barcodes->isNotEmpty() ? \Carbon\Carbon::parse($barcodes->first()->tanggal)->format('d-m-Y') : \Carbon\Carbon::parse($data->tanggal)->format('d-m-Y') }}</strong>
                                    @php
                                        $statusClass = match($data->status ?? 'pending') {
                                            'approved' => 'badge-success',
                                            'used' => 'badge-info',
                                            default => 'badge-warning',
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }} ml-2">{{ strtoupper($data->status ?? 'pending') }}</span>
                                </p>
                            </div>
                            <br>
                            <table class="table table-bordered text-nowrap" style="table-layout: fixed; width: 100%;">
                                <tr>
                                    <td style="width: 40%; text-align: left;">
                                        <strong>Pemasok : {{ $barcodes->first()->pemasok }}</strong>
                                    </td>
                                    <td style="width: 33.33%; text-align: center;">
                                        <strong>Pembeli : {{ $barcodes->first()->customer }}</strong>
                                    </td>
                                    <td style="width: 33.33%; text-align: right;">
                                        <strong>Mobil / No. Polisi : {{ $barcodes->first()->no_vehicle }}</strong>
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
                    <table id="packing_list_detail" class="table table-bordered table-head-fixed text-nowrap">
                        <thead>
                            <tr>
                                <th>Keterangan</th>
                                <th>Nomor Seri</th>
                                <th>Pcs</th>
                                <th>Berat (KG)</th>
                                <th>Panjang (MLC)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($barcodes as $barcode)
                                <tr>
                                    <td>{{ $barcode->salestext ?? '-' }}</td>
                                    <td>{{ $barcode->batch_no ?? ($barcode->nomor_seri ?? '-') }}</td>
                                    <td>{{ $barcode->pcs ?? '-' }}</td>
                                    <td>{{ isset($barcode->weight) ? number_format((float) $barcode->weight, 2) : (isset($barcode->berat_kg) ? number_format((float) $barcode->berat_kg, 2) : '-') }}</td>
                                    <td>{{ isset($barcode->length) ? number_format((float) $barcode->length, 2) : (isset($barcode->panjang_mlc) ? number_format((float) $barcode->panjang_mlc, 2) : '-') }}</td>
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
