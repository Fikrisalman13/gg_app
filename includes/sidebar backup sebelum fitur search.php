<?php
ob_start();

/*********************************************************
 * SIDEBAR DINAMIS BERDASARKAN ROLE (GroupId)
 *********************************************************/

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['UserId'])) {
    header("Location: /gg_app/login.php");
    exit;
}

/**
 * Helper to render Offline/Local Icon
 */
function renderSidebarIcon($iconName, $size = "20px") {
    $iconName = trim($iconName);
    if (empty($iconName)) return '<i class="nav-icon fas fa-question"></i>';
    
    // Check if it's an Iconify format (offline SVG)
    if (strpos($iconName, ':') !== false) {
        $safeIcon = str_replace(':', '_', $iconName);
        $localPath = __DIR__ . "/icons/$safeIcon.svg";
        
        if (file_exists($localPath)) {
            $svgContent = file_get_contents($localPath);
            // Ensure SVG scales to its container so it won't shift baseline
            $svgContent = str_replace('<svg', '<svg style="width:100%;height:100%;display:block;"', $svgContent);

            // Use a fixed square container and center the SVG inside it
            return '<span class="nav-icon sidebar-svg-icon" style="display:inline-flex;align-items:center;justify-content:center;flex:0 0 1.35rem;width:1.35rem;height:1.35rem;vertical-align:middle;fill:currentColor;">' . $svgContent . '</span>';
        }
        // Fallback for icons that haven't been downloaded yet
        return '<i class="nav-icon fas fa-cloud-download-alt text-warning" title="Belum didownload"></i>';
    }
    
    // Default FontAwesome
    return '<i class="nav-icon ' . htmlspecialchars($iconName) . '"></i>';
}

// Variabel session penting
$groupId    = $_SESSION['GroupId'];
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Query menu berdasarkan role
$sql = "
    SELECT 
        m.MenuId,
        m.MenuName,
        m.MenuUrl,
        m.MenuIcon,
        m.ParentMenuId
    FROM dbo.SMGroupTrustee ut
    JOIN dbo.SMMenu m ON ut.MenuId = m.MenuId
    WHERE ut.GroupId = ?
    ORDER BY m.ParentMenuId, m.MenuId
";
$stmt = sqlsrv_query($conn, $sql, [$groupId]);

// Build menu tree
$menuTree = [];
$menuWeights = [];
$configPath = __DIR__ . '/../config/menu_order.json';
if (file_exists($configPath)) {
    $menuWeights = json_decode(file_get_contents($configPath), true) ?? [];
}

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

        $row['MenuUrl']  = rtrim(trim($row['MenuUrl']), '/');
        $row['MenuIcon'] = trim($row['MenuIcon']);
        $row['ParentMenuId'] = $row['ParentMenuId'] ?? null;
        $row['SortOrder'] = $menuWeights[$row['MenuId']] ?? 999999;

        if ($row['ParentMenuId'] === null) {
            // Menu utama
            $menuTree[$row['MenuId']] = $row;
            $menuTree[$row['MenuId']]['submenus'] = [];
        } else {
            // Submenu
            if (!isset($menuTree[$row['ParentMenuId']])) {
                // Parent menu tidak ada atau tidak diakses user
                $menuTree[$row['ParentMenuId']] = [
                    'MenuId'     => $row['ParentMenuId'],
                    'MenuName'   => '(Unknown)',
                    'MenuUrl'    => '#',
                    'MenuIcon'   => 'fas fa-question',
                    'SortOrder'  => $menuWeights[$row['ParentMenuId']] ?? 999999,
                    'submenus'   => []
                ];
            }

            $menuTree[$row['ParentMenuId']]['submenus'][] = $row;
        }
    }
}

// Sort Parent Menus
uasort($menuTree, function($a, $b) {
    if ($a['SortOrder'] == $b['SortOrder']) {
        return $a['MenuId'] <=> $b['MenuId'];
    }
    return $a['SortOrder'] <=> $b['SortOrder'];
});

// Sort Submenus
foreach ($menuTree as $id => $menu) {
    if (!empty($menu['submenus'])) {
        usort($menuTree[$id]['submenus'], function($a, $b) {
            if ($a['SortOrder'] == $b['SortOrder']) {
                return $a['MenuId'] <=> $b['MenuId'];
            }
            return $a['SortOrder'] <=> $b['SortOrder'];
        });
    }
}

/* ---------------------------------------------------------
   DETEKSI ACTIVE MENU – menggunakan FULL PATH
--------------------------------------------------------- */
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentPath = rtrim($currentPath, '/');   // Normalisasi
?>

<!-- ======================================================
     RENDER ADMINLTE SIDEBAR
====================================================== -->
<style>
/* Normalize sidebar icons (including downloaded SVGs) so they align when sidebar is collapsed */
.main-sidebar .nav-icon.sidebar-svg-icon {
    width: 1.35rem !important;
    height: 1.35rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.main-sidebar .nav-link .nav-icon svg {
    width: 100% !important;
    height: 100% !important;
    display: block !important;
}
.main-sidebar .nav-link {
    display: flex !important;
    align-items: center !important;
}
.main-sidebar .nav-link .nav-icon {
    flex: 0 0 1.35rem !important;
    width: 1.35rem !important;
    height: 1.35rem !important;
    text-align: center !important;
}
.main-sidebar .nav-link p {
    margin: 0 0 0 .5rem !important;
    flex: 1 1 auto !important;
}
</style>
<aside class="main-sidebar sidebar-light-<?= htmlspecialchars($themeColor) ?> elevation-4">

    <!-- Logo -->
    <a href="/gg_app/index.php" class="brand-link bg-<?= htmlspecialchars($themeColor) ?>">
        <img src="/gg_app/dist/img/sumlogo.png"
             class="brand-image img-circle elevation-3"
             style="opacity:.8">
        <span class="brand-text font-weight-bold">Support App</span>
    </a>

    <div class="sidebar">

        <nav class="mt-2">

            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
                data-widget="treeview" role="menu">

                <!-- HOME -->
                <li class="nav-item">
                    <a href="/gg_app/index.php"
                       class="nav-link <?= ($currentPath === '/gg_app/index.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Beranda</p>
                    </a>
                </li>

                <!-- ============================
                     MENU DINAMIS
                =============================== -->
                <?php foreach ($menuTree as $menu): ?>

                    <?php
                    // Normalisasi URL
                    $menuUrl = rtrim($menu['MenuUrl'], '/');

                    // Cek menu aktif
                    $isMenuActive = ($menuUrl === $currentPath);

                    // Cek submenu aktif
                    $isSubActive = false;

                    if (!empty($menu['submenus'])) {
                        foreach ($menu['submenus'] as $sub) {
                            $subUrl = rtrim($sub['MenuUrl'], '/');

                            if ($subUrl === $currentPath) {
                                $isSubActive = true;
                                break;
                            }
                        }
                    }
                    ?>

                    <?php if (empty($menu['submenus'])): ?>

                        <!-- MENU TANPA SUBMENU -->
                        <li class="nav-item">
                            <a href="<?= htmlspecialchars($menuUrl) ?>"
                               class="nav-link <?= ($isMenuActive ? 'active bg-'.$themeColor : '') ?>">
                                <?= renderSidebarIcon($menu['MenuIcon']) ?>
                                <p><?= htmlspecialchars($menu['MenuName']) ?></p>
                            </a>
                        </li>

                    <?php else: ?>

                        <!-- MENU DENGAN SUBMENU -->
                         <li class="nav-item <?= ($isSubActive ? 'menu-open' : '') ?>">
                            <a href="#" class="nav-link <?= ($isSubActive ? 'active bg-'.$themeColor : '') ?>">
                                <?= renderSidebarIcon($menu['MenuIcon']) ?>
                                <p>
                                    <?= htmlspecialchars($menu['MenuName']) ?>
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>

                            <ul class="nav nav-treeview">
                                <?php foreach ($menu['submenus'] as $sub): ?>
                                    <?php 
                                        $subUrl = rtrim($sub['MenuUrl'], '/');
                                        $isThisSubActive = ($subUrl === $currentPath);
                                    ?>
                                    <li class="nav-item">
                                        <a href="<?= htmlspecialchars($subUrl) ?>"
                                           class="nav-link <?= ($isThisSubActive ? 'active bg-'.$themeColor : '') ?>">
                                            <?= renderSidebarIcon($sub['MenuIcon'], '16px') ?>
                                            <p><?= htmlspecialchars($sub['MenuName']) ?></p>
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
