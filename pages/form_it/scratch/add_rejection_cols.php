<?php
require_once 'c:/xampp/htdocs/gg_app/koneksi.php';

$sqls = [
    "ALTER TABLE Form_Buka_Tanggal_Closingan ADD rejected_by VARCHAR(50)",
    "ALTER TABLE Form_Buka_Tanggal_Closingan ADD rejection_reason NVARCHAR(MAX)",
    "ALTER TABLE Form_Buka_Tanggal_Closingan ADD rejection_date DATETIME"
];

foreach ($sqls as $sql) {
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        // Check if error is because column already exists
        $alreadyExists = false;
        if ($errors) {
            foreach ($errors as $error) {
                if ($error['code'] == 2705) { // Column name already exists
                    $alreadyExists = true;
                }
            }
        }
        
        if ($alreadyExists) {
            echo "Column already exists for: $sql\n";
        } else {
            echo "Error on: $sql -> " . print_r($errors, true) . "\n";
        }
    } else {
        echo "Success: $sql\n";
    }
}
?>
