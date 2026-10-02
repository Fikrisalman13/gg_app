// DISABLED: Ticket resolution button and modal functionality
// Uncomment below to re-enable

/*
(function(){
  // Run only on view_asset.php
  var path = window.location.pathname || '';
  if (!/\/pages\/asset\/view_asset\.php$/i.test(path)) return;

  function getQueryParam(name){
    var m = new RegExp('[?&]'+name+'=([^&#]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g,' ')) : null;
  }
  var idAsset = getQueryParam('id');
  if (!idAsset) return;

  function ready(fn){ if (document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

  ready(function(){
    var $ = window.jQuery;
    // Ensure Bootstrap loaded
    // Build modal HTML (mobile friendly)
    var modalHtml = '\n<div class="modal fade" id="modalTicketResolution" tabindex="-1" aria-hidden="true">\n  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">\n    <div class="modal-content">\n      <div class="modal-header">\n        <h5 class="modal-title">Keterangan Penyelesaian Ticket</h5>\n        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>\n      </div>\n      <div class="modal-body">\n        <form id="ticketResolutionForm">\n          <input type="hidden" name="id_asset" value="'+ idAsset +'"/>\n          <div class="mb-3">\n            <label class="form-label">Aksi</label>\n            <select name="action" class="form-select" required>\n              <option value="resolve">Resolved (Used)</option>\n              <option value="broken">Broken</option>\n            </select>\n          </div>\n          <div class="mb-3">\n            <label class="form-label">Catatan</label>\n            <textarea name="note" class="form-control" rows="4" placeholder="Tuliskan keterangan perbaikan/analisa" required></textarea>\n          </div>\n          <div class="mb-3">\n            <label class="form-label">Lampiran (opsional)</label>\n            <input type="file" name="attachments" class="form-control" multiple />\n          </div>\n        </form>\n      </div>\n      <div class="modal-footer">\n        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>\n        <button type="button" id="btnSubmitTicketResolution" class="btn btn-primary">Kirim</button>\n      </div>\n    </div>\n  </div>\n</div>\n';

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Inject button next to Status badge (best-effort DOM query)
    var statusBadge = document.querySelector('.badge, .badge-info, .badge-primary');
    var btn = document.createElement('button');
    btn.className = 'btn btn-sm btn-outline-primary ms-2';
    btn.type = 'button';
    btn.textContent = 'Tutup Ticket / Keterangan';
    btn.addEventListener('click', function(){
      var modalEl = document.getElementById('modalTicketResolution');
      if (window.bootstrap && window.bootstrap.Modal) {
        var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      } else if ($) {
        $('#modalTicketResolution').modal('show');
      } else {
        modalEl.style.display = 'block';
      }
    });
    if (statusBadge && statusBadge.parentNode){ statusBadge.parentNode.appendChild(btn); }
    else { document.querySelector('.content-wrapper')?.appendChild(btn); }

    function showToast(type, title, text){
      if (window.Swal) { Swal.fire({ icon:type, title:title, text:text, timer:2500, showConfirmButton:false }); }
      else { alert(title+': '+text); }
    }

    function appendHistoryRow(html){
      // Try to locate Riwayat table tbody
      var tbody = document.querySelector('#assetHistoryTable tbody, table tbody');
      if (!tbody) return;
      var temp = document.createElement('tbody');
      temp.innerHTML = html;
      var tr = temp.querySelector('tr');
      if (tr) tbody.insertBefore(tr, tbody.firstChild);
    }

    var submitting = false;
    document.getElementById('btnSubmitTicketResolution').addEventListener('click', function(){
      if (submitting) return; submitting = true;
      var form = document.getElementById('ticketResolutionForm');
      var fd = new FormData(form);
      var url = '/gg_app/pages/asset/ajax_update_status.php';
      if (window.Swal){ Swal.showLoading(); }
      fetch(url, { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(json){
          submitting = false;
          if (window.Swal){ Swal.close(); }
          if (json && json.success){
            showToast('success', 'Berhasil', json.message || 'Keterangan tersimpan');
            // hide modal
            var modalEl = document.getElementById('modalTicketResolution');
            if (window.bootstrap && window.bootstrap.Modal){ window.bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }
            else if ($) { $('#modalTicketResolution').modal('hide'); }
            // append history row if provided
            if (json.history_row_html){ appendHistoryRow(json.history_row_html); }
          } else {
            showToast('error', 'Gagal', (json && json.message) || 'Terjadi kesalahan');
          }
        })
        .catch(function(err){ submitting = false; if (window.Swal){ Swal.close(); } showToast('error','Gagal', err && (err.message||'Error')); });
    });
  });
})();
*/
