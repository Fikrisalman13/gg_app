<?php
session_start();
ob_start();

// Include database connections
require_once '../../koneksi.php';   // SQL Server
require_once '../../koneksi3.php';  // PostgreSQL (ERP_Crystal_SUM - $conn3)

// Include layout headers
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Auth check
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
date_default_timezone_set('Asia/Jakarta');

// Input filter parameter dengan nilai default
$startdate_input    = isset($_GET['startdate']) ? trim($_GET['startdate']) : '2026-08-01';
$enddate_input      = isset($_GET['enddate']) ? trim($_GET['enddate']) : '2026-08-31';
$rtgmsid_input      = isset($_GET['rtgmsid']) ? trim($_GET['rtgmsid']) : '589';
$workcenterid_input = isset($_GET['workcenterid']) ? trim($_GET['workcenterid']) : '111';

// Format validation
function isValidDate($date) {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}
if (!isValidDate($startdate_input)) $startdate_input = '2026-08-01';
if (!isValidDate($enddate_input))   $enddate_input   = '2026-08-31';

// Helper function: Tentukan "Kelompok" berdasarkan Status dan Fail Description
// Toleran terhadap typo dan variasi penulisan (fuzzy matching)
function getKelompok($status, $failDesc) {
    $statusStr = strtoupper(trim($status ?? ''));
    $desc = trim($failDesc ?? '');

    // Pass: status Pass tanpa fail description → "Pass"
    // Pass Upg: status Pass dengan fail description ada datanya → "Pass Upg"
    if (strpos($statusStr, 'PASS') !== false) {
        return empty($desc) ? 'Pass' : 'Pass Upg';
    }

    // Hanya proses Fail status untuk kelompok lainnya
    if (strpos($statusStr, 'FAIL') === false) return '';
    if (empty($desc)) return '';

    $upper = strtoupper($desc);

    // Ekstrak teks di dalam tanda kurung jika ada, contoh: LYFAIL(RF SHADING-RS) → RF SHADING-RS
    $searchText = $upper;
    if (preg_match('/\(([^)]+)\)/', $upper, $m)) {
        $searchText = trim($m[1]);
    }

    // 1. Repeat Shading: mengandung SHADING/SHD/SHADINGT + suffix RS
    if ((strpos($searchText, 'SHAD') !== false || strpos($searchText, 'SHD') !== false)
        && preg_match('/[\-\s]RS\b/', $searchText)) {
        return 'Repeat Shading';
    }

    // 2. Shading: mengandung SHADING/SHD/SHADINGT (tanpa RS)
    if (strpos($searchText, 'SHAD') !== false || strpos($searchText, 'SHD') !== false) {
        return 'Shading';
    }

    // 3. Soaping Ulang: mengandung SOAP* + UL*
    if (strpos($searchText, 'SOAP') !== false && strpos($searchText, 'UL') !== false) {
        return 'Soaping Ulang';
    }

    // 4. Test Larutan: mengandung TEST + L* (TEST LAN, TEST LARUTAN, dll)
    if (preg_match('/TEST[\s\-_.]*L/i', $searchText)) {
        return 'Test Larutan';
    }

    // 5. Top CPB: TOP/TOPING + CPB (cek sebelum Top Paddry)
    if (preg_match('/TOP\w*[\s\-_.]*CPB/i', $searchText)) {
        return 'Top CPB';
    }

    // 6. Top Paddry: TOP/TOPING + PADDRY/PADRY/PADDRI dll (toleran typo)
    if (preg_match('/TOP\w*[\s\-_.]+PAD/i', $searchText)) {
        return 'Top Paddry';
    }

    return '';
}

// Sanitize rtgmsid (numeric or comma-separated numbers)
$rtgmsidArray = array_filter(array_map('intval', explode(',', $rtgmsid_input)));
$rtgmsidStr = !empty($rtgmsidArray) ? implode(',', $rtgmsidArray) : '589';

$results = [];
$errorMsg = null;
$execution_time = 0;
$total_records = 0;

// Summary KPI variables
$sum_prdqty = 0;
$sum_greigekg = 0;
$sum_resultqty = 0;
$sum_durasi_operasi = 0;
$sum_normalqty = 0;
$sum_reproqty = 0;

// Flag apakah query dieksekusi (hanya saat tombol 'Proses Data' diklik)
$is_filter_active = isset($_GET['action']) && $_GET['action'] === 'filter';
$should_run_query = $is_filter_active; 

if ($should_run_query) {
    if (!$conn3) {
        $errorMsg = "Koneksi ke database PostgreSQL ERP (conn3) tidak tersedia.";
    } else {
        try {
            $t0 = microtime(true);

            // Quote parameters untuk keamanan SQL
            $qStartDate     = $conn3->quote($startdate_input);
            $qEndDate       = $conn3->quote($enddate_input);
            $qWorkcenterid  = $conn3->quote($workcenterid_input);

            // ========================================================================
            // 1. SETUP TEMPORARY UOM & FILTER TABLES (READ-ONLY SESSI-SCOPED TEMP TABLES)
            // ========================================================================
            $tempSql = "
            DROP TABLE IF EXISTS TmpUOMMeter;
            CREATE TEMP TABLE TmpUOMMeter AS 
            SELECT UOMId, UOMCode, UOMDecimal FROM SMUOM WHERE UOMCode IN ('m', 'M');
            CREATE UNIQUE INDEX idx_tmpuommeter_id ON TmpUOMMeter(UOMId);

            DROP TABLE IF EXISTS TmpUOMYard;
            CREATE TEMP TABLE TmpUOMYard AS 
            SELECT UOMId, UOMCode, UOMDecimal FROM SMUOM WHERE UOMCode IN ('yard', 'YARD', 'Yard');
            CREATE UNIQUE INDEX idx_tmpuomyard_id ON TmpUOMYard(UOMId);

            DROP TABLE IF EXISTS TmpUOMKg;
            CREATE TEMP TABLE TmpUOMKg AS 
            SELECT UOMId, UOMCode, UOMDecimal FROM SMUOM WHERE UOMCode IN ('kg', 'KG', 'Kg');
            CREATE UNIQUE INDEX idx_tmpuomkg_id ON TmpUOMKg(UOMId);

            DROP TABLE IF EXISTS TmpProdRtg;
            CREATE TEMP TABLE TmpProdRtg AS
            SELECT 
                r.isoid, r.productionhdid, r.productionrtgid, r.rtgseq, r.rtgmsid,
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
              AND r.enddate >= CAST($qStartDate AS DATE)
              AND r.enddate < (CAST($qEndDate AS DATE) + INTERVAL '1 day')
              AND r.rtgmsid IN ($rtgmsidStr)
              AND h.workcenterid = $qWorkcenterid;

            CREATE INDEX idx_tmpprodrtg_hd_seq ON TmpProdRtg(productionhdid, rtgseq);
            CREATE INDEX idx_tmpprodrtg_rtgid ON TmpProdRtg(productionrtgid);
            CREATE INDEX idx_tmpprodrtg_hdid ON TmpProdRtg(productionhdid);

            DROP TABLE IF EXISTS TempALLRtg;
            CREATE TEMP TABLE TempALLRtg AS
            SELECT 
                r.productionhdid, r.productionrtgid, h.prodid, r.endtime, r.rtgseq, r.rtgmsid,
                r.prdqty, r.prdstdqty, r.prduomid, r.prdstduomid
            FROM pdproductionrtg r
            INNER JOIN pdproductionhd h ON h.productionhdid = r.productionhdid
            WHERE r.compid = 2 
              AND r.productionhdid IN (SELECT productionhdid FROM TmpProdRtg);

            CREATE INDEX idx_tempallrtg_hd_seq ON TempALLRtg(productionhdid, rtgseq);

            UPDATE TmpProdRtg 
            SET prevrtgmsid = a.rtgmsid, prevendtime = a.endtime
            FROM TempALLRtg a 
            WHERE a.productionhdid = TmpProdRtg.productionhdid 
              AND a.rtgseq = TmpProdRtg.rtgseq - 1;

            UPDATE TmpProdRtg 
            SET nextrtgmsid = a.rtgmsid
            FROM TempALLRtg a 
            WHERE a.productionhdid = TmpProdRtg.productionhdid 
              AND a.rtgseq = TmpProdRtg.rtgseq + 1;

            DROP TABLE IF EXISTS TmpProdMatALL;
            CREATE TEMP TABLE TmpProdMatALL AS
            SELECT 
                m.productionhdid, m.matseq, m.prodid, m.prodcode, m.prodname,
                ROW_NUMBER() OVER (PARTITION BY m.productionhdid ORDER BY m.matseq) AS rn
            FROM pdproductionmat m
            WHERE m.compid = 2 
              AND m.fgusedtype = 'A' 
              AND COALESCE(m.prodname, '') NOT ILIKE '%RTL%'
              AND m.productionhdid IN (SELECT productionhdid FROM TempALLRtg WHERE rtgseq = 1);

            CREATE INDEX idx_tmpprodmatall_hd_rn ON TmpProdMatALL(productionhdid, rn);

            DROP TABLE IF EXISTS TmpProdMat;
            CREATE TEMP TABLE TmpProdMat AS 
            SELECT * FROM TmpProdMatALL WHERE rn = 1;
            CREATE UNIQUE INDEX idx_tmpprodmat_hd ON TmpProdMat(productionhdid);

            DROP TABLE IF EXISTS TmpProdMatPcs;
            CREATE TEMP TABLE TmpProdMatPcs AS 
            SELECT productionhdid, COUNT(1) AS greigepcs 
            FROM TmpProdMatALL 
            GROUP BY productionhdid;
            CREATE UNIQUE INDEX idx_tmpprodmatpcs_hd ON TmpProdMatPcs(productionhdid);

            DROP TABLE IF EXISTS TmpProdResult;
            CREATE TEMP TABLE TmpProdResult AS
            SELECT 
                d.resulthdid, h.productionhdid, d.prodid, d.prodcode, d.prodname, COUNT(1) AS resultpcs,
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

            UPDATE TmpProdResult 
            SET resultqtym = CASE
                WHEN TmpProdResult.resultuomid = m.uomid THEN TmpProdResult.resultqty
                WHEN TmpProdResult.resultstduomid = m.uomid THEN TmpProdResult.resultstdqty
                WHEN e.qtystd > 0 AND e.qtyconversion > 0 THEN ROUND((TmpProdResult.resultstdqty / e.qtystd) * e.qtyconversion, m.uomdecimal)
                ELSE 0 END
            FROM TmpUOMMeter m 
            LEFT JOIN smprodequivalent e ON e.uomequivalent = m.uomid
            WHERE e.prodid = TmpProdResult.prodid;

            UPDATE TmpProdResult 
            SET fgdefault = CASE WHEN TmpProdResult.prodid = h.prodid THEN 'Y' ELSE 'N' END
            FROM pdproductionhd h 
            WHERE TmpProdResult.productionhdid = h.productionhdid;

            DROP TABLE IF EXISTS TmpPermartaianM;
            CREATE TEMP TABLE TmpPermartaianM AS
            SELECT 
                t.productionhdid,
                CASE WHEN t.prduomid = m.uomid THEN t.prdqty
                     WHEN t.prdstduomid = m.uomid THEN t.prdstdqty
                     WHEN e.qtystd > 0 AND e.qtyconversion > 0 THEN ROUND((t.prdstdqty / e.qtystd) * e.qtyconversion, m.uomdecimal)
                     ELSE 0 END AS permartaianm
            FROM TempALLRtg t 
            CROSS JOIN TmpUOMMeter m
            LEFT JOIN smprodequivalent e ON e.prodid = t.prodid AND e.uomequivalent = m.uomid
            WHERE t.rtgseq = 1;
            CREATE UNIQUE INDEX idx_tmppermartaianm_hd ON TmpPermartaianM(productionhdid);

            DROP TABLE IF EXISTS TmpPermartaianY;
            CREATE TEMP TABLE TmpPermartaianY AS
            SELECT 
                t.productionhdid,
                CASE WHEN t.prduomid = y.uomid THEN t.prdqty
                     WHEN t.prdstduomid = y.uomid THEN t.prdstdqty
                     WHEN e.qtystd > 0 AND e.qtyconversion > 0 THEN ROUND((t.prdstdqty / e.qtystd) * e.qtyconversion, y.uomdecimal)
                     ELSE 0 END AS permartaiany
            FROM TempALLRtg t 
            CROSS JOIN TmpUOMYard y
            LEFT JOIN smprodequivalent e ON e.prodid = t.prodid AND e.uomequivalent = y.uomid
            WHERE t.rtgseq = 1;
            CREATE UNIQUE INDEX idx_tmppermartaiany_hd ON TmpPermartaianY(productionhdid);
            ";

            $conn3->exec($tempSql);

            // ========================================================================
            // 2. QUERY FINAL
            // ========================================================================
            $finalSql = <<<SQL
            WITH TmpPIC AS (
                SELECT ROW_NUMBER() OVER (PARTITION BY p.productionrtgid) AS rn, p.productionrtgid, e.empnik AS picnik, e.empname AS picname
                FROM pdresultpic p 
                INNER JOIN smemployee e ON p.empid = e.empid
                WHERE p.productionrtgid IN (SELECT productionrtgid FROM TmpProdRtg) AND p.compid = 2 AND p.fgpictype IS NULL
            ), TmpTestPIC AS (
                SELECT ROW_NUMBER() OVER (PARTITION BY p.productionrtgid) AS rn, p.productionrtgid, e.empnik AS testpicnik, e.empname AS testpicname
                FROM pdresultpic p 
                INNER JOIN smemployee e ON p.empid = e.empid
                WHERE p.productionrtgid IN (SELECT productionrtgid FROM TmpProdRtg) AND p.compid = 2 AND p.fgpictype = 'T'
            ), TmpMatcherPIC AS (
                SELECT ROW_NUMBER() OVER (PARTITION BY p.productionrtgid) AS rn, p.productionrtgid, e.empnik AS matcherpicnik, e.empname AS matcherpicname
                FROM pdresultpic p 
                INNER JOIN smemployee e ON p.empid = e.empid
                WHERE p.productionrtgid IN (SELECT productionrtgid FROM TmpProdRtg) AND p.compid = 2 AND p.fgpictype = 'M'
            ), DataUtama AS (
                SELECT 
                    w.wohdid, w.wonmbr, h.productionhdid, h.prdnmbr, h.workcenterid,
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
            SELECT 
                ROW_NUMBER() OVER (ORDER BY prdnmbr) AS "no_urut", 
                prdnmbr AS "nomor_produksi", 
                wonmbr AS "nomor_wo",
                workcenterstr AS "work_center", 
                prodstr AS "produk", 
                matname AS "nama_material", 
                colorcode AS "kode_warna",
                colorname AS "warna", 
                cuscode AS "kode_customer", 
                cusname AS "nama_customer", 
                cuscolor AS "cust_color",
                labeljual AS "label_jual", 
                rtgproccode AS "proses_id", 
                bomname AS "kode_bom", 
                prodcf AS "gramasi_greige",
                greigekg AS "greige_kg", 
                normalqty AS "qty_normal", 
                reproqty AS "qty_repro", 
                isodesc AS "ket_iso",
                rtggrpname AS "routing_group", 
                rtgseq AS "seq", 
                rtgname AS "nama_routing", 
                facode AS "kode_mesin",
                faname AS "nama_mesin", 
                fgresultstr AS "status", 
                failstr AS "jenis_fail",
                resultdesc AS "fail_desc",
                wheelno AS "no_roda", 
                lebarkain AS "lebar_kain",
                prdqty AS "qty_produksi", 
                startdate AS "tanggal_mulai", 
                starttime AS "jam_mulai", 
                enddate AS "tanggal_selesai",
                endtime AS "jam_selesai", 
                operasi AS "durasi_operasi", 
                tunggu AS "durasi_tunggu",
                leadtime AS "total_lead_time", 
                picstr AS "nama_pic", 
                orderqty AS "qty_order", 
                pemartaianqtym AS "qty_permartaian_m",
                pemartaianqtyy AS "qty_pemartaian_y", 
                grade AS "grade_hasil", 
                resultpcs AS "qty_pcs", 
                resultstdqty AS "qty_standard",
                resultstduomcode AS "uom_standard", 
                resultqty AS "qty_transaksi", 
                resultuomcode AS "uom_transaksi", 
                susut AS "persentase_susut"
            FROM DataFinalDistinct
            ORDER BY prdnmbr;
            SQL;

            $stmtFinal = $conn3->query($finalSql);
            $results = $stmtFinal->fetchAll(PDO::FETCH_ASSOC);
            $t1 = microtime(true);
            $execution_time = round(($t1 - $t0), 2);
            $total_records = count($results);

            // Compute summary metrics
            foreach ($results as $row) {
                $sum_prdqty        += (float)($row['qty_produksi'] ?? 0);
                $sum_greigekg      += (float)($row['greige_kg'] ?? 0);
                $sum_resultqty     += (float)($row['qty_transaksi'] ?? 0);
                $sum_durasi_operasi += (float)($row['durasi_operasi'] ?? 0);
                $sum_normalqty     += (float)($row['qty_normal'] ?? 0);
                $sum_reproqty      += (float)($row['qty_repro'] ?? 0);
            }
        } catch (PDOException $e) {
            $errorMsg = "Gagal mengambil data dari database: " . $e->getMessage();
        }
    }
}
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

<style>
    .card-header-gradient {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: #fff;
    }
    .kpi-card {
        border-radius: 10px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    .kpi-icon {
        font-size: 2.2rem;
        opacity: 0.85;
    }
    .table-container {
        position: relative;
        background: #fff;
        border-radius: 8px;
    }
    #analisaTable th {
        white-space: nowrap;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background-color: #f1f4f9;
        color: #334155;
        vertical-align: middle;
        border-bottom: 2px solid #cbd5e1;
    }
    #analisaTable td {
        font-size: 0.83rem;
        white-space: nowrap;
        vertical-align: middle;
    }
    .badge-pass {
        background-color: #d1fae5;
        color: #065f46;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
    }
    .badge-fail {
        background-color: #fee2e2;
        color: #991b1b;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
    }
    .badge-kelompok {
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.78rem;
        white-space: nowrap;
    }
    .badge-repeat-shading { background-color: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .badge-shading { background-color: #fefce8; color: #854d0e; border: 1px solid #fef08a; }
    .badge-soaping-ulang { background-color: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .badge-test-larutan { background-color: #f0fdfa; color: #115e59; border: 1px solid #99f6e4; }
    .badge-top-cpb { background-color: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff; }
    .badge-top-paddry { background-color: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; }
    .badge-pass { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .badge-pass-upg { background-color: #dbeafe; color: #1e3a8a; border: 1px solid #93c5fd; }
    .badge-info-custom {
        background-color: #e0f2fe;
        color: #0369a1;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
    }
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.7);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
</style>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="spinner-border text-primary" style="width: 3.5rem; height: 3.5rem;" role="status">
        <span class="sr-only">Memuat...</span>
    </div>
    <h5 class="mt-3 text-dark font-weight-bold">Memproses Data Analisa Warna...</h5>
    <p class="text-muted">Mohon tunggu beberapa saat...</p>
</div>

<div class="content-wrapper">
    <!-- Header Page -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold">
                        <i class="fas fa-palette text-primary mr-2"></i>Analisa Warna & Produksi
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/">Home</a></li>
                        <li class="breadcrumb-item active">Analisa Warna</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Card Filter -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter mr-1"></i> Filter Parameter Data
                    </h5>
                    <div class="card-tools">
                        <span class="badge badge-light border text-muted px-2 py-1">
                            <i class="fas fa-database text-success mr-1"></i> Mode Read-Only
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        <input type="hidden" name="action" value="filter">
                        <div class="row align-items-end">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold small text-secondary">
                                    <i class="far fa-calendar-alt text-primary mr-1"></i> Tanggal Awal Selesai:
                                </label>
                                <input type="date" class="form-control" name="startdate" value="<?= htmlspecialchars($startdate_input) ?>" required>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold small text-secondary">
                                    <i class="far fa-calendar-alt text-primary mr-1"></i> Tanggal Akhir Selesai:
                                </label>
                                <input type="date" class="form-control" name="enddate" value="<?= htmlspecialchars($enddate_input) ?>" required>
                            </div>
                            <div class="col-md-2 col-sm-6 mb-3">
                                <label class="font-weight-bold small text-secondary">
                                    <i class="fas fa-code-branch text-primary mr-1"></i> Routing ID:
                                </label>
                                <input type="text" class="form-control" name="rtgmsid" value="<?= htmlspecialchars($rtgmsid_input) ?>" placeholder="Contoh: 589" required>
                            </div>
                            <div class="col-md-2 col-sm-6 mb-3">
                                <label class="font-weight-bold small text-secondary">
                                    <i class="fas fa-industry text-primary mr-1"></i> Work Center:
                                </label>
                                <input type="text" class="form-control" name="workcenterid" value="<?= htmlspecialchars($workcenterid_input) ?>" placeholder="Contoh: 111" required>
                            </div>
                            <div class="col-md-2 col-sm-12 mb-3">
                                <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm">
                                    <i class="fas fa-play mr-1"></i> Proses Data
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <h5><i class="icon fas fa-ban"></i> Terjadi Kesalahan!</h5>
                    <?= htmlspecialchars($errorMsg) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($is_filter_active): ?>
                <!-- KPI Metric Summary -->
                <div class="row">
                    <div class="col-lg-3 col-6 mb-3">
                        <div class="small-box bg-gradient-info kpi-card shadow-sm">
                            <div class="inner">
                                <h3><?= number_format($total_records, 0, ',', '.') ?></h3>
                                <p class="font-weight-bold">Total Batch / Data Produksi</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6 mb-3">
                        <div class="small-box bg-gradient-success kpi-card shadow-sm">
                            <div class="inner">
                                <h3><?= number_format($sum_normalqty, 2, ',', '.') ?></h3>
                                <p class="font-weight-bold">Total Qty Normal</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6 mb-3">
                        <div class="small-box bg-gradient-warning text-white kpi-card shadow-sm">
                            <div class="inner">
                                <h3><?= number_format($sum_reproqty, 2, ',', '.') ?></h3>
                                <p class="font-weight-bold text-white">Total Qty Repro</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-redo-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6 mb-3">
                        <div class="small-box bg-gradient-purple kpi-card shadow-sm" style="background: linear-gradient(135deg, #6b21a8 0%, #9333ea 100%); color:#fff;">
                            <div class="inner">
                                <h3><?= number_format($sum_prdqty, 2, ',', '.') ?></h3>
                                <p class="font-weight-bold">Total Qty Produksi</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-dolly-flatbed"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Data Table -->
                <div class="card card-outline card-secondary shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <div>
                            <h5 class="card-title font-weight-bold text-dark m-0">
                                <i class="fas fa-table mr-1 text-primary"></i> Data Hasil Analisa Warna & Produksi
                            </h5>
                        </div>
                        <div>
                            <span class="badge badge-light border text-muted">
                                <i class="fas fa-clock mr-1"></i> Waktu Query: <?= $execution_time ?> detik
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="analisaTable" class="table table-bordered table-hover table-striped" style="width:100%">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;" class="text-center">No</th>
                                        <th>Nomor Produksi</th>
                                        <th>Nama Material</th>
                                        <th>Kode Warna</th>
                                        <th>Warna</th>
                                        <th>Cust Color</th>
                                        <th>Label Jual</th>
                                        <th class="text-right">Qty Normal</th>
                                        <th class="text-right">Qty Repro</th>
                                        <th>Nama Routing</th>
                                        <th class="text-center">Status</th>
                                        <th>Fail Description</th>
                                        <th>Kelompok</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($results)): ?>
                                        <?php $no = 1; foreach ($results as $row): ?>
                                            <tr>
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td class="font-weight-bold text-primary"><?= htmlspecialchars($row['nomor_produksi'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($row['nama_material'] ?? '-') ?></td>
                                                <td><span class="badge badge-light border"><?= htmlspecialchars($row['kode_warna'] ?? '-') ?></span></td>
                                                <td class="font-weight-bold"><?= htmlspecialchars($row['warna'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($row['cust_color'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($row['label_jual'] ?? '-') ?></td>
                                                <td class="text-right"><?= isset($row['qty_normal']) ? number_format((float)$row['qty_normal'], 2, ',', '.') : '0,00' ?></td>
                                                <td class="text-right"><?= isset($row['qty_repro']) ? number_format((float)$row['qty_repro'], 2, ',', '.') : '0,00' ?></td>
                                                <td><?= htmlspecialchars($row['nama_routing'] ?? '-') ?></td>
                                                <td class="text-center">
                                                    <?php 
                                                        $st = trim($row['status'] ?? '');
                                                        if (stripos($st, 'Pass') !== false) {
                                                            echo '<span class="badge badge-pass"><i class="fas fa-check-circle mr-1"></i>' . htmlspecialchars($st) . '</span>';
                                                        } elseif (stripos($st, 'Fail') !== false) {
                                                            echo '<span class="badge badge-fail"><i class="fas fa-times-circle mr-1"></i>' . htmlspecialchars($st) . '</span>';
                                                        } else {
                                                            echo '<span class="badge badge-info-custom">' . htmlspecialchars($st ?: '-') . '</span>';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['fail_desc'])): ?>
                                                        <span class="font-weight-bold text-danger"><?= htmlspecialchars($row['fail_desc']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $kelompok = getKelompok($row['status'] ?? '', $row['fail_desc'] ?? '');
                                                        if (!empty($kelompok)) {
                                                            $cssClass = 'badge-kelompok ';
                                                            switch ($kelompok) {
                                                                case 'Repeat Shading': $cssClass .= 'badge-repeat-shading'; break;
                                                                case 'Shading':        $cssClass .= 'badge-shading'; break;
                                                                case 'Soaping Ulang':  $cssClass .= 'badge-soaping-ulang'; break;
                                                                case 'Test Larutan':   $cssClass .= 'badge-test-larutan'; break;
                                                                case 'Top CPB':        $cssClass .= 'badge-top-cpb'; break;
                                                                case 'Top Paddry':     $cssClass .= 'badge-top-paddry'; break;
                                                                case 'Pass':           $cssClass .= 'badge-pass'; break;
                                                                case 'Pass Upg':       $cssClass .= 'badge-pass-upg'; break;
                                                                default:               $cssClass .= 'badge-light border'; break;
                                                            }
                                                            echo '<span class="' . $cssClass . '">' . htmlspecialchars($kelompok) . '</span>';
                                                        } else {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="13" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3 text-secondary d-block"></i>
                                                Tidak ada data ditemukan untuk kriteria filter yang dipilih.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Call to Action Card saat menu baru dibuka -->
                <div class="card card-outline card-secondary shadow-sm mb-4">
                    <div class="card-body text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-calendar-alt text-primary" style="font-size: 3.8rem; opacity: 0.85;"></i>
                        </div>
                        <h4 class="font-weight-bold text-dark mb-2">Filter Data Analisa Warna</h4>
                        <p class="text-muted mx-auto mb-3" style="max-width: 580px; font-size: 0.95rem;">
                            Silakan tentukan rentang tanggal selesai, Routing ID, dan Work Center pada form filter di atas, kemudian tekan tombol <strong class="text-primary"><i class="fas fa-play mx-1"></i>Proses Data</strong> untuk menampilkan hasil data secara spesifik.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables & Plugins JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jszip/jszip.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function() {
    $('#filterForm').on('submit', function() {
        $('#loadingOverlay').css('display', 'flex');
    });

    if ($('#analisaTable').length) {
        var table = $('#analisaTable').DataTable({
            "responsive": false,
            "scrollX": true,
            "lengthChange": true,
            "autoWidth": false,
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            "dom": "<'row mb-2'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "buttons": [
                {
                    extend: 'excelHtml5',
                    text: '<i class="far fa-file-excel mr-1"></i> Excel',
                    className: 'btn btn-success btn-sm font-weight-bold',
                    title: 'Data_Analisa_Warna_' + '<?= $startdate_input ?>_sd_<?= $enddate_input ?>'
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv mr-1"></i> CSV',
                    className: 'btn btn-info btn-sm font-weight-bold',
                    title: 'Data_Analisa_Warna_' + '<?= $startdate_input ?>_sd_<?= $enddate_input ?>'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print mr-1"></i> Cetak',
                    className: 'btn btn-secondary btn-sm font-weight-bold'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns mr-1"></i> Kolom',
                    className: 'btn btn-outline-dark btn-sm'
                }
            ],
            "language": {
                "processing": "Memproses...",
                "lengthMenu": "Tampilkan _MENU_ baris",
                "zeroRecords": "Tidak ada data yang cocok ditemukan",
                "info": "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                "infoEmpty": "Menampilkan 0 data",
                "infoFiltered": "(disaring dari _MAX_ total data)",
                "search": "Cari Cepat:",
                "paginate": {
                    "first": "Awal",
                    "last": "Akhir",
                    "next": "Berikutnya",
                    "previous": "Sebelumnya"
                }
            }
        });
    }
});
</script>
