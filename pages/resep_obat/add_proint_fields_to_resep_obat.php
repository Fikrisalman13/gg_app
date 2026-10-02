<?php
// pages/resep_obat/add_proint_fields_to_resep_obat.php
require_once '../../koneksi.php';

echo "<h2>Adding ProInt Fields to resep_obat</h2>";

$columns = [
    'no_cp_resep' => 'VARCHAR(50) NULL',
    'no_so' => 'VARCHAR(50) NULL',
    'rtg_code' => 'VARCHAR(50) NULL',
    'rtg_name' => 'VARCHAR(100) NULL',
    'status_desc' => 'VARCHAR(100) NULL',
    'cus_color' => 'VARCHAR(255) NULL'
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
