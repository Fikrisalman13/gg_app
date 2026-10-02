<?php
// pages/fabric_knowledge/dashboard.php
session_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// ====== AUTH & PERMISSION ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ====== PERMISSION CEK OPSIONAL ======
$MenuId = 122; // ID menu Knowledge Base
if (function_exists('checkPermissions')) {
    $permissions = checkPermissions($conn, $_SESSION['GroupId'], $MenuId);
    if ($permissions['CanView'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini.";
        header('Location: ../../index.php');
        exit;
    }
}

// ====== FUNGSI AMBIL NILAI CEPAT ======
function getValue($conn, $sql, $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return reset($row) ?? 0;
    }
    return 0;
}

// ====== QUERY STATISTIK ======
// Total masalah
$total_problem = getValue($conn, "SELECT COUNT(*) FROM fab_m_problem");

// Status diambil dari fab_m_solution, jika tidak ada maka 'Open'
$total_open   = getValue($conn, "SELECT COUNT(*) FROM fab_m_problem p 
                                 LEFT JOIN fab_m_solution s ON p.id_problem = s.id_problem 
                                 WHERE COALESCE(s.status, 'Open') = 'Open'");
                                 
$total_solved = getValue($conn, "SELECT COUNT(*) FROM fab_m_problem p 
                                 LEFT JOIN fab_m_solution s ON p.id_problem = s.id_problem 
                                 WHERE COALESCE(s.status, 'Open') = 'Solved'");
                                 
$total_reopen = getValue($conn, "SELECT COUNT(*) FROM fab_m_problem p 
                                 LEFT JOIN fab_m_solution s ON p.id_problem = s.id_problem 
                                 WHERE COALESCE(s.status, 'Open') = 'Reopen'");

// ====== STATISTIK HARI INI ======
$today = date('Y-m-d');

// Total masalah yang diinput hari ini
$today_input = getValue($conn, 
    "SELECT COUNT(*) FROM fab_m_problem WHERE CAST(created_at AS DATE) = ?", 
    [$today]
);

// Total masalah yang diselesaikan hari ini
$today_solved = getValue($conn, 
    "SELECT COUNT(*) FROM fab_m_solution WHERE status = 'Solved' AND CAST(updated_at AS DATE) = ?", 
    [$today]
);

// User yang menginput masalah hari ini
$today_input_users = [];
$sql_today_users = "SELECT DISTINCT created_by, COUNT(*) as input_count 
                    FROM fab_m_problem 
                    WHERE CAST(created_at AS DATE) = ? 
                    GROUP BY created_by 
                    ORDER BY input_count DESC";
$stmt_today_users = sqlsrv_query($conn, $sql_today_users, [$today]);
if ($stmt_today_users) {
    while ($row = sqlsrv_fetch_array($stmt_today_users, SQLSRV_FETCH_ASSOC)) {
        $today_input_users[] = $row;
    }
    sqlsrv_free_stmt($stmt_today_users);
}

// Analis yang menyelesaikan masalah hari ini
$today_analysts = [];
$sql_today_analysts = "SELECT DISTINCT p.analis, COUNT(*) as solved_count 
                       FROM fab_m_problem p
                       INNER JOIN fab_m_solution s ON p.id_problem = s.id_problem 
                       WHERE s.status = 'Solved' AND CAST(s.updated_at AS DATE) = ?
                       AND p.analis IS NOT NULL
                       GROUP BY p.analis 
                       ORDER BY solved_count DESC";
$stmt_today_analysts = sqlsrv_query($conn, $sql_today_analysts, [$today]);
if ($stmt_today_analysts) {
    while ($row = sqlsrv_fetch_array($stmt_today_analysts, SQLSRV_FETCH_ASSOC)) {
        $today_analysts[] = $row;
    }
    sqlsrv_free_stmt($stmt_today_analysts);
}

// ====== DISTRIBUSI KATEGORI ======
$kategori_labels = [];
$kategori_data   = [];
$sql = "SELECT k.nama_kategori, COUNT(p.id_problem) AS jml
        FROM fab_m_problem p
        LEFT JOIN fab_m_kategori k ON p.id_kategori = k.id_kategori
        GROUP BY k.nama_kategori
        ORDER BY jml DESC";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $kategori_labels[] = $row['nama_kategori'] ?? 'Tanpa Kategori';
        $kategori_data[]   = $row['jml'];
    }
    sqlsrv_free_stmt($stmt);
}

// ====== DISTRIBUSI STATUS ======
$status_labels = ['Open', 'Solved', 'Reopen'];
$status_data   = [$total_open, $total_solved, $total_reopen];
$status_colors = ['#f39c12', '#00a65a', '#dd4b39'];

// ====== TOP 10 TAG POPULER ======
$top10_tag_labels = [];
$top10_tag_data   = [];
$sql_top10 = "SELECT TOP 10 t.nama_tag, COUNT(p.id_problem) AS jml
        FROM fab_m_problem p
        LEFT JOIN fab_m_tag t ON p.id_tag = t.id_tag
        WHERE t.nama_tag IS NOT NULL
        GROUP BY t.nama_tag
        ORDER BY jml DESC";
$stmt_top10 = sqlsrv_query($conn, $sql_top10);
if ($stmt_top10) {
    while ($row = sqlsrv_fetch_array($stmt_top10, SQLSRV_FETCH_ASSOC)) {
        if ($row && isset($row['nama_tag']) && isset($row['jml'])) {
            $top10_tag_labels[] = $row['nama_tag'];
            $top10_tag_data[]   = $row['jml'];
        }
    }
    sqlsrv_free_stmt($stmt_top10);
}

// ====== TOP 3 TAG PER HARI (7 HARI TERAKHIR) ======
$top3_tag_per_hari_labels = [];
$top3_tag_per_hari_datasets = [];
$top3_tag_per_hari_colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE'];

$sql_top3_per_hari = "WITH RankedTags AS (
    SELECT 
        CONVERT(DATE, p.created_at) as tanggal,
        t.nama_tag,
        COUNT(p.id_problem) as jumlah,
        ROW_NUMBER() OVER (PARTITION BY CONVERT(DATE, p.created_at) ORDER BY COUNT(p.id_problem) DESC) as ranking
    FROM fab_m_problem p
    LEFT JOIN fab_m_tag t ON p.id_tag = t.id_tag
    WHERE t.nama_tag IS NOT NULL 
        AND p.created_at >= DATEADD(DAY, -7, GETDATE())
    GROUP BY CONVERT(DATE, p.created_at), t.nama_tag
)
SELECT 
    FORMAT(tanggal, 'dd MMM') as tanggal_format,
    nama_tag,
    jumlah,
    ranking
FROM RankedTags
WHERE ranking <= 3
ORDER BY tanggal DESC, ranking ASC";

$stmt_top3_per_hari = sqlsrv_query($conn, $sql_top3_per_hari);
if ($stmt_top3_per_hari) {
    $temp_data = [];
    while ($row = sqlsrv_fetch_array($stmt_top3_per_hari, SQLSRV_FETCH_ASSOC)) {
        if ($row && isset($row['tanggal_format']) && isset($row['nama_tag']) && isset($row['jumlah']) && isset($row['ranking'])) {
            $tanggal = $row['tanggal_format'];
            $tag_name = $row['nama_tag'];
            $jumlah = $row['jumlah'];
            $ranking = $row['ranking'];
            
            if (!isset($temp_data[$tanggal])) {
                $temp_data[$tanggal] = [];
            }
            $temp_data[$tanggal][$tag_name] = $jumlah;
        }
    }
    sqlsrv_free_stmt($stmt_top3_per_hari);
    
    $top3_tag_per_hari_labels = array_keys($temp_data);
    $all_tags = [];
    foreach ($temp_data as $tags) {
        $all_tags = array_merge($all_tags, array_keys($tags));
    }
    $unique_tags = array_unique($all_tags);
    
    foreach ($unique_tags as $index => $tag) {
        $tag_values = [];
        foreach ($top3_tag_per_hari_labels as $tanggal) {
            $tag_values[] = $temp_data[$tanggal][$tag] ?? 0;
        }
        $top3_tag_per_hari_datasets[] = [
            'label' => $tag,
            'data' => $tag_values,
            'backgroundColor' => $top3_tag_per_hari_colors[$index % count($top3_tag_per_hari_colors)]
        ];
    }
}

// ====== TREN BULANAN ======
$monthly_labels = [];
$monthly_data   = [];
$sql = "SELECT 
            FORMAT(COALESCE(s.updated_at, p.created_at), 'yyyy-MM') as bulan,
            COUNT(p.id_problem) as jml
        FROM fab_m_problem p
        LEFT JOIN fab_m_solution s ON p.id_problem = s.id_problem
        WHERE COALESCE(s.updated_at, p.created_at) >= DATEADD(MONTH, -6, GETDATE())
        GROUP BY FORMAT(COALESCE(s.updated_at, p.created_at), 'yyyy-MM')
        ORDER BY bulan";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row && isset($row['bulan']) && isset($row['jml'])) {
            $date = DateTime::createFromFormat('Y-m', $row['bulan']);
            $monthly_labels[] = $date ? $date->format('M Y') : $row['bulan'];
            $monthly_data[]   = $row['jml'];
        }
    }
    sqlsrv_free_stmt($stmt);
}

// ====== AKTIVITAS TERBARU ======
$sql_recent = "SELECT TOP 10 
                    p.id_problem,
                    p.nocp, 
                    p.judul,
                    k.nama_kategori,
                    t.nama_tag,
                    COALESCE(s.status, 'Open') as status,
                    COALESCE(s.updated_at, p.updated_at, p.created_at) as last_updated
               FROM fab_m_problem p
               LEFT JOIN fab_m_kategori k ON p.id_kategori = k.id_kategori
               LEFT JOIN fab_m_tag t ON p.id_tag = t.id_tag
               LEFT JOIN fab_m_solution s ON p.id_problem = s.id_problem
               ORDER BY last_updated DESC";
$recent_stmt = sqlsrv_query($conn, $sql_recent);

// ====== TOP PROBLEM SOLVERS ======
$top_solvers = [];
$sql_solvers = "SELECT TOP 5 
                       created_by as solver,
                       COUNT(*) as solved_count
                FROM fab_m_solution 
                WHERE status = 'Solved'
                GROUP BY created_by 
                ORDER BY solved_count DESC";
$stmt_solvers = sqlsrv_query($conn, $sql_solvers);
if ($stmt_solvers) {
    while ($row = sqlsrv_fetch_array($stmt_solvers, SQLSRV_FETCH_ASSOC)) {
        if ($row && isset($row['solver']) && isset($row['solved_count'])) {
            $top_solvers[] = $row;
        }
    }
    sqlsrv_free_stmt($stmt_solvers);
}
?>

<!-- ====== CONTENT WRAPPER ====== -->
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">Analisa Masalah Kain dan Warna</h1>
          <small class="text-muted">Analisa & Solusi Permasalahan Produksi</small>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
            <li class="breadcrumb-item active">Masalah Kain & Warna</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <!-- ====== SUMMARY CARDS ====== -->
      <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
          <div class="small-box bg-info shadow-sm">
            <div class="inner">
              <h3 class="font-weight-bold"><?= number_format($total_problem) ?></h3>
              <p class="mb-1">Total Masalah</p>
            </div>
            <div class="icon">
              <i class="fas fa-database"></i>
            </div>
            <a href="problem_list.php" class="small-box-footer">
              Lihat Semua <i class="fas fa-arrow-circle-right ml-1"></i>
            </a>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
          <div class="small-box bg-warning shadow-sm">
            <div class="inner">
              <h3 class="font-weight-bold"><?= number_format($total_open) ?></h3>
              <p class="mb-1">Masalah Terbuka</p>
            </div>
            <div class="icon">
              <i class="fas fa-hourglass-half"></i>
            </div>
            <a href="problem_list.php?filter_status=Open" class="small-box-footer">
              Lihat Detail <i class="fas fa-arrow-circle-right ml-1"></i>
            </a>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
          <div class="small-box bg-success shadow-sm">
            <div class="inner">
              <h3 class="font-weight-bold"><?= number_format($total_solved) ?></h3>
              <p class="mb-1">Masalah Terselesaikan</p>
            </div>
            <div class="icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <a href="problem_list.php?filter_status=Solved" class="small-box-footer">
              Lihat Detail <i class="fas fa-arrow-circle-right ml-1"></i>
            </a>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
          <div class="small-box bg-danger shadow-sm">
            <div class="inner">
              <h3 class="font-weight-bold"><?= number_format($total_reopen) ?></h3>
              <p class="mb-1">Masalah Reopen</p>
            </div>
            <div class="icon">
              <i class="fas fa-undo"></i>
            </div>
            <a href="problem_list.php?filter_status=Reopen" class="small-box-footer">
              Lihat Detail <i class="fas fa-arrow-circle-right ml-1"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- ====== STATISTIK HARI INI ====== -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white py-3">
              <h5 class="card-title mb-0">
                <i class="fas fa-calendar-day mr-2"></i>Statistik Hari Ini (<?= date('d M Y') ?>)
              </h5>
            </div>
            <div class="card-body">
              <div class="row">
                <!-- Card Input Hari Ini -->
                <div class="col-md-3 mb-3">
                  <div class="info-box bg-gradient-info shadow">
                    <span class="info-box-icon"><i class="fas fa-plus-circle"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Input Masalah</span>
                      <span class="info-box-number"><?= number_format($today_input) ?></span>
                      <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                      </div>
                      <small class="progress-description">
                        Total masalah yang diinput hari ini
                      </small>
                    </div>
                  </div>
                </div>

                <!-- Card Solved Hari Ini -->
                <div class="col-md-3 mb-3">
                  <div class="info-box bg-gradient-success shadow">
                    <span class="info-box-icon"><i class="fas fa-check-double"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Diselesaikan</span>
                      <span class="info-box-number"><?= number_format($today_solved) ?></span>
                      <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                      </div>
                      <small class="progress-description">
                        Masalah selesai hari ini
                      </small>
                    </div>
                  </div>
                </div>

                <!-- User Penginput -->
                <div class="col-md-3 mb-3">
                  <div class="info-box bg-gradient-warning shadow">
                    <span class="info-box-icon"><i class="fas fa-users"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">User Penginput</span>
                      <span class="info-box-number"><?= number_format(count($today_input_users)) ?></span>
                      <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                      </div>
                      <small class="progress-description">
                        User yang input masalah
                      </small>
                    </div>
                  </div>
                </div>

                <!-- Analis Aktif -->
                <div class="col-md-3 mb-3">
                  <div class="info-box bg-gradient-danger shadow">
                    <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Analis Aktif</span>
                      <span class="info-box-number"><?= number_format(count($today_analysts)) ?></span>
                      <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                      </div>
                      <small class="progress-description">
                        Analis yang menyelesaikan
                      </small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Detail User dan Analis -->
              <div class="row mt-3">
                <!-- User Penginput -->
                <div class="col-md-6">
                  <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                      <h6 class="card-title mb-0 text-dark">
                        <i class="fas fa-user-edit mr-1"></i>User Penginput Hari Ini
                      </h6>
                    </div>
                    <div class="card-body p-0">
                      <?php if (!empty($today_input_users)): ?>
                        <div class="list-group list-group-flush">
                          <?php foreach ($today_input_users as $user): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                              <div>
                                <i class="fas fa-user-circle text-primary mr-2"></i>
                                <span class="font-weight-bold"><?= htmlspecialchars($user['created_by']) ?></span>
                              </div>
                              <span class="badge badge-primary badge-pill"><?= $user['input_count'] ?></span>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center py-3 text-muted">
                          <i class="fas fa-user-slash fa-2x mb-2"></i>
                          <p class="mb-0">Tidak ada user yang menginput hari ini</p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <!-- Analis Aktif -->
                <div class="col-md-6">
                  <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                      <h6 class="card-title mb-0 text-dark">
                        <i class="fas fa-user-cog mr-1"></i>Analis Aktif Hari Ini
                      </h6>
                    </div>
                    <div class="card-body p-0">
                      <?php if (!empty($today_analysts)): ?>
                        <div class="list-group list-group-flush">
                          <?php foreach ($today_analysts as $analyst): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                              <div>
                                <i class="fas fa-user-shield text-success mr-2"></i>
                                <span class="font-weight-bold"><?= htmlspecialchars($analyst['analis']) ?></span>
                              </div>
                              <span class="badge badge-success badge-pill"><?= $analyst['solved_count'] ?></span>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center py-3 text-muted">
                          <i class="fas fa-user-times fa-2x mb-2"></i>
                          <p class="mb-0">Tidak ada analis aktif hari ini</p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ====== CHARTS ROW 1 ====== -->
      <div class="row">
        <div class="col-md-6 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0 text-primary">
                <i class="fas fa-chart-pie mr-2"></i>Distribusi Status Masalah
              </h5>
            </div>
            <div class="card-body">
              <canvas id="chartStatus" height="250"></canvas>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0 text-success">
                <i class="fas fa-chart-line mr-2"></i>Tren 6 Bulan Terakhir
              </h5>
            </div>
            <div class="card-body">
              <canvas id="chartMonthly" height="250"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- ====== CHARTS ROW 2 ====== -->
      <div class="row">
        <div class="col-md-6 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0 text-warning">
                <i class="fas fa-list-alt mr-2"></i>Distribusi per Kategori
              </h5>
            </div>
            <div class="card-body">
              <canvas id="chartKategori" height="250"></canvas>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0 text-info">
                <i class="fas fa-tags mr-2"></i>Top 10 Tag Populer
              </h5>
            </div>
            <div class="card-body">
              <?php if (!empty($top10_tag_labels)): ?>
                <canvas id="chartTop10Tag" height="250"></canvas>
              <?php else: ?>
                <div class="text-center py-4">
                  <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                  <p class="text-muted mb-0">Tidak ada data tag</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ====== CHARTS ROW 3 - TOP 3 TAG PER HARI ====== -->
      <div class="row">
        <div class="col-12 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0 text-danger">
                <i class="fas fa-chart-bar mr-2"></i>Top 3 Tag Populer per Hari (7 Hari Terakhir)
              </h5>
            </div>
            <div class="card-body">
              <?php if (!empty($top3_tag_per_hari_labels)): ?>
                <canvas id="chartTop3TagPerHari" height="300"></canvas>
              <?php else: ?>
                <div class="text-center py-4">
                  <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                  <p class="text-muted mb-0">Tidak ada data tag untuk 7 hari terakhir</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ====== BOTTOM ROW ====== -->
      <div class="row">
        <!-- AKTIVITAS TERBARU -->
        <div class="col-lg-8 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0">
                <i class="fas fa-clock mr-2 text-primary"></i>Aktivitas Terbaru
              </h5>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="bg-light">
                    <tr>
                      <th width="5%" class="border-0">#</th>
                      <th class="border-0">No CP</th>
                      <th class="border-0">Kategori</th>
                      <th class="border-0">Tag</th>
                      <th class="border-0">Status</th>
                      <th class="border-0">Update Terakhir</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $no = 1;
                    if ($recent_stmt) {
                        while ($row = sqlsrv_fetch_array($recent_stmt, SQLSRV_FETCH_ASSOC)) {
                            if ($row && isset($row['id_problem'])) {
                                $tgl = isset($row['last_updated']) && $row['last_updated'] instanceof DateTime
                                    ? $row['last_updated']->format('d-m-Y H:i')
                                    : '-';
                                
                                $status_badge = match($row['status'] ?? 'Open') {
                                    'Solved' => '<span class="badge badge-success">Solved</span>',
                                    'Reopen' => '<span class="badge badge-danger">Reopen</span>',
                                    default => '<span class="badge badge-warning">Open</span>'
                                };
                                
                                echo "
                                <tr>
                                  <td class='align-middle'>{$no}</td>
                                  <td class='align-middle'>" . htmlspecialchars($row['nocp'] ?? '-') . "</td>
                                  <td class='align-middle'>" . htmlspecialchars($row['nama_kategori'] ?? '-') . "</td>
                                  <td class='align-middle'>
                                    <span class='badge badge-light border'>" . htmlspecialchars($row['nama_tag'] ?? '-') . "</span>
                                  </td>
                                  <td class='align-middle'>{$status_badge}</td>
                                  <td class='align-middle'><small class='text-muted'>{$tgl}</small></td>
                                </tr>";
                                $no++;
                            }
                        }
                        sqlsrv_free_stmt($recent_stmt);
                    }
                    if ($no == 1) {
                        echo '<tr><td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                Belum ada data masalah
                              </td></tr>';
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- SIDEBAR KANAN -->
        <div class="col-lg-4 mb-4">
          <!-- TOP SOLVERS -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0 text-success">
                <i class="fas fa-trophy mr-2"></i>Top Input Masalah
              </h5>
            </div>
            <div class="card-body">
              <?php if (!empty($top_solvers)): ?>
                <div class="list-group list-group-flush">
                  <?php foreach ($top_solvers as $index => $solver): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                      <div>
                        <span class="font-weight-bold text-dark"><?= htmlspecialchars($solver['solver']) ?></span>
                      </div>
                      <span class="badge badge-success badge-pill"><?= $solver['solved_count'] ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-center py-3">
                  <i class="fas fa-trophy fa-2x text-muted mb-2"></i>
                  <p class="text-muted mb-0">Belum ada data solver</p>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- QUICK ACTIONS -->
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="card-title mb-0 text-primary">
                <i class="fas fa-bolt mr-2"></i>Quick Actions
              </h5>
            </div>
            <div class="card-body">
              <a href="add_problem.php" class="btn btn-primary btn-block mb-3 py-2">
                <i class="fas fa-plus mr-2"></i>Tambah Masalah Baru
              </a>
              <a href="problem_list.php" class="btn btn-outline-primary btn-block py-2">
                <i class="fas fa-list mr-2"></i>Lihat Semua Masalah
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- Chart CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<!-- Chart JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/chart.js"></script>

<script>
// Data dari PHP
const statusLabels = <?= json_encode($status_labels) ?>;
const statusData   = <?= json_encode($status_data) ?>;
const statusColors = <?= json_encode($status_colors) ?>;

const kategoriLabels = <?= json_encode($kategori_labels) ?>;
const kategoriData   = <?= json_encode($kategori_data) ?>;

const top10TagLabels = <?= json_encode($top10_tag_labels) ?>;
const top10TagData   = <?= json_encode($top10_tag_data) ?>;

const top3TagPerHariLabels = <?= json_encode($top3_tag_per_hari_labels) ?>;
const top3TagPerHariDatasets = <?= json_encode($top3_tag_per_hari_datasets) ?>;

const monthlyLabels = <?= json_encode($monthly_labels) ?>;
const monthlyData   = <?= json_encode($monthly_data) ?>;

// Chart Status
if (document.getElementById('chartStatus')) {
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            },
            cutout: '60%'
        }
    });
}

// Chart Kategori
if (document.getElementById('chartKategori')) {
    new Chart(document.getElementById('chartKategori'), {
        type: 'pie',
        data: {
            labels: kategoriLabels,
            datasets: [{
                data: kategoriData,
                backgroundColor: ['#00c0ef','#00a65a','#f39c12','#dd4b39','#3c8dbc','#605ca8','#ff851b','#d81b60','#001f3f','#39cccc'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'right',
                    labels: {
                        padding: 15,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

// Chart Top 10 Tag Populer
if (document.getElementById('chartTop10Tag') && top10TagLabels.length > 0) {
    new Chart(document.getElementById('chartTop10Tag'), {
        type: 'bar',
        data: {
            labels: top10TagLabels,
            datasets: [{
                label: 'Jumlah Masalah',
                data: top10TagData,
                backgroundColor: '#00a65a',
                borderColor: '#008d4c',
                borderWidth: 1
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        display: false
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Chart Monthly Trend
if (document.getElementById('chartMonthly')) {
    new Chart(document.getElementById('chartMonthly'), {
        type: 'line',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Jumlah Masalah',
                data: monthlyData,
                borderColor: '#00a65a',
                backgroundColor: 'rgba(0, 166, 90, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#00a65a',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Chart Top 3 Tag per Hari
if (document.getElementById('chartTop3TagPerHari') && top3TagPerHariLabels.length > 0) {
    new Chart(document.getElementById('chartTop3TagPerHari'), {
        type: 'bar',
        data: {
            labels: top3TagPerHariLabels,
            datasets: top3TagPerHariDatasets
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 20,
                        usePointStyle: true
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: {
                        display: false
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
</script>