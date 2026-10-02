<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');
if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'results' => [], 'message' => 'Sesi login telah berakhir.']);
    exit;
}
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/label_jual_lookup.php';
try {
    $search = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($search) < 2) { echo json_encode(['ok' => true, 'results' => []]); exit; }
    $statement = sqlsrv_query(
        $conn,
        "SELECT DISTINCT LTRIM(RTRIM(no_cp)) AS no_cp
         FROM dbo.resep_obat
         WHERE NULLIF(LTRIM(RTRIM(no_cp)), '') IS NOT NULL",
    );
    if ($statement === false) {
        throw new RuntimeException('Gagal membaca No CP resep lokal.');
    }

    $productionNumbers = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $productionNumbers[] = $row['no_cp'];
    }
    $labels = recipeSearchLocalSaleLabels($conn3, $productionNumbers, $search, 30);
    echo json_encode(['ok' => true, 'results' => array_map(static fn(string $label): array => ['id' => $label, 'text' => $label], $labels)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    $requestId = bin2hex(random_bytes(8));
    error_log("[$requestId] label_jual_options: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'results' => [], 'message' => 'Gagal memuat pilihan Label Jual.', 'request_id' => $requestId]);
}
