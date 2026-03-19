@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Detail Penerimaan Barang</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('penerimaan-barang.index') }}">Penerimaan Barang</a></li>
                        <li class="breadcrumb-item active">Detail 1</li>
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
                            <div class="text-start">
                                <h3 class="card-title mb-0"><b>No. Penerimaan Barang: {{ $penerimaanBarang->npb }}</b></h3>
                            </div>
                        </div>

                        <div class="card-body">
                            @if(isset($errorMessage) && $errorMessage)
                            <div class="alert alert-danger">
                                <h5><i class="icon fas fa-ban"></i> Gagal Memuat Data!</h5>
                                {{ $errorMessage }}
                            </div>
                            @endif
                            <table id="penerimaan_barang_detail" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th>Kode #</th>
                                        <th>Kuantitas</th>
                                        <th>Satuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    // Sort data by nama_barang ascending (A-Z)
                                    $sortedItems = collect($matchedItems)->sortBy('nama_barang')->values()->all();
                                    @endphp
                                    @foreach ($sortedItems as $pb)
                                    <tr>
                                        <td><a
                                                href="{{ route('penerimaan-barang.showApproval', ['npb' => $penerimaanBarang->npb, 'kodeBarang' => $pb['kode_barang']]) }}">{{ $pb['nama_barang'] ?? '-' }}</a></td>
                                        <td>{{ $pb['kode_barang'] ?? '-' }}</td>
                                        <td>{{ number_format($pb['panjang_total'], 2) }}</td>
                                        <td>
                                            {{ $pb['unit'] ?? (($pb['uom_acc'] ?? '') === 'YD' ? 'YD' : 'METER') }}
                                        </td>
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

    updateTitle('Detail Penerimaan Barang');
</script>
@endsection