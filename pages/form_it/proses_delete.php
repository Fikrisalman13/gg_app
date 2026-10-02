<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../koneksi.php';

function deleteResponse($statusCode, $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function executeDelete($conn, $sql, $params, $message)
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new RuntimeException($message);
    }
    $affected = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    return $affected;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deleteResponse(405, ['success' => false, 'message' => 'Metode tidak diizinkan.']);
}
if (!isset($_SESSION['UserName'], $_SESSION['GroupId'])) {
    deleteResponse(401, ['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
if (!$conn) {
    deleteResponse(500, ['success' => false, 'message' => 'Layanan database tidak tersedia.']);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['form_it_csrf_token']) || !hash_equals($_SESSION['form_it_csrf_token'], $csrfToken)) {
    deleteResponse(403, ['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.']);
}

$ticket = trim((string) ($_POST['ticket'] ?? ''));
if ($ticket === '' || strlen($ticket) > 100) {
    deleteResponse(422, ['success' => false, 'message' => 'Nomor pengajuan tidak valid.']);
}

$stmtPermission = sqlsrv_query($conn, "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = 134", [(int) $_SESSION['GroupId']]);
$permission = $stmtPermission ? sqlsrv_fetch_array($stmtPermission, SQLSRV_FETCH_ASSOC) : null;
if ($stmtPermission) {
    sqlsrv_free_stmt($stmtPermission);
}
if (!$permission || (int) $permission['CanDelete'] !== 1) {
    deleteResponse(403, ['success' => false, 'message' => 'Anda tidak memiliki hak untuk menghapus form.']);
}

$isCctv = stripos($ticket, 'CCTV-') === 0;
$isInternet = stripos($ticket, 'INET-') === 0;
$isApplication = stripos($ticket, 'APP-') === 0;
$applicationAttachment = null;

$table = 'Form_Pengajuan_Barang';
if (stripos($ticket, 'CCTV-P-') === 0) {
    $table = 'dbo.Form_Pemindahan_CCTV';
} elseif ($isCctv) {
    $table = 'Form_Pengajuan_CCTV';
} elseif ($isInternet) {
    $table = 'Form_Pengajuan_Akses_Internet';
} elseif (stripos($ticket, 'EMAIL-') === 0) {
    $table = 'Form_Pengajuan_Email_Account';
} elseif (stripos($ticket, 'GRT-') === 0) {
    $table = 'Form_Grant_Revoke_Trustee';
} elseif (stripos($ticket, 'DB-') === 0) {
    $table = 'Form_Perubahan_Data_Database';
} elseif (stripos($ticket, 'CLS-') === 0) {
    $table = 'Form_Buka_Tanggal_Closingan';
} elseif ($isApplication) {
    $table = 'Form_Pengajuan_Aplikasi';
} elseif (stripos($ticket, 'GDG-') === 0) {
    $table = 'dbo.Form_Penambahan_Gudang_Baru_ERP';
}

try {
    if (sqlsrv_begin_transaction($conn) === false) {
        throw new RuntimeException('Gagal memulai transaksi penghapusan.');
    }

    $sqlAssignments = "SELECT a.issue_id, i.status
                       FROM dbo.Form_IT_Assignees a WITH (UPDLOCK, HOLDLOCK)
                       LEFT JOIN dbo.issues i WITH (UPDLOCK, HOLDLOCK) ON i.issue_id = a.issue_id
                       WHERE a.ticket_no = ?";
    $stmtAssignments = sqlsrv_query($conn, $sqlAssignments, [$ticket]);
    if ($stmtAssignments === false) {
        throw new RuntimeException('Gagal memvalidasi penugasan pengajuan.');
    }

    $issueIds = [];
    while ($assignment = sqlsrv_fetch_array($stmtAssignments, SQLSRV_FETCH_ASSOC)) {
        $issueId = (int) ($assignment['issue_id'] ?? 0);
        $status = trim((string) ($assignment['status'] ?? ''));
        if ($issueId > 0 && $status !== '' && strcasecmp($status, 'In Progress') !== 0) {
            sqlsrv_free_stmt($stmtAssignments);
            sqlsrv_rollback($conn);
            deleteResponse(409, [
                'success' => false,
                'message' => strcasecmp($status, 'Done') === 0
                    ? 'Form tidak dapat dihapus karena issue terkait sudah Done.'
                    : 'Form tidak dapat dihapus karena status issue terkait bukan In Progress.',
            ]);
        }
        if ($issueId > 0 && $status !== '') {
            $issueIds[$issueId] = $issueId;
        }
    }
    sqlsrv_free_stmt($stmtAssignments);

    if ($isApplication) {
        $stmtAttachment = sqlsrv_query($conn, "SELECT lampiran_nama_file FROM Form_Pengajuan_Aplikasi WITH (UPDLOCK, HOLDLOCK) WHERE ticket = ?", [$ticket]);
        if ($stmtAttachment === false) {
            throw new RuntimeException('Gagal membaca lampiran pengajuan.');
        }
        $attachmentRow = sqlsrv_fetch_array($stmtAttachment, SQLSRV_FETCH_ASSOC);
        if ($attachmentRow) {
            $applicationAttachment = basename((string) ($attachmentRow['lampiran_nama_file'] ?? ''));
        }
        sqlsrv_free_stmt($stmtAttachment);
    }

    foreach ($issueIds as $issueId) {
        $stmtLinks = sqlsrv_query($conn, "IF OBJECT_ID('dbo.project_issue_links', 'U') IS NOT NULL DELETE FROM dbo.project_issue_links WHERE issue_id = ?", [$issueId]);
        if ($stmtLinks === false) {
            throw new RuntimeException('Gagal menghapus relasi project issue.');
        }
        sqlsrv_free_stmt($stmtLinks);
    }

    executeDelete($conn, "DELETE FROM dbo.Form_IT_Assignees WHERE ticket_no = ?", [$ticket], 'Gagal menghapus data penugasan.');
    foreach ($issueIds as $issueId) {
        executeDelete($conn, "DELETE FROM dbo.issues WHERE issue_id = ? AND status = 'In Progress'", [$issueId], 'Gagal menghapus issue terkait.');
    }
    executeDelete($conn, "DELETE FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket], 'Gagal menghapus data TTD.');

    $rowsAffected = executeDelete($conn, "DELETE FROM {$table} WHERE ticket = ?", [$ticket], 'Gagal menghapus data pengajuan.');
    if ($rowsAffected === 0 && !$isCctv && !$isInternet) {
        $rowsAffected = executeDelete($conn, "DELETE FROM Form_Pengajuan_CCTV WHERE ticket = ?", [$ticket], 'Gagal memeriksa data CCTV.');
    }
    if ($rowsAffected === 0) {
        throw new OutOfBoundsException('Data tidak ditemukan atau sudah dihapus.');
    }

    if (sqlsrv_commit($conn) === false) {
        throw new RuntimeException('Gagal menyelesaikan transaksi penghapusan.');
    }
} catch (OutOfBoundsException $e) {
    sqlsrv_rollback($conn);
    deleteResponse(404, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    sqlsrv_rollback($conn);
    deleteResponse(500, ['success' => false, 'message' => 'Penghapusan gagal. Tidak ada data yang diubah.']);
}

if ($applicationAttachment !== '') {
    $attachmentPath = __DIR__ . '/../../storage/form_it/pengajuan_aplikasi/' . $applicationAttachment;
    if (is_file($attachmentPath) && !unlink($attachmentPath)) {
        error_log('Lampiran pengajuan aplikasi gagal dihapus: ' . $ticket);
    }
}

sqlsrv_close($conn);
deleteResponse(200, ['success' => true, 'message' => 'Data dan penugasan terkait berhasil dihapus.', 'ticket' => $ticket]);
