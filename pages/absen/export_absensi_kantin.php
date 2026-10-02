<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// SET TIMEZONE KE INDONESIA
date_default_timezone_set('Asia/Jakarta');

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Absensi_Kantin_" . date("Ymd_His") . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// === Ambil filter dari GET ===
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');
$mesin = $_GET['mesin'] ?? '';
$shift = $_GET['shift'] ?? '';

/**
 * Determine shift based on time
 */
function determineShift($waktu) {
    if ($waktu instanceof DateTime) {
        $time = $waktu->format('H:i:s');
    } else {
        $time = date('H:i:s', strtotime($waktu));
    }
    
    if ($time >= '11:30:00' && $time <= '13:30:00') {
        return 'Non Shift';
    } elseif ($time >= '01:00:00' && $time <= '04:00:00') {
        return 'Shift Malam';
    } elseif ($time >= '09:00:00' && $time <= '11:30:00') {
        return 'Shift Pagi';
    } elseif ($time >= '17:00:00' && $time <= '20:00:00') {
        return 'Shift Siang';
    } elseif (($time >= '04:00:00' && $time <= '09:00:00') || 
              ($time >= '14:00:00' && $time <= '17:00:00') || 
              ($time >= '20:00:00' || $time <= '01:00:00')) {
        return 'No All';
    } else {
        return 'Unknown';
    }
}

/**
 * Apply shift filter to SQL query
 */
function applyShiftFilter(&$sql, &$params, $shift, $tanggalAwal, $tanggalAkhir) {
    $shiftRanges = [
        'non_shift' => [
            'name' => 'Non Shift',
            'start' => '11:30:00',
            'end' => '13:30:00',
            'date_adjustment' => 0
        ],
        'shift_malam' => [
            'name' => 'Shift Malam',
            'start' => '01:00:00',
            'end' => '04:00:00',
            'date_adjustment' => 1
        ],
        'shift_pagi' => [
            'name' => 'Shift Pagi',
            'start' => '09:00:00',
            'end' => '11:30:00',
            'date_adjustment' => 0
        ],
        'shift_siang' => [
            'name' => 'Shift Siang',
            'start' => '17:00:00',
            'end' => '20:00:00',
            'date_adjustment' => 0
        ],
        'no_all' => [
            'name' => 'No All',
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

// === Query dasar ===
$sql = "SELECT
            a.waktu,  
            a.nama, 
            d.dept, 
            b.bagian, 
            s.subbag, 
            f.nama_mesin
        FROM
            dbo.log_absensi AS a
        LEFT JOIN dbo.m_fingerprint AS f ON a.mesin_id = f.id
        LEFT JOIN dbo.m_emp AS e ON a.user_id = e.id_emp
        LEFT JOIN dbo.m_dept AS d ON e.id_dept = d.id_dept
        LEFT JOIN dbo.m_bag AS b ON e.id_bag = b.id_bag
        LEFT JOIN dbo.m_subbag AS s ON e.id_subbag = s.id_subbag
        WHERE 1=1";

$params = [];

// Filter tanggal
if (!empty($tanggal_awal)) {
    $sql .= " AND CAST(a.waktu AS DATE) >= ?";
    $params[] = $tanggal_awal;
}

if (!empty($tanggal_akhir)) {
    $sql .= " AND CAST(a.waktu AS DATE) <= ?";
    $params[] = $tanggal_akhir;
}

// Filter mesin
if (!empty($mesin)) {
    $sql .= " AND f.id = ?";
    $params[] = $mesin;
}

// Filter shift
if (!empty($shift)) {
    applyShiftFilter($sql, $params, $shift, $tanggal_awal, $tanggal_akhir);
}

$sql .= " ORDER BY a.waktu ASC";

// === Eksekusi query ===
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo "<pre>Query gagal!\n";
    echo "SQL: " . $sql . "\n";
    echo "Params: " . print_r($params, true) . "\n";
    echo "Error: " . print_r(sqlsrv_errors(), true) . "</pre>";
    exit;
}

// === Kumpulkan data dan kelompokkan per shift ===
$dataPerShift = [
    'Shift Pagi' => [],
    'Shift Siang' => [],
    'Shift Malam' => [],
    'Non Shift' => [],
    'No All' => [],
    'Unknown' => []
];

$totalRecords = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format tanggal dan jam - KONVERSI KE TIMEZONE INDONESIA
    if ($row['waktu'] instanceof DateTime) {
        $waktu_obj = clone $row['waktu'];
        $waktu_obj->setTimezone(new DateTimeZone('Asia/Jakarta'));
        $tanggal = $waktu_obj->format('d-m-Y');
        $jam = $waktu_obj->format('H:i:s');
        $waktu_full = $waktu_obj->format('Y-m-d H:i:s');
    } else {
        $waktu_obj = DateTime::createFromFormat('Y-m-d H:i:s', $row['waktu'], new DateTimeZone('UTC'));
        if ($waktu_obj) {
            $waktu_obj->setTimezone(new DateTimeZone('Asia/Jakarta'));
            $tanggal = $waktu_obj->format('d-m-Y');
            $jam = $waktu_obj->format('H:i:s');
            $waktu_full = $waktu_obj->format('Y-m-d H:i:s');
        } else {
            $tanggal = $row['waktu'];
            $jam = '-';
            $waktu_full = $row['waktu'];
        }
    }
    
    // Tentukan shift
    $shift_name = determineShift($waktu_full);
    
    // Tambahkan data ke array shift yang sesuai
    $dataPerShift[$shift_name][] = [
        'tanggal' => $tanggal,
        'jam' => $jam,
        'nama' => $row['nama'],
        'dept' => $row['dept'] ?? '-',
        'bagian' => $row['bagian'] ?? '-',
        'subbag' => $row['subbag'] ?? '-',
        'mesin' => $row['nama_mesin'],
        'shift' => $shift_name
    ];
    
    $totalRecords++;
}

// === Header informasi filter ===
echo "<h2>DATA ABSENSI KANTIN</h2>";
echo "<table border='0' style='margin-bottom: 20px;'>";
echo "<tr><td><strong>Periode</strong></td><td>: " . date('d-m-Y', strtotime($tanggal_awal)) . " s/d " . date('d-m-Y', strtotime($tanggal_akhir)) . "</td></tr>";

// Tampilkan filter mesin jika dipilih
if (!empty($mesin)) {
    $mesinSql = "SELECT nama_mesin FROM dbo.m_fingerprint WHERE id = ?";
    $mesinStmt = sqlsrv_query($conn, $mesinSql, [$mesin]);
    if ($mesinStmt && $mesinRow = sqlsrv_fetch_array($mesinStmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr><td><strong>Mesin</strong></td><td>: " . htmlspecialchars($mesinRow['nama_mesin']) . "</td></tr>";
    }
    if ($mesinStmt) sqlsrv_free_stmt($mesinStmt);
}

// Tampilkan filter shift jika dipilih
if (!empty($shift)) {
    $shiftNames = [
        'non_shift' => 'Non Shift (11:30-13:30)',
        'shift_malam' => 'Shift Malam (01:00-04:00)',
        'shift_pagi' => 'Shift Pagi (09:00-11:30)',
        'shift_siang' => 'Shift Siang (17:00-20:00)',
        'no_all' => 'No All (04:00-09:00, 14:00-17:00, 20:00-01:00)'
    ];
    echo "<tr><td><strong>Shift</strong></td><td>: " . ($shiftNames[$shift] ?? $shift) . "</td></tr>";
}

// TAMPILKAN WAKTU EKSPOR DENGAN TIMEZONE INDONESIA
echo "<tr><td><strong>Tanggal Export</strong></td><td>: " . date('d-m-Y H:i:s') . " WIB</td></tr>";
echo "</table>";



// === CETAK DATA PER SHIFT ===
$noUrut = 1;

foreach ($dataPerShift as $shiftName => $dataShift) {
    if (count($dataShift) > 0) {
        echo "<h3>DATA ABSENSI - " . $shiftName . " </h3>";
        
        echo "<table border='1'>";
        echo "<thead>
                <tr style='background-color:#d9d9d9; font-weight:bold; text-align:center;'>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Nama Karyawan</th>
                    <th>Departemen</th>
                    <th>Bagian</th>
                    <th>Sub Bagian</th>
                    <th>Mesin</th>
                    <th>Shift</th>
                </tr>
              </thead>";
        echo "<tbody>";
        
        $noShift = 1;
        foreach ($dataShift as $data) {
            echo "<tr>";
            echo "<td style='text-align:center'>" . $noUrut++ . "</td>";
            echo "<td style='text-align:center'>" . $data['tanggal'] . "</td>";
            echo "<td style='text-align:center'>" . $data['jam'] . "</td>";
            echo "<td>" . htmlspecialchars($data['nama']) . "</td>";
            echo "<td>" . htmlspecialchars($data['dept']) . "</td>";
            echo "<td>" . htmlspecialchars($data['bagian']) . "</td>";
            echo "<td>" . htmlspecialchars($data['subbag']) . "</td>";
            echo "<td style='text-align:center'>" . htmlspecialchars($data['mesin']) . "</td>";
            echo "<td style='text-align:center'>" . $data['shift'] . "</td>";
            echo "</tr>";
            $noShift++;
        }
        
        echo "</tbody></table>";
        echo "<br>";
    }
}

// === Footer dengan total data ===
echo "<div style='margin-top: 20px; font-weight: bold;'>";
echo "Total Data: " . $totalRecords . " record(s)";
echo "</div>";

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>