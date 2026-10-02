<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/kwh_listrik2_common.php');

header('Content-Type: application/json');

if (!isset($_POST['tanggal'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak dikirim.']);
    exit;
}

$tanggal = kwhl2_normalize_date($_POST['tanggal']);
if ($tanggal === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

$tableName = kwhl2_table_full_name($conn);
if (!$tableName) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tabel kwh_listrik2 tidak ditemukan.']);
    exit;
}

try {
    $sql = "SELECT TOP 1 * FROM " . $tableName . " WHERE CAST(tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);

    if ($stmt === false) {
        throw new Exception("Query error: " . print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    if ($row) {
        $mapped = kwhl2_row_from_db($row);
        
        $machineData = [];
        foreach (kwhl2_machine_defs() as $code => $def) {
            $machineData[$code] = [
                'kwh_hari_ini' => (float)($mapped[$def['hi_key']] ?? 0),
                'hi_key' => $def['hi_key'],
                'km_key' => $def['km_key'],
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'tanggal' => $tanggal,
            'data' => $mapped,
            'machines' => $machineData,
        ]);
    } else {
        http_response_code(200);
        echo json_encode(['success' => false, 'message' => 'Data untuk tanggal ' . $tanggal . ' tidak ditemukan.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
