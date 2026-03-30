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
                        <li class="breadcrumb-item"><a href="{{ route('hasil_stock_opname.index') }}">Hasil Stock Opname</a></li>
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
                                <h5><i class="icon fas fa-ban"></i> Gagal Memuat Data Awal!</h5>
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

                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>Nama Item</th>
                                            <th>Panjang (MLC)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($approvals as $index => $approval)
                                        <tr>
                                            <td>
                                                <span class="badge badge-primary">{{ $approval->barcode ?? 'N/A' }}</span>
                                            </td>
                                            <td>{{ $approval->nama ?? 'N/A' }}</td>
                                            <td>
                                                @if($approval->panjang)
                                                <strong>{{ number_format($approval->panjang, 2) }}</strong>
                                                @else
                                                <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Tidak ada data approval yang ditemukan dengan barcode yang matching
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer text-right">
                            <a href="{{ url('/hasil-stock-opname/detail/' . $detail['number']) }}" class="btn btn-primary">Kembali</a>
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

    updateTitle('Rincian Barang Approval');
</script>
@endsection
