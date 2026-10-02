<?php
// Get user theme color from session
if (!isset($themeColor)) {
    $themeColor = $_SESSION['Theme'] ?? 'primary';
}
?>
<!-- Standardized Modal TTD -->
<div id="modalTTD" class="modal" tabindex="-1" role="dialog" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); z-index:1050;">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:700px; margin: 60px auto;">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                <h5 class="modal-title">Buat Tanda Tangan</h5>
                <button type="button" class="close btn-close-modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <canvas id="ttdCanvas" width="600" height="200" style="border:1px solid #ccc; width:100%; height:200px;"></canvas>
                <div class="mt-2 d-flex gap-2">
                    <button type="button" id="clearSig" class="btn btn-light btn-sm">Bersihkan</button>
                    <button type="button" id="saveSig" class="btn btn-success btn-sm">Simpan TTD</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    // Helper to load jQuery if missing (defensive)
    function waitForjQuery(callback) {
        if (window.jQuery) callback(window.jQuery);
        else {
            var start = Date.now();
            var timer = setInterval(function(){
                if (window.jQuery) { clearInterval(timer); callback(window.jQuery); }
                if (Date.now() - start > 10000) { clearInterval(timer); console.error('TTD Component: jQuery not found.'); }
            }, 100);
        }
    }

    waitForjQuery(function($){
        // --- Modal & Canvas Logic ---
        var modal = $('#modalTTD');
        var canvas = document.getElementById('ttdCanvas');
        if (!canvas) return;
        
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        window._lastSignatureData = null;

        function showModal(){ modal.fadeIn(150); resizeCanvas(); }
        function hideModal(){ modal.fadeOut(150); }

        function resizeCanvas() {
            var width = 600, height = 200;
            var dpr = window.devicePixelRatio || 1;
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            
            ctx.scale(dpr, dpr);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }

        // Drawing logic
        var drawing = false, lastX=0, lastY=0;
        
        function getCoords(e) {
            var rect = canvas.getBoundingClientRect();
            var x, y;
            if (e.touches && e.touches.length) {
                x = e.touches[0].clientX - rect.left;
                y = e.touches[0].clientY - rect.top;
            } else if (e.changedTouches && e.changedTouches.length) {
                x = e.changedTouches[0].clientX - rect.left;
                y = e.changedTouches[0].clientY - rect.top;
            } else {
                x = e.clientX - rect.left;
                y = e.clientY - rect.top;
            }
            return [x, y];
        }

        function start(e) {
            e.preventDefault();
            drawing = true;
            var c = getCoords(e);
            lastX = c[0]; lastY = c[1];
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        }
        function draw(e) {
            if (!drawing) return;
            e.preventDefault();
            var c = getCoords(e);
            var x = c[0], y = c[1];
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#000';
            ctx.lineTo(x, y);
            ctx.stroke();
            lastX = x; lastY = y;
        }
        function stop(e) {
            if (!drawing) return;
            e.preventDefault();
            drawing = false;
            ctx.closePath();
        }

        canvas.addEventListener('pointerdown', start);
        canvas.addEventListener('pointermove', draw);
        canvas.addEventListener('pointerup', stop);
        canvas.addEventListener('pointercancel', stop);
        canvas.addEventListener('pointerleave', stop);
        canvas.addEventListener('touchstart', function(e){ if(e.target===canvas) start(e); }, {passive:false});
        canvas.addEventListener('touchmove', function(e){ if(e.target===canvas) draw(e); }, {passive:false});
        canvas.addEventListener('touchend', stop);

        // Buttons
        $('#clearSig').off('click').on('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            var w = canvas.width, h = canvas.height;
            ctx.clearRect(0,0,w,h);
        });
        $('.btn-close-modal').off('click').on('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            hideModal();
        });

        // Save Button in Modal
        $('#saveSig').off('click').on('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            var dataURL = canvas.toDataURL('image/png');
            var blank = true;
            try {
                var w = canvas.width, h = canvas.height;
                var pix = ctx.getImageData(0,0,w,h).data;
                for(var i=0; i<pix.length; i+=4){
                    if(pix[i+3] > 0) {
                        blank = false; break;
                    }
                }
            } catch(e) { blank = false; }

            if (blank) { alert('Silakan buat tanda tangan terlebih dahulu.'); return; }
            
            window._lastSignatureData = dataURL;
            hideModal();
            ctx.clearRect(0,0,canvas.width,canvas.height);
            
            window._skipTTDCheckOnce = true;
            
            console.log('[TTD Canvas] Looking for submit button...');
            var saveBtn = $('#btnSimpanForm'); 
            if (!saveBtn.length) saveBtn = $('#saveBtn');
            if (!saveBtn.length && window.parent) {
                console.log('[TTD Canvas] Button not found in current context, checking parent...');
                saveBtn = window.parent.$('#btnSimpanForm');
                if (!saveBtn.length) saveBtn = window.parent.$('#saveBtn');
            }
            
            console.log('[TTD Canvas] Button found:', saveBtn.length, 'Visible:', saveBtn.is(':visible'));
            
            if (saveBtn.length && saveBtn.is(':visible')) {
                console.log('[TTD Canvas] Triggering button click (AJAX submit)');
                saveBtn.trigger('click');
            } else {
                console.error('[TTD Canvas] Save button not found/visible. AJAX submit was not triggered.');
            }
        });

        // --- GLOBAL HELPER FUNCTIONS ---
        // 1. validateTTDBeforeSubmit
        // Checks if user has a template in DB.
        // Now accepts optional employeeName parameter to check for specific employee
        window.validateTTDBeforeSubmit = function(employeeName){
            return new Promise(function(resolve){
                var checkUrl = '/gg_app/pages/form_umum/check_ttd_user.php'; 
                var postData = {};
                
                // If employeeName is provided, check for that employee instead of session user
                if (employeeName && employeeName.trim()) {
                    postData.nama_pemohon = employeeName.trim();
                }
                
                $.ajax({ url: checkUrl, method: 'POST', dataType: 'json', data: postData })
                .done(function(resp){ 
                    if (resp && resp.exists === true) { 
                        resolve(true); 
                    } else { 
                        var w = canvas.width, h = canvas.height;
                        ctx.clearRect(0,0,w,h);
                        showModal(); 
                        resolve(false); 
                    } 
                })
                .fail(function(){ 
                    showModal(); 
                    resolve(false); 
                });
            });
        };

        // 2. saveTTDWithTicket
        // Saves the signature to the specific ticket.
        // Now accepts optional employeeName parameter
        window.saveTTDWithTicket = function(ticket, employeeName){
            return new Promise(function(resolve, reject){
                var dataURL = window._lastSignatureData;

                if (!dataURL) {
                    var getTTDUrl = '/gg_app/pages/form_umum/get_existing_ttd.php';
                    var getPostData = {};
                    
                    // If employeeName provided, get signature for that employee
                    if (employeeName && employeeName.trim()) {
                        getPostData.nama_pemohon = employeeName.trim();
                    }
                    
                    $.ajax({ url: getTTDUrl, method: 'POST', dataType: 'json', data: getPostData })
                    .done(function(r){
                        if (r && r.signature_path) {
                            var savePathUrl = '/gg_app/pages/form_umum/save_ttd_pemohon_tiket_path.php';
                            var savePathData = { ticket: ticket, signature_path: r.signature_path };
                            
                            // If employeeName provided, pass it to save endpoint
                            if (employeeName && employeeName.trim()) {
                                savePathData.nama_pemohon = employeeName.trim();
                            }
                            
                            $.ajax({ url: savePathUrl, method: 'POST', data: savePathData, dataType: 'json' })
                            .always(function(){ resolve(true); });
                        } else { 
                            resolve(true); 
                        }
                    })
                    .fail(function(){ resolve(true); });
                    return;
                }

                var saveUrl = '/gg_app/pages/form_umum/save_ttd_pemohon_tiket.php';
                var saveData = { ticket: ticket, image: dataURL };
                
                // If employeeName provided, pass it to save endpoint
                if (employeeName && employeeName.trim()) {
                    saveData.nama_pemohon = employeeName.trim();
                }
                
                console.log('[TTD] Saving canvas signature for ticket:', ticket, 'employee:', employeeName);
                
                $.ajax({ url: saveUrl, method: 'POST', data: saveData, dataType: 'json' })
                .done(function(resp){ 
                    console.log('[TTD] Canvas signature saved successfully:', resp);
                    window._lastSignatureData = null;
                    resolve(true); 
                })
                .fail(function(xhr, status, error){ 
                    console.error('[TTD] Failed to save canvas signature:', status, error);
                    resolve(true); 
                });
            });
        };

    });
})();
</script>
