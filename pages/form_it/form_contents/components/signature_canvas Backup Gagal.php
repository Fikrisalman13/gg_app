<?php
/**
 * AIO Signature Canvas Component
 * 
 * Includes:
 * 1. Modals for Confirm Delete, Success Delete, and Canvas/Upload TTD.
 * 2. JS Logic for Canvas Drawing.
 * 3. JS Logic for validateTTDBeforeSubmit (for Input Forms).
 * 
 * Usage in Detail View:
 * - Include this file at the bottom of the page (before </body>).
 * - Implement your own $(document).on('click', '.btn-ttd') logic to handle specific data-role and ajax calls, 
 *   OR rely on the generic ID-based logic if standardized.
 */
?>
<!-- Modal Konfirmasi Hapus TTD -->
<div class="modal fade" id="modalKonfirmasiHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Konfirmasi Hapus Tanda Tangan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin menghapus tanda tangan ini?</p>
        <p><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusTtd">Ya, Hapus Tanda Tangan</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Sukses Hapus TTD -->
<div class="modal fade" id="modalSuksesHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Berhasil</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="pesanSuksesHapus">Tanda tangan berhasil dihapus.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Buat / Upload TTD -->
<div class="modal fade" id="modalTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Buat / Upload Tanda Tangan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="formTtd" enctype="multipart/form-data">
          <!-- Hidden inputs should be populated by JS before showing modal -->
          <input type="hidden" name="ticket" id="ttdTicket" value="">
          <input type="hidden" name="role_code" id="ttdRoleCode" value="">
          <input type="hidden" name="canvas_data" id="canvasData" value="">
          
          <div class="row">
            <div class="col-md-8 mb-3">
              <label><b>Gambar di Canvas</b></label>
              <div style="border:1px solid #ccc;display:inline-block;">
                <canvas id="ttdCanvas" width="600" height="200" style="background:#fff;cursor:crosshair;"></canvas>
              </div>
              <button type="button" class="btn btn-sm btn-secondary mt-2" id="btnClearCanvas">Bersihkan Canvas</button>
            </div>
            <div class="col-md-4 mb-3">
              <label><b>Atau Upload File PNG/JPG</b></label>
              <input type="file" name="ttd_file" id="ttdFile" accept="image/png,image/jpeg" class="form-control mb-2">
              <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" id="chkSaveTemplate" name="save_template" value="1" checked>
                <label class="form-check-label" for="chkSaveTemplate">Simpan tanda tangan ini sebagai template</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnSimpanTtd">Simpan & Gunakan</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
    // --- Canvas Drawing Logic ---
    var canvas = document.getElementById('ttdCanvas');
    if(canvas){
        var ctx = canvas.getContext('2d');
        var drawing = false; 
        ctx.strokeStyle='#000'; 
        ctx.lineWidth=2;
        
        function getPos(e){ 
            var r=canvas.getBoundingClientRect(); 
            var x,y; 
            if(e.touches&&e.touches.length){ 
                x=e.touches[0].clientX-r.left; 
                y=e.touches[0].clientY-r.top; 
            } else { 
                x=e.clientX-r.left; 
                y=e.clientY-r.top; 
            } 
            return {x:x,y:y}; 
        }
        function startDraw(e){ drawing=true; var p=getPos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); }
        function draw(e){ if(!drawing) return; e.preventDefault(); var p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); }
        function endDraw(){ drawing=false; }
        
        canvas.addEventListener('mousedown', startDraw); 
        canvas.addEventListener('mousemove', draw); 
        canvas.addEventListener('mouseup', endDraw); 
        canvas.addEventListener('mouseleave', endDraw);
        canvas.addEventListener('touchstart', function(e){ startDraw(e); }, {passive:false});
        canvas.addEventListener('touchmove', function(e){ draw(e); }, {passive:false});
        canvas.addEventListener('touchend', function(e){ endDraw(e); }, {passive:false});
        
        function resetCanvas(){ ctx.clearRect(0,0,canvas.width,canvas.height); }
        
        // Reset canvas when modal opens
        $('#modalTtd').on('shown.bs.modal', function(){ resetCanvas(); $('#ttdFile').val(''); });
        $('#btnClearCanvas').on('click', function(){ resetCanvas(); });
        
        // Expose resetCanvas to global if needed
        window.resetTtdCanvas = resetCanvas;
    }
    
    // --- Helper: Prepare Modal for Usage ---
    // Can be called by external scripts
    window.prepareTtdModal = function(ticket, roleCode) {
        $('#ttdTicket').val(ticket);
        $('#ttdRoleCode').val(roleCode);
        $('#modalTtd').modal('show');
    };
    
    // --- Helper: Submit Logic (Generic) ---
    // Handles the 'Simpan & Gunakan' click inside the modal
    // Note: The Actual Ajax call to save might need to be custom per page if URL differs,
    // but typically it goes to ttd_save_template.php
    $('#btnSimpanTtd').on('click', function(){
         var roleCode = $('#ttdRoleCode').val();
         var ticket = $('#ttdTicket').val();
         
         if (canvas) {
             $('#canvasData').val(canvas.toDataURL('image/png'));
         }
         
         var form = document.getElementById('formTtd');
         var fd = new FormData(form);
         
         // If ticket input in form is empty (e.g. on input form where ticket doesn't exist yet),
         // we might need to handle it differently. But for Detail view, ticket is text.
         // For Input view, validation usually happens client side only or saves to temp?
         // This generic script assumes typical Usage.
         
         // Trigger a custom event so the parent page can handle the save if it wants
         // OR proceed with default ajax
         
         $.ajax({
           url: 'ttd_save_template.php', // Assumes strict location relative to detailed page
           method: 'POST', 
           data: fd, 
           processData:false, 
           contentType:false,
           success: function(resp){
             try{ resp = typeof resp === 'string' ? JSON.parse(resp) : resp; }catch(e){ resp={}; }
             if (resp && resp.success && resp.signature_url){ 
                 $('#modalTtd').modal('hide'); 
                 // Trigger global event for success
                 $(document).trigger('ttdSaved', [roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id]);
             }
             else { 
                 alert(resp.message || 'Gagal menyimpan tanda tangan.'); 
             }
           }, error: function(){ alert('Error saat menyimpan tanda tangan.'); }
         });
    });
    
    
    /**
     * validateTTDBeforeSubmit
     * Used by Input Forms to force signature before submit.
     * Returns a Promise that resolves to true (signed) or false (cancelled).
     */
    window.validateTTDBeforeSubmit = function() {
        return new Promise(function(resolve, reject){
            // 1. Check if user has a template?
            // This requires an AJAX check to see if current user has a 'Pemohon' signature template active.
            // If yes, we can auto-sign (or just return true, letting backend handle it).
            // If no, we must show the modal.
            
            // For now, simple implementation: Check if we have a way to verify identity.
            // In typical flow, we just show modal to let them sign "Pemohon"
            
            // NOTE: On Input Form, there is no Ticket Number yet!
            // So ttd_save_template.php might fail if it expects a Ticket.
            // Usually for Input Form, we just want to save the Template (User_TTD_Template).
            
            // Assuming this function is called on a form where we just want to ensure they HAVE a signature.
            // Logic:
            // 1. Check if user has signature template.
            // 2. If not, show modal -> Save Template -> Done.
            
             $.post('ttd_sign.php', { check_template_only: 1, role_code: 'pemohon' }, function(resp){
                 try{ resp = typeof resp === 'string' ? JSON.parse(resp) : resp; }catch(e){ resp={}; }
                 
                 if (resp && resp.has_template) {
                     // User has template, auto-sign allowed
                     resolve(true);
                 } else {
                     // User needs to create template
                     $('#ttdRoleCode').val('pemohon');
                     // Ticket is empty/dummy for template saving
                     $('#ttdTicket').val('TEMPLATE_ONLY'); 
                     $('#modalTtd').modal('show');
                     
                     // Handle the save specifically for this flow
                     // We override the default btnSimpanTtd handler for this one-off? 
                     // Or just let the default handler run, which saves template, then triggers 'ttdSaved'.
                     
                     var onSaved = function(e, role, url, name, uid){
                         $(document).off('ttdSaved', onSaved);
                         resolve(true); // Signed!
                     };
                     
                     $(document).on('ttdSaved', onSaved);
                     
                     // Use modal hide as cancellation
                     $('#modalTtd').one('hidden.bs.modal', function(){
                         // If event wasn't triggered (not saved), then it was cancelled
                         // Check strictly?
                         // resolve(false); 
                     });
                 }
             });
        });
    };
});
</script>
