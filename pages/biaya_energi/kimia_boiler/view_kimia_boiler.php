<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230;
requireView($conn, $menuId);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: kimia_boiler.php');
    exit;
}

$sql = "SELECT h.id, h.tanggal, h.pakai_kg, h.harga_rp, h.biaya_rp, h.catatan, h.creatby, h.creatat, h.updateby, h.updateat,
               m.nama_item, m.grup_laporan, m.satuan_pakai
        FROM dbo.kimia_boiler_harian h
        INNER JOIN dbo.kimia_boiler_master m ON m.id = h.master_id
        WHERE h.id=?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);

if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: kimia_boiler.php');
    exit;
}

$fmtNum = function($v){ if($v===null||$v==='') return '-'; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; };
$fmtDate = function($v){ if($v instanceof DateTime) return $v->format('Y-m-d'); return is_string($v)?$v:'-'; };
$fmtDateTime = function($v){ if($v instanceof DateTime) return $v->format('Y-m-d H:i:s'); return is_string($v)&&$v!==''?$v:'-'; };
?>

<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail Kimia Boiler</h1></div></section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="kimia_boiler.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <tr><th width="30%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
                            <tr><th>Parameter</th><td><?= htmlspecialchars($row['nama_item'] ?? '-') ?></td></tr>
                            <tr><th>Grup</th><td><?= htmlspecialchars($row['grup_laporan'] ?? '-') ?></td></tr>
                            <tr><th>Pakai (<?= htmlspecialchars($row['satuan_pakai'] ?? 'Kg') ?>)</th><td><?= htmlspecialchars($fmtNum($row['pakai_kg'] ?? null)) ?></td></tr>
                            <tr><th>Harga (Rp)</th><td><?= htmlspecialchars($fmtNum($row['harga_rp'] ?? null)) ?></td></tr>
                            <tr><th>Biaya (Rp)</th><td><?= htmlspecialchars($fmtNum($row['biaya_rp'] ?? null)) ?></td></tr>
                            <tr><th>Catatan</th><td><?= htmlspecialchars($row['catatan'] ?? '') ?></td></tr>
                            <tr><th>Created By</th><td><?= htmlspecialchars($row['creatby'] ?? '-') ?></td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars($fmtDateTime($row['creatat'] ?? null)) ?></td></tr>
                            <tr><th>Update By</th><td><?= htmlspecialchars($row['updateby'] ?? '-') ?></td></tr>
                            <tr><th>Update At</th><td><?= htmlspecialchars($fmtDateTime($row['updateat'] ?? null)) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>


