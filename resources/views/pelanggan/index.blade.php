@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Pelanggan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item active">Pelanggan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

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
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Data Pelanggan</h3>
                                <button type="button" class="btn btn-primary btn-sm" onclick="refreshCache()">
                                    <i class="fas fa-sync-alt"></i> Refresh Data
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <table id="satuan_barang" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>ID Pelanggan</th>
                                        <th>Kontak Utama</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pelanggan as $p)
                                    <tr>
                                        <td>{{ $p['name'] }}</td>
                                        <!-- Menampilkan wpName di kolom Kontak Utama -->
                                        <td>{{ $p['customerNo'] }}</td>
                                        <td>{{ $p['wpName'] }}</td> <!-- Menampilkan wpName sebagai Kontak Utama -->
                                        <td>{{ $p['currency']['code'] }}
                                            {{ number_format($p['balanceList'][0]['balance'] ?? 0, 2) }}
                                        </td>
                                        <!-- Menampilkan mata uang sebelum saldo -->
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
        window.location.href = '{{ route("pelanggan.index") }}?force_refresh=1';
    }

    updateTitle('Data Pelanggan');

    // =======================================================
    //   TAMBAHKAN BLOK KODE JAVASCRIPT DI BAWAH INI
    // =======================================================
    document.addEventListener('DOMContentLoaded', (event) => {
        // Cari elemen alert berdasarkan ID yang kita buat
        const alertBox = document.getElementById('auto-dismiss-alert');

        // Jika elemen alert tersebut ada di halaman
        if (alertBox) {
            // Atur timer untuk menyembunyikan alert setelah 5 detik
            setTimeout(() => {
                // Opsi 1: Sembunyikan secara langsung (paling simpel)
                // alertBox.style.display = 'none';

                // Opsi 2: Hilangkan dengan efek fade-out (lebih halus, butuh Bootstrap JS)
                // Cek jika jQuery dan plugin alert Bootstrap tersedia
                if (window.jQuery) {
                    $('#auto-dismiss-alert').fadeOut(500, function() {
                        $(this).remove();
                    });
                } else {
                    // Fallback jika tidak ada jQuery
                    alertBox.style.transition = 'opacity 0.5s';
                    alertBox.style.opacity = '0';
                    setTimeout(() => alertBox.remove(), 500);
                }
            }, 5000); // 5000 milidetik = 5 detik
        }
    });
    // =======================================================
    //               AKHIR BLOK TAMBAHAN
    // =======================================================
</script>
@endsection
