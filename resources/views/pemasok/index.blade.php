@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Pemasok</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item active">Pemasok</li>
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
                                <h3 class="card-title">Data Pemasok</h3>
                                <button type="button" class="btn btn-primary btn-sm" onclick="refreshCache()">
                                    <i class="fas fa-sync-alt"></i> Refresh Data
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <table id="pemasok" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Kontak Utama</th>
                                        <th>ID Pemasok</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pemasok as $p)
                                    <tr>
                                        <td>{{ $p['name'] }}</td>
                                        <!-- Adjust according to your API response structure -->
                                        <td>{{ $p['wpName'] ?? 'N/A' }}</td>
                                        <!-- Adjust according to your API response structure -->
                                        <td>{{ $p['vendorNo'] }}</td>
                                        <!-- Adjust according to your API response structure -->
                                        <td>{{ $p['balance'] ?? '0' }}</td>
                                        <!-- Adjust according to your API response structure -->
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

    function refreshCache() {
        // Tampilkan loading state pada tombol
        const button = event.target;
        button.disabled = true;

        // --- Menambahkan ikon loading untuk feedback visual ---
        const icon = button.querySelector('i');
        icon.classList.remove('fa-sync-alt');
        icon.classList.add('fa-spinner', 'fa-spin');

        // Redirect ke halaman yang sama dengan parameter force_refresh
        window.location.href = '{{ route("pemasok.index") }}?force_refresh=1';
    }

    updateTitle('Pemasok');

    // --- TAMBAHAN: Script untuk menyembunyikan alert otomatis ---
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
            }, 5000); // 5 detik
        }
    });
</script>
@endsection
