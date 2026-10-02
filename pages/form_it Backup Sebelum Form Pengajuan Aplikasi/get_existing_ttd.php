<?php
session_start();
header('Content-Type: application/json');

// include koneksi
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__FILE__) . '/../koneksi.php';
}
if (file_exists($koneksiPath)) require_once $koneksiPath;

$username = $_SESSION['NamaLengkap'] ?? '';
$result = ['signature_path' => null];

if ($username && isset($conn) && $conn !== false) {
    try {
        // Get UserId
        $sqlEmp = "SELECT id_emp FROM dbo.m_emp WHERE nama_lengkap = ?";
        $stmtEmp = sqlsrv_query($conn, $sqlEmp, [$username]);
        $empId = null;
        if ($stmtEmp && $row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
            $empId = $row['id_emp'];
        }
        if ($stmtEmp) sqlsrv_free_stmt($stmtEmp);

        if ($empId) {
            $sqlUser = "SELECT UserId FROM dbo.SMUserMs WHERE EmpId = ?";
            $stmtUser = sqlsrv_query($conn, $sqlUser, [$empId]);
            $userId = null;
            if ($stmtUser && $row = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
                $userId = $row['UserId'];
            }
            if ($stmtUser) sqlsrv_free_stmt($stmtUser);

            // Get the existing TTD template path
            if ($userId) {
                $sql = "SELECT TOP 1 SignaturePath FROM dbo.User_TTD_Template WHERE UserId = ? AND GroupRole = ?";
                $stmt = sqlsrv_query($conn, $sql, [$userId, 'Pemohon']);
                if ($stmt !== false) {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    if ($row && !empty($row['SignaturePath'])) {
                        $result['signature_path'] = $row['SignaturePath'];
                    }
                    sqlsrv_free_stmt($stmt);
                }
            }
        }
    } catch (Exception $ex) {
        // Silent fail, return null
    }
}

echo json_encode($result);
exit;
