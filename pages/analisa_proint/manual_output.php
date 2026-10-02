<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === 'lookup' || $action === 'save') {
    $koneksi3Path = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php';
    if (file_exists($koneksi3Path)) {
        require_once $koneksi3Path;
    }
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    }

    date_default_timezone_set('Asia/Jakarta');

    function mo_json_out($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    function mo_user_login()
    {
        return $_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? $_SESSION['UserId'] ?? 'User';
    }

    function mo_parse_scan($scan)
    {
        $scan = trim((string) $scan);
        if (strlen($scan) !== 16) {
            return [false, 'Scan harus 16 character.', null];
        }

        return [true, '', [
            'raw' => $scan,
            'iso' => substr($scan, 0, 8),
            'partai' => substr($scan, 8, 8),
        ]];
    }

    function mo_fetch_scan_row($conn3, $scan)
    {
        if (!($conn3 instanceof PDO)) {
            return [false, 'Koneksi conn3 bukan PDO.', null];
        }

        $scan = trim((string) $scan);
        $sql = "SELECT
                    h.prdnmbr,
                    h.prodid,
                    h.prdstduomid,
                    t.cuscolor,
                    t.labeljual,
                    t.stdcutfg
                FROM pdproductionhd h
                LEFT JOIN smprodtechdata t ON t.prodid = h.prodid
                WHERE RTRIM(LTRIM(CAST(h.prdnmbr AS varchar(100)))) = RTRIM(LTRIM(CAST(:scan AS varchar(100))))
                LIMIT 1";

        try {
            $stmt = $conn3->prepare($sql);
            $stmt->execute([':scan' => $scan]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return [true, '', $row ?: null];
        } catch (Throwable $e) {
            return [false, 'Query lookup gagal.', $e->getMessage()];
        }
    }
    function mo_get_existing_user_row($conn, $scan, $userName)
    {
        if (!isset($conn) || !$conn) {
            return null;
        }

        $sql = "SELECT TOP 1 id, prdnmbr, prodid, iso, partai, cuscolor, meter, gol, grey, tengah, start_time, finish_time, stdcutfg, uom, op_mesin, labeljual, ket, created_by
                FROM manual_output
                WHERE prdnmbr = ? AND created_by = ?
                ORDER BY id DESC";
        $stmt = sqlsrv_query($conn, $sql, [$scan, $userName]);
        if ($stmt === false) {
            return null;
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ?: null;
    }
    function mo_find_existing_combo_row($conn, $iso, $partai, $opMesin, $excludeId = 0)
    {
        if (!isset($conn) || !$conn) {
            return null;
        }

        $sql = "SELECT TOP 1 id, prdnmbr, iso, partai, op_mesin, finish_time, created_by
                FROM manual_output
                WHERE iso = ? AND partai = ? AND op_mesin = ?
                  AND (? = 0 OR id <> ?)
                ORDER BY id DESC";
        $stmt = sqlsrv_query($conn, $sql, [$iso, $partai, $opMesin, $excludeId, $excludeId]);
        if ($stmt === false) {
            return null;
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ?: null;
    }

    function mo_insert_draft($conn, $data)
    {
        if (!isset($conn) || !$conn) {
            return false;
        }

        $userName = mo_user_login();
        $sql = "INSERT INTO manual_output
                (prdnmbr, prodid, iso, partai, cuscolor, meter, gol, grey, tengah, start_time, finish_time, stdcutfg, uom, op_mesin, labeljual, ket, created_at, created_by)
                OUTPUT INSERTED.id
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?)";
        $params = [
            $data['scan'],
            $data['prodid'],
            $data['iso'],
            $data['partai'],
            $data['cuscolor'],
            null,
            null,
            null,
            null,
            $data['start_time'],
            null,
            $data['stdcutfg'],
            $data['uom'],
            $userName,
            $data['labeljual'],
            null,
            $userName,
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            return false;
        }
        $idRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $id = ($idRow && isset($idRow['id'])) ? (int) $idRow['id'] : false;
        sqlsrv_free_stmt($stmt);
        return $id;
    }

    [$ok, $msg, $scanData] = mo_parse_scan($action === 'lookup' ? ($_GET['scan'] ?? '') : ($_POST['scan'] ?? ''));
    if (!$ok) {
        mo_json_out(['success' => false, 'message' => $msg]);
    }

    try {
        [$scanOk, $scanMsg, $row] = mo_fetch_scan_row($conn3 ?? null, $scanData['raw']);
        if (!$scanOk) {
            mo_json_out(['success' => false, 'message' => $scanMsg, 'error' => print_r($row, true)]);
        }
    } catch (Throwable $e) {
        mo_json_out(['success' => false, 'message' => 'Query gagal.', 'error' => $e->getMessage()]);
    }

    if (!$row) {
        mo_json_out(['success' => false, 'message' => 'PRDNMBR tidak ditemukan.']);
    }

    $userName = mo_user_login();
    $draftId = (int) ($_POST['draft_id'] ?? 0);
    $existingDone = mo_get_existing_user_row($conn ?? null, $scanData['raw'], $userName);
    $existingCombo = mo_find_existing_combo_row($conn ?? null, $scanData['iso'], $scanData['partai'], $userName, $draftId);

    if ($action === 'lookup') {
        if ($existingDone) {
            $isFinished = !empty($existingDone['finish_time']);
            mo_json_out([
                'success' => true,
                'already_saved' => $isFinished,
                'read_only' => $isFinished,
                'message' => $isFinished ? 'Data sudah pernah di input.' : 'Data sudah ada, lanjut input.' ,
                'data' => [
                    'draft_id' => (int) ($existingDone['id'] ?? 0),
                    'scan' => $scanData['raw'],
                    'iso' => $existingDone['iso'] ?? $scanData['iso'],
                    'partai' => $existingDone['partai'] ?? $scanData['partai'],
                    'prodid' => $existingDone['prodid'] ?? ($row['prodid'] ?? ''),
                    'prdnmbr' => $existingDone['prdnmbr'] ?? ($row['prdnmbr'] ?? ''),
                    'cuscolor' => $existingDone['cuscolor'] ?? ($row['cuscolor'] ?? ''),
                    'labeljual' => $existingDone['labeljual'] ?? ($row['labeljual'] ?? ''),
                    'stdcutfg' => $existingDone['stdcutfg'] ?? ($row['stdcutfg'] ?? ''),
                    'uom' => $existingDone['uom'] ?? ($row['prdstduomid'] ?? ''),
                    'meter' => $existingDone['meter'] ?? '',
                    'gol' => $existingDone['gol'] ?? '',
                    'grey' => $existingDone['grey'] ?? '',
                    'tengah' => $existingDone['tengah'] ?? '',
                    'ket' => $existingDone['ket'] ?? '',
                    'op_mesin' => $existingDone['op_mesin'] ?? $userName,
                    'start_time' => $existingDone['start_time'] ?? '',
                    'finish_time' => $existingDone['finish_time'] ?? ''
                ]
            ]);
        }

        $existingCombo = mo_find_existing_combo_row($conn ?? null, $scanData['iso'], $scanData['partai'], $userName, $draftId);
        if ($existingCombo) {
            mo_json_out([
                'success' => true,
                'already_saved' => true,
                'read_only' => true,
                'message' => 'ISO + partai + OP Mesin sudah ada.',
                'data' => [
                    'draft_id' => (int) ($existingCombo['id'] ?? 0),
                    'scan' => $scanData['raw'],
                    'iso' => $existingCombo['iso'] ?? $scanData['iso'],
                    'partai' => $existingCombo['partai'] ?? $scanData['partai'],
                    'prodid' => $row['prodid'] ?? '',
                    'prdnmbr' => $existingCombo['prdnmbr'] ?? ($row['prdnmbr'] ?? ''),
                    'cuscolor' => $row['cuscolor'] ?? '',
                    'labeljual' => $row['labeljual'] ?? '',
                    'stdcutfg' => $row['stdcutfg'] ?? '',
                    'uom' => $row['prdstduomid'] ?? ''
                ]
            ]);
        }

        try {
            $draftId = mo_insert_draft($conn ?? null, [
                'scan' => $scanData['raw'],
                'prodid' => $row['prodid'] ?? null,
                'iso' => $scanData['iso'],
                'partai' => $scanData['partai'],
                'cuscolor' => $row['cuscolor'] ?? null,
                'start_time' => date('Y-m-d H:i:s'),
                'stdcutfg' => $row['stdcutfg'] ?? null,
                'uom' => $row['prdstduomid'] ?? null,
                'labeljual' => $row['labeljual'] ?? null,
            ]);
        } catch (Throwable $e) {
            mo_json_out(['success' => false, 'message' => 'Gagal buat draft.', 'error' => $e->getMessage()]);
        }

        if (!$draftId) {
            mo_json_out(['success' => false, 'message' => 'Gagal buat draft.', 'error' => print_r(sqlsrv_errors(), true)]);
        }

        mo_json_out([
            'success' => true,
            'data' => [
                'draft_id' => $draftId,
                'scan' => $scanData['raw'],
                'iso' => $scanData['iso'],
                'partai' => $scanData['partai'],
                'prodid' => $row['prodid'] ?? '',
                'prdnmbr' => $row['prdnmbr'] ?? '',
                'cuscolor' => $row['cuscolor'] ?? '',
                'labeljual' => $row['labeljual'] ?? '',
                'stdcutfg' => $row['stdcutfg'] ?? '',
                'uom' => $row['prdstduomid'] ?? ''
            ]
        ]);
    }

    if ($action === 'save') {
        if (!isset($conn) || !$conn) {
            mo_json_out(['success' => false, 'message' => 'Koneksi GG tidak tersedia.']);
        }

        $draftId = (int) ($_POST['draft_id'] ?? 0);
        if ($draftId <= 0) {
            mo_json_out(['success' => false, 'message' => 'Draft id kosong. Scan ulang dulu.']);
        }

        $meter = (float) ($_POST['meter'] ?? 0);
        $gol = (float) ($_POST['gol'] ?? 0);
        $grey = (float) ($_POST['grey'] ?? 0);
        $tengah = (float) ($_POST['tengah'] ?? 0);
        $ket = trim($_POST['ket'] ?? '');
        $finish = date('Y-m-d H:i:s');
        $userName = mo_user_login();

        $sqlUpd = "UPDATE manual_output
                   SET meter = ?, gol = ?, grey = ?, tengah = ?, finish_time = ?, ket = ?, op_mesin = ?
                   WHERE id = ?";
        $params = [$meter, $gol, $grey, $tengah, $finish, $ket, $userName, $draftId];
        $stmtUpd = sqlsrv_query($conn, $sqlUpd, $params);
        if ($stmtUpd === false) {
            mo_json_out(['success' => false, 'message' => 'Gagal update.', 'error' => print_r(sqlsrv_errors(), true)]);
        }
        sqlsrv_free_stmt($stmtUpd);

        mo_json_out(['success' => true, 'message' => 'Data tersimpan.']);
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$userName = $_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? $_SESSION['UserId'] ?? 'User';
$themeColor = $_SESSION['Theme'] ?? 'primary';
$search = trim($_GET['q'] ?? '');
$defaultDateFrom = date('Y-m-01');
$defaultDateTo = date('Y-m-d');
$dateFrom = trim($_GET['date_from'] ?? '') ?: $defaultDateFrom;
$dateTo = trim($_GET['date_to'] ?? '') ?: $defaultDateTo;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$rows = [];
$totalRows = 0;
$totalPages = 1;

function mo_format_date_only($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d-m-Y');
    }

    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d-m-Y', $timestamp) : $value;
}

if (isset($conn) && $conn) {
    $sqlList = "SELECT id, iso, partai, cuscolor, meter, gol, grey, tengah, start_time, finish_time, stdcutfg, op_mesin, labeljual, ket, created_at
                FROM manual_output
                WHERE (? = '' OR iso LIKE ? OR partai LIKE ? OR cuscolor LIKE ? OR labeljual LIKE ? OR op_mesin LIKE ? OR ket LIKE ?)
                  AND (? = '' OR CAST(created_at AS date) >= ?)
                  AND (? = '' OR CAST(created_at AS date) <= ?)
                ORDER BY id DESC";
    $like = '%' . $search . '%';
    $dateFromSql = $dateFrom !== '' ? $dateFrom : null;
    $dateToSql = $dateTo !== '' ? $dateTo : null;
    $stmtList = sqlsrv_query($conn, $sqlList, [$search, $like, $like, $like, $like, $like, $like, $dateFromSql, $dateFromSql, $dateToSql, $dateToSql]);
    if ($stmtList !== false) {
        while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
        sqlsrv_free_stmt($stmtList);
    }
}

$totalRows = count($rows);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = array_slice($rows, ($page - 1) * $perPage, $perPage);
?>
<style>\n  .scan-wrap{max-width:1240px;margin:0 auto}\n  .scan-card{border-radius:12px;overflow:hidden}\n  .scan-input{font-size:1.2rem;letter-spacing:2px;font-weight:700;text-align:center;height:52px}\n  .small-label{font-size:.78rem;text-transform:uppercase;color:#6c757d;font-weight:700;letter-spacing:.04em}\n  .readonly-box{background:#f8f9fa}\n  .table-wrap{overflow:auto}\n  .manual-output-dialog{max-width:980px}\n  .manual-output-fullscreen{max-width:100% !important;width:100% !important;height:100%;margin:0}\n  .manual-output-fullscreen .modal-content{height:100%;border-radius:0}\n  @media (max-width: 991.98px){\n    .scan-input{font-size:1rem;height:46px}\n    .card-header,.modal-header,.modal-footer{padding:.75rem 1rem}\n    .table-responsive{font-size:.9rem}\n  }\n  @media (max-width: 767.98px){\n    .scan-wrap{max-width:100%}\n  }\n</style>
<style>
  .scan-wrap{max-width:1240px;margin:0 auto}
  .scan-card{border-radius:12px;overflow:hidden}
  .scan-input{font-size:1.2rem;letter-spacing:2px;font-weight:700;text-align:center;height:52px}
  .small-label{font-size:.78rem;text-transform:uppercase;color:#6c757d;font-weight:700;letter-spacing:.04em}
  .readonly-box{background:#f8f9fa}
  .table-wrap{overflow:auto}
  .manual-output-dialog{max-width:980px}
  .manual-output-fullscreen{max-width:100% !important;width:100% !important;height:100%;margin:0}
  .manual-output-fullscreen .modal-content{height:100%;border-radius:0}
  .view-toolbar{gap:.5rem}
  @media (max-width: 991.98px){
    .scan-input{font-size:1rem;height:46px}
    .card-header,.modal-header,.modal-footer{padding:.75rem 1rem}
    .table-responsive{font-size:.9rem}
  }
  @media (max-width: 767.98px){
    .scan-wrap{max-width:100%}
    .view-toolbar{flex-direction:column;align-items:stretch}
    .view-toolbar form{width:100%}
    .view-toolbar .search-row{width:100%;justify-content:space-between}
  }
</style>

<div class="content-wrapper">
  <section class="content pt-3">
    <div class="container-fluid scan-wrap">
      <div class="card scan-card shadow-sm mb-3">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><strong>Manual Output</strong></div>
        <div class="card-body">
          <div id="scanLoading" class="alert alert-info d-none mb-3"><i class="fas fa-spinner fa-spin mr-1"></i> Mencari data...</div>
          <div class="form-group mb-2">
            <label class="small-label">Scan 16 Character</label>
            <input type="text" id="scanInput" class="form-control scan-input" maxlength="16" autocomplete="off" autofocus>
            <small class="text-muted">Begitu 16 character masuk, data dicari otomatis.</small>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex justify-content-between align-items-center flex-wrap">
          <strong>View Data</strong>
          <a href="export_manual_output.php?q=<?= urlencode($search) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-success btn-sm" target="_blank"><i class="fas fa-file-excel"></i> Export Excel</a>
        </div>
        <div class="card-body table-responsive table-wrap">
          <div class="d-flex flex-wrap align-items-center view-toolbar mb-3">
            <form method="get" id="manualFilterForm" class="form-inline search-row mr-auto">
              <input type="text" name="q" class="form-control form-control-sm mr-2" placeholder="Cari data..." value="<?= htmlspecialchars($search) ?>">
              <input type="date" name="date_from" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($dateFrom) ?>">
              <input type="date" name="date_to" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($dateTo) ?>">
              <button class="btn btn-light btn-sm ml-1" type="submit">Search</button>
            </form>
          </div>
        </div>
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Created At</th>
                <th>ISO</th>
                <th>Partai</th>
                <th>Warna</th>
                <th>Meter</th>
                <th>Gol</th>
                <th>Grey</th>
                <th>Tengah</th>
                <th>Start</th>
                <th>Finish</th>
                <th>STD Potong</th>
                <th>OP Mesin</th>
                <th>L Jual</th>
                <th>Ket</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="15" class="text-center text-muted">Data kosong</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $index => $row): ?>
                  <tr>
                    <td><?= (int) (($page - 1) * $perPage + $index + 1) ?></td>
                    <td><?= htmlspecialchars(mo_format_date_only($row["created_at"] ?? "")) ?></td>
                    <td><?= htmlspecialchars($row["iso"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["partai"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["cuscolor"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["meter"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["gol"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["grey"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["tengah"] ?? "") ?></td>
                    <td><?= htmlspecialchars(mo_format_date_only($row["start_time"] ?? "")) ?></td>
                    <td><?= htmlspecialchars(mo_format_date_only($row["finish_time"] ?? "")) ?></td>
                    <td><?= htmlspecialchars($row["stdcutfg"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["op_mesin"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["labeljual"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["ket"] ?? "") ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
          <div class="text-muted">Total: <?= (int) $totalRows ?> row</div>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="?q=<?= urlencode($search) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="manualOutputModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Manual Output</h5><button type="button" id="manualOutputFullscreenBtn" class="btn btn-sm btn-outline-light ml-auto mr-2" aria-label="Fullscreen"><i class="fas fa-expand"></i></button>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form id="manualOutputForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="draft_id" id="draftId">
          <input type="hidden" name="scan" id="formScan">
          <input type="hidden" name="start" id="formStart">
          <div class="row">
            <div class="col-md-3 form-group"><label>ISO</label><input type="text" id="iso" class="form-control readonly-box" readonly></div>
            <div class="col-md-3 form-group"><label>Partai</label><input type="text" id="partai" class="form-control readonly-box" readonly></div>
            <div class="col-md-3 form-group"><label>Warna</label><input type="text" id="cuscolor" class="form-control readonly-box" readonly></div>
            <div class="col-md-3 form-group"><label>L Jual</label><input type="text" id="labeljual" class="form-control readonly-box" readonly></div>
            <div class="col-md-4 form-group"><label>Meter</label><input type="number" step="0.01" name="meter" id="meter" class="form-control" required></div>
            <div class="col-md-2 form-group"><label>Gol</label><input type="number" step="0.01" name="gol" id="gol" class="form-control" required></div>
            <div class="col-md-2 form-group"><label>Grey</label><input type="number" step="0.01" name="grey" id="grey" class="form-control" required></div>
            <div class="col-md-2 form-group"><label>Tengah</label><input type="number" step="0.01" name="tengah" id="tengah" class="form-control" required></div>
            <div class="col-md-2 form-group"><label>STD Potong</label><input type="text" id="stdcutfg" class="form-control readonly-box" readonly></div>
            <div class="col-md-4 form-group"><label>OP Mesin</label><input type="text" id="opMesin" class="form-control readonly-box" readonly></div>
            <div class="col-md-4 form-group"><label>Start</label><input type="text" id="startDisplay" class="form-control readonly-box" readonly></div>
            <div class="col-md-4 form-group"><label>Finish</label><input type="text" id="finishDisplay" class="form-control readonly-box" readonly value="-"></div>
            <div class="col-12 form-group"><label>Ket</label><textarea name="ket" id="ket" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          <button type="submit" id="saveBtn" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
<script>
(function(){
  const input = document.getElementById("scanInput");
  const form = document.getElementById("manualOutputForm");
  const loadingEl = document.getElementById("scanLoading");
  const modalEl = document.getElementById("manualOutputModal");
  let modalInstance = null;
  let fullscreen = false;
  const startNow = new Date();
  function nowText(d){ const pad = n => String(n).padStart(2,"0"); return d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate())+" "+pad(d.getHours())+":"+pad(d.getMinutes())+":"+pad(d.getSeconds()); }
  const fsBtn = document.getElementById("manualOutputFullscreenBtn"); if (fsBtn) fsBtn.addEventListener("click", function(e){ e.preventDefault(); toggleFullscreen(); });
  function getModal(){ if (!modalInstance && window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) modalInstance = window.jQuery(modalEl); return modalInstance; }
  function showModal(){
    const modal = getModal();
    if (modal && typeof modal.modal === "function") { modal.modal("show"); return; }
    if (modalEl) {
      modalEl.style.display = "block";
      modalEl.classList.add("show");
      document.body.classList.add("modal-open");
    }
  }
  function showLoading(show){ if (loadingEl) loadingEl.classList.toggle("d-none", !show); }
  function toggleFullscreen(){ fullscreen = !fullscreen; const dialog = modalEl.querySelector(".modal-dialog"); if (dialog) dialog.classList.toggle("manual-output-fullscreen", fullscreen); if (fsBtn) fsBtn.innerHTML = fullscreen ? "<i class=\"fas fa-compress\"></i>" : "<i class=\"fas fa-expand\"></i>"; }
  function setValue(id, value){ const el = document.getElementById(id); if (el) el.value = value ?? ""; }
  function formatDateTimeValue(value){
    if (!value) return "";
    if (typeof value === "string") return value;
    if (value instanceof Date) return nowText(value);
    if (typeof value === "object") {
      if (typeof value.date === "string") return value.date.replace("T", " ").replace(/\.\d+/, "");
      try { return String(value); } catch (e) { return ""; }
    }
    return String(value);
  }
  function fill(data){
    setValue("draftId", data.draft_id || "");
    setValue("formScan", data.scan || "");
    setValue("formStart", formatDateTimeValue(data.start_time) || nowText(startNow));
    setValue("startDisplay", formatDateTimeValue(data.start_time) || nowText(startNow));
    setValue("finishDisplay", formatDateTimeValue(data.finish_time) || "-");
    setValue("iso", data.iso || "");
    setValue("partai", data.partai || "");
    setValue("cuscolor", data.cuscolor || "");
    setValue("labeljual", data.labeljual || "");
    setValue("stdcutfg", data.stdcutfg || "");
    setValue("opMesin", data.op_mesin || <?= json_encode($userName) ?>);
    setValue("meter", data.meter || "");
    setValue("gol", data.gol || "");
    setValue("grey", data.grey || "");
    setValue("tengah", data.tengah || "");
    setValue("ket", data.ket || "");
  }
  function setReadOnlyMode(isReadOnly){
    const saveBtn = document.getElementById("saveBtn");
    if (saveBtn) saveBtn.classList.toggle("d-none", !!isReadOnly);
    const inputs = ["meter","gol","grey","tengah","ket"];
    inputs.forEach(function(id){
      const el = document.getElementById(id);
      if (el) el.readOnly = !!isReadOnly;
    });
    const modalTitle = modalEl ? modalEl.querySelector(".modal-title") : null;
    if (modalTitle) modalTitle.textContent = "Input Manual Output";
  }

  async function submitScan(scan){
    try {
      showLoading(true);
      const res = await lookup(scan);
      if (!res.success) {
        showLoading(false);
        let errorMessage = res.message || "Data tidak ditemukan";
        if (res.error) errorMessage += "\n\nDetail error:\n" + res.error;
        console.error("Lookup error:", res);
        if (window.Swal && typeof Swal.fire === "function") { Swal.fire({ icon: "error", title: "Gagal", text: errorMessage }); } else { alert(errorMessage); }
        return;
      }
      fill(res.data);
      setReadOnlyMode(!!res.read_only);
      showLoading(false);
      if (modalEl) { modalEl.style.display = "block"; modalEl.classList.add("show"); document.body.classList.add("modal-open"); }
      showModal();
      if (res.already_saved && res.read_only) {
        if (window.Swal && typeof Swal.fire === "function") {
          Swal.fire({
            icon: "info",
            title: "Data sudah pernah di input",
            text: "Silakan lihat data, Save dinonaktifkan."
          });
        } else {
          alert("Data sudah pernah di input");
        }
      }
      setTimeout(() => { const meter = document.getElementById("meter"); if (meter && !meter.readOnly) meter.focus(); }, 250);
    } catch (err) {
      showLoading(false);
      if (window.Swal && typeof Swal.fire === "function") { Swal.fire({ icon: "error", title: "Gagal ambil data", text: (err && err.message ? err.message : "response tidak valid") }); } else { alert("Gagal ambil data: " + (err && err.message ? err.message : "response tidak valid")); }
    }
  }
  input.addEventListener("input", function(){ const v = (input.value || "").trim(); if (v.length === 16) { clearTimeout(input._timer); input._timer = setTimeout(function(){ if ((input.value || "").trim().length === 16) submitScan((input.value || "").trim()); }, 80); } });
  input.addEventListener("keydown", function(e){ if (e.key === "Enter") { e.preventDefault(); const v = (input.value || "").trim(); if (v.length === 16) submitScan(v); } });
  form.addEventListener("submit", async function(e){ e.preventDefault(); const fd = new FormData(form); const res = await fetch(window.location.href, { method: "POST", body: fd, headers: { "Accept": "application/json" } }); const json = await res.json(); if (!json.success) { if (window.Swal && typeof Swal.fire === "function") { Swal.fire({ icon: "error", title: "Gagal simpan", text: json.message || "Gagal simpan" }); } else { alert(json.message || "Gagal simpan"); } return; } setValue("finishDisplay", nowText(new Date())); if (window.Swal && typeof Swal.fire === "function") { Swal.fire({ icon: "success", title: "Tersimpan", text: json.message || "Tersimpan", timer: 1500, showConfirmButton: false }); } else { alert(json.message || "Tersimpan"); } const m = getModal(); if (m) m.modal("hide"); input.value = ""; input.focus(); });
  window.addEventListener("load", function(){ setTimeout(() => input && input.focus(), 200); });
})();
</script>


<?php include($_SERVER["DOCUMENT_ROOT"] . "/gg_app/includes/footer.php"); ?>
