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
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($userLoggedIn) ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="/gg_app/pages/profile.php"><i class="fas fa-user-circle"></i> Profil</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="/gg_app/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
    <script src="/gg_app/plugins/js/jquery.dataTables.min.js"></script>
    <script src="/gg_app/plugins/js/dataTables.bootstrap5.min.js"></script>

    <!-- Pastikan Dropdown Berfungsi -->
    <script>
        $(document).ready(function () {
            $('.dropdown-toggle').dropdown();
        });
    </script>
</body>
</html>
