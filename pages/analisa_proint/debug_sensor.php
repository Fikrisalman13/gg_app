<?php
// Test script to check PDO SQL Server connection
require_once '../../koneksi.php';
require_once '../../koneksi3.php';

echo "Testing PDO SQL Server...\n";

try {
    $dsn = "sqlsrv:Server={$serverName};Database={$connectionOptions['Database']};TrustServerCertificate=1";
    $pdo = new PDO($dsn, $connectionOptions['Uid'], $connectionOptions['PWD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "PDO SQL Server connected OK\n";
    
    // Check table structure
    $stmt = $pdo->query("SELECT TOP 5 * FROM dbo.MonitoringMesinDataAb ORDER BY DataAbId DESC");
    $rows = $stmt->fetchAll();
    echo "Total rows returned: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "DataAbId={$r['DataAbId']} RecordDate={$r['RecordDate']} RecordDateText={$r['RecordDateText']} RowJson=" . substr((string)($r['RowJson'] ?? ''), 0, 200) . "\n";
    }
    
    // Check RecordDate nulls
    $stmt2 = $pdo->query("SELECT COUNT(1) AS total, COUNT(RecordDate) AS with_date FROM dbo.MonitoringMesinDataAb WHERE RecordDate IS NOT NULL");
    $r2 = $stmt2->fetch();
    echo "Records with non-null RecordDate: {$r2['with_date']} / {$r2['total']}\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
