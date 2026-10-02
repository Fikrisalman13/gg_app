<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserId']) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['UserId'];
$ticketNo = trim($_POST['ticket_no'] ?? '');
$kategori = trim($_POST['kategori'] ?? 'Form IT');
$namaPemohon = trim($_POST['nama_pemohon'] ?? '-');
$departemen = trim($_POST['departemen'] ?? '-');

if ($ticketNo === '') {
    echo json_encode(['success' => false, 'message' => 'Nomor Pengajuan / Ticket wajib diisi!']);
    exit;
}

try {
    // 1. Ambil data user login (EmpId, Nama, Dept, Group)
    $sqlUser = "SELECT u.EmpId, u.UserName, g.GroupId, d.dept AS department_name,
                       ISNULL(e.nama_lengkap, u.UserName) AS FullName,
                       e.id_jab, j.jabatan, b.bagian
                FROM dbo.SMUserMs u
                LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
                LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
                LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
                LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
                LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
                LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
                WHERE u.UserId = ?";
    $stmtUser = sqlsrv_query($conn, $sqlUser, [$userId]);
    $userRow = $stmtUser ? sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtUser) sqlsrv_free_stmt($stmtUser);

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $userEmpId = (int)($userRow['EmpId'] ?? 0);
    $userGroupId = (int)($userRow['GroupId'] ?? 0);
    $userDept = trim($userRow['department_name'] ?? '');
    $techName = $userRow['FullName'];
    $techJabatan = $userRow['jabatan'] ?? '';
    $techBagian = $userRow['bagian'] ?? '';

    // Cek apakah user IT
    $isITStaff = (stripos($userDept, 'Information Technology') !== false) || (strtoupper($userDept) === 'IT') || ($userGroupId === 1);
    if (!$isITStaff || $userEmpId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Hanya tim IT yang dapat mengambil form ini!']);
        exit;
    }

    // 2. Mulai transaksi database
    if (sqlsrv_begin_transaction($conn) === false) {
        throw new Exception('Gagal memulai transaksi database.');
    }

    // Cek apakah ticket sudah di-assign
    $sqlCheck = "SELECT TOP 1 fa.id, fa.assigned_to, fa.issue_id, iss.issue_id AS active_issue_id 
                 FROM dbo.Form_IT_Assignees fa
                 LEFT JOIN dbo.issues iss ON fa.issue_id = iss.issue_id
                 WHERE fa.ticket_no = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$ticketNo]);
    $existing = $stmtCheck ? sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);

    // Jika form sudah di-assign DAN issue-nya masih aktif -> tolak self-claim
    if ($existing && !empty($existing['assigned_to']) && !empty($existing['active_issue_id'])) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Form ini sudah diambil atau ditugaskan ke teknisi lain!']);
        exit;
    }

    $issueId = null;
    // 3. Buat Issue di dbo.issues (Task - Support User - Penanganan Permintaan User)
    $issueName = "Mengerjakan Permintaan Form " . $kategori . " - " . $ticketNo;
    $issueType = "Task";
    $catIssue = "Support User";
    $subCatIssue = "Penanganan Permintaan User";
    $issueDesc = "Permintaan pengerjaan Form IT:\n- No Pengajuan: " . $ticketNo . "\n- Kategori: " . $kategori . "\n- Pemohon: " . $namaPemohon . " (" . $departemen . ")";
    $priority = "Normal";
    $statusIssue = "In Progress";

    $sqlIssue = "INSERT INTO dbo.issues (
                    issue_name, issue_type, kategori, sub_kategori,
                    status, priority, due_date, description,
                    created_at, created_by,
                    departemen, bagian, jabatan
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, CAST(GETDATE() AS DATE), ?,
                    GETDATE(), ?,
                    ?, ?, ?
                ); SELECT SCOPE_IDENTITY() AS issue_id;";

    $paramsIssue = [
        $issueName, $issueType, $catIssue, $subCatIssue,
        $statusIssue, $priority, $issueDesc,
        $techName,
        $userDept, $techBagian, $techJabatan
    ];

    $stmtIssue = sqlsrv_query($conn, $sqlIssue, $paramsIssue);
    if ($stmtIssue === false) {
        sqlsrv_rollback($conn);
        throw new Exception('Gagal membuat issue pengerjaan di Issue Tracker.');
    }

    // Ambil ID issue yang baru di-generate
    sqlsrv_next_result($stmtIssue);
    if ($rowIssue = sqlsrv_fetch_array($stmtIssue, SQLSRV_FETCH_ASSOC)) {
        $issueId = (int)$rowIssue['issue_id'];
    }
    sqlsrv_free_stmt($stmtIssue);

    // 4. Catat di dbo.Form_IT_Assignees
    if ($existing) {
        $sqlAssign = "UPDATE dbo.Form_IT_Assignees 
                      SET assigned_to = ?, issue_id = ?, assigned_at = GETDATE(), assigned_by = ?, status_pengerjaan = 'In Progress', updated_at = GETDATE(), updated_by = ?
                      WHERE id = ?";
        $stmtAssign = sqlsrv_query($conn, $sqlAssign, [$userEmpId, $issueId, $userId, $userId, $existing['id']]);
    } else {
        $sqlAssign = "INSERT INTO dbo.Form_IT_Assignees (ticket_no, assigned_to, issue_id, assigned_at, assigned_by, status_pengerjaan)
                      VALUES (?, ?, ?, GETDATE(), ?, 'In Progress')";
        $stmtAssign = sqlsrv_query($conn, $sqlAssign, [$ticketNo, $userEmpId, $issueId, $userId]);
    }

    if ($stmtAssign === false) {
        sqlsrv_rollback($conn);
        throw new Exception('Gagal mencatat penugasan form.');
    }
    sqlsrv_free_stmt($stmtAssign);

    // Commit transaksi
    sqlsrv_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Form berhasil diambil dan issue pengerjaan otomatis dibuat.',
        'assigned_name' => $techName,
        'issue_id' => $issueId
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn) {
        sqlsrv_rollback($conn);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
