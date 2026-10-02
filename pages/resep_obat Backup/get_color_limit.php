<?php
// pages/resep_obat/get_color_limit.php
require_once '../../koneksi.php';

header('Content-Type: application/json');

$kode = $_GET['kode'] ?? '';

if (empty($kode)) {
    echo json_encode(['status' => 'error', 'message' => 'Kode warna tidak valid']);
    exit;
}

// Try to get specific limit for this color
$sql = "SELECT max_cost, max_cf_disperse, max_cf_reactive, max_cf_total 
        FROM resep_limit_color 
        WHERE kode_warna = ?";
$stmt = sqlsrv_query($conn, $sql, [$kode]);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Found specific limit for this color
    // NULL means unlimited, convert to very high number for validation
    $response = [
        'status' => 'success',
        'source' => 'color_specific',
        'max_cost' => $row['max_cost'] === null ? 999999999 : floatval($row['max_cost']),
        'limits' => [
            'DISPERSE' => $row['max_cf_disperse'] === null ? 999999999 : floatval($row['max_cf_disperse']),
            'REACTIVE' => $row['max_cf_reactive'] === null ? 999999999 : floatval($row['max_cf_reactive']),
            'TOTAL' => $row['max_cf_total'] === null ? 999999999 : floatval($row['max_cf_total'])
        ]
    ];
} else {
    // No specific limit, fall back to global config
    $sqlGlobal = "SELECT TOP 1 max_cost, category_limits FROM resep_config";
    $stmtGlobal = sqlsrv_query($conn, $sqlGlobal);
    
    if ($stmtGlobal && $rowGlobal = sqlsrv_fetch_array($stmtGlobal, SQLSRV_FETCH_ASSOC)) {
        $catLimits = json_decode($rowGlobal['category_limits'] ?? '{}', true);
        
        $response = [
            'status' => 'success',
            'source' => 'global',
            'max_cost' => floatval($rowGlobal['max_cost'] ?? 0),
            'limits' => [
                'DISPERSE' => floatval($catLimits['DISPERSE'] ?? 0),
                'REACTIVE' => floatval($catLimits['REACTIVE'] ?? 0),
                'TOTAL' => 0 // Global config doesn't have total limit
            ]
        ];
    } else {
        // No config at all
        $response = [
            'status' => 'success',
            'source' => 'none',
            'max_cost' => 0,
            'limits' => [
                'DISPERSE' => 0,
                'REACTIVE' => 0,
                'TOTAL' => 0
            ]
        ];
    }
}

echo json_encode($response);
?>
