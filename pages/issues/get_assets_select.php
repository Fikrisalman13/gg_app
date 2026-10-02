<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../koneksi.php';

// Check auth
if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get filter type
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build Query
// We want: id_asset, and a display label
// Display label: [kode_asset_seq] merk tipe (lokasi)
$sql = "
    SELECT 
        a.id_asset, 
        a.kode_asset_seq, 
        m.nama_merk, 
        t.nama_tipe,
        l.nama_lokasi,
        kat.nama_kategori,
        e.nama_lengkap,
        a.keterangan
    FROM dbo.m_asset a
    LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
    LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
    LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
    LEFT JOIN dbo.m_kategori kat ON a.id_kategori = kat.id_kategori
    LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
    WHERE 1=1
";

$params = [];

// Apply filter if 'cctv' — include both 'CCTV' and 'IP Kamera' categories
if ($type === 'cctv') {
    $sql .= " AND (kat.nama_kategori LIKE ? OR kat.nama_kategori LIKE ? OR kat.nama_kategori LIKE ?)";
    $params[] = '%CCTV%';
    $params[] = '%IP Kamera%';
    $params[] = '%Kamera Analog%';
}

// Client filtering
// Logic:
// 1. If client_id is numeric, show assets for that client.
// 2. If client_id is 'unassigned' or empty (and checking unassigned), show assets where id_emp IS NULL.
// 3. If client_id is not passed, show all (default behavior) or allow normal flow.
// Based on user request "Pilih Client Dulu", we likely pass client_id.
// Let's expect 'client_id' param.
if (isset($_GET['client_id'])) {
    $clientId = $_GET['client_id'];
    if (is_numeric($clientId)) {
        $sql .= " AND a.id_emp = ?";
        $params[] = $clientId;
    } elseif ($clientId === '' || $clientId === 'null') {
        // "Untuk Asset yg tidak mempunyai client hanya Muncul Jika Pilihan Client itu -"
        // This means if I select "-" (Empty/Clear), show unassigned assets.
        $sql .= " AND a.id_emp IS NULL";
    }
}

// Apply filter if 'hardware' (optional, if we want to restrict 'Instalasi Asset' etc to non-software?)
// For now, per instructions, we only explicitly filter CCTV. 
// "Instalasi Asset" might imply any asset. 

$sql .= " ORDER BY a.kode_asset_seq ASC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'SQL Error', 'details' => sqlsrv_errors()]);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format label
    // [A001] Merk Tipe - Lokasi (or Client)
    $merk = $row['nama_merk'] ?? '-';
    $tipe = $row['nama_tipe'] ?? '';
    $lokasi = $row['nama_lokasi'] ?? '';
    $kode = $row['kode_asset_seq'] ?? '???';
    $kategori = $row['nama_kategori'] ?? '';
    $client = $row['nama_lengkap'] ?? ''; // from m_emp
    $keterangan = $row['keterangan'] ?? '';

    // Logic 1: If category is Peripheral, use keterangan instead of Merk Tipe
    if (stripos($kategori, 'Peripheral') !== false) {
        $fullDeviceName = trim($keterangan);
    } else {
        $fullDeviceName = trim("$merk $tipe");
    }
    
    if ($fullDeviceName === '') $fullDeviceName = 'Unknown Asset';
    
    // Logic 2: If client exists, use client (already in name? no, user said remove).
    // User request: "If NOT have client, add Location behind name".
    // "Dari [CAN-001] Infinity Outdoor Menjadi [CAN-001] Infinity Outdoor - Produksi DF"
    
    $label = "[$kode] $fullDeviceName";
    
    if (empty($client)) {
        // No client, append location
        if ($lokasi !== '') {
            $label .= " - $lokasi";
        }
    }

    $results[] = [
        'id' => $row['id_asset'],
        'text' => $label,
        'kategori' => $kategori // useful for debugging
    ];
}

sqlsrv_free_stmt($stmt);
echo json_encode($results);

