<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

$userName = $_GET['username'] ?? '';

if (empty($userName)) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

// Fetch theme based on username
$query = "SELECT Theme FROM SMUserMs WHERE UserName = ?";
$params = array($userName);
$stmt = sqlsrv_query($conn, $query, $params);

if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $theme = !empty($row['Theme']) ? $row['Theme'] : 'danger';
    echo json_encode(['status' => 'success', 'theme' => $theme]);
} else {
    // Return status instead of forcing a fallback theme
    echo json_encode(['status' => 'not_found']);
}

if ($stmt !== false) {
    sqlsrv_free_stmt($stmt);
}
sqlsrv_close($conn);
