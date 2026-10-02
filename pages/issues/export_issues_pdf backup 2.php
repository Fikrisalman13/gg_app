<?php
session_start();
// Set default timezone
date_default_timezone_set('Asia/Jakarta');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $permissions = ['CanView' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }
    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 162);
if ($permissions['CanView'] != 1) {
    die('Anda tidak memiliki hak untuk mengekspor data ini.');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php');

use Dompdf\Dompdf;
use Dompdf\Options;

$scope = strtolower($_GET['scope'] ?? 'active');
$filterType = trim((string)($_GET['filterType'] ?? ''));
$filterTanggalStart = trim((string)($_GET['filterTanggalStart'] ?? ''));
$filterTanggalEnd = trim((string)($_GET['filterTanggalEnd'] ?? ''));

$conditions = [];
$params = [];

if ($scope === 'done') {
    $conditions[] = "UPPER(LTRIM(RTRIM(status))) IN ('DONE','CLOSED')";
} elseif ($scope === 'active') {
    $conditions[] = "(status IS NULL OR UPPER(LTRIM(RTRIM(status))) NOT IN ('DONE','CLOSED'))";
}

if ($filterType !== '') {
    $conditions[] = "UPPER(issue_type) = UPPER(?)";
    $params[] = $filterType;
}

$filterKategori = trim((string)($_GET['filterKategori'] ?? ''));
$filterSubKategori = trim((string)($_GET['filterSubKategori'] ?? ''));

if ($filterKategori !== '') {
    $conditions[] = "kategori = ?";
    $params[] = $filterKategori;
}

if ($filterSubKategori !== '') {
    $conditions[] = "sub_kategori = ?";
    $params[] = $filterSubKategori;
}

// Date filter should be on created_at, not due_date (to match issues_serverside.php)
if ($filterTanggalStart !== '' && $filterTanggalEnd !== '') {
    $startDate = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    $endDate = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($startDate !== false && $endDate !== false) {
        $conditions[] = "CAST(created_at AS DATE) BETWEEN ? AND ?";
        $params[] = $startDate->format('Y-m-d');
        $params[] = $endDate->format('Y-m-d');
    }
} elseif ($filterTanggalStart !== '') {
    $startDate = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    if ($startDate !== false) {
        $conditions[] = "CAST(created_at AS DATE) >= ?";
        $params[] = $startDate->format('Y-m-d');
    }
} elseif ($filterTanggalEnd !== '') {
    $endDate = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($endDate !== false) {
        $conditions[] = "CAST(created_at AS DATE) <= ?";
        $params[] = $endDate->format('Y-m-d');
    }
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$sql = "SELECT issue_id, issue_name, issue_type, kategori, sub_kategori, created_by, status, priority, due_date, tanggal_selesai, created_at
        FROM dbo.issues $whereClause
        ORDER BY created_at DESC";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die('Gagal mengambil data issues.');
}

$rows = [];
while ($record = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $record;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

$scopeLabel = ($scope === 'done') ? 'Riwayat (Done)' : 'Aktif';
$reportTitle = ($scope === 'active') ? 'Laporan Issues Active' : 'Laporan Issues';


// Build a human-readable period string from supplied filters (match export_history_pdf style)
$dateRangeLabel = '';
if ($filterTanggalStart !== '' && $filterTanggalEnd !== '') {
    $s = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    $e = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($s && $e) {
        $dateRangeLabel = 'Periode: ' . $s->format('d/m/Y') . ' - ' . $e->format('d/m/Y');
    }
} elseif ($filterTanggalStart !== '') {
    $s = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    if ($s) {
        $dateRangeLabel = 'Dari: ' . $s->format('d/m/Y');
    }
} elseif ($filterTanggalEnd !== '') {
    $e = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($e) {
        $dateRangeLabel = 'Sampai: ' . $e->format('d/m/Y');
    }
} else {
    $dateRangeLabel = 'Semua Data';
}

$tableRows = '';
if (!empty($rows)) {
    $counter = 1;
    foreach ($rows as $record) {
        $kategori = $record['kategori'];
        $subKategori = $record['sub_kategori'] ?? '-';
            $selesai = '';
                    if (!empty($record['tanggal_selesai'])) {
                        // Prefer DateTime objects returned by the driver
                        if ($record['tanggal_selesai'] instanceof DateTime) {
                            $selesai = $record['tanggal_selesai']->format('d/m/Y H:i');
                        } else {
                            // Try common datetime formats including time
                            $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
                            $try = false;
                            foreach ($formats as $fmt) {
                                $tmp = DateTime::createFromFormat($fmt, $record['tanggal_selesai']);
                                if ($tmp !== false) { $try = $tmp; break; }
                            }
                            if ($try !== false) {
                                // If original format had no time, append 00:00
                                $selesai = $try->format('d/m/Y H:i');
                            } else {
                                // Fallback: try extracting numeric parts and assume date only
                                $raw = (string)$record['tanggal_selesai'];
                                $rawDate = preg_replace('/[^0-9\-:\/ ]/', '', $raw);
                                // Extract first 16 chars to include possible time portion
                                $rawDateShort = trim(substr($rawDate, 0, 16));
                                $parsed = DateTime::createFromFormat('Y-m-d H:i', $rawDateShort);
                                if ($parsed !== false) {
                                    $selesai = $parsed->format('d/m/Y H:i');
                                } else {
                                    // Last resort: take date-only portion
                                    $rawDateOnly = preg_replace('/[^0-9\-\/]/', '', substr($rawDate, 0, 10));
                                    $selesai = str_replace('-', '/', $rawDateOnly);
                                }
                            }
                        }
                    }
            $createdAt = '';
            if ($record['created_at'] instanceof DateTime) {
                $createdAt = $record['created_at']->format('d/m/Y H:i');
            } elseif (!empty($record['created_at'])) {
                $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
                $try = false;
                foreach ($formats as $fmt) {
                    $tmp = DateTime::createFromFormat($fmt, $record['created_at']);
                    if ($tmp !== false) { $try = $tmp; break; }
                }
                if ($try !== false) {
                    $createdAt = $try->format('d/m/Y H:i');
                } else {
                    $raw = (string)$record['created_at'];
                    $rawDate = preg_replace('/[^0-9\-:\/ ]/', '', $raw);
                    $rawDateShort = trim(substr($rawDate, 0, 16));
                    $parsed = DateTime::createFromFormat('Y-m-d H:i', $rawDateShort);
                    if ($parsed !== false) {
                        $createdAt = $parsed->format('d/m/Y H:i');
                    } else {
                        $rawDateOnly = preg_replace('/[^0-9\-\/]/', '', substr($rawDate, 0, 10));
                        $createdAt = str_replace('-', '/', $rawDateOnly);
                    }
                }
            }
            $tableRows .= '<tr>' .
                '<td>' . $counter++ . '</td>' .
                '<td>' . htmlspecialchars($record['issue_type'] ?? '-', ENT_QUOTES) . '</td>' .
                '<td>' . htmlspecialchars($kategori ?? '-', ENT_QUOTES) . '</td>' .
                '<td>' . htmlspecialchars($subKategori, ENT_QUOTES) . '</td>' .
                '<td>' . htmlspecialchars($record['issue_name'] ?? '-', ENT_QUOTES) . '</td>' .
                '<td>' . htmlspecialchars($record['created_by'] ?? '-', ENT_QUOTES) . '</td>' .
                '<td>' . htmlspecialchars($record['status'] ?? '-', ENT_QUOTES) . '</td>' .
                '<td>' . htmlspecialchars($record['priority'] ?? '-', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($createdAt ?: '-', ENT_QUOTES) . '</td>' .
                '<td>' . htmlspecialchars($selesai ?: '-', ENT_QUOTES) . '</td>' .
                '</tr>';
    }
} else {
    $tableRows = '<tr><td colspan="9" style="text-align:center;">Tidak ada data sesuai filter.</td></tr>';
}

$html = '<html><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
    h2 { text-align: center; margin-bottom: 5px; }
    .meta { text-align: center; margin-bottom: 15px; font-size: 10px; color: #555; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #444; padding: 6px 8px; }
    th { background-color: #f1f1f1; }
    tbody tr:nth-child(even) { background-color: #fafafa; }
</style></head><body>';
$html .= '<h2>' . htmlspecialchars($reportTitle, ENT_QUOTES) . '</h2>';
$html .= '<div class="meta">' . htmlspecialchars($dateRangeLabel, ENT_QUOTES) . '</div>';
$html .= '<table><thead><tr>
                    <th>No</th>
                    <th>Type</th>
                    <th>Kategori</th>
                    <th>Sub Kategori</th>
                    <th>Issue Name</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Tgl Dibuat</th>
                    <th>Tgl Selesai</th>
        </tr></thead><tbody>' . $tableRows . '</tbody></table>';
$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();

$filename = 'issues_' . $scope . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
