<?php
// ===================================================
// 1. INISIALISASI ENDPOINT
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

// ===================================================
// 2. RESPON DEFAULT & VALIDASI LOGIN
// ===================================================
$out = [ 'data' => [] ];
if (!isset($_SESSION['UserId']) || !$conn) {
    echo json_encode($out);
    exit;
}

try {
    // ===================================================
    // 3. IDENTIFIKASI PENGGUNA AKTIF
    // ===================================================
    $empSql = "SELECT e.id_emp, e.nama_lengkap FROM dbo.m_emp e WHERE e.id_emp = ?";
    $empStmt = sqlsrv_query($conn, $empSql, [ $_SESSION['UserId'] ]);
    $emp = $empStmt ? sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC) : null;
    if ($empStmt) sqlsrv_free_stmt($empStmt);
    // Nama Lengkap mengikuti pola form_pengajuan_perangkat.php agar konsisten
    $namaLengkap = htmlspecialchars($_SESSION['NamaLengkap'] ?? ($_SESSION['UserName'] ?? ($emp['nama_lengkap'] ?? '')));

    // ===================================================
    // 4. PENGAMBILAN DATA ASET
    // ===================================================
    $sql = "SELECT a.id_asset, a.kode_asset_seq, a.keterangan, k.nama_kategori, s.nama_status
            FROM dbo.m_asset a
            LEFT JOIN dbo.m_kategori k ON a.id_kategori = k.id_kategori
            LEFT JOIN dbo.m_status s ON a.id_status = s.id_status
            LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
            WHERE e.nama_lengkap = ?
            ORDER BY a.kode_asset_seq";
    $stmt = sqlsrv_query($conn, $sql, [ $namaLengkap ]);
    while ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $out['data'][] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
} catch (Exception $e) {
    // Dibiarkan kosong agar tidak membocorkan informasi sensitif ke frontend
}

echo json_encode($out);
