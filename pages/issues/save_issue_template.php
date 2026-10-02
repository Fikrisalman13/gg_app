<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = [
    'success' => false,
    'message' => 'Unknown error'
];

if (!isset($_SESSION['UserName'])) {
    $response['message'] = "Silakan login terlebih dahulu";
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_name = trim($_POST['template_name'] ?? '');
    $issue_names_json = $_POST['issue_names_json'] ?? '';
    $issue_type = $_POST['issue_type'] ?? '';
    $kategori = $_POST['kategori'] ?? '';
    $sub_kategori = $_POST['sub_kategori'] ?? '';
    $asset_id = !empty($_POST['asset_id']) ? $_POST['asset_id'] : null;
    $priority = $_POST['priority'] ?? 'Normal';
    $status = $_POST['status'] ?? 'To Do';
    $description = $_POST['description'] ?? '';
    
    $namaLengkap = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
    
    try {
        if (empty($template_name)) {
            throw new Exception("Nama template harus diisi.");
        }
        
        if (empty($issue_type)) {
            throw new Exception("Issue Type wajib diisi.");
        }
        
        // Cek apakah template dengan nama yang sama sudah ada untuk user ini
        $checkSql = "SELECT COUNT(*) as count FROM dbo.issues_template 
                     WHERE template_name = ? AND created_by = ?";
        $checkParams = [$template_name, $namaLengkap];
        $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
        
        if ($checkStmt === false) {
            throw new Exception("Database error: " . print_r(sqlsrv_errors(), true));
        }
        
        $checkRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($checkStmt);
        
        if ($checkRow['count'] > 0) {
            // Template sudah ada, update
            $sql = "UPDATE dbo.issues_template SET 
                        issue_names_json = ?, issue_type = ?, kategori = ?, sub_kategori = ?, 
                        asset_id = ?, priority = ?, description = ?, status = ?
                    WHERE template_name = ? AND created_by = ?";
            
            $params = [
                $issue_names_json, $issue_type, $kategori, $sub_kategori,
                $asset_id, $priority, $description, $status,
                $template_name, $namaLengkap
            ];
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Database error: " . print_r(sqlsrv_errors(), true));
            }
            
            $response['success'] = true;
            $response['message'] = "Template berhasil diperbarui.";
        } else {
            // Template belum ada, insert
            $sql = "INSERT INTO dbo.issues_template (
                        template_name, issue_names_json, issue_type, kategori, sub_kategori, 
                        asset_id, priority, description, created_by, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $template_name, $issue_names_json, $issue_type, $kategori, $sub_kategori,
                $asset_id, $priority, $description, $namaLengkap, $status
            ];
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Database error: " . print_r(sqlsrv_errors(), true));
            }
            
            $response['success'] = true;
            $response['message'] = "Template berhasil disimpan.";
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
sqlsrv_close($conn);
?>
