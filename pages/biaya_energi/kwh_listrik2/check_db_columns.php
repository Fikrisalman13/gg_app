<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/kwh_listrik2_common.php');

header('Content-Type: application/json');

try {
    $tableName = kwhl2_table_full_name($conn);
    
    if (!$tableName) {
        echo json_encode(['success' => false, 'message' => 'Tabel kwh_listrik2 belum ditemukan di database']);
        exit;
    }

    $tableNameOnly = str_replace(['[', ']'], '', explode('.', $tableName)[1] ?? 'kwh_listrik2');

    $sql = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ? 
            ORDER BY ORDINAL_POSITION";
    
    $stmt = sqlsrv_query($conn, $sql, [$tableNameOnly]);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        echo json_encode([
            'success' => false, 
            'message' => 'Error checking columns: ' . print_r($errors, true)
        ]);
        exit;
    }

    $columns = [];
    $allCols = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $columns[] = $row['COLUMN_NAME'];
        $allCols[] = $row['COLUMN_NAME'] . ' (' . $row['DATA_TYPE'] . ')';
    }
    sqlsrv_free_stmt($stmt);

    $requiredCols = [
        'tanggal', 'tarif_per_kwh',
        'kwh_hari_ini_ipab', 'kwh_kemarin_ipab', 'kwh_ipab', 'biaya_ipab',
        'kwh_hari_ini_jineng', 'kwh_kemarin_jineng', 'kwh_jineng', 'biaya_jineng',
        'kwh_hari_ini_xineng', 'kwh_kemarin_xineng', 'kwh_xineng', 'biaya_xineng',
        'kwh_hari_ini_20ton_l', 'kwh_kemarin_20ton_l', 'kwh_20ton_l', 'biaya_20ton_l',
        'kwh_hari_ini_ipal', 'kwh_kemarin_ipal', 'kwh_ipal', 'biaya_ipal',
        'kwh_hari_ini_21ton_actom', 'kwh_kemarin_21ton_actom', 'kwh_21ton_actom', 'biaya_21ton_actom',
        'total_kwh', 'total_biaya'
    ];
    $missingCols = [];

    foreach ($requiredCols as $col) {
        if (!in_array($col, $columns)) {
            $missingCols[] = $col;
        }
    }

    echo json_encode([
        'success' => true,
        'table' => $tableName,
        'table_only' => $tableNameOnly,
        'total_columns' => count($columns),
        'required_columns_count' => count($requiredCols),
        'missing_columns' => $missingCols,
        'all_columns' => $allCols
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
}
