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
    $v=trim((string)$v);
    if($v==='') return 0;
    $v=preg_replace('/[^0-9.,]/','',$v);
    if($v==='') return 0;
    if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; }
    $f=str_replace('.','',$v); $f=str_replace(',','.', $f);
    return is_numeric($f)?(float)$f:0;
}

$id = intval($_POST['id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
$shift = strtoupper(trim($_POST['shift_kode'] ?? ''));
if ($tanggal === '' || $shift === '') { echo json_encode(['success'=>false,'message'=>'Tanggal dan shift wajib diisi.']); exit; }
if (!in_array($shift, ['P','S','M'], true)) { echo json_encode(['success'=>false,'message'=>'Shift harus P, S, atau M.']); exit; }

$sv1 = n($_POST['sv30_aerasi1_pct'] ?? '0');
$sv2 = n($_POST['sv30_aerasi2_pct'] ?? '0');
$sv3 = n($_POST['sv30_aerasi3_pct'] ?? '0');
$sv4 = n($_POST['sv30_aerasi4_pct'] ?? '0');
$phEqual = n($_POST['ph_equal'] ?? '0');
$phAkhir = n($_POST['ph_akhir'] ?? '0');
$dewBawah = n($_POST['dewatering_bawah_per_day'] ?? '0');
$dewAtas = n($_POST['dewatering_atas_sinci1_per_day_ton'] ?? '0');
$sinci2 = n($_POST['sinci2_per_day_ton'] ?? '0');
$ket = trim($_POST['ket'] ?? '');

if ($phEqual > 14 || $phAkhir > 14) { echo json_encode(['success'=>false,'message'=>'Nilai pH tidak boleh lebih dari 14.']); exit; }

if ($id > 0) {
    $sql = "UPDATE dbo.pencatatan_ipal_harian
            SET tanggal=?, shift_kode=?, sv30_aerasi1_pct=?, sv30_aerasi2_pct=?, sv30_aerasi3_pct=?, sv30_aerasi4_pct=?,
                ph_equal=?, ph_akhir=?, dewatering_bawah_per_day=?, dewatering_atas_sinci1_per_day_ton=?, sinci2_per_day_ton=?, ket=?,
                updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [$tanggal,$shift,$sv1,$sv2,$sv3,$sv4,$phEqual,$phAkhir,$dewBawah,$dewAtas,$sinci2,$ket,$_SESSION['UserName'],$id];
    $msg = 'Data berhasil diperbarui.';
} else {
    $cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.pencatatan_ipal_harian WHERE tanggal=? AND shift_kode=?", [$tanggal,$shift]);
    $row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
    if ($cek) sqlsrv_free_stmt($cek);

    if ($row) {
        $sql = "UPDATE dbo.pencatatan_ipal_harian
                SET sv30_aerasi1_pct=?, sv30_aerasi2_pct=?, sv30_aerasi3_pct=?, sv30_aerasi4_pct=?,
                    ph_equal=?, ph_akhir=?, dewatering_bawah_per_day=?, dewatering_atas_sinci1_per_day_ton=?, sinci2_per_day_ton=?, ket=?,
                    updateby=?, updateat=GETDATE()
                WHERE id=?";
        $params = [$sv1,$sv2,$sv3,$sv4,$phEqual,$phAkhir,$dewBawah,$dewAtas,$sinci2,$ket,$_SESSION['UserName'],$row['id']];
        $msg = 'Data shift tanggal tersebut berhasil diperbarui.';
    } else {
        $sql = "INSERT INTO dbo.pencatatan_ipal_harian (
                    tanggal, shift_kode, sv30_aerasi1_pct, sv30_aerasi2_pct, sv30_aerasi3_pct, sv30_aerasi4_pct,
                    ph_equal, ph_akhir, dewatering_bawah_per_day, dewatering_atas_sinci1_per_day_ton, sinci2_per_day_ton, ket, creatby
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $params = [$tanggal,$shift,$sv1,$sv2,$sv3,$sv4,$phEqual,$phAkhir,$dewBawah,$dewAtas,$sinci2,$ket,$_SESSION['UserName']];
        $msg = 'Data berhasil disimpan.';
    }
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);
