<?php
ob_start();
header('Content-Type: application/json');
include('../../../koneksi.php');

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Total Count
$stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM arsip_peminjaman");
$totalRecords = 0;
if ($stmtCount && $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['total'];
}

// Filter search
$where = " WHERE 1=1";
$params = [];
if (!empty($searchValue)) {
    $where .= " AND (nama_peminjam LIKE ? OR status LIKE ? OR CAST(id_peminjaman AS VARCHAR) LIKE ?)";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
}

// Filtered Count
$stmtFiltered = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM arsip_peminjaman" . $where, $params);
$recordsFiltered = 0;
if ($stmtFiltered && $row = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)) {
    $recordsFiltered = $row['total'];
}

// Data Query
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'desc';
$columns = ['id_peminjaman', 'nama_peminjam', 'tgl_pinjam', 'tgl_kembali_rencana' , 'tgl_kembali', 'status'];
$orderBy = $columns[$orderColumnIndex] ?? 'id_peminjaman';

$dataQuery = "SELECT * FROM arsip_peminjaman" . $where . 
             " ORDER BY $orderBy $orderDir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

$stmtData = sqlsrv_query($conn, $dataQuery, $params);
$data = [];
$no = $start + 1;

// Helper function for safe JSON encoding
function safe_json($val) {
    if (is_string($val)) {
        return mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1, UTF-8, ASCII');
    }
    return $val;
}

// Disable error display for this script to prevent breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $statusBadge = ($row['status'] == 'Kembali') ? 'success' : 'warning';
        $lateHtml = '';
        
        if ($row['status'] == 'Kembali' && ($row['late_days'] ?? 0) > 0) {
            $lateHtml = ' <br><small class="badge badge-danger text-white">Telat ' . $row['late_days'] . ' Hari</small>';
        } elseif ($row['status'] != 'Kembali') {
            $today = new DateTime();
            $today->setTime(0, 0, 0); // Clear time for accurate day comparison
            $rencana = $row['tgl_kembali_rencana'];
            if ($rencana instanceof DateTime) {
                $rencana_cmp = clone $rencana;
                $rencana_cmp->setTime(0, 0, 0);
                if ($today > $rencana_cmp) {
                    $diff = $today->diff($rencana_cmp);
                    if ($diff->days > 0) {
                        $lateHtml = ' <br><small class="badge badge-danger text-white">Telat ' . $diff->days . ' Hari</small>';
                    }
                }
            }
        }

        $data[] = [
            "no" => $no++,
            "id_peminjaman" => $row['id_peminjaman'],
            "nama_peminjam" => safe_json(htmlspecialchars($row['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8')),
            "tgl_pinjam" => ($row['tgl_pinjam'] instanceof DateTime) ? $row['tgl_pinjam']->format('Y-m-d') : '-',
            "tgl_kembali_rencana" => ($row['tgl_kembali_rencana'] instanceof DateTime) ? $row['tgl_kembali_rencana']->format('Y-m-d') : '-',
            "tgl_kembali" => ($row['tgl_kembali'] instanceof DateTime) ? $row['tgl_kembali']->format('Y-m-d') : '-',
            "status" => '<span class="badge badge-'.$statusBadge.'">'.safe_json($row['status'] ?? '-').'</span>' . $lateHtml,
            "aksi" => '<button class="btn btn-info btn-sm btn-detail-pinjam" data-id="'.$row['id_peminjaman'].'" title="Detail"><i class="fas fa-eye"></i></button> '.($row['status'] != 'Kembali' ? '<button class="btn btn-success btn-sm btn-kembali" data-id="'.$row['id_peminjaman'].'" title="Kembalikan"><i class="fas fa-undo"></i></button> <button class="btn btn-warning btn-sm btn-edit-pinjam" data-id="'.$row['id_peminjaman'].'" title="Edit"><i class="fas fa-edit"></i></button> ' : '').'<button class="btn btn-danger btn-sm btn-delete-pinjam" data-id="'.$row['id_peminjaman'].'" title="Hapus"><i class="fas fa-trash"></i></button>'
        ];
    }
}

$response = [
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($recordsFiltered),
    "data" => $data
];

$json = json_encode($response, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    // If encoding fails, try to encode again with cleaner data
    $response['data'] = array_map(function($row) {
        return array_map(function($val) {
            return is_string($val) ? mb_convert_encoding($val, 'UTF-8', 'UTF-8') : $val;
        }, $row);
    }, $response['data']);
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
}

if ($json === false) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "JSON encoding failed: " . json_last_error_msg()]);
} else {
    ob_clean();
    echo $json;
}
?>
