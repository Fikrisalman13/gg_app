<?php

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
function renderSidebarIcon($iconName, $size = "20px")
{
    $iconName = trim($iconName);
    if (empty($iconName))
        return '<i class="nav-icon fas fa-question"></i>';

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
$groupId = $_SESSION['GroupId'];
$themeColor = $_SESSION['Theme'] ?? 'primary';

$sidebarSearchEnabled = true;
if ((int) $groupId === 1) {
    $prefsPath = __DIR__ . '/../config/sidebar_prefs.json';
    if (file_exists($prefsPath)) {
        $prefsData = json_decode(file_get_contents($prefsPath), true);
        $userIdKey = (string) ($_SESSION['UserId'] ?? '');
        if (
            is_array($prefsData)
            && isset($prefsData['sidebar_search_enabled'])
            && is_array($prefsData['sidebar_search_enabled'])
            && $userIdKey !== ''
            && array_key_exists($userIdKey, $prefsData['sidebar_search_enabled'])
        ) {
            $sidebarSearchEnabled = ((int) $prefsData['sidebar_search_enabled'][$userIdKey] === 1);
        }
    }
}

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

        $row['MenuUrl'] = rtrim(trim($row['MenuUrl']), '/');
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
                    'MenuId' => $row['ParentMenuId'],
                    'MenuName' => '(Unknown)',
                    'MenuUrl' => '#',
                    'MenuIcon' => 'fas fa-question',
                    'SortOrder' => $menuWeights[$row['ParentMenuId']] ?? 999999,
                    'submenus' => []
                ];
            }

            $menuTree[$row['ParentMenuId']]['submenus'][] = $row;
        }
    }
}

// Sort Parent Menus
uasort($menuTree, function ($a, $b) {
    if ($a['SortOrder'] == $b['SortOrder']) {
        return $a['MenuId'] <=> $b['MenuId'];
    }
    return $a['SortOrder'] <=> $b['SortOrder'];
});

// Sort Submenus
foreach ($menuTree as $id => $menu) {
    if (!empty($menu['submenus'])) {
        usort($menuTree[$id]['submenus'], function ($a, $b) {
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

    .main-sidebar .sidebar .sidebar-menu-search {
        padding: .5rem .5rem 0 .5rem;
    }

    /* Keep the searchbar visible while scrolling the sidebar */
    .main-sidebar .sidebar .sidebar-menu-search {
        position: sticky;
        top: 0;
        z-index: 1020;
        background: inherit;
        backdrop-filter: blur(4px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .main-sidebar .sidebar .sidebar-menu-search .btn {
        padding: .25rem .4rem;
    }

    .main-sidebar .sidebar .sidebar-menu-search .input-group {
        flex-wrap: nowrap;
    }

    .main-sidebar .sidebar .sidebar-menu-search .input-group>.form-control {
        width: 1%;
        flex: 1 1 auto;
    }

    .main-sidebar .sidebar .sidebar-menu-search .input-group-append {
        flex: 0 0 auto;
    }

    /* ============================================================
       FIX: Menu sidebar terpotong di HP (tidak sampai paling bawah)

       Root cause: AdminLTE menghitung tinggi sidebar dengan 100vh.
       Di mobile browser, 100vh = tinggi layar penuh termasuk
       toolbar browser (address bar + navigasi bawah), sehingga
       bagian bawah sidebar tersembunyi di balik toolbar tsb.
       Mode fullscreen berfungsi karena toolbar hilang.

       Solusi: gunakan 100dvh (Dynamic Viewport Height) yang
       otomatis menyesuaikan dengan area VISIBLE saat toolbar
       muncul/hilang. Tambah padding-bottom untuk safe area
       (iPhone home indicator, notch, dll).
    ============================================================ */

    .main-sidebar > .sidebar {
        /* Fallback untuk browser lama */
        height: calc(100vh - 3.5rem - 1px);
        /* 100dvh = visible viewport height (menyesuaikan toolbar browser) */
        height: calc(100dvh - 3.5rem - 1px);
        overflow-y: auto !important;
        overflow-x: hidden !important;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-y: contain;
        box-sizing: border-box;
    }

    /* Padding ekstra hanya di touch device (HP/tablet) — beri ruang agar
       menu terakhir tidak tertutup toolbar browser / safe-area iPhone.
       Gunakan pointer:coarse bukan max-width agar tidak konflik dengan
       breakpoint AdminLTE (992px) yang mengubah layout sidebar. */
    @media (hover: none) and (pointer: coarse) {
        .main-sidebar > .sidebar {
            padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 6rem);
        }
    }
</style>
<aside class="main-sidebar sidebar-light-<?= htmlspecialchars($themeColor) ?> elevation-4">

    <!-- Logo -->
    <a href="/gg_app/index.php" class="brand-link bg-<?= htmlspecialchars($themeColor) ?>">
        <img src="/gg_app/dist/img/sumlogo.png" class="brand-image img-circle elevation-3" style="opacity:.8">
        <span class="brand-text font-weight-bold">Support App</span>
    </a>

    <div class="sidebar">

        <?php if ((int) $groupId === 1): ?>
            <div id="sidebarMenuSearchWrap" class="sidebar-menu-search"
                style="<?= $sidebarSearchEnabled ? '' : 'display:none;' ?>">
                <div class="input-group input-group-sm">
                    <input id="sidebarMenuSearch" type="text" class="form-control" placeholder="Cari menu..."
                        autocomplete="off">
                    <div class="input-group-append">
                        <button id="sidebarMenuSearchClose" type="button" class="btn btn-outline-secondary"
                            aria-label="Tutup pencarian">&times;</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <nav class="mt-2">

            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu">

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
                                class="nav-link <?= ($isMenuActive ? 'active bg-' . $themeColor : '') ?>">
                                <?= renderSidebarIcon($menu['MenuIcon']) ?>
                                <p><?= htmlspecialchars($menu['MenuName']) ?></p>
                            </a>
                        </li>

                    <?php else: ?>

                        <!-- MENU DENGAN SUBMENU -->
                        <li class="nav-item <?= ($isSubActive ? 'menu-open' : '') ?>">
                            <a href="#" class="nav-link <?= ($isSubActive ? 'active bg-' . $themeColor : '') ?>">
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
                                            class="nav-link <?= ($isThisSubActive ? 'active bg-' . $themeColor : '') ?>">
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

<?php if ((int) $groupId === 1): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('sidebarMenuSearch');
            if (!input) return;

            var wrap = document.getElementById('sidebarMenuSearchWrap');
            var closeBtn = document.getElementById('sidebarMenuSearchClose');

            var sidebar = input.closest('.main-sidebar');
            if (!sidebar) return;

            var menuRoot = sidebar.querySelector('ul.nav.nav-sidebar');
            if (!menuRoot) return;

            var topItems = Array.prototype.slice.call(menuRoot.children).filter(function (el) {
                return el && el.classList && el.classList.contains('nav-item');
            });

            topItems.forEach(function (li) {
                if (!li.dataset) return;
                if (li.dataset.originalMenuOpen == null) {
                    li.dataset.originalMenuOpen = li.classList.contains('menu-open') ? '1' : '0';
                }
            });

            function getText(el) {
                if (!el) return '';
                return (el.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            }

            function setVisible(el, visible) {
                if (!el) return;
                el.style.display = visible ? '' : 'none';
            }

            function restoreState() {
                topItems.forEach(function (li) {
                    setVisible(li, true);

                    var subMenu = null;
                    for (var i = 0; i < li.children.length; i++) {
                        var ch = li.children[i];
                        if (ch && ch.tagName === 'UL' && ch.classList && ch.classList.contains('nav-treeview')) {
                            subMenu = ch;
                            break;
                        }
                    }
                    if (subMenu) {
                        Array.prototype.slice.call(subMenu.children).forEach(function (subLi) {
                            setVisible(subLi, true);
                        });
                    }

                    var originalOpen = li.dataset ? li.dataset.originalMenuOpen : '0';
                    if (originalOpen === '1') li.classList.add('menu-open');
                    else li.classList.remove('menu-open');
                });
            }

            function applyFilter(rawQuery) {
                var q = (rawQuery || '').trim().toLowerCase();
                if (!q) {
                    restoreState();
                    return;
                }

                topItems.forEach(function (li) {
                    var topLink = null;
                    for (var i = 0; i < li.children.length; i++) {
                        var ch = li.children[i];
                        if (ch && ch.tagName === 'A' && ch.classList && ch.classList.contains('nav-link')) {
                            topLink = ch;
                            break;
                        }
                    }
                    var topText = getText(topLink);
                    var subMenu = null;
                    for (var j = 0; j < li.children.length; j++) {
                        var ch2 = li.children[j];
                        if (ch2 && ch2.tagName === 'UL' && ch2.classList && ch2.classList.contains('nav-treeview')) {
                            subMenu = ch2;
                            break;
                        }
                    }

                    if (!subMenu) {
                        setVisible(li, topText.indexOf(q) !== -1);
                        return;
                    }

                    var subItems = Array.prototype.slice.call(subMenu.children).filter(function (el) {
                        return el && el.classList && el.classList.contains('nav-item');
                    });

                    var anySubMatch = false;
                    subItems.forEach(function (subLi) {
                        var subLink = null;
                        for (var i = 0; i < subLi.children.length; i++) {
                            var ch = subLi.children[i];
                            if (ch && ch.tagName === 'A' && ch.classList && ch.classList.contains('nav-link')) {
                                subLink = ch;
                                break;
                            }
                        }
                        var subText = getText(subLink);
                        var match = subText.indexOf(q) !== -1;
                        if (match) anySubMatch = true;
                        setVisible(subLi, match);
                    });

                    var topMatch = topText.indexOf(q) !== -1;
                    var showParent = topMatch || anySubMatch;
                    setVisible(li, showParent);

                    if (showParent) {
                        li.classList.add('menu-open');
                        if (topMatch) {
                            subItems.forEach(function (subLi) {
                                setVisible(subLi, true);
                            });
                        }
                    } else {
                        li.classList.remove('menu-open');
                    }
                });
            }

            input.addEventListener('input', function (e) {
                applyFilter(e.target.value);
            });

            function savePreference(enabled, cb) {
                var body = 'enabled=' + encodeURIComponent(enabled ? '1' : '0');
                fetch('/gg_app/set_sidebar_pref.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || data.ok !== true) throw new Error('save failed');
                        if (enabled === false && data.show_hint === true) {
                            var msg = 'Searchbar dinonaktifkan. Untuk mengaktifkan kembali tekan Shift+F.';

                            var container = document.getElementById('sidebarSearchHintToastContainer');
                            if (!container) {
                                container = document.createElement('div');
                                container.id = 'sidebarSearchHintToastContainer';
                                container.setAttribute('aria-live', 'polite');
                                container.setAttribute('aria-atomic', 'true');
                                container.style.position = 'fixed';
                                container.style.left = '50%';
                                container.style.top = '70px';
                                container.style.transform = 'translateX(-50%)';
                                container.style.zIndex = '1060';
                                container.style.maxWidth = '520px';
                                container.style.width = 'calc(100% - 24px)';
                                container.style.pointerEvents = 'none';
                                document.body.appendChild(container);
                            }

                            var toast = document.createElement('div');
                            toast.className = 'toast bg-info';
                            toast.setAttribute('role', 'alert');
                            toast.setAttribute('aria-live', 'assertive');
                            toast.setAttribute('aria-atomic', 'true');
                            toast.setAttribute('data-delay', '4500');
                            toast.style.pointerEvents = 'auto';

                            toast.innerHTML = '' +
                                '<div class="toast-header bg-info">' +
                                '<strong class="mr-auto text-white">Info</strong>' +
                                '<button type="button" class="ml-2 mb-1 close text-white" data-dismiss="toast" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                                '</button>' +
                                '</div>' +
                                '<div class="toast-body text-white">' + msg + '</div>';

                            container.appendChild(toast);

                            if (window.jQuery && jQuery.fn && typeof jQuery.fn.toast === 'function') {
                                jQuery(toast).toast('show');
                                jQuery(toast).on('hidden.bs.toast', function () {
                                    try { toast.parentNode && toast.parentNode.removeChild(toast); } catch (e) { }
                                });
                            } else {
                                toast.style.opacity = '1';
                                setTimeout(function () {
                                    try { toast.parentNode && toast.parentNode.removeChild(toast); } catch (e) { }
                                }, 4700);
                            }
                        }
                        if (typeof cb === 'function') cb();
                    })
                    .catch(function () {
                        if (typeof cb === 'function') cb();
                    });
            }

            function setHidden(hidden, persist) {
                if (!wrap) return;
                if (hidden) {
                    input.value = '';
                    applyFilter('');
                    wrap.style.display = 'none';
                    if (persist) savePreference(false);
                } else {
                    wrap.style.display = '';
                    if (persist) savePreference(true);
                    input.focus();
                }
            }

            function isHidden() {
                if (!wrap) return false;
                return wrap.style.display === 'none';
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    setHidden(true, true);
                });
            }

            document.addEventListener('keydown', function (e) {
                var key = (e.key || '').toLowerCase();
                if (!(e.shiftKey && key === 'f')) return;

                var t = e.target;
                var tag = t && t.tagName ? t.tagName.toLowerCase() : '';
                if (tag === 'input' || tag === 'textarea' || tag === 'select' || (t && t.isContentEditable)) return;

                e.preventDefault();
                setHidden(!isHidden(), true);
            });
        });
    </script>
<?php endif; ?>