<?php
function hasilProduksiSusutValidDateYmd($date) {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function hasilProduksiSusutFetchData(PDO $conn3, $startDate, $endDate) {
    $today = date('Y-m-d');
    $errors = [];
    $rows = [];
    $maxDays = 31;

    if (!hasilProduksiSusutValidDateYmd($startDate)) { $startDate = $today; }
    if (!hasilProduksiSusutValidDateYmd($endDate)) { $endDate = $today; }

    $startObj = new DateTime($startDate);
    $endObj = new DateTime($endDate);
    if ($startObj > $endObj) {
        [$startDate, $endDate] = [$endDate, $startDate];
        [$startObj, $endObj] = [$endObj, $startObj];
    }

    $rangeDays = $startObj->diff($endObj)->days + 1;
    if ($rangeDays > $maxDays) {
        $errors[] = "Range tanggal maksimal {$maxDays} hari agar query tidak berat.";
    }

    if (empty($errors)) {
        try {
            $conn3->beginTransaction();
            $conn3->exec('DROP TABLE IF EXISTS TmpFilteredHd;');
            $stmt = $conn3->prepare(<<<'SQL'
CREATE TEMP TABLE TmpFilteredHd ON COMMIT PRESERVE ROWS AS
SELECT phd.ProductionHdId, phd.PrdNmbr, phd.ColorId, phd.PrdUOMId,
       MAX(CAST(rtg.EndDate AS DATE)) AS tgl_selesai
FROM PDProductionHd phd
INNER JOIN PDProductionRtg rtg ON rtg.ProductionHdId = phd.ProductionHdId AND rtg.RtgMsId = '692' AND rtg.CompId = 2
INNER JOIN PDRtgMs rms ON rms.RtgMsId = rtg.RtgMsId AND (rms.FgLastRtg = 'Y' OR rtg.FgProdResult = 'Y')
WHERE phd.FgStatus = 'X' 
  AND phd.ISOId IS NOT NULL 
  AND phd.CompId = 2 
  AND phd.WorkCenterId = '111'
  AND rtg.EndDate IS NOT NULL 
  AND rtg.EndDate >= CAST(:start_date AS DATE) 
  AND rtg.EndDate < (CAST(:end_date AS DATE) + INTERVAL '1 day')
  AND EXISTS (
      SELECT 1 
      FROM PDProductionRtg rtg_x 
      WHERE rtg_x.ProductionHdId = phd.ProductionHdId 
        AND rtg_x.FgStatus = 'X'
  )
  AND NOT EXISTS (
      SELECT 1 
      FROM PDProductionMat pm 
      INNER JOIN SMProduct prod ON prod.ProdId = pm.ProdId 
      WHERE pm.ProductionHdId = phd.ProductionHdId 
        AND UPPER(COALESCE(prod.ProdName, '')) LIKE '%RTL%'
  )
GROUP BY phd.ProductionHdId, phd.PrdNmbr, phd.ColorId, phd.PrdUOMId;
SQL
);
            $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);

            $conn3->exec(<<<'SQL'
CREATE UNIQUE INDEX idx_tmp_filtered_hd ON TmpFilteredHd (ProductionHdId);

DROP TABLE IF EXISTS TmpListFirstRtg;
CREATE TEMP TABLE TmpListFirstRtg ON COMMIT PRESERVE ROWS AS
SELECT rtg.ProductionHdId, rtg.ProductionRtgId 
FROM PDProductionRtg rtg 
WHERE rtg.RtgSeq = 1
  AND rtg.ProductionHdId IN (SELECT ProductionHdId FROM TmpFilteredHd);
CREATE INDEX idx_tmp_first_rtg ON TmpListFirstRtg (ProductionHdId, ProductionRtgId);

DROP TABLE IF EXISTS TmpDataGreige;
CREATE TEMP TABLE TmpDataGreige ON COMMIT PRESERVE ROWS AS
SELECT rm.ProductionHdId,
    SUM(CASE WHEN rm.MatUOMId = uom.vUOMMeterId THEN COALESCE(rm.MatQty, 0)
        WHEN rm.MatStdUOMId = uom.vUOMMeterId THEN COALESCE(rm.MatStdQty, 0)
        ELSE COALESCE(ROUND((rm.MatStdQty * eq.QtyConversion / NULLIF(eq.QtyStd, 0)), uom_eq.UOMDecimal), 0) END) AS MatQtyM
FROM PDResultMat rm
INNER JOIN TmpListFirstRtg frtg ON frtg.ProductionRtgId = rm.ProductionRtgId AND frtg.ProductionHdId = rm.ProductionHdId
CROSS JOIN (SELECT MAX(CASE WHEN UOMCode = 'M' THEN UOMId END) AS vUOMMeterId FROM SMUOM) uom
LEFT JOIN SMProdEquivalent eq ON eq.ProdId = rm.ProdId AND eq.UOMEquivalent = uom.vUOMMeterId
LEFT JOIN SMUOM uom_eq ON uom_eq.UOMId = eq.UOMEquivalent
GROUP BY rm.ProductionHdId;
CREATE INDEX idx_tmp_greige ON TmpDataGreige (ProductionHdId);

DROP TABLE IF EXISTS TmpPivotResult;
CREATE TEMP TABLE TmpPivotResult ON COMMIT PRESERVE ROWS AS
SELECT hd.ProductionHdId,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'A1' THEN dt.ResultStdQty ELSE 0 END) AS A1_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'A1' THEN dt.ResultStdQty ELSE 0 END) AS A1_Yard,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'A2' THEN dt.ResultStdQty ELSE 0 END) AS A2_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'A2' THEN dt.ResultStdQty ELSE 0 END) AS A2_Yard,
    SUM(CASE WHEN uom.UOMCode = 'KG' AND g.GradeCode = 'A3' THEN dt.ResultStdQty ELSE 0 END) AS A3_KG,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'A3' THEN dt.ResultStdQty ELSE 0 END) AS A3_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'A3' THEN dt.ResultStdQty ELSE 0 END) AS A3_Yard,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'B1' THEN dt.ResultStdQty ELSE 0 END) AS B1_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'B1' THEN dt.ResultStdQty ELSE 0 END) AS B1_Yard,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'B2' THEN dt.ResultStdQty ELSE 0 END) AS B2_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'B2' THEN dt.ResultStdQty ELSE 0 END) AS B2_Yard,
    SUM(CASE WHEN uom.UOMCode = 'KG' AND g.GradeCode = 'B3' THEN dt.ResultStdQty ELSE 0 END) AS B3_KG,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'B3' THEN dt.ResultStdQty ELSE 0 END) AS B3_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'B3' THEN dt.ResultStdQty ELSE 0 END) AS B3_Yard,
    SUM(CASE WHEN uom.UOMCode = 'KG' AND g.GradeCode = 'C1' THEN dt.ResultStdQty ELSE 0 END) AS C1_KG,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'C1' THEN dt.ResultStdQty ELSE 0 END) AS C1_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'C1' THEN dt.ResultStdQty ELSE 0 END) AS C1_Yard
FROM PDResultDt dt
INNER JOIN PDResultHd hd ON hd.ResultHdId = dt.ResultHdId
INNER JOIN SMUOM uom ON uom.UOMId = dt.ResultStdUOMId
INNER JOIN SMProdTechdata tech ON tech.ProdId = dt.ProdId
INNER JOIN PDGradeMs g ON g.GradeMsId = tech.GradeMsId
WHERE hd.ProductionHdId IN (SELECT ProductionHdId FROM TmpFilteredHd)
GROUP BY hd.ProductionHdId;
CREATE INDEX idx_tmp_pivot ON TmpPivotResult (ProductionHdId);

DROP TABLE IF EXISTS TmpDisplayData;
CREATE TEMP TABLE TmpDisplayData ON COMMIT PRESERVE ROWS AS
WITH BaseData AS (
    SELECT phd.ProductionHdId AS id_produksi, phd.PrdNmbr AS nomor_produksi,
        phd.tgl_selesai,
        SUBSTRING(col.ColorCode, 5, 2) AS kode_proses, uom.UOMCode AS kode_uom,
        COALESCE(grg.MatQtyM, 0) AS qty_mat_meter, COALESCE(ROUND(grg.MatQtyM / 0.9144, 4), 0) AS qty_mat_yard,
        COALESCE(pvt.A1_Meter, 0) AS a1_mtr, COALESCE(pvt.A1_Yard, 0) AS a1_yrd, COALESCE(pvt.A2_Meter, 0) AS a2_mtr, COALESCE(pvt.A2_Yard, 0) AS a2_yrd,
        COALESCE(pvt.A3_KG, 0) AS a3_kg, COALESCE(pvt.A3_Meter, 0) AS a3_mtr, COALESCE(pvt.A3_Yard, 0) AS a3_yrd,
        COALESCE(pvt.B1_Meter, 0) AS b1_mtr, COALESCE(pvt.B1_Yard, 0) AS b1_yrd, COALESCE(pvt.B2_Meter, 0) AS b2_mtr, COALESCE(pvt.B2_Yard, 0) AS b2_yrd,
        COALESCE(pvt.B3_KG, 0) AS b3_kg, COALESCE(pvt.B3_Meter, 0) AS b3_mtr, COALESCE(pvt.B3_Yard, 0) AS b3_yrd, COALESCE(pvt.C1_KG, 0) AS c1_kg, COALESCE(pvt.C1_Meter, 0) AS c1_mtr, COALESCE(pvt.C1_Yard, 0) AS c1_yrd
    FROM TmpFilteredHd phd
    LEFT JOIN PDColorMs col ON col.ColorMsId = phd.ColorId
    LEFT JOIN SMUOM uom ON uom.UOMId = phd.PrdUOMId
    LEFT JOIN TmpDataGreige grg ON grg.ProductionHdId = phd.ProductionHdId
    LEFT JOIN TmpPivotResult pvt ON pvt.ProductionHdId = phd.ProductionHdId
), CalculatedData AS (
    SELECT *, ROUND(a1_mtr + (a1_yrd * 0.9144), 4) AS total_a1, ROUND(a2_mtr + (a2_yrd * 0.9144), 4) AS total_a2, ROUND((a3_kg * 2.5) + a3_mtr + (a3_yrd * 0.9144), 4) AS total_a3, ROUND(b1_mtr + (b1_yrd * 0.9144), 4) AS total_b1, ROUND(b2_mtr + (b2_yrd * 0.9144), 4) AS total_b2, ROUND((b3_kg * 2.5) + b3_mtr + (b3_yrd * 0.9144), 4) AS total_b3, ROUND((c1_kg * 3.33) + c1_mtr + (c1_yrd * 0.9144), 4) AS total_c1 FROM BaseData
)
SELECT *, (total_a1 + total_a2 + total_a3 + total_b1 + total_b2 + total_b3 + total_c1) AS total_kain, (qty_mat_meter - (total_a1 + total_a2 + total_a3 + total_b1 + total_b2 + total_b3 + total_c1)) AS qty_susut, CASE WHEN qty_mat_meter = 0 THEN 0 ELSE ROUND(((qty_mat_meter - (total_a1 + total_a2 + total_a3 + total_b1 + total_b2 + total_b3 + total_c1)) / qty_mat_meter) * 100, 2) END AS persen_susut FROM CalculatedData;
SQL);
            $finalSql = 'SELECT ROW_NUMBER() OVER (ORDER BY nomor_produksi) AS "No.", nomor_produksi AS "No Produksi", kode_proses AS "Kode Proses", kode_uom AS "UOM", qty_mat_meter AS "Qty Greige Meter", qty_mat_yard AS "Qty Greige Yard", a1_mtr AS "A1 Meter", a1_yrd AS "A1 Yard", total_a1 AS "Total A1 Meter", a2_mtr AS "A2 Meter", a2_yrd AS "A2 Yard", total_a2 AS "Total A2 Meter", a3_kg AS "A3 KG", a3_mtr AS "A3 Meter", a3_yrd AS "A3 Yard", total_a3 AS "Total A3 Meter", b1_mtr AS "B1 Meter", b1_yrd AS "B1 Yard", total_b1 AS "Total B1 Meter", b2_mtr AS "B2 Meter", b2_yrd AS "B2 Yard", total_b2 AS "Total B2 Meter", b3_kg AS "B3 KG", b3_mtr AS "B3 Meter", b3_yrd AS "B3 Yard", total_b3 AS "Total B3 Meter", c1_kg AS "C1 KG", c1_mtr AS "C1 Meter", c1_yrd AS "C1 Yard", total_c1 AS "Total C1 Meter", total_kain AS "Total Hasil Meter", qty_susut AS "Qty Susut Meter", persen_susut AS "Susut (%)" FROM TmpDisplayData ORDER BY nomor_produksi';
            $rows = $conn3->query($finalSql)->fetchAll(PDO::FETCH_ASSOC);
            $conn3->commit();
        } catch (Throwable $e) {
            if ($conn3->inTransaction()) { $conn3->rollBack(); }
            $errors[] = $e->getMessage();
        }
    }

    $summary = [
        'total_rows' => count($rows),
        'total_greige_meter' => 0,
        'total_hasil_meter' => 0,
        'total_susut_meter' => 0,
        'susut_percent' => 0,
        'total_a1_meter' => 0,
        'total_a2_meter' => 0,
        'total_a3_meter' => 0,
        'total_b1_meter' => 0,
        'total_b2_meter' => 0,
        'total_b3_meter' => 0,
        'total_c1_meter' => 0,
    ];
    foreach ($rows as $row) {
        $summary['total_greige_meter'] += (float)($row['Qty Greige Meter'] ?? 0);
        $summary['total_hasil_meter'] += (float)($row['Total Hasil Meter'] ?? 0);
        $summary['total_susut_meter'] += (float)($row['Qty Susut Meter'] ?? 0);
        $summary['total_a1_meter'] += (float)($row['Total A1 Meter'] ?? 0);
        $summary['total_a2_meter'] += (float)($row['Total A2 Meter'] ?? 0);
        $summary['total_a3_meter'] += (float)($row['Total A3 Meter'] ?? 0);
        $summary['total_b1_meter'] += (float)($row['Total B1 Meter'] ?? 0);
        $summary['total_b2_meter'] += (float)($row['Total B2 Meter'] ?? 0);
        $summary['total_b3_meter'] += (float)($row['Total B3 Meter'] ?? 0);
        $summary['total_c1_meter'] += (float)($row['Total C1 Meter'] ?? 0);
    }
    $summary['susut_percent'] = $summary['total_greige_meter'] == 0 ? 0 : round(($summary['total_susut_meter'] / $summary['total_greige_meter']) * 100, 2);
    return compact('startDate', 'endDate', 'errors', 'rows', 'summary', 'maxDays');
}

function hasilProduksiSusutFetchSummaryByDate(PDO $conn3, $startDate, $endDate) {
    $today = date('Y-m-d');
    $errors = [];
    $rows = [];
    $maxDays = 31;

    if (!hasilProduksiSusutValidDateYmd($startDate)) { $startDate = $today; }
    if (!hasilProduksiSusutValidDateYmd($endDate)) { $endDate = $today; }

    $startObj = new DateTime($startDate);
    $endObj = new DateTime($endDate);
    if ($startObj > $endObj) {
        [$startDate, $endDate] = [$endDate, $startDate];
        [$startObj, $endObj] = [$endObj, $startObj];
    }

    $rangeDays = $startObj->diff($endObj)->days + 1;
    if ($rangeDays > $maxDays) {
        $errors[] = "Range tanggal maksimal {$maxDays} hari agar query tidak berat.";
    }

    if (empty($errors)) {
        try {
            $conn3->beginTransaction();
            $conn3->exec('DROP TABLE IF EXISTS TmpFilteredHd;');
            $stmt = $conn3->prepare(<<<'SQL'
CREATE TEMP TABLE TmpFilteredHd ON COMMIT PRESERVE ROWS AS
SELECT phd.ProductionHdId, phd.PrdNmbr, phd.ColorId, phd.PrdUOMId,
       MAX(CAST(rtg.EndDate AS DATE)) AS tgl_selesai
FROM PDProductionHd phd
INNER JOIN PDProductionRtg rtg ON rtg.ProductionHdId = phd.ProductionHdId AND rtg.RtgMsId = '692' AND rtg.CompId = 2
INNER JOIN PDRtgMs rms ON rms.RtgMsId = rtg.RtgMsId AND (rms.FgLastRtg = 'Y' OR rtg.FgProdResult = 'Y')
WHERE phd.FgStatus = 'X' 
  AND phd.ISOId IS NOT NULL 
  AND phd.CompId = 2 
  AND phd.WorkCenterId = '111'
  AND rtg.EndDate IS NOT NULL 
  AND rtg.EndDate >= CAST(:start_date AS DATE) 
  AND rtg.EndDate < (CAST(:end_date AS DATE) + INTERVAL '1 day')
  AND EXISTS (
      SELECT 1 
      FROM PDProductionRtg rtg_x 
      WHERE rtg_x.ProductionHdId = phd.ProductionHdId 
        AND rtg_x.FgStatus = 'X'
  )
  AND NOT EXISTS (
      SELECT 1 
      FROM PDProductionMat pm 
      INNER JOIN SMProduct prod ON prod.ProdId = pm.ProdId 
      WHERE pm.ProductionHdId = phd.ProductionHdId 
        AND UPPER(COALESCE(prod.ProdName, '')) LIKE '%RTL%'
  )
GROUP BY phd.ProductionHdId, phd.PrdNmbr, phd.ColorId, phd.PrdUOMId;
SQL
);
            $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);

            $conn3->exec(<<<'SQL'
CREATE UNIQUE INDEX idx_tmp_filtered_hd ON TmpFilteredHd (ProductionHdId);

DROP TABLE IF EXISTS TmpListFirstRtg;
CREATE TEMP TABLE TmpListFirstRtg ON COMMIT PRESERVE ROWS AS
SELECT rtg.ProductionHdId, rtg.ProductionRtgId 
FROM PDProductionRtg rtg 
WHERE rtg.RtgSeq = 1
  AND rtg.ProductionHdId IN (SELECT ProductionHdId FROM TmpFilteredHd);
CREATE INDEX idx_tmp_first_rtg ON TmpListFirstRtg (ProductionHdId, ProductionRtgId);

DROP TABLE IF EXISTS TmpDataGreige;
CREATE TEMP TABLE TmpDataGreige ON COMMIT PRESERVE ROWS AS
SELECT rm.ProductionHdId,
    SUM(CASE WHEN rm.MatUOMId = uom.vUOMMeterId THEN COALESCE(rm.MatQty, 0)
        WHEN rm.MatStdUOMId = uom.vUOMMeterId THEN COALESCE(rm.MatStdQty, 0)
        ELSE COALESCE(ROUND((rm.MatStdQty * eq.QtyConversion / NULLIF(eq.QtyStd, 0)), uom_eq.UOMDecimal), 0) END) AS MatQtyM
FROM PDResultMat rm
INNER JOIN TmpListFirstRtg frtg ON frtg.ProductionRtgId = rm.ProductionRtgId AND frtg.ProductionHdId = rm.ProductionHdId
CROSS JOIN (SELECT MAX(CASE WHEN UOMCode = 'M' THEN UOMId END) AS vUOMMeterId FROM SMUOM) uom
LEFT JOIN SMProdEquivalent eq ON eq.ProdId = rm.ProdId AND eq.UOMEquivalent = uom.vUOMMeterId
LEFT JOIN SMUOM uom_eq ON uom_eq.UOMId = eq.UOMEquivalent
GROUP BY rm.ProductionHdId;
CREATE INDEX idx_tmp_greige ON TmpDataGreige (ProductionHdId);

DROP TABLE IF EXISTS TmpPivotResult;
CREATE TEMP TABLE TmpPivotResult ON COMMIT PRESERVE ROWS AS
SELECT hd.ProductionHdId,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'A1' THEN dt.ResultStdQty ELSE 0 END) AS A1_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'A1' THEN dt.ResultStdQty ELSE 0 END) AS A1_Yard,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'A2' THEN dt.ResultStdQty ELSE 0 END) AS A2_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'A2' THEN dt.ResultStdQty ELSE 0 END) AS A2_Yard,
    SUM(CASE WHEN uom.UOMCode = 'KG' AND g.GradeCode = 'A3' THEN dt.ResultStdQty ELSE 0 END) AS A3_KG,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'A3' THEN dt.ResultStdQty ELSE 0 END) AS A3_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'A3' THEN dt.ResultStdQty ELSE 0 END) AS A3_Yard,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'B1' THEN dt.ResultStdQty ELSE 0 END) AS B1_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'B1' THEN dt.ResultStdQty ELSE 0 END) AS B1_Yard,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'B2' THEN dt.ResultStdQty ELSE 0 END) AS B2_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'B2' THEN dt.ResultStdQty ELSE 0 END) AS B2_Yard,
    SUM(CASE WHEN uom.UOMCode = 'KG' AND g.GradeCode = 'B3' THEN dt.ResultStdQty ELSE 0 END) AS B3_KG,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'B3' THEN dt.ResultStdQty ELSE 0 END) AS B3_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'B3' THEN dt.ResultStdQty ELSE 0 END) AS B3_Yard,
    SUM(CASE WHEN uom.UOMCode = 'KG' AND g.GradeCode = 'C1' THEN dt.ResultStdQty ELSE 0 END) AS C1_KG,
    SUM(CASE WHEN uom.UOMCode = 'M' AND g.GradeCode = 'C1' THEN dt.ResultStdQty ELSE 0 END) AS C1_Meter,
    SUM(CASE WHEN uom.UOMCode = 'YARD' AND g.GradeCode = 'C1' THEN dt.ResultStdQty ELSE 0 END) AS C1_Yard
FROM PDResultDt dt
INNER JOIN PDResultHd hd ON hd.ResultHdId = dt.ResultHdId
INNER JOIN SMUOM uom ON uom.UOMId = dt.ResultStdUOMId
INNER JOIN SMProdTechdata tech ON tech.ProdId = dt.ProdId
INNER JOIN PDGradeMs g ON g.GradeMsId = tech.GradeMsId
WHERE hd.ProductionHdId IN (SELECT ProductionHdId FROM TmpFilteredHd)
GROUP BY hd.ProductionHdId;
CREATE INDEX idx_tmp_pivot ON TmpPivotResult (ProductionHdId);

DROP TABLE IF EXISTS TmpDisplayData;
CREATE TEMP TABLE TmpDisplayData ON COMMIT PRESERVE ROWS AS
WITH BaseData AS (
    SELECT phd.ProductionHdId AS id_produksi, phd.PrdNmbr AS nomor_produksi,
        phd.tgl_selesai,
        SUBSTRING(col.ColorCode, 5, 2) AS kode_proses, uom.UOMCode AS kode_uom,
        COALESCE(grg.MatQtyM, 0) AS qty_mat_meter, COALESCE(ROUND(grg.MatQtyM / 0.9144, 4), 0) AS qty_mat_yard,
        COALESCE(pvt.A1_Meter, 0) AS a1_mtr, COALESCE(pvt.A1_Yard, 0) AS a1_yrd, COALESCE(pvt.A2_Meter, 0) AS a2_mtr, COALESCE(pvt.A2_Yard, 0) AS a2_yrd,
        COALESCE(pvt.A3_KG, 0) AS a3_kg, COALESCE(pvt.A3_Meter, 0) AS a3_mtr, COALESCE(pvt.A3_Yard, 0) AS a3_yrd,
        COALESCE(pvt.B1_Meter, 0) AS b1_mtr, COALESCE(pvt.B1_Yard, 0) AS b1_yrd, COALESCE(pvt.B2_Meter, 0) AS b2_mtr, COALESCE(pvt.B2_Yard, 0) AS b2_yrd,
        COALESCE(pvt.B3_KG, 0) AS b3_kg, COALESCE(pvt.B3_Meter, 0) AS b3_mtr, COALESCE(pvt.B3_Yard, 0) AS b3_yrd, COALESCE(pvt.C1_KG, 0) AS c1_kg, COALESCE(pvt.C1_Meter, 0) AS c1_mtr, COALESCE(pvt.C1_Yard, 0) AS c1_yrd
    FROM TmpFilteredHd phd
    LEFT JOIN PDColorMs col ON col.ColorMsId = phd.ColorId
    LEFT JOIN SMUOM uom ON uom.UOMId = phd.PrdUOMId
    LEFT JOIN TmpDataGreige grg ON grg.ProductionHdId = phd.ProductionHdId
    LEFT JOIN TmpPivotResult pvt ON pvt.ProductionHdId = phd.ProductionHdId
), CalculatedData AS (
    SELECT *, ROUND(a1_mtr + (a1_yrd * 0.9144), 4) AS total_a1, ROUND(a2_mtr + (a2_yrd * 0.9144), 4) AS total_a2, ROUND((a3_kg * 2.5) + a3_mtr + (a3_yrd * 0.9144), 4) AS total_a3, ROUND(b1_mtr + (b1_yrd * 0.9144), 4) AS total_b1, ROUND(b2_mtr + (b2_yrd * 0.9144), 4) AS total_b2, ROUND((b3_kg * 2.5) + b3_mtr + (b3_yrd * 0.9144), 4) AS total_b3, ROUND((c1_kg * 3.33) + c1_mtr + (c1_yrd * 0.9144), 4) AS total_c1 FROM BaseData
)
SELECT *, (total_a1 + total_a2 + total_a3 + total_b1 + total_b2 + total_b3 + total_c1) AS total_kain, (qty_mat_meter - (total_a1 + total_a2 + total_a3 + total_b1 + total_b2 + total_b3 + total_c1)) AS qty_susut, CASE WHEN qty_mat_meter = 0 THEN 0 ELSE ROUND(((qty_mat_meter - (total_a1 + total_a2 + total_a3 + total_b1 + total_b2 + total_b3 + total_c1)) / qty_mat_meter) * 100, 2) END AS persen_susut FROM CalculatedData;
SQL);
            $summarySql = <<<'SQL'
SELECT 
    tgl_selesai,
    SUM(qty_mat_meter) AS greige_meter,
    SUM(qty_mat_yard) AS greige_yard,
    SUM(a1_mtr) AS a1_meter,
    SUM(a1_yrd) AS a1_yard,
    SUM(total_a1) AS total_a1_meter,
    SUM(a2_mtr) AS a2_meter,
    SUM(a2_yrd) AS a2_yard,
    SUM(total_a2) AS total_a2_meter,
    SUM(a3_kg) AS a3_kg,
    SUM(a3_mtr) AS a3_meter,
    SUM(a3_yrd) AS a3_yard,
    SUM(total_a3) AS total_a3_meter,
    SUM(b1_mtr) AS b1_meter,
    SUM(b1_yrd) AS b1_yard,
    SUM(total_b1) AS total_b1_meter,
    SUM(b2_mtr) AS b2_meter,
    SUM(b2_yrd) AS b2_yard,
    SUM(total_b2) AS total_b2_meter,
    SUM(b3_kg) AS b3_kg,
    SUM(b3_mtr) AS b3_meter,
    SUM(b3_yrd) AS b3_yard,
    SUM(total_b3) AS total_b3_meter,
    SUM(c1_kg) AS c1_kg,
    SUM(c1_mtr) AS c1_meter,
    SUM(c1_yrd) AS c1_yard,
    SUM(total_c1) AS total_c1_meter,
    SUM(total_kain) AS total_hasil_meter,
    SUM(qty_susut) AS qty_susut_meter,
    CASE WHEN SUM(qty_mat_meter) = 0 THEN 0 ELSE ROUND((SUM(qty_susut) / SUM(qty_mat_meter)) * 100, 2) END AS persen_susut
FROM TmpDisplayData
GROUP BY tgl_selesai
ORDER BY tgl_selesai ASC
SQL;
            $rows = $conn3->query($summarySql)->fetchAll(PDO::FETCH_ASSOC);
            $conn3->commit();
        } catch (Throwable $e) {
            if ($conn3->inTransaction()) { $conn3->rollBack(); }
            $errors[] = $e->getMessage();
        }
    }

    $grandTotal = [
        'total_days' => count($rows),
        'greige_meter' => 0,
        'greige_yard' => 0,
        'a1_meter' => 0,
        'a1_yard' => 0,
        'total_a1_meter' => 0,
        'a2_meter' => 0,
        'a2_yard' => 0,
        'total_a2_meter' => 0,
        'a3_kg' => 0,
        'a3_meter' => 0,
        'a3_yard' => 0,
        'total_a3_meter' => 0,
        'b1_meter' => 0,
        'b1_yard' => 0,
        'total_b1_meter' => 0,
        'b2_meter' => 0,
        'b2_yard' => 0,
        'total_b2_meter' => 0,
        'b3_kg' => 0,
        'b3_meter' => 0,
        'b3_yard' => 0,
        'total_b3_meter' => 0,
        'c1_kg' => 0,
        'c1_meter' => 0,
        'c1_yard' => 0,
        'total_c1_meter' => 0,
        'total_hasil_meter' => 0,
        'qty_susut_meter' => 0,
        'persen_susut' => 0,
    ];
    foreach ($rows as $row) {
        $grandTotal['greige_meter'] += (float)($row['greige_meter'] ?? 0);
        $grandTotal['greige_yard'] += (float)($row['greige_yard'] ?? 0);
        $grandTotal['a1_meter'] += (float)($row['a1_meter'] ?? 0);
        $grandTotal['a1_yard'] += (float)($row['a1_yard'] ?? 0);
        $grandTotal['total_a1_meter'] += (float)($row['total_a1_meter'] ?? 0);
        $grandTotal['a2_meter'] += (float)($row['a2_meter'] ?? 0);
        $grandTotal['a2_yard'] += (float)($row['a2_yard'] ?? 0);
        $grandTotal['total_a2_meter'] += (float)($row['total_a2_meter'] ?? 0);
        $grandTotal['a3_kg'] += (float)($row['a3_kg'] ?? 0);
        $grandTotal['a3_meter'] += (float)($row['a3_meter'] ?? 0);
        $grandTotal['a3_yard'] += (float)($row['a3_yard'] ?? 0);
        $grandTotal['total_a3_meter'] += (float)($row['total_a3_meter'] ?? 0);
        $grandTotal['b1_meter'] += (float)($row['b1_meter'] ?? 0);
        $grandTotal['b1_yard'] += (float)($row['b1_yard'] ?? 0);
        $grandTotal['total_b1_meter'] += (float)($row['total_b1_meter'] ?? 0);
        $grandTotal['b2_meter'] += (float)($row['b2_meter'] ?? 0);
        $grandTotal['b2_yard'] += (float)($row['b2_yard'] ?? 0);
        $grandTotal['total_b2_meter'] += (float)($row['total_b2_meter'] ?? 0);
        $grandTotal['b3_kg'] += (float)($row['b3_kg'] ?? 0);
        $grandTotal['b3_meter'] += (float)($row['b3_meter'] ?? 0);
        $grandTotal['b3_yard'] += (float)($row['b3_yard'] ?? 0);
        $grandTotal['total_b3_meter'] += (float)($row['total_b3_meter'] ?? 0);
        $grandTotal['c1_kg'] += (float)($row['c1_kg'] ?? 0);
        $grandTotal['c1_meter'] += (float)($row['c1_meter'] ?? 0);
        $grandTotal['c1_yard'] += (float)($row['c1_yard'] ?? 0);
        $grandTotal['total_c1_meter'] += (float)($row['total_c1_meter'] ?? 0);
        $grandTotal['total_hasil_meter'] += (float)($row['total_hasil_meter'] ?? 0);
        $grandTotal['qty_susut_meter'] += (float)($row['qty_susut_meter'] ?? 0);
    }
    $grandTotal['persen_susut'] = $grandTotal['greige_meter'] == 0 ? 0 : round(($grandTotal['qty_susut_meter'] / $grandTotal['greige_meter']) * 100, 2);

    return compact('startDate', 'endDate', 'errors', 'rows', 'grandTotal', 'maxDays');
}
