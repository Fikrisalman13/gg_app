<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = [
    'success' => false,
    'templates' => [],
    'message' => 'Terjadi kesalahan.'
];

if (!isset($_SESSION['UserName'])) {
    $response['message'] = 'Silakan login terlebih dahulu.';
    echo json_encode($response);
    exit;
}

$identifiers = [];
if (!empty($_SESSION['NamaLengkap'])) {
    $identifiers[] = $_SESSION['NamaLengkap'];
}
$identifiers[] = $_SESSION['UserName'];
$identifiers = array_values(array_unique(array_filter($identifiers)));

if (empty($identifiers)) {
    $response['message'] = 'Identitas pengguna tidak ditemukan.';
    echo json_encode($response);
    exit;
}

$placeholders = implode(',', array_fill(0, count($identifiers), '?'));

// Ambil template dari tabel issues_template
$sql = "SELECT template_id, template_name, issue_names_json, issue_type, kategori, sub_kategori, asset_id, priority, description, created_at, status
        FROM dbo.issues_template
        WHERE created_by IN ($placeholders)
        ORDER BY created_at DESC";

$stmt = sqlsrv_query($conn, $sql, $identifiers);

if ($stmt === false) {
    $response['message'] = 'Gagal mengambil template issue.';
} else {
    $templates = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $templates[] = [
            'template_id' => $row['template_id'],
            'template_name' => $row['template_name'],
            'issue_names_json' => $row['issue_names_json'],
            'issue_type' => $row['issue_type'],
            'kategori' => $row['kategori'],
            'sub_kategori' => $row['sub_kategori'],
            'asset_id' => $row['asset_id'],
            'priority' => $row['priority'],
            'description' => $row['description'],
            'status' => $row['status']
        ];
    }
    sqlsrv_free_stmt($stmt);

    $response['success'] = true;
    $response['templates'] = $templates;
    $response['message'] = '';
}

echo json_encode($response);
sqlsrv_close($conn);
?>
