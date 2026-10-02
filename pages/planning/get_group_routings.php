<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) exit(json_encode([]));

$group = $_GET['group'] ?? '';
if (empty($group)) exit(json_encode([]));

$routings = [];
$sql = "SELECT rtg_name FROM planning_group_rtg WHERE group_name = ?";
$stmt = sqlsrv_query($conn, $sql, [$group]);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $routings[] = $row['rtg_name'];
    }
}

echo json_encode($routings);
?>
