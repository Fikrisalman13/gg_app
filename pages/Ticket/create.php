<?php
// ===================================================
// 1. INISIALISASI HALAMAN CREATE TICKET
// ===================================================
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($_SESSION['UserId'])) { header('Location: /gg_app/login.php'); exit; }

// ===================================================
// 2. LAYOUT DAN KONFIGURASI TEMA
// ===================================================
$includeTicketThemeCss = true;
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$noteModalPalette = [
  'primary' => ['base' => '#0d6efd', 'text' => '#fff'],
  'secondary' => ['base' => '#6c757d', 'text' => '#fff'],
  'success' => ['base' => '#198754', 'text' => '#fff'],
  'danger' => ['base' => '#dc3545', 'text' => '#fff'],
  'warning' => ['base' => '#ffc107', 'text' => '#212529'],
  'info' => ['base' => '#0dcaf0', 'text' => '#0b2f44'],
  'light' => ['base' => '#f8f9fa', 'text' => '#212529'],
  'dark' => ['base' => '#212529', 'text' => '#fff'],
  'indigo' => ['base' => '#6610f2', 'text' => '#fff'],
  'navy' => ['base' => '#001f3f', 'text' => '#fff'],
  'purple' => ['base' => '#6f42c1', 'text' => '#fff'],
  'pink' => ['base' => '#e83e8c', 'text' => '#fff'],
  'teal' => ['base' => '#20c997', 'text' => '#fff'],
  'orange' => ['base' => '#fd7e14', 'text' => '#212529'],
  'olive' => ['base' => '#3d9970', 'text' => '#fff'],
  'lime' => ['base' => '#01ff70', 'text' => '#0b2e13'],
  'fuchsia' => ['base' => '#f012be', 'text' => '#fff'],
  'maroon' => ['base' => '#85144b', 'text' => '#fff']
];
$noteModalTheme = $noteModalPalette[$themeColor] ?? $noteModalPalette['primary'];
$noteModalHeaderHex = $noteModalTheme['base'];
$noteModalHeaderText = $noteModalTheme['text'];
$GLOBALS['ticketThemeOverride'] = $themeColor;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']);
$jabatan = $departemen = $bagian = '';
try {
  $sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
            FROM dbo.m_emp
            LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
            LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
            WHERE m_emp.nama_lengkap = ?";
  $params = array($username);
  $st = sqlsrv_query($conn, $sql, $params);
  if ($st !== false) {
    $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
    if ($row) {
        $jabatan = $row['jabatan'] ?? '';
        $departemen = $row['dept'] ?? '';
        $bagian = $row['bagian'] ?? '';
    }
    sqlsrv_free_stmt($st);
  }
} catch (Exception $e) {}
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/summernote-lite.min.css">

<style>
  .main-header { z-index: 1040 !important; position: relative; } /* Reduce header z-index slightly */
  
  /* Summernote Image Sizing - Strict Override */
  .note-editor .note-editable img {
      max-width: 200px !important;
      width: auto !important;
      height: auto !important;
      border-radius: 6px;
      display: block;
      margin-top: 0.35rem;
      background: rgba(0,0,0,0.03);
      box-shadow: 0 0 0 2px rgba(0,0,0,0.04);
      cursor: pointer;
      transition: box-shadow 0.2s ease, transform 0.2s ease;
  }
  .note-editor .note-editable img:hover {
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      transform: translateY(-1px);
  }

  /* Summernote Modal Fix - Force on top and push down */
  .note-modal-backdrop { 
      z-index: 10000 !important; 
  }
  .note-modal { 
      z-index: 10001 !important; 
      top: 50px !important; /* Push down to avoid header coverage */
  }
    .note-modal-content {
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 20px 45px rgba(15,28,45,0.25);
      border: none;
    }
    .note-modal-header {
      background: <?php echo $noteModalHeaderHex; ?>;
      color: <?php echo $noteModalHeaderText; ?>;
      border: none;
      padding: 0.85rem 1.25rem;
    }
    .note-modal-header .note-modal-title { color: inherit; font-weight: 600; }
    .note-modal-header .close,
    .note-modal-header .note-icon-close {
      color: <?php echo $noteModalHeaderText; ?>;
      opacity: 0.75;
    }
    .note-modal-header .close:hover,
    .note-modal-header .note-icon-close:hover { opacity: 1; }
</style>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Ticket Baru</h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/pages/ticket/list.php">Tickets</a></li><li class="breadcrumb-item active">Create</li></ol></div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">Form Ticket</div>
        <div class="card-body">
          <form id="ticketForm" class="mb-0">
            <div class="row">
              <div class="col-md-6">
                <div class="form-row">
                  <div class="col-6">
                    <label class="form-label mb-1">Nama Pemohon</label>
                    <input type="text" class="form-control form-control-sm" value="<?php echo $username;?>" readonly>
                  </div>
                  <div class="col-6">
                    <label class="form-label mb-1">Jabatan</label>
                    <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($jabatan);?>" readonly>
                  </div>
                </div>
                <div class="form-row mt-1">
                  <div class="col-4">
                    <label class="form-label mb-1">Tanggal Pengajuan</label>
                    <input type="text" class="form-control form-control-sm" value="<?php echo date('d-m-Y');?>" readonly>
                  </div>
                  <div class="col-4">
                    <label class="form-label mb-1">Departemen</label>
                    <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($departemen);?>" readonly>
                  </div>
                  <div class="col-4">
                    <label class="form-label mb-1">Bagian</label>
                    <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($bagian);?>" readonly>
                  </div>
                </div>
                <div class="mt-2">
                  <label class="form-label mb-1">Priority</label>
                  <select name="priority" class="form-control form-control-sm" required>
                    <option value="1">Low</option>
                    <option value="2" selected>Normal</option>
                    <option value="3">High</option>
                  </select>
                </div>
                <div class="mt-2" id="assetField" style="display:none;">
                  <label class="form-label mb-1">Asset <small class="text-muted">Opsional</small></label>
                  <select name="asset_id" class="form-control form-control-sm" id="assetSelect"></select>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label mb-1">Subject</label>
                <input type="text" name="subject" class="form-control form-control-sm mb-2" placeholder="Jelaskan masalah atau kebutuhan secara singkat" required>
                <label class="form-label mb-1">Message</label>
                <textarea id="messageEditor" name="message_html"></textarea>
                <div class="mt-2">
                  <label for="createAttachments" class="btn btn-sm btn-outline-secondary mb-0">
                    <i class="fas fa-paperclip"></i> Attach Files
                  </label>
                  <input type="file" id="createAttachments" name="attachments[]" multiple style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip">
                  <small id="createFilePreview" class="text-muted ml-2"></small>
                </div>
              </div>
            </div>
            <div class="text-right mt-3">
              <button type="button" id="btnSaveTicket" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script src="/gg_app/plugins/js/summernote-lite.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<!-- ===================================================
  3. JAVASCRIPT FORM TICKET BARU
======================================================= -->
<script>
$(function(){
  // Membersihkan elemen kosong yang sering ditinggalkan Summernote
  function tidySummernoteHtml(html) {
    if (!html) { return ''; }
    html = html.replace(/(<p>(?:&nbsp;|\s|<br\s*\/?>)*<\/p>\s*)+$/gi, '');
    html = html.replace(/(?:<br\s*\/?>\s*)+$/gi, '');
    return html.trim();
  }

  $('#messageEditor').summernote({ placeholder:'Jelaskan kronologi atau detail lengkapnya', tabsize:2, height:220 });
  var $saveBtn = $('#btnSaveTicket');
  var saveBtnHtml = $saveBtn.html();
  var saveTicketBusy = false;
  function isEditorEmpty(html) {
    if (!html) { return true; }
    var text = $('<div>').html(html).text().replace(/\s+/g, ' ').trim();
    var cleaned = tidySummernoteHtml(html).replace(/<[^>]+>/g, '').replace(/&nbsp;/gi, '').trim();
    return text.length === 0 && cleaned.length === 0;
  }

  $('#createAttachments').on('change', function(){
    var names = [].map.call(this.files, function(f){ return f.name; });
    $('#createFilePreview').text(names.length ? names.length + ' file(s): ' + names.join(', ') : '');
  });

  // Load assets for current user; show field only if any assets
  $.get('get_assets_for_user.php').done(function(resp){
    var $sel = $('#assetSelect');
    $sel.empty().append('<option value="">-</option>');
    if (resp && Array.isArray(resp.data) && resp.data.length > 0) {
      resp.data.forEach(function(a){
        var kategoriLabel = a.nama_kategori || '-';
        if (kategoriLabel === 'Peripheral' && a.keterangan) {
          kategoriLabel = a.keterangan;
        }
        $sel.append('<option value="'+a.id_asset+'">'+a.kode_asset_seq+' — '+kategoriLabel+' ('+(a.nama_status||'-')+')</option>');
      });
      $('#assetField').show();
    } else {
      $('#assetField').hide();
      $sel.removeAttr('required');
    }
  });

  $saveBtn.on('click', function(){
    if (saveTicketBusy) { return; }
    saveTicketBusy = true;
    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Memproses...');
    var formData = new FormData();
    formData.append('subject', $('input[name=subject]').val());
    formData.append('priority', $('select[name=priority]').val());
    formData.append('asset_id', $('#assetField:visible').length ? $('select[name=asset_id]').val() : '');
    var rawCreateHtml = $('#messageEditor').summernote('code');
    if (isEditorEmpty(rawCreateHtml)) {
      Swal.fire({ icon:'warning', title:'Pesan wajib diisi', text:'Silakan tulis detail kebutuhan pada kolom Message.' });
      saveTicketBusy = false;
      $saveBtn.prop('disabled', false).html(saveBtnHtml);
      return;
    }
    formData.append('message_html', tidySummernoteHtml(rawCreateHtml));

    var files = $('#createAttachments')[0].files;
    for (var i=0; i<files.length; i++) {
      formData.append('attachments[]', files[i]);
    }

    $.ajax({ url:'save_ticket.php', method:'POST', data: formData, processData:false, contentType:false, dataType:'json' })
      .done(function(r){
        if (r && r.success){
          Swal.fire({ icon:'success', title:'Tersimpan', timer:1800, showConfirmButton:false });
          window.location.href = 'list.php';
        } else {
          Swal.fire({ icon:'error', title:'Gagal', text:(r&&r.message)||'Error' });
        }
      })
      .fail(function(){ Swal.fire({ icon:'error', title:'Gagal', text:'Koneksi bermasalah' }); })
      .always(function(){
        saveTicketBusy = false;
        $saveBtn.prop('disabled', false).html(saveBtnHtml);
      });
  });
});
</script>
