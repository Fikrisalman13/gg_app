<?php
/**
 * Backend API untuk Web Portal Customer Sumber Agung
 * Menangani request data yang sudah di-publish, view detail, dan tracking status viewed.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// Cek sesi login
$userId = $_SESSION['UserId'] ?? null;
$userName = trim($_SESSION['UserName'] ?? '');
$namaLengkap = trim($_SESSION['NamaLengkap'] ?? $userName);

if (empty($userId) && empty($userName)) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid. Silakan login kembali.']);
    exit;
}

// Cek tabel sa_titipan
$tableReady = false;
if (!empty($conn)) {
    $qCheck = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'sa_titipan'");
    if ($qCheck && sqlsrv_has_rows($qCheck)) {
        $tableReady = true;
    }
}

if (!$tableReady) {
    echo json_encode(['status' => 'error', 'message' => 'Layanan portal sedang dalam pemeliharaan (tabel belum siap).']);
    exit;
}

function formatDatePortal($val, $format = 'd/m/Y') {
    if (empty($val)) return '-';
    if ($val instanceof DateTimeInterface) return $val->format($format);
    return date($format, strtotime($val));
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {

    // =========================================================================
    // LOAD PORTAL DATA
    // =========================================================================
    case 'load_portal_data':
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to'] ?? '');

        // Portal HANYA menampilkan data yang SUDAH DIPUBLISH dan TIDAK DIHAPUS
        $where = "is_deleted = 0 AND is_published = 1";
        $params = [];

        if (!empty($dateFrom) && !empty($dateTo)) {
            $where .= " AND COALESCE(tgl_kirim, tgl_titipan) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif (!empty($dateFrom)) {
            $where .= " AND COALESCE(tgl_kirim, tgl_titipan) >= ?";
            $params[] = $dateFrom;
        } elseif (!empty($dateTo)) {
            $where .= " AND COALESCE(tgl_kirim, tgl_titipan) <= ?";
            $params[] = $dateTo;
        }

        $sql = "SELECT * FROM dbo.sa_titipan WHERE {$where} ORDER BY COALESCE(tgl_kirim, tgl_titipan) DESC, id DESC";
        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        $no = 1;

        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $hasPL = !empty($row['packinglist_file']);
                $hasSJ = !empty($row['suratjalan_file']);

                $data[] = [
                    'no'               => $no++,
                    'id'               => (int)$row['id'],
                    'tgl_kirim'        => formatDatePortal($row['tgl_kirim']),
                    'no_sc'            => trim($row['no_sc'] ?? '-'),
                    'tgl_sc'           => formatDatePortal($row['tgl_sc']),
                    'product_name'     => trim($row['product_name'] ?? '-'),
                    'product_code'     => trim($row['product_code'] ?? ''),
                    'label_jual'       => trim($row['label_jual'] ?? '-'),
                    'cust_color'       => trim($row['cust_color'] ?? '-'),
                    'no_titipan'       => trim($row['no_titipan'] ?? '-'),
                    'tgl_titipan'      => formatDatePortal($row['tgl_titipan']),
                    'no_do'            => trim($row['no_do'] ?? '-'),
                    'tgl_do'           => formatDatePortal($row['tgl_do']),
                    'no_packing'       => trim($row['no_packing'] ?? '-'),
                    'no_bale'          => trim($row['no_bale'] ?? '-'),
                    'totqtym'          => $row['totqtym'] !== null ? (float)$row['totqtym'] : null,
                    'totqtyyard'       => $row['totqtyyard'] !== null ? (float)$row['totqtyyard'] : null,
                    'countbatch'       => $row['countbatch'] !== null ? (int)$row['countbatch'] : null,
                    'dodtdesc'         => trim($row['dodtdesc'] ?? ''),
                    'cust_name'        => trim($row['cust_name'] ?? '-'),
                    'do_qty'           => $row['do_qty'] !== null ? (float)$row['do_qty'] : null,
                    'qty_kirim'        => $row['qty_kirim'] !== null ? (float)$row['qty_kirim'] : null,
                    'uom'              => trim($row['uom'] ?? '-'),
                    'ket_titipan_2'    => trim($row['ket_titipan_2'] ?? ''),
                    'has_packinglist'  => $hasPL,
                    'packinglist_file' => $row['packinglist_file'],
                    'packinglist_type' => $row['packinglist_type'],
                    'has_suratjalan'   => $hasSJ,
                    'suratjalan_file'  => $row['suratjalan_file'],
                    'suratjalan_type'  => $row['suratjalan_type'],
                    'is_viewed'        => (bool)$row['is_viewed'],
                ];
            }
        }

        echo json_encode(['status' => 'ok', 'data' => $data]);
        break;

    // =========================================================================
    // MARK VIEWED
    // Saat customer membuka detail atau melihat dokumen PDF
    // =========================================================================
    case 'mark_viewed':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid.']);
            exit;
        }

        $viewerName = !empty($namaLengkap) ? $namaLengkap : $userName;

        $stmt = sqlsrv_query($conn,
            "UPDATE dbo.sa_titipan 
             SET is_viewed = 1, viewed_at = GETDATE(), viewed_by = ? 
             WHERE id = ? AND is_deleted = 0 AND is_published = 1 AND is_viewed = 0",
            [$viewerName, $id]);

        if ($stmt) {
            echo json_encode(['status' => 'ok', 'message' => 'Status dilihat berhasil dicatat.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mencatat status view.']);
        }
        break;

    // =========================================================================
    // GET PORTAL DETAIL
    // =========================================================================
    case 'get_portal_detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
            exit;
        }

        $q = sqlsrv_query($conn, "SELECT * FROM dbo.sa_titipan WHERE id = ? AND is_deleted = 0 AND is_published = 1", [$id]);
        if (!$q || !($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan atau belum dipublish.']);
            exit;
        }

        // Tandai otomatis viewed
        $viewerName = !empty($namaLengkap) ? $namaLengkap : $userName;
        sqlsrv_query($conn,
            "UPDATE dbo.sa_titipan SET is_viewed = 1, viewed_at = GETDATE(), viewed_by = ? WHERE id = ? AND is_viewed = 0",
            [$viewerName, $id]);

        $detail = [
            'id'               => (int)$row['id'],
            'tgl_kirim'        => formatDatePortal($row['tgl_kirim']),
            'no_sc'            => trim($row['no_sc'] ?? '-'),
            'tgl_sc'           => formatDatePortal($row['tgl_sc']),
            'product_name'     => trim($row['product_name'] ?? '-'),
            'product_code'     => trim($row['product_code'] ?? ''),
            'label_jual'       => trim($row['label_jual'] ?? '-'),
            'cust_color'       => trim($row['cust_color'] ?? '-'),
            'no_titipan'       => trim($row['no_titipan'] ?? '-'),
            'tgl_titipan'      => formatDatePortal($row['tgl_titipan']),
            'no_do'            => trim($row['no_do'] ?? '-'),
            'tgl_do'           => formatDatePortal($row['tgl_do']),
            'no_packing'       => trim($row['no_packing'] ?? '-'),
            'no_bale'          => trim($row['no_bale'] ?? '-'),
            'totqtym'          => $row['totqtym'] !== null ? (float)$row['totqtym'] : null,
            'totqtyyard'       => $row['totqtyyard'] !== null ? (float)$row['totqtyyard'] : null,
            'countbatch'       => $row['countbatch'] !== null ? (int)$row['countbatch'] : null,
            'dodtdesc'         => trim($row['dodtdesc'] ?? ''),
            'cust_name'        => trim($row['cust_name'] ?? '-'),
            'cust_id'          => trim($row['cust_id'] ?? ''),
            'do_qty'           => $row['do_qty'] !== null ? (float)$row['do_qty'] : null,
            'qty_kirim'        => $row['qty_kirim'] !== null ? (float)$row['qty_kirim'] : null,
            'uom'              => trim($row['uom'] ?? '-'),
            'ket_titipan_1'    => trim($row['ket_titipan_1'] ?? ''),
            'ket_titipan_2'    => trim($row['ket_titipan_2'] ?? ''),
            'dodesc'           => trim($row['dodesc'] ?? ''),
            'packinglist_file' => $row['packinglist_file'],
            'packinglist_type' => $row['packinglist_type'],
            'suratjalan_file'  => $row['suratjalan_file'],
            'suratjalan_type'  => $row['suratjalan_type'],
            'published_at'     => formatDatePortal($row['published_at'], 'd/m/Y H:i'),
        ];

        echo json_encode(['status' => 'ok', 'data' => $detail]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid: ' . htmlspecialchars($action)]);
        break;
}
