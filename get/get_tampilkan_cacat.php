<?php
include '../koneksi.php';

if (!isset($_POST['noCP'])) {
    exit("<div class='alert alert-danger'>No CP tidak ditemukan.</div>");
}

$noCP = trim($_POST['noCP']); // Bersihkan spasi yang tidak perlu

// Pastikan koneksi database tersedia
if (!$conn) {
    exit("<div class='alert alert-danger'>Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true) . "</div>");
}

$sql = "SELECT
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
        FROM
            dbo.SMCacatDetail AS c
            LEFT JOIN dbo.SMCacat AS d ON c.CacatId = d.CacatId
            LEFT JOIN dbo.SMShiftWInspector AS e ON c.ShiftId = e.ShiftId
            LEFT JOIN dbo.FormInspectHd AS f ON c.NoCP = f.NoCP
        WHERE c.NoCP = ?
        ORDER BY c.NoDetail ASC";

$params = [$noCP];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    exit("<div class='alert alert-danger'>Kesalahan SQL: " . print_r(sqlsrv_errors(), true) . "</div>");
}

$totalPoint = 0;
$panjangKainI = 0;
$no = 1;

if (sqlsrv_has_rows($stmt)) {
    echo "<div class='table-responsive'>
            <table class='table table-bordered table-striped'>
                <thead class='thead-dark'>
                    <tr>
                        <th>No</th>
                        <th>Kode Cacat</th>
                        <th>Nama Cacat</th>
                        <th>No Detail</th>
                        <th>Meter Ke</th>
                        <th>SMeter Ke</th>
                        <th>Point Cacat</th>
                        <th>Shift</th>
                    </tr>
                </thead>
                <tbody>";

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $totalPoint += $row['PointCacat']; // Menjumlahkan Total Point
        $panjangKainI = $row['PanjangKainI'];
        
        echo "<tr>
                <td>{$no}</td>
                <td>" . htmlspecialchars($row['CacatKode']) . "</td>
                <td>" . htmlspecialchars($row['CacatName']) . "</td>
                <td>" . htmlspecialchars($row['NoDetail']) . "</td>
                <td>" . htmlspecialchars($row['MeterKe']) . "</td>
                <td>" . htmlspecialchars($row['SMeterKe']) . "</td>
                <td>" . htmlspecialchars($row['PointCacat']) . "</td>
                <td>" . htmlspecialchars($row['ShiftKode']) . "</td>
              </tr>";
        $no++;
    }
    
    // Hitung Point Grade
    $pointGrade = $panjangKainI > 0 ? $totalPoint / $panjangKainI : 0;

    // Tentukan Grade
    if ($pointGrade >= 0.0 && $pointGrade <= 0.30) {
        $grade = 'A';
    } elseif ($pointGrade > 0.30 && $pointGrade <= 0.60) {
        $grade = 'B';
    } else {
        $grade = 'C';
    }

    echo "</tbody>
            <tfoot>
                <tr class='table-secondary'>
                    <td colspan='6' class='text-end fw-bold'>Total Point:</td>
                    <td class='text-center fw-bold text-danger'>{$totalPoint}</td>
                    <td></td>
                </tr>
                <tr class='table-secondary'>
                    <td colspan='6' class='text-end fw-bold'>Point Grade:</td>
                    <td class='text-center fw-bold text-danger'>" . number_format($pointGrade, 2) . "</td>
                    <td></td>
                </tr>
                <tr class='table-secondary'>
                    <td colspan='6' class='text-end fw-bold'>Grade:</td>
                    <td class='text-center fw-bold text-danger'>{$grade}</td>
                    <td></td>
                </tr>
            </tfoot>
          </table>
        </div>";
} else {
    echo "<div class='alert alert-warning'>Tidak ada data cacat untuk No CP ini. Pastikan No CP sudah benar dan memiliki data terkait.</div>";
}
?>
