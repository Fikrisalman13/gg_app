<?php
session_start();
include '../../koneksi.php';
// Ambil permissions dari POST request
$canEdit = $_POST['canEdit'] ?? 0;
$canDelete = $_POST['canDelete'] ?? 0;

$request = $_POST;

// Kolom untuk ORDER (sesuaikan index dengan kolom di tabel HTML)
$columns = [
    0 => 'm_emp.nik',        // No (urut pakai nik)
    1 => 'm_emp.id_emp',     // ID EMP
    2 => 'm_emp.nik',        // NIK
    3 => 'm_emp.nama_lengkap',
    4 => 'm_emp.aktif',
    5 => 'm_dept.dept',
    6 => 'm_bag.bagian',
    7 => 'm_subbag.subbag',
    8 => 'm_jab.jabatan',
    9 => 'm_gol.golongan'
];

// Ambil nilai filter (termasuk filterGol)
$filterDept   = isset($request['filterDept']) ? $request['filterDept'] : '';
$filterBag    = isset($request['filterBag']) ? $request['filterBag'] : '';
$filterSubbag = isset($request['filterSubbag']) ? $request['filterSubbag'] : '';
$filterJab    = isset($request['filterJab']) ? $request['filterJab'] : '';
$filterGol    = isset($request['filterGol']) ? $request['filterGol'] : '';
$filterStatus = isset($request['filterStatus']) ? $request['filterStatus'] : '';

// Hitung total data (tanpa filter)
$sqlTotal = "SELECT COUNT(*) as total FROM dbo.m_emp";
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalData = ($row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) ? $row['total'] : 0;
$totalFiltered = $totalData;

// Query utama (JOIN termasuk m_gol)
$sql = "SELECT 
            m_emp.id_emp,
            m_emp.nik, 
            m_emp.nama_lengkap, 
            m_emp.aktif, 
            m_dept.dept, 
            m_bag.bagian, 
            m_subbag.subbag, 
            m_jab.jabatan,
            m_gol.golongan,
            m_dept.id_dept, 
            m_bag.id_bag, 
            m_subbag.id_subbag, 
            m_jab.id_jab,
            m_emp.id_gol
        FROM dbo.m_emp
        LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
        LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
        LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
        LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
        LEFT JOIN dbo.m_gol ON m_emp.id_gol = m_gol.id_gol
        WHERE 1=1";

$params = [];

// Terapkan filter
if (!empty($filterDept)) {
    $sql .= " AND m_dept.id_dept = ?";
    $params[] = $filterDept;
}

if (!empty($filterBag)) {
    $sql .= " AND m_bag.id_bag = ?";
    $params[] = $filterBag;
}

if (!empty($filterSubbag)) {
    $sql .= " AND m_subbag.id_subbag = ?";
    $params[] = $filterSubbag;
}

if (!empty($filterJab)) {
    $sql .= " AND m_jab.id_jab = ?";
    $params[] = $filterJab;
}

// **Filter Golongan**
if (!empty($filterGol)) {
    // value dari dropdown kita adalah id_gol
    $sql .= " AND m_emp.id_gol = ?";
    $params[] = $filterGol;
}

if ($filterStatus !== '') {
    $sql .= " AND m_emp.aktif = ?";
    $params[] = $filterStatus;
}

// Pencarian global (termasuk golongan)
if (!empty($request['search']['value'])) {
    $search = "%" . $request['search']['value'] . "%";
    $sql .= " AND (
        m_emp.id_emp LIKE ? OR
        m_emp.nik LIKE ? OR 
        m_emp.nama_lengkap LIKE ? OR 
        m_dept.dept LIKE ? OR 
        m_bag.bagian LIKE ? OR 
        m_subbag.subbag LIKE ? OR 
        m_jab.jabatan LIKE ? OR
        m_gol.golongan LIKE ?
    )";
    $params = array_merge($params, [$search, $search, $search, $search, $search, $search, $search, $search]);
}

// Hitung totalFiltered (dengan filter yang diterapkan)
$sqlCount = "SELECT COUNT(*) as total FROM ($sql) as temp";
$stmtCount = sqlsrv_query($conn, $sqlCount, $params);
if ($stmtCount === false) {
    die("Error in COUNT query: " . print_r(sqlsrv_errors(), true));
}
$totalFiltered = ($row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) ? $row['total'] : 0;

// Order & Limit (aman)
$start = isset($request['start']) ? (int)$request['start'] : 0;
$length = isset($request['length']) ? (int)$request['length'] : 10;

$colIndex = isset($request['order'][0]['column']) ? (int)$request['order'][0]['column'] : 1;
$orderDir = isset($request['order'][0]['dir']) ? strtolower($request['order'][0]['dir']) : 'asc';
if (!in_array($orderDir, ['asc','desc'])) $orderDir = 'asc';

$orderCol = isset($columns[$colIndex]) ? $columns[$colIndex] : 'm_emp.nik';

$sql .= " ORDER BY $orderCol $orderDir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $start;
$params[] = $length;

// Ambil data
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Error in main query: " . print_r(sqlsrv_errors(), true));
}

$data = [];
$no = $start + 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $nested = [];
    $nested[] = $no++;
    $nested[] = htmlspecialchars($row['id_emp'] ?? '-');
    $nested[] = htmlspecialchars($row['nik']);
    $nested[] = htmlspecialchars($row['nama_lengkap']);
    $nested[] = $row['aktif'] == 1 
        ? '<span class="badge badge-success">Aktif</span>' 
        : '<span class="badge badge-danger">Nonaktif</span>';
    $nested[] = htmlspecialchars($row['dept'] ?? '-');
    $nested[] = htmlspecialchars($row['bagian'] ?? '-');
    $nested[] = htmlspecialchars($row['subbag'] ?? '-');
    $nested[] = htmlspecialchars($row['jabatan'] ?? '-');
    $nested[] = htmlspecialchars($row['golongan'] ?? '-'); // Golongan
    $nested[] = '
        <div class="d-flex flex-wrap align-items-center" style="gap: 5px; min-width: 80px;">
            <a href="view_emp.php?id='.urlencode($row['nik']).'" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
            <a href="edit_emp.php?nik='.urlencode($row['nik']).'" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
            <button class="btn btn-danger btn-sm btn-delete" data-id="'.htmlspecialchars($row['nik']).'"><i class="fas fa-trash"></i></button>
        </div>
    ';
    $data[] = $nested;
}

// JSON response
header('Content-Type: application/json; charset=utf-8');
$json_data = [
    "draw" => intval($request['draw'] ?? 0),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
];

echo json_encode($json_data);
