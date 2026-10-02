<?php
session_start();
ob_start();

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

if (!function_exists('cpWipSafeText')) {
    function cpWipSafeText($value): string
    {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('cpWipCellToText')) {
    function cpWipCellToText($value): string
    {
        $raw = (string)($value ?? '');
        $raw = strip_tags($raw);
        $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        return trim($raw);
    }
}

if (!function_exists('cpWipFetchSearchRows')) {
    /**
     * Ambil data Search CP & Routing dari endpoint internal cp_planning.php?action=search_cp.
     *
     * @return array<int,array<int,mixed>>
     */
    function cpWipFetchSearchRows(string $planType, string $machineId): array
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $endpoint = $scheme . '://' . $host . '/gg_app/pages/planning/cp_planning/cp_planning.php?action=search_cp';

        $postBody = http_build_query([
            'filter_routing' => '',
            'plan_type' => $planType,
            'machine_id' => $machineId,
            'force_refresh' => '1',
        ]);
        $cookie = session_name() . '=' . session_id();

        $raw = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postBody,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Cookie: ' . $cookie,
                ],
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $resp = curl_exec($ch);
            if ($resp !== false) {
                $raw = (string)$resp;
            }
            curl_close($ch);
        }

        if ($raw === '') {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                        . "Cookie: " . $cookie . "\r\n",
                    'content' => $postBody,
                    'timeout' => 120,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $fallback = @file_get_contents($endpoint, false, $context);
            if ($fallback !== false) {
                $raw = (string)$fallback;
            }
        }

        if ($raw === '') {
            return [];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return [];
        }

        $rows = $json['data'] ?? [];
        return is_array($rows) ? $rows : [];
    }
}

$planType = cpWipSafeText($_POST['plan_type'] ?? ($_GET['plan_type'] ?? ''));
$machineId = cpWipSafeText($_POST['machine_id'] ?? ($_GET['machine_id'] ?? ''));
$layout = cpWipSafeText($_POST['layout'] ?? ($_GET['layout'] ?? ''));
$searchField = cpWipSafeText($_POST['search_field'] ?? ($_GET['search_field'] ?? ''));
$keyword = cpWipSafeText($_POST['keyword'] ?? ($_GET['keyword'] ?? ''));
$rowsJsonRaw = (string)($_POST['rows_json'] ?? '');

if ($planType === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Plan type wajib dipilih sebelum export.';
    exit;
}

$rows = [];
if ($rowsJsonRaw !== '') {
    $decodedRows = json_decode($rowsJsonRaw, true);
    if (is_array($decodedRows)) {
        $rows = $decodedRows;
    }
}
if (empty($rows)) {
    $rows = cpWipFetchSearchRows($planType, $machineId);
}

$fieldIndexMap = [
    'cp_no' => 0,
    'product_code' => 4,
    'posisi_hari_ini' => 6,
    'lokasi_jetdyeing' => 36,
    'lokasi_washing' => 35,
    'lokasi_cpb' => 34,
    'lokasi_presett' => 33,
    'lokasi_scouring' => 32,
    'lokasi_paddry' => 30,
    'lokasi_bakar_bulu' => 31,
    'color_name' => 10,
];

if ($keyword !== '') {
    $kwLower = strtolower($keyword);
    $targetIdx = $fieldIndexMap[$searchField] ?? null;
    $filtered = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $haystack = '';
        if ($targetIdx !== null && array_key_exists($targetIdx, $row)) {
            $haystack = cpWipCellToText($row[$targetIdx]);
        } else {
            $cells = [];
            foreach ($row as $cell) {
                $cells[] = cpWipCellToText($cell);
            }
            $haystack = implode(' | ', $cells);
        }

        if ($haystack === '') {
            continue;
        }
        if (stripos(strtolower($haystack), $kwLower) !== false) {
            $filtered[] = $row;
        }
    }
    $rows = $filtered;
}

$columns = [
    ['idx' => 0, 'label' => 'CP No'],
    ['idx' => 1, 'label' => 'CP Date'],
    ['idx' => 4, 'label' => 'Product Code'],
    ['idx' => 5, 'label' => 'Product Name'],
    ['idx' => 6, 'label' => 'Posisi Hari Ini'],
    ['idx' => 30, 'label' => 'Lokasi Paddry'],
    ['idx' => 22, 'label' => 'Qty'],
    ['idx' => 7, 'label' => 'Work Center Code'],
    ['idx' => 8, 'label' => 'Work Center Name'],
    ['idx' => 9, 'label' => 'Color Code'],
    ['idx' => 10, 'label' => 'Color Name'],
    ['idx' => 11, 'label' => 'Label Jual'],
    ['idx' => 12, 'label' => 'Cust Color'],
    ['idx' => 15, 'label' => 'Brand Code'],
    ['idx' => 16, 'label' => 'Brand Name'],
    ['idx' => 17, 'label' => 'Grade'],
    ['idx' => 18, 'label' => 'Product Type'],
    ['idx' => 19, 'label' => 'Product Structure Code'],
    ['idx' => 20, 'label' => 'Product Structure Name'],
    ['idx' => 21, 'label' => 'Material'],
    ['idx' => 29, 'label' => 'Kategori Penimbangan'],
];

$planTypeSlug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($planType)) ?? 'plan';
$planTypeSlug = trim($planTypeSlug, '_');
if ($planTypeSlug === '') {
    $planTypeSlug = 'plan';
}
$timestamp = date('Ymd_His');
$filename = 'export_wip_' . $planTypeSlug . '_' . $timestamp . '.xls';

$dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$monthNames = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];
$nowTs = time();
$dayName = $dayNames[(int)date('w', $nowTs)] ?? '';
$dayNum = date('d', $nowTs);
$monthName = $monthNames[(int)date('n', $nowTs)] ?? '';
$yearNum = date('Y', $nowTs);
$generatedDateLabel = trim($dayName . ', ' . $dayNum . ' ' . $monthName . ' ' . $yearNum);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px; }
        th, td { border: 1px solid #777; padding: 4px 6px; white-space: nowrap; }
        th { background: #e9ecef; font-weight: 700; }
        .meta-table td {
            border: none !important;
            padding: 2px 0;
            white-space: nowrap;
            font-size: 12px;
        }
        .meta-title {
            font-weight: 700;
            font-size: 18px;
            padding-bottom: 6px !important;
        }
        .meta-date {
            font-weight: 700;
            font-size: 22px;
            padding-bottom: 12px !important;
        }
        .data-table {
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <table class="meta-table">
        <tbody>
            <tr>
                <td class="meta-title">WIP</td>
            </tr>
            <tr>
                <td class="meta-date"><?= $esc($generatedDateLabel) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <?php foreach ($columns as $col): ?>
                    <th><?= $esc($col['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= $esc((string)(count($columns) + 1)) ?>">Tidak ada data.</td>
                </tr>
            <?php else: ?>
                <?php $n = 1; ?>
                <?php foreach ($rows as $row): ?>
                    <?php if (!is_array($row)) { continue; } ?>
                    <tr>
                        <td><?= $esc((string)$n) ?></td>
                        <?php foreach ($columns as $col): ?>
                            <?php
                                $idx = (int)$col['idx'];
                                $val = array_key_exists($idx, $row) ? cpWipCellToText($row[$idx]) : '';
                            ?>
                            <td><?= $esc($val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php $n++; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
