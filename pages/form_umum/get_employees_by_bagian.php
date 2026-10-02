<?php
/**
 * API endpoint to get employees from the same bagian (section)
 * Returns JSON array of employees for the dropdown in form_izin_keluar_pabrik
 */
session_start();
header('Content-Type: application/json');

require '../../koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['NamaLengkap']) || !isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$bagian = isset($_GET['bagian']) ? trim($_GET['bagian']) : '';

if (empty($bagian)) {
    echo json_encode(['success' => false, 'message' => 'Bagian parameter required']);
    exit;
}

// Query to get all employees from the same bagian
$sql = "SELECT 
            m_emp.id_emp,
            m_emp.nik, 
            m_emp.nama_lengkap,
            m_dept.dept, 
            m_bag.bagian, 
            m_jab.jabatan, 
            m_emp.telp
        FROM dbo.m_emp
        LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
        LEFT JOIN dbo.m_bag    ON m_subbag.id_bag  = m_bag.id_bag
        LEFT JOIN dbo.m_dept   ON m_bag.id_dept    = m_dept.id_dept
        LEFT JOIN dbo.m_jab    ON m_emp.id_jab     = m_jab.id_jab
        WHERE m_bag.bagian = ?
        AND m_emp.aktif = 1
        ORDER BY m_emp.nama_lengkap ASC";

$params = [$bagian];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    error_log("SQL Error in get_employees_by_bagian.php: " . print_r($errors, true));
    echo json_encode([
        'success' => false, 
        'message' => 'Database error', 
        'error' => $errors,
        'sql' => $sql,
        'params' => $params
    ]);
    exit;
}

$employees = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $employees[] = [
        'id_emp'       => $row['id_emp'] ?? '',
        'nik'          => $row['nik'] ?? '',
        'nama_lengkap' => $row['nama_lengkap'] ?? '',
        'dept'         => $row['dept'] ?? '',
        'bagian'       => $row['bagian'] ?? '',
        'jabatan'      => $row['jabatan'] ?? '',
        'telp'         => $row['telp'] ?? ''
    ];
}

sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $employees]);
?>
