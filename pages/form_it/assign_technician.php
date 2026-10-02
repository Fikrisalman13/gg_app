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
$assignedTo = (int)($_POST['assigned_to'] ?? 0);
$kategori = trim($_POST['kategori'] ?? 'Form IT');
$namaPemohon = trim($_POST['nama_pemohon'] ?? '-');
$departemen = trim($_POST['departemen'] ?? '-');

if ($ticketNo === '' || $assignedTo <= 0) {
    echo json_encode(['success' => false, 'message' => 'Nomor Pengajuan dan Teknisi wajib dipilih!']);
    exit;
}

try {
    // 1. Cek hak akses penugasan (Admin / IT / Group 1)
    $sqlUser = "SELECT u.GroupId, u.UserName, d.dept AS department_name,
                       ISNULL(e.nama_lengkap, u.UserName) AS FullName
                FROM dbo.SMUserMs u
                LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
                LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
                LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
                WHERE u.UserId = ?";
    $stmtUser = sqlsrv_query($conn, $sqlUser, [$userId]);
    $userRow = $stmtUser ? sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtUser) sqlsrv_free_stmt($stmtUser);

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $adminName = !empty($userRow['FullName']) ? $userRow['FullName'] : ($_SESSION['NamaLengkap'] ?? ($_SESSION['UserName'] ?? 'Admin'));
    $userGroupId = (int)($userRow['GroupId'] ?? 0);
    $userDept = trim($userRow['department_name'] ?? '');
    $isAuthorized = ($userGroupId === 1) || (stripos($userDept, 'Information Technology') !== false) || (strtoupper($userDept) === 'IT');
    if (!$isAuthorized) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk menugaskan form!']);
        exit;
    }

    // 2. Ambil data teknisi yang ditugaskan
    $sqlTech = "SELECT e.id_emp, e.nama_lengkap, d.dept, j.jabatan, b.bagian
                FROM dbo.m_emp e
                LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
                LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
                LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
                LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
                WHERE e.id_emp = ?";
    $stmtTech = sqlsrv_query($conn, $sqlTech, [$assignedTo]);
    $techRow = $stmtTech ? sqlsrv_fetch_array($stmtTech, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtTech) sqlsrv_free_stmt($stmtTech);

    if (!$techRow) {
        echo json_encode(['success' => false, 'message' => 'Data teknisi tidak ditemukan']);
        exit;
    }

    $techName = $techRow['nama_lengkap'];
    $techDept = $techRow['dept'] ?? 'Information Technology';
    $techJabatan = $techRow['jabatan'] ?? '';
    $techBagian = $techRow['bagian'] ?? '';

    // 3. Mulai transaksi database
    if (sqlsrv_begin_transaction($conn) === false) {
        throw new Exception('Gagal memulai transaksi database.');
    }

    // Cek record penugasan existing
    $sqlCheck = "SELECT TOP 1 id, assigned_to, issue_id FROM dbo.Form_IT_Assignees WHERE ticket_no = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$ticketNo]);
    $existing = $stmtCheck ? sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);

    $issueId = $existing['issue_id'] ?? null;
    $issueStillExists = false;

    if ($issueId && (int)$issueId > 0) {
        $sqlCheckIssue = "SELECT TOP 1 issue_id FROM dbo.issues WHERE issue_id = ?";
        $stmtCI = sqlsrv_query($conn, $sqlCheckIssue, [$issueId]);
        if ($stmtCI && sqlsrv_fetch_array($stmtCI, SQLSRV_FETCH_ASSOC)) {
            $issueStillExists = true;
        }
        if ($stmtCI) sqlsrv_free_stmt($stmtCI);
    }

    // Jika issue masih ada -> update assignee di issues tracker, jika belum / sudah terhapus -> create issue baru
    if ($issueStillExists) {
        $sqlUpdateIssue = "UPDATE dbo.issues
                           SET created_by = ?, departemen = ?, bagian = ?, jabatan = ?, update_at = GETDATE(), update_by = ?
                           WHERE issue_id = ?";
        $stmtUpIssue = sqlsrv_query($conn, $sqlUpdateIssue, [$techName, $techDept, $techBagian, $techJabatan, $adminName, $issueId]);
        if ($stmtUpIssue === false) {
            sqlsrv_rollback($conn);
            throw new Exception('Gagal memperbarui data issue penugasan.');
        }
        sqlsrv_free_stmt($stmtUpIssue);
    } else {
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
                        created_at, created_by, update_at, update_by,
                        departemen, bagian, jabatan
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, CAST(GETDATE() AS DATE), ?,
                        GETDATE(), ?, GETDATE(), ?,
                        ?, ?, ?
                    ); SELECT SCOPE_IDENTITY() AS issue_id;";

        $paramsIssue = [
            $issueName, $issueType, $catIssue, $subCatIssue,
            $statusIssue, $priority, $issueDesc,
            $techName, $adminName,
            $techDept, $techBagian, $techJabatan
        ];

        $stmtIssue = sqlsrv_query($conn, $sqlIssue, $paramsIssue);
        if ($stmtIssue === false) {
            sqlsrv_rollback($conn);
            throw new Exception('Gagal membuat issue pengerjaan di Issue Tracker.');
        }

        sqlsrv_next_result($stmtIssue);
        if ($rowIssue = sqlsrv_fetch_array($stmtIssue, SQLSRV_FETCH_ASSOC)) {
            $issueId = (int)$rowIssue['issue_id'];
        }
        sqlsrv_free_stmt($stmtIssue);
    }

    // 4. Catat / update ke dbo.Form_IT_Assignees
    if ($existing) {
        $sqlAssign = "UPDATE dbo.Form_IT_Assignees 
                      SET assigned_to = ?, issue_id = ?, assigned_at = GETDATE(), assigned_by = ?, status_pengerjaan = 'In Progress', updated_at = GETDATE(), updated_by = ?
                      WHERE id = ?";
        $stmtAssign = sqlsrv_query($conn, $sqlAssign, [$assignedTo, $issueId, $userId, $userId, $existing['id']]);
    } else {
        $sqlAssign = "INSERT INTO dbo.Form_IT_Assignees (ticket_no, assigned_to, issue_id, assigned_at, assigned_by, status_pengerjaan)
                      VALUES (?, ?, ?, GETDATE(), ?, 'In Progress')";
        $stmtAssign = sqlsrv_query($conn, $sqlAssign, [$ticketNo, $assignedTo, $issueId, $userId]);
    }

    if ($stmtAssign === false) {
        sqlsrv_rollback($conn);
        throw new Exception('Gagal mencatat penugasan form.');
    }
    sqlsrv_free_stmt($stmtAssign);

    sqlsrv_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Teknisi berhasil ditugaskan.',
        'assigned_name' => $techName,
        'issue_id' => $issueId
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn) {
        sqlsrv_rollback($conn);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
