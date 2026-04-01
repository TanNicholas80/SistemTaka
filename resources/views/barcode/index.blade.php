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
                        <div class="card">
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
                                        flex: 0 0 auto;
                                        white-space: nowrap;
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

                                    .barcode-reset-sort-btn {
                                        margin-right: 0.5rem;
                                    }

                                    #barcode-main thead th {
                                        position: relative !important;
                                        padding-right: 30px !important;
                                        min-width: 100px !important;
                                        cursor: default !important;
                                    }

                                    .barcode-sort-icon,
                                    .barcode-sort-pointer {
                                        cursor: pointer !important;
                                    }

                                    #barcode-main thead th {
                                        cursor: default !important;
                                    }

                                    #barcode-main thead th.sorting::before,
                                    #barcode-main thead th.sorting_asc::before,
                                    #barcode-main thead th.sorting_desc::before {
                                        right: 20px !important;
                                        content: "\2191" !important;
                                        bottom: 0.5em !important;
                                        opacity: 0.1 !important;
                                        font-size: 1.25rem !important;
                                        transition: transform 0.1s ease;
                                    }

                                    #barcode-main thead th.sorting::after,
                                    #barcode-main thead th.sorting_asc::after,
                                    #barcode-main thead th.sorting_desc::after {
                                        right: 2px !important;
                                        content: "\2193" !important;
                                        bottom: 0.5em !important;
                                        opacity: 0.1 !important;
                                        font-size: 1.25rem !important;
                                        transition: transform 0.1s ease;
                                    }

                                    #barcode-main thead th.sorting_asc::before {
                                        opacity: 1 !important;
                                        color: #000 !important;
                                    }

                                    #barcode-main thead th.sorting_desc::after {
                                        opacity: 1 !important;
                                        color: #000 !important;
                                    }

                                    #barcode-main thead th.sorting_asc::after,
                                    #barcode-main thead th.sorting_desc::before {
                                        opacity: 0.1 !important;
                                        color: #000 !important;
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

                                    .barcode-table-container {
                                        width: 100%;
                                        overflow-x: auto;
                                        -webkit-overflow-scrolling: touch;
                                        position: relative;
                                        margin-bottom: 1rem;
                                    }

                                    #barcode-main {
                                        width: 100% !important;
                                        margin: 0 !important;
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
                                        display: block !important;
                                        position: fixed !important;
                                        margin: 0 !important;
                                    }

                                    #barcode_wrapper table.dataTable thead th.sorting::before,
                                    #barcode_wrapper table.dataTable thead th.sorting_asc::before,
                                    #barcode_wrapper table.dataTable thead th.sorting_desc::before {
                                        right: 0.7em !important;
                                    }

                                    #barcode_wrapper table.dataTable thead th.sorting::after,
                                    #barcode_wrapper table.dataTable thead th.sorting_asc::after,
                                    #barcode_wrapper table.dataTable thead th.sorting_desc::after {
                                        right: 0.2em !important;
                                    }

                                    /* Sembunyikan panah default DataTables untuk SEMUA th */
                                    #barcode-main thead th::before,
                                    #barcode-main thead th::after {
                                        display: none !important;
                                        content: "" !important;
                                    }

                                    /* Styling panah custom di dalam flex */
                                    .barcode-sort-icon {
                                        font-size: 0.85rem;
                                        display: inline-flex;
                                        align-items: center;
                                        gap: 1px;
                                        line-height: 1;
                                        color: #ccc;
                                        cursor: pointer;
                                        user-select: none;
                                        margin-left: 4px;
                                    }

                                    .barcode-sort-icon span {
                                        opacity: 0.2;
                                        color: #000;
                                        transition: opacity 0.1s;
                                    }

                                    /* Efek Highlight: warna hitam pekat saat aktif */
                                    .barcode-sort-icon.asc .up,
                                    .barcode-sort-icon.desc .down {
                                        opacity: 1 !important;
                                        font-weight: bold;
                                    }

                                    .barcode-filter-row {
                                        display: block;
                                    }
                                </style>
                                <table id="barcode-main" class="table text-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="d-none">RID</th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Barcode</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">No. Packing List</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="2" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">No. Billing</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="3" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Kode Barang</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="4" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Status</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="5" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Item Flag</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="6" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Keterangan</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="7" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Nomor Seri</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="8" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="8">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Pcs</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Berat (KG)</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Length</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">UOM</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="12" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Material Code</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="13" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Kode Warna</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="14" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="14">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Panjang (MLC)</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Warna</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="16" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="16">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Harga PPN</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Harga Jual</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Pemasok</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="19" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Customer</span>
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-secondary barcode-col-filter-btn"
                                                            data-column="20" data-toggle="dropdown" data-boundary="window"
                                                            data-flip="false" title="Filter">
                                                            <i class="fas fa-filter"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right barcode-filter-dropdown p-2"
                                                            data-column="20">
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
                                                                semua</button>
                                                        </div>
                                                    </div>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Kontrak</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Tanggal</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Jatuh Tempo</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="barcode-th-filter">
                                                    <span class="barcode-th-title">Mobil / No. Polisi</span>
                                                    <span class="barcode-sort-icon"><span class="up">↑</span><span
                                                            class="down">↓</span></span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($barcodes as $barcode)
                                            <tr>
                                                <td class="d-none">{{ $loop->index }}</td>
                                                <td>{{ $barcode->barcode }}</td>
                                                <td>{{ $barcode->no_packing_list }}</td>
                                                <td>{{ $barcode->no_billing }}</td>
                                                <td>{{ $barcode->kode_barang }}</td>
                                                <td>
                                                    @php
                                                        $statusClass = match ($barcode->status ?? 'temporary') {
                                                            'approved' => 'badge-success',
                                                            'uploaded' => 'badge-info',
                                                            default => 'badge-warning',
                                                        };
                                                    @endphp
                                                    <span
                                                        class="badge {{ $statusClass }}">{{ strtoupper($barcode->status ?? 'temporary') }}</span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $barcode->item_flag ?? 'pembelian')) }}</span>
                                                </td>
                                                <td>{{ $barcode->keterangan }}</td>
                                                <td>{{ $barcode->nomor_seri }}</td>
                                                <td>{{ $barcode->pcs }}</td>
                                                <td>{{ $barcode->berat_kg ? number_format($barcode->berat_kg, 2) : '-' }}</td>
                                                <td>{{ $barcode->length ? $barcode->length : '-' }}</td>
                                                <td>{{ $barcode->uom ? $barcode->uom : '-' }}</td>
                                                <td>{{ $barcode->material_code ? $barcode->material_code : '-' }}</td>
                                                <td>{{ $barcode->kode_warna ? $barcode->kode_warna : '-' }}</td>
                                                <td>{{ $barcode->panjang_mlc ? number_format($barcode->panjang_mlc, 2) : '-' }}
                                                </td>
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
        $(function () {
            var $table = $('#barcode-main');
            if (!$table.length) {
                return;
            }

            // Initialize manually to bypass global scrollX logic
            var table = $table.DataTable({
                "paging": true,
                "responsive": false,
                "lengthChange": true,
                "autoWidth": false,
                "scrollX": false, // Keep header and body in the same table
                "searching": true,
                "ordering": true,
                "order": [[0, "asc"]], // Default to original order (RID)
                "columnDefs": [
                    {
                        "targets": [0],
                        "visible": false,
                        "searchable": false,
                        "type": "num" // Ensure numeric sort for original order reset
                    },
                    {
                        "targets": "_all",
                        "orderSequence": ["asc", "desc"]
                    }
                ],
                "info": true,
                "buttons": ["copy", "colvis"],
                "dom": "<'row mb-2'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                    "<'row mb-2'<'col-sm-12 col-md-6 d-flex align-items-center'B><'col-sm-12 col-md-6 d-flex justify-content-end align-items-center barcode-reset-controls-container'>>" +
                    "<'row'<'col-sm-12 barcode-table-container't>>" +
                    "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
            });
            
            // Unbind default sorting click listeners to prevent resetting paging on sort
            $table.find('thead th').off('click.DT');
            $table.find('thead').off('click.DT');

            $('.barcode-reset-controls-container').html(
                '<button type="button" class="btn btn-sm btn-outline-danger barcode-reset-filter-btn mr-2" title="Reset Filter"><i class="fas fa-filter"></i> Reset Filter</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger barcode-reset-sort-btn" title="Reset Sortir"><i class="fas fa-undo"></i> Reset Sortir</button>'
            );
            var columnFilters = {};

            function getUniqueValues(colIdx, searchType) {
                var uniques = new Set();
                table.rows({
                    search: searchType || 'none'
                }).every(function () {
                    var txt = $(this.node()).children('td').eq(colIdx).text().replace(/\s+/g, ' ').trim();
                    uniques.add(txt);
                });
                return Array.from(uniques).sort(function (a, b) {
                    return a.localeCompare(b, 'id', {
                        numeric: true,
                        sensitivity: 'base'
                    });
                });
            }

            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'barcode-main') {
                    return true;
                }
                var api = new $.fn.dataTable.Api(settings);
                var row = api.row(dataIndex).node();
                if (!row) {
                    return true;
                }
                var $tds = $(row).children('td');
                var activeFilterCols = Object.keys(columnFilters);

                // If no filters are active, show everything
                if (activeFilterCols.length === 0) {
                    return true;
                }

                // "Tampil Juga Dong" (OR Logic Across Columns)
                // If a row matches ANY of the active column filters, we show it.
                for (var i = 0; i < activeFilterCols.length; i++) {
                    var colKey = activeFilterCols[i];
                    var colIdx = parseInt(colKey, 10);
                    var allowed = columnFilters[colKey];
                    if (!allowed) continue;

                    var txt = $tds.eq(colIdx).text().replace(/\s+/g, ' ').trim();
                    if (allowed.indexOf(txt) !== -1) {
                        return true; // Match found in at least one filtered column
                    }
                }
                return false; // Row doesn't match any of the active filters
            });

            function updateFilterButtons() {
                $('#barcode_wrapper .barcode-col-filter-btn').each(function () {
                    var col = String($(this).data('column'));
                    $(this).toggleClass('has-active-filter', columnFilters.hasOwnProperty(col));
                });
            }

            function applyFiltersFromDropdown($dd) {
                var col = String($dd.data('column'));
                var $cbs = $dd.find('.barcode-filter-cb:visible');
                var selected = [];
                $cbs.filter(':checked').each(function () {
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
                var isInitiallyFiltered = columnFilters.hasOwnProperty(String(col));

                // Always get ALL values for visibility
                var allVals = getUniqueValues(col, 'none');
                // Get ONLY visible values for the context-aware "kompak" checking logic
                var visibleVals = getUniqueValues(col, 'applied');

                var $box = $dd.find('.barcode-filter-checkboxes');
                var $search = $dd.find('.barcode-filter-search');
                $box.empty();
                $search.val('');

                var activeSelection = columnFilters[String(col)];

                allVals.forEach(function (val) {
                    var label = val === '' ? '(kosong)' : val;
                    var isVisibleInCurrentResults = visibleVals.indexOf(val) !== -1;

                    // "Kompak" Bidirectional Logic:
                    // A value is checked if it's currently in the results (isVisibleInCurrentResults)
                    // OR if the user manually selected it (activeSelection).
                    var checked = isVisibleInCurrentResults || (isInitiallyFiltered && activeSelection.indexOf(val) !== -1);

                    var $cb = $('<input type="checkbox" class="custom-control-input barcode-filter-cb">')
                        .data('filterValue', val)
                        .prop('checked', checked);

                    var $row = $('<label class="custom-control custom-checkbox barcode-filter-row mb-1">')
                        .append($cb, $('<span class="custom-control-label">').text(label));

                    $box.append($row);
                });
                filterCheckboxRows($dd);
            }

            function filterCheckboxRows($dd) {
                var q = ($dd.find('.barcode-filter-search').val() || '').toLowerCase();
                $dd.find('.barcode-filter-row').each(function () {
                    var t = $(this).find('.custom-control-label').text().toLowerCase();
                    $(this).toggle(t.indexOf(q) !== -1);
                });
            }

            /*
               Robust Filter & Sort Isolation Logic:
               1. Bind DIRECTLY to buttons (no delegation) to intercept events before bubbling to <th>.
               2. Use stopImmediatePropagation and stopPropagation to kill sort signals.
               3. Portal dropdown to body for visibility.
            */
            function closeOtherFilters() {
                $('.barcode-filter-dropdown.show').each(function () {
                    var col = $(this).data('column');
                    $('[data-column="' + col + '"][data-toggle="dropdown"]').dropdown('hide');
                });
            }

            function setupFilterButtons() {
                // Remove existing to avoid double-binding
                $('.barcode-col-filter-btn').off('click mousedown');

                // DYNAMIC INDEX MAPPING FIX:
                // We iterate over all <th> to find the real index.
                // This overcomes the offset caused by the hidden RID column.
                $table.find('thead th').each(function (i) {
                    var $th = $(this);
                    var $btn = $th.find('.barcode-col-filter-btn');
                    if ($btn.length) {
                        $btn.attr('data-column', i);
                        $btn.next('.dropdown-menu').attr('data-column', i);
                    }
                });

                // Direct binding is the only way to reliably beat DataTables' bubbling-based sortir
                $('.barcode-col-filter-btn').on('click mousedown', function (e) {
                    e.stopImmediatePropagation();
                    e.stopPropagation();

                    if (e.type === 'click') {
                        e.preventDefault();
                        var $btn = $(this);
                        var $dropdown = $btn.closest('.dropdown');

                        // Handle toggle state manually because we stopPropagation
                        if ($dropdown.hasClass('show')) {
                            $btn.dropdown('hide');
                        } else {
                            closeOtherFilters();
                            $btn.dropdown('toggle');
                        }
                    }
                });
            }

            // Init buttons immediately
            setupFilterButtons();

            $(document).on('show.bs.dropdown', '#barcode-main_wrapper .barcode-th-filter .dropdown', function (e) {
                var $btn = $(e.relatedTarget);
                var $menu = $btn.next('.dropdown-menu');

                // Portal to body
                $menu.data('originalParent', $menu.parent());
                $('body').append($menu);

                // Populate BEFORE displaying or immediately after portaling
                fillDropdown($menu);

                var rect = $btn[0].getBoundingClientRect();
                var top = rect.bottom + window.scrollY;
                var left = rect.right + window.scrollX - $menu.outerWidth();

                if (left < 10) left = 10;

                $menu.css({
                    top: top + 'px',
                    left: left + 'px',
                    width: '240px'
                });

                // Ensure alignment sync
                table.columns.adjust();
            });

            // Cumulative Multi-Column Sorting Logic
            var sortStack = [];

            function syncSortIcons() {
                // Clear existing sorting classes from all th
                $table.find('thead th').removeClass('sorting_asc sorting_desc').addClass('sorting');

                // Re-apply classes from sortStack
                sortStack.forEach(function (s) {
                    var colIdx = s[0];
                    var dir = s[1];
                    var $th = $(table.column(colIdx).header());
                    $th.removeClass('sorting').addClass('sorting_' + dir);
                });
            }

            // Enable click ONLY on the sort icon, not the entire header text
            $table.find('thead th .barcode-sort-icon').addClass('barcode-sort-pointer');
            $table.find('thead th').on('click', '.barcode-sort-icon', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var $th = $(this).closest('th');
                var colIdx = table.column($th).index();
                var foundIdx = -1;
                for (var i = 0; i < sortStack.length; i++) {
                    if (sortStack[i][0] === colIdx) {
                        foundIdx = i;
                        break;
                    }
                }

                if (foundIdx !== -1) {
                    if (sortStack[foundIdx][1] === 'asc') {
                        sortStack[foundIdx][1] = 'desc';
                    } else {
                        sortStack[foundIdx][1] = 'asc';
                    }
                } else {
                    sortStack.push([colIdx, 'asc']);
                }

                table.order(sortStack).draw(false);
                updateSortIcons();
            });

                $(document).on('click', '.barcode-reset-sort-btn', function () {
                sortStack = [];
                // Reset data to original DB order (Column 0: RID)
                table.order([[0, 'asc']]).draw(false);
                
                // Delay sync to ensure order state is settled
                setTimeout(function() {
                    updateSortIcons();
                }, 50);
            });

            $(document).on('click', '.barcode-reset-filter-btn', function () {
                // 1. Clear the values in the internal filter object
                columnFilters = {};

                // 2. Clear DataTables' internal column search strings
                table.columns().search('');

                // 3. Clear existing filters displayed in menus (if open)
                $('.barcode-filter-search').val('');
                $('.barcode-filter-cb').prop('checked', true);

                // 4. Update the highlight state of funnel icons
                updateFilterButtons();

                // 5. Redraw the table to match original data
                table.draw();
            });

            $(document).on('hidden.bs.dropdown', '#barcode-main_wrapper .barcode-th-filter .dropdown', function (e) {
                var $btn = $(e.relatedTarget);
                var $menu = $('.barcode-filter-dropdown[data-column="' + $btn.data('column') + '"]');

                if ($menu.data('originalParent')) {
                    $menu.appendTo($menu.data('originalParent'));
                }

                // Final adjustment to ensure alignment stays perfect
                setTimeout(function () {
                    table.columns.adjust();
                    updateSortIcons(); // Re-sync icons after transition
                }, 50);
            });

            // Global Listeners for Portaled Menus (Remove wrapper restriction)
            $(document).on('click', '.barcode-filter-select-all', function (e) {
                e.preventDefault();
                var $dd = $(this).closest('.barcode-filter-dropdown');
                $dd.find('.barcode-filter-cb:visible').prop('checked', true);
                applyFiltersFromDropdown($dd);
            });

            $(document).on('click', '.barcode-filter-clear-col', function (e) {
                e.preventDefault();
                var $dd = $(this).closest('.barcode-filter-dropdown');
                var col = String($dd.data('column'));
                // Uncheck all
                $dd.find('.barcode-filter-cb').prop('checked', false);
                // Apply filter (this will hide everything for that column)
                applyFiltersFromDropdown($dd);
                // Highlight filter button as active because it's now filtering out everything
                updateFilterButtons();
                // Optionally close dropdown or keep it open? User said "Hapus pilihan", usually they stay in dropdown to pick one.
                // But current logic for RESET closes it. Let's keep it open or close as per previous behavior?
                // Old behavior closed it. But if they just "Cleared all", maybe they want to pick one?
                // Actually, let's just close it as requested "Hapus semua" usually implies reset-like action.
                $(this).closest('body').find('.dropdown[data-column="' + col + '"]').dropdown('hide');
            });

            $(document).on('change', '.barcode-filter-cb', function () {
                var $dd = $(this).closest('.barcode-filter-dropdown');
                applyFiltersFromDropdown($dd);
            });

            $(document).on('keyup', '.barcode-filter-search', function () {
                filterCheckboxRows($(this).closest('.barcode-filter-dropdown'));
            });

            $(window).on('resize', function () {
                table.columns.adjust();
                syncSortIcons();
            });

            table.on('draw', function () {
                updateFilterButtons();
                setupFilterButtons();
                syncSortIcons();
                table.columns.adjust();
            });

            // Prevent scroll/click inside dropdown from bubbling to table
            $(document).on('click mousedown mouseup', '.barcode-filter-dropdown', function (e) {
                e.stopPropagation();
            });

            // Re-sync on window resize
            $(window).on('resize', function () {
                table.columns.adjust();
            });


            $(document).on('click', '#barcode-main_wrapper .barcode-filter-select-all', function (e) {
                e.preventDefault();
                var $dd = $(this).closest('.barcode-filter-dropdown');
                $dd.find('.barcode-filter-cb:visible').prop('checked', true);
                applyFiltersFromDropdown($dd);
            });

            $(document).on('click', '#barcode-main_wrapper .barcode-filter-clear-col', function (e) {
                e.preventDefault();
                var $dd = $(this).closest('.barcode-filter-dropdown');
                var col = String($dd.data('column'));
                // Uncheck all
                $dd.find('.barcode-filter-cb').prop('checked', false);
                // Apply filter
                applyFiltersFromDropdown($dd);
                updateFilterButtons();
                $(this).closest('.dropdown').find('[data-toggle="dropdown"]').dropdown('hide');
            });

            $(document).on('change', '#barcode-main_wrapper .barcode-filter-cb', function () {
                var $dd = $(this).closest('.barcode-filter-dropdown');
                applyFiltersFromDropdown($dd);
            });

            $(document).on('keyup', '#barcode-main_wrapper .barcode-filter-search', function () {
                filterCheckboxRows($(this).closest('.barcode-filter-dropdown'));
            });

            $(document).on('click', '.barcode-filter-dropdown', function (e) {
                e.stopPropagation();
            });

            // Update ikon panah custom sesuai state sort
            function updateSortIcons() {
                var $thList = $('#barcode-main').find('thead th');

                // Clear all existing sort highlights
                $thList.find('.barcode-sort-icon').removeClass('asc desc');

                // If the sort stack is empty (e.g., after Reset), all icons will remain neutral/grey.
                if (sortStack.length === 0) return;

                sortStack.forEach(function (o) {
                    var colIdx = o[0];
                    var dir = o[1]; // 'asc' atau 'desc'
                    var $th = $(table.column(colIdx).header());
                    var $icon = $th.find('.barcode-sort-icon');

                    if ($icon.length) {
                        $icon.addClass(dir);
                    }
                });
            }

            table.on('order.dt draw.dt', function () {
                updateFilterButtons();
                setupFilterButtons();
                syncSortIcons();
                updateSortIcons();
                table.columns.adjust();
            });

            updateFilterButtons();
            syncSortIcons();
            updateSortIcons();
            table.columns.adjust();
        });
    </script>
@endpush