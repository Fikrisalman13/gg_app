<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Data_Karyawan_" . date("Ymd_His") . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// === Ambil filter dari GET ===
$filterDept   = $_GET['filterDept'] ?? '';
$filterBag    = $_GET['filterBag'] ?? '';
$filterSubbag = $_GET['filterSubbag'] ?? '';
$filterJab    = $_GET['filterJab'] ?? '';
$filterGol    = $_GET['filterGol'] ?? '';
$filterStatus = $_GET['filterStatus'] ?? '';

// === Query dasar ===
$sql = "SELECT
            m_emp.nik,
            m_emp.nama_lengkap,
            m_emp.aktif,
            m_dept.dept,
            m_bag.bagian,
            m_subbag.subbag,
            m_jab.jabatan,
            m_gol.golongan
        FROM dbo.m_emp
        LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
        LEFT JOIN dbo.m_bag    ON m_subbag.id_bag = m_bag.id_bag
        LEFT JOIN dbo.m_dept   ON m_bag.id_dept = m_dept.id_dept
        LEFT JOIN dbo.m_jab    ON m_emp.id_jab = m_jab.id_jab
        LEFT JOIN dbo.m_gol    ON m_emp.id_gol = m_gol.id_gol
        WHERE 1=1";

$params = [];
if ($filterDept   !== '') { $sql .= " AND m_dept.id_dept = ?";     $params[] = $filterDept; }
if ($filterBag    !== '') { $sql .= " AND m_bag.id_bag = ?";       $params[] = $filterBag; }
if ($filterSubbag !== '') { $sql .= " AND m_subbag.id_subbag = ?"; $params[] = $filterSubbag; }
if ($filterJab    !== '') { $sql .= " AND m_jab.id_jab = ?";       $params[] = $filterJab; }
if ($filterGol    !== '') { $sql .= " AND m_emp.id_gol = ?";       $params[] = $filterGol; }
if ($filterStatus !== '') { $sql .= " AND m_emp.aktif = ?";        $params[] = $filterStatus; }

$sql .= " ORDER BY m_emp.nik";

// === Eksekusi query ===
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo "<pre>Query gagal!\n";
    echo "SQL: " . $sql . "\n";
    echo "Params: " . print_r($params, true) . "\n";
    echo "Error: " . print_r(sqlsrv_errors(), true) . "</pre>";
    exit;
}

// === Cetak tabel Excel ===
echo "<table border='1'>";
echo "<thead>
        <tr style='background-color:#d9d9d9; font-weight:bold; text-align:center;'>
            <th>No</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Status</th>
            <th>Departemen</th>
            <th>Bagian</th>
            <th>Subbagian</th>
            <th>Jabatan</th>
            <th>Golongan</th>
        </tr>
      </thead>";
echo "<tbody>";

$no = 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>".$no++."</td>";
    echo "<td>".htmlspecialchars($row['nik'])."</td>";
    echo "<td>".htmlspecialchars($row['nama_lengkap'])."</td>";
    echo "<td>".($row['aktif'] == 1 ? 'Aktif' : 'Nonaktif')."</td>";
    echo "<td>".htmlspecialchars($row['dept'])."</td>";
    echo "<td>".htmlspecialchars($row['bagian'])."</td>";
    echo "<td>".htmlspecialchars($row['subbag'])."</td>";
    echo "<td>".htmlspecialchars($row['jabatan'])."</td>";
    echo "<td>".htmlspecialchars($row['golongan'])."</td>";
    echo "</tr>";
}

echo "</tbody></table>";

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
