<?php
// ===================================================
// 1. INISIALISASI ENDPOINT EDIT TICKET
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$resp = ['success' => false, 'data' => null, 'message' => ''];

if (!isset($_SESSION['UserId']) || !$conn) {
    $resp['message'] = 'Silakan login terlebih dahulu';
    echo json_encode($resp);
    exit;
}

try {
    $ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    if ($ticketId <= 0) {
        throw new Exception("Ticket ID tidak valid");
    }

    // ===================================================
    // 2. DATA TICKET DASAR
    // ===================================================
    $sql = "SELECT t.ticket_id, t.ticket_no, t.subject, t.assigned_to, t.creator_id, t.creator_name,
                   ta.id_asset as current_asset_id
            FROM dbo.tickets t
            LEFT JOIN dbo.ticket_assets ta ON t.ticket_id = ta.ticket_id
            WHERE t.ticket_id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$ticketId]);
    $ticket = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    if ($stmt) sqlsrv_free_stmt($stmt);

    if (!$ticket) {
        throw new Exception("Ticket tidak ditemukan");
    }

    // ===================================================
    // 3. PESAN PERTAMA + LAMPIRAN
    // ===================================================
    $sqlMsg = "SELECT TOP 1 message_id, message_html 
               FROM dbo.ticket_messages 
               WHERE ticket_id = ? 
               ORDER BY created_at ASC, message_id ASC";
    $stmtMsg = sqlsrv_query($conn, $sqlMsg, [$ticketId]);
    $firstMessage = '';
    $firstMessageId = 0;
    if ($stmtMsg && $rowMsg = sqlsrv_fetch_array($stmtMsg, SQLSRV_FETCH_ASSOC)) {
        $firstMessage = $rowMsg['message_html'];
        $firstMessageId = intval($rowMsg['message_id']);
    }
    if ($stmtMsg) sqlsrv_free_stmt($stmtMsg);

    $attachments = [];
    if ($firstMessageId > 0) {
        $sqlAtt = "SELECT file_path, size, mime_type FROM dbo.ticket_attachments WHERE message_id = ?";
        $stmtAtt = sqlsrv_query($conn, $sqlAtt, [$firstMessageId]);
        while ($stmtAtt && $rowAtt = sqlsrv_fetch_array($stmtAtt, SQLSRV_FETCH_ASSOC)) {
            $attachments[] = $rowAtt;
        }
        if ($stmtAtt) sqlsrv_free_stmt($stmtAtt);
    }

    // ===================================================
    // 4. DAFTAR ASET PEMOHON
    // ===================================================
    // Strategi: gunakan creator_name pada ticket, lalu perbaiki melalui relasi ID jika memungkinkan
    
    $assets = [];
    $creatorName = $ticket['creator_name'];
    $creatorId = intval($ticket['creator_id']);

    // Mencoba menemukan nama lengkap paling akurat via UserId/EmpId
    if ($creatorId > 0) {
        $found = false;
        
        // Strategy A: Assume creator_id is SMUserMs.UserId (common pattern in this app's save logic)
        $sqlA = "SELECT e.nama_lengkap 
                 FROM dbo.SMUserMs u 
                 JOIN dbo.m_emp e ON u.EmpId = e.id_emp 
                 WHERE u.UserId = ?";
        $stmtA = sqlsrv_query($conn, $sqlA, [$creatorId]);
        if ($stmtA && $rowA = sqlsrv_fetch_array($stmtA, SQLSRV_FETCH_ASSOC)) {
            if (!empty($rowA['nama_lengkap'])) {
                $creatorName = $rowA['nama_lengkap'];
                $found = true;
            }
        }
        if ($stmtA) sqlsrv_free_stmt($stmtA);

        // Strategy B: If A failed, maybe creator_id IS EmpId
        if (!$found) {
            $sqlB = "SELECT nama_lengkap FROM dbo.m_emp WHERE id_emp = ?";
            $stmtB = sqlsrv_query($conn, $sqlB, [$creatorId]);
            if ($stmtB && $rowB = sqlsrv_fetch_array($stmtB, SQLSRV_FETCH_ASSOC)) {
                 if (!empty($rowB['nama_lengkap'])) {
                    $creatorName = $rowB['nama_lengkap'];
                 }
            }
            if ($stmtB) sqlsrv_free_stmt($stmtB);
        }
    }
    
    // Fetch assets using the resolved name
    $sqlAsset = "SELECT a.id_asset, a.kode_asset_seq, a.keterangan, k.nama_kategori
                 FROM dbo.m_asset a
                 LEFT JOIN dbo.m_kategori k ON a.id_kategori = k.id_kategori
                 LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
                 WHERE e.nama_lengkap = ?
                 ORDER BY a.kode_asset_seq";
    $stmtAsset = sqlsrv_query($conn, $sqlAsset, [$creatorName]);
    while ($stmtAsset && $rowAsset = sqlsrv_fetch_array($stmtAsset, SQLSRV_FETCH_ASSOC)) {
        $assets[] = $rowAsset;
    }
    if ($stmtAsset) sqlsrv_free_stmt($stmtAsset);

    // ===================================================
    // 5. DAFTAR TEKNISI
    // ===================================================
    $technicians = [];
    $sqlTech = "SELECT e.id_emp, e.nama_lengkap, g.group_name
            FROM dbo.m_emp e
            LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
            LEFT JOIN dbo.ticket_tech_members tm ON e.id_emp = tm.id_emp
            LEFT JOIN dbo.ticket_tech_groups g ON tm.group_id = g.id
            WHERE d.dept = 'Information Technology'
              AND e.aktif = 1
            ORDER BY e.nama_lengkap";
    $stmtTech = sqlsrv_query($conn, $sqlTech);
    while ($stmtTech && $rowTech = sqlsrv_fetch_array($stmtTech, SQLSRV_FETCH_ASSOC)) {
         $technicians[] = [
            'id_emp' => $rowTech['id_emp'],
            'nama_lengkap' => $rowTech['nama_lengkap'],
            'group_name' => $rowTech['group_name'] ?? null
        ];
    }
    if ($stmtTech) sqlsrv_free_stmt($stmtTech);

    $resp['success'] = true;
    $resp['data'] = [
        'ticket' => [
            'ticket_id' => $ticket['ticket_id'],
            'ticket_no' => $ticket['ticket_no'],
            'subject' => $ticket['subject'],
            'assigned_to' => $ticket['assigned_to'],
            'first_message' => $firstMessage,
            'attachments' => $attachments,
            'current_asset_id' => $ticket['current_asset_id']
        ],
        'assets' => $assets,
        'technicians' => $technicians
    ];

} catch (Exception $e) {
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
?>
