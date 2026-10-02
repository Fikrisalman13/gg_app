<?php
// pages/resep_obat/get_machines.php
// Fetch machine list from PostgreSQL (conn3) for the Production modal
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../koneksi3.php';

$keyword = trim($_GET['q'] ?? '');

try {
    $where = "WHERE UPPER(COALESCE(m.facode, '')) LIKE 'FAM%'";
    $params = [];

    if ($keyword !== '') {
        $where .= " AND (COALESCE(m.faname,'') ILIKE :kw OR COALESCE(m.facode,'') ILIKE :kw)";
        $params[':kw'] = '%' . $keyword . '%';
    }

    $sql = "SELECT m.famasterid, m.facode, m.faname
            FROM famaster m
            $where
            ORDER BY m.faname ASC
            LIMIT 100";

    $stmt = $conn3->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for Select2
    $results = array_map(function($r) {
        return [
            'id'   => $r['facode'],
            'text' => trim($r['faname']) . ' (' . trim($r['facode']) . ')',
            'faname' => trim($r['faname']),
            'facode' => trim($r['facode']),
        ];
    }, $rows);

    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
