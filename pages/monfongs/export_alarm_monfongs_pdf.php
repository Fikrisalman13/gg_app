<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
include '../../koneksi.php';
include '../../koneksi4.php';

// SET TIMEZONE INDONESIA SEBELUM APAPUN
date_default_timezone_set('Asia/Jakarta');

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// Ambil filter dari GET
$machine = $_GET['machine'] ?? 'MON4';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($start_date == '' || $end_date == '') {
    die("Parameter tanggal tidak valid!");
}

// Format tanggal dengan timezone Indonesia
$start_date_filter = str_replace("T", " ", $start_date) . ":00";
$end_date_filter = str_replace("T", " ", $end_date) . ":00";

// Mapping nama mesin
$machine_names = [
    'MON3' => 'Paddry 3',
    'MON2' => 'Paddry 4', 
    'MON4' => 'Paddry 5',
    'MON5' => 'Paddry 6'
];

$machine_name = $machine_names[$machine] ?? $machine;

// Query data alarm
$sqlData = "
    WITH SpeedData AS (
    SELECT
        a.LogTimeStamp,
        CASE 
            WHEN a.LogType_ID = '1311' THEN 'MON3'
            WHEN a.LogType_ID = '1212' THEN 'MON2'
            WHEN a.LogType_ID = '1401' THEN 'MON4'
            WHEN a.LogType_ID = '1501' THEN 'MON5'
        END AS Machine,
        CASE
            WHEN a.LogType_ID IN ('1311','1212') THEN ROUND(a.Value01 / 10, 0)
            WHEN a.LogType_ID IN ('1401','1501') THEN ROUND(a.Value37 / 10, 0)
        END AS Speed
    FROM dbo.logvaluefloat a
    WHERE 
        a.LogType_ID IN ('1311','1212','1401','1501')
)

SELECT
    CASE
        WHEN sd.Machine = 'MON3' THEN 'Paddry 3'
        WHEN sd.Machine = 'MON2' THEN 'Paddry 4'
        WHEN sd.Machine = 'MON4' THEN 'Paddry 5'
        WHEN sd.Machine = 'MON5' THEN 'Paddry 6'
        ELSE sd.Machine
    END AS MachineName,
    mp.LogTimeStamp,
    mp.AlarmNo,
    ma.Name AS AlarmName,
    sd.Speed
FROM SpeedData sd
JOIN dbo.machineprotocol mp
    ON mp.LogTimeStamp = sd.LogTimeStamp
    AND mp.Machine = sd.Machine
LEFT JOIN dbo.machgrpalarm ma
    ON mp.AlarmNo = ma.ID
WHERE
    sd.Machine IN (?)                         -- Parameter mesin MON2, MON3, MON4, MON5
    AND sd.LogTimeStamp BETWEEN ? AND ?
    AND sd.Speed > 0                          -- Mesin harus berjalan
    AND ma.ID IN (
        '215','117','2196','769','770','771','1151','1156','1269','1274','2191',
        '1105','1107','1176','1178','1180','1182','3156','3157','1104','1106',
        '1175','1177','1179','1181','3154','3155','979','983','1560','1564',
        '295','1380','967','975','1569','1574','968','976','1570','1575','964',
        '1540','4800','1540','1881'
    )
ORDER BY
    MachineName ASC,
    mp.LogTimeStamp ASC;

";

$stmtData = sqlsrv_prepare($conn4, $sqlData, [$machine, $start_date_filter, $end_date_filter]);
$data = [];
if ($stmtData) {
    sqlsrv_execute($stmtData);
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $data[] = $row;
    }
} else {
    die(print_r(sqlsrv_errors(), true));
}

// ================= HITUNG SUMMARY FREKUENSI ALARM =================
$alarm_summary = [];
$total_alarms = 0;

if (!empty($data)) {
    foreach ($data as $row) {
        $alarm_no = $row['AlarmNo'];
        $alarm_name = $row['AlarmName'];
        
        if (!isset($alarm_summary[$alarm_no])) {
            $alarm_summary[$alarm_no] = [
                'AlarmNo' => $alarm_no,
                'AlarmName' => $alarm_name,
                'Count' => 0,
                'FirstOccurrence' => null,
                'LastOccurrence' => null
            ];
        }
        
        $alarm_summary[$alarm_no]['Count']++;
        
        // Konversi timestamp ke DateTime
        if ($row['LogTimeStamp'] instanceof DateTime) {
            $timestamp = $row['LogTimeStamp'];
        } else {
            $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $row['LogTimeStamp'], new DateTimeZone('Asia/Jakarta'));
        }
        
        // Update first and last occurrence
        if ($alarm_summary[$alarm_no]['FirstOccurrence'] === null || $timestamp < $alarm_summary[$alarm_no]['FirstOccurrence']) {
            $alarm_summary[$alarm_no]['FirstOccurrence'] = $timestamp;
        }
        if ($alarm_summary[$alarm_no]['LastOccurrence'] === null || $timestamp > $alarm_summary[$alarm_no]['LastOccurrence']) {
            $alarm_summary[$alarm_no]['LastOccurrence'] = $timestamp;
        }
        
        $total_alarms++;
    }
    
    // Hitung persentase frekuensi untuk setiap alarm
    foreach ($alarm_summary as $key => $summary) {
        $alarm_summary[$key]['FrequencyPercentage'] = $total_alarms > 0 ? round(($summary['Count'] / $total_alarms) * 100, 1) : 0;
    }
}

// ================= HITUNG DISTRIBUSI WAKTU OPERASI (NORMAL/TIDAK NORMAL) =================
$start_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $start_date_filter, new DateTimeZone('Asia/Jakarta'));
$end_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $end_date_filter, new DateTimeZone('Asia/Jakarta'));

if (!$start_datetime || !$end_datetime) {
    die("Format tanggal tidak valid!");
}

$total_detik = $end_datetime->getTimestamp() - $start_datetime->getTimestamp();

// Inisialisasi variabel
$detik_dengan_alarm = 0;
$periode_alarm = [];

// Metode Interval Merging (2 menit threshold + 1 menit buffer)
if (!empty($data)) {
    // 1. Sort data by LogTimeStamp
    usort($data, function($a, $b) {
        $ta = $a['LogTimeStamp'] instanceof DateTime ? 
              $a['LogTimeStamp']->getTimestamp() : 
              strtotime($a['LogTimeStamp'] . ' Asia/Jakarta');
        $tb = $b['LogTimeStamp'] instanceof DateTime ? 
              $b['LogTimeStamp']->getTimestamp() : 
              strtotime($b['LogTimeStamp'] . ' Asia/Jakarta');
        return $ta <=> $tb;
    });

    // 2. Convert to DateTime objects
    $alarm_times = [];
    foreach ($data as $row) {
        if ($row['LogTimeStamp'] instanceof DateTime) {
            $dt = clone $row['LogTimeStamp'];
            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
        } else {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['LogTimeStamp'], new DateTimeZone('Asia/Jakarta'));
            if (!$dt) {
                $dt = new DateTime($row['LogTimeStamp'], new DateTimeZone('Asia/Jakarta'));
            }
        }
        $alarm_times[] = $dt;
    }

    if (count($alarm_times) > 0) {
        // 3. Merge intervals dengan threshold 2 menit (120 detik)
        $threshold_seconds = 2 * 60; // 2 menit
        $buffer_seconds = 60;        // 1 menit recovery buffer
        
        $merged_intervals = [];
        $cur_start = null;
        $cur_end = null;

        foreach ($alarm_times as $dt) {
            if ($cur_start === null) {
                $cur_start = clone $dt;
                $cur_end = clone $dt;
                continue;
            }

            $diff = $dt->getTimestamp() - $cur_end->getTimestamp();

            if ($diff <= $threshold_seconds) {
                // Masih dalam interval yang sama
                $cur_end = clone $dt;
            } else {
                // Interval baru
                $merged_intervals[] = [$cur_start, $cur_end];
                $cur_start = clone $dt;
                $cur_end = clone $dt;
            }
        }

        // Simpan interval terakhir
        if ($cur_start !== null) {
            $merged_intervals[] = [$cur_start, $cur_end];
        }

        // 4. Tambahkan buffer dan hitung durasi
        foreach ($merged_intervals as [$mulai, $selesai]) {
            // Tambahkan buffer 1 menit
            $selesai_buffer = clone $selesai;
            $selesai_buffer->modify('+1 minute');

            // Clamp ke periode monitoring
            if ($mulai < $start_datetime) {
                $mulai_clamped = clone $start_datetime;
            } else {
                $mulai_clamped = clone $mulai;
            }

            if ($selesai_buffer > $end_datetime) {
                $selesai_clamped = clone $end_datetime;
            } else {
                $selesai_clamped = clone $selesai_buffer;
            }

            $durasi = $selesai_clamped->getTimestamp() - $mulai_clamped->getTimestamp();

            if ($durasi > 0) {
                $periode_alarm[] = [
                    'mulai' => $mulai_clamped->format('Y-m-d H:i:s'),
                    'selesai' => $selesai_clamped->format('Y-m-d H:i:s'),
                    'durasi' => $durasi
                ];
                $detik_dengan_alarm += $durasi;
            }
        }

        // 5. Pastikan tidak melebihi total waktu
        $detik_dengan_alarm = min($detik_dengan_alarm, $total_detik);
    }
} else {
    $detik_dengan_alarm = 0;
}

// Hitung waktu normal
$detik_normal = max(0, $total_detik - $detik_dengan_alarm);

// Konversi ke menit
$menit_normal = $detik_normal / 60;
$menit_tidak_normal = $detik_dengan_alarm / 60;

// Hitung persentase distribusi waktu
$persen_normal = $total_detik > 0 ? round(($detik_normal / $total_detik) * 100, 1) : 0;
$persen_tidak_normal = $total_detik > 0 ? round(($detik_dengan_alarm / $total_detik) * 100, 1) : 0;

// Koreksi rounding
$total_persen = $persen_normal + $persen_tidak_normal;
if ($total_persen !== 100) {
    $persen_tidak_normal = 100 - $persen_normal;
}

// ================= PERBAIKAN: HITUNG & URUTKAN KONTRIBUSI DOWNTIME PER ALARM =================
if (!empty($alarm_summary) && $total_alarms > 0 && $detik_dengan_alarm > 0) {
    // Hitung kontribusi downtime untuk setiap alarm
    foreach ($alarm_summary as &$summary) {
        // Kontribusi downtime = (frekuensi alarm / total alarm) * total downtime
        $contribution_seconds = ($summary['Count'] / $total_alarms) * $detik_dengan_alarm;
        $contribution_percent = ($contribution_seconds / $total_detik) * 100;
        
        $summary['DowntimeSeconds'] = round($contribution_seconds);
        $summary['DowntimePercentage'] = round($contribution_percent, 1);
        $summary['AvgDowntimePerOccurrence'] = $summary['Count'] > 0 ? 
            round($contribution_seconds / $summary['Count']) : 0;
    }
    
    // PERBAIKAN: URUTKAN DARI YANG TERTINGGI berdasarkan kontribusi downtime
    usort($alarm_summary, function($a, $b) {
        // Prioritas 1: % Downtime tertinggi
        if ($b['DowntimePercentage'] != $a['DowntimePercentage']) {
            return $b['DowntimePercentage'] <=> $a['DowntimePercentage'];
        }
        
        // Prioritas 2: Frekuensi tertinggi (jika % downtime sama)
        if ($b['Count'] != $a['Count']) {
            return $b['Count'] <=> $a['Count'];
        }
        
        // Prioritas 3: Avg downtime tertinggi
        return $b['AvgDowntimePerOccurrence'] <=> $a['AvgDowntimePerOccurrence'];
    });
    
    // Validasi: total % downtime harus mendekati % tidak normal
    $total_downtime_percent = array_sum(array_column($alarm_summary, 'DowntimePercentage'));
    $deviation = abs($total_downtime_percent - $persen_tidak_normal);
    
    // Jika deviasi > 0.5%, lakukan koreksi
    if ($deviation > 0.5) {
        $correction_factor = $persen_tidak_normal / $total_downtime_percent;
        foreach ($alarm_summary as &$summary) {
            $summary['DowntimePercentage'] = round($summary['DowntimePercentage'] * $correction_factor, 1);
        }
    }
}

// ================= FUNGSI FORMAT WAKTU =================
function formatJamMenit($menit) {
    $jam = floor($menit / 60);
    $sisa_menit = round($menit % 60);
    
    if ($jam > 0 && $sisa_menit > 0) {
        return $jam . ' jam ' . $sisa_menit . ' menit';
    } elseif ($jam > 0) {
        return $jam . ' jam';
    } else {
        return $sisa_menit . ' menit';
    }
}

function formatDetikKeMenit($detik) {
    if ($detik <= 0) return '0m';
    
    $menit = $detik / 60;
    if ($menit >= 60) {
        $jam = floor($menit / 60);
        $sisa_menit = round($menit % 60);
        return $jam . 'j ' . $sisa_menit . 'm';
    }
    return round($menit) . 'm';
}

$jam_normal_formatted = formatJamMenit($menit_normal);
$jam_tidak_normal_formatted = formatJamMenit($menit_tidak_normal);
$total_waktu_formatted = formatJamMenit($total_detik / 60);

// Hitung kepadatan alarm
$alarm_density = $total_detik > 0 ? 
    round(($total_alarms / ($total_detik / 3600)), 2) : 0;

// ================= PDF Generator ==================

// Hapus semua output buffer sebelum membuat PDF
while (ob_get_level()) {
    ob_end_clean();
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GG App');
$pdf->SetAuthor('SUM IT Kabag');
$pdf->SetTitle('Data Alarm Mesin Monfongs');
$pdf->SetHeaderData('', 0, 'Data Alarm Mesin Monfongs - ' . $machine_name, 
    'Periode: ' . $start_date . ' s/d ' . $end_date);

// Header & Footer
$pdf->setHeaderFont(['helvetica', '', 10]);
$pdf->setFooterFont(['helvetica', '', 8]);
$pdf->SetMargins(10, 25, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

// ================= Informasi Laporan =================
$info = '
<div style="border:1px solid #17a2b8; background-color:#e9f7fd; padding:8px; margin-bottom:10px;">
    <strong>Informasi Laporan:</strong><br>
    <table cellpadding="3">
        <tr>
            <td><strong>Mesin</strong></td>
            <td>: ' . $machine_name . ' (' . $machine . ')</td>
        </tr>
        <tr>
            <td><strong>Periode</strong></td>
            <td>: ' . $start_date . ' s/d ' . $end_date . '</td>
        </tr>
        <tr>
            <td><strong>Total Kejadian Alarm</strong></td>
            <td>: ' . number_format($total_alarms) . ' kali</td>
        </tr>
        <tr>
            <td><strong>Kepadatan Alarm</strong></td>
            <td>: ' . $alarm_density . ' alarm/jam</td>
        </tr>
        <tr>
            <td><strong>Downtime Mesin</strong></td>
            <td>: ' . $jam_tidak_normal_formatted . ' (' . $persen_tidak_normal . '%)</td>
        </tr>
        <tr>
            <td><strong>Waktu Generate</strong></td>
            <td>: ' . date('Y-m-d H:i:s') . ' WIB</td>
        </tr>
    </table>
</div>';
$pdf->writeHTML($info, true, false, true, false, '');

// ================= PIE CHART DISTRIBUSI WAKTU OPERASI =================
$y_before_chart = $pdf->GetY();

// Buat container untuk pie chart
$pdf->SetFillColor(248, 249, 250);
$pdf->SetDrawColor(40, 167, 69);
$pdf->SetLineWidth(1);
$pdf->Rect(10, $y_before_chart, 277, 80, 'DF');
$pdf->SetLineWidth(0.3);

// Judul section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(40, 167, 69);
$pdf->SetXY(10, $y_before_chart + 5);
$pdf->Cell(277, 8, 'Summary Distribusi Waktu Operasi', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);

// Hitung koordinat untuk pie chart
$pie_center_x = 60;
$pie_center_y = $y_before_chart + 45;
$pie_radius = 25;

// Gambar Pie Chart
if ($persen_normal > 0) {
    $pdf->SetFillColor(40, 167, 69);
    $pdf->PieSector($pie_center_x, $pie_center_y, $pie_radius, 0, $persen_normal * 3.6, 'F', false, 0);
}

if ($persen_tidak_normal > 0) {
    $pdf->SetFillColor(220, 53, 69);
    $pdf->PieSector($pie_center_x, $pie_center_y, $pie_radius, $persen_normal * 3.6, 360, 'F', false, 0);
}

// Gambar lingkaran luar
$pdf->SetDrawColor(0, 0, 0);
$pdf->Circle($pie_center_x, $pie_center_y, $pie_radius, 0, 360, 'D');

// Teks di tengah pie chart
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($pie_center_x - 15, $pie_center_y - 4);
$pdf->Cell(30, 5, $persen_normal . '%', 0, 0, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($pie_center_x - 15, $pie_center_y + 4);
$pdf->Cell(30, 5, 'Normal', 0, 0, 'C');

// Legend
$legend_x = 100;
$legend_y = $y_before_chart + 25;

$pdf->SetFillColor(40, 167, 69);
$pdf->Rect($legend_x, $legend_y, 6, 6, 'F');
$pdf->SetXY($legend_x + 8, $legend_y);
$pdf->Cell(40, 6, 'Normal: ' . $persen_normal . '%', 0, 0, 'L');

$pdf->SetFillColor(220, 53, 69);
$pdf->Rect($legend_x, $legend_y + 8, 6, 6, 'F');
$pdf->SetXY($legend_x + 8, $legend_y + 8);
$pdf->Cell(40, 6, 'Tidak Normal: ' . $persen_tidak_normal . '%', 0, 0, 'L');

// Tabel detail waktu
$table_x = 160;
$table_y = $y_before_chart + 20;

// Header tabel
$pdf->SetFillColor(233, 236, 239);
$pdf->SetDrawColor(222, 226, 230);
$pdf->SetFont('helvetica', 'B', 9);

$pdf->SetXY($table_x, $table_y);
$pdf->Cell(50, 8, 'Status Operasi', 1, 0, 'C', 1);
$pdf->Cell(25, 8, '%', 1, 0, 'C', 1);
$pdf->Cell(35, 8, 'Waktu', 1, 1, 'C', 1);

// Data Normal
$pdf->SetFont('helvetica', '', 9);
$pdf->SetX($table_x);
$pdf->Cell(50, 8, 'Normal (tanpa alarm)', 1, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(40, 167, 69);
$pdf->Cell(25, 8, $persen_normal . '%', 1, 0, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(35, 8, $jam_normal_formatted, 1, 1, 'C');

// Data Tidak Normal
$pdf->SetFont('helvetica', '', 9);
$pdf->SetX($table_x);
$pdf->Cell(50, 8, 'Tidak Normal (ada alarm)', 1, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(220, 53, 69);
$pdf->Cell(25, 8, $persen_tidak_normal . '%', 1, 0, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(35, 8, $jam_tidak_normal_formatted, 1, 1, 'C');

// Total
$pdf->SetFillColor(248, 249, 250);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetX($table_x);
$pdf->Cell(50, 8, 'Total Monitoring', 1, 0, 'L', 1);
$pdf->Cell(25, 8, '100%', 1, 0, 'C', 1);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(35, 8, $total_waktu_formatted, 1, 1, 'C', 1);

// Box analisis
$pdf->SetFillColor(231, 243, 255);
$pdf->SetDrawColor(200, 220, 240);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(100, $y_before_chart + 60);
$pdf->MultiCell(177, 15, 
    'Analisis: Mesin mengalami downtime ' . $persen_tidak_normal . '% (' . $jam_tidak_normal_formatted . ') ' .
    'dari total waktu operasi. Alarm utama penyebab downtime ditampilkan di tabel berikut (diurutkan dari kontribusi tertinggi).', 
    1, 'L', true);

// Update posisi Y setelah chart
$pdf->SetY($y_before_chart + 85);

// ================= SUMMARY FREKUENSI ALARM - DIURUTKAN DARI TERTINGGI =================
if (!empty($alarm_summary)) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(0, 0, 128);
    $pdf->Cell(0, 8, 'Summary Frekuensi Alarm dan Kontribusi Downtime:', 0, 1, 'L');
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(128, 0, 0);
    $pdf->Cell(0, 6, '*Diurutkan berdasarkan kontribusi downtime tertinggi ke terendah*', 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    
    $summary_html = '<table border="1" cellpadding="4" style="font-size:8pt; width:100%; border-collapse:collapse;">
    <thead>
    <tr style="background-color:#343a40; color:white; font-weight:bold; text-align:center;">
        <th width="5%" style="border:1px solid #ddd;">Rank</th>
        <th width="12%" style="border:1px solid #ddd;">Kode Alarm</th>
        <th width="28%" style="border:1px solid #ddd;">Nama Alarm</th>
        <th width="8%" style="border:1px solid #ddd;">Jumlah</th>
        <th width="8%" style="border:1px solid #ddd;">% Frek</th>
        <th width="12%" style="border:1px solid #ddd;">Downtime</th>
        <th width="9%" style="border:1px solid #ddd;">% Down</th>
        <th width="10%" style="border:1px solid #ddd;">Rata2/ Alarm</th>
        <th width="8%" style="border:1px solid #ddd;">Prioritas</th>
    </tr>
    </thead>
    <tbody>';
    
    $summary_nomor = 1;
    $total_downtime_percent_calc = 0;
    $total_downtime_seconds_calc = 0;
    
    foreach ($alarm_summary as $summary) {
        $kode_alarm = str_pad($summary['AlarmNo'], 4, '0', STR_PAD_LEFT);
        $nama_alarm = wordwrap(htmlspecialchars($summary['AlarmName']), 35, "<br>", true);
        
        // Akumulasi untuk validasi
        $total_downtime_percent_calc += $summary['DowntimePercentage'] ?? 0;
        $total_downtime_seconds_calc += $summary['DowntimeSeconds'] ?? 0;
        
        // Tentukan warna dan prioritas berdasarkan ranking
        $priority_color = '#6c757d'; // Default gray
        $row_bg_color = '#ffffff';
        $priority_text = 'Rendah';
        
        if ($summary_nomor == 1) {
            // Ranking 1 - Paling tinggi
            $priority_color = '#dc3545'; // Red
            $row_bg_color = '#ffebee'; // Light red
            $priority_text = 'Tinggi';
        } elseif ($summary_nomor <= 3) {
            // Ranking 2-3
            $priority_color = '#fd7e14'; // Orange
            $row_bg_color = ($summary_nomor % 2 == 0) ? '#fff3e0' : '#fff8e1';
            $priority_text = 'Tinggi';
        } elseif ($summary_nomor <= 5) {
            // Ranking 4-5
            $priority_color = '#ffc107'; // Yellow
            $row_bg_color = ($summary_nomor % 2 == 0) ? '#fffde7' : '#ffffe0';
            $priority_text = 'Sedang';
        } elseif ($summary_nomor <= 10) {
            // Ranking 6-10
            $priority_color = '#17a2b8'; // Teal
            $row_bg_color = ($summary_nomor % 2 == 0) ? '#e3f2fd' : '#e8f4fd';
            $priority_text = 'Sedang';
        } else {
            // Ranking >10
            $priority_color = '#28a745'; // Green
            $row_bg_color = ($summary_nomor % 2 == 0) ? '#f9f9f9' : '#ffffff';
            $priority_text = 'Rendah';
        }
        
        // Highlight downtime persentase berdasarkan ranking
        $downtime_cell_style = 'border:1px solid #ddd; text-align:center; font-weight:bold;';
        if ($summary_nomor <= 3) {
            $downtime_cell_style .= ' background-color:#ffebee;';
        } elseif ($summary_nomor <= 5) {
            $downtime_cell_style .= ' background-color:#fff3e0;';
        }
        
        $summary_html .= '<tr style="background-color:' . $row_bg_color . ';">
            <td width="5%" style="border:1px solid #ddd; text-align:center; font-weight:bold; color:' . $priority_color . ';">' . $summary_nomor . '</td>
            <td width="12%" style="border:1px solid #ddd; text-align:center; font-weight:bold; color:' . $priority_color . ';">' . $kode_alarm . '</td>
            <td width="28%" style="border:1px solid #ddd; text-align:left; padding-left:8px;">' . $nama_alarm . '</td>
            <td width="8%" style="border:1px solid #ddd; text-align:center; font-weight:bold;">' . number_format($summary['Count']) . '</td>
            <td width="8%" style="border:1px solid #ddd; text-align:center; color:#6c757d;">' . ($summary['FrequencyPercentage'] ?? 0) . '%</td>
            <td width="12%" style="border:1px solid #ddd; text-align:center; color:#6c757d;">' . formatDetikKeMenit($summary['DowntimeSeconds'] ?? 0) . '</td>
            <td width="9%" style="' . $downtime_cell_style . ' color:' . $priority_color . ';">' . ($summary['DowntimePercentage'] ?? 0) . '%</td>
            <td width="10%" style="border:1px solid #ddd; text-align:center; color:#6c757d;">' . ($summary['AvgDowntimePerOccurrence'] ?? 0) . ' detik</td>
            <td width="8%" style="border:1px solid #ddd; text-align:center; font-weight:bold; color:' . $priority_color . ';">' . $priority_text . '</td>
        </tr>';
        $summary_nomor++;
    }
    
    // Validasi konsistensi
    $downtime_validation = '';
    $deviation_percent = abs($total_downtime_percent_calc - $persen_tidak_normal);
    $deviation_seconds = abs($total_downtime_seconds_calc - $detik_dengan_alarm);
    
    if ($deviation_percent > 0.5 || $deviation_seconds > 60) {
        $downtime_validation = '<span style="color:#dc3545;"> (Deviasi: ' . round($deviation_percent, 1) . '%)</span>';
    }
    
    // Total row dengan highlight
    $summary_html .= '
        <tr style="background-color:#212529; color:white; font-weight:bold;">
            <td colspan="3" style="border:1px solid #ddd; text-align:right; padding-right:10px;">TOTAL / RATA-RATA</td>
            <td style="border:1px solid #ddd; text-align:center; background-color:#495057;">' . number_format($total_alarms) . '</td>
            <td style="border:1px solid #ddd; text-align:center; background-color:#495057;">100%</td>
            <td style="border:1px solid #ddd; text-align:center; background-color:#495057;">' . $jam_tidak_normal_formatted . '</td>
            <td style="border:1px solid #ddd; text-align:center; background-color:#495057; color:#ff6b6b;">' . $persen_tidak_normal . '%' . $downtime_validation . '</td>
            <td style="border:1px solid #ddd; text-align:center; background-color:#495057;">' . ($total_alarms > 0 ? round($detik_dengan_alarm / $total_alarms) : 0) . ' detik</td>
            <td style="border:1px solid #ddd; text-align:center; background-color:#495057;">-</td>
        </tr>
        </tbody>
        </table>';
    
    $pdf->writeHTML($summary_html, true, false, true, false, '');
    
    // Keterangan ranking
    $keterangan = '
    <div style="font-size:7pt; color:#666; margin-top:5px; border-left:3px solid #dc3545; padding-left:5px;">
        <strong>KETERANGAN RANKING:</strong><br>
        <span style="color:#dc3545;">■ Ranking 1</span>: Alarm dengan kontribusi downtime tertinggi (prioritas perbaikan utama)<br>
        <span style="color:#fd7e14;">■ Ranking 2-3</span>: Kontributor downtime signifikan<br>
        <span style="color:#ffc107;">■ Ranking 4-5</span>: Perlu monitoring<br>
        <span style="color:#17a2b8;">■ Ranking 6-10</span>: Kontribusi sedang<br>
        <span style="color:#28a745;">■ Ranking >10</span>: Kontribusi rendah
    </div>';
    $pdf->writeHTML($keterangan, true, false, true, false, '');
    
    $pdf->Ln(5);
}

// ================= DETAIL DATA ALARM =================
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Detail Data Alarm (Chronological Order):', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);

$html = '<table border="1" cellpadding="4" style="font-size:8pt; width:100%; border-collapse:collapse;">
<thead>
<tr style="background-color:#f2f2f2; font-weight:bold; text-align:center;">
    <th width="5%" style="border:1px solid #ddd;">No</th>
    <th width="15%" style="border:1px solid #ddd;">Nama Mesin</th>
    <th width="20%" style="border:1px solid #ddd;">Waktu Alarm</th>
    <th width="15%" style="border:1px solid #ddd;">Kode Alarm</th>
    <th width="45%" style="border:1px solid #ddd;">Nama Alarm</th>
</tr>
</thead>
<tbody>';

if (!empty($data)) {
    $nomor = 1;
    foreach ($data as $row) {
        // Format waktu
        if ($row['LogTimeStamp'] instanceof DateTime) {
            $waktu = $row['LogTimeStamp']->format('Y-m-d H:i:s');
        } else {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['LogTimeStamp'], new DateTimeZone('Asia/Jakarta'));
            $waktu = $dt ? $dt->format('Y-m-d H:i:s') : $row['LogTimeStamp'];
        }
        
        $kode_alarm = str_pad($row['AlarmNo'], 4, '0', STR_PAD_LEFT);
        $nama_alarm = wordwrap(htmlspecialchars($row['AlarmName']), 45, "<br>", true);
        
        // Cari ranking alarm ini
        $ranking = 0;
        foreach ($alarm_summary as $index => $alarm) {
            if ($alarm['AlarmNo'] == $row['AlarmNo']) {
                $ranking = $index + 1;
                break;
            }
        }
        
        // Warna baris berdasarkan ranking
        $row_color = '#ffffff';
        if ($ranking == 1) {
            $row_color = '#ffebee'; // Red for rank 1
        } elseif ($ranking <= 3) {
            $row_color = '#fff3e0'; // Orange for rank 2-3
        } elseif ($ranking <= 5) {
            $row_color = '#fffde7'; // Yellow for rank 4-5
        } elseif ($ranking <= 10) {
            $row_color = '#e3f2fd'; // Blue for rank 6-10
        }
        
        $html .= '<tr style="background-color:' . $row_color . '">
            <td width="5%" style="border:1px solid #ddd; text-align:center; font-weight:bold;">' . $nomor . '</td>
            <td width="15%" style="border:1px solid #ddd; text-align:center;">' . htmlspecialchars($row['MachineName']) . '</td>
            <td width="20%" style="border:1px solid #ddd; text-align:center;">' . htmlspecialchars($waktu) . '</td>
            <td width="15%" style="border:1px solid #ddd; text-align:center; font-weight:bold;">' . $kode_alarm . '</td>
            <td width="45%" style="border:1px solid #ddd; text-align:left; padding-left:8px;">' . $nama_alarm . '</td>
        </tr>';
        $nomor++;
    }
} else {
    $html .= '<tr>
        <td colspan="5" align="center" style="border:1px solid #ddd; padding:15px;">
            <strong>Tidak ada data alarm untuk periode yang dipilih</strong>
        </td>
    </tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, true, false, '');

// ================= KESIMPULAN DAN PRIORITAS PERBAIKAN =================
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 8, 'Prioritas Perbaikan Berdasarkan Analisis Downtime:', 0, 1, 'L');

// Identifikasi alarm dengan kontribusi tinggi
$top_downtime_alarms = [];
$total_downtime_top_5 = 0;
$top_5_count = min(5, count($alarm_summary));

for ($i = 0; $i < $top_5_count; $i++) {
    if (isset($alarm_summary[$i])) {
        $alarm = $alarm_summary[$i];
        $top_downtime_alarms[] = [
            'kode' => str_pad($alarm['AlarmNo'], 4, '0', STR_PAD_LEFT),
            'nama' => $alarm['AlarmName'],
            'downtime' => $alarm['DowntimePercentage'] ?? 0,
            'frekuensi' => $alarm['Count']
        ];
        $total_downtime_top_5 += $alarm['DowntimePercentage'] ?? 0;
    }
}

$kesimpulan = '
<div style="border:1px solid #6c757d; background-color:#f8f9fa; padding:10px; font-size:8pt;">
    <strong>KESIMPULAN UTAMA:</strong><br>
    1. <strong>Availability Mesin</strong>: ' . $persen_normal . '% (Normal: ' . $jam_normal_formatted . ')<br>
    2. <strong>Total Downtime</strong>: ' . $persen_tidak_normal . '% (' . $jam_tidak_normal_formatted . ') dari ' . number_format($total_alarms) . ' kejadian alarm<br>
    3. <strong>Kepadatan Alarm</strong>: ' . $alarm_density . ' alarm per jam
    
    <br><br>
    <strong>TOP 5 ALARM PENYEBAB DOWNTIME (' . round($total_downtime_top_5, 1) . '% dari total downtime):</strong><br>';

foreach ($top_downtime_alarms as $index => $alarm) {
    $kesimpulan .= ($index + 1) . '. <strong>Alarm ' . $alarm['kode'] . '</strong>: ' . 
                  $alarm['nama'] . ' (' . $alarm['downtime'] . '% downtime, ' . 
                  number_format($alarm['frekuensi']) . 'x)<br>';
}

$kesimpulan .= '
    <br>
    <strong>REKOMENDASI PRIORITAS PERBAIKAN:</strong><br>
    1. <span style="color:#dc3545;">FOKUS PADA ALARM RANKING 1-3</span>: ' . 
    (isset($top_downtime_alarms[0]) ? 'Alarm ' . $top_downtime_alarms[0]['kode'] : 'Tidak ada') . 
    ' memberikan kontribusi downtime tertinggi<br>
    2. Analisis root cause untuk alarm dengan frekuensi tinggi<br>
    3. Implementasi preventive maintenance schedule<br>
    4. Monitoring trend alarm untuk prediksi failure
</div>';

$pdf->writeHTML($kesimpulan, true, false, true, false, '');

// ================= CATATAN KAKI =================
$footer = '
<div style="text-align:center; font-size:7pt; color:#666; margin-top:10px;">
    <hr style="border:0.5px solid #ccc;">
    <strong>CATATAN PERHITUNGAN:</strong><br>
    1. Downtime dihitung dengan metode interval merging (threshold 2 menit + buffer 1 menit)<br>
    2. Kontribusi downtime per alarm = (frekuensi alarm / total alarm) × total downtime<br>
    3. Ranking berdasarkan kontribusi downtime tertinggi ke terendah<br>
    4. Dokumen dihasilkan otomatis - Sistem Monitoring GG App (' . date('Y-m-d H:i:s') . ' WIB)
</div>';
$pdf->writeHTML($footer, true, false, true, false, '');

// ================= OUTPUT PDF =================
$machine_name_filename = str_replace(' ', '', $machine_name);
$tarikan_date = DateTime::createFromFormat('Y-m-d\TH:i', $start_date);
if (!$tarikan_date) {
    $tarikan_date = DateTime::createFromFormat('Y-m-d H:i:s', $start_date_filter);
}
if (!$tarikan_date) {
    $tarikan_date = new DateTime();
}

$date_formatted = $tarikan_date->format('dmY');
$filename = 'Data_Alarm_' . $machine_name_filename . '_' . $date_formatted . '.pdf';
$pdf->Output($filename, 'I');

exit;
?>