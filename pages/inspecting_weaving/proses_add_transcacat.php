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

    try {
        // 1) Update flag pada FormInspectHd sehingga MERGE akan membaca fh.FgCacat = 1
        $sqlUpdate = "UPDATE dbo.FormInspectHd SET FgCacat = 1 WHERE NoCP = ?";
        $updStmt = sqlsrv_query($conn, $sqlUpdate, [$noCP]);
        if ($updStmt === false) {
            throw new Exception("Gagal update FormInspectHd: " . print_r(sqlsrv_errors(), true));
        }

        // 2) Jalankan batch T-SQL yang berisi BEGIN TRAN + MERGE + TRY/CATCH
        //    MERGE hanya memproses NoCP yang sedang ditangani (parameter ? di akhir CTE WHERE d.NoCP = ?)
        $mergeBatch = "
BEGIN TRAN;
BEGIN TRY
    ;WITH Agg AS (
        SELECT
            d.NoCP,
            COUNT(d.NoDetail) AS TotalKodeCacat,
            SUM(ABS(d.SMeterKe - d.MeterKe) + 1) AS TotalMeterCacat,
            SUM((ABS(d.SMeterKe - d.MeterKe) + 1) * d.PointCacat) AS TotalPointCacat,
            fh.PanjangKainI
        FROM dbo.SMCacatDetail d WITH (NOLOCK)
        INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
        WHERE fh.FgCacat = 1
          AND d.NoCP = ?
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
        VALUES (S.NoCP, S.TotalKodeCacat, S.TotalMeterCacat, S.TotalPointCacat, S.PanjangKainI, S.Grade, GETDATE(), GETDATE());
    COMMIT TRAN;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRAN;
    DECLARE @ErrMsg NVARCHAR(4000) = ERROR_MESSAGE();
    DECLARE @ErrNo INT = ERROR_NUMBER();
    RAISERROR('Batch update failed. Error %d: %s', 16, 1, @ErrNo, @ErrMsg);
END CATCH;
";

        // Eksekusi batch MERGE dengan parameter NoCP
        $mergeStmt = sqlsrv_query($conn, $mergeBatch, [$noCP]);
        if ($mergeStmt === false) {
            throw new Exception("Gagal menjalankan MERGE batch: " . print_r(sqlsrv_errors(), true));
        }

        // Jika sampai sini sukses
        $_SESSION['success'] = "Data cacat berhasil ditambahkan & summary tersimpan/diupdate (MERGE)!";
        header('Location: datacacat_inspecting_weaving.php');
        exit;

    } catch (Exception $e) {
        // Pada error, tampilkan rollback info dan arahkan kembali ke form
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan summary (MERGE): " . $e->getMessage();
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
