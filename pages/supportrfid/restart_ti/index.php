<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_path' => '/gg_app/',
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ]);
}

if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$appRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs'), '/\\') . '/gg_app';
include $appRoot . '/koneksi.php';

date_default_timezone_set('Asia/Jakarta');

$message = null;
$isSuccess = null;
$selectedLabel = 'Service';
$logError = null;
$today = date('Y-m-d');
$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['startdate'] ?? ''))
    ? (string) $_GET['startdate']
    : $today;
$endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['enddate'] ?? ''))
    ? (string) $_GET['enddate']
    : $today;

$createLogTableSql = "
IF OBJECT_ID('dbo.restart_mqtt', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.restart_mqtt (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        restart_at DATETIME2(0) NOT NULL,
        restart_date DATE NOT NULL,
        restart_time TIME(0) NOT NULL,
        item_key NVARCHAR(80) NOT NULL,
        item_label NVARCHAR(160) NOT NULL,
        service_name NVARCHAR(160) NOT NULL,
        user_id INT NULL,
        username NVARCHAR(160) NOT NULL
    )
END
";
sqlsrv_query($conn, $createLogTableSql);

if (isset($_SESSION['restart_ti_flash']) && is_array($_SESSION['restart_ti_flash'])) {
    $message = (string) ($_SESSION['restart_ti_flash']['message'] ?? '');
    $isSuccess = (bool) ($_SESSION['restart_ti_flash']['is_success'] ?? false);
    $logError = isset($_SESSION['restart_ti_flash']['log_error'])
        ? (string) $_SESSION['restart_ti_flash']['log_error']
        : null;
    unset($_SESSION['restart_ti_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = require __DIR__ . '/config.php';
    $services = $config['services'] ?? [];
    $selectedKey = (string) ($_POST['service_key'] ?? 'ti_picking');

    if (!extension_loaded('ssh2')) {
        $message = 'Extension ssh2 belum aktif di PHP.';
        $isSuccess = false;
    } elseif (!isset($services[$selectedKey])) {
        $message = 'Menu restart tidak valid.';
        $isSuccess = false;
    } else {
        $selectedLabel = (string) ($services[$selectedKey]['label'] ?? 'Service');
        $connection = @ssh2_connect($config['host'], (int) $config['port']);
        if (!$connection) {
            $message = 'Gagal konek ke server SSH.';
            $isSuccess = false;
        } elseif (!@ssh2_auth_password($connection, $config['username'], $config['password'])) {
            $message = 'Login SSH gagal. Cek username/password.';
            $isSuccess = false;
        } else {
            $service = preg_replace('/[^a-zA-Z0-9._@-]/', '', (string) ($services[$selectedKey]['service'] ?? ''));
            $sudoPassword = str_replace("'", "'\"'\"'", (string) $config['sudo_password']);
            $command = "echo '{$sudoPassword}' | sudo -S -p '' systemctl restart {$service}";

            $stream = @ssh2_exec($connection, $command, null, ['LANG' => 'C'], 80, 24, SSH2_TERM_UNIT_CHARS);
            if (!$stream) {
                $message = 'Perintah restart gagal dikirim ke server.';
                $isSuccess = false;
            } else {
                $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
                stream_set_blocking($stream, true);
                stream_set_blocking($errorStream, true);

                $output = trim((string) stream_get_contents($stream));
                $errorOutput = trim((string) stream_get_contents($errorStream));

                fclose($stream);
                fclose($errorStream);

                if ($errorOutput !== '') {
                    $message = "Restart gagal: {$errorOutput}";
                    $isSuccess = false;
                } else {
                    $message = $output !== ''
                        ? "{$selectedLabel} berhasil direstart. Output: {$output}"
                        : "{$selectedLabel} berhasil direstart.";
                    $isSuccess = true;

                    $now = new DateTimeImmutable('now');
                    $insertLogSql = "
                        INSERT INTO dbo.restart_mqtt
                            (restart_at, restart_date, restart_time, item_key, item_label, service_name, user_id, username)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    $insertLogParams = [
                        $now->format('Y-m-d H:i:s'),
                        $now->format('Y-m-d'),
                        $now->format('H:i:s'),
                        $selectedKey,
                        $selectedLabel,
                        (string) ($services[$selectedKey]['service'] ?? ''),
                        (int) ($_SESSION['UserId'] ?? 0),
                        (string) ($_SESSION['UserName'] ?? 'Unknown'),
                    ];
                    $insertLogStmt = sqlsrv_query($conn, $insertLogSql, $insertLogParams);
                    if (!$insertLogStmt) {
                        $logError = 'Log restart gagal disimpan: ' . print_r(sqlsrv_errors(), true);
                    }
                }
            }
        }
    }

    $_SESSION['restart_ti_flash'] = [
        'message' => $message ?? '',
        'is_success' => (bool) $isSuccess,
        'log_error' => $logError,
    ];

    $redirectUrl = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/gg_app/pages/supportrfid/restart_ti/'), '?');
    header('Location: ' . $redirectUrl);
    exit;
}

$config = $config ?? require __DIR__ . '/config.php';
$services = $config['services'] ?? [];

$summaryRows = [];
$summaryCounts = [];
$detailRows = [];

$summarySql = "
    SELECT item_label, COUNT(*) AS total_restart
    FROM dbo.restart_mqtt
    WHERE restart_date >= ? AND restart_date <= ?
    GROUP BY item_label
    ORDER BY item_label
";
$summaryStmt = sqlsrv_query($conn, $summarySql, [$startDate, $endDate]);
if ($summaryStmt) {
    while ($row = sqlsrv_fetch_array($summaryStmt, SQLSRV_FETCH_ASSOC)) {
        $summaryRows[] = $row;
        $summaryCounts[(string) $row['item_label']] = (int) $row['total_restart'];
    }
}

$detailSql = "
    SELECT restart_date, restart_time, item_label, service_name, username
    FROM dbo.restart_mqtt
    WHERE restart_date >= ? AND restart_date <= ?
    ORDER BY restart_at DESC, id DESC
";
$detailStmt = sqlsrv_query($conn, $detailSql, [$startDate, $endDate]);
if ($detailStmt) {
    while ($row = sqlsrv_fetch_array($detailStmt, SQLSRV_FETCH_ASSOC)) {
        $detailRows[] = $row;
    }
}

include $appRoot . '/includes/header.php';
include $appRoot . '/includes/sidebar.php';
?>
<style>
        :root {
            --card: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --accent: #0f766e;
            --accent-dark: #115e59;
            --danger: #b91c1c;
            --success-bg: #ecfdf5;
            --success-border: #10b981;
            --error-bg: #fef2f2;
            --error-border: #ef4444;
        }

        * {
            box-sizing: border-box;
        }

        .restart-ti-page {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            padding: 24px;
        }

        .restart-ti-page .restart-wrap {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }

        .restart-ti-page .card {
            width: 100%;
            max-width: 520px;
            background: var(--card);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
            overflow: hidden;
            margin: 0 auto 24px;
        }

        .restart-ti-page .hero {
            padding: 28px 28px 20px;
            background: linear-gradient(135deg, #0f766e, #0f172a);
            color: #fff;
        }

        .restart-ti-page .hero h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .restart-ti-page .hero p {
            margin: 0;
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.5;
        }

        .restart-ti-page .restart-content {
            padding: 28px;
        }

        .restart-ti-page .info {
            margin-bottom: 20px;
            padding: 16px 18px;
            border-radius: 16px;
            background: #f8fafc;
            color: var(--muted);
            line-height: 1.6;
        }

        .restart-ti-page .status {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid transparent;
            line-height: 1.5;
        }

        .restart-ti-page .status.success {
            background: var(--success-bg);
            border-color: var(--success-border);
            color: #065f46;
        }

        .restart-ti-page .status.error {
            background: var(--error-bg);
            border-color: var(--error-border);
            color: var(--danger);
        }

        .restart-ti-page .actions {
            display: grid;
            gap: 12px;
        }

        .restart-ti-page .restart-btn {
            width: 100%;
            border: 0;
            border-radius: 18px;
            padding: 16px 20px;
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
            box-shadow: 0 16px 30px rgba(15, 118, 110, 0.28);
        }

        .restart-ti-page .restart-btn:hover {
            transform: translateY(-1px);
        }

        .restart-ti-page .restart-btn:active {
            transform: translateY(0);
            opacity: 0.95;
        }

        .restart-ti-page .restart-btn.secondary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.24);
        }

        .restart-ti-page .restart-btn.warning {
            background: linear-gradient(135deg, #f97316, #c2410c);
            box-shadow: 0 16px 30px rgba(249, 115, 22, 0.24);
        }

        .restart-ti-page .foot {
            margin-top: 14px;
            font-size: 13px;
            color: #64748b;
            text-align: center;
        }

        .restart-ti-page .report-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
            padding: 20px;
        }

        .restart-ti-page .report-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin-bottom: 16px;
        }

        .restart-ti-page .report-head h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
        }

        .restart-ti-page .filter-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .restart-ti-page .filter-form label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
        }

        .restart-ti-page .filter-form input {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
        }

        .restart-ti-page .filter-form button {
            border: 0;
            border-radius: 6px;
            padding: 9px 14px;
            font-weight: 800;
            color: #fff;
            background: #0f766e;
            cursor: pointer;
        }

        .restart-ti-page .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .restart-ti-page .summary-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            background: #f8fafc;
        }

        .restart-ti-page .summary-item .label {
            color: #475569;
            font-weight: 700;
            font-size: 13px;
        }

        .restart-ti-page .summary-item .total {
            margin-top: 6px;
            font-size: 28px;
            font-weight: 900;
            color: #0f766e;
        }

        .restart-ti-page table {
            width: 100%;
            border-collapse: collapse;
        }

        .restart-ti-page th,
        .restart-ti-page td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }

        .restart-ti-page th {
            background: #f8fafc;
            color: #334155;
            font-size: 13px;
        }

        .restart-ti-page .empty {
            color: #64748b;
            text-align: center;
            padding: 18px;
        }

        @media (max-width: 760px) {
            .restart-ti-page .report-head {
                display: block;
            }

            .restart-ti-page .filter-form {
                margin-top: 12px;
            }
        }
</style>
<div class="content-wrapper restart-ti-page">
    <div class="restart-wrap">
        <div class="card">
            <div class="hero">
                <h1>Restart MQTT</h1>
                <p></p>
            </div>
            <div class="restart-content">
                <?php if ($message !== null): ?>
                    <div class="status <?= $isSuccess ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if ($logError !== null): ?>
                    <div class="status error">
                        <?= htmlspecialchars($logError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="actions">
                    <?php foreach ($services as $key => $serviceConfig): ?>
                        <?php
                        $label = (string) ($serviceConfig['label'] ?? $key);
                        $buttonClass = $key === 'transfer_bale_sby' ? ' secondary' : ($key === 'transfer_bale_bandung' ? ' warning' : '');
                        ?>
                        <form method="post">
                            <input type="hidden" name="service_key" value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="restart-btn<?= $buttonClass ?>" onclick="this.disabled=true; this.innerText='Memproses Restart...'; this.form.submit();">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <div class="foot">Gunakan tombol ini hanya saat perlu restart service.</div>
            </div>
        </div>

        <div class="report-card">
            <div class="report-head">
                <div>
                    <h2>Laporan Restart MQTT</h2>
                    <div class="foot">Filter berdasarkan tanggal restart.</div>
                </div>
                <form method="get" class="filter-form">
                    <div>
                        <label for="startdate">Start Date</label>
                        <input type="date" id="startdate" name="startdate" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="enddate">End Date</label>
                        <input type="date" id="enddate" name="enddate" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <button type="submit">Filter</button>
                </form>
            </div>

            <div class="summary-grid">
                <?php if (!empty($services)): ?>
                    <?php foreach ($services as $serviceConfig): ?>
                        <?php $itemLabel = (string) ($serviceConfig['label'] ?? 'Service'); ?>
                            <div class="summary-item">
                                <div class="label"><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="total"><?= (int) ($summaryCounts[$itemLabel] ?? 0) ?></div>
                            </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="summary-item">
                        <div class="label">Belum ada data restart pada periode ini.</div>
                        <div class="total">0</div>
                    </div>
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Restart Item</th>
                        <th>Service</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($detailRows)): ?>
                        <?php foreach ($detailRows as $row): ?>
                            <?php
                            $restartDate = $row['restart_date'] instanceof DateTimeInterface
                                ? $row['restart_date']->format('Y-m-d')
                                : (string) $row['restart_date'];
                            $restartTime = $row['restart_time'] instanceof DateTimeInterface
                                ? $row['restart_time']->format('H:i:s')
                                : (string) $row['restart_time'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($restartDate, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($restartTime, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['item_label'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['service_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty">Tidak ada data restart pada periode ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include $appRoot . '/includes/footer.php'; ?>
