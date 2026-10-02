<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!isset($_SESSION['UserName'])) { header('Location: /gg_app/login.php'); exit; }
if (!$conn || !$conn3) { die('Koneksi ke database gagal: ' . print_r(sqlsrv_errors(), true)); }

$userName = $_SESSION['UserName'];
$today = date('Y-m-d');
$startDate = $_GET['start_date'] ?? $today;
$endDate = $_GET['end_date'] ?? $today;
if ($endDate < $startDate) $endDate = $startDate;
$vendor = trim((string)($_GET['vendor_name'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prodcode_option'])) {
    $vendorName = trim((string)($_POST['vendor_name'] ?? ''));
    $prodRows = (array)($_POST['prodcode'] ?? []);
    $productStmt = $conn3->query("SELECT prodcode, prodname FROM smproduct WHERE prodcode IS NOT NULL AND prodcode <> '' ORDER BY prodcode");
    $productList = $productStmt ? $productStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $productNameMap = [];
    foreach ($productList as $product) { $productNameMap[(string)($product['prodcode'] ?? '')] = (string)($product['prodname'] ?? ''); }
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
    header('Location: isopo_prodcode.php?vendor_name=' . urlencode($vendorName) . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate));
    exit;
}

$savedProdStmt = sqlsrv_query($conn, 'SELECT prodcode, prodname FROM dbo.po_option WHERE username = ? AND vendor_name = ? AND prodcode IS NOT NULL ORDER BY prodcode', [$userName, $vendor]);
$savedProdList = [];
if ($savedProdStmt !== false) { while ($row = sqlsrv_fetch_array($savedProdStmt, SQLSRV_FETCH_ASSOC)) { if (!empty($row['prodcode'])) $savedProdList[] = $row; } sqlsrv_free_stmt($savedProdStmt); }

$productList = [];
$productStmt = $conn3->query("SELECT prodcode, prodname FROM smproduct WHERE prodcode IS NOT NULL AND prodcode <> '' ORDER BY prodcode LIMIT 300");
if ($q !== '') {
    $stmt = $conn3->prepare("SELECT prodcode, prodname FROM smproduct WHERE prodcode IS NOT NULL AND prodcode <> '' AND (UPPER(prodcode) LIKE UPPER(?) OR UPPER(prodname) LIKE UPPER(?)) ORDER BY prodcode LIMIT 300");
    if ($stmt && $stmt->execute(["%" . $q . "%", "%" . $q . "%"])) { $productList = $stmt->fetchAll(PDO::FETCH_ASSOC); }
} elseif ($productStmt) {
    $productList = $productStmt->fetchAll(PDO::FETCH_ASSOC);
}

$savedMap = [];
foreach ($savedProdList as $savedProd) { $savedMap[(string)$savedProd['prodcode']] = true; }
?>
<body>
<div class="content-wrapper p-3">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Option Prodcode</h4>
      <a href="isopo_option.php?start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
    <div class="mb-2 small text-muted">Vendor: <?= htmlspecialchars($vendor) ?> | Total saved: <?= count($savedProdList) ?></div>
    <form method="get" class="mb-3">
      <input type="hidden" name="vendor_name" value="<?= htmlspecialchars($vendor) ?>">
      <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
      <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
      <div class="row g-2">
        <div class="col-md-8 mb-2"><input type="text" name="q" id="prodSearch" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Cari prodcode..." autocomplete="off"></div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <a href="isopo_prodcode.php?vendor_name=<?= urlencode($vendor) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>
    <form method="post">
      <input type="hidden" name="vendor_name" value="<?= htmlspecialchars($vendor, ENT_QUOTES) ?>">
      <div class="mb-2"><button type="submit" name="save_prodcode_option" value="1" class="btn btn-success btn-sm">Simpan</button></div>
      <div class="border rounded p-2" id="prodListBox" style="max-height:420px;overflow:auto;">
        <?php foreach ($productList as $product) : $code = (string)($product['prodcode'] ?? ''); ?>
          <div class="form-check prod-item">
            <input class="form-check-input prod-checkbox" type="checkbox" name="prodcode[]" value="<?= htmlspecialchars($code) ?>" id="p_<?= md5($code) ?>" <?= isset($savedMap[$code]) ? 'checked' : '' ?>>
            <label class="form-check-label" for="p_<?= md5($code) ?>"><?= htmlspecialchars($code) ?> - <?= htmlspecialchars((string)($product['prodname'] ?? '')) ?></label>
          </div>
        <?php endforeach; ?>
        <?php if (empty($productList)) : ?><div class="text-muted small">Tidak ada data</div><?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
</body></html>

