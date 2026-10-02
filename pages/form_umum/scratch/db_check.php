<?php
require_once '../../../koneksi.php';

// Check IKP table columns
echo "=== Form_Umum_Izin_Keluar_Pabrik columns ===\n";
$stmt = sqlsrv_query($conn, "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Izin_Keluar_Pabrik' ORDER BY ORDINAL_POSITION");
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo $row['COLUMN_NAME'] . ' (' . $row['DATA_TYPE'] . ")\n";
    }
}

echo "\n=== User_TTD_Template_Umum - HRD roles ===\n";
$stmt = sqlsrv_query($conn, "SELECT DISTINCT GroupRole FROM User_TTD_Template_Umum WHERE GroupRole LIKE '%HRD%' OR GroupRole LIKE '%Personalia%'");
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo $row['GroupRole'] . "\n";
    }
}

echo "\n=== All GroupRole values ===\n";
$stmt = sqlsrv_query($conn, "SELECT DISTINCT GroupRole FROM User_TTD_Template_Umum");
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo $row['GroupRole'] . "\n";
    }
}

echo "\n=== Sample IKP data with lampiran ===\n";
$stmt = sqlsrv_query($conn, "SELECT TOP 3 ticket, lampiran_path, lampiran_filename, lampiran_size FROM Form_Umum_Izin_Keluar_Pabrik WHERE lampiran_path IS NOT NULL");
if ($stmt && sqlsrv_has_rows($stmt)) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        print_r($row);
    }
} else {
    echo "No data found with lampiran\n";
}
?>
