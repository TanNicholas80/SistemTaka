@extends('layout.main')

@section('content')
<style>
    .table th {
        background-color: #f8f9fa;
    }

    .table td {
        vertical-align: middle;
    }

    .btn-primary {
        background-color: #007bff;
        border-color: #007bff;
    }

    .btn-primary:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Detail Hasil Stock Opname</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('hasil_stock_opname.index') }}">Hasil Stock
                                Opname</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Detail Data Hasil Stock Opname</h3>
                        </div>
                        <div class="card-body">
                            @if(isset($errorMessage) && $errorMessage)
                            <div class="alert alert-danger">
                                <h5><i class="icon fas fa-ban"></i> Gagal Memuat Data!</h5>
                                {{ $errorMessage }}
                            </div>
                            @endif
                            <h5>Informasi Umum</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Tanggal Opname</th>
                                            <td>{{ $detail['transDateView'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Perintah Opname</th>
                                            <td><a
                                                    href="{{ url('/perintah-stock-opname/detail/' . $detail['order']['number']) }}">{{ $detail['order']['number'] ?? 'N/A' }}</a>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>No. Opname</th>
                                            <td>{{ $detail['number'] ?? 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <h5>Detail Item</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nama Item</th>
                                            <th>Kuantitas (MLC)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detail['detailItem'] as $item)
                                        <tr>
                                            <td>
                                                <a href="{{ url('/hasil-stock-opname/detail/' . $detail['number'] . '/' . urlencode($item['item']['name'])) }}">
                                                    {{ $item['item']['name'] }}
                                                </a>
                                            </td>
                                            <td>{{ $item['quantity'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer text-right">
                            <a href="{{ route('hasil_stock_opname.index') }}" class="btn btn-primary">Kembali</a>
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

    updateTitle('Detail Hasil Stock Opname');
</script>
@endsection
