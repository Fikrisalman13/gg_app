<?php
session_start();
ob_start();
include '../../../koneksi.php';
include '../../../koneksi3.php';
require_once __DIR__ . '/databale_helpers.php';

$isAjaxSearchBales = isset($_GET['ajax_search_bales']);
$isAjaxRotatePhoto = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rotate_photo']);

if (!isset($_SESSION['UserName'])) {
    if ($isAjaxSearchBales || $isAjaxRotatePhoto) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Session habis. Silakan login kembali.', 'rows' => []]);
        exit;
    }
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}
if (!$conn || !$conn3) {
    if ($isAjaxSearchBales || $isAjaxRotatePhoto) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.', 'rows' => [], 'errors' => sqlsrv_errors()]);
        exit;
    }
    die('Koneksi ke database gagal: ' . print_r(sqlsrv_errors(), true));
}

/**
 * Hak akses halaman Databale. Menu dicari berdasarkan URL agar tetap sesuai
 * dengan MenuId yang diatur di halaman Hak Akses Group.
 */
function dbale_normalize_menu_url(string $url): string
{
    $url = strtolower(trim(str_replace('\\', '/', $url)));
    $url = '/' . ltrim($url, '/');
    return rtrim($url, '/');
}

function dbale_page_permissions($conn): array
{
    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    $groupId = (int)($_SESSION['GroupId'] ?? 0);
    if ($groupId <= 0) {
        return $permissions;
    }

    $menuId = 0;
    $stmt = sqlsrv_query($conn, 'SELECT MenuId, MenuName, MenuUrl FROM dbo.SMMenu');
    if ($stmt !== false) {
        $pageUrls = [
            '/gg_app/pages/logistik/databale/pengebalan.php',
            '/pages/logistik/databale/pengebalan.php',
        ];
        while ($menu = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $menuUrl = dbale_normalize_menu_url((string)($menu['MenuUrl'] ?? ''));
            if (in_array($menuUrl, $pageUrls, true) || str_ends_with($menuUrl, '/pages/logistik/databale/pengebalan.php')) {
                $menuId = (int)$menu['MenuId'];
                break;
            }
            if ($menuId === 0 && strcasecmp(trim((string)($menu['MenuName'] ?? '')), 'Databale') === 0) {
                $menuId = (int)$menu['MenuId'];
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    if ($menuId <= 0) {
        return $permissions;
    }

    $stmt = sqlsrv_query(
        $conn,
        'SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?',
        [$groupId, $menuId]
    );
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $permissions = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    return $permissions;
}

$permissions = dbale_page_permissions($conn);
$canView = (int)($permissions['CanView'] ?? 0) === 1;
$canAdd = (int)($permissions['CanAdd'] ?? 0) === 1;
$canEdit = (int)($permissions['CanEdit'] ?? 0) === 1;
$canDelete = (int)($permissions['CanDelete'] ?? 0) === 1;

if (!$canView) {
    if ($isAjaxSearchBales || $isAjaxRotatePhoto) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak untuk melihat Databale.', 'rows' => []]);
        exit;
    }
    $_SESSION['error'] = 'Anda tidak memiliki hak untuk melihat Databale.';
    header('Location: /gg_app/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadMode = (string)($_POST['upload_mode'] ?? 'bale');
    $requiredPermission = null;
    if (isset($_POST['delete_bale_upload']) || isset($_POST['delete_selected_uploads'])) {
        $requiredPermission = $canDelete;
        $deniedMessage = 'Anda tidak memiliki hak untuk menghapus data Databale.';
    } elseif (isset($_POST['create_pengebalan'])) {
        $requiredPermission = $canAdd;
        $deniedMessage = 'Anda tidak memiliki hak untuk menambah data Databale.';
    } elseif (isset($_POST['save_upload'])) {
        $requiredPermission = $uploadMode === 'draft' ? $canAdd : $canEdit;
        $deniedMessage = $uploadMode === 'draft'
            ? 'Anda tidak memiliki hak untuk menambah data Databale.'
            : 'Anda tidak memiliki hak untuk mengedit data Databale.';
    } elseif (isset($_POST['rotate_photo'])) {
        $requiredPermission = $canView;
        $deniedMessage = 'Anda tidak memiliki hak untuk melihat Databale.';
    }

    if ($requiredPermission === false) {
        if ($isAjaxRotatePhoto) {
            if (ob_get_length()) { ob_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => $deniedMessage]);
            exit;
        }
        $_SESSION['error'] = $deniedMessage;
        header('Location: pengebalan.php');
        exit;
    }
}


// AJAX pencarian bale untuk modal New.
// Default hanya ambil 10 data agar halaman/modal tidak berat.
if ($isAjaxSearchBales) {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $ajaxWrhsid = trim((string)($_GET['wrhsid'] ?? ''));
    $ajaxSearch = trim((string)($_GET['q'] ?? ''));

    if ($ajaxWrhsid === '' || !ctype_digit($ajaxWrhsid)) {
        echo json_encode(['status' => 'error', 'message' => 'wrhsid tidak valid', 'rows' => []]);
        exit;
    }

    $sql = "
        SELECT DISTINCT TOP (10)
            whbalehd.balehdid,
            whbalehd.balenmbr,
            whbalehd.baledate,
            whbalehd.refnmbr,
            whbaleprod.wrhsid
        FROM whbalehd
        INNER JOIN whbaleprod
            ON whbalehd.balehdid = whbaleprod.balehdid
        WHERE whbaleprod.wrhsid = ?
    ";

    $params = [(int)$ajaxWrhsid];

    if ($ajaxSearch !== '') {
        $sql .= " AND (whbalehd.balenmbr LIKE ? OR whbalehd.refnmbr LIKE ?)";
        $like = '%' . $ajaxSearch . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY whbalehd.baledate DESC, whbalehd.balenmbr DESC";

    $stmt = sqlsrv_query($conn3, $sql, $params);
    if ($stmt === false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Query gagal',
            'rows' => [],
            'errors' => sqlsrv_errors(),
        ]);
        exit;
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $baledate = $row['baledate'] ?? null;
        if ($baledate instanceof DateTimeInterface) {
            $baledate = $baledate->format('d/m/Y');
        } elseif (!empty($baledate)) {
            $ts = strtotime((string)$baledate);
            $baledate = $ts ? date('d/m/Y', $ts) : (string)$baledate;
        } else {
            $baledate = '-';
        }

        $rows[] = [
            'balehdid' => (string)($row['balehdid'] ?? ''),
            'balenmbr' => (string)($row['balenmbr'] ?? ''),
            'refnmbr' => (string)($row['refnmbr'] ?? ''),
            'baledate' => $baledate,
            'wrhsid' => (string)($row['wrhsid'] ?? ''),
        ];
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['status' => 'success', 'rows' => $rows]);
    exit;
}

if (empty($_SESSION['pengebalan_csrf'])) {
    $_SESSION['pengebalan_csrf'] = bin2hex(random_bytes(32));
}
$pengebalanCsrf = (string)$_SESSION['pengebalan_csrf'];

if ($isAjaxRotatePhoto) {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    try {
        $postedCsrf = (string)($_POST['csrf_token'] ?? '');
        if ($postedCsrf === '' || !hash_equals($pengebalanCsrf, $postedCsrf)) {
            http_response_code(403);
            throw new RuntimeException('Token keamanan tidak valid. Silakan refresh halaman.');
        }

        $fileName = trim((string)($_POST['photo_file'] ?? ''));
        $direction = trim((string)($_POST['direction'] ?? ''));
        if ($fileName === '' || basename($fileName) !== $fileName) {
            throw new RuntimeException('Nama file foto tidak valid.');
        }
        if (!dbale_upload_file_is_referenced($conn, $fileName)) {
            throw new RuntimeException('Foto tidak terdaftar pada data pengebalan.');
        }

        $filePath = dbale_ensure_upload_dir() . DIRECTORY_SEPARATOR . $fileName;
        dbale_rotate_upload_photo($filePath, $direction);
        echo json_encode([
            'status' => 'success',
            'message' => 'Foto berhasil diputar dan disimpan.',
            'photo_file' => $fileName,
            'version' => time() . '-' . bin2hex(random_bytes(3)),
        ]);
    } catch (Throwable $e) {
        if (http_response_code() < 400) {
            http_response_code(422);
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

include '../../../includes/header.php';
include '../../../includes/sidebar.php';

date_default_timezone_set('Asia/Jakarta');
$userName = $_SESSION['UserName'];

$warehouseList = dbale_load_warehouses($conn3);
$lockedWarehouseName = 'GUDANG JADI B GRADE';
$selectedWrhsid = '';
$selectedWrhsname = $lockedWarehouseName;
foreach ($warehouseList as $warehouse) {
    if (dbale_normalize_key($warehouse['wrhsname'] ?? '') === dbale_normalize_key($lockedWarehouseName)) {
        $selectedWrhsid = (string)($warehouse['wrhsid'] ?? '');
        $selectedWrhsname = (string)($warehouse['wrhsname'] ?? $lockedWarehouseName);
        break;
    }
}
if ($selectedWrhsid === '') {
    $selectedWrhsid = trim((string)($_GET['wrhsid'] ?? ($warehouseList[0]['wrhsid'] ?? '')));
}
if ($selectedWrhsid === '' && !empty($warehouseList)) {
    $selectedWrhsid = (string)($warehouseList[0]['wrhsid'] ?? '');
}

$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$listSearch = trim((string)($_GET['list_search'] ?? ''));
$statusFilter = strtoupper(trim((string)($_GET['status'] ?? 'O')));
if (!in_array($statusFilter, ['O', 'P', 'ALL'], true)) {
    $statusFilter = 'O';
}
if ($startDate !== '' && DateTime::createFromFormat('Y-m-d', $startDate) === false) {
    $startDate = '';
}
if ($endDate !== '' && DateTime::createFromFormat('Y-m-d', $endDate) === false) {
    $endDate = '';
}
if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
    $endDate = $startDate;
}
$allowedPageSizes = range(10, 100, 10);
$listPerPage = (int)($_GET['per_page'] ?? $_POST['per_page'] ?? 10);
if (!in_array($listPerPage, $allowedPageSizes, true)) {
    $listPerPage = 10;
}
$listPage = max(1, (int)($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedWrhsid = trim((string)($_POST['wrhsid'] ?? $selectedWrhsid));
    $startDate = trim((string)($_POST['start_date'] ?? $startDate));
    $endDate = trim((string)($_POST['end_date'] ?? $endDate));
    $listSearch = trim((string)($_POST['list_search'] ?? $listSearch));
    $statusFilter = strtoupper(trim((string)($_POST['status'] ?? $statusFilter)));
    if (!in_array($statusFilter, ['O', 'P', 'ALL'], true)) {
        $statusFilter = 'O';
    }

}

foreach ($warehouseList as $warehouse) {
    if ((string)($warehouse['wrhsid'] ?? '') === $selectedWrhsid) {
        $selectedWrhsname = (string)($warehouse['wrhsname'] ?? $selectedWrhsname);
        break;
    }
}

if (empty($warehouseList)) {
    $selectedWrhsid = '';
}

$newBaleRows = [];
$newBaleIndex = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_bale_upload']) || isset($_POST['delete_selected_uploads']))) {
    $deleteBalehdids = isset($_POST['delete_selected_uploads'])
        ? (array)($_POST['delete_balehdids'] ?? [])
        : [$_POST['delete_balehdid'] ?? ''];
    $deleteBalehdids = array_values(array_unique(array_filter(array_map('trim', $deleteBalehdids))));
    $transactionStarted = false;
    $deletedPhotoNames = [];

    try {
        $postedCsrf = (string)($_POST['csrf_token'] ?? '');
        if ($postedCsrf === '' || !hash_equals($pengebalanCsrf, $postedCsrf)) {
            throw new RuntimeException('Token keamanan tidak valid. Silakan refresh halaman.');
        }
        if (empty($deleteBalehdids)) {
            throw new RuntimeException('Pilih minimal satu data bale yang akan dihapus.');
        }

        if (!sqlsrv_begin_transaction($conn)) {
            throw new RuntimeException('Gagal mulai transaksi hapus: ' . print_r(sqlsrv_errors(), true));
        }
        $transactionStarted = true;

        foreach ($deleteBalehdids as $deleteBalehdid) {
            foreach (dbale_delete_single_bale_upload($conn, $deleteBalehdid, $userName) as $deletedPhotoName) {
                $deletedPhotoNames[$deletedPhotoName] = $deletedPhotoName;
            }
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException('Gagal commit penghapusan: ' . print_r(sqlsrv_errors(), true));
        }
        $transactionStarted = false;

        $uploadDir = dbale_ensure_upload_dir();
        foreach (array_values($deletedPhotoNames) as $deletedPhotoName) {
            if (basename($deletedPhotoName) !== $deletedPhotoName || dbale_upload_file_is_referenced($conn, $deletedPhotoName)) {
                continue;
            }
            $deletedPhotoPath = $uploadDir . DIRECTORY_SEPARATOR . $deletedPhotoName;
            if (is_file($deletedPhotoPath)) {
                @unlink($deletedPhotoPath);
            }
        }
        $_SESSION['success'] = count($deleteBalehdids) . ' data bale berhasil dihapus.';
    } catch (Throwable $e) {
        if ($transactionStarted) {
            @sqlsrv_rollback($conn);
        }
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: pengebalan.php?' . http_build_query([
        'wrhsid' => $selectedWrhsid,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'list_search' => $listSearch,
        'status' => $statusFilter,
        'tab' => 'list',
    ]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pengebalan'])) {
    $selectedBalehdids = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['selected_balehdids'] ?? [])))));

    try {
        if ($selectedWrhsid === '') {
            throw new RuntimeException('Gudang tidak valid.');
        }
        if (empty($selectedBalehdids)) {
            throw new RuntimeException('Pilih minimal 1 balenmbr.');
        }

        $uploadedBalenmbrSet = dbale_load_uploaded_balenmbr_set($conn);
        $uploadedBaleGroupMap = dbale_load_upload_group_map($conn);
        foreach (dbale_load_pengebalan_bales_by_ids($conn3, $selectedWrhsid, $selectedBalehdids) as $candidateBaleRow) {
            $balenmbrKey = dbale_normalize_key($candidateBaleRow['balenmbr'] ?? '');
            $balehdidKey = trim((string)($candidateBaleRow['balehdid'] ?? ''));
            if ($balenmbrKey !== '' && $balehdidKey !== '' && !isset($uploadedBalenmbrSet[$balenmbrKey]) && !isset($uploadedBaleGroupMap[$balehdidKey])) {
                $newBaleIndex[$balehdidKey] = $candidateBaleRow;
            }
        }

        foreach ($selectedBalehdids as $selectedBalehdid) {
            if (!isset($newBaleIndex[$selectedBalehdid])) {
                throw new RuntimeException('Balenmbr sudah upload atau tidak valid.');
            }

            $pickedBale = $newBaleIndex[$selectedBalehdid];
            $sourceDetails = dbale_load_uploadpengebalan_source_details($conn3, $selectedBalehdid, $selectedWrhsid);
            if (empty($sourceDetails)) {
                throw new RuntimeException('Detail source kosong untuk balenmbr ' . (string)($pickedBale['balenmbr'] ?? '') . '.');
            }
        }

        $_SESSION['pengebalan_draft'] = [
            'wrhsid' => $selectedWrhsid,
            'wrhsname' => $selectedWrhsname,
            'balehdids' => $selectedBalehdids,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $_SESSION['success'] = 'Draft siap. Upload Photo Barang dan Photo Packinglist, lalu klik Save untuk menyimpan.';
        header('Location: pengebalan.php?' . http_build_query([
            'wrhsid' => $selectedWrhsid,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'tab' => 'detail',
            'draft' => 1,
        ]));
        exit;
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: pengebalan.php?' . http_build_query([
            'wrhsid' => $selectedWrhsid,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'tab' => 'list',
        ]));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_upload']) && ($_POST['upload_mode'] ?? '') === 'draft') {
    $draft = $_SESSION['pengebalan_draft'] ?? null;
    $transactionStarted = false;
    $headerId = 0;
    $savedFiles = [];
    $saveSucceeded = false;

    try {
        if (!is_array($draft) || empty($draft['balehdids'])) {
            throw new RuntimeException('Draft pengebalan tidak ditemukan. Silakan pilih balenmbr melalui menu New lagi.');
        }
        if ((string)($draft['wrhsid'] ?? '') !== $selectedWrhsid) {
            throw new RuntimeException('Gudang draft tidak sesuai.');
        }
        if (empty($_FILES['photo_barang']['name']) || empty($_FILES['photo_packinglist']['name'])) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        $draftBalehdids = array_values(array_unique(array_filter(array_map('trim', (array)$draft['balehdids']))));
        $uploadedBalenmbrSet = dbale_load_uploaded_balenmbr_set($conn);
        $uploadedBaleGroupMap = dbale_load_upload_group_map($conn);
        $candidateIndex = [];
        foreach (dbale_load_pengebalan_bales_by_ids($conn3, $selectedWrhsid, $draftBalehdids) as $candidateRow) {
            $candidateIndex[(string)($candidateRow['balehdid'] ?? '')] = $candidateRow;
        }

        if (!sqlsrv_begin_transaction($conn)) {
            throw new RuntimeException('Gagal mulai transaksi: ' . print_r(sqlsrv_errors(), true));
        }
        $transactionStarted = true;
        $headerId = dbale_create_uploadpengebalan_header($conn, $selectedWrhsid, (string)($draft['wrhsname'] ?? $selectedWrhsname), $userName);
        $seq = 1;
        $totalPcs = 0;
        $totalM = 0.0;
        $totalYard = 0.0;

        foreach ($draftBalehdids as $draftBalehdid) {
            $candidateRow = $candidateIndex[$draftBalehdid] ?? null;
            $balenmbrKey = dbale_normalize_key($candidateRow['balenmbr'] ?? '');
            if (!$candidateRow || $balenmbrKey === '' || isset($uploadedBalenmbrSet[$balenmbrKey]) || isset($uploadedBaleGroupMap[$draftBalehdid])) {
                throw new RuntimeException('Balenmbr sudah upload atau tidak valid.');
            }

            $sourceDetails = dbale_load_uploadpengebalan_source_details($conn3, $draftBalehdid, $selectedWrhsid);
            if (empty($sourceDetails)) {
                throw new RuntimeException('Detail source kosong untuk balenmbr ' . (string)($candidateRow['balenmbr'] ?? '') . '.');
            }

            $totalPcs++;
            foreach ($sourceDetails as $sourceDetail) {
                $detailRow = [
                    'source_balehdid' => (string)($sourceDetail['source_balehdid'] ?? ''),
                    'source_baleprodid' => (string)($sourceDetail['source_baleprodid'] ?? ''),
                    'batchno' => (string)($sourceDetail['batchno'] ?? ''),
                    'qtym' => (float)($sourceDetail['qtym'] ?? 0),
                    'qtyyard' => (float)($sourceDetail['qtyyard'] ?? 0),
                    'qtykg' => (float)($sourceDetail['qtykg'] ?? 0),
                    'balenmbr' => (string)($sourceDetail['balenmbr'] ?? ''),
                    'baledesc' => (string)($sourceDetail['baledesc'] ?? ''),
                    'baledate' => dbale_db_date($sourceDetail['baledate'] ?? null),
                    'prodcode' => (string)($sourceDetail['prodcode'] ?? ''),
                    'prodname' => (string)($sourceDetail['prodname'] ?? ''),
                ];
                dbale_insert_uploadpengebalan_detail($conn, $headerId, $seq++, $detailRow, $userName);
                $totalM += $detailRow['qtym'];
                $totalYard += $detailRow['qtyyard'];
            }
        }

        $uploadDir = dbale_ensure_upload_dir();
        $savedBarang = dbale_store_upload_file($_FILES['photo_barang'], $uploadDir . DIRECTORY_SEPARATOR . 'uploadpengebalan_' . $headerId . '_barang.jpg');
        if ($savedBarang) {
            $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedBarang;
        }
        $savedPacking = dbale_store_upload_file($_FILES['photo_packinglist'], $uploadDir . DIRECTORY_SEPARATOR . 'uploadpengebalan_' . $headerId . '_packinglist.jpg');
        if ($savedPacking) {
            $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedPacking;
        }
        if (!$savedBarang || !$savedPacking) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        dbale_update_uploadpengebalan_summary($conn, $headerId, $totalPcs, $totalM, $totalYard, $userName);
        dbale_upsert_uploadpengebalan_photos($conn, $headerId, $savedBarang, $savedPacking, $userName);
        foreach ($draftBalehdids as $draftBalehdid) {
            dbale_upsert_upload($conn, $draftBalehdid, $savedBarang, $savedPacking, $userName);
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException('Gagal commit transaksi: ' . print_r(sqlsrv_errors(), true));
        }
        $transactionStarted = false;
        unset($_SESSION['pengebalan_draft']);
        $saveSucceeded = true;
        $_SESSION['success'] = 'Pengebalan dan kedua photo berhasil disimpan.';
    } catch (Throwable $e) {
        if ($transactionStarted) {
            @sqlsrv_rollback($conn);
        }
        foreach ($savedFiles as $savedFile) {
            if (is_file($savedFile)) {
                @unlink($savedFile);
            }
        }
        $_SESSION['error'] = $e->getMessage();
    }

    $redirectParams = [
        'wrhsid' => $selectedWrhsid,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'tab' => 'detail',
    ];
    $redirectParams[$saveSucceeded ? 'hdid' : 'draft'] = $saveSucceeded ? $headerId : 1;
    header('Location: pengebalan.php?' . http_build_query($redirectParams));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_upload']) && ($_POST['upload_mode'] ?? '') === 'group') {
    $groupBalehdids = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['upload_balehdids'] ?? ''))))));
    $savedFiles = [];

    try {
        if (empty($groupBalehdids)) {
            throw new RuntimeException('Grup bale tidak valid.');
        }
        if (empty($_FILES['photo_barang']['name']) || empty($_FILES['photo_packinglist']['name'])) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        $uploadDir = dbale_ensure_upload_dir();
        $safeGroupId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$groupBalehdids[0]) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
        $savedBarang = dbale_store_upload_file($_FILES['photo_barang'], $uploadDir . DIRECTORY_SEPARATOR . 'balegroup_' . $safeGroupId . '_barang.jpg');
        if ($savedBarang) {
            $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedBarang;
        }
        $savedPacking = dbale_store_upload_file($_FILES['photo_packinglist'], $uploadDir . DIRECTORY_SEPARATOR . 'balegroup_' . $safeGroupId . '_packinglist.jpg');
        if ($savedPacking) {
            $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedPacking;
        }
        if (!$savedBarang || !$savedPacking) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        if (!sqlsrv_begin_transaction($conn)) {
            throw new RuntimeException('Gagal mulai transaksi: ' . print_r(sqlsrv_errors(), true));
        }
        try {
            foreach ($groupBalehdids as $groupBalehdid) {
                dbale_upsert_upload($conn, $groupBalehdid, $savedBarang, $savedPacking, $userName);
            }
            if (!sqlsrv_commit($conn)) {
                throw new RuntimeException('Gagal commit bukti upload.');
            }
        } catch (Throwable $transactionError) {
            @sqlsrv_rollback($conn);
            throw $transactionError;
        }
        $_SESSION['success'] = 'Bukti upload seluruh bale dalam grup berhasil disimpan.';
    } catch (Throwable $e) {
        foreach ($savedFiles as $savedFile) {
            if (is_file($savedFile)) {
                @unlink($savedFile);
            }
        }
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: pengebalan.php?' . http_build_query([
        'wrhsid' => $selectedWrhsid,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'tab' => 'detail',
        'selected_balehdids' => implode(',', $groupBalehdids),
    ]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_upload']) && ($_POST['upload_mode'] ?? 'bale') === 'sheet') {
    $uploadHdId = (int)($_POST['upload_hdid'] ?? 0);
    $savedBarang = null;
    $savedPacking = null;
    $savedFiles = [];

    try {
        if ($uploadHdId <= 0 || !dbale_load_uploadpengebalan_header($conn, $uploadHdId)) {
            throw new RuntimeException('Header upload tidak valid.');
        }
        if (empty($_FILES['photo_barang']['name']) || empty($_FILES['photo_packinglist']['name'])) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        $uploadDir = dbale_ensure_upload_dir();
        if (!empty($_FILES['photo_barang']['name'])) {
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . 'uploadpengebalan_' . $uploadHdId . '_barang.jpg';
            $savedBarang = dbale_store_upload_file($_FILES['photo_barang'], $targetPath);
            if ($savedBarang) {
                $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedBarang;
            }
        }
        if (!empty($_FILES['photo_packinglist']['name'])) {
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . 'uploadpengebalan_' . $uploadHdId . '_packinglist.jpg';
            $savedPacking = dbale_store_upload_file($_FILES['photo_packinglist'], $targetPath);
            if ($savedPacking) {
                $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedPacking;
            }
        }
        if ($savedBarang === null || $savedPacking === null) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        dbale_upsert_uploadpengebalan_photos($conn, $uploadHdId, $savedBarang, $savedPacking, $userName);
        foreach (dbale_load_uploadpengebalan_source_balehdids($conn, $uploadHdId) as $sourceBalehdid) {
            dbale_upsert_upload($conn, $sourceBalehdid, $savedBarang, $savedPacking, $userName);
        }
        $_SESSION['success'] = 'Photo pengebalan tersimpan.';
    } catch (Throwable $e) {
        foreach ($savedFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: pengebalan.php?' . http_build_query([
        'wrhsid' => $selectedWrhsid,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'tab' => 'detail',
        'hdid' => $uploadHdId,
    ]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_upload'])) {
    $balehdid = trim((string)($_POST['upload_balehdid'] ?? ''));
    $baleprodid = trim((string)($_POST['upload_baleprodid'] ?? ''));
    $selectedWrhsid = trim((string)($_POST['wrhsid'] ?? $selectedWrhsid));
    $startDate = trim((string)($_POST['start_date'] ?? $startDate));
    $endDate = trim((string)($_POST['end_date'] ?? $endDate));
    $returnTab = trim((string)($_POST['return_tab'] ?? 'detail'));
    $returnSelectedBalehdid = trim((string)($_POST['return_selected_balehdid'] ?? $balehdid));
    $returnSelectedBaleprodid = trim((string)($_POST['return_selected_baleprodid'] ?? $baleprodid));

    $savedBarang = null;
    $savedPacking = null;
    $savedFiles = [];
    try {
        if ($balehdid === '') {
            throw new RuntimeException('Data bale tidak valid.');
        }
        if (empty($_FILES['photo_barang']['name']) || empty($_FILES['photo_packinglist']['name'])) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        $uploadDir = dbale_ensure_upload_dir();
        if (!empty($_FILES['photo_barang']['name'])) {
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . 'bale_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $balehdid) . '_barang.jpg';
            $savedBarang = dbale_store_upload_file($_FILES['photo_barang'], $targetPath);
            if ($savedBarang) {
                $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedBarang;
            }
        }
        if (!empty($_FILES['photo_packinglist']['name'])) {
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . 'bale_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $balehdid) . '_packinglist.jpg';
            $savedPacking = dbale_store_upload_file($_FILES['photo_packinglist'], $targetPath);
            if ($savedPacking) {
                $savedFiles[] = $uploadDir . DIRECTORY_SEPARATOR . $savedPacking;
            }
        }

        if ($savedBarang === null || $savedPacking === null) {
            throw new RuntimeException('Photo Barang dan Photo Packinglist wajib diisi.');
        }

        dbale_upsert_upload($conn, $balehdid, $savedBarang, $savedPacking, $userName);
        $_SESSION['success'] = 'Photo bale tersimpan.';
    } catch (Throwable $e) {
        foreach ($savedFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: pengebalan.php?' . http_build_query([
        'wrhsid' => $selectedWrhsid,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'status' => $statusFilter,
        'tab' => $returnTab,
        'selected_balehdid' => $returnSelectedBalehdid,
        'selected_baleprodid' => $returnSelectedBaleprodid,
    ]));
    exit;
}

$uploadedListBalehdids = dbale_load_uploaded_balehdids($conn, $startDate, $endDate);
$listResult = $selectedWrhsid !== ''
    ? dbale_load_rows_by_balehdids_paginated(
        $conn3,
        $uploadedListBalehdids,
        $selectedWrhsid,
        $listSearch,
        ($listPage - 1) * $listPerPage,
        $listPerPage,
        $statusFilter
    )
    : ['rows' => [], 'total' => 0];
$listRows = $listResult['rows'];
$listTotal = (int)$listResult['total'];
$listTotalPages = max(1, (int)ceil($listTotal / $listPerPage));
if ($listPage > $listTotalPages) {
    $listPage = $listTotalPages;
    $listResult = dbale_load_rows_by_balehdids_paginated(
        $conn3,
        $uploadedListBalehdids,
        $selectedWrhsid,
        $listSearch,
        ($listPage - 1) * $listPerPage,
        $listPerPage,
        $statusFilter
    );
    $listRows = $listResult['rows'];
}
$uploadedBaleSummary = $selectedWrhsid !== ''
    ? dbale_load_uploaded_bale_summary($conn3, $uploadedListBalehdids, $selectedWrhsid, $listSearch, $statusFilter)
    : ['total_packing_no' => 0, 'total_bale_no' => 0, 'total_qtym' => 0.0, 'total_qtyyard' => 0.0];
$selectedBalehdid = trim((string)($_GET['selected_balehdid'] ?? ''));
$selectedBaleprodid = trim((string)($_GET['selected_baleprodid'] ?? ''));
$selectedGroupBalehdids = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_GET['selected_balehdids'] ?? ''))))));
$uploadGroupFocusIds = $selectedGroupBalehdids;
if ($selectedBalehdid !== '') {
    $uploadGroupFocusIds[] = $selectedBalehdid;
}
foreach ($listRows as $listRow) {
    $uploadGroupFocusIds[] = (string)($listRow['balehdid'] ?? '');
}
$uploadGroupMap = dbale_load_upload_group_map($conn, $uploadGroupFocusIds);
if (empty($selectedGroupBalehdids) && $selectedBalehdid !== '') {
    $selectedGroupBalehdids = (array)($uploadGroupMap[$selectedBalehdid]['balehdids'] ?? [$selectedBalehdid]);
}

$selectedRow = null;
$detailBatchRows = [];
$detailTotals = ['qtym' => 0.0, 'qtyyard' => 0.0, 'qtykg' => 0.0];
$uploadRow = null;
$selectedHdId = (int)($_GET['hdid'] ?? 0);
$sheetGroups = [];

// Satu draft/sheet tersimpan adalah satu grup. Pilihan dari Sheet List dapat berisi beberapa grup upload.
if ($selectedHdId > 0 && ($sheetHeaderRow = dbale_load_uploadpengebalan_header($conn, $selectedHdId))) {
    $sheetGroups[] = [
        'header' => $sheetHeaderRow,
        'balehdids' => dbale_load_uploadpengebalan_source_balehdids($conn, $selectedHdId),
        'details' => dbale_load_uploadpengebalan_details($conn, $selectedHdId),
        'is_draft' => false,
        'is_upload_group' => false,
    ];
} elseif (isset($_GET['draft'])) {
    $draft = $_SESSION['pengebalan_draft'] ?? null;
    if (is_array($draft) && (string)($draft['wrhsid'] ?? '') === $selectedWrhsid && !empty($draft['balehdids'])) {
        $draftBalehdids = array_values(array_unique(array_filter(array_map('trim', (array)$draft['balehdids']))));
        $draftDetails = [];
        foreach ($draftBalehdids as $draftBalehdid) {
            foreach (dbale_load_uploadpengebalan_source_details($conn3, $draftBalehdid, $selectedWrhsid) as $sourceDetail) {
                $draftDetails[] = $sourceDetail;
            }
        }
        $sheetGroups[] = [
            'header' => ['hdid' => 0, 'wrhsname' => (string)($draft['wrhsname'] ?? $selectedWrhsname), 'created_at' => (string)($draft['created_at'] ?? ''), 'photo_barang' => '', 'photo_packinglist' => ''],
            'balehdids' => $draftBalehdids,
            'details' => $draftDetails,
            'is_draft' => true,
            'is_upload_group' => false,
        ];
    }
} elseif (!empty($selectedGroupBalehdids)) {
    $seenGroupKeys = [];
    foreach ($selectedGroupBalehdids as $selectedBalehdid) {
        $group = $uploadGroupMap[$selectedBalehdid] ?? null;
        if (!$group || empty($group['balehdids'])) {
            continue;
        }
        $groupBalehdids = array_values(array_unique(array_filter(array_map('trim', (array)$group['balehdids']))));
        $groupKey = implode('|', $groupBalehdids);
        if ($groupKey === '' || isset($seenGroupKeys[$groupKey])) {
            continue;
        }
        $seenGroupKeys[$groupKey] = true;
        $groupDetails = [];
        foreach ($groupBalehdids as $groupBalehdid) {
            foreach (dbale_load_uploadpengebalan_source_details($conn3, $groupBalehdid, $selectedWrhsid) as $sourceDetail) {
                $groupDetails[] = $sourceDetail;
            }
        }
        $groupUpload = $group['upload'] ?? dbale_load_upload($conn, (string)$groupBalehdids[0]);
        $sheetGroups[] = [
            'header' => ['hdid' => 0, 'wrhsname' => $selectedWrhsname, 'created_at' => $groupUpload['created_at'] ?? null, 'photo_barang' => $groupUpload['photo_barang'] ?? '', 'photo_packinglist' => $groupUpload['photo_packinglist'] ?? ''],
            'balehdids' => $groupBalehdids,
            'details' => $groupDetails,
            'is_draft' => false,
            'is_upload_group' => true,
        ];
    }
}

foreach ($sheetGroups as &$sheetGroup) {
    $sheetGroup['totals'] = ['qtym' => 0.0, 'qtyyard' => 0.0, 'qtykg' => 0.0];
    $sheetGroup['balenmbr_set'] = [];
    $sheetGroup['bale_subtotals'] = [];
    $sheetGroup['fgstatuses'] = [];
    $refnmbrByBalehdid = [];
    foreach (dbale_load_pengebalan_bales_by_ids($conn3, $selectedWrhsid, $sheetGroup['balehdids']) as $sourceBale) {
        $refnmbrByBalehdid[(string)($sourceBale['balehdid'] ?? '')] = (string)($sourceBale['refnmbr'] ?? '');
        foreach (explode(',', (string)($sourceBale['fgstatus'] ?? '')) as $fgstatus) {
            $fgstatus = strtoupper(trim($fgstatus));
            if (in_array($fgstatus, ['O', 'P'], true)) {
                $sheetGroup['fgstatuses'][$fgstatus] = $fgstatus;
            }
        }
    }
    foreach ($sheetGroup['details'] as &$detailRow) {
        $sourceBalehdid = (string)($detailRow['source_balehdid'] ?? '');
        if (empty($detailRow['refnmbr']) && isset($refnmbrByBalehdid[$sourceBalehdid])) {
            $detailRow['refnmbr'] = $refnmbrByBalehdid[$sourceBalehdid];
        }
        $sheetGroup['totals']['qtym'] += (float)($detailRow['qtym'] ?? 0);
        $sheetGroup['totals']['qtyyard'] += (float)($detailRow['qtyyard'] ?? 0);
        $sheetGroup['totals']['qtykg'] += (float)($detailRow['qtykg'] ?? 0);
        $baleKey = dbale_normalize_key($detailRow['balenmbr'] ?? '');
        if ($baleKey === '') {
            continue;
        }
        $sheetGroup['balenmbr_set'][$baleKey] = true;
        if (!isset($sheetGroup['bale_subtotals'][$baleKey])) {
            $sheetGroup['bale_subtotals'][$baleKey] = ['balehdid' => (string)($detailRow['source_balehdid'] ?? ''), 'balenmbr' => (string)($detailRow['balenmbr'] ?? ''), 'qtym' => 0.0, 'qtyyard' => 0.0, 'qtykg' => 0.0, 'rows' => 0];
        }
        $sheetGroup['bale_subtotals'][$baleKey]['qtym'] += (float)($detailRow['qtym'] ?? 0);
        $sheetGroup['bale_subtotals'][$baleKey]['qtyyard'] += (float)($detailRow['qtyyard'] ?? 0);
        $sheetGroup['bale_subtotals'][$baleKey]['qtykg'] += (float)($detailRow['qtykg'] ?? 0);
        $sheetGroup['bale_subtotals'][$baleKey]['rows']++;
    }
    unset($detailRow);
}
unset($sheetGroup);

/** Nama file unduhan mengikuti nomor Bale/Packing yang tampil pada detail. */
function dbale_download_file_name(string $prefix, string $reference, string $sourceFile): string
{
    $extension = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $extension = 'jpg';
    }
    $reference = preg_replace('/[^A-Za-z0-9._-]/', '_', trim($reference));
    return $prefix . ($reference !== '' ? $reference : 'tanpa_nomor') . '.' . $extension;
}

function dbale_sheet_reference(array $sheetGroup): string
{
    foreach ((array)($sheetGroup['details'] ?? []) as $detail) {
        $reference = trim((string)($detail['refnmbr'] ?? ''));
        if ($reference !== '') {
            return $reference;
        }
    }
    return '';
}

/* Export hanya data yang saat ini tampil pada Sheet Detail dan tersedia untuk Viewer. */
if (isset($_GET['export_sheet_excel']) && $canView && !empty($sheetGroups)) {
    $exportRows = [];
    foreach ($sheetGroups as $exportGroup) {
        foreach ((array)$exportGroup['details'] as $exportRow) {
            $exportRows[] = $exportRow;
        }
    }
    $exportReference = dbale_sheet_reference($sheetGroups[0]);
    $exportName = 'Detail_Pengebalan' . ($exportReference !== '' ? '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $exportReference) : '') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $exportName . '"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    ?>
    <table border="1">
      <thead><tr><th>No</th><th>Packing No</th><th>Bale No</th><th>Baledate</th><th>Prodcode</th><th>Prodname</th><th>Batch No</th><th>Qty M</th><th>Qty Yard</th></tr></thead>
      <tbody>
      <?php foreach ($exportRows as $exportIndex => $exportRow) : ?>
        <tr>
          <td><?= $exportIndex + 1 ?></td>
          <td><?= htmlspecialchars((string)($exportRow['refnmbr'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($exportRow['balenmbr'] ?? '')) ?></td>
          <td><?= htmlspecialchars(dbale_date_value($exportRow['baledate'] ?? null)) ?></td>
          <td><?= htmlspecialchars((string)($exportRow['prodcode'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($exportRow['prodname'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($exportRow['batchno'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($exportRow['qtym'] ?? '0')) ?></td>
          <td><?= htmlspecialchars((string)($exportRow['qtyyard'] ?? '0')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php
    exit;
}

if (!$sheetGroups && !empty($selectedGroupBalehdids)) {
    foreach (dbale_load_rows_by_balehdids($conn3, $selectedGroupBalehdids, $selectedWrhsid) as $row) {
        if ($selectedBaleprodid === '' || (string)($row['baleprodid'] ?? '') === $selectedBaleprodid) {
            $selectedRow = $row;
            break;
        }
    }
}
if ($selectedRow) {
    $detailBatchRows = dbale_load_detail_rows($conn3, (string)$selectedRow['balehdid'], (string)$selectedRow['baleprodid']);
    foreach ($detailBatchRows as $batchRow) {
        $detailTotals['qtym'] += (float)($batchRow['qtym'] ?? 0);
        $detailTotals['qtyyard'] += (float)($batchRow['qtyyard'] ?? 0);
        $detailTotals['qtykg'] += (float)($batchRow['qtykg'] ?? 0);
    }
    $uploadRow = dbale_load_upload($conn, (string)$selectedRow['balehdid']);
}

$activeTab = trim((string)($_GET['tab'] ?? 'list'));
if (!empty($sheetGroups)) {
    $activeTab = 'detail';
}
if ($selectedRow && $activeTab !== 'list') {
    $activeTab = 'detail';
}
if (!$selectedRow && empty($sheetGroups)) {
    $activeTab = 'list';
}

function dbale_public_upload_url(?string $fileName): string
{
    if (!$fileName) {
        return '';
    }
    return '/gg_app/uploads/databale/' . rawurlencode($fileName);
}

function dbale_value($row, string $key): string
{
    return htmlspecialchars((string)($row[$key] ?? ''));
}

function dbale_date_value($value, string $format = 'd/m/Y'): string
{
    if ($value instanceof DateTimeInterface) {
        return htmlspecialchars($value->format($format));
    }
    if (empty($value)) {
        return '-';
    }
    $time = strtotime((string)$value);
    return $time ? htmlspecialchars(date($format, $time)) : '-';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Databale</title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <style>
    .bale-photo-card img {
      width: 100%;
      max-height: 360px;
      object-fit: contain;
      background: #f8f9fa;
    }
    .js-photo-zoom {
      cursor: zoom-in;
    }
    .photo-rotate-actions {
      display: flex;
      justify-content: center;
      gap: .5rem;
      margin-top: .75rem;
    }
    .photo-zoom-overlay {
      position: fixed;
      inset: 0;
      z-index: 4000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 64px 24px 24px;
      background: rgba(0, 0, 0, .88);
    }
    .photo-zoom-overlay.is-visible {
      display: flex;
    }
    .photo-zoom-overlay img {
      max-width: 96vw;
      max-height: calc(100vh - 88px);
      object-fit: contain;
      box-shadow: 0 12px 40px rgba(0, 0, 0, .45);
    }
    .photo-zoom-close {
      position: absolute;
      top: 14px;
      right: 18px;
      width: 42px;
      height: 42px;
      padding: 0;
      border: 1px solid rgba(255, 255, 255, .75);
      border-radius: 50%;
      background: rgba(0, 0, 0, .45);
      color: #fff;
      font-size: 30px;
      line-height: 36px;
      cursor: pointer;
    }
    .photo-zoom-actions {
      position: absolute;
      left: 50%;
      bottom: 18px;
      z-index: 1;
      display: flex;
      gap: .75rem;
      transform: translateX(-50%);
    }
    .bale-photo-placeholder {
      min-height: 280px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8f9fa;
      border: 1px dashed #ced4da;
      color: #6c757d;
      border-radius: .25rem;
    }
    .new-modal-table td,
    .new-modal-table th {
      white-space: nowrap;
    }
    .data-loading-overlay {
      position: fixed;
      inset: 0;
      z-index: 5000;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, .78);
      backdrop-filter: blur(1px);
    }
    .data-loading-overlay.is-visible {
      display: flex;
    }
    .data-loading-box {
      width: min(420px, calc(100vw - 40px));
      padding: 20px;
      background: #fff;
      border-radius: .5rem;
      box-shadow: 0 8px 28px rgba(0, 0, 0, .2);
      text-align: center;
    }
    .loading-progress-bar {
      width: 35%;
      animation: databale-progress 1.15s ease-in-out infinite;
    }
    @keyframes databale-progress {
      0% { margin-left: 0; width: 20%; }
      50% { margin-left: 35%; width: 45%; }
      100% { margin-left: 80%; width: 20%; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="data-loading-overlay" id="dataLoadingOverlay" role="status" aria-live="polite" aria-hidden="true">
  <div class="data-loading-box">
    <div class="font-weight-bold mb-2"><i class="fas fa-spinner fa-spin mr-1"></i> Sedang memproses data...</div>
    <div class="progress" style="height:10px;">
      <div class="progress-bar progress-bar-striped progress-bar-animated loading-progress-bar"></div>
    </div>
    <small class="text-muted d-block mt-2">Mohon tunggu, jangan tutup halaman.</small>
  </div>
</div>
<div class="wrapper">
  <div class="content-wrapper p-3">
    <div class="content-header p-0 mb-3">
      <div class="container-fluid px-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h4 class="mb-0">Databale</h4>
            <div class="text-muted small">Sheet list, sheet detail, dan upload photo barang/packinglist.</div>
          </div>
          <a href="pengebalan.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-redo"></i> Reset</a>
        </div>
      </div>
    </div>

    <div class="container-fluid px-0">
      <?php if (!empty($_SESSION['success'])) : ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['error'])) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
      <?php endif; ?>

      <div class="card card-outline card-primary mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span>Filter</span>
          <div class="btn-group btn-group-sm ml-auto">
            <?php if ($canAdd) : ?>
              <button type="button" class="btn btn-success" id="openNewModalBtn" data-toggle="modal" data-target="#newModal"><i class="fas fa-plus"></i> New</button>
            <?php endif; ?>
            <?php if ($canDelete) : ?>
              <button type="button" class="btn btn-danger" id="deleteSelectedBtn" disabled><i class="fas fa-trash"></i> Hapus Dipilih</button>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($canDelete) : ?>
          <form method="post" id="deleteSelectedForm" class="d-none">
            <input type="hidden" name="delete_selected_uploads" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($pengebalanCsrf, ENT_QUOTES) ?>">
            <input type="hidden" name="wrhsid" value="<?= htmlspecialchars($selectedWrhsid, ENT_QUOTES) ?>">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate, ENT_QUOTES) ?>">
            <input type="hidden" name="list_search" value="<?= htmlspecialchars($listSearch, ENT_QUOTES) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES) ?>">
            <div id="deleteSelectedInputs"></div>
          </form>
        <?php endif; ?>
        <div class="card-body">
          <form method="get" class="row align-items-end">
            <div class="col-md-2 mb-2">
              <label class="form-label">Gudang</label>
              <input type="hidden" name="wrhsid" value="<?= htmlspecialchars($selectedWrhsid) ?>">
              <select class="form-control bg-light" disabled>
                <option value="<?= htmlspecialchars($selectedWrhsid) ?>" selected>
                  <?= htmlspecialchars($lockedWarehouseName) ?>
                </option>
              </select>
            </div>
            <div class="col-md-2 mb-2">
              <label class="form-label">Tanggal Dari</label>
              <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-2 mb-2">
              <label class="form-label">Tanggal Sampai</label>
              <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-md-2 mb-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-control">
                <option value="O" <?= $statusFilter === 'O' ? 'selected' : '' ?>>Open</option>
                <option value="P" <?= $statusFilter === 'P' ? 'selected' : '' ?>>Packinglist</option>
                <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All</option>
              </select>
            </div>
            <div class="col-md-2 mb-2">
              <label class="form-label">Search</label>
              <input type="text" name="list_search" class="form-control" value="<?= htmlspecialchars($listSearch, ENT_QUOTES) ?>" placeholder="Balenmbr / Prodcode / Prodname / Refnmbr / Baledesc">
            </div>
            <div class="col-md-1 mb-2">
              <label class="form-label">Tampilkan</label>
              <select name="per_page" class="form-control">
                <?php foreach ($allowedPageSizes as $pageSize) : ?>
                  <option value="<?= $pageSize ?>" <?= $listPerPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?> baris</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-1 mb-2">
              <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i></button>
            </div>
          </form>
        </div>
      </div>

      <ul class="nav nav-tabs" id="baleTab" role="tablist">
        <li class="nav-item">
          <a class="nav-link <?= $activeTab === 'list' ? 'active' : '' ?>" id="list-tab" data-toggle="tab" href="#sheet-list" role="tab">Sheet List</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activeTab === 'detail' ? 'active' : '' ?>" id="detail-tab" data-toggle="tab" href="#sheet-detail" role="tab">Sheet Detail</a>
        </li>
      </ul>

      <div class="tab-content border-left border-right border-bottom p-3 bg-white">
        <div class="tab-pane fade <?= $activeTab === 'list' ? 'show active' : '' ?>" id="sheet-list" role="tabpanel">
          <div class="card card-outline card-info mb-3">
            <div class="card-body py-2">
              <div class="row">
                <div class="col-md-3 mb-2 mb-md-0"><small class="text-muted d-block">Packing No Sudah Disimpan</small><strong><?= number_format((int)$uploadedBaleSummary['total_packing_no'], 0, ',', '.') ?></strong></div>
                <div class="col-md-3 mb-2 mb-md-0"><small class="text-muted d-block">Bale No Sudah Disimpan</small><strong><?= number_format((int)$uploadedBaleSummary['total_bale_no'], 0, ',', '.') ?></strong></div>
                <div class="col-md-3 mb-2 mb-md-0"><small class="text-muted d-block">Total Qty M</small><strong><?= number_format((float)$uploadedBaleSummary['total_qtym'], 4, ',', '.') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Total Qty Yard</small><strong><?= number_format((float)$uploadedBaleSummary['total_qtyyard'], 4, ',', '.') ?></strong></div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm mb-0">
              <thead class="thead-light text-center">
                <tr>
                  <th style="width:40px;">#</th>
                  <th>Packing No</th>
                  <th>Baledate</th>
                  <th>Prodcode</th>
                  <th>Prodname</th>
                  <th>Total Batch</th>
                  <th>TotQtyM</th>
                  <th>TotQtyYard</th>
                  <th>Bale No</th>
                  <th>Baledesc</th>
                  <th style="width:140px;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($listRows)) : ?>
                  <tr><td colspan="11" class="text-center text-muted">Data kosong</td></tr>
                <?php else : ?>
                  <?php foreach ($listRows as $index => $row) : ?>
                    <?php
                      $rowBalehdid = (string)($row['balehdid'] ?? '');
                      $rowBaleprodid = (string)($row['baleprodid'] ?? '');
                      $rowGroupBalehdids = (array)($uploadGroupMap[$rowBalehdid]['balehdids'] ?? [$rowBalehdid]);
                      $isSelected = in_array($rowBalehdid, $selectedGroupBalehdids, true);
                    ?>
                    <tr class="bale-row <?= $isSelected ? 'table-primary' : '' ?>">
                      <td class="text-center align-middle">
                        <input type="checkbox" class="bale-check" data-balehdid="<?= htmlspecialchars($rowBalehdid, ENT_QUOTES) ?>" data-baleprodid="<?= htmlspecialchars($rowBaleprodid, ENT_QUOTES) ?>" data-group-balehdids="<?= htmlspecialchars(json_encode(array_values($rowGroupBalehdids)), ENT_QUOTES) ?>" <?= $isSelected ? 'checked' : '' ?>>
                      </td>
                      <td><?= dbale_value($row, 'balenmbr') ?></td>
                      <td><?= !empty($row['baledate']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$row['baledate']))) : '-' ?></td>
                      <td><?= dbale_value($row, 'prodcode') ?></td>
                      <td><?= dbale_value($row, 'prodname') ?></td>
                      <td class="text-center"><?= (int)($row['total_batch'] ?? 0) ?></td>
                      <td class="text-end"><?= htmlspecialchars((string)($row['totqtym'] ?? '0')) ?></td>
                      <td class="text-end"><?= htmlspecialchars((string)($row['totqtyyard'] ?? '0')) ?></td>
                      <td><?= dbale_value($row, 'refnmbr') ?></td>
                      <td><?= dbale_value($row, 'baledesc') ?></td>
                      <td class="text-center">
                        <?php if ($canEdit) : ?>
                          <button type="button" class="btn btn-sm btn-outline-primary btn-upload-group" data-balehdids="<?= htmlspecialchars(implode(',', array_values($rowGroupBalehdids)), ENT_QUOTES) ?>" data-return-tab="list">
                            <i class="fas fa-edit"></i>
                          </button>
                        <?php endif; ?>
                        <?php if ($canDelete) : ?>
                          <form method="post" class="d-inline" onsubmit="return confirm('Hapus hanya bale ini? Bale lain dengan bukti upload yang sama tidak akan dihapus.');">
                          <input type="hidden" name="delete_bale_upload" value="1">
                          <input type="hidden" name="delete_balehdid" value="<?= htmlspecialchars($rowBalehdid, ENT_QUOTES) ?>">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($pengebalanCsrf, ENT_QUOTES) ?>">
                          <input type="hidden" name="wrhsid" value="<?= htmlspecialchars($selectedWrhsid, ENT_QUOTES) ?>">
                          <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES) ?>">
                          <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate, ENT_QUOTES) ?>">
                          <input type="hidden" name="list_search" value="<?= htmlspecialchars($listSearch, ENT_QUOTES) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus bale ini">
                            <i class="fas fa-trash"></i>
                          </button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
            <div class="text-muted small mb-2">
              <?php if ($listTotal > 0) : ?>
                Menampilkan <?= (($listPage - 1) * $listPerPage) + 1 ?>–<?= min($listPage * $listPerPage, $listTotal) ?> dari <?= $listTotal ?> data
              <?php else : ?>
                Tidak ada data
              <?php endif; ?>
            </div>
            <?php if ($listTotalPages > 1) : ?>
              <nav aria-label="Pagination Sheet List" class="mb-2">
                <ul class="pagination pagination-sm mb-0">
                  <?php
                    $pageParams = [
                        'wrhsid' => $selectedWrhsid,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'list_search' => $listSearch,
                        'status' => $statusFilter,
                        'per_page' => $listPerPage,
                        'tab' => 'list',
                    ];
                    $pageStart = max(1, $listPage - 2);
                    $pageEnd = min($listTotalPages, $listPage + 2);
                  ?>
                  <li class="page-item <?= $listPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($pageParams, ['page' => max(1, $listPage - 1)])), ENT_QUOTES) ?>" aria-label="Sebelumnya">&laquo;</a>
                  </li>
                  <?php for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++) : ?>
                    <li class="page-item <?= $pageNumber === $listPage ? 'active' : '' ?>">
                      <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($pageParams, ['page' => $pageNumber])), ENT_QUOTES) ?>"><?= $pageNumber ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $listPage >= $listTotalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($pageParams, ['page' => min($listTotalPages, $listPage + 1)])), ENT_QUOTES) ?>" aria-label="Berikutnya">&raquo;</a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'detail' ? 'show active' : '' ?>" id="sheet-detail" role="tabpanel">
          <?php if (!empty($sheetGroups)) : ?>
            <?php foreach ($sheetGroups as $sheetGroup) : ?>
              <?php
                $sheetHeaderRow = $sheetGroup['header'];
                $sheetDetailRows = $sheetGroup['details'];
                $sheetTotals = $sheetGroup['totals'];
                $sheetBalenmbrSet = $sheetGroup['balenmbr_set'];
                $sheetBaleSubtotals = $sheetGroup['bale_subtotals'];
                $isDraftSheet = $sheetGroup['is_draft'];
                $isUploadGroupSheet = $sheetGroup['is_upload_group'];
                $sheetGroupBalehdidsValue = implode(',', $sheetGroup['balehdids']);
                $sheetUploadButtonClass = $isDraftSheet ? 'btn-upload-draft' : ($isUploadGroupSheet ? 'btn-upload-group' : 'btn-upload-sheet');
                $sheetReference = dbale_sheet_reference($sheetGroup);
                $sheetReferenceLastPart = strrchr($sheetReference, '.');
                $sheetReferenceLastPart = $sheetReferenceLastPart === false ? $sheetReference : substr($sheetReferenceLastPart, 1);
                $sheetPhotoBarangFile = (string)($sheetHeaderRow['photo_barang'] ?? '');
                $sheetPhotoPackingFile = (string)($sheetHeaderRow['photo_packinglist'] ?? '');
                $sheetPhotoBarangPath = __DIR__ . '/../../../uploads/databale/' . $sheetPhotoBarangFile;
                $sheetPhotoPackingPath = __DIR__ . '/../../../uploads/databale/' . $sheetPhotoPackingFile;
                $sheetPhotoBarangAvailable = $sheetPhotoBarangFile !== '' && is_file($sheetPhotoBarangPath);
                $sheetPhotoPackingAvailable = $sheetPhotoPackingFile !== '' && is_file($sheetPhotoPackingPath);
                $sheetDownloadBarangName = dbale_download_file_name('Foto.Barang_', $sheetReference, $sheetPhotoBarangFile);
                $sheetDownloadPackingName = dbale_download_file_name('Foto_Packinglist.', $sheetReferenceLastPart, $sheetPhotoPackingFile);
                $sheetExportUrl = '?' . http_build_query(array_merge($_GET, ['tab' => 'detail', 'export_sheet_excel' => 1]));
              ?>
            <div class="mb-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <h5 class="mb-1"><?= $isDraftSheet ? 'Draft' : 'Detail' ?> Sheet Pengebalan<?= (int)($sheetHeaderRow['hdid'] ?? 0) > 0 ? ' #' . htmlspecialchars((string)$sheetHeaderRow['hdid']) : '' ?></h5>
                <div class="text-muted small">
                  Gudang: <b><?= htmlspecialchars((string)($sheetHeaderRow['wrhsname'] ?? $selectedWrhsname)) ?></b> |
                  Bale: <b><?= count($sheetBalenmbrSet) ?></b> |
                  Dibuat: <b><?= dbale_date_value($sheetHeaderRow['created_at'] ?? null, 'd/m/Y H:i') ?></b>
                </div>
              </div>
              <?php
                $sheetStatusLabels = [];
                foreach ((array)($sheetGroup['fgstatuses'] ?? []) as $sheetStatus) {
                    $sheetStatusLabels[] = $sheetStatus === 'P' ? 'Packinglist' : ($sheetStatus === 'O' ? 'Open' : $sheetStatus);
                }
              ?>
              <?php if ($sheetStatusLabels) : ?><span class="badge badge-<?= in_array('Packinglist', $sheetStatusLabels, true) ? 'info' : 'success' ?> px-3 py-2">Status: <?= htmlspecialchars(implode(' / ', array_unique($sheetStatusLabels))) ?></span><?php endif; ?>
            </div>

            <?php if ($isDraftSheet) : ?>
              <div class="alert alert-warning py-2">
                Data ini masih <b>draft</b> dan belum tersimpan ke database. Upload <b>Photo Barang</b> serta <b>Photo Packinglist</b>, kemudian klik <b>Save</b>.
              </div>
            <?php endif; ?>

            <div class="table-responsive mb-3">
              <table class="table table-bordered table-sm mb-0">
                <thead class="thead-light text-center">
                  <tr>
                    <th style="width:45px;">No</th>
                    <th>Packing No</th>
                    <th>Bale No</th>
                    <th>Baledate</th>
                    <th>Prodcode</th>
                    <th>Prodname</th>
                    <th>Batch No</th>
                    <th>Qty M</th>
                    <th>Qty Yard</th>
                    <th style="width:70px;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($sheetDetailRows)) : ?>
                    <tr><td colspan="10" class="text-center text-muted">Batch kosong</td></tr>
                  <?php else : ?>
                    <?php $sheetBaleRemainingRows = array_map(static function (array $subtotal): int { return (int)$subtotal['rows']; }, $sheetBaleSubtotals); ?>
                    <?php foreach ($sheetDetailRows as $sheetDetailIndex => $sheetDetailRow) : ?>
                      <?php $detailBalenmbrKey = dbale_normalize_key($sheetDetailRow['balenmbr'] ?? ''); ?>
                      <tr>
                        <td class="text-center"><?= $sheetDetailIndex + 1 ?></td>
                        <td><?= dbale_value($sheetDetailRow, 'balenmbr') ?></td>
                        <td><?= dbale_value($sheetDetailRow, 'refnmbr') ?></td>
                        <td><?= dbale_date_value($sheetDetailRow['baledate'] ?? null) ?></td>
                        <td><?= dbale_value($sheetDetailRow, 'prodcode') ?></td>
                        <td><?= dbale_value($sheetDetailRow, 'prodname') ?></td>
                        <td><?= dbale_value($sheetDetailRow, 'batchno') ?></td>
                        <td class="text-end"><?= htmlspecialchars((string)($sheetDetailRow['qtym'] ?? '0')) ?></td>
                        <td class="text-end"><?= htmlspecialchars((string)($sheetDetailRow['qtyyard'] ?? '0')) ?></td>
                        <td></td>
                      </tr>
                      <?php if ($detailBalenmbrKey !== '' && isset($sheetBaleRemainingRows[$detailBalenmbrKey])) : ?>
                        <?php $sheetBaleRemainingRows[$detailBalenmbrKey]--; ?>
                        <?php if ($sheetBaleRemainingRows[$detailBalenmbrKey] === 0) : ?>
                          <?php $baleSubtotal = $sheetBaleSubtotals[$detailBalenmbrKey]; ?>
                          <tr class="font-weight-bold table-info">
                            <td colspan="7">Subtotal Balenmbr <?= htmlspecialchars((string)$baleSubtotal['balenmbr']) ?></td>
                            <td class="text-end"><?= number_format((float)$baleSubtotal['qtym'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)$baleSubtotal['qtyyard'], 2, ',', '.') ?></td>
                            <td class="text-center">
                              <?php if ($canDelete && !$isDraftSheet && (string)$baleSubtotal['balehdid'] !== '') : ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus Balenmbr ini beserta seluruh batch-nya?');">
                                  <input type="hidden" name="delete_bale_upload" value="1">
                                  <input type="hidden" name="delete_balehdid" value="<?= htmlspecialchars((string)$baleSubtotal['balehdid'], ENT_QUOTES) ?>">
                                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($pengebalanCsrf, ENT_QUOTES) ?>">
                                  <input type="hidden" name="wrhsid" value="<?= htmlspecialchars($selectedWrhsid, ENT_QUOTES) ?>">
                                  <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES) ?>">
                                  <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate, ENT_QUOTES) ?>">
                                  <input type="hidden" name="list_search" value="<?= htmlspecialchars($listSearch, ENT_QUOTES) ?>">
                                  <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES) ?>">
                                  <button type="submit" class="btn btn-sm btn-danger" title="Hapus Balenmbr ini"><i class="fas fa-trash"></i></button>
                                </form>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
                <tfoot>
                  <tr class="font-weight-bold bg-light">
                    <td colspan="7">Total</td>
                    <td class="text-end"><?= number_format($sheetTotals['qtym'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($sheetTotals['qtyyard'], 2, ',', '.') ?></td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="row">
              <div class="col-lg-6 mb-3">
                <div class="card bale-photo-card h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Foto Barang</span>
                    <?php if (($isDraftSheet && $canAdd) || (!$isDraftSheet && $canEdit)) : ?>
                      <button type="button" class="btn btn-sm btn-outline-primary <?= htmlspecialchars($sheetUploadButtonClass, ENT_QUOTES) ?>" data-hdid="<?= htmlspecialchars((string)$sheetHeaderRow['hdid'], ENT_QUOTES) ?>" data-balehdids="<?= htmlspecialchars($sheetGroupBalehdidsValue, ENT_QUOTES) ?>">Upload</button>
                    <?php endif; ?>
                  </div>
                  <div class="card-body">
                    <?php $sheetPhotoBarangUrl = dbale_public_upload_url($sheetPhotoBarangFile); ?>
                    <?php if ($sheetPhotoBarangUrl !== '' && $sheetPhotoBarangAvailable) : ?>
                      <img src="<?= htmlspecialchars($sheetPhotoBarangUrl) ?>" alt="Foto Barang" class="img-fluid rounded border js-photo-zoom" data-photo-file="<?= htmlspecialchars((string)($sheetHeaderRow['photo_barang'] ?? ''), ENT_QUOTES) ?>" tabindex="0" title="Klik untuk memperbesar">
                      <?php if ($canView) : ?><div class="photo-rotate-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($sheetHeaderRow['photo_barang'] ?? ''), ENT_QUOTES) ?>" data-direction="left"><i class="fas fa-undo"></i> Putar Kiri</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($sheetHeaderRow['photo_barang'] ?? ''), ENT_QUOTES) ?>" data-direction="right"><i class="fas fa-redo"></i> Putar Kanan</button>
                      </div><?php endif; ?>
                    <?php else : ?>
                      <div class="bale-photo-placeholder">Belum ada foto barang</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-lg-6 mb-3">
                <div class="card bale-photo-card h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Foto Packinglist</span>
                    <?php if (($isDraftSheet && $canAdd) || (!$isDraftSheet && $canEdit)) : ?>
                      <button type="button" class="btn btn-sm btn-outline-primary <?= htmlspecialchars($sheetUploadButtonClass, ENT_QUOTES) ?>" data-hdid="<?= htmlspecialchars((string)$sheetHeaderRow['hdid'], ENT_QUOTES) ?>" data-balehdids="<?= htmlspecialchars($sheetGroupBalehdidsValue, ENT_QUOTES) ?>">Upload</button>
                    <?php endif; ?>
                  </div>
                  <div class="card-body">
                    <?php $sheetPhotoPackingUrl = dbale_public_upload_url($sheetPhotoPackingFile); ?>
                    <?php if ($sheetPhotoPackingUrl !== '' && $sheetPhotoPackingAvailable) : ?>
                      <img src="<?= htmlspecialchars($sheetPhotoPackingUrl) ?>" alt="Foto Packinglist" class="img-fluid rounded border js-photo-zoom" data-photo-file="<?= htmlspecialchars((string)($sheetHeaderRow['photo_packinglist'] ?? ''), ENT_QUOTES) ?>" tabindex="0" title="Klik untuk memperbesar">
                      <?php if ($canView) : ?><div class="photo-rotate-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($sheetHeaderRow['photo_packinglist'] ?? ''), ENT_QUOTES) ?>" data-direction="left"><i class="fas fa-undo"></i> Putar Kiri</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($sheetHeaderRow['photo_packinglist'] ?? ''), ENT_QUOTES) ?>" data-direction="right"><i class="fas fa-redo"></i> Putar Kanan</button>
                      </div><?php endif; ?>
                    <?php else : ?>
                      <div class="bale-photo-placeholder">Belum ada foto packinglist</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="card mb-4">
              <div class="card-body py-2">
                <div class="row">
                  <div class="col-md-4 mb-2"><small class="text-muted d-block">Total Bale</small><strong><?= count($sheetBalenmbrSet) ?></strong></div>
                  <div class="col-md-4 mb-2"><small class="text-muted d-block">Total M</small><strong><?= number_format($sheetTotals['qtym'], 2, ',', '.') ?></strong></div>
                  <div class="col-md-4 mb-2"><small class="text-muted d-block">Total Yard</small><strong><?= number_format($sheetTotals['qtyyard'], 2, ',', '.') ?></strong></div>
                </div>
                <?php if ($canView) : ?>
                  <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top">
                    <?php if ($sheetPhotoBarangAvailable) : ?>
                      <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($sheetPhotoBarangUrl, ENT_QUOTES) ?>" download="<?= htmlspecialchars($sheetDownloadBarangName, ENT_QUOTES) ?>"><i class="fas fa-download"></i> Save Foto Barang</a>
                    <?php endif; ?>
                    <?php if ($sheetPhotoPackingAvailable) : ?>
                      <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($sheetPhotoPackingUrl, ENT_QUOTES) ?>" download="<?= htmlspecialchars($sheetDownloadPackingName, ENT_QUOTES) ?>"><i class="fas fa-download"></i> Save Foto Packinglist</a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-success" href="<?= htmlspecialchars($sheetExportUrl, ENT_QUOTES) ?>"><i class="fas fa-file-excel"></i> Export Excel</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php elseif (!$selectedRow) : ?>
            <div class="alert alert-info mb-0">Pilih 1 data di sheet list lalu klik <b>Sheet Detail</b>.</div>
          <?php else : ?>
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
              <div>
                <h5 class="mb-1">Detail Bale</h5>
                <div class="text-muted small">
                  Bale: <b><?= dbale_value($selectedRow, 'balenmbr') ?></b> |
                  Tanggal: <b><?= !empty($selectedRow['baledate']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$selectedRow['baledate']))) : '-' ?></b> |
                  Gudang: <b><?= dbale_value($selectedRow, 'wrhsname') ?></b>
                </div>
              </div>
              <?php if ($canEdit) : ?>
                <button type="button" class="btn btn-outline-primary btn-sm btn-upload" data-balehdid="<?= htmlspecialchars((string)$selectedRow['balehdid'], ENT_QUOTES) ?>" data-baleprodid="<?= htmlspecialchars((string)$selectedRow['baleprodid'], ENT_QUOTES) ?>" data-return-tab="detail">
                  <i class="fas fa-edit"></i> Edit / Upload
                </button>
              <?php endif; ?>
            </div>

            <div class="card mb-3">
              <div class="card-body py-2">
                <div class="row">
                  <div class="col-md-2 mb-2"><small class="text-muted d-block">Packing No</small><strong><?= dbale_value($selectedRow, 'balenmbr') ?></strong></div>
                  <div class="col-md-2 mb-2"><small class="text-muted d-block">Bale No</small><strong><?= dbale_value($selectedRow, 'refnmbr') ?></strong></div>
                  <div class="col-md-2 mb-2"><small class="text-muted d-block">Baledate</small><strong><?= !empty($selectedRow['baledate']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$selectedRow['baledate']))) : '-' ?></strong></div>
                  <div class="col-md-2 mb-2"><small class="text-muted d-block">Prodcode</small><strong><?= dbale_value($selectedRow, 'prodcode') ?></strong></div>
                  <div class="col-md-2 mb-2"><small class="text-muted d-block">Prodname</small><strong><?= dbale_value($selectedRow, 'prodname') ?></strong></div>
                  <div class="col-md-2 mb-2"><small class="text-muted d-block">Baledesc</small><strong><?= dbale_value($selectedRow, 'baledesc') ?></strong></div>
                </div>
              </div>
            </div>

            <div class="table-responsive mb-3">
              <table class="table table-bordered table-sm mb-0">
                <thead class="thead-light text-center">
                  <tr>
                    <th>Batch No</th>
                    <th>Qty M</th>
                    <th>Qty Yard</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($detailBatchRows)) : ?>
                    <tr><td colspan="3" class="text-center text-muted">Batch kosong</td></tr>
                  <?php else : ?>
                    <?php foreach ($detailBatchRows as $batchRow) : ?>
                      <tr>
                        <td><?= dbale_value($batchRow, 'batchno') ?></td>
                        <td class="text-end"><?= htmlspecialchars((string)($batchRow['qtym'] ?? '0')) ?></td>
                        <td class="text-end"><?= htmlspecialchars((string)($batchRow['qtyyard'] ?? '0')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
                <tfoot>
                  <tr class="font-weight-bold bg-light">
                    <td>Total</td>
                    <td class="text-end"><?= number_format($detailTotals['qtym'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($detailTotals['qtyyard'], 2, ',', '.') ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="row">
              <div class="col-lg-6 mb-3">
                <div class="card bale-photo-card h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Foto Barang</span>
                    <?php if ($canEdit) : ?><button type="button" class="btn btn-sm btn-outline-primary btn-upload" data-balehdid="<?= htmlspecialchars((string)$selectedRow['balehdid'], ENT_QUOTES) ?>" data-baleprodid="<?= htmlspecialchars((string)$selectedRow['baleprodid'], ENT_QUOTES) ?>" data-return-tab="detail">Upload</button><?php endif; ?>
                  </div>
                  <div class="card-body">
                    <?php $photoBarangUrl = dbale_public_upload_url((string)($uploadRow['photo_barang'] ?? '')); ?>
                    <?php if ($photoBarangUrl !== '' && is_file(__DIR__ . '/../../../uploads/databale/' . (string)($uploadRow['photo_barang'] ?? ''))) : ?>
                      <img src="<?= htmlspecialchars($photoBarangUrl) ?>" alt="Foto Barang" class="img-fluid rounded border js-photo-zoom" data-photo-file="<?= htmlspecialchars((string)($uploadRow['photo_barang'] ?? ''), ENT_QUOTES) ?>" tabindex="0" title="Klik untuk memperbesar">
                      <?php if ($canView) : ?><div class="photo-rotate-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($uploadRow['photo_barang'] ?? ''), ENT_QUOTES) ?>" data-direction="left"><i class="fas fa-undo"></i> Putar Kiri</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($uploadRow['photo_barang'] ?? ''), ENT_QUOTES) ?>" data-direction="right"><i class="fas fa-redo"></i> Putar Kanan</button>
                      </div><?php endif; ?>
                    <?php else : ?>
                      <div class="bale-photo-placeholder">Belum ada foto barang</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-lg-6 mb-3">
                <div class="card bale-photo-card h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Foto Packinglist</span>
                    <?php if ($canEdit) : ?><button type="button" class="btn btn-sm btn-outline-primary btn-upload" data-balehdid="<?= htmlspecialchars((string)$selectedRow['balehdid'], ENT_QUOTES) ?>" data-baleprodid="<?= htmlspecialchars((string)$selectedRow['baleprodid'], ENT_QUOTES) ?>" data-return-tab="detail">Upload</button><?php endif; ?>
                  </div>
                  <div class="card-body">
                    <?php $photoPackingUrl = dbale_public_upload_url((string)($uploadRow['photo_packinglist'] ?? '')); ?>
                    <?php if ($photoPackingUrl !== '' && is_file(__DIR__ . '/../../../uploads/databale/' . (string)($uploadRow['photo_packinglist'] ?? ''))) : ?>
                      <img src="<?= htmlspecialchars($photoPackingUrl) ?>" alt="Foto Packinglist" class="img-fluid rounded border js-photo-zoom" data-photo-file="<?= htmlspecialchars((string)($uploadRow['photo_packinglist'] ?? ''), ENT_QUOTES) ?>" tabindex="0" title="Klik untuk memperbesar">
                      <?php if ($canView) : ?><div class="photo-rotate-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($uploadRow['photo_packinglist'] ?? ''), ENT_QUOTES) ?>" data-direction="left"><i class="fas fa-undo"></i> Putar Kiri</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-rotate-photo" data-photo-file="<?= htmlspecialchars((string)($uploadRow['photo_packinglist'] ?? ''), ENT_QUOTES) ?>" data-direction="right"><i class="fas fa-redo"></i> Putar Kanan</button>
                      </div><?php endif; ?>
                    <?php else : ?>
                      <div class="bale-photo-placeholder">Belum ada foto packinglist</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="photo-zoom-overlay" id="photoZoomOverlay" role="dialog" aria-modal="true" aria-label="Pratinjau foto" aria-hidden="true">
  <button type="button" class="photo-zoom-close" id="photoZoomClose" aria-label="Tutup zoom">&times;</button>
  <img id="photoZoomImage" src="" alt="">
  <?php if ($canView) : ?><div class="photo-zoom-actions">
    <button type="button" class="btn btn-light btn-rotate-photo" data-photo-file="" data-direction="left"><i class="fas fa-undo"></i> Putar Kiri</button>
    <button type="button" class="btn btn-light btn-rotate-photo" data-photo-file="" data-direction="right"><i class="fas fa-redo"></i> Putar Kanan</button>
  </div><?php endif; ?>
</div>

<div class="modal fade" id="newModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">New Pengebalan</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="create_pengebalan" value="1">
          <input type="hidden" name="wrhsid" value="<?= htmlspecialchars($selectedWrhsid) ?>">
          <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
          <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
          <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
          <div class="alert alert-info py-2 mb-3">Pilih beberapa <b>balenmbr</b> dari gudang <?= htmlspecialchars($selectedWrhsname) ?>. Bale yang sudah upload tidak muncul lagi.</div>
          <div class="form-group mb-2">
            <input type="text" class="form-control" id="newBaleSearch" placeholder="Search balenmbr / refnmbr...">
            <small class="text-muted">Default tampil 10 data. Ketik balenmbr/refnmbr lalu tekan Enter untuk mencari.</small>
          </div>
          <div id="selectedBaleInputs"></div>
          <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
            <table class="table table-bordered table-hover table-sm mb-0 new-modal-table">
              <thead class="thead-light text-center">
                <tr>
                  <th style="width:40px;"><input type="checkbox" id="checkAllNewBales"></th>
                  <th>Packing No</th>
                  <th>Bale No</th>
                  <th>Baledate</th>
                </tr>
              </thead>
              <tbody id="newBaleRows">
                <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Lanjut ke Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="uploadPhotoForm">
        <div class="modal-header">
          <h5 class="modal-title">Upload Photo Bale</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="save_upload" value="1">
          <input type="hidden" name="upload_mode" id="uploadMode" value="bale">
          <input type="hidden" name="upload_hdid" id="uploadHdid" value="">
          <input type="hidden" name="upload_balehdid" id="uploadBalehdid" value="">
          <input type="hidden" name="upload_baleprodid" id="uploadBaleprodid" value="">
          <input type="hidden" name="upload_balehdids" id="uploadBalehdids" value="">
          <input type="hidden" name="wrhsid" value="<?= htmlspecialchars($selectedWrhsid) ?>">
          <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
          <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
          <input type="hidden" name="return_tab" id="returnTab" value="detail">
          <input type="hidden" name="return_selected_balehdid" id="returnSelectedBalehdid" value="<?= htmlspecialchars($selectedBalehdid) ?>">
          <input type="hidden" name="return_selected_baleprodid" id="returnSelectedBaleprodid" value="<?= htmlspecialchars($selectedBaleprodid) ?>">
          <div class="form-group">
            <label>Photo Barang <span class="text-danger">*</span></label>
            <input type="file" name="photo_barang" id="photoBarangInput" class="form-control" accept="image/*">
          </div>
          <div class="form-group">
            <label>Photo Packinglist <span class="text-danger">*</span></label>
            <input type="file" name="photo_packinglist" id="photoPackingInput" class="form-control" accept="image/*">
          </div>
          <div class="alert alert-info py-2 mb-0"><i class="fas fa-camera mr-1"></i> Di HP pilih kamera atau galeri dari menu bawaan. Kedua foto otomatis dikompres maksimal 1 MB.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary" id="savePhotoButton" disabled><i class="fas fa-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var openDetailBtn = document.getElementById('openDetailBtn');
  var detailTabLink = document.getElementById('detail-tab');
  var selectedWrhsid = <?= json_encode($selectedWrhsid) ?>;
  var startDate = <?= json_encode($startDate) ?>;
  var endDate = <?= json_encode($endDate) ?>;
  var pengebalanCsrf = <?= json_encode($pengebalanCsrf) ?>;
  var selectedHdId = <?= json_encode($selectedHdId) ?>;
  var dataLoadingOverlay = document.getElementById('dataLoadingOverlay');
  var photoZoomOverlay = document.getElementById('photoZoomOverlay');
  var photoZoomImage = document.getElementById('photoZoomImage');
  var photoZoomClose = document.getElementById('photoZoomClose');

  function openPhotoZoom(sourceImage) {
    if (!photoZoomOverlay || !photoZoomImage) {
      return;
    }
    photoZoomImage.src = sourceImage.currentSrc || sourceImage.src;
    photoZoomImage.alt = sourceImage.alt || 'Foto pengebalan';
    photoZoomImage.dataset.photoFile = sourceImage.dataset.photoFile || '';
    photoZoomOverlay.querySelectorAll('.btn-rotate-photo').forEach(function (button) {
      button.dataset.photoFile = sourceImage.dataset.photoFile || '';
    });
    photoZoomOverlay.classList.add('is-visible');
    photoZoomOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (photoZoomClose) {
      photoZoomClose.focus();
    }
  }

  function closePhotoZoom() {
    if (!photoZoomOverlay || !photoZoomImage) {
      return;
    }
    photoZoomOverlay.classList.remove('is-visible');
    photoZoomOverlay.setAttribute('aria-hidden', 'true');
    photoZoomImage.src = '';
    photoZoomImage.dataset.photoFile = '';
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.js-photo-zoom').forEach(function (image) {
    image.addEventListener('click', function () {
      openPhotoZoom(image);
    });
    image.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openPhotoZoom(image);
      }
    });
  });
  if (photoZoomClose) {
    photoZoomClose.addEventListener('click', closePhotoZoom);
  }
  if (photoZoomOverlay) {
    photoZoomOverlay.addEventListener('click', function (event) {
      if (event.target === photoZoomOverlay) {
        closePhotoZoom();
      }
    });
  }
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && photoZoomOverlay && photoZoomOverlay.classList.contains('is-visible')) {
      closePhotoZoom();
    }
  });

  function photoUrlWithVersion(source, version) {
    var url = new URL(source, window.location.href);
    url.searchParams.set('v', version || String(Date.now()));
    return url.href;
  }

  document.querySelectorAll('.btn-rotate-photo').forEach(function (button) {
    button.addEventListener('click', async function () {
      var photoFile = button.dataset.photoFile || '';
      var direction = button.dataset.direction || '';
      if (!photoFile || (direction !== 'left' && direction !== 'right')) {
        return;
      }

      var relatedButtons = Array.from(document.querySelectorAll('.btn-rotate-photo')).filter(function (item) {
        return item.dataset.photoFile === photoFile;
      });
      relatedButtons.forEach(function (item) { item.disabled = true; });
      showPageLoading(direction === 'left' ? 'Sedang memutar foto ke kiri...' : 'Sedang memutar foto ke kanan...');

      try {
        var formData = new FormData();
        formData.append('rotate_photo', '1');
        formData.append('csrf_token', pengebalanCsrf);
        formData.append('photo_file', photoFile);
        formData.append('direction', direction);

        var response = await fetch('pengebalan.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: {'Accept': 'application/json'},
          cache: 'no-store'
        });
        var result = await response.json().catch(function () {
          throw new Error('Respons rotasi dari server tidak valid.');
        });
        if (!response.ok || !result || result.status !== 'success') {
          throw new Error((result && result.message) || 'Foto gagal diputar.');
        }

        document.querySelectorAll('img[data-photo-file]').forEach(function (image) {
          if (image.dataset.photoFile === photoFile && image.src) {
            image.src = photoUrlWithVersion(image.src, result.version);
          }
        });
        if (photoZoomImage && photoZoomImage.dataset.photoFile === photoFile && photoZoomImage.src) {
          photoZoomImage.src = photoUrlWithVersion(photoZoomImage.src, result.version);
        }
      } catch (error) {
        alert(error.message || 'Foto gagal diputar.');
      } finally {
        relatedButtons.forEach(function (item) { item.disabled = false; });
        hidePageLoading();
      }
    });
  });

  function showPageLoading(message) {
    if (!dataLoadingOverlay) {
      return;
    }
    var label = dataLoadingOverlay.querySelector('.font-weight-bold');
    if (label && message) {
      label.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> ' + message;
    }
    dataLoadingOverlay.classList.add('is-visible');
    dataLoadingOverlay.setAttribute('aria-hidden', 'false');
  }

  function hidePageLoading() {
    if (!dataLoadingOverlay) {
      return;
    }
    dataLoadingOverlay.classList.remove('is-visible');
    dataLoadingOverlay.setAttribute('aria-hidden', 'true');
  }

  window.addEventListener('pageshow', hidePageLoading);
  window.addEventListener('beforeunload', function () {
    if (!dataLoadingOverlay || !dataLoadingOverlay.classList.contains('is-visible')) {
      showPageLoading('Sedang memuat data...');
    }
  });

  var uploadPhotoForm = document.getElementById('uploadPhotoForm');
  var savePhotoButton = document.getElementById('savePhotoButton');
  var photoInputPairs = [
    {
      label: 'Photo Barang',
      input: document.getElementById('photoBarangInput')
    },
    {
      label: 'Photo Packinglist',
      input: document.getElementById('photoPackingInput')
    }
  ];

  function selectedPhotoFile(pair) {
    return pair.input.files[0] || null;
  }

  function updateSavePhotoButton() {
    if (!savePhotoButton) {
      return;
    }
    savePhotoButton.disabled = !photoInputPairs.every(function (pair) {
      return Boolean(selectedPhotoFile(pair));
    }) || (uploadPhotoForm && uploadPhotoForm.dataset.submitting === '1');
  }

  photoInputPairs.forEach(function (pair) {
    pair.input.addEventListener('change', updateSavePhotoButton);
  });

  function resetPhotoInputs() {
    photoInputPairs.forEach(function (pair) {
      pair.input.value = '';
    });
    if (uploadPhotoForm) {
      delete uploadPhotoForm.dataset.submitting;
    }
    updateSavePhotoButton();
  }

  if (uploadPhotoForm) {
    uploadPhotoForm.addEventListener('submit', function (event) {
      var missingPair = photoInputPairs.find(function (pair) {
        return !selectedPhotoFile(pair);
      });
      if (missingPair) {
        event.preventDefault();
        alert(missingPair.label + ' wajib diisi melalui kamera atau galeri/file.');
        return;
      }

      var maxPhotoBytes = 18 * 1024 * 1024;
      var oversizedPair = photoInputPairs.find(function (pair) {
        return selectedPhotoFile(pair).size > maxPhotoBytes;
      });
      if (oversizedPair) {
        event.preventDefault();
        alert(oversizedPair.label + ' maksimal 18 MB. Pilih foto dengan ukuran lebih kecil.');
        return;
      }

      if (uploadPhotoForm.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }
      uploadPhotoForm.dataset.submitting = '1';
      savePhotoButton.disabled = true;
      showPageLoading('Sedang mengunggah dan menyimpan foto...');
    });
  }

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      window.setTimeout(function () {
        if (!event.defaultPrevented) {
          var message = form.querySelector('[name="delete_bale_upload"], [name="delete_selected_uploads"]')
            ? 'Sedang menghapus data...'
            : 'Sedang memproses data...';
          showPageLoading(message);
        }
      }, 0);
    });
  });

  function goToDetail() {
    if (selectedHdId > 0) {
      showPageLoading('Sedang memuat Sheet Detail...');
      window.location.href = 'pengebalan.php?wrhsid=' + encodeURIComponent(selectedWrhsid) + '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate) + '&tab=detail&hdid=' + encodeURIComponent(selectedHdId);
      return false;
    }

    var checkedBalehdids = [];
    document.querySelectorAll('.bale-check:checked').forEach(function (checkbox) {
      var checkboxGroup = [];
      try {
        checkboxGroup = JSON.parse(checkbox.getAttribute('data-group-balehdids') || '[]');
      } catch (error) {
        checkboxGroup = [];
      }
      if (!checkboxGroup.length) {
        checkboxGroup = [checkbox.getAttribute('data-balehdid') || ''];
      }
      checkboxGroup.forEach(function (balehdid) {
        if (balehdid && checkedBalehdids.indexOf(balehdid) === -1) {
          checkedBalehdids.push(balehdid);
        }
      });
    });
    if (!checkedBalehdids.length) {
      alert('Pilih 1 data dulu dari sheet list.');
      return false;
    }

    var url = 'pengebalan.php?wrhsid=' + encodeURIComponent(selectedWrhsid) + '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate) + '&tab=detail&selected_balehdids=' + encodeURIComponent(checkedBalehdids.join(','));
    showPageLoading('Sedang memuat Sheet Detail...');
    window.location.href = url;
    return false;
  }

  if (openDetailBtn) {
    openDetailBtn.addEventListener('click', goToDetail);
  }

  if (detailTabLink) {
    detailTabLink.addEventListener('click', function (event) {
      if (goToDetail() !== false) {
        return;
      }
      event.preventDefault();
    });
  }

  var deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
  var deleteSelectedForm = document.getElementById('deleteSelectedForm');
  var deleteSelectedInputs = document.getElementById('deleteSelectedInputs');

  function checkedRowBalehdids() {
    var balehdids = [];
    document.querySelectorAll('.bale-check:checked').forEach(function (checkbox) {
      var balehdid = checkbox.getAttribute('data-balehdid') || '';
      if (balehdid && balehdids.indexOf(balehdid) === -1) {
        balehdids.push(balehdid);
      }
    });
    return balehdids;
  }

  function updateDeleteSelectedButton() {
    if (deleteSelectedBtn) {
      deleteSelectedBtn.disabled = checkedRowBalehdids().length === 0;
    }
  }

  if (deleteSelectedBtn && deleteSelectedForm && deleteSelectedInputs) {
    deleteSelectedBtn.addEventListener('click', function () {
      var balehdids = checkedRowBalehdids();
      if (!balehdids.length) {
        alert('Pilih minimal satu data bale.');
        return;
      }
      if (!confirm('Hapus ' + balehdids.length + ' data bale yang dicentang?')) {
        return;
      }
      deleteSelectedInputs.innerHTML = '';
      balehdids.forEach(function (balehdid) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_balehdids[]';
        input.value = balehdid;
        deleteSelectedInputs.appendChild(input);
      });
      deleteSelectedForm.requestSubmit();
    });
  }

  document.querySelectorAll('.bale-check').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      var groupBalehdids = [];
      try {
        groupBalehdids = JSON.parse(checkbox.getAttribute('data-group-balehdids') || '[]');
      } catch (error) {
        groupBalehdids = [checkbox.getAttribute('data-balehdid') || ''];
      }
      document.querySelectorAll('.bale-check').forEach(function (groupCheckbox) {
        if (groupBalehdids.indexOf(groupCheckbox.getAttribute('data-balehdid') || '') !== -1) {
          groupCheckbox.checked = checkbox.checked;
        }
      });
      updateDeleteSelectedButton();
    });
  });
  updateDeleteSelectedButton();

  document.querySelectorAll('.btn-upload').forEach(function (button) {
    button.addEventListener('click', function () {
      resetPhotoInputs();
      var balehdid = button.getAttribute('data-balehdid') || '';
      var baleprodid = button.getAttribute('data-baleprodid') || '';
      var returnTab = button.getAttribute('data-return-tab') || 'detail';
      document.getElementById('uploadMode').value = 'bale';
      document.getElementById('uploadHdid').value = '';
      document.getElementById('uploadBalehdid').value = balehdid;
      document.getElementById('uploadBaleprodid').value = baleprodid;
      document.getElementById('uploadBalehdids').value = balehdid;
      document.getElementById('returnTab').value = returnTab;
      document.getElementById('returnSelectedBalehdid').value = balehdid;
      document.getElementById('returnSelectedBaleprodid').value = baleprodid;
      showModal('uploadModal');
    });
  });

  document.querySelectorAll('.btn-upload-sheet').forEach(function (button) {
    button.addEventListener('click', function () {
      resetPhotoInputs();
      document.getElementById('uploadMode').value = 'sheet';
      document.getElementById('uploadHdid').value = button.getAttribute('data-hdid') || '';
      document.getElementById('uploadBalehdid').value = '';
      document.getElementById('uploadBaleprodid').value = '';
      document.getElementById('uploadBalehdids').value = '';
      document.getElementById('returnTab').value = 'detail';
      document.getElementById('returnSelectedBalehdid').value = '';
      document.getElementById('returnSelectedBaleprodid').value = '';
      showModal('uploadModal');
    });
  });

  document.querySelectorAll('.btn-upload-draft, .btn-upload-group').forEach(function (button) {
    button.addEventListener('click', function () {
      resetPhotoInputs();
      document.getElementById('uploadMode').value = button.classList.contains('btn-upload-draft') ? 'draft' : 'group';
      document.getElementById('uploadHdid').value = '';
      document.getElementById('uploadBalehdid').value = '';
      document.getElementById('uploadBaleprodid').value = '';
      document.getElementById('uploadBalehdids').value = button.getAttribute('data-balehdids') || '';
      document.getElementById('returnTab').value = 'detail';
      document.getElementById('returnSelectedBalehdid').value = '';
      document.getElementById('returnSelectedBaleprodid').value = '';
      showModal('uploadModal');
    });
  });

  function showModal(modalId) {
    // Bootstrap 4/AdminLTE memakai jQuery. Cek dulu agar tidak muncul "$ is not defined".
    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
      window.jQuery('#' + modalId).modal('show');
      return;
    }

    console.error('Bootstrap/jQuery belum termuat. Pastikan jquery.min.js dan bootstrap.bundle.min.js dimuat sebelum script halaman.');
    alert('Komponen modal belum siap. Cek pemanggilan jQuery/Bootstrap pada header/footer.');
  }

  var selectedBales = {};
  var newBaleRows = document.getElementById('newBaleRows');
  var newBaleSearch = document.getElementById('newBaleSearch');
  var selectedBaleInputs = document.getElementById('selectedBaleInputs');
  var checkAllNewBales = document.getElementById('checkAllNewBales');
  var openNewModalBtn = document.getElementById('openNewModalBtn');

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"]/g, function (char) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[char];
    });
  }

  function syncSelectedInputs() {
    if (!selectedBaleInputs) {
      return;
    }
    selectedBaleInputs.innerHTML = Object.keys(selectedBales).map(function (balehdid) {
      return '<input type="hidden" name="selected_balehdids[]" value="' + escapeHtml(balehdid) + '">';
    }).join('');
  }

  function renderNewBales(rows) {
    if (!newBaleRows) {
      return;
    }
    if (!rows.length) {
      newBaleRows.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Data kosong</td></tr>';
      return;
    }

    newBaleRows.innerHTML = rows.map(function (row) {
      var balehdid = String(row.balehdid || '');
      return '<tr>' +
        '<td class="text-center align-middle"><input type="checkbox" class="new-bale-row" value="' + escapeHtml(balehdid) + '"' + (selectedBales[balehdid] ? ' checked' : '') + '></td>' +
        '<td>' + escapeHtml(row.balenmbr) + '</td>' +
        '<td>' + escapeHtml(row.refnmbr) + '</td>' +
        '<td>' + escapeHtml(row.baledate) + '</td>' +
      '</tr>';
    }).join('');
  }

  function loadNewBales(searchValue) {
    if (!newBaleRows) {
      return;
    }

    var q = typeof searchValue === 'string' ? searchValue.trim() : '';
    newBaleRows.innerHTML = '<tr><td colspan="4" class="py-3"><div class="text-center text-muted mb-2"><i class="fas fa-spinner fa-spin mr-1"></i> Sedang memuat data bale...</div><div class="progress" style="height:8px;"><div class="progress-bar progress-bar-striped progress-bar-animated loading-progress-bar"></div></div></td></tr>';

    var params = new URLSearchParams({
      ajax_search_bales: '1',
      wrhsid: selectedWrhsid,
      q: q
    });

    fetch('search_pengebalan_bales.php?' + params.toString(), {
      method: 'GET',
      headers: {'Accept': 'application/json'},
      cache: 'no-store'
    })
    .then(function (response) {
      return response.json().catch(function () {
        throw new Error('Respons server bukan JSON (HTTP ' + response.status + ')');
      }).then(function (data) {
        if (!response.ok) {
          throw new Error(data.message || ('HTTP ' + response.status));
        }
        return data;
      });
    })
    .then(function (response) {
      if (!response || response.status !== 'success') {
        throw new Error((response && response.message) || 'Response tidak valid');
      }
      renderNewBales(response.rows || []);
    })
    .catch(function (error) {
      console.error(error);
      newBaleRows.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Gagal load data: ' + escapeHtml(error.message) + '</td></tr>';
    });
  }

  // Saat klik New: hanya ambil TOP 10, tanpa search.
  if (openNewModalBtn) {
    openNewModalBtn.addEventListener('click', function () {
      if (newBaleSearch) {
        newBaleSearch.value = '';
      }
      loadNewBales('');
    });
  }

  // Search TIDAK jalan setiap mengetik. Hanya saat Enter.
  if (newBaleSearch) {
    newBaleSearch.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter') {
        return;
      }
      event.preventDefault();
      loadNewBales(newBaleSearch.value);
    });
  }

  if (newBaleRows) {
    newBaleRows.addEventListener('change', function (event) {
      if (!event.target.classList.contains('new-bale-row')) {
        return;
      }
      if (event.target.checked) {
        selectedBales[event.target.value] = true;
      } else {
        delete selectedBales[event.target.value];
      }
      syncSelectedInputs();
    });
  }

  if (checkAllNewBales) {
    checkAllNewBales.addEventListener('change', function () {
      document.querySelectorAll('.new-bale-row').forEach(function (checkbox) {
        checkbox.checked = checkAllNewBales.checked;
        if (checkbox.checked) {
          selectedBales[checkbox.value] = true;
        } else {
          delete selectedBales[checkbox.value];
        }
      });
      syncSelectedInputs();
    });
  }
})();
</script>
<?php include '../../../includes/footer.php'; ?>
</body>
</html>
