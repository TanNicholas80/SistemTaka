@extends('layout.main')

@section('content')
    <style>
        .modal-tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1rem;
        }

        .modal-tab {
            padding: 0.75rem 1rem;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .modal-tab.active {
            color: #d32f2f;
            border-bottom-color: #d32f2f;
        }

        .modal-tab:hover:not(.active) {
            color: #374151;
            border-bottom-color: #d1d5db;
        }

        .swal2-popup.swal2-modal.item-detail-modal-vcentered {
            width: 700px !important;
            padding: 0;
            border-radius: 8px;
            overflow: hidden;
        }

        .item-detail-modal-vcentered .swal2-header {
            background: transparent;
            border: none;
            padding: 0.5rem 1rem;
            position: relative;
        }

        .item-detail-modal-vcentered .swal2-close {
            color: #6b7280 !important;
            top: 10px !important;
            right: 10px !important;
        }

        .item-detail-modal-vcentered .swal2-close:hover {
            color: #1f2937 !important;
        }

        .item-detail-modal-vcentered .swal2-html-container {
            margin: 0 !important;
            padding: 0 1.5rem 1.5rem 1.5rem !important;
            width: 100%;
        }

        .item-detail-modal-vcentered .btn-lanjut-modal {
            background: #1a4b8c;
            color: white;
            padding: 8px 30px;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid #1a4b8c;
        }

        .item-detail-modal-vcentered .btn-lanjut-modal:hover {
            background: #1e3a8a;
        }
    </style>

    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Detail Penerimaan Barang</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('penerimaan-barang.index') }}">Penerimaan
                                    Barang</a></li>
                            <li class="breadcrumb-item active">Detail 1</li>
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
                                <div class="text-start">
                                    <h3 class="card-title mb-0"><b>No. Penerimaan Barang: {{ $penerimaanBarang->npb }}</b>
                                    </h3>
                                </div>
                            </div>

                            <div class="card-body">
                                @if(isset($errorMessage) && $errorMessage)
                                    <div class="alert alert-danger">
                                        <h5><i class="icon fas fa-ban"></i> Gagal Memuat Data!</h5>
                                        {{ $errorMessage }}
                                    </div>
                                @endif
                                <table class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Nama Barang</th>
                                            <th>Kode #</th>
                                            <th>Kuantitas</th>
                                            <th>Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            // Sort data by nama_barang ascending (A-Z)
                                            $sortedItems = collect($matchedItems)->sortBy('nama_barang')->values()->all();
                                            $detailItemsJson = json_encode(array_values($sortedItems));
                                        @endphp
                                        @foreach ($sortedItems as $index => $pb)
                                            <tr>
                                                <td><span role="button" tabindex="0" class="text-primary font-weight-bold"
                                                        style="cursor: pointer;"
                                                        onclick="openItemDetailModal({{ $index }})">{{ $pb['nama_barang'] ?? '-' }}</span>
                                                </td>
                                                <td>{{ $pb['kode_barang'] ?? '-' }}</td>
                                                <td>{{ number_format($pb['panjang_total'], 2) }}</td>
                                                <td>{{ $pb['unit'] ?? '-' }}</td>
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function updateTitle(pageTitle) {
            document.title = pageTitle;
        }

        updateTitle('Detail Penerimaan Barang');

        const detailItems = {!! $detailItemsJson ?? '[]' !!};
        const noPesanan = "{{ $penerimaanBarang->no_po ?? '-' }}";

        function openItemDetailModal(index) {
            const item = detailItems[index];
            if (!item) return;

            const itemNo = item.kode_barang || '';
            const itemName = item.nama_barang || 'Barang';
            const qty = item.panjang_total || 0;
            const keterangan = item.keterangan || '-';

            Swal.fire({
                title: '',
                customClass: {
                    popup: 'item-detail-modal-vcentered !rounded-xl',
                    confirmButton: 'btn-lanjut-modal',
                },
                showCloseButton: false,
                showConfirmButton: false,
                showDenyButton: false,
                confirmButtonText: 'Tutup',
                buttonsStyling: false,
                html: `
                    <div>
                        <div class="d-flex align-items-center justify-content-between px-4 pt-4" style="padding-left: 1.5rem; padding-right: 1.5rem; border-bottom: 2px solid #e5e7eb; margin-bottom: 1rem; position: relative;">
                            <div class="d-flex m-0" id="modal-tabs" style="border-bottom: none; margin-bottom: -2px;">
                                <div class="modal-tab active" data-tab="rincian">Rincian Barang</div>
                                <div class="modal-tab" data-tab="seri">No Seri/Produksi</div>
                            </div>
                            <button type="button" id="close-modal-btn" style="background: transparent; border: none; cursor: pointer; color: #9ca3af; padding: 0.5rem; margin-bottom: 0.25rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="px-4 pb-2 text-left" style="padding-left: 1.5rem; padding-right: 1.5rem; text-align: left;">
                            <!-- Rincian Tab -->
                            <div id="tab-content-rincian" class="tab-content">
                                <table class="table table-borderless table-sm m-0" style="width: 100%;">
                                    <tr>
                                        <td style="width: 150px; color: #4b5563; padding-bottom: 0.5rem; vertical-align: middle;">Kode #</td>
                                        <td style="font-weight: 600; color: #1d4ed8; padding-bottom: 0.5rem; vertical-align: middle;">${itemNo}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #4b5563; padding-bottom: 0.5rem; vertical-align: middle;">Nama Barang</td>
                                        <td style="font-weight: 600; padding-bottom: 0.5rem; vertical-align: middle;">${itemName}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #4b5563; padding-bottom: 1rem; vertical-align: middle;">Kuantitas</td>
                                        <td style="padding-bottom: 1rem; vertical-align: middle;">
                                            <span style="font-weight: 600;">${qty}</span> <span style="color: #6b7280;">${item.unit || '-'}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="border-top: 1px solid #e5e7eb; padding-top: 1rem;"></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #4b5563; padding-bottom: 0.5rem; vertical-align: middle;">Keterangan</td>
                                        <td style="font-weight: 600; color: #1f2937; padding-bottom: 0.5rem; vertical-align: middle;">${keterangan}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #4b5563; padding-bottom: 0.5rem; vertical-align: middle;">No. Pesanan</td>
                                        <td style="padding-bottom: 0.5rem; vertical-align: middle;">
                                            <div style="font-family: monospace; color: #15803d; font-weight: bold; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 2px 8px; border-radius: 4px; display: inline-block;">${noPesanan}</div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Seri Tab -->
                            <div id="tab-content-seri" class="tab-content" style="display: none;">
                                <div style="max-height: 240px; overflow-y: auto; border: 1px solid #e5e7eb;">
                                    <table class="table table-sm m-0" style="width: 100%; border-collapse: collapse;">
                                        <thead style="background-color: #166534; color: white;">
                                            <tr>
                                                <th style="padding: 8px; border: 1px solid #d1d5db; text-align: center; width: 60px; position: sticky; top: 0; background-color: #166534;">No</th>
                                                <th style="padding: 8px; border: 1px solid #d1d5db; text-align: left; position: sticky; top: 0; background-color: #166534;">Nomor #</th>
                                                <th style="padding: 8px; border: 1px solid #d1d5db; text-align: center; width: 100px; position: sticky; top: 0; background-color: #166534;">Kuantitas</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal-serial-table-body">
                                            <!-- render serials here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div id="modal-serial-summary" style="font-size: 0.75rem; font-weight: 600; color: #374151; margin-top: 0.5rem;">
                                    0 No Seri/Produksi, Jumlah 0
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                didOpen: () => {
                    const btnX = document.getElementById('close-modal-btn');
                    if (btnX) {
                        btnX.addEventListener('click', () => { Swal.close(); });
                    }

                    const tabs = document.querySelectorAll('.modal-tab[data-tab]');
                    const rincianContent = document.getElementById('tab-content-rincian');
                    const seriContent = document.getElementById('tab-content-seri');

                    tabs.forEach(tab => {
                        tab.addEventListener('click', () => {
                            tabs.forEach(t => {
                                t.classList.remove('active');
                                t.style.color = '#6b7280';
                                t.style.borderBottomColor = 'transparent';
                            });
                            tab.classList.add('active');
                            tab.style.color = '#dc2626';
                            tab.style.borderBottomColor = '#dc2626';

                            const target = tab.getAttribute('data-tab');
                            if (target === 'rincian') {
                                rincianContent.style.display = 'block';
                                seriContent.style.display = 'none';
                            } else {
                                rincianContent.style.display = 'none';
                                seriContent.style.display = 'block';
                            }
                        });
                    });

                    // Set initial styling for tabs directly since CSS classes might be overridden or missing in this view
                    tabs.forEach(t => {
                        if (t.classList.contains('active')) {
                            t.style.color = '#dc2626';
                            t.style.borderBottomColor = '#dc2626';
                        }
                    });

                    // Render serial table
                    const tbody = document.getElementById('modal-serial-table-body');
                    const serials = item.serial_numbers || [];
                    let totalQty = 0;

                    if (serials.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="3" style="padding: 8px; text-align: center; color: #6b7280; font-style: italic; border-bottom: 1px solid #e5e7eb;">Tidak ada data serial/produksi</td></tr>';
                    } else {
                        let html = '';
                        serials.forEach((sn, idx) => {
                            totalQty += parseFloat(sn.quantity || 0);
                            html += `
                            <tr style="transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f9fafb'" onmouseout="this.style.backgroundColor='transparent'">
                                <td style="padding: 8px; border: 1px solid #d1d5db; text-align: center; color: #6b7280;">${idx + 1}</td>
                                <td style="padding: 8px; border: 1px solid #d1d5db; font-weight: 500;">${sn.serialNumberNo || '-'}</td>
                                <td style="padding: 8px; border: 1px solid #d1d5db; text-align: center; font-weight: 600; color: #0f766e;">${sn.quantity || 0}</td>
                            </tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    document.getElementById('modal-serial-summary').innerHTML = `${serials.length} No Seri/Produksi, Jumlah ${totalQty.toFixed(2)}`;
                }
            });
        }
    </script>
@endsection