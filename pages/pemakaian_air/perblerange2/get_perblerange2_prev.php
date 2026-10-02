<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId perblerange2 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses.']);
    exit;
}

function sql_error_text() {
    $errs = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!$errs || !is_array($errs)) return '';
    $parts = [];
    foreach ($errs as $e) {
        $parts[] = '[' . ($e['SQLSTATE'] ?? '-') . '] ' . ($e['message'] ?? '');
    }
    return implode(' | ', $parts);
}

$tanggal = $_GET['tanggal'] ?? '';
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

// Normalisasi tanggal input agar aman untuk format browser (Y-m-d) maupun locale (d/m/Y)
$tanggal = trim((string)$tanggal);
$dt = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$dt) $dt = DateTime::createFromFormat('d/m/Y', $tanggal);
if (!$dt) $dt = DateTime::createFromFormat('d-m-Y', $tanggal);
if (!$dt) {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}
$inputDate = $dt->format('Y-m-d');
$prevDate = $dt->modify('-1 day')->format('Y-m-d');

// Kompatibel untuk SQL Server lama: hindari TRY_CONVERT
$effectiveDateExpr = "COALESCE(CAST(Tanggal AS date), CAST(CreatAt AS date))";
$row = null;
$foundBy = '';

// 1) Cari tepat H-1
$sql1 = "SELECT TOP 1 PBR1, PBR2, Meter_Ahir, $effectiveDateExpr AS effective_date
         FROM dbo.perblerange2_air
         WHERE $effectiveDateExpr = ?
           AND (PBR1 IS NOT NULL OR PBR2 IS NOT NULL)
         ORDER BY Id DESC";
$stmt1 = sqlsrv_query($conn, $sql1, [$prevDate]);
if ($stmt1 === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Query H-1 gagal dijalankan.',
        'debug_input_date' => $inputDate,
        'debug_prev_date' => $prevDate,
        'debug_sql_error' => sql_error_text()
    ]);
    exit;
}
$row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt1);
if ($row) $foundBy = 'exact_h_minus_1';

// 2) Fallback: tanggal terdekat sebelum input
if (!$row) {
    $sql2 = "SELECT TOP 1 PBR1, PBR2, Meter_Ahir, $effectiveDateExpr AS effective_date
             FROM dbo.perblerange2_air
             WHERE $effectiveDateExpr < ?
               AND (PBR1 IS NOT NULL OR PBR2 IS NOT NULL)
             ORDER BY $effectiveDateExpr DESC, Id DESC";
    $stmt2 = sqlsrv_query($conn, $sql2, [$inputDate]);
    if ($stmt2 === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Query fallback tanggal terdekat gagal dijalankan.',
            'debug_input_date' => $inputDate,
            'debug_prev_date' => $prevDate,
            'debug_sql_error' => sql_error_text()
        ]);
        exit;
    }
    $row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt2);
    if ($row) $foundBy = 'closest_before';
}

$meterAkhir = $row['Meter_Ahir'] ?? ($row['meter_ahir'] ?? null);
$pbr1 = $row['PBR1'] ?? ($row['pbr1'] ?? null);
$pbr2 = $row['PBR2'] ?? ($row['pbr2'] ?? null);

if (!$row || ($pbr1 === null && $pbr2 === null)) {
    echo json_encode([
        'success' => false,
        'message' => 'Data PBR1/PBR2 tidak ditemukan. Input=' . $inputDate . ', H-1=' . $prevDate
    ]);
    exit;
}

$effectiveDate = null;
if (isset($row['effective_date']) && $row['effective_date'] instanceof DateTime) {
    $effectiveDate = $row['effective_date']->format('Y-m-d');
} elseif (isset($row['effective_date'])) {
    $effectiveDate = (string)$row['effective_date'];
}

echo json_encode([
    'success' => true,
    'meter_akhir' => $meterAkhir,
    'pbr1' => $pbr1,
    'pbr2' => $pbr2,
    'debug_input_date' => $inputDate,
    'debug_prev_date' => $prevDate,
    'debug_effective_date' => $effectiveDate,
    'debug_found_by' => $foundBy
]);






