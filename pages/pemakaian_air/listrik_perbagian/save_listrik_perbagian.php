<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function n($v){
    $v = trim((string)$v);
    if($v==='') return 0;
    $v = preg_replace('/[^0-9.,]/','',$v);
    if($v==='') return 0;
    if(strpos($v,'.')!==false){
        $a = str_replace(',','',$v);
        if(is_numeric($a)) return (float)$a;
    }
    $f = str_replace('.','',$v);
    $f = str_replace(',','.',$f);
    return is_numeric($f) ? (float)$f : 0;
}

function getKwhPlnByDate($conn, $tanggal){
    $sql = "WITH cte AS (
                SELECT CAST(x.tanggal AS DATE) AS tanggal, x.id, x.lvbp_kwh, x.vbp_kwh, x.faktor_kali,
                       LEAD(x.lvbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS lvbp_next,
                       LEAD(x.vbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS vbp_next
                FROM dbo.listrik_gardu_induk_harian x
            )
            SELECT TOP 1
                CASE WHEN lvbp_next IS NULL OR vbp_next IS NULL THEN 0
                     ELSE (((lvbp_next - lvbp_kwh) * faktor_kali) + ((vbp_next - vbp_kwh) * faktor_kali))
                END AS kwh_pln
            FROM cte
            WHERE tanggal = ?
            ORDER BY id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return is_numeric($row['kwh_pln'] ?? null) ? (float)$row['kwh_pln'] : 0;
}

$tanggal = trim($_POST['tanggal'] ?? '');
if ($tanggal === '') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }
$id = intval($_POST['id'] ?? 0);

$kwhPlnInput = n($_POST['kwh_pln'] ?? '0');
$faktor = n($_POST['faktor_konversi'] ?? '650');
$kapasitas = n($_POST['kapasitas_pembagi'] ?? '3292');
if ($faktor <= 0) $faktor = 650;
if ($kapasitas <= 0) $kapasitas = 3292;

$c = n($_POST['kwh_utility_amp_acb'] ?? '0');
$e = n($_POST['df_amp_acb'] ?? '0');
$g = n($_POST['weaving1_amp_acb'] ?? '0');
$i = n($_POST['weaving2_amp_acb'] ?? '0');
$ket = trim($_POST['ket'] ?? '');

$kwhPlnAuto = getKwhPlnByDate($conn, $tanggal);
$kwhPln = $kwhPlnInput > 0 ? $kwhPlnInput : $kwhPlnAuto;

$d = (($c * $faktor) / 1000) * 24;
$f = (($e * $faktor) / 1000) * 24;
$h = (($g * $faktor) / 1000) * 24;
$j = (($i * $faktor) / 1000) * 24;
$k = $d + $f + $h + (($j / 1000) * 24); // mengikuti formula Excel screenshot
$l = $c + $e + $g + $i;
$m = $k / 24;
$nEff = $kapasitas != 0 ? (($m / $kapasitas) * 100) : 0;

if ($id > 0) {
    $sql = "UPDATE dbo.listrik_perbagian_harian
            SET kwh_pln=?, faktor_konversi=?, kapasitas_pembagi=?,
                kwh_utility_amp_acb=?, df_amp_acb=?, weaving1_amp_acb=?, weaving2_amp_acb=?,
                kwh_utility_kwh_hari=?, df_kwh_hari=?, weaving1_kwh_hari=?, weaving2_kwh_hari=?,
                jumlah_kwh_hari=?, jumlah_ampere=?, kwh_per_jam=?, efisiensi_persen=?,
                ket=?, updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [
        $kwhPln, $faktor, $kapasitas,
        $c, $e, $g, $i,
        $d, $f, $h, $j,
        $k, $l, $m, $nEff,
        $ket, $_SESSION['UserName'], $id
    ];
    $msg = 'Data berhasil diperbarui.';
} else {
    $cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.listrik_perbagian_harian WHERE tanggal=?", [$tanggal]);
    $row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
    if ($cek) sqlsrv_free_stmt($cek);

    if ($row) {
        $sql = "UPDATE dbo.listrik_perbagian_harian
                SET kwh_pln=?, faktor_konversi=?, kapasitas_pembagi=?,
                    kwh_utility_amp_acb=?, df_amp_acb=?, weaving1_amp_acb=?, weaving2_amp_acb=?,
                    kwh_utility_kwh_hari=?, df_kwh_hari=?, weaving1_kwh_hari=?, weaving2_kwh_hari=?,
                    jumlah_kwh_hari=?, jumlah_ampere=?, kwh_per_jam=?, efisiensi_persen=?,
                    ket=?, updateby=?, updateat=GETDATE()
                WHERE id=?";
        $params = [
            $kwhPln, $faktor, $kapasitas,
            $c, $e, $g, $i,
            $d, $f, $h, $j,
            $k, $l, $m, $nEff,
            $ket, $_SESSION['UserName'], $row['id']
        ];
        $msg = 'Data berhasil diperbarui.';
    } else {
    $sql = "INSERT INTO dbo.listrik_perbagian_harian (
                tanggal, kwh_pln, faktor_konversi, kapasitas_pembagi,
                kwh_utility_amp_acb, df_amp_acb, weaving1_amp_acb, weaving2_amp_acb,
                kwh_utility_kwh_hari, df_kwh_hari, weaving1_kwh_hari, weaving2_kwh_hari,
                jumlah_kwh_hari, jumlah_ampere, kwh_per_jam, efisiensi_persen, ket, creatby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $params = [
        $tanggal, $kwhPln, $faktor, $kapasitas,
        $c, $e, $g, $i,
        $d, $f, $h, $j,
        $k, $l, $m, $nEff, $ket, $_SESSION['UserName']
    ];
    $msg = 'Data berhasil disimpan.';
    }
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);
