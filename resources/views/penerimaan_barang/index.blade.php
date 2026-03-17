@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Penerimaan Barang</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Penerimaan Barang</li>
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

                    <!-- Add Button -->
                    @if (Auth::user()->role != 'owner')
                    <div class="d-flex justify-content-end mb-3">
                        <a href="{{ route('penerimaan-barang.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add
                        </a>
                    </div>
                    @endif

                    <!-- Card Table -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Data Penerimaan Barang</h3>
                                <button type="button" class="btn btn-primary btn-sm" onclick="refreshCache()">
                                    <i class="fas fa-sync-alt"></i> Refresh Data
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <table id="penerimaan_barang" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Nomor #</th>
                                        <th>Packing List</th>
                                        <th>Tanggal</th>
                                        <th>Pemasok</th>
                                        <th>Keterangan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($penerimaanBarang as $item)
                                    <tr>
                                        <td><a
                                                href="{{ route('penerimaan-barang.show', $item->npb) }}">{{ $item->npb }}</a></td>
                                        <td>{{ $item->packingLists->pluck('npl')->join(', ') ?: '-' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->tanggal)->format('d-m-Y') }}</td>
                                        <td>{{ $detailPOs[$item->no_po]['vendor_name'] ?? '-' }}</td>
                                        <td>{{ $detailPOs[$item->no_po]['description'] ?? '-' }}</td>
                                        <td>{{ $detailPOs[$item->no_po]['status'] ?? '-' }}</td>
                                    </tr>

                                    <!-- Modal Hapus -->
                                    <div class="modal fade" id="modal-hapus{{ $item->id }}">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h4 class="modal-title">Konfirmasi Hapus</h4>
                                                    <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Apakah anda yakin ingin menghapus
                                                        <strong>{{ $item->nbrg }}</strong>?
                                                    </p>
                                                </div>
                                                <div class="modal-footer justify-content-between">
                                                    <button type="button" class="btn btn-default"
                                                        data-dismiss="modal">Batal</button>
                                                    <form action="{{ route('barang-masuk.destroy', $item->id) }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger">Ya,
                                                            Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Modal -->
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

    updateTitle('Penerimaan Barang');

    function refreshCache() {
        const button = event.target;
        button.disabled = true;
        const icon = button.querySelector('i');
        icon.classList.remove('fa-sync-alt');
        icon.classList.add('fa-spinner', 'fa-spin');
        window.location.href = '{{ route("penerimaan-barang.index") }}?force_refresh=1';
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