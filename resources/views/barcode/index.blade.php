@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Barcode</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                            <li class="breadcrumb-item active">Barcode</li>
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

                        <!-- Card Table -->
                        <div class="card barcode-dt-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Data Barcode</h3>
                                @if ($lastUpdated)
                                    <span class="badge badge-danger ml-auto"
                                        style="font-size: 0.95rem; min-width:220px; text-align:right; white-space:nowrap;">
                                        Terakhir update:
                                        {{ \Carbon\Carbon::parse($lastUpdated)->locale('id')->isoFormat('dddd, DD-MM-YYYY HH:mm') }}
                                    </span>
                                @endif
                            </div>

                            <div class="card-body barcode-dt-card-body">
                                <style>
                                    .barcode-dt-card,
                                    .barcode-dt-card-body {
                                        overflow: visible !important;
                                    }

                                    .barcode-th-filter {
                                        display: flex;
                                        align-items: center;
                                        gap: 0.35rem;
                                        min-width: 0;
                                    }

                                    .barcode-th-filter .barcode-th-title {
                                        flex: 1;
                                        min-width: 0;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                    }

                                    .barcode-col-filter-btn {
                                        padding: 0.1rem 0.35rem;
                                        line-height: 1;
                                        flex-shrink: 0;
                                    }

                                    .barcode-col-filter-btn.has-active-filter {
                                        color: #fff;
                                        background-color: #007bff;
                                        border-color: #007bff;
                                    }

                                    .barcode-filter-dropdown {
                                        min-width: 240px;
                                        max-height: 320px;
                                    }

                                    .barcode-filter-checkboxes {
                                        max-height: 200px;
                                        overflow-y: auto;
                                        font-size: 0.875rem;
                                    }

                                    .barcode-filter-checkboxes .custom-control-label {
                                        white-space: nowrap;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                        max-width: 210px;
                                    }

                                    /*
                                     * scrollX DataTables: tanpa table-head-fixed (sticky th) dan tanpa
                                     * overflow:visible pada scrollHead — keduanya mengacaukan sync scroll horizontal.
                                     * Override width 100% global layout agar lebar header = lebar konten (bisa > container).
                                     */
                                    #barcode_wrapper .dataTables_scrollHeadInner,
                                    #barcode_wrapper .dataTables_scrollHeadInner > .dataTable,
                                    #barcode_wrapper .dataTables_scrollBody table.dataTable {
                                        width: auto !important;
                                        min-width: 100% !important;
                                    }

                                    #barcode_wrapper .dataTables_scroll {
                                        width: 100%;
                                    }

                                    /*
                                     * scrollBody digambar setelah scrollHead → menutupi dropdown kecuali
                                     * header punya stacking lebih tinggi dari body.
                                     */
                                    #barcode_wrapper .dataTables_scrollHead {
                                        position: relative;
                                        z-index: 20;
                                    }

                                    #barcode_wrapper .dataTables_scrollBody {
                                        position: relative;
                                        z-index: 10;
                                    }

                                    #barcode_wrapper .barcode-th-filter .dropdown {
                                        position: relative;
                                        z-index: 21;
                                    }

                                    #barcode_wrapper .dropdown-menu.barcode-filter-dropdown {
                                        z-index: 10210 !important;
                                    }

                                    #barcode_wrapper .dropdown-menu.barcode-filter-dropdown.show {
                                        z-index: 10220 !important;
                                    }
                                </style>
                                {{-- Tanpa table-head-fixed: sticky AdminLTE bentrok dengan scrollX (header tidak ikut scroll X) --}}
                                <table id="barcode" class="table text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">No. Packing List</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="1" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="1">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">No. Billing</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="2" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="2">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Kode Barang</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="3" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="3">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Status</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="4" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="4">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Item Flag</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="5" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="5">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Keterangan</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="6" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="6">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Nomor Seri</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="7" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="7">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>Pcs</th>
                                            <th>Berat (KG)</th>
                                            <th>Length</th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">UOM</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="11" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="11">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Material Code</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="12" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="12">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Kode Warna</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="13" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="13">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>Panjang (MLC)</th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Warna</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="15" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="15">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>Harga PPN</th>
                                            <th>Harga Jual</th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Pemasok</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="18" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="18">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Customer</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="19" data-toggle="dropdown" data-boundary="window" data-flip="false"
                                                            title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="19">
                                                            <input type="text"
                                                                class="form-control form-control-sm mb-2 barcode-filter-search"
                                                                placeholder="Cari..." autocomplete="off">
                                                            <div class="barcode-filter-checkboxes"></div>
                                                            <div class="dropdown-divider my-2"></div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary btn-block barcode-filter-select-all">Pilih
                                                                semua</button>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-secondary btn-block barcode-filter-clear-col">Hapus
                                                                filter kolom</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                            <th>Kontrak</th>
                                            <th>Tanggal</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Mobil / No. Polisi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($barcodes as $barcode)
                                            <tr>
                                                <td>{{ $barcode->barcode }}</td>
                                                <td>{{ $barcode->no_packing_list }}</td>
                                                <td>{{ $barcode->no_billing }}</td>
                                                <td>{{ $barcode->kode_barang }}</td>
                                                <td>
                                                    @php
                                                        $statusClass = match($barcode->status ?? 'temporary') {
                                                            'approved' => 'badge-success',
                                                            'uploaded' => 'badge-info',
                                                            default => 'badge-warning',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $statusClass }}">{{ strtoupper($barcode->status ?? 'temporary') }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $barcode->item_flag ?? 'pembelian')) }}</span>
                                                </td>
                                                <td>{{ $barcode->keterangan }}</td>
                                                <td>{{ $barcode->nomor_seri }}</td>
                                                <td>{{ $barcode->pcs }}</td>
                                                <td>{{ $barcode->berat_kg ? number_format($barcode->berat_kg, 2) : '-' }}</td>
                                                <td>{{ $barcode->length ? $barcode->length : '-' }}</td>
                                                <td>{{ $barcode->uom ? $barcode->uom : '-' }}</td>
                                                <td>{{ $barcode->material_code ? $barcode->material_code : '-' }}</td>
                                                <td>{{ $barcode->kode_warna ? $barcode->kode_warna : '-' }}</td>
                                                <td>{{ $barcode->panjang_mlc ? number_format($barcode->panjang_mlc, 2) : '-' }}</td>
                                                <td>{{ $barcode->warna }}</td>
                                                <td>{{ 'Rp. ' . number_format($barcode->harga_ppn, 0, ',', '.') }}</td>
                                                <td>{{ 'Rp. ' . number_format($barcode->harga_jual, 0, ',', '.') }}</td>
                                                <td>{{ $barcode->pemasok }}</td>
                                                <td>{{ $barcode->customer }}</td>
                                                <td>{{ $barcode->kontrak }}</td>
                                                <td>{{ \Carbon\Carbon::parse($barcode->tanggal)->format('d-m-Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($barcode->jatuh)->format('d-m-Y') }}</td>
                                                <td>{{ $barcode->no_vehicle }}</td>
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

        updateTitle('Barcode');
    </script>
@endsection

@push('scripts')
    <script>
        $(function() {
            var $table = $('#barcode');
            if (!$table.length || !$.fn.DataTable.isDataTable($table)) {
                return;
            }

            var table = $table.DataTable();
            var columnFilters = {};

            function getUniqueValues(colIdx) {
                var uniques = new Set();
                table.rows({
                    search: 'none'
                }).every(function() {
                    var txt = $(this.node()).children('td').eq(colIdx).text().replace(/\s+/g, ' ').trim();
                    uniques.add(txt);
                });
                return Array.from(uniques).sort(function(a, b) {
                    return a.localeCompare(b, 'id', {
                        numeric: true,
                        sensitivity: 'base'
                    });
                });
            }

            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'barcode') {
                    return true;
                }
                var api = new $.fn.dataTable.Api(settings);
                var row = api.row(dataIndex).node();
                if (!row) {
                    return true;
                }
                var $tds = $(row).children('td');
                for (var colKey in columnFilters) {
                    if (!columnFilters.hasOwnProperty(colKey)) {
                        continue;
                    }
                    var allowed = columnFilters[colKey];
                    if (!allowed) {
                        continue;
                    }
                    if (allowed.length === 0) {
                        return false;
                    }
                    var colIdx = parseInt(colKey, 10);
                    var txt = $tds.eq(colIdx).text().replace(/\s+/g, ' ').trim();
                    if (allowed.indexOf(txt) === -1) {
                        return false;
                    }
                }
                return true;
            });

            function updateFilterButtons() {
                $('#barcode_wrapper .barcode-col-filter-btn').each(function() {
                    var col = String($(this).data('column'));
                    $(this).toggleClass('has-active-filter', columnFilters.hasOwnProperty(col));
                });
            }

            function applyFiltersFromDropdown($dd) {
                var col = String($dd.data('column'));
                var $cbs = $dd.find('.barcode-filter-cb:visible');
                var selected = [];
                $cbs.filter(':checked').each(function() {
                    var v = $(this).data('filterValue');
                    selected.push(v === undefined ? '' : String(v));
                });
                var total = $cbs.length;
                if (total === 0) {
                    delete columnFilters[col];
                } else if (selected.length === total) {
                    delete columnFilters[col];
                } else if (selected.length === 0) {
                    columnFilters[col] = [];
                } else {
                    columnFilters[col] = selected;
                }
                table.draw();
                updateFilterButtons();
            }

            function fillDropdown($dd) {
                var col = parseInt($dd.data('column'), 10);
                var $box = $dd.find('.barcode-filter-checkboxes');
                var $search = $dd.find('.barcode-filter-search');
                $box.empty();
                $search.val('');
                var uniques = getUniqueValues(col);
                var active = columnFilters[String(col)];
                uniques.forEach(function(val) {
                    var label = val === '' ? '(kosong)' : val;
                    var checked = !active || active.indexOf(val) !== -1;
                    var $cb = $('<input type="checkbox" class="custom-control-input barcode-filter-cb">')
                        .data('filterValue', val)
                        .prop('checked', checked);
                    var $row = $('<label class="custom-control custom-checkbox barcode-filter-row mb-1 d-block">')
                        .append($cb, $('<span class="custom-control-label">').text(label));
                    $box.append($row);
                });
                filterCheckboxRows($dd);
            }

            function filterCheckboxRows($dd) {
                var q = ($dd.find('.barcode-filter-search').val() || '').toLowerCase();
                $dd.find('.barcode-filter-row').each(function() {
                    var t = $(this).find('.custom-control-label').text().toLowerCase();
                    $(this).toggle(t.indexOf(q) !== -1);
                });
            }

            /* Bind di #barcode_wrapper (bukan document): stopPropagation mencegah bubble ke
               document sehingga handler Bootstrap tidak double-toggle; sekaligus th tidak
               dapat klik sort dari tombol filter. */
            $('#barcode_wrapper').on('click', '.barcode-col-filter-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).dropdown('toggle');
            });

            $(document).on('shown.bs.dropdown', '#barcode_wrapper .barcode-th-filter .dropdown', function() {
                fillDropdown($(this).find('.barcode-filter-dropdown'));
            });

            $(document).on('click', '#barcode_wrapper .barcode-filter-select-all', function(e) {
                e.preventDefault();
                var $dd = $(this).closest('.barcode-filter-dropdown');
                $dd.find('.barcode-filter-cb:visible').prop('checked', true);
                applyFiltersFromDropdown($dd);
            });

            $(document).on('click', '#barcode_wrapper .barcode-filter-clear-col', function(e) {
                e.preventDefault();
                var $dd = $(this).closest('.barcode-filter-dropdown');
                var col = String($dd.data('column'));
                delete columnFilters[col];
                table.draw();
                updateFilterButtons();
                $dd.find('.barcode-filter-cb').prop('checked', true);
                $(this).closest('.dropdown').find('[data-toggle="dropdown"]').dropdown('hide');
            });

            $(document).on('change', '#barcode_wrapper .barcode-filter-cb', function() {
                var $dd = $(this).closest('.barcode-filter-dropdown');
                applyFiltersFromDropdown($dd);
            });

            $(document).on('keyup', '#barcode_wrapper .barcode-filter-search', function() {
                filterCheckboxRows($(this).closest('.barcode-filter-dropdown'));
            });

            $(document).on('click', '#barcode_wrapper .barcode-filter-dropdown', function(e) {
                e.stopPropagation();
            });

            table.on('draw', function() {
                updateFilterButtons();
            });

            updateFilterButtons();
            table.columns.adjust();
        });
    </script>
@endpush
