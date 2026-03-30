@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Edit Packing List</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('packing-list.index') }}">Packing List</a></li>
                            <li class="breadcrumb-item active">Edit Packing List</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('packing-list.update', $packingList->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Form Edit Packing List</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="tanggal">Tanggal</label>
                                        <input type="date" name="tanggal" class="form-control" id="tanggal"
                                            value="{{ old('tanggal', \Carbon\Carbon::parse($packingList->tanggal)->format('Y-m-d')) }}"
                                            required readonly>
                                        @error('tanggal')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="npl">No. Packing List</label>
                                        <input type="text" name="npl" class="form-control" id="npl"
                                            value="{{ old('npl', $packingList->npl) }}" required>
                                        @error('npl')
                                            <small class="text-danger">No. Packing List tersebut sudah digunakan.</small>
                                        @enderror
                                    </div>

                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('packing-list.index') }}" class="btn btn-secondary">Kembali</a>
                                    <button type="submit" class="btn btn-primary">Update</button>
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

        updateTitle('Edit Packing List');

        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('npl').focus();
        });
    </script>
@endsection
