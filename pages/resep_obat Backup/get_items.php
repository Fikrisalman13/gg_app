<?php
// pages/resep_obat/get_items.php
require_once __DIR__ . '/../../koneksi.php';

$term = $_GET['q'] ?? '';
$data = [];

if ($term !== '') {
    $sql = "SELECT TOP 20 kode_obat, nama_obat, group_obat, codeprod_proint, uom FROM dbo.resep_master_obat 
            WHERE kode_obat LIKE ? OR nama_obat LIKE ?";
    $params = ["%$term%", "%$term%"];
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
            
            $data[] = [
                'id' => $row['kode_obat'],
                'text' => $row['kode_obat'] . ' - ' . $row['nama_obat'],
                'item_data' => [
                    'kode' => $row['kode_obat'],
                    'name' => $row['nama_obat'],
                    'group_obat' => $row['group_obat'],
                    'codeprod' => $row['codeprod_proint'],
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
