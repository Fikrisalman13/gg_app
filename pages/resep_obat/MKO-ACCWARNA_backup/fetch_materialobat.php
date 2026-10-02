<?php

session_start();
require_once __DIR__ . '/functions.php';

try {
    $kodePeriode = trim((string) ($_REQUEST['kode_periode'] ?? ''));
    if ($kodePeriode === '') {
        throw new InvalidArgumentException('Kode periode wajib diisi.');
    }

    $gg = getSqlsrvConnection('gg');
    $erp = getErpConnection();
    ensureTablesExist($gg);
    $kelompokMap = getEligibleKelompokMap($gg);

    if (!$kelompokMap) {
        throw new RuntimeException('Data referensi mko_kelompok_obat tidak ditemukan atau semua kelompok termasuk Auxiliaries.');
    }

    $periodStmt = sqlsrvExecOrFail(
        $gg,
        "SELECT TOP 1 period_start, period_end FROM dbo.mko_rawdata WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $periodRow = sqlsrv_fetch_array($periodStmt, SQLSRV_FETCH_ASSOC);
    if (!$periodRow) {
        throw new RuntimeException('Kode periode tidak ditemukan di mko_rawdata.');
    }

    if (getMaterialCountByKode($gg, $kodePeriode) > 0) {
        throw new RuntimeException('Kode periode ini sudah pernah ditarik material obat dan tidak bisa diproses ulang.');
    }

    $prdnmbrStmt = sqlsrvExecOrFail(
        $gg,
        "SELECT DISTINCT prdnmbr FROM dbo.mko_rawdata WHERE kode_periode = ? AND ISNULL(prdnmbr, '') <> ''",
        [$kodePeriode]
    );
    $prdnmbrRows = sqlsrvAll($prdnmbrStmt);
    $prdnmbrList = [];
    foreach ($prdnmbrRows as $row) {
        $prdnmbrList[] = $row['prdnmbr'];
    }

    if (!$prdnmbrList) {
        throw new RuntimeException('Tidak ada prdnmbr unik untuk kode periode ini.');
    }

    sqlsrv_begin_transaction($gg);
    sqlsrvExecOrFail($gg, "DELETE FROM dbo.MKO_materialobat WHERE kode_periode = ?", [$kodePeriode]);

    $placeholders = implode(', ', array_fill(0, count($prdnmbrList), '?'));
    $query = "
        WITH MaterialBase AS (
            SELECT DISTINCT
                pdproductionhd.prdnmbr,
                pdproductionhd.prodcode,
                pdproductionhd.prodname,
                smprodtechdata.labeljual,
                smprodtechdata.cuscolor,
                pdcolorms.colorcode,
                pdcolorms.colorname,
                pdrtgproc.rtgproccode,
                pdrtgproc.rtgprocname,
                pdrtgms.rtgcode,
                pdrtgms.rtgname,
                pdresultmat.prodcode AS material_code,
                pdresultmat.prodname AS material_name,
                pdresultmat.matqty,
                smuom.uomcode,
                pdresultmat.actualprice AS unit_price,
                pdresultmat.actualqtyprice AS subtotal,
                pdresulthd.resultqty AS routing_qty,
                pdproductionhd.prdqty,
                pdbonreq.bonweight,
                pdresultmat.prodcf,
                pdbomhd.bomcode,
                pdbonreq.vlot,
                pdproductionrtg.prdstdqty
            FROM pdproductionhd
            LEFT JOIN smprodtechdata
                ON smprodtechdata.prodid = pdproductionhd.prodid
            LEFT JOIN pdcolorms
                ON pdcolorms.colormsid = pdproductionhd.colorid
            LEFT JOIN pdresultmat
                ON pdresultmat.productionhdid = pdproductionhd.productionhdid
            LEFT JOIN smuom
                ON smuom.uomid = pdresultmat.matuomid
            LEFT JOIN pdresulthd
                ON pdresulthd.productionhdid = pdproductionhd.productionhdid
            LEFT JOIN pdrtgproc
                ON pdproductionhd.rtgprocid = pdrtgproc.rtgprocid
            LEFT JOIN pdproductionrtg
                ON pdresultmat.productionrtgid = pdproductionrtg.productionrtgid
            LEFT JOIN pdrtgms
                ON pdproductionrtg.rtgmsid = pdrtgms.rtgmsid
            LEFT JOIN pdbonreq
                ON pdproductionhd.productionhdid = pdbonreq.productionhdid
                AND pdproductionrtg.productionrtgid = pdbonreq.productionrtgid
            LEFT JOIN pdbomhd
                ON pdbonreq.bomhdid = pdbomhd.bomhdid
                AND pdresultmat.bomhdid = pdbomhd.bomhdid
            WHERE
                pdproductionhd.prdcenterid = 53
                AND pdresultmat.accmsid IS NULL
                AND pdresultmat.fgusedtype <> 'A'
                AND pdproductionhd.prddate BETWEEN '20250101' AND '20261231'
                AND pdproductionhd.prdnmbr IN ({$placeholders})
        ),
        MaterialTotal AS (
            SELECT
                prdnmbr,
                SUM(COALESCE(subtotal, 0)) AS total_subtotal
            FROM MaterialBase
            GROUP BY prdnmbr
        )
        SELECT
            b.*,
            CASE
                WHEN COALESCE(b.prdstdqty, 0) = 0 THEN 0
                ELSE t.total_subtotal / b.prdstdqty
            END AS cost_meter
        FROM MaterialBase b
        LEFT JOIN MaterialTotal t
            ON t.prdnmbr = b.prdnmbr
        ORDER BY b.prdnmbr, b.material_code
    ";

    $rows = pdoFetchAll($erp, $query, $prdnmbrList);
    $filteredRows = [];
    foreach ($rows as $row) {
        $materialCode = toNullableString($row['material_code'] ?? null);
        if ($materialCode === null) {
            continue;
        }

        if (!array_key_exists($materialCode, $kelompokMap)) {
            continue;
        }

        $row['kelompok'] = $kelompokMap[$materialCode];
        $filteredRows[] = $row;
    }
    $rows = $filteredRows;

    $insertSql = "
        INSERT INTO dbo.MKO_materialobat (
            kode_periode,
            period_start,
            period_end,
            prdnmbr,
            prodcode,
            prodname,
            labeljual,
            cuscolor,
            colorcode,
            colorname,
            rtgproccode,
            rtgprocname,
            rtgcode,
            rtgname,
            material_code,
            material_name,
            matqty,
            uomcode,
            unit_price,
            subtotal,
            routing_qty,
            cost_meter,
            prdqty,
            bonweight,
            prodcf,
            bomcode,
            vlot,
            prdstdqty,
            kelompok,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $inserted = 0;
    foreach ($rows as $row) {
        sqlsrvExecOrFail($gg, $insertSql, [
            $kodePeriode,
            $periodRow['period_start'],
            $periodRow['period_end'],
            $row['prdnmbr'],
            $row['prodcode'],
            $row['prodname'],
            $row['labeljual'],
            $row['cuscolor'],
            $row['colorcode'],
            $row['colorname'],
            $row['rtgproccode'],
            $row['rtgprocname'],
            $row['rtgcode'],
            $row['rtgname'],
            $row['material_code'],
            $row['material_name'],
            $row['matqty'],
            $row['uomcode'],
            $row['unit_price'],
            $row['subtotal'],
            $row['routing_qty'],
            $row['cost_meter'],
            $row['prdqty'],
            $row['bonweight'],
            $row['prodcf'],
            $row['bomcode'],
            $row['vlot'],
            $row['prdstdqty'],
            $row['kelompok'],
            getRequestUser(),
        ]);
        $inserted++;
    }

    sqlsrv_commit($gg);

    jsonResponse([
        'success' => true,
        'message' => 'Material obat berhasil disimpan.',
        'kode_periode' => $kodePeriode,
        'count' => $inserted,
        'prdnmbr_count' => count($prdnmbrList),
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
