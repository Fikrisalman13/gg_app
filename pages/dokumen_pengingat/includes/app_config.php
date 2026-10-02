<?php
// ======================================================
// app_config.php — GLOBAL APP CONFIG HELPER
// ======================================================

declare(strict_types=1);

/**
 * Ambil config aplikasi dari database
 */
function appConfig($conn, string $key, string $default = ''): string
{
    $stmt = sqlsrv_query(
        $conn,
        "SELECT config_value FROM dr_app_config WHERE config_key = ?",
        [$key]
    );

    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        return (string)$row['config_value'];
    }

    return $default;
}

/**
 * Simpan / update config aplikasi
 */
function setAppConfig($conn, string $key, string $value): bool
{
    $sql = "
        MERGE dr_app_config AS t
        USING (SELECT ? AS k, ? AS v) AS s
        ON t.config_key = s.k
        WHEN MATCHED THEN
            UPDATE SET config_value = s.v, updated_at = GETDATE()
        WHEN NOT MATCHED THEN
            INSERT (config_key, config_value)
            VALUES (s.k, s.v);
    ";

    return sqlsrv_query($conn, $sql, [$key, $value]) !== false;
}
