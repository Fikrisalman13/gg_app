<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    echo json_encode([]);
    exit;
}

$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode([]);
    exit;
}

// Fetch groups that this user doesn't have yet
$sql = "
    SELECT group_name 
    FROM (SELECT DISTINCT group_name FROM planning_trustee_group) g
    WHERE group_name NOT IN (
        SELECT group_name 
        FROM planning_trustee_user_group 
        WHERE username = ?
    )
    ORDER BY group_name ASC
";

$params = [$username];
$stmt = sqlsrv_query($conn, $sql, $params);

$groups = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $groups[] = $row['group_name'];
    }
}

header('Content-Type: application/json');
echo json_encode($groups);
