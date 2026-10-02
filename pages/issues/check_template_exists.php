<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = [
    'exists' => false,
    'message' => ''
];

if (!isset($_SESSION['UserName'])) {
    $response['message'] = "Silakan login terlebih dahulu";
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_name = trim($_POST['template_name'] ?? '');
    $namaLengkap = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
    
    if (!empty($template_name)) {
        $sql = "SELECT COUNT(*) as count FROM dbo.issues_template 
                WHERE template_name = ? AND created_by = ?";
        $params = [$template_name, $namaLengkap];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $response['exists'] = ($row['count'] > 0);
            sqlsrv_free_stmt($stmt);
        }
    }
}

echo json_encode($response);
sqlsrv_close($conn);
?>
