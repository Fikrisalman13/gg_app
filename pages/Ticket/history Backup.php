<?php
// ===================================================
// 1. INISIALISASI & PERLINDUNGAN HALAMAN
// ===================================================
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';

if (!isset($_SESSION['UserId'])) {
  header('Location: /gg_app/login.php');
  exit;
}

// ===================================================
// 2. HAK AKSES MENU TICKET (ID 148)
// ===================================================
if (!function_exists('checkPermissions')) {
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
// 3. KONFIGURASI TEMA & INCLUDE LAYOUT
// ===================================================
$includeTicketThemeCss = true;
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$GLOBALS['ticketThemeOverride'] = $themeColor;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- ===================================================
  4. STYLESHEET & PENYESUAIAN TAMPILAN
  =================================================== -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<style>
/* Fix Header Icon Clickability */
.main-header { z-index: 1100 !important; position: relative; }
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
  5. KONTEN UTAMA: TABEL RIWAYAT TICKET
  =================================================== -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Riwayat Ticket</h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li><li class="breadcrumb-item active">Riwayat Ticket</li></ol></div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
          <h3 class="card-title"><i class="fas fa-list mr-1"></i>Daftar Ticket Selesai</h3>
        </div>
        <div class="card-body">
          <!-- Filter Section -->
          <div class="filter-section p-3 mb-3" style="background-color: #f8f9fa; border-radius: 5px;">
            <div class="row">
              <div class="col-md-3">
                <label class="small mb-1" for="filterTanggalMulai">Tanggal Mulai</label>
                <input type="date" id="filterTanggalMulai" class="form-control form-control-sm">
              </div>
              <div class="col-md-3">
                <label class="small mb-1" for="filterTanggalAkhir">Tanggal Akhir</label>
                <input type="date" id="filterTanggalAkhir" class="form-control form-control-sm">
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button id="btnReset" class="btn btn-secondary btn-sm" title="Reset Filter">
                  <i class="fas fa-sync"></i> Reset
                </button>
              </div>
              <div class="col-md-4 d-flex align-items-end justify-content-end">
                <button id="btnExportPdf" class="btn btn-danger btn-sm">
                  <i class="fas fa-file-pdf"></i> Export PDF
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
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
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<!-- ===================================================
  6. SCRIPT: DATATABLE + AKSI HAPUS
  =================================================== -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function(){
  var ticketPermissions = {
    canDelete: <?php echo (($permissions['CanDelete'] ?? 0) == 1) ? 'true' : 'false'; ?>
  };

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

  function stripEditButtons(html) {
    if (!html) { return ''; }
    var container = document.createElement('div');
    container.innerHTML = html;
    var selectors = ['.js-edit-ticket', '.js-edit'];
    selectors.forEach(function(sel){
      container.querySelectorAll(sel).forEach(function(node){ node.remove(); });
    });
    return container.innerHTML;
  }

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
            d.view_mode = 'history';
            d.tanggal_mulai = $('#filterTanggalMulai').val();
            d.tanggal_akhir = $('#filterTanggalAkhir').val();
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
      { data:'aksi', orderable:false, searchable:false, render:function(d){ return stripEditButtons(d); } }
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

  // Auto Filter Action (Reload Table)
  $('#filterTanggalMulai, #filterTanggalAkhir').on('change', function() {
    table.ajax.reload();
  });

  // Reset Button Action
  $('#btnReset').on('click', function() {
    $('#filterTanggalMulai').val('');
    $('#filterTanggalAkhir').val('');
    table.ajax.reload();
  });

  // Export PDF Action
  $('#btnExportPdf').on('click', function() {
    var tanggalMulai = $('#filterTanggalMulai').val();
    var tanggalAkhir = $('#filterTanggalAkhir').val();
    
    var url = 'export_history_pdf.php?';
    var params = [];
    
    if (tanggalMulai) {
      params.push('tanggal_mulai=' + encodeURIComponent(tanggalMulai));
    }
    if (tanggalAkhir) {
      params.push('tanggal_akhir=' + encodeURIComponent(tanggalAkhir));
    }
    
    window.open(url + params.join('&'), '_blank');
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
});
</script>

