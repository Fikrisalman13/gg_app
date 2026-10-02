<?php
// history_asset_serverside.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../koneksi.php';

// --- Basic checks ---
if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!$conn) {
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

/**
 * Detects ticket numbers in text (e.g. TKT-27-12-2025-0001) 
 * and wraps them in a link to the ticket detail page.
 * Distinguishes between existing (blue) and deleted (red) tickets.
 */
function linkTicketNumbers($text, $conn) {
    if (empty($text) || !$conn) return $text;
    
    // Pattern for TKT-DD-MM-YYYY-XXXX
    return preg_replace_callback('/(TKT-\d{2}-\d{2}-\d{4}-\d+)/', function($matches) use ($conn) {
        $ticketNo = $matches[1];
        
        // Check if ticket exists in database
        $exists = false;
        $sql = "SELECT TOP 1 ticket_id FROM dbo.tickets WHERE ticket_no = ?";
        $stmt = sqlsrv_query($conn, $sql, [$ticketNo]);
        if ($stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $exists = true;
        }
        if ($stmt) sqlsrv_free_stmt($stmt);

        if ($exists) {
            return '<a href="/gg_app/pages/ticket/detail.php?no='.$ticketNo.'" class="text-primary font-weight-bold" title="View Ticket Detail">'.$ticketNo.'</a>';
        } else {
            return '<span class="text-danger font-weight-bold" title="Ticket telah dihapus atau tidak ditemukan">'.$ticketNo.'</span>';
        }
    }, $text);
}

// --- Read DataTables params (robust) ---
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

// Accept either default DataTables search or custom key 'search_value'
$searchValue = '';
if (!empty($_POST['search_value'])) {
    $searchValue = trim($_POST['search_value']);
} elseif (!empty($_POST['search']['value'])) {
    $searchValue = trim($_POST['search']['value']);
}

// Ordering: map DataTables column names to real DB columns
$columnIndex = $_POST['order'][0]['column'] ?? 1; // Default created_at
$columnName = $_POST['columns'][$columnIndex]['data'] ?? 'created_at';
$columnDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

$validColumns = [
    "created_at"      => "h.created_at",
    "created_by"      => "h.created_by",
    "kode_asset_seq"  => "a.kode_asset_seq",
    "merk_tipe"       => "m.nama_merk",
    "perubahan_status"=> "h.new_status", // Can't easily sort Old->New combination directly, sort by New instead
    "jenis_perubahan" => "h.jenis_perubahan",
    "note"            => "h.note"
];

// Choose safe order column (fallback to created_at)
$orderColumn = $validColumns[$columnName] ?? "h.created_at";

// --- Filters from client ---
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';

// --- Build WHERE clauses and params safely ---
$whereClauses = [];
$params = [];

if ($startDate !== '') {
    $whereClauses[] = "CAST(h.created_at AS DATE) >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $whereClauses[] = "CAST(h.created_at AS DATE) <= ?";
    $params[] = $endDate;
}

// Global search across multiple columns
$searchClauses = [];
if ($searchValue !== '') {
    // columns to search
    $searchCols = [
        "a.kode_asset_seq",
        "m.nama_merk",
        "t.nama_tipe",
        "h.created_by",
        "h.jenis_perubahan",
        "h.old_status",
        "h.new_status",
        "h.note"
    ];
    foreach ($searchCols as $col) {
        $searchClauses[] = "$col LIKE ?";
        $params[] = '%' . $searchValue . '%';
    }
}

// Combine where
$whereSql = "WHERE 1=1";
if (!empty($whereClauses)) {
    $whereSql .= " AND " . implode(" AND ", $whereClauses);
}
if (!empty($searchClauses)) {
    $whereSql .= " AND (" . implode(" OR ", $searchClauses) . ")";
}

// --- recordsTotal (without any filters/search) ---
// For performance we count from main table only
$totalSql = "SELECT COUNT(*) AS total FROM dbo.asset_history h";
$totalStmt = sqlsrv_query($conn, $totalSql);
if ($totalStmt === false) {
    echo json_encode(['error' => 'SQL Error total', 'detail' => sqlsrv_errors()]);
    exit;
}
$totalRow = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC);
$recordsTotal = intval($totalRow['total'] ?? 0);
if ($totalStmt !== false) sqlsrv_free_stmt($totalStmt);

// --- recordsFiltered (with filters + search) ---
$filteredCountSql = "
    SELECT COUNT(*) AS total
    FROM dbo.asset_history h
    LEFT JOIN dbo.m_asset a ON h.id_asset = a.id_asset
    LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
    LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
    $whereSql
";
$filteredStmt = sqlsrv_query($conn, $filteredCountSql, $params);
if ($filteredStmt === false) {
    echo json_encode(['error' => 'SQL Error filtered count', 'detail' => sqlsrv_errors()]);
    exit;
}
$filteredRow = sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC);
$recordsFiltered = intval($filteredRow['total'] ?? 0);
if ($filteredStmt !== false) sqlsrv_free_stmt($filteredStmt);

// --- Data query (apply joins, where, order, pagination) ---
$dataSql = "
    SELECT h.id_history, h.id_asset, h.old_status, h.new_status, h.note, h.jenis_perubahan, 
           h.created_by, h.created_at,
           a.kode_asset_seq, m.nama_merk, t.nama_tipe
    FROM dbo.asset_history h
    LEFT JOIN dbo.m_asset a ON h.id_asset = a.id_asset
    LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
    LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
    $whereSql
    ORDER BY $orderColumn $columnDir
";

// Pagination: handle length = -1 (show all)
if ($length != -1) {
    // OFFSET-FETCH requires ORDER BY (we have it)
    $dataSql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    // For OFFSET-FETCH add pagination params at end
    $dataParams = array_merge($params, [$start, $length]);
} else {
    $dataParams = $params;
}

// Execute
$stmt = sqlsrv_query($conn, $dataSql, $dataParams);
if ($stmt === false) {
    echo json_encode(['error' => 'SQL Error data query', 'detail' => sqlsrv_errors()]);
    exit;
}

// Fetch rows
$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $createdAt = '';
    if (isset($row['created_at'])) {
        if ($row['created_at'] instanceof DateTime) {
            $createdAt = $row['created_at']->format('Y-m-d\TH:i:s');
        } else {
            $createdAt = $row['created_at'];
        }
    }

    $oldStatus = $row['old_status'] ?? '-';
    $newStatus = $row['new_status'] ?? '-';
    $perubahanStatus = $oldStatus . ' &rarr; ' . $newStatus;

    $merk = $row['nama_merk'] ?? '';
    $tipe = $row['nama_tipe'] ?? '';
    $merkTipe = trim($merk . ($merk !== '' && $tipe !== '' ? ' / ' : '') . $tipe);

    $data[] = [
        'id_history' => $row['id_history'],
        'id_asset' => $row['id_asset'],
        'kode_asset_seq' => $row['kode_asset_seq'],
        'merk_tipe' => $merkTipe,
        'created_at' => $createdAt,
        'created_by' => $row['created_by'],
        'jenis_perubahan' => $row['jenis_perubahan'],
        'perubahan_status' => $perubahanStatus,
        'note' => linkTicketNumbers($row['note'], $conn)
    ];
}
if ($stmt !== false) sqlsrv_free_stmt($stmt);

// --- Output JSON ---
$response = [
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $data
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
