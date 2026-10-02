<?php $theme = $_SESSION['Theme'] ?? 'primary'; ?>

<footer class="main-footer bg-<?= htmlspecialchars($theme); ?> text-light text-sm py-2 mt-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong>&copy; 2025 
                <a href="#" class="text-light font-weight-bold">dmr</a>
            </strong>
            — All rights reserved.
        </div>
        <div>
            <b>Support App</b> v1.0
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


</body>
</html>
