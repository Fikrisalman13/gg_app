<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
// Mulai session untuk membaca informasi pengguna & tema
session_start();

// ===================================================
// 2. KONEKSI DATABASE
// ===================================================
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
require_once __DIR__ . '/theme_helper.php';

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: /gg_app/login.php');
  exit;
}

// ===================================================
// 4. HAK AKSES MENU ASSET
// ===================================================
if (!function_exists('checkPermissions')) {
  /**
   * Ambil hak akses user untuk menu tertentu.
   */
  function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $permissions = $row;
    }
    if ($stmt !== false) { sqlsrv_free_stmt($stmt); }

    return $permissions;
  }
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 148);
if (($permissions['CanView'] ?? 0) != 1) {
  $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
  header('Location: ../dashboard.php');
  exit;
}

// ===================================================
// 5. PENGATURAN TEMA & LAYOUT
// ===================================================
$includeTicketThemeCss = true;
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$GLOBALS['ticketThemeOverride'] = $themeColor;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- ===================================================
  6. STYLE & PLUGIN IMPORTS
======================================================= -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<style>
.ticket-notif-toggle {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-weight: 600;
}
.ticket-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  margin-bottom: 0;
}
.ticket-switch-input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.ticket-switch-slider {
  width: 78px;
  height: 30px;
  background: #9f9f9f;
  border-radius: 999px;
  position: relative;
  cursor: pointer;
  transition: background 0.2s ease;
  font-size: 0.78rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-align: center;
  line-height: 30px;
}
.ticket-switch-slider::before {
  content: attr(data-off);
  position: absolute;
  top: 50%;
  right: 10px;
  transform: translateY(-50%);
  z-index: 1;
  pointer-events: none;
}
.ticket-switch-slider::after {
  content: '';
  position: absolute;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #fff;
  top: 50%;
  left: 2px;
  transform: translateY(-50%);
  box-shadow: 0 1px 4px rgba(0,0,0,0.25);
  transition: left 0.2s ease;
  z-index: 2;
}
.ticket-switch-input:checked + .ticket-switch-slider {
  background: linear-gradient(90deg, #28a745, #007bff);
}
.ticket-switch-input:checked + .ticket-switch-slider::before {
  content: attr(data-on);
  right: auto;
  left: 10px;
}
.ticket-switch-input:checked + .ticket-switch-slider::after {
  left: 50px;
}
.ticket-switch-input:disabled + .ticket-switch-slider {
  opacity: 0.5;
  cursor: not-allowed;
}
/* Fix Header Icon Clickability */
.main-header { z-index: 1100 !important; position: relative; }
/* Fix Modal z-index to prevent header overlap */
.modal { z-index: 9999 !important; }
.modal-backdrop { z-index: 9998 !important; }
/* Summernote Modal Fix */
.note-modal-backdrop { z-index: 9997 !important; }
.note-modal { z-index: 9998 !important; }
.action-button-group { display: inline-flex; flex-wrap: nowrap; align-items: center; }
.action-button-group .action-btn { margin-right: 6px; }
.action-button-group .action-btn:last-child { margin-right: 0; }
.ticket-subject-cell {
  display: inline-block;
  max-width: 260px;
  white-space: normal;
  word-break: break-word;
  line-height: 1.25;
}
/* Refine Image Size in First Message & Editor */
#editFirstMessage img,
.note-editable img {
    max-width: 200px;
    height: auto;
    border-radius: 6px;
    display: block;
    margin-top: 0.35rem;
    background: rgba(0,0,0,0.03);
    box-shadow: 0 0 0 2px rgba(0,0,0,0.04);
}
#editFirstMessage img { cursor: zoom-in; }
/* Attachment Link Style */
.msg-attachment-link { 
    display: inline-flex; align-items: center; gap: 0.3rem; 
    padding: 0.15rem 0.5rem; 
    border: 1px solid rgba(0,0,0,0.1); 
    border-radius: 999px; 
    font-size: 0.85rem; 
    text-decoration: none; 
    background: #fff;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}
.msg-attachment-link:hover { background: #f8f9fa; }
/* DataTables Responsive Mobile Styling */
.ticket-table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
.ticket-table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
  background-color: #007bff;
  border: none;
  box-shadow: none;
  line-height: 1;
  top: 50%;
  transform: translateY(-50%);
}
.ticket-table.dataTable > tbody > tr.child ul.dtr-details {
  display: block;
  width: 100%;
  padding: 0;
}
.ticket-table.dataTable > tbody > tr.child ul.dtr-details > li {
  border-bottom: 1px solid #efefef;
  padding: 8px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  column-gap: 0.75rem;
}
.ticket-table.dataTable > tbody > tr.child ul.dtr-details > li:last-child {
  border-bottom: none;
}
.ticket-table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-title {
  font-weight: 600;
  color: #555;
  min-width: 120px;
}
.ticket-table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-data {
  text-align: right;
  flex: 1;
  word-break: break-word;
}
@media (max-width: 576px) {
  .ticket-table.dataTable > tbody > tr.child ul.dtr-details > li {
    flex-direction: column;
    align-items: flex-start;
  }
  .ticket-table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-data {
    width: 100%;
    text-align: left;
    margin-top: 4px;
  }
}
</style>

<!-- ===================================================
  7. KONTEN HALAMAN
======================================================= -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Tickets</h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li><li class="breadcrumb-item active">Tickets</li></ol></div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
          <h3 class="card-title" ><i class="fas fa-list mr-1"></i>Daftar Ticket Aktif</h3>
          <div class="card-tools">
            <div class="d-inline-block">
              <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
              <button class="btn btn-success btn-sm float-right" id="btnAddTicket"><i class="fas fa-plus"></i> Tambah Ticket</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-12 d-flex justify-content-end align-items-center">
              <div class="ticket-notif-toggle">
                <span id="ticketDesktopNotifLabel">Desktop Notif</span>
                <label class="ticket-switch">
                  <input type="checkbox" id="ticketDesktopNotifToggle" class="ticket-switch-input">
                  <span class="ticket-switch-slider" data-on="ON" data-off="Off"></span>
                </label>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table id="ticketTable" class="table table-hover table-sm nowrap ticket-table" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th>No</th>
                  <th>Ticket No</th>
                  <th>Pemohon</th>
                  <th>Departement</th>
                  <th>Subject</th>
                  <th>Priority</th>
                  <th>Assign</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>

    <!-- ===================================================
      8. MODAL EDIT TICKET
    ======================================================= -->
  <div class="modal fade" id="modalEditTicket" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
          <h5 class="modal-title">Edit Ticket</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="formEditTicket">
            <input type="hidden" id="editTicketId" name="ticket_id">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editSubject">Subject</label>
                  <input type="text" class="form-control" id="editSubject" name="subject" required>
                </div>
                <div class="form-group">
                  <label for="editAsset">Asset</label>
                  <select class="form-control" id="editAsset" name="asset_id">
                    <option value="0">-- Pilih Asset --</option>
                  </select>
                  <small class="text-muted">Daftar asset milik pemohon.</small>
                </div>
                <div class="form-group">
                  <label for="editAssign">Assign To</label>
                  <select class="form-control" id="editAssign" name="assigned_to">
                    <option value="0">-- Pilih Teknisi --</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Pesan Pertama (hanya baca)</label>
                  <div id="editFirstMessage" class="border p-2 rounded bg-light" style="height: 200px; overflow-y: auto; font-size: 0.9rem;"></div>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
            <small class="text-muted mr-auto">Perubahan Melalui Quick Edit</small>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-primary" id="btnSaveEditTicket"><i class="fas fa-save"></i> Simpan</button>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
  window.ticketListPageHandlesNotifications = true;
  window.ticketPermissions = {
    canAdd: <?php echo (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1) ? 'true' : 'false'; ?>,
    canEdit: <?php echo (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1) ? 'true' : 'false'; ?>,
    canDelete: <?php echo (!empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1) ? 'true' : 'false'; ?>
  };
</script>
<!-- ===================================================
  9. FOOTER & SCRIPT IMPORTS
======================================================= -->
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<!-- Plugin Scripts -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<!-- ===================================================
  10. JAVASCRIPT: INTERAKSI TICKET
======================================================= -->
<script>
$(function(){
  var ticketPermissions = window.ticketPermissions || { canAdd:false, canEdit:false, canDelete:false };
  var ticketUserId = <?php echo intval($_SESSION['UserId'] ?? 0); ?>;
  var latestTicketId = 0;
  var latestTicketUpdate = '';
  var ticketPollTimer = null;
  var desktopNotifToggle = $('#ticketDesktopNotifToggle');
  var desktopNotifLabel = $('#ticketDesktopNotifLabel');
  var notifSupport = ('Notification' in window);
  var notifStorageKey = 'ticket_desktop_notif_' + ticketUserId;
  var desktopNotifEnabled = notifSupport && localStorage.getItem(notifStorageKey) === '1';
  var notificationIconSource = '/gg_app/dist/img/sumlogo.png';
  var ticketStateKey = 'ticket_notif_state_' + ticketUserId;

  function escapeHtml(str) {
    return (str == null ? '' : String(str)).replace(/[&<>"']/g, function(ch) {
      switch (ch) {
        case '&': return '&amp;';
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '"': return '&quot;';
        case "'": return '&#39;';
        default: return ch;
      }
    });
  }

  hydrateTicketState();

  // Menstandarkan path aset agar dapat dipakai untuk ikon notifikasi
  function resolveAssetPath(path){
    if(!path){ return ''; }
    if(/^https?:\/\//i.test(path)){ return path; }
    if(path.charAt(0) !== '/'){ path = '/' + path; }
    var origin = window.location.origin || '';
    return origin ? origin + path : path;
  }

  var notificationIconResolved = resolveAssetPath(notificationIconSource);
  var notificationIconCached = '';
  var notificationIconPromise = null;

  // Mengambil ikon notifikasi sekali lalu cache di memori/browser
  function getNotificationIconUrl(){
    if(!notificationIconResolved){ return Promise.resolve(''); }
    if(notificationIconCached){ return Promise.resolve(notificationIconCached); }
    if(!window.fetch){
      notificationIconCached = notificationIconResolved;
      return Promise.resolve(notificationIconCached);
    }
    if(!notificationIconPromise){
      notificationIconPromise = fetch(notificationIconResolved)
        .then(function(resp){
          if(!resp.ok){ throw new Error('icon load failed'); }
          return resp.blob();
        })
        .then(function(blob){
          notificationIconCached = URL.createObjectURL(blob);
          return notificationIconCached;
        })
        .catch(function(){
          notificationIconCached = notificationIconResolved;
          return notificationIconCached;
        });
    }
    return notificationIconPromise;
  }

  // Membuka pratinjau gambar dari modal edit ticket
  function openEditImagePreview(src, altText){
    if(!src){ return; }
    var $modal = $('#ticketChatImageModal');
    var $img = $('#ticketChatImageModalImg');
    if(!$modal.length || !$img.length){
      window.open(src, '_blank');
      return;
    }
    $img.attr('src', src);
    if(altText){ $img.attr('alt', altText); }
    try {
      if(window.bootstrap && window.bootstrap.Modal){
        var ModalClass = window.bootstrap.Modal;
        var instance = (typeof ModalClass.getInstance === 'function') ? ModalClass.getInstance($modal[0]) : null;
        if(!instance){ instance = new ModalClass($modal[0]); }
        instance.show();
      } else if(typeof $modal.modal === 'function') {
        $modal.modal('show');
      } else {
        window.open(src, '_blank');
      }
    } catch(err) {
      if(typeof $modal.modal === 'function') {
        $modal.modal('show');
      } else {
        window.open(src, '_blank');
      }
    }
  }

  // Menambahkan event preview gambar pada pesan pertama
  function bindEditMessageImagePreview(){
    var $container = $('#editFirstMessage');
    if(!$container.length){ return; }
    $container.find('img').each(function(){
      var $img = $(this);
      $img.attr('loading', 'lazy');
      $img.css('cursor', 'zoom-in');
    });
    $container.off('click', '.js-previewable-img').on('click', '.js-previewable-img', function(e){
      var $link = $(this);
      var $img = $link.find('img').first();
      var src = $link.data('imgPreview') || ($img.length ? $img.attr('src') : '');
      if(!src){ return; }
      e.preventDefault();
      e.stopPropagation();
      openEditImagePreview(src, $img.length ? $img.attr('alt') : '');
    });
    $container.off('click', 'img').on('click', 'img', function(e){
      var $img = $(this);
      var src = $img.attr('src');
      if(!src){ return; }
      e.preventDefault();
      e.stopPropagation();
      openEditImagePreview(src, $img.attr('alt') || '');
    });
  }

  // Mengisi ulang informasi ticket terakhir dari localStorage
  function hydrateTicketState(){
    if(!window.localStorage){ return; }
    try {
      var stored = localStorage.getItem(ticketStateKey);
      if(!stored){ return; }
      var parsed = JSON.parse(stored);
      updateTicketState(parsed.latest_ticket_id, parsed.latest_ticket_update, true);
    } catch(err) {}
  }

  // Menyimpan snapshot ticket ke localStorage agar sinkron antartab
  function persistTicketState(){
    if(!window.localStorage){ return; }
    try {
      localStorage.setItem(ticketStateKey, JSON.stringify({
        latest_ticket_id: latestTicketId || 0,
        latest_ticket_update: latestTicketUpdate || ''
      }));
    } catch(err) {}
  }

  // Memperbarui catatan ticket terbaru bila ada nilai yang lebih baru
  function updateTicketState(newId, newUpdate, forceAssign){
    var changed = false;
    if(typeof newId !== 'undefined'){
      var numericId = parseInt(newId, 10);
      if(!isNaN(numericId) && (forceAssign || numericId > latestTicketId)){
        latestTicketId = numericId;
        changed = true;
      }
    }
    if(typeof newUpdate === 'string' && newUpdate && (forceAssign || newUpdate !== latestTicketUpdate)){
      latestTicketUpdate = newUpdate;
      changed = true;
    }
    if(changed){
      persistTicketState();
    }
    return changed;
  }

  // Menyusun isi teks untuk notifikasi desktop ticket
  function buildTicketNotificationBody(ticketInfo){
    var lines = [];
    if(ticketInfo && ticketInfo.subject){ lines.push(ticketInfo.subject); }
    if(ticketInfo && ticketInfo.creator_name){
      var applicantLine = 'Pemohon: ' + ticketInfo.creator_name;
      if(ticketInfo.latest_message){ applicantLine += ' · ' + ticketInfo.latest_message; }
      lines.push(applicantLine);
    }
    if(ticketInfo && ticketInfo.creator_dept){ lines.push('Dept: ' + ticketInfo.creator_dept); }
    return lines.join('\n');
  }

  if(!notifSupport){
    desktopNotifToggle.prop('disabled', true);
    desktopNotifLabel.text('Desktop notif tidak didukung');
    desktopNotifEnabled = false;
  } else if(Notification.permission !== 'granted' && desktopNotifEnabled){
    desktopNotifEnabled = false;
    localStorage.setItem(notifStorageKey, '0');
  }
  desktopNotifToggle.prop('checked', desktopNotifEnabled);

  // Menyimpan preferensi notifikasi desktop pengguna
  function setDesktopNotifState(state){
    desktopNotifEnabled = state;
    localStorage.setItem(notifStorageKey, state ? '1' : '0');
  }

  desktopNotifToggle.on('change', function(){
    if(!notifSupport){ this.checked = false; return; }
    if(this.checked){
      if(Notification.permission === 'granted'){
        setDesktopNotifState(true);
        return;
      }
      if(Notification.permission === 'denied'){
        Swal.fire('Notifikasi diblokir', 'Izinkan notifikasi Untuk Situs Ini Melalui Pengaturan Browser Atau Hubungi Admin.', 'warning');
        this.checked = false;
        setDesktopNotifState(false);
        return;
      }
      Notification.requestPermission().then(function(permission){
        if(permission === 'granted'){
          setDesktopNotifState(true);
        } else {
          desktopNotifToggle.prop('checked', false);
          setDesktopNotifState(false);
        }
      }).catch(function(){
        desktopNotifToggle.prop('checked', false);
        setDesktopNotifState(false);
      });
    } else {
      setDesktopNotifState(false);
    }
  });

  // Inisialisasi DataTable untuk daftar ticket aktif
  var table = $('#ticketTable').DataTable({
    processing:true,
    serverSide:true,
    order:[[1,'desc']],
    responsive:{
      details:{
        type:'column',
        target:0
      }
    },
    columnDefs:[
      { className:'dtr-control', orderable:false, targets:0 },
      { responsivePriority:1, targets:1 },
      { responsivePriority:2, targets:4 },
      { responsivePriority:3, targets:7 },
      { responsivePriority:4, targets:-1 },
      { className:'desktop', targets:6 }
    ],
    ajax:{ 
      url:'ticket_serverside.php', 
      type:'POST',
      data: function(d) {
        d.view_mode = 'active';
      }
    },
    columns:[
      { data:null, orderable:false, searchable:false, render:function(d,t,r,m){ return m.row+1+m.settings._iDisplayStart; }},
      { data:'ticket_no' },
      { data:'creator_name' },
      { data:'creator_dept' },
      { data:'subject', render:function(d){
          var safe = escapeHtml(d);
          return '<span class="ticket-subject-cell" title="' + safe + '">' + safe + '</span>';
        }
      },
      { data:'priority_label', render:function(d){ return d; } },
      { data:'assigned_name', render:function(d){ return d; } },
      { data:'status_label', render:function(d){ return d; } },
      { data:'aksi', orderable:false, searchable:false, render:function(d){ return d; } }
    ],
    language:{
      processing:"Sedang memproses...",
      lengthMenu:"Tampilkan _MENU_ data per halaman",
      zeroRecords:"Tidak ada ticket ditemukan",
      info:"Menampilkan _START_ - _END_ dari _TOTAL_ ticket",
      infoEmpty:"Tidak ada ticket tersedia",
      infoFiltered:"(disaring dari _MAX_ total ticket)",
      search:"Cari:",
      paginate:{
        first:"Pertama",
        last:"Terakhir",
        next:"Selanjutnya",
        previous:"Sebelumnya"
      }
    }
  });
  
  // Expose to global scope for chat bubble to trigger reload
  window.ticketDataTable = table;

  $('#ticketTable').on('xhr.dt', function(e, settings, json){
    var snapshotId = json ? json.latest_ticket_id : undefined;
    var snapshotUpdate = json ? json.latest_ticket_update : undefined;
    if(typeof snapshotId !== 'undefined' || snapshotUpdate){
      updateTicketState(snapshotId, snapshotUpdate);
    }
    if(!ticketPollTimer){
      checkNewTickets();
      ticketPollTimer = setInterval(checkNewTickets, 5000);
    }
  });

  // Melakukan polling data baru dari server tanpa mengganggu DataTable
  function checkNewTickets(){
    if(checkNewTickets.isPolling) return;
    checkNewTickets.isPolling = true;
    
    $.ajax({
      url: 'check_new_tickets.php',
      method: 'POST',
      dataType: 'json',
      global: false, // Suppress global ajax events if any
      data: { last_ticket_id: latestTicketId, last_ticket_update: latestTicketUpdate }
    }).done(function(resp){
      checkNewTickets.isPolling = false;
      if(!resp || !resp.success) return;
      var latest = parseInt(resp.latest_ticket_id || 0, 10);
      if(isNaN(latest)) return;
      var previousLatestId = latestTicketId;
      if(resp.latest_ticket_update || !isNaN(latest)){
        updateTicketState(latest, resp.latest_ticket_update || undefined);
      }
      if(previousLatestId === 0 && !isNaN(latest) && latest > 0){
        table.ajax.reload(null, false);
        return;
      }
      if(resp.has_new && !isNaN(latest) && latest > previousLatestId){
        table.ajax.reload(null, false);
        maybeShowDesktopNotification(resp.latest_ticket || null);
        return;
      }
      if(resp.has_update){
        table.ajax.reload(null, false);
      }
    }).fail(function(){
        checkNewTickets.isPolling = false;
    });
  }

  // Menampilkan notifikasi desktop hanya bila fitur aktif & tidak duplikat
  function maybeShowDesktopNotification(ticketInfo){
    if(!notifSupport || !desktopNotifEnabled) return;
    if(Notification.permission !== 'granted') return;

    // Prevent duplicate notifications (e.g. from multiple tabs)
    // Key includes ticket number to be specific
    var notifKey = 'ticket_notif_shown_' + (ticketInfo.ticket_no || 'unknown');
    var lastTime = localStorage.getItem(notifKey);
    var now = Date.now();
    
    // If shown less than 60 seconds ago, ignore
    if(lastTime && (now - parseInt(lastTime, 10) < 60000)){
      return;
    }
    localStorage.setItem(notifKey, now);

    var title = 'Ticket baru';
    if(ticketInfo && ticketInfo.ticket_no){
      title = 'Ticket ' + ticketInfo.ticket_no;
    }
    var body = buildTicketNotificationBody(ticketInfo) || 'Ticket baru berhasil dibuat.';
    getNotificationIconUrl().then(function(iconUrl){
      try {
        var notification = new Notification(title, {
          body: body,
          icon: iconUrl || undefined,
          badge: iconUrl || undefined,
          tag: ticketInfo && ticketInfo.ticket_no ? 'ticket-'+ticketInfo.ticket_no : undefined,
          renotify: true
        });
        notification.onclick = function(){ window.focus(); notification.close(); };
      } catch(err) {
        console.error('Desktop notification error', err);
      }
    });
  }

  $('#btnAddTicket').on('click', function(){
    window.location.href = 'create.php';
  });

  // Delete handler
  $('#ticketTable').on('click', '.js-del', function(e){
    e.preventDefault();
    if (!ticketPermissions.canDelete) {
      Swal.fire('Akses ditolak', 'Anda tidak memiliki izin menghapus ticket.', 'warning');
      return;
    }
    var ticketNo = $(this).data('ticket');
    var ticketId = $(this).data('id');
    if (!ticketNo && !ticketId) {
      Swal.fire('Gagal', 'Ticket tidak valid', 'error');
      return;
    }
    Swal.fire({
      title: 'Hapus ticket?',
      text: 'Tindakan ini tidak bisa dibatalkan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal'
    }).then(function(res){
      if(res.isConfirmed){
        $.ajax({
            url:'delete_ticket.php',
            method:'POST',
            dataType:'json',
            data:{ ticket_no: ticketNo || '', ticket_id: ticketId || '' }
          })
          .done(function(r){
            if(r && r.ok){
              Swal.fire('Terhapus','Ticket berhasil dihapus','success');
              table.ajax.reload(null,false);
            } else {
              Swal.fire('Gagal', (r && r.error)?r.error:'Gagal menghapus','error');
            }
          })
          .fail(function(){ Swal.fire('Gagal','Koneksi bermasalah','error'); });
      }
    });
  });
  
  // Listen for ticket status changes from chat bubble
  // Sinkronisasi perubahan status melalui localStorage antar tab
  window.addEventListener('storage', function(e) {
    if (e.key === 'ticket_status_changed') {
      table.ajax.reload(null, false);
      return;
    }
    if (e.key === ticketStateKey && e.newValue) {
      try {
        var snapshot = JSON.parse(e.newValue);
        updateTicketState(snapshot.latest_ticket_id, snapshot.latest_ticket_update || undefined);
      } catch(err) {}
    }
  });
  // Edit Ticket Handler
  $('#ticketTable').on('click', '.js-edit-ticket', function(e){
    e.preventDefault();
    if (!ticketPermissions.canEdit) {
      Swal.fire('Akses ditolak', 'Anda tidak memiliki izin mengedit ticket.', 'warning');
      return;
    }
    var ticketId = $(this).data('id');
    
    // Show loading or just open modal with loader
    Swal.fire({title: 'Memuat data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
    
    $.ajax({
      url: 'get_ticket_edit_data.php',
      method: 'POST',
      data: { ticket_id: ticketId },
      dataType: 'json'
    }).done(function(r){
      Swal.close();
      if(r && r.success && r.data) {
        var t = r.data.ticket;
        
        $('#editTicketId').val(t.ticket_id);
        $('#editSubject').val(t.subject);
        var contentHtml = t.first_message || '<em class="text-muted">Tidak ada pesan</em>';
        
        // Render Attachments if any
        if (t.attachments && t.attachments.length > 0) {
            contentHtml += '<div class="mt-3 border-top pt-2">';
            t.attachments.forEach(function(att){
                var filePath = att.file_path || '';
                var fileName = filePath.split('/').pop() || 'file';
                var cleanPath = (filePath.indexOf('/gg_app/') === 0) ? filePath : '/gg_app/' + filePath.replace(/^\//, '');
                
                var ext = fileName.split('.').pop().toLowerCase();
                var isImg = ['jpg','jpeg','png','gif','bmp','webp'].includes(ext);
                
                if (isImg) {
                  contentHtml += '<a href="'+cleanPath+'" target="_blank" class="d-inline-block mr-2 mb-2 js-previewable-img" data-img-preview="'+cleanPath+'"><img src="'+cleanPath+'" style="max-width:150px;max-height:100px;border-radius:6px;border:1px solid #ddd;"></a>';
                } else {
                    contentHtml += '<a href="'+cleanPath+'" target="_blank" class="msg-attachment-link"><i class="fas fa-paperclip"></i> '+fileName+'</a>';
                }
            });
            contentHtml += '</div>';
        }

        $('#editFirstMessage').html(contentHtml);
        bindEditMessageImagePreview();
        
        // Populate Assets
        var $assetSelect = $('#editAsset');
        $assetSelect.empty().append('<option value="0">-- Pilih Asset --</option>');
        if(r.data.assets && r.data.assets.length > 0){
          r.data.assets.forEach(function(a){
            var sel = (t.current_asset_id == a.id_asset) ? 'selected' : '';
            var kategoriLabel = a.nama_kategori || '';
            if (kategoriLabel === 'Peripheral' && a.keterangan) {
              kategoriLabel = a.keterangan;
            }
            var label = a.kode_asset_seq + ' - ' + kategoriLabel;
            $assetSelect.append('<option value="'+a.id_asset+'" '+sel+'>'+label+'</option>');
          });
        }
        
        // Populate Technicians
        var $techSelect = $('#editAssign');
        $techSelect.empty().append('<option value="0">-- Pilih Teknisi --</option>');
        if(r.data.technicians && r.data.technicians.length > 0){
          r.data.technicians.forEach(function(tc){
            var sel = (t.assigned_to == tc.id_emp) ? 'selected' : '';
            var label = tc.nama_lengkap;
            if(tc.group_name) label += ' (' + tc.group_name + ')';
            $techSelect.append('<option value="'+tc.id_emp+'" '+sel+'>'+label+'</option>');
          });
        }
        
        $('#modalEditTicket').modal('show');
        
      } else {
        Swal.fire('Error', r.message || 'Gagal memuat data ticket', 'error');
      }
    }).fail(function(){
      Swal.close();
      Swal.fire('Error', 'Koneksi gagal', 'error');
    });
  });

  // Save Edit Ticket
  $('#btnSaveEditTicket').on('click', function(){
    var form = $('#formEditTicket');
    var btn = $(this);
    var oldHtml = btn.html();
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Simpan...');
    
    $.ajax({
      url: 'save_ticket_edit.php',
      method: 'POST',
      data: form.serialize(),
      dataType: 'json'
    }).done(function(r){
      if(r && r.success){
        $('#modalEditTicket').modal('hide');
        Swal.fire('Sukses', 'Ticket berhasil diperbarui', 'success');
        table.ajax.reload(null, false);
      } else {
        Swal.fire('Gagal', r.message || 'Gagal menyimpan perubahan', 'error');
      }
    }).fail(function(){
      Swal.fire('Error', 'Terjadi kesalahan koneksi', 'error');
    }).always(function(){
      btn.prop('disabled', false).html(oldHtml);
    });
  });

});
</script>
