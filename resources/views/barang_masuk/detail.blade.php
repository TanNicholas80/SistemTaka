@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Barang Masuk Detail</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('barang-masuk.index') }}">Barang Masuk</a></li>
                            <li class="breadcrumb-item active"><a>Detail</a></li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <section class="content">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="text-start">
                        <h3 class="card-title mb-0"><b>No. Barang: {{ $data->nbrg }}</b></h3>
                    </div>
                    <div class="text-end" style="margin-left: auto;">
                        <h3 class="card-title mb-0"><b>Tanggal Masuk: {{ \Carbon\Carbon::parse($data->tanggal)->format('d-m-Y') }}</b></h3>
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
                                    <td>{{ $barcode->keterangan }}</td>
                                    <td>{{ $barcode->nomor_seri }}</td>
                                    <td>{{ $barcode->pcs }}</td>
                                    <td>{{ $barcode->berat_kg }}</td>
                                    <td>{{ $barcode->panjang_mlc }}</td>
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
        updateTitle('Barang Masuk Detail');
    </script>
@endsection
