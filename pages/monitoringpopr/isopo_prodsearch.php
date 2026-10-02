<?php
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName']) || !$conn3) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $conn3->prepare("SELECT prodcode, prodname FROM smproduct WHERE prodcode IS NOT NULL AND prodcode <> '' AND (UPPER(prodcode) LIKE UPPER(?) OR UPPER(prodname) LIKE UPPER(?)) ORDER BY prodcode LIMIT 50");
if ($stmt === false) {
    echo json_encode([]);
    exit;
}
$like = '%' . $q . '%';
$stmt->execute([$like, $like]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);

