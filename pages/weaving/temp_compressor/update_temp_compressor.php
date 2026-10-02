<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_helper.php');

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

if (!temp_compressor_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.temp_compressor belum tersedia.']);
}

$id = $_POST['id'] ?? '';
$tanggalParam = trim($_POST['tanggal'] ?? '');

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = temp_compressor_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '') {
    $sheet = temp_compressor_resolve_sheet_by_date($conn, $tanggalParam);
}

if (!$sheet) {
    json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
}

$tanggal = temp_compressor_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$petugas = trim($_POST['petugas_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rows = $_POST['rows'] ?? [];
$allowedHours = temp_compressor_hours();

if (!is_array($rows)) {
    json_response(['success' => false, 'message' => 'Format data input tidak valid.']);
}

$existingCells = temp_compressor_get_sheet_cells($conn, $tanggal);

$rowsToSave = [];
foreach ($allowedHours as $hour) {
    $rowC1In      = $rows[$hour]['c1_in'] ?? '';
    $rowC1Out     = $rows[$hour]['c1_out'] ?? '';
    $rowC2In      = $rows[$hour]['c2_in'] ?? '';
    $rowC2Out     = $rows[$hour]['c2_out'] ?? '';
    $rowAmper     = $rows[$hour]['amper'] ?? '';
    $rowPressP1   = $rows[$hour]['pressure_p1'] ?? '';
    $rowPressP2   = $rows[$hour]['pressure_p2'] ?? '';
    $rowTempT1    = $rows[$hour]['temp_t1'] ?? '';
    $rowTempT2    = $rows[$hour]['temp_t2'] ?? '';
    $rowTempT3    = $rows[$hour]['temp_t3'] ?? '';
    $rowDryerC    = $rows[$hour]['dryer_c'] ?? '';
    $rowTekananIn = $rows[$hour]['tekanan_in'] ?? '';
    $rowTekananOut= $rows[$hour]['tekanan_out'] ?? '';
    $rowPetugas   = trim($rows[$hour]['petugas'] ?? '');

    $c1In       = temp_compressor_normalize_decimal($rowC1In);
    $c1Out      = temp_compressor_normalize_decimal($rowC1Out);
    $c2In       = temp_compressor_normalize_decimal($rowC2In);
    $c2Out      = temp_compressor_normalize_decimal($rowC2Out);
    $amper      = temp_compressor_normalize_decimal($rowAmper);
    $pressP1    = temp_compressor_normalize_decimal($rowPressP1);
    $pressP2    = temp_compressor_normalize_decimal($rowPressP2);
    $tempT1     = temp_compressor_normalize_decimal($rowTempT1);
    $tempT2     = temp_compressor_normalize_decimal($rowTempT2);
    $tempT3     = temp_compressor_normalize_decimal($rowTempT3);
    $dryerC     = temp_compressor_normalize_decimal($rowDryerC);
    $tekananIn  = temp_compressor_normalize_decimal($rowTekananIn);
    $tekananOut = temp_compressor_normalize_decimal($rowTekananOut);

    if ($c1In === null && $c1Out === null && $c2In === null && $c2Out === null
        && $amper === null && $pressP1 === null && $pressP2 === null
        && $tempT1 === null && $tempT2 === null && $tempT3 === null
        && $dryerC === null && $tekananIn === null && $tekananOut === null) {
        continue;
    }

    $existingCell = $existingCells[$hour] ?? [];
    if ($rowPetugas !== '') {
        $finalPetugas = $rowPetugas;
    } elseif (!empty($existingCell['petugas'])) {
        $finalPetugas = $existingCell['petugas'];
    } else {
        $finalPetugas = $petugas;
    }

    $creatBy = !empty($existingCell['creat_by']) ? $existingCell['creat_by'] : ($sheet['CreatBy'] ?? $_SESSION['UserName']);
    $creatAt = !empty($existingCell['creat_at']) ? $existingCell['creat_at'] : null;

    $rowsToSave[] = [
        'jam'         => $hour,
        'c1_in'       => $c1In       !== null ? round((float)$c1In, 2)       : null,
        'c1_out'      => $c1Out      !== null ? round((float)$c1Out, 2)      : null,
        'c2_in'       => $c2In       !== null ? round((float)$c2In, 2)       : null,
        'c2_out'      => $c2Out      !== null ? round((float)$c2Out, 2)      : null,
        'amper'       => $amper      !== null ? round((float)$amper, 2)      : null,
        'pressure_p1' => $pressP1    !== null ? round((float)$pressP1, 2)    : null,
        'pressure_p2' => $pressP2    !== null ? round((float)$pressP2, 2)    : null,
        'temp_t1'     => $tempT1     !== null ? round((float)$tempT1, 2)     : null,
        'temp_t2'     => $tempT2     !== null ? round((float)$tempT2, 2)     : null,
        'temp_t3'     => $tempT3     !== null ? round((float)$tempT3, 2)     : null,
        'dryer_c'     => $dryerC     !== null ? round((float)$dryerC, 2)     : null,
        'tekanan_in'  => $tekananIn  !== null ? round((float)$tekananIn, 2)  : null,
        'tekanan_out' => $tekananOut !== null ? round((float)$tekananOut, 2) : null,
        'petugas'     => $finalPetugas !== '' ? $finalPetugas : null,
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

$deleteSql = "DELETE FROM dbo.temp_compressor WHERE CAST(Tanggal AS DATE) = ?";
$deleteStmt = sqlsrv_query($conn, $deleteSql, [$tanggal]);
if ($deleteStmt === false) {
    sqlsrv_rollback($conn);
    json_response(['success' => false, 'message' => 'Gagal menghapus data lama.']);
}
if ($deleteStmt) sqlsrv_free_stmt($deleteStmt);

$insertSql = "INSERT INTO dbo.temp_compressor (
                Tanggal, Jam,
                Compressor1_In_C, Compressor1_Out_C,
                Compressor2_In_C, Compressor2_Out_C,
                Amper, PressureBar_P1, PressureBar_P2,
                Temperature_T1, Temperature_T2, Temperature_T3,
                Dryer_C, TekananAir_In, TekananAir_Out,
                Petugas, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            ) VALUES (
                ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, COALESCE(?, GETDATE()), ?, GETDATE()
            )";

foreach ($rowsToSave as $r) {
    $creatAtParam = ($r['creat_at'] instanceof DateTimeInterface)
        ? $r['creat_at']->format('Y-m-d H:i:s')
        : (!empty($r['creat_at']) ? (string)$r['creat_at'] : null);

    $insertParams = [
        $tanggal,
        $r['jam'] . ':00',
        $r['c1_in'],
        $r['c1_out'],
        $r['c2_in'],
        $r['c2_out'],
        $r['amper'],
        $r['pressure_p1'],
        $r['pressure_p2'],
        $r['temp_t1'],
        $r['temp_t2'],
        $r['temp_t3'],
        $r['dryer_c'],
        $r['tekanan_in'],
        $r['tekanan_out'],
        $r['petugas'],
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

$newSheet = temp_compressor_resolve_sheet_by_date($conn, $tanggal);
$newId = $newSheet ? $newSheet['Id'] : $id;

json_response([
    'success' => true,
    'message' => 'Data Check Sheet Kompressor Sullair berhasil diperbarui.',
    'new_id' => $newId
]);
