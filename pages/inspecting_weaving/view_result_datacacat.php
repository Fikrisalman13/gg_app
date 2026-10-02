<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil parameter NoCP dari URL
$noCP = isset($_GET['noCP']) ? trim($_GET['noCP']) : '';

if (empty($noCP)) {
    $_SESSION['error'] = "No CP tidak ditemukan.";
    header('Location: index.php');
    exit;
}

try {
    // Ambil header inspeksi (seperti sebelumnya)
    $sql = "SELECT
        a.NoCP, 
        a.InspectDate, 
        b.EmpName, 
        c.ShiftKode, 
        d.ArtikelKode, 
        d.ArtikelName, 
        a.NoBeam, 
        a.PanjangKainW, 
        a.PanjangKainI, 
        a.LebarKainW, 
        a.LebarKainI, 
        a.WastKiri, 
        a.WastKanan, 
        a.Keterangan, 
        a.UpdDate, 
        a.UpdUser, 
        m1.MesinNo AS WRMC_MesinNo, 
        m1.MesinName AS WRMC_MesinName, 
        t1.TypeName AS WRMC_TypeName, 
        m2.MesinNo AS SZMC_MesinNo, 
        m2.MesinName AS SZMC_MesinName, 
        t2.TypeName AS SZMC_TypeName, 
        m3.MesinNo AS WVMC_MesinNo, 
        m3.MesinName AS WVMC_MesinName, 
        t3.TypeName AS WVMC_TypeName, 
        m4.MesinNo AS INSMC_MesinNo, 
        m4.MesinName AS INSMC_MesinName, 
        t4.TypeName AS INSMC_TypeName, 
        a.Lot
    FROM
        dbo.FormInspectHd AS a
        LEFT JOIN dbo.SMEmployeeInspector AS b ON a.EmpId = b.EmpId
        LEFT JOIN dbo.SMShiftWInspector AS c ON a.ShiftId = c.ShiftId
        LEFT JOIN dbo.SMArtikel AS d ON a.ArtikelId = d.ArtikelId
        LEFT JOIN dbo.SMMesinInspector AS m1 ON a.WRMCId = m1.MesinId
        LEFT JOIN dbo.SMMesinInspector AS m2 ON a.SZMCId = m2.MesinId
        LEFT JOIN dbo.SMMesinInspector AS m3 ON a.WVMCId = m3.MesinId
        LEFT JOIN dbo.SMMesinInspector AS m4 ON a.INSMCId = m4.MesinId
        LEFT JOIN dbo.SMMesinType AS t1 ON m1.TypeId = t1.TypeId
        LEFT JOIN dbo.SMMesinType AS t2 ON m2.TypeId = t2.TypeId
        LEFT JOIN dbo.SMMesinType AS t3 ON m3.TypeId = t3.TypeId
        LEFT JOIN dbo.SMMesinType AS t4 ON m4.TypeId = t4.TypeId
    WHERE a.NoCP = ?";
    $params = [$noCP];
    $stmt = sqlsrv_query($conn, $sql, $params);
    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    // Ambil detail cacat (tetap untuk menampilkan baris cacat dan Total Kode/Total Meter)
    $sql_cacat = "SELECT
        c.NoCP,
        d.CacatId, 
        d.CacatKode, 
        d.CacatName, 
        c.NoDetail, 
        c.MeterKe, 
        c.SMeterKe, 
        c.PointCacat, 
        e.ShiftKode
    FROM dbo.SMCacatDetail AS c
    LEFT JOIN dbo.SMCacat AS d ON c.CacatId = d.CacatId
    LEFT JOIN dbo.SMShiftWInspector AS e ON c.ShiftId = e.ShiftId
    WHERE c.NoCP = ?
    ORDER BY c.NoDetail ASC";
    $stmt_cacat = sqlsrv_query($conn, $sql_cacat, $params);

    $cacat_data = [];
    while ($row = sqlsrv_fetch_array($stmt_cacat, SQLSRV_FETCH_ASSOC)) {
        $cacat_data[] = $row;
    }
    sqlsrv_free_stmt($stmt_cacat);

    

    // Ambil summary dari SMCacatSummary (TIDAK ADA FALLBACK jika summary tidak ada)
    $sqlSummary = "SELECT  TotalPointCacat, PanjangKainI, Grade
                   FROM dbo.SMCacatSummary
                   WHERE NoCP = ?";
    $stmtSum = sqlsrv_query($conn, $sqlSummary, [$noCP]);
    $summary = sqlsrv_fetch_array($stmtSum, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtSum);

    // Jika summary ada, gunakan nilainya. Jika tidak ada, tampilkan '-' (tanpa fallback).
    if ($summary && is_array($summary)) {
        $displayTotalPoint = isset($summary['TotalPointCacat']) ? floatval($summary['TotalPointCacat']) : null;
        $displayPanjangKainI = isset($summary['PanjangKainI']) ? floatval($summary['PanjangKainI']) : null;
        $displayPointGrade = (isset($displayTotalPoint) && isset($displayPanjangKainI) && $displayPanjangKainI > 0)
            ? ($displayTotalPoint / $displayPanjangKainI)
            : null;
        $displayGrade = isset($summary['Grade']) ? $summary['Grade'] : null;
    } else {
        // Tidak ada summary: jangan menghitung lagi dari detail � set semua jadi null
        $displayTotalPoint = null;
        $displayPanjangKainI = null;
        $displayPointGrade = null;
        $displayGrade = null;
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header('Location: index.php');
    exit;
}

ob_end_flush();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Detail Inspect Weaving</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="result_data_cacat.php">Hasil Inspect Weaving</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
    <div class="content">
                <div class="card-header">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h5>Data Inspect</h5>
                </div>
                <div class="card-body table-responsive">
                    <?php if ($data): ?>
                        <table class="table table-hover table-sm">
                            <tr><th>No CP</th><td><?= htmlspecialchars($data['NoCP']) ?></td></tr>
                            <tr><th>Tanggal Inspeksi</th><td><?= ($data['InspectDate'] instanceof DateTime) ? $data['InspectDate']->format('Y-m-d') : (is_object($data['InspectDate']) ? $data['InspectDate']->format('Y-m-d') : htmlspecialchars($data['InspectDate'])) ?></td></tr>
                            <tr><th>Inspector</th><td><?= htmlspecialchars($data['EmpName']) ?></td></tr>
                            <tr><th>Artikel</th><td><?= htmlspecialchars($data['ArtikelKode']) . " - " . htmlspecialchars($data['ArtikelName']) ?></td></tr>
                            <tr><th>No. Beam</th><td><?= htmlspecialchars($data['NoBeam']) ?></td></tr>
                            <tr><th>Lot</th><td><?= htmlspecialchars($data['Lot']) ?></td></tr>
                            <tr><th>No. Mesin Weaving</th><td><?= htmlspecialchars($data['WVMC_MesinNo']). "-"  . htmlspecialchars($data['WVMC_TypeName']) ?></td></tr>
                          <tr><th>Panjang Kain (m)</th>
    <td><?= htmlspecialchars($data['PanjangKainW']) . " / " . (($displayPanjangKainI !== null) ? number_format($displayPanjangKainI, 2) : '-') ?></td>
</tr>

                            <tr><th>Lebar Kain (cm)</th><td><?= htmlspecialchars($data['LebarKainW']) . " / " . htmlspecialchars($data['LebarKainI']) ?></td></tr>
                            <tr><th>Keterangan</th><td><?= htmlspecialchars($data['Keterangan'])?></td></tr>
                        </table>
                    <?php else: ?>
                        <p class="text-danger">Data tidak ditemukan.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detail Cacat -->
            <div class="card mt-4">
                <div class="card-header">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h5>Data Cacat</h5>
                </div>

                <?php if (!empty($cacat_data)): ?>
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th style="text-align: center;">No</th>
                                <th style="text-align: center;">Kode Cacat</th>
                                <th style="text-align: center;">Nama Cacat</th>
                                <th style="text-align: center;">No Detail</th>
                                <th style="text-align: center;">Meter Ke</th>
                                <th style="text-align: center;">SMeter Ke</th>
                                <th style="text-align: center;">Point Cacat</th>
                                <th style="text-align: center;">Shift Weaving</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($cacat_data as $row): ?>
                                <tr>
                                    <td style="text-align: center;"><?= $i++ ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($row['CacatKode']) ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($row['CacatName']) ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($row['NoDetail']) ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($row['MeterKe']) ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($row['SMeterKe']) ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($row['PointCacat']) ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($row['ShiftKode']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            
                           
                            <!-- Sekarang Total Point, Point Grade, Grade hanya dari SMCacatSummary.
                                 Jika tidak ada summary akan tampil '-' (tanpa menghitung ulang). -->
                            <tr class="table-secondary">
                                <td colspan="6" class="text-end fw-bold">Total Point:</td>
                                <td class="text-center fw-bold text-danger"><?= ($displayTotalPoint !== null) ? $displayTotalPoint : '-' ?></td>
                                <td></td>
                            </tr>
                            <tr class="table-secondary">
                                <td colspan="6" class="text-end fw-bold">Point Grade:</td>
                                <td class="text-center fw-bold text-danger"><?= ($displayPointGrade !== null) ? number_format($displayPointGrade, 2) : '-' ?></td>
                                <td></td>
                            </tr>
                            <tr class="table-secondary">
                                <td colspan="6" class="text-end fw-bold">Grade:</td>
                                <td class="text-center fw-bold text-danger"><?= ($displayGrade !== null) ? htmlspecialchars($displayGrade) : '-' ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <p class="text-warning">Tidak ada data cacat untuk No CP ini.</p>
                    <table class="table table-borderless">
                        
                       
                        <tr>
                            <td>Total Point</td><td>: <?= ($displayTotalPoint !== null) ? $displayTotalPoint : '-' ?></td>
                        </tr>
                        <tr>
                            <td>Panjang Kain I</td><td>: <?= ($displayPanjangKainI !== null) ? $displayPanjangKainI : '-' ?></td>
                        </tr>
                        <tr>
                            <td>Grade</td><td>: <?= ($displayGrade !== null) ? htmlspecialchars($displayGrade) : '-' ?></td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>