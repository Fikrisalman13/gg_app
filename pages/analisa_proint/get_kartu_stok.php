<?php
session_start();
include '../../koneksi3.php'; // koneksi PostgreSQL via PDO

header('Content-Type: application/json');

if (!isset($_GET['prodcode'], $_GET['startdate'], $_GET['enddate'])) {
    echo json_encode(['data' => []]);
    exit;
}

$prodcode = $_GET['prodcode'];
$startdate = $_GET['startdate'];
$enddate = $_GET['enddate'];

try {
    $sql = "SELECT * FROM get_kartu_stok(:prodcode, :startdate, :enddate)";
    $stmt = $conn3->prepare($sql);
    $stmt->execute([
        ':prodcode' => $prodcode,
        ':startdate' => $startdate,
        ':enddate' => $enddate
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $results]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage(), 'data' => []]);
}
