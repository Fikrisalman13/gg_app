<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik2_common.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$tanggal = kwhl2_normalize_date($_POST['tanggal'] ?? ($_POST['tanggal_21t'] ?? ''));
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}

$machineKey = trim((string)($_POST['machine'] ?? '21ton_actom'));
$machines = kwhl2_machine_defs();
if (!isset($machines[$machineKey])) {
    $machineKey = '21ton_actom';
}
$def = $machines[$machineKey];

$kwhHariIni = (float)kwhl2_parse_number($_POST['kwh_hari_ini'] ?? 0);
$kwhKemarin = (float)kwhl2_parse_number($_POST['kwh_kemarin'] ?? 0);
$tarif = (float)kwhl2_parse_number($_POST['tarif_per_kwh'] ?? ($_POST['tarif_per_kwh_21t'] ?? kwhl2_default_tarif()));
if ($tarif <= 0) {
    $tarif = (float)kwhl2_default_tarif();
}

$kwhPemakaian = $kwhHariIni - $kwhKemarin;
if ($kwhPemakaian < 0) {
    $kwhPemakaian = 0.0;
}
$biayaPemakaian = $kwhPemakaian * $tarif;

if (!kwhl2_table_exists($conn)) {
    echo json_encode(['success' => false, 'message' => 'Tabel kwh_listrik2 belum dibuat di database.']);
    exit;
}
$tableName = kwhl2_table_full_name($conn);
$tableCols = kwhl2_get_table_columns($conn);
$username = $_SESSION['UserName'] ?? 'SYSTEM';

try {
    // Cek apakah data untuk tanggal ini sudah ada
    $checkSql = "SELECT TOP 1 * FROM " . $tableName . " WHERE CAST(tanggal AS DATE) = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$tanggal]);

    if ($checkStmt === false) {
        $err = sqlsrv_errors();
        throw new Exception('Gagal mengecek data: ' . ($err[0]['message'] ?? ''));
    }

    $existingRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($checkStmt);

    if ($existingRow) {
        // Record sudah ada -> UPDATE kolom mesin terpilih
        $setParts = [];
        $params = [];

        if (isset($tableCols[strtolower($def['hi_key'])])) {
            $setParts[] = '[' . $def['hi_key'] . ']=?';
            $params[] = $kwhHariIni;
        }
        if (isset($tableCols[strtolower($def['km_key'])])) {
            $setParts[] = '[' . $def['km_key'] . ']=?';
            $params[] = $kwhKemarin;
        }
        if (isset($tableCols[strtolower($def['kwh_key'])])) {
            $setParts[] = '[' . $def['kwh_key'] . ']=?';
            $params[] = $kwhPemakaian;
        }
        if (isset($tableCols[strtolower($def['biaya_key'])])) {
            $setParts[] = '[' . $def['biaya_key'] . ']=?';
            $params[] = $biayaPemakaian;
        }

        // Alt keys jika ada
        if (isset($def['alt_kwh_key']) && isset($tableCols[strtolower($def['alt_kwh_key'])])) {
            $setParts[] = '[' . $def['alt_kwh_key'] . ']=?';
            $params[] = $kwhPemakaian;
        }
        if (isset($def['alt_biaya_key']) && isset($tableCols[strtolower($def['alt_biaya_key'])])) {
            $setParts[] = '[' . $def['alt_biaya_key'] . ']=?';
            $params[] = $biayaPemakaian;
        }
        if (isset($def['alt_hi_key']) && isset($tableCols[strtolower($def['alt_hi_key'])])) {
            $setParts[] = '[' . $def['alt_hi_key'] . ']=?';
            $params[] = $kwhHariIni;
        }
        if (isset($def['alt_km_key']) && isset($tableCols[strtolower($def['alt_km_key'])])) {
            $setParts[] = '[' . $def['alt_km_key'] . ']=?';
            $params[] = $kwhKemarin;
        }

        if (isset($tableCols['updateby'])) {
            $setParts[] = '[updateby]=?';
            $params[] = $username;
        }
        if (isset($tableCols['updateat'])) {
            $setParts[] = '[updateat]=GETDATE()';
        }

        // Recalculate total_kwh dan total_biaya
        $mappedExisting = kwhl2_row_from_db($existingRow);
        $totalKwh = 0.0;
        $totalBiaya = 0.0;
        foreach ($machines as $c => $m) {
            if ($c === $machineKey) {
                $totalKwh += $kwhPemakaian;
                $totalBiaya += $biayaPemakaian;
            } else {
                $totalKwh += (float)($mappedExisting[$m['kwh_key']] ?? 0);
                $totalBiaya += (float)($mappedExisting[$m['biaya_key']] ?? 0);
            }
        }

        if (isset($tableCols['total_kwh'])) {
            $setParts[] = '[total_kwh]=?';
            $params[] = $totalKwh;
        }
        if (isset($tableCols['total_biaya'])) {
            $setParts[] = '[total_biaya]=?';
            $params[] = $totalBiaya;
        }

        $params[] = (int)$existingRow['id'];
        $updateSql = "UPDATE " . $tableName . " SET " . implode(', ', $setParts) . " WHERE id=?";
        $updateStmt = sqlsrv_query($conn, $updateSql, $params);

        if ($updateStmt === false) {
            $err = sqlsrv_errors();
            throw new Exception('Gagal mengupdate data: ' . ($err[0]['message'] ?? ''));
        }
        sqlsrv_free_stmt($updateStmt);

        echo json_encode([
            'success' => true,
            'message' => 'Data ' . $def['label'] . ' berhasil diperbarui.',
        ]);
        exit;
    } else {
        // Record belum ada -> INSERT baris baru untuk tanggal ini
        $insertCols = ['[tanggal]', '[tarif_per_kwh]'];
        $insertVals = ['?', '?'];
        $params = [$tanggal, $tarif];

        if (isset($tableCols[strtolower($def['hi_key'])])) {
            $insertCols[] = '[' . $def['hi_key'] . ']';
            $insertVals[] = '?';
            $params[] = $kwhHariIni;
        }
        if (isset($tableCols[strtolower($def['km_key'])])) {
            $insertCols[] = '[' . $def['km_key'] . ']';
            $insertVals[] = '?';
            $params[] = $kwhKemarin;
        }
        if (isset($tableCols[strtolower($def['kwh_key'])])) {
            $insertCols[] = '[' . $def['kwh_key'] . ']';
            $insertVals[] = '?';
            $params[] = $kwhPemakaian;
        }
        if (isset($tableCols[strtolower($def['biaya_key'])])) {
            $insertCols[] = '[' . $def['biaya_key'] . ']';
            $insertVals[] = '?';
            $params[] = $biayaPemakaian;
        }

        // Alt keys
        if (isset($def['alt_kwh_key']) && isset($tableCols[strtolower($def['alt_kwh_key'])])) {
            $insertCols[] = '[' . $def['alt_kwh_key'] . ']';
            $insertVals[] = '?';
            $params[] = $kwhPemakaian;
        }
        if (isset($def['alt_biaya_key']) && isset($tableCols[strtolower($def['alt_biaya_key'])])) {
            $insertCols[] = '[' . $def['alt_biaya_key'] . ']';
            $insertVals[] = '?';
            $params[] = $biayaPemakaian;
        }
        if (isset($def['alt_hi_key']) && isset($tableCols[strtolower($def['alt_hi_key'])])) {
            $insertCols[] = '[' . $def['alt_hi_key'] . ']';
            $insertVals[] = '?';
            $params[] = $kwhHariIni;
        }
        if (isset($def['alt_km_key']) && isset($tableCols[strtolower($def['alt_km_key'])])) {
            $insertCols[] = '[' . $def['alt_km_key'] . ']';
            $insertVals[] = '?';
            $params[] = $kwhKemarin;
        }

        if (isset($tableCols['total_kwh'])) {
            $insertCols[] = '[total_kwh]';
            $insertVals[] = '?';
            $params[] = $kwhPemakaian;
        }
        if (isset($tableCols['total_biaya'])) {
            $insertCols[] = '[total_biaya]';
            $insertVals[] = '?';
            $params[] = $biayaPemakaian;
        }

        if (isset($tableCols['creatby'])) {
            $insertCols[] = '[creatby]';
            $insertVals[] = '?';
            $params[] = $username;
        }
        if (isset($tableCols['createby'])) {
            $insertCols[] = '[createby]';
            $insertVals[] = '?';
            $params[] = $username;
        }
        if (isset($tableCols['created_by'])) {
            $insertCols[] = '[created_by]';
            $insertVals[] = '?';
            $params[] = $username;
        }
        if (isset($tableCols['creatat'])) {
            $insertCols[] = '[creatat]';
            $insertVals[] = 'GETDATE()';
        }
        if (isset($tableCols['createat'])) {
            $insertCols[] = '[createat]';
            $insertVals[] = 'GETDATE()';
        }
        if (isset($tableCols['created_at'])) {
            $insertCols[] = '[created_at]';
            $insertVals[] = 'GETDATE()';
        }

        $insertSql = "INSERT INTO " . $tableName . " (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
        $insertStmt = sqlsrv_query($conn, $insertSql, $params);

        if ($insertStmt === false) {
            $err = sqlsrv_errors();
            throw new Exception('Gagal menyimpan data: ' . ($err[0]['message'] ?? ''));
        }
        sqlsrv_free_stmt($insertStmt);

        echo json_encode([
            'success' => true,
            'message' => 'Data ' . $def['label'] . ' berhasil disimpan.',
        ]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
