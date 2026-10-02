<?php
session_start();
include '../../koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Set content type
header('Content-Type: application/json');

try {
    // Get request parameters
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderColumn = $_POST['order'][0]['column'] ?? 0;
    $orderDir = $_POST['order'][0]['dir'] ?? 'desc';
    
    // Get filter values
    $filters = [
        'kategori' => $_POST['kategori'] ?? '',
        'dept' => $_POST['dept'] ?? '',
        'bagian' => $_POST['bagian'] ?? '',
        'subbag' => $_POST['subbag'] ?? ''
    ];

    // Base query
    $baseQuery = "FROM dbo.dokumen AS d
        LEFT JOIN dbo.m_kode_dok AS kd ON d.id_kode_dok = kd.id_kode_dok
        LEFT JOIN dbo.m_kategori_dok AS k ON d.id_kategori = k.id_kategori
        LEFT JOIN dbo.m_dept ON d.id_dept = m_dept.id_dept
        LEFT JOIN dbo.m_bag ON d.id_bag = m_bag.id_bag
        LEFT JOIN dbo.m_subbag ON d.id_subbag = m_subbag.id_subbag
        WHERE 1=1";

    $params = [];

    // Apply filters
    if (!empty($filters['kategori'])) {
        $baseQuery .= " AND d.id_kategori = ?";
        $params[] = $filters['kategori'];
    }

    if (!empty($filters['dept'])) {
        $baseQuery .= " AND d.id_dept = ?";
        $params[] = $filters['dept'];
    }

    if (!empty($filters['bagian'])) {
        $baseQuery .= " AND d.id_bag = ?";
        $params[] = $filters['bagian'];
    }
    
    if (!empty($filters['subbag'])) {
        $baseQuery .= " AND d.id_subbag = ?";
        $params[] = $filters['subbag'];
    }

    // Apply search
    if (!empty($searchValue)) {
        $baseQuery .= " AND (d.nama_dokumen LIKE ? OR d.kode_dok_seq LIKE ? OR k.nama_kategori LIKE ? OR m_dept.dept LIKE ? OR m_bag.bagian LIKE ?)";
        $searchParam = "%{$searchValue}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Get total records (tanpa filter)
    $countTotalQuery = "SELECT COUNT(*) as total FROM dbo.dokumen";
    $stmt = sqlsrv_query($conn, $countTotalQuery);
    $totalRecords = 0;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['total'];
    }
    sqlsrv_free_stmt($stmt);

    // Get filtered records count (dengan filter)
    $countFilteredQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $stmt = sqlsrv_query($conn, $countFilteredQuery, $params);
    $filteredRecords = 0;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $filteredRecords = $row['total'];
    }
    sqlsrv_free_stmt($stmt);

    // Define columns for ordering
    $columns = [
        0 => 'd.id_dok',
        1 => 'd.kode_dok_seq',
        2 => 'd.nama_dokumen', 
        3 => 'd.revisi',
        4 => 'm_dept.dept',
        5 => 'd.tanggal_upload',
        6 => 'd.deskripsi'
    ];

    // Apply ordering
    $orderBy = "";
    if (isset($columns[$orderColumn])) {
        $orderBy = " ORDER BY " . $columns[$orderColumn] . " " . $orderDir;
    } else {
        $orderBy = " ORDER BY d.tanggal_upload DESC"; // Default ordering
    }

    // Main query dengan pagination
    $mainQuery = "SELECT 
        d.id_dok,
        d.kode_dok_seq,
        d.revisi,
        d.tanggal_upload,
        d.tanggal_revisi,
        d.tanggal_terbit,
        d.nama_dokumen,
        d.file_pdf,
        d.deskripsi,
        d.upddate,
        d.upduser,
        kd.kode_dok,
        k.nama_kategori,
        m_dept.dept,
        m_bag.bagian,
        m_subbag.subbag,
        CONCAT(kd.kode_dok, '-', d.kode_dok_seq) as no_dokumen
        " . $baseQuery . $orderBy;

    // Debug: Simpan query untuk troubleshooting
    $debugQuery = $mainQuery;
    
    // Add pagination hanya jika length tidak -1
    if ($length != -1) {
        $mainQuery .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $params[] = (int)$start;
        $params[] = (int)$length;
    }

    // Execute main query
    $stmt = sqlsrv_query($conn, $mainQuery, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        echo json_encode(['error' => 'Database error: ' . print_r($errors, true)]);
        exit;
    }
    
    $data = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format tanggal untuk response
            $tanggal_upload = '-';
            $tanggal_revisi = '-';
            $tanggal_terbit = '-';
            
            if ($row['tanggal_upload'] instanceof DateTime) {
                $tanggal_upload = $row['tanggal_upload']->format('Y-m-d H:i:s');
            } elseif (!empty($row['tanggal_upload'])) {
                $tanggal_upload = $row['tanggal_upload'];
            }
            
            if ($row['tanggal_revisi'] instanceof DateTime) {
                $tanggal_revisi = $row['tanggal_revisi']->format('Y-m-d');
            } elseif (!empty($row['tanggal_revisi'])) {
                $tanggal_revisi = $row['tanggal_revisi'];
            }
            
            if ($row['tanggal_terbit'] instanceof DateTime) {
                $tanggal_terbit = $row['tanggal_terbit']->format('Y-m-d');
            } elseif (!empty($row['tanggal_terbit'])) {
                $tanggal_terbit = $row['tanggal_terbit'];
            }
            
            // Format data untuk response
            $data[] = [
                'id_dok' => $row['id_dok'] ?? '',
                'no_dokumen' => $row['no_dokumen'] ?? '',
                'kode_dok_seq' => $row['kode_dok_seq'] ?? '',
                'nama_dokumen' => $row['nama_dokumen'] ?? '',
                'revisi' => $row['revisi'] ?? '1',
                'tanggal_upload' => $tanggal_upload,
                'tanggal_revisi' => $tanggal_revisi,
                'tanggal_terbit' => $tanggal_terbit,
                'deskripsi' => $row['deskripsi'] ?? '',
                'upduser' => $row['upduser'] ?? '',
                'nama_kategori' => $row['nama_kategori'] ?? '',
                'dept' => $row['dept'] ?? '',
                'bagian' => $row['bagian'] ?? '',
                'subbag' => $row['subbag'] ?? '',
                'file_pdf' => $row['file_pdf'] ?? ''
            ];
        }
        sqlsrv_free_stmt($stmt);
    }

    // Prepare response
    $response = [
        'draw' => intval($draw),
        'recordsTotal' => intval($totalRecords),
        'recordsFiltered' => intval($filteredRecords),
        'data' => $data
    ];

    // Debug output
    if (empty($data)) {
        $response['debug'] = [
            'query' => $mainQuery,
            'params' => $params,
            'totalRecords' => $totalRecords,
            'filteredRecords' => $filteredRecords
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>