<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

$formType = $_POST['form_type'] ?? '';
if ($formType !== 'serah_terima_aplikasi') {
    echo json_encode(['success' => false, 'message' => 'Jenis form tidak dikenal!']);
    exit;
}

function normalizeDateValue($value, $fallback = null)
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
        [$d, $m, $y] = explode('-', $value);
        return "$y-$m-$d";
    }

    return $fallback;
}

function generateTicketSerahTerima($conn)
{
    $today = date('dmY');
    $like = "STH-$today-%";
    $sql = "SELECT TOP 1 ticket
            FROM dbo.Form_Serah_Terima_Aplikasi
            WHERE ticket LIKE ?
            ORDER BY ticket DESC";
    $stmt = sqlsrv_query($conn, $sql, [$like]);
    $last = 0;

    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row && isset($row['ticket'])) {
            $parts = explode('-', $row['ticket']);
            $last = isset($parts[2]) ? intval($parts[2]) : 0;
        }
        sqlsrv_free_stmt($stmt);
    }

    $next = str_pad($last + 1, 3, '0', STR_PAD_LEFT);
    return "STH-$today-$next";
}

$namaAplikasi = trim($_POST['nama_aplikasi'] ?? '');
$namaModul = trim($_POST['nama_modul'] ?? '');
$tanggalSerahTerima = normalizeDateValue($_POST['tanggal_serah_terima'] ?? '', date('Y-m-d'));
$tanggalSelesai = normalizeDateValue($_POST['tanggal_selesai'] ?? '', date('Y-m-d'));
$dimintaOleh = trim($_POST['diminta_oleh'] ?? '');
$deskripsiAplikasi = trim($_POST['deskripsi_aplikasi'] ?? '');
$statusTestingIt = !empty($_POST['status_testing_it']) ? 1 : 0;
$statusTestingUser = !empty($_POST['status_testing_user']) ? 1 : 0;
$hasilTesting = trim($_POST['hasil_testing'] ?? '');
$catatanRevisi = trim($_POST['catatan_revisi'] ?? '');
$statusPenerimaan = trim($_POST['status_penerimaan'] ?? '');
$catatanPenerimaan = trim($_POST['catatan_penerimaan'] ?? '');
$namaPemohon = trim($_POST['nama_pemohon'] ?? ($_SESSION['NamaLengkap'] ?? ''));
$jabatan = trim($_POST['jabatan'] ?? '');
$departemen = trim($_POST['departemen'] ?? '');
$bagian = trim($_POST['bagian'] ?? '');
$createdBy = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];

if ($namaAplikasi === '' || $namaModul === '' || $dimintaOleh === '' || $deskripsiAplikasi === '') {
    echo json_encode(['success' => false, 'message' => 'Informasi umum dan deskripsi wajib diisi.']);
    exit;
}

if (!$statusTestingIt && !$statusTestingUser) {
    echo json_encode(['success' => false, 'message' => 'Pilih minimal satu status testing.']);
    exit;
}

if (!in_array($hasilTesting, ['sesuai', 'revisi'], true)) {
    echo json_encode(['success' => false, 'message' => 'Hasil testing wajib dipilih.']);
    exit;
}

if ($hasilTesting === 'revisi' && $catatanRevisi === '') {
    echo json_encode(['success' => false, 'message' => 'Catatan revisi wajib diisi.']);
    exit;
}

if (!in_array($statusPenerimaan, ['sesuai', 'catatan', 'perbaikan'], true)) {
    echo json_encode(['success' => false, 'message' => 'Status penerimaan wajib dipilih.']);
    exit;
}

if (in_array($statusPenerimaan, ['catatan', 'perbaikan'], true) && $catatanPenerimaan === '') {
    echo json_encode(['success' => false, 'message' => 'Catatan penerimaan/perbaikan wajib diisi.']);
    exit;
}

$ticket = generateTicketSerahTerima($conn);

$sql = "INSERT INTO dbo.Form_Serah_Terima_Aplikasi
        (ticket, nama_pemohon, jabatan, departemen, bagian,
         tanggal_serah_terima, tanggal_selesai, diminta_oleh,
         nama_aplikasi, nama_modul, deskripsi_aplikasi,
         status_testing_it, status_testing_user, hasil_testing, catatan_revisi,
         status_penerimaan, catatan_penerimaan,
         status_ticket, kategori, created_at, created_by, updated_at, updated_by)
        VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?, ?,
         ?, ?,
         'Pending', 'Serah Terima Aplikasi', GETDATE(), ?, GETDATE(), ?)";

$params = [
    $ticket, $namaPemohon, $jabatan, $departemen, $bagian,
    $tanggalSerahTerima, $tanggalSelesai, $dimintaOleh,
    $namaAplikasi, $namaModul, $deskripsiAplikasi,
    $statusTestingIt, $statusTestingUser, $hasilTesting, $catatanRevisi,
    $statusPenerimaan, $catatanPenerimaan,
    $createdBy, $createdBy,
];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan form serah terima: ' . print_r(sqlsrv_errors(), true),
    ]);
    exit;
}

sqlsrv_free_stmt($stmt);

echo json_encode([
    'success' => true,
    'message' => 'Form Serah Terima berhasil disimpan',
    'ticket' => $ticket,
]);
