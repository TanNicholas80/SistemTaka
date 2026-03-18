@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Kasir</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active">Kasir</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>

        <!-- Main Content -->
        <section class="content">
            <div class="container-fluid">
                @if(isset($errorMessage) && $errorMessage)
                    <div id="auto-dismiss-alert" class="alert alert-warning alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                        {{ $errorMessage }}
                    </div>
                @endif
                <div class="row">
                    <div class="col-12">

                        <!-- Card Table -->
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h3 class="card-title">Data Pesanan Penjualan</h3>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="refreshCache()">
                                        <i class="fas fa-sync-alt"></i> Refresh Data
                                    </button>
                                </div>
                            </div>

                            <div class="card-body">
                                <table id="barang_masuk" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Nomor #</th>
                                            <th>Tanggal</th>
                                            <th>Pelanggan</th>
                                            <th>Keterangan</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($salesOrders as $so)
                                            <tr>
                                                <td>{{ $so['number'] ?? '-' }}</td>
                                                <td>{{ !empty($so['transDate']) ? \Carbon\Carbon::createFromFormat('d/m/Y', $so['transDate'])->format('d-m-Y') : '-' }}
                                                </td>
                                                <td>{{ $so['customer']['contactInfo']['name'] ?? ($so['customer']['name'] ?? '-') }}
                                                </td>
                                                <td>{{ $so['description'] ?? '-' }}</td>
                                                <td>
                                                    @php
                                                        $status = strtoupper(trim($so['status'] ?? ''));
                                                    @endphp

                                                    @if(!empty($so['is_partially_delivered']))
                                                        <span class="badge"
                                                            style="background-color: #f97316; color: white; padding: 4px 8px; border-radius: 4px;">
                                                            <i class="fas fa-history mr-1"></i>Sebagian Diproses
                                                        </span>
                                                    @elseif($status === 'PROCESSED')
                                                        <span class="badge"
                                                            style="background-color: #16a34a; color: white; padding: 4px 8px; border-radius: 4px;">
                                                            <i class="fas fa-check-circle mr-1"></i>Selesai Diproses
                                                        </span>
                                                    @elseif($status === 'QUEUE')
                                                        <span class="badge badge-warning"
                                                            style="padding: 4px 8px; border-radius: 4px;">
                                                            <i class="fas fa-clock mr-1"></i>Menunggu diproses
                                                        </span>
                                                    @else
                                                        <span class="badge badge-secondary"
                                                            style="padding: 4px 8px; border-radius: 4px;">
                                                            {{ $so['statusName'] ?? ($status ?: '-') }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>{{ isset($so['totalAmount']) ? number_format($so['totalAmount'], 0, ',', '.') : '-' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Tidak ada data pesanan penjualan
                                                </td>
                                            </tr>
                                        @endforelse
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

        updateTitle('Pesanan Penjualan');

        function refreshCache() {
            const button = event.target;
            button.disabled = true;
            const icon = button.querySelector('i');
            icon.classList.remove('fa-sync-alt');
            icon.classList.add('fa-spinner', 'fa-spin');
            window.location.href = '{{ route("cashier.index") }}?force_refresh=1';
        }

        document.addEventListener('DOMContentLoaded', (event) => {
            const alertBox = document.getElementById('auto-dismiss-alert');
            if (alertBox) {
                setTimeout(() => {
                    if (window.jQuery) {
                        $('#auto-dismiss-alert').fadeOut(500, function () {
                            $(this).remove();
                        });
                    } else {
                        alertBox.style.transition = 'opacity 0.5s';
                        alertBox.style.opacity = '0';
                        setTimeout(() => alertBox.remove(), 500);
                    }
                }, 5000);
            }
        });
    </script>
@endsection