<?php

session_start();
require_once __DIR__ . '/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method tidak valid.');
    }

    $rtgmsIds = parseRtgmsIds($_POST['rtgmsid'] ?? '601,613,589,639,861');
    $startDate = normalizeDateInput($_POST['startdate'] ?? '');
    $endDate = normalizeDateInput($_POST['enddate'] ?? '', true);

    if (strtotime($startDate) > strtotime($endDate)) {
        throw new InvalidArgumentException('Start date tidak boleh lebih besar dari end date.');
    }

    $gg = getSqlsrvConnection('gg');
    $erp = getErpConnection();
    ensureTablesExist($gg);

    sqlsrv_begin_transaction($gg);

    $kodePeriode = generateUniquePeriodCode($gg, $startDate, $endDate);
    $placeholders = implode(', ', array_fill(0, count($rtgmsIds), '?'));
    $params = array_merge([$startDate, $endDate], $rtgmsIds);

    $sql = "
        SELECT
            r.productionhdid,
            r.rtgmsid,
            h.prdnmbr,
            r.fgresult,
            m.rtgname,
            h.fgstatus,
            r.startdate,
            r.enddate
        FROM pdproductionrtg r
        LEFT JOIN pdrtgms m
            ON r.rtgmsid = m.rtgmsid
        LEFT JOIN pdproductionhd h
            ON r.productionhdid = h.productionhdid
        WHERE
            r.startdate >= ?
            AND r.startdate <= ?
            AND r.rtgmsid IN ({$placeholders})
        ORDER BY
            r.startdate,
            r.productionhdid
    ";

    $rows = pdoFetchAll($erp, $sql, $params);

    $insertSql = "
        INSERT INTO dbo.mko_rawdata (
            kode_periode,
            period_start,
            period_end,
            productionhdid,
            rtgmsid,
            prdnmbr,
            fgresult,
            rtgname,
            fgstatus,
            startdate,
            enddate,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $inserted = 0;
    foreach ($rows as $row) {
        sqlsrvExecOrFail($gg, $insertSql, [
            $kodePeriode,
            $startDate,
            $endDate,
            $row['productionhdid'],
            $row['rtgmsid'],
            toNullableString($row['prdnmbr'] ?? null),
            toNullableString($row['fgresult'] ?? null),
            toNullableString($row['rtgname'] ?? null),
            toNullableString($row['fgstatus'] ?? null),
            $row['startdate'],
            $row['enddate'],
            getRequestUser(),
        ]);
        $inserted++;
    }

    sqlsrv_commit($gg);

    $prdnmbrUnique = array_values(array_filter(array_unique(array_map(
        static function ($row) {
            return trim((string) ($row['prdnmbr'] ?? ''));
        },
        $rows
    ))));

    jsonResponse([
        'success' => true,
        'message' => 'Raw data berhasil disimpan.',
        'kode_periode' => $kodePeriode,
        'count' => $inserted,
        'prdnmbr_count' => count($prdnmbrUnique),
        'filters' => [
            'rtgmsid' => $rtgmsIds,
            'startdate' => $startDate,
            'enddate' => $endDate,
        ],
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
