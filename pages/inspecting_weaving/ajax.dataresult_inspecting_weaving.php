<?php
require '../../koneksi.php';
header('Content-Type: application/json');

$draw   = $_GET['draw'] ?? 1;
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$search = $_GET['search']['value'] ?? '';

// Filter dinamis
$filterSQL = '';
$searchParam = [];

if (!empty($search)) {
    $filterSQL = "AND a.NoCP LIKE ?";
    $searchParam[] = "%$search%";
}

// Hitung total semua data
$countTotalQuery = "SELECT COUNT(DISTINCT a.NoCP) AS total 
                    FROM dbo.SMCacatDetail a
                    LEFT JOIN dbo.FormInspectHd d ON a.NoCP = d.NoCP 
                    WHERE d.FgCacat = '1'";
$countTotalStmt = sqlsrv_query($conn, $countTotalQuery);
$countTotalRow = sqlsrv_fetch_array($countTotalStmt, SQLSRV_FETCH_ASSOC);
$totalData = $countTotalRow['total'] ?? 0;

// Hitung total setelah filter
$countFilteredQuery = "SELECT COUNT(DISTINCT a.NoCP) AS total 
                       FROM dbo.SMCacatDetail a
                       LEFT JOIN dbo.FormInspectHd d ON a.NoCP = d.NoCP 
                       WHERE d.FgCacat = '1' $filterSQL";
$countFilteredStmt = sqlsrv_query($conn, $countFilteredQuery, $searchParam);
$countFilteredRow = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC);
$totalFiltered = $countFilteredRow['total'] ?? 0;

// Query data utama
$dataQuery = "
    SELECT 
        a.NoCP,
        COUNT(a.NoDetail) AS TotalKodeCacat,
        SUM(
            CASE 
                WHEN a.SMeterKe = a.MeterKe THEN 1  
                ELSE ABS(a.SMeterKe - a.MeterKe)
            END
        ) AS TotalMeterCacat,
        SUM(
            CASE 
                WHEN a.SMeterKe = a.MeterKe THEN 1 * a.PointCacat  
                ELSE ABS(a.SMeterKe - a.MeterKe) * a.PointCacat
            END
        ) AS TotalPointCacat,
        d.PanjangKainI
    FROM dbo.SMCacatDetail a WITH (NOLOCK) 
    LEFT JOIN dbo.FormInspectHd d ON a.NoCP = d.NoCP 
    WHERE d.FgCacat = '1' $filterSQL
    GROUP BY a.NoCP, d.PanjangKainI
    ORDER BY a.NoCP DESC
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";

// Gabungkan parameter pencarian + pagination
$params = array_merge($searchParam, [$start, $length]);
$stmt = sqlsrv_query($conn, $dataQuery, $params);

$data = [];
$no = $start + 1;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $grade = 'C';
    $point = floatval($row['TotalPointCacat']);
    $panjang = floatval($row['PanjangKainI']);

    if ($panjang > 0) {
        $ratio = $point / $panjang;
        if ($ratio <= 0.30) $grade = 'A';
        elseif ($ratio <= 0.60) $grade = 'B';
    }

    $aksi = "<a href='view_result_datacacat.php?noCP=" . urlencode($row['NoCP']) . "' class='btn btn-info btn-sm'>
                <i class='fas fa-eye'></i> View
            </a>";

    $data[] = [
        'no' => $no++,
        'NoCP' => htmlspecialchars($row['NoCP']),
        'TotalKodeCacat' => $row['TotalKodeCacat'],
        'TotalMeterCacat' => $row['TotalMeterCacat'],
        'TotalPointCacat' => $row['TotalPointCacat'],
        'Grade' => $grade,
        'Aksi' => $aksi
    ];
}

// Keluarkan JSON ke DataTables
echo json_encode([
    'draw' => intval($draw),
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);
