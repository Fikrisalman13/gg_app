<?php
// pages/ppc_guidance_v2/resep_obat/run_alter_precision_v4.php
require_once __DIR__ . '/../../../koneksi.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== MIGRATION: ALTER PRECISION TO DECIMAL(18, 4) FOR V2 TABLES ===\n\n";

$queries = [
    "ALTER TABLE dbo.resep_obat_detail_v2 ALTER COLUMN receipe DECIMAL(18, 4) NULL" => "dbo.resep_obat_detail_v2.receipe -> DECIMAL(18, 4)",
    "ALTER TABLE dbo.resep_obat_detail_v2 ALTER COLUMN vlot DECIMAL(18, 4) NULL" => "dbo.resep_obat_detail_v2.vlot -> DECIMAL(18, 4)",
    "ALTER TABLE dbo.resep_obat_v2 ALTER COLUMN weight DECIMAL(18, 4) NULL" => "dbo.resep_obat_v2.weight -> DECIMAL(18, 4)",
    "ALTER TABLE dbo.resep_obat_v2 ALTER COLUMN plan_qty DECIMAL(18, 4) NULL" => "dbo.resep_obat_v2.plan_qty -> DECIMAL(18, 4)",
    "ALTER TABLE dbo.resep_obat_v2 ALTER COLUMN vlot DECIMAL(18, 4) NULL" => "dbo.resep_obat_v2.vlot -> DECIMAL(18, 4)",
];

foreach ($queries as $sql => $desc) {
    echo "Executing: $desc ... ";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        echo "FAILED!\n";
        print_r(sqlsrv_errors());
        echo "\n";
    } else {
        echo "SUCCESS.\n";
    }
}

echo "\n=== VERIFYING COLUMN TYPES IN SQL SERVER ===\n";
$verifySql = "
    SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME IN ('resep_obat_v2', 'resep_obat_detail_v2')
      AND COLUMN_NAME IN ('receipe', 'vlot', 'weight', 'plan_qty')
    ORDER BY TABLE_NAME, COLUMN_NAME
";
$vStmt = sqlsrv_query($conn, $verifySql);
if ($vStmt) {
    while ($r = sqlsrv_fetch_array($vStmt, SQLSRV_FETCH_ASSOC)) {
        echo sprintf("%-25s %-15s %s (%s,%s)\n",
            $r['TABLE_NAME'],
            $r['COLUMN_NAME'],
            $r['DATA_TYPE'],
            $r['NUMERIC_PRECISION'],
            $r['NUMERIC_SCALE']
        );
    }
}

echo "\nMigration complete.\n";
