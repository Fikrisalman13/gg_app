<?php

session_start();
require_once __DIR__ . '/functions.php';

try {
    $kodePeriode = trim((string) ($_REQUEST['kode_periode'] ?? ''));
    if ($kodePeriode === '') {
        throw new InvalidArgumentException('Kode periode wajib diisi.');
    }

    $gg = getSqlsrvConnection('gg');
    ensureTablesExist($gg);

    if (getMaterialCountByKode($gg, $kodePeriode) <= 0) {
        throw new RuntimeException('Kode periode ini belum memiliki data material obat.');
    }

    if (getRekapCountByKode($gg, $kodePeriode) <= 0) {
        throw new RuntimeException('Kode periode ini belum memiliki data rekap.');
    }

    if (getPptFailCountByKode($gg, $kodePeriode) > 0 || getPptPerbedaanCountByKode($gg, $kodePeriode) > 0) {
        throw new RuntimeException('Kode periode ini sudah pernah ditarik PPT dan tidak bisa diproses ulang.');
    }

    $periodStmt = sqlsrvExecOrFail(
        $gg,
        "SELECT TOP 1 period_start, period_end FROM dbo.MKO_rekap WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $periodRow = sqlsrv_fetch_array($periodStmt, SQLSRV_FETCH_ASSOC);
    if (!$periodRow) {
        throw new RuntimeException('Periode rekap tidak ditemukan.');
    }

    $rekapRows = getRowsByKodePeriode(
        $gg,
        'MKO_rekap',
        $kodePeriode,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'prdnmbr', 'status_proses_acc', 'status_cp', 'rtgname', 'disperse', 'reactive', 'grand_total', 'master_resep'],
        'colorcode, prdnmbr, rtgname'
    );

    if (!$rekapRows) {
        throw new RuntimeException('Data rekap untuk kode periode ini kosong.');
    }

    $failRows = [];
    $perbedaanCandidates = [];

    foreach ($rekapRows as $row) {
        $masterResep = strtoupper((string) toNullableString($row['master_resep'] ?? null));
        $routing = toNullableString($row['rtgname'] ?? null);

        if (routingContainsPaddrY($routing)) {
            $perbedaanCandidates[] = $row;
        }

        if (
            $masterResep === 'YA'
            && routingContainsPaddrY($routing)
            && statusProsesEndsWithFail($row['status_proses_acc'] ?? null)
        ) {
            $failRows[] = $row;
        }
    }

    $perbedaanRows = [];
    $groupedByColor = [];
    foreach ($perbedaanCandidates as $row) {
        $colorCode = toNullableString($row['colorcode'] ?? null);
        if ($colorCode === null) {
            continue;
        }

        if (!isset($groupedByColor[$colorCode])) {
            $groupedByColor[$colorCode] = [];
        }
        $groupedByColor[$colorCode][] = $row;
    }

    foreach ($groupedByColor as $colorCode => $rows) {
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
            $perbedaanRows[] = $row;
        }
    }

    sqlsrv_begin_transaction($gg);

    sqlsrvExecOrFail($gg, "DELETE FROM dbo.MKO_PPT_CPSTATUS_FAIL WHERE kode_periode = ?", [$kodePeriode]);
    sqlsrvExecOrFail($gg, "DELETE FROM dbo.MKO_PPT_PERBEDAAN_RESEP WHERE kode_periode = ?", [$kodePeriode]);

    $insertFailSql = "
        INSERT INTO dbo.MKO_PPT_CPSTATUS_FAIL (
            kode_periode,
            period_start,
            period_end,
            labeljual,
            colorname,
            cuscolor,
            colorcode,
            cp,
            status_proses_acc,
            status_cp,
            routing,
            disperse,
            reactive,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    foreach ($failRows as $row) {
        sqlsrvExecOrFail($gg, $insertFailSql, [
            $kodePeriode,
            $periodRow['period_start'],
            $periodRow['period_end'],
            toNullableString($row['labeljual'] ?? null),
            toNullableString($row['colorname'] ?? null),
            toNullableString($row['cuscolor'] ?? null),
            toNullableString($row['colorcode'] ?? null),
            toNullableString($row['prdnmbr'] ?? null),
            toNullableString($row['status_proses_acc'] ?? null),
            toNullableString($row['status_cp'] ?? null),
            toNullableString($row['rtgname'] ?? null),
            $row['disperse'] ?? 0,
            $row['reactive'] ?? 0,
            getRequestUser(),
        ]);
    }

    $insertBedaSql = "
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

    foreach ($perbedaanRows as $row) {
        sqlsrvExecOrFail($gg, $insertBedaSql, [
            $kodePeriode,
            $periodRow['period_start'],
            $periodRow['period_end'],
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
            getRequestUser(),
        ]);
    }

    sqlsrv_commit($gg);

    jsonResponse([
        'success' => true,
        'message' => 'Data PPT berhasil disimpan.',
        'kode_periode' => $kodePeriode,
        'cpstatus_fail_count' => count($failRows),
        'perbedaan_resep_count' => count($perbedaanRows),
    ]);
} catch (Throwable $e) {
    if (isset($gg) && $gg) {
        @sqlsrv_rollback($gg);
    }

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
