<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ======================================================
// SESSION & AUTH
// ======================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['UserName'])) {
    header("Location: /dokumen_pengingat/login.php");
    exit;
}

// ======================================================
// CORE INCLUDE
// ======================================================
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// ======================================================
// HELPER FUNCTION
// ======================================================
function getCount($conn, string $sql, array $params = []): int {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

// ======================================================
// REMINDER INTERVAL SURAT KENDARAAN
// ======================================================
$intervalKendaraan = 7;
$stmtInterval = sqlsrv_query(
    $conn,
    "SELECT nilai FROM reminder_interval WHERE kunci = ?",
    ['reminder_interval_surat_kendaraan']
);
if ($stmtInterval && $r = sqlsrv_fetch_array($stmtInterval, SQLSRV_FETCH_ASSOC)) {
    $intervalKendaraan = (int)$r['nilai'];
}

// ======================================================
// DATA SURAT KENDARAAN
// ======================================================
$kendaraanAktif = getCount($conn,
    "SELECT COUNT(*) total FROM surat_kendaraan
     WHERE expire_date >= CAST(GETDATE() AS DATE)"
);

$kendaraanReminder = getCount($conn,
    "SELECT COUNT(*) total FROM surat_kendaraan
     WHERE expire_date BETWEEN CAST(GETDATE() AS DATE)
     AND DATEADD(DAY, ?, CAST(GETDATE() AS DATE))",
    [$intervalKendaraan]
);

$kendaraanExpired = getCount($conn,
    "SELECT COUNT(*) total FROM surat_kendaraan
     WHERE expire_date < CAST(GETDATE() AS DATE)"
);

$kendaraanTotal = getCount($conn,
    "SELECT COUNT(*) total FROM surat_kendaraan"
);
?>

<div class="content-wrapper">
<!-- Content Header -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h2 class="m-0">Dashboard Surat Kendaraan</h2>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item active">Home</li>
        </ol>
      </div>
    </div>
  </div>
</section>

<!-- Main Content -->
<section class="content">
<div class="container-fluid">
<div class="row">

<?php
function box($color, $value, $label, $link, $icon) {
    echo "
    <div class='col-lg-3 col-6'>
      <div class='small-box bg-$color'>
        <div class='inner'>
          <h3>$value</h3>
          <p>$label</p>
        </div>
        <div class='icon'>
          <i class='fas $icon'></i>
        </div>
        <a href='$link' class='small-box-footer'>
          More info <i class='fas fa-arrow-circle-right'></i>
        </a>
      </div>
    </div>";
}
?>

<?php
box('info',    $kendaraanAktif,    'Surat Kendaraan Aktif',    '/dokumen_pengingat/pages/surat_kendaraan_aktif.php',    'fa-car');
box('warning', $kendaraanReminder, 'Surat Kendaraan Reminder', '/dokumen_pengingat/pages/surat_kendaraan_reminder.php', 'fa-car');
box('danger',  $kendaraanExpired,  'Surat Kendaraan Expired',  '/dokumen_pengingat/pages/surat_kendaraan_expired.php',  'fa-car');
box('purple',  $kendaraanTotal,    'Semua Surat Kendaraan',    '/dokumen_pengingat/pages/surat_kendaraan.php',          'fa-car');
?>

</div>
</div>
</section>
</div>

<?php include '../includes/footer.php'; ?>
