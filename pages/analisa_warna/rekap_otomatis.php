<?php
session_start();
ob_start();

// Include database connections
require_once '../../koneksi.php';   // SQL Server (Database GG)

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
// ============================================================================
// AUTO-ADD qty_over_warna COLUMN IF MISSING
// ============================================================================
$alterOverWarna = "
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('analisa_warna_periode') AND name = 'qty_over_warna'
)
BEGIN
    ALTER TABLE analisa_warna_periode ADD qty_over_warna DECIMAL(19,2) DEFAULT 0;
END
";
if ($conn) {
    @sqlsrv_query($conn, $alterOverWarna);
}

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

    if (strpos($searchText, 'OVER') !== false || strpos($upper, 'OVER') !== false) {
        return 'Over Warna';
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
 * Hitung agregasi dari tabel analisa_warna_data di SQL Server GG
 * Data kelompok sudah tersimpan dari proses simpan di analisa_warna.php
 */
function calculatePeriodeMetrics($conn, $startDate, $endDate, $rtgmsid = '589', $workcenterid = '111') {
    if (!$conn) {
        throw new Exception("Koneksi ke SQL Server GG tidak tersedia.");
    }

    $sql = "SELECT status, fail_desc, kelompok, qty_normal, qty_repro, qty_produksi
            FROM analisa_warna_data
            WHERE tanggal_selesai BETWEEN ? AND ?
              AND rtgmsid = ?
              AND workcenterid = ?
              AND kelompok IS NOT NULL 
              AND LTRIM(RTRIM(kelompok)) <> ''";

    $stmt = sqlsrv_query($conn, $sql, [$startDate, $endDate, $rtgmsid, $workcenterid]);
    if ($stmt === false) {
        throw new Exception("Gagal query analisa_warna_data: " . print_r(sqlsrv_errors(), true));
    }

    $validKelompok = [
        'pass',
        'pass upg',
        'repeat shading',
        'shading',
        'soaping ulang',
        'test larutan',
        'top cpb',
        'top paddry',
        'over warna'
    ];

    $total_cp = 0;
    $cp_pass = 0;
    $cp_pass_upg = 0;
    $cp_fail = 0;
    $qty_pass = 0.0;
    $qty_pass_upg = 0.0;
    $qty_fail = 0.0;

    $qty_over_warna     = 0.0;
    $qty_repeat_shading = 0.0;
    $qty_shading        = 0.0;
    $qty_soaping_ulang  = 0.0;
    $qty_top_cpb        = 0.0;
    $qty_top_paddry     = 0.0;
    $qty_test_larutan   = 0.0;

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $klp = trim($r['kelompok'] ?? '');
        $klpLower = strtolower($klp);

        // Jika data tidak ada kelompoknya atau tidak masuk ke kelompok yang valid, abaikan dari hitungan
        if (empty($klp) || !in_array($klpLower, $validKelompok, true)) {
            continue;
        }

        $total_cp++;

        // Total qty = qty_normal + qty_repro (dijumlah keduanya)
        $qtyNormal = (float)($r['qty_normal'] ?? 0);
        $qtyRepro  = (float)($r['qty_repro'] ?? 0);
        $qty = $qtyNormal + $qtyRepro;

        if ($klpLower === 'pass') {
            $cp_pass++;
            $qty_pass += $qty;
        } elseif ($klpLower === 'pass upg') {
            $cp_pass_upg++;
            $qty_pass_upg += $qty;
        } else {
            // Fail kategori (Over Warna, Repeat Shading, Shading, Soaping Ulang, Top CPB, Top Paddry, Test Larutan)
            $cp_fail++;
            $qty_fail += $qty;
        }

        switch ($klpLower) {
            case 'over warna':     $qty_over_warna += $qty; break;
            case 'repeat shading': $qty_repeat_shading += $qty; break;
            case 'shading':        $qty_shading += $qty; break;
            case 'soaping ulang':  $qty_soaping_ulang += $qty; break;
            case 'top cpb':        $qty_top_cpb += $qty; break;
            case 'top paddry':     $qty_top_paddry += $qty; break;
            case 'test larutan':   $qty_test_larutan += $qty; break;
        }
    }
    sqlsrv_free_stmt($stmt);

    $cp_total_pass  = $cp_pass + $cp_pass_upg;
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
        'qty_over_warna'     => $qty_over_warna,
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
                // Hitung agregasi dari tabel analisa_warna_data di SQL Server GG
                $m = calculatePeriodeMetrics($conn, $startDate, $endDate, $rtgmsid, $workcenterid);

                // Simpan ke SQL Server (Database GG)
                $insertSql = "
                INSERT INTO analisa_warna_periode (
                    nama_periode, start_date, end_date, rtgmsid, workcenterid,
                    total_cp, cp_pass, cp_pass_upg, cp_total_pass, cp_fail,
                    qty_pass, qty_pass_upg, qty_total_pass, qty_fail,
                    pct_pass, pct_fail,
                    qty_repeat_shading, qty_shading, qty_soaping_ulang,
                    qty_top_cpb, qty_top_paddry, qty_test_larutan,
                    qty_over_warna,
                    created_by, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

                $params = [
                    $namaPeriode, $startDate, $endDate, $rtgmsid, $workcenterid,
                    $m['total_cp'], $m['cp_pass'], $m['cp_pass_upg'], $m['cp_total_pass'], $m['cp_fail'],
                    $m['qty_pass'], $m['qty_pass_upg'], $m['qty_total_pass'], $m['qty_fail'],
                    $m['pct_pass'], $m['pct_fail'],
                    $m['qty_repeat_shading'], $m['qty_shading'], $m['qty_soaping_ulang'],
                    $m['qty_top_cpb'], $m['qty_top_paddry'], $m['qty_test_larutan'],
                    $m['qty_over_warna'],
                    $_SESSION['UserName'] ?? 'System'
                ];

                $stmt = sqlsrv_query($conn, $insertSql, $params);
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $alertError = "Gagal menyimpan ke database GG: " . ($errors[0]['message'] ?? 'Unknown error');
                } else {
                    if ($m['total_cp'] === 0) {
                        $alertSuccess = "Periode '<strong>" . htmlspecialchars($namaPeriode) . "</strong>' berhasil dibuat, namun belum ada data di tabel <code>analisa_warna_data</code> untuk rentang tanggal tersebut. Pastikan data sudah disimpan dari halaman Analisa Warna terlebih dahulu.";
                    } else {
                        $alertSuccess = "Periode '<strong>" . htmlspecialchars($namaPeriode) . "</strong>' berhasil dibuat dan data ({$m['total_cp']} CP) berhasil dihitung!";
                    }
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
                    $m = calculatePeriodeMetrics($conn, $startDate, $endDate, $rtgmsid, $workcenterid);

                    $updateSql = "
                    UPDATE analisa_warna_periode SET
                        total_cp = ?, cp_pass = ?, cp_pass_upg = ?, cp_total_pass = ?, cp_fail = ?,
                        qty_pass = ?, qty_pass_upg = ?, qty_total_pass = ?, qty_fail = ?,
                        pct_pass = ?, pct_fail = ?,
                        qty_repeat_shading = ?, qty_shading = ?, qty_soaping_ulang = ?,
                        qty_top_cpb = ?, qty_top_paddry = ?, qty_test_larutan = ?,
                        qty_over_warna = ?,
                        updated_at = GETDATE()
                    WHERE id = ?";

                    $uParams = [
                        $m['total_cp'], $m['cp_pass'], $m['cp_pass_upg'], $m['cp_total_pass'], $m['cp_fail'],
                        $m['qty_pass'], $m['qty_pass_upg'], $m['qty_total_pass'], $m['qty_fail'],
                        $m['pct_pass'], $m['pct_fail'],
                        $m['qty_repeat_shading'], $m['qty_shading'], $m['qty_soaping_ulang'],
                        $m['qty_top_cpb'], $m['qty_top_paddry'], $m['qty_test_larutan'],
                        $m['qty_over_warna'],
                        $periodeId
                    ];

                    $uStmt = sqlsrv_query($conn, $updateSql, $uParams);
                    if ($uStmt) {
                        if ($m['total_cp'] === 0) {
                            $alertSuccess = "Data periode '<strong>" . htmlspecialchars($row['nama_periode']) . "</strong>' diperbarui, namun belum ada data di <code>analisa_warna_data</code> untuk rentang tanggal ini.";
                        } else {
                            $alertSuccess = "Data periode '<strong>" . htmlspecialchars($row['nama_periode']) . "</strong>' berhasil diperbarui ({$m['total_cp']} CP dari database GG)!";
                        }
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
                    <a href="analisa_warna.php" class="btn btn-outline-primary font-weight-bold shadow-sm">
                        <i class="fas fa-table mr-1"></i> Halaman Analisa Warna
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
                        <i class="fas fa-plus-circle mr-2"></i> Buat Penentuan Periode Baru
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
                                    <i class="fas fa-save mr-1"></i> Simpan Periode
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle text-info mr-1"></i>
                                    Data agregasi dihitung dari tabel <code>analisa_warna_data</code> di database <strong>GG</strong>.
                                    Data yang belum memiliki kelompok otomatis <strong>tidak masuk hitungan</strong> (tidak dihitung di Total CP, Pass, maupun Fail).
                                </small>
                                <input type="hidden" name="rtgmsid" value="589">
                                <input type="hidden" name="workcenterid" value="111">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABEL UTAMA: DAFTAR PERIODE -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h3 class="card-title font-weight-bold text-dark m-0 pt-1">
                        <i class="fas fa-list-ul text-primary mr-2"></i> Daftar Periode Rekap Analisa Warna
                    </h3>
                    <div class="card-tools d-flex align-items-center ml-auto">
                        <span class="badge badge-light border text-secondary mr-2 py-2 px-2">
                            <i class="fas fa-database text-success mr-1"></i> Data Tersimpan di SQL Server GG
                        </span>
                        <button type="button" class="btn btn-export-excel font-weight-bold shadow-sm btn-sm" onclick="exportSelectedPeriode()">
                            <i class="fas fa-file-excel mr-1"></i> Export ke Excel (.xlsx)
                            <span class="badge badge-light text-success ml-1 font-weight-bold" id="countSelectedBadge" style="display:none;">0</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped period-table m-0" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" id="checkAllPeriode" title="Pilih Semua / Batal Pilih Semua" style="cursor: pointer; transform: scale(1.2);">
                                    </th>
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
                                        <td colspan="14" class="text-center py-5 text-muted">
                                            <i class="fas fa-folder-open fa-3x mb-3 text-secondary" style="opacity: 0.3;"></i>
                                            <p class="font-weight-bold mb-1">Belum ada periode yang dibuat.</p>
                                            <p class="small">Silakan gunakan form di atas untuk menentukan periode pertama Anda.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($listPeriode as $row): ?>
                                        <tr id="row-periode-<?= $row['id'] ?>">
                                            <td class="text-center align-middle">
                                                <input type="checkbox" class="check-periode" value="<?= $row['id'] ?>" style="cursor: pointer; transform: scale(1.2);" onchange="updateSelectedCount()">
                                            </td>
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
                                                    <!-- Hitung Ulang dari GG -->
                                                    <form method="POST" style="display:inline;" onsubmit="return confirmRefresh('<?= htmlspecialchars($row['nama_periode']) ?>')">
                                                        <input type="hidden" name="action" value="refresh_periode">
                                                        <input type="hidden" name="periode_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-info btn-sm mr-1" title="Hitung Ulang dari Database GG">
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
                <div class="card-footer bg-light">
                    <span class="small text-muted">
                        Total <strong><?= $countPeriode ?></strong> periode tersimpan di database. Gunakan tombol <strong>Export ke Excel (.xlsx)</strong> di atas untuk mengunduh rekap.
                    </span>
                </div>
            </div>

            <!-- Keterangan Struktur Sheet Excel -->
            <div class="card shadow-sm mb-4 border-left-success">
                <div class="card-body">
                    <h5 class="font-weight-bold text-dark mb-2">
                        <i class="fas fa-file-excel text-success mr-2"></i> Struktur File Excel yang Dihasilkan:
                    </h5>
                    <p class="text-secondary small mb-3">
                        Ketika Anda menekan tombol <strong>"Export ke Excel"</strong>, sistem langsung men-generate workbook Excel (.xlsx) dengan <strong>7 sheet</strong> lengkap beserta tabel dan grafik interaktif bawaan Microsoft Excel:
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
                                <span class="badge badge-warning mr-1">Sheet 2</span> <strong>Over Warna</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Over Warna</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 3</span> <strong>Repeat Shading</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Repeat Shading</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 4</span> <strong>Shading</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Shading</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 5</span> <strong>Soaping Ulang</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Soaping Ulang</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 6</span> <strong>Top CPB</strong>
                                <p class="small text-muted m-0">Tabel Periode & Total Qty serta grafik garis tren <em>Top CPB</em>.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2 bg-light">
                                <span class="badge badge-primary mr-1">Sheet 7</span> <strong>Top Paddry</strong>
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
        if (confirm("Apakah Anda yakin ingin menghitung ulang data periode: " + nama + " dari database GG?")) {
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


    function updateSelectedCount() {
        var total = $('.check-periode').length;
        var checked = $('.check-periode:checked').length;

        if (total > 0 && total === checked) {
            $('#checkAllPeriode').prop('checked', true).prop('indeterminate', false);
        } else if (checked > 0) {
            $('#checkAllPeriode').prop('checked', false).prop('indeterminate', true);
        } else {
            $('#checkAllPeriode').prop('checked', false).prop('indeterminate', false);
        }

        if (checked > 0) {
            $('#countSelectedBadge').text(checked + ' dipilih').show();
        } else {
            $('#countSelectedBadge').text('0').hide();
        }
    }

    function exportSelectedPeriode() {
        var selectedIds = [];
        $('.check-periode:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert("Silakan pilih/centang minimal 1 periode pada tabel yang ingin diexport ke Excel!");
            return;
        }

        var url = 'export_excel_analisa.php?ids=' + encodeURIComponent(selectedIds.join(','));
        window.location.href = url;
    }

    $(document).ready(function() {
        // Centang semua periode secara default saat halaman dimuat
        $('.check-periode').prop('checked', true);
        if ($('.check-periode').length > 0) {
            $('#checkAllPeriode').prop('checked', true);
        }
        updateSelectedCount();

        // Toggle pilih semua checkbox
        $('#checkAllPeriode').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('.check-periode').prop('checked', isChecked);
            updateSelectedCount();
        });
    });
</script>

<?php 
include '../../includes/footer.php'; 
?>
