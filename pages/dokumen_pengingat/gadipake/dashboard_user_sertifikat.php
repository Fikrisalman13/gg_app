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
// HELPER
// ======================================================
function getCount($conn, string $sql, array $params = []): int {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

// ======================================================
// REMINDER INTERVAL SERTIFIKAT
// ======================================================
$intervalSertifikat = 7;
$stmtInterval = sqlsrv_query(
    $conn,
    "SELECT nilai FROM reminder_interval WHERE kunci = ?",
    ['reminder_interval_sertifikat']
);
if ($stmtInterval && $r = sqlsrv_fetch_array($stmtInterval, SQLSRV_FETCH_ASSOC)) {
    $intervalSertifikat = (int)$r['nilai'];
}

// ======================================================
// DATA SERTIFIKAT
// ======================================================
$sertifikatAktif = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat
     WHERE expire_date >= CAST(GETDATE() AS DATE)"
);

$sertifikatReminder = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat
     WHERE expire_date BETWEEN CAST(GETDATE() AS DATE)
     AND DATEADD(DAY, ?, CAST(GETDATE() AS DATE))",
    [$intervalSertifikat]
);

$sertifikatExpired = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat
     WHERE expire_date < CAST(GETDATE() AS DATE)"
);

$sertifikatTotal = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat"
);
?>

<div class="content-wrapper">
<!-- Content Header -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h2 class="m-0">Dashboard Sertifikat</h2>
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
box('info',    $sertifikatAktif,    'Sertifikat Aktif',    '/dokumen_pengingat/pages/dokumen_sertifikat_aktif.php',    'fa-certificate');
box('warning', $sertifikatReminder, 'Sertifikat Reminder', '/dokumen_pengingat/pages/dokumen_sertifikat_reminder.php', 'fa-certificate');
box('danger',  $sertifikatExpired,  'Sertifikat Expired',  '/dokumen_pengingat/pages/dokumen_sertifikat_expired.php',  'fa-certificate');
box('purple',  $sertifikatTotal,    'Semua Sertifikat',    '/dokumen_pengingat/pages/dokumen_sertifikat.php',          'fa-certificate');
?>

</div>
</div>
</section>
</div>

<?php include '../includes/footer.php'; ?>
