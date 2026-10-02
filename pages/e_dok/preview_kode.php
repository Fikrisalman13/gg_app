<?php
include '../../koneksi.php';

if (!$conn) {
    die(json_encode(['error' => 'Database connection failed']));
}

$format = $_POST['format'] ?? 'lengkap';
$id_kode_dok = $_POST['id_kode_dok'] ?? null;

if (!$id_kode_dok) {
    die(json_encode(['preview' => 'SUM.XX.']));
}

// Fungsi untuk ambil next sequence number berdasarkan format
function getNextSequenceNumber($conn, $id_kode_dok, $id_dept = null, $id_subbag = null, $format = 'lengkap') {
    if ($format === 'simple') {
        $sql = "SELECT CAST(RIGHT(kode_dok_seq, 3) AS INT) AS seq
                FROM dbo.dokumen
                WHERE id_kode_dok = ? AND format_kode = 'simple'
                ORDER BY seq ASC";
        $params = [$id_kode_dok];
    } else {
        $sql = "SELECT CAST(RIGHT(kode_dok_seq, 3) AS INT) AS seq
                FROM dbo.dokumen
                WHERE id_kode_dok = ? AND id_dept = ? AND id_subbag = ? AND format_kode = 'lengkap'
                ORDER BY seq ASC";
        $params = [$id_kode_dok, $id_dept, $id_subbag];
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $usedSeq = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($row['seq'])) {
                $usedSeq[] = (int)$row['seq'];
            }
        }
    }
    sqlsrv_free_stmt($stmt);

    if (empty($usedSeq)) {
        return str_pad(1, 3, '0', STR_PAD_LEFT);
    }

    sort($usedSeq);
    $maxSeq = max($usedSeq);
    
    for ($i = 1; $i <= $maxSeq + 1; $i++) {
        if (!in_array($i, $usedSeq)) {
            return str_pad($i, 3, '0', STR_PAD_LEFT);
        }
    }
}

// Prefix
$prefix = "SUM";

// Kode dokumen
$sql = "SELECT kode_dok FROM dbo.m_kode_dok WHERE id_kode_dok = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_kode_dok]);
$kode_dok = 'XX';
if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $kode_dok = $row['kode_dok'];
}
sqlsrv_free_stmt($stmt);

if ($format === 'simple') {
    $sequenceNumber = getNextSequenceNumber($conn, $id_kode_dok, null, null, 'simple');
    $preview = $prefix . '.' . $kode_dok . '.' . $sequenceNumber;
} else {
    $id_dept = $_POST['id_dept'] ?? null;
    $id_subbag = $_POST['id_subbag'] ?? null;
    
    if (!$id_dept || !$id_subbag) {
        die(json_encode(['preview' => 'SUM-XX-XX-XX-']));
    }
    
    $sequenceNumber = getNextSequenceNumber($conn, $id_kode_dok, $id_dept, $id_subbag, 'lengkap');
    
    // Kode dept
    $sql = "SELECT sn_dept FROM dbo.m_dept WHERE id_dept = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_dept]);
    $sn_dept = 'XX';
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $sn_dept = $row['sn_dept'];
    }
    sqlsrv_free_stmt($stmt);
    
    // Kode subbag
    $sql = "SELECT sn_subbag FROM dbo.m_subbag WHERE id_subbag = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_subbag]);
    $sn_subbag = 'XX';
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $sn_subbag = $row['sn_subbag'];
    }
    sqlsrv_free_stmt($stmt);
    
    $preview = $prefix . '-' . $kode_dok . '-' . $sn_dept . '-' . $sn_subbag . '-' . $sequenceNumber;
}

echo json_encode([
    'preview' => $preview,
    'seq' => $sequenceNumber
]);
?>