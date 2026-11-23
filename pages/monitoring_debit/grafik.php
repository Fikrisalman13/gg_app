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
.mode-badge   { font-size: .75rem; border-radius: 20px; }
#no-data-msg  { display: none; }
.param-select-wrap { position: relative; }
#param-loading { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); display: none; }

/* Preset Pills */
.preset-pill {
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 500;
    padding: .25rem .75rem;
    margin-right: .4rem;
    margin-bottom: .25rem;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
    color: #495057;
    cursor: pointer;
    transition: all .15s ease-in-out;
}
.preset-pill:hover {
    background: #e9ecef;
    color: #212529;
    border-color: #ced4da;
}
.preset-pill.active {
    background: #28a745;
    border-color: #28a745;
    color: #fff;
    box-shadow: 0 2px 5px rgba(40,167,69,.25);
}

/* Modern KPI Cards */
.kpi-card {
    background: #ffffff;
    border-radius: .5rem;
    border: 1px solid rgba(0,0,0,.08);
    padding: .9rem 1.1rem;
    position: relative;
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,.07) !important;
}
.kpi-card.kpi-primary { border-top: 3px solid #007bff; }
.kpi-card.kpi-warning { border-top: 3px solid #fd7e14; }
.kpi-card.kpi-danger  { border-top: 3px solid #dc3545; }
.kpi-card.kpi-success { border-top: 3px solid #28a745; }

.kpi-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #6c757d;
}
.kpi-value {
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1.2;
}
.kpi-unit {
    font-size: .85rem;
    font-weight: 600;
    color: #6c757d;
}
.kpi-footer {
    font-size: .75rem;
    color: #8898aa;
    margin-top: .3rem;
}
.kpi-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
}
.bg-primary-soft { background-color: rgba(0, 123, 255, 0.12); }
.bg-warning-soft { background-color: rgba(253, 126, 20, 0.12); }
.bg-danger-soft  { background-color: rgba(220, 53, 69, 0.12); }
.bg-success-soft { background-color: rgba(40, 167, 69, 0.12); }
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
                        <div class="col-xl-2 col-md-3 col-sm-6 mb-2">
                            <label class="font-weight-bold text-sm mb-1">Waktu Mulai</label>
                            <input type="datetime-local" id="f-start-date" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d\T00:00') ?>">
                        </div>
                        <div class="col-xl-2 col-md-3 col-sm-6 mb-2">
                            <label class="font-weight-bold text-sm mb-1">Waktu Selesai</label>
                            <input type="datetime-local" id="f-end-date" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-xl-3 col-md-3 col-sm-6 mb-2">
                            <label class="font-weight-bold text-sm mb-1">Stasiun</label>
                            <select id="f-station" class="form-control form-control-sm select2">
                                <option value="">— Semua Stasiun —</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-3 col-sm-6 mb-2">
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
                        <div class="col-xl-2 col-md-12 col-12 mb-2">
                            <button id="btn-chart" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm btn-block" style="height: 31px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chart-line mr-1"></i>Tampilkan
                            </button>
                        </div>
                    </div>

                    <!-- Quick Presets -->
                    <div class="pt-2 border-top mt-1 d-flex flex-wrap align-items-center">
                        <small class="font-weight-bold text-muted mr-3 mb-1"><i class="fas fa-bolt text-warning mr-1"></i>Preset Waktu Cepat:</small>
                        <div class="d-flex flex-wrap align-items-center mb-1">
                            <button type="button" class="preset-pill" data-preset="3h">3 Jam Terakhir</button>
                            <button type="button" class="preset-pill active" data-preset="today">Hari Ini</button>
                            <button type="button" class="preset-pill" data-preset="24h">24 Jam Terakhir</button>
                            <button type="button" class="preset-pill" data-preset="7d">7 Hari Terakhir</button>
                            <button type="button" class="preset-pill" data-preset="month">Bulan Ini</button>
                        </div>
                        <small class="text-muted ml-auto mb-1 d-none d-lg-inline"><i class="fas fa-info-circle mr-1"></i>Klik preset untuk langsung memuat data</small>
                    </div>
                </div>
            </div>

            <!-- Summary Stat Cards (Modern Minimalist) -->
            <div class="row mb-3" id="stat-cards" style="display:none">
                <!-- Nilai Terakhir -->
                <div class="col-6 col-lg-3 mb-2">
                    <div class="kpi-card kpi-primary shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="kpi-label" id="stat-lbl-last">Nilai Terakhir</span>
                            <div class="kpi-icon-wrap bg-primary-soft text-primary">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <span class="kpi-value text-primary" id="stat-val-last">—</span>
                            <span class="kpi-unit ml-1" id="stat-unit-last"></span>
                        </div>
                        <div class="kpi-footer text-truncate" id="stat-time-last">—</div>
                    </div>
                </div>

                <!-- Rata-rata Periode -->
                <div class="col-6 col-lg-3 mb-2">
                    <div class="kpi-card kpi-warning shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="kpi-label">Rata-rata Periode</span>
                            <div class="kpi-icon-wrap bg-warning-soft text-warning">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <span class="kpi-value text-warning" id="stat-val-avg">—</span>
                            <span class="kpi-unit ml-1" id="stat-unit-avg"></span>
                        </div>
                        <div class="kpi-footer text-truncate" id="stat-sub-avg">—</div>
                    </div>
                </div>

                <!-- Tertinggi (Max) -->
                <div class="col-6 col-lg-3 mb-2">
                    <div class="kpi-card kpi-danger shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="kpi-label">Tertinggi (Max)</span>
                            <div class="kpi-icon-wrap bg-danger-soft text-danger">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <span class="kpi-value text-danger" id="stat-val-max">—</span>
                            <span class="kpi-unit ml-1" id="stat-unit-max"></span>
                        </div>
                        <div class="kpi-footer text-truncate" id="stat-time-max">—</div>
                    </div>
                </div>

                <!-- Terendah (Min) -->
                <div class="col-6 col-lg-3 mb-2">
                    <div class="kpi-card kpi-success shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="kpi-label">Terendah (Min)</span>
                            <div class="kpi-icon-wrap bg-success-soft text-success">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <span class="kpi-value text-success" id="stat-val-min">—</span>
                            <span class="kpi-unit ml-1" id="stat-unit-min"></span>
                        </div>
                        <div class="kpi-footer text-truncate" id="stat-time-min">—</div>
                    </div>
                </div>
            </div>

            <!-- Chart Card -->
            <div class="card shadow-sm chart-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center py-2">
                    <h3 class="card-title my-1" id="chart-title">
                        <i class="fas fa-chart-line text-<?= htmlspecialchars($themeColor) ?> mr-1"></i>Grafik Debit
                    </h3>
                    <div class="d-flex align-items-center flex-wrap my-1">
                        <span id="sampling-badge" class="badge badge-secondary mode-badge mr-2 py-1 px-2" style="display:none"></span>
                        <span id="unit-badge"     class="badge badge-info mode-badge mr-2 py-1 px-2" style="display:none"></span>
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

    function fmtNum(n) {
        if (n === null || n === undefined) return '—';
        return Number(n).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDisplayDateTime(dtStr) {
        if (!dtStr) return '';
        var clean = dtStr.replace('T', ' ');
        var parts = clean.split(' ');
        if (parts.length >= 2) {
            var dParts = parts[0].split('-');
            var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            var mIdx = parseInt(dParts[1], 10) - 1;
            var monthName = months[mIdx] || dParts[1];
            return dParts[2] + ' ' + monthName + ' ' + dParts[0] + ' ' + parts[1].substring(0, 5);
        }
        return clean;
    }

    function fmtTime(t, station) {
        if (!t) return '—';
        var parts = t.split(' ');
        var timeStr = t;
        if (parts.length === 2) {
            var dParts = parts[0].split('-');
            var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            var mIdx = parseInt(dParts[1], 10) - 1;
            timeStr = dParts[2] + ' ' + (months[mIdx] || dParts[1]) + ' ' + parts[1].substring(0, 5);
        }
        return (station ? '<span class="text-dark font-weight-bold">' + station + '</span> &bull; ' : '') + timeStr;
    }

    function renderChart(data) {
        var $container = $('#chart-container');
        var $noData    = $('#no-data-msg');
        var $sampBadge = $('#sampling-badge');
        var $unitBadge = $('#unit-badge');
        var $btnDl     = $('#btn-download');
        var $chartInfo = $('#chart-info');
        var $statCards = $('#stat-cards');

        if (!data.labels || !data.labels.length || !data.datasets || !data.datasets.length) {
            $container.hide();
            $noData.show();
            $sampBadge.hide();
            $unitBadge.hide();
            $statCards.hide();
            $btnDl.prop('disabled', true);
            $chartInfo.text('Tidak ada titik data numerik untuk digambar.');
            return;
        }

        $container.show();
        $noData.hide();

        // Update Kartu Statistik Ringkasan Modern
        if (data.summary) {
            $statCards.show();
            var s = data.summary;
            var isSingleStation = s.stations_count === 1;
            var unitStr = data.unit ? data.unit : '';

            $('#stat-lbl-last').text(isSingleStation ? 'Nilai Terakhir' : 'Pembacaan Terkini');
            if (s.latest) {
                $('#stat-val-last').text(fmtNum(s.latest.value));
                $('#stat-unit-last').text(unitStr);
                $('#stat-time-last').html('<i class="far fa-calendar-check mr-1 text-muted"></i>' + fmtTime(s.latest.time, !isSingleStation ? s.latest.station : ''));
            } else {
                $('#stat-val-last').text('—');
                $('#stat-unit-last').text('');
                $('#stat-time-last').text('—');
            }

            if (s.avg !== null && s.avg !== undefined) {
                $('#stat-val-avg').text(fmtNum(s.avg));
                $('#stat-unit-avg').text(unitStr);
                $('#stat-sub-avg').html('<i class="fas fa-layer-group mr-1 text-muted"></i>' + s.count + ' titik data' + (!isSingleStation ? ' (' + s.stations_count + ' stasiun)' : ''));
            } else {
                $('#stat-val-avg').text('—');
                $('#stat-unit-avg').text('');
                $('#stat-sub-avg').text('—');
            }

            if (s.max) {
                $('#stat-val-max').text(fmtNum(s.max.value));
                $('#stat-unit-max').text(unitStr);
                $('#stat-time-max').html('<i class="far fa-clock mr-1 text-muted"></i>' + fmtTime(s.max.time, !isSingleStation ? s.max.station : ''));
            } else {
                $('#stat-val-max').text('—');
                $('#stat-unit-max').text('');
                $('#stat-time-max').text('—');
            }

            if (s.min) {
                $('#stat-val-min').text(fmtNum(s.min.value));
                $('#stat-unit-min').text(unitStr);
                $('#stat-time-min').html('<i class="far fa-clock mr-1 text-muted"></i>' + fmtTime(s.min.time, !isSingleStation ? s.min.station : ''));
            } else {
                $('#stat-val-min').text('—');
                $('#stat-unit-min').text('');
                $('#stat-time-min').text('—');
            }
        } else {
            $statCards.hide();
        }

        $sampBadge.text(modeLabels[data.mode] || data.mode).show();
        if (data.unit) {
            $unitBadge.text('Unit: ' + data.unit).show();
        } else {
            $unitBadge.hide();
        }

        var totalPoints = data.datasets.reduce(function (s, d) {
            return s + (d.data ? d.data.filter(function (v) { return v !== null && v !== undefined; }).length : 0);
        }, 0);

        var diffLabel = data.diff_text ? data.diff_text : (data.diff_days + ' hari');
        $chartInfo.html('Total titik data: <strong>' + totalPoints + '</strong> | Rentang: <strong>' + diffLabel + '</strong>');

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        var canvas = document.getElementById('debit-chart');
        var ctx = canvas.getContext('2d');

        // Susun dataset (tambahkan Garis Rata-rata horizontal bila tersedia)
        var chartDatasets = data.datasets.slice();
        if (data.summary && data.summary.avg !== null && data.labels.length > 0) {
            chartDatasets.push({
                label: 'Rata-rata (' + Number(data.summary.avg).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + (data.unit ? ' ' + data.unit : '') + ')',
                data: new Array(data.labels.length).fill(data.summary.avg),
                borderColor: '#fd7e14',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [6, 6],
                pointRadius: 0,
                pointHoverRadius: 0,
                fill: false,
                tension: 0
            });
        }

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels:   data.labels,
                datasets: chartDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 14,
                        fontSize: 12,
                        usePointStyle: true,
                        padding: 15
                    }
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(30, 41, 59, 0.92)',
                    titleFontSize: 12,
                    bodyFontSize: 12,
                    xPadding: 12,
                    yPadding: 10,
                    cornerRadius: 6,
                    callbacks: {
                        title: function (tooltipItems) {
                            if (!tooltipItems.length) return '';
                            var raw = tooltipItems[0].xLabel;
                            if (!raw) return '';
                            var parts = raw.split(' ');
                            if (parts.length === 2) {
                                var d = parts[0].split('-');
                                var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                                var mIdx = parseInt(d[1], 10) - 1;
                                return d[2] + ' ' + (months[mIdx] || d[1]) + ' ' + d[0] + ', ' + parts[1] + ' WIB';
                            }
                            return raw;
                        },
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
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            fontSize: 11,
                            autoSkip: true,
                            maxTicksLimit: 12,
                            callback: function (val) {
                                if (!val) return '';
                                var parts = val.split(' ');
                                if (parts.length === 2) {
                                    var dParts = parts[0].split('-');
                                    var timeStr = parts[1].substring(0, 5);
                                    if (data.mode === 'raw' || data.diff_days <= 1) {
                                        return timeStr;
                                    } else if (data.diff_days <= 7) {
                                        return dParts[2] + '/' + dParts[1] + ' ' + timeStr;
                                    } else {
                                        return dParts[2] + '/' + dParts[1];
                                    }
                                }
                                return val;
                            }
                        }
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

        var displayStart = formatDisplayDateTime(startDate);
        var displayEnd   = formatDisplayDateTime(endDate);

        $('#chart-title').html(
            '<i class="fas fa-chart-line text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>' +
            '<span>' + (parameterName || 'Semua Parameter') + '</span>' +
            '<small class="text-muted ml-2 font-weight-normal"><i class="far fa-calendar-alt mr-1"></i>' + displayStart + ' &mdash; ' + displayEnd + '</small>'
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

    $('#btn-chart').on('click', function () {
        fetchChart();
    });

    // Helper datetime-local formatting
    function padZero(num) {
        return num < 10 ? '0' + num : num;
    }

    function formatDatetimeLocal(d) {
        var year  = d.getFullYear();
        var month = padZero(d.getMonth() + 1);
        var day   = padZero(d.getDate());
        var hour  = padZero(d.getHours());
        var min   = padZero(d.getMinutes());
        return year + '-' + month + '-' + day + 'T' + hour + ':' + min;
    }

    // Quick Presets Handler
    $('.preset-pill').on('click', function () {
        $('.preset-pill').removeClass('active');
        $(this).addClass('active');

        var preset = $(this).data('preset');
        var now = new Date();
        var start = new Date();

        if (preset === '3h') {
            start = new Date(now.getTime() - (3 * 3600 * 1000));
        } else if (preset === 'today') {
            start.setHours(0, 0, 0, 0);
        } else if (preset === '24h') {
            start = new Date(now.getTime() - (24 * 3600 * 1000));
        } else if (preset === '7d') {
            start = new Date(now.getTime() - (7 * 24 * 3600 * 1000));
        } else if (preset === 'month') {
            start.setDate(1);
            start.setHours(0, 0, 0, 0);
        }

        $('#f-start-date').val(formatDatetimeLocal(start));
        $('#f-end-date').val(formatDatetimeLocal(now));

        loadParameters(function () {
            fetchChart();
        });
    });

    $('#f-start-date, #f-end-date').on('input change', function () {
        $('.preset-pill').removeClass('active');
    });

    // Load initial parameters & chart
    loadParameters(function () {
        fetchChart();
    });
});
</script>
