<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

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
    // Query utama untuk detail inspeksi
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
	LEFT JOIN
	dbo.SMEmployeeInspector AS b
	ON 
		a.EmpId = b.EmpId
	LEFT JOIN
	dbo.SMShiftWInspector AS c
	ON 
		a.ShiftId = c.ShiftId
	LEFT JOIN
	dbo.SMArtikel AS d
	ON 
		a.ArtikelId = d.ArtikelId
	LEFT JOIN
	dbo.SMMesinInspector AS m1
	ON 
		a.WRMCId = m1.MesinId
	LEFT JOIN
	dbo.SMMesinInspector AS m2
	ON 
		a.SZMCId = m2.MesinId
	LEFT JOIN
	dbo.SMMesinInspector AS m3
	ON 
		a.WVMCId = m3.MesinId
	LEFT JOIN
	dbo.SMMesinInspector AS m4
	ON 
		a.INSMCId = m4.MesinId
	LEFT JOIN
	dbo.SMMesinType AS t1
	ON 
		m1.TypeId = t1.TypeId
	LEFT JOIN
	dbo.SMMesinType AS t2
	ON 
		m2.TypeId = t2.TypeId
	LEFT JOIN
	dbo.SMMesinType AS t3
	ON 
		m3.TypeId = t3.TypeId
	LEFT JOIN
	dbo.SMMesinType AS t4
	ON 
		m4.TypeId = t4.TypeId
    WHERE a.NoCP = ?";
    $params = [$noCP];
    $stmt = sqlsrv_query($conn, $sql, $params);
    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    // Query untuk detail cacat
    $sql_cacat = "SELECT
        c.NoCP,
        d.CacatId, 
        d.CacatKode, 
        d.CacatName, 
        c.NoDetail, 
        c.MeterKe, 
        c.SMeterKe, 
        c.PointCacat, 
        e.ShiftKode,
        f.PanjangKainI
    FROM dbo.SMCacatDetail AS c
    LEFT JOIN dbo.SMCacat AS d ON c.CacatId = d.CacatId
    LEFT JOIN dbo.SMShiftWInspector AS e ON c.ShiftId = e.ShiftId
    LEFT JOIN dbo.FormInspectHd AS f ON c.NoCP = f.NoCP
    WHERE c.NoCP = ?
    ORDER BY c.NoDetail ASC";
    $stmt_cacat = sqlsrv_query($conn, $sql_cacat, $params);

    $totalPoint = 0;
$panjangKainI = $data['PanjangKainI'] ?? 0;
$no = 1;
$cacat_data = [];

while ($row = sqlsrv_fetch_array($stmt_cacat, SQLSRV_FETCH_ASSOC)) {
    $cacat_data[] = $row;

    // Pastikan data tidak null sebelum perhitungan
    $meterKe = $row['MeterKe'] ?? 0;
    $sMeterKe = $row['SMeterKe'] ?? 0;
    $pointCacat = $row['PointCacat'] ?? 0;

    // Jika sMeterKe == meterKe, gunakan nilai 1 sebagai default
    if ($sMeterKe == $meterKe) {
        $selisihMeter = 1; 
    } else {
        $selisihMeter = max(0, $sMeterKe - $meterKe); // Hindari nilai negatif
    }

    // Hitung total point sesuai rumus
    $totalPoint += $selisihMeter * $pointCacat;
}

// Menghindari pembagian dengan nol
$pointGrade = ($panjangKainI > 0) ? $totalPoint / $panjangKainI : 0;

// Menentukan Grade berdasarkan Point Grade
if ($pointGrade <= 0.30) {
    $grade = 'A';
} elseif ($pointGrade <= 0.60) {
    $grade = 'B';
} else {
    $grade = 'C';
}


    sqlsrv_free_stmt($stmt_cacat);
} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header('Location: index.php');
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Inspect Weaving</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
    

</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Hasil Inspect Weaving</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
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
                            <tr><th>Tanggal Inspeksi</th><td><?= $data['InspectDate']->format('Y-m-d') ?></td></tr>
                            <tr><th>Inspector</th><td><?= htmlspecialchars($data['EmpName']) ?></td></tr>                            
                            <tr><th>Artikel</th><td><?= htmlspecialchars($data['ArtikelKode']) . " - " . htmlspecialchars($data['ArtikelName']) ?></td></tr>
                            <tr><th>No. Beam</th><td><?= htmlspecialchars($data['NoBeam']) ?></td></tr>
                            <tr><th>Lot</th><td><?= htmlspecialchars($data['Lot']) ?></td></tr>
                            <tr><th>No. Mesin Weaving</th><td><?= htmlspecialchars($data['WVMC_MesinNo']). "-"  . htmlspecialchars($data['WVMC_TypeName']) ?></td></tr>                            
                            <tr><th>Panjang Kain (m)</th><td><?= htmlspecialchars($data['PanjangKainW']) . " / " . htmlspecialchars($data['PanjangKainI']) ?></td></tr>
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
                <table class="table table-hover table-sm">
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
                                <?php foreach ($cacat_data as $row): ?>
                                    <tr>
                                        <td style="text-align: center;"><?= $no++ ?></td>
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
                                <tr class="table-secondary">
                                    <td colspan="6" class="text-end fw-bold">Total Point:</td>
                                    <td class="text-center fw-bold text-danger"><?= $totalPoint ?></td>
                                    <td></td>
                                </tr>
                                <tr class="table-secondary">
                                    <td colspan="6" class="text-end fw-bold">Point Grade:</td>
                                    <td class="text-center fw-bold text-danger"><?= number_format($pointGrade, 2) ?></td>
                                    <td></td>
                                </tr>
                                <tr class="table-secondary">
                                    <td colspan="6" class="text-end fw-bold">Grade:</td>
                                    <td class="text-center fw-bold text-danger"><?= $grade ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <p class="text-warning">Tidak ada data cacat untuk No CP ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>


