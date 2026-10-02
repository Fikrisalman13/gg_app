<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId perblerange2 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah data.']);
    exit;
}

function normalize_decimal($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return null;

    // Primary format: 77,942.2956 (comma thousands, dot decimal)
    if (strpos($value, '.') !== false) {
        $value = str_replace(',', '', $value);
        if (is_numeric($value)) return $value;
    }

    // Backward compatibility for old input like 77.942.2956
    if (strpos($value, '.') !== false && strpos($value, ',') === false) {
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $dec = array_pop($parts);
            $int = implode('', $parts);
            $normalized = $int . '.' . $dec;
            if (is_numeric($normalized)) return $normalized;
        }
    }

    // Fallback: comma decimal
    $fallback = str_replace('.', '', $value);
    $fallback = str_replace(',', '.', $fallback);
    return is_numeric($fallback) ? $fallback : null;
}

function get_pbr_columns($conn) {
    $sql = "SELECT COL_LENGTH('dbo.perblerange2_air','PBR1') AS pbr1, COL_LENGTH('dbo.perblerange2_air','PBR2') AS pbr2";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) return ['pbr1' => false, 'pbr2' => false];
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return [
        'pbr1' => isset($row['pbr1']) && $row['pbr1'] !== null,
        'pbr2' => isset($row['pbr2']) && $row['pbr2'] !== null
    ];
}

function get_next_meter_awal($conn, $tanggal) {
    $dt = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$dt) return null;
    $nextDate = $dt->modify('+1 day')->format('Y-m-d');
    $sql = "SELECT TOP 1 Meter_Awal
            FROM dbo.perblerange2_air
            WHERE CAST(Tanggal AS DATE) = ?
            ORDER BY Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$nextDate]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['Meter_Awal'] ?? null;
}

function update_prev_day_from_current($conn, $tanggal, $meterAwalCurrent) {
    if ($meterAwalCurrent === null) return;
    $dt = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$dt) return;
    $prevDate = $dt->modify('-1 day')->format('Y-m-d');

    $sqlPrev = "SELECT TOP 1 Id, Meter_Awal, Meter_Ahir, Oprasional_Mesin, Keterangan
                FROM dbo.perblerange2_air
                WHERE CAST(Tanggal AS DATE) = ?
                ORDER BY Id DESC";
    $stmtPrev = sqlsrv_query($conn, $sqlPrev, [$prevDate]);
    if ($stmtPrev === false) return;
    $prev = sqlsrv_fetch_array($stmtPrev, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtPrev);
    if (!$prev || !isset($prev['Id'])) return;
    if ($prev['Meter_Ahir'] !== null && $prev['Meter_Ahir'] !== '') return;

    $meterAwalPrev = $prev['Meter_Awal'];
    if (!is_numeric($meterAwalPrev)) return;
    $totalPemakaianPrev = round(((float)$meterAwalCurrent - (float)$meterAwalPrev), 2);

    $ketPrev = strtolower(trim((string)($prev['Keterangan'] ?? '')));
    $isOffPrev = ($ketPrev === 'off');
    $oprPrev = $prev['Oprasional_Mesin'];
    $pemakaianRataPrev = null;
    if (!$isOffPrev && is_numeric($oprPrev) && (float)$oprPrev > 0) {
        $pemakaianRataPrev = round(($totalPemakaianPrev / (float)$oprPrev), 2);
    }

    $updateSql = "UPDATE dbo.perblerange2_air
                  SET Meter_Ahir = ?, Total_Pemakaian = ?, Pemakaian_Rata2perjam = ?, UpdateAt = GETDATE()
                  WHERE Id = ?";
    $params = [$meterAwalCurrent, $totalPemakaianPrev, $pemakaianRataPrev, (int)$prev['Id']];
    $stmtUpdate = sqlsrv_query($conn, $updateSql, $params);
    if ($stmtUpdate) {
        sqlsrv_free_stmt($stmtUpdate);
    }
}

$tanggal = $_POST['tanggal'] ?? '';
$pbr1Raw = $_POST['pbr1'] ?? '';
$pbr2Raw = $_POST['pbr2'] ?? '';
$meterAwalRaw = $_POST['meter_awal'] ?? '';
$meterAkhirRaw = $_POST['meter_akhir'] ?? '';
$meterAkhirMode = strtolower(trim((string)($_POST['meter_akhir_mode'] ?? 'otomatis')));
$meterAkhirMode = ($meterAkhirMode === 'manual') ? 'manual' : 'otomatis';
$oprMesinRaw = $_POST['oprasional_mesin'] ?? '';
$keterangan = $_POST['keterangan'] ?? null;
$keteranganNorm = strtolower(trim((string)$keterangan));
$isOff = ($keteranganNorm === 'off');

if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi.']);
    exit;
}

$hasPbrInput = (trim((string)$pbr1Raw) !== '' || trim((string)$pbr2Raw) !== '');
if (!$hasPbrInput && $meterAwalRaw === '') {
    echo json_encode(['success' => false, 'message' => 'PBR1 dan PBR2 wajib diisi untuk menghitung Meter Awal.']);
    exit;
}


$meterAwal = null;
$pbr1 = null;
$pbr2 = null;
if ($hasPbrInput) {
    $pbr1 = normalize_decimal($pbr1Raw);
    $pbr2 = normalize_decimal($pbr2Raw);
    if ($pbr1 === null && $pbr2 === null) {
        echo json_encode(['success' => false, 'message' => 'Format angka PBR1/PBR2 tidak valid.']);
        exit;
    }
    if ($pbr1 === null) $pbr1 = 0;
    if ($pbr2 === null) $pbr2 = 0;
    $meterAwal = round(((float)$pbr1 + (float)$pbr2), 2);
} else {
    $meterAwal = normalize_decimal($meterAwalRaw);
}

if ($meterAwal === null) {
    echo json_encode(['success' => false, 'message' => 'Format angka Meter Awal tidak valid.']);
    exit;
}

$meterAkhir = null;
if ($meterAkhirMode === 'manual') {
    $meterAkhir = normalize_decimal($meterAkhirRaw);
}
$oprMesin = $oprMesinRaw === '' ? null : normalize_decimal($oprMesinRaw);

if ($meterAkhirMode === 'manual' && ($meterAkhirRaw === '' || $meterAkhir === null)) {
    echo json_encode(['success' => false, 'message' => 'Meter Akhir wajib diisi dengan format angka valid jika mode Manual dipilih.']);
    exit;
}
if ($meterAkhirMode === 'otomatis') {
    $meterAkhir = get_next_meter_awal($conn, $tanggal);
}
if ($meterAkhir !== null) {
    if (!is_numeric($meterAkhir)) {
        $meterAkhir = null;
    } else {
        $meterAkhir = round((float)$meterAkhir, 2);
    }
}
if (!$isOff && $oprMesinRaw !== '' && $oprMesin === null) {
    echo json_encode(['success' => false, 'message' => 'Format angka operasional mesin tidak valid.']);
    exit;
}

$totalPemakaian = null;
$pemakaianRata2 = null;
if ($meterAwal !== null && $meterAkhir !== null) {
    $totalPemakaian = round(((float)$meterAkhir - (float)$meterAwal), 2);
}
if (!$isOff && $totalPemakaian !== null && $oprMesin !== null && (float)$oprMesin > 0) {
    $pemakaianRata2 = round(($totalPemakaian / (float)$oprMesin), 2);
}

$columns = [
    'Tanggal',
    'CreatBy',
    'Meter_Awal',
    'Meter_Ahir',
    'Oprasional_Mesin',
    'Pemakaian_Rata2perjam',
    'Keterangan',
    'Total_Pemakaian'
];
$params = [
    $tanggal,
    $_SESSION['UserName'],
    $meterAwal,
    $meterAkhir,
    $isOff ? null : $oprMesin,
    $pemakaianRata2,
    $keterangan,
    $totalPemakaian
];

$pbrCols = get_pbr_columns($conn);
if ($pbrCols['pbr1']) {
    $columns[] = 'PBR1';
    $params[] = $pbr1;
}
if ($pbrCols['pbr2']) {
    $columns[] = 'PBR2';
    $params[] = $pbr2;
}

$placeholders = implode(',', array_fill(0, count($columns), '?'));
$sql = "INSERT INTO dbo.perblerange2_air (" . implode(', ', $columns) . ")
        VALUES (" . $placeholders . ")";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
    exit;
}

if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

// Sesuai konsep excel: saat hari ini disimpan, tutup data H-1 jika meter akhir-nya kosong
update_prev_day_from_current($conn, $tanggal, $meterAwal);

echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan.']);






