@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Barcode</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active">Barcode</li>
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

                        <!-- Card Table -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Data Barcode</h3>
                                @if ($lastUpdated)
                                    <span class="badge badge-danger ml-auto"
                                        style="font-size: 0.95rem; min-width:220px; text-align:right; white-space:nowrap;">
                                        Terakhir update:
                                        {{ \Carbon\Carbon::parse($lastUpdated)->locale('id')->isoFormat('dddd, DD-MM-YYYY HH:mm') }}
                                    </span>
                                @endif
                            </div>

                            <div class="card-body">
                                <table id="barcode" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>No. Packing List</th>
                                            <th>No. Billing</th>
                                            <th>Kode Barang</th>
                                            <th>Status</th>
                                            <th>Item Flag</th>
                                            <th>Keterangan</th>
                                            <th>Nomor Seri</th>
                                            <th>Pcs</th>
                                            <th>Berat (KG)</th>
                                            <th>Length</th>
                                            <th>UOM</th>
                                            <th>Material Code</th>
                                            <th>Kode Warna</th>
                                            <th>Panjang (MLC)</th>
                                            <th>Warna</th>
                                            <th>Harga PPN</th>
                                            <th>Harga Jual</th>
                                            <th>Pemasok</th>
                                            <th>Customer</th>
                                            <th>Kontrak</th>
                                            <th>Tanggal</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Mobil / No. Polisi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($barcodes as $barcode)
                                            <tr>
                                                <td>{{ $barcode->barcode }}</td>
                                                <td>{{ $barcode->no_packing_list }}</td>
                                                <td>{{ $barcode->no_billing }}</td>
                                                <td>{{ $barcode->kode_barang }}</td>
                                                <td>
                                                    @php
                                                        $statusClass = match($barcode->status ?? 'temporary') {
                                                            'approved' => 'badge-success',
                                                            'uploaded' => 'badge-info',
                                                            default => 'badge-warning',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $statusClass }}">{{ strtoupper($barcode->status ?? 'temporary') }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $barcode->item_flag ?? 'pembelian')) }}</span>
                                                </td>
                                                <td>{{ $barcode->keterangan }}</td>
                                                <td>{{ $barcode->nomor_seri }}</td>
                                                <td>{{ $barcode->pcs }}</td>
                                                <td>{{ $barcode->berat_kg ? number_format($barcode->berat_kg, 2) : '-' }}</td>
                                                <td>{{ $barcode->length ? $barcode->length : '-' }}</td>
                                                <td>{{ $barcode->uom ? $barcode->uom : '-' }}</td>
                                                <td>{{ $barcode->material_code ? $barcode->material_code : '-' }}</td>
                                                <td>{{ $barcode->kode_warna ? $barcode->kode_warna : '-' }}</td>
                                                <td>{{ $barcode->panjang_mlc ? number_format($barcode->panjang_mlc, 2) : '-' }}</td>
                                                <td>{{ $barcode->warna }}</td>
                                                <td>{{ 'Rp. ' . number_format($barcode->harga_ppn, 0, ',', '.') }}</td>
                                                <td>{{ 'Rp. ' . number_format($barcode->harga_jual, 0, ',', '.') }}</td>
                                                <td>{{ $barcode->pemasok }}</td>
                                                <td>{{ $barcode->customer }}</td>
                                                <td>{{ $barcode->kontrak }}</td>
                                                <td>{{ \Carbon\Carbon::parse($barcode->tanggal)->format('d-m-Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($barcode->jatuh)->format('d-m-Y') }}</td>
                                                <td>{{ $barcode->no_vehicle }}</td>
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

        updateTitle('Barcode');
    </script>
@endsection
