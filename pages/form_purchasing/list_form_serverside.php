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
$reportType = isset($_POST['report_type']) ? trim($_POST['report_type']) : '';

// Cek ketersediaan tabel
$chkCod = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_COD'");
$hasCod = ($chkCod && sqlsrv_fetch_array($chkCod));

$chkBpu = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_Pengembalian_Uang'");
$hasBpu = ($chkBpu && sqlsrv_fetch_array($chkBpu));

if (!$hasCod && !$hasBpu) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

// Bangun query sumber data
$sources = [];

if ($hasCod && ($reportType === '' || $reportType === 'rekap_cash_on_delivery')) {
    $sources[] = "
        SELECT 
            id,
            ticket,
            ref_po_no,
            'Pengajuan Cash On Delivery' AS kategori,
            'cash_on_delivery' AS form_type,
            nama_pemohon,
            departemen,
            tgl_pengajuan AS tanggal,
            status,
            created_at,
            'detail_cash_on_delivery.php' AS detail_page,
            supplier,
            keterangan,
            is_deleted
        FROM dbo.Form_Purchasing_COD
        WHERE (is_deleted = 0 OR is_deleted IS NULL)
    ";
}

if ($hasBpu && ($reportType === '' || $reportType === 'rekap_bukti_pengembalian_uang')) {
    $sources[] = "
        SELECT 
            id,
            ticket,
            ref_po_no,
            'Bukti Pengembalian Uang' AS kategori,
            'bukti_pengembalian_uang' AS form_type,
            nama_pemohon,
            departemen,
            tanggal AS tanggal,
            status,
            created_at,
            'detail_bukti_pengembalian_uang.php' AS detail_page,
            supplier,
            keterangan,
            is_deleted
        FROM dbo.Form_Purchasing_Pengembalian_Uang
        WHERE (is_deleted = 0 OR is_deleted IS NULL)
    ";
}

if (empty($sources)) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

$unionQuery = "(" . implode(" UNION ALL ", $sources) . ") AS u";

// Filter kondisi
$where = ["1=1"];
$params = [];

if (!empty($searchValue)) {
    $where[] = "(u.ticket LIKE ? OR u.nama_pemohon LIKE ? OR u.departemen LIKE ? OR u.keterangan LIKE ? OR u.ref_po_no LIKE ? OR u.supplier LIKE ? OR u.kategori LIKE ?)";
    $searchParam = "%$searchValue%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($statusFilter)) {
    $where[] = "u.status = ?";
    $params[] = $statusFilter;
}

if (!empty($tglDari)) {
    $where[] = "u.tanggal >= ?";
    $params[] = $tglDari;
}

if (!empty($tglSampai)) {
    $where[] = "u.tanggal <= ?";
    $params[] = $tglSampai;
}

$whereSql = implode(' AND ', $where);

// Count total
$totalStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM $unionQuery");
$totalRow = $totalStmt ? sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$recordsTotal = $totalRow['total'] ?? 0;

// Count filtered
$filteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM $unionQuery WHERE $whereSql", $params);
$filteredRow = $filteredStmt ? sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$recordsFiltered = $filteredRow['total'] ?? 0;

// Fetch paged data
$sql = "SELECT * FROM $unionQuery 
        WHERE $whereSql 
        ORDER BY u.created_at DESC, u.id DESC 
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
        if (isset($row['tanggal'])) {
            $tgl = $row['tanggal'] instanceof DateTime ? $row['tanggal']->format('d/m/Y') : date('d/m/Y', strtotime($row['tanggal']));
        }

        $detailPage = $row['detail_page'] ?? 'detail_cash_on_delivery.php';
        $formType = $row['form_type'] ?? 'cash_on_delivery';

        $aksi = '<div class="btn-group">';
        $aksi .= '<button type="button" class="btn btn-info btn-sm action-btn btn-detail" data-ticket="' . $ticket . '" data-url="' . $detailPage . '" title="Detail"><i class="fas fa-eye"></i></button>';
        $aksi .= '<a href="' . $detailPage . '?ticket=' . urlencode($ticket) . '" target="_blank" class="btn btn-danger btn-sm action-btn" title="Cetak Dokumen"><i class="fas fa-file-pdf"></i></a>';
        $aksi .= '<button type="button" class="btn btn-warning btn-sm action-btn btn-edit" data-ticket="' . $ticket . '" data-form-type="' . $formType . '" title="Edit"><i class="fas fa-edit"></i></button>';
        $aksi .= '<button type="button" class="btn btn-danger btn-sm action-btn btn-delete" data-ticket="' . $ticket . '" title="Hapus"><i class="fas fa-trash"></i></button>';
        $aksi .= '</div>';

        $noPo = trim($row['ref_po_no'] ?? '');
        $noPoHtml = !empty($noPo) && $noPo !== '-' 
            ? '<span class="badge badge-light border font-weight-bold text-dark px-2 py-1"><i class="fas fa-file-invoice mr-1 text-primary"></i>' . htmlspecialchars($noPo) . '</span>'
            : '<span class="text-muted">-</span>';

        $kategori = htmlspecialchars($row['kategori'] ?? '');
        $kategoriBadge = ($formType === 'bukti_pengembalian_uang') 
            ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-undo-alt mr-1"></i>' . $kategori . '</span>'
            : '<span class="badge badge-primary px-2 py-1"><i class="fas fa-money-bill-wave mr-1"></i>' . $kategori . '</span>';

        $data[] = [
            'ticket' => $ticket,
            'no_po' => $noPoHtml,
            'kategori' => $kategoriBadge,
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
