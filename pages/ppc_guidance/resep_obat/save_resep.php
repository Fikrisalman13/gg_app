<?php
// pages/resep_obat/save_resep.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/includes/field_trustee_helper.php';

header('Content-Type: application/json');

$requestId = bin2hex(random_bytes(8));

/** Log important save failures without recording request payloads or raw database errors. */
function logResepSaveError(string $message, array $context = [], string $severity = 'ERROR'): void
{
    global $requestId, $user;

    $logDirectory = __DIR__ . '/../../../logs';
    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }

    $entry = [
        'timestamp' => date(DATE_ATOM),
        'severity' => $severity,
        'request_id' => $requestId,
        'module' => 'ppc_guidance/resep_obat',
        'action' => 'save_resep',
        'user' => $user ?? ($_SESSION['UserName'] ?? null),
        'message' => $message,
        'source' => basename(__FILE__),
        'context' => $context,
    ];

    @file_put_contents(
        $logDirectory . '/error-' . date('Y-m-d') . '.log',
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

set_exception_handler(static function (Throwable $exception): void {
    global $requestId;
    logResepSaveError('Unhandled exception while saving recipe.', [
        'exception' => get_class($exception),
        'source_file' => basename($exception->getFile()),
        'source_line' => $exception->getLine(),
    ], 'CRITICAL');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan saat menyimpan resep.',
        'request_id' => $requestId,
    ]);
});

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized', 'request_id' => $requestId]);
    exit;
}

$user = $_SESSION['UserName'];
$permissionStatement = sqlsrv_query($conn, 'SELECT CanAdd, CanEdit FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=215', [$_SESSION['GroupId'] ?? 0]);
$permission = $permissionStatement ? sqlsrv_fetch_array($permissionStatement, SQLSRV_FETCH_ASSOC) : null;
$fieldPermissions = loadPpcResepFieldTrustee(
    $conn,
    $user,
    (int) ($_SESSION['GroupId'] ?? 0)
);

// Fix: Don't strip dots blindly. 
// For weight/plan_qty (type="number"), browser sends 100.50 (dot decimal).
// For receipe (type="text"), user sends 21,175 (comma decimal).
function cleanHeaderNum($str) {
    if ($str === '') return 0;
    // Assume standard float (dot decimal) from type="number"
    return floatval($str); 
}

// US Format: 3,410.50 (Comma thousand, Dot decimal)
function cleanUSNum($str) {
    if ($str === '') return 0;
    // Remove comma
    $val = str_replace(',', '', $str);
    return floatval($val);
}

function cleanReceipeNum($str) {
    // Legacy support or just alias?
    return cleanUSNum($str);
}

function cleanPriceNum($str) {
    // Input: "Rp 130.594,22" or "130594.22"
    // Remove Rp and spaces
    $val = str_replace(['Rp', ' '], '', $str);
    // Remove dots (thousand separator)
    $val = str_replace('.', '', $val);
    // Replace comma with dot (decimal separator)
    $val = str_replace(',', '.', $val);
    return floatval($val);
}


$resep_id = $_POST['resep_id'] ?? '';
if (($resep_id && ($permission['CanEdit'] ?? 0) != 1) || (!$resep_id && ($permission['CanAdd'] ?? 0) != 1)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Tidak memiliki hak menyimpan resep.', 'request_id' => $requestId]);
    exit;
}
$sourceExperimentId = filter_var($_POST['source_experiment_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$duplicateConfirmed = ($_POST['duplicate_confirmed'] ?? '') === '1';
$noCpDuplicateConfirmed = ($_POST['no_cp_duplicate_confirmed'] ?? '') === '1';
$no_cp = trim((string) ($_POST['no_cp'] ?? ''));
if (trim($no_cp) === '') {
    $no_cp = '-';
}
$kode_warna = $_POST['kode_warna'] ?? '';
$color_name = $_POST['color_name'] ?? '';
$color_desc = $_POST['color_desc'] ?? '';
$lot_no = $_POST['lot_no'] ?? '';
$weight = cleanUSNum($_POST['weight'] ?? 0); // US Format
$plan_qty = cleanHeaderNum($_POST['plan_qty'] ?? 0);
$items = $_POST['items'] ?? [];

$kode_grey = $_POST['kode_grey'] ?? '';
$mesin = $_POST['mesin'] ?? '';
$vlot = cleanHeaderNum($_POST['vlot'] ?? 0);

if (empty($kode_warna)) {
    echo json_encode(['status' => 'error', 'message' => 'Kode Warna wajib diisi']);
    exit;
}

$now = date('Y-m-d H:i:s');

$resepprodcode = $_POST['resepprodcode'] ?? '';
$resepprodname = $_POST['resepprodname'] ?? '';

// ProInt Details
$resep_no   = $_POST['resep_no'] ?? '';
$resep_seq  = $_POST['resep_seq'] ?? NULL;
$resep_date = $_POST['resep_date'] ?? NULL;
$resep_type = $_POST['resep_type'] ?? '';
$is_manual  = $_POST['is_manual'] ?? 0;

$status_resep_lipat = $_POST['status_resep_lipat'] ?? NULL;
if ($status_resep_lipat !== null && trim($status_resep_lipat) === '') {
    $status_resep_lipat = NULL;
}

$proint_resephdid = $_POST['proint_resephdid'] ?? NULL;
if ($proint_resephdid !== null && trim($proint_resephdid) === '') {
    $proint_resephdid = NULL;
} elseif ($proint_resephdid !== null) {
    $proint_resephdid = intval($proint_resephdid);
}

// New Fields
$no_cp_resep = $_POST['no_cp_resep'] ?? '';
if (trim($no_cp_resep) === '') $no_cp_resep = '-';

$no_so = $_POST['no_so'] ?? '';
if (trim($no_so) === '') $no_so = '-';

$rtg_code = $_POST['rtg_code'] ?? '';
if (trim($rtg_code) === '') $rtg_code = '-';

$rtg_name = $_POST['rtg_name'] ?? '';
if (trim($rtg_name) === '') $rtg_name = '-';

$status_desc = $_POST['status_desc'] ?? '';
if (trim($status_desc) === '') $status_desc = '-';

$cus_color = $_POST['cus_color'] ?? '';
if (trim($cus_color) === '') {
    // Fallback: ambil segmen pertama dari Description (color_desc) sebelum '/'
    // Hanya jika Description mengandung '/', contoh: "0604/1616NEW/..." -> "0604"
    // Jika tidak ada '/' (misal "0.1.0331.0.0"), simpan '-'
    $desc_trimmed = trim($color_desc);
    if ($desc_trimmed !== '' && strpos($desc_trimmed, '/') !== false) {
        $desc_parts = explode('/', $desc_trimmed);
        $cus_color = trim($desc_parts[0]) !== '' ? trim($desc_parts[0]) : '-';
    } else {
        $cus_color = '-';
    }
}

$headerValues = [
    'kode_grey' => &$kode_grey,
    'kode_warna' => &$kode_warna,
    'no_cp' => &$no_cp,
    'resep_prod_code' => &$resepprodcode,
    'cus_color' => &$cus_color,
    'lot_no' => &$lot_no,
    'plan_qty' => &$plan_qty,
    'weight' => &$weight,
    'vlot' => &$vlot,
];
$existingDetails = [];
if ($resep_id) {
    $baselineStatement = sqlsrv_query(
        $conn,
        'SELECT kode_grey,kode_warna,no_cp,resep_prod_code,cus_color,lot_no,plan_qty,weight,vlot
         FROM dbo.resep_obat WHERE id=?',
        [$resep_id]
    );
    $baseline = $baselineStatement
        ? sqlsrv_fetch_array($baselineStatement, SQLSRV_FETCH_ASSOC)
        : null;
    if (!$baseline) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Resep tidak ditemukan.', 'request_id' => $requestId]);
        exit;
    }
    foreach ($headerValues as $fieldKey => &$submittedValue) {
        if (isPpcResepFieldReadonly($fieldPermissions, $fieldKey)) {
            $submittedValue = $baseline[$fieldKey] ?? '';
        }
    }
    unset($submittedValue);

    $detailStatement = sqlsrv_query($conn, 'SELECT * FROM dbo.resep_obat_detail WHERE id_resep=? ORDER BY id', [$resep_id]);
    while ($detailStatement && $detailRow = sqlsrv_fetch_array($detailStatement, SQLSRV_FETCH_ASSOC)) {
        $existingDetails[] = $detailRow;
    }
    if (isPpcResepFieldReadonly($fieldPermissions, 'detail_items')) {
        $items = $existingDetails;
    } else {
        $detailColumns = [
            'detail_kode' => ['kode', 'name', 'category', 'uom', 'std_price', 'price_satuan', 'price_source', 'is_manual'],
            'detail_qty' => ['receipe'],
            'detail_cf' => ['cf'],
            'detail_uom_cf' => ['uom_cf'],
        ];
        foreach ($detailColumns as $fieldKey => $columns) {
            if (!isPpcResepFieldReadonly($fieldPermissions, $fieldKey)) {
                continue;
            }
            foreach ($items as $index => &$submittedItem) {
                foreach ($columns as $column) {
                    $submittedItem[$column] = $existingDetails[$index][$column] ?? '';
                }
            }
            unset($submittedItem);
        }
    }
} elseif (!$sourceExperimentId) {
    $protectedHeaderSubmitted = array_filter(
        array_keys($headerValues),
        static fn(string $fieldKey): bool => isPpcResepFieldReadonly($fieldPermissions, $fieldKey)
            && trim((string) $headerValues[$fieldKey]) !== ''
    );
    $protectedDetailsSubmitted = !empty($items) && (
        isPpcResepFieldReadonly($fieldPermissions, 'detail_items')
        || isPpcResepFieldReadonly($fieldPermissions, 'detail_kode')
        || isPpcResepFieldReadonly($fieldPermissions, 'detail_qty')
        || isPpcResepFieldReadonly($fieldPermissions, 'detail_cf')
        || isPpcResepFieldReadonly($fieldPermissions, 'detail_uom_cf')
    );
    if ($protectedHeaderSubmitted || $protectedDetailsSubmitted) {
        logResepSaveError('Blocked read-only trustee field submission.', ['field_count' => count($protectedHeaderSubmitted)], 'SECURITY');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Field Read Only tidak dapat disimpan untuk Sub Bagian Anda.',
            'request_id' => $requestId,
        ]);
        exit;
    }
}

if (!$noCpDuplicateConfirmed && trim($no_cp) !== '' && $no_cp !== '-') {
    $duplicateNoCpSql = "SELECT TOP 10 id, no_cp, kode_warna, cus_color, status_resep_lipat, created_at, created_by
        FROM dbo.resep_obat
        WHERE UPPER(LTRIM(RTRIM(no_cp))) = UPPER(?)";
    $duplicateNoCpParams = [$no_cp];
    if ($resep_id) {
        $duplicateNoCpSql .= ' AND id <> ?';
        $duplicateNoCpParams[] = (int) $resep_id;
    }
    $duplicateNoCpSql .= ' ORDER BY id DESC';
    $duplicateNoCpStatement = sqlsrv_query($conn, $duplicateNoCpSql, $duplicateNoCpParams);
    if ($duplicateNoCpStatement === false) {
        logResepSaveError('Failed to validate duplicate No CP.', ['recipe_id' => (int) ($resep_id ?: 0)]);
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal memvalidasi No CP. Data belum disimpan.',
            'request_id' => $requestId,
        ]);
        exit;
    }
    $duplicateNoCpRows = [];
    while ($duplicateNoCpRow = sqlsrv_fetch_array($duplicateNoCpStatement, SQLSRV_FETCH_ASSOC)) {
        $createdAt = $duplicateNoCpRow['created_at'] ?? null;
        $duplicateNoCpRows[] = [
            'id' => (int) $duplicateNoCpRow['id'],
            'no_cp' => trim((string) ($duplicateNoCpRow['no_cp'] ?? '')),
            'kode_warna' => trim((string) ($duplicateNoCpRow['kode_warna'] ?? '')) ?: '-',
            'cus_color' => trim((string) ($duplicateNoCpRow['cus_color'] ?? '')) ?: '-',
            'status' => trim((string) ($duplicateNoCpRow['status_resep_lipat'] ?? '')) ?: '-',
            'created_at' => $createdAt instanceof DateTimeInterface ? $createdAt->format('d-m-Y H:i') : '-',
            'created_by' => trim((string) ($duplicateNoCpRow['created_by'] ?? '')) ?: '-',
        ];
    }
    if ($duplicateNoCpRows) {
        http_response_code(409);
        echo json_encode([
            'status' => 'duplicate_no_cp_warning',
            'message' => 'No CP sudah pernah digunakan pada resep lain.',
            'no_cp' => $no_cp,
            'duplicates' => $duplicateNoCpRows,
            'request_id' => $requestId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($sourceExperimentId) {
    require_once __DIR__ . '/../../resep_obat/experiment/experiment_visibility_helper.php';
    $sourceConditions = ['e.id = ?', 'e.was_approved = 1'];
    $sourceParams = [$sourceExperimentId];
    resepExperimentApplyVisibility($sourceConditions, $sourceParams, 'e', $conn);
    $sourceStatement = sqlsrv_query($conn, 'SELECT e.id FROM dbo.resep_obat_experiment e WHERE ' . implode(' AND ', $sourceConditions), $sourceParams);
    if (!$sourceStatement || !sqlsrv_fetch_array($sourceStatement, SQLSRV_FETCH_ASSOC)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Sumber experiment tidak valid atau belum pernah Approved.', 'request_id' => $requestId]);
        exit;
    }
    if (!$duplicateConfirmed) {
        $duplicateParams = [$cus_color, $kode_warna];
        $duplicateWhere = 'UPPER(LTRIM(RTRIM(cus_color)))=UPPER(?) AND UPPER(LTRIM(RTRIM(kode_warna)))=UPPER(?)';
        if (trim($no_cp) !== '' && $no_cp !== '-') {
            $duplicateWhere .= ' AND UPPER(LTRIM(RTRIM(no_cp)))=UPPER(?)';
            $duplicateParams[] = $no_cp;
        }
        $duplicateStatement = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.resep_obat WHERE $duplicateWhere", $duplicateParams);
        if ($duplicateStatement && ($duplicate = sqlsrv_fetch_array($duplicateStatement, SQLSRV_FETCH_ASSOC))) {
            http_response_code(409);
            echo json_encode([
                'status' => 'duplicate_warning',
                'message' => 'Resep PPC serupa ditemukan. Konfirmasi Tetap Lanjut diperlukan.',
                'duplicate_id' => (int)$duplicate['id'],
                'request_id' => $requestId,
            ]);
            exit;
        }
    }
}

if ($resep_id) {
    // UPDATE
    $sql = "UPDATE dbo.resep_obat SET no_cp = ?, kode_warna = ?, color_name = ?, color_desc = ?, lot_no = ?, weight = ?, plan_qty = ?, kode_grey = ?, vlot = ?, resep_prod_code = ?, resep_prod_name = ?, resep_no = ?, resep_seq =?, resep_date = ?, resep_type = ?, is_manual = ?, updated_at = ?, updated_by = ?, no_cp_resep = ?, no_so = ?, rtg_code = ?, rtg_name = ?, status_desc = ?, cus_color = ?, mesin = ?, status_resep_lipat = ?, proint_resephdid = ? WHERE id = ?";
    $params = [$no_cp, $kode_warna, $color_name, $color_desc, $lot_no, $weight, $plan_qty, $kode_grey, $vlot, $resepprodcode, $resepprodname, $resep_no, $resep_seq, $resep_date, $resep_type, $is_manual, $now, $user, $no_cp_resep, $no_so, $rtg_code, $rtg_name, $status_desc, $cus_color, $mesin, $status_resep_lipat, $proint_resephdid, $resep_id];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        logResepSaveError('Failed to update recipe header.', ['recipe_id' => (int)$resep_id]);
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal memperbarui header resep.',
            'request_id' => $requestId,
        ]);
        exit;
    }
    
    // Clear old details before inserting the submitted detail set.
    $delSql = "DELETE FROM dbo.resep_obat_detail WHERE id_resep = ?";
    $deleteStatement = sqlsrv_query($conn, $delSql, [$resep_id]);
    if ($deleteStatement === false) {
        logResepSaveError('Failed to clear old recipe details.', ['recipe_id' => (int)$resep_id]);
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal memperbarui detail resep.',
            'request_id' => $requestId,
        ]);
        exit;
    }
    $id = $resep_id; // For reference in response

} else {
    // INSERT
    // Using OUTPUT INSERTED.id is safer for some SQL Server drivers than multiple queries
    $sql = "INSERT INTO dbo.resep_obat (no_cp, kode_warna, color_name, color_desc, lot_no, weight, plan_qty, kode_grey, vlot, resep_prod_code, resep_prod_name, resep_no, resep_seq, resep_date, resep_type, is_manual, created_at, created_by, no_cp_resep, no_so, rtg_code, rtg_name, status_desc, cus_color, mesin, status_resep_lipat, proint_resephdid, source_experiment_id) OUTPUT INSERTED.id VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [$no_cp, $kode_warna, $color_name, $color_desc, $lot_no, $weight, $plan_qty, $kode_grey, $vlot, $resepprodcode, $resepprodname, $resep_no, $resep_seq, $resep_date, $resep_type, $is_manual, $now, $user, $no_cp_resep, $no_so, $rtg_code, $rtg_name, $status_desc, $cus_color, $mesin, $status_resep_lipat, $proint_resephdid, $sourceExperimentId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        logResepSaveError('Failed to insert recipe header.');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menyimpan header resep.',
            'request_id' => $requestId,
        ]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id = $row['id'] ?? null;
    
    if (!$id) {
        logResepSaveError('Recipe header insert returned no ID.');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal mendapatkan ID resep baru.',
            'request_id' => $requestId,
        ]);
        exit;
    }
}

// Insert Details
$errDetail = '';
if (!empty($items) && is_array($items)) {
    $sqlDetail = "INSERT INTO dbo.resep_obat_detail (id_resep, kode, name, category, receipe, uom, cf, uom_cf, std_price, total, created_at, created_by, price_satuan, price_source, is_manual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    foreach ($items as $item) {
        $kode = trim((string)($item['kode'] ?? ''));
        $name = trim((string)($item['name'] ?? ''));
        if ($kode === '' && $name === '') {
            continue;
        }
        $category = $item['category'] ?? ''; // New
        
        // Clean Parsing
        $receipe = cleanUSNum($item['receipe'] ?? 0);
        
        $uom = $item['uom'] ?? '';
        // $cc = $item['cc']; // Removed
        $cf = cleanUSNum($item['cf'] ?? 0); // US Format
        $uom_cf = $item['uom_cf'] ?? ''; // New

        // Fix: Use cleanPriceNum
        $std_price = cleanPriceNum($item['std_price']); 
        
        // Calculate Total
        $total = $receipe * $std_price;
        
        // Apply G/L Logic (Same as input_resep.php)
        $uomUpper = strtoupper(trim($uom));
        if ($uomUpper === 'GR' || $uomUpper === 'G/L') {
            $total = $total / 1000;
        }
        $price_satuan = $item['price_satuan'] ?? '';
        $price_source = $item['price_source'] ?? '';
        $item_manual  = $item['is_manual'] ?? 0;
        
        $paramsDetail = [$id, $kode, $name, $category, $receipe, $uom, $cf, $uom_cf, $std_price, $total, $now, $user, $price_satuan, $price_source, $item_manual];
        $stmtD = sqlsrv_query($conn, $sqlDetail, $paramsDetail);
        if ($stmtD === false) {
            $errDetail = 'Satu atau lebih detail gagal disimpan.';
            logResepSaveError('Failed to insert recipe detail.', [
                'recipe_id' => (int)$id,
                'item_code' => $kode,
            ]);
        }
    }
}

if ($errDetail) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $errDetail,
        'request_id' => $requestId,
    ]);
} else {
    echo json_encode(['status' => 'success', 'id' => $id]);
}
?>
