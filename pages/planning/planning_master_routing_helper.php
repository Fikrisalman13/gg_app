<?php

function planning_master_routing_snapshot_path(): string
{
    return __DIR__ . '/planning_master_routing_paddry.json';
}

function planning_load_master_routing_snapshot(): array
{
    $path = planning_master_routing_snapshot_path();
    if (!file_exists($path)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }

    $codes = array_values(array_filter(array_map('strval', $data['codes'] ?? [])));
    $normalized = array_values(array_filter(array_map('strval', $data['normalized_codes'] ?? [])));

    return [
        'codes' => array_values(array_unique($codes)),
        'normalized_codes' => array_values(array_unique($normalized)),
        'generated_at' => $data['generated_at'] ?? null,
    ];
}

function planning_refresh_master_routing_snapshot($conn, int $ttlSeconds = 300): array
{
    $path = planning_master_routing_snapshot_path();
    if (file_exists($path) && (time() - filemtime($path)) < $ttlSeconds) {
        return planning_load_master_routing_snapshot();
    }

    if (!$conn) {
        return planning_load_master_routing_snapshot();
    }

    $sql = "
        SELECT DISTINCT LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) AS routing_code
        FROM dbo.ms_routing
        WHERE LOWER(LTRIM(RTRIM(ISNULL(planning_type, '')))) = 'd'
          AND LTRIM(RTRIM(ISNULL(CAST(routing_id AS NVARCHAR(50)), ''))) <> ''
        ORDER BY LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) ASC
    ";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        return planning_load_master_routing_snapshot();
    }

    $codes = [];
    $normalized = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $routingCode = trim((string) ($row['routing_code'] ?? ''));
        if ($routingCode === '') {
            continue;
        }

        $codes[$routingCode] = $routingCode;
        $normalizedCode = ltrim($routingCode, '0');
        if ($normalizedCode === '') {
            $normalizedCode = '0';
        }
        $normalized[$normalizedCode] = $normalizedCode;
    }
    sqlsrv_free_stmt($stmt);

    if (empty($codes)) {
        return planning_load_master_routing_snapshot();
    }

    $payload = [
        'generated_at' => date('Y-m-d H:i:s'),
        'codes' => array_values($codes),
        'normalized_codes' => array_values($normalized),
    ];
    file_put_contents($path, json_encode($payload));

    return $payload;
}
