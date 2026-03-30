@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Perintah Stock Opname</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item active">Perintah Stock Opname</li>
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
                                <h3 class="card-title">Data Perintah Stock Opname</h3>
                                <div class="d-flex align-items-center">
                                    <div class="dt-reset-controls mr-2" data-table="#perintah_stock_opname">
                                        <!-- Reset Sortir button will be injected here by global JS -->
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="refreshCache()">
                                        <i class="fas fa-sync-alt"></i> Refresh Data
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <table id="perintah_stock_opname" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="dt-th-flex">
                                                <span class="dt-th-title">Nomor #</span>
                                                <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dt-th-flex">
                                                <span class="dt-th-title">Tanggal Perintah</span>
                                                <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dt-th-flex">
                                                <span class="dt-th-title">Tanggal Mulai</span>
                                                <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dt-th-flex">
                                                <span class="dt-th-title">Gudang</span>
                                                <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dt-th-flex">
                                                <span class="dt-th-title">Status</span>
                                                <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dt-th-flex">
                                                <span class="dt-th-title">Penanggung Jawab</span>
                                                <span class="dt-sort-icon"><span class="up">↑</span><span class="down">↓</span></span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($perintahstockOpname as $pso)
                                    <tr>
                                        <td><a
                                                href="{{ url('/perintah-stock-opname/detail/' . $pso['number']) }}">{{ $pso['number'] ?? 'N/A' }}</a>
                                        </td>
                                        <td>{{ $pso['transDateView'] ?? 'N/A' }}</td>
                                        <td>{{ $pso['startDateView'] ?? 'N/A' }}</td>
                                        <td>{{ $pso['warehouse']['name'] ?? 'N/A' }}</td>
                                        <td>{{ $pso['statusName'] ?? 'N/A' }}</td>
                                        <td>{{ $pso['personCharged'] ?? 'N/A' }}</td>
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

    updateTitle('Perintah Stock Opname');

    function refreshCache() {
        const button = event.target;
        button.disabled = true;
        const icon = button.querySelector('i');
        icon.classList.remove('fa-sync-alt');
        icon.classList.add('fa-spinner', 'fa-spin');
        window.location.href = '{{ route("perintah_stock_opname.index") }}?force_refresh=1';
    }

    document.addEventListener('DOMContentLoaded', (event) => {
        const alertBox = document.getElementById('auto-dismiss-alert');
        if (alertBox) {
            setTimeout(() => {
                if (window.jQuery) {
                    $('#auto-dismiss-alert').fadeOut(500, function() {
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
