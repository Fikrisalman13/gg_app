<?php
/**
 * grafik.php - Grafik Monitoring Debit
 * Line chart interaktif Chart.js 2.9.4 (AdminLTE local), filter tanggal + stasiun + parameter.
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

<style>
.filter-card  { border-radius: .45rem; }
.chart-card   { border-radius: .45rem; min-height: 420px; }
.chart-wrap   { position: relative; height: 420px; }
.mode-badge   { font-size: .75rem; }
#no-data-msg  { display: none; }
.param-select-wrap { position: relative; }
#param-loading { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); display: none; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-chart-line text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>Grafik Monitoring Debit</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <small class="text-muted"><i class="far fa-clock mr-1"></i><?= date('d M Y, H:i') ?></small>
                </div>
            </div>
            <ol class="breadcrumb bg-transparent p-0 mb-0">
                <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="/gg_app/pages/monitoring_debit/index.php">Monitoring Debit</a></li>
                <li class="breadcrumb-item active">Grafik</li>
            </ol>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Filter -->
            <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?> shadow-sm filter-card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i>Filter Grafik</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-sm-2 col-6 mb-2">
                            <label class="font-weight-bold text-sm mb-1">Tanggal Mulai</label>
                            <input type="date" id="f-start-date" class="form-control form-control-sm"
                                   value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-sm-2 col-6 mb-2">
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
                            <label class="font-weight-bold text-sm mb-1">Parameter</label>
                            <div class="param-select-wrap">
                                <select id="f-parameter" class="form-control form-control-sm select2">
                                    <option value="">— Semua / Pilih Parameter —</option>
                                </select>
                                <span id="param-loading">
                                    <i class="fas fa-spinner fa-spin text-muted" style="font-size:.75rem"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-2 col-12 mb-2">
                            <button id="btn-chart" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm btn-block">
                                <i class="fas fa-chart-line mr-1"></i>Tampilkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Card -->
            <div class="card shadow-sm chart-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0" id="chart-title">
                        <i class="fas fa-chart-line mr-1"></i>Grafik Debit
                    </h3>
                    <div class="d-flex align-items-center">
                        <span id="sampling-badge" class="badge badge-secondary mode-badge mr-2" style="display:none"></span>
                        <span id="unit-badge"     class="badge badge-info mode-badge mr-2" style="display:none"></span>
                        <button id="btn-download" class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="fas fa-download mr-1"></i>Download PNG
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chart-loading" class="text-center py-5" style="display:none">
                        <div class="spinner-border text-<?= htmlspecialchars($themeColor) ?>"></div>
                        <p class="mt-2 text-muted">Memuat data grafik...</p>
                    </div>
                    <div id="no-data-msg" class="alert alert-warning text-center" style="display:none">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Tidak ada data nilai debit pada rentang yang dipilih.</strong><br>
                        <small class="text-muted">Jika stasiun berstatus Timeout (NULL), grafik belum dapat membentuk garis. Coba pilih rentang yang mencakup tanggal 16 November atau stasiun PC-DESKTOP-3L56DM4.</small>
                    </div>
                    <div class="chart-wrap" id="chart-container">
                        <canvas id="debit-chart"></canvas>
                    </div>
                </div>
                <div class="card-footer py-2">
                    <small class="text-muted" id="chart-info">
                        Pilih filter lalu klik <strong>Tampilkan</strong> untuk memuat grafik.
                    </small>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<!-- Chart.js 2.9.4 Local from AdminLTE (Loaded AFTER footer.php) -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.bundle.min.js"></script>

<script>
$(function () {
    'use strict';

    var chartInstance = null;
    var modeLabels = {
        'raw':    'Sampling: Real-time',
        'hourly': 'Sampling: Per Jam (Rata-rata)',
        'daily':  'Sampling: Per Hari (Rata-rata)'
    };

    // Load stasiun dropdown
    $.getJSON('/gg_app/pages/monitoring_debit/ajax_stations.php', function (resp) {
        if (resp && resp.status === 'ok') {
            resp.data.forEach(function (s) {
                $('#f-station').append(
                    $('<option>').val(s.station_id).text(s.station_name + ' (' + s.station_id + ')')
                );
            });
            if ($.fn.select2) {
                $('#f-station').select2({ theme: 'bootstrap4', placeholder: '— Semua Stasiun —', allowClear: true, width: '100%' });
            }
        }
    });

    // Load daftar parameter
    function loadParameters(callback) {
        var stationId = $('#f-station').val();
        var startDate = $('#f-start-date').val();
        var endDate   = $('#f-end-date').val();

        $('#param-loading').show();

        var url = '/gg_app/pages/monitoring_debit/ajax_parameters.php?' +
            'start_date=' + encodeURIComponent(startDate) +
            '&end_date='  + encodeURIComponent(endDate) +
            (stationId ? '&station_id=' + encodeURIComponent(stationId) : '');

        $.getJSON(url, function (resp) {
            var $sel = $('#f-parameter');
            $sel.empty().append('<option value="">— Semua / Pilih Parameter —</option>');
            if (resp && resp.status === 'ok' && resp.data.length) {
                resp.data.forEach(function (p) {
                    $sel.append($('<option>').val(p.parameter_name).text(p.parameter_name + (p.unit ? ' (' + p.unit + ')' : '')));
                });
                // Default ke parameter pertama jika ada
                if (resp.data.length > 0) {
                    $sel.val(resp.data[0].parameter_name);
                }
            }
            if ($.fn.select2) {
                $sel.select2({ theme: 'bootstrap4', placeholder: '— Pilih Parameter —', allowClear: true, width: '100%' });
            }
            if (typeof callback === 'function') callback();
        }).always(function () {
            $('#param-loading').hide();
        });
    }

    function renderChart(data) {
        var $container = $('#chart-container');
        var $noData    = $('#no-data-msg');
        var $sampBadge = $('#sampling-badge');
        var $unitBadge = $('#unit-badge');
        var $btnDl     = $('#btn-download');
        var $chartInfo = $('#chart-info');

        if (!data.labels || !data.labels.length || !data.datasets || !data.datasets.length) {
            $container.hide();
            $noData.show();
            $sampBadge.hide();
            $unitBadge.hide();
            $btnDl.prop('disabled', true);
            $chartInfo.text('Tidak ada titik data numerik untuk digambar.');
            return;
        }

        $container.show();
        $noData.hide();

        $sampBadge.text(modeLabels[data.mode] || data.mode).show();
        if (data.unit) {
            $unitBadge.text('Unit: ' + data.unit).show();
        } else {
            $unitBadge.hide();
        }

        var totalPoints = data.datasets.reduce(function (s, d) {
            return s + (d.data ? d.data.filter(function (v) { return v !== null && v !== undefined; }).length : 0);
        }, 0);

        $chartInfo.html('Total titik data: <strong>' + totalPoints + '</strong> | Rentang: <strong>' + data.diff_days + ' hari</strong>');

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        var canvas = document.getElementById('debit-chart');
        var ctx = canvas.getContext('2d');

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels:   data.labels,
                datasets: data.datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (tooltipItem, chartData) {
                            var dataset = chartData.datasets[tooltipItem.datasetIndex];
                            var val = tooltipItem.yLabel;
                            if (val === null || val === undefined) return dataset.label + ': -';
                            return dataset.label + ': ' + Number(val).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 4}) + (data.unit ? ' ' + data.unit : '');
                        }
                    }
                },
                hover: { mode: 'nearest', intersect: true },
                scales: {
                    xAxes: [{
                        display: true,
                        gridLines: { color: 'rgba(0,0,0,0.06)' },
                        ticks: { maxRotation: 45, fontSize: 11 }
                    }],
                    yAxes: [{
                        display: true,
                        gridLines: { color: 'rgba(0,0,0,0.08)' },
                        ticks: {
                            beginAtZero: false,
                            fontSize: 11,
                            callback: function (value) {
                                return Number(value).toLocaleString('id-ID', {maximumFractionDigits: 2}) + (data.unit ? ' ' + data.unit : '');
                            }
                        }
                    }]
                }
            }
        });

        $btnDl.prop('disabled', false).off('click').on('click', function () {
            var tempCanvas = document.createElement('canvas');
            tempCanvas.width = canvas.width;
            tempCanvas.height = canvas.height;
            var tctx = tempCanvas.getContext('2d');
            tctx.fillStyle = '#ffffff';
            tctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
            tctx.drawImage(canvas, 0, 0);

            var link = document.createElement('a');
            link.download = 'grafik_debit_' + new Date().toISOString().slice(0, 10) + '.png';
            link.href = tempCanvas.toDataURL('image/png');
            link.click();
        });
    }

    function fetchChart() {
        var startDate     = $('#f-start-date').val();
        var endDate       = $('#f-end-date').val();
        var stationId     = $('#f-station').val();
        var parameterName = $('#f-parameter').val();

        $('#chart-loading').show();
        $('#chart-container').hide();
        $('#no-data-msg').hide();
        $('#btn-download').prop('disabled', true);

        $('#chart-title').html(
            '<i class="fas fa-chart-line mr-1"></i>' +
            (parameterName || 'Semua Parameter') + ' — ' + startDate + ' s/d ' + endDate
        );

        var url = '/gg_app/pages/monitoring_debit/ajax_grafik.php?' +
            'start_date='     + encodeURIComponent(startDate) +
            '&end_date='      + encodeURIComponent(endDate) +
            (stationId     ? '&station_id='     + encodeURIComponent(stationId)     : '') +
            (parameterName ? '&parameter_name=' + encodeURIComponent(parameterName) : '');

        $.getJSON(url, function (data) {
            if (data && data.status === 'ok') {
                renderChart(data);
            } else {
                console.error('Ajax error:', data);
                renderChart({ labels: [], datasets: [] });
            }
        }).fail(function (xhr, err) {
            console.error('Fetch error:', err);
            renderChart({ labels: [], datasets: [] });
        }).always(function () {
            $('#chart-loading').hide();
        });
    }

    $('#f-station, #f-start-date, #f-end-date').on('change', function () {
        loadParameters();
    });

    $('#btn-chart').on('click', fetchChart);

    // Load initial parameters & chart
    loadParameters(function () {
        fetchChart();
    });
});
</script>
