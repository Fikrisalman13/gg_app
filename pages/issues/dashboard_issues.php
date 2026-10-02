<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../ticket/theme_helper.php';

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserId'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 3. HAK AKSES MENU ISSUES (MENU ID 162)
// ===================================================
if (!function_exists('checkPermissions')) {
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

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 173);
if (($permissions['CanView'] ?? 0) != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ===================================================
// 4. KONFIGURASI TEMA & INCLUDE LAYOUT
// ===================================================
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- ===================================================
    5. GAYA TAMBAHAN
======================================================= -->
<style>
  
  .chart-container-issues {
    position: relative;
    height: 300px;
    width: 100%;
  }
  
  #btnToggleFullscreen {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #28a745;
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 10000;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    transition: all 0.3s ease;
  }
  
  .fullscreen-mode {
    position: fixed !important;
    top: 0 !important; left: 0 !important; right: 0 !important;
    width: 100vw !important; height: 100vh !important;
    margin: 0 !important; padding: 20px !important;
    background-color: #f4f6f9 !important;
    z-index: 9999 !important;
    overflow-y: auto !important;
  }

    /* Improve display for very short names (1-2 chars) */
    .top-short-name { font-size: 1.6rem; letter-spacing: 0.12em; padding: 0 0.25rem; display: inline-block; }

    /* Reduce vertical gap between name and count in Top Performer card */
    #topPerformerSection { padding-top: 0.5rem; }
    #topPerformerSection i.fas.fa-crown { margin-bottom: 0.5rem; }
    #topName { font-size: 1.15rem; line-height: 1.1; min-height: 1.2rem; margin-bottom: 0.15rem !important; padding: 0; }
    #topName > span { display: inline-block; }
    #topCount { margin-top: 0.05rem; margin-bottom: 0.25rem; line-height: 1; }
    #topPerformerSection p.text-muted { margin-top: 0.15rem; }

  body.in-fullscreen .main-header,
  body.in-fullscreen .main-sidebar,
  body.in-fullscreen .main-footer {
    display: none !important;
  }

  body.in-fullscreen .content-wrapper {
    margin-left: 0 !important;
  }
</style>

<!-- ===================================================
    6. KONTEN HALAMAN DASHBOARD
======================================================= -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Issues Analytics Dashboard</h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/pages/issues/list_issues.php">Issues</a></li><li class="breadcrumb-item active">Dashboard</li></ol></div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      
      <!-- FILTER SECTION -->
      <div class="card card-<?php echo $themeColor; ?> card-outline no-print">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label>Range Tanggal</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-01'); ?>">
                            <div class="input-group-append"><span class="input-group-text">s/d</span></div>
                            <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100 mb-0" id="btnFilter"><i class="fas fa-filter"></i> Filter</button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100 mb-0" id="btnResetFilter"><i class="fas fa-sync"></i> Reset</button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-danger w-100 mb-0" id="btnExportPdf"><i class="fas fa-file-pdf"></i> Export</button>
                </div>
            </div>
        </div>
      </div>

      <!-- MAIN STATS -->
      <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner"><h3 id="statTotal">0</h3><p>Total Issues</p></div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3 id="statOpen">0</h3><p>To Do</p></div>
                <div class="icon"><i class="fas fa-folder-open"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner"><h3 id="statInProgress">0</h3><p>In Progress</p></div>
                <div class="icon"><i class="fas fa-spinner fa-spin-slow"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner"><h3 id="statCompleted">0</h3><p>Done</p></div>
                <div class="icon"><i class="fas fa-check-double"></i></div>
            </div>
        </div>
      </div>


      <div class="row">
        <!-- TYPE DISTRIBUTION (Doughnut Chart) -->
        <div class="col-md-4">
            <div class="card card-<?php echo $themeColor; ?> card-outline">
                <div class="card-header"><h3 class="card-title">Type</h3></div>
                <div class="card-body">
                    <div class="chart-container-issues">
                        <canvas id="typeDistChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CATEGORY BREAKDOWN (Bar Chart) -->
        <div class="col-md-5">
            <div class="card card-<?php echo $themeColor; ?> card-outline">
                <div class="card-header d-flex p-0">
                    <h3 class="card-title p-3" id="mainChartTitle">Issues Identified by Category</h3>
                    <div class="ml-auto p-2">
                        <select id="chartToggle" class="form-control form-control-sm">
                            <option value="category">By Category</option>
                            <option value="technician">By Technician</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container-issues">
                        <canvas id="leaderboardChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOP PERFORMER -->
        <div class="col-md-3">
            <div class="card card-warning card-outline">
                <div class="card-header"><h3 class="card-title text-center w-100">🏆 Top Performer</h3></div>
                <div class="card-body text-center">
                    <div id="topPerformerSection">
                        <i class="fas fa-crown text-warning" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                        <div id="topName" class="font-weight-bold mb-3" style="font-size: 1.15rem; line-height: 1.3; min-height: 3rem; display: flex; align-items: center; justify-content: center; flex-direction: column;">-</div>
                        <h1 id="topCount" class="display-4 font-weight-bold text-success">0</h1>
                        <p class="text-muted">Issues Solved</p>
                    </div>
                </div>
            </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>

<script>
$(function(){
    var charts = {
        leaderboard: null,
        distribution: null
    };

    // Store data globally for toggle
    var dashboardData = {
        category: [],
        technician: []
    };

    var themeColorMap = {
        primary: '#0d6efd', secondary: '#6c757d', success: '#198754', danger: '#dc3545',
        warning: '#ffc107', info: '#0dcaf0', light: '#f8f9fa', dark: '#212529',
        indigo: '#6610f2', navy: '#001f3f', purple: '#6f42c1', pink: '#e83e8c',
        teal: '#20c997', orange: '#fd7e14', olive: '#3d9970', lime: '#01ff70',
        fuchsia: '#f012be', maroon: '#85144b'
    };
    var fallbackPalette = ['#17a2b8','#ff6b6b','#f3722c','#277da1','#70a1d7','#ffa822','#6c5ce7','#48c774','#ffafcc','#2ec4b6'];
    var lightThemeOverrides = { light: '#ced4da' };

    function loadDashboard() {
        var start = $('#startDate').val();
        var end = $('#endDate').val();

        $.getJSON('dashboard_data_issues.php', { start_date: start, end_date: end }, function(r){
            if(r.success) {
                // Update Stats
                $('#statTotal').text(r.data.stats.total);
                $('#statCompleted').text(r.data.stats.completed);
                $('#statOpen').text(r.data.stats.open);
                $('#statInProgress').text(r.data.stats.in_progress);


                // Update Top Performer (format short names for better appearance)
                function escapeHtml(str) {
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/\"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                if(r.data.top_performers && r.data.top_performers.length > 0) {
                    var names = r.data.top_performers.map(function(p){
                        var n = (p.name || '').trim();
                        if(n.length < 3) {
                            return '<span class="top-short-name">' + escapeHtml(n) + '</span>';
                        }
                        return escapeHtml(n);
                    }).join('<br>');
                    var count = r.data.top_performers[0].count;
                    $('#topName').html(names);
                    $('#topCount').text(count);
                } else {
                    $('#topName').text('-');
                    $('#topCount').text('0');
                }

                // Store data for toggle
                dashboardData.category = r.data.category_breakdown || [];
                dashboardData.technician = r.data.leaderboard || [];

                // Render based on current selection
                updateMainChart();
                renderDistribution(r.data.type_distribution);
            }
        });
    }

    function updateMainChart() {
        var mode = $('#chartToggle').val();
        var data = (mode === 'technician') ? dashboardData.technician : dashboardData.category;
        var title = (mode === 'technician') ? 'Issues Identified by Technician' : 'Issues Identified by Category';
        
        $('#mainChartTitle').text(title);
        renderLeaderboard(data, mode);
    }

    $('#chartToggle').change(function() {
        updateMainChart();
    });

    function renderLeaderboard(data, mode) {
        var labels = [];
        var values = [];
        var colors = [];
        var themeUsage = {};
        var fallbackIndex = 0;

        function nextFallbackColor() {
            var color = fallbackPalette[fallbackIndex % fallbackPalette.length];
            fallbackIndex++;
            return color;
        }

        var maxCount = data.length > 0 ? data[0].count : 0;

        data.forEach(function(item) {
            labels.push(item.name);
            values.push(item.count);
            
            if (item.count === maxCount && maxCount > 0) {
                colors.push('#ffc107'); // Gold for top performer
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
        if(charts.leaderboard) charts.leaderboard.destroy();
        
        charts.leaderboard = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: (mode === 'technician') ? 'Issues Solved' : 'Issues Recorded',
                    data: values,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{ ticks: { beginAtZero: true, stepSize: 1 } }]
                },
                legend: { display: false }
            }
        });
    }

    function renderDistribution(data) {
        var labels = data.map(function(i){ return i.label; });
        var values = data.map(function(i){ return i.value; });
        var ctx = document.getElementById('typeDistChart').getContext('2d');
        
        if(charts.distribution) charts.distribution.destroy();
        
        charts.distribution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        '#1f8ef1', '#2dce89', '#f5365c', '#fb6340', '#11cdef', '#ced4da'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom', labels: { boxWidth: 12, fontSize: 11 } }
            }
        });
    }

    $('#btnFilter').click(loadDashboard);
    
    $('#btnResetFilter').click(function() {
        $('#startDate').val('<?php echo date('Y-m-01'); ?>');
        $('#endDate').val('<?php echo date('Y-m-d'); ?>');
        loadDashboard();
    });

    $('#btnExportPdf').click(function() {
        var start = $('#startDate').val();
        var end = $('#endDate').val();
        var url = 'dashboard_export_pdf_issues.php?start_date=' + start + '&end_date=' + end;
        window.open(url, '_blank');
    });

    loadDashboard();
});
</script>
