@extends('layout.main')

@section('content')
<style>
    .table th { background-color: #f8f9fa; }
    .table td { vertical-align: middle; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Detail Pesanan Penjualan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('cashier.index') }}">Pesanan Penjualan</a></li>
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
                            <h3 class="card-title">Detail Data Pesanan Penjualan</h3>
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
                                            <td>{{ $accurateDetail['number'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Pelanggan</th>
                                            <td>{{ $accurateDetail['customer']['name'] ?? ($accurateDetail['customer']['contactInfo']['name'] ?? 'N/A') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Pesanan</th>
                                            <td>{{ $accurateDetail['transDate'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Pengiriman</th>
                                            <td>{{ $accurateDetail['shipDate'] ?? 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Status</th>
                                            <td>{{ $accurateDetail['statusName'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Syarat Pembayaran</th>
                                            <td>{{ $accurateDetail['paymentTerm']['name'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Alamat Kirim</th>
                                            <td>{{ $accurateDetail['toAddress'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Informasi Pajak</th>
                                            <td>
                                                <div style="display: flex; gap: 20px;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            {{ ($accurateDetail['taxable'] ?? false) ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label">Kena Pajak</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            {{ ($accurateDetail['inclusiveTax'] ?? false) ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label">Total termasuk Pajak</label>
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
                                            <td>{{ $accurateDetail['description'] ?? 'N/A' }}</td>
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
                                        @forelse ($accurateDetailItems as $item)
                                        <tr>
                                            <td>{{ $item['item']['name'] ?? '-' }}</td>
                                            <td>{{ $item['item']['no'] ?? '-' }}</td>
                                            <td>{{ $item['quantity'] ?? 0 }}</td>
                                            <td>{{ $item['itemUnit']['name'] ?? '-' }}</td>
                                            <td>Rp. {{ number_format((float)($item['unitPrice'] ?? 0), 0, ',', '.') }}</td>
                                            <td>Rp. {{ number_format((float)($item['itemCashDiscount'] ?? 0), 0, ',', '.') }}</td>
                                            <td>Rp. {{ number_format((float)($item['totalPrice'] ?? 0), 0, ',', '.') }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Tidak ada detail item</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('cashier.index') }}" class="btn btn-primary">Kembali</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    updateTitle('Detail Pesanan Penjualan');
</script>
@endsection
