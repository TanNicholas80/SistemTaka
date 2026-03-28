<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title id="dynamic-title">Dashboard - Taka Textile</title>
    <link rel="icon" type="svg" href="{{ asset('images/logo.svg') }}">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet"
        href="{{ asset('lte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <!-- Bootstrap4 Duallistbox -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/bootstrap4-duallistbox/bootstrap-duallistbox.min.css') }}">
    <!-- BS Stepper -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/bs-stepper/css/bs-stepper.min.css') }}">
    <!-- dropzonejs -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/dropzone/min/dropzone.min.css') }}">
    <!-- iCheck -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <!-- JQVMap -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/jqvmap/jqvmap.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('lte/dist/css/adminlte.min.css') }}">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
    <!-- Daterange picker -->
    {{--
    <link rel="stylesheet" href="{{ asset('lte/plugins/daterangepicker/daterangepicker.css') }}"> --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <!-- summernote -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/summernote/summernote-bs4.min.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Vite for Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Loading Screen CSS -->
    <style>
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #08332c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            margin-top: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #08332c;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading-dots {
            margin-top: 10px;
            display: flex;
            gap: 4px;
        }

        .loading-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #08332c;
            animation: bounce 1.4s ease-in-out infinite both;
        }

        .loading-dot:nth-child(1) {
            animation-delay: -0.32s;
        }

        .loading-dot:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes bounce {

            0%,
            80%,
            100% {
                transform: scale(0);
            }

            40% {
                transform: scale(1);
            }
        }

        /* Sidebar Width Consistency */
        :root {
            --main-sidebar-width: 300px;
        }

        /* Default Sidebar Width (Expanded) */
        body:not(.sidebar-collapse) .main-sidebar,
        body:not(.sidebar-collapse) .main-sidebar::before {
            width: var(--main-sidebar-width) !important;
        }

        /* Sidebar Mini Width (Collapsed) */
        .sidebar-collapse .main-sidebar,
        .sidebar-collapse .main-sidebar::before {
            width: 4.6rem !important;
            /* Standard Mini Width */
        }

        /* Hover expansion in Collapsed mode */
        .sidebar-mini.sidebar-collapse .main-sidebar:hover,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused {
            width: var(--main-sidebar-width) !important;
        }

        /* Content-wrapper & Header margin when EXPANDED */
        body:not(.sidebar-collapse) .content-wrapper,
        body:not(.sidebar-collapse) .main-footer,
        body:not(.sidebar-collapse) .main-header {
            margin-left: var(--main-sidebar-width) !important;
        }

        /* Content-wrapper & Header margin when COLLAPSED */
        .sidebar-collapse .content-wrapper,
        .sidebar-collapse .main-footer,
        .sidebar-collapse .main-header {
            margin-left: 4.6rem !important;
        }

        /* Prevent Horizontal Shift */
        html,
        body {
            overflow-x: hidden !important;
            width: 100%;
            position: relative;
        }

        .wrapper {
            overflow-x: hidden !important;
            width: 100%;
            position: relative;
        }

        /* Ensure Table takes full width */
        .table {
            width: 100% !important;
        }

        /* Fix DataTable scrollX header alignment */
        .dataTables_scrollHeadInner,
        .dataTables_scrollHeadInner>.table {
            width: 100% !important;
        }

        /* Profile Image Fix */
        .user-panel .image img {
            width: 50px !important;
            height: 50px !important;
            object-fit: cover !important;
            aspect-ratio: 1/1 !important;
        }

        /* Sidebar Item Improvements */
        .nav-sidebar .nav-link p {
            white-space: normal;
        }

        /* Hide all sub-menus when sidebar is small and NOT hovered */
        .sidebar-collapse .main-sidebar:not(:hover) .nav-sidebar .nav-treeview {
            display: none !important;
        }

        /* Hide text labels and arrows to prevent them from floating when mouse leaves the sidebar */
        .sidebar-collapse .main-sidebar:not(:hover) .nav-sidebar .nav-link p,
        .sidebar-collapse .main-sidebar:not(:hover) .nav-sidebar .nav-link .right {
            display: none !important;
        }

        /* Hide profile info on collapse */
        .sidebar-collapse .main-sidebar:not(:hover) .user-panel {
            display: none !important;
        }

        /* Reset parent "open" background (the grey) on collapse, but KEEP the active blue */
        .sidebar-collapse .main-sidebar:not(:hover) .nav-item.menu-open>.nav-link:not(.active) {
            background-color: transparent !important;
        }

        /* Ensure Brand Logo remains visible and correctly styled */
        .brand-link {
            display: flex !important;
            align-items: center !important;
            white-space: nowrap !important;
        }

        .brand-link .brand-image {
            margin-right: .8rem !important;
            max-height: 33px !important;
            width: auto !important;
            float: none !important;
        }

        .brand-text {
            padding-left: .2rem !important;
        }

        /* Branding Adjustments in Collapse Mode */
        .sidebar-collapse .main-sidebar:not(:hover) .brand-link .brand-image {
            padding-left: .1rem !important;
            margin-right: 0 !important;
        }

        .sidebar-collapse .main-sidebar:not(:hover) .brand-text {
            display: none !important;
        }

        /* Area Separation: Prevent backgrounds from merging (Ultimate Pill Mode) */
        /* Neutralize ANY grouping backgrounds on menu containers */
        .nav-sidebar .nav-treeview,
        .nav-sidebar .nav-item.menu-open,
        .nav-sidebar .nav-item {
            background-color: transparent !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Target links specifically to be "Rounded Rectangles" */
        ul.nav-sidebar li.nav-item>a.nav-link {
            width: auto !important;
            margin-bottom: 6px !important;
            /* Standard vertical gap */
            margin-left: 10px !important;
            /* Horizontal gap from left */
            margin-right: 10px !important;
            /* Horizontal gap from right */
            border-radius: 6px !important;
            /* Rounded Rectangle shape - NOT Pill */
            display: block !important;
            transition: all 0.2s ease !important;
        }

        /* Standardized Hierarchy Indentation (Consistently shifting 1.2rem per level) */
        /* Applied when Expanded OR when Collapsed but Hovered */

        /* Level 1: Root */
        body:not(.sidebar-collapse) .nav-sidebar>.nav-item>.nav-link,
        .sidebar-collapse .main-sidebar:hover .nav-sidebar>.nav-item>.nav-link {
            padding-left: 1rem !important;
        }

        /* Level 2: Sub-menu */
        body:not(.sidebar-collapse) .nav-sidebar .nav-treeview>.nav-item>.nav-link,
        .sidebar-collapse .main-sidebar:hover .nav-sidebar .nav-treeview>.nav-item>.nav-link {
            padding-left: 2.2rem !important;
        }

        /* Level 3: Nested Sub-menu */
        body:not(.sidebar-collapse) .nav-sidebar .nav-treeview .nav-treeview>.nav-item>.nav-link,
        .sidebar-collapse .main-sidebar:hover .nav-sidebar .nav-treeview .nav-treeview>.nav-item>.nav-link {
            padding-left: 3.4rem !important;
        }

        /* Ensure nav-treeview links also follow the capsule/pill box style */
        .nav-sidebar .nav-treeview .nav-link {
            width: auto !important;
        }

        /* Centered Icons on Collapse (ONLY when NOT hovered) */
        .sidebar-collapse .main-sidebar:not(:hover) .nav-sidebar .nav-link {
            width: 100% !important;
            margin: 0 !important;
            padding: 0.5rem 0 !important;
            text-align: center !important;
        }

        .sidebar-collapse .main-sidebar:not(:hover) .nav-sidebar .nav-icon {
            margin: 0 !important;
            float: none !important;
        }

        .nav-sidebar .nav-link {
            width: 100% !important;
            margin: 0 !important;
        }

        .nav-sidebar .nav-treeview {
            padding-left: 0 !important;
            margin-left: 0 !important;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <script>
        // Apply sidebar state as early as possible to prevent flicker
        (function () {
            const sidebarState = localStorage.getItem('sidebar-state');
            if (sidebarState === 'collapsed') {
                document.body.classList.add('sidebar-collapse');
            }
        })();
    </script>
    <div class="wrapper">
        <!-- Loading Screen -->
        <div class="loading-screen" id="loadingScreen">
            <div class="loading-spinner"></div>
            <div class="loading-text">Memuat...</div>
            <div class="loading-dots">
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
            </div>
        </div>

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="{{ route('dashboard') }}" class="nav-link">Home</a>
                </li>

            </ul>

            <!-- Centered Store Name -->
            <div class="navbar-nav mx-auto d-none d-sm-flex"
                style="position: absolute; left: 50%; transform: translateX(-50%); pointer-events: none;">
                <span class="nav-link font-weight-bold" style="font-size: 1.1rem; color:#09332c; white-space: nowrap;">
                    Toko {{ session('active_branch_name') ?? (Auth::user()->branches->first()->name ?? '-') }}
                </span>
            </div>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto" style="position: relative; display: flex;">
                <span id="datetime" class="nav-link" style="min-width:220px">
                    {{ \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, DD-MM-YYYY | HH:mm:ss') }}
                </span>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('branch.select') }}">
                        <i class="fas fa-sign-out-alt"></i> Pilih Toko
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.navbar -->

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-light-primary elevation-4">
            <!-- Brand Logo -->
            <div class="brand-link" style="cursor: default;">
                <img src="{{ asset('images/logo.svg') }}" alt="Duniatex Logo" class="brand-image" style="opacity: .8">
                <span class="brand-text font-weight-bold">
                    <span style="color: #08332c;">TAKA TEXTILE</span>
                </span>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel (optional) -->
                <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
                    <div class="image">
                        <img src="{{ asset('images/av1.png') }}" class="img-circle elevation-2" alt="User Image">
                    </div>
                    <div class="info py-1">
                        @auth
                            <span class="d-block mb-0"
                                style="font-size: 18px; font-weight: bold; color: #343a40; line-height: 1.1;">{{ Auth::user()->username }}</span>
                            <span class="d-block small text-muted" style="text-transform: capitalize; line-height: 1.1;">
                                {{ str_replace('_', ' ', Auth::user()->role) }}
                            </span>
                        @else
                            <label class="d-block mb-0 text-dark">Guest</label>
                        @endauth
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="false">

                        <!-- Dashboard Menu -->
                        <!-- <li class="nav-item">
                            <a href="{{ route('dashboard') }}"
                                class="nav-link {{ Request::routeIs('dashboard') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li> -->

                        <!-- User Menu -->
                        @if (Auth::user()->role === 'super_admin')
                            <li class="nav-item">
                                <a href="{{ route('user.index') }}"
                                    class="nav-link {{ Request::is('user*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-user"></i>
                                    <p>User</p>
                                </a>
                            </li>
                        @endif

                        <li class="nav-item">
                            <a href="{{ route('user.profile') }}"
                                class="nav-link {{ Request::routeIs('user.profile') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-user-circle"></i>
                                <p>Profile</p>
                            </a>
                        </li>

                        <!-- Log Aktivitas Menu (Restricted to SA & KT) -->
                        @if(in_array(Auth::user()->role, ['super_admin', 'kepala_toko']))
                            <li class="nav-item">
                                <a href="{{ route('activity_logs.index') }}"
                                    class="nav-link {{ Request::routeIs('activity_logs.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-history"></i>
                                    <p>Log Activity</p>
                                </a>
                            </li>
                        @endif

                        <li
                            class="nav-item {{ Request::routeIs('pesanan_pembelian.*') || Request::is('packing-list*') || Request::is('barang-masuk*') || Request::routeIs('penerimaan-barang.*') || Request::routeIs('retur_pembelian.*') ? 'menu-open' : '' }}">
                            <a href="#"
                                class="nav-link {{ Request::routeIs('pesanan_pembelian.*') || Request::is('packing-list*') || Request::is('barang-masuk*') || Request::routeIs('penerimaan-barang.*') || Request::routeIs('retur_pembelian.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-warehouse"></i>
                                <p>Pembelian <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('pesanan_pembelian.index') }}"
                                        class="nav-link {{ Request::is('pesanan-pembelian*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Pesanan Pembelian</p>
                                    </a>
                                </li>

                                <li
                                    class="nav-item {{ Request::is('packing-list*') || Request::is('barang-masuk*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ Request::is('packing-list*') || Request::is('barang-masuk*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Packing List <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview">
                                        <li class="nav-item">
                                            <a href="{{ route('packing-list.index') }}"
                                                class="nav-link {{ Request::is('packing-list*') ? 'active' : '' }}">
                                                <i class="fas fa-minus nav-icon"></i>
                                                <p>Packing List</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="{{ route('barang-masuk.index') }}"
                                                class="nav-link {{ Request::is('barang-masuk*') ? 'active' : '' }}">
                                                <i class="fas fa-minus nav-icon"></i>
                                                <p>Scan Barang</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="nav-item">
                                    <a href="{{ route('penerimaan-barang.index') }}"
                                        class="nav-link {{ Request::is('penerimaan-barang*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Penerimaan Barang</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('retur_pembelian.index') }}"
                                        class="nav-link {{ Request::is('retur-pembelian*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Retur Pembelian</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li
                            class="nav-item {{ Request::routeIs('barcode.*') || Request::routeIs('perintah_stock_opname.*') || Request::routeIs('hasil_stock_opname.*') || Request::routeIs('barang_master.*') || Request::routeIs('print_barcode.*') ? 'menu-open' : '' }}">
                            <a href="#"
                                class="nav-link {{ Request::routeIs('barcode.*') || Request::routeIs('perintah_stock_opname.*') || Request::routeIs('hasil_stock_opname.*') || Request::routeIs('barang_master.*') || Request::routeIs('print_barcode.*') ? 'active' : '' }}">
                                <i class="nav-icon fa-solid fa-cart-flatbed"></i>
                                <p>Persediaan <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('barcode.index') }}"
                                        class="nav-link {{ Request::routeIs('barcode.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Daftar Barcode</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('barang_master.index') }}"
                                        class="nav-link {{ Request::is('barang-master*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Barang & Jasa</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('print_barcode.index') }}"
                                        class="nav-link {{ Request::routeIs('print_barcode.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Reprint Barcode</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li
                            class="nav-item {{ Request::routeIs('pelanggan.*') || Request::routeIs('cashier.*') || Request::routeIs('pengiriman_pesanan.*') || Request::routeIs('retur_penjualan.*') || Request::routeIs('print_barcode_retur.*') ? 'menu-open' : '' }}">
                            <a href="#"
                                class="nav-link {{ Request::routeIs('pelanggan.*') || Request::routeIs('cashier.*') || Request::routeIs('pengiriman_pesanan.*') || Request::routeIs('retur_penjualan.*') || Request::routeIs('print_barcode_retur.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-tags"></i>
                                <p>Penjualan <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                <li class="nav-item">
                                    <a href="{{ route('cashier.index') }}"
                                        class="nav-link {{ Request::routeIs('cashier.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Pesanan Penjualan</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('pengiriman_pesanan.index') }}"
                                        class="nav-link {{ Request::routeIs('pengiriman_pesanan.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Pengiriman Pesanan</p>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::routeIs('retur_penjualan.*') || Request::routeIs('print_barcode_retur.*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ Request::routeIs('retur_penjualan.*') || Request::routeIs('print_barcode_retur.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Retur <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview">
                                        <li class="nav-item">
                                            <a href="{{ route('retur_penjualan.index') }}"
                                                class="nav-link {{ Request::routeIs('retur_penjualan.*') ? 'active' : '' }}">
                                                <i class="fas fa-minus nav-icon"></i>
                                                <p>Retur Penjualan</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="{{ route('print_barcode_retur.index') }}"
                                                class="nav-link {{ Request::routeIs('print_barcode_retur.*') ? 'active' : '' }}">
                                                <i class="fas fa-minus nav-icon"></i>
                                                <p>Print Barcode Retur</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>

                    </ul>
                </nav>
                <!-- /.sidebar-menu -->

            </div>
            <!-- /.sidebar -->
        </aside>

        <!-- Content Wrapper. Contains page content -->
        @yield('content')
        <!-- /.content-wrapper -->
        <footer class="main-footer text-center">
            <strong>
                Copyright &copy; {{ date('Y') }} <!-- Menggunakan Blade untuk mendapatkan tahun saat ini -->
                <a href="#" target="_blank">
                    <span style="color: #08332C;">TAKA TEXTILES</span>
                </a>.
            </strong>
            All rights reserved.
        </footer>

    </div>
    <!-- ./wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="{{ asset('lte/plugins/jquery/jquery.min.js') }}"></script>
    <!-- jQuery UI 1.11.4 -->
    <script src="{{ asset('lte/plugins/jquery-ui/jquery-ui.min.js') }}"></script>
    <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
    <script>
        $.widget.bridge('uibutton', $.ui.button)
    </script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('lte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- ChartJS -->
    <script src="{{ asset('lte/plugins/chart.js/Chart.min.js') }}"></script>
    <!-- Sparkline -->
    <script src="{{ asset('lte/plugins/sparklines/sparkline.js') }}"></script>
    <!-- JQVMap -->
    <script src="{{ asset('lte/plugins/jqvmap/jquery.vmap.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/jqvmap/maps/jquery.vmap.usa.js') }}"></script>
    <!-- jQuery Knob Chart -->
    <script src="{{ asset('lte/plugins/jquery-knob/jquery.knob.min.js') }}"></script>
    <!-- Bootstrap Switch -->
    <script src="{{ asset('lte/plugins/bootstrap-switch/js/bootstrap-switch.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="{{ asset('lte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <!-- Summernote -->
    <script src="{{ asset('lte/plugins/summernote/summernote-bs4.min.js') }}"></script>
    <!-- overlayScrollbars -->
    <script src="{{ asset('lte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>

    <!-- DataTables  & Plugins -->
    <script src="{{ asset('lte/plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
    <!-- Select2 -->
    <script src="{{ asset('lte/plugins/select2/js/select2.full.min.js') }}"></script>
    <!-- Bootstrap4 Duallistbox -->
    <script src="{{ asset('lte/plugins/bootstrap4-duallistbox/jquery.bootstrap-duallistbox.min.js') }}"></script>
    <!-- InputMask -->
    <script src="{{ asset('lte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/inputmask/jquery.inputmask.min.js') }}"></script>
    <!-- BS-Stepper -->
    <script src="{{ asset('lte/plugins/bs-stepper/js/bs-stepper.min.js') }}"></script>
    <!-- dropzonejs -->
    <script src="{{ asset('lte/plugins/dropzone/min/dropzone.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('lte/dist/js/adminlte.min.js') }}"></script>
    <!-- AdminLTE for demo purposes -->
    <script src="{{ asset('lte/dist/js/demo.js') }}"></script>
    <!-- Bootstrap Switch -->
    <script src="{{ asset('lte/plugins/bootstrap-switch/js/bootstrap-switch.min.js') }}"></script>

    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.js"></script>

    <script src="https://kit.fontawesome.com/de9d16bb0f.js" crossorigin="anonymous"></script>

    <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
    <script src="{{ asset('lte/dist/js/pages/dashboard3.js') }}"></script>

    <!-- OPTIONAL SCRIPTS -->
    <script src="plugins/chart.js/Chart.min.js"></script>

    <!-- JavaScript to update version with real-time date and time -->
    <script>
        function updateDateTime() {
            const dateTimeElement = document.getElementById('datetime');
            if (dateTimeElement) {
                const now = new Date();
                const daysOfWeek = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const dayOfWeek = daysOfWeek[now.getDay()];
                const dayOfMonth = now.getDate().toString().padStart(2, '0');
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                const year = now.getFullYear();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const dateTimeString = `${dayOfWeek}, ${dayOfMonth}-${month}-${year} | ${hours}:${minutes}:${seconds}`;
                dateTimeElement.textContent = dateTimeString;
            }
        }

        // Call the function initially when the page loads
        document.addEventListener('DOMContentLoaded', function () {
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });

        $(document).ready(function () {
            let tables = [
                '#user',
                '#packing_list',
                '#packing_list_detail',
                '#perintah_stock_opname',
                '#barcode',
                '#faktur',
                '#faktur_detail',
                '#surat_jalan',
                '#surat_jalan_detail',
                '#barang_masuk',
                '#barang_master',
                '#pemasok',
                '#activityTable',
                '#pengiriman_pesanan',
                '#faktur_penjualan',
                '#pesanan_pembelian',
                '#penerimaan_barang',
                '#retur_pembelian',
                '#retur_penjualan'
            ];
            let initialized = 0;

            function initializeDataTable(tableId) {
                if ($(tableId).length) {
                    $(tableId).DataTable({
                        "paging": true,
                        "responsive": false,
                        "lengthChange": true,
                        "autoWidth": false,
                        "scrollX": true,
                        "searching": true,
                        "ordering": true,
                        "info": true,
                        "buttons": ["copy", "colvis"]
                    }).on('init', function () {
                        initialized++;
                        if (initialized === tables.filter(id => $(id).length).length) {
                            hideLoading();
                        }
                    });
                    $(tableId + '_wrapper .col-md-6:eq(0)').length && $(tableId).DataTable().buttons().container()
                        .appendTo($(tableId + '_wrapper .col-md-6:eq(0)'));
                }
            }
            tables.forEach(initializeDataTable);
            // Jika tidak ada tabel, tetap hide loading agar tidak macet
            if (tables.filter(id => $(id).length).length === 0) {
                hideLoading();
            }
        });


        $(document).ready(function () {
            $('.select2').select2();
            $('.select2bs4').select2({
                theme: 'bootstrap4'
            });
        });

        //Bootstrap Duallistbox
        $('.duallistbox').bootstrapDualListbox()

        // BS-Stepper Init
        document.addEventListener('DOMContentLoaded', function () {
            window.stepper = new Stepper(document.querySelector('.bs-stepper'))
        })


        $(function () {
            $("input[data-bootstrap-switch]").each(function () {
                $(this).bootstrapSwitch('state', $(this).prop('checked'));
            })
        })

        // Loading Screen Functions
        function showLoading() {
            document.getElementById('loadingScreen').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingScreen').style.display = 'none';
        }

        // Show loading on page navigation
        document.addEventListener('DOMContentLoaded', function () {
            // Hide loading when page is fully loaded
            hideLoading();

            // Get current page URL to compare
            const currentPath = window.location.pathname;

            // Add loading to links that actually change pages
            const pageLinks = document.querySelectorAll('a[href]');
            pageLinks.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    const href = this.getAttribute('href');

                    // Only show loading for actual page changes
                    if (href &&
                        href !== '#' &&
                        !href.includes('javascript:') &&
                        !href.includes('void(0)') &&
                        !href.startsWith('mailto:') &&
                        !href.startsWith('tel:') &&
                        href !== currentPath &&
                        !this.hasAttribute('target')) { // Exclude links that open in new tab

                        // Check if it's a different page by comparing routes
                        const linkPath = href.startsWith('/') ? href : '/' + href;
                        if (linkPath !== currentPath) {
                            showLoading();
                        }
                    }
                });
            });

            // Add loading to form submissions that redirect to different pages
            const forms = document.querySelectorAll('form');
            forms.forEach(function (form) {
                form.addEventListener('submit', function () {
                    // Only show loading if form method is GET or if action leads to different page
                    const method = this.getAttribute('method');
                    const action = this.getAttribute('action');

                    if (!method || method.toLowerCase() === 'get' ||
                        (action && action !== window.location.pathname)) {
                        showLoading();
                    }
                });
            });

            // Hide loading if browser back/forward button is used
            window.addEventListener('pageshow', function (event) {
                hideLoading();
            });

            // Hide loading on any page load/refresh
            window.addEventListener('load', function () {
                hideLoading();
            });
        });

        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // Toast notifications
        @if(session('success'))
            const ToastSuccess = Swal.mixin({
                toast: true,
                position: 'top-end',
                icon: 'success',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
            });

            ToastSuccess.fire({
                title: "{{ session('success') }}"
            });
        @endif

            @if(session('error'))
                const ToastError = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 1000,
                    timerProgressBar: true,
                });

                ToastError.fire({
                    title: "{{ session('error') }}"
                });
            @endif

            @if(session('info'))
                const ToastInfo = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    showConfirmButton: false,
                    timer: 1000,
                    timerProgressBar: true,
                });

                ToastInfo.fire({
                    title: "{{ session('info') }}"
                });
            @endif

        // Sidebar Collapse/Expand Behavior (Table Resize & Persistence)
        $('[data-widget="pushmenu"]').on('collapsed.lte.pushmenu shown.lte.pushmenu', function () {
            const isCollapsed = $('body').hasClass('sidebar-collapse');
            localStorage.setItem('sidebar-state', isCollapsed ? 'collapsed' : 'expanded');

            // Force table redraw specifically for DataTables on sidebar toggle
            setTimeout(function () {
                $(window).trigger('resize');
                // Recalculate columns for all visible DataTables
                if ($.fn.DataTable) {
                    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
                }
            }, 400);
        });

        // "Lock" Sidebar Open on Menu Click (when currently collapsed)
        $(document).on('click', '.nav-sidebar .nav-link', function (e) {
            if ($('body').hasClass('sidebar-collapse')) {
                // If the sidebar is collapsed and you click a menu, expand it permanently
                $('[data-widget="pushmenu"]').PushMenu('expand');
                localStorage.setItem('sidebar-state', 'expanded');
            }
        });
    </script>
    @stack('scripts')
</body>

</html>