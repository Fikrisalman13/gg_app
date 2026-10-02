<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
weaving_require($conn, 'CanEdit', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode request tidak valid.']);
}

if (!temp_compressor_v2_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.temp_compressor_v2 belum tersedia.']);
}

$id = $_POST['id'] ?? '';
$tanggalParam = trim($_POST['tanggal'] ?? '');
$weavingParam = intval($_POST['weaving'] ?? 0);
$compressorParam = intval($_POST['compressor_no'] ?? 0);

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = temp_compressor_v2_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $weavingParam > 0 && $compressorParam > 0) {
    $sheet = temp_compressor_v2_resolve_sheet_by_params($conn, $tanggalParam, $weavingParam, $compressorParam);
}

if (!$sheet) {
    json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
}

$tanggal = temp_compressor_v2_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$weaving = (int)($sheet['Weaving'] ?? 1);
$compressorNo = (int)($sheet['Compressor_No'] ?? 1);
$pelaksana = trim($_POST['pelaksana_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rows = $_POST['rows'] ?? [];
$allowedHours = temp_compressor_v2_hours();

if (!is_array($rows)) {
    json_response(['success' => false, 'message' => 'Format data input tidak valid.']);
}

$existingCells = temp_compressor_v2_get_sheet_cells($conn, $tanggal, $weaving, $compressorNo);

$rowsToSave = [];
foreach ($allowedHours as $hour) {
    $rowPressP1  = $rows[$hour]['pressure_p1'] ?? '';
    $rowPressP2  = $rows[$hour]['pressure_p2'] ?? '';
    $rowTempT1   = $rows[$hour]['temp_t1'] ?? '';
    $rowTempT2   = $rows[$hour]['temp_t2'] ?? '';
    $rowTempT3   = $rows[$hour]['temp_t3'] ?? '';
    $rowDryerC   = $rows[$hour]['dryer_c'] ?? '';
    $rowArusA    = $rows[$hour]['arus_a'] ?? '';
    $rowPressIn  = $rows[$hour]['press_in'] ?? '';
    $rowPressOut = $rows[$hour]['press_out'] ?? '';
    $rowTempIn   = $rows[$hour]['temp_in'] ?? '';
    $rowTempOut  = $rows[$hour]['temp_out'] ?? '';
    $rowPelaksana= trim($rows[$hour]['pelaksana'] ?? '');

    $pressP1  = temp_compressor_v2_normalize_decimal($rowPressP1);
    $pressP2  = temp_compressor_v2_normalize_decimal($rowPressP2);
    $tempT1   = temp_compressor_v2_normalize_decimal($rowTempT1);
    $tempT2   = temp_compressor_v2_normalize_decimal($rowTempT2);
    $tempT3   = temp_compressor_v2_normalize_decimal($rowTempT3);
    $dryerC   = temp_compressor_v2_normalize_decimal($rowDryerC);
    $arusA    = temp_compressor_v2_normalize_decimal($rowArusA);
    $pressIn  = temp_compressor_v2_normalize_decimal($rowPressIn);
    $pressOut = temp_compressor_v2_normalize_decimal($rowPressOut);
    $tempIn   = temp_compressor_v2_normalize_decimal($rowTempIn);
    $tempOut  = temp_compressor_v2_normalize_decimal($rowTempOut);

    if ($pressP1 === null && $pressP2 === null
        && $tempT1 === null && $tempT2 === null && $tempT3 === null
        && $dryerC === null && $arusA === null
        && $pressIn === null && $pressOut === null
        && $tempIn === null && $tempOut === null) {
        continue;
    }

    $existingCell = $existingCells[$hour] ?? [];
    if ($rowPelaksana !== '') {
        $finalPelaksana = $rowPelaksana;
    } elseif (!empty($existingCell['pelaksana'])) {
        $finalPelaksana = $existingCell['pelaksana'];
    } else {
        $finalPelaksana = $pelaksana;
    }

    $creatBy = !empty($existingCell['creat_by']) ? $existingCell['creat_by'] : ($sheet['CreatBy'] ?? $_SESSION['UserName']);
    $creatAt = !empty($existingCell['creat_at']) ? $existingCell['creat_at'] : null;

    $rowsToSave[] = [
        'jam'         => $hour,
        'pressure_p1' => $pressP1  !== null ? round((float)$pressP1, 2)  : null,
        'pressure_p2' => $pressP2  !== null ? round((float)$pressP2, 2)  : null,
        'temp_t1'     => $tempT1   !== null ? round((float)$tempT1, 2)   : null,
        'temp_t2'     => $tempT2   !== null ? round((float)$tempT2, 2)   : null,
        'temp_t3'     => $tempT3   !== null ? round((float)$tempT3, 2)   : null,
        'dryer_c'     => $dryerC   !== null ? round((float)$dryerC, 2)   : null,
        'arus_a'      => $arusA    !== null ? round((float)$arusA, 2)    : null,
        'press_in'    => $pressIn  !== null ? round((float)$pressIn, 2)  : null,
        'press_out'   => $pressOut !== null ? round((float)$pressOut, 2) : null,
        'temp_in'     => $tempIn   !== null ? round((float)$tempIn, 2)   : null,
        'temp_out'    => $tempOut  !== null ? round((float)$tempOut, 2)  : null,
        'pelaksana'   => $finalPelaksana !== '' ? $finalPelaksana : null,
        'creat_by'    => $creatBy,
        'creat_at'    => $creatAt,
    ];
}

if (count($rowsToSave) === 0) {
    json_response(['success' => false, 'message' => 'Isi minimal satu data pengecekan.']);
}

if (!sqlsrv_begin_transaction($conn)) {
    json_response(['success' => false, 'message' => 'Gagal memulai transaksi update data.']);
}

$deleteSql = "DELETE FROM dbo.temp_compressor_v2 WHERE CAST(Tanggal AS DATE) = ? AND Weaving = ? AND Compressor_No = ?";
$deleteStmt = sqlsrv_query($conn, $deleteSql, [$tanggal, $weaving, $compressorNo]);
if ($deleteStmt === false) {
    sqlsrv_rollback($conn);
    json_response(['success' => false, 'message' => 'Gagal menghapus data lama.']);
}
if ($deleteStmt) sqlsrv_free_stmt($deleteStmt);

$insertSql = "INSERT INTO dbo.temp_compressor_v2 (
                Tanggal, Weaving, Compressor_No, Jam,
                PressureBar_P1, PressureBar_P2,
                Temperature_T1, Temperature_T2, Temperature_T3,
                Dryer_C, ArusListrik_A,
                AirCooling_PressIn, AirCooling_PressOut,
                AirCooling_TempIn, AirCooling_TempOut,
                Pelaksana, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?, COALESCE(?, GETDATE()), ?, GETDATE()
            )";

foreach ($rowsToSave as $r) {
    $creatAtParam = ($r['creat_at'] instanceof DateTimeInterface)
        ? $r['creat_at']->format('Y-m-d H:i:s')
        : (!empty($r['creat_at']) ? (string)$r['creat_at'] : null);

    $insertParams = [
        $tanggal,
        $weaving,
        $compressorNo,
        $r['jam'] . ':00',
        $r['pressure_p1'],
        $r['pressure_p2'],
        $r['temp_t1'],
        $r['temp_t2'],
        $r['temp_t3'],
        $r['dryer_c'],
        $r['arus_a'],
        $r['press_in'],
        $r['press_out'],
        $r['temp_in'],
        $r['temp_out'],
        $r['pelaksana'],
        $keterangan !== '' ? $keterangan : null,
        $r['creat_by'],
        $creatAtParam,
        $_SESSION['UserName'],
    ];

    $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
    if ($insertStmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan data baru.']);
    }
    if ($insertStmt) sqlsrv_free_stmt($insertStmt);
}

if (!sqlsrv_commit($conn)) {
    sqlsrv_rollback($conn);
    json_response(['success' => false, 'message' => 'Gagal menyelesaikan transaksi data.']);
}

$newSheet = temp_compressor_v2_resolve_sheet_by_params($conn, $tanggal, $weaving, $compressorNo);
$newId = $newSheet ? $newSheet['Id'] : $id;

json_response([
    'success' => true,
    'message' => 'Data Check Sheet Kompressor Sullair V2 berhasil diperbarui.',
    'new_id' => $newId
]);
