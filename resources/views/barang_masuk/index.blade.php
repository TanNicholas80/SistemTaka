@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Barang Masuk</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item active">Barang Masuk</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">

                        <!-- Add Button -->
                        <div class="d-flex justify-content-end mb-3">
                            <a href="{{ route('barang-masuk.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add
                            </a>
                        </div>

                        <!-- Card Table -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Data Barang Masuk</h3>
                            </div>

                            <div class="card-body">
                                <table id="barang_masuk" class="table text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">Tanggal</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">No Barang</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">Keterangan</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">Warna</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">No Seri</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">PCS</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">Berat (KG)</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">Panjang (MLC)</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="dt-th-flex">
                                                    <span class="dt-th-title">Panjang (Yard)</span>
                                                    <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($barangMasuk as $item)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($item->tanggal)->format('d-m-Y') }}</td>
                                                <td>{{ $item->nbrg }}</td>
                                                @if ($item->barcode)
                                                    <td>{{ $item->barcode->keterangan }}</td>
                                                    <td>{{ $item->barcode->warna }}</td>
                                                    <td>{{ $item->barcode->nomor_seri }}</td>
                                                    <td>{{ $item->barcode->pcs }}</td>
                                                    <td>{{ $item->barcode->berat_kg }}</td>
                                                    <td>{{ $item->barcode->panjang_mlc }}</td>
                                                    <td>{{ number_format($item->barcode->panjang_mlc / 0.9, 2) }}
                                                @else
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        function updateTitle(pageTitle) {
            document.title = pageTitle;
        }

        updateTitle('Barang Masuk');
    </script>
@endsection
