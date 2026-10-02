<?php
session_start();
ob_start();
include '../../../koneksi.php';
include '../../../koneksi3.php';
include '../../../includes/header.php';
include '../../../includes/sidebar.php';
require_once __DIR__ . '/databale_helpers.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

if (!$conn || !$conn3) {
    die('Koneksi ke database gagal: ' . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$userName = (string) $_SESSION['UserName'];
$lockedWarehouseName = 'GUDANG JADI B GRADE';

function upl_val($row, string $key): string
{
    return htmlspecialchars((string)($row[$key] ?? ''));
}

function upl_date($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y');
    }
    if (empty($value)) {
        return '-';
    }
    $time = strtotime((string) $value);
    return $time ? date('d/m/Y', $time) : '-';
}

function upl_datetime($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y H:i');
    }
    if (empty($value)) {
        return '-';
    }
    $time = strtotime((string) $value);
    return $time ? date('d/m/Y H:i', $time) : '-';
}

function upl_db_date($value): ?string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    if (empty($value)) {
        return null;
    }
    $time = strtotime((string) $value);
    return $time ? date('Y-m-d', $time) : null;
}

function upl_photo_url(?string $fileName): string
{
    if (!$fileName) {
        return '';
    }
    return '/gg_app/uploads/databale/' . rawurlencode($fileName);
}

function upl_load_latest_header_id($conn): int
{
    $stmt = sqlsrv_query($conn, 'SELECT TOP 1 hdid FROM dbo.upload_pengebalan_hd ORDER BY created_at DESC, hdid DESC');
    if ($stmt === false) {
        return 0;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return (int)($row['hdid'] ?? 0);
}

$warehouseList = dbale_load_warehouses($conn3);
$selectedWrhsid = '';
$selectedWrhsname = $lockedWarehouseName;
foreach ($warehouseList as $warehouse) {
    if (dbale_normalize_key($warehouse['wrhsname'] ?? '') === dbale_normalize_key($lockedWarehouseName)) {
        $selectedWrhsid = (string)($warehouse['wrhsid'] ?? '');
        $selectedWrhsname = (string)($warehouse['wrhsname'] ?? $lockedWarehouseName);
        break;
    }
}

$selectedHdId = (int)($_GET['hdid'] ?? 0);
if ($selectedHdId <= 0) {
    $selectedHdId = upl_load_latest_header_id($conn);
}

$candidateRows = $selectedWrhsid !== '' ? dbale_load_uploadpengebalan_candidates($conn3, $selectedWrhsid) : [];
$candidateIndex = [];
foreach ($candidateRows as $candidateRow) {
    $candidateIndex[(string)($candidateRow['balehdid'] ?? '') . '|' . (string)($candidateRow['baleprodid'] ?? '')] = $candidateRow;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_upload'])) {
            if ($selectedWrhsid === '') {
                throw new RuntimeException('Gudang GUDANG JADI B GRADE tidak ditemukan.');
            }

            $selectedItems = array_values(array_filter(array_map('trim', (array)($_POST['selected_items'] ?? []))));
            if (empty($selectedItems)) {
                throw new RuntimeException('Pilih minimal 1 balenmbr.');
            }

            sqlsrv_begin_transaction($conn);
            $headerId = dbale_create_uploadpengebalan_header($conn, $selectedWrhsid, $selectedWrhsname, $userName);
            $seq = 1;
            $totalPcs = 0;
            $totalM = 0.0;
            $totalYard = 0.0;

            foreach ($selectedItems as $itemKey) {
                if (!isset($candidateIndex[$itemKey])) {
                    throw new RuntimeException('Data kandidat tidak ditemukan untuk salah satu pilihan.');
                }

                $pickedRow = $candidateIndex[$itemKey];
                $sourceDetails = dbale_load_detail_rows($conn3, (string)($pickedRow['balehdid'] ?? ''), (string)($pickedRow['baleprodid'] ?? ''));
                if (empty($sourceDetails)) {
                    throw new RuntimeException('Detail source kosong untuk balenmbr ' . (string)($pickedRow['balenmbr'] ?? '') . '.');
                }

                $totalPcs++;
                foreach ($sourceDetails as $sourceDetail) {
                    $detailRow = [
                        'source_balehdid' => (string)($pickedRow['balehdid'] ?? ''),
                        'source_baleprodid' => (string)($pickedRow['baleprodid'] ?? ''),
                        'batchno' => (string)($sourceDetail['batchno'] ?? ''),
                        'qtym' => (float)($sourceDetail['qtym'] ?? 0),
                        'qtyyard' => (float)($sourceDetail['qtyyard'] ?? 0),
                        'qtykg' => (float)($sourceDetail['qtykg'] ?? 0),
                        'balenmbr' => (string)($pickedRow['balenmbr'] ?? ''),
                        'baledesc' => (string)($pickedRow['baledesc'] ?? ''),
                        'baledate' => upl_db_date($pickedRow['baledate'] ?? null),
                        'prodcode' => (string)($pickedRow['prodcode'] ?? ''),
                        'prodname' => (string)($pickedRow['prodname'] ?? ''),
                    ];
                    dbale_insert_uploadpengebalan_detail($conn, $headerId, $seq++, $detailRow, $userName);
                    $totalM += (float)$detailRow['qtym'];
                    $totalYard += (float)$detailRow['qtyyard'];
                }
            }

            dbale_update_uploadpengebalan_summary($conn, $headerId, $totalPcs, $totalM, $totalYard, $userName);
            sqlsrv_commit($conn);

            $_SESSION['success'] = 'Create pengebalan berhasil.';
            header('Location: uploadpengebalan.php?hdid=' . urlencode((string)$headerId));
            exit;
        }

        if (isset($_POST['save_photo'])) {
            $uploadHdId = (int)($_POST['upload_hdid'] ?? 0);
            if ($uploadHdId <= 0) {
                throw new RuntimeException('Header upload tidak valid.');
            }

            $savedBarang = null;
            $savedPacking = null;
            $uploadDir = dbale_ensure_upload_dir();

            if (!empty($_FILES['photo_barang']['name'])) {
                $savedBarang = dbale_store_upload_file($_FILES['photo_barang'], $uploadDir . DIRECTORY_SEPARATOR . 'uploadpengebalan_' . $uploadHdId . '_barang.jpg');
            }
            if (!empty($_FILES['photo_packinglist']['name'])) {
                $savedPacking = dbale_store_upload_file($_FILES['photo_packinglist'], $uploadDir . DIRECTORY_SEPARATOR . 'uploadpengebalan_' . $uploadHdId . '_packinglist.jpg');
            }
            if ($savedBarang === null && $savedPacking === null) {
                throw new RuntimeException('Pilih minimal 1 photo untuk diupload.');
            }

            dbale_upsert_uploadpengebalan_photos($conn, $uploadHdId, $savedBarang, $savedPacking, $userName);
            $_SESSION['success'] = 'Photo pengebalan tersimpan.';
            header('Location: uploadpengebalan.php?hdid=' . urlencode((string)$uploadHdId) . '#detail-bale');
            exit;
        }
    }
} catch (Throwable $e) {
    if (function_exists('sqlsrv_rollback')) {
        @sqlsrv_rollback($conn);
    }
    $_SESSION['error'] = $e->getMessage();
    header('Location: uploadpengebalan.php' . ($selectedHdId > 0 ? '?hdid=' . urlencode((string)$selectedHdId) : ''));
    exit;
}

$headerRow = $selectedHdId > 0 ? dbale_load_uploadpengebalan_header($conn, $selectedHdId) : null;
if (!$headerRow && $selectedHdId <= 0) {
    $selectedHdId = upl_load_latest_header_id($conn);
    $headerRow = $selectedHdId > 0 ? dbale_load_uploadpengebalan_header($conn, $selectedHdId) : null;
}
$detailRows = $headerRow ? dbale_load_uploadpengebalan_details($conn, (int)$headerRow['hdid']) : [];

$computedTotalPcs = 0;
$computedTotalM = 0.0;
$computedTotalYard = 0.0;
$seenBales = [];
foreach ($detailRows as $detailRow) {
    $computedTotalM += (float)($detailRow['qtym'] ?? 0);
    $computedTotalYard += (float)($detailRow['qtyyard'] ?? 0);
    $seenBales[(string)($detailRow['source_balehdid'] ?? '') . '|' . (string)($detailRow['source_baleprodid'] ?? '')] = true;
}
$computedTotalPcs = count($seenBales);

$photoBarangUrl = $headerRow ? upl_photo_url((string)($headerRow['photo_barang'] ?? '')) : '';
$photoPackingUrl = $headerRow ? upl_photo_url((string)($headerRow['photo_packinglist'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Pengebalan</title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <style>
    .bale-photo-card img {
      width: 100%;
      max-height: 360px;
      object-fit: contain;
      background: #f8f9fa;
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

    .table-subtotal {
      background: #f1f3f5;
      font-weight: 600;
    }

    .new-modal-table td,
    .new-modal-table th {
      vertical-align: middle;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper p-3">
    <div class="content-header p-0 mb-3">
      <div class="container-fluid px-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h4 class="mb-0">Upload Pengebalan</h4>
            <div class="text-muted small">Data baca dari `dbo.upload_pengebalan_hd` dan `dbo.upload_pengebalan_dt`.</div>
          </div>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newModal"><i class="fas fa-plus"></i> New</button>
            <a href="#detail-bale" class="btn btn-outline-primary"><i class="fas fa-eye"></i> Detail Bale</a>
            <a href="uploadpengebalan.php<?= $selectedHdId > 0 ? '?hdid=' . urlencode((string)$selectedHdId) : '' ?>" class="btn btn-outline-secondary"><i class="fas fa-sync"></i> Refresh</a>
          </div>
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
          <span>Gudang</span>
          <small class="text-muted">Gudang dikunci ke <b><?= htmlspecialchars($lockedWarehouseName) ?></b></small>
        </div>
        <div class="card-body">
          <div class="row align-items-end">
            <div class="col-md-5 mb-2">
              <label class="form-label">Gudang</label>
              <input type="hidden" name="wrhsid" value="<?= htmlspecialchars($selectedWrhsid) ?>">
              <select class="form-control bg-light" disabled>
                <option selected><?= htmlspecialchars($selectedWrhsname) ?></option>
              </select>
            </div>
            <div class="col-md-7 mb-2 d-flex justify-content-md-end">
              <div class="alert alert-info py-2 mb-0 w-100">
                Klik <b>New</b> untuk pilih beberapa <b>balenmbr</b>. Hasil simpan langsung masuk ke detail di bawah.
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-outline card-primary mb-3" id="detail-bale">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span>Detail Bale</span>
          <small class="text-muted">Sumber detail dari tabel upload existing</small>
        </div>
        <div class="card-body">
          <?php if (!$headerRow) : ?>
            <div class="alert alert-info mb-0">Belum ada data tersimpan. Klik <b>New</b> lalu <b>Save</b>.</div>
          <?php else : ?>
            <div class="row mb-3">
              <div class="col-lg-8 mb-3">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6 mb-2"><b>Header</b> #<?= htmlspecialchars((string)$headerRow['hdid']) ?></div>
                      <div class="col-md-6 mb-2"><b>Gudang</b> <?= htmlspecialchars((string)($headerRow['wrhsname'] ?? $selectedWrhsname)) ?></div>
                      <div class="col-md-6 mb-2"><b>Status</b> <?= htmlspecialchars((string)($headerRow['status'] ?? 'DRAFT')) ?></div>
                      <div class="col-md-6 mb-2"><b>Create</b> <?= upl_datetime($headerRow['created_at'] ?? null) ?></div>
                      <div class="col-md-6 mb-2"><b>Update</b> <?= upl_datetime($headerRow['updated_at'] ?? null) ?></div>
                      <div class="col-md-6 mb-2"><b>Total PCS</b> <?= (int)$computedTotalPcs ?></div>
                      <div class="col-md-6 mb-2"><b>Total M</b> <?= number_format($computedTotalM, 2, ',', '.') ?></div>
                      <div class="col-md-6 mb-2"><b>Total Yard</b> <?= number_format($computedTotalYard, 2, ',', '.') ?></div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 mb-3">
                <div class="card h-100">
                  <div class="card-body d-flex align-items-center justify-content-center text-center bg-light">
                    <div>
                      <div class="h5 mb-1">Upload aktif</div>
                      <div class="text-muted small">Hidup di header `upload_pengebalan_hd`.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive mb-3">
              <table class="table table-bordered table-sm mb-0">
                <thead class="thead-light text-center">
                  <tr>
                    <th>Batchno</th>
                    <th>Qty M</th>
                    <th>Qty Yard</th>
                    <th>Qty Kg</th>
                    <th>Balenmbr</th>
                    <th>Baledesc</th>
                    <th>Baledate</th>
                    <th>Prodcode</th>
                    <th>Prodname</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($detailRows)) : ?>
                    <tr><td colspan="9" class="text-center text-muted">Detail kosong</td></tr>
                  <?php else : ?>
                    <?php
                    $currentProdcode = null;
                    $subtotalM = 0.0;
                    $subtotalYard = 0.0;
                    $subtotalKg = 0.0;
                    $subtotalBales = [];

                    $renderSubtotal = function () use (&$currentProdcode, &$subtotalM, &$subtotalYard, &$subtotalKg, &$subtotalBales): void {
                        if ($currentProdcode === null) {
                            return;
                        }
                        echo '<tr class="table-subtotal">';
                        echo '<td>Subtotal ' . htmlspecialchars((string)$currentProdcode) . '</td>';
                        echo '<td class="text-right">' . number_format($subtotalM, 2, ',', '.') . '</td>';
                        echo '<td class="text-right">' . number_format($subtotalYard, 2, ',', '.') . '</td>';
                        echo '<td class="text-right">' . number_format($subtotalKg, 2, ',', '.') . '</td>';
                        echo '<td class="text-center">PCS ' . count($subtotalBales) . '</td>';
                        echo '<td colspan="4"></td>';
                        echo '</tr>';
                    };

                    foreach ($detailRows as $row) {
                        $prodcode = (string)($row['prodcode'] ?? '');
                        $groupKey = (string)($row['source_balehdid'] ?? '') . '|' . (string)($row['source_baleprodid'] ?? '');

                        if ($currentProdcode !== null && $currentProdcode !== $prodcode) {
                            $renderSubtotal();
                            $subtotalM = 0.0;
                            $subtotalYard = 0.0;
                            $subtotalKg = 0.0;
                            $subtotalBales = [];
                        }
                        if ($currentProdcode !== $prodcode) {
                            $currentProdcode = $prodcode;
                        }

                        $subtotalM += (float)($row['qtym'] ?? 0);
                        $subtotalYard += (float)($row['qtyyard'] ?? 0);
                        $subtotalKg += (float)($row['qtykg'] ?? 0);
                        $subtotalBales[$groupKey] = true;

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars((string)($row['batchno'] ?? '')) . '</td>';
                        echo '<td class="text-right">' . number_format((float)($row['qtym'] ?? 0), 2, ',', '.') . '</td>';
                        echo '<td class="text-right">' . number_format((float)($row['qtyyard'] ?? 0), 2, ',', '.') . '</td>';
                        echo '<td class="text-right">' . number_format((float)($row['qtykg'] ?? 0), 2, ',', '.') . '</td>';
                        echo '<td>' . htmlspecialchars((string)($row['balenmbr'] ?? '')) . '</td>';
                        echo '<td>' . htmlspecialchars((string)($row['baledesc'] ?? '')) . '</td>';
                        echo '<td>' . htmlspecialchars(upl_date($row['baledate'] ?? null)) . '</td>';
                        echo '<td>' . htmlspecialchars((string)($row['prodcode'] ?? '')) . '</td>';
                        echo '<td>' . htmlspecialchars((string)($row['prodname'] ?? '')) . '</td>';
                        echo '</tr>';
                    }

                    $renderSubtotal();
                    ?>
                  <?php endif; ?>
                </tbody>
                <tfoot>
                  <tr class="font-weight-bold bg-light text-center">
                    <td colspan="3">Total PCS: <?= (int)$computedTotalPcs ?></td>
                    <td colspan="3">Total M: <?= number_format($computedTotalM, 2, ',', '.') ?></td>
                    <td colspan="3">Total Yard: <?= number_format($computedTotalYard, 2, ',', '.') ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="row">
              <div class="col-lg-6 mb-3">
                <div class="card bale-photo-card h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Foto Barang</span>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-upload" data-hdid="<?= htmlspecialchars((string)$headerRow['hdid'], ENT_QUOTES) ?>">Upload</button>
                  </div>
                  <div class="card-body">
                    <?php if ($photoBarangUrl !== '' && is_file(__DIR__ . '/../../../uploads/databale/' . (string)($headerRow['photo_barang'] ?? ''))) : ?>
                      <img src="<?= htmlspecialchars($photoBarangUrl) ?>" alt="Foto Barang" class="img-fluid rounded border">
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
                    <button type="button" class="btn btn-sm btn-outline-primary btn-upload" data-hdid="<?= htmlspecialchars((string)$headerRow['hdid'], ENT_QUOTES) ?>">Upload</button>
                  </div>
                  <div class="card-body">
                    <?php if ($photoPackingUrl !== '' && is_file(__DIR__ . '/../../../uploads/databale/' . (string)($headerRow['photo_packinglist'] ?? ''))) : ?>
                      <img src="<?= htmlspecialchars($photoPackingUrl) ?>" alt="Foto Packinglist" class="img-fluid rounded border">
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

<div class="modal fade" id="newModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">New Pengebalan</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="create_upload" value="1">
          <div class="alert alert-info py-2 mb-3">Pilih beberapa <b>balenmbr</b> dari gudang <?= htmlspecialchars($selectedWrhsname) ?>, lalu klik <b>Save</b>.</div>
          <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
            <table class="table table-bordered table-hover table-sm mb-0 new-modal-table">
              <thead class="thead-light text-center">
                <tr>
                  <th style="width:40px;"><input type="checkbox" id="checkAllCandidates"></th>
                  <th>Balenmbr</th>
                  <th>Baledesc</th>
                  <th>Baledate</th>
                  <th>Prodcode</th>
                  <th>Prodname</th>
                  <th>Total Batch</th>
                  <th>Qty M</th>
                  <th>Qty Yard</th>
                  <th>Qty Kg</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($candidateRows)) : ?>
                  <tr><td colspan="10" class="text-center text-muted">Data kosong</td></tr>
                <?php else : ?>
                  <?php foreach ($candidateRows as $row) : ?>
                    <?php $rowKey = (string)($row['balehdid'] ?? '') . '|' . (string)($row['baleprodid'] ?? ''); ?>
                    <tr>
                      <td class="text-center align-middle">
                        <input type="checkbox" class="candidate-row" name="selected_items[]" value="<?= htmlspecialchars($rowKey, ENT_QUOTES) ?>">
                      </td>
                      <td><?= upl_val($row, 'balenmbr') ?></td>
                      <td><?= upl_val($row, 'baledesc') ?></td>
                      <td><?= upl_date($row['baledate'] ?? null) ?></td>
                      <td><?= upl_val($row, 'prodcode') ?></td>
                      <td><?= upl_val($row, 'prodname') ?></td>
                      <td class="text-center"><?= (int)($row['total_batch'] ?? 0) ?></td>
                      <td class="text-right"><?= number_format((float)($row['totqtym'] ?? 0), 2, ',', '.') ?></td>
                      <td class="text-right"><?= number_format((float)($row['totqtyyard'] ?? 0), 2, ',', '.') ?></td>
                      <td class="text-right"><?= number_format((float)($row['totqtykg'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Upload Photo Pengebalan</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="save_photo" value="1">
          <input type="hidden" name="upload_hdid" id="uploadHdid" value="">
          <div class="form-group">
            <label>Foto Barang</label>
            <input type="file" name="photo_barang" class="form-control" accept="image/*">
          </div>
          <div class="form-group mb-0">
            <label>Foto Packinglist</label>
            <input type="file" name="photo_packinglist" class="form-control" accept="image/*">
          </div>
          <small class="text-muted d-block mt-2">File otomatis dikompres di bawah 200 KB.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var uploadHdidInput = document.getElementById('uploadHdid');
  var checkAllCandidates = document.getElementById('checkAllCandidates');

  document.querySelectorAll('.btn-upload').forEach(function (button) {
    button.addEventListener('click', function () {
      uploadHdidInput.value = button.getAttribute('data-hdid') || '';
      $('#uploadModal').modal('show');
    });
  });

  if (checkAllCandidates) {
    checkAllCandidates.addEventListener('change', function () {
      var checked = checkAllCandidates.checked;
      document.querySelectorAll('.candidate-row').forEach(function (checkbox) {
        checkbox.checked = checked;
      });
    });
  }
})();
</script>
<?php include '../../../includes/footer.php'; ?>
</body>
</html>


