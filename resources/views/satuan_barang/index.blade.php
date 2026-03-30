@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Satuan Barang</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item active">Satuan Barang</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">
            @if (isset($errorMessage) && $errorMessage)
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
                                <h3 class="card-title">Data Unit Barang</h3>
                                <button type="button" class="btn btn-primary btn-sm" onclick="refreshCache()">
                                    <i class="fas fa-sync-alt"></i> Refresh Data
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <table id="satuan_barang" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Nama Satuan Barang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($satuanBarang as $sb)
                                    <tr>
                                        <!-- Adjust according to your API response structure -->
                                        <td>{{ $sb['name'] }}</td>
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

    updateTitle('Satuan Barang');

    // --- TAMBAHAN: Fungsi refresh cache & auto-dismiss alert ---
    function refreshCache() {
        const button = event.target;
        button.disabled = true;

        const icon = button.querySelector('i');
        icon.classList.remove('fa-sync-alt');
        icon.classList.add('fa-spinner', 'fa-spin');

        // Pastikan route name ini benar sesuai dengan file web.php Anda
        window.location.href = '{{ route("satuan_barang.index") }}?force_refresh=1';
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
            }, 5000); // 5 detik
        }
    });
</script>
@endsection
