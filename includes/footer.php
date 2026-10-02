<?php $theme = $_SESSION['Theme'] ?? 'primary'; ?>

<footer class="main-footer bg-<?= htmlspecialchars($theme); ?> text-light text-sm py-2 mt-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong>&copy; <?= date('Y'); ?> 
                <a href="#" class="text-light font-weight-bold">dmr</a>
            </strong>
            — All rights reserved.
        </div>
        <div>
            <?php require_once __DIR__ . '/app_version.php'; ?>
            <b>Support App</b> v<?= APP_VERSION ?>
        </div>
    </div>
</footer>

</div> <!-- end wrapper -->

<!-- SCRIPTS -->
<!-- jQuery (WAJIB dari AdminLTE) -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap Bundle -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- AdminLTE -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>

<!-- Select2 CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<!-- Select2 JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>

<?php if (!empty($_SESSION['UserId'])): ?>
<script>
window.ticketDesktopNotifConfig = window.ticketDesktopNotifConfig || {};
window.ticketDesktopNotifConfig.userId = <?php echo intval($_SESSION['UserId'] ?? 0); ?>;
window.ticketDesktopNotifConfig.notificationIcon = '/gg_app/dist/img/sumlogo.png';
window.ticketDesktopNotifConfig.pollInterval = 8000;
</script>
<script src="/gg_app/scripts/ticket-desktop-notif.js"></script>
<?php include __DIR__ . '/ticket_chat.php'; ?>


<!-- KeepAlive: Ping server setiap 1 menit agar sesi tidak timeout -->
<script>
(function() {
    var PING_INTERVAL = 60 * 1000; // 1 menit
    var expiredHitCount = 0;

    function pingSession() {
        if (typeof fetch !== 'undefined') {
            fetch('/gg_app/includes/ping.php', {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.alive === false) {
                        expiredHitCount++;
                        if (expiredHitCount >= 2) {
                            window.location.reload();
                        }
                        return;
                    }
                    expiredHitCount = 0;
                })
                .catch(function() { /* abaikan error jaringan sementara */ });
        }
    }

    pingSession();
    setInterval(pingSession, PING_INTERVAL);
})();
</script>
<?php endif; ?>

</body>
</html>

<?php
// Flush output buffer yang dibuka di header.php.
// Semua output halaman baru dikirim ke browser di sini.
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
