<?php // /gg_app/pages/rute/driver.php — Driver Dashboard
// Penambahan: flag 'arrived' per-rute agar setelah reload tombol jadi "Selesai" bila sudah pernah TIBA.
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../koneksi.php'; // harus menyediakan $conn (sqlsrv_connect)

/* ---------- Idle timeout: auto logout setelah 1 hari tidak aktif ---------- */
$IDLE_TIMEOUT = 86400; // 24 jam

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - (int)$_SESSION['LAST_ACTIVITY']) > $IDLE_TIMEOUT) {
    // Hapus semua data session
    $_SESSION = [];

    // Hapus cookie session di browser
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    // Redirect ke login (session sudah tidak ada)
    header('Location: /login.php');
    exit;
}

// Update timestamp aktivitas setiap kali halaman ini diakses
$_SESSION['LAST_ACTIVITY'] = time();

/* ---------- Helpers ---------- */
function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}
function utf8ize_mixed($m) {
  if (is_array($m)) { $o=[]; foreach($m as $k=>$v){ $o[$k]=utf8ize_mixed($v);} return $o; }
  if ($m instanceof DateTimeInterface) return $m->format('Y-m-d H:i:s');
  if (is_string($m) && !mb_check_encoding($m,'UTF-8')) {
    return mb_convert_encoding($m,'UTF-8','UTF-8, ISO-8859-1, Windows-1252, ASCII');
  }
  return $m;
}
function sqlerr(): string {
  $es = sqlsrv_errors(SQLSRV_ERR_ERRORS);
  if (!$es) return 'Unknown database error';
  $msgs = array_map(fn($e)=>"[{$e['SQLSTATE']}] {$e['code']} {$e['message']}", $es);
  return implode(' | ', $msgs);
}
function has_column($conn, string $table, string $column): bool {
  $st = sqlsrv_query(
    $conn,
    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=? AND COLUMN_NAME=?",
    [$table, $column]
  );
  $ok = ($st && sqlsrv_fetch_array($st, SQLSRV_FETCH_NUMERIC));
  if ($st) sqlsrv_free_stmt($st);
  return (bool)$ok;
}

nocache();
date_default_timezone_set('Asia/Jakarta');

/* ---------- Auth ---------- */
if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: /login.php'); exit;
}
$USER_LOGIN = $_SESSION['UserName'] ?? '';

if (!$conn) {
  die("Koneksi ke database gagal: ".sqlerr());
}

/* ---------- Ambil nama driver default dari login ---------- */
function get_driver_fullname_from_login($conn, string $username): ?string {
  $st = sqlsrv_query(
    $conn,
    "SELECT TOP 1 e.nama_lengkap
       FROM dbo.SMUserMs u
       JOIN dbo.m_emp e ON e.id_emp = u.EmpId
      WHERE u.UserName = ?",
    [$username]
  );
  if ($st && ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) {
    sqlsrv_free_stmt($st);
    $nm = trim((string)($row['nama_lengkap'] ?? ''));
    return $nm !== '' ? $nm : null;
  }
  return null;
}
$driverName = trim((string)($_GET['driver'] ?? ''));
if ($driverName === '') {
  $driverName = get_driver_fullname_from_login($conn, $USER_LOGIN) ?? $USER_LOGIN;
}

/* ============================================================
   Tentukan batch aktif (tanpa future). Prioritas carry-over.
=============================================================== */
$today = date('Y-m-d');
$hasLockedCol = has_column($conn, 'RouteBatch', 'Locked');

$batches = []; // [BatchId, RouteDate, NotDone, Locked]
$sql = "
  SELECT
    r.BatchId,
    CONVERT(varchar(10), MIN(r.RouteDate), 23) AS RouteDate,
    SUM(CASE WHEN LOWER(r.Status) = 'done' THEN 0 ELSE 1 END) AS NotDone
    " . ($hasLockedCol ? ", ISNULL(MAX(CAST(b.Locked AS INT)), 0) AS Locked" : ", CAST(0 AS INT) AS Locked") . "
  FROM dbo.Routes r
  " . ($hasLockedCol ? "LEFT JOIN dbo.RouteBatch b ON b.BatchId = r.BatchId" : "") . "
  WHERE r.DriverName = ?
  GROUP BY r.BatchId
";
$st = sqlsrv_query($conn, $sql, [$driverName]);
if ($st) {
  while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $batches[] = [
      'BatchId'   => (int)$r['BatchId'],
      'RouteDate' => (string)$r['RouteDate'],
      'NotDone'   => (int)$r['NotDone'],
      'Locked'    => (int)$r['Locked']
    ];
  }
  sqlsrv_free_stmt($st);
}

$activeBatchId = null;
$activeBatchDate = null;

/* 1) Cari pending s/d hari ini (<= today) */
$pendingPastOrToday = array_values(array_filter(
  $batches,
  fn($x) => $x['NotDone'] > 0 && $x['RouteDate'] <= $today
));
if (!empty($pendingPastOrToday)) {
  usort($pendingPastOrToday, function($a, $b) {
    if ($a['RouteDate'] === $b['RouteDate']) return $a['BatchId'] <=> $b['BatchId'];
    return strcmp($a['RouteDate'], $b['RouteDate']); // paling lama dulu
  });
  $activeBatchId   = (int)$pendingPastOrToday[0]['BatchId'];
  $activeBatchDate = (string)$pendingPastOrToday[0]['RouteDate'];
}

/* ---------- Ambil rute utk batch aktif (jika ada) ---------- */
$rows = [];
if ($activeBatchId) {
  $st = sqlsrv_query(
    $conn,
    "SELECT r.RouteId, r.BatchId, r.Name, r.Address, r.VehiclePlate, r.DriverName, r.Status,
            CONVERT(varchar(10), r.RouteDate, 23) AS RouteDate
     FROM dbo.Routes r
     WHERE r.DriverName = ? AND r.BatchId = ?
     ORDER BY r.RouteId ASC",
    [$driverName, $activeBatchId]
  );
  if ($st) { while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $rows[] = $r; sqlsrv_free_stmt($st); }
}

/* ---------- Info kendaraan aktif ---------- */
$vehiclePlate = $rows[0]['VehiclePlate'] ?? '';
$vehInfo = ['plate'=>$vehiclePlate,'merk'=>'','color'=>''];
if ($vehiclePlate) {
  $qv = sqlsrv_query($conn, "SELECT TOP 1 Merk, Color FROM dbo.Vehicles WHERE Plate=?", [$vehiclePlate]);
  if ($qv && ($v = sqlsrv_fetch_array($qv, SQLSRV_FETCH_ASSOC))) {
    $vehInfo['merk']  = (string)($v['Merk'] ?? '');
    $vehInfo['color'] = (string)($v['Color'] ?? '');
  }
  if ($qv) sqlsrv_free_stmt($qv);
}

/* ---------- Rute awal di batch aktif ---------- */
$ridStart = null;
if ($activeBatchId) {
  $q = sqlsrv_query(
    $conn,
    "SELECT TOP 1 RouteId
     FROM dbo.Routes
     WHERE BatchId = ?
     ORDER BY CASE WHEN CHARINDEX('(Rute Awal)', Name) > 0 THEN 0 ELSE 1 END, RouteId ASC",
    [$activeBatchId]
  );
  if ($q) { $ridStart = (int)(sqlsrv_fetch_array($q, SQLSRV_FETCH_NUMERIC)[0] ?? 0); sqlsrv_free_stmt($q); }
}

/* ---------- Buat map 'arrived' untuk semua RouteId di batch aktif ---------- */
$arrivedMap = [];        // routeId => 1/0
$initialArrived = false; // untuk rute awal (FULL)
if (!empty($rows)) {
  $ids = array_map(fn($r)=>(int)$r['RouteId'], $rows);
  if ($ridStart) $ids[] = (int)$ridStart;
  $ids = array_values(array_unique($ids));
  // Susun parameter dinamis IN (...)
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $qArr = sqlsrv_query(
    $conn,
    "SELECT RouteId,
            MAX(CASE WHEN Comment IN ('Tiba lokasi','Tiba Di FULL') OR ArriveTime IS NOT NULL THEN 1 ELSE 0 END) AS Arrived
     FROM dbo.RouteActivities
     WHERE RouteId IN ($placeholders)
     GROUP BY RouteId",
    $ids
  );
  if ($qArr) {
    while ($r = sqlsrv_fetch_array($qArr, SQLSRV_FETCH_ASSOC)) {
      $arrivedMap[(int)$r['RouteId']] = ((int)$r['Arrived'] > 0) ? 1 : 0;
    }
    sqlsrv_free_stmt($qArr);
  }
  if ($ridStart && isset($arrivedMap[$ridStart])) $initialArrived = ($arrivedMap[$ridStart] === 1);
}

/* ---------- Cek FULL trip & detail rute awal ---------- */
$fullStart=false;
$fullFinish=false;
$initialRouteRow=null;
if ($ridStart) {
  $qs = sqlsrv_query($conn, "SELECT COUNT(*) AS C FROM dbo.RouteActivities WHERE RouteId=? AND Comment='Menuju FULL'", [$ridStart]);
  if ($qs) { $fullStart = ((int)(sqlsrv_fetch_array($qs, SQLSRV_FETCH_ASSOC)['C'] ?? 0) > 0); sqlsrv_free_stmt($qs); }
  $qf = sqlsrv_query($conn, "SELECT COUNT(*) AS C FROM dbo.RouteActivities WHERE RouteId=? AND Comment='Tiba Di FULL'", [$ridStart]);
  if ($qf) { $fullFinish = ((int)(sqlsrv_fetch_array($qf, SQLSRV_FETCH_ASSOC)['C'] ?? 0) > 0); sqlsrv_free_stmt($qf); }

  $q2 = sqlsrv_query(
    $conn,
    "SELECT RouteId, Name, Address, VehiclePlate, DriverName,
            CONVERT(varchar(10), RouteDate, 23) AS RouteDate, Status
     FROM dbo.Routes WHERE RouteId=?",
    [$ridStart]
  );
  if ($q2 && ($row = sqlsrv_fetch_array($q2, SQLSRV_FETCH_ASSOC))) $initialRouteRow = $row;
  if ($q2) sqlsrv_free_stmt($q2);

  // refresh veh info bila beda
  if ($initialRouteRow && $initialRouteRow['VehiclePlate'] && $initialRouteRow['VehiclePlate'] !== $vehiclePlate) {
    $vehiclePlate = $initialRouteRow['VehiclePlate'];
    $vehInfo = ['plate'=>$vehiclePlate,'merk'=>'','color'=>''];
    $qv = sqlsrv_query($conn, "SELECT TOP 1 Merk, Color FROM dbo.Vehicles WHERE Plate=?", [$vehiclePlate]);
    if ($qv && ($v = sqlsrv_fetch_array($qv, SQLSRV_FETCH_ASSOC))) {
      $vehInfo['merk']  = (string)($v['Merk'] ?? '');
      $vehInfo['color'] = (string)($v['Color'] ?? '');
    }
    if ($qv) sqlsrv_free_stmt($qv);
  }
}

/* ---------- Susun routes (tanpa rute awal) + flag arrived ---------- */
$routes = [];
foreach ($rows as $r) {
  $isStart = ((int)$r['RouteId'] === (int)$ridStart) || (stripos((string)$r['Name'],'(Rute Awal)') !== false);
  if ($isStart) continue;
  $rid = (int)$r['RouteId'];
  $routes[] = [
    'id'      => $rid,
    'batchId' => (int)$r['BatchId'],
    'name'    => (string)$r['Name'],
    'address' => (string)$r['Address'],
    'vehicle' => (string)$r['VehiclePlate'],
    'driver'  => (string)$r['DriverName'],
    'status'  => strtolower((string)$r['Status']),
    'date'    => (string)$r['RouteDate'],
    'arrived' => (isset($arrivedMap[$rid]) && $arrivedMap[$rid] === 1) ? true : false  // <— NEW
  ];
}

/* ---------- Banner ---------- */
$showCarryOverBanner = ($activeBatchId && $activeBatchDate && $activeBatchDate < $today);
$showNoTodayBanner   = (!$activeBatchId && !$showCarryOverBanner);

/* ---------- Payload ke JS ---------- */
$server = [
  'driver'=>['name'=>$driverName,'vehicle'=>$vehiclePlate,'batchId'=>$activeBatchId,'date'=>$activeBatchDate],
  'vehicleInfo'=>$vehInfo,
  'routes'=>$routes,
  'fullTrip'=>[
    'startLogged'=>$fullStart,
    'finishLogged'=>$fullFinish,
    'initialArrived'=>$initialArrived // <— NEW: rute awal sudah tiba FULL?
  ],
  'initialRoute'=> $initialRouteRow ? [
    'id'=>(int)$initialRouteRow['RouteId'],
    'name'=>(string)$initialRouteRow['Name'],
    'address'=>(string)$initialRouteRow['Address'],
    'vehicle'=>(string)$initialRouteRow['VehiclePlate'],
    'driver'=>(string)$initialRouteRow['DriverName'],
    'date'=>(string)$initialRouteRow['RouteDate'],
    'status'=>strtolower((string)($initialRouteRow['Status'] ?? 'open')),
  ] : null
];
$server = utf8ize_mixed($server);

// URL api.php (sefolder)
$API_URL = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/api.php';

/* ---------- Layout ---------- */
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

  <style>
    :root{ --bg:#f4f6f9; --card:#fff; --muted:#6b7280; --open:#0d6efd; --inprog:#ff9800; --done:#28a745; --soft-shadow:0 6px 20px rgba(16,24,40,0.06); }
    body{background:var(--bg)}
    .top-stats-simple{display:flex;gap:12px;margin-bottom:18px;align-items:stretch;flex-wrap:wrap}
    .stat-small{flex:1 1 140px;background:var(--card);border-radius:10px;padding:14px;box-shadow:var(--soft-shadow);display:flex;flex-direction:column;gap:6px;min-width:140px}
    .stat-label{font-size:13px;color:var(--muted);font-weight:600}
    .stat-number{font-weight:800;font-size:22px}

    .driver-panel{background:var(--card);border-radius:12px;padding:14px;box-shadow:var(--soft-shadow);}
    .driver-left{display:flex;gap:12px;align-items:center}
    .driver-photo{width:56px;height:56px;border-radius:10px;background:linear-gradient(135deg,#eef6ff,#e1f0ff);display:flex;align-items:center;justify-content:center;color:var(--open);font-weight:700;font-size:18px}
    .driver-info{min-width:220px;display:flex;flex-direction:column;gap:6px}
    .vehicle-badge{background:#eef5ff;border-radius:8px;padding:6px 10px;color:var(--open);font-weight:700;display:inline-block}
    .initial-route{background:#fbfdff;border-radius:10px;padding:10px 12px;border:1px solid rgba(15,23,42,0.04);min-width:240px}
    .routes-wrap{margin-top:14px}
    .route-card{background:linear-gradient(180deg,#fff,#fbfdff);border-radius:12px;display:flex;align-items:flex-start;gap:12px;padding:14px;margin-bottom:12px;border:1px solid rgba(15,23,42,0.04);transition:.14s}
    .route-left{flex:1}
    .route-title{font-weight:700}
    .status-pill{min-width:88px;display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;color:#fff;font-weight:700;font-size:13px}
    .status-open{background:linear-gradient(135deg,var(--open),#3b82f6)}
    .status-inprog{background:linear-gradient(135deg,var(--inprog),#ffb86b)}
    .status-done{background:linear-gradient(135deg,var(--done),#66d19e)}

    .right-actions{display:flex;flex-direction:column;gap:8px}
    .btn-start{border-radius:10px;padding:.48rem .9rem;font-weight:700}

    @media (max-width: 576px){
      .right-actions{align-items:stretch}
      .btn-start{width:100%; font-size:16px}
      .initial-route{margin-top:10px}
    }
    @media (max-width: 767.98px){ #initialRouteBox { display:none !important; } }
    @media (min-width: 768px){   #initialRouteBox { display:block !important; } }
  </style>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <h3 class="m-0">Driver Dashboard</h3>
      <div class="text-muted small">
        <i class="fas fa-user mr-1"></i><?= htmlspecialchars($driverName ?: '-', ENT_QUOTES, 'UTF-8') ?>
        <?php if ($activeBatchDate): ?>
          <span class="ml-2 badge badge-light"><?= htmlspecialchars($activeBatchDate) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">

      <?php if ($activeBatchId && $activeBatchDate < $today): ?>
        <div class="alert alert-warning">
          <strong>Perhatian!</strong> Anda masih memiliki rute tanggal
          <b><?= htmlspecialchars($activeBatchDate) ?></b> yang belum selesai.
          <br/>Selesaikan rute ini karena sudah <b>berbeda hari</b> (hari ini: <?= htmlspecialchars($today) ?>).
        </div>
      <?php elseif (!$activeBatchId): ?>
        <div class="alert alert-info mb-3">
          <strong>Hari ini belum ada rute.</strong>
          Anda akan melihat rute di sini jika ada penugasan untuk tanggal <b><?= htmlspecialchars($today) ?></b>.
        </div>
      <?php endif; ?>

      <!-- Kartu ringkas -->
      <div class="top-stats-simple">
        <div class="stat-small"><div class="stat-label">Status Open</div><div class="stat-number" id="statOpen">-</div></div>
        <div class="stat-small"><div class="stat-label">Status Inprogress</div><div class="stat-number" id="statInprog">-</div></div>
        <div class="stat-small"><div class="stat-label">Status Selesai</div><div class="stat-number" id="statDone">-</div></div>
      </div>

      <!-- Panel driver -->
      <div class="driver-panel mb-3">
        <div class="row w-100">
          <div class="col-12 col-md-8">
            <div class="driver-left">
              <div class="driver-photo" id="driverPhoto"><?= htmlspecialchars(mb_strtoupper(mb_substr($driverName,0,1),'UTF-8')) ?></div>
              <div class="driver-info">
                <div style="font-size:13px;color:#6b7280">Nama Driver</div>
                <div style="font-weight:800;font-size:18px" id="driverName"><?= htmlspecialchars($driverName) ?></div>
                <div style="margin-top:6px"><span class="vehicle-badge" id="vehicleVal"><?= htmlspecialchars($vehInfo['plate'] ?: '-') ?></span></div>
              </div>

              <div id="initialRouteBox" class="initial-route d-none d-md-block ml-2">
                <div>
                  <div style="font-weight:700" id="initialRouteName">-</div>
                  <div style="color:#6b7280;font-size:13px" id="initialRouteAddress">-</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Aksi kanan -->
          <div class="col-12 col-md-4 mt-2 mt-md-0">
            <div class="right-actions text-md-right">
              <button id="btnStart" class="btn btn-primary btn-start">
                <i class="fas fa-play mr-2"></i>Mulai Perjalanan
              </button>
              <div id="fullTripInfo" class="small text-muted" style="display:none"></div>
            </div>
          </div>

          <!-- initial-route versi mobile -->
          <div class="col-12 d-md-none mt-2">
            <div id="initialRouteBoxMobile" class="initial-route" style="display:none">
              <div>
                <div style="font-weight:700" id="initialRouteNameMobile">-</div>
                <div style="color:#6b7280;font-size:13px" id="initialRouteAddressMobile">-</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Daftar rute -->
      <div class="routes-wrap">
        <?php if (!$activeBatchId): ?>
          <div class="alert alert-light border">Tidak ada rute untuk ditampilkan.</div>
        <?php endif; ?>
        <div id="routesList"></div>
        <div class="d-flex justify-content-center align-items-center mt-2">
          <button id="prevPage" class="btn btn-light btn-sm mr-2">Prev</button>
          <div id="pageInfo" style="padding:8px 12px;color:#6b7280"></div>
          <button id="nextPage" class="btn btn-light btn-sm ml-2">Next</button>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



<script>
  // Server payload untuk JS.
  window.__SERVER   = <?= json_encode($server, JSON_UNESCAPED_UNICODE) ?>;
  window.API_URL    = <?= json_encode($API_URL, JSON_UNESCAPED_SLASHES) ?>;
  window.LOGIN_USER = <?= json_encode($USER_LOGIN, JSON_UNESCAPED_UNICODE) ?>;

  // Catatan penting untuk driver.js:
  // - Jika r.status === 'inprog' && r.arrived === true => render tombol "Selesai"
  // - Jika r.status === 'inprog' && !r.arrived => render tombol "Tiba"
  // - Jika r.status === 'open' => "Mulai"
  // - Jika r.status === 'done' => badge selesai
  $.ajaxSetup({ headers: {'X-Requested-With':'XMLHttpRequest'}, cache:false });
</script>

<!-- Bersihkan storage/cache ketika masuk ke halaman driver -->
<script>
  (function () {
    try {
      if (window.localStorage)  localStorage.clear();
      if (window.sessionStorage) sessionStorage.clear();
      if ('caches' in window) {
        caches.keys().then(function (names) {
          for (const name of names) {
            caches.delete(name);
          }
        });
      }
    } catch (e) {
      if (window.console && console.warn) {
        console.warn('Gagal clear storage/cache:', e);
      }
    }
  })();
</script>

<!-- Modal finish rute -->
<div class="modal fade" id="finishModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="finishForm">
        <div class="modal-header">
          <h5 class="modal-title">Selesaikan Rute</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
        <label>Kategori (opsional, tapi jika diisi maka Qty & Note wajib)</label>
            <select id="inputCategory" class="form-control form-control-sm">
              <option value="">- pilih -</option>
              <option value="menaikan">Menaikan Barang</option>
              <option value="menurunkan">Menurunkan Barang</option>
            </select>
          </div>
          <div class="form-group">
            <label>Qty (opsional)</label>
            <input id="inputQty" type="number" min="0" class="form-control form-control-sm" />
          </div>
          <div class="form-group">
            <label>Note (opsional)</label>
            <input id="inputNote" type="text" class="form-control form-control-sm" />
          </div>
          <input type="hidden" id="finishRouteId" />
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm">Simpan & Selesai</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="toast-fixed" id="toastContainer" style="position:fixed;right:12px;bottom:12px;z-index:1080"></div>

<script src="assets/js/driver.js" charset="utf-8"></script>

