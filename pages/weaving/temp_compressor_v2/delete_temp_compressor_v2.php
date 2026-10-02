<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
weaving_require($conn, 'CanDelete', true);

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

$id = $isPost ? ($_POST['id'] ?? '') : ($_GET['id'] ?? '');
$tanggalParam = trim($isPost ? ($_POST['tanggal'] ?? '') : ($_GET['tanggal'] ?? ''));
$weavingParam = intval($isPost ? ($_POST['weaving'] ?? 0) : ($_GET['weaving'] ?? 0));
$compressorParam = intval($isPost ? ($_POST['compressor_no'] ?? 0) : ($_GET['compressor_no'] ?? 0));

if (!temp_compressor_v2_table_exists($conn)) {
    if ($isPost) json_response(['success' => false, 'message' => 'Tabel dbo.temp_compressor_v2 belum tersedia.']);
    $_SESSION['error'] = 'Tabel dbo.temp_compressor_v2 belum tersedia.';
    header('Location: temp_compressor_v2.php');
    exit;
}

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = temp_compressor_v2_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $weavingParam > 0 && $compressorParam > 0) {
    $sheet = temp_compressor_v2_resolve_sheet_by_params($conn, $tanggalParam, $weavingParam, $compressorParam);
}

if (!$sheet) {
    if ($isPost) json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: temp_compressor_v2.php');
    exit;
}

$tanggal = temp_compressor_v2_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$weaving = (int)($sheet['Weaving'] ?? 1);
$compressor = (int)($sheet['Compressor_No'] ?? 1);

$sql = "DELETE FROM dbo.temp_compressor_v2 WHERE CAST(Tanggal AS DATE) = ? AND Weaving = ? AND Compressor_No = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $weaving, $compressor]);
if ($stmt === false) {
    if ($isPost) json_response(['success' => false, 'message' => 'Gagal menghapus data Check Sheet Kompressor Sullair V2.']);
    $_SESSION['error'] = 'Gagal menghapus data.';
    header('Location: temp_compressor_v2.php');
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$msg = 'Data Check Sheet Kompressor Sullair V2 tanggal ' . temp_compressor_v2_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y') . ' (Weaving ' . $weaving . ' - Compressor ' . $compressor . ') berhasil dihapus.';

if ($isPost) {
    json_response(['success' => true, 'message' => $msg]);
}

$_SESSION['success'] = $msg;
header('Location: temp_compressor_v2.php');
exit;
