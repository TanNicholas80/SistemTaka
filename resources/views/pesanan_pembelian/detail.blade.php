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
                    <h1 class="m-0">Detail Pesanan Pembelian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('pesanan_pembelian.index') }}">Pesanan Pembelian</a></li>
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
                            <h3 class="card-title">Detail Data Pesanan Pembelian</h3>
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
                                            <th>Nomor</th>
                                            <td>{{ $detail['number'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Pemasok</th>
                                            <td>{{ $detail['vendor']['name'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Pesanan</th>
                                            <td>{{ $detail['transDate'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Pengiriman</th>
                                            <td>{{ $detail['shipDate'] ?? 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Status</th>
                                            <td>{{ $detail['statusName'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Syarat Pembayaran</th>
                                            <td>{{ $detail['paymentTerm']['name'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Alamat Kirim</th>
                                            <td>{{ $detail['toAddress'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Informasi Pajak</th>
                                            <td>
                                                <div class="form-check-container" style="display: flex; gap: 20px;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="kenaPajak"
                                                            {{ ($detail['taxable'] ?? false) ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label" for="kenaPajak">
                                                            Kena Pajak
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="totalTermasukPajak"
                                                            {{ ($detail['inclusiveTax'] ?? false) ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label" for="totalTermasukPajak">
                                                            Total termasuk Pajak
                                                        </label>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-12">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Deskripsi</th>
                                            <td>{{ $detail['description'] ?? 'N/A' }}</td>
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
                                            <th>Kode Item</th>
                                            <th>Kuantitas</th>
                                            <th>Satuan</th>
                                            <th>@Harga</th>
                                            <th>Diskon</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detail['detailItem'] as $item)
                                        <tr>
                                            <td>{{ $item['item']['name'] }}</td>
                                            <td>{{ $item['item']['no'] }}</td>
                                            <td>
                                                {{ rtrim(rtrim(number_format((float)($item['quantity'] ?? 0), 3, ',', '.'), '0'), ',') }}
                                            </td>
                                            <td>{{ $item['itemUnit']['name'] }}</td>
                                            <td>{{ 'Rp. ' . number_format((float)($item['unitPrice'] ?? 0), 0, ',', '.') }}</td>
                                            <td>{{ 'Rp. ' . number_format((float)($item['itemCashDiscount'] ?? 0), 0, ',', '.') }}</td>
                                            <td>{{ 'Rp. ' . number_format((float)($item['totalPrice'] ?? 0), 0, ',', '.') }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('pesanan_pembelian.index') }}" class="btn btn-primary">Kembali</a>
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

    updateTitle('Detail Pesanan Pembelian');
</script>
@endsection
