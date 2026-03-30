@extends('layout.main')

@section('content')
<style>
    .status-badge {
        display: inline-block;
        padding: 0.25em 0.75em;
        font-weight: 700;
        border-radius: 12px;
        font-size: 0.875rem;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        user-select: none;
    }

    .status-approved {
        background-color: #28a745;
    }

    .status-draft {
        background-color: #ffc107;
        /* Bootstrap yellow */
        color: #212529;
    }

    /* Optionally add a default style */
    .status-default {
        background-color: #6c757d;
    }
</style>
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Data Detail Barang Approval</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('penerimaan-barang.index') }}">Penerimaan Barang</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('penerimaan-barang.show', $penerimaanBarang->npb) }}">Detail 1</a></li>
                        <li class="breadcrumb-item active">Detail 2</li>
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
                                <h3 class="card-title mb-0"><b>Detail Barang: {{ $approvalStock->first()->nama ?? '-' }}</b></h3>
                            </div>
                        </div>

                        <div class="card-body">
                            @if(isset($errorMessage) && $errorMessage)
                            <div class="alert alert-danger">
                                <h5><i class="icon fas fa-ban"></i> Gagal Memuat Data Awal!</h5>
                                {{ $errorMessage }}
                            </div>
                            @endif
                            <table id="data_detail_barang_approval" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Barcode</th>
                                        <th>Nama</th>
                                        <th>No Packing List</th>
                                        <th>No Invoice</th>
                                        <th>Panjang</th>
                                        <th>Harga Unit</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($approvalStock as $as)
                                    <tr>
                                        <td>{{ $as->barcode ?? '-' }}</td>
                                        <td>{{ $as->nama ?? '-' }}</td>
                                        <td>{{ $as->npl ?? '-' }}</td>
                                        <td>{{ $as->no_invoice ?? '-' }}</td>
                                        <td>{{ $as->panjang ?? '-' }}</td>
                                        <td>{{ 'Rp. ' . number_format($as->harga_unit , 0, ',', '.') }}</td>
                                        <td>
                                            @php
                                            $statusClass = match ($as->status) {
                                            'approved' => 'status-approved',
                                            'draft' => 'status-draft',
                                            default => 'status-default',
                                            };
                                            @endphp
                                            <span class="status-badge {{ $statusClass }}">
                                                {{ strtoupper($as->status) }}
                                            </span>
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

    updateTitle('Data Detail Barang Approval');
</script>
@endsection
