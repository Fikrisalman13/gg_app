<?php
/**
 * index.php - Dashboard Monitoring Debit
 * Menampilkan statistik, status stasiun, dan pembacaan terbaru dari EMDS_SUM.
 * Auto-refresh via AJAX setiap 60 detik.
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
.emds-info-box {
    border-radius: .5rem;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    transition: transform .2s;
}
.emds-info-box:hover { transform: translateY(-2px); }
.station-badge { font-size: .72rem; font-weight: 600; padding: .28rem .55rem; border-radius: .3rem; }
.quality-badge { font-size: .72rem; font-weight: 600; padding: .22rem .5rem; border-radius: .25rem; }
.refresh-indicator { font-size: .78rem; color: #6c757d; }
.refresh-indicator .spinner-border { width: .9rem; height: .9rem; border-width: .15em; }
#last-refresh-time { font-size: .78rem; }
.station-card {
    border: 1px solid rgba(0,0,0,.08);
    border-radius: .45rem;
    padding: 1rem;
    background: #fff;
    transition: box-shadow .2s;
}
.station-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.1); }
.station-card .status-dot {
    width: .65rem; height: .65rem;
    border-radius: 50%; display: inline-block; margin-right: .35rem;
}
.station-card .status-dot.online  { background: #28a745; box-shadow: 0 0 0 .2rem rgba(40,167,69,.18); }
.station-card .status-dot.offline { background: #dc3545; }
</style>

<div class="content-wrapper">

    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-water text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>Monitoring Debit</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <span class="refresh-indicator mr-2">
                        <span class="spinner-border spinner-border-sm d-none" id="refresh-spinner"></span>
                        <i class="fas fa-sync-alt" id="refresh-icon"></i>
                        Auto-refresh 60s
                    </span>
                    <small class="text-muted" id="last-refresh-time"></small>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <ol class="breadcrumb bg-transparent p-0 mb-0">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Monitoring Debit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Shortcut Nav -->
            <div class="row mb-3">
                <div class="col-12">
                    <a href="/gg_app/pages/monitoring_debit/data_list.php" class="btn btn-outline-<?= htmlspecialchars($themeColor) ?> btn-sm mr-2">
                        <i class="fas fa-table mr-1"></i>List Data &amp; Export Excel
                    </a>
                    <a href="/gg_app/pages/monitoring_debit/grafik.php" class="btn btn-outline-<?= htmlspecialchars($themeColor) ?> btn-sm">
                        <i class="fas fa-chart-line mr-1"></i>Grafik Detail
                    </a>
                </div>
            </div>

            <!-- Info Boxes -->
            <div class="row" id="info-boxes">
                <!-- Total Stasiun -->
                <div class="col-6 col-md-3">
                    <div class="info-box emds-info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-broadcast-tower"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Stasiun</span>
                            <span class="info-box-number" id="stat-total-stations">—</span>
                        </div>
                    </div>
                </div>
                <!-- Stasiun Online -->
                <div class="col-6 col-md-3">
                    <div class="info-box emds-info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-success">
                            <i class="fas fa-wifi"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Stasiun Online</span>
                            <span class="info-box-number" id="stat-online-stations">—</span>
                        </div>
                    </div>
                </div>
                <!-- Pembacaan Hari Ini -->
                <div class="col-6 col-md-3">
                    <div class="info-box emds-info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-warning">
                            <i class="fas fa-database"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pembacaan Hari Ini</span>
                            <span class="info-box-number" id="stat-readings-today">—</span>
                        </div>
                    </div>
                </div>
                <!-- Rata-rata 24 jam -->
                <div class="col-6 col-md-3">
                    <div class="info-box emds-info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-info">
                            <i class="fas fa-tachometer-alt"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Rata-rata 24 Jam</span>
                            <span class="info-box-number" id="stat-avg-24h">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">

                <!-- Status Stasiun -->
                <div class="col-lg-5">
                    <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?> shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-broadcast-tower mr-1"></i>Status Stasiun</h3>
                        </div>
                        <div class="card-body p-2" id="station-cards">
                            <div class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                <span class="ml-2">Memuat data stasiun...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pembacaan Terbaru -->
                <div class="col-lg-7">
                    <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?> shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-history mr-1"></i>10 Pembacaan Terbaru</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" id="recent-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Stasiun</th>
                                            <th>Parameter</th>
                                            <th class="text-right">Nilai</th>
                                            <th>Kualitas</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-tbody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                                <span class="ml-2">Memuat data pembacaan...</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-right py-2">
                            <a href="/gg_app/pages/monitoring_debit/data_list.php" class="btn btn-sm btn-outline-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-table mr-1"></i>Lihat Semua Data (Tabel Lengkap)
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /.row -->

        </div><!-- /.container-fluid -->
    </section>
</div><!-- /.content-wrapper -->

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script>
(function () {
    'use strict';

    var REFRESH_URL = '/gg_app/pages/monitoring_debit/ajax_dashboard.php';
    var REFRESH_INTERVAL = 60000; // 60 detik

    var qualityMap = {
        'fresh':    ['success', 'Segar'],
        'stale':    ['warning', 'Lama'],
        'timeout':  ['danger',  'Timeout'],
        'invalid':  ['danger',  'Invalid'],
        'isolated': ['secondary','Isolated']
    };

    function qualityBadge(q) {
        var m = qualityMap[q] || ['secondary', q || '-'];
        return '<span class="quality-badge badge badge-' + m[0] + '">' + m[1] + '</span>';
    }

    function formatDt(str) {
        if (!str) return '-';
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d.getTime())) return str;
        return d.toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric'}) +
               ' ' + d.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }

    function timeAgo(str) {
        if (!str) return '-';
        var diff = Math.floor((Date.now() - new Date(str.replace(' ', 'T')).getTime()) / 1000);
        if (diff < 60)  return diff + ' dtk lalu';
        if (diff < 3600) return Math.floor(diff/60) + ' mnt lalu';
        return Math.floor(diff/3600) + ' jam lalu';
    }

    function renderStats(stats) {
        document.getElementById('stat-total-stations').textContent  = stats.total_stations ?? '-';
        document.getElementById('stat-online-stations').textContent = stats.online_stations ?? '-';
        document.getElementById('stat-readings-today').textContent  = (stats.readings_today ?? 0).toLocaleString('id-ID');
        var avgEl = document.getElementById('stat-avg-24h');
        if (stats.avg_reading_24h !== null && stats.avg_reading_24h !== undefined) {
            avgEl.textContent = parseFloat(stats.avg_reading_24h).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2});
        } else {
            avgEl.innerHTML = '<span class="text-warning text-sm font-weight-normal"><i class="fas fa-exclamation-triangle mr-1"></i>Timeout (NULL)</span>';
        }
    }

    function renderStations(stations) {
        var el = document.getElementById('station-cards');
        if (!stations || !stations.length) {
            el.innerHTML = '<p class="text-center text-muted py-3">Belum ada stasiun terdaftar.</p>';
            return;
        }
        var html = '';
        stations.forEach(function(s) {
            var statusClass = s.is_online ? 'online' : 'offline';
            var statusText  = s.is_online ? 'Online' : 'Offline';
            var statusBadge = s.is_online ? 'success' : 'danger';
            html += '<div class="station-card mb-2">' +
                '<div class="d-flex justify-content-between align-items-start">' +
                    '<div>' +
                        '<span class="status-dot ' + statusClass + '"></span>' +
                        '<strong class="text-sm">' + (s.station_name || s.station_id) + '</strong>' +
                        ' <small class="text-muted">(' + s.station_id + ')</small>' +
                    '</div>' +
                    '<span class="station-badge badge badge-' + statusBadge + '">' + statusText + '</span>' +
                '</div>' +
                '<div class="row mt-1 text-xs text-muted">' +
                    '<div class="col-6"><i class="fas fa-network-wired mr-1"></i>' + (s.ip_address || '-') + '</div>' +
                    '<div class="col-6 text-right"><i class="fas fa-clock mr-1"></i>' + timeAgo(s.last_ping_at) + '</div>' +
                '</div>';

            if (s.last_value !== null && s.last_value !== undefined) {
                html += '<div class="mt-2 text-xs d-flex justify-content-between align-items-center bg-light p-1 rounded">' +
                    '<span>' + (s.last_parameter || 'DEBIT') + ': <strong>' +
                    parseFloat(s.last_value).toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:3}) +
                    ' ' + (s.last_unit || '') + '</strong></span>' +
                    qualityBadge(s.last_quality) +
                '</div>';
            } else if (s.last_parameter || s.last_quality) {
                html += '<div class="mt-2 text-xs d-flex justify-content-between align-items-center bg-light p-1 rounded">' +
                    '<span class="text-muted">' + (s.last_parameter || 'DEBIT') + ': <strong class="text-danger">Timeout (NULL) ' + (s.last_unit || '') + '</strong></span>' +
                    qualityBadge(s.last_quality) +
                '</div>';
            }
            html += '</div>';
        });
        el.innerHTML = html;
    }

    function renderRecent(rows) {
        var tbody = document.getElementById('recent-tbody');
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada data pembacaan.</td></tr>';
            return;
        }
        var html = '';
        rows.forEach(function(r) {
            html += '<tr>' +
                '<td style="white-space:nowrap;font-size:.8rem">' + formatDt(r.captured_at) + '</td>' +
                '<td class="font-size-sm"><small>' + (r.station_name || r.station_id) + '</small></td>' +
                '<td class="font-size-sm"><small>' + (r.parameter_name || '-') + '</small></td>' +
                '<td class="text-right font-weight-bold">' +
                    (r.reading_value !== null && r.reading_value !== undefined
                        ? parseFloat(r.reading_value).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:3}) + ' <small class="text-muted">' + (r.unit || '') + '</small>'
                        : '<span class="badge badge-light text-danger border"><i class="fas fa-exclamation-triangle mr-1"></i>Timeout (NULL)</span>') +
                '</td>' +
                '<td>' + qualityBadge(r.quality) + '</td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
    }

    function refresh() {
        var spinner = document.getElementById('refresh-spinner');
        var icon    = document.getElementById('refresh-icon');
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');

        fetch(REFRESH_URL, {credentials: 'same-origin', cache: 'no-store'})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.status === 'ok') {
                    renderStats(data.stats);
                    renderStations(data.stations);
                    renderRecent(data.recent);
                    var t = document.getElementById('last-refresh-time');
                    if (t) t.textContent = 'Update: ' + new Date().toLocaleTimeString('id-ID');
                }
            })
            .catch(function(err) {
                console.warn('Gagal refresh dashboard:', err);
            })
            .finally(function() {
                if (spinner) spinner.classList.add('d-none');
                if (icon) icon.classList.remove('d-none');
            });
    }

    // Eksekusi segera
    refresh();
    setInterval(refresh, REFRESH_INTERVAL);
})();
</script>
