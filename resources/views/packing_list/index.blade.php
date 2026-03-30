@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Packing List</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item active">Packing List</li>
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
                            <a href="{{ route('packing-list.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add
                            </a>
                        </div>

                        <!-- Card Table -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Data Packing List</h3>
                            </div>

                            <div class="card-body">
                                <table id="packing_list" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>No Packing List</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($packingList as $pl)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($pl->tanggal)->format('d-m-Y') }}</td>
                                                <td><a href="{{ route('packing-list.show', $pl->id) }}">{{ $pl->npl }}</a></td>
                                                <td>
                                                    @php
                                                        $statusClass = match($pl->status ?? 'pending') {
                                                            'approved' => 'badge-success',
                                                            'used' => 'badge-info',
                                                            default => 'badge-warning',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $statusClass }}">{{ strtoupper($pl->status ?? 'pending') }}</span>
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

        updateTitle('Packing List');
    </script>
@endsection
