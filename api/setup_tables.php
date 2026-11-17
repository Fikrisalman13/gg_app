<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../koneksi.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal koneksi ke SQL Server']);
    exit;
}

$queries = [
    "Tabel emds_stations" => "
        IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'emds_stations')
        BEGIN
            CREATE TABLE emds_stations (
                station_id VARCHAR(50) NOT NULL PRIMARY KEY,
                station_name NVARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NULL,
                is_active BIT NOT NULL DEFAULT 1,
                last_ping_at DATETIME2 NULL,
                created_at DATETIME2 NOT NULL DEFAULT GETDATE()
            );
        END
    ",
    "Tabel emds_readings" => "
        IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'emds_readings')
        BEGIN
            CREATE TABLE emds_readings (
                id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                station_id VARCHAR(50) NOT NULL,
                device_name NVARCHAR(100) NOT NULL,
                parameter_name NVARCHAR(100) NOT NULL,
                reading_value FLOAT NULL,
                unit NVARCHAR(20) NOT NULL,
                quality VARCHAR(20) NOT NULL,
                captured_at DATETIME2 NOT NULL,
                synced_at DATETIME2 NOT NULL DEFAULT GETDATE(),
                local_reading_id BIGINT NULL,
                CONSTRAINT FK_emds_readings_station FOREIGN KEY (station_id) 
                    REFERENCES emds_stations(station_id) 
                    ON UPDATE CASCADE
            );
        END
    ",
    "Indeks IX_emds_readings_query" => "
        IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_emds_readings_query' AND object_id = OBJECT_ID('emds_readings'))
        BEGIN
            CREATE INDEX IX_emds_readings_query 
                ON emds_readings (station_id, parameter_name, captured_at DESC);
        END
    ",
    "Indeks IX_emds_readings_captured_at" => "
        IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_emds_readings_captured_at' AND object_id = OBJECT_ID('emds_readings'))
        BEGIN
            CREATE INDEX IX_emds_readings_captured_at 
                ON emds_readings (captured_at DESC);
        END
    "
];

$results = [];
foreach ($queries as $label => $sql) {
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        $results[$label] = ['success' => false, 'errors' => sqlsrv_errors()];
    } else {
        $results[$label] = ['success' => true];
        sqlsrv_free_stmt($stmt);
    }
}

echo json_encode(['status' => 'completed', 'results' => $results], JSON_PRETTY_PRINT);
