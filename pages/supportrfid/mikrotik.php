<?php
require_once 'includes/routeros_api.class.php';
ini_set('session.gc_maxlifetime', 86400); // 24 jam
ini_set('session.cookie_lifetime', 86400);
session_set_cookie_params(86400);
session_start();

$API = new RouterosAPI();
$API->debug = false;
$API->timeout = 10;

$message = '';
$connected = false;

// === LOGOUT ===
if (isset($_POST['logout'])) {
  session_destroy();
  header("Location: mikrotik.php");
  exit;
}

// === LOGIN ===
if (isset($_POST['action']) && $_POST['action'] === 'login') {
  $ip = trim($_POST['router_ip']);
  $user = trim($_POST['username']);
  $pass = trim($_POST['password']);
  $port = 8728;

  if ($API->connect($ip, $user, $pass, $port)) {
    $_SESSION['mikrotik_router_ip'] = $ip;
    $_SESSION['mikrotik_username'] = $user;
    $_SESSION['mikrotik_password'] = $pass;
    $connected = true;
    $message = "✅ Berhasil terhubung ke Mikrotik";

    // Redirect agar tidak confirm form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?connected=1");
    exit;
  } else {
    $message = "❌ Gagal konek ke Mikrotik! Pastikan /ip service enable api dan firewall mengizinkan port 8728.";
  }
}

// === AUTO-CONNECT DARI KONFIG / SESSION ===
$config = include 'mikrotik_config.php';
if (isset($_SESSION['mikrotik_router_ip'], $_SESSION['mikrotik_username'], $_SESSION['mikrotik_password'])) {
  $connected = true;
} else {
  if ($API->connect($config['ip'], $config['user'], $config['pass'], $config['port'])) {
    $_SESSION['mikrotik_router_ip'] = $config['ip'];
    $_SESSION['mikrotik_username'] = $config['user'];
    $_SESSION['mikrotik_password'] = $config['pass'];
    $connected = true;
    $API->disconnect();
  }
}

// === HELPER FUNCTIONS ===
function formatSpeed($value) {
  if (!is_numeric($value) || $value == 0) return '0 bps';
  if ($value >= 1000000) return round($value / 1000000, 2) . ' Mbps';
  if ($value >= 1000) return round($value / 1000, 2) . ' Kbps';
  return $value . ' bps';
}

function formatBytes($bytes) {
  if (!is_numeric($bytes) || $bytes == 0) return '0 B';
  $units = ['B', 'KB', 'MB', 'GB', 'TB'];
  $power = floor(log($bytes, 1024));
  return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}

function formatIp($ip) {
  $parts = explode('/', $ip);
  return $parts[0];
}

function fetchQueueData($API, $ip, $user, $pass, $port = 8728) {
  $result = [];
  if ($API->connect($ip, $user, $pass, $port)) {
    $rawData = $API->comm('/queue/simple/print');
    foreach ($rawData as $row) {
      $uploadMax = $downloadMax = $uploadAvg = $downloadAvg = '';
      $uploadBytes = $downloadBytes = 0;
      if (!empty($row['max-limit'])) {
        [$uploadMax, $downloadMax] = explode('/', $row['max-limit']);
      }
      if (!empty($row['rate'])) {
        [$uploadAvg, $downloadAvg] = explode('/', $row['rate']);
      }
      if (!empty($row['bytes'])) {
        [$uploadBytes, $downloadBytes] = explode('/', $row['bytes']);
      }
      $result[] = [
        'Name' => $row['name'] ?? '',
        'Target' => formatIp($row['target'] ?? ''),
        'Upload Max Limit' => formatSpeed($uploadMax),
        'Download Max Limit' => formatSpeed($downloadMax),
        'Upload Avg. Rate' => formatSpeed($uploadAvg),
        'Download Avg. Rate' => formatSpeed($downloadAvg),
        'Total Uploaded' => formatBytes($uploadBytes),
        'Total Downloaded' => formatBytes($downloadBytes),
      ];
    }
    $API->disconnect();
  } else {
    throw new Exception("Tidak dapat konek ke Mikrotik.");
  }
  return $result;
}

// === MODE AJAX UNTUK REFRESH DATA TANPA RELOAD ===
if (isset($_GET['ajax']) && $_GET['ajax'] === 'pull' && $connected) {
  header('Content-Type: application/json');
  try {
    $ip = $_SESSION['mikrotik_router_ip'];
    $user = $_SESSION['mikrotik_username'];
    $pass = $_SESSION['mikrotik_password'];
    $data = fetchQueueData($API, $ip, $user, $pass, 8728);
    echo json_encode(['ok' => true, 'data' => $data]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

// === FETCH DATA NORMAL ===
$dataResult = [];
if (isset($_POST['data_type']) && $connected) {
  $_SESSION['last_data_type'] = $_POST['data_type']; // simpan ke session agar reload tidak hilang
  $ip = $_SESSION['mikrotik_router_ip'];
  $user = $_SESSION['mikrotik_username'];
  $pass = $_SESSION['mikrotik_password'];
  try {
    if ($_POST['data_type'] === 'queue') {
      $dataResult = fetchQueueData($API, $ip, $user, $pass, 8728);
    }
  } catch (Exception $e) {
    $message = "❌ Tidak bisa konek ke Mikrotik (koneksi terputus).";
    $connected = false;
    session_destroy();
  }

  // Redirect setelah POST agar tidak kehilangan data saat refresh
  $_SESSION['last_data'] = $dataResult;
  header("Location: " . $_SERVER['PHP_SELF'] . "?show=data");
  exit;
}

// === AMBIL DATA DARI SESSION SAAT REFRESH ===
if (isset($_GET['show']) && $_GET['show'] === 'data' && isset($_SESSION['last_data'])) {
  $dataResult = $_SESSION['last_data'];
}
?>


<!DOCTYPE html>
<html lang="id">
<meta http-equiv="refresh" content="60">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mikrotik Queue Monitor</title>
<link rel="stylesheet" href="stylestabel.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<?php include 'sidebar.php'; ?>
<div class="content">
<div class="container">

<?php if ($message): ?>
<div class="alert <?= strpos($message,'❌')!==false?'error':'success' ?>"><?= $message ?></div>
<?php endif; ?>

<?php if (!$connected): ?>
<div class="login-box">
  <h3>Login ke Mikrotik</h3>
  <form method="POST" id="loginForm">
    <input type="hidden" name="action" value="login">
    <label>IP Address Mikrotik:</label>
    <input type="text" name="router_ip" id="router_ip" placeholder="192.168.88.1" required>
    <label>Username:</label>
    <input type="text" name="username" id="username" placeholder="admin" required>
    <label>Password:</label>
    <input type="password" name="password" id="password" placeholder="Password">
    <button type="submit">🔗 Konek</button>
  </form>
</div>

<?php else: ?>
<div class="connection-info">
  <div class="conn-left">
    <span class="status-dot"></span>
    <span>Terhubung ke:</span>
    <strong><?= htmlspecialchars($_SESSION['mikrotik_router_ip']) ?></strong>
    <span class="port-info">:8728</span>
  </div>

  <div class="toolbar-right">
    <form method="POST">
      <button name="logout" class="btn btn-red">🔒 Logout</button>
    </form>
  </div>
</div>


<!-- TOOLBAR -->
<div class="toolbar">
  <div class="toolbar-left">
    <form method="POST" class="inline-form">
      <select name="data_type" required>
        <option value="">📂 Pilih Data</option>
        <option value="queue">Queue List</option>
      </select>
      <button type="submit" class="btn btn-green">📥 Tarik Data</button>
    </form>
  </div>

 
</div><?php if (!empty($dataResult)): ?>
<!-- AUTO EXPORT -->
<div class="auto-box">
  <h3>🕓 Jadwal Export Otomatis</h3>
  <div class="schedule-row">
    <div class="time-group">
      <label>Jam:</label>
      <input type="time" id="schedTime" value="08:00">
    </div>
    <div class="repeat-group">
      <label>Ulangi:</label>
      <select id="schedRepeat">
        <option value="harian" selected>Harian</option>
        <option value="weekdays">Senin–Jumat</option>
        <option value="weekly">Mingguan</option>
      </select>
    </div>
    <div class="action-group">
      <button id="startSched" type="button" class="btn btn-primary">▶️ Mulai</button>
      <button id="stopSched" type="button" class="btn btn-warning">⏹️ Stop</button>
      <button id="testExportBtn" type="button" class="btn btn-success" style="background-color: #16a085; margin-left: 10px;">🧪 Test Export</button>
      <span id="schedStatus" class="status-text">Status: <b>Nonaktif</b></span>
    </div>
  </div>
  <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px; display: none;" id="testPanel">
    <label><strong>🧪 Mode Testing:</strong></label>
    <div style="margin-top: 10px; font-size: 12px; color: #666;">
      <p id="testLog" style="margin: 0; line-height: 1.6;">Klik "Test Export" untuk simulasi export.</p>
    </div>
  </div>
  <div style="margin-top: 15px; padding: 12px; background: #e8f4f8; border: 1px solid #2196F3; border-radius: 5px;" id="debugPanel">
    <label><strong>🔍 Debug Monitor:</strong> <button id="toggleDebug" style="float: right; padding: 2px 8px; font-size: 12px; cursor: pointer; border: 1px solid #2196F3; background: #2196F3; color: white; border-radius: 3px;">Tutup</button></label>
    <div style="margin-top: 10px; font-size: 11px; color: #0c5460; background: white; padding: 8px; border-radius: 3px; max-height: 200px; overflow-y: auto; font-family: monospace;" id="debugLog">
      <div>⏳ Memuat informasi monitor...</div>
    </div>
  </div>
</div>

<!-- Letakkan SCRIPT di sini, di bawah HTML -->
<script>
document.addEventListener('DOMContentLoaded', () => {
(function(){
  const schedTimeInput = document.getElementById('schedTime');
  const schedRepeatInput = document.getElementById('schedRepeat');
  const startBtn = document.getElementById('startSched');
  const stopBtn  = document.getElementById('stopSched');
  const statusEl = document.getElementById('schedStatus');
  // Pisahkan key agar test manual tidak menghalangi jadwal otomatis
  const autoRunKey = 'mikro_export_lastRun_auto';            // ISO timestamp terakhir export otomatis
  const autoRunDateKey = 'mikro_last_auto_export_date';      // YYYY-MM-DD untuk mencegah double di hari sama
  const testRunKey = 'mikro_export_lastRun_test';            // ISO timestamp test manual
  let cachedSchedule = null;

   function renderStatus(s) {
    if (!s) {
        statusEl.textContent = 'Status: Nonaktif';
        statusEl.style.backgroundColor = '#e74c3c'; // merah cerah
        statusEl.style.color = 'white';            // teks putih
        statusEl.style.padding = '6px 12px';
        statusEl.style.borderRadius = '5px';
        statusEl.style.fontWeight = 'bold';
      return;
    }
    statusEl.textContent = `Status: Aktif • ${s.time} • ${s.repeat}`;
    statusEl.style.backgroundColor = '#27ae60'; // hijau cerah
    statusEl.style.color = 'white';
    statusEl.style.padding = '6px 12px';
    statusEl.style.borderRadius = '5px';
    statusEl.style.fontWeight = 'bold';
  }

  async function saveSchedule(obj){
    try{
      const r = await fetch('save_schedule.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(obj)
      });
      const j = await r.json();
      if(!j.ok) throw new Error(j.error||'Gagal simpan jadwal');
      cachedSchedule = obj;
      localStorage.removeItem(autoRunKey);
      localStorage.removeItem(autoRunDateKey);
      renderStatus(obj);
    }catch(e){console.error(e);alert('Gagal simpan jadwal!');}
  }

  async function loadSchedule(){
    try{
      const r = await fetch('load_schedule.php?get=1&_='+Date.now());
      const j = await r.json();
      if(j.ok){cachedSchedule=j.data;return j.data;}
      return null;
    }catch(e){console.error(e);return cachedSchedule;}
  }

  async function clearSchedule(){
    try{
      await fetch('load_schedule.php?clear=1',{method:'POST'});
      cachedSchedule=null;
      localStorage.removeItem(autoRunKey);
      localStorage.removeItem(autoRunDateKey);
      renderStatus(null);
    }catch(e){console.error(e);}
  }

  function isDue(now,sched){
    const [sh,sm] = sched.time.split(':').map(Number);
    const schedTime = new Date(now);
    schedTime.setHours(sh, sm, 0, 0);
    const diff = Math.abs(now - schedTime);
    // window 1 menit sebelum dan sesudah jam jadwal
    return diff <= 60000;
  }

  async function pullData(){
    const r=await fetch('mikrotik.php?ajax=pull');
    const j=await r.json();
    if(!j.ok) throw new Error(j.error||'Tarik gagal');
    return j.data;
  }

  async function exportExcel(data){
    try{
      const fd=new FormData();
      fd.append('data',JSON.stringify(data));
      const resp=await fetch('uploadmikro.php',{method:'POST',body:fd});
      const blob=await resp.blob();
      const url=URL.createObjectURL(blob);
      const ts=new Date();
      const name=`mikrotik-${ts.getFullYear()}${String(ts.getMonth()+1).padStart(2,'0')}${String(ts.getDate()).padStart(2,'0')}_${String(ts.getHours()).padStart(2,'0')}${String(ts.getMinutes()).padStart(2,'0')}.xlsx`;
      const a=document.createElement('a');
      a.href=url;a.download=name;a.click();
      URL.revokeObjectURL(url);
      return true;
    }catch(e){console.error(e);return false;}
  }

  let ticking=false;
  async function tick(){
    if(ticking) return;
    ticking=true;
    try{
      const sched = cachedSchedule || await loadSchedule();
      if(!sched) return;
      const now = new Date();
      const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
      const lastRunDate = localStorage.getItem(autoRunDateKey);

      // Tentukan hari valid
      let isScheduledDay=false;
      if(sched.repeat==='harian') isScheduledDay=true; else if(sched.repeat==='weekdays'){ const d=now.getDay(); isScheduledDay = d>=1 && d<=5; } else if(sched.repeat==='weekly'){ isScheduledDay = now.getDay()===(sched.weekday ?? now.getDay()); }

      // Sudah jalan hari ini?
      const alreadyToday = lastRunDate === todayStr;
      if(alreadyToday) return; // tidak perlu cek waktu

      // Jam tercapai?
      const [h,m] = sched.time.split(':').map(Number);
      const nowTotal = now.getHours()*60 + now.getMinutes();
      const schedTotal = h*60 + m;

      if(isScheduledDay && nowTotal >= schedTotal){
        const catchUp = nowTotal > schedTotal;
        console.log('Eksekusi export otomatis:', catchUp?'catch-up (melewati jam)':'on-time', sched.time, now.toLocaleString());
        statusEl.textContent='Status: Export berjalan...';
        statusEl.style.backgroundColor='#f39c12';statusEl.style.color='#fff';
        try {
          const data = await pullData();
          const ok = await exportExcel(data);
          if(ok){
            localStorage.setItem(autoRunKey, now.toISOString());
            localStorage.setItem(autoRunDateKey, todayStr);
            statusEl.textContent=`Status: Export selesai ${catchUp?'(catch-up) ':''}${now.toLocaleTimeString()}`;
            statusEl.style.backgroundColor='#3498db';
          } else {
            statusEl.textContent='Status: Export gagal';
            statusEl.style.backgroundColor='#e74c3c';
          }
        } catch(err){
          console.error('Export error', err);
          statusEl.textContent='Status: Export error';
          statusEl.style.backgroundColor='#e74c3c';
        }
        setTimeout(()=>renderStatus(sched),5000);
      }
    }catch(e){ console.error('Tick error', e); }
    finally{ ticking=false; }
  }

  startBtn.addEventListener('click',()=>{
    const t=schedTimeInput.value||'08:00';
    const r=schedRepeatInput.value||'harian';
    const s={time:t,repeat:r};
    if(r==='weekly') s.weekday=new Date().getDay();
    saveSchedule(s);
  });
  stopBtn.addEventListener('click',clearSchedule);

  // === DEBUG MONITOR ===
  const debugPanel = document.getElementById('debugPanel');
  const debugLog = document.getElementById('debugLog');
  const toggleDebugBtn = document.getElementById('toggleDebug');
  let debugVisible = true;
  
  toggleDebugBtn.addEventListener('click', ()=>{
    debugVisible = !debugVisible;
    debugLog.style.display = debugVisible ? 'block' : 'none';
    toggleDebugBtn.textContent = debugVisible ? 'Tutup' : 'Buka';
  });
  
  function updateDebugMonitor(){
    const now = new Date();
    const autoLastRun = localStorage.getItem(autoRunKey);      // ISO terakhir
    const autoLastRunDate = localStorage.getItem(autoRunDateKey); // YYYY-MM-DD terakhir
    const testLastRun = localStorage.getItem(testRunKey);
    const sched = cachedSchedule;
    
    let html = `<div style="margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 6px;">`;
    html += `<strong>⏰ Waktu Sekarang:</strong> ${now.toLocaleString('id-ID')}<br>`;
    html += `</div>`;
    
    if(!sched){
      html += `<div style="color: #ff9800;"><strong>⚠️ Jadwal:</strong> Belum dikonfigurasi</div>`;
    }else{
      html += `<div style="margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 6px;">`;
      html += `<strong>✅ Jadwal Aktif:</strong><br>`;
      html += `&nbsp;&nbsp;Jam: <code>${sched.time}</code><br>`;
      html += `&nbsp;&nbsp;Tipe: <code>${sched.repeat}</code>`;
      if(sched.weekday !== undefined){
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        html += `<br>&nbsp;&nbsp;Hari: <code>${days[sched.weekday]}</code>`;
      }
      html += `<br>`;
      html += `</div>`;
      
      // Status sederhana sesuai logika baru
      const [sh,sm] = sched.time.split(':').map(Number);
      const schedTotal = sh*60 + sm;
      const nowTotal = now.getHours()*60 + now.getMinutes();
      let isScheduledDay=false;
      if(sched.repeat==='harian') isScheduledDay=true; else if(sched.repeat==='weekdays'){ const d=now.getDay(); isScheduledDay = d>=1 && d<=5; } else if(sched.repeat==='weekly'){ isScheduledDay = now.getDay()===(sched.weekday ?? now.getDay()); }
      const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
      const alreadyToday = autoLastRunDate === todayStr;
      const formatHMS = (s)=>{const h=Math.floor(s/3600);const m=Math.floor((s%3600)/60);const sec=s%60;return [h,m,sec].map(v=>String(v).padStart(2,'0')).join(':');};
      let countdownLine='';
      if(!isScheduledDay) countdownLine='-(bukan hari jadwal)';
      else if(alreadyToday) countdownLine='Sudah dijalankan';
      else if(nowTotal >= schedTotal) countdownLine='Lewat - akan catch-up (menjalankan)';
      else {
        const remainSec = (schedTotal - nowTotal)*60 - now.getSeconds();
        countdownLine = formatHMS(remainSec);
      }
      html += `<div style=\"margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 6px;\">`;
      html += `<strong>📋 Status Hari Ini:</strong><br>`;
      html += `&nbsp;&nbsp;${isScheduledDay ? '✅' : '❌'} Sesuai jadwal: <code>${isScheduledDay ? 'Ya' : 'Tidak'}</code><br>`;
      html += `&nbsp;&nbsp;${nowTotal >= schedTotal ? '✅' : '⏳'} Jam tercapai: <code>${nowTotal >= schedTotal ? 'Ya' : 'Belum'}</code><br>`;
      html += `&nbsp;&nbsp;⏱️ Hitung mundur / Status: <code>${countdownLine}</code><br>`;
      html += `</div>`;
    }
    
    // Rincian run otomatis
    if(!autoLastRunDate){
      html += `<div style=\"color:#ff9800;\"><strong>⏸️ Auto Run:</strong> Belum pernah jalan</div>`;
    } else {
      const isoStr = autoLastRun ? new Date(autoLastRun).toLocaleString('id-ID') : autoLastRunDate;
      const isSameDay = autoLastRunDate === `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
      html += `<div style=\"margin-bottom:6px;\"><strong>📅 Auto Run Terakhir:</strong> <code>${isoStr}</code> • ${isSameDay?'<span style=\"color:#27ae60;\">Hari ini</span>':'<span style=\"color:#e67e22;\">Bukan hari ini</span>'}</div>`;
    }
    // Rincian run test manual
    if(testLastRun){
      const testDate = new Date(testLastRun);
      html += `<div style=\"margin-bottom:6px;\"><strong>🧪 Test Terakhir:</strong> <code>${testDate.toLocaleString('id-ID')}</code></div>`;
    } else {
      html += `<div style=\"color:#888;\"><strong>🧪 Test:</strong> Belum pernah</div>`;
    }
    
    html += `<div style="font-size: 10px; color: #999; margin-top: 6px;">`;
    html += `🔄 Update otomatis setiap 2 detik`;
    html += `</div>`;
    
    debugLog.innerHTML = html;
  }
  
  updateDebugMonitor();
  setInterval(updateDebugMonitor, 2000);

  // === TEST EXPORT FUNCTION ===
  const testBtn = document.getElementById('testExportBtn');
  const testPanel = document.getElementById('testPanel');
  const testLog = document.getElementById('testLog');
  
  function addTestLog(msg, type='info'){
    const now = new Date().toLocaleTimeString('id-ID');
    const color = type==='error'?'#e74c3c':type==='success'?'#27ae60':'#333';
    const prefix = type==='error'?'❌':type==='success'?'✅':'ℹ️';
    testLog.innerHTML += `<div style="color:${color};">${prefix} [${now}] ${msg}</div>`;
    testLog.scrollTop = testLog.scrollHeight;
  }
  
  testBtn.addEventListener('click', async ()=>{
    testPanel.style.display = 'block';
    testLog.innerHTML = '🧪 Simulasi Export dimulai...\n';
    testBtn.disabled = true;
    testBtn.textContent = '⏳ Sedang diproses...';
    
    try{
      addTestLog('1. Mengambil data dari Mikrotik...', 'info');
      const data = await pullData();
      addTestLog(`2. Data berhasil diambil (${data.length} baris)`, 'success');
      
      addTestLog('3. Membuat file Excel...', 'info');
      const ok = await exportExcel(data);
      
      if(ok){
        addTestLog('4. Export berhasil! File telah diunduh.', 'success');
        addTestLog('💡 Test ini tidak menahan jadwal otomatis.', 'info');
        localStorage.setItem(testRunKey, new Date().toISOString());
      }else{
        addTestLog('Export gagal!', 'error');
      }
    }catch(err){
      console.error('Test error:', err);
      addTestLog(`Error: ${err.message}`, 'error');
    }finally{
      testBtn.disabled = false;
      testBtn.textContent = '🧪 Test Export';
    }
  });

  (async()=>{
    const s=await loadSchedule();
    if(s){schedTimeInput.value=s.time;schedRepeatInput.value=s.repeat;}
    renderStatus(s);
  })();

  setInterval(tick,10000);
  setTimeout(tick,2000);

  let lastStatusJson=null;
  async function syncStatusLoop(){
    try{
      const newStatus=await loadSchedule();
      const newJson=JSON.stringify(newStatus);
      if(newJson!==lastStatusJson){
        renderStatus(newStatus);
        if(newStatus){
          schedTimeInput.value=newStatus.time;
          schedRepeatInput.value=newStatus.repeat;
        }
        lastStatusJson=newJson;
      }
    }catch(err){console.error('Sync error',err);}
  }
  setInterval(syncStatusLoop,5000);
})(); 
});
</script>




<!-- TABLE CONTROLS -->
<div class="table-controls">
  <input type="text" id="searchInput" placeholder="🔍 Cari nama / target...">
  <div class="button-group">
    <button id="resetBtn" class="btn btn-yellow">🔄 Reset</button>
    <button id="showChartBtn" class="btn btn-info">📊 Pemakaian Tertinggi</button>
    <form action="uploadmikro.php" method="POST">
      <input type="hidden" name="data" value='<?= json_encode($dataResult); ?>'>
      <button type="submit" class="btn btn-success">⬇️ Export Excel</button>
    </form>
  </div>
</div>
<div class="table-container">
  <!-- === Tabel untuk desktop === -->
  <table id="mikrotikTable">
    <thead>
      <tr>
        <th>No</th>
        <th class="sortable">Name</th>
        <th>Target</th>
        <th>Upload Max</th>
        <th>Download Max</th>
        <th>Upload Avg</th>
        <th>Download Avg</th>
        <th class="sortable">Total Uploaded</th>
        <th class="sortable">Total Downloaded</th>
      </tr>
    </thead>
    <tbody>
      <?php $no=1; foreach($dataResult as $row): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td><?= htmlspecialchars($row['Name']) ?></td>
        <td><?= htmlspecialchars($row['Target']) ?></td>
        <td><?= htmlspecialchars($row['Upload Max Limit']) ?></td>
        <td><?= htmlspecialchars($row['Download Max Limit']) ?></td>
        <td><?= htmlspecialchars($row['Upload Avg. Rate']) ?></td>
        <td><?= htmlspecialchars($row['Download Avg. Rate']) ?></td>
        <td><?= htmlspecialchars($row['Total Uploaded']) ?></td>
        <td><?= htmlspecialchars($row['Total Downloaded']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- === Card list untuk mobile === -->
  <div class="card-list">
    <?php $no=1; foreach($dataResult as $row): ?>
      <div class="card">
        <p><b>No</b><span><?= $no++ ?></span></p>
        <p><b>Name</b><span><?= htmlspecialchars($row['Name']) ?></span></p>
        <p><b>Target</b><span><?= htmlspecialchars($row['Target']) ?></span></p>
        <p><b>Upload Max</b><span><?= htmlspecialchars($row['Upload Max Limit']) ?></span></p>
        <p><b>Download Max</b><span><?= htmlspecialchars($row['Download Max Limit']) ?></span></p>
        <p><b>Upload Avg</b><span><?= htmlspecialchars($row['Upload Avg. Rate']) ?></span></p>
        <p><b>Download Avg</b><span><?= htmlspecialchars($row['Download Avg. Rate']) ?></span></p>
        <p class="sortable"><b>Total Uploaded</b><span><?= htmlspecialchars($row['Total Uploaded']) ?></span></p>
        <p class="sortable"><b>Total Downloaded</b><span><?= htmlspecialchars($row['Total Downloaded']) ?></span></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>


<!-- Pagination -->
<div class="pagination-container">
  <button id="prevPage" class="btn btn-small">⏮ Prev</button>
  <span id="pageInfo"></span>
  <button id="nextPage" class="btn btn-small">Next ⏭</button>
</div>

<!-- Chart Modal -->
<div id="chartModal">
  <div class="chart-modal-content">
    <span class="close-chart">&times;</span>
    <h3>Top 10 Pemakaian Tertinggi</h3>
    <canvas id="top10Chart"></canvas>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
</div>


<style>
/* General */
body {
  font-family: 'Segoe UI', sans-serif;
  background: #f5f7fb;
  color: #333;
  margin: 0;
}
.container {
  padding: 25px;
}
.connection-info {
  font-size: 15px;
  margin-bottom: 15px;
  color: #0066cc;
}

/* Toolbar */
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  background: #ffffff;
  padding: 10px 15px;
  border-radius: 10px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.08);
  margin-bottom: 20px;
}
.inline-form {
  display: flex;
  gap: 10px;
  align-items: center;
}
.toolbar select {
  padding: 8px;
  border-radius: 6px;
  border: 1px solid #ccc;
  background: #fff;
}

/* Buttons */
.btn {
  border: none;
  padding: 8px 15px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}
.btn:hover {
  transform: scale(1.05);
  opacity: 0.9;
}
.btn-success { background: #28a745; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-primary { background: #007bff; color: #fff; }
.btn-warning { background: #ffc107; color: #000; }
.btn-secondary { background: #6c757d; color: #fff; }
.btn-info { background: #17a2b8; color: #fff; }

/* Auto Export Box */
.auto-box {
  background: #ffffff;
  border-radius: 10px;
  padding: 15px 20px;
  margin-bottom: 20px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.07);
}
.auto-box h3 {
  margin-top: 0;
  margin-bottom: 10px;
  color: #333;
}
.schedule-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 15px;
}
.schedule-row label {
  font-weight: 500;
  margin-right: 5px;
}
.status-text {
  font-size: 14px;
  margin-left: 10px;
  color: #555;
}

/* Table Controls */
.table-controls {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  gap: 10px;
}
#searchInput {
  flex: 1;
  min-width: 250px;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 8px;
}
.button-group {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* Table */
.table-container {
  overflow-x: auto;
}
#mikrotikTable {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
#mikrotikTable th, #mikrotikTable td {
  padding: 10px;
  border: 1px solid #ddd;
  text-align: center;
  font-size: 13px;
}
#mikrotikTable th {
  background: linear-gradient(90deg, #007bff, #17a2b8);
  color: white;
}
#mikrotikTable tr:nth-child(even) { background: #f9fafb; }
#mikrotikTable tr:hover { background: #eef7ff; }

/* Pagination */
.pagination-container {
  text-align: center;
  margin-top: 15px;
}
.pagination-container button {
  margin: 0 5px;
}

/* Chart Modal */
#chartModal {
  display: none;
  position: fixed;
  z-index: 9999;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.6);
  justify-content: center;
  align-items: center;
  backdrop-filter: blur(4px);
}
.chart-modal-content {
  background: #fff;
  border-radius: 12px;
  padding: 25px;
  max-width: 800px;
  width: 90%;
  position: relative;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.close-chart {
  position: absolute;
  right: 15px;
  top: 10px;
  font-size: 22px;
  color: #666;
  cursor: pointer;
}
.close-chart:hover {
  color: #e74c3c;
}
/* === Tombol modern & berwarna seimbang === */
button.btn,
input[type="button"].btn,
input[type="submit"].btn {
  border: none !important;
  border-radius: 8px !important;
  padding: 8px 14px !important;
  color: white !important;
  font-weight: 500;
  letter-spacing: 0.3px;
  transition: all 0.2s ease;
  box-shadow: 0 3px 6px rgba(0,0,0,0.1);
}

button.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 5px 10px rgba(0,0,0,0.15);
  opacity: 0.95;
}

/* Warna-warna */
.btn-green {
  background: linear-gradient(135deg, #28a745, #38ef7d) !important;
}

.btn-red {
  background: linear-gradient(135deg, #dc3545, #ff6b6b) !important;
}

.btn-blue {
  background: linear-gradient(135deg, #007bff, #33b5e5) !important;
}

.btn-primary {
  background: linear-gradient(135deg, #4a6cf7, #6ba8ff) !important;
}

.btn-info {
  background: linear-gradient(135deg, #17a2b8, #48c6ef) !important;
}

.btn-yellow {
  background: linear-gradient(135deg, #ffc107, #ffea3d) !important;
  color: #000 !important;
}


/* Hanya logout & stop yang merah */
button[name="logout"],
#stopSched {
  background: linear-gradient(135deg, #dc3545, #ff6b6b) !important;
}

/* Export Excel hijau */
form[action="uploadmikro.php"] button {
  background: linear-gradient(135deg, #28a745, #38ef7d) !important;
}

/* Tambah spacing antar tombol */
.toolbar, .table-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

/* === Info koneksi bergaya elegan === */
.connection-info {
  display: inline-flex;
  align-items: center;
  background: #e8f7ee;
  border: 1px solid #b6e3c6;
  color: #155724;
  font-size: 15px;
  padding: 8px 14px;
  border-radius: 10px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.05);
  gap: 6px;
  margin-bottom: 15px;
}

.connection-info .status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #28a745;
  box-shadow: 0 0 6px #28a745;
  animation: pulse 1.5s infinite;
}

.connection-info .port-info {
  color: #0d6efd;
  font-weight: 600;
}

@keyframes pulse {
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.6; }
  100% { transform: scale(1); opacity: 1; }
}
/* === Info koneksi dengan tombol kanan === */
.connection-info {
  display: flex;
  justify-content: space-between; /* ⬅️ pisahkan kiri & kanan */
  align-items: center;
  background: #e8f7ee;
  border: 1px solid #b6e3c6;
  color: #155724;
  font-size: 15px;
  padding: 10px 16px;
  border-radius: 12px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.05);
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}

.conn-left {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #28a745;
  box-shadow: 0 0 6px #28a745;
  animation: pulse 1.5s infinite;
}

.port-info {
  color: #0d6efd;
  font-weight: 600;
}

.toolbar-right button {
  border-radius: 8px;
  font-size: 14px;
  padding: 7px 14px;
}

@keyframes pulse {
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.6; }
  100% { transform: scale(1); opacity: 1; }
}
@media (max-width: 600px) {
  .action-group {
    display: flex;
    flex-direction: column;
    align-items: stretch; /* ubah dari center jadi stretch supaya elemen ikut 100% */
    width: 100%;
    gap: 10px; /* jarak antar tombol */
  }

 
}

</style>
<script>
document.addEventListener("DOMContentLoaded", () => {
  // ===== Global =====
  const table = document.querySelector("#mikrotikTable");
  const tbody = table.querySelector("tbody");
  const originalRows = Array.from(tbody.rows);
  const sortDirections = {};
  const rowsPerPage = 10;
  let currentPage = 1;

  // ===== Helper =====
  function parseSize(size) {
    if (!size) return 0;
    const units = { K: 1024, M: 1024**2, G: 1024**3, T: 1024**4 };
    const match = size.match(/([\d.]+)\s*([KMGT]?)/i);
    if (!match) return 0;
    const value = parseFloat(match[1]);
    const unit = match[2].toUpperCase();
    return units[unit] ? value * units[unit] : value;
  }

  function naturalCompare(a, b) {
    const re = /(\d+)|(\D+)/g;
    const aa = a.match(re) || [];
    const bb = b.match(re) || [];
    const len = Math.min(aa.length, bb.length);
    for (let i = 0; i < len; i++) {
      const x = aa[i], y = bb[i];
      if (!isNaN(x) && !isNaN(y)) {
        const diff = Number(x) - Number(y);
        if (diff !== 0) return diff;
      } else {
        const diff = x.localeCompare(y);
        if (diff !== 0) return diff;
      }
    }
    return aa.length - bb.length;
  }

  // ===== Sorting =====
  function sortTable(colIndex) {
    const headerText = table.querySelectorAll("th")[colIndex].innerText.trim();
    const isSizeCol = headerText.includes("Uploaded") || headerText.includes("Downloaded");
    sortDirections[colIndex] = !sortDirections[colIndex];

    const rows = Array.from(tbody.rows);
    rows.sort((a, b) => {
      let A = a.cells[colIndex].innerText.trim();
      let B = b.cells[colIndex].innerText.trim();
      if (isSizeCol) {
        A = parseSize(A);
        B = parseSize(B);
        return sortDirections[colIndex] ? A - B : B - A;
      } else {
        const cmp = naturalCompare(A, B);
        return sortDirections[colIndex] ? cmp : -cmp;
      }
    });
    rows.forEach(r => tbody.appendChild(r));
    paginateTable();
  }

  table.querySelectorAll("th").forEach((th, i) => {
    const text = th.innerText.trim();
    if (text === "Name" || text.includes("Uploaded") || text.includes("Downloaded")) {
      th.style.cursor = "pointer";
      th.addEventListener("click", () => sortTable(i));
    }
  });

/// ===== Search & Reset =====
document.getElementById("searchInput")?.addEventListener("keyup", e => {
  const filter = e.target.value.toLowerCase();

  // Menyaring baris pada tabel di desktop
  document.querySelectorAll("#mikrotikTable tbody tr").forEach(row => {
    row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
  });

  // Menyaring kartu pada tampilan mobile
  document.querySelectorAll(".card").forEach(card => {
    card.style.display = card.innerText.toLowerCase().includes(filter) ? "" : "none";
  });
});

// Function to reset sorting
// Function to reset sorting
function resetSorting() {
  const table = document.getElementById("mikrotikTable");
  const tbody = table.querySelector("tbody");
  const rows = Array.from(tbody.querySelectorAll("tr"));

  // Sort rows based on the numerical value of the first cell
  rows.sort((a, b) => {
    const aValue = parseInt(a.cells[0].innerText, 10);
    const bValue = parseInt(b.cells[0].innerText, 10);
    return aValue - bValue; // Sorts numerically
  });

  // Clear existing rows and append sorted rows
  tbody.innerHTML = "";
  rows.forEach(row => tbody.appendChild(row));
}

// Reset button event listener
document.getElementById("resetBtn")?.addEventListener("click", () => {
  document.getElementById("searchInput").value = "";

  // Reset tabel desktop
  document.querySelectorAll("#mikrotikTable tbody tr").forEach(row => {
    row.style.display = "";
  });

  // Reset kartu mobile
  document.querySelectorAll(".card").forEach(card => {
    card.style.display = "";
  });

  // Reset sorting
  resetSorting();

  // Reset pagination (assuming you have a function for pagination)
  currentPage = 1; // Reset to the first page
  paginateTable();  // Call your pagination function to reapply pagination
});

  // ===== Pagination =====
  function paginateTable() {
    const rows = tbody.querySelectorAll("tr");
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    rows.forEach((r, i) => {
      r.style.display = (i >= (currentPage - 1) * rowsPerPage && i < currentPage * rowsPerPage) ? "" : "none";
    });
    document.getElementById("pageInfo").textContent = `Halaman ${currentPage} / ${totalPages}`;
    document.getElementById("prevPage").disabled = currentPage === 1;
    document.getElementById("nextPage").disabled = currentPage === totalPages;
  }
  document.getElementById("prevPage")?.addEventListener("click", () => { if(currentPage>1){currentPage--;paginateTable();} });
  document.getElementById("nextPage")?.addEventListener("click", () => {
    const rows = tbody.querySelectorAll("tr");
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    if(currentPage<totalPages){currentPage++;paginateTable();}
  });
  paginateTable();

  // ===== Chart Modal =====
  const chartBtn = document.getElementById("showChartBtn");
  const modal = document.getElementById("chartModal");
  const closeBtn = document.querySelector(".close-chart");

  function parseBytes(str) { return parseSize(str); }

  function destroyChart() {
    if (window.top10ChartInstance) { window.top10ChartInstance.destroy(); window.top10ChartInstance = null; }
  }

  function createTop10Chart() {
    const rows = Array.from(tbody.querySelectorAll("tr"));
    if (!rows.length) return;
    const users = rows.map(r => ({
      name: r.cells[1].innerText.trim(),
      download: parseBytes(r.cells[8].innerText.trim())
    }));
    const top10 = users.sort((a,b)=>b.download-a.download).slice(0,10);
    const labels = top10.map(u=>u.name);
    const data = top10.map(u=>(u.download/(1024**2)).toFixed(2));
    const colors = ["#FF6384","#36A2EB","#FFCE56","#4BC0C0","#9966FF","#FF9F40","#2ECC71","#E74C3C","#F1C40F","#9B59B6"];
    const ctx = document.getElementById("top10Chart").getContext("2d");
    window.top10ChartInstance = new Chart(ctx,{
      type:"bar",
      data:{labels,datasets:[{label:"Total Download (MB)",data,backgroundColor:colors,borderColor:colors.map(c=>c+"bb"),borderWidth:2,borderRadius:6}]},
      options:{
        responsive:true,
        maintainAspectRatio:false,
        scales:{
          y:{beginAtZero:true,title:{display:true,text:"MB"},grid:{color:"rgba(200,200,200,0.3)"}},
          x:{ticks:{autoSkip:false,maxRotation:45,minRotation:25},grid:{display:false}}
        },
        plugins:{legend:{display:false},tooltip:{backgroundColor:"#333",titleColor:"#fff",bodyColor:"#fff",callbacks:{label:ctx=>`${ctx.parsed.y} MB`}}},
        animation:{duration:700,easing:"easeOutQuart"}
      }
    });
  }

  function openModalWithChart() {
    modal.style.display="block"; destroyChart();
    setTimeout(()=>requestAnimationFrame(()=>createTop10Chart()),150);
  }

  function closeModal() { modal.style.display="none"; destroyChart(); }

  chartBtn?.addEventListener("click", openModalWithChart);
  closeBtn?.addEventListener("click", closeModal);
  window.addEventListener("click", e => { if(e.target===modal) closeModal(); });

 
});
</script>

</body>
</html>
