<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) exit(json_encode([]));

$group = $_GET['group'] ?? '';
if (empty($group)) exit(json_encode([]));

$usernames = [];
$sql = "SELECT username FROM planning_user_group WHERE group_name = ?";
$stmt = sqlsrv_query($conn, $sql, [$group]);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $usernames[] = $row['username'];
    }
}

echo json_encode($usernames);
?>
