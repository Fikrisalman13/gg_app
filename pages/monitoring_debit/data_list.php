<?php
/**
 * data_list.php - List Data Monitoring Debit
 * Tabel server-side DataTables dengan filter tanggal & stasiun + export Excel.
 */
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 220;
if (isset($_SESSION['GroupId']) && (int)$_SESSION['GroupId'] !== 1) {
    requireView($conn, $menuId);
}
?>

<!-- DataTables Local CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<style>
.filter-card { border-radius: .45rem; }
.quality-badge { font-size: .72rem; font-weight: 600; padding: .22rem .5rem; border-radius: .25rem; }
#emds-table td { vertical-align: middle; font-size: .85rem; }
#emds-table th { font-size: .82rem; white-space: nowrap; }
.export-btn { white-space: nowrap; }
</style>

<div class="content-wrapper">
    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-table text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>Data Pembacaan Debit</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <small class="text-muted">
                        <i class="far fa-clock mr-1"></i><?= date('d M Y, H:i') ?>
                    </small>
                </div>
            </div>
            <ol class="breadcrumb bg-transparent p-0 mb-0">
                <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="/gg_app/pages/monitoring_debit/index.php">Monitoring Debit</a></li>
                <li class="breadcrumb-item active">List Data</li>
            </ol>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Filter Card -->
            <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?> shadow-sm filter-card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i>Filter Data</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-sm-3 col-6 mb-2">
                            <label class="font-weight-bold text-sm mb-1">Tanggal Mulai</label>
                            <input type="date" id="f-start-date" class="form-control form-control-sm"
                                   value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-sm-3 col-6 mb-2">
                            <label class="font-weight-bold text-sm mb-1">Tanggal Selesai</label>
                            <input type="date" id="f-end-date" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-sm-3 col-6 mb-2">
                            <label class="font-weight-bold text-sm mb-1">Stasiun</label>
                            <select id="f-station" class="form-control form-control-sm select2">
                                <option value="">— Semua Stasiun —</option>
                            </select>
                        </div>
                        <div class="col-sm-3 col-6 mb-2">
                            <button id="btn-filter" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm btn-block">
                                <i class="fas fa-search mr-1"></i>Tampilkan
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="export-info">Periode: <?= date('01/m/Y') ?> s/d <?= date('d/m/Y') ?> | Semua Stasiun</small>
                    <form id="export-form" method="POST" action="/gg_app/pages/monitoring_debit/export_excel.php" target="_blank">
                        <input type="hidden" name="start_date" id="exp-start" value="<?= date('Y-m-01') ?>">
                        <input type="hidden" name="end_date"   id="exp-end" value="<?= date('Y-m-d') ?>">
                        <input type="hidden" name="station_id" id="exp-station" value="">
                        <button type="submit" class="btn btn-success btn-sm export-btn">
                            <i class="fas fa-file-excel mr-1"></i>Export Excel (.xlsx)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Data Table Card -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="emds-table" class="table table-sm table-hover table-bordered mb-0 w-100">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Waktu Capture</th>
                                    <th>Stasiun</th>
                                    <th>Device</th>
                                    <th>Parameter</th>
                                    <th class="text-right">Nilai</th>
                                    <th>Satuan</th>
                                    <th>Kualitas</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<!-- DataTables Local JS (Loaded AFTER footer.php so jQuery is available) -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
$(function () {
    var currentParams = {
        start_date: $('#f-start-date').val(),
        end_date:   $('#f-end-date').val(),
        station_id: ''
    };

    var qualityMap = {
        'fresh':    ['success',   'Segar'],
        'stale':    ['warning',   'Lama'],
        'timeout':  ['danger',    'Timeout'],
        'invalid':  ['danger',    'Invalid'],
        'isolated': ['secondary', 'Isolated']
    };

    function qualityBadge(q) {
        var m = qualityMap[q] || ['secondary', q || '-'];
        return '<span class="quality-badge badge badge-' + m[0] + '">' + m[1] + '</span>';
    }

    function formatDt(str) {
        if (!str) return '-';
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d.getTime())) return str;
        return d.toLocaleDateString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric'}) +
               ' ' + d.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }

    // Load stasiun dropdown
    $.getJSON('/gg_app/pages/monitoring_debit/ajax_stations.php', function (resp) {
        if (resp && resp.status === 'ok') {
            resp.data.forEach(function (s) {
                $('#f-station').append(
                    $('<option>').val(s.station_id).text(s.station_name + ' (' + s.station_id + ')')
                );
            });
            if ($.fn.select2) {
                $('#f-station').select2({
                    theme: 'bootstrap4',
                    placeholder: '— Semua Stasiun —',
                    allowClear: true,
                    width: '100%'
                });
            }
        }
    });

    // Init DataTable
    var table = $('#emds-table').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        language: {
            processing:    '<div class="spinner-border spinner-border-sm text-primary"></div> Memuat data...',
            search:        'Cari:',
            lengthMenu:    'Tampilkan _MENU_ baris',
            info:          'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty:     'Tidak ada data',
            infoFiltered:  '(difilter dari _MAX_ total)',
            zeroRecords:   'Tidak ada data ditemukan',
            paginate: { first:'«', last:'»', next:'›', previous:'‹' }
        },
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
        order: [[1, 'desc']],
        ajax: {
            url: '/gg_app/pages/monitoring_debit/ajax_data.php',
            type: 'GET',
            data: function (d) {
                d.start_date = currentParams.start_date;
                d.end_date   = currentParams.end_date;
                d.station_id = currentParams.station_id;
                return d;
            },
            error: function (xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
            }
        },
        columns: [
            {
                data: null, orderable: false, searchable: false,
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                },
                width: '45px', className: 'text-center'
            },
            {
                data: 'captured_at',
                render: function (data) { return formatDt(data); },
                className: 'text-nowrap'
            },
            { data: 'station_name' },
            { data: 'device_name' },
            { data: 'parameter_name' },
            {
                data: 'reading_value', className: 'text-right font-weight-bold',
                render: function (data) {
                    if (data === null || data === undefined) {
                        return '<span class="badge badge-light text-danger border"><i class="fas fa-exclamation-triangle mr-1"></i>Timeout (NULL)</span>';
                    }
                    return parseFloat(data).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:4});
                }
            },
            { data: 'unit', className: 'text-muted' },
            {
                data: 'quality',
                render: function (data) { return qualityBadge(data); },
                className: 'text-center'
            }
        ]
    });

    // Tombol filter
    $('#btn-filter').on('click', function () {
        currentParams.start_date = $('#f-start-date').val();
        currentParams.end_date   = $('#f-end-date').val();
        currentParams.station_id = $('#f-station').val() || '';

        // Update export form
        $('#exp-start').val(currentParams.start_date);
        $('#exp-end').val(currentParams.end_date);
        $('#exp-station').val(currentParams.station_id);

        $('#export-info').text(
            'Periode: ' + currentParams.start_date + ' s/d ' + currentParams.end_date +
            (currentParams.station_id ? ' | Stasiun: ' + currentParams.station_id : ' | Semua Stasiun')
        );

        table.ajax.reload();
    });
});
</script>
