<?php
require_once '../../koneksi.php';
$sql = file_get_contents('alter_resep_obat.sql');
// Split by GO or just run if single block. sqlsrv_query only runs one batch.
// Splitting by 'END' logic or just generic execution.
// Simple way:
$parts = explode('END', $sql);
foreach ($parts as $part) {
    if (trim($part)) {
        $q = $part . ' END'; // simplistic re-add
        // Actually just run it. The IF NOT EXISTS logic allows safe re-run.
        // But sqlsrv might not like multiple batches.
        // Let's try running the whole thing if the driver supports it, or split manually.
    }
}
// Actually, simple sqlsrv_query handles IF/BEGIN/END block fine usually.
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) die(print_r(sqlsrv_errors(), true));
echo "Table Altered.";
?>
