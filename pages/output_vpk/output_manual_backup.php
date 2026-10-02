<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php';

function om_user(): string
{
    return trim((string)($_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? $_SESSION['UserId'] ?? ''));
}

function om_json(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function om_dt($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d\TH:i:s');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i:s', $timestamp) : $value;
}

function om_display_dt($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d-m-Y H:i:s');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d-m-Y H:i:s', $timestamp) : $value;
}

function om_lookup_conn3(string $scan)
{
    global $conn3;
    $sql = "SELECT
                h.prdnmbr,
                h.prodid,
                h.prdstduomid,
                t.cuscolor,
                t.labeljual,
                t.stdcutfg
            FROM pdproductionhd h
            LEFT JOIN smprodtechdata t ON t.prodid = h.prodid
            WHERE RTRIM(LTRIM(CAST(h.prdnmbr AS varchar(100)))) = :scan
            LIMIT 1";

    try {
        $stmt = $conn3->prepare($sql);
        $stmt->execute(['scan' => $scan]);
        return [$stmt->fetch(PDO::FETCH_ASSOC) ?: null, null];
    } catch (PDOException $e) {
        return [null, $e->getMessage()];
    }
}

function om_same_user_row(string $scan, string $user)
{
    global $conn;
    $sql = "SELECT TOP 1 * FROM manual_output WHERE prdnmbr = ? AND created_by = ? ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql, [$scan, $user]);
    if ($stmt === false) {
        return null;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null;
    sqlsrv_free_stmt($stmt);
    return $row;
}

function om_other_user_row(string $scan, string $user)
{
    global $conn;
    $sql = "SELECT TOP 1 * FROM manual_output WHERE prdnmbr = ? AND created_by <> ? ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql, [$scan, $user]);
    if ($stmt === false) {
        return null;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null;
    sqlsrv_free_stmt($stmt);
    return $row;
}

function om_insert_row(array $source, string $user, string $scan): int
{
    global $conn;
    $sql = "INSERT INTO manual_output
            (prdnmbr, prodid, iso, partai, cuscolor, meter, gol, grey, tengah, start_time, finish_time, stdcutfg, prdstduomid, op_mesin, labeljual, ket, created_at, created_by, status_draft)
            OUTPUT INSERTED.id
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, 1)";
    $params = [
        $scan,
        $source['prodid'] ?? null,
        substr($scan, 0, 8),
        substr($scan, 8, 8),
        $source['cuscolor'] ?? null,
        null,
        null,
        null,
        null,
        date('Y-m-d H:i:s'),
        null,
        $source['stdcutfg'] ?? null,
        $source['prdstduomid'] ?? null,
        $user,
        $source['labeljual'] ?? null,
        null,
        $user,
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return 0;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return (int)($row['id'] ?? 0);
}

function om_update_row(int $id, array $input): bool
{
    global $conn;
    $sql = "UPDATE manual_output
            SET meter = ?, gol = ?, grey = ?, tengah = ?, ket = ?, finish_time = ?, upd_at = GETDATE()
            WHERE id = ?";
    return sqlsrv_query($conn, $sql, [
        $input['meter'] !== '' ? $input['meter'] : null,
        $input['gol'] !== '' ? $input['gol'] : null,
        $input['grey'] !== '' ? $input['grey'] : null,
        $input['tengah'] !== '' ? $input['tengah'] : null,
        $input['ket'],
        date('Y-m-d H:i:s'),
        $id,
    ]) !== false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === 'lookup') {
    $scan = trim((string)($_GET['scan'] ?? $_POST['scan'] ?? ''));
    if (!in_array(strlen($scan), [15, 16], true)) {
        om_json(['success' => false, 'message' => 'Scan harus 15 atau 16 character.']);
    }

    $user = om_user();
    $sameRow = om_same_user_row($scan, $user);
    if ($sameRow) {
        om_json([
            'success' => true,
            'mode' => !empty($sameRow['finish_time']) ? 'readonly' : 'edit',
            'title' => !empty($sameRow['finish_time']) ? 'prdnmbr telah terinput' : 'Data siap dilanjutkan',
            'message' => !empty($sameRow['finish_time']) ? 'Data sudah di input.' : 'Data ada, lanjut input.',
            'data' => [
                'id' => (int)$sameRow['id'],
                'prdnmbr' => $sameRow['prdnmbr'] ?? $scan,
                'iso' => $sameRow['iso'] ?? substr($scan, 0, 8),
                'partai' => $sameRow['partai'] ?? substr($scan, 8, 8),
                'cuscolor' => $sameRow['cuscolor'] ?? '',
                'meter' => $sameRow['meter'] ?? '',
                'gol' => $sameRow['gol'] ?? '',
                'grey' => $sameRow['grey'] ?? '',
                'tengah' => $sameRow['tengah'] ?? '',
                'start_time' => om_dt($sameRow['start_time'] ?? date('Y-m-d H:i:s')),
                'finish_time' => om_dt($sameRow['finish_time'] ?? ''),
                'stdcutfg' => $sameRow['stdcutfg'] ?? '',
                'op_mesin' => $sameRow['op_mesin'] ?? $user,
                'labeljual' => $sameRow['labeljual'] ?? '',
                'ket' => $sameRow['ket'] ?? '',
                'created_by' => $sameRow['created_by'] ?? $user,
            ],
        ]);
    }

    $otherRow = om_other_user_row($scan, $user);
    $lookup = om_lookup_conn3($scan);
    $source = $lookup[0];
    if (!$source) {
        om_json(['success' => false, 'message' => 'Data tidak ditemukan di conn3.']);
    }

    if ($otherRow && empty($otherRow['finish_time'])) {
        om_json([
            'success' => true,
            'mode' => 'readonly_other',
            'title' => 'prdnmbr telah terinput',
            'message' => 'Data masih diproses oleh user lain.',
            'data' => [
                'id' => (int)$otherRow['id'],
                'prdnmbr' => $source['prdnmbr'] ?? $scan,
                'iso' => substr($scan, 0, 8),
                'partai' => substr($scan, 8, 8),
                'cuscolor' => $otherRow['cuscolor'] ?? ($source['cuscolor'] ?? ''),
                'meter' => $otherRow['meter'] ?? '',
                'gol' => $otherRow['gol'] ?? '',
                'grey' => $otherRow['grey'] ?? '',
                'tengah' => $otherRow['tengah'] ?? '',
                'start_time' => om_dt($otherRow['start_time'] ?? date('Y-m-d H:i:s')),
                'finish_time' => om_dt($otherRow['finish_time'] ?? ''),
                'stdcutfg' => $otherRow['stdcutfg'] ?? ($source['stdcutfg'] ?? ''),
                'op_mesin' => $otherRow['op_mesin'] ?? $user,
                'labeljual' => $otherRow['labeljual'] ?? ($source['labeljual'] ?? ''),
                'ket' => $otherRow['ket'] ?? '',
                'created_by' => $otherRow['created_by'] ?? '',
            ],
        ]);
    }

    if ($otherRow && !empty($otherRow['finish_time'])) {
        $insertId = om_insert_row($source, $user, $scan);
        if (!$insertId) {
            om_json(['success' => false, 'message' => 'Gagal simpan draft ulang.']);
        }

        om_json([
            'success' => true,
            'mode' => 'edit',
            'title' => 'Input ulang',
            'message' => 'PRDNMBR sudah finish, lanjut input untuk user ini.',
            'data' => [
                'id' => $insertId,
                'prdnmbr' => $source['prdnmbr'] ?? $scan,
                'iso' => substr($scan, 0, 8),
                'partai' => substr($scan, 8, 8),
                'cuscolor' => $otherRow['cuscolor'] ?? ($source['cuscolor'] ?? ''),
                'meter' => '',
                'gol' => '',
                'grey' => '',
                'tengah' => '',
                'start_time' => date('Y-m-d\TH:i:s'),
                'finish_time' => '',
                'stdcutfg' => $otherRow['stdcutfg'] ?? ($source['stdcutfg'] ?? ''),
                'op_mesin' => $user,
                'labeljual' => $otherRow['labeljual'] ?? ($source['labeljual'] ?? ''),
                'ket' => '',
                'created_by' => $user,
            ],
        ]);
    }

    $insertId = om_insert_row($source, $user, $scan);
    if (!$insertId) {
        om_json(['success' => false, 'message' => 'Gagal simpan draft.']);
    }

    om_json([
        'success' => true,
        'mode' => 'edit',
        'title' => 'Data baru',
        'message' => 'Data belum pernah di input.',
        'data' => [
            'id' => $insertId,
            'prdnmbr' => $source['prdnmbr'] ?? $scan,
            'iso' => substr($scan, 0, 8),
            'partai' => substr($scan, 8, 8),
            'cuscolor' => $source['cuscolor'] ?? '',
            'meter' => '',
            'gol' => '',
            'grey' => '',
            'tengah' => '',
            'start_time' => date('Y-m-d\TH:i:s'),
            'finish_time' => '',
            'stdcutfg' => $source['stdcutfg'] ?? '',
            'op_mesin' => $user,
            'labeljual' => $source['labeljual'] ?? '',
            'ket' => '',
            'created_by' => $user,
        ],
    ]);
}

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        om_json(['success' => false, 'message' => 'ID tidak valid.']);
    }
    $saved = om_update_row($id, [
        'meter' => trim((string)($_POST['meter'] ?? '')),
        'gol' => trim((string)($_POST['gol'] ?? '')),
        'grey' => trim((string)($_POST['grey'] ?? '')),
        'tengah' => trim((string)($_POST['tengah'] ?? '')),
        'ket' => trim((string)($_POST['ket'] ?? '')),
    ]);
    om_json(['success' => $saved, 'message' => $saved ? 'Data tersimpan.' : 'Gagal update data.']);
}

$dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: date('Y-m-01');
$dateTo = trim((string)($_GET['date_to'] ?? '')) ?: date('Y-m-d');
$q = trim((string)($_GET['q'] ?? ''));

$rows = [];
if (!empty($conn)) {
    $sql = "SELECT id, prdnmbr, iso, partai, cuscolor, meter, gol, grey, tengah, start_time, finish_time, stdcutfg, op_mesin, labeljual, ket, created_at, created_by
            FROM manual_output
            WHERE CAST(created_at AS date) BETWEEN ? AND ?
              AND (? = '' OR prdnmbr LIKE ? OR iso LIKE ? OR partai LIKE ? OR cuscolor LIKE ? OR labeljual LIKE ? OR op_mesin LIKE ? OR ket LIKE ?)
            ORDER BY id DESC";
    $like = '%' . $q . '%';
    $stmt = sqlsrv_query($conn, $sql, [$dateFrom, $dateTo, $q, $like, $like, $like, $like, $like, $like, $like]);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$allowedThemes = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'navy', 'olive', 'lime', 'fuchsia', 'maroon', 'blue', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'green', 'teal', 'cyan', 'white', 'gray', 'gray-dark'];
$userTheme = strtolower((string)($_SESSION['Theme'] ?? 'primary'));
if (!in_array($userTheme, $allowedThemes, true)) $userTheme = 'primary';
$themeTextClass = in_array($userTheme, ['warning', 'light', 'lime', 'yellow', 'white'], true) ? 'text-dark' : 'text-white';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Output Manual</title>
    <link rel="stylesheet" href="/gg_app/plugins/bootstrap-5.0.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
    <script src="/gg_app/pages/output_vpk/sweetalert2@11.js"></script>
    <style>
        .modal-lg{max-width:1100px}.form-control[readonly]{background:#f8f9fa}
        .output-table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before{background-color:#007bff;border:0;box-shadow:none;line-height:1;top:50%;transform:translateY(-50%)}
        .output-table.dataTable>tbody>tr.child ul.dtr-details{display:block;width:100%;padding:0}
        .output-table.dataTable>tbody>tr.child ul.dtr-details>li{border-bottom:1px solid #efefef;padding:8px 0;display:flex;justify-content:space-between;gap:.75rem}
        .output-table.dataTable>tbody>tr.child .dtr-title{font-weight:600;color:#555;min-width:120px}
        .output-table.dataTable>tbody>tr.child .dtr-data{text-align:right;flex:1;word-break:break-word}
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php'; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php'; ?>
    <div class="content-wrapper p-3"><section class="content"><div class="container-fluid">
        <div class="card card-outline card-<?= htmlspecialchars($userTheme) ?>">
            <div class="card-header bg-<?= htmlspecialchars($userTheme) ?> <?= $themeTextClass ?>"><strong>Scan 15-16 Character</strong></div>
            <div class="card-body"><div class="form-group">
                <label>Scan 15-16 Character</label>
                <input type="text" id="scan_input" class="form-control" maxlength="16" autocomplete="off" placeholder="Scan 15 atau 16 karakter">
            </div></div>
        </div>
        <div class="card card-outline card-<?= htmlspecialchars($userTheme) ?> mb-3">
            <div class="card-header bg-<?= htmlspecialchars($userTheme) ?> <?= $themeTextClass ?> d-flex justify-content-between align-items-center flex-wrap">
                <strong>View Data</strong><div class="d-flex align-items-center flex-wrap ml-auto">
                    <div class="mr-2 mb-2 mb-md-0"><input type="date" id="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                    <div class="mr-2 mb-2 mb-md-0"><input type="date" id="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>"></div>
                    <div class="mr-2 mb-2 mb-md-0" style="min-width:220px"><input type="text" id="search_text" class="form-control form-control-sm" value="<?= htmlspecialchars($q) ?>" placeholder="Search"></div>
                    <button class="btn btn-light btn-sm mr-2 mb-2 mb-md-0" id="btnSearch"><i class="fas fa-search"></i> Search</button>
                    <a class="btn btn-success btn-sm mb-2 mb-md-0" target="_blank" href="export_manual_output.php?q=<?= urlencode($q) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><i class="fas fa-file-excel"></i> Export Excel</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="outputTable" class="table table-hover table-sm nowrap output-table" style="width:100%">
                        <thead class="thead-light"><tr><th>No</th><th>PRDNMBR</th><th>ISO</th><th>Partai</th><th>Warna</th><th>Meter</th><th>Gol</th><th>Grey</th><th>Tengah</th><th>Start</th><th>Finish</th><th>STD Potong</th><th>OP Mesin</th><th>L Jual</th><th>Ket</th><th>Created By</th></tr></thead>
                        <tbody><?php if ($rows): $no=1; foreach ($rows as $row): ?><tr>
                            <td><?= $no++ ?></td><td><?= htmlspecialchars($row['prdnmbr'] ?? '') ?></td><td><?= htmlspecialchars($row['iso'] ?? '') ?></td><td><?= htmlspecialchars($row['partai'] ?? '') ?></td><td><?= htmlspecialchars($row['cuscolor'] ?? '') ?></td><td><?= htmlspecialchars((string)($row['meter'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['gol'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['grey'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['tengah'] ?? '')) ?></td><td><?= htmlspecialchars(om_display_dt($row['start_time'] ?? '')) ?></td><td><?= htmlspecialchars(om_display_dt($row['finish_time'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['stdcutfg'] ?? '')) ?></td><td><?= htmlspecialchars($row['op_mesin'] ?? '') ?></td><td><?= htmlspecialchars($row['labeljual'] ?? '') ?></td><td><?= htmlspecialchars($row['ket'] ?? '') ?></td><td><?= htmlspecialchars($row['created_by'] ?? '') ?></td>
                        </tr><?php endforeach; endif; ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div></section></div>
</div>
<div class="modal fade" id="outputModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($userTheme) ?> <?= $themeTextClass ?>"><h5 class="modal-title" id="modalTitle">Output Manual</h5><button type="button" class="close <?= $themeTextClass ?>" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body"><div id="modalNotice" class="alert alert-info d-none"></div><form id="outputForm"><input type="hidden" name="id" id="row_id"><ul class="nav nav-tabs mb-3" id="outputManualTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="output-info-tab" data-toggle="tab" href="#output-info" role="tab" aria-controls="output-info" aria-selected="true">Info</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="output-input-tab" data-toggle="tab" href="#output-input" role="tab" aria-controls="output-input" aria-selected="false">Input</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="output-info" role="tabpanel" aria-labelledby="output-info-tab">
                            <div class="form-row">
                                <div class="form-group col-md-4"><label>ISO</label><input type="text" id="iso" class="form-control" readonly></div>
                                <div class="form-group col-md-4"><label>Partai</label><input type="text" id="partai" class="form-control" readonly></div>
                                <div class="form-group col-md-4"><label>Warna</label><input type="text" id="cuscolor" class="form-control" readonly></div>
                                <div class="form-group col-md-4"><label>Start</label><input type="text" id="start_time" class="form-control" readonly></div>
                                <div class="form-group col-md-4"><label>Finish</label><input type="text" id="finish_time" class="form-control" readonly></div>
                                <div class="form-group col-md-4"><label>STD Potong</label><input type="text" id="stdcutfg" class="form-control" readonly></div>
                                <div class="form-group col-md-4"><label>OP Mesin</label><input type="text" id="op_mesin" class="form-control" readonly></div>
                                <div class="form-group col-md-4"><label>L Jual</label><input type="text" id="labeljual" class="form-control" readonly></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="output-input" role="tabpanel" aria-labelledby="output-input-tab">
                            <div class="form-row">
                                <div class="form-group col-md-3"><label>Meter</label><input type="number" step="0.01" id="meter" name="meter" class="form-control"></div>
                                <div class="form-group col-md-3"><label>Gol</label><input type="number" step="0.01" id="gol" name="gol" class="form-control"></div>
                                <div class="form-group col-md-3"><label>Grey</label><input type="number" step="0.01" id="grey" name="grey" class="form-control"></div>
                                <div class="form-group col-md-3"><label>Tengah</label><input type="number" step="0.01" id="tengah" name="tengah" class="form-control"></div>
                                <div class="form-group col-md-4"><label>Ket</label><input type="text" id="ket" name="ket" class="form-control"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-<?= htmlspecialchars($userTheme) ?>" id="btnSave">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
(function () {
    const scanInput = document.getElementById('scan_input');
    const searchText = document.getElementById('search_text');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    const btnSearch = document.getElementById('btnSearch');
    const btnSave = document.getElementById('btnSave');
    const modal = $('#outputModal');
    const notice = document.getElementById('modalNotice');

    $('#outputTable').DataTable({
        order: [[0, 'asc']],
        responsive: {details: {type: 'column', target: 0}},
        columnDefs: [
            {className: 'dtr-control', orderable: false, targets: 0},
            {responsivePriority: 1, targets: 1},
            {responsivePriority: 2, targets: 4},
            {responsivePriority: 3, targets: 9},
            {responsivePriority: 4, targets: 10}
        ],
        language: {
            lengthMenu: 'Tampilkan _MENU_ data per halaman',
            zeroRecords: 'Tidak ada data ditemukan',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data tersedia',
            infoFiltered: '(disaring dari _MAX_ total data)',
            search: 'Cari:',
            paginate: {first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya'}
        }
    });

    function toast(msg, icon = 'info') {
        if (window.Swal) {
            Swal.fire({icon, title: msg, timer: 2200, showConfirmButton: false});
            return;
        }
        alert(msg);
    }

    function setReadonly(flag) {
        ['meter','gol','grey','tengah','ket'].forEach(id => {
            document.getElementById(id).readOnly = flag;
        });
        btnSave.style.display = flag ? 'none' : 'inline-block';
    }

    function fillModal(data, title, message, mode) {
        document.getElementById('row_id').value = data.id || '';
        document.getElementById('modalTitle').textContent = title || 'Output Manual';
        notice.textContent = message || '';
        notice.classList.toggle('d-none', !message);
        ['iso','partai','cuscolor','meter','gol','grey','tengah','stdcutfg','op_mesin','labeljual','ket'].forEach(id => {
            document.getElementById(id).value = data[id] || '';
        });
        document.getElementById('start_time').value = data.start_time ? data.start_time.replace('T',' ') : '';
        document.getElementById('finish_time').value = data.finish_time ? data.finish_time.replace('T',' ') : '';
        setReadonly(mode === 'readonly' || mode === 'readonly_other');
        modal.modal('show');
    }

    async function lookup(scan) {
        try {
            const res = await fetch(`output_manual.php?action=lookup&scan=${encodeURIComponent(scan)}`, {credentials: 'same-origin'});
            const json = await res.json();
            if (!json.success) {
                toast(json.message || 'Gagal lookup', 'error');
                return;
            }
            fillModal(json.data || {}, json.title || 'Output Manual', json.message || '', json.mode || 'edit');
        } catch (error) {
            toast('Gagal memproses hasil scan.', 'error');
        }
    }

    scanInput.addEventListener('input', function () {
        const scan = this.value.trim();
        if (scan.length === 16) lookup(scan);
    });

    scanInput.addEventListener('paste', function () {
        setTimeout(() => {
            const scan = this.value.trim();
            if (scan.length === 15 || scan.length === 16) lookup(scan);
        }, 0);
    });

    scanInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const scan = this.value.trim();
            if (scan.length === 15 || scan.length === 16) lookup(scan);
            else toast('Scan harus 15 atau 16 character.', 'warning');
        }
    });

    btnSave.addEventListener('click', async function () {
        const form = new FormData();
        form.append('action', 'save');
        form.append('id', document.getElementById('row_id').value);
        ['meter','gol','grey','tengah','ket'].forEach(id => form.append(id, document.getElementById(id).value));
        const res = await fetch('output_manual.php', {method: 'POST', body: form, credentials: 'same-origin'});
        const json = await res.json();
        if (json.success) {
            toast(json.message || 'Data tersimpan', 'success');
            location.reload();
        } else toast(json.message || 'Gagal update data', 'error');
    });

    btnSearch.addEventListener('click', function () {
        const params = new URLSearchParams({q: searchText.value.trim(), date_from: dateFrom.value, date_to: dateTo.value});
        window.location = `output_manual.php?${params.toString()}`;
    });

    [searchText, dateFrom, dateTo].forEach(el => el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); btnSearch.click(); }
    }));
})();
</script>
</body>
</html>

