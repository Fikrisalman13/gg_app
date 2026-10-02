<?php
// sample_data_ipal.php - helper to print sample rows for verification
require_once __DIR__ . '/../../koneksi.php';
header('Content-Type: application/json; charset=UTF-8');
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$sql = "
    SELECT TOP 10
        COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal) AS Tanggal,
        COALESCE(ph.Shift, cod.Shift, tss.Shift) AS Shift,
        ph.PekatBesar AS ph_PekatBesar, ph.PekatKecil AS ph_PekatKecil, ph.Anoxit AS ph_Anoxit, ph.EqualSum AS ph_EqualSum,
        ph.Daff1 AS ph_Daff1, ph.Daff2 AS ph_Daff2, ph.Daff3 AS ph_Daff3,
        ph.Aerasi1 AS ph_Aerasi1, ph.Aerasi2 AS ph_Aerasi2, ph.Aerasi3 AS ph_Aerasi3, ph.Aerasi4 AS ph_Aerasi4,
        ph.Sedimen AS ph_Sedimen, ph.Outlet AS ph_Outlet,
        cod.PekatBesar AS cod_PekatBesar, cod.PekatKecil AS cod_PekatKecil, cod.Anoxit AS cod_Anoxit, cod.EqualSum AS cod_EqualSum,
        cod.Daff1 AS cod_Daff1, cod.Daff2 AS cod_Daff2, cod.Daff3 AS cod_Daff3,
        cod.Aerasi1 AS cod_Aerasi1, cod.Aerasi2 AS cod_Aerasi2, cod.Aerasi3 AS cod_Aerasi3, cod.Aerasi4 AS cod_Aerasi4,
        cod.Sedimen AS cod_Sedimen, cod.Dwatring AS cod_Dwatring, cod.SelokanPekat AS cod_SelokanPekat, cod.SelokanReaktif AS cod_SelokanReaktif, cod.Outlet AS cod_Outlet,
        tss.Pekat_Besar AS tss_PekatBesar, tss.Pekat_Kecil AS tss_PekatKecil, tss.Anoxit AS tss_Anoxit, tss.Equal AS tss_EqualSum,
        tss.Daff_1 AS tss_Daff1, tss.Daff_2 AS tss_Daff2, tss.Daff_3 AS tss_Daff3,
        tss.Aerasi_1 AS tss_Aerasi1, tss.Aerasi_2 AS tss_Aerasi2, tss.Aerasi_3 AS tss_Aerasi3, tss.Aerasi_4 AS tss_Aerasi4,
        tss.Sedimen AS tss_Sedimen, tss.Dwatring AS tss_Dwatring, tss.Outlet AS tss_Outlet
    FROM dbo.PH_Air ph
    FULL OUTER JOIN dbo.COD_air cod ON ph.Tanggal = cod.Tanggal AND ph.Shift = cod.Shift
    FULL OUTER JOIN dbo.TSS_Air tss ON COALESCE(ph.Tanggal, cod.Tanggal) = tss.Tanggal AND COALESCE(ph.Shift, cod.Shift) = tss.Shift
    WHERE COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal) BETWEEN ? AND ?
    ORDER BY Tanggal DESC, Shift
";
$params = [$start, $end];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['error' => sqlsrv_errors()], JSON_PRETTY_PRINT);
    exit;
}
$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
