<?php
include '../../koneksi.php';

if (isset($_POST['bag_id']) && !empty($_POST['bag_id'])) {
    $bagId = $_POST['bag_id'];
    
    $sql = "SELECT id_subbag, subbag FROM dbo.m_subbag WHERE id_bag = ? ORDER BY subbag";
    $stmt = sqlsrv_query($conn, $sql, [$bagId]);
    
    $options = '';
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $options .= '<option value="' . $row['id_subbag'] . '">' . htmlspecialchars($row['subbag']) . '</option>';
        }
    }
    
    echo $options;
} else {
    echo '';
}
?>
