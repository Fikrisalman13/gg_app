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
$today = new DateTimeImmutable('today');
$monitoringError = '';

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

function buildReminderInfo(?DateTimeImmutable $lastFillDate, ?int $intervalDays, DateTimeImmutable $today)
{
    if (!$lastFillDate) {
        return [
            'label' => 'Belum ada histori isi tinta',
            'badge_class' => 'badge-secondary',
            'row_class' => '',
            'due_date' => null,
            'remaining_days' => null,
        ];
    }

    if ($intervalDays === null || $intervalDays <= 0) {
        return [
            'label' => 'Menunggu histori isi tinta berikutnya',
            'badge_class' => 'badge-secondary',
            'row_class' => '',
            'due_date' => null,
            'remaining_days' => null,
        ];
    }

    $fillDateOnly = $lastFillDate->setTime(0, 0, 0);
    $daysSinceLastFill = (int) $fillDateOnly->diff($today)->format('%r%a');
    $remainingDays = $intervalDays - $daysSinceLastFill;
    $dueDate = $fillDateOnly->modify('+' . $intervalDays . ' days');

    if ($remainingDays > 0) {
        return [
            'label' => $remainingDays . ' hari lagi',
            'badge_class' => 'badge-warning',
            'row_class' => '',
            'due_date' => $dueDate,
            'remaining_days' => $remainingDays,
        ];
    }

    if ($remainingDays === 0) {
        return [
            'label' => 'Harus isi tinta hari ini',
            'badge_class' => 'badge-danger',
            'row_class' => 'table-danger',
            'due_date' => $dueDate,
            'remaining_days' => 0,
        ];
    }

    $overdueDays = abs($remainingDays);
    return [
        'label' => 'Terlambat ' . $overdueDays . ' hari',
        'badge_class' => 'badge-danger',
        'row_class' => 'table-danger',
        'due_date' => $dueDate,
        'remaining_days' => $remainingDays,
    ];
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 162);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

if (!ensurePrinterWhitelistTable($conn)) {
    $monitoringError = 'Gagal menyiapkan whitelist printer.';
}
if ($monitoringError === '' && !syncPrinterLateLogs($conn)) {
    $monitoringError = 'Gagal sinkronisasi log keterlambatan isi tinta.';
}

$monitoringData = [];
$sql = "WITH tinta_history AS (
            SELECT
                ah.id_history,
                ah.id_asset,
                ah.created_at,
                ah.note,
                ROW_NUMBER() OVER (PARTITION BY ah.id_asset ORDER BY ah.created_at DESC, ah.id_history DESC) AS rn
            FROM dbo.asset_history ah
            WHERE ah.new_status = 'Maintenance'
              AND LOWER(ISNULL(ah.note, '')) LIKE '%isi tinta%'
        ),
        tinta_pivot AS (
            SELECT
                id_asset,
                MAX(CASE WHEN rn = 1 THEN id_history END) AS last_fill_history_id,
                MAX(CASE WHEN rn = 1 THEN created_at END) AS last_fill_date,
                MAX(CASE WHEN rn = 1 THEN note END) AS last_fill_note,
                MAX(CASE WHEN rn = 2 THEN created_at END) AS prev_fill_date,
                MAX(CASE WHEN rn = 3 THEN created_at END) AS prev_prev_fill_date
            FROM tinta_history
            WHERE rn <= 3
            GROUP BY id_asset
        )
        SELECT
            a.id_asset,
            a.kode_asset_seq,
            a.keterangan AS asset_keterangan,
            mk.nama_merk,
            tp.nama_tipe,
            pw.jenis_tinta,
            st.nama_status,
            st.keterangan AS status_keterangan,
            e.nama_lengkap,
            pl.log_message AS late_fill_log,
            tpv.last_fill_date,
            tpv.prev_fill_date,
            tpv.last_fill_note
        FROM dbo.m_asset a
        INNER JOIN dbo.printer_user_whitelist pw ON a.id_asset = pw.id_asset AND pw.is_active = 1
        LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
        LEFT JOIN dbo.m_merk mk ON a.id_merk = mk.id_merk
        LEFT JOIN dbo.m_tipe tp ON a.id_tipe = tp.id_tipe
        LEFT JOIN dbo.m_status st ON a.id_status = st.id_status
        LEFT JOIN tinta_pivot tpv ON a.id_asset = tpv.id_asset
        LEFT JOIN dbo.printer_isi_tinta_log pl ON tpv.last_fill_history_id = pl.id_history
        WHERE a.id_kode = ?
          AND a.id_status IN (1, 6)
        ORDER BY a.kode_asset_seq ASC, a.id_asset ASC";
$stmt = $monitoringError === '' ? sqlsrv_query($conn, $sql, [4]) : false;

if ($stmt === false && $monitoringError === '') {
    $sqlErrors = sqlsrv_errors();
    $monitoringError = 'Gagal mengambil data monitoring isi tinta.';
    if (!empty($sqlErrors[0]['message'])) {
        $monitoringError .= ' ' . $sqlErrors[0]['message'];
    }
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $lastFillDate = normalizePrinterSqlsrvDate($row['last_fill_date'] ?? null);
        $prevFillDate = normalizePrinterSqlsrvDate($row['prev_fill_date'] ?? null);

        $intervalDays = null;
        if ($lastFillDate && $prevFillDate) {
            $intervalDays = (int) $prevFillDate->setTime(0, 0, 0)->diff($lastFillDate->setTime(0, 0, 0))->format('%a');
            if ($intervalDays === 0) {
                $intervalDays = 1;
            }
        }

        $reminderInfo = buildReminderInfo($lastFillDate, $intervalDays, $today);
        $row['last_fill_date_obj'] = $lastFillDate;
        $row['prev_fill_date_obj'] = $prevFillDate;
        $row['interval_days'] = $intervalDays;
        $row['reminder_info'] = $reminderInfo;
        $monitoringData[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

$totalAsset = count($monitoringData);
$dueTodayCount = 0;
$needAttentionCount = 0;
foreach ($monitoringData as $item) {
    $remainingDays = $item['reminder_info']['remaining_days'];
    if ($remainingDays !== null && $remainingDays <= 0) {
        $dueTodayCount++;
    }
    if ($remainingDays !== null && $remainingDays <= 2) {
        $needAttentionCount++;
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<style>
    .monitoring-table td,
    .monitoring-table th {
        vertical-align: middle;
    }

    .monitoring-table td.wrap-text {
        white-space: normal !important;
        word-break: break-word;
        min-width: 180px;
    }

    .badge-reminder {
        font-size: 0.78rem;
        padding: 0.35rem 0.65rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .summary-box {
        border-radius: 0.5rem;
        padding: 1rem;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        height: 100%;
    }

    .summary-filter {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .summary-filter:hover {
        border-color: #007bff;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 123, 255, 0.12);
    }

    .summary-filter.is-active {
        border-color: #007bff;
        background: #eaf4ff;
        box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, 0.12);
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

    .small-meta {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .reminder-link {
        text-decoration: none;
    }

    .reminder-link:hover .badge-reminder,
    .reminder-link:focus .badge-reminder {
        opacity: 0.9;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Monitoring Isi Tinta</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row mb-3">
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="summary-box summary-filter is-active" data-filter-type="all">
                            <div class="summary-value"><?php echo number_format($totalAsset); ?></div>
                            <div class="summary-label">Total Printer</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="summary-box summary-filter" data-filter-type="attention">
                            <div class="summary-value"><?php echo number_format($needAttentionCount); ?></div>
                            <div class="summary-label">Reminder <= 2 Hari</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="summary-box summary-filter" data-filter-type="due">
                            <div class="summary-value"><?php echo number_format($dueTodayCount); ?></div>
                            <div class="summary-label">Harus Isi Tinta</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="d-flex align-items-center h-100">
                            <button type="button" id="btnResetMonitoringFilter" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sync-alt"></i> Reset Filter
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> <?php echo $headerTextClass; ?>">
                        <h3 class="card-title">
                            <i class="fas fa-fill-drip mr-1"></i>
                            Monitoring Reminder Isi Tinta
                        </h3>
                        <a href="/gg_app/pages/issues/report_isi_tinta.php" class="btn btn-success btn-sm float-right">
                            <i class="fas fa-file-alt"></i> Report Isi Tinta
                        </a>
                        <a href="/gg_app/pages/issues/manage_printer_whitelist.php" class="btn btn-warning btn-sm float-right mr-2">
                            <i class="fas fa-user-cog"></i> Add User Printer
                        </a>
                    </div>

                    <div class="card-body">
                        <?php if ($monitoringError !== ''): ?>
                            <div class="alert alert-danger mb-0">
                                <?php echo htmlspecialchars($monitoringError); ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="monitoringTable" class="table table-hover table-sm nowrap monitoring-table" style="width:100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Kode Asset</th>
                                            <th>Merk</th>
                                            <th>Tipe</th>
                                            <th>Jenis Tinta</th>
                                            <th>Status</th>
                                            <th>Nama Lengkap</th>
                                            <th>Isi Tinta Terakhir</th>
                                            <th>Jarak Isi Tinta</th>
                                            <th>Reminder</th>
                                            <th>Log Reminder</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monitoringData as $index => $row): ?>
                                            <?php
                                            $lastFillDate = $row['last_fill_date_obj'];
                                            $intervalDays = $row['interval_days'];
                                            $reminder = $row['reminder_info'];
                                            $statusName = trim((string) ($row['nama_status'] ?? ''));
                                            $assetNote = trim((string) ($row['asset_keterangan'] ?? ''));
                                            $historyNote = trim((string) ($row['last_fill_note'] ?? ''));
                                            $lateFillLog = trim((string) ($row['late_fill_log'] ?? ''));
                                            $dueDate = $reminder['due_date'] instanceof DateTimeImmutable ? $reminder['due_date']->format('d-m-Y') : '-';
                                            $reportUrl = '/gg_app/pages/issues/report_isi_tinta.php?id_asset=' . urlencode((string) ($row['id_asset'] ?? ''));
                                            ?>
                                            <tr
                                                class="<?php echo htmlspecialchars($reminder['row_class']); ?>"
                                                data-remaining-days="<?php echo htmlspecialchars((string) ($reminder['remaining_days'] ?? '')); ?>"
                                            >
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['kode_asset_seq'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['nama_merk'] ?? '-')); ?></td>
                                                <td class="wrap-text"><?php echo htmlspecialchars((string) ($row['nama_tipe'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['jenis_tinta'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars($statusName !== '' ? $statusName : '-'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['nama_lengkap'] ?? '-')); ?></td>
                                                <td>
                                                    <?php echo $lastFillDate ? htmlspecialchars($lastFillDate->format('d-m-Y H:i')) : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php echo $intervalDays !== null ? htmlspecialchars($intervalDays . ' hari') : '-'; ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($reportUrl); ?>" class="reminder-link" title="Lihat histori isi tinta asset ini">
                                                        <span class="badge badge-reminder <?php echo htmlspecialchars($reminder['badge_class']); ?>">
                                                            <i class="fas fa-bell"></i>
                                                            <?php echo htmlspecialchars($reminder['label']); ?>
                                                        </span>
                                                    </a>
                                                    <div class="small-meta mt-1">Target: <?php echo htmlspecialchars($dueDate); ?></div>
                                                </td>
                                                <td class="wrap-text"><?php echo htmlspecialchars($lateFillLog !== '' ? $lateFillLog : '-'); ?></td>
                                                <td class="wrap-text">
                                                    <?php
                                                    $displayNote = $historyNote !== '' ? $historyNote : ($assetNote !== '' ? $assetNote : '-');
                                                    echo htmlspecialchars($displayNote);
                                                    ?>
                                                </td>
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

<script>
$(function () {
    var monitoringFilter = 'all';
    var monitoringTable = $('#monitoringTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[8, 'asc'], [1, 'asc']],
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

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'monitoringTable') {
            return true;
        }

        if (monitoringFilter === 'all') {
            return true;
        }

        var rowNode = monitoringTable.row(dataIndex).node();
        if (!rowNode) {
            return true;
        }

        var remainingRaw = $(rowNode).data('remaining-days');
        if (remainingRaw === '' || typeof remainingRaw === 'undefined') {
            return false;
        }

        var remainingDays = Number(remainingRaw);
        if (isNaN(remainingDays)) {
            return false;
        }

        if (monitoringFilter === 'due') {
            return remainingDays <= 0;
        }

        if (monitoringFilter === 'attention') {
            return remainingDays <= 2;
        }

        return true;
    });

    function applySummaryFilter(filterType) {
        monitoringFilter = filterType;
        $('.summary-filter').removeClass('is-active');
        $('.summary-filter[data-filter-type="' + filterType + '"]').addClass('is-active');
        monitoringTable.draw();
    }

    $('.summary-filter').on('click', function () {
        applySummaryFilter($(this).data('filter-type'));
    });

    $('#btnResetMonitoringFilter').on('click', function () {
        applySummaryFilter('all');
    });
});
</script>
