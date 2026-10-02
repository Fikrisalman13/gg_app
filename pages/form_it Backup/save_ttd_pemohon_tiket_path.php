<?php
session_start();
header('Content-Type: application/json');

// include koneksi
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__FILE__) . '/../koneksi.php';
}
if (file_exists($koneksiPath)) require_once $koneksiPath;

$result = ['success' => false, 'message' => 'Error'];
$username = $_SESSION['NamaLengkap'] ?? '';
$ticket = $_POST['ticket'] ?? '';
$signaturePath = $_POST['signature_path'] ?? '';

if ($username && $ticket && $signaturePath && isset($conn) && $conn !== false) {
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

            if ($userId) {
                $now = date('Y-m-d H:i:s');

                // Insert/update using IF EXISTS pattern
                $sql = "IF EXISTS (SELECT 1 FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?)
                        UPDATE Form_Pengajuan_Barang_TTD 
                        SET SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = ?
                        WHERE Ticket = ?
                        ELSE
                        INSERT INTO Form_Pengajuan_Barang_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
                        VALUES (?, ?, ?, ?, ?, ?)";

                $params = [
                    $ticket,                    // for EXISTS check
                    $signaturePath,             // UPDATE: SignaturePath
                    $userId,                    // UPDATE: SignedByUserId
                    $username,                  // UPDATE: SignedByUserName
                    $now,                       // UPDATE: SignedAt
                    $ticket,                    // UPDATE: WHERE Ticket
                    $ticket,                    // INSERT: Ticket
                    'Pemohon',                  // INSERT: GroupRole
                    $signaturePath,             // INSERT: SignaturePath
                    $userId,                    // INSERT: SignedByUserId
                    $username,                  // INSERT: SignedByUserName
                    $now                        // INSERT: SignedAt
                ];

                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt !== false) {
                    sqlsrv_free_stmt($stmt);
                    $result['success'] = true;
                    $result['message'] = 'TTD template assigned to ticket successfully';
                } else {
                    $result['message'] = 'Database error: ' . print_r(sqlsrv_errors(), true);
                }
            } else {
                $result['message'] = 'User ID not found';
            }
        } else {
            $result['message'] = 'Employee ID not found';
        }
    } catch (Exception $ex) {
        $result['message'] = 'Exception: ' . $ex->getMessage();
    }
} else {
    $result['message'] = 'Missing required parameters (ticket, signature_path)';
}

echo json_encode($result);
exit;
