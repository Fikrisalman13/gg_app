<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/kwh_listrik_common.php');

header('Content-Type: application/json');

try {
    $tableName = kwhl_table_full_name($conn);
    
    if (!$tableName) {
        echo json_encode(['success' => false, 'message' => 'Tabel KWH Listrik tidak ditemukan']);
        exit;
    }

    // Extract table name without schema
    $tableNameOnly = str_replace(['[', ']'], '', explode('.', $tableName)[1] ?? '');

    // Check columns
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
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
    $requiredCols = ['kwh_hari_ini_21t', 'kwh_kemarin_21t', 'kwh_21ton_actom', 'biaya_21ton_actom'];
    $missingCols = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $columns[] = $row['COLUMN_NAME'];
    }
    sqlsrv_free_stmt($stmt);

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
        'required_columns' => $requiredCols,
        'missing_columns' => $missingCols,
        'all_columns' => $columns
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
}
?>
