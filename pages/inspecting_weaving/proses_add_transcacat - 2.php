<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
ob_start();
include '../../koneksi.php';

// Pastikan user login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}

if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

$noCP       = $_POST['noCP'] ?? null;
$noDetail   = $_POST['noDetail'] ?? null;
$cacatId    = $_POST['cacatId'] ?? null;
$meterKe    = $_POST['meterKe'] ?? null;
$sMeterKe   = $_POST['sMeterKe'] ?? null;
$pointCacat = $_POST['pointCacat'] ?? null;
$shiftId    = $_POST['shiftId'] ?? null;

if (
    trim($noCP) === '' || trim($noDetail) === '' || trim($cacatId) === '' ||
    trim($meterKe) === '' || trim($sMeterKe) === '' || trim($pointCacat) === '' || trim($shiftId) === ''
) {
    $_SESSION['error'] = "Semua field harus diisi!";
    header("Location: add_transcacat.php?noCP=" . urlencode($noCP));
    exit;
}

if (!is_numeric($noDetail) || !is_numeric($meterKe) || !is_numeric($sMeterKe) || !is_numeric($pointCacat)) {
    $_SESSION['error'] = "Harap masukkan angka yang valid!";
    header("Location: add_transcacat.php?noCP=" . urlencode($noCP));
    exit;
}

$shiftId = strval($shiftId);
$updDate = date('Y-m-d H:i:s');
$updUser = $_SESSION['UserName'] ?? 'system';

// 1) Masukkan detail cacat
$sql = "INSERT INTO dbo.SMCacatDetail 
        (NoCP, NoDetail, CacatId, MeterKe, SMeterKe, PointCacat, ShiftId, UpdDate, UpdUser) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$params = [$noCP, $noDetail, $cacatId, $meterKe, $sMeterKe, $pointCacat, $shiftId, $updDate, $updUser];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal menambahkan data cacat: " . print_r(sqlsrv_errors(), true);
    header("Location: add_transcacat.php?noCP=" . urlencode($noCP));
    exit;
}

// Jika checkbox Last No Detail dicentang => hitung summary dan upsert ke SMCacatSummary
if (isset($_POST['lastNoDetail']) && $_POST['lastNoDetail'] == "1") {

    // mulai transaction supaya konsisten
    if (!sqlsrv_begin_transaction($conn)) {
        // gagal mulai transaction
        $_SESSION['error'] = "Gagal memulai transaksi: " . print_r(sqlsrv_errors(), true);
        header('Location: add_transcacat.php?noCP=' . urlencode($noCP));
        exit;
    }

    try {
        // Update flag pada FormInspectHd
        $sqlUpdate = "UPDATE dbo.FormInspectHd SET FgCacat = 1 WHERE NoCP = ?";
        $updStmt = sqlsrv_query($conn, $sqlUpdate, [$noCP]);
        if ($updStmt === false) {
            throw new Exception("Gagal update FormInspectHd: " . print_r(sqlsrv_errors(), true));
        }

        /*
        ============================================
          HITUNG TOTAL DATA UNTUK SUMMARY
        ============================================
        */
        $summaryQuery = "
            SELECT 
                COUNT(NoDetail) AS TotalKodeCacat,
                SUM(
                    CASE 
                        WHEN SMeterKe = MeterKe THEN 1
                        ELSE ABS(SMeterKe - MeterKe)
                    END
                ) AS TotalMeterCacat,
                SUM(
                    CASE 
                        WHEN SMeterKe = MeterKe THEN 1 * PointCacat
                        ELSE ABS(SMeterKe - MeterKe) * PointCacat
                    END
                ) AS TotalPointCacat
            FROM dbo.SMCacatDetail
            WHERE NoCP = ?
        ";

        $stmtSum = sqlsrv_query($conn, $summaryQuery, [$noCP]);
        if ($stmtSum === false) {
            throw new Exception("Gagal hitung summary: " . print_r(sqlsrv_errors(), true));
        }
        $rowSum = sqlsrv_fetch_array($stmtSum, SQLSRV_FETCH_ASSOC);

        $totalKode   = intval($rowSum['TotalKodeCacat'] ?? 0);
        $totalMeter  = floatval($rowSum['TotalMeterCacat'] ?? 0);
        $totalPoint  = floatval($rowSum['TotalPointCacat'] ?? 0);

        // Ambil Panjang Kain I
        $stmtKain = sqlsrv_query($conn, "SELECT PanjangKainI FROM dbo.FormInspectHd WHERE NoCP = ?", [$noCP]);
        if ($stmtKain === false) {
            throw new Exception("Gagal ambil PanjangKainI: " . print_r(sqlsrv_errors(), true));
        }
        $rowKain = sqlsrv_fetch_array($stmtKain, SQLSRV_FETCH_ASSOC);
        $panjangKain = floatval($rowKain['PanjangKainI'] ?? 0);

        // Tentukan Grade
        $grade = 'C';
        if ($panjangKain > 0) {
            $ratio = $totalPoint / $panjangKain;
            if ($ratio <= 0.30) $grade = 'A';
            elseif ($ratio <= 0.60) $grade = 'B';
        }

        // Lakukan UPSERT: coba UPDATE dulu, jika tidak ada row yang di-update -> INSERT
        $sqlUpdateSummary = "
            UPDATE dbo.SMCacatSummary
            SET TotalKodeCacat = ?, TotalMeterCacat = ?, TotalPointCacat = ?, PanjangKainI = ?, Grade = ?, UpdatedAt = GETDATE()
            WHERE NoCP = ?
        ";
        $paramsUpdateSummary = [$totalKode, $totalMeter, $totalPoint, $panjangKain, $grade, $noCP];
        $updSummaryStmt = sqlsrv_query($conn, $sqlUpdateSummary, $paramsUpdateSummary);
        if ($updSummaryStmt === false) {
            throw new Exception("Gagal UPDATE SMCacatSummary: " . print_r(sqlsrv_errors(), true));
        }

        $rowsAffected = sqlsrv_rows_affected($updSummaryStmt);
        if ($rowsAffected === false) {
            // jika tidak bisa ambil rows_affected, kita lanjut cek keberadaan record secara eksplisit
            $checkStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.SMCacatSummary WHERE NoCP = ?", [$noCP]);
            if ($checkStmt === false) {
                throw new Exception("Gagal cek keberadaan SMCacatSummary: " . print_r(sqlsrv_errors(), true));
            }
            $r = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
            $exists = intval($r['cnt'] ?? 0) > 0;
        } else {
            $exists = ($rowsAffected > 0);
        }

        if (!$exists) {
            // tidak ada, lakukan INSERT
            $sqlInsertSummary = "
                INSERT INTO dbo.SMCacatSummary
                (NoCP, TotalKodeCacat, TotalMeterCacat, TotalPointCacat, PanjangKainI, Grade, CreatedAt, UpdatedAt)
                VALUES (?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())
            ";
            $paramsInsertSummary = [$noCP, $totalKode, $totalMeter, $totalPoint, $panjangKain, $grade];
            $insStmt = sqlsrv_query($conn, $sqlInsertSummary, $paramsInsertSummary);
            if ($insStmt === false) {
                throw new Exception("Gagal INSERT SMCacatSummary: " . print_r(sqlsrv_errors(), true));
            }
        }

        // commit transaction
        if (!sqlsrv_commit($conn)) {
            throw new Exception("Gagal commit transaksi: " . print_r(sqlsrv_errors(), true));
        }

        $_SESSION['success'] = "Data cacat berhasil ditambahkan & summary tersimpan/diupdate!";
        header('Location: datacacat_inspecting_weaving.php');
        exit;

    } catch (Exception $e) {
        // rollback jika error
        @sqlsrv_rollback($conn);
        // set message error (jangan expose terlalu detail di production, namun log)
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan summary: " . $e->getMessage();
        // optional: error_log($e->getMessage());
        header('Location: add_transcacat.php?noCP=' . urlencode($noCP));
        exit;
    }
}

// Jika tidak centang lastNoDetail kembali ke form input
$_SESSION['success'] = "Data cacat berhasil ditambahkan!";
header("Location: add_transcacat.php?noCP=" . urlencode($noCP));
exit;

ob_end_flush();
?>
