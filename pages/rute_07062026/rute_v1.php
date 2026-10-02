<?php
// pages/rute/rute.php — Single-file (sqlsrv, session + permissions + SSR + JS)

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../koneksi.php'; // harus menyediakan $conn (sqlsrv_connect)

// ---------- Helpers ----------
function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache'); header('Expires: 0');
}
function json_out(array $data): never {
  header('Content-Type: application/json; charset=utf-8');
  nocache(); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
nocache();
date_default_timezone_set('Asia/Jakarta');

// ---------- Auth wajib login ----------
if (!isset($_SESSION['UserName'])) {
  if (($_GET['action'] ?? $_POST['action'] ?? null)) {
    http_response_code(401);
    json_out(['success'=>false, 'error'=>'Unauthorized']);
  }
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: ../login.php'); exit;
}
$LOGGED_IN_USER = $_SESSION['UserName'] ?? '';

// ---------- Permissions ----------
if (!$conn) { die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true)); }

$groupId = $_SESSION['GroupId'] ?? null;
$menuId  = 46;
$permissions = ['CanView'=>1,'CanAdd'=>1,'CanEdit'=>1,'CanDelete'=>1];

if ($groupId !== null) {
  $st = sqlsrv_query($conn,
    "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?",
    [$groupId, $menuId]
  );
  if ($st && ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) $permissions = $row;
  if ($st) sqlsrv_free_stmt($st);
}
$error_message = empty($permissions['CanView']) ? "Anda tidak memiliki hak untuk melihat halaman ini." : null;

/* ====================== HELPERS UTF-8 + DATETIME ====================== */
function dt_to_str($v): ?string {
  return ($v instanceof DateTimeInterface) ? $v->format('Y-m-d H:i:s') : (is_string($v) ? $v : null);
}
function utf8ize_mixed($m) {
  if (is_array($m)) {
    $out = [];
    foreach ($m as $k=>$v) $out[$k] = utf8ize_mixed($v);
    return $out;
  } elseif (is_string($m)) {
    if (!mb_check_encoding($m, 'UTF-8')) {
      $m = mb_convert_encoding($m, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, ASCII');
    }
    return $m;
  } elseif ($m instanceof DateTimeInterface) {
    return $m->format('Y-m-d H:i:s');
  }
  return $m;
}

/* ====================== DATA SSR dari DB (sqlsrv) ====================== */
$vehicles = $drivers = $routeMasters = $routes = $activities = [];

// Vehicles
$st = sqlsrv_query($conn, "SELECT VehicleId, Plate, Merk, Model, Color FROM dbo.Vehicles ORDER BY Plate");
if ($st) { while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $vehicles[] = $r; sqlsrv_free_stmt($st); }

// Drivers (GroupId 29) - SMUserMs x m_emp (sesuai permintaan)
$st = sqlsrv_query(
  $conn,
  "SELECT u.EmpId, e.nama_lengkap
   FROM dbo.SMUserMs u
   JOIN dbo.m_emp e ON e.id_emp = u.EmpId
   WHERE u.GroupId = 29
   ORDER BY e.nama_lengkap"
);
if ($st) { while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $drivers[] = $r; sqlsrv_free_stmt($st); }

// RouteMaster
$st = sqlsrv_query($conn, "SELECT RouteMasterId, Name, Address FROM dbo.RouteMaster ORDER BY Name");
if ($st) { while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $routeMasters[] = $r; sqlsrv_free_stmt($st); }

// Routes
$st = sqlsrv_query($conn, "
  SELECT RouteId, BatchId, Name, Address, VehiclePlate, DriverName, Status,
         CONVERT(varchar(10), RouteDate, 23) AS RouteDate
  FROM dbo.Routes ORDER BY RouteId
");
if ($st) { while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $routes[] = $r; sqlsrv_free_stmt($st); }

// Activities
$st = sqlsrv_query($conn, "
  SELECT ActivityId, RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, CreatedAt
  FROM dbo.RouteActivities ORDER BY CreatedAt DESC
");
if ($st) {
  while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $r['DepartTime'] = dt_to_str($r['DepartTime'] ?? null);
    $r['ArriveTime'] = dt_to_str($r['ArriveTime'] ?? null);
    $r['CreatedAt']  = dt_to_str($r['CreatedAt']  ?? null);
    $activities[] = $r;
  }
  sqlsrv_free_stmt($st);
}

// Bentuk payload ke JS
$serverData = [
  'vehicles' => array_map(fn($v)=> [
    'plate'=>$v['Plate'] ?? '', 'merk'=>$v['Merk'] ?? '', 'color'=>$v['Color'] ?? '', 'status'=>'on'
  ], $vehicles),
  'drivers'  => array_map(fn($d)=> [
    'id'=>(int)($d['EmpId'] ?? 0), 'name'=>$d['nama_lengkap'] ?? ''
  ], $drivers),
  'masterRoutes' => array_map(fn($m)=> [
    'id'=>(int)($m['RouteMasterId'] ?? 0), 'name'=>$m['Name'] ?? '', 'address'=>$m['Address'] ?? ''
  ], $routeMasters),
  'routes'   => array_map(fn($r)=> [
    'id'=>(int)($r['RouteId'] ?? 0),
    'batchId'=>(int)($r['BatchId'] ?? 0),
    'name'=>$r['Name'] ?? '',
    'address'=>$r['Address'] ?? '',
    'vehicle'=>$r['VehiclePlate'] ?? '',
    'driver'=>$r['DriverName'] ?? '',
    'status'=>strtolower((string)($r['Status'] ?? 'open')),
    'date'=>$r['RouteDate'] ?? null
  ], $routes),
  'activities' => array_map(fn($a)=> [
    'id'=>(int)($a['ActivityId'] ?? 0),
    'routeId'=>(int)($a['RouteId'] ?? 0),
    'from'=>$a['FromLocation'] ?? null,
    'to'=>$a['ToLocation'] ?? null,
    'depart'=>$a['DepartTime'] ?? null,
    'arrive'=>$a['ArriveTime'] ?? null,
    'comment'=>$a['Comment'] ?? '',
    'time'=>$a['CreatedAt'] ?? null
  ], $activities)
];

// Sanitasi payload
$serverData = utf8ize_mixed($serverData);

// ---------- Template AdminLTE (header/sidebar/footer) ----------
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manajemen Rute & Armada</title>

  <!-- Bootstrap & FontAwesome -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">

  <!-- Select2 core + theme (rantai fallback; jika dua-duanya gagal, kita masih jalan tanpa tema) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link
    id="s2bs4"
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.6.4/dist/select2-bootstrap4.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
    onerror="
      this.onerror=null;
      this.href='https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css';
      document.documentElement.classList.add('s2-theme-fallback');
    ">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

  <style>
    body{background:#f4f6f9}
    .route-list{border:1px solid #e2e8f0;border-radius:6px;background:#fff}
    .list-item{border:1px solid #eef2f6;padding:12px;border-radius:8px;margin-bottom:10px;background:#fff}
    .status-badge.open{background:#e9f5ff;color:#0366d6;border-radius:6px;padding:4px 8px;font-weight:600}
    .status-badge.inprog{background:#fff7e6;color:#b36b00;border-radius:6px;padding:4px 8px;font-weight:600}
    .status-badge.done{background:#eef7ee;color:#197a2f;border-radius:6px;padding:4px 8px;font-weight:600}
    .small-muted{color:#6b7280}
    .activity-wrap{max-height:260px;overflow:auto;}
    .select2-selection__clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:18px;color:#999;}
    .badge-lock{font-size:10px;margin-left:6px}
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;}
    /* fallback tema jika CDN select2 theme gagal total */
    html.s2-theme-fallback .select2-container--bootstrap4 .select2-selection{
      border:1px solid #ced4da;border-radius:.25rem; min-height: calc(1.5em + .5rem + 2px);
    }
  </style>
</head>
<body>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <h3 class="m-0">Manajemen Rute & Armada</h3>
      <div class="text-muted small d-none">
  <i class="fas fa-user mr-1"></i><?= htmlspecialchars($LOGGED_IN_USER ?: '-', ENT_QUOTES, 'UTF-8') ?>
  &nbsp;| Terhubung ke DB: <strong>GG</strong>
</div>

    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php else: ?>

      <div class="row">
        <!-- KIRI: input -->
        <div class="col-lg-6">
          <div class="card shadow-sm mb-3">
            <div class="card-body">
              <div class="form-row">
                <div class="col-sm-4">
                  <label class="small" for="selectMobil">Mobil <span id="lockInfoVeh" class="badge badge-secondary badge-lock d-none">terkunci</span></label>
                  <select id="selectMobil" name="mobil" class="form-control form-control-sm js-s2" data-placeholder="Pilih mobil"></select>
                </div>
                <div class="col-sm-4">
                  <label class="small" for="selectDriver">Driver <span id="lockInfoDrv" class="badge badge-secondary badge-lock d-none">terkunci</span></label>
                  <select id="selectDriver" name="driver" class="form-control form-control-sm js-s2" data-placeholder="Pilih driver"></select>
                </div>
                <div class="col-sm-4">
                  <label class="small" for="selectRuteAwal">
                    Rute Awal
                    <span id="lockInfoStart" class="badge badge-secondary badge-lock d-none">terkunci</span>
                  </label>
                  <select id="selectRuteAwal" name="rute_awal" class="form-control form-control-sm js-s2" data-placeholder="Pilih rute awal"></select>
                </div>
              </div>

              <div class="form-row mt-2 align-items-end">
                <div class="col-sm-8">
                  <label class="small" for="selectRuteTujuan">Rute Tujuan</label>
                  <select id="selectRuteTujuan" name="rute_tujuan" class="form-control form-control-sm js-s2" data-placeholder="Pilih rute tujuan"></select>
                </div>
                <div class="col-sm-4 text-right">
                  <button id="btnAddRute" type="button" class="btn btn-outline-primary btn-sm mr-2">Add Rute</button>
                  <button id="btnSimpan" type="button" class="btn btn-primary btn-sm">Simpan</button>
                </div>
              </div>

              <!-- tabel merah (unsaved) -->
              <div class="route-list mt-2">
                <table class="table table-sm mb-0" id="tableRute">
                  <thead class="thead-light">
                    <tr>
                      <th style="width:60px">No</th>
                      <th>Nama Rute</th>
                      <th>Alamat lengkap</th>
                      <th style="width:140px" class="text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <!-- filter bawah tabel -->
              <div class="form-row mt-3">
                <div class="col-3">
                  <label class="small mb-1" for="filterStartRoutes">Start Date</label>
                  <input id="filterStartRoutes" name="filter_start" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-3">
                  <label class="small mb-1" for="filterEndRoutes">End Date</label>
                  <input id="filterEndRoutes" name="filter_end" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-3">
                  <label class="small mb-1" for="filterStatusRoutes">Status</label>
                  <select id="filterStatusRoutes" name="filter_status" class="form-control form-control-sm">
                    <option value="">Semua Status</option>
                    <option value="open">Open</option>
                    <option value="inprog">In Progress</option>
                    <option value="done">Selesai</option>
                  </select>
                </div>
                <div class="col-3">
                  <label class="small mb-1" for="filterMobilRoutes">Mobil</label>
                  <div class="d-flex">
                    <select id="filterMobilRoutes" name="filter_mobil" class="form-control form-control-sm mr-2"></select>
                    <button id="btnApplyRoutes" type="button" class="btn btn-outline-primary btn-sm">Apply</button>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- KANAN: statistik & aktivitas -->
        <div class="col-lg-6">
          <div class="card shadow-sm mb-3">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <div class="p-3 bg-white rounded shadow-sm text-center">
                    <canvas id="chartStatus" width="220" height="140"></canvas>
                    <div class="small-muted mt-2">
                      Selesai: <b id="statDone">0</b> —
                      InProg: <b id="statOnRoute">0</b> —
                      Belum: <b id="statPending">0</b>
                      <div id="statRangeInfo" class="small mt-1 text-muted"></div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="p-3 bg-white rounded shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="small-muted">Filter Aktivitas</div>
                      <button id="btnClearActFilter" type="button" class="btn btn-sm btn-outline-secondary">Clear</button>
                    </div>
                    <div class="mt-2 d-flex">
                      <label class="sr-only" for="filterSearch">Cari Aktivitas</label>
                      <input id="filterSearch" name="filter_search" class="form-control form-control-sm mr-2" placeholder="Cari (mobil/alamat/komentar)" />
                      <label class="sr-only" for="filterDate">Tanggal Aktivitas</label>
                      <input id="filterDate" name="filter_date" type="date" class="form-control form-control-sm" />
                    </div>
                    <div id="activityList" class="activity-wrap mt-2"></div>
                  </div>
                </div>
              </div>
              <div class="small-muted"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- 3 kolom batch -->
      <div class="row" id="batchRow">
        <div class="col-lg-4"><div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between"><div id="col1HeaderVehicle">Informasi Mobil</div><div id="col1HeaderDriver">Nama Driver</div></div><div class="card-body" id="listCol1"></div><div class="card-footer text-center small" id="pagerCol1"></div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between"><div id="col2HeaderVehicle">Informasi Mobil</div><div id="col2HeaderDriver">Nama Driver</div></div><div class="card-body" id="listCol2"></div><div class="card-footer text-center small" id="pagerCol2"></div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between"><div id="col3HeaderVehicle">Informasi Mobil</div><div id="col3HeaderDriver">Nama Driver</div></div><div class="card-body" id="listCol3"></div><div class="card-footer text-center small" id="pagerCol3"></div></div></div>
      </div>

      <div class="text-center mt-2" id="outerPager"></div>
      <div class="text-center text-muted small mt-2"></div>

      <?php endif; ?>
    </div>
  </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<!-- JS ORDER: jQuery → Bootstrap bundle → Select2 -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>

<script>
$.ajaxSetup({
  cache:false,
  headers: {'X-Requested-With':'XMLHttpRequest'} // bantu server kenali request AJAX
});

const DATA = <?php echo json_encode($serverData, JSON_UNESCAPED_UNICODE); ?>;

// URL API absolut relatif path file ini (hindari efek <base>)
const API_URL = <?php
  $apiUrl = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/api.php';
  echo json_encode($apiUrl, JSON_UNESCAPED_SLASHES);
?>;

// user login untuk createdBy
const CREATED_BY = <?php echo json_encode($LOGGED_IN_USER, JSON_UNESCAPED_UNICODE); ?>;

const today = ()=> new Date().toISOString().slice(0,10);

/* ===== helper AJAX robust: tetap coba parse JSON jika header salah ===== */
function postJSON(url, data, onOk){
  $.ajax({ url, method:'POST', data, dataType:'json' })
    .done(resp => onOk(resp))
    .fail((xhr, textStatus) => {
      let parsed = null;
      try { parsed = JSON.parse(xhr.responseText); } catch(e){}
      if (parsed) { onOk(parsed); return; }
      const preview = (xhr.responseText||'').slice(0,400);
      alert(`Request error — ${xhr.status} (${textStatus}).\nKemungkinan response bukan JSON (mis. halaman login/notice PHP).\nPreview:\n${preview}`);
    });
}

/* ===== Select2 ===== */
function initS2(){
  $('.js-s2').select2({
    theme:'bootstrap4', width:'100%', allowClear:true,
    placeholder:function(){return $(this).data('placeholder')||'';}
  });
  $('.select2-selection__clear').css({right:8,left:'auto'});
}
$(document).on('select2:open', () => {
  document.querySelectorAll('.select2-search__field').forEach(el => {
    if (!el.getAttribute('aria-label')) el.setAttribute('aria-label','Cari pada pilihan');
    if (!el.id) el.id = 's2search_' + Math.random().toString(36).slice(2);
  });
});

function setSelectOptions(sel, opts, withEmpty){
  const $el = $(sel); $el.empty();
  if(withEmpty){ $el.append(new Option('', '', false, false)); }
  for(const o of opts){
    const opt = new Option(o.text, o.value, false, false);
    if (o.dataset) { Object.entries(o.dataset).forEach(([k,v]) => $(opt).attr('data-'+k, v)); }
    $el.append(opt);
  }
}

/* ===== Unsaved table ===== */
let unsaved = [];
let currentStartId = null;
const masterById = id => (DATA.masterRoutes||[]).find(m=> Number(m.id)===Number(id))||null;

function buildStartRow(veh,drv,startId){
  const m = masterById(startId);
  return {_tmpId:'start_'+startId,isStart:true,name:(m?m.name:'')+' (Rute Awal)',address:m?m.address:'',vehicle:veh,driver:drv,status:'open',date:today()};
}
function ensureStartRow(){
  const veh = $('#selectMobil').val(), drv = $('#selectDriver').val(), sid = $('#selectRuteAwal').val();
  if(!veh || !drv || !sid) return;
  currentStartId = Number(sid);
  const idx = unsaved.findIndex(r=>r.isStart);
  if(idx===-1){ unsaved.unshift(buildStartRow(veh,drv,currentStartId)); }
  else{
    const fresh = buildStartRow(veh,drv,currentStartId);
    unsaved[idx] = {...unsaved[idx], ...fresh};
    if(idx!==0){ const row=unsaved.splice(idx,1)[0]; unsaved.unshift(row); }
  }
  renderUnsaved();
}
function renderUnsaved(){
  const tb = $('#tableRute tbody').empty();
  unsaved.forEach((r,i)=>{
    tb.append(`<tr>
      <td>${i+1}</td>
      <td>${r.isStart?'<span class="badge badge-info mr-1">Rute Awal</span>':''}${r.name||''}</td>
      <td>${r.address||''}</td>
      <td class="text-right">
        <button type="button" class="btn btn-sm btn-outline-secondary btn-hide" data-id="${r._tmpId}">
          <i class="fas fa-times"></i> Hapus (Tabel)
        </button>
      </td>
    </tr>`);
  });
  tb.find('.btn-hide').on('click', function(){
    const id = String($(this).data('id'));
    unsaved = unsaved.filter(u=> u._tmpId!==id);
    if(id.startsWith('start_')) currentStartId=null;
    renderUnsaved();
  });
}
function clearUnsaved(){ unsaved=[]; currentStartId=null; renderUnsaved(); }

/* ===== Helpers: batch status & active pairs ===== */
function groupByBatchForPlate(plate){
  const map = new Map();
  (DATA.routes||[]).forEach(r=>{ if(r.vehicle===plate){ if(!map.has(r.batchId)) map.set(r.batchId, []); map.get(r.batchId).push(r); }});
  return map;
}
function groupByBatchForDriver(drv){
  const map = new Map();
  (DATA.routes||[]).forEach(r=>{ if(r.driver===drv){ if(!map.has(r.batchId)) map.set(r.batchId, []); map.get(r.batchId).push(r); }});
  return map;
}
function statusOfArray(arr){
  if(arr.length===0) return 'open';
  const allDone = arr.every(x=>x.status==='done');
  if(allDone) return 'done';
  if(arr.some(x=>x.status==='inprog')) return 'inprog';
  return 'open';
}
function activePairByVehicle(plate){
  const map = groupByBatchForPlate(plate);
  const ids = Array.from(map.keys()).sort((a,b)=>b-a);
  for(const bid of ids){
    const arr = map.get(bid);
    if(statusOfArray(arr) !== 'done'){
      const drv = arr[0]?.driver || '';
      return {driver: drv, batchId: bid};
    }
  }
  return null;
}
function activePairByDriver(drv){
  const map = groupByBatchForDriver(drv);
  const ids = Array.from(map.keys()).sort((a,b)=>b-a);
  for(const bid of ids){
    const arr = map.get(bid);
    if(statusOfArray(arr) !== 'done'){
      const plate = arr[0]?.vehicle || '';
      return {vehicle: plate, batchId: bid};
    }
  }
  return null;
}
function findStartIdInRoutes(routesArr){
  const startRow = routesArr.find(r => /\(rute awal\)/i.test(r.name||''));
  if(!startRow) return null;
  const pure = String(startRow.name||'').replace(/\s*\(rute awal\)\s*$/i,'').trim().toLowerCase();
  const m = (DATA.masterRoutes||[]).find(mm => String(mm.name||'').trim().toLowerCase() === pure);
  return m ? m.id : null;
}

/* ===== Chart ===== */
const ctx = document.getElementById('chartStatus').getContext('2d');
const chart = new Chart(ctx,{ type:'doughnut', data:{labels:['Selesai','In Progress','Belum Mulai'],datasets:[{data:[0,0,0],backgroundColor:['#28a745','#ffc107','#6c757d']}]}, options:{plugins:{legend:{display:false}},cutout:'68%'}});

/* ===== Filter ===== */
function routeMatchesFilters(r){
  const s = $('#filterStartRoutes').val();
  const e = $('#filterEndRoutes').val();
  const status = $('#filterStatusRoutes').val() || '';
  const veh = $('#filterMobilRoutes').val() || '';

  let start=s, end=e;
  if(start && end && start> end){ const t=start; start=end; end=t; }

  if(start && (!r.date || r.date < start)) return false;
  if(end   && (!r.date || r.date > end))   return false;
  if(status && r.status !== status)        return false;
  if(veh && veh!=='all' && r.vehicle !== veh) return false;
  return true;
}
function getFilteredRoutes(){ return (DATA.routes||[]).filter(routeMatchesFilters); }
function getRoutesForStats(){ return getFilteredRoutes(); }

function updateStat(){
  const routes = getRoutesForStats();
  const done = routes.filter(r=>r.status==='done').length;
  const inpg = routes.filter(r=>r.status==='inprog').length;
  const open = routes.filter(r=>r.status==='open').length;
  $('#statDone').text(done); $('#statOnRoute').text(inpg); $('#statPending').text(open);
  chart.data.datasets[0].data=[done,inpg,open]; chart.update();
  const s = $('#filterStartRoutes').val(); const e = $('#filterEndRoutes').val();
  const info = (s||e) ? `${s||'...'} s/d ${e||'...'}` : `Per hari ini (${today()})`;
  $('#statRangeInfo').text(info);
}

/* ===== Aktivitas ===== */
function renderActivities(){
  const q = ($('#filterSearch').val()||'').toLowerCase();
  const d = $('#filterDate').val();

  const list = (DATA.activities||[])
    .slice()
    .sort((a,b)=> new Date(b.time)-new Date(a.time))
    .filter(a=>{
      if(d && a.time){
        if((new Date(a.time)).toISOString().slice(0,10)!==d) return false;
      }
      if(q){
        const r = (DATA.routes||[]).find(rr=> rr.id===a.routeId);
        const hay = [
          a.comment||'',
          r?.name||'',
          r?.address||'',
          r?.vehicle||'',
          r?.driver||''
        ].join(' ').toLowerCase();
        if(!hay.includes(q)) return false;
      }
      return true;
    });

  const el = $('#activityList').empty().css({maxHeight:260,overflowY:'auto'});
  if(!list.length){
    el.html('<div class="text-muted small">Tidak ada aktivitas.</div>');
    return;
  }

  list.forEach(a=>{
    const r = (DATA.routes||[]).find(rr=> rr.id===a.routeId);
    const routeName = r ? r.name : ('#'+a.routeId);
    const veh = r ? (DATA.vehicles||[]).find(v=> v.plate===r.vehicle) : null;
    const vehInfo = veh ? `${veh.plate} • ${veh.merk||'-'} • ${veh.color||'-'}` : (r?.vehicle||'-');
    const driverInfo = r?.driver || '-';
    const line = `${a.comment||''} — ${routeName}`;

    el.append(`
      <div class="mb-2">
        <span class="badge badge-success mr-1">&bull;</span>
        ${line}
        <div class="small text-muted">${vehInfo} • ${driverInfo}</div>
        <div class="small text-muted">#${a.routeId} • ${a.time||''}</div>
      </div>
    `);
  });
}

$('#filterSearch').on('input', renderActivities);
$('#filterDate').on('change', renderActivities);
$('#btnClearActFilter').on('click', ()=>{ $('#filterSearch').val(''); $('#filterDate').val(''); renderActivities(); });

/* ===== 3 kolom batch ===== */
const PER_COL = 3; let outerPage=1;
function groupsByBatch(){
  const src = getFilteredRoutes();
  const map = new Map();
  src.forEach(r=>{ if(!r.batchId) return; if(!map.has(r.batchId)) map.set(r.batchId,[]); map.get(r.batchId).push(r); });
  return Array.from(map.entries()).map(([batchId, rs])=>({batchId, routes: rs.sort((a,b)=>a.id-b.id)})).sort((a,b)=> b.batchId-a.batchId);
}
function renderCol(hVeh,hDrv,body,pager,group){
  $('#'+body).empty(); $('#'+pager).empty();
  if(!group){ $('#'+hVeh).html('Informasi Mobil'); $('#'+hDrv).html('Nama Driver'); return; }

  const first = group.routes[0]||{};
  const veh = (DATA.vehicles||[]).find(v=> v.plate===first.vehicle) || {plate:first.vehicle, merk:'', color:''};
  const summary = `Rute: ${group.routes.length} • Open: ${group.routes.filter(x=>x.status==='open').length} • InProg: ${group.routes.filter(x=>x.status==='inprog').length} • Done: ${group.routes.filter(x=>x.status==='done').length}`;

  $('#'+hVeh).html(`<div><strong>${veh.plate||'-'} - ${veh.merk||'-'} - ${veh.color||'-'}</strong><div class="small-muted">${summary}</div></div>`);
  $('#'+hDrv).html(`<div class="small-muted">Driver</div><div><strong>${first.driver||'-'}</strong></div>`);

  function paginate(arr,p){
    const total=Math.max(1,Math.ceil(arr.length/PER_COL));
    p=Math.min(Math.max(1,p),total);
    return{items:arr.slice((p-1)*PER_COL,(p-1)*PER_COL+PER_COL),page:p,total};
  }

  function draw(p=1){
    const res = paginate(group.routes,p);
    $('#'+body).empty();

    res.items.forEach(it=>{
      const cls = it.status==='open'?'open':(it.status==='inprog'?'inprog':'done');
      const label = cls==='open'?'Open':cls==='inprog'?'In Progress':'Selesai';
      const canDelete = (it.status==='open');

      $('#'+body).append(`
        <div class="list-item">
          <div class="d-flex justify-content-between align-items-start">
            <div class="text-left">
              <div class="small text-muted">${it.name||'-'}</div>
              <div class="small text-muted">${it.address||''}</div>
            </div>
            <div class="text-right">
              <div class="d-flex align-items-center justify-content-end">
                <div class="status-badge ${cls} mr-2">${label}</div>
                <button type="button" class="btn btn-sm btn-danger btn-del-route" data-id="${it.id}" ${canDelete?'':'disabled'} ${canDelete?'':'title="Hanya bisa hapus jika status OPEN"'}>
                  <i class="fas fa-trash"></i>
                </button>
              </div>
              <div class="text-muted small mt-1">${it.date||''}</div>
            </div>
          </div>
        </div>
      `);
    });

    $('#'+body+' .btn-del-route').off('click').on('click', function(){
      const rid = Number($(this).data('id'));
      if(!confirm('Hapus rute ini? (hanya status OPEN)')) return;
      postJSON(API_URL,{action:'delete_route',routeId:rid}, (resp)=>{
        if(resp?.success){
          DATA.routes = DATA.routes.filter(r=> r.id!==rid);
          renderAll();
        } else {
          alert(resp?.error||'Gagal hapus rute');
        }
      });
    });

    let html='';
    for(let i=1;i<=res.total;i++){
      html+=`<button type="button" class="btn btn-sm btn-light mx-1 ${i===res.page?'active':''}" data-p="${i}">${i}</button>`;
    }
    $('#'+pager).html(html).find('button').on('click', function(){ draw(parseInt($(this).data('p'))); });
  }
  draw(1);
}
function renderBatches(page=1){
  outerPage=page;
  const groups = groupsByBatch();
  const totalPages = Math.max(1, Math.ceil(groups.length/3));
  const slice = groups.slice((page-1)*3,(page-1)*3+3);
  renderCol('col1HeaderVehicle','col1HeaderDriver','listCol1','pagerCol1',slice[0]);
  renderCol('col2HeaderVehicle','col2HeaderDriver','listCol2','pagerCol2',slice[1]);
  renderCol('col3HeaderVehicle','col3HeaderDriver','listCol3','pagerCol3',slice[2]);
  const $p = $('#outerPager').empty();
  for(let i=1;i<=totalPages;i++){ $p.append(`<button type="button" class="btn btn-sm btn-light mx-1 ${i===page?'active':''}" data-p="${i}">${i}</button>`); }
  $p.find('button').on('click', function(){ renderBatches(parseInt($(this).data('p'))); });
}

/* ===== Autofill & Lock rules ===== */
let autofillLock = false;
function withAutofillLock(fn){ if(autofillLock) return; autofillLock = true; try { fn(); } finally { setTimeout(()=>{autofillLock=false;},0); } }
function setDriverLock(locked){ $('#selectDriver').prop('disabled', locked); $('#lockInfoDrv').toggleClass('d-none', !locked); }
function setVehicleLock(locked){ $('#selectMobil').prop('disabled', locked); $('#lockInfoVeh').toggleClass('d-none', !locked); }
function setStartLock(locked){ $('#selectRuteAwal').prop('disabled', locked); $('#lockInfoStart').toggleClass('d-none', !locked); }
function resetSelect(sel){
  $(sel).val(null).trigger('change.select2');
  if(sel === '#selectRuteAwal'){ ensureStartRow(); clearUnsaved(); }
}

/* ===== Enforcement: pair & reset behavior ===== */
function enforcePairOnVehicleChange(){
  if(autofillLock) return;
  const plate = $('#selectMobil').val();
  const wasDrvLocked   = $('#selectDriver').prop('disabled');
  const wasStartLocked = $('#selectRuteAwal').prop('disabled');
  if(wasDrvLocked || wasStartLocked){
    setDriverLock(false); resetSelect('#selectDriver');
    setStartLock(false);  resetSelect('#selectRuteAwal');
    clearUnsaved();
  }
  if(!plate){
    setDriverLock(false); setVehicleLock(false); setStartLock(false);
    resetSelect('#selectDriver'); resetSelect('#selectRuteAwal');
    clearUnsaved(); return;
  }
  const pair = activePairByVehicle(plate);
  if(pair){
    withAutofillLock(()=>{
      $('#selectDriver').val(pair.driver).trigger('change.select2'); setDriverLock(true);
      const routesArr = (DATA.routes||[]).filter(r=> r.batchId===pair.batchId);
      const sid = findStartIdInRoutes(routesArr);
      if(sid){ $('#selectRuteAwal').val(String(sid)).trigger('change.select2'); setStartLock(true); clearUnsaved(); }
      else { resetSelect('#selectRuteAwal'); setStartLock(false); clearUnsaved(); }
    });
  } else { setDriverLock(false); setStartLock(false); clearUnsaved(); }
}
function enforcePairOnDriverChange(){
  if(autofillLock) return;
  const drv = $('#selectDriver').val();
  const wasVehLocked   = $('#selectMobil').prop('disabled');
  const wasStartLocked = $('#selectRuteAwal').prop('disabled');
  if(wasVehLocked || wasStartLocked){
    setVehicleLock(false); resetSelect('#selectMobil');
    setStartLock(false);   resetSelect('#selectRuteAwal');
    clearUnsaved();
  }
  if(!drv){
    setDriverLock(false); setVehicleLock(false); setStartLock(false);
    resetSelect('#selectMobil'); resetSelect('#selectRuteAwal');
    clearUnsaved(); return;
  }
  const pair = activePairByDriver(drv);
  if(pair){
    withAutofillLock(()=>{
      $('#selectMobil').val(pair.vehicle).trigger('change.select2'); setVehicleLock(true);
      const routesArr = (DATA.routes||[]).filter(r=> r.batchId===pair.batchId);
      const sid = findStartIdInRoutes(routesArr);
      if(sid){ $('#selectRuteAwal').val(String(sid)).trigger('change.select2'); setStartLock(true); clearUnsaved(); }
      else { resetSelect('#selectRuteAwal'); setStartLock(false); clearUnsaved(); }
    });
  } else { setVehicleLock(false); setStartLock(false); clearUnsaved(); }
}

/* ===== Events ===== */
function bindHandlers(){
  $('#selectRuteAwal').on('change.startrow', ensureStartRow);
  $('#selectMobil, #selectDriver').on('change.startrow', ()=>{ if($('#selectRuteAwal').val()) ensureStartRow(); });
  $('#selectMobil').on('change.autofill', enforcePairOnVehicleChange);
  $('#selectDriver').on('change.autofill', enforcePairOnDriverChange);

  $('#btnAddRute').on('click', ()=>{
    const veh=$('#selectMobil').val(), drv=$('#selectDriver').val(), a=$('#selectRuteAwal').val(), t=$('#selectRuteTujuan').val();
    if(!veh||!drv||!a||!t){ alert('Lengkapi mobil, driver, rute awal & tujuan.'); return; }
    if(a===t){ alert('Rute awal & tujuan tidak boleh sama'); return; }
    ensureStartRow();
    const m = (DATA.masterRoutes||[]).find(x=> String(x.id)===String(t));
    unsaved.push({_tmpId:'tmp_'+Date.now(), name:m?m.name:'', address:m?m.address:'', vehicle:veh, driver:drv, status:'open', date:today()});
    renderUnsaved();
  });

  $('#btnSimpan').on('click', ()=>{
    if(unsaved.length===0){ alert('Tidak ada rute baru'); return; }
    if(!confirm('Simpan '+unsaved.length+' rute ke database?')) return;
    const copy=[...unsaved];
    const sIdx=copy.findIndex(r=>r.isStart); if(sIdx>0){ const s=copy.splice(sIdx,1)[0]; copy.unshift(s); }
    const payload = copy.map(u=>({
      name:u.name,
      address:u.address,
      vehicle_plate:u.vehicle,
      driver_name:u.driver, // tetap nama (data rute pakai nama driver)
      status:u.status||'open',
      route_date:u.date||today()
    }));
    postJSON(API_URL,{action:'save_batch',createdBy:CREATED_BY,routes:JSON.stringify(payload)}, function(resp){
      if(resp && resp.success){
        alert('Batch '+resp.batchNo+' tersimpan ('+resp.mode+')');
        location.replace(location.pathname+'?ts='+Date.now());
      } else {
        alert(resp?.error||'Gagal simpan');
      }
    });
  });

  $('#btnApplyRoutes').on('click', ()=>{
    updateStat();
    renderBatches(1);
    renderActivities();
  });
}

/* ===== Boot ===== */
function fillSelects(){
  setSelectOptions('#selectMobil', (DATA.vehicles||[]).map(v=>({value:v.plate, text:v.plate})), true);
  setSelectOptions('#selectDriver',
    (DATA.drivers||[]).map(d=>({ value: d.name, text: d.name, dataset: { empid: d.id } })), true
  );
  setSelectOptions('#selectRuteAwal', (DATA.masterRoutes||[]).map(m=>({value:m.id, text:m.name})), true);
  setSelectOptions('#selectRuteTujuan', (DATA.masterRoutes||[]).map(m=>({value:m.id, text:m.name})), true);
  setSelectOptions('#filterMobilRoutes', [{value:'all', text:'Tampilkan Semua Mobil'}].concat((DATA.vehicles||[]).map(v=>({value:v.plate, text:v.plate}))), false);
  $('#filterStartRoutes').val(today());
  $('#filterEndRoutes').val(today());
}
function renderAll(){
  fillSelects();
  initS2();
  bindHandlers();
  renderUnsaved();
  updateStat();
  renderActivities();
  renderBatches(1);
}
renderAll();
</script>
</body>
</html>
