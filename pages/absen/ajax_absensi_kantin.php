<?php
/**
 * AJAX Handler untuk Absensi Kantin
 * File ini HANYA menghasilkan output JSON, tidak ada HTML
 */

session_start();

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Error reporting untuk development (nonaktifkan di production)
error_reporting(0);
ini_set('display_errors', 0);

// Include koneksi database
require_once '../../koneksi.php';

// Cek session
if (!isset($_SESSION['UserId'])) {
    sendJsonError('Session expired. Silakan login kembali.', 401);
}

// Cek permission
$groupId = $_SESSION['GroupId'] ?? null;
$menuId = 126; // MenuId untuk Absensi Kantin

if (!$groupId) {
    sendJsonError('GroupId tidak ditemukan dalam session.', 403);
}

// Cek permission view
$sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
$canView = 0;

if ($stmt) {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canView = (int)$row['CanView'];
    }
    sqlsrv_free_stmt($stmt);
}

if ($canView == 0) {
    sendJsonError('Anda tidak memiliki hak akses untuk melihat data ini.', 403);
}

// Fungsi untuk mengirim error JSON
function sendJsonError($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $message
    ]);
    exit;
}

/**
 * Apply shift filter to SQL query
 */
function applyShiftFilter(&$sql, &$params, $shift, $tanggalAwal, $tanggalAkhir) {
    $shiftRanges = [
        'non_shift' => [
            'start' => '11:30:00',
            'end' => '13:30:00',
            'date_adjustment' => 0
        ],
        'shift_malam' => [
            'start' => '01:00:00',
            'end' => '04:00:00',
            'date_adjustment' => 1
        ],
        'shift_pagi' => [
            'start' => '09:00:00',
            'end' => '11:30:00',
            'date_adjustment' => 0
        ],
        'shift_siang' => [
            'start' => '17:00:00',
            'end' => '20:00:00',
            'date_adjustment' => 0
        ],
        'no_all' => [
            'ranges' => [
                ['start' => '04:00:00', 'end' => '09:00:00', 'date_adjustment' => 0],
                ['start' => '14:00:00', 'end' => '17:00:00', 'date_adjustment' => 0],
                ['start' => '20:00:00', 'end' => '01:00:00', 'date_adjustment' => 1]
            ]
        ]
    ];
    
    if (empty($shift) || !isset($shiftRanges[$shift])) {
        return;
    }
    
    $range = $shiftRanges[$shift];
    
    // Untuk No All yang memiliki multiple ranges
    if ($shift == 'no_all') {
        $sql .= " AND (";
        $first = true;
        foreach ($range['ranges'] as $subRange) {
            if (!$first) {
                $sql .= " OR ";
            }
            
            if ($subRange['date_adjustment'] == 0) {
                $sql .= " CAST(a.waktu AS TIME) BETWEEN ? AND ?";
                $params[] = $subRange['start'];
                $params[] = $subRange['end'];
            } else {
                $sql .= " (CAST(a.waktu AS TIME) BETWEEN ? AND '23:59:59' AND CAST(a.waktu AS DATE) = ?)";
                $params[] = $subRange['start'];
                $params[] = $tanggalAwal;
                
                $sql .= " OR (CAST(a.waktu AS TIME) BETWEEN '00:00:00' AND ? AND CAST(a.waktu AS DATE) = DATEADD(DAY, 1, ?))";
                $params[] = $subRange['end'];
                $params[] = $tanggalAwal;
            }
            $first = false;
        }
        $sql .= ")";
        return;
    }
    
    // Untuk shift lainnya
    if ($range['date_adjustment'] == 0) {
        $sql .= " AND CAST(a.waktu AS TIME) BETWEEN ? AND ?";
        $params[] = $range['start'];
        $params[] = $range['end'];
    } else {
        if ($shift == 'shift_malam') {
            $sql .= " AND (CAST(a.waktu AS TIME) BETWEEN ? AND '23:59:59' AND CAST(a.waktu AS DATE) = ?)";
            $params[] = $range['start'];
            $params[] = $tanggalAwal;
            
            $sql .= " OR (CAST(a.waktu AS TIME) BETWEEN '00:00:00' AND ? AND CAST(a.waktu AS DATE) = DATEADD(DAY, 1, ?))";
            $params[] = $range['end'];
            $params[] = $tanggalAwal;
        }
    }
}

/**
 * Determine shift based on time
 */
function determineShift($waktu) {
    $time = date('H:i:s', strtotime($waktu));
    
    if ($time >= '11:30:00' && $time <= '13:30:00') {
        return 'Non Shift';
    } elseif ($time >= '01:00:00' && $time <= '04:00:00') {
        return 'Shift Malam';
    } elseif ($time >= '09:00:00' && $time <= '11:30:00') {
        return 'Shift Pagi';
    } elseif ($time >= '17:00:00' && $time <= '20:00:00') {
        return 'Shift Siang';
    } elseif ($time >= '04:00:00' && $time <= '09:00:00') {
        return 'No All';
    } elseif ($time >= '14:00:00' && $time <= '17:00:00') {
        return 'No All';
    } elseif ($time >= '20:00:00' || $time <= '01:00:00') {
        return 'No All';
    } else {
        return 'Unknown';
    }
}

try {
    // Parameters DataTables
    $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
    $search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
    
    // Filter parameters
    $filters = [
        'tanggal_awal' => $_GET['tanggal_awal'] ?? date('Y-m-01'),
        'tanggal_akhir' => $_GET['tanggal_akhir'] ?? date('Y-m-d'),
        'mesin' => $_GET['mesin'] ?? '',
        'shift' => $_GET['shift'] ?? ''
    ];
    
    // ==================== HITUNG TOTAL RECORDS ====================
    $totalSql = "SELECT COUNT(*) as total FROM dbo.log_absensi";
    $totalStmt = sqlsrv_query($conn, $totalSql);
    $totalRecords = 0;
    
    if ($totalStmt && $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = (int)$row['total'];
        sqlsrv_free_stmt($totalStmt);
    }
    
    // ==================== QUERY UTAMA DENGAN FILTER ====================
    $sql = "SELECT
                a.waktu,  
                a.nama, 
                d.dept, 
                b.bagian, 
                s.subbag, 
                f.nama_mesin,
                f.id as mesin_id
            FROM
                dbo.log_absensi AS a
            LEFT JOIN dbo.m_fingerprint AS f ON a.mesin_id = f.id
            LEFT JOIN dbo.m_emp AS e ON a.user_id = e.id_emp
            LEFT JOIN dbo.m_dept AS d ON e.id_dept = d.id_dept
            LEFT JOIN dbo.m_bag AS b ON e.id_bag = b.id_bag
            LEFT JOIN dbo.m_subbag AS s ON e.id_subbag = s.id_subbag
            WHERE 1=1";
    
    $params = [];
    
    // Filter mesin
    if (!empty($filters['mesin'])) {
        $sql .= " AND f.id = ?";
        $params[] = $filters['mesin'];
    }
    
    // Filter tanggal
    if (!empty($filters['tanggal_awal'])) {
        $sql .= " AND CAST(a.waktu AS DATE) >= ?";
        $params[] = $filters['tanggal_awal'];
    }
    
    if (!empty($filters['tanggal_akhir'])) {
        $sql .= " AND CAST(a.waktu AS DATE) <= ?";
        $params[] = $filters['tanggal_akhir'];
    }
    
    // Filter shift
    if (!empty($filters['shift'])) {
        applyShiftFilter($sql, $params, $filters['shift'], $filters['tanggal_awal'], $filters['tanggal_akhir']);
    }
    
    // Search untuk DataTables
    if (!empty($search)) {
        $sql .= " AND (a.nama LIKE ? OR d.dept LIKE ? OR b.bagian LIKE ? OR s.subbag LIKE ? OR f.nama_mesin LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Hitung total filtered records
    $countSql = "SELECT COUNT(*) as total FROM ($sql) as subquery";
    $countStmt = sqlsrv_query($conn, $countSql, $params);
    $totalFiltered = 0;
    
    if ($countStmt && $row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = (int)$row['total'];
    }
    
    if ($countStmt) {
        sqlsrv_free_stmt($countStmt);
    }
    
    // Tambahkan ORDER BY dan pagination
    $sql .= " ORDER BY a.waktu DESC
              OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    $params[] = $start;
    $params[] = $length;
    
    // Eksekusi query utama
    $stmt = sqlsrv_query($conn, $sql, $params);
    $absensiData = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format waktu
            if ($row['waktu'] instanceof DateTime) {
                $tanggalFormatted = $row['waktu']->format('d-m-Y');
                $jamFormatted = $row['waktu']->format('H:i:s');
                $waktuFull = $row['waktu']->format('Y-m-d H:i:s');
            } else {
                $waktu = DateTime::createFromFormat('Y-m-d H:i:s', $row['waktu']);
                if ($waktu) {
                    $tanggalFormatted = $waktu->format('d-m-Y');
                    $jamFormatted = $waktu->format('H:i:s');
                    $waktuFull = $waktu->format('Y-m-d H:i:s');
                } else {
                    $tanggalFormatted = $row['waktu'];
                    $jamFormatted = '-';
                    $waktuFull = $row['waktu'];
                }
            }
            
            // Tentukan shift
            $shift = determineShift($waktuFull);
            
            // Format data untuk DataTables
            $absensiData[] = [
                $start + count($absensiData) + 1, // No
                $tanggalFormatted,
                htmlspecialchars($jamFormatted),
                htmlspecialchars($row['nama'] ?? ''),
                htmlspecialchars($row['dept'] ?? '-'),
                htmlspecialchars($row['bagian'] ?? '-'),
                htmlspecialchars($row['subbag'] ?? '-'),
                htmlspecialchars($row['nama_mesin'] ?? ''),
                '<span class="badge badge-info">' . $shift . '</span>'
            ];
        }
        sqlsrv_free_stmt($stmt);
    }
    
    // Prepare response
    $response = [
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data' => $absensiData
    ];
    
    // Output JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log("AJAX Error in absensi_kantin: " . $e->getMessage());
    
    // Send error response
    sendJsonError('Terjadi kesalahan saat memuat data: ' . $e->getMessage(), 500);
}

// Tutup koneksi jika masih terbuka
if ($conn) {
    sqlsrv_close($conn);
}
?>