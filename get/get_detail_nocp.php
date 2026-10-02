<?php
include '../koneksi.php';

if (!isset($_POST['noCP'])) {
    exit("<div class='alert alert-danger'>No CP tidak ditemukan.</div>");
}

$noCP = $_POST['noCP'];

$sql = "SELECT
	a.NoCP, 
	a.InspectDate, 
	b.EmpName, 
	c.ShiftKode, 
	d.ArtikelKode, 
	d.ArtikelName, 
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
	t1.TypeName AS WRMC_TypeName,  -- TypeName untuk WRMC
	m2.MesinNo AS SZMC_MesinNo, 
	m2.MesinName AS SZMC_MesinName, 
	t2.TypeName AS SZMC_TypeName,  -- TypeName untuk SZMC
	m3.MesinNo AS WVMC_MesinNo, 
	m3.MesinName AS WVMC_MesinName, 
	t3.TypeName AS WVMC_TypeName,  -- TypeName untuk WVMC
	m4.MesinNo AS INSMC_MesinNo, 
	m4.MesinName AS INSMC_MesinName, 
	t4.TypeName AS INSMC_TypeName  -- TypeName untuk INSMC
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

$stmt = sqlsrv_query($conn, $sql, [$noCP]);

if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    echo "<table class='table table-bordered'>
            <tr><th>No CP</th><td>{$row['NoCP']}</td></tr>
            <tr><th>Tanggal Inspeksi</th><td>{$row['InspectDate']->format('Y-m-d')}</td></tr>
            <tr><th>Inspector</th><td>{$row['EmpName']}</td></tr>
          
            <tr><th>Artikel</th><td>{$row['ArtikelKode']} - {$row['ArtikelName']}</td></tr>
            <tr><th>No. Mesin Weaving</th><td>{$row['WVMC_MesinNo']} - {$row['WVMC_TypeName']}</td></tr>
         
            <tr><th>Panjang Kain (m)</th><td>{$row['PanjangKainW']} / {$row['PanjangKainI']}</td></tr>
            <tr><th>Lebar Kain (cm)</th><td>{$row['LebarKainW']} / {$row['LebarKainI']}</td></tr>
          </table>";
} else {
    echo "<div class='alert alert-warning'>Data tidak ditemukan.</div>";
}

sqlsrv_free_stmt($stmt);
?>
