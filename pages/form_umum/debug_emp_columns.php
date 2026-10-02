<?php
/**
 * Debug script to check m_emp table structure and find the correct status column name
 */
session_start();
require '../../koneksi.php';

if (!isset($conn) || $conn === false) {
    die('Connection failed');
}

echo "<h3>Checking m_emp table structure...</h3>";

// Get column information
$sql = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'm_emp' 
        AND COLUMN_NAME LIKE '%aktif%' OR COLUMN_NAME LIKE '%status%' OR COLUMN_NAME LIKE '%active%'
        ORDER BY COLUMN_NAME";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo "<p>Error: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column Name</th><th>Data Type</th><th>Nullable</th></tr>";
    
    $found = false;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $found = true;
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['COLUMN_NAME']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['DATA_TYPE']) . "</td>";
        echo "<td>" . htmlspecialchars($row['IS_NULLABLE']) . "</td>";
        echo "</tr>";
    }
    
    if (!$found) {
        echo "<tr><td colspan='3'>No status/aktif/active columns found</td></tr>";
    }
    
    echo "</table>";
    
    sqlsrv_free_stmt($stmt);
}

// Also try to get a sample row
echo "<h3>Sample employee data:</h3>";
$sql2 = "SELECT TOP 1 * FROM dbo.m_emp";
$stmt2 = sqlsrv_query($conn, $sql2);

if ($stmt2 === false) {
    echo "<p>Error: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    $row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
    if ($row) {
        echo "<pre>";
        echo "Columns in m_emp table:\n";
        foreach (array_keys($row) as $colName) {
            echo "  - " . $colName . "\n";
        }
        echo "</pre>";
    }
    sqlsrv_free_stmt($stmt2);
}
?>
