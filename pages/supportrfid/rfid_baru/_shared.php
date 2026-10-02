<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_mongo.php';
require_once __DIR__ . '/../includes/access_helper.php';

function coba1_support_username(): string
{
    return trim((string) ($_SESSION['username'] ?? $_SESSION['UserName'] ?? ''));
}

function coba1_support_group_id()
{
    $username = coba1_support_username();
    return $username !== '' ? getUserGroup($username) : null;
}

function coba1_has_menu_access(string $menuId): bool
{
    $groupId = coba1_support_group_id();
    return $groupId === null ? true : canAccessMenu($groupId, $menuId);
}

function coba1_has_button_access(string $buttonId): bool
{
    $groupId = coba1_support_group_id();
    return $groupId === null ? true : canAccessButton($groupId, $buttonId);
}

function coba1_mongo_collection(string $name)
{
    global $client;
    return $client->api_sum->{$name};
}

function coba1_parse_keywords(string $text): array
{
    $text = str_replace(';', ',', trim($text));
    if ($text === '') {
        return [];
    }

    $keywords = preg_split('/[\r\n,]+/', $text);
    $keywords = array_map('trim', $keywords ?: []);

    return array_values(array_filter($keywords, static fn ($value) => $value !== ''));
}

function coba1_detect_mode(string $keyword): string
{
    $keyword = trim($keyword);
    if ($keyword === '') {
        return 'baleno';
    }

    if (preg_match('/^\d+\.\d+$/', $keyword)) {
        return 'packingno';
    }

    if (preg_match('/[A-Za-z]\d{2,}/', $keyword) && substr_count($keyword, '.') >= 2) {
        return 'batchno';
    }

    return 'baleno';
}

function coba1_warehouses(): array
{
    return [
        ['wrhscode' => '103', 'wrhsname' => 'GUDANG KAIN JADI 2', 'wrhsrunid' => '03', 'wrhsid' => 350],
        ['wrhscode' => '193', 'wrhsname' => 'GUDANG SAMPLE SUM', 'wrhsrunid' => '12', 'wrhsid' => 314],
        ['wrhscode' => '111', 'wrhsname' => 'GUDANG EX-WARNA', 'wrhsrunid' => '10', 'wrhsid' => 361],
        ['wrhscode' => '103B', 'wrhsname' => 'GUDANG JADI B GRADE', 'wrhsrunid' => '03', 'wrhsid' => 352],
        ['wrhscode' => '112', 'wrhsname' => 'GUDANG TRANSIT VERPACKING', 'wrhsrunid' => '10', 'wrhsid' => 362],
        ['wrhscode' => '190', 'wrhsname' => 'GUDANG SAMPLE', 'wrhsrunid' => '11', 'wrhsid' => 371],
        ['wrhscode' => '103A', 'wrhsname' => 'GUDANG KAIN JADI 1', 'wrhsrunid' => '03', 'wrhsid' => 351],
        ['wrhscode' => '103C', 'wrhsname' => 'GUDANG KAIN JADI 3', 'wrhsrunid' => '03', 'wrhsid' => 412],
        ['wrhscode' => '173', 'wrhsname' => 'GUDANG SBY BUNDLING', 'wrhsrunid' => '17', 'wrhsid' => 391],
        ['wrhscode' => '172-C', 'wrhsname' => 'GUDANG SBY 27', 'wrhsrunid' => '17', 'wrhsid' => 411],
        ['wrhscode' => '172-B', 'wrhsname' => 'GUDANG SBY 22', 'wrhsrunid' => '17', 'wrhsid' => 410],
        ['wrhscode' => '172-A', 'wrhsname' => 'GUDANG TRANSIT SBY', 'wrhsrunid' => '17', 'wrhsid' => 409],
        ['wrhscode' => '172', 'wrhsname' => 'GUDANG SBY', 'wrhsrunid' => '17', 'wrhsid' => 310],
    ];
}

function coba1_regex(string $keyword, bool $wordBoundary = true): MongoDB\BSON\Regex
{
    $pattern = $wordBoundary ? '\\b' . preg_quote($keyword, '/') . '\\b' : '^' . preg_quote($keyword, '/') . '$';
    return new MongoDB\BSON\Regex($pattern, 'i');
}

function coba1_layout_start(string $title): void
{
    global $conn;

    if (empty($_SESSION['UserId']) && !empty($_SESSION['logged_in'])) {
        $_SESSION['UserId'] = 1;
        $_SESSION['GroupId'] = $_SESSION['GroupId'] ?? 1;
        $_SESSION['UserName'] = $_SESSION['UserName'] ?? ($_SESSION['username'] ?? 'Support');
        $_SESSION['Theme'] = $_SESSION['Theme'] ?? 'primary';
    }

    require_once __DIR__ . '/../../../koneksi.php';
    include __DIR__ . '/../../../includes/header.php';
    include __DIR__ . '/../../../includes/sidebar.php';

    echo '<div class="content-wrapper">';
    echo '<section class="content pt-3">';
    echo '<div class="container-fluid">';
    echo '<div class="d-flex flex-wrap gap-2 mb-3">';
    echo '<a class="btn btn-outline-primary btn-sm" href="dashboard_picking.php">Picking</a>';
    echo '<a class="btn btn-outline-primary btn-sm" href="dashboard_multi_update.php">Multi Update</a>';
    echo '<a class="btn btn-outline-primary btn-sm" href="dashboard_cp_batch.php">CP Batch</a>';
    echo '<a class="btn btn-outline-primary btn-sm" href="process_upload.php">Upload</a>';
    echo '</div>';
    echo '<div class="content-header mb-3"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">' . htmlspecialchars($title) . '</h1></div></div></div></div>';
}

function coba1_layout_end(): void
{
    global $conn;

    echo '</div></section></div>';
    include __DIR__ . '/../../../includes/footer.php';
}

