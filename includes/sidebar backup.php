<?php
// Gunakan session yang sama dengan header
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pengecekan yang konsisten dengan header
if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
    if (!isset($_SESSION['login_redirect'])) {
        $_SESSION['error'] = "Silakan login terlebih dahulu!";
        $_SESSION['login_redirect'] = true;
    }
    header("Location: /gg_app/login.php"); // Path absolut yang konsisten
    exit;
}

// Validasi session variables sebelum digunakan
$userId = $_SESSION['UserId'] ?? null;
$groupId = $_SESSION['GroupId'] ?? null;

if (!$userId || !$groupId) {
    header("Location: /gg_app/login.php");
    exit;
}

// Ambil theme (default: primary)
$themeColor = $_SESSION['Theme'] ?? 'primary';

    // Query to retrieve the menus
    $sql = "SELECT m.MenuId, m.MenuName, m.MenuUrl, m.MenuIcon, m.ParentMenuId
            FROM dbo.SMGroupTrustee AS ut
            JOIN dbo.SMMenu AS m ON ut.MenuId = m.MenuId
            WHERE ut.GroupId = ? ";
    $params = [$groupId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $menuItems = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $menuItems[] = $row;
        }
    }

    // Organize menu items into a hierarchical structure
    $menuTree = [];
    foreach ($menuItems as $menu) {
    if ($menu['ParentMenuId'] === null) {
        $menuTree[$menu['MenuId']] = $menu;
        $menuTree[$menu['MenuId']]['submenus'] = [];
    } else {
        // Cek dulu apakah parent-nya ada
        if (isset($menuTree[$menu['ParentMenuId']])) {
            $menuTree[$menu['ParentMenuId']]['submenus'][] = $menu;
        } else {
            // Buat parent kosong jika belum ada untuk mencegah error
            $menuTree[$menu['ParentMenuId']] = [
                'MenuId' => $menu['ParentMenuId'],
                'MenuName' => '(Unknown)',
                'MenuUrl' => '#',
                'MenuIcon' => 'fas fa-question',
                'submenus' => [$menu]
            ];
        }
    }
}
    ?>

    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>GG</title>
        <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
        <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    </head>
    <body class="hold-transition sidebar-mini">
        <div class="wrapper">
            <!-- Main Sidebar Container -->
            <aside class="main-sidebar sidebar-light-<?php echo htmlspecialchars($themeColor); ?> elevation-4">
    <!-- Brand Logo -->
    <a href="/gg_app/index.php" class="brand-link bg-<?php echo htmlspecialchars($themeColor); ?>">
        <img src="/gg_app/dist/img/sumlogo.png" alt="SUMLogo" class="brand-image img-circle elevation-3" style="opacity:.8">
        <span class="brand-text font-weight-bold">Support App</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-compact nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="/gg_app/index.php" class="nav-link">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Home</p>
                    </a>
                </li>

                <?php foreach ($menuTree as $menu): ?>
                    <?php if (empty($menu['submenus'])): ?>
                        <li class="nav-item">
                            <a href="<?= htmlspecialchars($menu['MenuUrl']) ?>" class="nav-link">
                                <i class="nav-icon <?= htmlspecialchars($menu['MenuIcon']) ?>"></i>
                                <p><?= htmlspecialchars($menu['MenuName']) ?></p>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon <?= htmlspecialchars($menu['MenuIcon']) ?>"></i>
                                <p><?= htmlspecialchars($menu['MenuName'])?> <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <?php foreach ($menu['submenus'] as $subMenu): ?>
                                    <li class="nav-item">
                                        <a href="<?= htmlspecialchars($subMenu['MenuUrl']) ?>" class="nav-link">
                                            <i class="nav-icon <?= htmlspecialchars($subMenu['MenuIcon']) ?>"></i>
                                            <p><?= htmlspecialchars($subMenu['MenuName']) ?></p>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</aside>

        </div>

        <!-- Scripts -->
        <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
        <script src="/gg_app/plugins/js/bootstrap.bundle.min.js"></script>
        <script src="/gg_app/plugins/js/adminlte.min.js"></script>
        <script>
            $(document).ready(function () {
                $('.nav-link').each(function () {
                    if (this.href === window.location.href) {
                        $(this).addClass('active');
                        $(this).closest('.has-treeview').addClass('menu-open');
                    }
                });
            });
        </script>
    </body>
    </html>