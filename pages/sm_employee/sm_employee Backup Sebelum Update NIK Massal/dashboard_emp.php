<?php
session_start();
ob_start();
include '../../koneksi.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

date_default_timezone_set('Asia/Jakarta');

// Cek koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// === Permission check (MenuId untuk Dashboard Emp: 74 misalnya) ===
$groupId = $_SESSION['GroupId'];
$menuId = 77;
$sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?";
$stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
$permissions = ['CanView' => 0];
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $permissions = $row;
}
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====================
// === Ambil Statistik
// ====================
$empStats = [];

// Total Karyawan (hanya aktif)
$sql = "SELECT COUNT(*) as total FROM dbo.m_emp WHERE aktif=1";
$stmt = sqlsrv_query($conn, $sql);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$empStats['total'] = $row['total'];
sqlsrv_free_stmt($stmt);

// Per Departemen (hanya aktif)
$sql = "SELECT m_dept.dept, COUNT(m_emp.nik) as count
        FROM dbo.m_dept
        LEFT JOIN dbo.m_bag ON m_dept.id_dept = m_bag.id_dept
        LEFT JOIN dbo.m_subbag ON m_bag.id_bag = m_subbag.id_bag
        LEFT JOIN dbo.m_emp ON m_subbag.id_subbag = m_emp.id_subbag AND m_emp.aktif=1
        GROUP BY m_dept.dept";
$stmt = sqlsrv_query($conn, $sql);
$empStats['by_department'] = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $empStats['by_department'][$row['dept']] = $row['count'];
}
sqlsrv_free_stmt($stmt);

// Per Golongan (hanya aktif, satukan Magang & Training)
$sql = "SELECT m_gol.golongan, COUNT(m_emp.nik) as count
        FROM dbo.m_gol
        LEFT JOIN dbo.m_emp ON m_gol.id_gol = m_emp.id_gol AND m_emp.aktif=1
        GROUP BY m_gol.golongan";
$stmt = sqlsrv_query($conn, $sql);
$empStats['by_gol'] = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $gol = $row['golongan'];

    // Satukan Magang 1 & 2
    if (stripos($gol, 'Magang') !== false) {
        $gol = 'Magang';
    }
    // Satukan Training 1 & 2
    if (stripos($gol, 'Training') !== false) {
        $gol = 'Training';
    }

    if (!isset($empStats['by_gol'][$gol])) {
        $empStats['by_gol'][$gol] = 0;
    }
    $empStats['by_gol'][$gol] += $row['count'];
}
sqlsrv_free_stmt($stmt);

// ====================
// === Data Karyawan Baru (5 terbaru aktif)
// ====================
$recentEmployees = [];
$sql = "SELECT TOP 5 nik, nama_lengkap, upddate, upduser 
        FROM dbo.m_emp 
        WHERE aktif=1
        ORDER BY upddate DESC";
$stmt = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $recentEmployees[] = $row;
}
sqlsrv_free_stmt($stmt);

// Header & Sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

  <style>
    :root {
      --primary-color: #007bff;
      --secondary-color: #6c757d;
      --success-color: #28a745;
      --info-color: #17a2b8;
      --warning-color: #ffc107;
      --danger-color: #dc3545;
      --light-color: #f8f9fa;
      --dark-color: #343a40;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f7fa;
    }
    
    .content-wrapper {
      background-color: #f5f7fa;
    }
    
    .dashboard-header {
      padding: 20px 0 10px;
      margin-bottom: 20px;
      border-bottom: 1px solid #eaeaea;
    }
    
    .dashboard-title {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 5px;
    }
    
    .breadcrumb {
      background: transparent;
      padding: 0;
      margin-bottom: 0;
    }
    
    .stats-container {
      margin-bottom: 25px;
    }
    
    .stat-card {
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: none;
      transition: transform 0.3s, box-shadow 0.3s;
      overflow: hidden;
      height: 100%;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }
    
    .stat-card.primary {
      background: linear-gradient(120deg, #4e73df 0%, #224abe 100%);
      color: white;
    }
    
    .stat-card.warning {
      background: linear-gradient(120deg, #f6c23e 0%, #dda20a 100%);
      color: white;
    }
    
    .stat-card .card-body {
      padding: 1.8rem;
      display: flex;
      align-items: center;
    }
    
    .stat-icon {
      font-size: 2.8rem;
      margin-right: 1.2rem;
      opacity: 0.9;
    }
    
    .stat-content {
      flex: 1;
    }
    
    .stat-title {
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.6rem;
      font-weight: 500;
      opacity: 0.9;
    }
    
    .stat-value {
      font-size: 2.2rem;
      margin-bottom: 0;
      font-weight: 700;
    }
    
    .chart-card {
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: none;
      margin-bottom: 25px;
      background: white;
    }
    
    .chart-card .card-header {
      background: white;
      border-bottom: 1px solid #eaecf4;
      padding: 1.2rem 1.5rem;
      border-top-left-radius: 12px !important;
      border-top-right-radius: 12px !important;
    }
    
    .chart-card .card-header h3 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 600;
      color: #2c3e50;
    }
    
    .chart-card .card-header h3 i {
      margin-right: 10px;
      color: #4e73df;
    }
    
    .chart-card .card-body {
      padding: 1.5rem;
    }
    
    .chart-container {
      height: 300px;
      position: relative;
    }
    
    .table-card {
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: none;
      margin-bottom: 25px;
      background: white;
    }
    
    .table-card .card-header {
      background: linear-gradient(120deg, #36b9cc 0%, #2a96a5 100%);
      color: white;
      padding: 1.2rem 1.5rem;
      border-top-left-radius: 12px !important;
      border-top-right-radius: 12px !important;
    }
    
    .table-card .card-header h3 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 600;
    }
    
    .table-card .card-header h3 i {
      margin-right: 10px;
    }
    
    .table-card .card-body {
      padding: 0;
    }
    
    .table-responsive {
      border-radius: 0 0 12px 12px;
      overflow: hidden;
    }
    
    .table th {
      background-color: #f8f9fc;
      color: #4e73df;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      border-top: none;
    }
    
    .table td {
      padding: 0.75rem 1.5rem;
      vertical-align: middle;
    }
    
    .table tbody tr {
      transition: background-color 0.2s;
    }
    
    .table tbody tr:hover {
      background-color: #f8f9fc;
    }
    
    .badge-count {
      font-size: 0.85rem;
      padding: 0.35em 0.65em;
      border-radius: 50rem;
      font-weight: 600;
    }
    
    .employee-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .employee-item {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #eaecf4;
      display: flex;
      align-items: center;
      transition: background-color 0.2s;
    }
    
    .employee-item:hover {
      background-color: #f8f9fc;
    }
    
    .employee-item:last-child {
      border-bottom: none;
    }
    
    .employee-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: linear-gradient(120deg, #4e73df 0%, #224abe 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      margin-right: 1.2rem;
      flex-shrink: 0;
      font-size: 1.1rem;
    }
    
    .employee-info {
      flex: 1;
    }
    
    .employee-name {
      font-weight: 600;
      margin-bottom: 0.2rem;
      color: #2c3e50;
    }
    
    .employee-details {
      font-size: 0.85rem;
      color: #6c757d;
    }
    
    .employee-date {
      font-size: 0.8rem;
      color: #adb5bd;
      text-align: right;
    }
    
    @media (max-width: 768px) {
      .stat-card .card-body {
        padding: 1.2rem;
      }
      
      .stat-icon {
        font-size: 2.2rem;
        margin-right: 1rem;
      }
      
      .stat-value {
        font-size: 1.8rem;
      }
      
      .employee-item {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .employee-date {
        margin-top: 0.5rem;
        text-align: left;
      }
    }
  </style>

<div class="wrapper">
  <div class="content-wrapper">
    <section class="content">
      <div class="container-fluid">
        <div class="dashboard-header">
          <div class="row">
            <div class="col-sm-6">
              <h1 class="dashboard-title">Dashboard Karyawan</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="../dashboard.php">Beranda</a></li>
                <li class="breadcrumb-item active">Dashboard Karyawan</li>
              </ol>
            </div>
          </div>
        </div>
        
        <div class="row stats-container">
          <div class="col-lg-6 col-md-6">
            <div class="card stat-card primary">
              <div class="card-body">
                <div class="stat-icon">
                  <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-title">Total Karyawan Aktif</div>
                  <h2 class="stat-value"><?= number_format($empStats['total']) ?></h2>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 col-md-6">
            <div class="card stat-card warning">
              <div class="card-body">
                <div class="stat-icon">
                  <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-title">Jumlah Golongan</div>
                  <h2 class="stat-value"><?= number_format(count($empStats['by_gol'])) ?></h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-lg-6">
            <div class="card chart-card">
              <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i>Distribusi per Departemen</h3>
              </div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="deptChart"></canvas>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card chart-card">
              <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i>Distribusi per Golongan</h3>
              </div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="golChart"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-lg-6">
            <div class="card table-card">
              <div class="card-header">
                <h3><i class="fas fa-list"></i>Tabel Rincian Golongan</h3>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead>
                      <tr>
                        <th>Golongan</th>
                        <th class="text-right">Jumlah Karyawan</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach($empStats['by_gol'] as $gol => $jumlah): ?>
                        <tr>
                          <td><?= htmlspecialchars($gol) ?></td>
                          <td class="text-right"><span class="badge badge-primary badge-count"><?= number_format($jumlah) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                      <tr class="font-weight-bold">
                        <td>Total</td>
                        <td class="text-right">
                          <span class="badge badge-success badge-count"><?= number_format(array_sum($empStats['by_gol'])) ?></span>
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-lg-6">
            <div class="card chart-card">
              <div class="card-header">
                <h3><i class="fas fa-user-clock"></i>Karyawan Terbaru</h3>
              </div>
              <div class="card-body p-0">
                <?php if (empty($recentEmployees)): ?>
                  <div class="p-4 text-center text-muted">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <p>Tidak ada karyawan baru</p>
                  </div>
                <?php else: ?>
                  <ul class="employee-list">
                    <?php foreach($recentEmployees as $emp): 
                      $initial = substr($emp['nama_lengkap'], 0, 1);
                      $updateDate = $emp['upddate'] ? $emp['upddate']->format('d M Y H:i') : 'Tanggal tidak tersedia';
                    ?>
                      <li class="employee-item">
                        <div class="employee-avatar">
                          <?= $initial ?>
                        </div>
                        <div class="employee-info">
                          <div class="employee-name"><?= $emp['nama_lengkap'] ?></div>
                          <div class="employee-details">NIK: <?= $emp['nik'] ?> | Diupdate oleh: <?= $emp['upduser'] ?></div>
                        </div>
                        <div class="employee-date"><?= $updateDate ?></div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
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
// Warna yang lebih profesional
const professionalColors = [
  '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
  '#858796', '#f8f9fc', '#5a5c69', '#2e59d9', '#17a673'
];

function getProfessionalColors(count) {
  return professionalColors.slice(0, count);
}

const deptChart = new Chart(document.getElementById('deptChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($empStats['by_department'])) ?>,
    datasets: [{
      label: 'Jumlah Karyawan',
      data: <?= json_encode(array_values($empStats['by_department'])) ?>,
      backgroundColor: professionalColors[0],
      barPercentage: 0.6,
      categoryPercentage: 0.8
    }]
  },
  options: {
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          precision: 0
        }
      }
    },
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        backgroundColor: 'rgb(255, 255, 255)',
        bodyColor: '#858796',
        titleColor: '#6e707e',
        titleMarginBottom: 10,
        borderColor: '#dddfeb',
        borderWidth: 1,
        padding: 15,
        displayColors: false
      }
    }
  }
});

const golLabels = <?= json_encode(array_keys($empStats['by_gol'])) ?>;
const golData = <?= json_encode(array_values($empStats['by_gol'])) ?>;
const golColors = getProfessionalColors(golLabels.length);

const golChart = new Chart(document.getElementById('golChart'), {
  type: 'doughnut',
  data: {
    labels: golLabels,
    datasets: [{
      data: golData,
      backgroundColor: golColors,
      hoverBackgroundColor: golColors.map(color => color.replace(')', ', 0.9)').replace('rgb', 'rgba')),
      hoverBorderColor: 'rgba(234, 236, 244, 1)',
    }]
  },
  options: {
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
      legend: {
        position: 'right',
        labels: {
          usePointStyle: true,
          padding: 20
        }
      },
      tooltip: {
        backgroundColor: 'rgb(255, 255, 255)',
        bodyColor: '#858796',
        titleColor: '#6e707e',
        borderColor: '#dddfeb',
        borderWidth: 1,
        padding: 15,
        displayColors: false,
        callbacks: {
          label: function(context) {
            var label = context.label || '';
            var value = context.formattedValue || '';
            var sum = context.dataset.data.reduce((a, b) => a + b, 0);
            var percentage = Math.round((value / sum) * 100) + '%';
            return label + ': ' + value + ' (' + percentage + ')';
          }
        }
      }
    }
  }
});
</script>
