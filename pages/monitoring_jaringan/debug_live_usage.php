<?php
require_once '../../koneksi.php';

function out($value)
{
    if (is_array($value)) {
        print_r($value);
        return;
    }

    echo $value . PHP_EOL;
}

$providerName = 'Starlink';

$providerStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1 id, provider_name FROM dbo.internet_usage_providers WHERE provider_name = ?",
    [$providerName]
);

if ($providerStmt === false) {
    out(sqlsrv_errors());
    exit(1);
}

$provider = sqlsrv_fetch_array($providerStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($providerStmt);

if (!$provider) {
    out('PROVIDER_NOT_FOUND');
    exit(2);
}

out('PROVIDER');
out($provider);

$locationStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1 id, location_name FROM dbo.internet_usage_locations WHERE provider_id = ? ORDER BY id ASC",
    [$provider['id']]
);

if ($locationStmt === false) {
    out(sqlsrv_errors());
    exit(3);
}

$location = sqlsrv_fetch_array($locationStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($locationStmt);

out('LOCATION');
out($location ?: 'NO_LOCATION');

$locationId = $location['id'] ?? null;

$sql = "
    INSERT INTO dbo.internet_usage_reports (
        provider_id, location_id, month_name, total_data_used_gb, total_usage_hours,
        average_daily_usage_gb, keterangan, created_date, updated_date, created_by, updated_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE(), ?, ?)
";

$params = [
    (int) $provider['id'],
    $locationId === null ? null : (int) $locationId,
    'Januari',
    1,
    1,
    1,
    'debug insert',
    'IT1',
    'IT1'
];

$insertStmt = sqlsrv_query($conn, $sql, $params);
if ($insertStmt === false) {
    out('INSERT_FAILED');
    out(sqlsrv_errors());
    exit(4);
}
sqlsrv_free_stmt($insertStmt);

out('INSERT_OK');
