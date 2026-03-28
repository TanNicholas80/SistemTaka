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
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
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
            <form id="form_packing_list_create" action="{{ route('packing-list.store') }}" method="POST" enctype="multipart/form-data">
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

    /** NPL wajib 10 digit angka (validasi server). Ambil digit dari segmen pertama sebelum ';' (mis. dari scanner). */
    function extractNplTenDigits(raw) {
        var first = (String(raw).split(';')[0] || '').trim();
        var digits = first.replace(/\D/g, '');
        return digits.length >= 10 ? digits.substring(0, 10) : null;
    }

    window.addEventListener('DOMContentLoaded', function() {
        $('[data-toggle="tooltip"]').tooltip();

        var nplInput = document.getElementById('npl');
        var form = document.getElementById('form_packing_list_create');
        var nplSubmitting = false;

        nplInput.addEventListener('input', function() {
            if (nplSubmitting) return;
            var ten = extractNplTenDigits(nplInput.value);
            if (!ten) return;
            nplSubmitting = true;
            nplInput.value = ten;
            form.submit();
        });

        nplInput.focus();
    });
</script>
@endsection