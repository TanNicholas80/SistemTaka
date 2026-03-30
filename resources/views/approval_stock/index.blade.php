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
                        <h1 class="m-0">Approval Stock</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item active">Approval Stock</li>
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
                            <a href="{{ route('approval-stock.update') }}" class="btn btn-success">
                                <i class="fas fa-sync-alt"></i> Update
                            </a>
                        </div>

                        <!-- Card Table -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Data Approval Stock</h3>
                            </div>

                            <div class="card-body">
                                <table id="approval_stock" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>Nama</th>
                                            <th>No Packing List</th>
                                            <th>No Invoice</th>
                                            <th>Panjang (MLC)</th>
                                            <th>Harga Unit</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($approvalStocks as $approvalStock)
                                            <tr>
                                                <td>{{ $approvalStock->barcode }}</td>
                                                <td>{{ $approvalStock->nama ?? '-' }}</td>
                                                <td>{{ $approvalStock->npl ?? '-' }}</td>
                                                <td>{{ $approvalStock->no_invoice ?? '-' }}</td>
                                                <td>{{ $approvalStock->panjang ?? '-' }}</td>
                                                <td>{{ 'Rp. ' . number_format($approvalStock->harga_unit, 0, ',', '.') ?? '-'  }}</td>
                                                <td>
                                                    @php
                                                        $statusClass = match ($approvalStock->status) {
                                                            'approved' => 'status-approved',
                                                            'draft' => 'status-draft',
                                                            default => 'status-default',
                                                        };
                                                    @endphp
                                                    <span class="status-badge {{ $statusClass }}">
                                                        {{ strtoupper($approvalStock->status) }}
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
        updateTitle('Approval Stock');
    </script>
@endsection
