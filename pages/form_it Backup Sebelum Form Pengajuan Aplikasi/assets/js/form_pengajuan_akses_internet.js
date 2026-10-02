(function waitForjQuery(){
    var start = Date.now();
    function tick(){
        if (window.jQuery) return init(window.jQuery);
        if (Date.now() - start > 10000) { console.error('TTD init: jQuery not found after 10s'); return; }
        setTimeout(tick, 100);
    }
    tick();

    function init($){
        var today = new Date();
        var day = today.getDate().toString().padStart(2, '0');
        var month = (today.getMonth() + 1).toString().padStart(2, '0');
        var year = today.getFullYear();
        (function waitForjQuery(){
            var start = Date.now();
            function tick(){
                if (window.jQuery) return init(window.jQuery);
                if (Date.now() - start > 10000) { console.error('TTD init: jQuery not found after 10s'); return; }
                setTimeout(tick, 100);
            }
            tick();

            function init($){
                var today = new Date();
                var day = today.getDate().toString().padStart(2, '0');
                var month = (today.getMonth() + 1).toString().padStart(2, '0');
                var year = today.getFullYear();
                var currentDate = day + '-' + month + '-' + year;
                $('.tgl-pengajuan-inet').val(currentDate);

                var reqAksesCheckbox = document.getElementById('requestAksesInternet');
                var aksesSection = document.getElementById('aksesInternetSection');
                var aksesTempRadio = document.getElementById('aksesTemporary');
                var aksesPermRadio = document.getElementById('aksesPermanent');
                var tempDuration = document.getElementById('temporaryDuration');

                if (reqAksesCheckbox) {
                    reqAksesCheckbox.addEventListener('change', function(){
                        aksesSection.style.display = this.checked ? 'block' : 'none';
                        if (!this.checked) {
                            if (aksesTempRadio) aksesTempRadio.checked = false;
                            if (aksesPermRadio) aksesPermRadio.checked = false;
                            if (tempDuration) tempDuration.style.display = 'none';
                            var f1 = document.querySelector('input[name="akses_temporary_from"]');
                            var f2 = document.querySelector('input[name="akses_temporary_to"]');
                            if (f1) { f1.required = false; f1.value = ''; }
                            if (f2) { f2.required = false; f2.value = ''; }
                        }
                    });
                }

                if (aksesTempRadio) {
                    aksesTempRadio.addEventListener('change', function() {
                        if (tempDuration) tempDuration.style.display = this.checked ? 'block' : 'none';
                        var f1 = document.querySelector('input[name="akses_temporary_from"]');
                        var f2 = document.querySelector('input[name="akses_temporary_to"]');
                        if (this.checked) { if (f1) f1.required = true; if (f2) f2.required = true; }
                    });
                }
                if (aksesPermRadio) {
                    aksesPermRadio.addEventListener('change', function() {
                        if (tempDuration) tempDuration.style.display = 'none';
                        var f1 = document.querySelector('input[name="akses_temporary_from"]');
                        var f2 = document.querySelector('input[name="akses_temporary_to"]');
                        if (f1) { f1.required = false; f1.value = ''; }
                        if (f2) { f2.required = false; f2.value = ''; }
                    });
                }

                var reqBandwidth = document.getElementById('requestTambahBandwidth');
                var bwSection = document.getElementById('bandwidthSection');
                var bwTemp = document.getElementById('bandwidthTemporary');
                var bwPerm = document.getElementById('bandwidthPermanent');
                var bwTempDur = document.getElementById('bandwidthTemporaryDuration');

                if (reqBandwidth) {
                    reqBandwidth.addEventListener('change', function(){
                        if (bwSection) bwSection.style.display = this.checked ? 'block' : 'none';
                        try { if (typeof updateBwJumlahVisibility === 'function') updateBwJumlahVisibility(); } catch(e){}
                        if (!this.checked) {
                            if (bwTemp) bwTemp.checked = false;
                            if (bwPerm) bwPerm.checked = false;
                            if (bwTempDur) bwTempDur.style.display = 'none';
                            var b1 = document.querySelector('input[name="bandwidth_temporary_from"]');
                            var b2 = document.querySelector('input[name="bandwidth_temporary_to"]');
                            if (b1) { b1.required = false; b1.value = ''; }
                            if (b2) { b2.required = false; b2.value = ''; }
                        }
                    });
                }

                if (bwTemp) {
                    bwTemp.addEventListener('change', function(){
                        if (bwTempDur) bwTempDur.style.display = this.checked ? 'block' : 'none';
                        var b1 = document.querySelector('input[name="bandwidth_temporary_from"]');
                        var b2 = document.querySelector('input[name="bandwidth_temporary_to"]');
                        if (this.checked) { if (b1) b1.required = true; if (b2) b2.required = true; }
                        try { if (typeof updateBwJumlahVisibility === 'function') updateBwJumlahVisibility(); } catch(e){}
                    });
                }
                if (bwPerm) {
                    bwPerm.addEventListener('change', function(){
                        if (bwTempDur) bwTempDur.style.display = 'none';
                        var b1 = document.querySelector('input[name="bandwidth_temporary_from"]');
                        var b2 = document.querySelector('input[name="bandwidth_temporary_to"]');
                        if (b1) { b1.required = false; b1.value = ''; }
                        if (b2) { b2.required = false; b2.value = ''; }
                        try { if (typeof updateBwJumlahVisibility === 'function') updateBwJumlahVisibility(); } catch(e){}
                    });
                }

                try {
                    if (reqAksesCheckbox && reqBandwidth) {
                        reqAksesCheckbox.addEventListener('change', function() {
                            if (this.checked && reqBandwidth.checked) {
                                reqBandwidth.checked = false;
                                reqBandwidth.dispatchEvent(new Event('change'));
                            }
                        });
                        reqBandwidth.addEventListener('change', function() {
                            if (this.checked && reqAksesCheckbox.checked) {
                                reqAksesCheckbox.checked = false;
                                reqAksesCheckbox.dispatchEvent(new Event('change'));
                            }
                        });
                    }
                } catch (e) { console.warn('Mutual exclusivity listeners failed', e); }

                // Show/hide jumlah wrappers and enable/disable only the visible inputs
                try {
                    var bwJumlahWrapper = document.getElementById('bwJumlahWrapper');
                    var bwJumlahWrapperPerm = document.getElementById('bwJumlahWrapperPerm');
                    function updateBwJumlahVisibility(){
                        var bwChecked = reqBandwidth && reqBandwidth.checked;
                        var selected = document.querySelector('input[name="bandwidth_type"]:checked');
                        var isTemp = selected && selected.value === 'Temporary';
                        var isPerm = selected && selected.value === 'Permanent';

                        var tempInput = bwJumlahWrapper ? bwJumlahWrapper.querySelector('input[name="tambah_bandwidth"]') : null;
                        var tempSelect = bwJumlahWrapper ? bwJumlahWrapper.querySelector('select[name="tambah_bandwidth_unit"]') : null;
                        var permInput = bwJumlahWrapperPerm ? bwJumlahWrapperPerm.querySelector('input[name="tambah_bandwidth"]') : null;
                        var permSelect = bwJumlahWrapperPerm ? bwJumlahWrapperPerm.querySelector('select[name="tambah_bandwidth_unit"]') : null;

                        if (bwJumlahWrapper) bwJumlahWrapper.style.display = 'none';
                        if (bwJumlahWrapperPerm) bwJumlahWrapperPerm.style.display = 'none';

                        if (tempInput) { tempInput.required = false; tempInput.disabled = true; }
                        if (permInput) { permInput.required = false; permInput.disabled = true; }
                        if (tempSelect) { tempSelect.disabled = true; }
                        if (permSelect) { permSelect.disabled = true; }

                        if (bwChecked && isTemp) {
                            if (bwJumlahWrapper) bwJumlahWrapper.style.display = 'block';
                            if (tempInput) { tempInput.required = true; tempInput.disabled = false; }
                            if (tempSelect) tempSelect.disabled = false;
                            if (permInput) { permInput.value = ''; permInput.required = false; permInput.disabled = true; }
                            if (permSelect) { permSelect.selectedIndex = 0; permSelect.disabled = true; }
                        } else if (bwChecked && isPerm) {
                            if (bwJumlahWrapperPerm) bwJumlahWrapperPerm.style.display = 'block';
                            if (permInput) { permInput.required = true; permInput.disabled = false; }
                            if (permSelect) permSelect.disabled = false;
                            if (tempInput) { tempInput.value = ''; tempInput.required = false; tempInput.disabled = true; }
                            if (tempSelect) { tempSelect.selectedIndex = 0; tempSelect.disabled = true; }
                        } else {
                            if (tempInput) tempInput.value = '';
                            if (permInput) permInput.value = '';
                        }
                    }
                    if (reqBandwidth) reqBandwidth.addEventListener('change', updateBwJumlahVisibility);
                    var bwTypeEls = document.querySelectorAll('input[name="bandwidth_type"]');
                    bwTypeEls.forEach(function(el){ el.addEventListener('change', updateBwJumlahVisibility); });
                    updateBwJumlahVisibility();
                } catch (e) { console.warn('bw jumlah visibility error', e); }

                // Defensive submit handling
                window._allowSubmit = false;
                $('#formPengajuanAksesInternet').off('submit.myTTD').on('submit.myTTD', function(e){
                    if (!window._allowSubmit) {
                        e.preventDefault();
                        if (!window._skipTTDCheckOnce) {
                            $('#saveBtn').trigger('click');
                        } else {
                            window._allowSubmit = true;
                            $('#formPengajuanAksesInternet')[0].submit();
                        }
                        return false;
                    }
                    window._allowSubmit = false;
                    return true;
                });

                var canvas = document.getElementById('ttdCanvas');
                if (!canvas) { console.error('TTD init: canvas not found'); return; }
                var ctx = canvas.getContext('2d', { willReadFrequently: true });
                window._lastSignatureData = null;

                function resizeCanvas() {
                    var width = 600, height = 200, dpr = window.devicePixelRatio || 1;
                    canvas.width = Math.round(width * dpr);
                    canvas.height = Math.round(height * dpr);
                    canvas.style.width = width + 'px';
                    canvas.style.height = height + 'px';
                    ctx.scale(dpr, dpr);
                    ctx.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr);
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                }

                resizeCanvas();

                var drawing = false, lastX = 0, lastY = 0, lastTime = 0, lastPressure = 0.5;
                function getCoords(e) { var rect = canvas.getBoundingClientRect(); if (e.touches && e.touches.length) return [e.touches[0].clientX - rect.left, e.touches[0].clientY - rect.top]; if (e.clientX !== undefined) return [e.clientX - rect.left, e.clientY - rect.top]; if (e.changedTouches && e.changedTouches.length) return [e.changedTouches[0].clientX - rect.left, e.changedTouches[0].clientY - rect.top]; return [0,0]; }
                function start(e){ e.preventDefault(); drawing = true; var c = getCoords(e); lastX = c[0]; lastY = c[1]; lastTime = Date.now(); lastPressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : 0.5; ctx.beginPath(); ctx.moveTo(lastX, lastY); }
                function draw(e){ if(!drawing) return; e.preventDefault(); var c = getCoords(e); var x = c[0], y = c[1]; var now = Date.now(); var elapsed = Math.max(1, now - lastTime); lastTime = now; var pressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : lastPressure; var dist = Math.hypot(x - lastX, y - lastY); var speed = dist / elapsed; var dynamicWidth = Math.max(1, 5 - speed * 2) * pressure; ctx.lineWidth = dynamicWidth; ctx.strokeStyle = '#000'; ctx.lineTo(x, y); ctx.stroke(); lastX = x; lastY = y; lastPressure = pressure; }
                function stop(e){ if(!drawing) return; e.preventDefault(); drawing = false; ctx.closePath(); }

                canvas.addEventListener('pointerdown', start);
                canvas.addEventListener('pointermove', draw);
                canvas.addEventListener('pointerup', stop);
                canvas.addEventListener('pointercancel', stop);
                canvas.addEventListener('pointerleave', stop);
                canvas.addEventListener('touchstart', function(ev){ ev.preventDefault(); }, { passive:false });

                $('#clearSig').off('click').on('click', function(){ var dpr = window.devicePixelRatio || 1; ctx.clearRect(0,0,canvas.width/dpr,canvas.height/dpr); });
                $('.btn-close-modal').off('click').on('click', function(){ $('#modalTTD').fadeOut(150); });

                if (typeof window.validateTTDBeforeSubmit !== 'function') {
                    window.validateTTDBeforeSubmit = function() {
                        return new Promise(function(resolve){
                            var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
                            var basePath = origin + '/gg_app/pages/form_it/form_contents/';
                            var checkUrl = basePath + 'check_ttd_user.php';
                            $.ajax({ url: checkUrl, method: 'POST', dataType: 'json', data: {} })
                            .done(function(resp){ if (resp && resp.exists === true) { resolve(true); } else { ctx.clearRect(0,0,canvas.width,canvas.height); $('#modalTTD').fadeIn(150); resolve(false); } })
                            .fail(function(){ ctx.clearRect(0,0,canvas.width,canvas.height); $('#modalTTD').fadeIn(150); resolve(false); });
                        });
                    };
                }

                if (typeof window.saveTTDWithTicket !== 'function') {
                    window.saveTTDWithTicket = function(ticket) {
                        return new Promise(function(resolve){
                            var dataURL = window._lastSignatureData;
                            if (!dataURL) {
                                var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
                                var getTTDUrl = origin + '/gg_app/pages/form_it/get_existing_ttd.php';
                                $.ajax({ url: getTTDUrl, method: 'POST', dataType: 'json' })
                                .done(function(r){
                                    if (r && r.signature_path) {
                                        $.ajax({ url: origin + '/gg_app/pages/form_it/save_ttd_pemohon_tiket_path.php', method: 'POST', data: { ticket: ticket, signature_path: r.signature_path }, dataType: 'json' })
                                        .always(function(){ resolve(true); });
                                    } else { resolve(true); }
                                })
                                .fail(function(){ resolve(true); });
                                return;
                            }
                            var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
                            $.ajax({ url: origin + '/gg_app/pages/form_it/save_ttd_pemohon_tiket.php', method: 'POST', data: { ticket: ticket, image: dataURL }, dataType: 'json' })
                            .always(function(){ window._lastSignatureData = null; resolve(true); });
                        });
                    };
                }

                $('#saveSig').off('click').on('click', function(){
                    var dataURL = canvas.toDataURL('image/png');
                    var blank = true;
                    try { var pix = ctx.getImageData(0,0,canvas.width,canvas.height).data; for (var i=0;i<pix.length;i+=4) { if (pix[i+3] !== 0) { blank = false; break; } } } catch(e) { blank = false; }
                    if (blank) { alert('Silakan buat tanda tangan terlebih dahulu.'); return; }
                    window._lastSignatureData = dataURL;
                    $('#modalTTD').fadeOut(150);
                    ctx.clearRect(0,0,canvas.width,canvas.height);
                    window._skipTTDCheckOnce = true;
                    var saveBtn = $('#btnSimpanForm');
                    if (!saveBtn.length && window.parent) { saveBtn = window.parent.$('#btnSimpanForm'); }
                    if (saveBtn.length) { saveBtn.trigger('click'); } else { window._allowSubmit = true; $('#formPengajuanAksesInternet')[0].submit(); }
                });
            }
        })();