<?php 
// gg_app/pages/rute/generate_pdf.php
declare(strict_types=1);

// ====== DEBUG SWITCH ======
$DEBUG = isset($_GET['debug']);
if ($DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

ob_start();
ini_set('log_errors', '1');

session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';

date_default_timezone_set('Asia/Jakarta');

// ===== Auth =====
if (!isset($_SESSION['UserName'])) {
    header('Location: ../login.php');
    exit;
}

// ===== PDO helper =====
if (!function_exists('pdo')) {
    function pdo(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $server = $GLOBALS['serverName'] ?? '(local)';
        $opts   = $GLOBALS['connectionOptions'] ?? [];
        $db     = $opts['Database'] ?? '';
        $uid    = $opts['Uid'] ?? '';
        $pwd    = $opts['PWD'] ?? '';
        $dsn    = "sqlsrv:Server={$server};Database={$db}" .
                 (!empty($opts['TrustServerCertificate']) ? ";TrustServerCertificate=1" : "");

        try {
            $pdo = new PDO($dsn, $uid, $pwd, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
            ]);
        } catch (Throwable $e) {
            if (isset($_GET['debug'])) {
                header('Content-Type: text/plain');
                echo "DB ERROR: " . $e->getMessage();
                exit;
            }
            throw $e;
        }
        return $pdo;
    }
}

$pdo = pdo();

/* ===== Filters ===== */
$today   = date('Y-m-d');
$start   = $_GET['start']   ?? $today;
$end     = $_GET['end']     ?? $today;
$driver  = $_GET['driver']  ?? 'all';
$vehicle = $_GET['vehicle'] ?? 'all';

// Validasi tanggal
if ($start > $end) {
    $temp = $start;
    $start = $end;
    $end = $temp;
}

/* ===== Helper functions ===== */
function indo_bulan(int $m): string {
    static $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return $months[$m] ?? '';
}

function indo_tanggal(string $ts): string {
    $t = strtotime($ts);
    if ($t === false) return $ts;
    return date('d', $t) . ' ' . indo_bulan((int)date('n', $t)) . ' ' . date('Y', $t);
}

function dt_str($v): ?string {
    if ($v instanceof DateTimeInterface) {
        return $v->format('Y-m-d H:i:s');
    }
    return is_string($v) ? $v : null;
}

function clean_name(?string $s): string {
    if ($s === null) return '-';
    $s = preg_replace('/\s*\(Rute Awal\)\s*$/ui', '', $s);
    return trim($s) === '' ? '-' : trim($s);
}

/* ===== Data mappings ===== */
// Vehicle mapping
$vehMap = [];
$vehicles = $pdo->query("SELECT Plate, Merk, Color FROM dbo.Vehicles")->fetchAll();
foreach ($vehicles as $v) {
    $vehMap[$v['Plate']] = [
        'merk' => $v['Merk'] ?? '',
        'color' => $v['Color'] ?? ''
    ];
}

// Route master mapping untuk alamat lengkap
$routeMasterMap = [];
$routes = $pdo->query("SELECT Name, Address FROM dbo.RouteMaster")->fetchAll();
foreach ($routes as $m) {
    $key = mb_strtolower(trim((string)$m['Name']), 'UTF-8');
    $routeMasterMap[$key] = (string)($m['Address'] ?? '');
}

function lookup_address(?string $placeName): string {
    if ($placeName === null) return '';
    $clean = preg_replace('/\s*\(Rute Awal\)\s*$/ui', '', (string)$placeName);
    $key = mb_strtolower(trim($clean), 'UTF-8');
    return $GLOBALS['routeMasterMap'][$key] ?? '';
}

/* ===== Cache untuk start names ===== */
$cacheStartName = [];
function get_start_name_for_batch(PDO $pdo, int $batchId): string {
    global $cacheStartName;
    if (isset($cacheStartName[$batchId])) {
        return $cacheStartName[$batchId];
    }

    $st = $pdo->prepare("
        SELECT TOP 1 Name FROM dbo.Routes
        WHERE BatchId = ?
        ORDER BY CASE WHEN CHARINDEX('(Rute Awal)', Name) > 0 THEN 0 ELSE 1 END, RouteId ASC
    ");
    $st->execute([$batchId]);
    $name = clean_name($st->fetchColumn() ?: '-');
    $cacheStartName[$batchId] = $name;

    return $name;
}

/* ===== Ambil data aktivitas ===== */
$acts = [];
if ($start && $end) {
    $sql = "
        SELECT 
            a.ActivityId, a.RouteId, a.FromLocation, a.ToLocation, 
            a.DepartTime, a.ArriveTime, a.Comment, a.CreatedAt,
            r.VehiclePlate, r.DriverName, r.BatchId, r.Name AS RouteName, r.RouteDate
        FROM dbo.RouteActivities a
        JOIN dbo.Routes r ON r.RouteId = a.RouteId
        WHERE CONVERT(date, a.CreatedAt) BETWEEN :s AND :e
    ";

    $params = [':s' => $start, ':e' => $end];

    if ($driver !== 'all') {
        $sql .= " AND r.DriverName = :drv";
        $params[':drv'] = $driver;
    }

    if ($vehicle !== 'all') {
        $sql .= " AND r.VehiclePlate = :veh";
        $params[':veh'] = $vehicle;
    }

    $sql .= " ORDER BY r.BatchId ASC, a.ActivityId ASC";

    try {
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $acts = $q->fetchAll();
    } catch (PDOException $e) {
        if ($DEBUG) {
            header('Content-Type: text/plain');
            echo "Query Error: " . $e->getMessage();
            exit;
        }
        throw $e;
    }
}

/* ===== Process keterangan + NOTE (ambil dari RouteActivities pada baris 'Selesai pengiriman') ===== */
$finishInfoByRouteId = [];
if ($acts) {
    $routeIds = array_values(array_unique(array_column($acts, 'RouteId')));
    if ($routeIds) {
        $in = implode(',', array_map('intval', $routeIds));
        $rows = $pdo->query("
            SELECT RouteId, Comment, Note
            FROM dbo.RouteActivities
            WHERE RouteId IN ($in) AND Comment LIKE 'Selesai pengiriman%'
        ")->fetchAll();

        foreach ($rows as $r) {
            $txt = (string)$r['Comment'];

            // Ambil Kategori / Qty / Note yang ditulis di COMMENT (jika ada)
            $cat = $qty = $noteInComment = null;
            if (preg_match('/Kategori:\s*([^—-]+)/ui', $txt, $m)) $cat = trim($m[1]);
            if (preg_match('/Qty:\s*([^—-]+)/ui', $txt, $m))     $qty = trim($m[1]);
            if (preg_match('/Note:\s*(.+)$/ui', $txt, $m))       $noteInComment = trim($m[1]);

            $parts = [];
            if ($cat  !== null && $cat  !== '') $parts[] = 'Kategori: ' . $cat;
            if ($qty  !== null && $qty  !== '') $parts[] = 'Qty: ' . $qty;
            if ($noteInComment !== null && $noteInComment !== '') $parts[] = 'Note: ' . $noteInComment;

            $finishInfoByRouteId[(int)$r['RouteId']] = [
                'keterangan' => $parts ? implode(' — ', $parts) : '-',
                // NOTE asli dari kolom RouteActivities.Note (hasil inline edit di layar)
                'note'       => (string)($r['Note'] ?? '')
            ];
        }
    }
}

/* ===== Susun data per batch ===== */
$byBatch = [];
if ($acts) {
    $tmp = [];
    foreach ($acts as $a) {
        $tmp[(int)$a['BatchId']][] = $a;
    }

    foreach ($tmp as $bid => $list) {
        $plate = $list[0]['VehiclePlate'] ?? '-';
        $driverName = $list[0]['DriverName'] ?? '-';
        $vehText = $plate ?: '-';

        // Tambahkan info merk dan warna kendaraan
        if (!empty($vehMap[$plate])) {
            $add = [];
            if ($vehMap[$plate]['merk']) $add[] = $vehMap[$plate]['merk'];
            if ($vehMap[$plate]['color']) $add[] = $vehMap[$plate]['color'];
            if ($add) $vehText .= ' — ' . implode(' / ', $add);
        }

        $byBatch[$bid] = [
            'vehicleText' => $vehText,
            'driver' => $driverName,
            'rows' => []
        ];

        $lastFrom = null;
        $current = null;

        foreach ($list as $a) {
            $comment = trim((string)$a['Comment']);
            $cmtLow = mb_strtolower($comment, 'UTF-8');
            $fromLoc = clean_name($a['FromLocation'] ?: null);
            $toLoc = clean_name($a['ToLocation'] ?: null);
            $depart = $a['DepartTime'] ? dt_str($a['DepartTime']) : dt_str($a['CreatedAt']);
            $arrive = $a['ArriveTime'] ? dt_str($a['ArriveTime']) : dt_str($a['CreatedAt']);

            $startLegIfNone = function() use (&$current, &$lastFrom, $depart, $bid, $fromLoc, $pdo) {
                if ($current === null) {
                    $fromText = $lastFrom ?? ($fromLoc ?: get_start_name_for_batch($pdo, $bid));
                    $current = [
                        'from' => $fromText ?: '-',
                        'depart' => $depart,
                        'to' => '-',
                        'arrive' => '-',
                        'startTs' => $depart,
                        'endTs' => null,
                        'routeId' => null
                    ];
                }
            };

            if (strpos($cmtLow, 'mulai perjalanan') === 0) {
                $startName = $fromLoc ?: get_start_name_for_batch($pdo, $bid);
                $current = [
                    'from' => $startName ?: '-',
                    'depart' => $depart,
                    'to' => '-',
                    'arrive' => '-',
                    'startTs' => $depart,
                    'endTs' => null,
                    'routeId' => null
                ];
                $lastFrom = $startName ?: '-';
            } elseif (strpos($cmtLow, 'menuju lokasi') === 0) {
                $startLegIfNone();
                if ($current['to'] === '-' || !$current['to']) {
                    $current['to'] = $toLoc ?: '-';
                }
            } elseif (strpos($cmtLow, 'tiba lokasi') === 0) {
                $startLegIfNone();
                if ($current['to'] === '-' || !$current['to']) {
                    $current['to'] = $toLoc ?: '-';
                }
                $current['arrive'] = $arrive;
                $current['endTs'] = $arrive;
                $current['routeId'] = (int)$a['RouteId'];

                $fi = $finishInfoByRouteId[$current['routeId']] ?? ['keterangan'=>'-', 'note'=>''];

                $byBatch[$bid]['rows'][] = [
                    'from'     => $current['from'],
                    'fromAddr' => lookup_address($current['from']),
                    'depart'   => $current['depart'],
                    'to'       => $current['to'],
                    'toAddr'   => lookup_address($current['to']),
                    'arrive'   => $current['arrive'],
                    'keterangan' => $fi['keterangan'],
                    'noteText'   => $fi['note'],
                    'startTs'  => substr((string)$current['startTs'], 0, 19),
                    'endTs'    => substr((string)$current['endTs'], 0, 19),
                ];
                $lastFrom = $current['to'] ?: ($toLoc ?: $lastFrom);
                $current = null;
            } elseif (strpos($cmtLow, 'menuju full') === 0) {
                $startName = get_start_name_for_batch($pdo, $bid);
                $current = [
                    'from' => $lastFrom ?: ($fromLoc ?: '-'),
                    'depart' => $depart,
                    'to' => $startName ?: '-',
                    'arrive' => '-',
                    'startTs' => $depart,
                    'endTs' => null,
                    'routeId' => (int)$a['RouteId']
                ];
            } elseif (strpos($cmtLow, 'tiba di full') === 0) {
                $startName = get_start_name_for_batch($pdo, $bid);
                if ($current === null) {
                    $current = [
                        'from' => $lastFrom ?: ($fromLoc ?: '-'),
                        'depart' => null,
                        'to' => $toLoc ?: ($startName ?: '-'),
                        'arrive' => '-',
                        'startTs' => $depart ?: dt_str($a['CreatedAt']),
                        'endTs' => null,
                        'routeId' => (int)$a['RouteId']
                    ];
                }
                if ($current['to'] === '-' || !$current['to']) {
                    $current['to'] = $toLoc ?: ($startName ?: '-');
                }
                $current['arrive'] = $arrive;
                $current['endTs'] = $arrive;

                $fi = $finishInfoByRouteId[$current['routeId']] ?? ['keterangan'=>'-', 'note'=>''];

                $byBatch[$bid]['rows'][] = [
                    'from'     => $current['from'],
                    'fromAddr' => lookup_address($current['from']),
                    'depart'   => $current['depart'],
                    'to'       => $current['to'],
                    'toAddr'   => lookup_address($current['to']),
                    'arrive'   => $current['arrive'],
                    'keterangan' => $fi['keterangan'],
                    'noteText'   => $fi['note'],
                    'startTs'  => substr((string)$current['startTs'], 0, 19),
                    'endTs'    => substr((string)$current['endTs'], 0, 19),
                ];
                $lastFrom = $current['to'] ?: ($startName ?: '-');
                $current = null;
            }
        }

        if ($current !== null) {
            $fi = $finishInfoByRouteId[$current['routeId'] ?? 0] ?? ['keterangan'=>'-', 'note'=>''];
            $byBatch[$bid]['rows'][] = [
                'from'     => $current['from'],
                'fromAddr' => lookup_address($current['from']),
                'depart'   => $current['depart'],
                'to'       => $current['to'],
                'toAddr'   => lookup_address($current['to']),
                'arrive'   => $current['arrive'],
                'keterangan' => $fi['keterangan'],
                'noteText'   => $fi['note'],
                'startTs'  => substr((string)$current['startTs'], 0, 19),
                'endTs'    => substr((string)($current['endTs'] ?? ''), 0, 19),
            ];
        }
    }
}

/* ===== Notes data (fallback lama dari ReportFindingsDetail, opsional) ===== */
$existingNotes = [];
$adminCekText = indo_tanggal(date('Y-m-d')); // tanggal admin klik generate (hari ini)

if ($start && $end) {
    $qf = $pdo->prepare("
        SELECT DriverName, StartTime, EndTime, Note
        FROM dbo.ReportFindingsDetail
        WHERE CONVERT(date, StartTime) BETWEEN :s AND :e
          " . ($driver !== 'all' ? " AND DriverName = :drv" : "") . "
    ");
    $pars = [':s' => $start, ':e' => $end];
    if ($driver !== 'all') $pars[':drv'] = $driver;

    $qf->execute($pars);

    foreach ($qf->fetchAll() as $f) {
        $key = substr($f['StartTime'], 0, 19) . '|' . substr((string)$f['EndTime'], 0, 19);
        $existingNotes[$key] = (string)($f['Note'] ?? '');
    }
}

/* ===== Tanggal pelaporan (berdasarkan RouteActivities.CreatedAt) ===== */
if (!empty($acts)) {
    $dates = [];
    foreach ($acts as $a) {
        $d = strtotime((string)$a['CreatedAt']);
        if ($d !== false) {
            $dates[] = date('Y-m-d', $d);
        }
    }
    if (!empty($dates)) {
        $min = min($dates);
        $max = max($dates);
        $pelaporanText = ($min === $max)
            ? indo_tanggal($min)
            : (indo_tanggal($min) . ' s/d ' . indo_tanggal($max));
    } else {
        $pelaporanText = ($start === $end)
            ? indo_tanggal($start)
            : (indo_tanggal($start) . ' s/d ' . indo_tanggal($end));
    }
} else {
    $pelaporanText = ($start === $end)
        ? indo_tanggal($start)
        : (indo_tanggal($start) . ' s/d ' . indo_tanggal($end));
}

/* ===== TCPDF Configuration ===== */
try {
    $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('GG System');
    $pdf->SetAuthor('GG System');
    $pdf->SetTitle('Laporan Rute Harian - PT. SUMI');
    $pdf->SetSubject('Laporan Evaluasi Harian Perjalanan Kendaraan');

    // Set margins
    $pdf->SetMargins(8, 15, 8);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(8);
    $pdf->SetAutoPageBreak(true, 12);

    // Set font
    $pdf->SetFont('helvetica', '', 9);

} catch (Throwable $e) {
    if ($DEBUG) {
        header('Content-Type: text/plain');
        echo "TCPDF INIT ERROR: " . $e->getMessage();
        exit;
    }
    throw $e;
}

// Add first page
$pdf->AddPage();

/* ===== Header Section ===== */
// Company Header
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 6, 'PT. SURYA USAHA MANDIRI', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 5, 'LAPORAN EVALUASI HARIAN PERJALANAN KENDARAAN ANGKUTAN', 0, 1, 'C');
$pdf->Ln(3);

// Information Section
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(40, 5, 'Tanggal pelaporan sopir', 0, 0, 'L');
$pdf->Cell(3, 5, ':', 0, 0, 'C');
$pdf->Cell(0, 5, $pelaporanText, 0, 1, 'L');

$pdf->Cell(40, 5, 'Tanggal Pengecekan Admin', 0, 0, 'L');
$pdf->Cell(3, 5, ':', 0, 0, 'C');
$pdf->Cell(0, 5, $adminCekText, 0, 1, 'L');

// Filter information
if ($driver !== 'all') {
    $pdf->Cell(40, 5, 'Driver', 0, 0, 'L');
    $pdf->Cell(3, 5, ':', 0, 0, 'C');
    $pdf->Cell(0, 5, $driver, 0, 1, 'L');
}

if ($vehicle !== 'all') {
    $pdf->Cell(40, 5, 'Kendaraan', 0, 0, 'L');
    $pdf->Cell(3, 5, ':', 0, 0, 'C');
    $pdf->Cell(0, 5, $vehicle, 0, 1, 'L');
}

$pdf->Ln(5);

/* ===== Table Configuration ===== */
// Header (tambah kolom "Tanggal" di kiri "No")
$header = [
    'Tanggal', 'No', 'Jenis & No Kendaraan', 'Nama Sopir',
    'Dari (Lokasi & Alamat)', 'Jam Berangkat', 'Ke (Lokasi & Alamat)',
    'Jam Tiba', 'Keterangan', 'Temuan/Komentar'
];

/*
 * Lebar kolom (total ~275mm untuk A4 landscape)
 * [20, 8, 30, 23, 40, 18, 40, 18, 30, 48] => 275
 */
$widths = [20, 8, 30, 23, 40, 18, 40, 18, 30, 48];

// >>> Vertical alignment setting untuk kolom teks panjang:
$VALIGN_TEXT = 'M'; // 'M' = tengah (atas-bawah seimbang). Ubah ke 'T' jika mau rata atas.

/** Draw table header */
function drawTableHeader($pdf, $header, $widths) {
    $pdf->SetFillColor(60, 60, 60);
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 7);

    $height = 10;
    foreach ($header as $i => $h) {
        $pdf->Cell($widths[$i], $height, $h, 1, 0, 'C', 1, 0, true);
    }
    $pdf->Ln();

    // Reset text color
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 7);
}

/** Tinggi baris approx by text width per kolom (multiline sederhana) */
function calculateRowHeight($pdf, $data, $widths) {
    $maxLines = 1;
    $colCount = count($widths);
    for ($i = 0; $i < $colCount; $i++) {
        $text = $data[$i] ?? '';
        if ($text !== '') {
            $textWidth = $pdf->GetStringWidth($text);
            $colWidth = max(1, $widths[$i] - 2);
            $lines = max(1, (int)ceil($textWidth / $colWidth));
            $nlCount = substr_count($text, "\n");
            $lines += $nlCount;
            $maxLines = max($maxLines, $lines);
        }
    }
    return $maxLines * 4; // 4mm per line
}

drawTableHeader($pdf, $header, $widths);

/* ===== Table Content (tanpa merge, No reset per BatchId) ===== */
$batchIds = array_keys($byBatch);
sort($batchIds);

foreach ($batchIds as $bid) {
    $group = $byBatch[$bid];
    $rows = $group['rows'];
    if (empty($rows)) continue;

    $rowNo = 1; // reset nomor untuk batch ini

    foreach ($rows as $row) {
        // Siapkan teks kolom
        $tglRaw = $row['startTs'] ?? '';
        $tglIso = $tglRaw ? substr((string)$tglRaw, 0, 10) : '';
        $tglText = $tglIso ? indo_tanggal($tglIso) : '-';

        $vehText   = $group['vehicleText'];
        $drvText   = $group['driver'];
        $fromText  = $row['from'] . "\n" . ($row['fromAddr'] ?: '-');
        $depart    = $row['depart'] ? substr((string)$row['depart'], 11, 8) : '-';
        $toText    = $row['to'] . "\n" . ($row['toAddr'] ?: '-');
        $arrive    = $row['arrive'] ? substr((string)$row['arrive'], 11, 8) : '-';
        $ketText   = $row['keterangan'];

        // NOTE: utamakan Note dari RouteActivities.Note (hasil inline edit)
        $noteText = $row['noteText'] ?? '';
        // Fallback opsional ke ReportFindingsDetail jika kosong
        if ($noteText === '') {
            $key = ($row['startTs'] ?? '') . '|' . ($row['endTs'] ?? '');
            $noteText = $existingNotes[$key] ?? '';
        }

        // Data untuk hitung tinggi baris (urut sesuai header/widths)
        $rowData = [
            $tglText,
            (string)$rowNo,
            $vehText,
            $drvText,
            $fromText,
            $depart,
            $toText,
            $arrive,
            $ketText,
            $noteText
        ];

        $rowHeight = calculateRowHeight($pdf, $rowData, $widths);

        // Page break jika perlu
        if ($pdf->GetY() + $rowHeight > 180) {
            $pdf->AddPage();
            drawTableHeader($pdf, $header, $widths);
        }

        // Gambar sel per kolom
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // 0: Tanggal
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($widths[0], $rowHeight, $tglText, 1, 'L', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');

        // 1: No (per-row, reset per batch)
        $pdf->MultiCell($widths[1], $rowHeight, (string)$rowNo, 1, 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');

        // 2: Jenis & No Kendaraan
        $pdf->MultiCell($widths[2], $rowHeight, $vehText, 1, 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');

        // 3: Nama Sopir
        $pdf->MultiCell($widths[3], $rowHeight, $drvText, 1, 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');

        // 4: Dari (Lokasi & Alamat)
        $pdf->MultiCell($widths[4], $rowHeight, $fromText, 1, 'L', false, 0, '', '', true, 0, false, true, $rowHeight, $VALIGN_TEXT);

        // 5: Jam Berangkat
        $pdf->MultiCell($widths[5], $rowHeight, $depart, 1, 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');

        // 6: Ke (Lokasi & Alamat)
        $pdf->MultiCell($widths[6], $rowHeight, $toText, 1, 'L', false, 0, '', '', true, 0, false, true, $rowHeight, $VALIGN_TEXT);

        // 7: Jam Tiba
        $pdf->MultiCell($widths[7], $rowHeight, $arrive, 1, 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');

        // 8: Keterangan
        $pdf->MultiCell($widths[8], $rowHeight, $ketText, 1, 'L', false, 0, '', '', true, 0, false, true, $rowHeight, $VALIGN_TEXT);

        // 9: Temuan/Komentar
        $pdf->MultiCell($widths[9], $rowHeight, $noteText, 1, 'L', false, 1, '', '', true, 0, false, true, $rowHeight, $VALIGN_TEXT);

        $rowNo++;
    }
}

/* ===== Footer Information ===== */
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(0, 4, 'Dokumen ini dihasilkan secara otomatis oleh Sistem GG App', 0, 1, 'C');
$pdf->Cell(0, 4, 'Tanggal generate: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

/* ===== Output PDF ===== */
if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'Laporan_Rute_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
