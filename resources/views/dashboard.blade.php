@extends('layout.main')
@section('content')
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <div class="d-flex align-items-center">
                        <h1 class="m-0">Dashboard</h1>
                        <button type="button" class="btn btn-primary btn-sm ml-3" onclick="refreshCache()">
                            <i class="fas fa-sync-alt"></i> Refresh Data
                        </button>
                    </div>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a>Home</a></li>
                        <li class="breadcrumb-item active"><a>Dashboard</a></li>
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
                @if (Auth::user()->role != 'owner')
                <div class="col-12 col-sm-6 col-md-6">
                    <a href="{{ route('cashier.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-shopping-cart"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Total Barang
                                    Terjual</span>
                                <span class="info-box-number">{{ $totalPenjualan ?? '0' }}</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                    <a href="{{ route('barang-masuk.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-archive"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Total Barang
                                    Masuk</span>
                                <span class="info-box-number">{{ $totalBarangMasuk ?? '0' }}</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                    <a href="{{ route('packing-list.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-box-open"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Total Batch Barang
                                    Masuk (Packing List)</span>
                                <span class="info-box-number">{{ $totalPackingList ?? '0' }}</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                    <a href="{{ route('penerimaan-barang.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-truck-loading"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Total Batch
                                    Penerimaan Barang</span>
                                <span class="info-box-number">{{ $totalPenerimaanBarang ?? '0' }}</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                    <a href="{{ route('barcode.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-warehouse"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Total Stock
                                    Barang</span>
                                <span class="info-box-number">{{ $barangSiapJual ?? '0' }}</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                    <a href="{{ route('hasil_stock_opname.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-calendar-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Stock Opname
                                    Terakhir</span>
                                <span class="info-box-number">{{ $formattedTanggal ?? 'dd/mm/yyyy' }}</span>
                            </div>
                        </div>
                    </a>
                </div>
                @endif

                @if (Auth::user()->role == 'owner')
                <div class="col-12 col-sm-4 col-md-4">
                    <a href="{{ route('cashier.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-shopping-cart"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Total Barang
                                    Terjual</span>
                                <span class="info-box-number">{{ $totalPenjualan ?? '0' }}</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-4 col-md-4">
                    <a href="{{ route('barang_master.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-warehouse"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Total Stock Barang</span>
                                <span class="info-box-number">{{ $totalBarangAccurate ?? '0' }} Barang ({{ $totalAvailableToSell ?? '0' }} Meter)</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-4 col-md-4">
                    <a href="{{ route('hasil_stock_opname.index')}}">
                        <div class="info-box">
                            <span class="info-box-icon elevation-1"><i class="fas fa-calendar-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" style="color: black; font-weight: bold;">Stock Opname
                                    Terakhir</span>
                                <span class="info-box-number">{{ $formattedTanggal ?? 'dd/mm/yyyy' }}</span>
                            </div>
                        </div>
                    </a>
                </div>
                @endif

                <div class="col-12 col-sm-6 col-md-6">
                    <div class="card">
                        <div class="card-header border-0">
                            <div class="d-flex justify-content-between">
                                <h3 class="card-title">Panjang Barang Terjual</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex">
                                <p class="d-flex flex-column">
                                    <span class="text-bold text-lg">{{ number_format($totalPanjangKeseluruhan ?? 0, 2) }} Meter</span>
                                    <span>Total Panjang Terjual</span>
                                </p>
                                <p class="ml-auto d-flex flex-column text-right">
                                    <span class="{{ ($persentasePertumbuhanPanjang ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                        <i class="fas fa-arrow-{{ ($persentasePertumbuhanPanjang ?? 0) >= 0 ? 'up' : 'down' }}"></i>
                                        {{ number_format(abs($persentasePertumbuhanPanjang ?? 0), 1) }}%
                                    </span>
                                    <span class="text-muted">Dari bulan lalu</span>
                                </p>
                            </div>

                            <div class="position-relative mb-4">
                                <canvas id="length-chart-dashboard" height="200"></canvas>
                            </div>

                            <div class="d-flex flex-row justify-content-end">
                                <span class="mr-2">
                                    <i class="fas fa-circle text-primary"></i> Panjang Bulanan (Meter)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                    <div class="card">
                        <div class="card-header border-0">
                            <div class="d-flex justify-content-between">
                                <h3 class="card-title">Pendapatan</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex">
                                <p class="d-flex flex-column">
                                    <span class="text-bold text-lg">
                                        Rp {{ number_format($totalAmountKeseluruhan ?? 0, 0, ',', '.') }}
                                    </span>
                                    <span>Total Penjualan Keseluruhan</span>
                                </p>
                                <p class="ml-auto d-flex flex-column text-right">
                                    <span class="{{ ($persentasePertumbuhan ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                        <i class="fas fa-arrow-{{ ($persentasePertumbuhan ?? 0) >= 0 ? 'up' : 'down' }}"></i>
                                        {{ number_format(abs($persentasePertumbuhan ?? 0), 1) }}%
                                    </span>
                                    <span class="text-muted">Dari bulan lalu</span>
                                </p>
                            </div>

                            <div class="position-relative mb-4">
                                <canvas id="sales-chart-dashboard" height="200"></canvas>
                            </div>

                            <div class="d-flex flex-row justify-content-end">
                                <span class="mr-2">
                                    <i class="fas fa-circle text-primary"></i> Penjualan Bulanan
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>

<script>
    'use strict'

    function refreshCache() {
        const button = event.target.closest('button'); // Dapatkan elemen button
        button.disabled = true;

        const icon = button.querySelector('i');
        icon.classList.remove('fa-sync-alt');
        icon.classList.add('fa-spinner', 'fa-spin');

        // Pastikan route name 'dashboard' ada di web.php Anda
        window.location.href = '{{ route("dashboard") }}?force_refresh=1';
    }

    document.addEventListener('DOMContentLoaded', function() {
        // --- TAMBAHAN: Script untuk menyembunyikan alert otomatis ---        // Logika untuk menyembunyikan alert otomatis
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
            }, 7000); // Tampilkan sedikit lebih lama untuk dashboard (7 detik)
        }

        // Data dari controller Laravel (optimized)
        const chartData = JSON.parse('{!! addslashes(json_encode($chartData ?? [])) !!}');
        const chartDataPanjang = JSON.parse('{!! addslashes(json_encode($chartDataPanjang ?? [])) !!}');
        const chartLabels = JSON.parse('{!! addslashes(json_encode($chartLabels ?? [])) !!}');

        var ticksStyle = {
            fontColor: '#495057',
            fontStyle: 'bold'
        }

        var mode = 'index'
        var intersect = false

        // Sales Chart
        var salesChartCanvas = document.getElementById('sales-chart-dashboard');
        if (!salesChartCanvas) {
            console.error('Canvas element with id "sales-chart-dashboard" not found!');
            return;
        }

        var salesChart = new Chart(salesChartCanvas, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Penjualan (Rp)',
                    data: chartData,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#007bff',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#007bff',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: mode,
                        intersect: intersect,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#007bff',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return 'Penjualan: Rp ' + formatNumber(context.parsed.y);
                            },
                            title: function(tooltipItems) {
                                return 'Bulan ' + tooltipItems[0].label;
                            }
                        }
                    }
                },
                hover: {
                    mode: mode,
                    intersect: intersect
                },
                scales: {
                    yAxes: [{
                        display: true,
                        gridLines: {
                            display: false
                        },
                        ticks: $.extend({
                            beginAtZero: true,
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }, ticksStyle)
                    }],
                    xAxes: [{
                        display: true,
                        gridLines: {
                            display: false
                        },
                        ticks: ticksStyle
                    }]
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Length Chart
        var lengthChartCanvas = document.getElementById('length-chart-dashboard');
        if (lengthChartCanvas) {
            var lengthChart = new Chart(lengthChartCanvas, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Panjang Terjual (Meter)',
                        data: chartDataPanjang,
                        backgroundColor: '#007bff',
                        borderColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: mode,
                            intersect: intersect,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#28a745',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    return 'Panjang: ' + formatNumber(context.parsed.y) + ' Meter';
                                },
                                title: function(tooltipItems) {
                                    return 'Bulan ' + tooltipItems[0].label;
                                }
                            }
                        }
                    },
                    hover: {
                        mode: mode,
                        intersect: intersect
                    },
                    scales: {
                        yAxes: [{
                            display: true,
                            gridLines: {
                                drawOnChartArea: false,
                                drawTicks: false
                            },
                            ticks: $.extend({
                                beginAtZero: true,
                                callback: function(value) {
                                    return formatNumber(value) + ' M';
                                }
                            }, ticksStyle)
                        }],
                        xAxes: [{
                            display: true,
                            gridLines: {
                                drawOnChartArea: false,
                                drawTicks: false
                            },
                            ticks: ticksStyle
                        }]
                    },
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        // Helper functions
        function formatNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        }

        function formatCurrency(value) {
            if (value >= 1000000000) {
                return 'Rp ' + (value / 1000000000).toFixed(1) + 'M';
            } else if (value >= 1000000) {
                return 'Rp ' + (value / 1000000).toFixed(1) + 'Jt';
            } else if (value >= 1000) {
                return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
            }
            return 'Rp ' + formatNumber(value);
        }

        // Chart update functions (for future use)
        window.updateSalesChart = function(newData, newLabels) {
            salesChart.data.datasets[0].data = newData;
            salesChart.data.labels = newLabels;
            salesChart.update();
        };

        window.updateLengthChart = function(newData, newLabels) {
            if (typeof lengthChart !== 'undefined') {
                lengthChart.data.datasets[0].data = newData;
                lengthChart.data.labels = newLabels;
                lengthChart.update();
            }
        };
    });
</script>
@endsection