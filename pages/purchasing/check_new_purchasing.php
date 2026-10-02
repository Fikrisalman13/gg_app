<?php
// ==============================================================================
// ENDPOINT POLLING NOTIFIKASI BROWSER PURCHASING (ALA MODUL TICKET)
// Mengecek dokumen serah terima baru yang ditujukan ke user (Penerima)
// ==============================================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once file_exists(__DIR__ . '/../../koneksi.php') ? __DIR__ . '/../../koneksi.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = [
    'success'       => false,
    'table_ready'   => false,
    'has_new'       => false,
    'latest_id'     => 0,
    'pending_count' => 0,
    'latest_doc'    => null
];

if (!isset($_SESSION['UserId']) && !isset($_SESSION['UserName'])) {
    echo json_encode($response);
    exit;
}

$lastId = intval($_POST['last_id'] ?? ($_GET['last_id'] ?? 0));

// 1. Identifikasi User Login
$currUserName = trim($_SESSION['UserName'] ?? '');
$currNama     = trim($_SESSION['NamaLengkap'] ?? '');
$currDept     = '';
$currFormatted = '';

if (!empty($conn) && !empty($currUserName)) {
    $qUser = sqlsrv_query($conn, "SELECT e.nama_lengkap, d.dept 
        FROM dbo.SMUserMs u 
        LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp 
        LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
        LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
        LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
        WHERE u.UserName = ?", [$currUserName]);
    if ($qUser && ($uRow = sqlsrv_fetch_array($qUser, SQLSRV_FETCH_ASSOC))) {
        if (!empty($uRow['nama_lengkap'])) $currNama = trim($uRow['nama_lengkap']);
        if (!empty($uRow['dept'])) $currDept = trim($uRow['dept']);
    }
}

if (empty($currNama)) $currNama = $currUserName;
$currFormatted = !empty($currDept) ? "{$currNama} ({$currDept})" : $currNama;

// 2. Cek Apakah Tabel Database 'purchasing_header' Sudah Dibuat
$tableReady = false;
if (!empty($conn)) {
    $qCheckTable = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'purchasing_header'");
    if ($qCheckTable && sqlsrv_has_rows($qCheckTable)) {
        $tableReady = true;
    }
}

$response['table_ready'] = $tableReady;

// 3. Logika Pengecekan Dokumen Baru
if ($tableReady) {
    // Mode DATABASE (SQL Server)
    // Query dokumen aktif yang penerimanya cocok dengan user yang sedang login
    $sqlPending = "SELECT COUNT(*) as total FROM dbo.purchasing_header 
                   WHERE is_deleted = 0 
                     AND status_ttd = 'Menunggu TTD'
                     AND (penerima LIKE ? OR penerima LIKE ? OR penerima = ?)";
    $paramsPending = ["%{$currNama}%", "%{$currUserName}%", $currFormatted];
    $stmtPending = sqlsrv_query($conn, $sqlPending, $paramsPending);
    if ($stmtPending && ($pRow = sqlsrv_fetch_array($stmtPending, SQLSRV_FETCH_ASSOC))) {
        $response['pending_count'] = intval($pRow['total']);
    }

    // Query dokumen terbaru yang ditujukan untuk user ini
    $sqlLatest = "SELECT TOP 1 id, tanggal, type, type_keterangan, no_po, vendor, pengirim, penerima, status_ttd, created_at
                  FROM dbo.purchasing_header
                  WHERE is_deleted = 0 
                    AND (penerima LIKE ? OR penerima LIKE ? OR penerima = ?)
                  ORDER BY id DESC";
    $stmtLatest = sqlsrv_query($conn, $sqlLatest, $paramsPending);

    if ($stmtLatest && ($latestRow = sqlsrv_fetch_array($stmtLatest, SQLSRV_FETCH_ASSOC))) {
        $maxId = intval($latestRow['id']);
        $response['latest_id'] = $maxId;

        // Ada dokumen baru jika ID dokumen lebih besar dari yang terakhir diketahui client
        // dan dokumen tersebut masih Menunggu TTD
        if ($lastId > 0 && $maxId > $lastId && $latestRow['status_ttd'] === 'Menunggu TTD') {
            $response['has_new'] = true;
            $response['latest_doc'] = [
                'id'         => $maxId,
                'tanggal'    => $latestRow['tanggal'] ? $latestRow['tanggal']->format('d/m/Y') : date('d/m/Y'),
                'type'       => $latestRow['type'] . (!empty($latestRow['type_keterangan']) ? ' (' . $latestRow['type_keterangan'] . ')' : ''),
                'no_po'      => $latestRow['no_po'] ?? '-',
                'vendor'     => $latestRow['vendor'] ?? '-',
                'pengirim'   => $latestRow['pengirim'] ?? '-',
                'penerima'   => $latestRow['penerima'] ?? '-'
            ];
        }
    }

    $response['success'] = true;
} else {
    // Mode FALLBACK SESSION (sebelum user mengeksekusi script SQL di SSMS)
    if (isset($_SESSION['purchasing_data']) && is_array($_SESSION['purchasing_data'])) {
        $pendingCount = 0;
        $maxId = 0;
        $latestFound = null;

        foreach ($_SESSION['purchasing_data'] as $item) {
            $itemId = intval($item['id'] ?? 0);
            if ($itemId > $maxId) $maxId = $itemId;

            $penerima = $item['penerima'] ?? '';
            $isRecipient = (stripos($penerima, $currNama) !== false) || 
                           (stripos($penerima, $currUserName) !== false) || 
                           (!empty($currDept) && stripos($penerima, $currDept) !== false && stripos($penerima, $currNama) !== false);

            if ($isRecipient && ($item['status_ttd_raw'] ?? '') === 'Menunggu TTD') {
                $pendingCount++;
                if ($itemId > $lastId) {
                    $latestFound = $item;
                }
            }
        }

        $response['pending_count'] = $pendingCount;
        $response['latest_id'] = $maxId;

        if ($lastId > 0 && $latestFound !== null) {
            $response['has_new'] = true;
            $response['latest_doc'] = [
                'id'       => $latestFound['id'],
                'tanggal'  => date('d/m/Y', strtotime($latestFound['tanggal'])),
                'type'     => $latestFound['type'] . (!empty($latestFound['type_keterangan']) ? ' (' . $latestFound['type_keterangan'] . ')' : ''),
                'no_po'    => $latestFound['no_po'] ?? '-',
                'vendor'   => $latestFound['vendor'] ?? '-',
                'pengirim' => $latestFound['pengirim'] ?? '-',
                'penerima' => $latestFound['penerima'] ?? '-'
            ];
        }
    }
    $response['success'] = true;
}

echo json_encode($response);
