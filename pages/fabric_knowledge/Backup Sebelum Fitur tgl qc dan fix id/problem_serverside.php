<?php
// pages/fabric_knowledge/problem_serverside.php

// ====== CLEAN OUTPUT BUFFER ======
ob_clean();
ob_start();

session_start();
require_once __DIR__ . '/../../koneksi.php';

// ====== Auth ======
if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ====== ERROR HANDLING ======
error_reporting(0); // Nonaktifkan error reporting untuk mencegah output tidak diinginkan
ini_set('display_errors', 0);

try {
    // ====== Parameters dari DataTables ======
    $start  = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    $draw   = $_POST['draw'] ?? 1;

    // ====== Filter ======
    $kategori = $_POST['kategori'] ?? '';
    $tag      = $_POST['tag'] ?? '';
    $status   = $_POST['status'] ?? '';
    $nocp     = $_POST['nocp'] ?? '';
    $padry    = $_POST['padry'] ?? '';
    $tglAwal  = $_POST['tanggal_awal'] ?? '';
    $tglAkhir = $_POST['tanggal_akhir'] ?? '';

    // ====== Build Query ======
    $whereConditions = [];
    $params = [];
    $paramTypes = [];

    // Base query
    $baseQuery = "FROM dbo.fab_m_problem p 
                  LEFT JOIN dbo.fab_m_kategori k ON p.id_kategori = k.id_kategori 
                  LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag 
                  LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem";

    // ====== Search ======
    if (!empty($search)) {
        $whereConditions[] = "(p.deskripsi LIKE ? OR k.nama_kategori LIKE ? OR t.nama_tag LIKE ? OR p.nocp LIKE ? OR p.color LIKE ? OR p.routing LIKE ? OR p.created_by LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // ====== Filter kategori ======
    if (!empty($kategori)) {
        $whereConditions[] = "p.id_kategori = ?";
        $params[] = $kategori;
    }

    // ====== Filter tag ======
    if (!empty($tag)) {
        $whereConditions[] = "p.id_tag = ?";
        $params[] = $tag;
    }

    // ====== Filter status ======
    if (!empty($status)) {
        $whereConditions[] = "COALESCE(s.status, 'Open') = ?";
        $params[] = $status;
    }

    // ====== Filter No CP ======
    if (!empty($nocp)) {
        $whereConditions[] = "p.nocp LIKE ?";
        $params[] = "%{$nocp}%";
    }

    // ====== Filter Padry ======
    if (!empty($padry)) {
        $whereConditions[] = "p.pdr = ?";
        $params[] = $padry;
    }

    // ====== Filter tanggal transaksi ======
    if (!empty($tglAwal) && !empty($tglAkhir)) {
        $whereConditions[] = "CAST(p.created_at AS DATE) BETWEEN ? AND ?";
        $params[] = $tglAwal;
        $params[] = $tglAkhir;
    } elseif (!empty($tglAwal)) {
        $whereConditions[] = "CAST(p.created_at AS DATE) >= ?";
        $params[] = $tglAwal;
    } elseif (!empty($tglAkhir)) {
        $whereConditions[] = "CAST(p.created_at AS DATE) <= ?";
        $params[] = $tglAkhir;
    }

    // ====== Combine WHERE ======
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // ====== Total records ======
    $sqlTotal = "SELECT COUNT(*) as total " . $baseQuery;
    $stmtTotal = sqlsrv_query($conn, $sqlTotal);
    $totalRecords = 0;
    if ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['total'];
    }
    if ($stmtTotal) sqlsrv_free_stmt($stmtTotal);

    // ====== Total filtered records ======
    $sqlFiltered = "SELECT COUNT(*) as total " . $baseQuery . " " . $whereClause;
    $stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $params);
    $totalFiltered = 0;
    if ($stmtFiltered && $row = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = $row['total'];
    }
    if ($stmtFiltered) sqlsrv_free_stmt($stmtFiltered);

    // ====== Data records ======
    $sqlData = "SELECT 
                    p.id_problem, 
                    p.deskripsi, 
                    p.nocp,
                    p.color,
                    p.routing,
                    p.status_qc,
                    p.pdr,
                    p.resep,
                    p.analis,
                    p.review,
                    p.solusi,
                    k.nama_kategori, 
                    t.nama_tag, 
                    p.created_by,
                    p.created_at,
                    p.updated_at,
                    COALESCE(s.status, 'Open') AS status
                $baseQuery
                $whereClause
                ORDER BY p.created_at DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    // Tambahkan parameter untuk pagination
    $paramsData = array_merge($params, [(int)$start, (int)$length]);

    $stmtData = sqlsrv_query($conn, $sqlData, $paramsData);

    $data = [];
    if ($stmtData) {
        while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
            // Format dates safely
            $created_at = '-';
            if ($row['created_at'] instanceof DateTime) {
                $created_at = $row['created_at']->format('d-m-Y H:i');
            } elseif ($row['created_at']) {
                $created_at = date('d-m-Y H:i', strtotime($row['created_at']));
            }

            $updated_at = '-';
            if ($row['updated_at'] instanceof DateTime) {
                $updated_at = $row['updated_at']->format('d-m-Y H:i');
            } elseif ($row['updated_at']) {
                $updated_at = date('d-m-Y H:i', strtotime($row['updated_at']));
            }

            $deskripsi = $row['deskripsi'] ?? '';
            // if (strlen($deskripsi) > 100) {
            //     $deskripsi = substr($deskripsi, 0, 100) . '...';
            // }

            $data[] = [
                'id_problem'    => $row['id_problem'] ?? 0,
                'deskripsi'     => $deskripsi ?: '-',
                'nocp'          => $row['nocp'] ?? '-',
                'nama_kategori' => $row['nama_kategori'] ?? '-',
                'nama_tag'      => $row['nama_tag'] ?? '-',
                'status'        => $row['status'] ?? 'Open',
                'updated_at'    => $updated_at,
                'created_by'    => $row['created_by'] ?? '-',
                'created_at'    => $created_at,
                'aksi'          => $row['id_problem'] ?? 0
            ];
        }
        sqlsrv_free_stmt($stmtData);
    }

    $response = [
        'draw' => intval($draw),
        'recordsTotal' => intval($totalRecords),
        'recordsFiltered' => intval($totalFiltered),
        'data' => $data
    ];

} catch (Exception $e) {
    // Error handling yang lebih baik
    $response = [
        'draw' => intval($draw ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ];
}

// ====== CLEAN OUTPUT DAN KIRIM RESPONSE ======
ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>