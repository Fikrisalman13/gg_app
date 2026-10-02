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
                    <button id="clearSig" class="btn btn-light btn-sm">Bersihkan</button>
                    <button id="saveSig" class="btn btn-success btn-sm">Simpan TTD</button>
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
            // Handle high DPI if needed, but for simple signature keep it simple to avoid scaling issues on save
            var dpr = window.devicePixelRatio || 1;
            // canvas.width/height is the actual resolution
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            // style.width/height is the display size
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            
            ctx.scale(dpr, dpr);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height); // fill with white
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
            ctx.lineWidth = 2; // Fixed width for consistency
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
        // Touch fallback if pointer events fail (iOS sometimes tricky)
        canvas.addEventListener('touchstart', function(e){ if(e.target===canvas) start(e); }, {passive:false});
        canvas.addEventListener('touchmove', function(e){ if(e.target===canvas) draw(e); }, {passive:false});
        canvas.addEventListener('touchend', stop);

        // Buttons
        $('#clearSig').off('click').on('click', function(){
            var w = canvas.width, h = canvas.height;
            ctx.clearRect(0,0,w,h);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0,0,w/(window.devicePixelRatio||1),h/(window.devicePixelRatio||1));
        });
        $('.btn-close-modal').off('click').on('click', hideModal);

        // Save Button in Modal
        $('#saveSig').off('click').on('click', function(){
            var dataURL = canvas.toDataURL('image/png');
            // Check if blank
            var blank = true;
            try {
                var dpr = window.devicePixelRatio || 1;
                // Simply check center pixel or look for non-white/non-transparent
                // A better approach for white bg: check if any pixel is not (255,255,255)
                var w = canvas.width, h = canvas.height;
                var pix = ctx.getImageData(0,0,w,h).data;
                for(var i=0; i<pix.length; i+=4){
                    // If not white (assuming white bg)
                    if(pix[i] < 250 || pix[i+1] < 250 || pix[i+2] < 250) {
                        blank = false; break;
                    }
                }
            } catch(e) { blank = false; } // Access error -> assume drawn

            if (blank) { alert('Silakan buat tanda tangan terlebih dahulu.'); return; }
            
            window._lastSignatureData = dataURL;
            hideModal();
            ctx.clearRect(0,0,canvas.width,canvas.height);
            
            // Trigger whatever process needed the signature
            // Logic: we set _skipTTDCheckOnce = true, then trigger the save button again
            window._skipTTDCheckOnce = true;
            
            // Try to find the main save button (adjust selector as needed per form)
            var saveBtn = $('#btnSimpanForm'); 
            if (!saveBtn.length) saveBtn = $('#saveBtn'); // Support #saveBtn as well
            if (!saveBtn.length && window.parent) {
                saveBtn = window.parent.$('#btnSimpanForm');
                if (!saveBtn.length) saveBtn = window.parent.$('#saveBtn');
            }
            
            if (saveBtn.length && saveBtn.is(':visible')) {
                saveBtn.trigger('click');
            } else {
                // If using standard form submit (like some forms do directly)
                // We need to know which form. Standardize to look for the first form with specific class/id if generic
                // Or fallback to checking global variable formId if defined
                var formId = window._activeFormId;
                if(formId && $(formId).length) {
                    window._allowSubmit = true; 
                    $(formId).submit();
                }
            }
        });

        // --- GLOBAL HELPER FUNCTIONS ---
        // 1. validateTTDBeforeSubmit
        // Checks if user has a template in DB. If yes -> returns true. If no -> opens modal, returns false.
        window.validateTTDBeforeSubmit = function(){
            return new Promise(function(resolve){
                var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
                // Adjust path relative to where this file effectively runs
                var checkUrl = '/gg_app/pages/form_it/form_contents/check_ttd_user.php'; 
                
                $.ajax({ url: checkUrl, method: 'POST', dataType: 'json', data: {} })
                .done(function(resp){ 
                    if (resp && resp.exists === true) { 
                        resolve(true); 
                    } else { 
                        // reset canvas and show
                        var w = canvas.width, h = canvas.height; // raw size
                        ctx.clearRect(0,0,w,h);
                        showModal(); 
                        resolve(false); 
                    } 
                })
                .fail(function(){ 
                    // Network error / file missing -> fail safe to show modal
                    showModal(); 
                    resolve(false); 
                });
            });
        };

        // 2. saveTTDWithTicket
        // Saves the signature (either from existing template or new canvas data) linked to a Ticket.
        window.saveTTDWithTicket = function(ticket){
            return new Promise(function(resolve, reject){
                var dataURL = window._lastSignatureData;
                var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);

                if (!dataURL) {
                    // Scenario: User had a template, so we didn't open canvas. 
                    // We need to fetch that template path and link it to this ticket.
                    var getTTDUrl = '/gg_app/pages/form_it/get_existing_ttd.php'; // ensure this path exists/is correct
                    
                    $.ajax({ url: getTTDUrl, method: 'POST', dataType: 'json' })
                    .done(function(r){
                        if (r && r.signature_path) {
                            var savePathUrl = '/gg_app/pages/form_it/save_ttd_pemohon_tiket_path.php';
                            $.ajax({ url: savePathUrl, method: 'POST', data: { ticket: ticket, signature_path: r.signature_path }, dataType: 'json' })
                            .always(function(){ resolve(true); });
                        } else { 
                            // Odd case: check says exists but get returns nothing? Just resolve.
                            resolve(true); 
                        }
                    })
                    .fail(function(){ resolve(true); });
                    return;
                }

                // Scenario: User drew a new signature (dataURL is present)
                var saveUrl = '/gg_app/pages/form_it/save_ttd_pemohon_tiket.php'; // standardized path
                $.ajax({ url: saveUrl, method: 'POST', data: { ticket: ticket, image: dataURL }, dataType: 'json' })
                .done(function(){ 
                    window._lastSignatureData = null; 
                    resolve(true); 
                })
                .fail(function(){ resolve(true); });
            });
        };

    }); // end waitForjQuery
})();
</script>
