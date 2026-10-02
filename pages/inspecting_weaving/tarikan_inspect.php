<?php
// tarikaninspect.php (parameterized query, export excel, default filter = today)
// Perubahan penting: jika export=1 maka jangan include header/sidebar/footer agar HTML layout tidak ikut ter-download.

session_start();
ob_start();
include '../../koneksi.php'; // koneksi DB (sqlsrv)
date_default_timezone_set('Asia/Jakarta');

// pastikan login
if (!isset($_SESSION['UserName'])) {
    // jika export, kirim pesan sederhana; untuk UI biasa redirect
    if (isset($_GET['export']) && $_GET['export'] == '1') {
        header('HTTP/1.1 401 Unauthorized');
        echo "Silakan login terlebih dahulu.";
        exit;
    }
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}

// helper format number
function fmtNumber($v, $dec = 2) {
    if (!is_numeric($v)) return '0';
    $f = floatval($v);
    if (floor($f) == $f) return number_format($f, 0, '.', ',');
    return number_format($f, $dec, '.', ',');
}

/**
 * Parse InspectDate value returned from SQL Server.
 * Returns DateTime object in Asia/Jakarta or null.
 */
function parseInspectDate($val) {
    $tz = new DateTimeZone('Asia/Jakarta');
    if ($val instanceof DateTime) {
        try { $val->setTimezone($tz); } catch (Exception $e) {}
        return $val;
    }
    if (!is_string($val) || trim($val) === '') return null;
    $s = trim($val);

    // Try several formats
    $formats = [
        'Y-m-d H:i:s.u', // microseconds (if present)
        'Y-m-d H:i:s.v', // milliseconds (PHP 7.2+)
        'Y-m-d H:i:s',   // no fractional
        'Y-m-d'          // date only
    ];

    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $s, $tz);
        if ($dt !== false) return $dt;
    }

    // strip fractional seconds then try
    $s2 = preg_replace('/\.\d+$/', '', $s);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $s2, $tz);
    if ($dt !== false) return $dt;

    try {
        return new DateTime($s, $tz);
    } catch (Exception $e) {
        return null;
    }
}

// ambil filter tanggal dari GET
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$export = isset($_GET['export']) && $_GET['export'] == '1';

// default: hari ini .. hari ini (requested)
if ($from === '') {
    $from = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
}
if ($to === '') {
    $to = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
}

// normalisasi dan validasi tanggal
try {
    $dtFrom = new DateTime($from, new DateTimeZone('Asia/Jakarta'));
    $dtTo   = new DateTime($to, new DateTimeZone('Asia/Jakarta'));
} catch (Exception $e) {
    $dtFrom = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $dtTo   = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
}

// Untuk memenuhi permintaan: 1 hari full dihitung
// kita set range inklusif dari 00:00:01 sampai 23:59:59 pada tanggal 'to'
$fromParam = $dtFrom->format('Y-m-d') . ' 00:00:01';
$toParam   = $dtTo->format('Y-m-d') . ' 23:59:59';

// Query (parameterized, inklusif)
$sql = "
SELECT
  CONVERT(varchar(23), fh.InspectDate, 121) AS InspectDate,
  fh.NoCP,
  fh.Lot,
  ISNULL(art.ArtikelKode, '') AS ArtikelKode,
  ISNULL(emp.EmpName, '') AS Inspektor,
  ISNULL(s.TotalKodeCacat, 0) AS TotalKodeCacat,
  ISNULL(s.TotalMeterCacat, 0) AS TotalMeterCacat,
  ISNULL(s.TotalPointCacat, 0) AS TotalPointCacat,
  ISNULL(s.PanjangKainI, 0) AS PanjangKainI,
  ISNULL(s.Grade, '') AS Grade
FROM dbo.FormInspectHd fh WITH (NOLOCK)
LEFT JOIN dbo.SMCacatSummary s WITH (NOLOCK) ON fh.NoCP = s.NoCP
LEFT JOIN dbo.SMArtikel art WITH (NOLOCK) ON fh.ArtikelId = art.ArtikelId
LEFT JOIN dbo.SMEmployeeInspector emp WITH (NOLOCK) ON fh.EmpId = emp.EmpId
WHERE fh.InspectDate >= ? 
  AND fh.InspectDate <= ?
  AND fh.FgCacat = 1
ORDER BY fh.InspectDate DESC, fh.NoCP DESC;
";

$params = [$fromParam, $toParam];
$stmt = sqlsrv_query($conn, $sql, $params);
$rows = [];
if ($stmt !== false) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
} else {
    // debug: uncomment di dev env
    // error_log("tarikaninspect query error: " . print_r(sqlsrv_errors(), true));
    $rows = [];
}

// ----- Jika export, kirim file Excel lalu exit (TANPA include header/sidebar/footer) -----
if ($export) {
    $filename = "tarikan_inspect_" . $dtFrom->format('Ymd') . "_" . $dtTo->format('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename={$filename}");
    header("Pragma: no-cache");
    header("Expires: 0");

    // BOM agar Excel membaca UTF-8 dengan benar
    echo "\xEF\xBB\xBF";

    // Hanya keluarkan tabel data (tanpa HTML layout)
    echo "<table border='1'>";
    echo "<thead><tr>";
    echo "<th>Tanggal Inspect</th>";
    echo "<th>No CP</th>";
    echo "<th>Artikel</th>";
    echo "<th>LOT</th>";
    echo "<th>Inspektor</th>";
    echo "<th>Total Kode Cacat</th>";
    echo "<th>Total Meter Cacat</th>";
    echo "<th>Point Grade (%)</th>";
    echo "<th>Total Point Cacat</th>";
    echo "<th>Grade</th>";
    echo "</tr></thead><tbody>";

    foreach ($rows as $r) {
        // parse tanggal
        $inspectDateOut = '';
        if (isset($r['InspectDate'])) {
            $dtObj = parseInspectDate($r['InspectDate']);
            if ($dtObj instanceof DateTime) $inspectDateOut = $dtObj->format('Y-m-d H:i:s');
            elseif (is_string($r['InspectDate'])) $inspectDateOut = $r['InspectDate'];
        }

        $panjang = is_numeric($r['PanjangKainI']) ? floatval($r['PanjangKainI']) : 0;
        $totalPoint = is_numeric($r['TotalPointCacat']) ? floatval($r['TotalPointCacat']) : 0;
        $pointGrade = ($panjang > 0) ? ($totalPoint / $panjang) * 100 : 0;

        echo "<tr>";
        echo "<td>" . htmlspecialchars($inspectDateOut) . "</td>";
        echo "<td>" . htmlspecialchars($r['NoCP']) . "</td>";
        echo "<td>" . htmlspecialchars($r['ArtikelKode']) . "</td>";
        echo "<td>" . htmlspecialchars($r['Lot']) . "</td>";
        echo "<td>" . htmlspecialchars($r['Inspektor']) . "</td>";
        echo "<td>" . intval($r['TotalKodeCacat']) . "</td>";
        echo "<td>" . (is_numeric($r['TotalMeterCacat']) ? number_format($r['TotalMeterCacat'], 2, '.', ',') : '0') . "</td>";
        echo "<td>" . number_format($pointGrade, 2, '.', ',') . "</td>";
        echo "<td>" . (is_numeric($r['TotalPointCacat']) ? number_format($r['TotalPointCacat'], 2, '.', ',') : '0') . "</td>";
        echo "<td>" . htmlspecialchars($r['Grade']) . "</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
    exit;
}

// ----- Kalau bukan export: include header/sidebar dan tampilkan UI seperti biasa -----
include '../../includes/header.php';
include '../../includes/sidebar.php';

// hak akses (sesuaikan MenuId bila perlu) - lakukan setelah include header bila Anda menampilkan menu berbasis $permissions
$themeColor = $_SESSION['Theme'] ?? 'primary'; // pastikan tema tetap tersedia

// (Jika Anda perlu cek hak akses lebih ketat, lakukan cek di atas sebelum query. Saya asumsikan cek awal sudah cukup.)

?>
<!-- HTML output -->
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tarikan Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Tarikan Inspect</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h3 class="card-title">Filter</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label>From</label>
                        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($dtFrom->format('Y-m-d')) ?>">
                    </div>
                    <div class="col-auto">
                        <label>To</label>
                        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($dtTo->format('Y-m-d')) ?>">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-<?php echo htmlspecialchars($themeColor); ?>">Terapkan</button>
                        <a class="btn btn-outline-secondary" href="tarikaninspect.php">Reset</a>
                    </div>
                    <div class="col-auto ms-auto">
                        <a href="?<?= http_build_query(['from' => $dtFrom->format('Y-m-d'), 'to' => $dtTo->format('Y-m-d'), 'export' => 1]) ?>" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export to Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h3 class="card-title">Hasil Tarikan (<?= htmlspecialchars($dtFrom->format('Y-m-d')) ?> s/d <?= htmlspecialchars($dtTo->format('Y-m-d')) ?>)</h3>
            </div>
            <div class="card-body table-responsive">

                <!-- small CSS untuk center TH dan cell -->
                <style>
                    #tarikanTable th {
                        text-align: center !important;
                        vertical-align: middle !important;
                    }
                    #tarikanTable td {
                        vertical-align: middle !important;
                    }
                </style>

                <table id="tarikanTable" class="table table-sm table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px">No</th>
                            <th>Tanggal Inspect</th>
                            <th>No CP</th>
                            <th>Artikel</th>
                            <th>LOT</th>
                            <th>Inspektor</th>
                            <th>Total Kode Cacat</th>
                            <th>Total Meter Cacat</th>
                            <th>Point Grade (%)</th>
                            <th>Total Point Cacat</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach ($rows as $r):
                            // parse inspect date safely
                            $inspectDateStr = '';
                            if (isset($r['InspectDate'])) {
                                $dtObj = parseInspectDate($r['InspectDate']);
                                if ($dtObj instanceof DateTime) $inspectDateStr = $dtObj->format('Y-m-d H:i:s');
                                elseif (is_string($r['InspectDate'])) $inspectDateStr = $r['InspectDate'];
                            }

                            $panjang = is_numeric($r['PanjangKainI']) ? floatval($r['PanjangKainI']) : 0;
                            $totalPoint = is_numeric($r['TotalPointCacat']) ? floatval($r['TotalPointCacat']) : 0;
                            $pointGradePerc = ($panjang > 0) ? ($totalPoint / $panjang) * 100 : 0;
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="text-center"><?= htmlspecialchars($inspectDateStr) ?></td>
                            <td class="text-center"><?= htmlspecialchars($r['NoCP']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($r['ArtikelKode']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($r['Lot']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($r['Inspektor']) ?></td>
                            <td class="text-center"><?= intval($r['TotalKodeCacat']) ?></td>
                            <td class="text-end"><?= fmtNumber($r['TotalMeterCacat'], 2) ?></td>
                            <td class="text-end"><?= number_format($pointGradePerc, 2, '.', ',') ?>%</td>
                            <td class="text-end"><?= fmtNumber($r['TotalPointCacat'], 2) ?></td>
                            <td class="text-center"><?= htmlspecialchars($r['Grade']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($rows) === 0): ?>
                        <tr><td colspan="11" class="text-center">Tidak ada data untuk periode ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Safe DataTables loader + initialization (tetap seperti sebelumnya) -->
<script>
(function(){
    // Paths - sesuaikan jika berbeda
    var paths = {
        jquery: '/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js',
        dt_css: '/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
        dt_responsive_css: '/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
        dt_js: '/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js',
        dt_bs4_js: '/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
        dt_responsive_js: '/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js',
        dt_responsive_bs4_js: '/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js'
    };

    function loadCssOnce(href){
        if (!document.querySelector('link[href="'+href+'"]')) {
            var l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = href;
            document.head.appendChild(l);
        }
    }

    function loadScriptOnce(src, cb){
        var existing = document.querySelector('script[src="'+src+'"]');
        if (existing) {
            if (existing.getAttribute('data-loaded') === '1') return cb && cb();
            existing.addEventListener('load', function(){ cb && cb(); });
            existing.addEventListener('error', function(){ cb && cb(); });
            return;
        }
        var s = document.createElement('script');
        s.src = src;
        s.async = false;
        s.onload = function(){ s.setAttribute('data-loaded','1'); cb && cb(); };
        s.onerror = function(){ console.error('Gagal load script: ' + src); cb && cb(); };
        document.head.appendChild(s);
    }

    function findExistingJQueryScript() {
        var scripts = Array.from(document.querySelectorAll('script[src]'));
        for (var i=0;i<scripts.length;i++){
            var s = scripts[i].getAttribute('src') || '';
            if (/jquery(\.min)?\.js/i.test(s)) return scripts[i];
        }
        return null;
    }

    function ensureJQueryReady(cb) {
        if (typeof window.jQuery !== 'undefined') return cb();
        var existing = findExistingJQueryScript();
        if (existing) {
            if (existing.getAttribute('data-loaded') === '1' || existing.readyState === 'complete') {
                setTimeout(cb, 20);
            } else {
                existing.addEventListener('load', function(){ setTimeout(cb,20); });
                existing.addEventListener('error', function(){ 
                    loadScriptOnce(paths.jquery, function(){ setTimeout(cb,20); });
                });
            }
            return;
        }
        loadScriptOnce(paths.jquery, function(){ setTimeout(cb,20); });
    }

    function ensureDataTablesThen(cb){
        if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) return cb();
        loadCssOnce(paths.dt_css);
        loadCssOnce(paths.dt_responsive_css);
        loadScriptOnce(paths.dt_js, function(){
            loadScriptOnce(paths.dt_bs4_js, function(){
                loadScriptOnce(paths.dt_responsive_js, function(){
                    loadScriptOnce(paths.dt_responsive_bs4_js, function(){
                        setTimeout(cb, 30);
                    });
                });
            });
        });
    }

    function initTableSafe(){
        if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) {
            console.warn('DataTables belum tersedia saat init.');
            return;
        }
        var $ = jQuery;
        var $t = $('#tarikanTable');
        if (!$t.length) return;
        var headerCount = $t.find('thead tr').first().children('th').length;
        $t.find('tbody tr').each(function(){
            var cells = $(this).children('td,th').length;
            while (cells < headerCount) {
                $(this).append('<td></td>');
                cells++;
            }
        });

        if ($.fn.dataTable.isDataTable('#tarikanTable')) {
            try { $t.DataTable().clear().destroy(); } catch(e){ console.warn('destroy failed', e); }
        }

        $t.DataTable({
            lengthMenu: [10,25,50],
            responsive: true,
            order: [[1, 'desc']], // sort by InspectDate (col index 1)
            columnDefs: [
                { orderable: false, targets: [0,6] } // No and TotalKodeCacat non-orderable
            ],
            deferRender: true,
            autoWidth: false,
            language: { emptyTable: "Tidak ada data untuk periode ini." }
        });
    }

    ensureJQueryReady(function(){
        ensureDataTablesThen(function(){
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTableSafe);
            } else {
                initTableSafe();
            }
        });
    });

})();
</script>
