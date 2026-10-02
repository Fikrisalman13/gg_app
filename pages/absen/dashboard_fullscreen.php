<?php
session_start();

// Entry khusus layar kiosk kantin. Pakai URL localhost/Chrome --kiosk agar tidak perlu login manual.
$_SESSION['UserName'] = $_SESSION['UserName'] ?? 'KIOSK_KANTIN';
$_SESSION['UserId'] = $_SESSION['UserId'] ?? 'KIOSK_KANTIN';
$_SESSION['Name'] = $_SESSION['Name'] ?? 'Kiosk Kantin';
$_SESSION['GroupId'] = $_SESSION['GroupId'] ?? 1;
$_SESSION['Theme'] = $_SESSION['Theme'] ?? 'success';

require __DIR__ . '/dashboard_kantin.php';
?>
<script>
window.addEventListener('load', function () {
    const AUTO_RELOAD_MS = 36000000;

    function applyKioskZoom() {
        const overlay = document.getElementById('kioskOverlay');
        const page = overlay ? overlay.querySelector('.dk-kiosk-page') : null;
        if (!overlay || !page) return;

        overlay.classList.add('active', 'is-full');
        document.documentElement.style.zoom = '';
        document.body.style.zoom = '';
        overlay.style.setProperty('--dk-scale', '1');

        requestAnimationFrame(function () {
            const widthScale = window.innerWidth / Math.max(page.scrollWidth, 1);
            const heightScale = window.innerHeight / Math.max(page.scrollHeight, 1);
            const firstScale = Math.max(0.62, Math.min(1, widthScale, heightScale) * 0.96);
            overlay.style.setProperty('--dk-scale', String(firstScale));

            requestAnimationFrame(function () {
                const secondWidthScale = window.innerWidth / Math.max(page.scrollWidth * firstScale, 1);
                const secondHeightScale = window.innerHeight / Math.max(page.scrollHeight * firstScale, 1);
                const finalScale = Math.max(0.62, Math.min(1, firstScale, firstScale * secondWidthScale, firstScale * secondHeightScale) * 0.98);
                overlay.style.setProperty('--dk-scale', String(finalScale));
            });
        });
    }

    function activateKioskView() {
        try {
            applyKioskZoom();
            if (typeof showKioskMode === 'function') {
                showKioskMode();
            }
            if (typeof fetchLatest === 'function') {
                fetchLatest();
            }
            const overlay = document.getElementById('kioskOverlay');
            if (overlay) {
                overlay.classList.add('active', 'is-full');
            }
            document.documentElement.classList.add('dk-no-scroll');
            document.body.classList.add('dk-no-scroll');
        } catch (e) {}
    }

    applyKioskZoom();
    activateKioskView();
    setTimeout(activateKioskView, 500);
    window.addEventListener('resize', applyKioskZoom);
    setTimeout(function () {
        window.location.reload();
    }, AUTO_RELOAD_MS);
});
</script>
