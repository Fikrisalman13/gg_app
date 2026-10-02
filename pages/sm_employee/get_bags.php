<?php
include '../../koneksi.php';

if (isset($_POST['dept_id']) && !empty($_POST['dept_id'])) {
    $deptId = $_POST['dept_id'];
    
    $sql = "SELECT id_bag, bagian FROM dbo.m_bag WHERE id_dept = ? ORDER BY bagian";
    $stmt = sqlsrv_query($conn, $sql, [$deptId]);
    
    $options = '';
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $options .= '<option value="' . $row['id_bag'] . '">' . htmlspecialchars($row['bagian']) . '</option>';
        }
    }
    
    echo $options;
} else {
    echo '';
}
?>
