<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
weaving_require($conn, 'CanDelete', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Metode request tidak valid.']);
if (!dryer_weaving_table_exists($conn)) json_response(['success' => false, 'message' => 'Tabel dbo.dryer_weaving belum tersedia.']);

$id = $_POST['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) json_response(['success' => false, 'message' => 'ID tidak valid.']);

$sheet = dryer_weaving_resolve_sheet($conn, (int)$id);
if (!$sheet) json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);

$tanggal = dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$sql = "DELETE FROM dbo.dryer_weaving
        WHERE CAST(Tanggal AS DATE) = ?
          AND Dryer_No = ?
          AND Ct_No = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $sheet['Dryer_No'], $sheet['Ct_No']]);
if ($stmt === false) {
    json_response(['success' => false, 'message' => 'Gagal menghapus data Dryer Weaving.']);
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response(['success' => true, 'message' => 'Data Dryer Weaving berhasil dihapus.']);
