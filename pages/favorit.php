<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserId'])) {
    header('Location: /gg_app/login.php');
    exit;
}

include '../koneksi.php';

$userId = (int) $_SESSION['UserId'];
$groupId = (int) ($_SESSION['GroupId'] ?? 0);
$themeColor = $_SESSION['Theme'] ?? 'primary';
$themeTextColor = in_array($themeColor, ['warning', 'light', 'lime', 'white'], true) ? 'dark' : 'white';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function ok($message) {
    jsonResponse(['ok' => true, 'message' => $message]);
}

function fail($message) {
    jsonResponse(['ok' => false, 'message' => $message]);
}

function validMenuUrl($url) {
    $url = trim((string) $url);
    return $url !== '' && $url !== '#' && $url !== '/';
}

function nullableInt($value) {
    if ($value === '' || $value === null) return null;
    return (int) $value;
}

function menuLabel($row) {
    return !empty($row['ParentName']) ? $row['ParentName'] . ' / ' . $row['MenuName'] : $row['MenuName'];
}

function validCategoryIcon($icon) {
    return preg_match('/^(?:[a-z0-9-]+:[a-z0-9-]+|(?:fa[brsld]?)\s+fa-[a-z0-9-]+)$/i', $icon) === 1;
}

function renderFavoriteIcon($icon, $fallback = 'fas fa-star') {
    $icon = trim((string) $icon);
    if ($icon === '') $icon = $fallback;

    if (strpos($icon, ':') !== false) {
        $safeIcon = preg_replace('/[^a-z0-9_-]/i', '_', $icon);
        $localPath = __DIR__ . '/../includes/icons/' . $safeIcon . '.svg';
        if (file_exists($localPath)) {
            $svg = file_get_contents($localPath);
            return '<span class="fav-svg-icon">' . str_replace('<svg', '<svg style="width:100%;height:100%;display:block;"', $svg) . '</span>';
        }
    }

    return '<i class="' . h(strpos($icon, ':') !== false ? $fallback : $icon) . '"></i>';
}

function affected($stmt) {
    if (!$stmt) return 0;
    $rows = sqlsrv_rows_affected($stmt);
    return $rows === false ? 0 : (int) $rows;
}

function userCanAccessMenu($conn, $groupId, $menuId) {
    $sql = "SELECT TOP 1 m.MenuUrl
            FROM dbo.SMGroupTrustee gt
            JOIN dbo.SMMenu m ON gt.MenuId = m.MenuId
            WHERE gt.GroupId = ?
              AND m.MenuId = ?
              AND ISNULL(gt.CanView, 0) = 1";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    if (!$stmt) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row && validMenuUrl($row['MenuUrl'] ?? '');
}

function categoryBelongsToUser($conn, $userId, $categoryId) {
    if ($categoryId === null) return true;
    $stmt = sqlsrv_query($conn, "SELECT 1 FROM dbo.SMUserFavoriteCategory WHERE CategoryId = ? AND UserId = ?", [$categoryId, $userId]);
    return $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function nextFavoriteSort($conn, $userId, $categoryId) {
    $sql = "SELECT ISNULL(MAX(SortOrder), 0) + 1 AS NextSort
            FROM dbo.SMUserFavoriteMenu
            WHERE UserId = ?
              AND ((CategoryId = ?) OR (CategoryId IS NULL AND ? IS NULL))";
    $stmt = sqlsrv_query($conn, $sql, [$userId, $categoryId, $categoryId]);
    if (!$stmt) return 1;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int) ($row['NextSort'] ?? 1);
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'download_icon') {
        $icon = trim($_POST['icon'] ?? '');
        if (strpos($icon, ':') === false || !validCategoryIcon($icon)) fail('Icon tidak valid.');

        $safeIcon = preg_replace('/[^a-z0-9_-]/i', '_', $icon);
        $iconDir = __DIR__ . '/../includes/icons';
        $iconPath = $iconDir . '/' . $safeIcon . '.svg';
        if (file_exists($iconPath)) ok('Icon siap digunakan.');
        if (!is_dir($iconDir) && !mkdir($iconDir, 0777, true)) fail('Folder cache icon tidak bisa dibuat.');

        [$prefix, $name] = explode(':', $icon, 2);
        $svg = @file_get_contents('https://api.iconify.design/' . rawurlencode($prefix) . '/' . rawurlencode($name) . '.svg');
        if (!$svg || file_put_contents($iconPath, $svg) === false) fail('Gagal menyimpan icon.');
        ok('Icon siap digunakan.');
    }

    if ($action === 'save_open_mode') {
        $openInNewTab = ($_POST['open_in_new_tab'] ?? '0') === '1' ? 1 : 0;
        $sql = "UPDATE dbo.SMUserFavoriteSetting SET OpenInNewTab = ?, UpdatedDate = GETDATE() WHERE UserId = ?;
                IF @@ROWCOUNT = 0 INSERT INTO dbo.SMUserFavoriteSetting (UserId, OpenInNewTab, UpdatedDate) VALUES (?, ?, GETDATE());";
        $stmt = sqlsrv_query($conn, $sql, [$openInNewTab, $userId, $userId, $openInNewTab]);
        $stmt ? ok($openInNewTab ? 'Semua Favorit akan dibuka di tab baru.' : 'Semua Favorit akan dibuka di tab saat ini.') : fail('Gagal menyimpan pengaturan.');
    }

    if ($action === 'save_layout_mode') {
        $layoutMode = $_POST['layout_mode'] ?? '';
        if (!in_array($layoutMode, ['card', 'compact'], true)) fail('Tampilan tidak valid.');
        $sql = "UPDATE dbo.SMUserFavoriteSetting SET LayoutMode = ?, UpdatedDate = GETDATE() WHERE UserId = ?;
                IF @@ROWCOUNT = 0 INSERT INTO dbo.SMUserFavoriteSetting (UserId, OpenInNewTab, LayoutMode, UpdatedDate) VALUES (?, 0, ?, GETDATE());";
        $stmt = sqlsrv_query($conn, $sql, [$layoutMode, $userId, $userId, $layoutMode]);
        $stmt ? ok($layoutMode === 'compact' ? 'Tampilan List disimpan.' : 'Tampilan Card disimpan.') : fail('Gagal menyimpan tampilan.');
    }

    if ($action === 'add_category') {
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fas fa-folder');
        if ($name === '' || !validCategoryIcon($icon)) fail('Data kategori atau icon tidak valid.');

        $stmtMax = sqlsrv_query($conn, "SELECT ISNULL(MAX(SortOrder), 0) + 1 AS NextSort FROM dbo.SMUserFavoriteCategory WHERE UserId = ?", [$userId]);
        $next = ($stmtMax && ($row = sqlsrv_fetch_array($stmtMax, SQLSRV_FETCH_ASSOC))) ? (int) $row['NextSort'] : 1;
        $stmt = sqlsrv_query($conn, "INSERT INTO dbo.SMUserFavoriteCategory (UserId, CategoryName, Icon, SortOrder) VALUES (?, ?, ?, ?)", [$userId, $name, $icon, $next]);
        $stmt ? ok('Kategori ditambahkan.') : fail('Gagal menambah kategori.');
    }

    if ($action === 'rename_category') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fas fa-folder');
        if ($categoryId <= 0 || $name === '' || !validCategoryIcon($icon)) fail('Data kategori atau icon tidak valid.');

        $stmt = sqlsrv_query($conn, "UPDATE dbo.SMUserFavoriteCategory SET CategoryName = ?, Icon = ?, UpdatedDate = GETDATE() WHERE CategoryId = ? AND UserId = ?", [$name, $icon, $categoryId, $userId]);
        affected($stmt) > 0 ? ok('Kategori diperbarui.') : fail('Kategori tidak ditemukan atau tidak berubah.');
    }

    if ($action === 'delete_category') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) fail('Kategori tidak valid.');

        if (!sqlsrv_begin_transaction($conn)) fail('Gagal mulai transaksi.');
        $stmtMove = sqlsrv_query($conn, "UPDATE dbo.SMUserFavoriteMenu SET CategoryId = NULL, UpdatedDate = GETDATE() WHERE CategoryId = ? AND UserId = ?", [$categoryId, $userId]);
        $stmtDelete = sqlsrv_query($conn, "DELETE FROM dbo.SMUserFavoriteCategory WHERE CategoryId = ? AND UserId = ?", [$categoryId, $userId]);

        if ($stmtMove && affected($stmtDelete) > 0) {
            sqlsrv_commit($conn);
            ok('Kategori dihapus. Menu dipindah ke Tanpa Kategori.');
        }

        sqlsrv_rollback($conn);
        fail('Gagal hapus kategori.');
    }

    if ($action === 'add_favorite') {
        $menuIds = array_values(array_unique(array_filter(array_map('intval', $_POST['menu_ids'] ?? []))));
        $categoryId = nullableInt($_POST['category_id'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        if (!$menuIds) fail('Pilih minimal satu menu.');
        if (!categoryBelongsToUser($conn, $userId, $categoryId)) fail('Kategori tidak valid.');
        if ($icon !== '' && !validCategoryIcon($icon)) fail('Icon tidak valid.');
        foreach ($menuIds as $menuId) {
            if (!userCanAccessMenu($conn, $groupId, $menuId)) fail('Salah satu menu tidak valid atau tidak punya akses.');
        }

        if (!sqlsrv_begin_transaction($conn)) fail('Gagal mulai transaksi.');
        $next = nextFavoriteSort($conn, $userId, $categoryId);
        foreach ($menuIds as $offset => $menuId) {
            $stmt = sqlsrv_query($conn, "INSERT INTO dbo.SMUserFavoriteMenu (UserId, MenuId, CategoryId, Icon, SortOrder) VALUES (?, ?, ?, ?, ?)", [$userId, $menuId, $categoryId, $icon === '' ? null : $icon, $next + $offset]);
            if (!$stmt) {
                sqlsrv_rollback($conn);
                fail('Menu sudah ada di Favorit atau gagal disimpan.');
            }
        }
        sqlsrv_commit($conn);
        ok(count($menuIds) . ' menu ditambahkan ke Favorit.');
    }

    if ($action === 'delete_favorite') {
        $favoriteId = (int) ($_POST['favorite_id'] ?? 0);
        if ($favoriteId <= 0) fail('Favorit tidak valid.');

        $stmt = sqlsrv_query($conn, "DELETE FROM dbo.SMUserFavoriteMenu WHERE FavoriteId = ? AND UserId = ?", [$favoriteId, $userId]);
        affected($stmt) > 0 ? ok('Favorit dihapus.') : fail('Favorit tidak ditemukan.');
    }

    if ($action === 'update_favorite_icon') {
        $favoriteId = (int) ($_POST['favorite_id'] ?? 0);
        $icon = trim($_POST['icon'] ?? '');
        if ($favoriteId <= 0 || ($icon !== '' && !validCategoryIcon($icon))) fail('Data favorit atau icon tidak valid.');

        $stmt = sqlsrv_query($conn, "UPDATE dbo.SMUserFavoriteMenu SET Icon = ?, UpdatedDate = GETDATE() WHERE FavoriteId = ? AND UserId = ?", [$icon === '' ? null : $icon, $favoriteId, $userId]);
        affected($stmt) > 0 ? ok($icon === '' ? 'Icon default menu digunakan.' : 'Icon favorit diperbarui.') : fail('Favorit tidak ditemukan atau tidak berubah.');
    }

    if ($action === 'move_favorite') {
        $favoriteId = (int) ($_POST['favorite_id'] ?? 0);
        $categoryId = nullableInt($_POST['category_id'] ?? '');
        if ($favoriteId <= 0) fail('Favorit tidak valid.');
        if (!categoryBelongsToUser($conn, $userId, $categoryId)) fail('Kategori tidak valid.');

        $next = nextFavoriteSort($conn, $userId, $categoryId);
        $stmt = sqlsrv_query($conn, "UPDATE dbo.SMUserFavoriteMenu SET CategoryId = ?, SortOrder = ?, UpdatedDate = GETDATE() WHERE FavoriteId = ? AND UserId = ?", [$categoryId, $next, $favoriteId, $userId]);
        affected($stmt) > 0 ? ok('Favorit dipindahkan.') : fail('Favorit tidak ditemukan atau tidak berubah.');
    }

    fail('Action tidak dikenal.');
}

$openInNewTab = false;
$layoutMode = 'card';
$stmtSetting = sqlsrv_query($conn, "SELECT OpenInNewTab, LayoutMode FROM dbo.SMUserFavoriteSetting WHERE UserId = ?", [$userId]);
if ($stmtSetting && ($setting = sqlsrv_fetch_array($stmtSetting, SQLSRV_FETCH_ASSOC))) {
    $openInNewTab = (bool) $setting['OpenInNewTab'];
    $layoutMode = $setting['LayoutMode'] === 'compact' ? 'compact' : 'card';
}

$categories = [];
$stmtCat = sqlsrv_query($conn, "SELECT CategoryId, CategoryName, Icon FROM dbo.SMUserFavoriteCategory WHERE UserId = ? ORDER BY SortOrder, CategoryId", [$userId]);
if ($stmtCat) {
    while ($row = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
        $row['items'] = [];
        $categories[(int) $row['CategoryId']] = $row;
    }
}

$uncategorized = ['CategoryId' => null, 'CategoryName' => 'Tanpa Kategori', 'items' => []];
$sqlFav = "SELECT f.FavoriteId, f.CategoryId, f.SortOrder, f.Icon AS FavoriteIcon, m.MenuId, m.MenuName, m.MenuUrl, m.MenuIcon, p.MenuName AS ParentName
           FROM dbo.SMUserFavoriteMenu f
           JOIN dbo.SMMenu m ON f.MenuId = m.MenuId
           LEFT JOIN dbo.SMMenu p ON m.ParentMenuId = p.MenuId
           JOIN dbo.SMGroupTrustee gt ON gt.MenuId = m.MenuId AND gt.GroupId = ? AND ISNULL(gt.CanView, 0) = 1
           WHERE f.UserId = ?
           ORDER BY ISNULL(f.CategoryId, 0), f.SortOrder, f.FavoriteId";
$stmtFav = sqlsrv_query($conn, $sqlFav, [$groupId, $userId]);
if ($stmtFav) {
    while ($row = sqlsrv_fetch_array($stmtFav, SQLSRV_FETCH_ASSOC)) {
        $cid = $row['CategoryId'] === null ? null : (int) $row['CategoryId'];
        if ($cid !== null && isset($categories[$cid])) $categories[$cid]['items'][] = $row;
        else $uncategorized['items'][] = $row;
    }
}

$availableMenus = [];
$sqlMenus = "SELECT m.MenuId, m.MenuName, m.MenuUrl, m.MenuIcon, p.MenuName AS ParentName, p.MenuIcon AS ParentIcon
             FROM dbo.SMGroupTrustee gt
             JOIN dbo.SMMenu m ON gt.MenuId = m.MenuId
             LEFT JOIN dbo.SMMenu p ON m.ParentMenuId = p.MenuId
             WHERE gt.GroupId = ?
               AND ISNULL(gt.CanView, 0) = 1
               AND LTRIM(RTRIM(ISNULL(m.MenuUrl, ''))) NOT IN ('', '#', '/')
               AND NOT EXISTS (SELECT 1 FROM dbo.SMUserFavoriteMenu f WHERE f.UserId = ? AND f.MenuId = m.MenuId)
             ORDER BY ISNULL(p.MenuName, m.MenuName), m.MenuName";
$stmtMenus = sqlsrv_query($conn, $sqlMenus, [$groupId, $userId]);
if ($stmtMenus) {
    while ($row = sqlsrv_fetch_array($stmtMenus, SQLSRV_FETCH_ASSOC)) $availableMenus[] = $row;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
:root { --favorite-accent: var(--<?= h($themeColor) ?>, #007bff); }
.favorite-command { background: #fff; border: 1px solid rgba(31,45,61,.09); border-radius: 12px; box-shadow: 0 5px 16px rgba(31,45,61,.05); padding: .85rem 1rem; }
.favorite-kicker { display: none; }
.favorite-title-main { font-weight: 700; letter-spacing: -.02em; font-size: 1.35rem; white-space: nowrap; }
.favorite-summary { display: block; font-size: .72rem; line-height: 1.1; }
.favorite-search { min-height: 42px; border-radius: 9px; border-color: rgba(0,0,0,.1); }
.favorite-header-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.favorite-header-actions .btn { min-height: 42px; padding: .55rem .8rem; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; border: 1px solid rgba(0,0,0,.12); box-shadow: 0 2px 5px rgba(31,45,61,.08); }
.favorite-header-actions .btn:hover { filter: brightness(.92); transform: translateY(-1px); }
.favorite-header-actions .btn i { color: inherit; }
.favorite-open-mode { min-height: 42px; display: inline-flex; align-items: center; gap: .5rem; white-space: nowrap; }
.favorite-open-label { color: #6c757d; font-size: .72rem; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; }
.favorite-open-options { padding: 3px; display: inline-flex; gap: 2px; border: 1px solid rgba(31,45,61,.16); border-radius: 9px; background: #f1f4f8; box-shadow: inset 0 1px 2px rgba(31,45,61,.05); }
.favorite-open-option { position: relative; margin: 0; }
.favorite-open-option input { position: absolute; opacity: 0; pointer-events: none; }
.favorite-open-option span { min-height: 34px; padding: .42rem .65rem; border: 1px solid transparent; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; color: #5b6570; font-size: .82rem; font-weight: 600; cursor: pointer; transition: background .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease; }
.favorite-open-option:hover span { background: #fff; border-color: rgba(31,45,61,.14); color: #212529; }
.favorite-open-option input:checked + span, .favorite-open-option:hover input:checked + span { background: var(--favorite-accent); border-color: var(--favorite-accent); color: #fff; box-shadow: 0 2px 6px rgba(31,45,61,.18); }
.favorite-open-option input:focus + span { box-shadow: 0 0 0 3px rgba(0,123,255,.2); }
.favorite-open-option input:disabled + span { opacity: .55; cursor: wait; }
.favorite-category { border: 1px solid rgba(31,45,61,.1); border-radius: 14px; overflow: visible; box-shadow: 0 5px 16px rgba(31,45,61,.05); background: #fff; }
.favorite-category-header { min-height: 58px; padding: .7rem 1rem; border-bottom: 0; color: #fff; background-image: linear-gradient(115deg, rgba(255,255,255,.12), rgba(0,0,0,.08)) !important; }
.favorite-category-header .text-muted { color: rgba(255,255,255,.78) !important; }
.favorite-category-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: rgba(255,255,255,.18); color: #fff; box-shadow: inset 0 0 0 1px rgba(255,255,255,.22); }
.favorite-category-name { font-weight: 700; font-size: 1rem; }
.menu-select-option { display: flex; align-items: center; min-width: 0; }
.menu-select-icon { width: 30px; height: 30px; flex: 0 0 30px; margin-right: .65rem; border-radius: 8px; background: #f1f4f8; display: inline-flex; align-items: center; justify-content: center; }
.menu-select-icon .iconify { width: 18px; height: 18px; }
.menu-select-label { min-width: 0; white-space: normal; line-height: 1.25; }
#modalAddFavorite .select2-container--bootstrap4 .select2-selection--single { height: 46px; padding: 4px 34px 4px 6px; display: flex; align-items: center; }
#modalAddFavorite .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered { width: 100%; padding: 0; line-height: normal; }
#modalAddFavorite .select2-container--bootstrap4 .select2-selection--single .menu-select-option { height: 36px; align-items: center; }
#modalAddFavorite .select2-container--bootstrap4 .select2-selection--single .menu-select-icon { width: 30px; height: 30px; flex-basis: 30px; margin-top: 0; margin-bottom: 0; }
#modalAddFavorite .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: 7px; display: flex; align-items: center; justify-content: center; }
#modalAddFavorite .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow b { position: static; margin: 0; }
.favorite-category-body { padding: 1rem; }
.favorite-category-actions { gap: 6px; }
.favorite-category-actions .btn { width: 34px; height: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; }
.favorite-category-actions .btn i { margin: 0 !important; font-size: .88rem; }
.favorite-tile-shell { display: flex; min-height: 112px; overflow: hidden; border: 1px solid rgba(31,45,61,.1); border-radius: 14px; background: #fff; transition: box-shadow .16s ease, border-color .16s ease; }
.favorite-tile-shell:hover { border-color: rgba(0,123,255,.42); box-shadow: 0 7px 16px rgba(31,45,61,.1); }
.favorite-tile { flex: 1 1 auto; min-width: 0; padding: 1rem; display: flex; align-items: center; color: #212529; }
.favorite-tile:hover { color: #212529; text-decoration: none; }
.favorite-icon { width: 58px; height: 58px; flex: 0 0 58px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: #f1f4f8; font-size: 27px; }
.fav-svg-icon { width: 28px; height: 28px; display: inline-flex; }
.favorite-tile-copy { min-width: 0; margin-left: .85rem; }
.favorite-parent { font-size: .76rem; color: #6c757d; line-height: 1.3; overflow-wrap: anywhere; }
.favorite-title { font-weight: 700; font-size: .9rem; line-height: 1.3; overflow-wrap: anywhere; }
.favorite-action-rail { width: 44px; flex: 0 0 44px; padding: 0; border: 0; border-left: 1px solid rgba(31,45,61,.1); border-radius: 0; background: #f8fafc; display: flex; align-items: center; justify-content: center; transition: background .15s ease, filter .15s ease; }
.favorite-action-rail:hover, .favorite-action-rail:focus { background: #eef2f6; filter: brightness(.88); outline: 0; }
.favorite-action-rail i { font-size: 1rem; }
#favoriteGroups { position: relative; transition: opacity .18s ease; }
#favoriteGroups.is-layout-pending { min-height: 160px; opacity: 0; pointer-events: none; }
.favorite-layout-loader { display: none; }
.favorite-layout-loader.is-visible { min-height: 160px; display: flex; align-items: center; justify-content: center; color: #6c757d; }
.favorite-layout-loader i { margin-right: .55rem; color: var(--favorite-accent); }
#favoriteGroups.is-compact { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-auto-rows: 1px; column-gap: 1rem; align-items: start; }
#favoriteGroups.is-compact .favorite-category { min-width: 0; margin-bottom: 0 !important; }
#favoriteGroups.is-compact .favorite-category-body { padding: .65rem; }
#favoriteGroups.is-compact .favorite-category-body > .row { margin-right: -.3rem; margin-left: -.3rem; }
#favoriteGroups.is-compact .js-favorite-tile { flex: 0 0 100%; max-width: 100%; margin-bottom: .4rem !important; padding-right: .3rem; padding-left: .3rem; }
#favoriteGroups.is-compact .favorite-tile-shell { min-height: 52px; border-radius: 9px; }
#favoriteGroups.is-compact .favorite-tile { min-height: 52px; padding: .45rem .65rem; }
#favoriteGroups.is-compact .favorite-icon { width: 34px; height: 34px; flex-basis: 34px; border-radius: 9px; font-size: 17px; }
#favoriteGroups.is-compact .fav-svg-icon { width: 19px; height: 19px; }
#favoriteGroups.is-compact .favorite-tile-copy { flex: 1 1 0; width: 0; margin-left: .65rem; display: grid; grid-template-columns: minmax(0, 1fr) minmax(88px, 38%); align-items: center; gap: .6rem; min-width: 0; }
#favoriteGroups.is-compact .favorite-title { margin: 0 !important; padding-right: .1rem; font-size: .86rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#favoriteGroups.is-compact .favorite-parent { min-width: 0; margin: 0; padding-left: .6rem; border-left: 1px solid rgba(31,45,61,.14); font-size: .72rem; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#favoriteGroups.is-compact .favorite-action-rail { width: 40px; flex-basis: 40px; }
@media (max-width: 1399.98px) {
    #favoriteGroups.is-compact { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
    #favoriteGroups.is-compact { grid-template-columns: 1fr; }
}
.favorite-layout-control { margin-left: auto; display: flex; align-items: center; gap: .5rem; }
.empty-fav, .favorite-empty-category { border: 1px dashed rgba(31,45,61,.2); border-radius: 12px; padding: 2rem; background: #f8fafc; }
.modal-header .close { color: #fff; opacity: .9; }
#favoriteToastContainer { position: fixed; bottom: 20px; right: 20px; z-index: 1060; min-width: 280px; pointer-events: none; }
#favoriteToast { pointer-events: auto; }
@media (max-width: 575.98px) {
    .favorite-header-actions .btn { flex: 1 1 100%; width: 100%; }
    .favorite-command { padding: 1rem; }
    #favoriteToastContainer { left: 12px; right: 12px; bottom: 12px; min-width: 0; }
}
</style>

<div class="content-wrapper">
    <section class="content pt-3">
        <div class="container-fluid">
            <?php $totalFavorites = count($uncategorized['items']); foreach ($categories as $c) { $totalFavorites += count($c['items']); } ?>
            <div class="favorite-command mb-4">
                <div class="row align-items-center">
                    <div class="col-auto pr-0">
                        <h1 class="h3 m-0 text-<?= h($themeColor) ?> favorite-title-main"><i class="fas fa-star mr-1"></i>Favorit</h1>
                        <small class="text-muted favorite-summary"><?= $totalFavorites ?> shortcut · <?= count($categories) ?> kategori</small>
                    </div>
                    <div class="col-lg px-lg-4 my-3 my-lg-0">
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span></div>
                            <input id="favoriteSearch" type="search" class="form-control favorite-search border-left-0" placeholder="..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-lg-auto favorite-header-actions justify-content-lg-end">
                        <div class="favorite-open-mode" role="radiogroup" aria-label="Buka menu Favorit" title="Pilih lokasi untuk membuka semua shortcut Favorit">
                            <span class="favorite-open-label">Buka menu</span>
                            <div class="favorite-open-options">
                                <label class="favorite-open-option">
                                    <input type="radio" name="favoriteOpenMode" value="0"<?= !$openInNewTab ? ' checked' : '' ?>>
                                    <span><i class="fas fa-window-maximize mr-1"></i>Tab ini</span>
                                </label>
                                <label class="favorite-open-option">
                                    <input type="radio" name="favoriteOpenMode" value="1"<?= $openInNewTab ? ' checked' : '' ?>>
                                    <span><i class="fas fa-external-link-alt mr-1"></i>Tab baru</span>
                                </label>
                            </div>
                        </div>
                        <div class="favorite-layout-control" role="radiogroup" aria-label="Tampilan Favorit">
                            <span class="favorite-open-label">Tampilan</span>
                            <div class="favorite-open-options">
                                <label class="favorite-open-option" title="Tampilan kartu">
                                    <input type="radio" name="favoriteLayoutMode" value="card"<?= $layoutMode === 'card' ? ' checked' : '' ?>>
                                    <span><i class="fas fa-th-large mr-1"></i>Card</span>
                                </label>
                                <label class="favorite-open-option" title="Tampilan daftar ringkas">
                                    <input type="radio" name="favoriteLayoutMode" value="compact"<?= $layoutMode === 'compact' ? ' checked' : '' ?>>
                                    <span><i class="fas fa-list mr-1"></i>List</span>
                                </label>
                            </div>
                        </div>
                        <button type="button" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?>" data-toggle="modal" data-target="#modalAddCategory"><i class="fas fa-folder-plus mr-1"></i>Tambah Kategori</button>
                    </div>
                </div>
            </div>

            <?php if ($totalFavorites === 0 && count($categories) === 0): ?>
                <div class="empty-fav text-center text-muted mb-4">
                    <i class="far fa-star fa-3x mb-3"></i><h4>Belum ada favorit</h4>
                </div>
            <?php endif; ?>
            <div id="favoriteNoResults" class="empty-fav text-center text-muted mb-4 d-none"><i class="fas fa-search fa-2x mb-3"></i><h4>Menu tidak ditemukan</h4><p class="mb-0">Coba kata kunci lain.</p></div>

            <?php $groups = array_merge([$uncategorized], array_values($categories)); ?>
            <div id="favoriteLayoutLoader" class="favorite-layout-loader<?= $layoutMode === 'compact' ? ' is-visible' : '' ?>" role="status"><i class="fas fa-circle-notch fa-spin"></i>Menyiapkan tampilan...</div>
            <div id="favoriteGroups" class="<?= $layoutMode === 'compact' ? 'is-compact is-layout-pending' : '' ?>">
                <?php foreach ($groups as $category): ?>
                    <?php if (empty($category['items']) && $category['CategoryId'] === null) continue; ?>
                    <section class="favorite-category mb-4 js-favorite-category" data-search="<?= h(strtolower($category['CategoryName'])) ?>">
                        <header class="favorite-category-header bg-<?= h($themeColor) ?> d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center min-width-0">
                                <span class="favorite-category-icon"><?= renderFavoriteIcon($category['Icon'] ?? 'fas fa-folder', 'fas fa-folder') ?></span>
                                <div class="ml-2 min-width-0"><div class="favorite-category-name text-truncate"><?= h($category['CategoryName']) ?></div><small class="text-muted"><?= count($category['items']) ?> shortcut</small></div>
                            </div>
                            <div class="ml-auto d-flex align-items-center favorite-category-actions">
                                <button type="button" class="btn btn-success js-add-to-category" data-category-id="<?= $category['CategoryId'] === null ? '' : (int) $category['CategoryId'] ?>" data-category-name="<?= h($category['CategoryName']) ?>" title="Tambah menu ke kategori ini" aria-label="Tambah menu"><i class="fas fa-plus"></i></button>
                                <?php if ($category['CategoryId'] !== null): ?>
                                    <button type="button" class="btn btn-warning js-rename-category" data-id="<?= (int) $category['CategoryId'] ?>" data-name="<?= h($category['CategoryName']) ?>" data-icon="<?= h($category['Icon'] ?? 'fas fa-folder') ?>" title="Edit kategori" aria-label="Edit kategori"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="btn btn-danger js-delete-category" data-id="<?= (int) $category['CategoryId'] ?>" title="Hapus kategori" aria-label="Hapus kategori"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </header>
                        <div class="favorite-category-body">
                            <?php if (empty($category['items'])): ?>
                                <div class="favorite-empty-category text-muted"><i class="fas fa-info-circle mr-1"></i>Kategori kosong. Tambahkan shortcut dari tombol <b>Tambah Menu</b>.</div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($category['items'] as $item): ?>
                                        <?php $tileSearch = strtolower($category['CategoryName'] . ' ' . $item['MenuName'] . ' ' . ($item['ParentName'] ?? '')); ?>
                                        <div class="col-12 col-md-6 col-xl-4 mb-3 js-favorite-tile" data-search="<?= h($tileSearch) ?>">
                                            <div class="favorite-tile-shell">
                                                <a href="<?= h($item['MenuUrl']) ?>" class="favorite-tile js-favorite-link"<?= $openInNewTab ? ' target="_blank" rel="noopener noreferrer"' : '' ?> title="Buka <?= h($item['MenuName']) ?>">
                                                    <div class="favorite-icon text-<?= h($themeColor) ?>"><?= renderFavoriteIcon($item['FavoriteIcon'] ?? '', $item['MenuIcon'] ?? 'fas fa-star') ?></div>
                                                    <div class="favorite-tile-copy">
                                                        <h2 class="favorite-title h6 mb-1"><?= h($item['MenuName']) ?></h2>
                                                        <?php if (!empty($item['ParentName'])): ?><div class="favorite-parent"><?= h($item['ParentName']) ?></div><?php endif; ?>
                                                    </div>
                                                </a>
                                                <button type="button" class="favorite-action-rail text-<?= h($themeColor) ?> js-open-favorite-settings" data-id="<?= (int) $item['FavoriteId'] ?>" data-name="<?= h($item['MenuName']) ?>" data-icon="<?= h($item['FavoriteIcon'] ?? '') ?>" data-category-id="<?= $category['CategoryId'] === null ? '' : (int) $category['CategoryId'] ?>" title="Atur shortcut" aria-label="Atur <?= h($item['MenuName']) ?>"><i class="fas fa-sliders-h"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="modalAddFavorite" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form id="addFavoriteForm" class="modal-content">
            <div class="modal-header bg-<?= h($themeColor) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-star mr-2"></i>Tambah Menu Favorit</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_favorite">
                <div class="form-group">
                    <label>Pilih Menu <small class="text-muted">(bisa lebih dari satu)</small></label>
                    <select name="menu_ids[]" class="form-control select2" multiple required data-placeholder="Pilih satu atau beberapa menu">
                        <?php foreach ($availableMenus as $menu): ?>
                            <option value="<?= (int) $menu['MenuId'] ?>" data-icon="<?= h($menu['ParentIcon'] ?: ($menu['MenuIcon'] ?: 'fas fa-play')) ?>"><?= h(menuLabel($menu)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Cari lalu klik semua menu yang ingin ditambahkan.</small>
                </div>
                <div class="form-group">
                    <label for="addFavoriteCategoryName">Kategori</label>
                    <input type="text" id="addFavoriteCategoryName" class="form-control" readonly>
                    <input type="hidden" name="category_id" id="addFavoriteCategory">
                </div>
                <div class="form-group mb-0">
                    <label>Custom Icon <small class="text-muted">(opsional, berlaku untuk semua pilihan)</small></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text category-icon-preview"><i class="fas fa-star"></i></span></div>
                        <input type="text" name="icon" class="form-control category-icon-input" maxlength="100" placeholder="Kosongkan untuk icon menu default">
                        <div class="input-group-append"><button type="button" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?> js-open-icon-picker">Cari</button></div>
                    </div>
                    <small class="form-text text-muted">Kosongkan untuk memakai icon bawaan menu.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?>"><i class="fas fa-save mr-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAddCategory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="addCategoryForm" class="modal-content">
            <div class="modal-header bg-<?= h($themeColor) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-folder-plus mr-2"></i>Tambah Kategori</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_category">
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input type="text" name="category_name" class="form-control" maxlength="100" required>
                </div>
                <div class="form-group mb-0">
                    <label>Icon</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text category-icon-preview"><i class="fas fa-folder"></i></span></div>
                        <input type="text" name="icon" class="form-control category-icon-input" value="fas fa-folder" maxlength="100" required>
                        <div class="input-group-append"><button type="button" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?> js-open-icon-picker">Cari</button></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?>"><i class="fas fa-save mr-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalRenameCategory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="renameCategoryForm" class="modal-content">
            <div class="modal-header bg-<?= h($themeColor) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-edit mr-2"></i>Rename Kategori</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="rename_category">
                <input type="hidden" name="category_id" id="renameCategoryId">
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input type="text" name="category_name" id="renameCategoryName" class="form-control" maxlength="100" required>
                </div>
                <div class="form-group mb-0">
                    <label>Icon</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text category-icon-preview"><i class="fas fa-folder"></i></span></div>
                        <input type="text" name="icon" id="renameCategoryIcon" class="form-control category-icon-input" value="fas fa-folder" maxlength="100" required>
                        <div class="input-group-append"><button type="button" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?> js-open-icon-picker">Cari</button></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?>"><i class="fas fa-save mr-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalFavoriteSettings" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= h($themeColor) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-sliders-h mr-2"></i>Atur Shortcut</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="settingsFavoriteId">
                <input type="hidden" id="settingsFavoriteIcon">
                <div class="font-weight-bold mb-3" id="settingsFavoriteName"></div>
                <div class="form-group mb-0">
                    <label for="settingsFavoriteCategory">Pindah Kategori</label>
                    <select id="settingsFavoriteCategory" class="form-control">
                        <option value="">Tanpa Kategori</option>
                        <?php foreach ($categories as $category): ?><option value="<?= (int) $category['CategoryId'] ?>"><?= h($category['CategoryName']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-danger" id="settingsDeleteFavorite"><i class="fas fa-trash mr-1"></i>Hapus</button>
                <div>
                    <button type="button" class="btn btn-warning" id="settingsEditIcon"><i class="fas fa-icons mr-1"></i>Ganti Icon</button>
                    <button type="button" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?>" id="settingsMoveFavorite"><i class="fas fa-folder mr-1"></i>Pindahkan</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfirm" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= h($themeColor) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-question-circle mr-2"></i>Konfirmasi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="confirmMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmOk"><i class="fas fa-check mr-1"></i>Ya</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFavoriteIcon" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="favoriteIconForm" class="modal-content">
            <div class="modal-header bg-<?= h($themeColor) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-icons mr-2"></i>Ganti Icon Menu Favorit</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update_favorite_icon">
                <input type="hidden" name="favorite_id" id="favoriteIconId">
                <div class="form-group mb-0">
                    <label>Icon</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text category-icon-preview"><i class="fas fa-star"></i></span></div>
                        <input type="text" name="icon" id="favoriteIconInput" class="form-control category-icon-input" maxlength="100" placeholder="Kosongkan untuk icon menu default">
                        <div class="input-group-append"><button type="button" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?> js-open-icon-picker">Cari</button></div>
                    </div>
                    <small class="form-text text-muted">Kosongkan lalu simpan untuk memakai icon bawaan menu.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="resetFavoriteIcon">Icon Default</button>
                <button type="submit" class="btn bg-<?= h($themeColor) ?> text-<?= $themeTextColor ?>"><i class="fas fa-save mr-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalIconPicker" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= h($themeColor) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-search mr-2"></i>Cari Icon</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom"><input type="text" id="searchIcon" class="form-control" placeholder="Cari icon, contoh: folder, star, user..."></div>
                <div id="iconList" class="row no-gutters p-3" style="max-height:400px;overflow-y:auto;"><div class="col-12 text-center text-muted py-5">Ketik minimal 2 karakter.</div></div>
            </div>
            <div class="modal-footer"><small class="text-muted mr-auto">Powered by Iconify</small><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<div id="favoriteToastContainer">
    <div class="toast hide" id="favoriteToast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3500">
        <div class="toast-header bg-<?= h($themeColor) ?> text-white">
            <strong class="mr-auto text-white"><i class="fas fa-info-circle mr-1"></i>Favorit</strong>
            <button type="button" class="ml-2 mb-1 close text-white" data-dismiss="toast" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="toast-body" id="favoriteToastBody"></div>
    </div>
</div>

<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = window.jQuery;
    if (!$) return;

    function menuSelectTemplate(option) {
        if (!option.id) return option.text;
        var icon = $(option.element).data('icon') || 'fas fa-play';
        var row = $('<span>', { 'class': 'menu-select-option' });
        var iconBox = $('<span>', { 'class': 'menu-select-icon text-<?= h($themeColor) ?>' });
        if (icon.indexOf(':') !== -1) {
            iconBox.append($('<span>', { 'class': 'iconify', 'data-icon': icon }));
        } else {
            iconBox.append($('<i>', { 'class': icon }));
        }
        row.append(iconBox).append($('<span>', { 'class': 'menu-select-label', text: option.text }));
        setTimeout(function () { if (window.Iconify) window.Iconify.scan(row[0]); }, 0);
        return row;
    }

    $('#modalAddFavorite .select2').select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalAddFavorite'),
        closeOnSelect: false,
        placeholder: 'Pilih satu atau beberapa menu',
        templateResult: menuSelectTemplate,
        templateSelection: menuSelectTemplate
    });

    function layoutCompactCategories() {
        var groups = document.getElementById('favoriteGroups');
        if (!groups || !groups.classList.contains('is-compact')) return;
        groups.querySelectorAll('.favorite-category:not(.d-none)').forEach(function (category) {
            category.style.gridRowEnd = 'span ' + Math.ceil((category.getBoundingClientRect().height + 16));
        });
    }

    function revealFavoriteLayout() {
        $('#favoriteGroups').removeClass('is-layout-pending');
        $('#favoriteLayoutLoader').removeClass('is-visible');
    }

    function prepareCompactLayout() {
        requestAnimationFrame(function () {
            layoutCompactCategories();
            requestAnimationFrame(revealFavoriteLayout);
        });
    }

    var initialLayoutMode = '<?= $layoutMode ?>';
    if (initialLayoutMode === 'compact') prepareCompactLayout();
    else revealFavoriteLayout();
    setTimeout(revealFavoriteLayout, 800);

    $('input[name="favoriteLayoutMode"]').on('change', function () {
        var mode = this.value === 'compact' ? 'compact' : 'card';
        var previousMode = mode === 'compact' ? 'card' : 'compact';
        var controls = $('input[name="favoriteLayoutMode"]');
        $('#favoriteGroups').toggleClass('is-compact', mode === 'compact');
        $('.favorite-category').css('grid-row-end', '');
        if (mode === 'compact') requestAnimationFrame(layoutCompactCategories);
        controls.prop('disabled', true);
        postForm({ action: 'save_layout_mode', layout_mode: mode }).then(function (response) {
            if (!response.ok) {
                controls.filter('[value="' + previousMode + '"]').prop('checked', true);
                $('#favoriteGroups').toggleClass('is-compact', previousMode === 'compact');
                if (previousMode === 'compact') requestAnimationFrame(layoutCompactCategories);
                showToast(response.message, false);
                return;
            }
            showToast(response.message, true);
        }).catch(function () {
            controls.filter('[value="' + previousMode + '"]').prop('checked', true);
            $('#favoriteGroups').toggleClass('is-compact', previousMode === 'compact');
            if (previousMode === 'compact') requestAnimationFrame(layoutCompactCategories);
            showToast('Gagal menyimpan tampilan.', false);
        }).finally(function () {
            controls.prop('disabled', false);
        });
    });

    $(window).on('resize', function () { requestAnimationFrame(layoutCompactCategories); });

    $('input[name="favoriteOpenMode"]').on('change', function () {
        var enabled = this.value === '1';
        var previousValue = enabled ? '0' : '1';
        var controls = $('input[name="favoriteOpenMode"]');
        controls.prop('disabled', true);
        postForm({ action: 'save_open_mode', open_in_new_tab: enabled ? '1' : '0' }).then(function (response) {
            if (!response.ok) {
                controls.filter('[value="' + previousValue + '"]').prop('checked', true);
                showToast(response.message, false);
                return;
            }
            $('.js-favorite-link')
                .attr('target', enabled ? '_blank' : null)
                .attr('rel', enabled ? 'noopener noreferrer' : null);
            showToast(response.message, true);
        }).catch(function () {
            controls.filter('[value="' + previousValue + '"]').prop('checked', true);
            showToast('Gagal menyimpan pengaturan.', false);
        }).finally(function () {
            controls.prop('disabled', false);
        });
    });

    var confirmCallback = null;
    var iconTarget = null;
    var iconSearchTimer = null;
    var toast = $('#favoriteToast');
    var toastContainer = $('#favoriteToastContainer');

    function updateIconPreview(input) {
        var icon = $(input).val().trim() || 'fas fa-folder';
        var preview = $(input).closest('.input-group').find('.category-icon-preview');
        preview.empty();
        if (icon.indexOf(':') !== -1 && window.Iconify) {
            preview.append($('<span>', { 'class': 'iconify', 'data-icon': icon, 'data-width': '20', 'data-height': '20' }));
            window.Iconify.scan(preview[0]);
        } else {
            preview.append($('<i>', { 'class': icon.indexOf(':') === -1 ? icon : 'fas fa-folder' }));
        }
    }

    function renderIconResults(icons) {
        var list = $('#iconList').empty();
        if (!icons || !icons.length) {
            list.html('<div class="col-12 text-center text-muted py-5">Icon tidak ditemukan.</div>');
            return;
        }
        icons.forEach(function (icon) {
            var button = $('<button>', { type: 'button', 'class': 'col-3 col-md-2 p-2 border-0 bg-transparent js-select-icon', 'data-icon': icon });
            button.append($('<span>', { 'class': 'd-flex flex-column align-items-center justify-content-center border rounded p-2 h-100 icon-picker-item' })
                .append($('<span>', { 'class': 'iconify mb-1', 'data-icon': icon, 'data-width': '24', 'data-height': '24' }))
                .append($('<small>', { text: icon, 'class': 'text-truncate w-100', style: 'font-size:8px;' })));
            list.append(button);
        });
        if (window.Iconify) window.Iconify.scan(list[0]);
    }

    function searchIcons(query) {
        if (query.length < 2) {
            $('#iconList').html('<div class="col-12 text-center text-muted py-5">Ketik minimal 2 karakter.</div>');
            return;
        }
        $('#iconList').html('<div class="col-12 text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-1"></i>Mencari icon...</div>');
        fetch('https://api.iconify.design/search?query=' + encodeURIComponent(query) + '&limit=48')
            .then(function (response) { return response.json(); })
            .then(function (data) { renderIconResults(data.icons); })
            .catch(function () { $('#iconList').html('<div class="col-12 text-center text-danger py-5">Gagal mencari icon.</div>'); });
    }

    function postForm(data) {
        return fetch('favorit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(data),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    function showToast(message, ok) {
        $('#favoriteToast .toast-header')
            .removeClass('bg-danger bg-success bg-<?= h($themeColor) ?>')
            .addClass(ok ? 'bg-success' : 'bg-danger');
        $('#favoriteToastBody').text(message || (ok ? 'Berhasil.' : 'Gagal.'));
        toastContainer.css('pointer-events', 'auto');
        toast.toast('show');
    }

    function handle(resp) {
        showToast(resp.message, !!resp.ok);
        if (resp.ok) setTimeout(function () { location.reload(); }, 650);
    }

    function confirmAdmin(message, cb) {
        confirmCallback = cb;
        $('#confirmMessage').text(message);
        $('#modalConfirm').modal('show');
    }

    toast.on('hidden.bs.toast', function () {
        toastContainer.css('pointer-events', 'none');
    });

    $('#confirmOk').on('click', function () {
        $('#modalConfirm').modal('hide');
        if (typeof confirmCallback === 'function') confirmCallback();
        confirmCallback = null;
    });

    $('#addFavoriteForm, #addCategoryForm, #renameCategoryForm, #favoriteIconForm').on('submit', function (e) {
        e.preventDefault();
        postForm(new FormData(this)).then(handle).catch(function () { showToast('Request gagal.', false); });
    });

    $(document).on('click', '.js-delete-favorite', function () {
        var id = this.getAttribute('data-id');
        confirmAdmin('Hapus favorit ini?', function () {
            postForm({ action: 'delete_favorite', favorite_id: id }).then(handle).catch(function () { showToast('Request gagal.', false); });
        });
    });

    $(document).on('click', '.js-delete-category', function () {
        var id = this.getAttribute('data-id');
        confirmAdmin('Hapus kategori? Menu akan pindah ke Tanpa Kategori.', function () {
            postForm({ action: 'delete_category', category_id: id }).then(handle).catch(function () { showToast('Request gagal.', false); });
        });
    });

    $(document).on('click', '.js-rename-category', function () {
        $('#renameCategoryId').val(this.getAttribute('data-id'));
        $('#renameCategoryName').val(this.getAttribute('data-name') || '');
        $('#renameCategoryIcon').val(this.getAttribute('data-icon') || 'fas fa-folder');
        updateIconPreview($('#renameCategoryIcon'));
        $('#modalRenameCategory').modal('show');
    });

    $(document).on('input', '.category-icon-input', function () {
        updateIconPreview(this);
    });

    $(document).on('click', '.js-open-icon-picker', function () {
        iconTarget = $(this).closest('.input-group').find('.category-icon-input');
        $('#searchIcon').val('folder');
        $('#modalIconPicker').modal('show');
        searchIcons('folder');
    });

    $('#searchIcon').on('input', function () {
        var query = this.value.trim();
        clearTimeout(iconSearchTimer);
        iconSearchTimer = setTimeout(function () { searchIcons(query); }, 350);
    });

    $(document).on('click', '.js-select-icon', function () {
        var icon = this.getAttribute('data-icon');
        if (!iconTarget || !icon) return;
        postForm({ action: 'download_icon', icon: icon }).then(function (response) {
            if (!response.ok) { showToast(response.message, false); return; }
            iconTarget.val(icon);
            updateIconPreview(iconTarget);
            $('#modalIconPicker').modal('hide');
        }).catch(function () { showToast('Gagal menyiapkan icon.', false); });
    });

    $('#modalAddCategory').on('show.bs.modal', function () {
        var input = $(this).find('.category-icon-input');
        if (!input.val()) input.val('fas fa-folder');
        updateIconPreview(input);
    });

    $(document).on('click', '.js-edit-favorite-icon', function () {
        $('#favoriteIconId').val(this.getAttribute('data-id'));
        $('#favoriteIconInput').val(this.getAttribute('data-icon') || '');
        updateIconPreview($('#favoriteIconInput'));
        $('#modalFavoriteIcon').modal('show');
    });

    $('#resetFavoriteIcon').on('click', function () {
        $('#favoriteIconInput').val('');
        updateIconPreview($('#favoriteIconInput'));
    });

    $(document).on('click', '.js-move-favorite', function () {
        postForm({
            action: 'move_favorite',
            favorite_id: this.getAttribute('data-id'),
            category_id: this.getAttribute('data-category-id') || ''
        }).then(handle).catch(function () { showToast('Request gagal.', false); });
    });

    $('#favoriteSearch').on('input', function () {
        var query = this.value.trim().toLowerCase();
        var visibleCount = 0;
        $('.js-favorite-category').each(function () {
            var category = $(this);
            var categoryMatch = (category.data('search') || '').indexOf(query) !== -1;
            var visibleTiles = 0;
            category.find('.js-favorite-tile').each(function () {
                var match = categoryMatch || (($(this).data('search') || '').indexOf(query) !== -1);
                $(this).toggleClass('d-none', !match);
                if (match) visibleTiles++;
            });
            var hasNoTiles = category.find('.js-favorite-tile').length === 0;
            category.toggleClass('d-none', query !== '' && !hasNoTiles && visibleTiles === 0);
            if (query === '' || hasNoTiles || visibleTiles > 0) visibleCount += visibleTiles;
        });
        $('#favoriteNoResults').toggleClass('d-none', query === '' || visibleCount > 0);
        requestAnimationFrame(layoutCompactCategories);
    });

    $(document).on('click', '.js-open-favorite-settings', function () {
        $('#settingsFavoriteId').val(this.getAttribute('data-id'));
        $('#settingsFavoriteIcon').val(this.getAttribute('data-icon') || '');
        $('#settingsFavoriteName').text(this.getAttribute('data-name') || 'Shortcut');
        $('#settingsFavoriteCategory').val(this.getAttribute('data-category-id') || '');
        $('#modalFavoriteSettings').modal('show');
    });

    $('#settingsMoveFavorite').on('click', function () {
        postForm({
            action: 'move_favorite',
            favorite_id: $('#settingsFavoriteId').val(),
            category_id: $('#settingsFavoriteCategory').val() || ''
        }).then(handle).catch(function () { showToast('Request gagal.', false); });
    });

    $('#settingsEditIcon').on('click', function () {
        $('#favoriteIconId').val($('#settingsFavoriteId').val());
        $('#favoriteIconInput').val($('#settingsFavoriteIcon').val());
        updateIconPreview($('#favoriteIconInput'));
        $('#modalFavoriteSettings').modal('hide');
        $('#modalFavoriteIcon').modal('show');
    });

    $('#settingsDeleteFavorite').on('click', function () {
        var id = $('#settingsFavoriteId').val();
        $('#modalFavoriteSettings').modal('hide');
        confirmAdmin('Hapus menu dari Favorit?', function () {
            postForm({ action: 'delete_favorite', favorite_id: id }).then(handle).catch(function () { showToast('Request gagal.', false); });
        });
    });

    $(document).on('click', '.js-add-to-category', function () {
        var categoryId = this.getAttribute('data-category-id') || '';
        var categoryName = this.getAttribute('data-category-name') || 'Tanpa Kategori';
        $('#addFavoriteCategory').val(categoryId);
        $('#addFavoriteCategoryName').val(categoryName);
        $('#modalAddFavorite').modal('show');
        $('#modalAddFavorite .modal-title').html('<i class="fas fa-plus mr-2"></i>Tambah Menu — ' + $('<div>').text(categoryName).html());
    });

    $('#modalAddFavorite').on('hidden.bs.modal', function () {
        $('#addFavoriteCategory, #addFavoriteCategoryName').val('');
        $(this).find('.modal-title').html('<i class="fas fa-star mr-2"></i>Tambah Menu Favorit');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
