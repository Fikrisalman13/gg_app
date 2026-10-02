<?php
$defaultConfig = [
    'host' => '192.168.7.4',
    'port' => '3306',
    'database' => 'surya_usaha_mandiri',
    'username' => 'sidik',
    'password' => '',
];

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

$message = null;
$messageType = null;
$results = [];
$notFoundPackingNos = [];
$updatedPackingNos = [];
$failedPackingNos = [];

function normalizeLines(string $text): array
{
    $lines = preg_split('/\R+/', $text);
    $items = [];

    foreach ($lines as $line) {
        $value = trim($line);
        if ($value !== '') {
            $items[$value] = true;
        }
    }

    return array_keys($items);
}

function isDuplicateKeyError(mysqli_sql_exception $exception): bool
{
    return (int) $exception->getCode() === 1062;
}

function nextSuffix(string $suffix): string
{
    return $suffix . 'U';
}

$form = [
    'host' => $defaultConfig['host'],
    'port' => $defaultConfig['port'],
    'database' => $defaultConfig['database'],
    'username' => $defaultConfig['username'],
    'password' => $defaultConfig['password'],
    'suffix' => $_POST['suffix'] ?? '-RU',
    'packingnos' => $_POST['packingnos'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packingNos = normalizeLines((string) $form['packingnos']);
    $suffixStart = trim((string) $form['suffix']);

    if ($suffixStart === '') {
        $message = 'Suffix wajib diisi, contoh: -RU.';
        $messageType = 'error';
    } elseif (empty($packingNos)) {
        $message = 'Packing No wajib diisi minimal 1 baris.';
        $messageType = 'error';
    } elseif (!extension_loaded('mysqli')) {
        $message = 'Extension mysqli belum aktif di PHP.';
        $messageType = 'error';
    } else {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $mysqli = new mysqli(
                (string) $form['host'],
                (string) $form['username'],
                (string) $form['password'],
                (string) $form['database'],
                (int) $form['port']
            );
            $mysqli->set_charset('utf8mb4');

            $stmt = $mysqli->prepare('UPDATE bales SET baleno = CONCAT(baleno, ?) WHERE baleno = ?');

            foreach ($packingNos as $packingNo) {
                $suffix = $suffixStart;
                $attempt = 0;
                $maxAttempts = 20;

                while (true) {
                    try {
                        $stmt->bind_param('ss', $suffix, $packingNo);
                        $stmt->execute();

                        $status = $stmt->affected_rows > 0 ? 'updated' : 'not_found';
                        if ($status === 'not_found') {
                            $notFoundPackingNos[] = $packingNo;
                        } else {
                            $updatedPackingNos[] = $packingNo . $suffix;
                        }

                        $results[] = [
                            'packingno' => $packingNo,
                            'suffix' => $suffix,
                            'status' => $status,
                            'message' => $stmt->affected_rows > 0
                                ? 'Berhasil diubah menjadi ' . $packingNo . $suffix
                                : 'Packing No / Bale No tidak ada.',
                        ];
                        break;
                    } catch (mysqli_sql_exception $exception) {
                        if (!isDuplicateKeyError($exception)) {
                            throw $exception;
                        }

                        $attempt++;
                        if ($attempt >= $maxAttempts) {
                            $results[] = [
                                'packingno' => $packingNo,
                                'suffix' => $suffix,
                                'status' => 'error',
                                'message' => 'Duplicate key masih terjadi setelah beberapa percobaan.',
                            ];
                            $failedPackingNos[] = $packingNo;
                            break;
                        }

                        $suffix = nextSuffix($suffix);
                    }
                }
            }

            $message = 'Proses selesai.';
            $messageType = 'success';
        } catch (mysqli_sql_exception $exception) {
            $message = 'Koneksi/query gagal: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

$appRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs'), '/\\') . '/gg_app';
include $appRoot . '/koneksi.php';
include $appRoot . '/includes/header.php';
include $appRoot . '/includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<style>
        :root {
            --panel: #ffffff;
            --text: #111827;
            --muted: #64748b;
            --border: #d8e0eb;
            --primary: #0f766e;
            --primary-dark: #115e59;
            --danger: #b91c1c;
            --success: #047857;
            --warning: #92400e;
        }

        * {
            box-sizing: border-box;
        }

        .rfidlama-page {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            padding: 24px;
        }

        .rfidlama-page .wrap {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
        }

        .rfidlama-page .topbar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .rfidlama-page h1 {
            margin: 0;
            font-size: 28px;
            line-height: 1.2;
        }

        .rfidlama-page .subtitle {
            margin: 6px 0 0;
            color: var(--muted);
        }

        .rfidlama-page .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
            padding: 22px;
            margin-bottom: 18px;
        }

        .rfidlama-page .grid {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            gap: 14px;
        }

        .rfidlama-page .field.full {
            grid-column: 1 / -1;
        }

        .rfidlama-page label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .rfidlama-page input,
        .rfidlama-page textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 12px;
            font: inherit;
            color: var(--text);
            background: #fff;
        }

        .rfidlama-page textarea {
            min-height: 180px;
            resize: vertical;
            font-family: Consolas, "Courier New", monospace;
        }

        .rfidlama-page .hint {
            color: var(--muted);
            font-size: 12px;
            margin-top: 6px;
        }

        .rfidlama-page .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }

        .rfidlama-page button {
            border: 0;
            border-radius: 6px;
            padding: 12px 18px;
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(15, 118, 110, 0.22);
        }

        .rfidlama-page .alert {
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 18px;
            border: 1px solid;
            font-weight: 700;
        }

        .rfidlama-page .alert.success {
            color: var(--success);
            background: #ecfdf5;
            border-color: #86efac;
        }

        .rfidlama-page .alert.error {
            color: var(--danger);
            background: #fef2f2;
            border-color: #fecaca;
        }

        .rfidlama-page table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .rfidlama-page th,
        .rfidlama-page td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .rfidlama-page th {
            background: #f8fafc;
            font-size: 13px;
            color: #334155;
        }

        .rfidlama-page tr:last-child td {
            border-bottom: 0;
        }

        .rfidlama-page .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 12px;
            font-weight: 800;
        }

        .rfidlama-page .badge.updated {
            color: var(--success);
            background: #dcfce7;
        }

        .rfidlama-page .badge.not_found {
            color: var(--warning);
            background: #fef3c7;
        }

        .rfidlama-page .badge.error {
            color: var(--danger);
            background: #fee2e2;
        }

        @media (max-width: 720px) {
            .rfidlama-page {
                padding: 16px;
            }

            .rfidlama-page .topbar {
                display: block;
            }

            .rfidlama-page .grid {
                grid-template-columns: 1fr;
            }
        }
</style>
<div class="content-wrapper rfidlama-page">
    <section class="content pt-4">
    <main class="wrap">
        <div class="topbar">
            <div>
                <h1>RFID Lama - Update Bale</h1>
                <p class="subtitle">Update `bales.baleno` berdasarkan daftar nomor, dengan suffix otomatis saat duplicate key.</p>
            </div>
        </div>

        <?php if ($message !== null): ?>
            <div class="alert <?= htmlspecialchars((string) $messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" class="panel">
            <div class="grid">
                <div class="field">
                    <label for="suffix">Suffix</label>
                    <input id="suffix" name="suffix" value="<?= htmlspecialchars((string) $form['suffix'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="hint">Contoh: -RU. Jika duplicate, otomatis dicoba -RUU, -RUUU, dan seterusnya.</div>
                </div>
                <div class="field full">
                    <label for="packingnos">Packing No / Bale No</label>
                    <textarea id="packingnos" name="packingnos" placeholder="2596.7008&#10;2596.7009"><?= htmlspecialchars((string) $form['packingnos'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="hint">Pisahkan dengan Enter, satu nomor per baris.</div>
                </div>
            </div>

            <div class="actions">
                <button type="submit" onclick="this.disabled=true; this.innerText='Memproses...'; this.form.submit();">Update Data</button>
            </div>
        </form>

        <?php if (!empty($results)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Packing No / Bale No</th>
                        <th>Suffix Dipakai</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $row['packingno'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['suffix'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge <?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string) $row['message'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    </section>
</div>
<?php if (!empty($results)): ?>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var updatedPackingNos = <?= json_encode($updatedPackingNos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            var notFoundPackingNos = <?= json_encode($notFoundPackingNos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            var failedPackingNos = <?= json_encode($failedPackingNos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char];
                });
            }

            function renderList(title, items) {
                if (!items.length) {
                    return '';
                }

                return '<div class="mb-2"><b>' + title + '</b><br>' + items.map(function (item) {
                    return '<code>' + escapeHtml(item) + '</code>';
                }).join('<br>') + '</div>';
            }

            if (window.Swal) {
                var hasFailed = notFoundPackingNos.length > 0 || failedPackingNos.length > 0;
                Swal.fire({
                    icon: hasFailed ? 'warning' : 'success',
                    title: hasFailed ? 'Ada Packing No / Bale No yang tidak berhasil' : 'Update berhasil',
                    html: '<div style="text-align:left">' +
                        renderList('Berhasil di-update:', updatedPackingNos) +
                        renderList('Packing No / Bale No tidak ada:', notFoundPackingNos) +
                        renderList('Gagal di-update:', failedPackingNos) +
                        '</div>',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
<?php endif; ?>
<?php include $appRoot . '/includes/footer.php'; ?>
