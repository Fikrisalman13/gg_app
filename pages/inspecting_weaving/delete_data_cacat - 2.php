<?php
session_start();
ob_start();
date_default_timezone_set('Asia/Jakarta');
include '../../koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'] ?? null;
if (!$groupId) {
    $_SESSION['error'] = "Session tidak valid.";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

// Ambil MenuId untuk Inspecting Weaving
$menuId = 23;

// Periksa hak akses CanDelete
$sql = "SELECT TOP 1 CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal memeriksa hak akses: " . print_r(sqlsrv_errors(), true);
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

$permissions = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?? [];
sqlsrv_free_stmt($stmt);

if (empty($permissions) || $permissions['CanDelete'] == 0) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

// Pastikan id dikirim via POST
if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = "Data tidak valid.";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

$id = intval($_POST['id']);

// Ambil NoCP berdasarkan Id yang akan dihapus
$sqlGetNoCP = "SELECT NoCP FROM dbo.SMCacatDetail WHERE Id = ?";
$paramsGetNoCP = [$id];
$stmtGetNoCP = sqlsrv_query($conn, $sqlGetNoCP, $paramsGetNoCP);

$noCP = null;
if ($stmtGetNoCP !== false && ($row = sqlsrv_fetch_array($stmtGetNoCP, SQLSRV_FETCH_ASSOC))) {
    $noCP = $row['NoCP'];
}
if ($stmtGetNoCP) sqlsrv_free_stmt($stmtGetNoCP);

// Mulai transaksi untuk operasi delete + recalc
if (!sqlsrv_begin_transaction($conn)) {
    $_SESSION['error'] = "Gagal memulai transaksi: " . print_r(sqlsrv_errors(), true);
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

$success = true;

// 1) Hapus baris SMCacatDetail
$sqlDelete = "DELETE FROM dbo.SMCacatDetail WHERE Id = ?";
$paramsDelete = [$id];
$stmtDelete = sqlsrv_query($conn, $sqlDelete, $paramsDelete);

if ($stmtDelete === false) {
    $success = false;
    $err = sqlsrv_errors();
    sqlsrv_rollback($conn);
    $_SESSION['error'] = "Terjadi kesalahan saat menghapus data: " . print_r($err, true);
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}
if ($stmtDelete) sqlsrv_free_stmt($stmtDelete);

// 2) Cek apakah masih ada data dengan NoCP yang sama
if ($noCP) {
    $sqlCheck = "SELECT COUNT(*) AS cnt FROM dbo.SMCacatDetail WHERE NoCP = ?";
    $paramsCheck = [$noCP];
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

    $remaining = 0;
    if ($stmtCheck !== false && ($rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC))) {
        $remaining = intval($rowCheck['cnt']);
    }
    if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);

    // Jika tidak ada lagi detail, set FormInspectHd.FgCacat = NULL
    if ($remaining === 0) {
        $sqlUpdateFgCacat = "UPDATE dbo.FormInspectHd SET FgCacat = NULL WHERE NoCP = ?";
        $stmtUpdateFg = sqlsrv_query($conn, $sqlUpdateFgCacat, [$noCP]);
        if ($stmtUpdateFg === false) {
            $success = false;
            $err = sqlsrv_errors();
            sqlsrv_rollback($conn);
            $_SESSION['error'] = "Gagal mengupdate FormInspectHd.FgCacat: " . print_r($err, true);
            header('Location: datacacat_inspecting_weaving.php');
            exit;
        }
        if ($stmtUpdateFg) sqlsrv_free_stmt($stmtUpdateFg);
    }

    // 3) Recalculate & upsert SMCacatSummary hanya untuk NoCP terkait
    $mergeSql = "
    DECLARE @NoCPParam nvarchar(100) = ?;

    WITH Agg AS (
        SELECT
            d.NoCP,
            COUNT(d.NoDetail) AS TotalKodeCacat,
            SUM(
                CASE 
                    WHEN d.SMeterKe = d.MeterKe THEN 1
                    ELSE ABS(d.SMeterKe - d.MeterKe)
                END
            ) AS TotalMeterCacat,
            SUM(
                CASE
                    WHEN d.SMeterKe = d.MeterKe THEN 1 * d.PointCacat
                    ELSE ABS(d.SMeterKe - d.MeterKe) * d.PointCacat
                END
            ) AS TotalPointCacat,
            fh.PanjangKainI
        FROM dbo.SMCacatDetail d WITH (NOLOCK)
        INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
        WHERE fh.FgCacat = 1
          AND d.NoCP = @NoCPParam
        GROUP BY d.NoCP, fh.PanjangKainI
    ),
    AggWithGrade AS (
        SELECT
            a.*,
            CASE 
                WHEN ISNULL(a.PanjangKainI,0) <= 0 THEN 'C'
                ELSE
                    CASE 
                        WHEN (CAST(a.TotalPointCacat AS float) / CAST(a.PanjangKainI AS float)) <= 0.30 THEN 'A'
                        WHEN (CAST(a.TotalPointCacat AS float) / CAST(a.PanjangKainI AS float)) <= 0.60 THEN 'B'
                        ELSE 'C'
                    END
            END AS Grade
        FROM Agg a
    )

    MERGE INTO dbo.SMCacatSummary AS T
    USING AggWithGrade AS S
        ON T.NoCP = S.NoCP
    WHEN MATCHED THEN
        UPDATE SET
            T.TotalKodeCacat = S.TotalKodeCacat,
            T.TotalMeterCacat = S.TotalMeterCacat,
            T.TotalPointCacat = S.TotalPointCacat,
            T.PanjangKainI = S.PanjangKainI,
            T.Grade = S.Grade,
            T.UpdatedAt = GETDATE()
    WHEN NOT MATCHED BY TARGET THEN
        INSERT (NoCP, TotalKodeCacat, TotalMeterCacat, TotalPointCacat, PanjangKainI, Grade, CreatedAt, UpdatedAt)
        VALUES (S.NoCP, S.TotalKodeCacat, S.TotalMeterCacat, S.TotalPointCacat, S.PanjangKainI, S.Grade, GETDATE(), GETDATE())
    ;

    -- Jika tidak ada hasil agregasi maka hapus SMCacatSummary untuk NoCP tersebut
    IF NOT EXISTS (SELECT 1 FROM AggWithGrade)
    BEGIN
        DELETE FROM dbo.SMCacatSummary WHERE NoCP = @NoCPParam;
    END
    ";

    $stmtMerge = sqlsrv_query($conn, $mergeSql, [$noCP]);
    if ($stmtMerge === false) {
        $success = false;
        $err = sqlsrv_errors();
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Gagal memperbarui SMCacatSummary: " . print_r($err, true);
        header('Location: datacacat_inspecting_weaving.php');
        exit;
    }
    if ($stmtMerge) sqlsrv_free_stmt($stmtMerge);
}

// Commit transaksi jika semua sukses
if ($success) {
    if (!sqlsrv_commit($conn)) {
        $err = sqlsrv_errors();
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Gagal commit transaksi: " . print_r($err, true);
        header('Location: datacacat_inspecting_weaving.php');
        exit;
    }

    $_SESSION['success'] = "Data berhasil dihapus dan SMCacatSummary diperbarui.";
} else {
    // seharusnya sudah di-rollback pada jalur error di atas
    $_SESSION['error'] = "Terjadi kesalahan saat memproses penghapusan.";
}

sqlsrv_close($conn);
header('Location: datacacat_inspecting_weaving.php');
exit;
ob_end_flush();
?>
