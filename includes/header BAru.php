<?php
// Pastikan session_start() dipanggil pertama
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 hari
        'cookie_path' => '/gg_app/',
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true
    ]);
}

// Pengecekan login
if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
    if (!isset($_SESSION['login_redirect'])) {
        $_SESSION['error'] = "Silakan login terlebih dahulu!";
        $_SESSION['login_redirect'] = true;
    }
    header("Location: /gg_app/login.php");
    exit;
}

// Hapus pesan error jika sudah valid
if (isset($_SESSION['error']) && $_SESSION['error'] == "Silakan login terlebih dahulu!") {
    unset($_SESSION['error']);
    unset($_SESSION['login_redirect']);
}

// Ambil data user & tema
$userLoggedIn = $_SESSION['UserName'] ?? 'User';
$themeColor   = $_SESSION['Theme'] ?? 'primary'; // default primary kalau kosong
// Tentukan apakah dark/light (default: dark biar teks jelas)
$navbarMode = in_array($themeColor, ['warning','light','lime']) ? 'light' : 'dark';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>SUM Support App</title>
    <link rel="icon" href="/gg_app/dist/img/sumlogo.png" type="image/x-icon">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
    
    <!-- Bootstrap 5 CSS (diperlukan untuk dropdown) -->
    <link href="/gg_app/plugins/bootstrap-5.0.2-dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* CSS untuk memperbaiki dropdown */
        .dropdown-menu {
            z-index: 1030; /* Pastikan dropdown di atas konten lain */
        }
        
        /* Animasi untuk dropdown */
        .dropdown-menu {
            animation: fadeIn 0.2s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Perbaikan untuk navbar */
        .navbar-nav .dropdown-menu {
            position: absolute;
            right: 0;
            left: auto;
        }
        
        /* Pastikan dropdown tetap terbuka saat di-hover (opsional) */
        .dropdown:hover .dropdown-menu {
            display: block;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-<?= htmlspecialchars($navbarMode) ?> navbar-<?= htmlspecialchars($themeColor) ?>">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <span class="ml-3"></span>

        <!-- Dropdown User -->
        <ul class="navbar-nav ms-auto"> <!-- Menggunakan ms-auto (margin-start-auto) untuk Bootstrap 5 -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($userLoggedIn) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown"> <!-- Menggunakan dropdown-menu-end -->
                    <li><a class="dropdown-item" href="/gg_app/pages/profile.php"><i class="fas fa-user-circle"></i> Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/gg_app/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Scripts -->
    <!-- Bootstrap 5 JS (diperlukan untuk dropdown) -->
    <script src="/gg_app/plugins/bootstrap-5.0.2-dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery dan plugin lainnya -->
    <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
    <script src="/gg_app/plugins/js/jquery.dataTables.min.js"></script>
    <script src="/gg_app/plugins/js/dataTables.bootstrap5.min.js"></script>

    <!-- Script untuk memperbaiki dropdown -->
    <script>
        $(document).ready(function () {
            // Inisialisasi dropdown Bootstrap 5
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
            
            // Mencegah dropdown tertutup saat mengklik item di dalamnya
            $('.dropdown-menu').on('click', function(e) {
                e.stopPropagation();
            });
            
            // Menutup dropdown saat mengklik di luar
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                    $('.dropdown-toggle').attr('aria-expanded', 'false');
                }
            });
        });
    </script>
</body>
</html>