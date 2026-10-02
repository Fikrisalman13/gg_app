<?php
// pages/resep_obat/add_status_resep_lipat_columns.php
require_once '../../koneksi.php';

echo "<h2>Adding status_resep_lipat and proint_resephdid to resep_obat</h2>";

$columns = [
    'status_resep_lipat' => 'VARCHAR(30) NULL',
    'proint_resephdid' => 'INT NULL'
];

foreach ($columns as $col => $def) {
    try {
        // Check if column exists
        $checkSql = "SELECT COL_LENGTH('resep_obat', '$col') as len";
        $check = sqlsrv_query($conn, $checkSql);
        $row = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
        
        if ($row && $row['len'] !== null) {
             echo "Column <b>$col</b> already exists.<br>";
        } else {
             // Column doesn't exist (or len is null), add it
            $sql = "ALTER TABLE resep_obat ADD $col $def";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt) {
                echo "Column <b>$col</b> added successfully.<br>";
            } else {
                echo "Error adding column $col: " . print_r(sqlsrv_errors(), true) . "<br>";
            }
        }
    } catch (Exception $e) {
        echo "Exception for $col: " . $e->getMessage() . "<br>";
    }
}

echo "Done.";
?>
