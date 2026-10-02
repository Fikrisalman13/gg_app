<?php

/** Read one PPC Guidance recipe setting, returning the supplied default on failure. */
function getResepPpcGuidanceConfig($conn, string $key, string $default = ''): string
{
    $statement = sqlsrv_query(
        $conn,
        'SELECT config_value FROM dbo.resep_ppc_guidance_config WHERE config_key = ?',
        [$key]
    );
    if ($statement === false) {
        return $default;
    }

    $row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
    return $row ? (string)$row['config_value'] : $default;
}

/** Determine whether ProInt metadata is visible in PPC Guidance user outputs. */
function showResepPpcGuidanceProIntMetadata($conn): bool
{
    return getResepPpcGuidanceConfig($conn, 'show_proint_metadata', '0') === '1';
}
