<?php
// Buffer seluruh output halaman sejak awal.
// Ini mencegah error "headers already sent" karena header()/session_start()
// bisa dipanggil kapan saja selama buffer aktif.
ob_start();

// Pastikan session aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_path' => '/gg_app/',
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true
    ]);
}

// Redirect jika belum login
if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
    if (!isset($_SESSION['login_redirect'])) {
        $_SESSION['error'] = "Silakan login terlebih dahulu!";
        $_SESSION['login_redirect'] = true;
    }
    header("Location: /gg_app/login.php");
    exit;
}

// Jika sudah login, pastikan pesan error login sebelumnya dibersihkan
if (isset($_SESSION['error']) && $_SESSION['error'] == "Silakan login terlebih dahulu!") {
    unset($_SESSION['error']);
}

$userId = $_SESSION['UserId'] ?? null;
$groupId = $_SESSION['GroupId'] ?? null;
$userName = $_SESSION['UserName'] ?? 'User';
$theme = $_SESSION['Theme'] ?? 'primary';
if (!empty($GLOBALS['ticketThemeOverride'])) {
    $theme = $GLOBALS['ticketThemeOverride'];
}
$theme = strtolower($theme);

if (!$userId || !$groupId) {
    header("Location: /gg_app/login.php");
    exit;
}

// Navbar mode (auto menyesuaikan warna terang/gelap)
$navbarMode = in_array($theme, ['warning', 'light', 'lime', 'white'])
    ? 'light'
    : 'dark';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUM Support App</title>
    <link rel="icon" href="/gg_app/dist/img/sumlogo.png" type="image/x-icon">

    <!-- ================================================
         Favicon & Homescreen Icon (bookmark ke desktop/HP)
    ================================================= -->
    <!-- Favicon standard -->
    <link rel="icon" type="image/png" sizes="32x32" href="/gg_app/dist/img/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/gg_app/dist/img/icons/icon-16x16.png">

    <!-- Apple Touch Icon (iOS — "Add to Home Screen") -->
    <link rel="apple-touch-icon" sizes="180x180" href="/gg_app/dist/img/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/gg_app/dist/img/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/gg_app/dist/img/icons/icon-120x120.png">

    <!-- Android / Chrome — Web App Manifest -->
    <link rel="manifest" href="/gg_app/manifest.json">

    <!-- Microsoft Tiles (Windows bookmark) -->
    <meta name="msapplication-TileImage" content="/gg_app/dist/img/icons/icon-144x144.png">
    <meta name="msapplication-TileColor" content="#007bff">

    <!-- Theme color browser chrome (Android Chrome address bar) -->
    <meta name="theme-color" content="#007bff">

    <!-- iOS webapp meta -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Support App">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <?php if (!empty($GLOBALS['includeTicketThemeCss'])): ?>
        <?php include __DIR__ . '/ticket_theme_assets.php'; ?>
    <?php endif; ?>

    <style>
        .navbar-nav .dropdown-menu {
            right: 0 !important;
            left: auto !important;
        }

        .dropdown-menu {
            animation: fadeIn .2s ease-in-out;
            z-index: 1050;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hilangkan garis hitam pada navbar AdminLTE */
        .main-header {
            border-bottom: none !important;
            box-shadow: none !important;
            /* jika ada bayangan tipis */
        }
    </style>
</head>

<?php
$bodyThemeAttr = '';
if (!empty($GLOBALS['includeTicketThemeCss'])) {
    $safeTheme = htmlspecialchars(strtolower($theme), ENT_QUOTES, 'UTF-8');
    $bodyThemeAttr = " data-theme=\"{$safeTheme}\"";
}
?>

<body class="hold-transition sidebar-mini layout-fixed" <?= $bodyThemeAttr ?>>
    <div class="wrapper">

        <!-- NAVBAR -->
        <nav
            class="main-header navbar navbar-expand navbar-<?= htmlspecialchars($navbarMode) ?> navbar-<?= htmlspecialchars($theme) ?>">

            <!-- Tombol Sidebar -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
            </ul>

            <!-- User Menu -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="fas fa-user-circle mr-1"></i>
                        <?= htmlspecialchars($userName) ?>
                    </a>

                    <div class="dropdown-menu dropdown-menu-right shadow">
                        <a href="/gg_app/pages/profile.php" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Profil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="/gg_app/logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- /NAVBAR -->