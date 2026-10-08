<?php
// pages/resep_obat/get_items.php
require_once __DIR__ . '/../../../koneksi.php';

$term = $_GET['q'] ?? '';
$data = [];

if ($term !== '') {
    $sql = "SELECT TOP 20 kode_obat, nama_obat, group_obat, codeprod_proint, uom FROM dbo.resep_master_obat 
            WHERE kode_obat LIKE ? OR nama_obat LIKE ? OR codeprod_proint LIKE ?";
    $params = ["%$term%", "%$term%", "%$term%"];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $uomRaw = $row['uom'] ?? '';
            // Map G/L -> GR per User Request
            if (strtoupper(trim($uomRaw)) === 'G/L') {
                $uom = 'GR';
            } else {
                $uom = $uomRaw;
            }
            
            $prointCode = !empty(trim($row['codeprod_proint'] ?? '')) ? trim($row['codeprod_proint']) : trim($row['kode_obat'] ?? '');
            
            $data[] = [
                'id' => $prointCode,
                'text' => $prointCode . ' - ' . $row['nama_obat'],
                'item_data' => [
                    'kode' => $prointCode,
                    'kode_obat' => $row['kode_obat'],
                    'name' => $row['nama_obat'],
                    'group_obat' => $row['group_obat'],
                    'codeprod' => $prointCode,
                    'uom' => $uom, 
                    'uom_raw' => $uomRaw,
                    'std_price' => 0, 
                    'cc' => '',
                    'receipe' => 0
                ]
            ];
        }
    }
}

echo json_encode(['results' => $data]);
?>
