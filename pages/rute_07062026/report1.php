<?php
// gg_app/pages/rute/report.php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../koneksi.php'; // $serverName, $connectionOptions, $conn (sqlsrv)

// ------ Utils & Auth (sejalan dgn rute.php)
function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache'); header('Expires: 0');
}
nocache();

if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: ../login.php'); exit;
}

/** ===== PDO koneksi dari koneksi.php ===== */
if (!function_exists('pdo')) {
  function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $server = $GLOBALS['serverName'] ?? '(local)';
    $opts   = $GLOBALS['connectionOptions'] ?? [];
    $db  = $opts['Database'] ?? '';
    $uid = $opts['Uid']      ?? '';
    $pwd = $opts['PWD']      ?? '';
    $dsn = "sqlsrv:Server={$server};Database={$db}" . (!empty($opts['TrustServerCertificate'])?";TrustServerCertificate=1":"");
    $pdo = new PDO($dsn, $uid, $pwd, [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
    return $pdo;
  }
}
$pdo = pdo();

/* ===== Pastikan kolom Note ada di RouteActivities (sekali saja) ===== */
$pdo->exec("
  IF COL_LENGTH('dbo.RouteActivities','Note') IS NULL
    ALTER TABLE dbo.RouteActivities ADD Note NVARCHAR(MAX) NULL;
");

/* =============== INPUT (default hari ini) =============== */
$today   = date('Y-m-d');
$driver  = $_GET['driver']  ?? 'all';
$vehicle = $_GET['vehicle'] ?? 'all';
$start   = $_GET['start']   ?? $today;
$end     = $_GET['end']     ?? $today;

/* =============== OPTIONS (DB) =============== */
$driverOpts  = $pdo->query("
  SELECT DISTINCT DriverName
  FROM dbo.Routes
  WHERE DriverName IS NOT NULL AND DriverName <> ''
  ORDER BY DriverName
")->fetchAll(PDO::FETCH_COLUMN);

$vehicleOpts = $pdo->query("SELECT Plate FROM dbo.Vehicles ORDER BY Plate")->fetchAll(PDO::FETCH_COLUMN);

/* =============== KAMUS KENDARAAN =============== */
$vehMap = [];
foreach ($pdo->query("SELECT Plate, Merk, Color FROM dbo.Vehicles")->fetchAll() as $v) {
  $vehMap[$v['Plate']] = ['merk'=>$v['Merk'] ?? '', 'color'=>$v['Color'] ?? ''];
}

/* =============== KAMUS ROUTE MASTER (Name -> Address) =============== */
$routeMasterMap = [];
foreach ($pdo->query("SELECT Name, Address FROM dbo.RouteMaster")->fetchAll() as $m) {
  $key = mb_strtolower(trim((string)$m['Name']), 'UTF-8');
  $routeMasterMap[$key] = (string)($m['Address'] ?? '');
}
function lookup_addr_for_name(?string $placeName): string {
  if ($placeName === null) return '';
  $clean = preg_replace('/\s*\(Rute Awal\)\s*$/ui', '', (string)$placeName);
  $key   = mb_strtolower(trim($clean), 'UTF-8');
  return $GLOBALS['routeMasterMap'][$key] ?? '';
}

/* =============== HELPERS =============== */
function clean_name(?string $s): string {
  $s = (string)$s;
  $s = preg_replace('/\s*\(Rute Awal\)\s*$/ui', '', $s);
  return trim($s) === '' ? '-' : trim($s);
}
$cacheStartName = [];
function get_start_name_for_batch(PDO $pdo, int $batchId): string {
  global $cacheStartName;
  if (isset($cacheStartName[$batchId])) return $cacheStartName[$batchId];
  $st = $pdo->prepare("
    SELECT TOP 1 Name FROM dbo.Routes
    WHERE BatchId=?
    ORDER BY CASE WHEN CHARINDEX('(Rute Awal)', Name) > 0 THEN 0 ELSE 1 END, RouteId ASC
  ");
  $st->execute([$batchId]);
  $name = clean_name($st->fetchColumn() ?: '-');
  return $cacheStartName[$batchId] = $name;
}
/** Normalisasi dash pintar ke minus biasa & rapikan spasi di sekitar '-' */
function normalize_ascii_dash(string $s): string {
  // en dash (U+2013) & em dash (U+2014) -> "-"
  $s = strtr($s, ["\xE2\x80\x93" => "-", "\xE2\x80\x94" => "-"]);
  $s = preg_replace('/\s*-\s*/', ' - ', $s);
  return trim($s);
}

/* =============== AMBIL AKTIVITAS =============== */
$acts = [];
if ($start && $end) {
  if ($start > $end) { $t=$start; $start=$end; $end=$t; }

  // Rentang sargable berdasarkan RouteActivities.CreatedAt
  $rangeStart = $start . ' 00:00:00';
  $rangeEndEx = date('Y-m-d', strtotime($end . ' +1 day')) . ' 00:00:00';

  $sql = "
    SELECT a.ActivityId, a.RouteId, a.FromLocation, a.ToLocation, a.DepartTime, a.ArriveTime, a.Comment, a.Note, a.CreatedAt,
           r.VehiclePlate, r.DriverName, r.BatchId, r.Name AS RouteName, r.RouteDate
    FROM dbo.RouteActivities a
    JOIN dbo.Routes r ON r.RouteId = a.RouteId
    WHERE a.CreatedAt >= :s AND a.CreatedAt < :e2
  ";
  $params = [':s'=>$rangeStart, ':e2'=>$rangeEndEx];
  if ($driver  !== 'all') { $sql .= " AND r.DriverName = :drv";     $params[':drv']  = $driver; }
  if ($vehicle !== 'all') { $sql .= " AND r.VehiclePlate = :veh";   $params[':veh']  = $vehicle; }
  $sql .= " ORDER BY r.BatchId ASC, a.ActivityId ASC";
  $q = $pdo->prepare($sql); $q->execute($params);
  $acts = $q->fetchAll();
}

/* =============== Ambil KETERANGAN & NOTE per RouteId dari baris 'Selesai pengiriman' =============== */
$finishInfoByRouteId = [];
if ($acts) {
  $rids = array_values(array_unique(array_column($acts, 'RouteId')));
  if ($rids) {
    $in = implode(',', array_map('intval', $rids));
    $rows = $pdo->query("
      SELECT ActivityId, RouteId, Comment, Note
      FROM dbo.RouteActivities
      WHERE RouteId IN ($in) AND Comment LIKE 'Selesai pengiriman%'
    ")->fetchAll();

    foreach ($rows as $r) {
      $raw = (string)$r['Comment'];
      $comment = normalize_ascii_dash($raw);

      // Ambil bagian setelah "Selesai pengiriman"
      $body = preg_replace('/^Selesai\s+pengiriman\b[:\-]?\s*/i', '', $comment);

      // Ambil Kategori (sampai sebelum "Qty:" atau akhir)
      $cat  = '';
      if (preg_match('/Kategori:\s*(.*?)(?=\s*(?:-\s*)?Qty:|$)/i', $body, $m)) {
        $cat = trim($m[1]);
      }

      // Ambil Qty (sampai sebelum "Note:" atau akhir)
      $qty  = '';
      if (preg_match('/Qty:\s*(.*?)(?=\s*(?:-\s*)?Note:|$)/i', $body, $m)) {
        $qty = trim($m[1]);
      }

      // Ambil Note (sampai akhir baris)
      $notePart = '';
      if (preg_match('/Note:\s*(.*)$/i', $body, $m)) {
        $notePart = trim($m[1]);
      }

      // Susun keterangan display (pakai minus biasa), sembunyikan nilai '-'
      $parts = [];
      if ($cat      !== '' && $cat      !== '-') $parts[] = 'Kategori: '.$cat;
      if ($qty      !== '' && $qty      !== '-') $parts[] = 'Qty: '.$qty;
      if ($notePart !== '' && $notePart !== '-') $parts[] = 'Note: '.$notePart;

      $finishInfoByRouteId[(int)$r['RouteId']] = [
        'activityId' => (int)$r['ActivityId'],
        'keterangan' => $parts ? implode(' - ', $parts) : '-',
        'note'       => (string)($r['Note'] ?? '')
      ];
    }
  }
}

/* =============== RATAKAN KE BARIS (untuk layar) =============== */
$rowsFlat = [];
if ($acts) {
  $byBatch=[]; foreach ($acts as $a) $byBatch[(int)$a['BatchId']][]=$a;

  foreach ($byBatch as $bid=>$list) {
    $plate=$list[0]['VehiclePlate']??'-'; $driverName=$list[0]['DriverName']??'-';
    $vehText = $plate ?: '-';
    if (!empty($vehMap[$plate])) {
      $add=[]; if($vehMap[$plate]['merk']) $add[]=$vehMap[$plate]['merk']; if($vehMap[$plate]['color']) $add[]=$vehMap[$plate]['color'];
      if ($add) $vehText .= ' - '.implode(' / ',$add);
    }
    $lastFrom=null; $current=null;

    foreach ($list as $a) {
      $comment=trim((string)$a['Comment']); $cmtLow=mb_strtolower($comment,'UTF-8');
      $fromLoc=clean_name($a['FromLocation']?:null); $toLoc=clean_name($a['ToLocation']?:null);
      $depart=$a['DepartTime'] ?: $a['CreatedAt']; $arrive=$a['ArriveTime'] ?: $a['CreatedAt'];

      $startLegIfNone=function() use (&$current,&$lastFrom,$depart,$bid,$fromLoc,$pdo){
        if($current===null){
          $fromText=$lastFrom ?? ($fromLoc ?: get_start_name_for_batch($pdo,$bid));
          $current=['from'=>$fromText?:'-','depart'=>$depart,'to'=>'-','arrive'=>'-','startTs'=>$depart,'endTs'=>null,'routeId'=>null];
        }
      };

      if (strpos($cmtLow,'mulai perjalanan')===0) {
        $startName=$fromLoc ?: get_start_name_for_batch($pdo,$bid);
        $current=['from'=>$startName?:'-','depart'=>$depart,'to'=>'-','arrive'=>'-','startTs'=>$depart,'endTs'=>null,'routeId'=>null];
        $lastFrom=$startName?:'-';

      } elseif (strpos($cmtLow,'menuju lokasi')===0) {
        $startLegIfNone(); if($current['to']==='-'||!$current['to']) $current['to']=$toLoc?:'-';

      } elseif (strpos($cmtLow,'tiba lokasi')===0) {
        $startLegIfNone(); if($current['to']==='-'||!$current['to']) $current['to']=$toLoc?:'-';
        $current['arrive']=$arrive; $current['endTs']=$arrive; $current['routeId']=(int)$a['RouteId'];

        $fi = $finishInfoByRouteId[$current['routeId']] ?? ['activityId'=>0,'keterangan'=>'-','note'=>''];

        $rowsFlat[]=[
          'batchId'=>$bid,
          'vehicle'=>$vehText,'driver'=>$driverName,
          'from'=>$current['from'],'fromAddr'=>lookup_addr_for_name($current['from']),
          'depart'=>$current['depart'],
          'to'=>$current['to'],'toAddr'=>lookup_addr_for_name($current['to']),
          'arrive'=>$current['arrive'],
          'keterangan'=>$fi['keterangan'],
          'noteActId'=>$fi['activityId'],
          'noteText'=>$fi['note'],
          'startTs'=>substr((string)$current['startTs'],0,19),
          'endTs'=>substr((string)$current['endTs'],0,19),
        ];
        $lastFrom=$current['to'] ?: ($toLoc ?: $lastFrom); $current=null;

      } elseif (strpos($cmtLow,'menuju full')===0) {
        $startName=get_start_name_for_batch($pdo,$bid);
        $current=['from'=>$lastFrom ?: ($fromLoc?:'-'),'depart'=>$depart,'to'=>$startName?:'-','arrive'=>'-','startTs'=>$depart,'endTs'=>null,'routeId'=>(int)$a['RouteId']];

      } elseif (strpos($cmtLow,'tiba di full')===0) {
        $startName=get_start_name_for_batch($pdo,$bid);
        if($current===null){
          $current=['from'=>$lastFrom ?: ($fromLoc?:'-'),'depart'=>null,'to'=>$toLoc ?: ($startName?:'-'),'arrive'=>'-','startTs'=>$depart ?: $a['CreatedAt'],'endTs'=>null,'routeId'=>(int)$a['RouteId']];
        }
        if($current['to']==='-'||!$current['to']) $current['to']=$toLoc ?: ($startName?:'-');
        $current['arrive']=$arrive; $current['endTs']=$arrive;

        $fi = $finishInfoByRouteId[$current['routeId']] ?? ['activityId'=>0,'keterangan'=>'-','note'=>''];

        $rowsFlat[]=[
          'batchId'=>$bid,
          'vehicle'=>$vehText,'driver'=>$driverName,
          'from'=>$current['from'],'fromAddr'=>lookup_addr_for_name($current['from']),
          'depart'=>$current['depart'],
          'to'=>$current['to'],'toAddr'=>lookup_addr_for_name($current['to']),
          'arrive'=>$current['arrive'],
          'keterangan'=>$fi['keterangan'],
          'noteActId'=>$fi['activityId'],
          'noteText'=>$fi['note'],
          'startTs'=>substr((string)$current['startTs'],0,19),
          'endTs'=>substr((string)$current['endTs'],0,19),
        ];
        $lastFrom=$current['to'] ?: ($startName?:'-'); $current=null;
      }
    }
    if ($current!==null) {
      $fi = $finishInfoByRouteId[$current['routeId']??0] ?? ['activityId'=>0,'keterangan'=>'-','note'=>''];
      $rowsFlat[]=[
        'batchId'=>$bid,
        'vehicle'=>$vehText,'driver'=>$driverName,
        'from'=>$current['from'],'fromAddr'=>lookup_addr_for_name($current['from']),
        'depart'=>$current['depart'],
        'to'=>$current['to'],'toAddr'=>lookup_addr_for_name($current['to']),
        'arrive'=>$current['arrive'],
        'keterangan'=>$fi['keterangan'],
        'noteActId'=>$fi['activityId'],
        'noteText'=>$fi['note'],
        'startTs'=>substr((string)$current['startTs'],0,19),
        'endTs'=>substr((string)$current['endTs']??'',0,19),
      ];
    }
  }
}

/* =============== GROUP UNTUK MERGE TABEL (layar) =============== */
$rowsByBatch = [];
if ($rowsFlat) foreach ($rowsFlat as $r) { $rowsByBatch[$r['batchId']][] = $r; }

// ---------- Template AdminLTE ----------
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Rute Harian</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    body{background:#f4f6f9;}
    .content-wrapper{background:#f4f6f9;}
    .card-soft{border:0;border-radius:18px;box-shadow:0 6px 18px rgba(16,24,40,.06);}
    .card-soft .card-header{background:#fff;border-bottom:1px solid #e9ecef;border-radius:18px 18px 0 0;font-weight:600;}
    .filter-grid .form-group{margin-bottom:.6rem}

    table thead th,
    table tbody td{
      text-align:center !important;
      vertical-align:middle !important;
    }
    .inline-note{
      min-height:44px; padding:.35rem .5rem; border-radius:12px;
      display:flex; align-items:center; justify-content:center;
    }
    .inline-note[contenteditable="false"]{background:#f3f4f6; cursor:pointer}
    .inline-note[contenteditable="true"]{background:#fff; outline:2px solid #80bdff}

    .place .name { font-weight: 600; line-height: 1.1; }
    .place .addr { font-size: 11px; color: #6c757d; line-height: 1.2; }

    .table-responsive{overflow-x:auto;}
    .nowrap{white-space:nowrap;}
  </style>
</head>
<body>
<div class="content-wrapper">
  <!-- Konten -->
  <section class="content">
    <div class="container-fluid">

      <!-- Filter -->
      <div class="card card-soft mb-3">
        <div class="card-header">Filter</div>
        <div class="card-body">
          <form method="get" class="filter-grid">
            <div class="form-row align-items-end">
              <div class="col-md-3">
                <div class="form-group">
                  <label class="small mb-1">Driver</label>
                  <select name="driver" class="custom-select custom-select-sm">
                    <option value="all" <?= $driver==='all'?'selected':''; ?>>Semua Driver</option>
                    <?php foreach($driverOpts as $d): ?>
                      <option value="<?= htmlspecialchars($d) ?>" <?= $driver===$d?'selected':''; ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="small mb-1">Kendaraan</label>
                  <select name="vehicle" class="custom-select custom-select-sm">
                    <option value="all" <?= $vehicle==='all'?'selected':''; ?>>Semua Kendaraan</option>
                    <?php foreach($vehicleOpts as $v): ?>
                      <option value="<?= htmlspecialchars($v) ?>" <?= $vehicle===$v?'selected':''; ?>><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-6 col-md-2">
                <div class="form-group">
                  <label class="small mb-1">Start</label>
                  <input name="start" type="date" class="form-control form-control-sm" value="<?= htmlspecialchars($start ?? '') ?>">
                </div>
              </div>
              <div class="col-6 col-md-2">
                <div class="form-group">
                  <label class="small mb-1">End</label>
                  <input name="end" type="date" class="form-control form-control-sm" value="<?= htmlspecialchars($end ?? '') ?>">
                </div>
              </div>
              <div class="col-12 col-md-2 d-flex">
                <button class="btn btn-primary btn-sm mr-2 flex-fill">Terapkan</button>
                <a class="btn btn-outline-secondary btn-sm mr-2 flex-fill" href="report.php">Reset</a>
                <button id="btnPdf" type="button" class="btn btn-danger btn-sm flex-fill"> PDF</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabel hasil -->
      <div class="card card-soft">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th class="nowrap">No</th>
                  <th class="nowrap">Jenis &amp; No Kendaraan</th>
                  <th class="nowrap">Nama Sopir</th>
                  <th class="nowrap">From</th>
                  <th class="nowrap">Jam Berangkat</th>
                  <th class="nowrap">To</th>
                  <th class="nowrap">Jam Tiba</th>
                  <th class="nowrap">Keterangan</th>
                  <th class="nowrap">Temuan/Komentar (per from-to)</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (!$rowsByBatch) {
                  echo '<tr><td colspan="9" class="text-center text-muted">Tidak ada data.</td></tr>';
                } else {
                  $no = 1;
                  foreach ($rowsByBatch as $bid => $rows) {
                    $rowspan = count($rows);
                    $veh = $rows[0]['vehicle'] ?? '-';
                    $drv = $rows[0]['driver']  ?? '-';
                    $first = true;

                    foreach ($rows as $r) {
                      $safeDriver = htmlspecialchars($r['driver'] ?: '-');
                      $safeFrom   = htmlspecialchars($r['from']   ?: '-');
                      $safeTo     = htmlspecialchars($r['to']     ?: '-');
                      $safeBatch  = (int)$r['batchId'];

                      echo '<tr>';

                      if ($first) {
                        echo '<td rowspan="'.$rowspan.'">'.$no.'</td>';
                        echo '<td rowspan="'.$rowspan.'">'.htmlspecialchars($veh).'</td>';
                        echo '<td rowspan="'.$rowspan.'">'.htmlspecialchars($drv).'</td>';
                        $first = false;
                        $no++;
                      }

                      // FROM: nama + alamat
                      $fromAddr = $r['fromAddr'] ?? '';
                      echo '<td class="place">
                              <div class="name">'.htmlspecialchars($r['from'] ?: '-').'</div>
                              <div class="addr">'.htmlspecialchars($fromAddr ?: '-').'</div>
                            </td>';

                      echo '<td>'.($r['depart'] ? htmlspecialchars(substr($r['depart'],11,8)) : '-').'</td>';

                      // TO: nama + alamat
                      $toAddr = $r['toAddr'] ?? '';
                      echo '<td class="place">
                              <div class="name">'.htmlspecialchars($r['to'] ?: '-').'</div>
                              <div class="addr">'.htmlspecialchars($toAddr ?: '-').'</div>
                            </td>';

                      echo '<td>'.($r['arrive'] ? htmlspecialchars(substr($r['arrive'],11,8)) : '-').'</td>';
                      echo '<td>'.htmlspecialchars($r['keterangan'] ?: '-').'</td>';

                      // Inline Note: data-activity berisi ActivityId dari 'Selesai pengiriman'
                      $noteText = (string)($r['noteText'] ?? '');
                      $noteActId = (int)($r['noteActId'] ?? 0);
                      echo '<td>
                              <div class="inline-note"
                                   contenteditable="false"
                                   title="Double-click untuk mengedit"
                                   data-activity="'.$noteActId.'"
                                   data-driver="'.$safeDriver.'"
                                   data-from="'.$safeFrom.'"
                                   data-to="'.$safeTo.'"
                                   data-batch="'.$safeBatch.'"
                              >'.htmlspecialchars($noteText).'</div>
                            </td>';

                      echo '</tr>';
                    }
                  }
                }
                ?>
              </tbody>
            </table>
          </div><!-- /table-responsive -->
        </div>
      </div>

    </div>
  </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function saveInlineNote(activityId, noteText){
  const fd = new FormData();
  fd.append('action', 'save_inline_note');      // <- api.php action
  fd.append('activity_id', activityId);
  fd.append('note', noteText || '');
  return fetch('api.php', { method:'POST', body: fd })
    .then(r=>r.json())
    .then(j=>{ if(!j.success) alert('Gagal: ' + (j.error||'')); return j.success; })
    .catch(()=> { alert('Request error'); return false; });
}

// Inline editor
$(document).on('dblclick', '.inline-note', function(){
  if (this.getAttribute('contenteditable')==='true') return;
  // hanya izinkan edit kalau punya ActivityId valid (baris 'Selesai pengiriman')
  if (!Number(this.dataset.activity || 0)) {
    alert('Catatan hanya tersedia setelah rute diselesaikan (baris "Selesai pengiriman").');
    return;
  }
  this.setAttribute('contenteditable','true');
  const range=document.createRange(); range.selectNodeContents(this);
  const sel=window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
  this.focus();
});

$(document).on('blur', '.inline-note[contenteditable="true"]', async function(){
  const el=this;
  const actId = Number(el.dataset.activity || 0);
  const ok = await saveInlineNote(actId, el.innerText.trim());
  if(ok) el.setAttribute('contenteditable','false');
});

// Tombol Generate PDF: buka tab baru dengan query filter aktif
$('#btnPdf').on('click', function(){
  const params = new URLSearchParams({
    start:   $('input[name="start"]').val()   || '',
    end:     $('input[name="end"]').val()     || '',
    driver:  $('select[name="driver"]').val() || 'all',
    vehicle: $('select[name="vehicle"]').val()|| 'all'
  });
  window.open('generate_pdf.php?' + params.toString(), '_blank');
});
</script>
</body>
</html>
