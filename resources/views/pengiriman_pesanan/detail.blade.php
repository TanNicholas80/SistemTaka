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

    .total-section {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 20px;
        margin-top: 20px;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #dee2e6;
    }

    .total-row:last-child {
        border-bottom: none;
        font-weight: bold;
        font-size: 1.1em;
        color: #007bff;
    }

    .total-label {
        font-weight: 500;
        color: #495057;
    }

    .total-value {
        font-weight: 600;
        color: #212529;
    }

    .discount-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .discount-percentage {
        background-color: #007bff;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Detail Pengiriman Pesanan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('pengiriman_pesanan.index') }}">Pengiriman Pesanan</a></li>
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
                            <h3 class="card-title">Detail Pengiriman Pesanan</h3>
                        </div>

                        <div class="card-body">
                            @if(isset($errorMessage) && $errorMessage)
                            <div class="alert alert-danger">
                                <h5><i class="icon fas fa-ban"></i> Gagal Memuat Data!</h5>
                                {{ $errorMessage }}
                            </div>
                            @endif
                            <h5>Informasi Pengiriman Pesanan</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Nomor</th>
                                            <td>{{ $pengirimanPesanan->no_pengiriman ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal</th>
                                            <td>{{ $pengirimanPesanan->tanggal_pengiriman ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Alamat Pengiriman</th>
                                            <td>{{ $accurateDeliveryOrderDetail['toAddress'] ?? 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Status Pengiriman</th>
                                            <td>{{ $accurateDeliveryOrderDetail['statusName'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>ID Penjualan</th>
                                            <td>{{ $pengirimanPesanan->penjualan_id ?? 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card-header">
                            <h3 class="card-title">Detail Penjualan</h3>
                        </div>
                        <div class="card-body">
                            <h5>Informasi Penjualan</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Nomor</th>
                                            <td>{{ $kasirPenjualan->npj ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal</th>
                                            <td>{{ $kasirPenjualan->tanggal ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Syarat Pembayaran</th>
                                            <td>{{ $accurateSalesOrderDetail['paymentTerm']['name'] ?? 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Status Penjualan</th>
                                            <td>{{ $accurateSalesOrderDetail['statusName'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Info Pajak</th>
                                            <td>
                                                <div class="form-check-container" style="display: flex; gap: 20px;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="kenaPajak"
                                                            {{ ($accurateSalesOrderDetail['taxable'] ?? false) ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label" for="kenaPajak">
                                                            Kena Pajak
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="totalTermasukPajak"
                                                            {{ ($accurateSalesOrderDetail['inclusiveTax'] ?? false) ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label" for="totalTermasukPajak">
                                                            Total termasuk Pajak
                                                        </label>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <h5>Detail Item (Merged by Name)</h5>

                            <!-- Tab Navigation -->
                            <ul class="nav nav-tabs" id="itemTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="merged-tab" data-toggle="tab" data-target="#merged" type="button" role="tab" aria-controls="merged" aria-selected="true">
                                        <i class="fas fa-layer-group mr-1"></i>Summary (Merged)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="detail-tab" data-toggle="tab" data-target="#detail" type="button" role="tab" aria-controls="detail" aria-selected="false">
                                        <i class="fas fa-list-ul mr-1"></i>Detail per Barcode
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="accurate-tab" data-toggle="tab" data-target="#accurate" type="button" role="tab" aria-controls="accurate" aria-selected="false">
                                        <i class="fas fa-cloud mr-1"></i>Data Accurate
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content" id="itemTabsContent">
                                <!-- Merged Items Tab -->
                                <div class="tab-pane fade show active" id="merged" role="tabpanel" aria-labelledby="merged-tab">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped table-hover">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th><i class="fas fa-tag mr-1"></i>Nama Item</th>
                                                    <th><i class="fas fa-sort-numeric-up mr-1"></i>Total Kuantitas</th>
                                                    <th><i class="fas fa-money-bill-wave mr-1"></i>@Harga</th>
                                                    <th><i class="fas fa-calculator mr-1"></i>Subtotal</th>
                                                    <th><i class="fas fa-percentage mr-1"></i>Diskon</th>
                                                    <th><i class="fas fa-coins mr-1"></i>Total Final</th>
                                                    <th><i class="fas fa-barcode mr-1"></i>Barcodes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if(isset($mergedItems) && count($mergedItems) > 0)
                                                @foreach ($mergedItems as $item)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $item['nama'] }}</strong>
                                                        @if($item['approval_stock'])
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-info-circle mr-1"></i>
                                                            {{ $item['approval_stock']['warna'] ?? '' }}
                                                            {{ $item['approval_stock']['motif'] ?? '' }}
                                                        </small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-primary badge-lg">
                                                            {{ number_format($item['total_qty'], 2) }}
                                                        </span>
                                                        @if($item['approval_stock'])
                                                        <br><small class="text-muted">Meter</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <strong>Rp. {{ number_format($item['harga_satuan'], 0, ',', '.') }}</strong>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong>Rp. {{ number_format($item['subtotal_sebelum_diskon'], 0, ',', '.') }}</strong>
                                                            @if($item['total_nominal_diskon'] > 0)
                                                            <br><small class="text-danger">
                                                                <i class="fas fa-minus-circle mr-1"></i>
                                                                Diskon: Rp. {{ number_format($item['total_nominal_diskon'], 0, ',', '.') }}
                                                            </small>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($item['persentase_diskon_efektif'] > 0)
                                                        <div class="text-center">
                                                            <span class="badge badge-warning badge-lg">
                                                                {{ number_format($item['persentase_diskon_efektif'], 1) }}%
                                                            </span>
                                                            <br><small class="text-muted">
                                                                @if($item['diskon'] <= 100)
                                                                    Original: {{ number_format($item['diskon'], 0) }}%
                                                                    @else
                                                                    Nominal: Rp. {{ number_format($item['diskon'], 0, ',', '.') }}
                                                                    @endif
                                                                    </small>
                                                        </div>
                                                        @else
                                                        <span class="text-muted text-center d-block">No Discount</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="text-center">
                                                            <strong class="text-success d-block" style="font-size: 1.1em;">
                                                                Rp. {{ number_format($item['total_harga'], 0, ',', '.') }}
                                                            </strong>
                                                            @if($item['total_nominal_diskon'] > 0)
                                                            <small class="text-muted">
                                                                Hemat: Rp. {{ number_format($item['total_nominal_diskon'], 0, ',', '.') }}
                                                            </small>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @foreach($item['barcodes'] as $barcode)
                                                        <span class="badge badge-secondary mb-1">{{ $barcode }}</span>
                                                        @if(!$loop->last)<br>@endif
                                                        @endforeach
                                                    </td>
                                                </tr>
                                                @endforeach
                                                @else
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">
                                                        <i class="fas fa-info-circle mr-2"></i>Tidak ada data item yang tersedia
                                                    </td>
                                                </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Detail per Barcode Tab -->
                                <div class="tab-pane fade" id="detail" role="tabpanel" aria-labelledby="detail-tab">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped table-sm">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Barcode</th>
                                                    <th>Nama Item</th>
                                                    <th>Qty</th>
                                                    <th>@Harga</th>
                                                    <th>Diskon</th>
                                                    <th>Total Harga</th>
                                                    <th>Info Detail</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if(isset($detailBarcodeMappings) && count($detailBarcodeMappings) > 0)
                                                @foreach ($detailBarcodeMappings as $detail)
                                                <tr>
                                                    <td>
                                                        <span class="badge badge-dark">{{ $detail['barcode'] }}</span>
                                                    </td>
                                                    <td>{{ $detail['nama'] }}</td>
                                                    <td>{{ number_format($detail['qty'], 2) }}</td>
                                                    <td>Rp. {{ number_format($detail['harga'], 0, ',', '.') }}</td>
                                                    <td>
                                                        @if($detail['diskon'] && $detail['diskon'] > 0)
                                                        @if($detail['diskon'] <= 100)
                                                            <span class="badge badge-warning">{{ number_format($detail['diskon'], 0) }}%</span>
                                                            @else
                                                            <span class="badge badge-info">Rp. {{ number_format($detail['diskon'], 0, ',', '.') }}</span>
                                                            @endif
                                                            @if($detail['nominal_diskon'] > 0)
                                                            <br><small class="text-danger">
                                                                -Rp. {{ number_format($detail['nominal_diskon'], 0, ',', '.') }}
                                                            </small>
                                                            @endif
                                                            @else
                                                            <span class="text-muted">-</span>
                                                            @endif
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong class="text-success">Rp. {{ number_format($detail['total_harga'], 0, ',', '.') }}</strong>
                                                            @if($detail['nominal_diskon'] > 0)
                                                            <br><small class="text-muted">
                                                                <del>Rp. {{ number_format($detail['subtotal_sebelum_diskon'], 0, ',', '.') }}</del>
                                                            </small>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($detail['approval_stock'])
                                                        <small class="text-muted">
                                                            @if($detail['approval_stock']['warna'])
                                                            <span class="badge badge-info">{{ $detail['approval_stock']['warna'] }}</span>
                                                            @endif
                                                            @if($detail['approval_stock']['motif'])
                                                            <span class="badge badge-success">{{ $detail['approval_stock']['motif'] }}</span>
                                                            @endif
                                                            @if($detail['approval_stock']['grade'])
                                                            <br><span class="badge badge-warning">Grade: {{ $detail['approval_stock']['grade'] }}</span>
                                                            @endif
                                                        </small>
                                                        @else
                                                        <small class="text-muted">No detail available</small>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                                @else
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">Tidak ada detail barcode tersedia</td>
                                                </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Accurate Data Tab -->
                                <div class="tab-pane fade" id="accurate" role="tabpanel" aria-labelledby="accurate-tab">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th>Nama Item</th>
                                                    <th>Kode #</th>
                                                    <th>Kuantitas</th>
                                                    <th>Satuan</th>
                                                    <th>@Harga</th>
                                                    <th>Diskon</th>
                                                    <th>Total Harga</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if(isset($accurateDetailItems) && count($accurateDetailItems) > 0)
                                                @foreach ($accurateDetailItems as $item)
                                                <tr>
                                                    <td>{{ $item['item']['name'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['item']['no'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['quantity'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['itemUnit']['name'] ?? 'N/A' }}</td>
                                                    <td>Rp. {{ number_format($item['unitPrice'] ?? 0, 0, ',', '.') }}</td>
                                                    <td>Rp. {{ number_format($item['lastItemCashDiscount'] ?? 0, 0, ',', '.') }}</td>
                                                    <td>Rp. {{ number_format($item['totalPrice'] ?? 0, 0, ',', '.') }}</td>
                                                </tr>
                                                @endforeach
                                                @else
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">Tidak ada data dari Accurate API</td>
                                                </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Total Section -->
                                <div class="row">
                                    <div class="col-md-6 ml-auto">
                                        <div class="total-section">
                                            <div class="total-row">
                                                <span class="total-label">
                                                    <div class="discount-info">
                                                        <span>Diskon</span>
                                                        @if(isset($accurateSalesOrderDetail['cashDiscPercent']) && $accurateSalesOrderDetail['cashDiscPercent'] > 0)
                                                        <span class="discount-percentage">{{ $accurateSalesOrderDetail['cashDiscPercent'] }}%</span>
                                                        @endif
                                                    </div>
                                                </span>
                                                <span class="total-value">Rp. {{ number_format($accurateSalesOrderDetail['cashDiscount'] ?? 0, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="total-row">
                                                <span class="total-label">Total</span>
                                                <span class="total-value">Rp. {{ number_format($accurateSalesOrderDetail['totalAmount'] ?? 0, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('pengiriman_pesanan.index') }}" class="btn btn-primary">
                                <i class="fas fa-arrow-left mr-1"></i>Kembali ke Daftar Pengiriman Pesanan
                            </a>
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

    updateTitle('Detail Pengiriman Pesanan');
</script>
@endsection
