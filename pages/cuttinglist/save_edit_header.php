<?php
require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_header'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$id_header = (int)$_POST['id_header'];
$cp_no = strval($_POST['cp_no'] ?? '');
$uom_cp = strval($_POST['uom_cp'] ?? 'M');
$id_mesin = intval($_POST['id_mesin'] ?? 0);
$type_counter = strval($_POST['type_counter'] ?? 'M');

try {
    if (!$cp_no) {
        throw new Exception('CP No tidak boleh kosong');
    }

    if (!$id_mesin) {
        throw new Exception('Mesin Inspect harus dipilih');
    }

    $stmt = sqlsrv_query($conn, "
        UPDATE cl_cutting_header
        SET cp_no = ?,
            uom_cp = ?,
            id_mesin = ?,
            type_counter = ?,
            updated_by = ?,
            updated_date = GETDATE()
        WHERE id_header = ?
    ", [
        $cp_no,
        $uom_cp,
        $id_mesin,
        $type_counter,
        $_SESSION['UserName'] ?? 'SYSTEM',
        $id_header
    ]);

    if (!$stmt) {
        throw new Exception('Update gagal: ' . json_encode(sqlsrv_errors()));
    }

    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
