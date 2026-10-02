<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/printer_whitelist_helpers.php');

header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

if (!ensurePrinterWhitelistTable($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan tabel whitelist.']);
    exit;
}

$idEmp = isset($_GET['id_emp']) ? (int) $_GET['id_emp'] : 0;
if ($idEmp <= 0) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$sql = "SELECT
            a.id_asset,
            a.kode_asset_seq,
            tp.nama_tipe,
            pw.jenis_tinta,
            CASE WHEN pw.id IS NOT NULL AND pw.is_active = 1 THEN 1 ELSE 0 END AS is_whitelisted
        FROM dbo.m_asset a
        LEFT JOIN dbo.m_tipe tp ON a.id_tipe = tp.id_tipe
        LEFT JOIN dbo.printer_user_whitelist pw ON a.id_asset = pw.id_asset AND pw.is_active = 1
        WHERE a.id_kode = ?
          AND a.id_emp = ?
          AND a.id_status IN (1, 6)
        ORDER BY a.kode_asset_seq ASC, a.id_asset ASC";
$stmt = sqlsrv_query($conn, $sql, [4, $idEmp]);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil asset printer.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = [
        'id_asset' => (int) ($row['id_asset'] ?? 0),
        'kode_asset_seq' => (string) ($row['kode_asset_seq'] ?? ''),
        'nama_tipe' => (string) ($row['nama_tipe'] ?? ''),
        'jenis_tinta' => (string) ($row['jenis_tinta'] ?? ''),
        'is_whitelisted' => !empty($row['is_whitelisted']),
        'label' => trim((string) ($row['kode_asset_seq'] ?? '-') . ' - ' . (string) ($row['nama_tipe'] ?? '-')),
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);
