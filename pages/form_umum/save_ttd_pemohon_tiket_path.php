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
// Check for specific employee name from parameter, otherwise use session user
$username = isset($_POST['nama_pemohon']) && !empty(trim($_POST['nama_pemohon'])) 
            ? trim($_POST['nama_pemohon']) 
            : ($_SESSION['NamaLengkap'] ?? '');
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

                // Prevent overwrite: if Pemohon signature already exists by another user, do nothing
                $stmtExisting = sqlsrv_query(
                    $conn,
                    "SELECT TOP 1 SignaturePath, SignedByUserId FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole = ?",
                    [$ticket, 'Pemohon']
                );
                if ($stmtExisting && ($rowEx = sqlsrv_fetch_array($stmtExisting, SQLSRV_FETCH_ASSOC))) {
                    $existingPath = trim((string)($rowEx['SignaturePath'] ?? ''));
                    $existingSigner = $rowEx['SignedByUserId'] ?? null;
                    if ($existingPath !== '' && $existingSigner !== null && (int)$existingSigner !== (int)$userId) {
                        sqlsrv_free_stmt($stmtExisting);
                        $result['success'] = true;
                        $result['message'] = 'TTD Pemohon sudah ada.';
                        echo json_encode($result);
                        exit;
                    }
                }
                if ($stmtExisting) sqlsrv_free_stmt($stmtExisting);

                // Insert/update ONLY for Pemohon role to avoid overwriting other signatures
                $sql = "IF EXISTS (SELECT 1 FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole = ?)
                        UPDATE Form_Umum_TTD 
                        SET SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = ?
                        WHERE Ticket = ? AND GroupRole = ?
                        ELSE
                        INSERT INTO Form_Umum_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
                        VALUES (?, ?, ?, ?, ?, ?)";

                $params = [
                    $ticket,                    // for EXISTS check
                    'Pemohon',                  // EXISTS: GroupRole
                    $signaturePath,             // UPDATE: SignaturePath
                    $userId,                    // UPDATE: SignedByUserId
                    $username,                  // UPDATE: SignedByUserName
                    $now,                       // UPDATE: SignedAt
                    $ticket,                    // UPDATE: WHERE Ticket
                    'Pemohon',                  // UPDATE: WHERE GroupRole
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
