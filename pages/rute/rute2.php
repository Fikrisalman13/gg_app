<?php
// pages/rute/rute1.php — Full integrated & themed version
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../koneksi.php';

function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache'); header('Expires: 0');
}
nocache();
date_default_timezone_set('Asia/Jakarta');

$LOGGED_IN_USER = $_SESSION['UserName'] ?? 'PUBLIC';
$themeColor = $_SESSION['Theme'] ?? 'primary'; // contoh: 'primary', 'success', 'danger'

if (!$conn) { die("Koneksi DB gagal: " . print_r(sqlsrv_errors(), true)); }
$error_message = null;

/* ===== LOAD DATA ===== */
$vehicles = $drivers = $routeMasters = $routes = [];

// Vehicles
$st = sqlsrv_query($conn, "SELECT VehicleId, Plate, Merk, Model, Color FROM dbo.Vehicles ORDER BY Plate");
if ($st) { while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $vehicles[] = $r; sqlsrv_free_stmt($st); }

// Drivers
$st = sqlsrv_query($conn, "
  SELECT u.EmpId, e.nama_lengkap
  FROM dbo.SMUserMs u
  JOIN dbo.m_emp e ON e.id_emp = u.EmpId
  WHERE u.GroupId IN (29,41)
  ORDER BY e.nama_lengkap
");
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

// prepare server data for JS
$serverData = [
  'vehicles' => array_map(fn($v)=> [
    'plate'=>$v['Plate'] ?? '', 'merk'=>$v['Merk'] ?? '', 'color'=>$v['Color'] ?? ''
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
  ], $routes)
];

// include your layout (header/sidebar)
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- MAIN CONTENT -->
<style>
/* layout tweak: force card-header relative so header-tools can be positioned to the corner */
.card-header {
  position: relative;
}

/* header-tools absolute to the right, vertically centered */
.card-header .header-tools {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  gap: .5rem;
  align-items: center;
  z-index: 5;
}

/* ensure search input looks good on dark header: use white background & small radius */
.card-header .global-search .form-control {
  background: #fff;
  color: #222;
  border-radius: 4px;
}

/* remove outline for small clear button */
.card-header .header-tools .btn-outline-light { color: rgba(255,255,255,.9); }

/* responsive: header-tools should stack on small screens */
@media (max-width: 575px) {
  .card-header .header-tools {
    position: static;
    transform: none;
    margin-top: .5rem;
    flex-wrap: wrap;
    gap: .35rem;
  }
  .card-header { padding-bottom: .75rem; }
}

/* small helper classes for status badges (kept minimal — styling consistent dengan tampilan Anda) */
.status-badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
.status-open{background:#ffec99;color:#7a5d00;}
.status-inprog{background:#dc3545;color:#fff;}
.status-done{background:#28a745;color:#fff;}
.td-aksi{width:60px;text-align:center}
.addr{white-space:pre-wrap}
</style>

<div class="content-wrapper">
  <div class="content-header bg-white mb-4" style="border-bottom:1px solid #dee2e6;">
    <div class="container-fluid py-3">
      <div class="row align-items-center">
        <div class="col">
          <h1 class="m-0">Manajemen Rute Mobil Angkutan</h1>
          <p class="m-0 text-muted">Kelola rute perjalanan dan angkutan kendaraan</p>
        </div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php else: ?>

      <div class="card">
        <!-- Card header uses theme color -->
        <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white d-flex align-items-center">
          <div class="me-2">
            <strong>Daftar Rute Berdasarkan Batch</strong>
          </div>

          <!-- spacer: search + button pada pojok kanan, warna mengikuti theme -->
          <div class="ms-auto d-flex align-items-center gap-2 header-tools">
            <!-- global search (small) — tampilkan dengan latar putih agar kontras pada header gelap -->
            <div class="global-search" style="min-width:160px; max-width:320px;">
              <div class="input-group input-group-sm">
                <input id="globalSearch" type="search" class="form-control form-control-sm" placeholder="Cari...">
                <button id="clearGlobalSearch" class="btn btn-outline-light" type="button" title="Bersihkan"><i class="fas fa-times"></i></button>
              </div>
            </div>

            <!-- Create Route button on the right corner — menggunakan kelas tema -->
            <button id="btnOpenModal" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm text-white" data-toggle="modal" data-target="#modalRute" title="Buat Rute Baru">
              <i class="fas fa-plus me-1"></i> Buat Rute Baru
            </button>
          </div>
        </div>

        <div class="card-body">
          <!-- overdue notice -->
          <div id="overdueNotice" class="alert alert-warning d-none" role="alert" style="border-radius:8px;">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="overdueText">Ada rute dari hari sebelumnya yang masih OPEN/IN PROGRESS.</span>
              </div>
              <button id="btnShowOverdue" class="btn btn-sm btn-warning">
                <i class="fas fa-filter me-1"></i>Tampilkan
              </button>
            </div>
          </div>

          <!-- Filters -->
          <div class="mb-3">
            <div class="form-row row gx-2">
              <div class="col-md-2 mb-2">
                <label class="small font-weight-bold">Tanggal Mulai</label>
                <input type="date" id="fltStart" class="form-control form-control-sm">
              </div>
              <div class="col-md-2 mb-2">
                <label class="small font-weight-bold">Tanggal Selesai</label>
                <input type="date" id="fltEnd" class="form-control form-control-sm">
              </div>
              <div class="col-md-2 mb-2">
                <label class="small font-weight-bold">Status</label>
                <select id="fltStatus" class="form-control form-control-sm">
                  <option value="">Semua</option>
                  <option value="active">Open + In Progress</option>
                  <option value="open">Open</option>
                  <option value="inprog">In Progress</option>
                  <option value="done">Selesai</option>
                </select>
              </div>
              <div class="col-md-3 mb-2">
                <label class="small font-weight-bold">Kendaraan</label>
                <select id="fltVeh" class="form-control form-control-sm js-s2" data-placeholder="Semua Kendaraan"></select>
              </div>
              <div class="col-md-3 mb-2">
                <label class="small font-weight-bold">Driver</label>
                <select id="fltDrv" class="form-control form-control-sm js-s2" data-placeholder="Semua Driver"></select>
              </div>
            </div>
            <div class="mt-2">
              <button id="btnApplyFilter" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm me-2 text-white">Terapkan</button>
              <button id="btnResetFilter" class="btn btn-light btn-sm">Reset</button>
            </div>
          </div>

          <!-- Table wrapper -->
          <div id="batchTableWrap" class="table-responsive"></div>

          <!-- Pager -->
          <div class="mt-2">
            <div id="pager" class="btn-group btn-group-sm"></div>
            <div class="small text-muted text-center mt-1" id="pageInfo"></div>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </div>
  </section>
</div>

<!-- Modal: Buat Rute Baru -->
<div class="modal fade" id="modalRute" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Buat Rute Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 d-flex justify-content-between align-items-start gap-2">
          <div style="flex:1">
            <label class="small font-weight-bold">Jadwal Rute</label>
            <div class="d-flex gap-2">
              <input type="datetime-local" id="dtSchedule" class="form-control form-control-sm" step="60">
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-light" id="btnSetNow">Sekarang</button>
                <button type="button" class="btn btn-light" id="btnSetTomorrow">Besok</button>
              </div>
            </div>
            <small class="text-muted d-block mt-1">Tanggal jadwal menentukan batch.</small>
          </div>
          <div class="text-end" style="min-width:140px;">
            <button id="btnAutoclose" class="btn btn-outline-danger btn-sm">Auto Close Overdue</button>
          </div>
        </div>

        <div class="row gx-2">
          <div class="col-md-4 mb-2">
            <label class="small font-weight-bold">Kendaraan</label>
            <div class="d-flex">
              <select id="selectMobil" class="form-control form-control-sm js-s2" data-placeholder="Pilih Kendaraan"></select>
              <span id="lockInfoVeh" class="badge bg-secondary ms-2 d-none" title="Terkunci">🔒</span>
            </div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="small font-weight-bold">Pengemudi</label>
            <div class="d-flex">
              <select id="selectDriver" class="form-control form-control-sm js-s2" data-placeholder="Pilih Pengemudi"></select>
              <span id="lockInfoDrv" class="badge bg-secondary ms-2 d-none" title="Terkunci">🔒</span>
            </div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="small font-weight-bold">Rute Awal</label>
            <div class="d-flex">
              <select id="selectRuteAwal" class="form-control form-control-sm js-s2" data-placeholder="Pilih Rute Awal"></select>
              <span id="lockInfoStart" class="badge bg-secondary ms-2 d-none" title="Terkunci">🔒</span>
            </div>
          </div>

          <div class="col-md-8 mb-2">
            <label class="small font-weight-bold">Rute Tujuan</label>
            <select id="selectRuteTujuan" class="form-control form-control-sm js-s2" data-placeholder="Pilih Rute Tujuan"></select>
          </div>
          <div class="col-md-4 mb-2 d-flex align-items-end">
            <button id="btnAddRute" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm text-white w-100">Tambah Rute</button>
          </div>
        </div>

        <hr>

        <h6 class="mb-2">Daftar Rute Sementara</h6>
        <div class="table-responsive">
          <table id="tableRute" class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr><th style="width:60px">No</th><th>Nama Rute</th><th>Alamat</th><th style="width:90px">Aksi</th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button id="btnSimpan" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> text-white">Simpan Rute</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal finish -->
<div class="modal fade" id="finishModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="finishForm">
        <div class="modal-header">
          <h5 class="modal-title">Selesaikan Rute</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="small">Kategori (opsional)</label>
            <select id="inputCategory" class="form-control form-control-sm">
              <option value="">- pilih -</option>
              <option value="menaikan">Menaikan Barang</option>
              <option value="menurunkan">Menurunkan Barang</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="small">Qty (opsional)</label>
            <input id="inputQty" type="number" min="0" class="form-control form-control-sm" />
          </div>
          <div class="mb-2">
            <label class="small">Note (opsional)</label>
            <input id="inputNote" type="text" class="form-control form-control-sm" />
          </div>
          <div class="small text-muted">Status diubah oleh <span id="editedByLbl"></span></div>
          <input type="hidden" id="finishRouteId" />
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm">Simpan & Selesai</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// include footer (footer.php should load the global scripts like jQuery/bootstrap/select2)
include __DIR__ . '/../../includes/footer.php';
?>

<!-- Page-specific JS -->
<script>
/* Debounce helper */
function debounce(fn, ms){ let t; return function(...a){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,a), ms); }; }

$.ajaxSetup({cache:false, headers:{'X-Requested-With':'XMLHttpRequest'}});

const DATA = <?php echo json_encode($serverData, JSON_UNESCAPED_UNICODE); ?>;
const API_URL = <?php $apiUrl = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/api.php'; echo json_encode($apiUrl, JSON_UNESCAPED_SLASHES); ?>;
const CREATED_BY = <?php echo json_encode($LOGGED_IN_USER, JSON_UNESCAPED_UNICODE); ?>;
const PAGE_SIZE = 10;
const today = ()=> new Date().toISOString().slice(0,10);
const yesterday = ()=> { const d=new Date(); d.setDate(d.getDate()-1); return d.toISOString().slice(0,10); };

/* Helper functions (kept behaviour sama dengan versi sebelumnya) */
(function(){
  function setSelectOptions(sel, opts, withEmpty){
    const $el = $(sel); $el.empty();
    if(withEmpty){ $el.append(new Option('', '', false, false)); }
    for(const o of opts){
      const opt = new Option(o.text, o.value, false, false);
      if (o.dataset) { Object.entries(o.dataset).forEach(([k,v]) => $(opt).attr('data-'+k, v)); }
      $el.append(opt);
    }
  }
  function esc(s){ return $('<div>').text(s||'').html(); }
  function indoDate(ymd){
    if(!ymd) return '';
    const [Y,M,D] = ymd.split('-').map(Number);
    const dt = new Date(Date.UTC(Y, M-1, D));
    return dt.toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' });
  }
  function vehicleLabel(plate){
    const v = (DATA.vehicles||[]).find(x=>x.plate===plate);
    if(!v) return plate||'-';
    return `${v.plate||'-'} - ${v.merk||'-'} - ${v.color||'-'}`;
  }

  function pad(n){ return String(n).padStart(2,'0'); }
  function nowLocalISO(){ const d=new Date(); d.setSeconds(0,0); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; }
  function tomorrowLocalISO(){ const d=new Date(); d.setDate(d.getDate()+1); d.setSeconds(0,0); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; }
  function getScheduleISO(){ return $('#dtSchedule').val() || nowLocalISO(); }
  function getScheduleDate(){ return getScheduleISO().slice(0,10); }
  function scheduleMidnightOneSec(dateYMD){ return `${dateYMD} 00:00:01`; }

  function initS2(){
    if (typeof $.fn.select2 === 'undefined') return;
    $('.js-s2').select2({ theme:'bootstrap4', width:'100%', allowClear:true, placeholder:function(){return $(this).data('placeholder')||'';} });
    const $modal = $('#modalRute');
    ['#selectMobil','#selectDriver','#selectRuteAwal'].forEach(function(id){
      const $el=$(id);
      if($el.length){
        try {
          $el.select2('destroy').select2({
            theme:'bootstrap4', width:'100%', allowClear:true,
            placeholder: $el.data('placeholder')||'',
            dropdownParent: $modal
          });
        } catch(e){}
      }
    });
    try {
      $('#selectRuteTujuan').select2('destroy').select2({
        theme:'bootstrap4', width:'100%', allowClear:true,
        placeholder: $('#selectRuteTujuan').data('placeholder') || '',
        templateResult: function(item){
          if(!item.id) return item.text;
          const $opt = $(item.element || []);
          const address = $opt.data('address') || '';
          const $wrap = $('<div><div class="fw-bold"></div>' + (address?'<div class="small text-muted"></div>':'') + '</div>');
          $wrap.find('.fw-bold').text(item.text || '');
          if(address) $wrap.find('.small').text(address);
          return $wrap;
        },
        dropdownParent: $modal,
        dropdownCssClass: 's2-3rows'
      });
    } catch(e){}
  }

  let unsaved = [];
  let currentStartId = null;
  let autofillLock = false;
  let SORT_KEY = 'date', SORT_DIR = 'asc';
  let currentPage = 1;
  let pairSource = null;

  function withAutofillLock(fn){ if(autofillLock) return; autofillLock = true; try{ fn(); } finally { setTimeout(()=>{autofillLock=false;},0); } }
  function setDriverLock(locked){ $('#selectDriver').prop('disabled', locked); $('#lockInfoDrv').toggleClass('d-none', !locked); }
  function setVehicleLock(locked){ $('#selectMobil').prop('disabled', locked); $('#lockInfoVeh').toggleClass('d-none', !locked); }
  function setStartLock(locked){ $('#selectRuteAwal').prop('disabled', locked); $('#lockInfoStart').toggleClass('d-none', !locked); }
  function resetSelect(sel){ $(sel).val(null).trigger('change'); if(sel === '#selectRuteAwal'){ ensureStartRow(); clearUnsaved(); } }
  function groupByBatchForPlate(plate){
    const map = new Map();
    (DATA.routes||[]).forEach(r=>{ if(r.vehicle===plate){ if(!map.has(r.batchId)) map.set(r.batchId,[]); map.get(r.batchId).push(r); } });
    return map;
  }
  function groupByBatchForDriver(drv){
    const map = new Map();
    (DATA.routes||[]).forEach(r=>{ if(r.driver===drv){ if(!map.has(r.batchId)) map.set(r.batchId,[]); map.get(r.batchId).push(r); } });
    return map;
  }
  function statusOfArray(arr){ if(arr.length===0) return 'open'; if(arr.every(x=>x.status==='done')) return 'done'; if(arr.some(x=>x.status==='inprog')) return 'inprog'; return 'open'; }
  function activePairByVehicle(plate, forDate){
    const map = groupByBatchForPlate(plate); const ids = Array.from(map.keys()).sort((a,b)=>b-a);
    for(const bid of ids){
      const arr = map.get(bid); if(statusOfArray(arr) !== 'done'){ const date0 = arr[0]?.date || null; if(forDate && date0 && date0 !== forDate) continue; return { driver: arr[0]?.driver || '', batchId: bid, date: date0 }; }
    }
    return null;
  }
  function activePairByDriver(drv, forDate){
    const map = groupByBatchForDriver(drv); const ids = Array.from(map.keys()).sort((a,b)=>b-a);
    for(const bid of ids){
      const arr = map.get(bid); if(statusOfArray(arr) !== 'done'){ const date0 = arr[0]?.date || null; if(forDate && date0 && date0 !== forDate) continue; return { vehicle: arr[0]?.vehicle || '', batchId: bid, date: date0 }; }
    }
    return null;
  }
  function findStartIdInRoutes(routesArr){
    const startRow = routesArr.find(r => /\(rute awal\)/i.test(r.name||'')); if(!startRow) return null;
    const pure = String(startRow.name||'').replace(/\s*\(rute awal\)\s*$/i,'').trim().toLowerCase();
    const m = (DATA.masterRoutes||[]).find(mm => String(mm.name||'').trim().toLowerCase() === pure);
    return m ? m.id : null;
  }

  function getSortVal(row, key){
    if(key==='no') return row.noInBatch||0;
    if(key==='date') return row.date||'';
    if(key==='vehicle') return row.vehicle||'';
    if(key==='driver') return row.driver||'';
    if(key==='status') return row.status||'';
    if(key==='address') return [row.name||'', row.address||''].filter(Boolean).join(' ');
    return '';
  }
  function sortRows(rows){
    rows.sort((a,b)=>{
      const va = getSortVal(a, SORT_KEY), vb = getSortVal(b, SORT_KEY);
      if(typeof va === 'number' || typeof vb === 'number') return (va - vb) * (SORT_DIR==='asc'?1:-1);
      return String(va).localeCompare(String(vb), 'id', {sensitivity:'base'}) * (SORT_DIR==='asc'?1:-1);
    });
  }

  function groupsByBatchAll(){
    const src = (DATA.routes||[]).slice(); const map = new Map();
    src.forEach(r=>{ if(!r.batchId) return; if(!map.has(r.batchId)) map.set(r.batchId,[]); map.get(r.batchId).push(r); });
    return Array.from(map.entries()).map(([batchId, rs])=>({batchId, routes: rs.sort((a,b)=>a.id-b.id)})).sort((a,b)=> b.batchId-a.batchId);
  }
  function readFilters(){
    return {
      start: $('#fltStart').val() || '',
      end: $('#fltEnd').val() || '',
      status: ($('#fltStatus').val()||'').toLowerCase(),
      vehicle: $('#fltVeh').val() || '',
      driver: $('#fltDrv').val() || '',
      global: ($('#globalSearch').val()||'').trim().toLowerCase()
    };
  }
  function matchRoute(r, F){
    if(F.start && (!r.date || r.date < F.start)) return false;
    if(F.end && (!r.date || r.date > F.end)) return false;
    if(F.status === 'active'){ if(!(r.status==='open' || r.status==='inprog')) return false; } else if(F.status){ if(r.status !== F.status) return false; }
    if(F.vehicle && r.vehicle !== F.vehicle) return false;
    if(F.driver && r.driver !== F.driver) return false;
    if(F.global){
      const hay = [String(r.name||''), String(r.address||''), String(r.vehicle||''), String(r.driver||''), String(r.date||'')].join(' ').toLowerCase();
      if(hay.indexOf(F.global) === -1) return false;
    }
    return true;
  }
  function flattenFiltered(){
    const F = readFilters(); const groups = groupsByBatchAll(); const rows = [];
    groups.forEach(g=>{ let no=0; g.routes.forEach(r=>{ if(matchRoute(r,F)){ no+=1; rows.push({...r, noInBatch:no}); } }); });
    return rows;
  }

  function statusLabel(st){ if(st==='open') return 'Open'; if(st==='inprog') return 'In Progress'; return 'Selesai'; }
  function statusBadgeHTML(st){ const cls = st==='open'? 'status-badge status-open' : st==='inprog' ? 'status-badge status-inprog' : 'status-badge status-done'; return `<span class="${cls}">${statusLabel(st)}</span>`; }

  function countOverdue(){ const y = today(); return (DATA.routes||[]).filter(r => r.date && r.date < y && (r.status==='open' || r.status==='inprog')).length; }
  function updateOverdueNotice(){ const n = countOverdue(); const $wrap = $('#overdueNotice'); if(n>0){ $('#overdueText').text(`Ada ${n} rute dari hari sebelumnya yang masih OPEN/IN PROGRESS.`); $wrap.removeClass('d-none'); } else { $wrap.addClass('d-none'); } }

  function renderPager(totalPages, page){
    const $pager = $('#pager').empty();
    function btn(p,label,disabled=false,active=false){ const cls = `btn ${active?'btn-primary':'btn-light'} ${disabled?'disabled':''}`; return `<button type="button" class="${cls}" data-p="${p}" ${disabled?'disabled':''}>${label}</button>`; }
    $pager.append(btn(1,'Awal',page<=1)); $pager.append(btn(page-1,'Prev',page<=1));
    const maxPageBtns = 7; let start = Math.max(1,page-3), end = Math.min(totalPages, start+maxPageBtns-1);
    if(end-start<maxPageBtns-1) start = Math.max(1, end-maxPageBtns+1);
    for(let i=start;i<=end;i++){ $pager.append(btn(i,i,false,i===page)); }
    $pager.append(btn(page+1,'Next',page>=totalPages)); $pager.append(btn(totalPages,'Akhir',page>=totalPages));
    $pager.find('button').on('click', function(){ const p = parseInt($(this).data('p'),10); if(!isNaN(p)) renderBatchTable(p); });
  }

  function renderBatchTable(page=1){
    currentPage = page;
    const rowsAll = flattenFiltered(); sortRows(rowsAll);
    const total = rowsAll.length; const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    const startIdx = (page-1)*PAGE_SIZE;
    const rows = rowsAll.slice(startIdx, startIdx + PAGE_SIZE);
    const $wrap = $('#batchTableWrap').empty();
    const $table = $(`<table class="table table-striped table-hover table-sm"><thead class="table-light"><tr>
      <th style="width:150px" class="th-sort" data-key="date">Tanggal <i class="fas fa-sort"></i></th>
      <th style="width:60px" class="th-sort" data-key="no">No <i class="fas fa-sort"></i></th>
      <th style="min-width:240px" class="th-sort" data-key="vehicle">Kendaraan <i class="fas fa-sort"></i></th>
      <th style="min-width:180px" class="th-sort" data-key="driver">Driver <i class="fas fa-sort"></i></th>
      <th style="width:170px" class="th-sort" data-key="status">Status <i class="fas fa-sort"></i></th>
      <th class="th-sort" data-key="address">Alamat <i class="fas fa-sort"></i></th>
      <th class="td-aksi">Aksi</th>
    </tr></thead><tbody></tbody></table>`);
    const $tb = $table.find('tbody');
    if(rows.length===0) $tb.append('<tr><td colspan="7" class="text-center text-muted">Tidak ada data rute</td></tr>');
    else {
      rows.forEach(r=>{
        const alamatLines = [r.name||'']; if(r.address) alamatLines.push(r.address);
        const alamatHTML = esc(alamatLines.filter(Boolean).join('\n')).replace(/\n/g,'<br>');
        const canDelete = (r.status==='open');
        const statusView = `<div class="d-flex align-items-center">${statusBadgeHTML(r.status)}<button class="btn btn-light btn-xs ms-2 btn-edit-status" data-id="${r.id}" data-status="${r.status}"><i class="fas fa-edit"></i> Edit</button></div>`;
        $tb.append(`<tr data-id="${r.id}" data-status="${r.status}"><td>${esc(indoDate(r.date||''))}</td><td>${r.noInBatch}</td><td>${esc(vehicleLabel(r.vehicle))}</td><td>${esc(r.driver||'-')}</td><td class="td-status">${statusView}</td><td class="addr">${alamatHTML}</td><td class="td-aksi"><button type="button" class="btn btn-sm btn-danger btn-del-route" data-id="${r.id}" ${canDelete?'':'disabled title="Hanya bisa hapus jika status OPEN"'}><i class="fas fa-trash"></i></button></td></tr>`);
      });
    }
    $wrap.append($table);
    $wrap.find('.th-sort').on('click', function(){ const key = $(this).data('key'); if(!key) return; if(SORT_KEY===key) SORT_DIR=(SORT_DIR==='asc'?'desc':'asc'); else { SORT_KEY=key; SORT_DIR='asc'; } renderBatchTable(1); });
    $wrap.find('.btn-del-route').on('click', function(){ const rid = Number($(this).data('id')); if(!rid) return; if(!confirm('Hapus rute ini? (hanya status OPEN)')) return; $.post(API_URL, {action:'delete_route', routeId: rid}, function(resp){ if(resp && resp.success){ DATA.routes = DATA.routes.filter(r=> r.id!==rid); const afterTotal = flattenFiltered().length; const maxPage = Math.max(1, Math.ceil(afterTotal / PAGE_SIZE)); renderBatchTable(Math.min(currentPage, maxPage)); } else { alert(resp?.error || 'Gagal hapus rute'); } }, 'json'); });
    renderPager(totalPages, page);
    updateOverdueNotice();
    $('#pageInfo').text(`Menampilkan ${rows.length} dari ${total} hasil — Halaman ${page} / ${totalPages}`);
  }

  $('#batchTableWrap').on('click', '.btn-edit-status', function(){
    const $row = $(this).closest('tr'); const current = String($(this).data('status')); const $cell = $row.find('.td-status');
    const selectHTML = `<div class="d-flex align-items-center"><select class="form-control form-control-sm js-status-edit" style="max-width:150px"><option value="open" ${current==='open'?'selected':''}>Open</option><option value="inprog" ${current==='inprog'?'selected':''}>In Progress</option><option value="done" ${current==='done'?'selected':''}>Selesai</option></select><button class="btn btn-primary btn-xs ms-2 btn-save-status">Simpan</button><button class="btn btn-light btn-xs ms-1 btn-cancel-status">Batal</button><span class="small text-muted ms-2">diubah oleh <?= htmlspecialchars($LOGGED_IN_USER, ENT_QUOTES, 'UTF-8') ?></span></div>`;
    $cell.data('prevHtml', $cell.html()); $cell.data('currentStatus', current); $cell.html(selectHTML);
  });
  $('#batchTableWrap').on('click', '.btn-cancel-status', function(){ const $cell = $(this).closest('.td-status'); const prev = $cell.data('prevHtml'); if(prev !== undefined) $cell.html(prev); });
  $('#batchTableWrap').on('click', '.btn-save-status', function(){
    const $cell = $(this).closest('.td-status'); const $row = $(this).closest('tr'); const rid = Number($row.data('id'));
    const current = String($cell.data('currentStatus') || 'open'); const newVal = String($cell.find('.js-status-edit').val() || current);
    if(newVal === current){ const prev = $cell.data('prevHtml'); if(prev !== undefined) $cell.html(prev); return; }
    if(newVal === 'done'){
      $('#finishRouteId').val(String(rid)); $('#inputCategory').val(''); $('#inputQty').val(''); $('#inputNote').val('');
      $('#editedByLbl').text(<?= json_encode($LOGGED_IN_USER, JSON_UNESCAPED_UNICODE) ?>);
      $('#finishModal').modal('show');
      $('#finishModal').off('hidden.bs.modal').on('hidden.bs.modal', function(){ const prev = $cell.data('prevHtml'); if(prev !== undefined) $cell.html(prev); });
    } else {
      const endpoint = (newVal==='inprog') ? 'start_route' : 'cancel_route';
      $.post(API_URL, {action:endpoint, routeId: rid, updatedBy: <?= json_encode($LOGGED_IN_USER, JSON_UNESCAPED_UNICODE) ?>}, function(resp){
        if(resp && resp.success){ const i = DATA.routes.findIndex(x=>x.id===rid); if(i>-1) DATA.routes[i].status = newVal; renderBatchTable(currentPage); }
        else { alert(resp?.error || 'Gagal update status'); const prev = $cell.data('prevHtml'); if(prev !== undefined) $cell.html(prev); }
      }, 'json').fail(function(){ alert('Request gagal'); const prev = $cell.data('prevHtml'); if(prev !== undefined) $cell.html(prev); });
    }
  });

  function renderUnsaved(){ const tb = $('#tableRute tbody').empty(); unsaved.forEach((r,i)=>{ tb.append(`<tr><td>${i+1}</td><td>${esc(r.name||'')}</td><td>${esc(r.address||'')}</td><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-hide" data-id="${r._tmpId}"><i class="fas fa-times"></i></button></td></tr>`); }); tb.find('.btn-hide').on('click', function(){ const id = String($(this).data('id')); unsaved = unsaved.filter(u=> u._tmpId!==id); if(id.startsWith('start_')) currentStartId=null; renderUnsaved(); }); }
  function buildStartRow(veh,drv,startId){ const m = (DATA.masterRoutes||[]).find(x=> Number(x.id)===Number(startId)); return {_tmpId:'start_'+startId,isStart:true,name:(m?m.name:'')+' (Rute Awal)',address:m?m.address:'',vehicle:veh,driver:drv,status:'open',date:getScheduleDate()}; }
  function ensureStartRow(){ const veh=$('#selectMobil').val(), drv=$('#selectDriver').val(), sid=$('#selectRuteAwal').val(); if(!veh||!drv||!sid) return; currentStartId = Number(sid); const idx = unsaved.findIndex(r=>r.isStart); const fresh = buildStartRow(veh,drv,sid); if(idx===-1) unsaved.unshift(fresh); else unsaved[idx] = {...unsaved[idx], ...fresh}; if(idx>0){ const s=unsaved.splice(idx,1)[0]; unsaved.unshift(s); } renderUnsaved(); }
  function clearUnsaved(){ unsaved=[]; currentStartId=null; renderUnsaved(); }

  function enforcePairOnVehicleChange(){ if(autofillLock) return; const plate=$('#selectMobil').val(); if(!plate){ setDriverLock(false); setStartLock(false); resetSelect('#selectDriver'); resetSelect('#selectRuteAwal'); clearUnsaved(); return; } const pair = activePairByVehicle(plate, getScheduleDate()); if(pair){ withAutofillLock(()=>{ $('#selectDriver').val(pair.driver).trigger('change'); setDriverLock(true); const routesArr = (DATA.routes||[]).filter(r=> r.batchId===pair.batchId); const sid = findStartIdInRoutes(routesArr); if(sid){ $('#selectRuteAwal').val(String(sid)).trigger('change'); setStartLock(true); clearUnsaved(); } else { resetSelect('#selectRuteAwal'); setStartLock(false); clearUnsaved(); } }); } else { setDriverLock(false); setStartLock(false); clearUnsaved(); } }
  function enforcePairOnDriverChange(){ if(autofillLock) return; const drv=$('#selectDriver').val(); if(!drv){ setVehicleLock(false); setStartLock(false); resetSelect('#selectMobil'); resetSelect('#selectRuteAwal'); clearUnsaved(); return; } const pair = activePairByDriver(drv, getScheduleDate()); if(pair){ withAutofillLock(()=>{ $('#selectMobil').val(pair.vehicle).trigger('change'); setVehicleLock(true); const routesArr = (DATA.routes||[]).filter(r=> r.batchId===pair.batchId); const sid = findStartIdInRoutes(routesArr); if(sid){ $('#selectRuteAwal').val(String(sid)).trigger('change'); setStartLock(true); clearUnsaved(); } else { resetSelect('#selectRuteAwal'); setStartLock(false); clearUnsaved(); } }); } else { setVehicleLock(false); setStartLock(false); clearUnsaved(); } }

  function bindHandlers(){
    $('#btnApplyFilter').on('click', ()=>{ runAutoClose(function(){ renderBatchTable(1); }); });
    $('#btnResetFilter').on('click', ()=>{ $('#fltStatus').val(''); $('#fltVeh,#fltDrv').val(null).trigger('change'); $('#fltStart').val(today()); $('#fltEnd').val(today()); $('#globalSearch').val(''); renderBatchTable(1); });
    $('#globalSearch').on('input', debounce(()=> renderBatchTable(1), 300));
    $('#clearGlobalSearch').on('click', function(){ $('#globalSearch').val(''); renderBatchTable(1); });

    $('#btnShowOverdue').on('click', ()=>{ $('#fltStatus').val('active'); $('#fltVeh,#fltDrv').val(null).trigger('change'); $('#fltStart').val(''); $('#fltEnd').val(yesterday()); renderBatchTable(1); });

    $('#dtSchedule').val(nowLocalISO());
    $('#btnSetNow').on('click', ()=> $('#dtSchedule').val(nowLocalISO()).trigger('change'));
    $('#btnSetTomorrow').on('click', ()=> $('#dtSchedule').val(tomorrowLocalISO()).trigger('change'));
    $('#dtSchedule').on('change', ()=>{ const d=getScheduleDate(); unsaved = unsaved.map(u=>({...u, date:d})); renderUnsaved(); if($('#selectRuteAwal').val()) ensureStartRow(); refreshPairLocks(); });

    $('#btnAddRute').on('click', ()=>{ const veh=$('#selectMobil').val(), drv=$('#selectDriver').val(), a=$('#selectRuteAwal').val(), t=$('#selectRuteTujuan').val(); if(!veh||!drv||!a||!t){ alert('Lengkapi mobil, driver, rute awal & tujuan.'); return; } if(a===t){ alert('Rute awal & tujuan tidak boleh sama'); return; } ensureStartRow(); const m = (DATA.masterRoutes||[]).find(x=> String(x.id)===String(t)); unsaved.push({_tmpId:'tmp_'+Date.now(), name:m?m.name:'', address:m?m.address:'', vehicle:veh, driver:drv, status:'open', date:getScheduleDate()}); renderUnsaved(); });

    $('#btnSimpan').on('click', ()=>{ if(unsaved.length===0){ alert('Tidak ada rute baru'); return; } if(!confirm('Simpan '+unsaved.length+' rute ke database?')) return; const copy=[...unsaved]; const sIdx = copy.findIndex(r=> r.isStart); if(sIdx>0){ const s = copy.splice(sIdx,1)[0]; copy.unshift(s); } const dYMD=getScheduleDate(); const payload = copy.map(u=>({ name:u.name,address:u.address,vehicle_plate:u.vehicle,driver_name:u.driver,status:u.status||'open',route_date:u.date||dYMD })); const extra={ schedule_date:dYMD, schedule_created_at:scheduleMidnightOneSec(dYMD), batch_date:dYMD }; $.post(API_URL, Object.assign({ action:'save_batch', createdBy: CREATED_BY, routes: JSON.stringify(payload) }, extra), function(resp){ if(resp && resp.success){ alert('Batch '+resp.batchNo+' tersimpan ('+resp.mode+')'); location.replace(location.pathname+'?ts='+Date.now()); } else { alert(resp?.error || 'Gagal simpan'); } }, 'json').fail(function(xhr, status){ const prev=(xhr.responseText||'').slice(0,400); alert(`Request error — ${xhr.status} (${status}).\nPreview:\n${prev}`); }); });

    $('#selectRuteAwal').on('change', ensureStartRow);
    $('#selectMobil').on('change', ()=>{ pairSource='veh'; if($('#selectRuteAwal').val()) ensureStartRow(); enforcePairOnVehicleChange(); });
    $('#selectDriver').on('change', ()=>{ pairSource='drv'; if($('#selectRuteAwal').val()) ensureStartRow(); enforcePairOnDriverChange(); });

    $('#finishForm').on('submit', function(e){ e.preventDefault(); const rid = Number($('#finishRouteId').val()); const category = String($('#inputCategory').val()||''); const qty = String($('#inputQty').val()||''); const note = String($('#inputNote').val()||''); if(!rid){ $('#finishModal').modal('hide'); return; } $.post(API_URL, {action:'finish_route', routeId: rid, category, qty, note, updatedBy: <?= json_encode($LOGGED_IN_USER, JSON_UNESCAPED_UNICODE) ?>}, function(resp){ if(resp && resp.success){ const i = DATA.routes.findIndex(x=>x.id===rid); if(i>-1) DATA.routes[i].status='done'; $('#finishModal').modal('hide'); renderBatchTable(currentPage); } else { alert(resp?.error || 'Gagal menyelesaikan rute'); } }, 'json').fail(function(){ alert('Request gagal'); }); });

    $('#modalRute').on('hidden.bs.modal', function(){ $('#selectMobil,#selectDriver,#selectRuteAwal,#selectRuteTujuan').val(null).trigger('change'); $('#dtSchedule').val(nowLocalISO()); clearUnsaved(); });

    $('#btnAutoclose').on('click', function(){ const $btn = $(this).prop('disabled', true).text('Processing...'); $.post(API_URL, { action:'autoclose_overdue' }).done(function(r){ if (r && r.success) { alert('Auto-close sukses' + (r.closedIds && r.closedIds.length ? (': ' + r.closedIds.length + ' route') : '')); location.reload(); } else { alert('Auto-close gagal:\n' + (r && r.error ? r.error : 'unknown')); } }).fail(function(xhr){ alert('Auto-close gagal\nHTTP ' + xhr.status + '\n' + (xhr.responseText || '')); console.error('detail:', xhr); }).always(function(){ $btn.prop('disabled', false).text('Auto Close Overdue'); }); });
  }

  function runAutoClose(done){
    $.post(API_URL, {action:'autoclose_overdue'}, function(resp){ if(resp && resp.success){ const ids = resp.closedIds || []; if(Array.isArray(ids) && ids.length) DATA.routes = DATA.routes.map(r => ids.includes(r.id) ? {...r, status:'done'} : r); } if(typeof done==='function') done(); }, 'json').fail(function(){ if(typeof done==='function') done(); });
  }

  function refreshPairLocks(){ const veh=$('#selectMobil').val(); const drv=$('#selectDriver').val(); if(pairSource === 'veh' && veh){ enforcePairOnVehicleChange(); return; } if(pairSource === 'drv' && drv){ enforcePairOnDriverChange(); return; } if(veh){ enforcePairOnVehicleChange(); return; } if(drv){ enforcePairOnDriverChange(); return; } }

  function fillSelects(){
    setSelectOptions('#fltVeh', [{value:'', text:'Semua Kendaraan'}].concat((DATA.vehicles||[]).map(v=>({value:v.plate, text:v.plate}))), false);
    setSelectOptions('#fltDrv', [{value:'', text:'Semua Driver'}].concat((DATA.drivers||[]).map(d=>({value:d.name, text:d.name}))), false);
    setSelectOptions('#selectMobil', (DATA.vehicles||[]).map(v=>({value:v.plate, text:v.plate})), true);
    setSelectOptions('#selectDriver', (DATA.drivers||[]).map(d=>({value:d.name, text:d.name})), true);
    setSelectOptions('#selectRuteAwal', (DATA.masterRoutes||[]).map(m=>({value:m.id, text:m.name})), true);
    setSelectOptions('#selectRuteTujuan', (DATA.masterRoutes||[]).map(m=>({value:m.id, text:m.name, dataset:{address:m.address||''}})), true);
    $('#fltStart').val(today()); $('#fltEnd').val(today());
  }

  // start
  $(function(){
    fillSelects();
    initS2();
    bindHandlers();
    renderUnsaved();
    runAutoClose(function(){ renderBatchTable(1); updateOverdueNotice(); });
  });
})();
</script>
