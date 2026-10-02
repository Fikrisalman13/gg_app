<?php
// ======================================================
// dashboard/index.php — FINAL (ADMINLTE + PERMISSION)
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------
// CORE INCLUDE
// ------------------------------------------------------
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// ------------------------------------------------------
// PERMISSION (DASHBOARD)
// ------------------------------------------------------
$menuId = 167; // ⚠️ sesuaikan MenuId Dashboard di DB
requireView($conn, $menuId);

// ------------------------------------------------------
// HELPER
// ------------------------------------------------------
function getCount($conn, string $sql, array $params = []): int {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

function getInterval($conn, string $key, int $default = 7): int {
    $stmt = sqlsrv_query(
        $conn,
        "SELECT setting_value FROM dr_setting WHERE setting_key = ?",
        [$key]
    );
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return (int)$row['setting_value'];
    }
    return $default;
}

// ------------------------------------------------------
// INTERVAL
// ------------------------------------------------------
$intervalKontrak   = getInterval($conn, 'reminder_interval_kontrak');
$intervalSertifikat= getInterval($conn, 'reminder_interval_sertifikat');
$intervalKendaraan = getInterval($conn, 'reminder_interval_surat_kendaraan');

// ------------------------------------------------------
// DATA KONTRAK
// ------------------------------------------------------
$kontrakAktif = getCount($conn,
    "SELECT COUNT(*) total FROM dr_kontrak WHERE expire_date >= CAST(GETDATE() AS DATE)"
);

$kontrakReminder = getCount($conn,
    "SELECT COUNT(*) total FROM dr_kontrak
     WHERE expire_date BETWEEN CAST(GETDATE() AS DATE)
     AND DATEADD(DAY, ?, CAST(GETDATE() AS DATE))",
    [$intervalKontrak]
);

$kontrakExpired = getCount($conn,
    "SELECT COUNT(*) total FROM dr_kontrak WHERE expire_date < CAST(GETDATE() AS DATE)"
);

$kontrakTotal = getCount($conn,
    "SELECT COUNT(*) total FROM dr_kontrak"
);

// ------------------------------------------------------
// DATA SERTIFIKAT
// ------------------------------------------------------
$sertifikatAktif = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat WHERE expire_date >= CAST(GETDATE() AS DATE)"
);

$sertifikatReminder = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat
     WHERE expire_date BETWEEN CAST(GETDATE() AS DATE)
     AND DATEADD(DAY, ?, CAST(GETDATE() AS DATE))",
    [$intervalSertifikat]
);

$sertifikatExpired = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat WHERE expire_date < CAST(GETDATE() AS DATE)"
);

$sertifikatTotal = getCount($conn,
    "SELECT COUNT(*) total FROM dr_sertifikat"
);

// ------------------------------------------------------
// DATA SURAT KENDARAAN
// ------------------------------------------------------
$kendaraanAktif = getCount($conn,
    "SELECT COUNT(*) total FROM dr_surat_kendaraan WHERE expire_date >= CAST(GETDATE() AS DATE)"
);

$kendaraanReminder = getCount($conn,
    "SELECT COUNT(*) total FROM dr_surat_kendaraan
     WHERE expire_date BETWEEN CAST(GETDATE() AS DATE)
     AND DATEADD(DAY, ?, CAST(GETDATE() AS DATE))",
    [$intervalKendaraan]
);

$kendaraanExpired = getCount($conn,
    "SELECT COUNT(*) total FROM dr_surat_kendaraan WHERE expire_date < CAST(GETDATE() AS DATE)"
);

$kendaraanTotal = getCount($conn,
    "SELECT COUNT(*) total FROM dr_surat_kendaraan"
);

// ------------------------------------------------------
// UI HELPER
// ------------------------------------------------------
function box($color, $value, $label, $icon, $link) {
    echo "
    <div class='col-lg-3 col-6'>
        <div class='small-box bg-$color'>
            <div class='inner'>
                <h3>$value</h3>
                <p>$label</p>
            </div>
            <div class='icon'><i class='$icon'></i></div>
            <a href='$link' class='small-box-footer'>
                More info <i class='fas fa-arrow-circle-right'></i>
            </a>
        </div>
    </div>";
}
?>

<div class="content-wrapper">
<section class="content-header">
    <div class="container-fluid">
        <h1>Dashboard</h1>
    </div>
</section>

<section class="content">
<div class="container-fluid">
<div class="row">

<?php
// KONTRAK
box('info',    $kontrakAktif,    'Kontrak Aktif',    'fas fa-file-contract', '/gg_app/pages/dokumen_pengingat/kontrak/aktif.php');
box('warning', $kontrakReminder, 'Kontrak Reminder', 'fas fa-file-contract', '/gg_app/pages/dokumen_pengingat/kontrak/reminder.php');
box('danger',  $kontrakExpired,  'Kontrak Kadaluarsa', 'fas fa-file-contract', '/gg_app/pages/dokumen_pengingat/kontrak/kadaluarsa.php');
box('secondary',$kontrakTotal,   'Semua Kontrak',   'fas fa-file-contract', '/gg_app/pages/dokumen_pengingat/kontrak/index.php');

// SERTIFIKAT
box('info',    $sertifikatAktif,    'Sertifikat Aktif',    'fas fa-certificate', '/gg_app/pages/dokumen_pengingat/sertifikat/aktif.php');
box('warning', $sertifikatReminder, 'Sertifikat Reminder', 'fas fa-certificate', '/gg_app/pages/dokumen_pengingat/sertifikat/reminder.php');
box('danger',  $sertifikatExpired,  'Sertifikat Kadaluarsa','fas fa-certificate', '/gg_app/pages/dokumen_pengingat/sertifikat/kadaluarsa.php');
box('secondary',$sertifikatTotal,   'Semua Sertifikat',   'fas fa-certificate', '/gg_app/pages/dokumen_pengingat/sertifikat/index.php');

// KENDARAAN
box('info',    $kendaraanAktif,    'Surat Kendaraan Aktif',    'fas fa-car', '/gg_app/pages/dokumen_pengingat/kendaraan/aktif.php');
box('warning', $kendaraanReminder, 'Surat Kendaraan Reminder', 'fas fa-car', '/gg_app/pages/dokumen_pengingat/kendaraan/reminder.php');
box('danger',  $kendaraanExpired,  'Surat Kendaraan Kadaluarsa','fas fa-car', '/gg_app/pages/dokumen_pengingat/kendaraan/kadaluarsa.php');
box('secondary',$kendaraanTotal,   'Semua Surat Kendaraan',   'fas fa-car', '/gg_app/pages/dokumen_pengingat/kendaraan/index.php');
?>

</div>
</div>
</section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
