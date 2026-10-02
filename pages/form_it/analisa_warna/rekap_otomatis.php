<?php
session_start();
ob_start();

// Include database connections
require_once '../../koneksi.php';   // SQL Server (Database GG)
require_once '../../koneksi3.php';  // PostgreSQL (ERP Crystal SUM)

// Layout includes
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

// ============================================================================
// 1. AUTO-SETUP DATABASE TABLE IN SQL SERVER (IF NOT EXISTS)
// ============================================================================
$createTableSql = "
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'analisa_warna_periode')
BEGIN
    CREATE TABLE analisa_warna_periode (
        id INT IDENTITY(1,1) PRIMARY KEY,
        nama_periode NVARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        rtgmsid NVARCHAR(50) DEFAULT '589',
        workcenterid NVARCHAR(10) DEFAULT '111',
        total_cp INT DEFAULT 0,
        cp_pass INT DEFAULT 0,
        cp_pass_upg INT DEFAULT 0,
        cp_total_pass INT DEFAULT 0,
        cp_fail INT DEFAULT 0,
        qty_pass DECIMAL(19,2) DEFAULT 0,
        qty_pass_upg DECIMAL(19,2) DEFAULT 0,
        qty_total_pass DECIMAL(19,2) DEFAULT 0,
        qty_fail DECIMAL(19,2) DEFAULT 0,
        pct_pass DECIMAL(5,2) DEFAULT 0,
        pct_fail DECIMAL(5,2) DEFAULT 0,
        qty_repeat_shading DECIMAL(19,2) DEFAULT 0,
        qty_shading DECIMAL(19,2) DEFAULT 0,
        qty_soaping_ulang DECIMAL(19,2) DEFAULT 0,
        qty_top_cpb DECIMAL(19,2) DEFAULT 0,
        qty_top_paddry DECIMAL(19,2) DEFAULT 0,
        qty_test_larutan DECIMAL(19,2) DEFAULT 0,
        created_at DATETIME DEFAULT GETDATE(),
        created_by NVARCHAR(50),
        updated_at DATETIME DEFAULT GETDATE()
    );
END
";
if ($conn) {
    @sqlsrv_query($conn, $createTableSql);
}

// ============================================================================
// 2. HELPER FUNCTIONS
// ============================================================================

/**
 * Format nama periode otomatis ke bahasa Indonesia, contoh: "05 - 11 Agustus 2026"
 */
function generatePeriodeName($start, $end) {
    $bln = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $ts1 = strtotime($start);
    $ts2 = strtotime($end);
    if (!$ts1 || !$ts2) return "$start - $end";

    $d1 = date('d', $ts1);
    $m1 = (int)date('m', $ts1);
    $y1 = date('Y', $ts1);

    $d2 = date('d', $ts2);
    $m2 = (int)date('m', $ts2);
    $y2 = date('Y', $ts2);

    if ($m1 === $m2 && $y1 === $y2) {
        return "$d1 - $d2 {$bln[$m1]} $y1";
    } elseif ($y1 === $y2) {
        return "$d1 {$bln[$m1]} - $d2 {$bln[$m2]} $y1";
    } else {
        return "$d1 {$bln[$m1]} $y1 - $d2 {$bln[$m2]} $y2";
    }
}

/**
 * Tentukan Kelompok berdasarkan Status dan Fail Description
 */
function getKelompok($status, $failDesc) {
    $statusStr = strtoupper(trim($status ?? ''));
    $desc = trim($failDesc ?? '');

    if (strpos($statusStr, 'PASS') !== false) {
        return empty($desc) ? 'Pass' : 'Pass Upg';
    }

    if (strpos($statusStr, 'FAIL') === false) return '';
    if (empty($desc)) return '';

    $upper = strtoupper($desc);
    $searchText = $upper;
    if (preg_match('/\(([^)]+)\)/', $upper, $m)) {
        $searchText = trim($m[1]);
    }

    if ((strpos($searchText, 'SHAD') !== false || strpos($searchText, 'SHD') !== false)
        && preg_match('/[\-\s]RS\b/', $searchText)) {
        return 'Repeat Shading';
    }

    if (strpos($searchText, 'SHAD') !== false || strpos($searchText, 'SHD') !== false) {
        return 'Shading';
    }

    if (strpos($searchText, 'SOAP') !== false && strpos($searchText, 'UL') !== false) {
        return 'Soaping Ulang';
    }

    if (preg_match('/TEST[\s\-_.]*L/i', $searchText)) {
        return 'Test Larutan';
    }

    if (preg_match('/TOP\w*[\s\-_.]*CPB/i', $searchText)) {
        return 'Top CPB';
    }

    if (preg_match('/TOP\w*[\s\-_.]+PAD/i', $searchText)) {
        return 'Top Paddry';
    }

    return '';
}

/**
 * Tarik data dari PostgreSQL (ERP) sesuai periode dan hitung agregasi lengkap
 */
function calculatePeriodeMetrics($conn3, $startDate, $endDate, $rtgmsid = '589', $workcenterid = '111') {
    if (!$conn3) {
        throw new Exception("Koneksi ke PostgreSQL ERP (conn3) tidak tersedia.");
    }

    $qStartDate     = $conn3->quote($startDate);
    $qEndDate       = $conn3->quote($endDate);
    $qWorkcenterid  = $conn3->quote($workcenterid);

    $rtgmsidArray = array_filter(array_map('intval', explode(',', $rtgmsid)));
    $rtgmsidStr = !empty($rtgmsidArray) ? implode(',', $rtgmsidArray) : '589';

    // Buat tabel temporary per sesi
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
    ";

    $conn3->exec($tempSql);

    $finalSql = "
    WITH DataUtama AS (
        SELECT 
            h.prdnmbr,
            rtg.fgresult,
            dm.domaindesc AS fgresultstr,
            rtg.resultdesc,
            rtg.prdqty,
            CASE WHEN COALESCE(rtg.fgrework, 'N') = 'N' THEN rtg.prdqty ELSE 0 END AS normalqty,
            CASE WHEN rtg.fgrework = 'Y' AND rtg.fgresult = 'F' THEN rtg.prdqty ELSE 0 END AS reproqty,
            rtg.enddate,
            rtg.endtime
        FROM TmpProdRtg rtg
        INNER JOIN pdproductionhd h ON h.productionhdid = rtg.productionhdid AND h.compid = 2 AND h.workcenterid = $qWorkcenterid
        LEFT JOIN smdomainmap dm ON dm.domaintable = 'pdproductionrtg' AND dm.domainfield = 'fgresult' AND dm.domaincode = rtg.fgresult
        WHERE NOT EXISTS (
            SELECT 1 FROM pdproductionmat mrtl
            WHERE mrtl.productionhdid = rtg.productionhdid AND mrtl.compid = 2 AND mrtl.fgusedtype = 'A'
              AND COALESCE(mrtl.prodname, '') ILIKE '%RTL%'
        )
    ), DataFinalDistinct AS (
        SELECT DISTINCT ON (prdnmbr) * FROM DataUtama ORDER BY prdnmbr, enddate DESC, endtime DESC
    )
    SELECT * FROM DataFinalDistinct;
    ";

    $stmt = $conn3->query($finalSql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_cp = count($rows);
    $cp_pass = 0;
    $cp_pass_upg = 0;
    $cp_fail = 0;
    $qty_pass = 0.0;
    $qty_pass_upg = 0.0;
    $qty_fail = 0.0;

    $qty_repeat_shading = 0.0;
    $qty_shading        = 0.0;
    $qty_soaping_ulang  = 0.0;
    $qty_top_cpb        = 0.0;
    $qty_top_paddry     = 0.0;
    $qty_test_larutan   = 0.0;

    foreach ($rows as $r) {
        $st = strtoupper(trim($r['fgresultstr'] ?? ''));
        $desc = trim($r['resultdesc'] ?? '');
        $qtyNormal = (float)($r['normalqty'] ?? 0);
        $qtyRepro  = (float)($r['reproqty'] ?? 0);
        $qtyPrd    = (float)($r['prdqty'] ?? 0);
        $qty = ($qtyNormal > 0 ? $qtyNormal : ($qtyRepro > 0 ? $qtyRepro : $qtyPrd));

        $kelompok = getKelompok($r['fgresultstr'] ?? '', $r['resultdesc'] ?? '');

        if (strpos($st, 'PASS') !== false) {
            if (empty($desc)) {
                $cp_pass++;
                $qty_pass += $qty;
            } else {
                $cp_pass_upg++;
                $qty_pass_upg += $qty;
            }
        } elseif (strpos($st, 'FAIL') !== false) {
            $cp_fail++;
            $qty_fail += $qty;
        } else {
            $cp_fail++;
            $qty_fail += $qty;
        }

        switch ($kelompok) {
            case 'Repeat Shading':
                $qty_repeat_shading += $qty;
                break;
            case 'Shading':
                $qty_shading += $qty;
                break;
            case 'Soaping Ulang':
                $qty_soaping_ulang += $qty;
                break;
            case 'Top CPB':
                $qty_top_cpb += $qty;
                break;
            case 'Top Paddry':
                $qty_top_paddry += $qty;
                break;
            case 'Test Larutan':
                $qty_test_larutan += $qty;
                break;
        }
    }

    $cp_total_pass = $cp_pass + $cp_pass_upg;
    $qty_total_pass = $qty_pass + $qty_pass_upg;
    
    $pct_pass = $total_cp > 0 ? round(($cp_total_pass / $total_cp) * 100, 2) : 0;
    $pct_fail = $total_cp > 0 ? round(($cp_fail / $total_cp) * 100, 2) : 0;

    return [
        'total_cp'           => $total_cp,
        'cp_pass'            => $cp_pass,
        'cp_pass_upg'        => $cp_pass_upg,
        'cp_total_pass'      => $cp_total_pass,
        'cp_fail'            => $cp_fail,
        'qty_pass'           => $qty_pass,
        'qty_pass_upg'       => $qty_pass_upg,
        'qty_total_pass'     => $qty_total_pass,
        'qty_fail'           => $qty_fail,
        'pct_pass'           => $pct_pass,
        'pct_fail'           => $pct_fail,
        'qty_repeat_shading' => $qty_repeat_shading,
        'qty_shading'        => $qty_shading,
        'qty_soaping_ulang'  => $qty_soaping_ulang,
        'qty_top_cpb'        => $qty_top_cpb,
        'qty_top_paddry'     => $qty_top_paddry,
        'qty_test_larutan'   => $qty_test_larutan
    ];
}

// ============================================================================
// 3. ACTION HANDLERS (POST)
// ============================================================================
$alertSuccess = null;
$alertError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH / BUAT PERIODE BARU & TARIK DATA
    if ($action === 'tambah_periode') {
        $startDate    = trim($_POST['start_date'] ?? '');
        $endDate      = trim($_POST['end_date'] ?? '');
        $namaPeriode  = trim($_POST['nama_periode'] ?? '');
        $rtgmsid      = trim($_POST['rtgmsid'] ?? '589');
        $workcenterid = trim($_POST['workcenterid'] ?? '111');

        if (empty($startDate) || empty($endDate)) {
            $alertError = "Tanggal awal dan tanggal akhir harus diisi!";
        } else {
            if (empty($namaPeriode)) {
                $namaPeriode = generatePeriodeName($startDate, $endDate);
            }

            try {
                // Hitung agregasi dari PostgreSQL
                $m = calculatePeriodeMetrics($conn3, $startDate, $endDate, $rtgmsid, $workcenterid);

                // Simpan ke SQL Server (Database GG)
                $insertSql = "
                INSERT INTO analisa_warna_periode (
                    nama_periode, start_date, end_date, rtgmsid, workcenterid,
                    total_cp, cp_pass, cp_pass_upg, cp_total_pass, cp_fail,
                    qty_pass, qty_pass_upg, qty_total_pass, qty_fail,
                    pct_pass, pct_fail,
                    qty_repeat_shading, qty_shading, qty_soaping_ulang,
                    qty_top_cpb, qty_top_paddry, qty_test_larutan,
                    created_by, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

                $params = [
                    $namaPeriode, $startDate, $endDate, $rtgmsid, $workcenterid,
                    $m['total_cp'], $m['cp_pass'], $m['cp_pass_upg'], $m['cp_total_pass'], $m['cp_fail'],
                    $m['qty_pass'], $m['qty_pass_upg'], $m['qty_total_pass'], $m['qty_fail'],
                    $m['pct_pass'], $m['pct_fail'],
                    $m['qty_repeat_shading'], $m['qty_shading'], $m['qty_soaping_ulang'],
                    $m['qty_top_cpb'], $m['qty_top_paddry'], $m['qty_test_larutan'],
                    $_SESSION['UserName'] ?? 'System'
                ];

                $stmt = sqlsrv_query($conn, $insertSql, $params);
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $alertError = "Gagal menyimpan ke database GG: " . ($errors[0]['message'] ?? 'Unknown error');
                } else {
                    $alertSuccess = "Periode '<strong>" . htmlspecialchars($namaPeriode) . "</strong>' berhasil dibuat dan data ({$m['total_cp']} CP) berhasil disimpan!";
                    sqlsrv_free_stmt($stmt);
                }
            } catch (Exception $e) {
                $alertError = "Terjadi kesalahan saat memproses data: " . $e->getMessage();
            }
        }
    }

    // TARIK ULANG / REFRESH DATA PERIODE YANG SUDAH ADA
    if ($action === 'refresh_periode') {
        $periodeId = (int)($_POST['periode_id'] ?? 0);
        if ($periodeId > 0) {
            $selectSql = "SELECT * FROM analisa_warna_periode WHERE id = ?";
            $stmt = sqlsrv_query($conn, $selectSql, [$periodeId]);
            if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                $startDate = ($row['start_date'] instanceof DateTime) ? $row['start_date']->format('Y-m-d') : (string)$row['start_date'];
                $endDate   = ($row['end_date'] instanceof DateTime) ? $row['end_date']->format('Y-m-d') : (string)$row['end_date'];
                $rtgmsid   = $row['rtgmsid'] ?? '589';
                $workcenterid = $row['workcenterid'] ?? '111';
                sqlsrv_free_stmt($stmt);

                try {
                    $m = calculatePeriodeMetrics($conn3, $startDate, $endDate, $rtgmsid, $workcenterid);

                    $updateSql = "
                    UPDATE analisa_warna_periode SET
                        total_cp = ?, cp_pass = ?, cp_pass_upg = ?, cp_total_pass = ?, cp_fail = ?,
                        qty_pass = ?, qty_pass_upg = ?, qty_total_pass = ?, qty_fail = ?,
                        pct_pass = ?, pct_fail = ?,
                        qty_repeat_shading = ?, qty_shading = ?, qty_soaping_ulang = ?,
                        qty_top_cpb = ?, qty_top_paddry = ?, qty_test_larutan = ?,
                        updated_at = GETDATE()
                    WHERE id = ?";

                    $uParams = [
                        $m['total_cp'], $m['cp_pass'], $m['cp_pass_upg'], $m['cp_total_pass'], $m['cp_fail'],
                        $m['qty_pass'], $m['qty_pass_upg'], $m['qty_total_pass'], $m['qty_fail'],
                        $m['pct_pass'], $m['pct_fail'],
                        $m['qty_repeat_shading'], $m['qty_shading'], $m['qty_soaping_ulang'],
                        $m['qty_top_cpb'], $m['qty_top_paddry'], $m['qty_test_larutan'],
                        $periodeId
                    ];

                    $uStmt = sqlsrv_query($conn, $updateSql, $uParams);
                    if ($uStmt) {
                        $alertSuccess = "Data periode '<strong>" . htmlspecialchars($row['nama_periode']) . "</strong>' berhasil diperbarui ({$m['total_cp']} CP)!";
                        sqlsrv_free_stmt($uStmt);
                    } else {
                        $alertError = "Gagal memperbarui periode di database.";
                    }
                } catch (Exception $e) {
                    $alertError = "Kesalahan saat refresh data: " . $e->getMessage();
                }
            }
        }
    }

    // HAPUS PERIODE
    if ($action === 'hapus_periode') {
        $periodeId = (int)($_POST['periode_id'] ?? 0);
        if ($periodeId > 0) {
            $delSql = "DELETE FROM analisa_warna_periode WHERE id = ?";
            $delStmt = sqlsrv_query($conn, $delSql, [$periodeId]);
            if ($delStmt) {
                $alertSuccess = "Periode berhasil dihapus dari database.";
                sqlsrv_free_stmt($delStmt);
            } else {
                $alertError = "Gagal menghapus periode.";
            }
        }
    }
}

// ============================================================================
// 4. FETCH SEMUA PERIODE DARI SQL SERVER
// ============================================================================
$listPeriode = [];
$totalAllCp = 0;
$avgPassPct = 0;
$avgFailPct = 0;

if ($conn) {
    $fetchSql = "SELECT * FROM analisa_warna_periode ORDER BY start_date ASC, id ASC";
    $fetchStmt = sqlsrv_query($conn, $fetchSql);
    if ($fetchStmt) {
        while ($p = sqlsrv_fetch_array($fetchStmt, SQLSRV_FETCH_ASSOC)) {
            if ($p['start_date'] instanceof DateTime) {
                $p['start_date_str'] = $p['start_date']->format('d/m/Y');
            } else {
                $p['start_date_str'] = (string)$p['start_date'];
            }
            if ($p['end_date'] instanceof DateTime) {
                $p['end_date_str'] = $p['end_date']->format('d/m/Y');
            } else {
                $p['end_date_str'] = (string)$p['end_date'];
            }
            $listPeriode[] = $p;
            $totalAllCp += (int)($p['total_cp'] ?? 0);
        }
        sqlsrv_free_stmt($fetchStmt);
    }
}

$countPeriode = count($listPeriode);
if ($countPeriode > 0) {
    $sumPassPct = array_sum(array_column($listPeriode, 'pct_pass'));
    $sumFailPct = array_sum(array_column($listPeriode, 'pct_fail'));
    $avgPassPct = round($sumPassPct / $countPeriode, 1);
    $avgFailPct = round($sumFailPct / $countPeriode, 1);
}

// Data JSON untuk Chart preview jika dibuka
$chartLabels = [];
$chartPassData = [];
$chartFailData = [];

foreach ($listPeriode as $p) {
    $chartLabels[] = $p['nama_periode'];
    $chartPassData[] = (float)round($p['pct_pass']);
    $chartFailData[] = (float)round($p['pct_fail']);
}
?>

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
    .period-table th {
        font-size: 0.85rem;
        background-color: #f8fafc;
        color: #334155;
        border-bottom: 2px solid #cbd5e1;
        vertical-align: middle;
    }
    .period-table td {
        font-size: 0.88rem;
        vertical-align: middle;
    }
    .badge-percent-pass {
        background-color: #dbeafe;
        color: #1d4ed8;
        font-weight: 700;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
    }
    .badge-percent-fail {
        background-color: #ffedd5;
        color: #c2410c;
        font-weight: 700;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
    }
    .btn-export-excel {
        background: linear-gradient(135deg, #107c41 0%, #1f9a55 100%);
        color: #fff !important;
        border: none;
        transition: all 0.2s ease;
    }
    .btn-export-excel:hover {
        background: linear-gradient(135deg, #0b582e 0%, #157941 100%);
        box-shadow: 0 6px 15px rgba(16, 124, 65, 0.35);
        transform: translateY(-1px);
    }
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.7);
        z-index: 9999;
        color: #fff;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
</style>

<!-- Loading Spinner Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="spinner-border text-light mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
    <h4 class="font-weight-bold">Menarik Data dari Database ERP...</h4>
    <p class="text-light text-muted small">Mohon tunggu beberapa saat hingga proses agregasi selesai.</p>
</div>

<div class="content-wrapper">
    <!-- Header Page -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold">
                        <i class="fas fa-calendar-check text-primary mr-2"></i> Rekap Periode Analisa Warna
                    </h1>
                    <small class="text-muted">Kelola penentuan periode data produksi analisa warna untuk export Excel</small>
                </div>
                <div class="col-sm-6 text-right">
                    <!-- Tombol Export Excel Utama -->
                    <a href="export_excel_analisa.php" class="btn btn-export-excel btn-lg font-weight-bold shadow mr-2">
                        <i class="fas fa-file-excel mr-2"></i> Export ke Excel (.xlsx) Lengkap
                    </a>
                    <a href="analisa_warna.php" class="btn btn-outline-secondary font-weight-bold shadow-sm">
                        <i class="fas fa-table mr-1"></i> Data Detail
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Flash Alert Message -->
            <?php if ($alertSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <h5><i class="icon fas fa-check-circle"></i> Berhasil!</h5>
                    <?= $alertSuccess ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($alertError): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <h5><i class="icon fas fa-ban"></i> Terjadi Kesalahan!</h5>
                    <?= htmlspecialchars($alertError) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Ringkasan Singkat KPI -->
            <div class="row mb-3">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-gradient-info kpi-card shadow-sm">
                        <div class="inner">
                            <h3><?= $countPeriode ?></h3>
                            <p class="font-weight-bold">Total Periode Dibuat</p>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-gradient-primary kpi-card shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($totalAllCp, 0, ',', '.') ?></h3>
                            <p class="font-weight-bold">Total Produksi (CP)</p>
                        </div>
                        <div class="icon"><i class="fas fa-layer-group"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-gradient-success kpi-card shadow-sm">
                        <div class="inner">
                            <h3><?= $avgPassPct ?>%</h3>
                            <p class="font-weight-bold">Rata-rata % Pass</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-gradient-danger kpi-card shadow-sm">
                        <div class="inner">
                            <h3><?= $avgFailPct ?>%</h3>
                            <p class="font-weight-bold">Rata-rata % Fail</p>
                        </div>
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
            </div>

            <!-- Form Buat Periode Baru -->
            <div class="card shadow-sm mb-4">
                <div class="card-header card-header-gradient d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-plus-circle mr-2"></i> Buat Penentuan Periode Baru (Tarik Data ERP)
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body bg-light">
                    <form method="POST" id="formBuatPeriode" onsubmit="showLoading()">
                        <input type="hidden" name="action" value="tambah_periode">
                        <div class="row align-items-end">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold small text-secondary">
                                    <i class="far fa-calendar-alt text-primary mr-1"></i> Tanggal Mulai:
                                </label>
                                <input type="date" class="form-control" name="start_date" id="startDateInput" value="<?= date('Y-m-01') ?>" required onchange="autoFillPeriodeName()">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold small text-secondary">
                                    <i class="far fa-calendar-alt text-primary mr-1"></i> Tanggal Selesai:
                                </label>
                                <input type="date" class="form-control" name="end_date" id="endDateInput" value="<?= date('Y-m-d') ?>" required onchange="autoFillPeriodeName()">
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="font-weight-bold small text-secondary">
                                    <i class="fas fa-tag text-primary mr-1"></i> Nama Periode:
                                </label>
                                <input type="text" class="form-control" name="nama_periode" id="namaPeriodeInput" placeholder="Otomatis terisi, contoh: 05 - 11 Agustus 2026">
                            </div>
                            <div class="col-md-2 col-sm-6 mb-3">
                                <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm" style="height: 38px;">
                                    <i class="fas fa-cloud-download-alt mr-1"></i> Tarik & Simpan
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle text-info mr-1"></i>
                                    Default: Routing ID = <strong>589</strong>, Work Center = <strong>111</strong>.
                                </small>
                                <input type="hidden" name="rtgmsid" value="589">
                                <input type="hidden" name="workcenterid" value="111">
                            </div>
                            <div class="col-md-6 text-right">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('week1')">05 - 11 Agust</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('week2')">12 - 18 Agust</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('week3')">19 - 25 Agust</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('week4')">26 Agust - 01 Sept</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('week5')">02 - 08 Sept</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABEL UTAMA: DAFTAR PERIODE -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-list-ul text-primary mr-2"></i> Daftar Periode Rekap Analisa Warna
                    </h3>
                    <div>
                        <a href="export_excel_analisa.php" class="btn btn-export-excel font-weight-bold shadow-sm btn-sm mr-2">
                            <i class="fas fa-file-excel mr-1"></i> Download File Excel (.xlsx)
                        </a>
                        <span class="badge badge-light border text-secondary">
                            <i class="fas fa-database mr-1"></i> Tersimpan di SQL Server GG
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped period-table m-0" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">No</th>
                                    <th>Nama Periode</th>
                                    <th class="text-center">Tanggal Mulai</th>
                                    <th class="text-center">Tanggal Selesai</th>
                                    <th class="text-center">Total CP</th>
                                    <th class="text-center">% Pass</th>
                                    <th class="text-center">% Fail</th>
                                    <th class="text-right">Qty Repeat Shading</th>
                                    <th class="text-right">Qty Shading</th>
                                    <th class="text-right">Qty Soaping Ulang</th>
                                    <th class="text-right">Qty Top CPB</th>
                                    <th class="text-right">Qty Top Paddry</th>
                                    <th style="width: 110px;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($listPeriode)): ?>
                                    <tr>
                                        <td colspan="13" class="text-center py-5 text-muted">
                                            <i class="fas fa-folder-open fa-3x mb-3 text-secondary" style="opacity: 0.3;"></i>
                                            <p class="font-weight-bold mb-1">Belum ada periode yang dibuat.</p>
                                            <p class="small">Silakan gunakan form di atas untuk menentukan periode pertama Anda.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($listPeriode as $row): ?>
                                        <tr>
                                            <td class="text-center font-weight-bold text-muted"><?= $no++ ?></td>
                                            <td class="font-weight-bold text-primary">
                                                <i class="fas fa-calendar-day mr-1 text-secondary"></i>
                                                <?= htmlspecialchars($row['nama_periode']) ?>
                                            </td>
                                            <td class="text-center"><?= $row['start_date_str'] ?></td>
                                            <td class="text-center"><?= $row['end_date_str'] ?></td>
                                            <td class="text-center font-weight-bold">
                                                <?= number_format((int)$row['total_cp'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-percent-pass">
                                                    <?= round((float)$row['pct_pass']) ?>%
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-percent-fail">
                                                    <?= round((float)$row['pct_fail']) ?>%
                                                </span>
                                            </td>
                                            <td class="text-right font-weight-bold text-secondary">
                                                <?= number_format((float)($row['qty_repeat_shading'] ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right font-weight-bold text-secondary">
                                                <?= number_format((float)($row['qty_shading'] ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right font-weight-bold text-secondary">
                                                <?= number_format((float)($row['qty_soaping_ulang'] ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right font-weight-bold text-secondary">
                                                <?= number_format((float)($row['qty_top_cpb'] ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-right font-weight-bold text-secondary">
                                                <?= number_format((float)($row['qty_top_paddry'] ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <!-- Tarik Ulang -->
                                                    <form method="POST" style="display:inline;" onsubmit="return confirmRefresh('<?= htmlspecialchars($row['nama_periode']) ?>')">
                                                        <input type="hidden" name="action" value="refresh_periode">
                                                        <input type="hidden" name="periode_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-info btn-sm mr-1" title="Tarik Ulang Data dari ERP">
                                                            <i class="fas fa-sync-alt"></i>
                                                        </button>
                                                    </form>
                                                    <!-- Hapus -->
                                                    <form method="POST" style="display:inline;" onsubmit="return confirmDelete('<?= htmlspecialchars($row['nama_periode']) ?>')">
                                                        <input type="hidden" name="action" value="hapus_periode">
                                                        <input type="hidden" name="periode_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus Periode Ini">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        Total <strong><?= $countPeriode ?></strong> periode tersimpan di database.
                    </span>
                    <a href="export_excel_analisa.php" class="btn btn-export-excel font-weight-bold shadow-sm">
                        <i class="fas fa-download mr-1"></i> Download File Excel Lengkap (.xlsx)
                    </a>
                </div>
            </div>

            <!-- Keterangan Struktur Sheet Excel -->
            <div class="card shadow-sm mb-4 border-left-success">
                <div class="card-body">
                    <h5 class="font-weight-bold text-dark mb-2">
                        <i class="fas fa-file-excel text-success mr-2"></i> Struktur File Excel yang Dihasilkan:
                    </h5>
                    <p class="text-secondary small mb-3">
                        Ketika Anda menekan tombol <strong>"Export ke Excel"</strong>, sistem langsung men-generate workbook Excel (.xlsx) dengan <strong>6 sheet</strong> lengkap beserta tabel dan grafik interaktif bawaan Microsoft Excel:
                    </p>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-success mr-1">Sheet 1</span> <strong>% Pass Fail</strong>
                                <p class="small text-muted m-0">Tabel persentase CP & Qty serta grafik garis <em>% Pass & Fail Acc Warna R</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 2</span> <strong>Repeat Shading</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Repeat Shading</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 3</span> <strong>Shading</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Shading</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 4</span> <strong>Soaping Ulang</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Soaping Ulang</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 5</span> <strong>Top CPB</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Top CPB</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 6</span> <strong>Top Paddry</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Top Paddry</em>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    function confirmRefresh(nama) {
        if (confirm("Apakah Anda yakin ingin menarik ulang data untuk periode: " + nama + "?")) {
            showLoading();
            return true;
        }
        return false;
    }

    function confirmDelete(nama) {
        return confirm("Apakah Anda yakin ingin menghapus data periode: " + nama + "?");
    }

    function autoFillPeriodeName() {
        const startVal = document.getElementById('startDateInput').value;
        const endVal = document.getElementById('endDateInput').value;
        if (!startVal || !endVal) return;

        const months = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        const dStart = new Date(startVal);
        const dEnd = new Date(endVal);

        if (isNaN(dStart.getTime()) || isNaN(dEnd.getTime())) return;

        const pad = n => n.toString().padStart(2, '0');
        const d1 = pad(dStart.getDate());
        const m1 = dStart.getMonth();
        const y1 = dStart.getFullYear();

        const d2 = pad(dEnd.getDate());
        const m2 = dEnd.getMonth();
        const y2 = dEnd.getFullYear();

        let generatedName = '';
        if (m1 === m2 && y1 === y2) {
            generatedName = `${d1} - ${d2} ${months[m1]} ${y1}`;
        } else if (y1 === y2) {
            generatedName = `${d1} ${months[m1]} - ${d2} ${months[m2]} ${y1}`;
        } else {
            generatedName = `${d1} ${months[m1]} ${y1} - ${d2} ${months[m2]} ${y2}`;
        }

        document.getElementById('namaPeriodeInput').value = generatedName;
    }

    function setPresetRange(type) {
        const presets = {
            'week1': { start: '2026-08-05', end: '2026-08-11', name: '05 - 11 Agustus 2026' },
            'week2': { start: '2026-08-12', end: '2026-08-18', name: '12 - 18 Agustus 2026' },
            'week3': { start: '2026-08-19', end: '2026-08-25', name: '19 - 25 Agustus 2026' },
            'week4': { start: '2026-08-26', end: '2026-09-01', name: '26 Agustus - 01 September 2026' },
            'week5': { start: '2026-09-02', end: '2026-09-08', name: '02 - 08 September 2026' },
        };

        if (presets[type]) {
            document.getElementById('startDateInput').value = presets[type].start;
            document.getElementById('endDateInput').value = presets[type].end;
            document.getElementById('namaPeriodeInput').value = presets[type].name;
        }
    }
</script>

<?php 
include '../../includes/footer.php'; 
?>
