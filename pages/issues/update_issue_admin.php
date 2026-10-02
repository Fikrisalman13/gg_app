<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = ['success' => false, 'message' => 'Unauthorized'];

// Security Check: Only ITADM and IT7
$allowedUsers = ['ITADM', 'IT7'];
$currentUser = $_SESSION['UserName'] ?? '';

if (!in_array($currentUser, $allowedUsers)) {
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueId = $_POST['issue_id'] ?? 0;
    $createdAt = $_POST['created_at'] ?? null;
    $tanggalSelesai = $_POST['tanggal_selesai'] ?? null;

    if (!$issueId) {
        $response['message'] = "ID Issue tidak ditemukan.";
        echo json_encode($response);
        exit;
    }

    try {
        // Prepare SQL with conditional updates
        $sql = "UPDATE dbo.issues SET 
                created_at = ?, 
                tanggal_selesai = ?,
                update_at = GETDATE(),
                update_by = ?
                WHERE issue_id = ?";
        
        $params = [
            !empty($createdAt) ? str_replace('T', ' ', $createdAt) : null,
            !empty($tanggalSelesai) ? str_replace('T', ' ', $tanggalSelesai) : null,
            $_SESSION['NamaLengkap'] ?? $currentUser,
            $issueId
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            throw new Exception("Database error: " . print_r(sqlsrv_errors(), true));
        }

        $response['success'] = true;
        $response['message'] = "Timestamp berhasil diperbarui secara administratif.";

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = "Method not allowed.";
}

echo json_encode($response);
sqlsrv_close($conn);
?>
