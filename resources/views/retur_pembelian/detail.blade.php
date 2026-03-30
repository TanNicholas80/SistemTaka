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
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Detail Retur Pembelian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('retur_pembelian.index') }}">Retur Pembelian</a></li>
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
                            <h3 class="card-title">Detail Retur Pembelian</h3>
                            @if(isset($returPembelian) && $returPembelian)
                            <div class="card-tools">
                                <a href="{{ route('retur_pembelian.detail', ['no_retur' => $returPembelian->no_retur, 'force_refresh' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-sync-alt mr-1"></i>Refresh Data
                                </a>
                            </div>
                            @endif
                        </div>
                        <div class="card-body">
                            @if(isset($errorMessage) && $errorMessage)
                            <div class="alert alert-danger">
                                <h5><i class="icon fas fa-ban"></i> Gagal Memuat Data!</h5>
                                {{ $errorMessage }}
                            </div>
                            @endif

                            @if(isset($returPembelian) && $returPembelian)
                            <h5>Informasi Retur Pembelian</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Nomor Retur</th>
                                            <td>{{ $returPembelian->no_retur ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Retur</th>
                                            <td>{{ $returPembelian->tanggal_retur ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Vendor</th>
                                            <td>{{ $accurateDetail['vendor']['name'] ?? $returPembelian->vendor ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tipe Retur</th>
                                            <td>
                                                @php
                                                    $returnTypeLabels = [
                                                        'receive' => 'Penerimaan Barang',
                                                        'invoice' => 'Faktur',
                                                        'invoice_dp' => 'Uang Muka',
                                                        'no_invoice' => 'Tanpa Faktur',
                                                    ];
                                                @endphp
                                                {{ $returnTypeLabels[$returPembelian->return_type] ?? $returPembelian->return_type }}
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Status di Accurate</th>
                                            <td>{{ $accurateDetail['statusName'] ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Total Amount</th>
                                            <td>Rp {{ number_format($accurateDetail['totalAmount'] ?? 0, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Referensi</th>
                                            <td>
                                                @if(isset($referenceType) && $referenceType === 'receive' && isset($returPembelian->penerimaan_barang_id))
                                                    {{ $returPembelian->penerimaan_barang_id }}
                                                @elseif(isset($referenceType) && $referenceType === 'invoice' && isset($returPembelian->faktur_pembelian_id))
                                                    {{ $returPembelian->faktur_pembelian_id }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                        @if(isset($returPembelian->syarat_bayar) && $returPembelian->syarat_bayar)
                                        <tr>
                                            <th>Syarat Bayar</th>
                                            <td>{{ $returPembelian->syarat_bayar }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            @if(isset($accurateDetail['toAddress']) && $accurateDetail['toAddress'])
                            <div class="row mt-2">
                                <div class="col-12">
                                    <table class="table table-bordered table-sm">
                                        <tr>
                                            <th style="width: 120px;">Alamat</th>
                                            <td>{{ $accurateDetail['toAddress'] }}</td>
                                        </tr>
                                        @if(isset($accurateDetail['description']) && $accurateDetail['description'])
                                        <tr>
                                            <th>Keterangan</th>
                                            <td>{{ $accurateDetail['description'] }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                            @endif
                            @endif

                            @if(isset($referenceType) && $referenceType && isset($accurateReferenceDetail))
                            <div class="card-header mt-3">
                                <h3 class="card-title">Detail Referensi ({{ $referenceType === 'receive' ? 'Penerimaan Barang' : 'Faktur Pembelian' }})</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th>Nomor</th>
                                                <td>{{ $accurateReferenceDetail['number'] ?? ($referenceType === 'receive' ? ($penerimaanBarang->npb ?? $penerimaanBarang->no_terima ?? 'N/A') : ($returPembelian->faktur_pembelian_id ?? 'N/A')) }}</td>
                                            </tr>
                                            <tr>
                                                <th>Tanggal</th>
                                                <td>{{ $accurateReferenceDetail['transDate'] ?? ($referenceType === 'receive' ? ($penerimaanBarang->tanggal ?? 'N/A') : 'N/A') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Alamat</th>
                                                <td>{{ $accurateReferenceDetail['toAddress'] ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th>Status</th>
                                                <td>{{ $accurateReferenceDetail['statusName'] ?? 'N/A' }}</td>
                                            </tr>
                                            @if($referenceType === 'invoice')
                                            <tr>
                                                <th>Total Amount</th>
                                                <td>Rp {{ number_format($accurateReferenceDetail['totalAmount'] ?? 0, 0, ',', '.') }}</td>
                                            </tr>
                                            @endif
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="card-header mt-3">
                                <h3 class="card-title">Detail Item Retur</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Nama Barang</th>
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
                                                @foreach($accurateDetailItems as $item)
                                                <tr>
                                                    <td>{{ $item['item']['name'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['item']['no'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['quantity'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['itemUnit']['name'] ?? 'N/A' }}</td>
                                                    <td>Rp {{ number_format($item['unitPrice'] ?? 0, 0, ',', '.') }}</td>
                                                    <td>Rp {{ number_format($item['itemCashDiscount'] ?? $item['lastItemCashDiscount'] ?? 0, 0, ',', '.') }}</td>
                                                    <td>Rp {{ number_format($item['totalPrice'] ?? 0, 0, ',', '.') }}</td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">
                                                        <i class="fas fa-info-circle mr-2"></i>Tidak ada data item
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>

                                @if(isset($accurateDetail) && (isset($accurateDetail['totalAmount']) || isset($accurateDetail['cashDiscount'])))
                                <div class="row mt-3">
                                    <div class="col-md-6 ml-auto">
                                        <div class="total-section">
                                            @if(isset($accurateDetail['cashDiscount']) && $accurateDetail['cashDiscount'] > 0)
                                            <div class="total-row">
                                                <span class="total-label">Diskon</span>
                                                <span class="total-value">Rp {{ number_format($accurateDetail['cashDiscount'], 0, ',', '.') }}</span>
                                            </div>
                                            @endif
                                            <div class="total-row">
                                                <span class="total-label">Total</span>
                                                <span class="total-value">Rp {{ number_format($accurateDetail['totalAmount'] ?? 0, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('retur_pembelian.index') }}" class="btn btn-primary">
                                <i class="fas fa-arrow-left mr-1"></i>Kembali ke Daftar Retur Pembelian
                            </a>
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
    updateTitle('Detail Retur Pembelian');
</script>
@endsection
