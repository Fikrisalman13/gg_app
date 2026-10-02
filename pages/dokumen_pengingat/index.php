<?php
// ======================================================
// index.php — Dashboard Dokumen Pengingat (DYNAMIC HIERARCHY)
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

// ------------------------------------------------------
// MAIN PAGE ACCESS CONTROL
// ------------------------------------------------------
requireView($conn, 166);

$username = $_SESSION['UserName'] ?? null;

// ------------------------------------------------------
// TRUSTEE ACCESS CONTROL & SUPER ADMIN CHECK
// ------------------------------------------------------
$isSuperAdmin = false;
$permTrustee = userPermissions($conn, 1286); // 1286 = Menu Manage Trustees
if ($permTrustee['CanView'] == 1) {
    $isSuperAdmin = true;
}

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

function checkTrustee($catKey, $myTrustees, $isSuperAdmin) {
    if ($isSuperAdmin) {
        return ['CanView' => 1, 'CanEdit' => 1, 'CanDelete' => 1, 'CanAdd' => 1];
    }
    $res = $myTrustees[$catKey] ?? ['CanView' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    $res['CanAdd'] = $res['CanEdit'] ?? 0;
    return $res;
}

// Helper to get database count
function getCount($conn, string $sql, array $params = []): int
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt)
        return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int) ($row['total'] ?? 0);
}

// ─── DYNAMIC STATISTICS ENGINE ───
// 1. Ambil seluruh Sub-Kategori (Child) dinamis
$subCategories = [];
$stmtCats = sqlsrv_query($conn, "
    SELECT c.id, c.category_name, c.reminder_interval,
           p.id AS parent_id, p.category_name AS parent_name, p.color AS parent_color, p.icon AS parent_icon
    FROM dr_categories c
    JOIN dr_categories p ON c.parent_id = p.id
    WHERE c.parent_id IS NOT NULL
");
if ($stmtCats) {
    while ($rc = sqlsrv_fetch_array($stmtCats, SQLSRV_FETCH_ASSOC)) {
        if ($rc['parent_color'] === 'user') {
            $rc['parent_color'] = $theme;
        }
        $dynKey = 'dynamic_' . $rc['id'];
        $permDyn = checkTrustee($dynKey, $myTrustees, $isSuperAdmin);
        
        if ($permDyn['CanView'] == 1) {
            $rc['perms'] = $permDyn;
            $subCategories[] = $rc;
        }
    }
}

// 2. Akumulasikan statistik dokumen berdasarkan Kategori Utama (Parent)
$parentStats = [];
foreach ($subCategories as $sub) {
    $pId = $sub['parent_id'];
    if (!isset($parentStats[$pId])) {
        $parentStats[$pId] = [
            'name' => $sub['parent_name'],
            'icon' => $sub['parent_icon'] ?: 'fas fa-file',
            'color' => $sub['parent_color'] ?: $theme,
            'aktif' => 0,
            'reminder' => 0,
            'expired' => 0,
            'total' => 0,
            'first_child_id' => $sub['id']
        ];
    }
    
    $catId = $sub['id'];
    $interval = (int)$sub['reminder_interval'];
    
    $aktif = getCount($conn, "SELECT COUNT(*) total FROM dr_documents WHERE category_id = ? AND expire_date >= CAST(GETDATE() AS DATE)", [$catId]);
    $reminder = getCount($conn, "SELECT COUNT(*) total FROM dr_documents WHERE category_id = ? AND expire_date BETWEEN CAST(GETDATE() AS DATE) AND DATEADD(DAY, ?, CAST(GETDATE() AS DATE))", [$catId, $interval]);
    $expired = getCount($conn, "SELECT COUNT(*) total FROM dr_documents WHERE category_id = ? AND expire_date < CAST(GETDATE() AS DATE)", [$catId]);
    $total = getCount($conn, "SELECT COUNT(*) total FROM dr_documents WHERE category_id = ?", [$catId]);
    
    $parentStats[$pId]['aktif'] += $aktif;
    $parentStats[$pId]['reminder'] += $reminder;
    $parentStats[$pId]['expired'] += $expired;
    $parentStats[$pId]['total'] += $total;
}
?>

<script>
    setTimeout(() => location.reload(), 60000);
</script>

<style>
    .dokumen-dashboard .small-box {
        border-radius: .25rem;
        box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    }
    .dokumen-dashboard .small-box .inner h3 {
        font-weight: 700;
    }
    .dokumen-dashboard .small-box .inner p {
        min-height: 2.4em;
        margin-bottom: 0;
    }
    .dokumen-dashboard .small-box-footer {
        font-weight: 600;
    }
    @media (max-width: 576px) {
        .dokumen-dashboard .small-box .inner h3 {
            font-size: 1.75rem;
        }
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Dashboard Dokumen Pengingat</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Dokumen Pengingat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid dokumen-dashboard">
            <div class="card card-<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?> card-outline">
                <div class="card-body">
                    <div class="row">

                <?php
                function box($color, $value, $label, $icon, $link)
                {
                    $textClass = in_array($color, ['warning', 'light', 'lime', 'white', 'orange'], true) ? 'text-dark' : 'text-white';
                    $value = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                    $label = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
                    $icon = htmlspecialchars((string)$icon, ENT_QUOTES, 'UTF-8');
                    $link = htmlspecialchars((string)$link, ENT_QUOTES, 'UTF-8');
                    $color = htmlspecialchars((string)$color, ENT_QUOTES, 'UTF-8');
                    echo "
                    <div class='col-lg-3 col-6 mb-3'>
                      <div class='small-box bg-$color $textClass'>
                        <div class='inner'>
                          <h3>$value</h3>
                          <p>$label</p>
                        </div>
                        <div class='icon'><i class='$icon'></i></div>
                        <a href='$link' class='small-box-footer'>
                          Buka Halaman <i class='fas fa-arrow-circle-right'></i>
                        </a>
                      </div>
                    </div>";
                }

                // Render Dynamic Summary Boxes Grouped by Parent Category
                if (empty($parentStats)) {
                    echo "<div class='col-12 py-5 text-center text-muted'>";
                    echo "<i class='fas fa-lock fa-3x mb-3'></i>";
                    echo "<h5>Tidak Ada Hak Akses Kategori</h5>";
                    echo "<p>Silakan hubungi Administrator untuk memberikan akses Trustee kepada Anda.</p>";
                    echo "</div>";
                } else {
                    foreach ($parentStats as $pId => $stats) {
                        $pName = $stats['name'];
                        $pIcon = $stats['icon'];
                        $pColor = $stats['color'];
                        $targetUrl = 'master_dokumen.php?category_id=dynamic-' . $stats['first_child_id'];
                        
                        box('success', $stats['aktif'], $pName . ' Aktif', $pIcon, $targetUrl);
                        box('warning', $stats['reminder'], $pName . ' Reminder', $pIcon, $targetUrl);
                        box('danger', $stats['expired'], $pName . ' Kadaluarsa', $pIcon, $targetUrl);
                        box($pColor, $stats['total'], 'Semua ' . $pName, $pIcon, $targetUrl);
                    }
                }
                ?>

                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
