<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId=236; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: listrik_gardu_induk.php'); exit; }

$sql = "WITH cte AS (
            SELECT x.*, 
                   LEAD(x.lvbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS lvbp_next,
                   LEAD(x.vbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS vbp_next
            FROM dbo.listrik_gardu_induk_harian x
        )
        SELECT cte.*,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                    ELSE (((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) END AS total_daya_perday_kw,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                    ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) END AS total_daya_perjam_kwh,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                    ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) * cte.rp_per_kwh) END AS biaya_perday_rp,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.pf_standar,0)=0 THEN NULL
                    ELSE (((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.pf_standar) END AS kva_pln_perjam,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.kapasitas_kva,0)=0 THEN NULL
                    ELSE ((((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.kapasitas_kva) * 100.0) END AS efisiensi_persen
        FROM cte
        WHERE cte.id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: listrik_gardu_induk.php'); exit; }
$fmtNum=function($v,$d=2){ return is_numeric($v)?number_format((float)$v,$d,'.',','):'-'; };
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('d-m-Y'); return $v?date('d-m-Y',strtotime((string)$v)):'-'; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail Listrik Gardu Induk</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="listrik_gardu_induk.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body table-responsive"><table class="table table-sm table-bordered">
<tr><th width="30%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
<tr><th>LVBP (kWh)</th><td><?= htmlspecialchars($fmtNum($row['lvbp_kwh'] ?? 0,3)) ?></td></tr>
<tr><th>VBP (kWh)</th><td><?= htmlspecialchars($fmtNum($row['vbp_kwh'] ?? 0,3)) ?></td></tr>
<tr><th>KVARH</th><td><?= htmlspecialchars($fmtNum($row['kvarh'] ?? 0,3)) ?></td></tr>
<tr><th>Cos Phi</th><td><?= htmlspecialchars($fmtNum($row['cos_phi'] ?? 0,3)) ?></td></tr>
<tr><th>Total Daya Terpakai Perday (kW)</th><td><?= htmlspecialchars($fmtNum($row['total_daya_perday_kw'],2)) ?></td></tr>
<tr><th>Total Daya Terpakai Perjam (kWh)</th><td><?= htmlspecialchars($fmtNum($row['total_daya_perjam_kwh'],2)) ?></td></tr>
<tr><th>Rp per kWh</th><td><?= htmlspecialchars($fmtNum($row['rp_per_kwh'] ?? 0,3)) ?></td></tr>
<tr><th>Biaya Pemakaian Per Day (Rp)</th><td><?= htmlspecialchars($fmtNum($row['biaya_perday_rp'],2)) ?></td></tr>
<tr><th>Efisiensi (%)</th><td><?= htmlspecialchars($fmtNum($row['efisiensi_persen'],2)) ?></td></tr>
<tr><th>KVA PLN per Jam</th><td><?= htmlspecialchars($fmtNum($row['kva_pln_perjam'],2)) ?></td></tr>
<tr><th>Keterangan</th><td><?= htmlspecialchars($row['ket'] ?? '') ?></td></tr>
<tr><th>Note</th><td><?= nl2br(htmlspecialchars($row['note'] ?? '')) ?></td></tr>
</table></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
