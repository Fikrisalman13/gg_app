<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__DIR__, 2) . '/koneksi.php';
}
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['error' => 'File koneksi database tidak ditemukan']);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search_value']) ? trim($_POST['search_value']) : '';
$statusFilter = isset($_POST['status_filter']) ? trim($_POST['status_filter']) : '';
$tglDari = isset($_POST['tanggal']) ? trim($_POST['tanggal']) : '';
$tglSampai = isset($_POST['tanggal_sampai']) ? trim($_POST['tanggal_sampai']) : '';

// Cek apakah tabel Form COD / Form Purchasing sudah ada
$tableCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_COD'");
$tableExists = ($tableCheck && sqlsrv_fetch_array($tableCheck));

if (!$tableExists) {
    // Jika tabel belum ada, kembalikan data kosong agar DataTables tetap berjalan normal
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

// Jika tabel sudah ada, query data
$where = ["1=1"];
$params = [];

if (!empty($searchValue)) {
    $where[] = "(ticket LIKE ? OR nama_pemohon LIKE ? OR departemen LIKE ? OR keterangan LIKE ? OR ref_po_no LIKE ? OR supplier LIKE ?)";
    $searchParam = "%$searchValue%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($statusFilter)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($tglDari)) {
    $where[] = "tgl_pengajuan >= ?";
    $params[] = $tglDari;
}

if (!empty($tglSampai)) {
    $where[] = "tgl_pengajuan <= ?";
    $params[] = $tglSampai;
}

$whereSql = implode(' AND ', $where);

// Count total
$totalStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Form_Purchasing_COD");
$totalRow = $totalStmt ? sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$recordsTotal = $totalRow['total'] ?? 0;

// Count filtered
$filteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Form_Purchasing_COD WHERE $whereSql", $params);
$filteredRow = $filteredStmt ? sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$recordsFiltered = $filteredRow['total'] ?? 0;

// Fetch data
$sql = "SELECT * FROM Form_Purchasing_COD 
        WHERE $whereSql 
        ORDER BY id DESC 
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$paramsPaged = array_merge($params, [$start, $length]);
$stmt = sqlsrv_query($conn, $sql, $paramsPaged);

$data = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ticket = htmlspecialchars($row['ticket'] ?? '');
        $status = htmlspecialchars($row['status'] ?? 'Pending');
        $badgeClass = 'badge-warning';
        if ($status === 'Approved') $badgeClass = 'badge-success';
        elseif ($status === 'Ditolak') $badgeClass = 'badge-danger';

        $tgl = '';
        if (isset($row['tgl_pengajuan'])) {
            $tgl = $row['tgl_pengajuan'] instanceof DateTime ? $row['tgl_pengajuan']->format('d/m/Y') : date('d/m/Y', strtotime($row['tgl_pengajuan']));
        }

        $aksi = '<div class="btn-group">';
        $aksi .= '<button type="button" class="btn btn-info btn-sm action-btn btn-detail" data-ticket="' . $ticket . '" title="Detail"><i class="fas fa-eye"></i></button>';
        $aksi .= '<a href="detail_cash_on_delivery.php?ticket=' . urlencode($ticket) . '" target="_blank" class="btn btn-danger btn-sm action-btn" title="Cetak Formulir SUM-FM-PB-004"><i class="fas fa-file-pdf"></i></a>';
        $aksi .= '<button type="button" class="btn btn-warning btn-sm action-btn btn-edit" data-ticket="' . $ticket . '" title="Edit"><i class="fas fa-edit"></i></button>';
        $aksi .= '<button type="button" class="btn btn-danger btn-sm action-btn btn-delete" data-ticket="' . $ticket . '" title="Hapus"><i class="fas fa-trash"></i></button>';
        $aksi .= '</div>';

        $noPo = trim($row['ref_po_no'] ?? '');
        $noPoHtml = !empty($noPo) && $noPo !== '-' 
            ? '<span class="badge badge-light border font-weight-bold text-dark px-2 py-1"><i class="fas fa-file-invoice mr-1 text-primary"></i>' . htmlspecialchars($noPo) . '</span>'
            : '<span class="text-muted">-</span>';

        $data[] = [
            'ticket' => $ticket,
            'no_po' => $noPoHtml,
            'kategori' => 'Pengajuan Cash On Delivery',
            'nama_pemohon' => htmlspecialchars($row['nama_pemohon'] ?? ''),
            'departemen' => htmlspecialchars($row['departemen'] ?? ''),
            'tgl_pengajuan' => $tgl,
            'status_ticket' => '<span class="badge ' . $badgeClass . '">' . $status . '</span>',
            'aksi' => $aksi
        ];
    }
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
]);
