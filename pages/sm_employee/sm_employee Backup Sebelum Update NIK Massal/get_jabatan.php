<?php
include '../../koneksi.php';

// Ambil parameter filter
$deptId = isset($_POST['dept_id']) ? $_POST['dept_id'] : '';
$bagId = isset($_POST['bag_id']) ? $_POST['bag_id'] : '';
$subbagId = isset($_POST['subbag_id']) ? $_POST['subbag_id'] : '';

// Query untuk mengambil jabatan berdasarkan filter
$sql = "SELECT DISTINCT m_jab.id_jab, m_jab.jabatan 
        FROM dbo.m_jab 
        INNER JOIN dbo.m_emp ON m_jab.id_jab = m_emp.id_jab
        LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
        LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
        LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
        WHERE 1=1";

$params = [];

// Terapkan filter berdasarkan parameter
if (!empty($deptId)) {
    $sql .= " AND m_dept.id_dept = ?";
    $params[] = $deptId;
}

if (!empty($bagId)) {
    $sql .= " AND m_bag.id_bag = ?";
    $params[] = $bagId;
}

if (!empty($subbagId)) {
    $sql .= " AND m_subbag.id_subbag = ?";
    $params[] = $subbagId;
}

$sql .= " ORDER BY m_jab.jabatan";

$stmt = sqlsrv_query($conn, $sql, $params);

$options = '';
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options .= '<option value="' . $row['id_jab'] . '">' . htmlspecialchars($row['jabatan']) . '</option>';
    }
}

echo $options;
?>