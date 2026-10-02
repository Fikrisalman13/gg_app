<?php
session_start();
require_once '../../koneksi.php';

$ticket = $_GET['ticket'] ?? '';
if (!$ticket) { die('Ticket tidak ditemukan'); }

$sql = "SELECT * FROM Form_Perubahan_Data_Database WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) { die('Data tidak ditemukan'); }
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Flag rejected
$isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';

// Permission check via User_TTD_Template
$canSignPemohon = $canSignAtasan = $canSignIT = $canSignDireksi = false;

if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT DISTINCT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            elseif (strcasecmp($role, 'Atasan Pemohon') === 0) $canSignAtasan = true;
            // IT Head: Assuming 'Kabag IT' or 'Kadept IT' covers "Mengetahui" (Kabag/Kadept IT)
            elseif (strcasecmp($role, 'Kabag IT') === 0 || strcasecmp($role, 'Kadept IT') === 0) $canSignIT = true;
            elseif (strcasecmp($role, 'Direksi') === 0) $canSignDireksi = true;
        }
    }
}

// Ambil TTD yang sudah ada
$sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttd = [];
if ($stmtTtd && sqlsrv_has_rows($stmtTtd)) {
    while ($rowTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
        $role = $rowTtd['GroupRole'];
        $ttd[$role] = [
            'SignaturePath' => $rowTtd['SignaturePath'],
            'SignedByUserName' => $rowTtd['SignedByUserName'],
            'SignedByUserId' => $rowTtd['SignedByUserId']
        ];
    }
}

function isTtdByCurrentUser($ttdArray, $currentUserId) {
    if (!isset($ttdArray['SignaturePath']) || empty($ttdArray['SignaturePath'])) return false;
    if (!isset($ttdArray['SignedByUserId'])) return false;
    return $ttdArray['SignedByUserId'] == $currentUserId;
}
function isTtdExists($ttdArray) {
    return isset($ttdArray['SignaturePath']) && !empty($ttdArray['SignaturePath']);
}
function fmtDate($d){
    if ($d instanceof DateTime) return $d->format('d-m-Y');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) { $p=explode('-', $d); return $p[2].'-'.$p[1].'-'.$p[0]; }
    return '-';
}
function checkMarkup($val) {
    return ($val == 1) ? '<span style="font-family: DejaVu Sans, sans-serif;">&#9745;</span>' : '<span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Pengajuan Database - <?= htmlspecialchars($ticket) ?></title>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<style>
 body{background:#ffffff;font-size:15px;color:#000;} 
 table{border-collapse:collapse;width:100%;} 
 td{padding:5px 8px;vertical-align:top;} 
 .b{font-weight:bold;} 
 .center{text-align:center;} 
 .border{border:1px solid #000;} 
 .ttd-scroll{width:100%;} 
 .ttd-scroll table{width:100%;text-align:center;} 
 @media(max-width:768px){.ttd-scroll{overflow-x:auto;} .ttd-scroll table{min-width:600px;}}
</style>
<script>
window.addEventListener('load', function(){
  // Allow reject if user is IT or Direksi (adjust as needed based on policy)
  var canReject = <?php echo ($canSignIT || $canSignDireksi) && !$isRejected ? 'true':'false'; ?>;
  var ticket = '<?= htmlspecialchars($ticket) ?>';
  if (window.parent && window.parent.updateRejectButtonVisibility) {
    window.parent.updateRejectButtonVisibility(canReject, ticket);
  }
});
</script>
</head>
<body>
<style>
  /* Mobile Responsive Styles - Horizontal Scroll */
  @media (max-width: 768px) {
      .form-wrapper {
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          width: 100%;
          padding-bottom: 10px;
          margin: 5px auto !important;
      }
      .outer-form { min-width: 800px; }
      img[alt="logo"] { width: 50px !important; }
      body { font-size: 14px; }
  }
</style>

   <!-- STATUS PENOLAKAN (jika ada) -->
   <?php include 'form_contents/components/rejection_status.php'; ?>

   <div class="form-wrapper" style="max-width:900px;margin:10px auto;">
   <table class="outer-form" style="width:100%;border:1px solid #000;border-collapse:collapse;font-size:14px;line-height:1.4;">
     <tr>
       <td style="width:100%;padding:0;">
         <table style="width:100%;border-collapse:collapse;">
           <tr>
             <td style="width:90px;border-right:1px solid #000;padding:8px 6px;vertical-align:top;text-align:center;">
               <img src="../../dist/img/sumlogo.png" width="70" alt="logo" style="display:block;margin:0 auto;">
             </td>
             <td style="padding:6px 8px;vertical-align:top;">
               <b style="font-size:16px;">PT. SURYA USAHA MANDIRI</b><br>
               Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
               Banjaran – Kab. Bandung<br>
               40377 Telp. (022) 594-0313
             </td>
           </tr>
           <tr>
             <td colspan="2" style="border-top:1px solid #000;border-bottom:1px solid #000;text-align:center;font-weight:bold;padding:6px 4px;">PENGAJUAN PERUBAHAN DATA VIA DATABASE</td>
           </tr>
         </table>
       </td>
     </tr>
     <tr>
       <td style="padding:0;">
         <table style="width:100%;border-collapse:collapse;">
           <tr>
             <td style="width:180px;padding:4px 6px;">Nama Pemohon</td>
             <td style="padding:4px 6px;">: <?= htmlspecialchars($data['nama_pemohon']) ?></td>
             <td style="width:10px;"></td> 
             <td style="width:100px;padding:4px 6px;"></td>
             <td style="padding:4px 6px;"></td>
           </tr>
           <tr>
             <td style="padding:4px 6px;">Jabatan</td>
             <td style="padding:4px 6px;">: <?= htmlspecialchars($data['jabatan']) ?></td>
             <td></td>
             <td style="padding:4px 6px;">Tgl Pengajuan</td>
             <td style="padding:4px 6px;">: <?= fmtDate($data['tgl_pengajuan']) ?></td>
           </tr>
           <tr>
             <td style="padding:4px 6px;">Departemen</td>
             <td style="padding:4px 6px;">: <?= htmlspecialchars($data['departemen']) ?></td>
             <td></td> 
             <td style="padding:4px 6px;"></td>
             <td style="padding:4px 6px;"></td>
           </tr>
            <!-- BAGIAN -->
           <?php if (!empty($data['bagian'])): ?>
            <tr>
             <td style="padding:4px 6px;">Bagian</td>
             <td colspan="4" style="padding:4px 6px;">: <?= htmlspecialchars($data['bagian']) ?></td>
           </tr>
           <?php endif; ?>

           <tr><td colspan="5" style="height:10px;"></td></tr>

           <tr>
             <td style="padding:4px 6px;">Mengajukan permintaan untuk</td>
             <td colspan="4" style="padding:4px 6px;">: 
                <?= checkMarkup($data['req_add']) ?> Add &nbsp;&nbsp;
                <?= checkMarkup($data['req_edit']) ?> Edit &nbsp;&nbsp;
                <?= checkMarkup($data['req_delete']) ?> Delete
             </td>
           </tr>
           <tr>
             <td style="padding:4px 6px;">Aplikasi</td>
             <td colspan="4" style="padding:4px 6px;">: 
                <?= checkMarkup($data['app_proint']) ?> Proint &nbsp;&nbsp;
                <?= checkMarkup($data['app_hris']) ?> HRIS &nbsp;&nbsp;
                <?= checkMarkup($data['app_lainnya']) ?> Lainnya : <span style="border-bottom:1px solid #000; display:inline-block; min-width:150px;"><?= htmlspecialchars($data['app_lainnya_text'] ?? '') ?></span>
             </td>
           </tr>
           <tr>
             <td style="padding:4px 6px;vertical-align:top;">Perubahan</td>
             <td colspan="4" style="padding:4px 6px;">: 
                   <?= nl2br(htmlspecialchars($data['perubahan'])) ?>
                </div>
             </td>
           </tr>
           <tr>
             <td style="padding:4px 6px;vertical-align:top;">Keterangan/Alasan</td>
             <td colspan="4" style="padding:4px 6px;">: 
                  <?= nl2br(htmlspecialchars($data['keterangan'] ?? '-')) ?>
                </div>
             </td>
           </tr>
         </table>
       </td>
     </tr>
     <tr>
       <td style="padding:6px;">
         <div class="signature-wrapper">
         <table class="signature-table" style="width:100%;border-collapse:collapse;" border="1">
           <tr style="text-align:center;font-size:14px;font-weight:bold;">
             <td style="width:25%;padding:4px;">Diajukan oleh,</td>
             <td style="width:25%;padding:4px;">Diketahui oleh,</td>
             <td style="width:25%;padding:4px;">Mengetahui,</td>
             <td style="width:25%;padding:4px;">Disetujui oleh,</td>
           </tr>
           <!-- ROW FOR SIGNATURE IMAGES -->
           <tr style="height:120px;text-align:center;vertical-align:middle;">
             <!-- 1. PEMOHON -->
             <td style="padding:6px;vertical-align:middle;">
               <div id="ttd-area-pemohon">
                 <?php if (isset($ttd['Pemohon'])): ?><img src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60" alt="TTD Pemohon"><?php endif; ?>
               </div>
               <?php if ($canSignPemohon && !$isRejected): ?>
                 <?php if (isTtdByCurrentUser($ttd['Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                   <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="pemohon">Hapus Tanda Tangan</button>
                 <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                   <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                 <?php else: ?>
                   <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="pemohon">Tanda Tangan</button>
                 <?php endif; ?>
               <?php endif; ?>
             </td>

             <!-- 2. ATASAN LANGSUNG (Taken as 'Atasan Pemohon') -->
             <td style="padding:6px;vertical-align:middle;">
               <div id="ttd-area-atasan">
                 <?php if (isset($ttd['Atasan Pemohon'])): ?><img src="<?= htmlspecialchars($ttd['Atasan Pemohon']['SignaturePath']) ?>" height="60" alt="TTD Atasan"><?php endif; ?>
               </div>
               <?php if ($canSignAtasan && !$isRejected): ?>
                 <?php if (isTtdByCurrentUser($ttd['Atasan Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                   <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="atasan_pemohon">Hapus Tanda Tangan</button>
                 <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                   <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                 <?php else: ?>
                   <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="atasan_pemohon">Tanda Tangan</button>
                 <?php endif; ?>
               <?php endif; ?>
             </td>

             <!-- 3. KABAG/KADEPT IT (Mengetahui) -->
             <td style="padding:6px;vertical-align:middle;">
               <div id="ttd-area-it">
                 <?php 
                    // Prefer Kadept, then Kabag
                    if (isset($ttd['Kadept IT'])) echo '<img src="'.htmlspecialchars($ttd['Kadept IT']['SignaturePath']).'" height="60">';
                    elseif (isset($ttd['Kabag IT'])) echo '<img src="'.htmlspecialchars($ttd['Kabag IT']['SignaturePath']).'" height="60">';
                 ?>
               </div>
               <?php if ($canSignIT && !$isRejected): ?>
                 <?php 
                    $myRoleIT = '';
                    $sqlRole2 = "SELECT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1 AND (GroupRole = 'Kabag IT' OR GroupRole = 'Kadept IT')";
                    $stmtRole2 = sqlsrv_query($conn, $sqlRole2, [$_SESSION['UserId']]);
                    if($stmtRole2 && $rowR2=sqlsrv_fetch_array($stmtRole2, SQLSRV_FETCH_ASSOC)){
                        $myRoleIT = $rowR2['GroupRole']; // 'Kabag IT' or 'Kadept IT'
                    }
                    $roleKeyIT = ($myRoleIT == 'Kadept IT') ? 'Kadept IT' : 'Kabag IT';
                    $roleCodeIT = ($myRoleIT == 'Kadept IT') ? 'kadept_it' : 'kabag_it';
                    
                    // Check if signed by ANY IT Head (Kabag OR Kadept)
                    $isSignedByIT = isTtdExists($ttd['Kadept IT'] ?? []) || isTtdExists($ttd['Kabag IT'] ?? []);
                    $isSignedByMe = isTtdByCurrentUser($ttd[$roleKeyIT] ?? [], $_SESSION['UserId'] ?? 0);

                    if ($isSignedByMe): ?>
                   <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="<?= $roleCodeIT ?>">Hapus Tanda Tangan</button>
                 <?php elseif ($isSignedByIT): ?>
                   <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                 <?php elseif ($myRoleIT): ?>
                   <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="<?= $roleCodeIT ?>">Tanda Tangan</button>
                 <?php endif; ?>
               <?php endif; ?>
             </td>

             <!-- 4. DIREKSI (Disetujui) -->
             <td style="padding:6px;vertical-align:middle;">
               <div id="ttd-area-direksi">
                 <?php if (isset($ttd['Direksi'])): ?><img src="<?= htmlspecialchars($ttd['Direksi']['SignaturePath']) ?>" height="60" alt="TTD Direksi"><?php endif; ?>
               </div>
               <?php if ($canSignDireksi && !$isRejected): ?>
                 <?php if (isTtdByCurrentUser($ttd['Direksi'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                   <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="direksi">Hapus Tanda Tangan</button>
                 <?php elseif (isTtdExists($ttd['Direksi'] ?? [])): ?>
                   <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                 <?php else: ?>
                   <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="direksi">Tanda Tangan</button>
                 <?php endif; ?>
               <?php endif; ?>
             </td>
           </tr>

           <!-- ROW FOR NAMES -->
           <tr style="text-align:center;font-weight:bold;font-size:14px;">
             <!-- Note: If not signed, display role name as placeholder. If signed, display name only. -->
             <td style="padding:4px;" id="ttd-label-pemohon">
                <?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?>
             </td>
             <td style="padding:4px;" id="ttd-label-atasan">
                <?= isset($ttd['Atasan Pemohon']) ? htmlspecialchars($ttd['Atasan Pemohon']['SignedByUserName']) : 'Atasan Langsung' ?>
             </td>
             <td style="padding:4px;" id="ttd-label-it">
                <?php 
                    if (isset($ttd['Kadept IT'])) echo htmlspecialchars($ttd['Kadept IT']['SignedByUserName']);
                    elseif (isset($ttd['Kabag IT'])) echo htmlspecialchars($ttd['Kabag IT']['SignedByUserName']);
                    else echo 'Kabag/Kadept IT';
                ?>
             </td>
             <td style="padding:4px;" id="ttd-label-direksi">
                <?= isset($ttd['Direksi']) ? htmlspecialchars($ttd['Direksi']['SignedByUserName']) : 'Direksi' ?>
             </td>
           </tr>
         </table>
         </div>
       </td>
     </tr>
     <tr>
       <td style="padding:6px 8px;font-size:14px;">
         <b>Ketentuan :</b><br>
         <ol style="margin:4px 0 0 18px;padding-left:0;">
           <li>Database Aplikasi sepenuhnya menjadi tanggung jawab Departemen IT.</li>
           <li>Perubahan data via Database <u>tidak dianjurkan jika tidak urgent</u>, karna dapat beresiko terhadap integritas, keamanan dan konsistensi data.</li>
         </ol>
       </td>
     </tr>
     <tr>
       <td style="padding:0;">
         <table style="width:100%;border-collapse:collapse;border-top:1px solid #000;">
           <tr>
           
             <td style="width:50%;text-align:center;font-weight:bold;padding:4px;border:1px solid #000;">SUM-FM-IT-022</td>
             <td style="width:20%;text-align:center;font-weight:bold;padding:4px;border:1px solid #000;">SW</td>
           </tr>
         </table>
       </td>
     </tr>
   </table>
  


 <!-- Include Signature Canvas Component (AIO) -->
 <?php include 'form_contents/components/signature_canvas.php'; ?>

 <!-- Modal Konfirmasi Hapus TTD -->
 <div class="modal fade" id="modalKonfirmasiHapusTtd" tabindex="-1" role="dialog">
   <div class="modal-dialog modal-dialog-centered" role="document">
     <div class="modal-content">
       <div class="modal-header bg-danger text-white">
         <h5 class="modal-title">Hapus Tanda Tangan?</h5>
         <button type="button" class="close" data-dismiss="modal" aria-label="Close">
           <span aria-hidden="true">&times;</span>
         </button>
       </div>
       <div class="modal-body">
         <p>Apakah Anda yakin ingin menghapus tanda tangan ini?</p>
       </div>
       <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
         <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusTtd">Ya, Hapus</button>
       </div>
     </div>
   </div>
 </div>

 <!-- Modal Sukses Hapus TTD -->
 <div class="modal fade" id="modalSuksesHapusTtd" tabindex="-1" role="dialog">
   <div class="modal-dialog modal-dialog-centered" role="document">
     <div class="modal-content">
       <div class="modal-header bg-success text-white">
         <h5 class="modal-title">Berhasil</h5>
         <button type="button" class="close" data-dismiss="modal" aria-label="Close">
             <span aria-hidden="true">&times;</span>
         </button>
       </div>
       <div class="modal-body">
         <p id="pesanSuksesHapus">Tanda tangan berhasil dihapus.</p>
       </div>
       <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
       </div>
     </div>
   </div>
 </div>
 
 <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
 <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
 <script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
 <script>
 $(function(){
   var currentRoleCode = null;
   var isRejected = <?= $isRejected ? 'true':'false' ?>;
   var roleToDelete = null;
   var ticket = '<?= htmlspecialchars($data['ticket']) ?>';
 
   // --- 1. HANDLE TOMBOL SIGN ---
   $(document).on('click', '.btn-ttd', function(){
     var roleCode = $(this).data('role');
     currentRoleCode = roleCode;
     
     // Cek status signature via AJAX
     $.post('ttd_sign.php', { ticket: ticket, role_code: roleCode }, function(resp){
       try{ resp = typeof resp === 'string' ? JSON.parse(resp) : resp; }catch(e){ resp={}; }
       
       if (resp && resp.success && resp.signature_url){
         // Sukses sign (langsung pakai template)
         updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
       } else if (resp && resp.need_template){
         // Butuh template -> Buka Modal dari Component
         // Set value hidden input di modal
         $('#ttdTicket').val(ticket);
         $('#ttdRoleCode').val(roleCode);
         $('#modalTtd').modal('show');
       } else {
         alert(resp.message || 'Gagal memproses tanda tangan.');
       }
     });
   });
   
   // --- 2. LISTEN EVENT 'ttdSaved' DARI COMPONENT ---
   // Component signature_canvas.php akan mentrigger ini setelah simpan sukses
   $(document).on('ttdSaved', function(e, roleCode, imgUrl, signedBy, signedByUserId){
       updateTtdArea(roleCode, imgUrl, signedBy, signedByUserId);
   });
 
   // --- 3. HANDLE TOMBOL DELETE ---
   $(document).on('click', '.btn-delete-ttd', function(){ 
       roleToDelete = $(this).data('role'); 
       $('#modalKonfirmasiHapusTtd').modal('show'); 
   });
   
   $('#btnKonfirmasiHapusTtd').on('click', function(){
     if (!roleToDelete){ alert('Role tidak ditemukan.'); return; }
     $.post('delete_ttd.php', { ticket: ticket, role_code: roleToDelete }, function(resp){
       try{ resp = typeof resp === 'string' ? JSON.parse(resp) : resp; }catch(e){ resp={}; }
       $('#modalKonfirmasiHapusTtd').modal('hide');
       
       if (resp && resp.success){
         removeTtdArea(roleToDelete);
         $('#modalSuksesHapusTtd').modal('show');
       } else {
         alert(resp.message || 'Gagal menghapus tanda tangan.');
       }
     });
   });
 
   // --- HELPER UI UPDATES ---
   function updateTtdArea(roleCode, imgUrl, name, signedByUserId){
     var areaId,labelId;
     if (roleCode==='pemohon'){ areaId='#ttd-area-pemohon'; labelId='#ttd-label-pemohon'; }
     else if (roleCode==='atasan_pemohon'){ areaId='#ttd-area-atasan'; labelId='#ttd-label-atasan'; }
     else if (roleCode==='kabag_it' || roleCode==='kadept_it'){ areaId='#ttd-area-it'; labelId='#ttd-label-it'; }
     else if (roleCode==='direksi'){ areaId='#ttd-area-direksi'; labelId='#ttd-label-direksi'; }
     
     if (areaId) $(areaId).html('<img src="'+imgUrl+'" height="60">');
     if (labelId && name) $(labelId).text(name);
     
     var tdContainer = $('button[data-role="'+roleCode+'"]').closest('td');
     if (tdContainer.length){ 
         tdContainer.find('button.btn-ttd').remove();
         tdContainer.find('button.btn-delete-ttd').remove();
         tdContainer.find('button[disabled]').remove();
         
       var currentUserId = <?= $_SESSION['UserId'] ?? 0 ?>;
       if (isRejected) return;
       // Loose comparison for ID
       if (signedByUserId && parseInt(signedByUserId) == parseInt(currentUserId)) {
         tdContainer.append('<button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="'+roleCode+'">Hapus Tanda Tangan</button>');
       } else {
         tdContainer.append('<button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>');
       }
     }
   }
   
   function removeTtdArea(roleCode){
     var areaId,labelId,def;
     if (roleCode==='pemohon'){ areaId='#ttd-area-pemohon'; labelId='#ttd-label-pemohon'; def='Pemohon'; }
     else if (roleCode==='atasan_pemohon'){ areaId='#ttd-area-atasan'; labelId='#ttd-label-atasan'; def='Atasan Langsung'; }
     else if (roleCode==='kabag_it' || roleCode==='kadept_it'){ areaId='#ttd-area-it'; labelId='#ttd-label-it'; def='Kabag/Kadept IT'; }
     else if (roleCode==='direksi'){ areaId='#ttd-area-direksi'; labelId='#ttd-label-direksi'; def='Direksi'; }

     if (areaId) $(areaId).html(''); 
     if (labelId) $(labelId).text(def);
     
     var tdContainer = $('button[data-role="'+roleCode+'"]').closest('td');
     if (tdContainer.length){ 
         tdContainer.find('button.btn-delete-ttd').remove(); 
         tdContainer.find('button[disabled]').remove();
         if(!isRejected){ 
             tdContainer.append('<button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="'+roleCode+'">Tanda Tangan</button>'); 
        } 
     }
   }
 });
 </script>
 </body>
 </html>
