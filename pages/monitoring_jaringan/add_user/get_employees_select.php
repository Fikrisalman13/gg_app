<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once '../../../koneksi.php';

header('Content-Type: text/html; charset=utf-8');

$out = '';
$sqlEmp = "SELECT m_emp.nik, m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
FROM dbo.m_emp 
LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
WHERE m_emp.aktif = 1 
ORDER BY m_dept.dept ASC, m_emp.nama_lengkap ASC";

$stmtEmp = sqlsrv_query($conn, $sqlEmp);
if ($stmtEmp) {
    while ($row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
        $nik = htmlspecialchars($row['nik']);
        $nama = htmlspecialchars($row['nama_lengkap']);
        $dept = htmlspecialchars($row['dept'] ?? $row['bagian'] ?? '');
        $bagian = htmlspecialchars($row['bagian'] ?? '');

        $label = trim(($dept ? $dept : $bagian) . ' - ' . $nama);
        $out .= '<option value="' . $nik . '" data-name="' . $nama . '" data-dept="' . $dept . '" data-bagian="' . $bagian . '">' . $label . '</option>' . "\n";
    }
}

echo $out;

?>
