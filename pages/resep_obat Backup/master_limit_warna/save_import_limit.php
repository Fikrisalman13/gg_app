<?php
// pages/resep_obat/master_limit_warna/save_import_limit.php
require_once '../../../koneksi.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$data = $input['data'] ?? [];

if (empty($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada data untuk disimpan']);
    exit;
}

$successCount = 0;
$errors = [];

$sql = "MERGE INTO resep_limit_color AS target
        USING (SELECT ? AS kode_warna) AS source
        ON (target.kode_warna = source.kode_warna)
        WHEN MATCHED THEN
            UPDATE SET 
                max_cost = ?,
                max_cf_disperse = ?,
                max_cf_reactive = ?,
                max_cf_total = ?,
                updated_at = GETDATE()
        WHEN NOT MATCHED THEN
            INSERT (kode_warna, max_cost, max_cf_disperse, max_cf_reactive, max_cf_total, created_at)
            VALUES (?, ?, ?, ?, ?, GETDATE());";

foreach ($data as $row) {
    try {
        $kode = $row['kode'];
        $maxCost = $row['max_cost'];
        $maxDisc = $row['max_cf_disperse'];
        $maxReact = $row['max_cf_reactive'];
        $maxTotal = $row['max_cf_total'];
        
        // Params for MERGE:
        // 1. Source Kode
        // 2,3,4,5. Update Values
        // 6. Insert Kode
        // 7,8,9,10. Insert Values
        $params = [
            $kode,
            $maxCost, $maxDisc, $maxReact, $maxTotal,
            $kode,
            $maxCost, $maxDisc, $maxReact, $maxTotal
        ];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
             throw new Exception(print_r(sqlsrv_errors(), true));
        }
        $successCount++;
        
    } catch (Exception $e) {
        $errors[] = "Kode $kode: " . $e->getMessage();
    }
}

if ($successCount > 0) {
    echo json_encode([
        'status' => 'success', 
        'message' => "Berhasil mengimpor $successCount data.",
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengimpor data.',
        'errors' => $errors
    ]);
}
?>
