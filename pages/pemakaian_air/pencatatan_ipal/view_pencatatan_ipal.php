<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { $_SESSION['error'] = 'ID tidak valid.'; header('Location: pencatatan_ipal.php'); exit; }
$tanggalParam = trim($_GET['tanggal'] ?? '');
$shiftParam = strtoupper(trim($_GET['shift'] ?? ''));

function row_has_meter_values($row) {
    if (!$row || !is_array($row)) return false;
    $keys = [
        'tanggal', 'shift_kode',
        'sv30_aerasi1_pct', 'sv30_aerasi2_pct', 'sv30_aerasi3_pct', 'sv30_aerasi4_pct',
        'ph_equal', 'ph_akhir',
        'dewatering_bawah_per_day', 'dewatering_atas_sinci1_per_day_ton', 'sinci2_per_day_ton'
    ];
    foreach ($keys as $k) {
        if (!array_key_exists($k, $row)) continue;
        $v = $row[$k];
        if ($v instanceof DateTime) return true;
        if ($v !== null && $v !== '') return true;
    }
    return false;
}

$stmt = sqlsrv_query($conn, "SELECT
    id,
    tanggal AS tanggal,
    shift_kode AS shift_kode,
    sv30_aerasi1_pct AS sv30_aerasi1_pct,
    sv30_aerasi2_pct AS sv30_aerasi2_pct,
    sv30_aerasi3_pct AS sv30_aerasi3_pct,
    sv30_aerasi4_pct AS sv30_aerasi4_pct,
    ph_equal AS ph_equal,
    ph_akhir AS ph_akhir,
    dewatering_bawah_per_day AS dewatering_bawah_per_day,
    dewatering_atas_sinci1_per_day_ton AS dewatering_atas_sinci1_per_day_ton,
    sinci2_per_day_ton AS sinci2_per_day_ton,
    ket AS ket,
    creatby AS creatby
  FROM dbo.pencatatan_ipal_harian
  WHERE id=?", [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);
$row = $row ? array_change_key_case($row, CASE_LOWER) : null;

if ((!$row || !row_has_meter_values($row)) && $tanggalParam !== '' && in_array($shiftParam, ['P','S','M'], true)) {
    $stmt2 = sqlsrv_query($conn, "SELECT TOP 1
        id,
        tanggal AS tanggal,
        shift_kode AS shift_kode,
        sv30_aerasi1_pct AS sv30_aerasi1_pct,
        sv30_aerasi2_pct AS sv30_aerasi2_pct,
        sv30_aerasi3_pct AS sv30_aerasi3_pct,
        sv30_aerasi4_pct AS sv30_aerasi4_pct,
        ph_equal AS ph_equal,
        ph_akhir AS ph_akhir,
        dewatering_bawah_per_day AS dewatering_bawah_per_day,
        dewatering_atas_sinci1_per_day_ton AS dewatering_atas_sinci1_per_day_ton,
        sinci2_per_day_ton AS sinci2_per_day_ton,
        ket AS ket,
        creatby AS creatby
      FROM dbo.pencatatan_ipal_harian
      WHERE CAST(tanggal AS DATE)=? AND shift_kode=?
      ORDER BY id DESC", [$tanggalParam, $shiftParam]);
    $row2 = $stmt2 ? sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC) : null;
    if ($stmt2) sqlsrv_free_stmt($stmt2);
    if ($row2) $row = array_change_key_case($row2, CASE_LOWER);
}

if (!$row) { $_SESSION['error'] = 'Data tidak ditemukan.'; header('Location: pencatatan_ipal.php'); exit; }

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$fmtNum = function($v){ return number_format((float)$v,2,'.',','); };
$tglRaw = $row['tanggal'] ?? null;
if ($tglRaw instanceof DateTime) {
    $tgl = $tglRaw->format('d-m-Y');
} elseif (!empty($tglRaw)) {
    $ts = strtotime((string)$tglRaw);
    $tgl = $ts ? date('d-m-Y', $ts) : '-';
} else {
    $tgl = '-';
}
?>
<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid"><h1>Detail Pencatatan IPAL</h1></div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-body table-responsive p-0">
          <table class="table table-bordered mb-0">
            <tr><th width="260">Tanggal</th><td><?= htmlspecialchars($tgl) ?></td></tr>
            <tr><th>Shift</th><td><?= htmlspecialchars((string)($row['shift_kode'] ?? '-')) ?></td></tr>
            <tr><th>SV30 Aerasi 1 (%)</th><td><?= htmlspecialchars($fmtNum($row['sv30_aerasi1_pct'] ?? 0)) ?></td></tr>
            <tr><th>SV30 Aerasi 2 (%)</th><td><?= htmlspecialchars($fmtNum($row['sv30_aerasi2_pct'] ?? 0)) ?></td></tr>
            <tr><th>SV30 Aerasi 3 (%)</th><td><?= htmlspecialchars($fmtNum($row['sv30_aerasi3_pct'] ?? 0)) ?></td></tr>
            <tr><th>SV30 Aerasi 4 (%)</th><td><?= htmlspecialchars($fmtNum($row['sv30_aerasi4_pct'] ?? 0)) ?></td></tr>
            <tr><th>pH Equal</th><td><?= htmlspecialchars($fmtNum($row['ph_equal'] ?? 0)) ?></td></tr>
            <tr><th>pH Akhir</th><td><?= htmlspecialchars($fmtNum($row['ph_akhir'] ?? 0)) ?></td></tr>
            <tr><th>Dewatering Bawah (Per Day)</th><td><?= htmlspecialchars($fmtNum($row['dewatering_bawah_per_day'] ?? 0)) ?></td></tr>
            <tr><th>Dewatering Atas & Sinci 1 (Per Day/Ton)</th><td><?= htmlspecialchars($fmtNum($row['dewatering_atas_sinci1_per_day_ton'] ?? 0)) ?></td></tr>
            <tr><th>Sinci 2 (Per Day/Ton)</th><td><?= htmlspecialchars($fmtNum($row['sinci2_per_day_ton'] ?? 0)) ?></td></tr>
            <tr><th>Keterangan</th><td><?= htmlspecialchars((string)($row['ket'] ?? '-')) ?></td></tr>
            <tr><th>Created By</th><td><?= htmlspecialchars((string)($row['creatby'] ?? '-')) ?></td></tr>
          </table>
        </div>
        <div class="card-footer">
          <a href="pencatatan_ipal.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>
