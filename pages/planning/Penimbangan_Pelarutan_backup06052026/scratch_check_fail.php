<?php
include '../../../koneksi.php';

$sql = "SELECT id, cp_no, period_date, rencana_start, aktual_start FROM dbo.cpp_paddry WHERE cp_no = 'D25F1002.01.0101'";
$stmt = sqlsrv_query($conn, $sql);

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . "\n";
    echo "CP: " . $row['cp_no'] . "\n";
    echo "Period Date: " . ($row['period_date'] ? $row['period_date']->format('Y-m-d') : 'NULL') . "\n";
    echo "Plan: " . ($row['rencana_start'] ? $row['rencana_start']->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "Actual: " . ($row['aktual_start'] ? $row['aktual_start']->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "-------------------\n";
}
?>
