<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$response = [ 'success' => false, 'message' => 'Unknown error' ];

try {
    if (!isset($_SESSION['UserId'])) { throw new Exception('Silakan login.'); }
    if (!$conn) { throw new Exception('Koneksi database gagal.'); }

    $id_asset = isset($_POST['id_asset']) ? intval($_POST['id_asset']) : 0;
    $action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
    $note   = isset($_POST['note']) ? trim($_POST['note']) : '';
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : null;

    if ($id_asset <= 0) throw new Exception('id_asset tidak valid');
    if ($action !== 'resolve' && $action !== 'broken') throw new Exception('Aksi tidak valid');
    if ($note === '') throw new Exception('Catatan wajib diisi');

    // Permission: check m_emp.id_bagian = 'Information Technology'
    $empSql = "SELECT e.id_emp, b.bagian FROM dbo.m_emp e LEFT JOIN dbo.m_subbag s ON e.id_subbag=s.id_subbag LEFT JOIN dbo.m_bag b ON s.id_bag=b.id_bag WHERE e.id_emp = ?";
    $empStmt = sqlsrv_query($conn, $empSql, [ $_SESSION['UserId'] ]);
    $isIT = false; $empRow = $empStmt ? sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC) : null;
    if ($empRow && isset($empRow['bagian']) && strcasecmp($empRow['bagian'], 'Information Technology') === 0) { $isIT = true; }
    if ($empStmt) sqlsrv_free_stmt($empStmt);
    if (!$isIT) throw new Exception('Akses ditolak: hanya Petugas IT.');

    // Read current status name (do not modify m_asset)
    $curSql = "SELECT a.id_status, s.nama_status FROM dbo.m_asset a LEFT JOIN dbo.m_status s ON a.id_status=s.id_status WHERE a.id_asset = ?";
    $curStmt = sqlsrv_query($conn, $curSql, [ $id_asset ]);
    $cur = $curStmt ? sqlsrv_fetch_array($curStmt, SQLSRV_FETCH_ASSOC) : null;
    if ($curStmt) sqlsrv_free_stmt($curStmt);
    $oldStatusName = $cur && $cur['nama_status'] ? $cur['nama_status'] : '-';

    // Map action -> new status name (display only; not updating m_asset per requirement)
    $newStatusName = ($action === 'resolve') ? 'Used' : 'Broken';
    $jenisPerubahan = 'status asset';

    // Begin pseudo-transaction (sqlsrv does autocommit; for real transaction use sqlsrv_begin_transaction)
    sqlsrv_begin_transaction($conn);

    // Insert into asset_status_overrides (new table)
    $ovSql = "INSERT INTO dbo.asset_status_overrides (id_asset, old_status_name, new_status_name, action, note, ticket_id, created_at, created_by) VALUES (?,?,?,?,?,?,GETDATE(),?)";
    $ovParams = [ $id_asset, $oldStatusName, $newStatusName, $action, $note, $ticket_id, $_SESSION['UserId'] ];
    $ovStmt = sqlsrv_query($conn, $ovSql, $ovParams);
    if ($ovStmt === false) { throw new Exception('Gagal menyimpan override status'); }
    sqlsrv_free_stmt($ovStmt);

    // If attachment present, save to uploads/tickets and record into ticket_attachments (optional)
    if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/tickets/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
        $names = $_FILES['attachments']['name'];
        $tmps  = $_FILES['attachments']['tmp_name'];
        $sizes = $_FILES['attachments']['size'];
        $types = $_FILES['attachments']['type'];
        for ($i=0; $i<count($names); $i++) {
            if (!$tmps[$i]) continue;
            $ext = pathinfo($names[$i], PATHINFO_EXTENSION);
            $safe = 'ticket_' . ($ticket_id ?: 'asset'.$id_asset) . '_' . time() . '_' . $i . '.' . $ext;
            $dest = $uploadDir . $safe;
            if (@move_uploaded_file($tmps[$i], $dest)) {
                // optional: insert into ticket_attachments if ticket_id present
                if ($ticket_id) {
                    $attSql = "INSERT INTO dbo.ticket_attachments (ticket_id, message_id, file_path, mime_type, size, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,GETDATE())";
                    $attParams = [ $ticket_id, null, '/gg_app/uploads/tickets/' . $safe, $types[$i], intval($sizes[$i]), $_SESSION['UserId'] ];
                    sqlsrv_query($conn, $attSql, $attParams);
                }
            }
        }
    }

    // If ticket present, add a ticket message
    if ($ticket_id) {
        $msgSql = "INSERT INTO dbo.ticket_messages (ticket_id, sender_id, sender_name, message_html, created_at, created_by) VALUES (?,?,?,?,GETDATE(),?)";
        $senderName = htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']);
        $messageHtml = '<p><strong>'+ $jenisPerubahan +'</strong>: ' . htmlspecialchars($oldStatusName) . ' → ' . htmlspecialchars($newStatusName) . '</p><p>' . htmlspecialchars($note) . '</p>';
        $msgParams = [ $ticket_id, $_SESSION['UserId'], $senderName, $messageHtml, $_SESSION['UserId'] ];
        $msgStmt = sqlsrv_query($conn, $msgSql, $msgParams);
        if ($msgStmt) sqlsrv_free_stmt($msgStmt);
    }

    sqlsrv_commit($conn);

    // Produce history row HTML compatible with existing table
    $dateStr = date('d-m-Y H:i');
    $userLabel = htmlspecialchars($_SESSION['UserName'] ?? 'IT');
    $rowHtml = '<tr>'
        . '<td>#</td>'
        . '<td>' . $dateStr . '</td>'
        . '<td>' . $userLabel . '</td>'
        . '<td>' . htmlspecialchars($oldStatusName) . ' → ' . htmlspecialchars($newStatusName) . '</td>'
        . '<td>' . htmlspecialchars($jenisPerubahan) . '</td>'
        . '<td>' . htmlspecialchars($note) . '</td>'
        . '</tr>';

    $response['success'] = true;
    $response['message'] = 'Keterangan tersimpan';
    $response['history_row_html'] = $rowHtml;
} catch (Exception $e) {
    if ($conn) { @sqlsrv_rollback($conn); }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
