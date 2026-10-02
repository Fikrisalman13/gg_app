<?php
session_start();

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

include '../../../koneksi.php';

$layout = trim((string)($_POST['layout'] ?? ($_GET['layout'] ?? '')));
$machineId = trim((string)($_POST['machine_id'] ?? ($_GET['machine_id'] ?? '')));
$machineName = trim((string)($_POST['machine_name'] ?? ($_GET['machine_name'] ?? '')));
$periodDate = trim((string)($_POST['period_date'] ?? ($_GET['period_date'] ?? '')));
$rowsJsonRaw = (string)($_POST['rows_json'] ?? '');

if ($layout === '' || $machineId === '' || $periodDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
    http_response_code(400);
    echo 'Parameter tidak valid.';
    exit;
}

$toText = static function ($value): string {
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y H:i:s');
    }
    return trim((string)($value ?? ''));
};

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$toDateDisplay = static function ($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('d/m/Y');
        }
    }
    $ts = strtotime($raw);
    return $ts === false ? $raw : date('d/m/Y', $ts);
};

$toTimeDisplay = static function ($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d{2}:\d{2}/', $raw)) {
        return substr($raw, 0, 5);
    }
    $ts = strtotime($raw);
    return $ts === false ? $raw : date('H:i', $ts);
};

$rowValue = static function (array $rowAssoc, array $keys, $default = '') {
    foreach ($keys as $k) {
        $key = strtolower((string)$k);
        if (array_key_exists($key, $rowAssoc)) {
            return $rowAssoc[$key];
        }
    }
    return $default;
};

$layoutKey = strtolower($layout);
$tableName = '';
$title = '';
$columns = [];
$headerTop = [];
$headerSub = [];
$headerBlue = '#8fb5d7';
$dataGray = '#d9d9d9';
$dataWhite = '#ffffff';

if ($layoutKey === 'paddry') {
    $tableName = 'dbo.cpp_paddry';
    $title = 'CP Planning - Paddry';
    $headerTop = [
        ['Seq', 2, 1], ['CP No', 2, 1], ['Label', 2, 1], ['Cust Color', 2, 1], ['Kode Lab', 2, 1],
        ['Material Name', 2, 1], ['Planning Qty', 2, 1], ['Posisi Hari Ini', 2, 1], ['Speed', 2, 1],
        ['Resep Lipat', 2, 1], ['Status Resep', 2, 1], ['Vlot Resep', 2, 1], ['Bon Penimbangan', 2, 1],
        ['Kategori Penimbangan', 2, 1], ['Tgl', 2, 1], ['Est Tmbng Plrtn LA/LAB', 2, 1],
        ['Act. Tmbg LAB', 1, 2], ['Act. Tmbg LA', 1, 2], ['Act. Larut LAB', 1, 2], ['Act. Larut LA', 1, 2],
        ['Est Plrtm Prdks', 2, 1], ['Act. Larut Produksi', 1, 2], ['Rencana Celup', 1, 2], ['Aktual Celup', 1, 2],
        ['Aktual Topping Paddry', 1, 2],
        ['Vlot Aktual', 2, 1], ['Sisa Larut', 2, 1], ['Sample Kain', 2, 1], ['RKO', 2, 1],
        ['Next Routing', 2, 1], ['Last Update', 2, 1], ['Update By', 2, 1],
    ];
    $headerSub = [
        'Start', 'Finish', 'Start', 'Finish', 'Start', 'Finish', 'Start', 'Finish',
        'Start', 'Finish', 'Start', 'Finish', 'Start', 'Finish', 'Start', 'Finish',
    ];
    $columns = [
        ['Seq', static fn(array $r) => (string)($r['_seq'] ?? ''), false],
        ['CP No', static fn(array $r) => $toText($r['cp_no'] ?? ''), false],
        ['Label', static fn(array $r) => $toText($r['label'] ?? ($r['label_jual'] ?? '')), false],
        ['Cust Color', static fn(array $r) => $toText($r['cust_color'] ?? ''), false],
        ['Kode Lab', static fn(array $r) => $toText($r['kode_lab'] ?? ''), false],
        ['Material Name', static fn(array $r) => $toText($r['material_name'] ?? ($r['material'] ?? '')), false],
        ['Planning Qty', static fn(array $r) => $toText($r['qty'] ?? ''), false],
        ['Posisi Hari Ini', static fn(array $r) => $toText($r['posisi_hari_ini'] ?? ($r['routing_name'] ?? '')), false],
        ['Speed', static fn(array $r) => $toText($r['speed'] ?? ''), false],
        ['Resep Lipat', static fn(array $r) => $toText($r['resep_lipat'] ?? ($r['resp_lipat'] ?? '')), false],
        ['Status Resep', static fn(array $r) => $toText($r['status_resep'] ?? ($r['status_resp'] ?? '')), false],
        ['Vlot Resep', static fn(array $r) => $toText($r['vlot_resep'] ?? ($r['vlot_resp'] ?? '')), false],
        ['Bon Penimbangan', static fn(array $r) => $toText($r['bon_resep'] ?? ($r['bon_resp'] ?? '')), false],
        ['Kategori Penimbangan', static fn(array $r) => $toText($r['plan_description'] ?? ($r['kategori_penimbangan'] ?? ($r['ket'] ?? ($r['kategori'] ?? '')))), false],
        ['Tgl', static fn(array $r) => $toDateDisplay($r['tgl'] ?? ($r['plan_date'] ?? '')), false],
        ['Est Tmbng Plrtn LA/LAB', static fn(array $r) => $toTimeDisplay($r['est_tmbng_plrtn_lalab'] ?? ($r['est_tmbng_plrtm_lalab'] ?? '')), false, true],
        ['Act. Tmbg LAB Start', static fn(array $r) => $toTimeDisplay($r['aktual_timbang_lab_start'] ?? ($r['actual_timbang_lab_start'] ?? ($r['aktual_timbang_lalab_start'] ?? ($r['actual_timbang_lalab_start'] ?? '')))), true, true],
        ['Act. Tmbg LAB Finish', static fn(array $r) => $toTimeDisplay($r['aktual_timbang_lab_finish'] ?? ($r['actual_timbang_lab_finish'] ?? ($r['aktual_timbang_lalab_finish'] ?? ($r['actual_timbang_lalab_finish'] ?? '')))), true, true],
        ['Act. Tmbg LA Start', static fn(array $r) => $toTimeDisplay($r['aktual_timbang_la_start'] ?? ($r['actual_timbang_la_start'] ?? '')), true, true],
        ['Act. Tmbg LA Finish', static fn(array $r) => $toTimeDisplay($r['aktual_timbang_la_finish'] ?? ($r['actual_timbang_la_finish'] ?? '')), true, true],
        ['Act. Larut LAB Start', static fn(array $r) => $toTimeDisplay($r['aktual_larut_lab_start'] ?? ($r['actual_larut_lab_start'] ?? ($r['aktual_larut_lalab_start'] ?? ($r['actual_larut_lalab_start'] ?? '')))), true, true],
        ['Act. Larut LAB Finish', static fn(array $r) => $toTimeDisplay($r['aktual_larut_lab_finish'] ?? ($r['actual_larut_lab_finish'] ?? ($r['aktual_larut_lalab_finish'] ?? ($r['actual_larut_lalab_finish'] ?? '')))), true, true],
        ['Act. Larut LA Start', static fn(array $r) => $toTimeDisplay($r['aktual_larut_la_start'] ?? ($r['actual_larut_la_start'] ?? '')), true, true],
        ['Act. Larut LA Finish', static fn(array $r) => $toTimeDisplay($r['aktual_larut_la_finish'] ?? ($r['actual_larut_la_finish'] ?? '')), true, true],
        ['Est Plrtm Prdks', static fn(array $r) => $toTimeDisplay($r['est_plrtn_prdks'] ?? ($r['est_plrtm_prdks'] ?? '')), false, true],
        ['Act. Larut Produksi Start', static fn(array $r) => $toTimeDisplay($r['aktual_larut_prdks_start'] ?? ($r['actual_larut_prdks_start'] ?? ($r['aktual_larut_produksi_start'] ?? ($r['actual_larut_produksi_start'] ?? '')))), true, true],
        ['Act. Larut Produksi Finish', static fn(array $r) => $toTimeDisplay($r['aktual_larut_prdks_finish'] ?? ($r['actual_larut_prdks_finish'] ?? ($r['aktual_larut_produksi_finish'] ?? ($r['actual_larut_produksi_finish'] ?? '')))), true, true],
        ['Rencana Celup Start', static fn(array $r) => $toTimeDisplay($r['rencana_start'] ?? ($r['plan_start'] ?? '')), false, true],
        ['Rencana Celup Finish', static fn(array $r) => $toTimeDisplay($r['rencana_finish'] ?? ($r['plan_finish'] ?? ($r['plan_end'] ?? ''))), false, true],
        ['Aktual Celup Start', static fn(array $r) => $toTimeDisplay($r['aktual_start'] ?? ($r['actual_start'] ?? '')), true, true],
        ['Aktual Celup Finish', static fn(array $r) => $toTimeDisplay($r['aktual_finish'] ?? ($r['actual_finish'] ?? ($r['actual_end'] ?? ''))), true, true],
        ['Aktual Topping Paddry Start', static fn(array $r) => $toTimeDisplay($r['aktual_topping_paddry_start'] ?? ($r['actual_topping_paddry_start'] ?? ($r['aktual_topping_start'] ?? ($r['actual_topping_start'] ?? '')))), true, true],
        ['Aktual Topping Paddry Finish', static fn(array $r) => $toTimeDisplay($r['aktual_topping_paddry_finish'] ?? ($r['actual_topping_paddry_finish'] ?? ($r['aktual_topping_finish'] ?? ($r['actual_topping_finish'] ?? '')))), true, true],
        ['Vlot Aktual', static fn(array $r) => $toText($r['vlot_aktual'] ?? ($r['actual_vlot'] ?? '')), false],
        ['Sisa Larut', static fn(array $r) => $toText($r['sisa_larut'] ?? ($r['sisa_saturator'] ?? '')), false],
        ['Sample Kain', static fn(array $r) => $toText($r['sample_kain'] ?? ($r['sample'] ?? '')), false],
        ['RKO', static fn(array $r) => $toDateDisplay($r['rko'] ?? ''), false],
        ['Next Routing', static fn(array $r) => $toText($r['next_routing'] ?? ''), false],
        ['Last Update', static fn(array $r) => $toText($r['last_update'] ?? ($r['modified_at'] ?? ($r['created_at'] ?? ''))), false],
        ['Update By', static fn(array $r) => $toText($r['update_by'] ?? ($r['updated_by'] ?? ($r['modified_by'] ?? ($r['created_by'] ?? '')))), false],
    ];
} elseif ($layoutKey === 'bakar_bulu') {
    $tableName = 'dbo.cpp_bakar_bulu';
    $title = 'CP Planning - Bakar Bulu';
    $headerTop = [
        ['Seq', 2, 1], ['CP No', 2, 1], ['Label Jual', 2, 1], ['Cust Color', 2, 1], ['Kode Lab', 2, 1],
        ['Routing Name', 2, 1], ['Qty', 2, 1], ['Material Name', 2, 1], ['Paddry Planning', 1, 4],
        ['Plant Description', 2, 1], ['Actual', 1, 5], ['Posisi Hari Ini', 2, 1], ['Next Routing', 2, 1],
        ['Last Update', 2, 1], ['Updated By', 2, 1],
    ];
    $headerSub = [
        'Machine', 'Date', 'Start', 'End', 'Date', 'Start', 'End', 'Shift', 'Realisasi',
    ];
    $columns = [
        ['Seq', static fn(array $r) => (string)($r['_seq'] ?? ''), false],
        ['CP No', static fn(array $r) => $toText($r['cp_no'] ?? ''), false],
        ['Label Jual', static fn(array $r) => $toText($r['label_jual'] ?? ''), false],
        ['Cust Color', static fn(array $r) => $toText($r['cust_color'] ?? ''), false],
        ['Kode Lab', static fn(array $r) => $toText($r['kode_lab'] ?? ''), false],
        ['Routing Name', static fn(array $r) => $toText($r['routing_name'] ?? ''), false],
        ['Qty', static fn(array $r) => $toText($r['qty'] ?? ''), false],
        ['Material Name', static fn(array $r) => $toText($r['material_name'] ?? ''), false],
        ['Plan Machine', static fn(array $r) => $toText($r['plan_machine'] ?? ''), false],
        ['Plan Date', static fn(array $r) => $toDateDisplay($r['plan_date'] ?? ''), false],
        ['Plan Start', static fn(array $r) => $toTimeDisplay($r['plan_start'] ?? ''), false, true],
        ['Plan End', static fn(array $r) => $toTimeDisplay($r['plan_end'] ?? ''), false, true],
        ['Plant Description', static fn(array $r) => $toText($r['plan_description'] ?? ''), false],
        ['Actual Date', static fn(array $r) => $toDateDisplay($r['actual_date'] ?? ''), true],
        ['Actual Start', static fn(array $r) => $toTimeDisplay($r['actual_start'] ?? ''), true, true],
        ['Actual End', static fn(array $r) => $toTimeDisplay($r['actual_end'] ?? ''), true, true],
        ['Actual Shift', static fn(array $r) => $toText($r['actual_shift'] ?? ''), true],
        ['Actual Realisasi', static fn(array $r) => $toText($r['actual_realisasi'] ?? ''), true],
        ['Posisi Hari Ini', static fn(array $r) => $toText($r['posisi_hari_ini'] ?? ($r['routing_name'] ?? '')), false],
        ['Next Routing', static fn(array $r) => $toText($r['next_routing'] ?? ''), false],
        ['Last Update', static fn(array $r) => $toText($r['last_update'] ?? ($r['modified_at'] ?? ($r['created_at'] ?? ''))), false],
        ['Updated By', static fn(array $r) => $toText($r['updated_by'] ?? ($r['update_by'] ?? ($r['modified_by'] ?? ($r['created_by'] ?? '')))), false],
    ];
} elseif ($layoutKey === 'scouring') {
    $tableName = 'dbo.cpp_scouring';
    $title = 'CP Planning - Scouring';
    $headerTop = [
        ['Seq', 2, 1], ['CP No', 2, 1], ['Label Jual', 2, 1], ['Cust Color', 2, 1], ['Kode Lab', 2, 1],
        ['Qty', 2, 1], ['Material Name', 2, 1], ['Speed', 2, 1], ['Grammature', 2, 1], ['Machine', 2, 1],
        ['Paddry Planning', 1, 2], ['Plan Description', 2, 1], ['Aktual Proses', 1, 3], ['Operator', 2, 1],
        ['Shift', 2, 1], ['Down Time (Mnt)', 2, 1], ['Realisasi', 2, 1], ['Posisi Hari Ini', 2, 1],
        ['Next Routing', 2, 1], ['Last Update', 2, 1], ['Updated By', 2, 1],
    ];
    $headerSub = ['Tgl', 'Paddry', 'Tgl', 'Start', 'Finish'];
    $columns = [
        ['Seq', static fn(array $r) => (string)($r['_seq'] ?? ''), false],
        ['CP No', static fn(array $r) => $toText($r['cp_no'] ?? ''), false],
        ['Label Jual', static fn(array $r) => $toText($r['label_jual'] ?? ($r['label'] ?? '')), false],
        ['Cust Color', static fn(array $r) => $toText($r['cust_color'] ?? ''), false],
        ['Kode Lab', static fn(array $r) => $toText($r['kode_lab'] ?? ''), false],
        ['Qty', static fn(array $r) => $toText($r['qty'] ?? ''), false],
        ['Material Name', static fn(array $r) => $toText($r['material_name'] ?? ($r['material'] ?? '')), false],
        ['Speed', static fn(array $r) => $toText($r['speed'] ?? ''), false],
        ['Grammature', static fn(array $r) => $toText($r['grammature'] ?? ''), false],
        ['Machine', static fn(array $r) => $toText($r['plan_machine'] ?? ''), false],
        ['Paddry Tgl', static fn(array $r) => $toDateDisplay($r['plan_date'] ?? ''), false],
        ['Paddry', static fn(array $r) => $toText($r['plan_paddry'] ?? ''), false],
        ['Plan Description', static fn(array $r) => $toText($r['plan_description'] ?? ''), false],
        ['Aktual Tgl', static fn(array $r) => $toDateDisplay($r['actual_date'] ?? ''), true],
        ['Aktual Start', static fn(array $r) => $toTimeDisplay($r['actual_start'] ?? ''), true, true],
        ['Aktual Finish', static fn(array $r) => $toTimeDisplay($r['actual_end'] ?? ''), true, true],
        ['Operator', static fn(array $r) => $toText($r['actual_operator'] ?? ''), true],
        ['Shift', static fn(array $r) => $toText($r['actual_shift'] ?? ''), true],
        ['Down Time (Mnt)', static fn(array $r) => $toText($r['down_time'] ?? ''), true],
        ['Realisasi', static fn(array $r) => $toText($r['actual_realisasi'] ?? ''), true],
        ['Posisi Hari Ini', static fn(array $r) => $toText($r['posisi_hari_ini'] ?? ($r['routing_name'] ?? '')), false],
        ['Next Routing', static fn(array $r) => $toText($r['next_routing'] ?? ''), false],
        ['Last Update', static fn(array $r) => $toText($r['last_update'] ?? ($r['modified_at'] ?? ($r['created_at'] ?? ''))), false],
        ['Updated By', static fn(array $r) => $toText($r['updated_by'] ?? ($r['update_by'] ?? ($r['modified_by'] ?? ($r['created_by'] ?? '')))), false],
    ];
} elseif ($layoutKey === 'presett') {
    $tableName = 'dbo.cpp_presett';
    $title = 'CP Planning - PreSett';
    $headerTop = [
        ['Seq', 2, 1], ['No CP', 2, 1], ['Urut Plan', 2, 1], ['Label Jual', 2, 1], ['Cust Color', 2, 1],
        ['Kode Lab', 2, 1], ['Material Name', 2, 1], ['Qty', 2, 1], ['Ket', 2, 1], ['Posisi Hari Ini', 2, 1],
        ['Rencana Paddry', 1, 2], ['Aktual Proses', 1, 2], ['Operator', 2, 1], ['Shift', 2, 1], ['Status', 2, 1],
        ['Downtime (Mnt)', 2, 1], ['Keterangan', 2, 1], ['Last Update', 2, 1], ['Update By', 2, 1],
    ];
    $headerSub = ['Tgl', 'Paddry', 'Start', 'Finish'];
    $columns = [
        ['Seq', static fn(array $r) => (string)($r['_seq'] ?? ''), false],
        ['No CP', static fn(array $r) => $toText($r['cp_no'] ?? ''), false],
        ['Urut Plan', static fn(array $r) => $toText($r['urut_plan'] ?? ''), false],
        ['Label Jual', static fn(array $r) => $toText($r['label_jual'] ?? ($r['label'] ?? '')), false],
        ['Cust Color', static fn(array $r) => $toText($r['cust_color'] ?? ''), false],
        ['Kode Lab', static fn(array $r) => $toText($r['kode_lab'] ?? ''), false],
        ['Material Name', static fn(array $r) => $toText($r['material_name'] ?? ($r['material'] ?? '')), false],
        ['Qty', static fn(array $r) => $toText($r['qty'] ?? ''), false],
        ['Ket', static fn(array $r) => $toText($r['ket'] ?? ''), false],
        ['Posisi Hari Ini', static fn(array $r) => $toText($r['posisi_hari_ini'] ?? ($r['routing_name'] ?? '')), false],
        ['Rencana Paddry Tgl', static fn(array $r) => $toDateDisplay($r['plan_date'] ?? ''), false],
        ['Rencana Paddry', static fn(array $r) => $toText($r['plan_machine'] ?? ''), false],
        ['Aktual Start', static fn(array $r) => $toTimeDisplay($r['actual_start'] ?? ''), true, true],
        ['Aktual Finish', static fn(array $r) => $toTimeDisplay($r['actual_end'] ?? ''), true, true],
        ['Operator', static fn(array $r) => $toText($r['actual_operator'] ?? ''), true],
        ['Shift', static fn(array $r) => $toText($r['actual_shift'] ?? ''), true],
        ['Status', static fn(array $r) => $toText($r['actual_wheel_no'] ?? ($r['status'] ?? '')), true],
        ['Downtime (Mnt)', static fn(array $r) => $toText($r['down_time'] ?? ''), true],
        ['Keterangan', static fn(array $r) => $toText($r['keterangan'] ?? ($r['plan_description'] ?? '')), false],
        ['Last Update', static fn(array $r) => $toText($r['last_update'] ?? ($r['modified_at'] ?? ($r['created_at'] ?? ''))), false],
        ['Update By', static fn(array $r) => $toText($r['updated_by'] ?? ($r['update_by'] ?? ($r['modified_by'] ?? ($r['created_by'] ?? '')))), false],
    ];
} else {
    http_response_code(400);
    echo 'Export hanya tersedia untuk layout paddry, bakar_bulu, scouring, dan presett.';
    exit;
}

$rows = [];
if ($rowsJsonRaw !== '') {
    $decodedRows = json_decode($rowsJsonRaw, true);
    if (is_array($decodedRows)) {
        foreach ($decodedRows as $idx => $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }
            $rowAssoc = [];
            foreach ($rawRow as $k => $v) {
                if (is_string($k)) {
                    $rowAssoc[strtolower($k)] = $v;
                } else {
                    $rowAssoc[$k] = $v;
                }
            }
            if (!isset($rowAssoc['_seq']) || trim((string)$rowAssoc['_seq']) === '') {
                $rowAssoc['_seq'] = (int)$idx + 1;
            }
            $rows[] = $rowAssoc;
        }
    }
}

if (empty($rows)) {
    $sql = "SELECT * FROM {$tableName} WHERE machine_id = ? AND period_date = ? ORDER BY seq_no ASC, id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$machineId, $periodDate]);
    if ($stmt === false) {
        http_response_code(500);
        echo 'Gagal query data.';
        exit;
    }

    $seq = 0;
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $seq++;
        $rowAssoc = [];
        foreach ($r as $k => $v) {
            $rowAssoc[strtolower((string)$k)] = $v;
        }
        $rowAssoc['_seq'] = $seq;
        $rows[] = $rowAssoc;
    }
    sqlsrv_free_stmt($stmt);
}

$filename = 'cp_planning_' . $layoutKey . '_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $machineId) . '_' . $periodDate . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html><head><meta charset="utf-8"></head><body>';
echo '<table border="1" cellpadding="4" cellspacing="0">';
echo '<tr><th colspan="' . (count($columns)) . '" style="font-size:14px;">' . $esc($title) . '</th></tr>';
echo '<tr><td colspan="' . (count($columns)) . '">Periode: ' . $esc($periodDate) . ' | Machine: ' . $esc($machineName !== '' ? $machineName : $machineId) . '</td></tr>';
echo '<tr>';
foreach ($headerTop as $h) {
    $text = (string)($h[0] ?? '');
    $rowspan = (int)($h[1] ?? 1);
    $colspan = (int)($h[2] ?? 1);
    echo '<th style="background:' . $headerBlue . '; text-align:center; vertical-align:middle;" rowspan="' . $rowspan . '" colspan="' . $colspan . '">' . $esc($text) . '</th>';
}
echo '</tr>';
if (!empty($headerSub)) {
    echo '<tr>';
    foreach ($headerSub as $sub) {
        echo '<th style="background:' . $headerBlue . '; text-align:center; vertical-align:middle;">' . $esc((string)$sub) . '</th>';
    }
    echo '</tr>';
}

foreach ($rows as $row) {
    echo '<tr>';
    foreach ($columns as $col) {
        $val = $col[1]($row);
        $isActual = (bool)($col[2] ?? false);
        $isTimeCol = (bool)($col[3] ?? false);
        $cellBg = $isActual ? $dataWhite : $dataGray;
        $textAlign = $isTimeCol ? 'center' : 'left';
        echo '<td style="background:' . $cellBg . '; text-align:' . $textAlign . ';">' . $esc($val) . '</td>';
    }
    echo '</tr>';
}

if ($layoutKey === 'paddry') {
    $signNames = ['Dani', 'Tria', 'Galih', 'Dikdik.KP', 'Umar'];
    $signTitles = ['Kasie.PPC', 'Kasie.Paddry', 'Ka.Sie G.Zat', 'Kabag.Analisa', 'Kabag.Celup'];
    $signCount = count($signNames);
    $baseSpan = intdiv(count($columns), $signCount);
    $rem = count($columns) % $signCount;
    $spans = [];
    for ($i = 0; $i < $signCount; $i++) {
        $spans[] = $baseSpan + ($i < $rem ? 1 : 0);
    }

    echo '<tr><td colspan="' . count($columns) . '" style="border:0; background:#ffffff; height:18px;"></td></tr>';
    echo '<tr>';
    for ($i = 0; $i < $signCount; $i++) {
        echo '<td colspan="' . $spans[$i] . '" style="border:0; background:#ffffff; text-align:center; font-weight:600; text-decoration:underline;">' . $esc($signNames[$i]) . '</td>';
    }
    echo '</tr>';
    echo '<tr>';
    for ($i = 0; $i < $signCount; $i++) {
        echo '<td colspan="' . $spans[$i] . '" style="border:0; background:#ffffff; text-align:center;">' . $esc($signTitles[$i]) . '</td>';
    }
    echo '</tr>';
}

echo '</table>';
echo '</body></html>';
