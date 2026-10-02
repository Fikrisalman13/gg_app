<?php
// dashboard_smcacat.php
// Dashboard SMCacat - Left: charts, Right: summaries + quick lists (aligned)

session_start();
date_default_timezone_set('Asia/Jakarta');
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /login.php');
    exit;
}

function getDateInput($key, $default) {
    if (!empty($_GET[$key])) return $_GET[$key];
    return $default;
}
function fmt($n) {
    return number_format(floatval($n), 2, '.', ',');
}

// Default: month-to-date (1 .. today)
$tz = new DateTimeZone('Asia/Jakarta');
$today = new DateTime('now', $tz);
$firstOfThisMonth = (clone $today)->modify('first day of this month')->setTime(0,0,0);
$defaultFrom = $firstOfThisMonth->format('Y-m-d');
$defaultTo   = $today->format('Y-m-d');

$from = getDateInput('from', $defaultFrom);
$to   = getDateInput('to', $defaultTo);

$fromParam = date('Y-m-d', strtotime($from));
$toParam   = date('Y-m-d', strtotime($to));

// ---------------- 1) Daily points (weaving01 vs weaving02) --------------------
$sqlDaily = "
SELECT PeriodDay, WeavingGroup, SUM(TotalPoint) AS TotalPoint
FROM (
    SELECT
        CONVERT(varchar(10), fh.InspectDate, 23) AS PeriodDay,
        CASE 
            WHEN m.MesinNo BETWEEN 1 AND 80 THEN 'weaving01' 
            WHEN m.MesinNo BETWEEN 81 AND 170 THEN 'weaving02' 
            ELSE 'other' 
        END AS WeavingGroup,
        SUM( (ABS(ISNULL(d.SMeterKe,d.MeterKe) - ISNULL(d.MeterKe,d.SMeterKe)) + 1) * ISNULL(d.PointCacat,0) ) AS TotalPoint
    FROM dbo.SMCacatDetail d WITH (NOLOCK)
    INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
    LEFT JOIN dbo.SMMesinInspector m WITH (NOLOCK) ON fh.WVMCId = m.MesinId
    WHERE fh.InspectDate BETWEEN ? AND ?
      AND fh.FgCacat = 1
    GROUP BY CONVERT(varchar(10), fh.InspectDate, 23),
             CASE 
                WHEN m.MesinNo BETWEEN 1 AND 80 THEN 'weaving01' 
                WHEN m.MesinNo BETWEEN 81 AND 170 THEN 'weaving02' 
                ELSE 'other' 
             END
) t
GROUP BY PeriodDay, WeavingGroup
ORDER BY PeriodDay ASC;
";
$paramsDaily = [$fromParam, $toParam];
$stmtDaily = sqlsrv_query($conn, $sqlDaily, $paramsDaily);
$dailyData = [];
if ($stmtDaily !== false) {
    while ($r = sqlsrv_fetch_array($stmtDaily, SQLSRV_FETCH_ASSOC)) {
        $dailyData[$r['PeriodDay']][$r['WeavingGroup']] = floatval($r['TotalPoint']);
    }
    sqlsrv_free_stmt($stmtDaily);
}

// build contiguous day arrays
$start = new DateTime($fromParam, $tz);
$end = new DateTime($toParam, $tz);
$periodRange = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->add(new DateInterval('P1D')));
$labels = []; $weaving01=[]; $weaving02=[];
foreach ($periodRange as $dt) {
    $d = $dt->format('Y-m-d');
    $labels[] = $d;
    $weaving01[] = $dailyData[$d]['weaving01'] ?? 0;
    $weaving02[] = $dailyData[$d]['weaving02'] ?? 0;
}

// ---------------- 2) Grade per weaving group for selected period -----------------
$sqlGradePerWeaving = "
SELECT WeavingGroup,
       SUM(TotalPoint) AS TotalPointGroup,
       SUM(PanjangKain) AS TotalPanjangGroup
FROM (
    SELECT
        d.NoCP,
        CASE WHEN m.MesinNo BETWEEN 1 AND 80 THEN 'weaving01'
             WHEN m.MesinNo BETWEEN 81 AND 170 THEN 'weaving02'
             ELSE 'other' END AS WeavingGroup,
        SUM((ABS(ISNULL(d.SMeterKe,d.MeterKe) - ISNULL(d.MeterKe,d.SMeterKe)) + 1) * ISNULL(d.PointCacat,0)) AS TotalPoint,
        MAX(ISNULL(fh.PanjangKainI,0)) AS PanjangKain
    FROM dbo.SMCacatDetail d WITH (NOLOCK)
    INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
    LEFT JOIN dbo.SMMesinInspector m WITH (NOLOCK) ON fh.WVMCId = m.MesinId
    WHERE fh.InspectDate BETWEEN ? AND ?
      AND fh.FgCacat = 1
    GROUP BY d.NoCP, CASE WHEN m.MesinNo BETWEEN 1 AND 80 THEN 'weaving01'
                          WHEN m.MesinNo BETWEEN 81 AND 170 THEN 'weaving02'
                          ELSE 'other' END
) t
GROUP BY WeavingGroup;
";
$paramsGradeW = [$fromParam, $toParam];
$stmtGradeW = sqlsrv_query($conn, $sqlGradePerWeaving, $paramsGradeW);
$gradeByWeaving = ['weaving01'=>['totalPoint'=>0,'totalPanjang'=>0,'ratio'=>0,'grade'=>'-'],
                   'weaving02'=>['totalPoint'=>0,'totalPanjang'=>0,'ratio'=>0,'grade'=>'-']];
if ($stmtGradeW !== false) {
    while ($r = sqlsrv_fetch_array($stmtGradeW, SQLSRV_FETCH_ASSOC)) {
        $grp = $r['WeavingGroup'];
        $tp = floatval($r['TotalPointGroup'] ?? 0);
        $tk = floatval($r['TotalPanjangGroup'] ?? 0);
        $ratio = ($tk > 0) ? ($tp / $tk) : 0;
        $gr = 'C';
        if ($ratio <= 0.30) $gr = 'A';
        elseif ($ratio <= 0.60) $gr = 'B';
        if ($grp == 'weaving01' || $grp == 'weaving02') {
            $gradeByWeaving[$grp] = ['totalPoint'=>$tp,'totalPanjang'=>$tk,'ratio'=>$ratio,'grade'=>$gr];
        }
    }
    sqlsrv_free_stmt($stmtGradeW);
}

// ---------------- 3) Top5 combined (overall) -----------------------------------
$sqlTop5 = "
SELECT TOP 5
    c.CacatId, c.CacatKode, c.CacatName,
    SUM((ABS(ISNULL(d.SMeterKe,d.MeterKe) - ISNULL(d.MeterKe,d.SMeterKe)) + 1) * ISNULL(d.PointCacat,0)) AS TotalPoint
FROM dbo.SMCacatDetail d WITH (NOLOCK)
INNER JOIN dbo.SMCacat c WITH (NOLOCK) ON d.CacatId = c.CacatId
INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
WHERE fh.InspectDate BETWEEN ? AND ?
  AND fh.FgCacat = 1
GROUP BY c.CacatId, c.CacatKode, c.CacatName
ORDER BY TotalPoint DESC;
";
$paramsTop5 = [$fromParam, $toParam];
$stmtTop5 = sqlsrv_query($conn, $sqlTop5, $paramsTop5);
$top5 = [];
if ($stmtTop5 !== false) {
    while ($r = sqlsrv_fetch_array($stmtTop5, SQLSRV_FETCH_ASSOC)) $top5[] = $r;
    sqlsrv_free_stmt($stmtTop5);
}

// ---------------- 4) For the Top5 labels, get breakdown per-weaving ------------
$topLabels = [];
$topW1Data = [];
$topW2Data = [];

if (!empty($top5)) {
    // build placeholders and params
    $topIds = array_map(function($r){ return $r['CacatId']; }, $top5);
    $placeholders = implode(',', array_fill(0, count($topIds), '?'));

    $sqlTop5ByWeaving = "
    SELECT c.CacatId, c.CacatKode, c.CacatName,
           CASE 
             WHEN m.MesinNo BETWEEN 1 AND 80 THEN 'weaving01'
             WHEN m.MesinNo BETWEEN 81 AND 170 THEN 'weaving02'
             ELSE 'other'
           END AS WeavingGroup,
           SUM((ABS(ISNULL(d.SMeterKe,d.MeterKe) - ISNULL(d.MeterKe,d.SMeterKe)) + 1) * ISNULL(d.PointCacat,0)) AS TotalPoint
    FROM dbo.SMCacatDetail d WITH (NOLOCK)
    INNER JOIN dbo.SMCacat c WITH (NOLOCK) ON d.CacatId = c.CacatId
    INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
    LEFT JOIN dbo.SMMesinInspector m WITH (NOLOCK) ON fh.WVMCId = m.MesinId
    WHERE fh.InspectDate BETWEEN ? AND ?
      AND fh.FgCacat = 1
      AND c.CacatId IN ($placeholders)
    GROUP BY c.CacatId, c.CacatKode, c.CacatName,
             CASE 
               WHEN m.MesinNo BETWEEN 1 AND 80 THEN 'weaving01'
               WHEN m.MesinNo BETWEEN 81 AND 170 THEN 'weaving02'
               ELSE 'other'
             END;
    ";

    // params: from,to, then each id
    $params = array_merge([$fromParam, $toParam], $topIds);
    $stmt = sqlsrv_query($conn, $sqlTop5ByWeaving, $params);

    $byWeaving = [];
    $labelsMap = [];
    if ($stmt !== false) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $cid = $r['CacatId'];
            $grp = $r['WeavingGroup'];
            $byWeaving[$cid][$grp] = floatval($r['TotalPoint']);
            $labelsMap[$cid] = $r['CacatKode'] . ' - ' . $r['CacatName'];
        }
        sqlsrv_free_stmt($stmt);
    }

    // prepare arrays in the same order as $top5
    foreach ($top5 as $p) {
        $cid = $p['CacatId'];
        $topLabels[] = $labelsMap[$cid] ?? ($p['CacatKode'] . ' - ' . $p['CacatName']);
        $topW1Data[] = $byWeaving[$cid]['weaving01'] ?? 0;
        $topW2Data[] = $byWeaving[$cid]['weaving02'] ?? 0;
    }
}

// ---------------- 5) Top5 per weaving tables (for quick lists) ----------
$sqlTopWeaving1 = "
SELECT TOP 5
    c.CacatId, c.CacatKode, c.CacatName,
    SUM((ABS(ISNULL(d.SMeterKe,d.MeterKe) - ISNULL(d.MeterKe,d.SMeterKe)) + 1) * ISNULL(d.PointCacat,0)) AS TotalPoint
FROM dbo.SMCacatDetail d WITH (NOLOCK)
INNER JOIN dbo.SMCacat c WITH (NOLOCK) ON d.CacatId = c.CacatId
INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
LEFT JOIN dbo.SMMesinInspector m WITH (NOLOCK) ON fh.WVMCId = m.MesinId
WHERE fh.InspectDate BETWEEN ? AND ?
  AND fh.FgCacat = 1
  AND m.MesinNo BETWEEN 1 AND 80
GROUP BY c.CacatId, c.CacatKode, c.CacatName
ORDER BY TotalPoint DESC;
";
$sqlTopWeaving2 = "
SELECT TOP 5
    c.CacatId, c.CacatKode, c.CacatName,
    SUM((ABS(ISNULL(d.SMeterKe,d.MeterKe) - ISNULL(d.MeterKe,d.SMeterKe)) + 1) * ISNULL(d.PointCacat,0)) AS TotalPoint
FROM dbo.SMCacatDetail d WITH (NOLOCK)
INNER JOIN dbo.SMCacat c WITH (NOLOCK) ON d.CacatId = c.CacatId
INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
LEFT JOIN dbo.SMMesinInspector m WITH (NOLOCK) ON fh.WVMCId = m.MesinId
WHERE fh.InspectDate BETWEEN ? AND ?
  AND fh.FgCacat = 1
  AND m.MesinNo BETWEEN 81 AND 170
GROUP BY c.CacatId, c.CacatKode, c.CacatName
ORDER BY TotalPoint DESC;
";

$paramsW = [$fromParam, $toParam];
$stmtW1 = sqlsrv_query($conn, $sqlTopWeaving1, $paramsW);
$topW1 = [];
if ($stmtW1 !== false) {
    while ($r = sqlsrv_fetch_array($stmtW1, SQLSRV_FETCH_ASSOC)) $topW1[] = $r;
    sqlsrv_free_stmt($stmtW1);
}
$stmtW2 = sqlsrv_query($conn, $sqlTopWeaving2, $paramsW);
$topW2 = [];
if ($stmtW2 !== false) {
    while ($r = sqlsrv_fetch_array($stmtW2, SQLSRV_FETCH_ASSOC)) $topW2[] = $r;
    sqlsrv_free_stmt($stmtW2);
}

// explanatory texts
$explainTop = "Nilai di chart adalah TOTAL POINT CACAT per kode, dibagi kontribusi dari Weaving01 & Weaving02. Total Point = Σ[(ABS(SMeterKe - MeterKe) + 1) × PointCacat].";
$explainRatio = "Ratio = TotalPoint / TotalPanjangKain (point per meter). Ditampilkan dalam persen (ratio×100). Grade: A ≤ 30%, B ≤ 60%, C > 60.";

// ---------------- render HTML ----------------
?>
<style>
/* Merapikan tampilan summary agar header & value sejajar dan rata tengah */
.summary-table th,
.summary-table td {
    text-align: center;
    font-size: 0.9rem;
}
.summary-table .value {
    font-size: 1.15rem;
    font-weight: 600;
}
</style>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-12">
      <h1 class="m-0">Dashboard SMCacat — Periode <?= htmlspecialchars($fromParam) ?> s/d <?= htmlspecialchars($toParam) ?></h1>
    </div></div></div>
  </div>

  <div class="content"><div class="container-fluid">
    <div class="card mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-auto"><label>From</label><input type="date" name="from" value="<?= htmlspecialchars($fromParam) ?>" class="form-control"></div>
          <div class="col-auto"><label>To</label><input type="date" name="to" value="<?= htmlspecialchars($toParam) ?>" class="form-control"></div>
          <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
          <div class="col-auto"><button class="btn btn-outline-secondary" type="button" onclick="document.querySelector('input[name=from]').value='<?= $defaultFrom ?>'; document.querySelector('input[name=to]').value='<?= $defaultTo ?>';">Reset</button></div>
        </form>
      </div>
    </div>

    <div class="row">
      <!-- LEFT: charts -->
      <div class="col-md-8">
        <!-- Daily chart -->
        <div class="card mb-3">
          <div class="card-header">Point Cacat Per Hari (Weaving01 vs Weaving02)</div>
          <div class="card-body"><canvas id="chartDaily"></canvas></div>
        </div>

        <!-- Top 5 Combined but split per weaving -->
        <div class="card mb-3">
          <div class="card-header">Top 5 (Gabungan) — Perbandingan Weaving01 vs Weaving02</div>
          <div class="card-body">
            <p class="small text-muted"><?= htmlspecialchars($explainTop) ?></p>
            <canvas id="chartTop5"></canvas>
          </div>
        </div>
      </div>

      <!-- RIGHT: summaries + quick lists (aligned vertically) -->
      <div class="col-md-4">
        <!-- Summary Weaving01 (1 baris, 3 kolom) -->
        <div class="card mb-3">
          <div class="card-header">Summary Periode — Weaving01 (Mesin 1-80)</div>
          <div class="card-body">
            <table class="table table-sm mb-1 summary-table">
              <thead>
                <tr>
                  <th>Total Point</th>
                  <th>Total Panjang Kain</th>
                  <th>Ratio (%)</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="value text-danger"><?= fmt($gradeByWeaving['weaving01']['totalPoint']) ?></td>
                  <td class="value"><?= fmt($gradeByWeaving['weaving01']['totalPanjang']) ?></td>
                  <td class="value"><?= fmt($gradeByWeaving['weaving01']['ratio'] * 100) ?>%</td>
                </tr>
              </tbody>
            </table>
            <div class="text-center mb-1">
              <strong>Grade: </strong>
              <span class="badge bg-<?php echo ($gradeByWeaving['weaving01']['grade']=='A'?'success':($gradeByWeaving['weaving01']['grade']=='B'?'warning':'danger')); ?>">
                <?= htmlspecialchars($gradeByWeaving['weaving01']['grade']) ?>
              </span>
            </div>
            <p class="small text-muted mb-0"><?= htmlspecialchars($explainRatio) ?></p>
          </div>
        </div>

        <!-- Summary Weaving02 (1 baris, 3 kolom) -->
        <div class="card mb-3">
          <div class="card-header">Summary Periode — Weaving02 (Mesin 81-170)</div>
          <div class="card-body">
            <table class="table table-sm mb-1 summary-table">
              <thead>
                <tr>
                  <th>Total Point</th>
                  <th>Total Panjang Kain</th>
                  <th>Ratio (%)</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="value text-danger"><?= fmt($gradeByWeaving['weaving02']['totalPoint']) ?></td>
                  <td class="value"><?= fmt($gradeByWeaving['weaving02']['totalPanjang']) ?></td>
                  <td class="value"><?= fmt($gradeByWeaving['weaving02']['ratio'] * 100) ?>%</td>
                </tr>
              </tbody>
            </table>
            <div class="text-center mb-1">
              <strong>Grade: </strong>
              <span class="badge bg-<?php echo ($gradeByWeaving['weaving02']['grade']=='A'?'success':($gradeByWeaving['weaving02']['grade']=='B'?'warning':'danger')); ?>">
                <?= htmlspecialchars($gradeByWeaving['weaving02']['grade']) ?>
              </span>
            </div>
            <p class="small text-muted mb-0"><?= htmlspecialchars($explainRatio) ?></p>
          </div>
        </div>

        <!-- Top 5 Quick List (Weaving01) -->
        <div class="card mb-3">
          <div class="card-header">Top 5 Quick List (Weaving01)</div>
          <div class="card-body p-2">
            <table class="table table-sm table-bordered mb-0">
              <thead><tr><th style="width:32px">No</th><th>Kode</th><th>Nama</th><th style="width:110px;text-align:right">Total Point</th></tr></thead>
              <tbody>
                <?php if (!empty($topW1)): $no1=1; foreach ($topW1 as $r): ?>
                  <tr>
                    <td class="text-center"><?= $no1++ ?></td>
                    <td><?= htmlspecialchars($r['CacatKode']) ?></td>
                    <td><?= htmlspecialchars($r['CacatName']) ?></td>
                    <td class="text-end"><?= fmt($r['TotalPoint']) ?></td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="4" class="text-center">Tidak ada data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top 5 Quick List (Weaving02) -->
        <div class="card mb-3">
          <div class="card-header">Top 5 Quick List (Weaving02)</div>
          <div class="card-body p-2">
            <table class="table table-sm table-bordered mb-0">
              <thead><tr><th style="width:32px">No</th><th>Kode</th><th>Nama</th><th style="width:110px;text-align:right">Total Point</th></tr></thead>
              <tbody>
                <?php if (!empty($topW2)): $no2=1; foreach ($topW2 as $r): ?>
                  <tr>
                    <td class="text-center"><?= $no2++ ?></td>
                    <td><?= htmlspecialchars($r['CacatKode']) ?></td>
                    <td><?= htmlspecialchars($r['CacatName']) ?></td>
                    <td class="text-end"><?= fmt($r['TotalPoint']) ?></td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="4" class="text-center">Tidak ada data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

  </div></div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily chart
const labels = <?= json_encode($labels) ?>;
const weaving01 = <?= json_encode($weaving01) ?>;
const weaving02 = <?= json_encode($weaving02) ?>;
const ctxDaily = document.getElementById('chartDaily')?.getContext('2d');
if (ctxDaily) {
    new Chart(ctxDaily, {
        type: 'line',
        data: { labels: labels, datasets: [
            { label: 'Weaving01', data: weaving01, tension:0.3, fill:false },
            { label: 'Weaving02', data: weaving02, tension:0.3, fill:false }
        ]},
        options: { responsive:true, plugins:{ tooltip:{mode:'index',intersect:false} }, interaction:{mode:'nearest',axis:'x',intersect:false} }
    });
}

// Top5 combined chart split by weaving
const topLabels = <?= json_encode($topLabels) ?>;
const topW1 = <?= json_encode($topW1Data) ?>;
const topW2 = <?= json_encode($topW2Data) ?>;
const ctxTop5 = document.getElementById('chartTop5')?.getContext('2d');
if (ctxTop5) {
    new Chart(ctxTop5, {
        type: 'bar',
        data: {
            labels: topLabels,
            datasets: [
                { label: 'Weaving01', data: topW1, order:1 },
                { label: 'Weaving02', data: topW2, order:2 }
            ]
        },
        options: {
            responsive: true,
            plugins: { tooltip: { mode: 'index', intersect: false } },
            interaction: { mode: 'nearest', axis: 'x', intersect: false },
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
