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
    $masterResepMap = getMasterResepMap($gg);
    $rawdataSummaryMap = getRawdataSummaryByKode($gg, $kodePeriode);

    if (getMaterialCountByKode($gg, $kodePeriode) <= 0) {
        throw new RuntimeException('Kode periode ini belum memiliki data material obat.');
    }

    if (getRekapCountByKode($gg, $kodePeriode) > 0) {
        throw new RuntimeException('Kode periode ini sudah pernah ditarik rekap dan tidak bisa diproses ulang.');
    }

    $periodStmt = sqlsrvExecOrFail(
        $gg,
        "SELECT TOP 1 period_start, period_end FROM dbo.MKO_materialobat WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $periodRow = sqlsrv_fetch_array($periodStmt, SQLSRV_FETCH_ASSOC);
    if (!$periodRow) {
        throw new RuntimeException('Periode material obat tidak ditemukan.');
    }

    $sql = "
        SELECT
            prdnmbr,
            labeljual,
            colorname,
            cuscolor,
            colorcode,
            rtgname,
            MAX(vlot) AS vlot,
            SUM(CASE WHEN kelompok = 'Disperse' THEN ISNULL(prodcf, 0) ELSE 0 END) AS disperse,
            SUM(CASE WHEN kelompok = 'Reactive' THEN ISNULL(prodcf, 0) ELSE 0 END) AS reactive,
            SUM(ISNULL(prodcf, 0)) AS grand_total
        FROM dbo.MKO_materialobat
        WHERE kode_periode = ?
        GROUP BY
            prdnmbr,
            labeljual,
            colorname,
            cuscolor,
            colorcode,
            rtgname
        ORDER BY
            prdnmbr,
            labeljual,
            colorname,
            cuscolor,
            colorcode,
            rtgname
    ";
    $stmt = sqlsrvExecOrFail($gg, $sql, [$kodePeriode]);
    $rows = sqlsrvAll($stmt);

    if (!$rows) {
        throw new RuntimeException('Tidak ada data rekap yang bisa dibentuk dari material obat.');
    }

    sqlsrv_begin_transaction($gg);

    $insertSql = "
        INSERT INTO dbo.MKO_rekap (
            kode_periode,
            period_start,
            period_end,
            prdnmbr,
            labeljual,
            colorname,
            cuscolor,
            colorcode,
            master_resep,
            rtgname,
            status_proses_acc,
            status_cp,
            vlot,
            disperse,
            reactive,
            grand_total,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $inserted = 0;
    foreach ($rows as $row) {
        $prdnmbr = toNullableString($row['prdnmbr'] ?? null);
        $colorCode = toNullableString($row['colorcode'] ?? null);
        $rawSummary = ($prdnmbr !== null && isset($rawdataSummaryMap[$prdnmbr])) ? $rawdataSummaryMap[$prdnmbr] : null;

        sqlsrvExecOrFail($gg, $insertSql, [
            $kodePeriode,
            $periodRow['period_start'],
            $periodRow['period_end'],
            $prdnmbr,
            toNullableString($row['labeljual'] ?? null),
            toNullableString($row['colorname'] ?? null),
            toNullableString($row['cuscolor'] ?? null),
            $colorCode,
            isset($masterResepMap[$colorCode ?? '']) ? 'Ya' : 'Belum',
            toNullableString($row['rtgname'] ?? null),
            $rawSummary['status_proses_acc'] ?? null,
            $rawSummary['status_cp'] ?? null,
            toNullableString($row['vlot'] ?? null),
            $row['disperse'] ?? 0,
            $row['reactive'] ?? 0,
            $row['grand_total'] ?? 0,
            getRequestUser(),
        ]);
        $inserted++;
    }

    sqlsrv_commit($gg);

    jsonResponse([
        'success' => true,
        'message' => 'Rekap berhasil disimpan.',
        'kode_periode' => $kodePeriode,
        'count' => $inserted,
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
