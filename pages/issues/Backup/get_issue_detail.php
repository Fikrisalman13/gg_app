<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

// Ensure PHP uses Jakarta timezone when formatting dates returned to client
date_default_timezone_set('Asia/Jakarta');

function resolveUsernameByFullName($conn, $fullName) {
    if (!$conn || empty($fullName)) {
        return null;
    }
    $sql = "SELECT TOP 1 u.UserName
            FROM dbo.m_emp e
            INNER JOIN dbo.SMUserMs u ON e.id_emp = u.EmpId
            WHERE e.nama_lengkap = ?";
    $stmt = sqlsrv_query($conn, $sql, [$fullName]);
    if ($stmt === false) {
        return null;
    }
    $username = null;
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $username = $row['UserName'] ?? null;
    }
    sqlsrv_free_stmt($stmt);
    return $username;
}

$response = [
    'success' => false,
    'message' => 'Permintaan tidak dapat diproses.',
];

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    $response['message'] = 'Silakan login terlebih dahulu.';
    echo json_encode($response);
    exit;
}

$issueId = isset($_GET['issue_id']) ? (int) $_GET['issue_id'] : 0;
if ($issueId <= 0) {
    http_response_code(400);
    $response['message'] = 'ID issue tidak valid.';
    echo json_encode($response);
    exit;
}

$sql = "SELECT i.*, 
               a.kode_asset_seq, a.keterangan, 
               m.nama_merk, 
               t.nama_tipe, 
               l.nama_lokasi, 
               kat.nama_kategori AS asset_kategori,
               emp.nama_lengkap AS asset_owner,
               c.nama_lengkap AS client_name,
               c.nik AS client_nik,
               cj.jabatan AS client_jabatan
        FROM dbo.issues i
        LEFT JOIN dbo.m_asset a ON i.asset_id = a.id_asset
        LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
        LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
        LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
        LEFT JOIN dbo.m_kategori kat ON a.id_kategori = kat.id_kategori
        LEFT JOIN dbo.m_emp emp ON a.id_emp = emp.id_emp
        LEFT JOIN dbo.m_emp c ON i.client_id = c.id_emp
        LEFT JOIN dbo.m_jab cj ON c.id_jab = cj.id_jab
        WHERE i.issue_id = ?";

$stmt = sqlsrv_query($conn, $sql, [$issueId]);
if ($stmt === false) {
    http_response_code(500);
    $response['message'] = 'Gagal mengambil data issue.';
    $response['details'] = sqlsrv_errors();
    echo json_encode($response);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    $response['message'] = 'Data issue tidak ditemukan.';
    echo json_encode($response);
    sqlsrv_free_stmt($stmt);
    exit;
}

// Format dates using Jakarta timezone; use dot as time separator to match UI pattern (e.g., 08.03)
$dueDate = ($row['due_date'] instanceof DateTimeInterface)
    ? $row['due_date']->format('Y-m-d')
    : (!empty($row['due_date']) ? (string) $row['due_date'] : null);

$formatWithDot = function($dt) {
    if ($dt instanceof DateTimeInterface) {
        return $dt->format('Y-m-d H:i');
    }
    if (empty($dt)) return null;
    // Try parsing common formats
    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
    foreach ($formats as $f) {
        $tmp = DateTime::createFromFormat($f, $dt);
        if ($tmp !== false) {
            return $tmp->format('Y-m-d H:i');
        }
    }
    return (string)$dt;
};

$tanggalSelesai = $formatWithDot($row['tanggal_selesai']);
$createdAt = $formatWithDot($row['created_at']);
$updatedAt = $formatWithDot($row['update_at']);

$assetLabel = null;
if (!empty($row['asset_id'])) {
    $merk = $row['nama_merk'] ?? '-';
    $tipe = $row['nama_tipe'] ?? '';
    $lokasi = $row['nama_lokasi'] ?? '';
    $kode = $row['kode_asset_seq'] ?? '';
    $kategori = $row['asset_kategori'] ?? '';
    $client = $row['asset_owner'] ?? '';
    $keterangan = $row['keterangan'] ?? '';

    $deviceName = trim($merk . ' ' . $tipe);
    if (stripos((string) $kategori, 'Peripheral') !== false && $keterangan !== '') {
        $deviceName = trim($keterangan);
    }
    if ($deviceName === '') {
        $deviceName = 'Unknown Asset';
    }

    $locationDisplay = $client !== '' ? $client : $lokasi;
    $labelParts = [];
    if ($kode !== '') {
        $labelParts[] = '[' . $kode . ']';
    }
    $labelParts[] = $deviceName;
    if ($locationDisplay !== '') {
        $labelParts[] = '- ' . $locationDisplay;
    }
    $assetLabel = trim(implode(' ', $labelParts));
}

$createdByUsername = resolveUsernameByFullName($conn, $row['created_by'] ?? '');
$updatedByUsername = resolveUsernameByFullName($conn, $row['update_by'] ?? '');

// Format Client Display
$clientLabel = null;
if (!empty($row['client_id'])) {
    $cName = $row['client_name'] ?? '';
    $cJab = $row['client_jabatan'] ?? '';
    $clientLabel = $cName;
    if ($cJab !== '') {
        $clientLabel .= ' (' . $cJab . ')';
    }
}

$data = [
    'issue_id' => (int) $row['issue_id'],
    'issue_name' => $row['issue_name'] ?? '',
    'issue_type' => $row['issue_type'] ?? '',
    'kategori' => $row['kategori'] ?? '',
    'sub_kategori' => $row['sub_kategori'] ?? '',
    'status' => $row['status'] ?? '',
    'priority' => $row['priority'] ?? '',
    'due_date' => $dueDate,
    'tanggal_selesai' => $tanggalSelesai,
    'description' => $row['description'] ?? '',
    'asset_id' => $row['asset_id'] ?? null,
    'asset_label' => $assetLabel,
    'client_id' => $row['client_id'] ?? null,
    'client_label' => $clientLabel,
    'departemen' => $row['departemen'] ?? '',
    'bagian' => $row['bagian'] ?? '',
    'jabatan' => $row['jabatan'] ?? '',
    'created_by' => $row['created_by'] ?? '',
    'created_by_username' => $createdByUsername,
    'created_at' => $createdAt,
    'updated_by' => $row['update_by'] ?? '',
    'updated_by_username' => $updatedByUsername,
    'updated_at' => $updatedAt,
];

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

$response['success'] = true;
$response['message'] = 'OK';
$response['data'] = $data;
echo json_encode($response);
