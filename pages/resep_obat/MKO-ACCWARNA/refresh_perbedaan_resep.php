<?php

require __DIR__ . '/functions.php';

try {
    $gg = getSqlsrvConnection('gg');
    ensureTablesExist($gg);

    $periodStmt = sqlsrvExecOrFail(
        $gg,
        "SELECT DISTINCT kode_periode FROM dbo.MKO_rekap WHERE ISNULL(kode_periode, '') <> '' ORDER BY kode_periode"
    );
    $periodRows = sqlsrvAll($periodStmt);

    sqlsrv_begin_transaction($gg);
    sqlsrvExecOrFail($gg, "DELETE FROM dbo.MKO_PPT_PERBEDAAN_RESEP");

    $insertSql = "
        INSERT INTO dbo.MKO_PPT_PERBEDAAN_RESEP (
            kode_periode,
            period_start,
            period_end,
            labeljual,
            colorname,
            cuscolor,
            colorcode,
            cp,
            status_cp,
            routing,
            disperse,
            reactive,
            grand_total,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $inserted = 0;

    foreach ($periodRows as $periodRow) {
        $kodePeriode = trim((string) ($periodRow['kode_periode'] ?? ''));
        if ($kodePeriode === '') {
            continue;
        }

        $rangeStmt = sqlsrvExecOrFail(
            $gg,
            "SELECT TOP 1 period_start, period_end FROM dbo.MKO_rekap WHERE kode_periode = ?",
            [$kodePeriode]
        );
        $rangeRow = sqlsrv_fetch_array($rangeStmt, SQLSRV_FETCH_ASSOC);
        if (!$rangeRow) {
            continue;
        }

        $rekapRows = getRowsByKodePeriode(
            $gg,
            'MKO_rekap',
            $kodePeriode,
            ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'prdnmbr', 'status_cp', 'rtgname', 'disperse', 'reactive', 'grand_total'],
            'colorcode, prdnmbr, rtgname'
        );

        if (!$rekapRows) {
            continue;
        }

        $groupedByColor = [];
        foreach ($rekapRows as $row) {
            $routing = toNullableString($row['rtgname'] ?? null);
            if (!routingContainsPaddrY($routing)) {
                continue;
            }

            $colorCode = toNullableString($row['colorcode'] ?? null);
            if ($colorCode === null) {
                continue;
            }

            if (!isset($groupedByColor[$colorCode])) {
                $groupedByColor[$colorCode] = [];
            }

            $groupedByColor[$colorCode][] = $row;
        }

        foreach ($groupedByColor as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $distinctTotals = [];
            foreach ($rows as $row) {
                $distinctTotals[] = implode('|', [
                    (string) ($row['disperse'] ?? 0),
                    (string) ($row['reactive'] ?? 0),
                    (string) ($row['grand_total'] ?? 0),
                ]);
            }

            if (count(array_unique($distinctTotals)) <= 1) {
                continue;
            }

            foreach ($rows as $row) {
                sqlsrvExecOrFail($gg, $insertSql, [
                    $kodePeriode,
                    $rangeRow['period_start'],
                    $rangeRow['period_end'],
                    toNullableString($row['labeljual'] ?? null),
                    toNullableString($row['colorname'] ?? null),
                    toNullableString($row['cuscolor'] ?? null),
                    toNullableString($row['colorcode'] ?? null),
                    toNullableString($row['prdnmbr'] ?? null),
                    toNullableString($row['status_cp'] ?? null),
                    toNullableString($row['rtgname'] ?? null),
                    $row['disperse'] ?? 0,
                    $row['reactive'] ?? 0,
                    $row['grand_total'] ?? 0,
                    'system-refresh',
                ]);
                $inserted++;
            }
        }
    }

    sqlsrv_commit($gg);
    echo "OK {$inserted}" . PHP_EOL;
} catch (Throwable $e) {
    if (isset($gg) && $gg) {
        @sqlsrv_rollback($gg);
    }

    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
