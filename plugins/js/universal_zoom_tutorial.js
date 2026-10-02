(function () {
    var UniversalZoomTutorial = {
        checkAndShow: function (onComplete) {
            // If already seen (flag false) or specifically disabled, skip
            if (!window.needUniversalZoomTutorial) {
                if (typeof onComplete === 'function') onComplete();
                return;
            }

            // Prevent double firing
            if (window.isShowingUniversalZoomTutorial) return;
            window.isShowingUniversalZoomTutorial = true;

            // Show Unlock Animation Modal
            Swal.fire({
                title: '',
                html: `
                    <div style="padding: 20px 0;">
                        <div class="lock-animation-container" style="margin-bottom: 20px; height: 100px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-lock" id="tutorialLockIcon" style="font-size: 60px; color: #ffc107; transition: all 0.5s ease;"></i>
                        </div>
                        <h3 id="tutorialTitle" style="opacity: 0; transform: translateY(20px); transition: all 0.5s ease;">Fitur Baru Terbuka!</h3>
                        <p id="tutorialText" style="opacity: 0; transition: all 0.5s ease 0.2s;"><strong>Universal Zoom</strong>.</p>
                        
                        <div id="tutorialGuide" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #ced4da; margin-top: 20px; text-align: left; opacity: 0; transition: opacity 0.5s ease;">
                            <ul style="padding-left: 20px; margin-bottom: 0; font-size: 0.9rem;">
                                <li><strong>Scroll Mouse</strong> untuk Zoom In / Out</li>
                                <li><strong>Klik & Geser</strong> untuk memindahkan gambar</li>
                            </ul>
                        </div>
                    </div>
                `,
                showConfirmButton: true, // Render it, but we'll hide it via CSS
                confirmButtonText: 'Buka Gambar!',
                confirmButtonColor: '#28a745',
                backdrop: `rgba(0,0,0,0.85)`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () {
                    // FIX: Force High Z-Index on Container to cover header (1100)
                    var container = Swal.getContainer();
                    if (container) {
                        container.style.zIndex = '99999';
                        container.style.position = 'fixed'; // Ensure it's not trapped
                    }

                    var lock = document.getElementById('tutorialLockIcon');
                    var title = document.getElementById('tutorialTitle');
                    var text = document.getElementById('tutorialText');
                    var guide = document.getElementById('tutorialGuide');

                    // Force button hidden initially
                    var confirmBtn = Swal.getConfirmButton();
                    if (confirmBtn) confirmBtn.style.display = 'none';

                    // 1. Shake Animation (Locked)
                    lock.style.animation = 'shake 0.5s ease-in-out';

                    setTimeout(function () {
                        // 2. Unlock Animation
                        lock.classList.remove('fa-lock');
                        lock.classList.add('fa-lock-open');
                        lock.style.color = '#28a745';
                        lock.style.transform = 'scale(1.2)';

                        // 3. Show Text
                        title.style.opacity = '1';
                        title.style.transform = 'translateY(0)';
                        text.style.opacity = '1';

                        // 4. Show Guide & Button TOGETHER
                        setTimeout(function () {
                            guide.style.display = 'block';
                            // Force reflow for transition
                            void guide.offsetWidth;
                            guide.style.opacity = '1';

                            // Show the button AT THE SAME TIME
                            var confirmBtn = Swal.getConfirmButton();
                            if (confirmBtn) {
                                confirmBtn.style.display = 'inline-block';
                                // Add fade-in effect manually for button
                                confirmBtn.style.opacity = '0';
                                confirmBtn.style.transition = 'opacity 0.5s ease';
                                setTimeout(() => confirmBtn.style.opacity = '1', 10);
                            }
                        }, 800);

                    }, 600);
                },
                willClose: function () {
                    window.isShowingUniversalZoomTutorial = false;
                    window.needUniversalZoomTutorial = false;

                    // Mark as seen in backend
                    if (navigator.sendBeacon) {
                        var data = new FormData();
                        navigator.sendBeacon('/gg_app/pages/ticket/api_mark_tutorial_seen.php', data);
                    } else {
                        $.post('/gg_app/pages/ticket/api_mark_tutorial_seen.php');
                    }

                    // PROCEED TO OPEN IMAGE
                    if (typeof onComplete === 'function') onComplete();
                }
            });

            // Add CSS for Shake & High Z-Index Override
            if (!document.getElementById('tutorialShakeStyle')) {
                var style = document.createElement('style');
                style.id = 'tutorialShakeStyle';
                style.innerHTML = `
                    @keyframes shake {
                        0% { transform: translateX(0); }
                        25% { transform: translateX(-5px) rotate(-5deg); }
                        50% { transform: translateX(5px) rotate(5deg); }
                        75% { transform: translateX(-5px) rotate(-5deg); }
                        100% { transform: translateX(0); }
                    }
                    /* Force SweetAlert container on top of everything */
                    .swal2-container {
                        z-index: 99999 !important;
                    }
                `;
                document.head.appendChild(style);
            }
        }
    };

    // Expose Global
    window.UniversalZoomTutorial = UniversalZoomTutorial;
})();
