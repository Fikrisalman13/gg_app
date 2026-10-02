<?php
function routing692NormalizeDateTime($value, string $fallback, bool $endOfDay = false): string {
    $value = str_replace('T', ' ', trim((string) $value));
    $formats = ['Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();
        $hasErrors = is_array($errors) && ($errors['warning_count'] || $errors['error_count']);
        if ($dt && !$hasErrors && $dt->format($format) === $value) {
            if ($format === 'Y-m-d') { $dt->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0); }
            return $dt->format('Y-m-d H:i');
        }
    }
    return $fallback;
}

function routing692FetchData(PDO $conn3, $startDate, $endDate, array $routingIds = ['692']) {
    $today = date('Y-m-d');
    $errors = [];
    $rows = [];
    $maxDays = 31;
    $startDate = routing692NormalizeDateTime($startDate, $today . ' 00:00');
    $endDate = routing692NormalizeDateTime($endDate, $today . ' 23:59', true);
    $routingIds = array_values(array_unique(array_filter(array_map('strval', $routingIds), fn($id) => preg_match('/^\d+$/', $id))));
    if (!$routingIds) { $errors[] = 'Pilih minimal 1 routing.'; }
    if (count($routingIds) > 5) { $errors[] = 'Routing maksimal 5 pilihan.'; }

    $startObj = DateTime::createFromFormat('Y-m-d H:i', $startDate);
    $endObj = DateTime::createFromFormat('Y-m-d H:i', $endDate);
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
            $conn3->exec(<<<'SQL'
DROP TABLE IF EXISTS TmpUOMMeter;
CREATE TEMP TABLE TmpUOMMeter AS SELECT UOMId, UOMCode, UOMDecimal FROM SMUOM WHERE UOMCode IN ('m', 'M');
CREATE UNIQUE INDEX idx_tmpuommeter_id ON TmpUOMMeter(UOMId);
DROP TABLE IF EXISTS TmpUOMYard;
CREATE TEMP TABLE TmpUOMYard AS SELECT UOMId, UOMCode, UOMDecimal FROM SMUOM WHERE UOMCode IN ('yard', 'YARD', 'Yard');
CREATE UNIQUE INDEX idx_tmpuomyard_id ON TmpUOMYard(UOMId);
DROP TABLE IF EXISTS TmpUOMKg;
CREATE TEMP TABLE TmpUOMKg AS SELECT UOMId, UOMCode, UOMDecimal FROM SMUOM WHERE UOMCode IN ('kg', 'KG', 'Kg');
CREATE UNIQUE INDEX idx_tmpuomkg_id ON TmpUOMKg(UOMId);
DROP TABLE IF EXISTS TmpProdRtg;
SQL);

            $stmt = $conn3->prepare(<<<'SQL'
CREATE TEMP TABLE TmpProdRtg AS
SELECT r.isoid, r.productionhdid, r.productionrtgid, r.rtgseq, r.rtgmsid,
    0 AS prevrtgmsid, 0 AS nextrtgmsid, CAST(NULL AS TIMESTAMP) AS prevendtime,
    r.startdate, r.starttime, r.enddate, r.endtime,
    CASE WHEN r.rtgseq = 1 THEN h.prdqty ELSE r.prdqty END AS prdqty,
    r.prduomid, u.uomcode AS prduomcode, u.uomdecimal AS prduomdecimal,
    COALESCE(r.fgrework, 'N') AS fgrework, r.fgresult, r.fgstatus, h.fgstatus AS hdstatus,
    h.workcenterid, dt.wohdid, ms.rtggrpid, ms.fglastrtg, r.fgprodresult, r.famasterid,
    r.failmsid, r.resultdesc, r.startshiftid, r.chgtopassdesc, r.upddate, r.upduser
FROM pdproductionrtg r
INNER JOIN pdproductionhd h ON h.productionhdid = r.productionhdid
INNER JOIN pdproductiondt dt ON dt.productionhdid = h.productionhdid
INNER JOIN pdrtgms ms ON ms.rtgmsid = r.rtgmsid
LEFT JOIN smuom u ON u.uomid = r.prduomid
WHERE r.compid = 2
  AND r.enddate >= CAST(:start_date AS DATE)
  AND r.enddate < (CAST(:end_date AS DATE) + INTERVAL '1 day')
  AND r.rtgmsid IN (__ROUTING_PLACEHOLDERS__)
  AND h.workcenterid = '111';
SQL);
            $routingParams = [];
            foreach ($routingIds as $i => $routingId) { $routingParams[':rtg' . $i] = $routingId; }
            $routingPlaceholders = implode(', ', array_keys($routingParams));
            $sql = str_replace('__ROUTING_PLACEHOLDERS__', $routingPlaceholders, $stmt->queryString);
            $stmt = $conn3->prepare($sql);
            $stmt->execute(array_merge([':start_date' => $startDate, ':end_date' => $endDate], $routingParams));

            $conn3->exec(<<<'SQL'
CREATE INDEX idx_tmpprodrtg_hd_seq ON TmpProdRtg(productionhdid, rtgseq);
CREATE INDEX idx_tmpprodrtg_rtgid ON TmpProdRtg(productionrtgid);
CREATE INDEX idx_tmpprodrtg_hdid ON TmpProdRtg(productionhdid);

DROP TABLE IF EXISTS TempALLRtg;
CREATE TEMP TABLE TempALLRtg AS
SELECT r.productionhdid, r.productionrtgid, h.prodid, r.endtime, r.rtgseq, r.rtgmsid,
    r.prdqty, r.prdstdqty, r.prduomid, r.prdstduomid
FROM pdproductionrtg r
INNER JOIN pdproductionhd h ON h.productionhdid = r.productionhdid
WHERE r.compid = 2 AND r.productionhdid IN (SELECT productionhdid FROM TmpProdRtg);
CREATE INDEX idx_tempallrtg_hd_seq ON TempALLRtg(productionhdid, rtgseq);

UPDATE TmpProdRtg SET prevrtgmsid = a.rtgmsid, prevendtime = a.endtime
FROM TempALLRtg a WHERE a.productionhdid = TmpProdRtg.productionhdid AND a.rtgseq = TmpProdRtg.rtgseq - 1;
UPDATE TmpProdRtg SET nextrtgmsid = a.rtgmsid
FROM TempALLRtg a WHERE a.productionhdid = TmpProdRtg.productionhdid AND a.rtgseq = TmpProdRtg.rtgseq + 1;

DROP TABLE IF EXISTS TmpProdMatALL;
CREATE TEMP TABLE TmpProdMatALL AS
SELECT m.productionhdid, m.matseq, m.prodid, m.prodcode, m.prodname,
    ROW_NUMBER() OVER (PARTITION BY m.productionhdid ORDER BY m.matseq) AS rn
FROM pdproductionmat m
WHERE m.compid = 2 AND m.fgusedtype = 'A' AND COALESCE(m.prodname, '') NOT ILIKE '%RTL%'
  AND m.productionhdid IN (SELECT productionhdid FROM TempALLRtg WHERE rtgseq = 1);
CREATE INDEX idx_tmpprodmatall_hd_rn ON TmpProdMatALL(productionhdid, rn);
DROP TABLE IF EXISTS TmpProdMat;
CREATE TEMP TABLE TmpProdMat AS SELECT * FROM TmpProdMatALL WHERE rn = 1;
CREATE UNIQUE INDEX idx_tmpprodmat_hd ON TmpProdMat(productionhdid);
DROP TABLE IF EXISTS TmpProdMatPcs;
CREATE TEMP TABLE TmpProdMatPcs AS SELECT productionhdid, COUNT(1) AS greigepcs FROM TmpProdMatALL GROUP BY productionhdid;
CREATE UNIQUE INDEX idx_tmpprodmatpcs_hd ON TmpProdMatPcs(productionhdid);

DROP TABLE IF EXISTS TmpProdResult;
CREATE TEMP TABLE TmpProdResult AS
SELECT d.resulthdid, h.productionhdid, d.prodid, d.prodcode, d.prodname, COUNT(1) AS resultpcs,
    SUM(d.resultqty) AS resultqty, d.resultuomid, ru.uomcode AS resultuomcode, ru.uomdecimal AS resultuomdecimal,
    SUM(d.resultstdqty) AS resultstdqty, d.resultstduomid, su.uomcode AS resultstduomcode, su.uomdecimal AS resultstduomdecimal,
    CAST(0 AS NUMERIC(19, 4)) AS resultqtym,
    SUBSTRING(d.prodcode, 4, 1) || SUBSTRING(d.prodcode, 3, 1) AS grade
FROM pdresultdt d
INNER JOIN pdresulthd h ON h.resulthdid = d.resulthdid
INNER JOIN smuom ru ON ru.uomid = d.resultuomid
INNER JOIN smuom su ON su.uomid = d.resultstduomid
WHERE h.productionhdid IN (SELECT productionhdid FROM TmpProdRtg)
GROUP BY d.resulthdid, h.productionhdid, d.prodid, d.prodcode, d.prodname, d.resultuomid,
    ru.uomcode, ru.uomdecimal, d.resultstduomid, su.uomcode, su.uomdecimal;
CREATE INDEX idx_tmpprodresult_hd_prod ON TmpProdResult(productionhdid, prodid);
ALTER TABLE TmpProdResult ADD COLUMN IF NOT EXISTS fgdefault VARCHAR(1);

UPDATE TmpProdResult SET resultqtym = CASE
    WHEN TmpProdResult.resultuomid = m.uomid THEN TmpProdResult.resultqty
    WHEN TmpProdResult.resultstduomid = m.uomid THEN TmpProdResult.resultstdqty
    WHEN e.qtystd > 0 AND e.qtyconversion > 0 THEN ROUND((TmpProdResult.resultstdqty / e.qtystd) * e.qtyconversion, m.uomdecimal)
    ELSE 0 END
FROM TmpUOMMeter m LEFT JOIN smprodequivalent e ON e.uomequivalent = m.uomid
WHERE e.prodid = TmpProdResult.prodid;
UPDATE TmpProdResult SET fgdefault = CASE WHEN TmpProdResult.prodid = h.prodid THEN 'Y' ELSE 'N' END
FROM pdproductionhd h WHERE TmpProdResult.productionhdid = h.productionhdid;

DROP TABLE IF EXISTS TmpPermartaianM;
CREATE TEMP TABLE TmpPermartaianM AS
SELECT t.productionhdid,
    CASE WHEN t.prduomid = m.uomid THEN t.prdqty
         WHEN t.prdstduomid = m.uomid THEN t.prdstdqty
         WHEN e.qtystd > 0 AND e.qtyconversion > 0 THEN ROUND((t.prdstdqty / e.qtystd) * e.qtyconversion, m.uomdecimal)
         ELSE 0 END AS permartaianm
FROM TempALLRtg t CROSS JOIN TmpUOMMeter m
LEFT JOIN smprodequivalent e ON e.prodid = t.prodid AND e.uomequivalent = m.uomid
WHERE t.rtgseq = 1;
CREATE UNIQUE INDEX idx_tmppermartaianm_hd ON TmpPermartaianM(productionhdid);
DROP TABLE IF EXISTS TmpPermartaianY;
CREATE TEMP TABLE TmpPermartaianY AS
SELECT t.productionhdid,
    CASE WHEN t.prduomid = y.uomid THEN t.prdqty
         WHEN t.prdstduomid = y.uomid THEN t.prdstdqty
         WHEN e.qtystd > 0 AND e.qtyconversion > 0 THEN ROUND((t.prdstdqty / e.qtystd) * e.qtyconversion, y.uomdecimal)
         ELSE 0 END AS permartaiany
FROM TempALLRtg t CROSS JOIN TmpUOMYard y
LEFT JOIN smprodequivalent e ON e.prodid = t.prodid AND e.uomequivalent = y.uomid
WHERE t.rtgseq = 1;
CREATE UNIQUE INDEX idx_tmppermartaiany_hd ON TmpPermartaianY(productionhdid);
SQL);

            $finalSql = <<<SQL
WITH TmpPIC AS (
    SELECT ROW_NUMBER() OVER (PARTITION BY p.productionrtgid) AS rn, p.productionrtgid, e.empnik AS picnik, e.empname AS picname
    FROM pdresultpic p INNER JOIN smemployee e ON p.empid = e.empid
    WHERE p.productionrtgid IN (SELECT productionrtgid FROM TmpProdRtg) AND p.compid = 2 AND p.fgpictype IS NULL
), TmpTestPIC AS (
    SELECT ROW_NUMBER() OVER (PARTITION BY p.productionrtgid) AS rn, p.productionrtgid, e.empnik AS testpicnik, e.empname AS testpicname
    FROM pdresultpic p INNER JOIN smemployee e ON p.empid = e.empid
    WHERE p.productionrtgid IN (SELECT productionrtgid FROM TmpProdRtg) AND p.compid = 2 AND p.fgpictype = 'T'
), TmpMatcherPIC AS (
    SELECT ROW_NUMBER() OVER (PARTITION BY p.productionrtgid) AS rn, p.productionrtgid, e.empnik AS matcherpicnik, e.empname AS matcherpicname
    FROM pdresultpic p INNER JOIN smemployee e ON p.empid = e.empid
    WHERE p.productionrtgid IN (SELECT productionrtgid FROM TmpProdRtg) AND p.compid = 2 AND p.fgpictype = 'M'
), DataUtama AS (
    SELECT w.wohdid, w.wonmbr, h.productionhdid, h.prdnmbr, h.workcenterid,
        wc.workcentercode, wc.workcentername, wc.workcentercode || ' - ' || wc.workcentername AS workcenterstr,
        rtg.productionrtgid, rtg.rtgseq, ms.rtgcode, ms.rtgname, ms.rtgcode || ' - ' || ms.rtgname AS rtgstr,
        r.prodid, r.prodcode, r.prodname, r.prodcode || ' - ' || r.prodname AS prodstr, COALESCE(r.fgdefault, 'N') AS fgdefault,
        rtg.prduomid, rtg.prduomcode, rtg.prduomdecimal, mat.prodid AS matid, mat.prodcode AS matcode, mat.prodname AS matname,
        c.colormsid AS colorid, c.colorcode, c.colorname, so.socusid AS cusid, so.socuscode AS cuscode, so.socusname AS cusname,
        tech.cuscolor, tech.labeljual, proc.rtgproccode, bom.bomhdid, bom.bomcode, bom.bomname, bom.prodcf,
        rtg.prdqty, ROUND((bom.prodcf * rtg.prdqty) / 1000, COALESCE(kg.uomdecimal, 0)) AS greigekg,
        COALESCE(kg.uomdecimal, 0) AS greigekgdecimal,
        CASE WHEN COALESCE(rtg.fgrework, 'N') = 'N' THEN rtg.prdqty ELSE 0 END AS normalqty,
        CASE WHEN rtg.fgrework = 'Y' AND rtg.fgresult = 'F' THEN rtg.prdqty ELSE 0 END AS reproqty,
        iso.isodesc, grp.rtggrpid, grp.rtggrpcode, grp.rtggrpname, grp.rtggrpcode || ' - ' || grp.rtggrpname AS rtggrpstr,
        fa.facode, fa.faname, rtg.fgresult, dm.domaindesc AS fgresultstr, rtg.failmsid, fail.failcode, fail.faildesc,
        fail.failcode || ' - ' || fail.faildesc AS failstr, rtg.resultdesc, ps.lebarkain, ps.prdspeed, ps.prdtemp, ps.prdtempfinish,
        ps.wheelno, ps.leftlisting, ps.middlelisting, ps.rightlisting, ps.skewing,
        prev.rtgcode AS prevrtgcode, prev.rtgname AS prevrtgname, prev.rtgcode || ' - ' || prev.rtgname AS prevrtgstr,
        next.rtgcode AS nextrtgcode, next.rtgname AS nextrtgname, next.rtgcode || ' - ' || next.rtgname AS nextrtgstr,
        TO_CHAR(rtg.startdate, 'YYYYMMDD') AS startdate, TO_CHAR(rtg.starttime, 'HH24:MI') AS starttime,
        TO_CHAR(rtg.enddate, 'YYYYMMDD') AS enddate, TO_CHAR(rtg.endtime, 'HH24:MI') AS endtime,
        COALESCE(EXTRACT(MINUTE FROM rtg.endtime - rtg.starttime), 0) AS operasi,
        COALESCE(EXTRACT(MINUTE FROM rtg.starttime - rtg.prevendtime), 0) AS tunggu,
        COALESCE(EXTRACT(MINUTE FROM rtg.endtime - rtg.starttime), 0) + COALESCE(EXTRACT(MINUTE FROM rtg.starttime - rtg.prevendtime), 0) AS leadtime,
        rtg.startshiftid, sh.shiftcode AS startshiftcode, sh.shiftname AS startshiftname, rtg.chgtopassdesc,
        pic.picnik, pic.picname, pic.picnik || ' - ' || pic.picname AS picstr,
        tpic.testpicnik, tpic.testpicname, tpic.testpicnik || ' - ' || tpic.testpicname AS testpicstr,
        mpic.matcherpicnik, mpic.matcherpicname, mpic.matcherpicnik || ' - ' || mpic.matcherpicname AS matcherpicstr,
        feel.hfeelcode, feel.hfeelname, TO_CHAR(so.sodlvrdate1, 'YYYYMMDD') AS sodlvrdate1, TO_CHAR(iso.isodate, 'YYYYMMDD') AS isodate,
        iso.orderqty, pm.permartaianm AS pemartaianqtym, meter.uomdecimal AS decimalm, py.permartaiany AS pemartaianqtyy,
        yard.uomdecimal AS decimaly, pcs.greigepcs, r.grade, r.resultpcs, r.resultstdqty, r.resultstduomcode, r.resultstduomdecimal,
        r.resultqty, r.resultuomcode, r.resultuomdecimal,
        CASE WHEN COALESCE(pm.permartaianm, 0) = 0 THEN 0 ELSE CAST((pm.permartaianm - r.resultqtym) / pm.permartaianm AS NUMERIC(19, 4)) * 100 END AS susut,
        TO_CHAR(rtg.upddate, 'YYYYMMDD HH24:MI:SS') AS upddate, rtg.upduser,
        ps.leftlistingmiring, ps.middlelistingmiring, ps.rightlistingmiring, grade.gradecode
    FROM TmpProdRtg rtg
    INNER JOIN pdproductionhd h ON h.productionhdid = rtg.productionhdid
    INNER JOIN pdproductiondt dt ON dt.productionhdid = h.productionhdid
    INNER JOIN pdworkcenter wc ON h.workcenterid = wc.workcenterid
    INNER JOIN pdwohd w ON w.wohdid = dt.wohdid
    INNER JOIN pdiso iso ON iso.isoid = h.isoid
    LEFT JOIN TmpProdMat mat ON mat.productionhdid = rtg.productionhdid
    LEFT JOIN TmpProdMatPcs pcs ON pcs.productionhdid = rtg.productionhdid
    LEFT JOIN TmpProdResult r ON r.productionhdid = rtg.productionhdid AND (rtg.fglastrtg = 'Y' OR rtg.fgprodresult = 'Y')
    LEFT JOIN pdproductionsum ps ON ps.productionrtgid = rtg.productionrtgid
    LEFT JOIN pdgradems grade ON grade.grademsid = ps.grademsid
    LEFT JOIN pdcolorms c ON c.colormsid = h.colorid
    LEFT JOIN insohd so ON so.sohdid = iso.sohdid
    LEFT JOIN smprodtechdata tech ON tech.prodid = h.prodid
    LEFT JOIN pdrtgproc proc ON proc.rtgprocid = tech.rtgprocid
    LEFT JOIN pdbomhd bom ON bom.bomhdid = h.bomhdid
    LEFT JOIN pdrtgms ms ON ms.rtgmsid = rtg.rtgmsid
    LEFT JOIN pdrtggrp grp ON grp.rtggrpid = ms.rtggrpid
    LEFT JOIN famaster fa ON fa.famasterid = rtg.famasterid
    LEFT JOIN pdrtgms prev ON prev.rtgmsid = rtg.prevrtgmsid
    LEFT JOIN pdrtgms next ON next.rtgmsid = rtg.nextrtgmsid
    LEFT JOIN pdfailms fail ON fail.failmsid = rtg.failmsid
    LEFT JOIN pdshiftms sh ON sh.shiftmsid = rtg.startshiftid
    LEFT JOIN pdhfeelms feel ON tech.hfeelmsid = feel.hfeelmsid
    LEFT JOIN TmpPermartaianM pm ON pm.productionhdid = rtg.productionhdid
    LEFT JOIN TmpPermartaianY py ON py.productionhdid = rtg.productionhdid
    LEFT JOIN TmpPIC pic ON pic.productionrtgid = rtg.productionrtgid AND pic.rn = 1
    LEFT JOIN TmpTestPIC tpic ON tpic.productionrtgid = rtg.productionrtgid AND tpic.rn = 1
    LEFT JOIN TmpMatcherPIC mpic ON mpic.productionrtgid = rtg.productionrtgid AND mpic.rn = 1
    LEFT JOIN TmpUOMKg kg ON 1 = 1
    LEFT JOIN TmpUOMMeter meter ON 1 = 1
    LEFT JOIN TmpUOMYard yard ON 1 = 1
    LEFT JOIN smdomainmap dm ON dm.domaintable = 'pdproductionrtg' AND dm.domainfield = 'fgresult' AND dm.domaincode = rtg.fgresult
    WHERE NOT EXISTS (
        SELECT 1 FROM pdproductionmat mrtl
        WHERE mrtl.productionhdid = rtg.productionhdid AND mrtl.compid = 2 AND mrtl.fgusedtype = 'A'
          AND COALESCE(mrtl.prodname, '') ILIKE '%RTL%'
    )
), DataFinalDistinct AS (
    SELECT DISTINCT ON (prdnmbr) * FROM DataUtama ORDER BY prdnmbr, enddate DESC, endtime DESC
)
SELECT ROW_NUMBER() OVER (ORDER BY prdnmbr) AS "No.", prdnmbr AS "Nomor Produksi", wonmbr AS "Nomor WO",
    workcenterstr AS "Work Center", prodstr AS "Produk", matname AS "Nama Material", colorcode AS "Kode Warna",
    colorname AS "Warna", cuscode AS "Kode Customer", cusname AS "Nama Customer", cuscolor AS "Cust Color",
    labeljual AS "Label Jual", rtgproccode AS "Proses Id", bomname AS "Kode BOM", prodcf AS "Gramasi Greige",
    greigekg AS "Greige (KG)", normalqty AS "Qty Normal", reproqty AS "Qty Repro", isodesc AS "Ket. ISO",
    rtggrpname AS "Routing Group", rtgseq AS "Seq", rtgname AS "Nama Routing", facode AS "Kode Mesin",
    faname AS "Nama Mesin", fgresultstr AS "Status", wheelno AS "No Roda", lebarkain AS "Lebar Kain",
    prdqty AS "Qty Produksi", startdate AS "Tanggal Mulai", starttime AS "Jam Mulai", enddate AS "Tanggal Selesai",
    endtime AS "Jam Selesai", operasi AS "Durasi Operasi (Min)", tunggu AS "Durasi Tunggu (Min)",
    leadtime AS "Total Lead Time", picstr AS "Nama PIC", orderqty AS "Qty Order", pemartaianqtym AS "Qty Permartaian(M)",
    pemartaianqtyy AS "Qty Pemartaian(Y)", grade AS "Grade Hasil", resultpcs AS "Qty Pcs", resultstdqty AS "Qty Standard",
    resultstduomcode AS "UOM Standard", resultqty AS "Qty Transaksi", resultuomcode AS "UOM Transaksi", susut AS "Persentase Susut (%)"
FROM DataFinalDistinct
ORDER BY prdnmbr
SQL;
            $rows = $conn3->query($finalSql)->fetchAll(PDO::FETCH_ASSOC);
            $conn3->commit();
        } catch (Throwable $e) {
            if ($conn3->inTransaction()) { $conn3->rollBack(); }
            $errors[] = $e->getMessage();
        }
    }

    $summary = [
        'total_pemartaian_m' => 0,
        'total_pemartaian_y' => 0,
        'total_qty_produksi' => 0,
        'total_rows' => count($rows),
    ];
    foreach ($rows as $row) {
        $summary['total_pemartaian_m'] += (float)($row['Qty Permartaian(M)'] ?? 0);
        $summary['total_pemartaian_y'] += (float)($row['Qty Pemartaian(Y)'] ?? 0);
        $summary['total_qty_produksi'] += (float)($row['Qty Produksi'] ?? 0);
    }
    $selectedRoutingIds = $routingIds;
    return compact('startDate', 'endDate', 'errors', 'rows', 'summary', 'maxDays', 'selectedRoutingIds');
}
