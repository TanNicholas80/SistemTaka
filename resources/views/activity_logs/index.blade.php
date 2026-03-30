@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Log Activity</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('barang_master.index') }}">Home</a></li>
                        <li class="breadcrumb-item active">Log Activity</li>
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
                        <div class="card-header">
                            <h3 class="card-title">Data Log Activity</h3>
                        </div>

                        <div class="card-body">
                            <table id="activityTable" class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>UserName</th>
                                        <th>Log Name</th>
                                        <th>Description</th>
                                        <th>Event</th>
                                        <th>Before</th>
                                        <th>After</th>
                                        <th>Log At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activities as $activity)
                                    <tr>
                                        <td>
                                            @if($activity->causer)
                                            {{ $activity->causer->username ?? 'N/A' }}
                                            @else
                                            <span class="text-muted">System</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $activity->log_name ?? 'N/A' }}
                                        </td>
                                        <td>{{ $activity->description ?? 'N/A' }}</td>
                                        <td>
                                            @php
                                            $eventType = $activity->properties['event_type'] ?? $activity->event ?? 'unknown';
                                            $badgeClass = '';
                                            switch($eventType) {
                                            case 'created':
                                            $badgeClass = 'badge-success';
                                            break;
                                            case 'updated':
                                            $badgeClass = 'badge-warning';
                                            break;
                                            case 'deleted':
                                            $badgeClass = 'badge-danger';
                                            break;
                                            default:
                                            $badgeClass = 'badge-secondary';
                                            }
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ ucfirst($eventType) }}</span>
                                        </td>
                                        <td>
                                            @if(isset($activity->properties['before_update']) && !empty($activity->properties['before_update']))
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                data-content="{{ json_encode($activity->properties['before_update']) }}"
                                                data-title="Data Sebelum Update"
                                                onclick="showData(this)">
                                                <i class="fas fa-eye"></i> Lihat
                                            </button>
                                            @elseif(isset($activity->properties['import_source']))
                                            <span class="badge badge-info">
                                                <i class="fas fa-file-csv"></i> {{ $activity->properties['import_source'] }}
                                            </span>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($activity->properties['after_update']) && !empty($activity->properties['after_update']))
                                            <button type="button" class="btn btn-sm btn-outline-success"
                                                data-content="{{ json_encode($activity->properties['after_update']) }}"
                                                data-title="Data Setelah Update"
                                                onclick="showData(this)">
                                                <i class="fas fa-eye"></i> Lihat
                                            </button>
                                            @elseif(isset($activity->properties['data_after_csv_import']))
                                            <button type="button" class="btn btn-sm btn-outline-success"
                                                data-content="{{ json_encode($activity->properties['data_after_csv_import']) }}"
                                                data-title="Data Setelah Import CSV"
                                                onclick="showData(this)">
                                                <i class="fas fa-file-csv"></i> Lihat CSV
                                            </button>
                                            @elseif(isset($activity->properties['created_data']))
                                            <button type="button" class="btn btn-sm btn-outline-success"
                                                data-content="{{ json_encode($activity->properties['created_data']) }}"
                                                data-title="Data yang Dibuat"
                                                onclick="showData(this)">
                                                <i class="fas fa-eye"></i> Lihat
                                            </button>
                                            @elseif(isset($activity->properties['deleted_data']))
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-content="{{ json_encode($activity->properties['deleted_data']) }}"
                                                data-title="Data yang Dihapus"
                                                onclick="showData(this)">
                                                <i class="fas fa-eye"></i> Lihat
                                            </button>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($activity->properties['timestamp_info']))
                                            <strong>{{ $activity->properties['timestamp_info']['action_date'] }}</strong><br>
                                            <small class="text-muted">{{ $activity->properties['timestamp_info']['action_time'] }}</small>
                                            @else
                                            {{ $activity->created_at->format('Y-m-d H:i:s') }}
                                            @endif
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

<!-- Modal untuk menampilkan data -->
<div class="modal fade" id="dataModal" tabindex="-1" role="dialog" aria-labelledby="dataModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataModalLabel">Detail Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="dataContent" class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
    function updateTitle(pageTitle) {
        document.title = pageTitle;
    }

    function showData(button) {
        const data = JSON.parse(button.getAttribute('data-content'));
        const title = button.getAttribute('data-title');

        document.getElementById('dataModalLabel').textContent = title;

        // Format data tanpa bracket untuk tampilan yang lebih bersih
        let formattedContent = '';

        if (typeof data === 'object' && data !== null) {
            Object.keys(data).forEach(key => {
                let value = data[key];

                // Format nilai berdasarkan tipe data
                if (value === null || value === undefined) {
                    value = '-';
                } else if (typeof value === 'object') {
                    value = JSON.stringify(value);
                } else if (typeof value === 'boolean') {
                    value = value ? 'Ya' : 'Tidak';
                } else if (typeof value === 'number') {
                    // Format angka dengan pemisah ribuan jika lebih dari 1000
                    if (value >= 1000) {
                        value = new Intl.NumberFormat('id-ID').format(value);
                    }
                }

                // Buat format key: value tanpa bracket
                const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                formattedContent += `${formattedKey}: ${value}\n`;
            });
        } else {
            formattedContent = data.toString();
        }

        document.getElementById('dataContent').textContent = formattedContent;

        $('#dataModal').modal('show');
    }

    updateTitle('Log Activity');
</script>
@endsection
