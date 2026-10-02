<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
// 1. AUTO-SETUP DATABASE TABLE IN SQL SERVER GG (IF NOT EXISTS)
// ============================================================================
$createTableSql = "
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'analisa_kain_periode')
BEGIN
    CREATE TABLE analisa_kain_periode (
        id INT IDENTITY(1,1) PRIMARY KEY,
        nama_periode NVARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        total_cp INT DEFAULT 0,
        cp_solved INT DEFAULT 0,
        cp_open INT DEFAULT 0,
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
 * Hitung metrik masalah kain dari database GG untuk rentang tanggal tertentu
 * Menggunakan kriteria id_kategori = 1 (Masalah Kain) dan acuan CAST(p.created_at AS DATE)
 */
function calculateKainPeriodeMetrics($conn, $startDate, $endDate) {
    if (!$conn) {
        throw new Exception("Koneksi ke database SQL Server GG tidak tersedia.");
    }

    $sql = "SELECT 
                COUNT(*) as total_cp,
                SUM(CASE WHEN COALESCE(s.status, 'Open') = 'Solved' THEN 1 ELSE 0 END) as cp_solved,
                SUM(CASE WHEN COALESCE(s.status, 'Open') = 'Open' THEN 1 ELSE 0 END) as cp_open
            FROM dbo.fab_m_problem p
            LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem
            WHERE p.id_kategori = 1
              AND COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) BETWEEN ? AND ?";

    $stmt = sqlsrv_query($conn, $sql, [$startDate, $endDate]);
    if ($stmt === false) {
        throw new Exception("Gagal query fab_m_problem: " . print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    return [
        'total_cp'  => (int)($row['total_cp'] ?? 0),
        'cp_solved' => (int)($row['cp_solved'] ?? 0),
        'cp_open'   => (int)($row['cp_open'] ?? 0),
    ];
}

// ============================================================================
// 3. ACTION HANDLERS (POST)
// ============================================================================
$alertSuccess = null;
$alertError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. TAMBAH PERIODE MANUAL
    if ($action === 'tambah_periode') {
        $startDate   = trim($_POST['start_date'] ?? '');
        $endDate     = trim($_POST['end_date'] ?? '');
        $namaPeriode = trim($_POST['nama_periode'] ?? '');

        if (empty($startDate) || empty($endDate)) {
            $alertError = "Tanggal awal dan tanggal akhir harus diisi!";
        } elseif (strtotime($startDate) > strtotime($endDate)) {
            $alertError = "Tanggal awal tidak boleh lebih besar dari tanggal akhir!";
        } else {
            if (empty($namaPeriode)) {
                $namaPeriode = generatePeriodeName($startDate, $endDate);
            }

            try {
                $m = calculateKainPeriodeMetrics($conn, $startDate, $endDate);

                $insertSql = "INSERT INTO analisa_kain_periode (
                    nama_periode, start_date, end_date, total_cp, cp_solved, cp_open, created_by, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";

                $params = [
                    $namaPeriode, $startDate, $endDate,
                    $m['total_cp'], $m['cp_solved'], $m['cp_open'],
                    $_SESSION['UserName'] ?? 'System'
                ];

                $stmt = sqlsrv_query($conn, $insertSql, $params);
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $alertError = "Gagal menyimpan periode: " . ($errors[0]['message'] ?? 'Unknown error');
                } else {
                    $alertSuccess = "Periode '<strong>" . htmlspecialchars($namaPeriode) . "</strong>' berhasil ditambahkan ({$m['total_cp']} Masalah Kain ditemukan).";
                    sqlsrv_free_stmt($stmt);
                }
            } catch (Exception $e) {
                $alertError = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }

    // B. AUTO-GENERATE MINGGUAN (7 HARIAN)
    if ($action === 'auto_generate_mingguan') {
        $rangeStart = trim($_POST['range_start'] ?? '');
        $rangeEnd   = trim($_POST['range_end'] ?? '');

        if (empty($rangeStart) || empty($rangeEnd)) {
            $alertError = "Rentang tanggal awal dan akhir untuk generate otomatis wajib diisi!";
        } elseif (strtotime($rangeStart) > strtotime($rangeEnd)) {
            $alertError = "Tanggal awal tidak boleh lebih besar dari tanggal akhir!";
        } else {
            try {
                $tStart = strtotime($rangeStart);
                $tEnd   = strtotime($rangeEnd);
                $generatedCount = 0;

                $currStart = $tStart;
                while ($currStart <= $tEnd) {
                    $currEnd = min(strtotime('+6 days', $currStart), $tEnd);
                    $sDate = date('Y-m-d', $currStart);
                    $eDate = date('Y-m-d', $currEnd);
                    $pName = generatePeriodeName($sDate, $eDate);

                    $m = calculateKainPeriodeMetrics($conn, $sDate, $eDate);

                    // Cek apakah periode dengan start_date & end_date sama sudah ada
                    $checkSql = "SELECT id FROM analisa_kain_periode WHERE start_date = ? AND end_date = ?";
                    $checkStmt = sqlsrv_query($conn, $checkSql, [$sDate, $eDate]);
                    if ($checkStmt && ($existing = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC))) {
                        // Update
                        $updSql = "UPDATE analisa_kain_periode SET 
                                    nama_periode = ?, total_cp = ?, cp_solved = ?, cp_open = ?, updated_at = GETDATE() 
                                   WHERE id = ?";
                        sqlsrv_query($conn, $updSql, [$pName, $m['total_cp'], $m['cp_solved'], $m['cp_open'], $existing['id']]);
                    } else {
                        // Insert
                        $insSql = "INSERT INTO analisa_kain_periode (
                            nama_periode, start_date, end_date, total_cp, cp_solved, cp_open, created_by, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
                        sqlsrv_query($conn, $insSql, [$pName, $sDate, $eDate, $m['total_cp'], $m['cp_solved'], $m['cp_open'], $_SESSION['UserName'] ?? 'System']);
                    }
                    if ($checkStmt) sqlsrv_free_stmt($checkStmt);
                    $generatedCount++;

                    // Lompat 7 hari
                    $currStart = strtotime('+1 day', $currEnd);
                }

                $alertSuccess = "Berhasil membuat/memperbarui <strong>$generatedCount periode mingguan</strong> dari rentang $rangeStart s/d $rangeEnd.";
            } catch (Exception $e) {
                $alertError = "Gagal auto-generate periode: " . $e->getMessage();
            }
        }
    }

    // C. REFRESH SATU PERIODE
    if ($action === 'refresh_periode') {
        $periodeId = (int)($_POST['periode_id'] ?? 0);
        if ($periodeId > 0) {
            $selectSql = "SELECT * FROM analisa_kain_periode WHERE id = ?";
            $stmt = sqlsrv_query($conn, $selectSql, [$periodeId]);
            if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                $sDate = ($row['start_date'] instanceof DateTime) ? $row['start_date']->format('Y-m-d') : (string)$row['start_date'];
                $eDate = ($row['end_date'] instanceof DateTime) ? $row['end_date']->format('Y-m-d') : (string)$row['end_date'];
                sqlsrv_free_stmt($stmt);

                try {
                    $m = calculateKainPeriodeMetrics($conn, $sDate, $eDate);
                    $updateSql = "UPDATE analisa_kain_periode SET 
                                    total_cp = ?, cp_solved = ?, cp_open = ?, updated_at = GETDATE() 
                                  WHERE id = ?";
                    sqlsrv_query($conn, $updateSql, [$m['total_cp'], $m['cp_solved'], $m['cp_open'], $periodeId]);
                    $alertSuccess = "Data periode '<strong>" . htmlspecialchars($row['nama_periode']) . "</strong>' berhasil diperbarui ({$m['total_cp']} Masalah Kain).";
                } catch (Exception $e) {
                    $alertError = "Gagal refresh data: " . $e->getMessage();
                }
            }
        }
    }

    // D. REFRESH SEMUA PERIODE
    if ($action === 'refresh_all') {
        $selSql = "SELECT id, start_date, end_date FROM analisa_kain_periode";
        $stmt = sqlsrv_query($conn, $selSql);
        if ($stmt) {
            $refCount = 0;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $sDate = ($row['start_date'] instanceof DateTime) ? $row['start_date']->format('Y-m-d') : (string)$row['start_date'];
                $eDate = ($row['end_date'] instanceof DateTime) ? $row['end_date']->format('Y-m-d') : (string)$row['end_date'];
                $m = calculateKainPeriodeMetrics($conn, $sDate, $eDate);
                $updSql = "UPDATE analisa_kain_periode SET total_cp = ?, cp_solved = ?, cp_open = ?, updated_at = GETDATE() WHERE id = ?";
                sqlsrv_query($conn, $updSql, [$m['total_cp'], $m['cp_solved'], $m['cp_open'], $row['id']]);
                $refCount++;
            }
            sqlsrv_free_stmt($stmt);
            $alertSuccess = "Seluruh data ($refCount periode) berhasil disinkronisasi ulang dari database GG!";
        }
    }

    // E. HAPUS SATU PERIODE
    if ($action === 'hapus_periode') {
        $periodeId = (int)($_POST['periode_id'] ?? 0);
        if ($periodeId > 0) {
            $delSql = "DELETE FROM analisa_kain_periode WHERE id = ?";
            $delStmt = sqlsrv_query($conn, $delSql, [$periodeId]);
            if ($delStmt) {
                $alertSuccess = "Periode berhasil dihapus.";
                sqlsrv_free_stmt($delStmt);
            } else {
                $alertError = "Gagal menghapus periode.";
            }
        }
    }
}

// ============================================================================
// 4. FETCH SEMUA PERIODE DARI SQL SERVER GG
// ============================================================================
$listPeriode = [];
$totalAllCp = 0;
$totalAllSolved = 0;
$totalAllOpen = 0;

if ($conn) {
    $fetchSql = "SELECT * FROM analisa_kain_periode ORDER BY start_date ASC, id ASC";
    $fetchStmt = sqlsrv_query($conn, $fetchSql);
    if ($fetchStmt) {
        while ($p = sqlsrv_fetch_array($fetchStmt, SQLSRV_FETCH_ASSOC)) {
            $p['s_date'] = ($p['start_date'] instanceof DateTime) ? $p['start_date']->format('Y-m-d') : (string)$p['start_date'];
            $p['e_date'] = ($p['end_date'] instanceof DateTime) ? $p['end_date']->format('Y-m-d') : (string)$p['end_date'];
            $p['s_str']  = ($p['start_date'] instanceof DateTime) ? $p['start_date']->format('d/m/Y') : (string)$p['start_date'];
            $p['e_str']  = ($p['end_date'] instanceof DateTime) ? $p['end_date']->format('d/m/Y') : (string)$p['end_date'];

            $listPeriode[] = $p;
            $totalAllCp     += (int)($p['total_cp'] ?? 0);
            $totalAllSolved += (int)($p['cp_solved'] ?? 0);
            $totalAllOpen   += (int)($p['cp_open'] ?? 0);
        }
        sqlsrv_free_stmt($fetchStmt);
    }
}

$pctSolvedTotal = $totalAllCp > 0 ? round(($totalAllSolved / $totalAllCp) * 100, 1) : 0;
$pctOpenTotal   = $totalAllCp > 0 ? round(($totalAllOpen / $totalAllCp) * 100, 1) : 0;

// ============================================================================
// 5. FETCH DATA PRATINJAU (PREVIEW REKAP MASALAH KAIN)
// ============================================================================
// Group data: $previewData[nama_tag][periode_id] = [ 'periode_name' => ..., 'cps' => [...] ]
$previewData = [];
$previewTotalMasalah = 0;
$previewTotalSolved = 0;
$previewTotalOpen = 0;

if (!empty($listPeriode)) {
    // Ambil rentang min start_date dan max end_date
    $minStart = $listPeriode[0]['s_date'];
    $maxEnd   = $listPeriode[count($listPeriode) - 1]['e_date'];
    foreach ($listPeriode as $lp) {
        if ($lp['s_date'] < $minStart) $minStart = $lp['s_date'];
        if ($lp['e_date'] > $maxEnd) $maxEnd = $lp['e_date'];
    }

    $qPreview = "SELECT 
                    p.id_problem,
                    p.nocp,
                    COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) as tgl_problem,
                    COALESCE(t.nama_tag, 'Lain-Lain') as nama_tag,
                    COALESCE(s.status, 'Open') as status
                FROM dbo.fab_m_problem p
                LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag
                LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem
                WHERE p.id_kategori = 1
                  AND COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) BETWEEN ? AND ?
                ORDER BY t.nama_tag ASC, COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) ASC, p.nocp ASC";

    $stmtPrev = sqlsrv_query($conn, $qPreview, [$minStart, $maxEnd]);
    if ($stmtPrev) {
        while ($row = sqlsrv_fetch_array($stmtPrev, SQLSRV_FETCH_ASSOC)) {
            $tglStr = ($row['tgl_problem'] instanceof DateTime) ? $row['tgl_problem']->format('Y-m-d') : (string)$row['tgl_problem'];
            $tag = trim($row['nama_tag']);
            if (empty($tag)) $tag = 'Lain-Lain';
            $status = trim($row['status']);

            // Cocokkan ke periode mana record ini jatuh
            foreach ($listPeriode as $lp) {
                if ($tglStr >= $lp['s_date'] && $tglStr <= $lp['e_date']) {
                    $pid = $lp['id'];
                    if (!isset($previewData[$tag])) {
                        $previewData[$tag] = [];
                    }
                    if (!isset($previewData[$tag][$pid])) {
                        $previewData[$tag][$pid] = [
                            'nama_periode' => $lp['nama_periode'],
                            'cps' => []
                        ];
                    }
                    $previewData[$tag][$pid]['cps'][] = [
                        'id_problem' => $row['id_problem'],
                        'nocp'       => $row['nocp'],
                        'status'     => $status,
                        'tgl'        => $tglStr
                    ];

                    $previewTotalMasalah++;
                    if ($status === 'Solved') $previewTotalSolved++;
                    else $previewTotalOpen++;
                    break;
                }
            }
        }
        sqlsrv_free_stmt($stmtPrev);
    }
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold">
                        <i class="fas fa-layer-group text-primary mr-2"></i>Otomatisasi Rekap Analisa Kain
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/">Home</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/fabric_knowledge/problem_list.php">Fabric Knowledge</a></li>
                        <li class="breadcrumb-item active">Analisa Kain</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- Alerts -->
            <?php if (!empty($alertSuccess)): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?= $alertSuccess ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            <?php if (!empty($alertError)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?= $alertError ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- KPI Summary Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info shadow-sm rounded">
                        <div class="inner">
                            <h3><?= count($listPeriode) ?></h3>
                            <p>Periode Terdaftar</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary shadow-sm rounded">
                        <div class="inner">
                            <h3><?= number_format($totalAllCp) ?></h3>
                            <p>Total Masalah Kain</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-cubes"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success shadow-sm rounded">
                        <div class="inner">
                            <h3><?= number_format($totalAllSolved) ?> <sup style="font-size: 16px">(<?= $pctSolvedTotal ?>%)</sup></h3>
                            <p>Status Solved</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-double"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning shadow-sm rounded">
                        <div class="inner text-white">
                            <h3 class="text-white"><?= number_format($totalAllOpen) ?> <sup style="font-size: 16px">(<?= $pctOpenTotal ?>%)</sup></h3>
                            <p>Status Open</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Toolbar & Modals -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-sliders-h mr-2"></i>Kontrol Rekap Periode
                    </h3>
                    <div class="card-tools d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-primary mr-1" data-toggle="modal" data-target="#modalTambahManual">
                            <i class="fas fa-plus mr-1"></i> Tambah Periode Manual
                        </button>
                        <button type="button" class="btn btn-sm btn-info mr-1" data-toggle="modal" data-target="#modalAutoGenerate">
                            <i class="fas fa-magic mr-1"></i> Auto-Generate Mingguan
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menyinkronkan ulang hitungan untuk SEMUA periode?');">
                            <input type="hidden" name="action" value="refresh_all">
                            <button type="submit" class="btn btn-sm btn-secondary mr-1" title="Tarik ulang hitungan semua periode dari DB">
                                <i class="fas fa-sync-alt mr-1"></i> Sinkron Semua
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm btn-success font-weight-bold shadow-sm" onclick="exportSelectedPeriode()">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel Rekap & Grafik
                            <span class="badge badge-light ml-1" id="countSelectedBadge" style="display:none;">0</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 text-center">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="checkAllPeriode" title="Pilih Semua Periode" style="transform: scale(1.2); cursor: pointer;">
                                    </th>
                                    <th width="50">No</th>
                                    <th class="text-left">Nama Periode</th>
                                    <th width="120">Tgl Awal</th>
                                    <th width="120">Tgl Akhir</th>
                                    <th width="110">Total CP</th>
                                    <th width="100">Solved</th>
                                    <th width="100">Open</th>
                                    <th width="110">% Solved</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($listPeriode)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-3x mb-2 text-secondary d-block"></i>
                                            Belum ada periode yang didaftarkan.<br>
                                            Silakan gunakan tombol <strong>"Auto-Generate Mingguan"</strong> atau <strong>"Tambah Periode Manual"</strong> di atas.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($listPeriode as $p): 
                                        $pSolved = (int)$p['cp_solved'];
                                        $pOpen = (int)$p['cp_open'];
                                        $pTotal = (int)$p['total_cp'];
                                        $pRate = $pTotal > 0 ? round(($pSolved / $pTotal) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="check-periode" value="<?= $p['id'] ?>" onchange="updateSelectedCount()" style="transform: scale(1.2); cursor: pointer;">
                                            </td>
                                            <td><?= $no++ ?></td>
                                            <td class="text-left font-weight-bold text-primary">
                                                <?= htmlspecialchars($p['nama_periode']) ?>
                                            </td>
                                            <td><span class="badge badge-light border"><?= $p['s_str'] ?></span></td>
                                            <td><span class="badge badge-light border"><?= $p['e_str'] ?></span></td>
                                            <td>
                                                <span class="badge badge-primary px-2 py-1 font-weight-bold" style="font-size: 13px;">
                                                    <?= number_format($pTotal) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 13px;">
                                                    <?= number_format($pSolved) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-warning text-white px-2 py-1 font-weight-bold" style="font-size: 13px;">
                                                    <?= number_format($pOpen) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 18px;" title="<?= $pRate ?>% Solved">
                                                    <div class="progress-bar bg-success font-weight-bold" role="progressbar" style="width: <?= $pRate ?>%; font-size: 11px;">
                                                        <?= $pRate ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="refresh_periode">
                                                    <input type="hidden" name="periode_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-info mr-1" title="Sinkron ulang data periode ini">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus periode <?= htmlspecialchars($p['nama_periode']) ?>?');">
                                                    <input type="hidden" name="action" value="hapus_periode">
                                                    <input type="hidden" name="periode_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus periode">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Card Pratinjau Rekap Masalah Kain (Sesuai Layout Excel Sheet 1) -->
            <div class="card card-outline card-success shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-table mr-2 text-success"></i>Pratinjau Tabel Rekap Masalah Kain
                        </h3>
                        <span class="badge badge-secondary ml-2">Sesuai Format Sheet Excel</span>
                    </div>
                    <div class="card-tools d-flex align-items-center">
                        <input type="text" id="tableFilterInput" class="form-control form-control-sm" placeholder="Cari masalah / No CP..." style="width: 220px;">
                    </div>
                </div>
                <div class="card-body">
                    <!-- Summary Box Widget Mirip Kolom G-I di Excel -->
                    <div class="row mb-3">
                        <div class="col-md-7">
                            <div class="alert alert-light border shadow-sm">
                                <h6 class="font-weight-bold text-primary mb-1">
                                    <i class="fas fa-info-circle mr-1"></i>Informasi Rekap
                                </h6>
                                <p class="mb-0 text-muted small">
                                    Tabel di bawah merupakan pratinjau data masalah kain yang dikelompokkan berdasarkan <strong>Jenis Masalah (Tag)</strong> dan <strong>Periode</strong>. Saat diekspor ke Excel, data ini akan menjadi <strong>Sheet Rekap Utama</strong> beserta Pie Chart, dan setiap Jenis Masalah akan memiliki <strong>Sheet Grafik Garis</strong> tersendiri.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card border border-primary shadow-sm mb-0">
                                <div class="card-header bg-primary text-white py-1 px-3 text-center font-weight-bold" style="font-size: 13px;">
                                    UPDATE: <?= date('d-m-Y') ?> (TOTAL MASALAH KAIN)
                                </div>
                                <div class="card-body p-2 text-center">
                                    <div class="row">
                                        <div class="col-4 border-right">
                                            <div class="text-muted small">Total Masalah</div>
                                            <div class="font-weight-bold text-primary" style="font-size: 18px;"><?= number_format($previewTotalMasalah) ?></div>
                                        </div>
                                        <div class="col-4 border-right">
                                            <div class="text-muted small">Solved</div>
                                            <div class="font-weight-bold text-success" style="font-size: 18px;"><?= number_format($previewTotalSolved) ?></div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Open</div>
                                            <div class="font-weight-bold text-warning" style="font-size: 18px;"><?= number_format($previewTotalOpen) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Pratinjau Rekap -->
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-bordered table-sm text-center mb-0" id="previewTable" style="border: 2px solid #2F5597;">
                            <thead>
                                <tr style="background-color: #2F5597; color: white;">
                                    <th colspan="5" class="py-2 text-center font-weight-bold" style="font-size: 15px; letter-spacing: 1px;">
                                        MASALAH KAIN
                                    </th>
                                </tr>
                                <tr style="background-color: #D9E1F2; color: #000; font-weight: bold;">
                                    <th width="22%" class="text-center align-middle">Jenis Masalah</th>
                                    <th width="28%" class="text-center align-middle">Periode</th>
                                    <th width="24%" class="text-center align-middle">No CP</th>
                                    <th width="14%" class="text-center align-middle">Status</th>
                                    <th width="12%" class="text-center align-middle">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($previewData)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            Belum ada data masalah kain pada periode yang dipilih.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($previewData as $tagName => $tagPeriods): 
                                        // Hitung total baris untuk rowspan Jenis Masalah
                                        $tagTotalRows = 0;
                                        foreach ($tagPeriods as $pid => $pInfo) {
                                            $tagTotalRows += count($pInfo['cps']);
                                        }
                                        $isFirstTagRow = true;
                                    ?>
                                        <?php foreach ($tagPeriods as $pid => $pInfo): 
                                            $periodRows = count($pInfo['cps']);
                                            $isFirstPeriodRow = true;
                                        ?>
                                            <?php foreach ($pInfo['cps'] as $cp): ?>
                                                <tr class="preview-row">
                                                    <?php if ($isFirstTagRow): ?>
                                                        <td rowspan="<?= $tagTotalRows ?>" class="align-middle font-weight-bold text-left bg-light" style="border-right: 2px solid #2F5597; font-size: 13px;">
                                                            <?= htmlspecialchars($tagName) ?>
                                                        </td>
                                                        <?php $isFirstTagRow = false; ?>
                                                    <?php endif; ?>

                                                    <?php if ($isFirstPeriodRow): ?>
                                                        <td rowspan="<?= $periodRows ?>" class="align-middle font-weight-bold text-left" style="background-color: #fafbfc;">
                                                            <?= htmlspecialchars($pInfo['nama_periode']) ?>
                                                        </td>
                                                    <?php endif; ?>

                                                    <td class="align-middle text-left font-family-monospace" style="font-size: 12px;">
                                                        <?= htmlspecialchars($cp['nocp']) ?>
                                                    </td>
                                                    <td class="align-middle">
                                                        <?php if ($cp['status'] === 'Solved'): ?>
                                                            <span class="badge badge-success px-2 py-1">Solved</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning text-white px-2 py-1">Open</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <?php if ($isFirstPeriodRow): ?>
                                                        <td rowspan="<?= $periodRows ?>" class="align-middle font-weight-bold" style="background-color: #fafbfc; font-size: 14px;">
                                                            <?= $periodRows ?>
                                                        </td>
                                                        <?php $isFirstPeriodRow = false; ?>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Modal Tambah Periode Manual -->
<div class="modal fade" id="modalTambahManual" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-calendar-plus mr-2"></i>Tambah Periode Manual
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="tambah_periode">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Tanggal Awal <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" id="man_start_date" class="form-control" required onchange="autoFillManualName()">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Tanggal Akhir <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" id="man_end_date" class="form-control" required onchange="autoFillManualName()">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Nama Periode</label>
                        <input type="text" name="nama_periode" id="man_nama_periode" class="form-control" placeholder="Contoh: 05 - 11 Agustus 2026">
                        <small class="form-text text-muted">Boleh dikosongkan, sistem akan memformat otomatis bahasa Indonesia.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Periode
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Auto-Generate Mingguan -->
<div class="modal fade" id="modalAutoGenerate" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-magic mr-2"></i>Auto-Generate Periode Mingguan (7 Hari)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="auto_generate_mingguan">
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Pilih rentang tanggal besar (misal 1 bulan penuh). Sistem akan otomatis membagi rentang tersebut menjadi blok mingguan per 7 hari dan menghitung jumlah masalah kainnya.
                    </p>
                    <div class="form-group">
                        <label class="font-weight-bold">Rentang Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" name="range_start" class="form-control" value="<?= date('Y-m-01') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Rentang Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="date" name="range_end" class="form-control" value="<?= date('Y-m-t') ?>" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info font-weight-bold text-white">
                        <i class="fas fa-bolt mr-1"></i> Generate Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Update selected checkboxes count
    function updateSelectedCount() {
        var checked = $('.check-periode:checked').length;
        var total = $('.check-periode').length;

        if (total > 0 && checked === total) {
            $('#checkAllPeriode').prop('checked', true);
        } else {
            $('#checkAllPeriode').prop('checked', false);
        }

        if (checked > 0) {
            $('#countSelectedBadge').text(checked + ' dipilih').show();
        } else {
            $('#countSelectedBadge').text('0').hide();
        }
    }

    // Export selected periodes to Excel
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

    // Filter table live search
    $(document).ready(function() {
        // Centang semua periode secara default
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

        // Search in preview table
        $('#tableFilterInput').on('keyup', function() {
            var val = $(this).val().toLowerCase();
            $('#previewTable tbody tr.preview-row').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
            });
        });
    });

    // Auto-fill manual period name
    function autoFillManualName() {
        var s = $('#man_start_date').val();
        var e = $('#man_end_date').val();
        if (s && e) {
            var blnNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            var d1 = new Date(s);
            var d2 = new Date(e);
            if (!isNaN(d1.getTime()) && !isNaN(d2.getTime())) {
                var day1 = ('0' + d1.getDate()).slice(-2);
                var m1 = blnNames[d1.getMonth()];
                var y1 = d1.getFullYear();

                var day2 = ('0' + d2.getDate()).slice(-2);
                var m2 = blnNames[d2.getMonth()];
                var y2 = d2.getFullYear();

                var name = '';
                if (m1 === m2 && y1 === y2) {
                    name = day1 + ' - ' + day2 + ' ' + m1 + ' ' + y1;
                } else if (y1 === y2) {
                    name = day1 + ' ' + m1 + ' - ' + day2 + ' ' + m2 + ' ' + y1;
                } else {
                    name = day1 + ' ' + m1 + ' ' + y1 + ' - ' + day2 + ' ' + m2 + ' ' + y2;
                }
                $('#man_nama_periode').attr('placeholder', name);
            }
        }
    }
</script>

<?php 
include '../../includes/footer.php'; 
?>
