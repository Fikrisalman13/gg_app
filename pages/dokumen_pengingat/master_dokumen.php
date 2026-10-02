<?php
// ======================================================
// master_dokumen.php — Unified Dynamic Document Manager (V3)
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../ticket/theme_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$includeTicketThemeCss = true;
$theme = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$GLOBALS['ticketThemeOverride'] = $theme;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// CSS DataTables
echo '<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">';
echo '<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">';

// ------------------------------------------------------
// MAIN PAGE ACCESS CONTROL
// ------------------------------------------------------
requireView($conn, 1283);

$username = $_SESSION['UserName'] ?? null;
$groupId = (int) ($_SESSION['GroupId'] ?? 0);
$isAdministratorGroup = ($groupId === 1);
$idDeptUser = null;

if ($username) {
    $stmtDeptUser = sqlsrv_query(
        $conn,
        "SELECT b.id_bag
         FROM dbo.SMUserMs u
         JOIN dbo.m_emp e ON u.EmpId = e.id_emp
         JOIN dbo.m_bag b ON e.id_bag = b.id_bag
         WHERE u.UserName = ?",
        [$username]
    );
    if ($stmtDeptUser && $rDeptUser = sqlsrv_fetch_array($stmtDeptUser, SQLSRV_FETCH_ASSOC)) {
        $idDeptUser = $rDeptUser['id_bag'];
    }
    if ($stmtDeptUser) {
        sqlsrv_free_stmt($stmtDeptUser);
    }
}

$bagianFilterSql = (!$isAdministratorGroup && $idDeptUser !== null) ? ' AND d.bagian_id = ?' : '';
$bagianFilterParams = (!$isAdministratorGroup && $idDeptUser !== null) ? [$idDeptUser] : [];

// ------------------------------------------------------
// TRUSTEE ACCESS CONTROL & SUPER ADMIN CHECK
// ------------------------------------------------------
$isSuperAdmin = false;
$permTrustee = userPermissions($conn, 1286); // 1286 = Menu Manage Trustees
if ($permTrustee['CanView'] == 1) {
    $isSuperAdmin = true;
}

// Fetch user's assigned trustees if not super admin
$myTrustees = [];
if (!$isSuperAdmin && $username) {
    $stmtT = sqlsrv_query($conn, "SELECT kategori_tipe, category_id, can_view, can_edit, can_delete FROM dr_trustees WHERE username = ?", [$username]);
    if ($stmtT) {
        while ($rt = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC)) {
            $key = $rt['kategori_tipe'] === 'dynamic' ? 'dynamic_' . $rt['category_id'] : $rt['kategori_tipe'];
            $myTrustees[$key] = [
                'CanView' => $rt['can_view'],
                'CanEdit' => $rt['can_edit'],
                'CanDelete' => $rt['can_delete']
            ];
        }
    }
}

// Helper function to check permission
function checkTrustee($catKey, $myTrustees, $isSuperAdmin)
{
    if ($isSuperAdmin) {
        return ['CanView' => 1, 'CanEdit' => 1, 'CanDelete' => 1, 'CanAdd' => 1];
    }
    $res = $myTrustees[$catKey] ?? ['CanView' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    $res['CanAdd'] = $res['CanEdit'] ?? 0;
    return $res;
}

// Helper to get count
function getCount($conn, string $sql, array $params = []): int
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt)
        return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int) ($row['total'] ?? 0);
}

// ------------------------------------------------------
// FETCH CATEGORIES & STRUCTURE (DYNAMIC TREE)
// ------------------------------------------------------
$categories = [];
$docCountSql = $isAdministratorGroup
    ? "SELECT COUNT(*) FROM dr_documents d WHERE d.category_id = c.id"
    : "SELECT COUNT(*) FROM dr_documents d WHERE d.category_id = c.id AND d.bagian_id = ?";
$categoryParams = $isAdministratorGroup ? [] : [$idDeptUser ?? 0];

$stmt = sqlsrv_query($conn, "
    SELECT c.*, 
           (SELECT COUNT(*) FROM dr_fields f WHERE f.category_id = c.id) AS field_count, 
           ($docCountSql) AS doc_count 
    FROM dr_categories c 
    ORDER BY c.parent_id ASC, c.id ASC
", $categoryParams);
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($r['color'] === 'user') {
            $r['color'] = $theme;
        }
        $categories[] = $r;
    }
}

// Filter and map Parents & Children based on trustee authorization
$parentCategories = [];
$subCategories = [];

foreach ($categories as $cat) {
    if ($cat['parent_id'] === null) {
        $parentCategories[$cat['id']] = $cat;
        $parentCategories[$cat['id']]['children'] = [];
    } else {
        $dynKey = 'dynamic_' . $cat['id'];
        $permDyn = checkTrustee($dynKey, $myTrustees, $isSuperAdmin);

        if ($permDyn['CanView'] == 1) {
            $cat['perms'] = $permDyn;
            $subCategories[] = $cat;
        }
    }
}

foreach ($subCategories as $sub) {
    $pId = $sub['parent_id'];
    if (isset($parentCategories[$pId])) {
        $parentCategories[$pId]['children'][] = $sub;
    }
}

// Exclude Parent Categories that don't have any authorized children
$parentCategories = array_filter($parentCategories, function ($p) {
    return !empty($p['children']);
});

$activeTab = '';
if (!empty($subCategories)) {
    $activeTab = 'dynamic_' . $subCategories[0]['id'];
}

// ------------------------------------------------------
// CALCULATE LIVE STATS FOR USER
// ------------------------------------------------------
$totalSubCats = count($subCategories);
$totalDocs = 0;
$totalExpired = 0;
$totalReminder = 0;

$authSubIds = array_column($subCategories, 'id');
if (!empty($authSubIds)) {
    $in = implode(',', array_fill(0, count($authSubIds), '?'));
    $totalDocs = getCount($conn, "SELECT COUNT(*) total FROM dr_documents d WHERE d.category_id IN ($in)$bagianFilterSql", array_merge($authSubIds, $bagianFilterParams));
    $totalExpired = getCount($conn, "SELECT COUNT(*) total FROM dr_documents d WHERE d.status = 'Expired' AND d.category_id IN ($in)$bagianFilterSql", array_merge($authSubIds, $bagianFilterParams));
    $totalReminder = getCount($conn, "SELECT COUNT(*) total FROM dr_documents d WHERE d.status = 'Reminder' AND d.category_id IN ($in)$bagianFilterSql", array_merge($authSubIds, $bagianFilterParams));
}

/**
 * Helper to render icon (Supports FontAwesome and Iconify SVG)
 */
function renderIcon($icon, $class = "")
{
    $icon = trim($icon);
    if (empty($icon))
        return '<i class="fas fa-file ' . $class . '"></i>';
    if (strpos($icon, ':') !== false) {
        $safeIcon = str_replace(':', '_', $icon);
        $localPath = __DIR__ . "/../../includes/icons/$safeIcon.svg";
        if (file_exists($localPath)) {
            return '<span class="local-icon ' . $class . '" style="display:inline-block; width:1em; height:1em; vertical-align:middle; fill:currentColor;">' . file_get_contents($localPath) . '</span>';
        }
        return '<span class="iconify ' . $class . '" data-icon="' . htmlspecialchars($icon) . '"></span>';
    }
    return '<i class="' . htmlspecialchars($icon) . ' ' . $class . '"></i>';
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Master Dokumen Reminder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Master Dokumen Reminder</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Hero and Accordion Grid Design (Inspired by biaya_energi.php) */
        .energy-hero {
            border-radius: 15px;
            <?php
            $themeGradients = [
                'primary' => ['#007bff', '#0056b3'],
                'success' => ['#28a745', '#1e7e34'],
                'danger' => ['#dc3545', '#bd2130'],
                'warning' => ['#ffc107', '#d39e00'],
                'info' => ['#17a2b8', '#117a8b'],
                'secondary' => ['#6c757d', '#545b62'],
                'dark' => ['#343a40', '#1d2124'],
                'indigo' => ['#6610f2', '#4b0082'],
                'navy' => ['#001f3f', '#000f1f'],
                'teal' => ['#20c997', '#158c67'],
                'orange' => ['#fd7e14', '#c25807'],
                'light' => ['#f8f9fa', '#dae0e5'],
                'white' => ['#ffffff', '#f1f3f5']
            ];
            $heroTheme = $themeGradients[$theme] ?? $themeGradients['primary'];
            $colorStart = $heroTheme[0];
            $colorEnd = $heroTheme[1];
            $textColor = in_array($theme, ['light', 'white'], true) ? '#212529' : '#fff';
            $subtextColor = in_array($theme, ['light', 'white'], true) ? '#495057' : 'rgba(255,255,255,0.9)';
            ?>
            background: linear-gradient(135deg,
                    <?= $colorStart ?>
                    0%,
                    <?= $colorEnd ?>
                    100%);
            color:
                <?= $textColor ?>
                !important;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            border: none;
        }

        .energy-hero-main p {
            color:
                <?= $subtextColor ?>
                !important;
        }

        .energy-hero-body {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: space-between;
            align-items: center;
        }

        .energy-hero-main {
            flex: 1 1 360px;
            min-width: 280px;
        }

        .energy-hero-main .hero-title {
            font-weight: 700;
            margin-bottom: 2px;
        }

        .energy-hero-side {
            flex: 0 1 520px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .energy-hero-stats {
            display: flex;
            gap: 10px;
            width: 100%;
            justify-content: flex-end;
        }

        .energy-hero .small-box {
            background:
                <?= $textColor === '#212529' ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.12)' ?>
            ;
            border: 1px solid
                <?= $textColor === '#212529' ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.25)' ?>
            ;
            border-radius: 8px;
            padding: 8px 12px;
            min-width: 110px;
            height: 68px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .energy-hero .small-box .num {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.1;
        }

        .energy-hero .small-box .label {
            font-size: 11px;
            opacity: 0.9;
            margin-top: 2px;
            color:
                <?= $textColor === '#212529' ? '#495057' : '#fff' ?>
            ;
        }

        .energy-group-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .energy-group {
            border: 1px solid #e5eaf0;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .energy-group summary {
            list-style: none;
            cursor: pointer;
            outline: none;
        }

        .energy-group summary::-webkit-details-marker {
            display: none;
        }

        .energy-group-summary {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            color: #fff;
            transition: background 0.2s ease;
        }

        .energy-group:not([open]) .energy-group-summary {
            border-bottom: 0;
        }

        .energy-group-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .energy-group-meta {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            min-width: 0;
        }

        .energy-group-right {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 13px;
        }

        .energy-group-arrow {
            transition: transform 0.2s ease;
        }

        .energy-group[open] .energy-group-arrow {
            transform: rotate(180deg);
        }

        .energy-subgrid .energy-card {
            display: block;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 16px;
            background: #ffffff;
            color: #212529;
            text-decoration: none;
            height: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        }

        .energy-subgrid .energy-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            border-color: #dee2e6;
        }

        .local-icon svg {
            width: 100%;
            height: 100%;
            fill: currentColor;
            display: block;
        }

        .hover-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
        }

        .document-table th,
        .document-table td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .document-table th:first-child,
        .document-table td:first-child {
            width: 45px !important;
            text-align: center;
        }

        .document-table .action-button-group {
            display: inline-flex;
            gap: 4px;
        }

        .document-table .btn-sm {
            width: 30px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .dr-open-category-pending #categoryGridSection {
            display: none !important;
        }

        .dr-open-category-pending #tableViewSection {
            display: block !important;
        }
    </style>

    <script>
        if (sessionStorage.getItem('dr_open_category_after_reload')) {
            document.documentElement.classList.add('dr-open-category-pending');
        }
    </script>

    <section class="content">
        <div class="container-fluid">

            <!-- SECTION: GRID HIERARCHICAL KATEGORI -->
            <div id="categoryGridSection">
                <!-- Hero Banner Stats -->
                <div class="card energy-hero mb-4">
                    <div class="card-body energy-hero-body py-4 px-4">
                        <div class="energy-hero-main">
                            <h3 class="hero-title"><i class="fas fa-layer-group mr-2"></i>Dokumen Reminder</h3>
                            <p class="mb-0 opacity-90">Pilih sub-kategori dokumen di bawah untuk monitor,
                                memperbarui tanggal, dan menginput berkas.</p>
                        </div>
                        <div class="energy-hero-side">
                            <div class="energy-hero-stats">
                                <div class="small-box">
                                    <div class="num text-warning"><?= $totalReminder ?></div>
                                    <div class="label">Reminder</div>
                                </div>
                                <div class="small-box">
                                    <div class="num text-danger"><?= $totalExpired ?></div>
                                    <div class="label">Kadaluarsa</div>
                                </div>
                                <div class="small-box">
                                    <div class="num text-success"><?= $totalDocs ?></div>
                                    <div class="label">Total Dokumen</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Global Search -->
                <div class="row justify-content-center mb-4">
                    <div class="col-md-8">
                        <div class="input-group input-group-lg shadow-sm"
                            style="border-radius: 50px; overflow: hidden; border: 2px solid #2a5298; background-color: #fff;">
                            <input type="text" class="form-control border-0" id="globalSearchInput"
                                placeholder="Pencarian Global: Masukkan Kategori Atau Apapun Yang Berkaitan Dengan Dokumen..."
                                style="padding-left: 25px; outline: none; box-shadow: none;">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-white border-0 px-3 d-none" id="btnResetSearch"
                                    style="color: #aaa; background-color: #fff;" title="Reset Pencarian">
                                    <i class="fas fa-times-circle fa-lg"></i>
                                </button>
                                <button type="button" class="btn btn-primary border-0 px-4" id="btnGlobalSearch"
                                    style="background: #2a5298;">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hasil Pencarian -->
                <div id="globalSearchResults" class="card d-none mb-4 shadow">
                    <div class="card-header text-white" style="background: #2a5298;">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-search mr-2"></i>Hasil Pencarian</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool text-white" id="btnCloseSearch"><i
                                    class="fas fa-times fa-lg"></i></button>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-sm document-table m-0" id="tableGlobalSearch"
                            style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kategori</th>
                                    <th>Identitas Dokumen</th>
                                    <th>Info Tambahan</th>
                                    <th>Kadaluarsa</th>
                                    <th class="text-center" width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="globalSearchBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Ketik kata kunci untuk
                                        mencari...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Dynamic Accordions Group List -->
                <?php if (empty($parentCategories)): ?>
                    <div class="text-center py-5 bg-white border rounded shadow-sm">
                        <i class="fas fa-lock fa-4x text-muted mb-3"></i>
                        <h4 class="font-weight-bold text-dark">Akses Terbatas</h4>
                        <p class="text-muted">Anda belum terdaftar atau tidak memiliki akses Trustee ke kategori dokumen
                            mana pun.</p>
                    </div>
                <?php else: ?>
                    <div class="energy-group-list">
                        <?php foreach ($parentCategories as $parent): ?>
                            <details class="energy-group" open>
                                <summary class="energy-group-summary bg-<?= htmlspecialchars($parent['color']) ?>">
                                    <span class="energy-group-icon"><i
                                            class="fas <?= htmlspecialchars($parent['icon']) ?>"></i></span>
                                    <span class="energy-group-meta">
                                        <span class="energy-group-title font-weight-bold"
                                            style="font-size: 16px;"><?= htmlspecialchars($parent['category_name']) ?></span>
                                        <span class="energy-group-subtitle text-light opacity-90 small">Grup Dokumen
                                            Utama</span>
                                    </span>
                                    <span class="energy-group-right">
                                        <span><?= count($parent['children']) ?> Sub-Kategori</span>
                                        <i class="fas fa-chevron-down energy-group-arrow"></i>
                                    </span>
                                </summary>
                                <div class="energy-group-content p-3 bg-light">
                                    <div class="row energy-subgrid">
                                        <?php foreach ($parent['children'] as $child): ?>
                                            <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                                <a href="javascript:void(0)" class="energy-card shadow-sm category-card-trigger"
                                                    data-target="dynamic-<?= $child['id'] ?>"
                                                    data-name="<?= htmlspecialchars($child['category_name']) ?>"
                                                    data-parent-name="<?= htmlspecialchars($parent['category_name']) ?>">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <span
                                                            class="text-<?= htmlspecialchars($parent['color']) ?> font-weight-bold"
                                                            style="font-size: 15px;">
                                                            <?= htmlspecialchars($child['category_name']) ?>
                                                        </span>
                                                        <span class="badge badge-light border"><?= (int) $child['doc_count'] ?>
                                                            Data</span>
                                                    </div>
                                                    <div class="small text-muted mb-2"><i
                                                            class="fas fa-layer-group mr-1"></i><?= (int) $child['field_count'] ?>
                                                        Kolom Formulir</div>
                                                    <div
                                                        class="energy-action font-weight-bold text-<?= htmlspecialchars($parent['color']) ?> small">
                                                        Buka Dokumen <i class="fas fa-arrow-right ml-1"></i>
                                                    </div>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION: TABLE VIEW (Hidden by default) -->
            <div id="tableViewSection" class="d-none shadow">
                <div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
                    <div class="card-header bg-<?= htmlspecialchars($theme) ?> text-white d-flex align-items-center justify-content-between py-3"
                        style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100%;">
                        <h3 class="card-title font-weight-bold mb-0" id="tableCategoryTitle">
                            <i class="fas fa-layer-group mr-2"></i> Data Dokumen
                        </h3>
                        <div class="ml-auto">
                            <button type="button" class="btn btn-light btn-sm font-weight-bold shadow-sm"
                                onclick="showCategoryGrid()">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Kategori
                            </button>
                        </div>
                    </div>
                    <div class="card-body bg-white p-0">
                        <div class="tab-content" id="masterTabsContent">
                            <!-- DYNAMIC TAB PANES -->
                            <?php foreach ($subCategories as $dcat): ?>
                                <div class="tab-pane fade <?= ($activeTab == 'dynamic_' . $dcat['id']) ? 'show active' : '' ?> p-3"
                                    id="content-dynamic-<?= $dcat['id'] ?>" role="tabpanel">
                                    <div class="row mb-3 align-items-center">
                                        <div class="col-12 text-right">
                                            <?php if ($dcat['perms']['CanAdd'] == 1): ?>
                                                <button class="btn btn-success btn-sm font-weight-bold shadow-sm"
                                                    data-toggle="modal" data-target="#modalTambahDynamic"
                                                    onclick="setDynamicCategory(<?= $dcat['id'] ?>, '<?= htmlspecialchars($dcat['category_name']) ?>')">
                                                    <i class="fas fa-plus mr-1"></i> Tambah
                                                    <?= htmlspecialchars($dcat['category_name']) ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table id="tableDynamic<?= $dcat['id'] ?>"
                                            class="table table-hover table-sm document-table dynamic-table bg-white"
                                            style="width:100%">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="text-center" width="50">No</th>
                                                    <?php
                                                    // Fetch columns for table
                                                    $stmtFields = sqlsrv_query($conn, "SELECT field_label FROM dr_fields WHERE category_id = ? AND is_show_on_table = 1 ORDER BY sort_order ASC", [$dcat['id']]);
                                                    if ($stmtFields) {
                                                        while ($rf = sqlsrv_fetch_array($stmtFields, SQLSRV_FETCH_ASSOC)) {
                                                            echo '<th>' . htmlspecialchars($rf['field_label']) . '</th>';
                                                        }
                                                    }
                                                    ?>
                                                    <th width="100" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Get columns schema
                                                $showFields = [];
                                                $stmtF = sqlsrv_query($conn, "SELECT id, field_name FROM dr_fields WHERE category_id = ? AND is_show_on_table = 1 ORDER BY sort_order ASC", [$dcat['id']]);
                                                if ($stmtF) {
                                                    while ($rF = sqlsrv_fetch_array($stmtF, SQLSRV_FETCH_ASSOC)) {
                                                        $showFields[] = $rF;
                                                    }
                                                }

                                                // Get Documents in Category
                                                $stmtDocs = sqlsrv_query($conn, "SELECT d.id, d.expire_date, d.status, d.email_reminder, d.no_whatsapp, b.bagian AS nama_bagian FROM dr_documents d LEFT JOIN dbo.m_bag b ON d.bagian_id = b.id_bag WHERE d.category_id = ?$bagianFilterSql ORDER BY d.expire_date ASC", array_merge([$dcat['id']], $bagianFilterParams));
                                                if ($stmtDocs) {
                                                    $no = 1;
                                                    while ($rDoc = sqlsrv_fetch_array($stmtDocs, SQLSRV_FETCH_ASSOC)) {
                                                        // Fetch EAV field values
                                                        $docValues = [];
                                                        $stmtVal = sqlsrv_query($conn, "SELECT field_id, field_value FROM dr_doc_values WHERE document_id = ?", [$rDoc['id']]);
                                                        if ($stmtVal) {
                                                            while ($rVal = sqlsrv_fetch_array($stmtVal, SQLSRV_FETCH_ASSOC)) {
                                                                $docValues[$rVal['field_id']] = $rVal['field_value'];
                                                            }
                                                        }

                                                        // Fetch attached files
                                                        $docFiles = [];
                                                        $stmtFile = sqlsrv_query($conn, "SELECT file_path, file_name FROM dr_doc_files WHERE document_id = ?", [$rDoc['id']]);
                                                        if ($stmtFile) {
                                                            while ($rFile = sqlsrv_fetch_array($stmtFile, SQLSRV_FETCH_ASSOC)) {
                                                                $docFiles[] = $rFile;
                                                            }
                                                        }

                                                        echo '<tr>';
                                                        echo '<td class="text-center align-middle">' . $no++ . '</td>';

                                                        // Render Dynamic EAV Cells
                                                        foreach ($showFields as $f) {
                                                            if ($f['field_name'] === 'system_expire_date') {
                                                                $expDate = $rDoc['expire_date'] ? $rDoc['expire_date']->format('d/m/Y') : '-';
                                                                $badgeClass = 'success';
                                                                if ($rDoc['status'] === 'Expired')
                                                                    $badgeClass = 'danger';
                                                                elseif ($rDoc['status'] === 'Reminder')
                                                                    $badgeClass = 'warning';
                                                                echo '<td class="align-middle"><span class="badge badge-' . $badgeClass . ' px-2 py-1" style="font-size:12px;">' . $expDate . '</span></td>';
                                                            } elseif ($f['field_name'] === 'system_file_dokumen') {
                                                                echo '<td class="text-center align-middle">';
                                                                if (!empty($docFiles)) {
                                                                    foreach ($docFiles as $df) {
                                                                        echo '<div class="mb-1"><a href="javascript:void(0)" onclick="openPreview(\'' . htmlspecialchars($df['file_path']) . '\')" class="text-primary d-block" style="text-decoration:none;" title="' . htmlspecialchars($df['file_name']) . '">';
                                                                        echo '<i class="fas fa-eye fa-lg"></i><br><small>Lihat</small>';
                                                                        echo '</a></div>';
                                                                    }
                                                                } else {
                                                                    echo '-';
                                                                }
                                                                echo '</td>';
                                                            } elseif ($f['field_name'] === 'system_email_reminder') {
                                                                $val = $rDoc['email_reminder'] ?: '-';
                                                                echo '<td class="align-middle">' . htmlspecialchars($val) . '</td>';
                                                            } elseif ($f['field_name'] === 'system_no_whatsapp') {
                                                                $val = $rDoc['no_whatsapp'] ?: '-';
                                                                echo '<td class="align-middle">' . htmlspecialchars($val) . '</td>';
                                                            } elseif ($f['field_name'] === 'system_bagian') {
                                                                $val = $rDoc['nama_bagian'] ?: '-';
                                                                echo '<td class="align-middle">' . htmlspecialchars($val) . '</td>';
                                                            } else {
                                                                $val = $docValues[$f['id']] ?? '-';
                                                                echo '<td class="align-middle">' . htmlspecialchars($val) . '</td>';
                                                            }
                                                        }

                                                        // Dynamic Action Row Button Actions
                                                        echo '<td class="text-center align-middle" style="white-space: nowrap;"><div class="action-button-group">';
                                                        echo '<button class="btn btn-sm btn-info btn-detail-dynamic" data-id="' . $rDoc['id'] . '" title="Lihat Detail"><i class="fas fa-eye"></i></button>';
                                                        if ($dcat['perms']['CanEdit'] == 1) {
                                                            echo '<button class="btn btn-sm btn-warning btn-edit-dynamic" data-id="' . $rDoc['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>';
                                                            echo '<button class="btn btn-sm btn-success btn-renew-dynamic" data-id="' . $rDoc['id'] . '" title="Perpanjang Dokumen"><i class="fas fa-sync-alt"></i></button>';
                                                        }
                                                        if ($dcat['perms']['CanDelete'] == 1) {
                                                            echo '<button class="btn btn-sm btn-danger btn-delete-dynamic" data-id="' . $rDoc['id'] . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                                                        }
                                                        echo '</div></td>';
                                                        echo '</tr>';
                                                    }
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- COMMON DYNAMIC MODALS -->
<?php include __DIR__ . '\modal_tambah_dynamic.php'; ?>

<!-- COMMON RENEWAL MODAL -->
<div class="modal fade" id="modalRenewDocument" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formRenewDocument" enctype="multipart/form-data">
                <div class="modal-header bg-<?= htmlspecialchars($theme) ?>">
                    <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-sync-alt mr-1"></i> Perpanjang
                        Dokumen</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body bg-white p-4">
                    <input type="hidden" name="action" value="renew_document">
                    <input type="hidden" name="document_type" id="renew_document_type" value="dynamic">
                    <input type="hidden" name="doc_id" id="renew_doc_id" value="">

                    <div class="form-group">
                        <label class="font-weight-bold text-danger">Tanggal Expire Baru <span
                                class="text-danger">*</span></label>
                        <input type="date" name="new_expire_date" id="renew_expire_date"
                            class="form-control border-danger" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Upload File Baru (Opsional)</label>
                        <input type="file" name="new_file[]" multiple class="form-control-file"
                            accept="application/pdf,image/jpeg,image/png">
                        <small class="text-muted">Format didukung: PDF, JPG, PNG. Kosongkan jika tidak ada file
                            baru.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Catatan Perpanjangan (Opsional)</label>
                        <textarea name="remarks" class="form-control" rows="2"
                            placeholder="Contoh: Diperpanjang untuk periode berikutnya"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold px-4" id="btnSaveRenew"><i
                            class="fas fa-save mr-1"></i> Simpan Perpanjangan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- COMMON DETAIL MODAL -->
<div class="modal fade" id="modalDetailCommon" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-<?= htmlspecialchars($theme) ?>">
                <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-info-circle mr-1"></i> Detail
                    Informasi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body bg-white p-4" id="detailBodyCommon">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-3x text-<?= htmlspecialchars($theme) ?>"></i>
                    <p class="mt-2 text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- IFRAME PREVIEW MODAL -->
<div class="modal fade" id="modalPreview" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 90%;">
        <div class="modal-content shadow-lg style=" border: none; border-radius: 8px; overflow: hidden;">
            <div class="modal-body p-0" style="height: 90vh; background-color: #525659;">
                <iframe id="previewIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>

<!-- DataTables & Plugins -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
    function openPreview(path) {
        const viewerUrl = 'pdf_viewer.php?file=' + encodeURIComponent(path);
        $('#previewIframe').attr('src', viewerUrl);
        $('#modalPreview').modal('show');
    }

    $('#modalPreview').on('hidden.bs.modal', function () {
        $('#previewIframe').attr('src', '');
    });

    $(function () {
        // Config DataTables
        const dtConfig = {
            responsive: {
                details: {
                    type: 'inline',
                    target: 'tr'
                }
            },
            autoWidth: false,
            columnDefs: [
                { orderable: false, responsivePriority: 1, width: '38px', targets: 0 },
                { responsivePriority: 2, targets: -1 }
            ],
            language: {
                processing: "Sedang memproses...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                paginate: { first: "Awal", last: "Akhir", next: "→", previous: "←" }
            }
        };

        $('.dynamic-table').DataTable(dtConfig);

        const categoryStateKey = 'dr_current_category';
        const pendingCategoryStateKey = 'dr_open_category_after_reload';

        function getCategoryName(targetId) {
            const card = $('.category-card-trigger[data-target="' + targetId + '"]');
            return card.length ? card.data('name') : 'Dokumen';
        }

        function getParentCategoryName(targetId) {
            const card = $('.category-card-trigger[data-target="' + targetId + '"]');
            return card.length ? card.data('parent-name') : 'Kategori';
        }

        function getCurrentCategory() {
            const activePane = $('#masterTabsContent .tab-pane.show.active').attr('id') || '';
            if (activePane.indexOf('content-') === 0) {
                return activePane.replace('content-', '');
            }
            return sessionStorage.getItem(categoryStateKey) || '';
        }

        function reopenCategory(targetId) {
            if (!targetId) return;

            const targetName = getCategoryName(targetId);
            const parentName = getParentCategoryName(targetId);

            $('#categoryGridSection').addClass('d-none');
            $('#tableViewSection').removeClass('d-none');
            $('#tableCategoryTitle').html('<i class="fas fa-layer-group mr-2"></i> Data Dokumen: ' + parentName + ' &gt; ' + targetName);

            $('.tab-pane').removeClass('show active');
            $('#content-' + targetId).addClass('show active');
            sessionStorage.setItem(categoryStateKey, targetId);

            const childId = targetId.replace('dynamic-', '');
            const tableId = '#tableDynamic' + childId;

            if ($.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable().columns.adjust().responsive.recalc();
            }
        }

        function reloadCurrentCategory() {
            const currentCategory = getCurrentCategory();
            if (currentCategory) {
                sessionStorage.setItem(pendingCategoryStateKey, currentCategory);
            }
            location.reload();
        }

        const urlParams = new URLSearchParams(window.location.search);
        const catParam = urlParams.get('category_id');
        const pendingCategory = sessionStorage.getItem(pendingCategoryStateKey);
        
        if (catParam) {
            reopenCategory(catParam);
            document.documentElement.classList.remove('dr-open-category-pending');
        } else if (pendingCategory) {
            sessionStorage.removeItem(pendingCategoryStateKey);
            reopenCategory(pendingCategory);
            document.documentElement.classList.remove('dr-open-category-pending');
        }

        // Ajax Submit Form
        initAjaxForm('#formTambahDynamic', 'ajax_handler.php');

        function initAjaxForm(formId, url) {
            $(formId).on('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(this);
                const btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: fd,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message }).then(() => reloadCurrentCategory());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                            btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                        }
                    },
                    error: function () {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Terjadi kesalahan sistem.' });
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                    }
                });
            });
        }

        // Ajax Delete Handler (Dynamic Only)
        $(document).on('click', '.btn-delete-dynamic', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: 'Hapus Dokumen?',
                text: "Data dan file fisik akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_handler.php',
                        type: 'POST',
                        data: { action: 'delete_document', id: id },
                        dataType: 'json',
                        success: function (res) {
                            if (res.status === 'success') {
                                Swal.fire('Terhapus!', res.message, 'success').then(() => reloadCurrentCategory());
                            } else {
                                Swal.fire('Gagal!', res.message, 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error!', 'Koneksi ke server gagal.', 'error');
                        }
                    });
                }
            });
        });

        // Detail Handler
        $(document).on('click', '.btn-detail-dynamic', function () {
            const id = $(this).data('id');
            $('#detailBodyCommon').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-<?= $theme ?>"></i><p class="mt-2 text-muted">Memuat data...</p></div>');
            $('#modalDetailCommon').modal('show');
            $.get('ajax_handler.php', { action: 'get_detail', id: id }, function (data) {
                $('#detailBodyCommon').html(data);
            });
        });

        // Edit Handler
        $(document).on('click', '.btn-edit-dynamic', function () {
            const id = $(this).data('id');
            $('#dynamicModalTitle').html('<i class="fas fa-edit mr-2"></i>Edit Dokumen');
            $('#dynamicFormContainer').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-3x text-<?= $theme ?>"></i><p class="mt-2 text-muted">Memuat form...</p></div>');
            $('#modalTambahDynamic').modal('show');
            $.get('ajax_handler.php', { action: 'get_edit_form', id: id }, function (data) {
                $('#dynamicFormContainer').html(data);
                $('#formTambahDynamic').find('[name="action"]').val('update_document');
                if (!$('#formTambahDynamic').find('[name="doc_id"]').length) {
                    $('<input type="hidden" name="doc_id">').val(id).appendTo('#formTambahDynamic');
                } else {
                    $('#formTambahDynamic').find('[name="doc_id"]').val(id);
                }
            });
        });

        // UI Navigation Logic
        $('.category-card-trigger').on('click', function () {
            const targetId = $(this).data('target');
            reopenCategory(targetId);
        });

        window.showCategoryGrid = function () {
            $('#tableViewSection').addClass('d-none');
            $('#categoryGridSection').removeClass('d-none');
            sessionStorage.removeItem(categoryStateKey);
            sessionStorage.removeItem(pendingCategoryStateKey);
            $('#globalSearchResults').addClass('d-none');
            $('#globalSearchInput').val('').trigger('input');
        };

        // Renewal Logic
        $(document).on('click', '.btn-renew-dynamic', function () {
            const id = $(this).data('id');
            $('#renew_doc_id').val(id);
            $('#renew_document_type').val('dynamic');
            $('#formRenewDocument')[0].reset();
            $('#modalRenewDocument').modal('show');
        });

        $('#formRenewDocument').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#btnSaveRenew');
            btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...').prop('disabled', true);

            const formData = new FormData(this);
            $.ajax({
                url: 'ajax_handler.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function (res) {
                    btn.html('<i class="fas fa-save mr-1"></i> Simpan Perpanjangan').prop('disabled', false);
                    if (res.status === 'success') {
                        $('#modalRenewDocument').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => reloadCurrentCategory());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function () {
                    btn.html('<i class="fas fa-save mr-1"></i> Simpan Perpanjangan').prop('disabled', false);
                    Swal.fire('Error', 'Terjadi kesalahan jaringan/server.', 'error');
                }
            });
        });

        // Global Search Logic
        $('#btnGlobalSearch').on('click', performGlobalSearch);
        $('#globalSearchInput').on('keypress', function (e) {
            if (e.which == 13) performGlobalSearch();
        });

        $('#globalSearchInput').on('input', function () {
            const val = $(this).val().trim().toLowerCase();
            if (val.length > 0) {
                $('#btnResetSearch').removeClass('d-none');

                // Live filter categories on grid view
                $('.category-card-trigger').each(function () {
                    const cardName = $(this).data('name').toLowerCase();
                    const parentName = $(this).data('parent-name').toLowerCase();
                    if (cardName.includes(val) || parentName.includes(val)) {
                        $(this).closest('.col-sm-6').removeClass('d-none');
                    } else {
                        $(this).closest('.col-sm-6').addClass('d-none');
                    }
                });
            } else {
                $('#btnResetSearch').addClass('d-none');
                $('#globalSearchResults').addClass('d-none');
                $('.category-card-trigger').closest('.col-sm-6').removeClass('d-none');
            }
        });

        $('#btnResetSearch').on('click', function () {
            $('#globalSearchInput').val('').trigger('input');
            $('#globalSearchResults').addClass('d-none');
        });

        $('#btnCloseSearch').on('click', function () {
            $('#globalSearchResults').addClass('d-none');
            $('#globalSearchInput').val('').trigger('input');
        });

        function performGlobalSearch() {
            const q = $('#globalSearchInput').val().trim();
            if (q.length < 3) {
                Swal.fire('Info', 'Masukkan minimal 3 karakter untuk melakukan pencarian.', 'info');
                return;
            }

            $('#globalSearchBody').html('<tr><td colspan="5" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><br>Mencari dokumen di semua database...</td></tr>');
            $('#globalSearchResults').removeClass('d-none');

            $.ajax({
                url: 'ajax_handler.php',
                type: 'POST',
                data: { action: 'global_search', keyword: q },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        if (res.data.length > 0) {
                            let html = '';
                            res.data.forEach(item => {
                                html += `<tr>
                                    <td><span class="badge badge-${item.color} px-3 py-2" style="font-size:12px; border-radius:10px;">${item.kategori}</span></td>
                                    <td><strong>${item.identitas}</strong></td>
                                    <td>${item.info || '-'}</td>
                                    <td><span class="badge badge-light border">${item.expire || '-'}</span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary ${item.btn_class}" data-id="${item.id}" title="Lihat Detail">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </button>
                                    </td>
                                </tr>`;
                            });
                            $('#globalSearchBody').html(html);
                        } else {
                            $('#globalSearchBody').html('<tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-folder-open fa-3x mb-3" style="opacity:0.3;"></i><br><h4>Pencarian Nihil</h4><p>Tidak ada dokumen yang cocok dengan kata kunci "<b>' + htmlspecialchars(q) + '</b>".</p></td></tr>');
                        }
                    } else {
                        $('#globalSearchBody').html(`<tr><td colspan="5" class="text-center text-danger py-4">Error: ${res.message}</td></tr>`);
                    }
                },
                error: function () {
                    $('#globalSearchBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Terjadi kesalahan koneksi saat mencari data.</td></tr>');
                }
            });
        }

        function htmlspecialchars(str) {
            if (typeof (str) == "string") {
                str = str.replace(/&/g, "&amp;");
                str = str.replace(/"/g, "&quot;");
                str = str.replace(/'/g, "&#039;");
                str = str.replace(/</g, "&lt;");
                str = str.replace(/>/g, "&gt;");
            }
            return str;
        }
    });
</script>