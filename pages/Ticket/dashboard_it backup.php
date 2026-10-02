<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserId'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 3. HAK AKSES MENU TICKET (MENU ID 148)
// ===================================================
if (!function_exists('checkPermissions')) {
    /** Ambil hak akses user untuk menu tertentu */
    function checkPermissions($conn, $groupId, $menuId) {
        $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
        $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
        $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
        if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $permissions = $row;
        }
        if ($stmt !== false) { sqlsrv_free_stmt($stmt); }
        return $permissions;
    }
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 148);
if (($permissions['CanView'] ?? 0) != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ===================================================
// 4. KONFIGURASI TEMA & INCLUDE LAYOUT
// ===================================================
$includeTicketThemeCss = true;
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$GLOBALS['ticketThemeOverride'] = $themeColor;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

// ===================================================
// 5. DATA AWAL UNTUK FILTER
// ===================================================
$groups = [];
$sqlG = "SELECT * FROM dbo.ticket_tech_groups ORDER BY group_name";
$stmtG = sqlsrv_query($conn, $sqlG);
while ($row = sqlsrv_fetch_array($stmtG, SQLSRV_FETCH_ASSOC)) {
    $groups[] = $row;
}
?>

<!-- ===================================================
    5. GAYA TAMBAHAN
======================================================= -->
<style>
  /* Fix Header Icon Clickability */
  .main-header { z-index: 1100 !important; position: relative; }
</style>

<!-- ===================================================
    6. KONTEN HALAMAN DASHBOARD
======================================================= -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">IT Performance Dashboard</h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/pages/ticket/list.php">Tickets</a></li><li class="breadcrumb-item active">Dashboard</li></ol></div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      
     <!-- ===================================================
         6.1 FILTER PARAMETERS
     ======================================================= -->
      <div class="card card-<?php echo $themeColor; ?> card-outline no-print">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label>Start Date</label>
                        <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label>End Date</label>
                        <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label>Group</label>
                        <select class="form-control" id="filterGroup">
                            <option value="0">All Groups</option>
                            <?php foreach($groups as $g): ?>
                            <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['group_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100 mb-2" id="btnFilter"><i class="fas fa-filter"></i> Filter</button>
                    <button class="btn btn-danger w-100" id="btnExportDashboardPdf"><i class="fas fa-file-pdf"></i> Export PDF</button>
                </div>
            </div>
        </div>
      </div>

     <!-- ===================================================
         6.2 RINGKASAN STATISTIK
     ======================================================= -->
      <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner"><h3 id="statTotal">0</h3><p>Total Tickets</p></div>
                <div class="icon"><i class="fas fa-ticket-alt"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3 id="statOpen">0</h3><p>Open</p></div>
                <div class="icon"><i class="fas fa-folder-open"></i></div>
            
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner"><h3 id="statInProgress">0</h3><p>In Progress</p></div>
                <div class="icon"><i class="fas fa-tasks"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner"><h3 id="statCompleted">0</h3><p>Completed</p></div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
      </div>

     <!-- ===================================================
         6.3 LEADERBOARD & CHART
     ======================================================= -->
      <div class="row">
        <div class="col-md-8">
            <div class="card card-<?php echo $themeColor; ?> card-outline">
                <div class="card-header"><h3 class="card-title">Technician Leaderboard (Completed Tickets)</h3></div>
                <div class="card-body">
                    <canvas id="leaderboardChart" style="min-height: 300px; height: 300px; max-height: 300px; max-width: 100%;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-warning card-outline">
                <div class="card-header"><h3 class="card-title text-center w-100">🏆 Top Performer</h3></div>
                <div class="card-body text-center">
                    <div id="topPerformerSection">
                        <i class="fas fa-crown text-warning" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                        <h3 id="topName" class="font-weight-bold">-</h3>
                        <h1 id="topCount" class="display-4 font-weight-bold text-success">0</h1>
                        <p class="text-muted">Tickets Completed</p>
                    </div>
                </div>
            </div>
        </div>
      </div>

     <!-- ===================================================
         6.4 RECENT ACTIVITIES
     ======================================================= -->
      <div class="row">
        <div class="col-md-12">
            <div class="card card-<?php echo $themeColor; ?> card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-2"></i>10 Aktivitas Ticket Terbaru</h3>
                </div>
                <div class="card-body">
                    <div class="timeline" id="recentActivitiesTimeline">
                        <!-- Activities will be loaded here via JavaScript -->
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                            <p class="mt-2">Memuat aktivitas...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- ===================================================
    7. PRINT STYLES
======================================================= -->
<style>
@media print {
  .no-print, .main-footer, .main-header, .main-sidebar { display: none !important; }
  .content-wrapper { margin-left: 0 !important; }
  .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>
<script>
    window.ticketDashboardPermissions = {
        canView: <?php echo (($permissions['CanView'] ?? 0) == 1) ? 'true' : 'false'; ?>
    };
</script>
<!-- ===================================================
    8. JAVASCRIPT DASHBOARD
======================================================= -->
<script>
$(function(){
    var chartInstance = null;
    var themeColorMap = {
        primary: '#0d6efd',
        secondary: '#6c757d',
        success: '#198754',
        danger: '#dc3545',
        warning: '#ffc107',
        info: '#0dcaf0',
        light: '#f8f9fa',
        dark: '#212529',
        indigo: '#6610f2',
        navy: '#001f3f',
        purple: '#6f42c1',
        pink: '#e83e8c',
        teal: '#20c997',
        orange: '#fd7e14',
        olive: '#3d9970',
        lime: '#01ff70',
        fuchsia: '#f012be',
        maroon: '#85144b'
    };
    var fallbackPalette = ['#17a2b8','#ff6b6b','#f3722c','#277da1','#70a1d7','#ffa822','#6c5ce7','#48c774','#ffafcc','#2ec4b6'];
    var lightThemeOverrides = {
        light: '#ced4da'
    };

    // Mengambil data dashboard berdasarkan filter aktif
    function loadDashboard() {
        var start = $('#startDate').val();
        var end = $('#endDate').val();
        var group = $('#filterGroup').val();

        $.getJSON('dashboard_data.php', { start_date:start, end_date:end, group_id:group }, function(r){
            if(r.success) {
                // Update Stats
                $('#statTotal').text(r.data.stats.total);
                $('#statCompleted').text(r.data.stats.completed);
                $('#statOpen').text(r.data.stats.open);
                $('#statInProgress').text(r.data.stats.in_progress);

                // Update Top Performers
                if(r.data.top_performers && r.data.top_performers.length > 0) {
                    var names = r.data.top_performers.map(function(p){ return p.name; }).join('<br>');
                    var count = r.data.top_performers[0].count;
                    $('#topName').html(names);
                    $('#topCount').text(count);
                    $('#topPerformerSection').show();
                } else {
                    $('#topName').text('-');
                    $('#topCount').text('0');
                }

                // Update Chart
                updateChart(r.data.leaderboard);

                // Update Recent Activities
                updateRecentActivities(r.data.recent_activities || []);
            }
        });
    }

    // Menampilkan 10 aktivitas ticket terbaru dalam format timeline
    function updateRecentActivities(activities) {
        var $timeline = $('#recentActivitiesTimeline');
        $timeline.empty();

        if (!activities || activities.length === 0) {
            $timeline.html('<div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x"></i><p class="mt-2">Tidak ada aktivitas</p></div>');
            return;
        }

        activities.forEach(function(activity) {
            var iconClass = 'fas ' + activity.icon;
            var colorClass = 'bg-' + activity.color;
            var timeAgo = formatTimeAgo(activity.time);
            
            var timelineItem = $('<div>').addClass('time-label');
            timelineItem.append($('<span>').addClass('bg-secondary').text(timeAgo));
            $timeline.append(timelineItem);

            var activityItem = $('<div>').html(
                '<i class="' + iconClass + ' ' + colorClass + '"></i>' +
                '<div class="timeline-item">' +
                    '<span class="time"><i class="fas fa-clock"></i> ' + activity.time + '</span>' +
                    '<h3 class="timeline-header">' +
                        '<a href="detail.php?id=' + activity.ticket_id + '">' + escapeHtml(activity.ticket_no) + '</a> ' +
                        '<span class="badge badge-' + activity.color + '">' + escapeHtml(activity.status) + '</span>' +
                    '</h3>' +
                    '<div class="timeline-body">' +
                        '<strong>' + escapeHtml(activity.subject) + '</strong><br>' +
                        '<small class="text-muted">' + activity.text + '</small>' +
                    '</div>' +
                '</div>'
            );
            $timeline.append(activityItem);
        });

        // Add end marker
        $timeline.append('<div><i class="fas fa-clock bg-gray"></i></div>');
    }

    // Format waktu menjadi "X menit yang lalu", "X jam yang lalu", dll
    function formatTimeAgo(timeStr) {
        if (!timeStr) return 'Baru saja';
        
        try {
            var activityTime = new Date(timeStr.replace(' ', 'T'));
            var now = new Date();
            var diffMs = now - activityTime;
            var diffMins = Math.floor(diffMs / 60000);
            var diffHours = Math.floor(diffMs / 3600000);
            var diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Baru saja';
            if (diffMins < 60) return diffMins + ' menit yang lalu';
            if (diffHours < 24) return diffHours + ' jam yang lalu';
            if (diffDays < 7) return diffDays + ' hari yang lalu';
            
            return activityTime.toLocaleDateString('id-ID');
        } catch(e) {
            return timeStr;
        }
    }

    // Escape HTML untuk keamanan
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Menggambar ulang grafik leaderboard berdasarkan data terbaru
    function updateChart(data) {
        var labels = [];
        var counts = [];
        var colors = [];
        var themeUsage = {};
        var fallbackIndex = 0;

        function nextFallbackColor() {
            var color = fallbackPalette[fallbackIndex % fallbackPalette.length];
            fallbackIndex++;
            return color;
        }

        // Find max count
        var maxCount = data.length > 0 ? data[0].count : 0;

        data.forEach(function(item, idx){
            labels.push(item.name);
            counts.push(item.count);
            // Highlight top performer(s) in gold; others cycle through palette for variety
            if (item.count === maxCount && maxCount > 0) {
                colors.push('#ffc107');
            } else {
                var themeKey = (item.theme || '').toLowerCase();
                if (themeKey && themeColorMap[themeKey]) {
                    var usage = themeUsage[themeKey] || 0;
                    if (usage === 0) {
                        colors.push(lightThemeOverrides[themeKey] || themeColorMap[themeKey]);
                    } else {
                        colors.push(nextFallbackColor());
                    }
                    themeUsage[themeKey] = usage + 1;
                } else {
                    colors.push(nextFallbackColor());
                }
            }
        });

        var ctx = document.getElementById('leaderboardChart').getContext('2d');
        
        if(chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Tickets Completed',
                    data: counts,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true, stepSize: 1 }
                    }]
                },
                legend: { display: false }
            }
        });
    }

    // Tombol Filter akan me-refresh data sesuai parameter
    $('#btnFilter').click(loadDashboard);

    // Ekspor PDF membuka tab baru agar pengguna tetap di dashboard
    $('#btnExportDashboardPdf').click(function(){
        var start = $('#startDate').val() || '';
        var end = $('#endDate').val() || '';
        var group = $('#filterGroup').val() || 0;
        var url = 'dashboard_export_pdf.php?start_date=' + encodeURIComponent(start) +
                  '&end_date=' + encodeURIComponent(end) +
                  '&group_id=' + encodeURIComponent(group);
        window.open(url, '_blank');
    });

    // Muat data pertama kali saat halaman siap
    loadDashboard();

    // ===================================================
    // AUTO-REFRESH: Update data setiap 10 detik
    // ===================================================
    // Dashboard akan otomatis reload data tanpa perlu refresh halaman
    setInterval(function() {
        loadDashboard();
    }, 10000); // 10000ms = 10 detik
});
</script>
