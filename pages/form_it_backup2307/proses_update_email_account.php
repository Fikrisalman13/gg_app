<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket is required for update!']);
    exit;
}

// Ambil data form
$nama        = $_POST['nama_pemohon'] ?? '';
$jab         = $_POST['jabatan'] ?? '';
$dept        = $_POST['departemen'] ?? '';
$area        = $_POST['area'] ?? '';
$tgl_form    = $_POST['tgl_pengajuan'] ?? '';
$emailAccount = $_POST['email_user'] ?? '';
$reqEmail    = !empty($_POST['request_email_account']) ? 1 : 0;
$reqChange   = !empty($_POST['request_change_access']) ? 1 : 0;
$reqSetting  = !empty($_POST['request_setting_device']) ? 1 : 0;
$deviceAndroid = !empty($_POST['device_android']) ? 1 : 0;
$deviceIOS     = !empty($_POST['device_ios']) ? 1 : 0;
$deviceLainnya = !empty($_POST['device_lainnya']) ? 1 : 0;
$deviceLainnyaText = $_POST['device_lainnya_text'] ?? '';
$aksesLocal   = !empty($_POST['akses_email_local']) ? 1 : 0;
$aksesGlobal  = !empty($_POST['akses_email_global']) ? 1 : 0;
$globalKirim  = !empty($_POST['global_kirim']) ? 1 : 0;
$globalTerima = !empty($_POST['global_terima']) ? 1 : 0;
$globalFull   = !empty($_POST['global_full']) ? 1 : 0;
$keterangan   = $_POST['keterangan'] ?? '';
$updatedBy    = $_SESSION['NamaLengkap'] ?? $nama;

// Format tanggal ke SQL Server (convert to YYYY-MM-DD)
$tgl_sql = null;
if (!empty($tgl_form)) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_form)) {
        $tgl_sql = $tgl_form;
    } else {
        $parts = explode("-", $tgl_form);
        if (count($parts) == 3) {
            $tgl_sql = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
        }
    }
}

// Koneksi
global $conn;
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
}
if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi gagal']);
    exit;
}

$sql = "UPDATE Form_Pengajuan_Email_Account SET
    nama_pemohon=?, jabatan=?, departemen=?, area=?, tgl_pengajuan=?,
    request_email_account=?, request_change_access=?, request_setting_device=?,
    device_android=?, device_ios=?, device_lainnya=?, device_lainnya_text=?,
    akses_email_local=?, akses_email_global=?, global_kirim=?, global_terima=?, global_full=?,
    keterangan=?, email_account=?
    WHERE ticket=?";
$params = [
    $nama, $jab, $dept, $area, $tgl_sql,
    $reqEmail, $reqChange, $reqSetting,
    $deviceAndroid, $deviceIOS, $deviceLainnya, $deviceLainnyaText,
    $aksesLocal, $aksesGlobal, $globalKirim, $globalTerima, $globalFull,
    $keterangan, $emailAccount, $ticket
];
$stmt = sqlsrv_query($conn, $sql, $params);
if (!$stmt) {
    echo json_encode(['success'=>false,'message'=>'Gagal update Email Account: '.print_r(sqlsrv_errors(), true)]);
    exit;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
echo json_encode(['success'=>true,'message'=>'Pengajuan Email Account berhasil diupdate','ticket'=>$ticket]);
