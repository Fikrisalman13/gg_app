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
$vendorSearch = trim((string)($_GET['vendor_search'] ?? ''));
$savedSearch = trim((string)($_GET['saved_search'] ?? ''));

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
    header('Location: isopo_option.php?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_option'])) {
    $optionId = trim((string)($_POST['option_id'] ?? ''));
    if ($optionId !== '' && ctype_digit($optionId)) {
        $del = sqlsrv_query($conn, 'DELETE FROM dbo.po_option WHERE username = ? AND option_id = ?', [$userName, (int)$optionId]);
    } else {
        $vendor = trim((string)($_POST['vendor_name'] ?? ''));
        $prodcode = trim((string)($_POST['prodcode'] ?? ''));
        $del = sqlsrv_query($conn, "DELETE FROM dbo.po_option WHERE username = ? AND vendor_name = ? AND ISNULL(prodcode, '') = ?", [$userName, $vendor, $prodcode]);
    }
    if ($del === false) die('Hapus option gagal: ' . print_r(sqlsrv_errors(), true));
    sqlsrv_free_stmt($del);
    header('Location: isopo_option.php?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate));
    exit;
}

$vendorStmt = $conn3->query("SELECT DISTINCT vendorname FROM smvendor WHERE vendorname IS NOT NULL AND vendorname <> '' ORDER BY vendorname");
$vendorList = $vendorStmt ? $vendorStmt->fetchAll(PDO::FETCH_COLUMN) : [];
if ($vendorSearch !== '') {
    $vendorTerms = array_values(array_filter(array_map('trim', preg_split('/\s*;\s*/', $vendorSearch))));
    $vendorList = array_values(array_filter($vendorList, function ($vendor) use ($vendorTerms) {
        foreach ($vendorTerms as $term) {
            if ($term !== '' && stripos((string)$vendor, $term) !== false) return true;
        }
        return false;
    }));
}

$savedStmt = sqlsrv_query($conn, 'SELECT option_id, vendor_name, prodcode, prodname FROM dbo.po_option WHERE username = ? ORDER BY vendor_name, prodcode, option_id', [$userName]);
$savedOptionList = [];
if ($savedStmt !== false) {
    while ($row = sqlsrv_fetch_array($savedStmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['vendor_name'])) $savedOptionList[] = $row;
    }
    sqlsrv_free_stmt($savedStmt);
}
if ($savedSearch !== '') {
    $savedTerms = array_values(array_filter(array_map('trim', preg_split('/\s*;\s*/', $savedSearch))));
    $savedOptionList = array_values(array_filter($savedOptionList, function ($item) use ($savedTerms) {
        $haystack = (string)($item['vendor_name'] ?? '') . ' ' . (string)($item['prodcode'] ?? '') . ' ' . (string)($item['prodname'] ?? '');
        foreach ($savedTerms as $term) {
            if ($term !== '' && stripos($haystack, $term) !== false) return true;
        }
        return false;
    }));
}
$savedRowsCount = count($savedOptionList);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Option Vendor</title>
</head>
<body>
<div class="content-wrapper p-3">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Option Vendor</h4>
      <a href="isopo.php?start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>" class="btn btn-secondary btn-sm">Kembali</a>
    </div>

    <div class="mb-2 small text-muted">Total saved: <?= $savedRowsCount ?></div>

    <form method="get" class="mb-3" id="searchForm">
      <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
      <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
      <div class="row g-2">
        <div class="col-md-6 mb-2">
          <label class="form-label small mb-1">Cari vendor</label>
          <input type="text" name="vendor_search" id="vendorSearch" class="form-control form-control-sm live-search" value="<?= htmlspecialchars($vendorSearch) ?>" placeholder="Cari vendor...">
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label small mb-1">Cari saved</label>
          <input type="text" name="saved_search" id="savedSearch" class="form-control form-control-sm live-search" value="<?= htmlspecialchars($savedSearch) ?>" placeholder="Cari saved...">
        </div>
      </div>
      <a href="isopo_option.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>

    <div class="row g-3 align-items-start">
      <div class="col-lg-5">
        <div class="border p-2 rounded shadow-sm bg-white">
          <strong>Vendor</strong>
          <div id="vendorListBox" style="max-height:320px;overflow:auto;">
            <?php foreach ($vendorList as $vendor) : ?>
              <div class="form-check vendor-row">
                <input class="form-check-input vendor-check" type="checkbox" value="<?= htmlspecialchars($vendor) ?>" id="v_<?= md5($vendor) ?>">
                <label class="form-check-label" for="v_<?= md5($vendor) ?>"><?= htmlspecialchars($vendor) ?></label>
              </div>
            <?php endforeach; ?>
            <?php if (empty($vendorList)) : ?><div class="text-muted small">Tidak ada data</div><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="border p-2 rounded shadow-sm bg-white sticky-top" style="top:10px;">
          <div class="d-flex justify-content-between align-items-center mb-2"><strong>Saved</strong><span class="text-muted small"><?= $savedRowsCount ?> data</span></div>
          <div id="savedListBox" style="max-height:320px;overflow:auto;">
            <?php foreach ($savedOptionList as $item) : ?>
              <?php $optionId = (string)($item['option_id'] ?? ''); $vendorName = (string)($item['vendor_name'] ?? ''); $prodcode = (string)($item['prodcode'] ?? ''); $prodname = (string)($item['prodname'] ?? ''); ?>
              <div class="border-bottom py-2">
                <div><strong>Vendor:</strong> <?= htmlspecialchars($vendorName) ?></div>
                <div><strong>Prodcode:</strong> <?= htmlspecialchars($prodcode) ?></div>
                <div><strong>Prodname:</strong> <?= htmlspecialchars($prodname) ?></div>
                <div class="mt-1 d-flex gap-1 flex-wrap">
                  <a class="btn btn-sm btn-outline-primary" href="isopo_prodcode.php?vendor_name=<?= urlencode($vendorName) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>">Prodcode</a>
                  <form method="post" class="m-0">
                    <input type="hidden" name="option_id" value="<?= htmlspecialchars($optionId, ENT_QUOTES) ?>">
                    <input type="hidden" name="vendor_name" value="<?= htmlspecialchars($vendorName, ENT_QUOTES) ?>">
                    <input type="hidden" name="prodcode" value="<?= htmlspecialchars($prodcode, ENT_QUOTES) ?>">
                    <button type="submit" name="delete_option" value="1" class="btn btn-sm btn-outline-danger">Hapus</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($savedOptionList)) : ?><div class="text-muted small">Tidak ada data saved</div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <form method="post" class="mb-3" id="saveForm">
      <div id="selectedVendorInputs"></div>
      <div class="mb-2 d-flex gap-2 flex-wrap align-items-center">
        <button type="submit" name="save_option" value="1" class="btn btn-success btn-sm">Simpan Option Vendor</button>
        <span class="badge bg-primary" id="checkedCount">0 dipilih</span>
        <a href="?export_excel=1" class="btn btn-success btn-sm">Export Excel</a>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  const form = document.getElementById('searchForm');
  const saveForm = document.getElementById('saveForm');
  const storageKey = 'isopo_option_selected_vendors';
  const checkedCount = document.getElementById('checkedCount');
  const vendorListBox = document.getElementById('vendorListBox');
  const selectedVendorInputs = document.getElementById('selectedVendorInputs');
  const vendorCheckboxes = Array.from(document.querySelectorAll('.vendor-check'));
  let timer = null;

  const readSelection = () => {
    try { return JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch (error) { return []; }
  };
  const writeSelection = () => {
    const selected = vendorCheckboxes.filter((box) => box.checked).map((box) => box.value);
    localStorage.setItem(storageKey, JSON.stringify(selected));
  };
  const restoreSelection = () => {
    const selected = new Set(readSelection());
    vendorCheckboxes.forEach((box) => { box.checked = selected.has(box.value); });
  };
  const updateHiddenInputs = () => {
    if (!selectedVendorInputs) return;
    const selected = new Set([...(readSelection() || []), ...vendorCheckboxes.filter((box) => box.checked).map((box) => box.value)]);
    selectedVendorInputs.innerHTML = '';
    selected.forEach((vendor) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'vendor_name[]';
      input.value = vendor;
      selectedVendorInputs.appendChild(input);
    });
  };
  const sortCheckedFirst = () => {
    if (!vendorListBox) return;
    const rows = Array.from(vendorListBox.querySelectorAll('.vendor-row'));
    rows.sort((left, right) => (right.querySelector('.vendor-check')?.checked ? 1 : 0) - (left.querySelector('.vendor-check')?.checked ? 1 : 0));
    rows.forEach((row) => vendorListBox.appendChild(row));
  };
  const updateCount = () => {
    if (!checkedCount) return;
    checkedCount.textContent = document.querySelectorAll('.vendor-check:checked').length + ' dipilih';
  };

  restoreSelection();
  sortCheckedFirst();
  updateHiddenInputs();
  updateCount();

  document.querySelectorAll('.live-search').forEach((input) => {
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => form && form.submit && form.submit(), 120);
    });
  });

  vendorCheckboxes.forEach((box) => box.addEventListener('change', () => {
    writeSelection();
    updateHiddenInputs();
    sortCheckedFirst();
    updateCount();
  }));

  if (saveForm) saveForm.addEventListener('submit', () => { writeSelection(); updateHiddenInputs(); });
})();
</script>
<?php include '../../includes/footer.php'; ?>
</body>
</html>




