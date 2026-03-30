@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Packing List</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('packing-list.index') }}">Packing List</a></li>
                        <li class="breadcrumb-item active"><a>Tambah Packing List</a></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">
            <form action="{{ route('packing-list.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Form Tambah Packing List</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="tanggal">Tanggal</label>
                                    <input type="date" name="tanggal" class="form-control" id="tanggal"
                                        value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" readonly required>
                                    @error('tanggal')
                                    <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="npl">
                                        No. Packing List
                                        <span class="ml-1"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title="Silakan scan barcode Packing List untuk mengisi field ini"
                                            style="cursor: help;">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                    <input type="text" name="npl" id="npl" class="form-control" required
                                        placeholder="Scan barcode Packing List di sini">

                                    @error('npl')
                                    <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                            </div>
                            <div class="card-footer text-right">
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    function updateTitle(pageTitle) {
        document.title = pageTitle;
    }

    updateTitle('Tambah Packing List');

    window.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Focus on NPL input
        document.getElementById('npl').focus();
    });
</script>
@endsection
