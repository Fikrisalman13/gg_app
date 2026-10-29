<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/printer_whitelist_helpers.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$lightThemeOptions = ['warning', 'light', 'lime', 'white'];
$isLightTheme = in_array($themeColor, $lightThemeOptions, true);
$headerTextClass = $isLightTheme ? 'text-dark' : 'text-white';
$reportError = '';
$selectedAssetId = isset($_GET['id_asset']) ? (int) $_GET['id_asset'] : 0;
$todayDate = new DateTimeImmutable('today');
$defaultEndDate = $todayDate->format('Y-m-d');
$defaultStartDate = $todayDate->modify('-1 month')->format('Y-m-d');
$startDate = $_GET['start_date'] ?? $defaultStartDate;
$endDate = $_GET['end_date'] ?? $defaultEndDate;
$chartLabels = [];
$chartIntervals = [];
$statsSummary = null;
$userOptions = [];
$performerStats = [];
$topPerformer = null;
$performerChartLabels = [];
$performerChartTotals = [];
$performerChartUsers = [];

function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }

    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 162);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultStartDate;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $defaultEndDate;
}
if ($startDate > $endDate) {
    $tempDate = $startDate;
    $startDate = $endDate;
    $endDate = $tempDate;
}

if (!ensurePrinterWhitelistTable($conn)) {
    $reportError = 'Gagal menyiapkan whitelist printer.';
}
if ($reportError === '' && !syncPrinterLateLogs($conn)) {
    $reportError = 'Gagal sinkronisasi log keterlambatan isi tinta.';
}

$reportData = [];
$sql = "SELECT
            ah.id_asset,
            ah.id_history,
            ah.created_at,
            owner_emp.nama_lengkap,
            a.kode_asset_seq,
            pw.jenis_tinta,
            ISNULL(performer_emp.nama_lengkap, ah.created_by) AS dikerjakan_oleh,
            pl.log_message AS late_fill_log,
            ah.note AS keterangan
        FROM dbo.asset_history ah
        INNER JOIN dbo.m_asset a ON ah.id_asset = a.id_asset
        INNER JOIN dbo.printer_user_whitelist pw ON a.id_asset = pw.id_asset AND pw.is_active = 1
        LEFT JOIN dbo.printer_isi_tinta_log pl ON ah.id_history = pl.id_history
        LEFT JOIN dbo.m_emp owner_emp ON a.id_emp = owner_emp.id_emp
        LEFT JOIN dbo.SMUserMs smu ON LTRIM(RTRIM(smu.UserName)) = LTRIM(RTRIM(ah.created_by))
        LEFT JOIN dbo.m_emp performer_emp ON smu.EmpId = performer_emp.id_emp
        WHERE a.id_kode = ?
          AND (? = 0 OR ah.id_asset = ?)
          AND a.id_status IN (1, 6)
          AND ah.new_status = 'Maintenance'
          AND LOWER(ISNULL(ah.note, '')) LIKE '%isi tinta%'
          AND ah.created_at >= ?
          AND ah.created_at < DATEADD(DAY, 1, ?)
        ORDER BY ah.created_at DESC, ah.id_history DESC";
$stmt = $reportError === '' ? sqlsrv_query($conn, $sql, [4, $selectedAssetId, $selectedAssetId, $startDate, $endDate]) : false;

if ($stmt === false && $reportError === '') {
    $sqlErrors = sqlsrv_errors();
    $reportError = 'Gagal mengambil report isi tinta.';
    if (!empty($sqlErrors[0]['message'])) {
        $reportError .= ' ' . $sqlErrors[0]['message'];
    }
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['created_at_obj'] = normalizePrinterSqlsrvDate($row['created_at'] ?? null);
        $row['interval_days'] = null;
        $reportData[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    $groupedByAsset = [];
    foreach ($reportData as $idx => $row) {
        $assetId = (int) ($row['id_asset'] ?? 0);
        if (!isset($groupedByAsset[$assetId])) {
            $groupedByAsset[$assetId] = [];
        }
        $groupedByAsset[$assetId][] = $idx;
    }

    foreach ($groupedByAsset as $indexes) {
        usort($indexes, function ($a, $b) use ($reportData) {
            $dateA = $reportData[$a]['created_at_obj'] instanceof DateTimeImmutable ? $reportData[$a]['created_at_obj']->getTimestamp() : 0;
            $dateB = $reportData[$b]['created_at_obj'] instanceof DateTimeImmutable ? $reportData[$b]['created_at_obj']->getTimestamp() : 0;
            return $dateA <=> $dateB;
        });

        $prevDate = null;
        foreach ($indexes as $idx) {
            $currentDate = $reportData[$idx]['created_at_obj'] ?? null;
            if ($prevDate instanceof DateTimeImmutable && $currentDate instanceof DateTimeImmutable) {
                $reportData[$idx]['interval_days'] = (int) $prevDate->setTime(0, 0, 0)->diff($currentDate->setTime(0, 0, 0))->format('%a');
            }
            $prevDate = $currentDate;
        }
    }
}

if ($selectedAssetId > 0 && !empty($reportData)) {
    $ascendingRows = $reportData;
    usort($ascendingRows, function ($a, $b) {
        $dateA = $a['created_at_obj'] instanceof DateTimeImmutable ? $a['created_at_obj']->getTimestamp() : 0;
        $dateB = $b['created_at_obj'] instanceof DateTimeImmutable ? $b['created_at_obj']->getTimestamp() : 0;
        return $dateA <=> $dateB;
    });

    $previousDate = null;
    $intervalAccumulator = [];
    $fillDates = [];

    foreach ($ascendingRows as $row) {
        $createdAt = $row['created_at_obj'] ?? null;
        if (!$createdAt instanceof DateTimeImmutable) {
            continue;
        }

        $fillDates[] = $createdAt->format('d-m-Y');
        $chartLabels[] = $createdAt->format('d-m-Y');

        if ($previousDate instanceof DateTimeImmutable) {
            $intervalDays = (int) $previousDate->setTime(0, 0, 0)->diff($createdAt->setTime(0, 0, 0))->format('%a');
            $chartIntervals[] = $intervalDays;
            $intervalAccumulator[] = $intervalDays;
        } else {
            $chartIntervals[] = 0;
        }

        $previousDate = $createdAt;
    }

    $lastRow = $ascendingRows[count($ascendingRows) - 1];
    $statsSummary = [
        'nama_lengkap' => (string) ($lastRow['nama_lengkap'] ?? '-'),
        'kode_asset_seq' => (string) ($lastRow['kode_asset_seq'] ?? '-'),
        'jenis_tinta' => (string) ($lastRow['jenis_tinta'] ?? '-'),
        'total_pengisian' => count($ascendingRows),
        'rata_interval' => !empty($intervalAccumulator) ? round(array_sum($intervalAccumulator) / count($intervalAccumulator), 1) : 0,
        'tanggal_terakhir' => $lastRow['created_at_obj'] instanceof DateTimeImmutable ? $lastRow['created_at_obj']->format('d-m-Y H:i') : '-',
        'tanggal_list' => implode(', ', $fillDates),
    ];
}

foreach ($reportData as $row) {
    $name = trim((string) ($row['nama_lengkap'] ?? ''));
    if ($name !== '') {
        $userOptions[$name] = $name;
    }

    $performerName = trim((string) ($row['dikerjakan_oleh'] ?? ''));
    if ($performerName !== '') {
        if (!isset($performerStats[$performerName])) {
            $performerStats[$performerName] = [
                'nama' => $performerName,
                'total' => 0,
                'users' => [],
            ];
        }
        $performerStats[$performerName]['total']++;
        if ($name !== '') {
            $performerStats[$performerName]['users'][$name] = $name;
        }
    }
}
ksort($userOptions, SORT_NATURAL | SORT_FLAG_CASE);

if (!empty($performerStats)) {
    foreach ($performerStats as &$performerRow) {
        ksort($performerRow['users'], SORT_NATURAL | SORT_FLAG_CASE);
    }
    unset($performerRow);

    usort($performerStats, function ($a, $b) {
        if ($a['total'] === $b['total']) {
            return strcasecmp($a['nama'], $b['nama']);
        }
        return $b['total'] <=> $a['total'];
    });

    $topPerformer = $performerStats[0];

    foreach ($performerStats as $performerRow) {
        $performerChartLabels[] = $performerRow['nama'];
        $performerChartTotals[] = (int) $performerRow['total'];
        $performerChartUsers[] = count($performerRow['users']);
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<style>
    .summary-box {
        border-radius: 0.5rem;
        padding: 1rem;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        height: 100%;
    }

    .summary-box.summary-blue {
        background: linear-gradient(135deg, #eaf4ff 0%, #d8ebff 100%);
        border-color: #9ecbff;
    }

    .summary-box.summary-green {
        background: linear-gradient(135deg, #e9f9ef 0%, #d5f2e0 100%);
        border-color: #9fdfb4;
    }

    .summary-box.summary-orange {
        background: linear-gradient(135deg, #fff4e5 0%, #ffe8c7 100%);
        border-color: #ffd08a;
    }

    .summary-box.summary-red {
        background: linear-gradient(135deg, #ffebee 0%, #ffd9df 100%);
        border-color: #ffb0bd;
    }

    .summary-box.summary-cyan {
        background: linear-gradient(135deg, #e8fbff 0%, #d2f5fb 100%);
        border-color: #9ddfeb;
    }

    .summary-box.summary-purple {
        background: linear-gradient(135deg, #f3ecff 0%, #e6dbff 100%);
        border-color: #ccb2ff;
    }

    .summary-box.summary-yellow {
        background: linear-gradient(135deg, #fff9df 0%, #fff0b8 100%);
        border-color: #f3d96f;
    }

    .summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .summary-label {
        font-size: 0.85rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .date-history {
        font-size: 0.9rem;
        line-height: 1.7;
        white-space: normal;
    }

    .filter-panel {
        background: linear-gradient(135deg, #f7f8fc 0%, #eef2f7 100%);
        border: 1px solid #d9e2ec;
        border-radius: 0.75rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }

    .chart-panel {
        background: #ffffff;
        border: 1px solid #e5e9f0;
        border-radius: 0.75rem;
        box-shadow: 0 10px 30px rgba(31, 45, 61, 0.06);
    }

    .stats-section-title {
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: 0.75rem;
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Report Isi Tinta</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card card-primary">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> <?php echo $headerTextClass; ?>">
                        <h3 class="card-title">
                            <i class="fas fa-file-alt mr-1"></i>
                            Histori Pengisian Tinta
                        </h3>
                        <a href="/gg_app/pages/issues/monitoring.php" class="btn btn-secondary btn-sm float-right">
                            <i class="fas fa-arrow-left"></i> Kembali Monitoring
                        </a>
                        <a href="/gg_app/pages/issues/manage_printer_whitelist.php" class="btn btn-warning btn-sm float-right mr-2">
                            <i class="fas fa-user-cog"></i> Add User Printer
                        </a>
                    </div>

                    <div class="card-body">
                        <?php if ($reportError !== ''): ?>
                            <div class="alert alert-danger mb-0">
                                <?php echo htmlspecialchars($reportError); ?>
                            </div>
                        <?php else: ?>
                            <form method="get" class="card card-outline card-secondary mb-3">
                                <?php if ($selectedAssetId > 0): ?>
                                    <input type="hidden" name="id_asset" value="<?php echo (int) $selectedAssetId; ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label for="start_date">Start Date</label>
                                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="end_date">End Date</label>
                                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary mr-2">
                                                <i class="fas fa-filter"></i> Filter
                                            </button>
                                            <a
                                                href="/gg_app/pages/issues/report_isi_tinta_export_pdf.php?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?><?php echo $selectedAssetId > 0 ? '&id_asset=' . (int) $selectedAssetId : ''; ?>"
                                                target="_blank"
                                                class="btn btn-danger mr-2"
                                            >
                                                <i class="fas fa-file-pdf"></i> Export PDF
                                            </a>
                                            <a href="/gg_app/pages/issues/report_isi_tinta.php<?php echo $selectedAssetId > 0 ? '?id_asset=' . (int) $selectedAssetId : ''; ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-sync-alt"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted mt-2">Default report menampilkan 1 bulan terakhir.</small>
                                </div>
                            </form>

                            <?php if ($selectedAssetId > 0): ?>
                                <div class="alert alert-info">
                                    Menampilkan histori isi tinta untuk asset terpilih.
                                    <a href="/gg_app/pages/issues/report_isi_tinta.php" class="alert-link">Lihat semua report</a>
                                </div>
                            <?php endif; ?>
                            <?php if ($selectedAssetId === 0): ?>
                                <div class="stats-section-title">Filter Grafik User</div>
                                <div class="row mb-4">
                                    <div class="col-lg-4 mb-3 mb-lg-0">
                                        <div class="card filter-panel h-100">
                                            <div class="card-header">
                                                <h3 class="card-title">Filter Grafik User</h3>
                                            </div>
                                            <div class="card-body">
                                                <label for="filterUsers">Nama Lengkap</label>
                                                <select id="filterUsers" class="form-control" multiple size="8">
                                                    <?php foreach ($userOptions as $userName): ?>
                                                        <option value="<?php echo htmlspecialchars($userName); ?>">
                                                            <?php echo htmlspecialchars($userName); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="mt-2">
                                                    <button type="button" id="btnSelectAllUsers" class="btn btn-outline-primary btn-sm mr-2">
                                                        <i class="fas fa-check-square"></i> Pilih Semua
                                                    </button>
                                                    <button type="button" id="btnClearUserFilter" class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-eraser"></i> Kosongkan Pilihan
                                                    </button>
                                                </div>
                                                <small class="form-text text-muted">Klik `Kosongkan Pilihan` untuk menampilkan semua user.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="card chart-panel h-100">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <div class="summary-box summary-blue">
                                                            <div class="summary-value" id="overallTotalFill">0</div>
                                                            <div class="summary-label">Total Isi Tinta</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="summary-box summary-green">
                                                            <div class="summary-value" id="overallAvgInterval">0 hari</div>
                                                            <div class="summary-label">Rata-rata Interval</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div style="height: 320px;">
                                                    <canvas id="userSummaryChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($topPerformer): ?>
                                <div class="stats-section-title">Statistik Pelaksana</div>
                                <div class="row mb-3">
                                    <div class="col-md-4 col-sm-6 mb-3">
                                        <div class="summary-box summary-red">
                                            <div class="summary-value"><?php echo htmlspecialchars((string) $topPerformer['nama']); ?></div>
                                            <div class="summary-label">Pelaksana Terbanyak</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6 mb-3">
                                        <div class="summary-box summary-orange">
                                            <div class="summary-value"><?php echo number_format((int) $topPerformer['total']); ?>x</div>
                                            <div class="summary-label">Total Dikerjakan</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-12 mb-3">
                                        <div class="summary-box summary-cyan">
                                            <div class="summary-value" style="font-size:1rem;"><?php echo htmlspecialchars(implode(', ', array_values($topPerformer['users']))); ?></div>
                                            <div class="summary-label">User Yang Pernah Ditangani</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4 col-sm-6 mb-3">
                                        <div class="summary-box summary-purple">
                                            <div class="summary-value"><?php echo number_format(count($performerStats)); ?></div>
                                            <div class="summary-label">Total Pelaksana</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6 mb-3">
                                        <div class="summary-box summary-yellow">
                                            <div class="summary-value"><?php echo number_format((int) $topPerformer['total']); ?>x</div>
                                            <div class="summary-label">Pekerjaan Tertinggi</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-12 mb-3">
                                        <div class="summary-box summary-green">
                                            <div class="summary-value"><?php echo number_format(count($topPerformer['users'])); ?></div>
                                            <div class="summary-label">Jumlah User Ditangani Top Pelaksana</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card card-outline card-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">Grafik Ranking Pelaksana Isi Tinta</h3>
                                    </div>
                                    <div class="card-body">
                                        <div style="height: 340px;">
                                            <canvas id="performerRankingChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="card card-outline card-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">Ranking Pelaksana Isi Tinta</h3>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>No</th>
                                                    <th>Dikerjakan Oleh</th>
                                                    <th>Total</th>
                                                    <th>User Yang Pernah Ditangani</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($performerStats as $performerIndex => $performer): ?>
                                                    <tr>
                                                        <td><?php echo $performerIndex + 1; ?></td>
                                                        <td><?php echo htmlspecialchars((string) $performer['nama']); ?></td>
                                                        <td><?php echo number_format((int) $performer['total']); ?></td>
                                                        <td><?php echo htmlspecialchars(implode(', ', array_values($performer['users']))); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($statsSummary): ?>
                                <div class="stats-section-title">Statistik Asset</div>
                                <div class="row mb-3">
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="summary-box summary-blue">
                                            <div class="summary-value"><?php echo number_format((int) $statsSummary['total_pengisian']); ?></div>
                                            <div class="summary-label">Total Isi Tinta</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="summary-box summary-green">
                                            <div class="summary-value"><?php echo htmlspecialchars((string) $statsSummary['rata_interval']); ?> hari</div>
                                            <div class="summary-label">Rata-rata Interval</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="summary-box summary-orange">
                                            <div class="summary-value"><?php echo htmlspecialchars((string) $statsSummary['kode_asset_seq']); ?></div>
                                            <div class="summary-label">Kode Asset</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="summary-box summary-purple">
                                            <div class="summary-value"><?php echo htmlspecialchars((string) $statsSummary['jenis_tinta']); ?></div>
                                            <div class="summary-label">Jenis Tinta</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card card-outline card-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">Statistik Pengisian Tinta</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3 mb-md-0">
                                                <strong>Nama Lengkap</strong><br>
                                                <?php echo htmlspecialchars((string) $statsSummary['nama_lengkap']); ?>
                                            </div>
                                            <div class="col-md-4 mb-3 mb-md-0">
                                                <strong>Tanggal Isi Tinta Terakhir</strong><br>
                                                <?php echo htmlspecialchars((string) $statsSummary['tanggal_terakhir']); ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Riwayat Tanggal Isi Tinta</strong><br>
                                                <div class="date-history"><?php echo htmlspecialchars((string) $statsSummary['tanggal_list']); ?></div>
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <canvas id="intervalChart" height="95"></canvas>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table id="reportIsiTintaTable" class="table table-hover table-sm nowrap" style="width:100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Tanggal Isi Tinta</th>
                                            <th>Nama Lengkap</th>
                                            <th>Kode Asset</th>
                                            <th>Jenis Tinta</th>
                                            <th>Dikerjakan Oleh</th>
                                            <th>Log Reminder</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData as $index => $row): ?>
                                            <?php
                                            $createdAt = $row['created_at_obj'] ?? $row['created_at'];
                                            $formattedDate = '-';
                                            if ($createdAt instanceof DateTimeInterface) {
                                                $formattedDate = $createdAt->format('d-m-Y H:i');
                                            } elseif (is_string($createdAt) && trim($createdAt) !== '') {
                                                $formattedDate = $createdAt;
                                            }
                                            ?>
                                            <tr
                                                data-user-name="<?php echo htmlspecialchars((string) ($row['nama_lengkap'] ?? '')); ?>"
                                                data-interval-days="<?php echo htmlspecialchars((string) ($row['interval_days'] ?? '')); ?>"
                                            >
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($formattedDate); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['nama_lengkap'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['kode_asset_seq'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['jenis_tinta'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['dikerjakan_oleh'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) (($row['late_fill_log'] ?? '') !== '' ? $row['late_fill_log'] : '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['keterangan'] ?? '-')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/js/Chart.min.js"></script>

<script>
$(function () {
    function buildColorPalette(labels, alpha) {
        var baseColors = [
            [33, 150, 243],
            [76, 175, 80],
            [255, 152, 0],
            [233, 30, 99],
            [156, 39, 176],
            [0, 188, 212],
            [244, 67, 54],
            [139, 195, 74],
            [255, 193, 7],
            [63, 81, 181],
            [0, 150, 136],
            [121, 85, 72]
        ];

        return labels.map(function (_, index) {
            var color = baseColors[index % baseColors.length];
            return 'rgba(' + color[0] + ', ' + color[1] + ', ' + color[2] + ', ' + alpha + ')';
        });
    }

    function buildBorderPalette(labels) {
        return buildColorPalette(labels, 1);
    }

    var reportTable = $('#reportIsiTintaTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, 'desc']],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Data tidak ditemukan',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: 'Berikutnya',
                previous: 'Sebelumnya'
            }
        }
    });

    var chartLabels = <?php echo json_encode($chartLabels); ?>;
    var chartIntervals = <?php echo json_encode($chartIntervals); ?>;
    var performerChartLabels = <?php echo json_encode($performerChartLabels); ?>;
    var performerChartTotals = <?php echo json_encode($performerChartTotals); ?>;
    var performerChartUsers = <?php echo json_encode($performerChartUsers); ?>;

    if (chartLabels.length > 0 && document.getElementById('intervalChart')) {
        new Chart(document.getElementById('intervalChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Interval isi tinta (hari)',
                    data: chartIntervals,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.18)',
                    fill: true,
                    lineTension: 0.2,
                    pointRadius: 4,
                    pointBackgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }]
                }
            }
        });
    }

    if (performerChartLabels.length > 0 && document.getElementById('performerRankingChart')) {
        var performerBarColors = buildColorPalette(performerChartLabels, 0.78);
        var performerBorderColors = buildBorderPalette(performerChartLabels);
        var performerUserPointColors = buildColorPalette(performerChartLabels, 1);

        new Chart(document.getElementById('performerRankingChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: performerChartLabels,
                datasets: [{
                    label: 'Total Isi Tinta',
                    data: performerChartTotals,
                    backgroundColor: performerBarColors,
                    borderColor: performerBorderColors,
                    borderWidth: 1,
                    yAxisID: 'y-axis-1'
                }, {
                    label: 'Jumlah User Ditangani',
                    data: performerChartUsers,
                    type: 'line',
                    fill: false,
                    borderColor: '#2f4858',
                    backgroundColor: '#2f4858',
                    pointBackgroundColor: performerUserPointColors,
                    pointBorderColor: performerUserPointColors,
                    pointRadius: 4,
                    lineTension: 0.2,
                    yAxisID: 'y-axis-2'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        id: 'y-axis-1',
                        position: 'left',
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }, {
                        id: 'y-axis-2',
                        position: 'right',
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        },
                        gridLines: {
                            drawOnChartArea: false
                        }
                    }]
                }
            }
        });
    }

    var userSummaryChart = null;
    var filterUsers = $('#filterUsers');

    function getSelectedUsers() {
        return filterUsers.length ? (filterUsers.val() || []) : [];
    }

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'reportIsiTintaTable') {
            return true;
        }

        var selectedUsers = getSelectedUsers();
        if (!selectedUsers.length) {
            return true;
        }

        var rowNode = reportTable.row(dataIndex).node();
        var rowUser = rowNode ? $(rowNode).data('user-name') : '';
        return selectedUsers.indexOf(String(rowUser)) !== -1;
    });

    function buildOverallUserChart() {
        if (!document.getElementById('userSummaryChart')) {
            return;
        }

        var selectedUsers = getSelectedUsers();
        var statsByUser = {};
        var totalFill = 0;
        var intervalTotal = 0;
        var intervalCount = 0;

        $('#reportIsiTintaTable tbody tr').each(function () {
            var $row = $(this);
            var userName = String($row.data('user-name') || '');
            var intervalRaw = $row.data('interval-days');
            var intervalDays = intervalRaw === '' || typeof intervalRaw === 'undefined' ? null : Number(intervalRaw);

            if (selectedUsers.length && selectedUsers.indexOf(userName) === -1) {
                return;
            }

            if (!statsByUser[userName]) {
                statsByUser[userName] = {
                    total_fill: 0,
                    interval_total: 0,
                    interval_count: 0
                };
            }

            statsByUser[userName].total_fill += 1;
            totalFill += 1;

            if (intervalDays !== null && !isNaN(intervalDays)) {
                statsByUser[userName].interval_total += intervalDays;
                statsByUser[userName].interval_count += 1;
                intervalTotal += intervalDays;
                intervalCount += 1;
            }
        });

        var labels = Object.keys(statsByUser);
        var totalFillData = labels.map(function (label) {
            return statsByUser[label].total_fill;
        });
        var avgIntervalData = labels.map(function (label) {
            if (!statsByUser[label].interval_count) {
                return 0;
            }
            return Number((statsByUser[label].interval_total / statsByUser[label].interval_count).toFixed(1));
        });

        $('#overallTotalFill').text(totalFill);
        $('#overallAvgInterval').text((intervalCount ? (intervalTotal / intervalCount).toFixed(1) : 0) + ' hari');

        var userBarColors = buildColorPalette(labels, 0.78);
        var userBorderColors = buildBorderPalette(labels);
        var userPointColors = buildColorPalette(labels, 1);
        var ctx = document.getElementById('userSummaryChart').getContext('2d');
        if (userSummaryChart) {
            userSummaryChart.destroy();
        }

        userSummaryChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Isi Tinta',
                    data: totalFillData,
                    backgroundColor: userBarColors,
                    borderColor: userBorderColors,
                    borderWidth: 1,
                    yAxisID: 'y-axis-1'
                }, {
                    label: 'Rata-rata Interval (hari)',
                    data: avgIntervalData,
                    type: 'line',
                    fill: false,
                    borderColor: '#222f3e',
                    backgroundColor: '#222f3e',
                    pointBackgroundColor: userPointColors,
                    pointBorderColor: userPointColors,
                    pointRadius: 4,
                    lineTension: 0.2,
                    yAxisID: 'y-axis-2'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        id: 'y-axis-1',
                        position: 'left',
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }, {
                        id: 'y-axis-2',
                        position: 'right',
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        },
                        gridLines: {
                            drawOnChartArea: false
                        }
                    }]
                }
            }
        });
    }

    if (filterUsers.length) {
        $('#btnSelectAllUsers').on('click', function () {
            filterUsers.find('option').prop('selected', true);
            filterUsers.trigger('change');
        });

        $('#btnClearUserFilter').on('click', function () {
            filterUsers.val([]);
            filterUsers.trigger('change');
        });

        filterUsers.on('change', function () {
            reportTable.draw();
            buildOverallUserChart();
        });
        buildOverallUserChart();
    }
});
</script>
