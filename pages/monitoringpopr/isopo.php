<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
if (!isset($_GET['export_excel'])) {
    include '../../includes/header.php';
    include '../../includes/sidebar.php';
}
require_once __DIR__ . '/../../vendor/autoload.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn || !$conn3) {
    die('Koneksi ke database gagal: ' . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');
$userName = $_SESSION['UserName'];
$today = date('Y-m-d');
$startDate = $_GET['start_date'] ?? $today;
$endDate = $_GET['end_date'] ?? $today;
$startDateTime = DateTime::createFromFormat('Y-m-d', $startDate);
$endDateTime = DateTime::createFromFormat('Y-m-d', $endDate);
if (!$startDateTime) $startDate = $today;
if (!$endDateTime) $endDate = $today;
if ($endDate < $startDate) $endDate = $startDate;
$vendorQueryParam = $_GET['vendor_name'] ?? '';
$selectedVendor = $vendorQueryParam;
$selectedVendorList = [];
if (is_array($selectedVendor)) {
    $selectedVendorList = array_values(array_filter(array_map('trim', $selectedVendor)));
} else {
    $selectedVendorList = array_values(array_filter(array_map('trim', preg_split('/\s*[;,]\s*/', (string)$selectedVendor))));
}
$optionCheck = sqlsrv_query($conn, "SELECT 1 FROM sys.columns c INNER JOIN sys.tables t ON c.object_id=t.object_id INNER JOIN sys.schemas s ON t.schema_id=s.schema_id WHERE s.name='dbo' AND t.name='po_option' AND c.name='username'");
if ($optionCheck === false) {
    die('Cek po_option gagal: ' . print_r(sqlsrv_errors(), true));
}
// Kolom prodcode / prodname harus sudah ada di dbo.po_option.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_option'])) {
    $vendors = array_values(array_filter(array_map('trim', (array)($_POST['vendor_name'] ?? []))));
    foreach ($vendors as $vendor) {
        $existsStmt = sqlsrv_query($conn, 'SELECT TOP 1 1 FROM dbo.po_option WHERE username = ? AND vendor_name = ?', [$userName, $vendor]);
        if ($existsStmt === false) die('Cek option gagal: ' . print_r(sqlsrv_errors(), true));
        $exists = (bool) sqlsrv_fetch_array($existsStmt, SQLSRV_FETCH_NUMERIC);
        sqlsrv_free_stmt($existsStmt);
        if (!$exists) {
            $ins = sqlsrv_query($conn, 'INSERT INTO dbo.po_option (username, vendor_name) VALUES (?, ?)', [$userName, $vendor]);
            if ($ins === false) die('Simpan option gagal: ' . print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($ins);
        }
    }
    $_SESSION['success'] = 'Option vendor tersimpan.';
    header('Location: isopo.php?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) . '&vendor_name=' . urlencode($selectedVendor));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prodcode_option'])) {
    $vendorName = trim((string)($_POST['vendor_name'] ?? ''));
    $prodRows = (array)($_POST['prodcode'] ?? []);
    $productNameMap = [];
    foreach ($productList as $product) {
        $productNameMap[(string)($product['prodcode'] ?? '')] = (string)($product['prodname'] ?? '');
    }
    foreach ($prodRows as $prodcodeValue) {
        $prodcode = trim((string)$prodcodeValue);
        $prodname = trim((string)($productNameMap[$prodcode] ?? ''));
        if ($vendorName === '' || $prodcode === '') continue;
        $existsStmt = sqlsrv_query($conn, 'SELECT TOP 1 1 FROM dbo.po_option WHERE username = ? AND vendor_name = ? AND prodcode = ?', [$userName, $vendorName, $prodcode]);
        if ($existsStmt === false) die('Cek prodcode gagal: ' . print_r(sqlsrv_errors(), true));
        $exists = (bool) sqlsrv_fetch_array($existsStmt, SQLSRV_FETCH_NUMERIC);
        sqlsrv_free_stmt($existsStmt);
        if (!$exists) {
            $ins = sqlsrv_query($conn, 'INSERT INTO dbo.po_option (username, vendor_name, prodcode, prodname) VALUES (?, ?, ?, ?)', [$userName, $vendorName, $prodcode, $prodname]);
            if ($ins === false) die('Simpan prodcode gagal: ' . print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($ins);
        }
    }
    $_SESSION['success'] = 'Option prodcode tersimpan.';
    header('Location: isopo.php?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_option'])) {
    $vendorInput = $_POST['vendor_name'] ?? '';
    $vendor = trim(is_array($vendorInput) ? ($vendorInput[0] ?? '') : $vendorInput);
    if ($vendor !== '') {
        $del = sqlsrv_query($conn, 'DELETE FROM dbo.po_option WHERE username = ? AND vendor_name = ?', [$userName, $vendor]);
        if ($del === false) die('Hapus option gagal: ' . print_r(sqlsrv_errors(), true));
        sqlsrv_free_stmt($del);
        $_SESSION['success'] = 'Option vendor dihapus.';
    }
    header('Location: isopo.php?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate));
    exit;
}

$vendorStmt = $conn3->query("SELECT DISTINCT vendorname FROM smvendor WHERE vendorname IS NOT NULL AND vendorname <> '' ORDER BY vendorname");
$vendorList = $vendorStmt ? $vendorStmt->fetchAll(PDO::FETCH_COLUMN) : [];

$productStmt = $conn3->query("SELECT prodcode, prodname FROM smproduct WHERE fgstatus = 'A' AND prodcode IS NOT NULL AND prodcode <> '' ORDER BY prodcode LIMIT 5");
$productList = $productStmt ? $productStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$savedOptionsStmt = sqlsrv_query($conn, 'SELECT vendor_name FROM dbo.po_option WHERE username = ? ORDER BY vendor_name', [$userName]);
$savedOptionList = [];
if ($savedOptionsStmt !== false) {
    while ($row = sqlsrv_fetch_array($savedOptionsStmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['vendor_name'])) $savedOptionList[] = $row['vendor_name'];
    }
    sqlsrv_free_stmt($savedOptionsStmt);
}

$savedProdStmt = sqlsrv_query($conn, 'SELECT vendor_name, prodcode, prodname FROM dbo.po_option WHERE username = ? AND prodcode IS NOT NULL ORDER BY vendor_name, prodcode', [$userName]);
$savedProdList = [];
if ($savedProdStmt !== false) {
    while ($row = sqlsrv_fetch_array($savedProdStmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['vendor_name'])) $savedProdList[] = $row;
    }
    sqlsrv_free_stmt($savedProdStmt);
}

$filterRules = [];
$filterRules = [];
foreach ($savedOptionList as $savedVendorName) {
    $vendorKey = strtoupper(trim((string)$savedVendorName));
    if ($vendorKey === '') continue;
    if (!isset($filterRules[$vendorKey])) $filterRules[$vendorKey] = ['all' => false, 'codes' => []];
    $filterRules[$vendorKey]['all'] = true;
}
foreach ($savedProdList as $savedProd) {
    $vendorKey = strtoupper(trim((string)($savedProd['vendor_name'] ?? '')));
    $prodcodeKey = strtoupper(trim((string)($savedProd['prodcode'] ?? '')));
    if ($vendorKey === '') continue;
    if (!isset($filterRules[$vendorKey])) $filterRules[$vendorKey] = ['all' => false, 'codes' => []];
    if ($prodcodeKey !== '') {
        $filterRules[$vendorKey]['codes'][$prodcodeKey] = true;
        $filterRules[$vendorKey]['all'] = false;
    }
}

if (empty($selectedVendorList) && !empty($savedOptionList)) {
    $selectedVendorList = $savedOptionList;
}

$filterVendorLookup = [];
foreach ($selectedVendorList as $vendorFilter) {
    $vendorKey = strtoupper(trim((string)$vendorFilter));
    if ($vendorKey !== '') $filterVendorLookup[$vendorKey] = true;
}

$query = "SELECT h.ponmbr, h.podate, h.povendorname, d.poprodname, h.fgstatus, d.poprodid, d.poprodcode, d.poqty FROM prpohd h INNER JOIN prpodt d ON h.pohdid = d.pohdid WHERE h.podate BETWEEN ? AND ? ORDER BY h.podate, h.ponmbr";
$stmt = $conn3->prepare($query);
$stmt->execute([$startDate, $endDate]);
$poData = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($poData)) $poData = [];
if (!empty($filterRules)) {
    $poData = array_values(array_filter($poData, function ($row) use ($filterRules, $filterVendorLookup) {
        $vendorKey = strtoupper(trim((string)($row['povendorname'] ?? '')));
        $prodcodeKey = strtoupper(trim((string)($row['poprodcode'] ?? '')));
        if ($vendorKey === '' || !isset($filterRules[$vendorKey])) return false;
        if (!empty($filterVendorLookup) && !isset($filterVendorLookup[$vendorKey])) return false;
        if (!empty($filterRules[$vendorKey]['codes'])) {
            return $prodcodeKey !== '' && isset($filterRules[$vendorKey]['codes'][$prodcodeKey]);
        }
        return $filterRules[$vendorKey]['all'];
    }));
}
$statusSummary = ['O'=>['count'=>0,'label'=>'Open','color'=>'warning'],'X'=>['count'=>0,'label'=>'Close','color'=>'primary'],'V'=>['count'=>0,'label'=>'Approve','color'=>'success'],'U'=>['count'=>0,'label'=>'Outstanding','color'=>'info'],'C'=>['count'=>0,'label'=>'Cancel','color'=>'danger']];
$statusSummary = ['O'=>['count'=>0,'label'=>'Open','color'=>'warning'],'X'=>['count'=>0,'label'=>'Close','color'=>'primary'],'V'=>['count'=>0,'label'=>'Approve','color'=>'success'],'U'=>['count'=>0,'label'=>'Outstanding','color'=>'info'],'C'=>['count'=>0,'label'=>'Cancel','color'=>'danger']];
$uniquePoNumbers = [];
$totalItems = 0;
$monthlySummary = [];
foreach ($poData as $po) {
    if (isset($statusSummary[$po['fgstatus']])) $statusSummary[$po['fgstatus']]['count']++;
    $ponmbr = trim((string)($po['ponmbr'] ?? ''));
    if ($ponmbr !== '') $uniquePoNumbers[$ponmbr] = true;
    $totalItems++;
    $poDate = !empty($po['podate']) ? date('Y-m', strtotime((string)$po['podate'])) : '';
    if ($poDate === '') continue;
    if (!isset($monthlySummary[$poDate])) {
        $monthlySummary[$poDate] = ['month' => $poDate, 'closed' => 0, 'items' => 0];
    }
    $monthlySummary[$poDate]['items']++;
    if ((string)($po['fgstatus'] ?? '') === 'X') {
        $monthlySummary[$poDate]['closed']++;
    }
}
$monthlySummary = array_values($monthlySummary);
usort($monthlySummary, function ($left, $right) {
    return strcmp($left['month'], $right['month']);
});
$totalPo = count($uniquePoNumbers);
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$totalRows = count($poData);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;
$pagedPoData = array_slice($poData, $offset, $perPage);
?>
</style>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monitoring ISO PO</title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Monitoring ISO PO</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
              <li class="breadcrumb-item active">Monitoring ISO PO</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <div class="content"><div class="container-fluid">
      <?php if (isset($error_message)) : ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php else : ?>
      <?php if (!empty($_SESSION['success'])) : ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
      <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?> mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Filter</span>
          <div>
            <a href="export_isopo.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&vendor_name=<?= urlencode($vendorQueryParam) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="isopo_option.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-primary btn-sm"><i class="fas fa-cog"></i> Option</a>
          </div>
        </div>
        <div class="card-body">
          <form method="get" class="row align-items-end">
            <div class="col-md-4"><label class="form-label">Tanggal PO Dari</label><input type="date" name="start_date" id="startDate" class="form-control" value="<?= htmlspecialchars($startDate) ?>" max="<?= htmlspecialchars($today) ?>"></div>
            <div class="col-md-4"><label class="form-label">Tanggal PO Sampai</label><input type="date" name="end_date" id="endDate" class="form-control" value="<?= htmlspecialchars($endDate) ?>" max="<?= htmlspecialchars($today) ?>" required></div>
            <div class="col-md-4"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button></div>
          </form>
          <?php if (!empty($selectedVendor)) : ?>
            <div class="mt-3 small text-muted">
              Filter vendor aktif:
              <strong>
                <?php if (is_array($selectedVendor)) : ?>
                  <?= htmlspecialchars(implode(', ', $selectedVendor)) ?>
                <?php else : ?>
                  <?= htmlspecialchars($selectedVendor) ?>
                <?php endif; ?>
              </strong>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-2 col-sm-4 mb-2"><div class="small-box bg-secondary"><div class="inner"><h3><?= (int)$totalPo ?></h3><p>Jumlah PO</p></div></div></div>
        <div class="col-md-2 col-sm-4 mb-2"><div class="small-box bg-secondary"><div class="inner"><h3><?= (int)$totalItems ?></h3><p>Jumlah Item PO</p></div></div></div>
        <?php foreach ($statusSummary as $summary) : ?><div class="col-md-2 col-sm-4 mb-2"><div class="small-box bg-<?= $summary['color'] ?>"><div class="inner"><h3><?= (int)$summary['count'] ?></h3><p><?= htmlspecialchars($summary['label']) ?></p></div></div></div><?php endforeach; ?>
      </div>
      <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?> mb-3">
        <div class="card-header">Rekap Pemenuhan PO Bulanan</div>
        <div class="card-body table-responsive">
          <table class="table table-bordered table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>BULAN</th>
                <th class="text-center">JUMLAH CLOSED</th>
                <th class="text-center">JUMLAH ITEM</th>
                <th class="text-end">Persentase Pemenuhan PO</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($monthlySummary)) : ?>
                <tr><td colspan="4" class="text-center text-muted">Data kosong</td></tr>
              <?php else : ?>
                <?php foreach ($monthlySummary as $summary) : ?>
                  <?php $percent = $summary['items'] > 0 ? ($summary['closed'] / $summary['items']) * 100 : 0; ?>
                  <tr>
                    <td><?= htmlspecialchars(date('F Y', strtotime($summary['month'] . '-01'))) ?></td>
                    <td class="text-center"><?= (int)$summary['closed'] ?></td>
                    <td class="text-center"><?= (int)$summary['items'] ?></td>
                    <td class="text-end"><?= number_format($percent, 2, ',', '.') ?>%</td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?>"><div class="card-header">Data PO</div><div class="card-body"><div class="table-responsive"><table id="poTable" class="table table-bordered table-hover table-sm"><thead><tr><th>No</th><th>No PO</th><th>Tgl PO</th><th>Vendor</th><th>Kode Produk</th><th>Nama Produk</th><th>Qty</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($pagedPoData as $index => $po) : ?><tr><td class="text-center"><?= $offset + $index + 1 ?></td><td><?= htmlspecialchars($po['ponmbr']) ?></td><td><?= htmlspecialchars(date('d/m/Y', strtotime($po['podate']))) ?></td><td><?= htmlspecialchars($po['povendorname']) ?></td><td><?= htmlspecialchars($po['poprodcode']) ?></td><td><?= htmlspecialchars($po['poprodname']) ?></td><td class="text-end"><?= htmlspecialchars($po['poqty']) ?></td><td class="text-center"><?php $badge='secondary'; $text=$po['fgstatus']; if($po['fgstatus']==='O'){ $badge='warning'; $text='Open'; } if($po['fgstatus']==='X'){ $badge='primary'; $text='Close'; } if($po['fgstatus']==='V'){ $badge='success'; $text='Approve'; } if($po['fgstatus']==='U'){ $badge='info'; $text='Outstanding'; } if($po['fgstatus']==='C'){ $badge='danger'; $text='Cancel'; } ?><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($text) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">Menampilkan <?= count($pagedPoData) ?> dari <?= (int)$totalRows ?> data</div>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php $queryBase = $_GET; unset($queryBase['page']); ?>
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => max(1, $currentPage - 1)])) ?>">&laquo;</a>
            </li>
            <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) : ?>
              <li class="page-item <?= $pageNumber === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => min($totalPages, $currentPage + 1)])) ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div></div>
  </div>

  <div class="modal fade" id="vendorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Option Vendor</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <form method="post">
          <div class="modal-body">
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Vendor name</label>
                <span class="badge badge-info option-count" id="vendorSelectedCount">0 terpilih</span>
              </div>
              <input type="text" id="vendorSearch" class="form-control form-control-sm mb-2 option-search" placeholder="Cari vendor...">
              <div class="border rounded p-2 option-list" id="vendorListBox">
                <?php foreach ($vendorList as $vendor) : ?>
                  <div class="form-check vendor-item">
                    <input class="form-check-input vendor-checkbox" type="checkbox" name="vendor_name[]" value="<?= htmlspecialchars($vendor) ?>" id="v_<?= md5($vendor) ?>" <?= in_array($vendor, $savedOptionList, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="v_<?= md5($vendor) ?>"><?= htmlspecialchars($vendor) ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small text-muted mb-0">Vendor tersimpan</div>
                <input type="text" id="savedVendorSearch" class="form-control form-control-sm option-search w-50" placeholder="Cari vendor tersimpan...">
              </div>
              <div class="table-responsive border rounded" style="max-height:220px;overflow:auto;">
                <table class="table table-sm table-striped mb-0" id="savedVendorTable">
                  <thead><tr><th>Vendor</th><th class="text-end">Aksi</th></tr></thead>
                  <tbody>
                    <?php if (!empty($savedOptionList)) : ?>
                      <?php foreach ($savedOptionList as $item) : ?>
                        <tr class="saved-vendor-row">
                          <td><?= htmlspecialchars($item) ?></td>
                          <td class="text-end">
                            <a href="isopo_prodcode.php?vendor_name=<?= urlencode($item) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-sm btn-outline-primary">Prodcode</a>
                            <button type="submit" name="delete_option" value="1" class="btn btn-sm btn-outline-danger" onclick="this.form.vendor_name.value='<?= htmlspecialchars($item, ENT_QUOTES) ?>';">Hapus</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else : ?>
                      <tr><td colspan="2" class="text-muted">Belum ada</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="modal-footer"><a href="export_isopo.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&vendor_name=<?= urlencode($vendorQueryParam) ?>" class="btn btn-success mr-auto"><i class="fas fa-file-excel"></i> Export Vendor</a><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button><button type="submit" name="save_option" value="1" class="btn btn-success">Simpan Option Vendor</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="prodcodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Option Prodcode</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <form method="post">
          <input type="hidden" name="vendor_name" id="prodVendorName">
          <div class="modal-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="font-weight-bold" id="prodVendorLabel">Vendor</div>
              <span class="badge badge-info option-count" id="prodSelectedCount">0 terpilih</span>
            </div>
              <input type="text" id="prodSearch" class="form-control form-control-sm mb-2 option-search" placeholder="Cari prodcode..." autocomplete="off" autofocus>
              <div class="border rounded p-2 option-list" id="prodListBox">
              <?php foreach ($productList as $product) : ?>
                <div class="form-check prod-item">
                  <input class="form-check-input prod-checkbox" type="checkbox" name="prodcode[]" value="<?= htmlspecialchars($product['prodcode']) ?>" id="p_<?= md5($product['prodcode']) ?>">
                  <label class="form-check-label" for="p_<?= md5($product['prodcode']) ?>"><?= htmlspecialchars($product['prodcode']) ?> - <?= htmlspecialchars($product['prodname']) ?></label>
                </div>
              <?php endforeach; ?>
              </div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button><button type="submit" name="save_prodcode_option" value="1" class="btn btn-success">Simpan Option Prodcode</button></div>
        </form>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var searchInput = document.getElementById('vendorSearch');
      var savedSearchInput = document.getElementById('savedVendorSearch');
      var prodSearchInput = document.getElementById('prodSearch');
      var prodListBox = document.getElementById('prodListBox');
      var prodCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.prod-checkbox'));
      var items = Array.prototype.slice.call(document.querySelectorAll('.vendor-item'));
      var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.vendor-checkbox'));
      var savedRows = Array.prototype.slice.call(document.querySelectorAll('.saved-vendor-row'));
      var counter = document.getElementById('vendorSelectedCount');
      var prodCounter = document.getElementById('prodSelectedCount');
      var prodVendorName = document.getElementById('prodVendorName');
      var prodVendorLabel = document.getElementById('prodVendorLabel');
      var startDate = document.getElementById('startDate');
      var endDate = document.getElementById('endDate');
      var initialProdListHtml = prodListBox ? prodListBox.innerHTML : '';
      var prodSearchTimer = null;
      var prodItems = Array.prototype.slice.call(document.querySelectorAll('.prod-item'));
      var selectedProdMap = <?= json_encode($savedProdMap) ?>;

      function updateCount() {
        var count = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        counter.textContent = count + ' terpilih';
      }

      function syncDateLimits() {
        if (!startDate || !endDate) return;
        if (startDate.value && endDate.value && endDate.value < startDate.value) endDate.value = startDate.value;
        if (startDate.value) endDate.min = startDate.value;
        startDate.max = today;
        endDate.max = today;
      }

      function filterItems() {
        var term = (searchInput.value || '').toLowerCase();
        items.forEach(function (item) {
          var label = item.textContent.toLowerCase();
          item.style.display = label.indexOf(term) !== -1 ? '' : 'none';
        });
      }

      function filterSavedRows() {
        if (!savedSearchInput) return;
        var term = (savedSearchInput.value || '').toLowerCase();
        savedRows.forEach(function (row) {
          var label = row.textContent.toLowerCase();
          row.style.display = label.indexOf(term) !== -1 ? '' : 'none';
        });
      }

      function updateProdCount() {
        var count = prodCheckboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        prodCounter.textContent = count + ' terpilih';
      }

      function renderProdItems(data) {
        if (!prodListBox) return;
        var fragment = document.createDocumentFragment();
        (Array.isArray(data) ? data : []).forEach(function (product) {
          var row = document.createElement('div');
          row.className = 'form-check prod-item';
          var checkbox = document.createElement('input');
          checkbox.className = 'form-check-input prod-checkbox';
          checkbox.type = 'checkbox';
          checkbox.name = 'prodcode[]';
          checkbox.value = product.prodcode || '';
          checkbox.id = 'p_' + String(product.prodcode || '').replace(/[^a-zA-Z0-9_-]/g, '_');
          if (selectedProdMap[checkbox.value]) checkbox.checked = true;
          var label = document.createElement('label');
          label.className = 'form-check-label';
          label.setAttribute('for', checkbox.id);
          label.textContent = (product.prodcode || '') + ' - ' + (product.prodname || '');
          row.appendChild(checkbox);
          row.appendChild(label);
          fragment.appendChild(row);
        });
        prodListBox.innerHTML = '';
        if (!fragment.childNodes.length) {
          prodListBox.innerHTML = '<div class="text-muted small">Tidak ada data</div>';
        } else {
          prodListBox.appendChild(fragment);
        }
        prodItems = Array.prototype.slice.call(document.querySelectorAll('.prod-item'));
        prodCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.prod-checkbox'));
        prodCheckboxes.forEach(function (checkbox) {
          checkbox.addEventListener('change', function () {
            if (checkbox.checked) selectedProdMap[checkbox.value] = true; else delete selectedProdMap[checkbox.value];
            updateProdCount();
          });
        });
        updateProdCount();
      }

      function loadProdItems(term) {
        var query = String(term || '').trim();
        if (prodSearchTimer) clearTimeout(prodSearchTimer);
        prodSearchTimer = setTimeout(function () {
          if (!query) {
            if (prodListBox) prodListBox.innerHTML = initialProdListHtml;
            prodItems = Array.prototype.slice.call(document.querySelectorAll('.prod-item'));
            prodCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.prod-checkbox'));
            prodCheckboxes.forEach(function (checkbox) {
              checkbox.addEventListener('change', function () {
                if (checkbox.checked) selectedProdMap[checkbox.value] = true; else delete selectedProdMap[checkbox.value];
                updateProdCount();
              });
            });
            updateProdCount();
            return;
          }
          fetch('isopo_prodsearch.php?q=' + encodeURIComponent(query), { credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
            .then(function (data) { renderProdItems(data); })
            .catch(function () {
              if (prodListBox) prodListBox.innerHTML = initialProdListHtml;
            });
        }, 250);
      }

      function filterProdItems() {
        loadProdItems(prodSearchInput ? prodSearchInput.value : '');
      }

      searchInput.addEventListener('input', filterItems);
      if (savedSearchInput) savedSearchInput.addEventListener('input', filterSavedRows);
      if (prodSearchInput) prodSearchInput.addEventListener('input', filterProdItems);
      if (startDate && endDate) {
        startDate.addEventListener('change', syncDateLimits);
        endDate.addEventListener('change', syncDateLimits);
        syncDateLimits();
      }
      checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateCount);
      });
      prodCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateProdCount);
      });

      function focusProdSearch() {
        if (!prodSearchInput) return;
        setTimeout(function () {
          prodSearchInput.focus();
          prodSearchInput.select();
        }, 0);
      }

      function openModal(modal) {
        if (!modal) return;
        modal.style.display = 'block';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        modal.removeAttribute('aria-modal');
        document.body.classList.add('modal-open');
        if (!document.getElementById('nativeModalBackdrop')) {
          var backdrop = document.createElement('div');
          backdrop.id = 'nativeModalBackdrop';
          backdrop.className = 'modal-backdrop fade show';
          document.body.appendChild(backdrop);
        }
      }

      function closeModal(modal) {
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('nativeModalBackdrop');
        if (backdrop) backdrop.remove();
      }

      if (prodListBox) {
        prodListBox.addEventListener('click', function (event) { event.stopPropagation(); });
      }
      if (prodSearchInput) {
        prodSearchInput.addEventListener('click', function (event) { event.stopPropagation(); });
      }

      if (prodListBox) {
        prodListBox.addEventListener('click', function (event) { event.stopPropagation(); });
      }
      if (prodSearchInput) {
        prodSearchInput.addEventListener('click', function (event) { event.stopPropagation(); });
      }

      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.btn-prodcode-option');
        if (trigger) {
          event.preventDefault();
          var vendor = String(trigger.getAttribute('data-vendor') || '').trim();
          if (!vendor) return;
          prodVendorName.value = vendor;
          prodVendorLabel.textContent = 'Vendor: ' + vendor;
          prodCheckboxes.forEach(function (checkbox) {
            checkbox.checked = false;
          });
          <?php if (!empty($savedProdMap)) : ?>
          var selectedMap = <?= json_encode($savedProdMap) ?>;
          if (selectedMap[vendor]) {
            prodCheckboxes.forEach(function (checkbox) {
              if (selectedMap[vendor][checkbox.value]) checkbox.checked = true;
            });
          }
          <?php endif; ?>
          updateProdCount();
          filterProdItems();
          openModal(document.getElementById('prodcodeModal'));
          focusProdSearch();
          return;
        }

        if (event.target.closest('[data-dismiss="modal"]')) {
          var modal = event.target.closest('.modal');
          if (modal) closeModal(modal);
        }
      });

      updateCount();
      updateProdCount();
    })();
  </script>

  <?php include '../../includes/footer.php'; ?>
</body>
</html>















