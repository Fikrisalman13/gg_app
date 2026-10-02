<?php
// ir_serverside.php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$sd     = trim($_POST['start_date'] ?? '');
$ed     = trim($_POST['end_date'] ?? '');
$q      = trim($_POST['search']['value'] ?? '');

$where = " WHERE 1=1 ";
$params = [];

// filter tanggal (kolom ir_date di header)
if ($sd !== '') {
  $where .= " AND ir_date >= ?";
  $params[] = dmY_to_Ymd($sd);
}
if ($ed !== '') {
  $where .= " AND ir_date <= ?";
  $params[] = dmY_to_Ymd($ed);
}

if ($q !== '') {
  $where .= " AND (
    COALESCE(request_no,'') LIKE ?
    OR COALESCE(descr,'') LIKE ?
  )";
  $like = '%'.$q.'%';
  $params[] = $like;
  $params[] = $like;
}

// total semua IR
$total = (int)$pdo->query("SELECT COUNT(*) FROM siin_ir")->fetchColumn();

// total setelah filter
$stCnt = $pdo->prepare("SELECT COUNT(*) FROM siin_ir $where");
$stCnt->execute($params);
$filtered = (int)$stCnt->fetchColumn();

// paging
$drv    = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$offset = max(0, $start);
if ($length < 1) {
  $length = $filtered > 0 ? $filtered : 1000;
}

// ambil data (tanpa ppnmbr/ponmbr/grnnmbr karena sekarang di item)
if ($drv === 'sqlsrv') {
  $sql = "SELECT id, ir_date, request_no, descr, status
          FROM siin_ir $where
          ORDER BY ir_date DESC, id DESC
          OFFSET $offset ROWS FETCH NEXT $length ROWS ONLY";
  $st = $pdo->prepare($sql);
  $st->execute($params);
} else {
  $sql = "SELECT id, ir_date, request_no, descr, status
          FROM siin_ir $where
          ORDER BY ir_date DESC, id DESC
          LIMIT ? OFFSET ?";
  $p2 = array_merge($params, [$length, $offset]);
  $st = $pdo->prepare($sql);
  $st->execute($p2);
}

$data = [];
while ($r = $st->fetch()) {
  $badgeClass = [
    'open'        => 'info',
    'Approved'    => 'success',
    'outstanding' => 'warning',
    'closed'      => 'dark',
  ][$r['status']] ?? 'secondary';

  $data[] = [
    'ir_date'      => Ymd_to_dmY($r['ir_date']),
    'request_no'   => htmlspecialchars($r['request_no']),
    'descr'        => htmlspecialchars($r['descr'] ?? ''),
    'status_badge' => '<span class="badge badge-' . $badgeClass . '">' . strtoupper($r['status']) . '</span>',
    'aksi'         => '<div class="btn-group btn-group-sm">
      <button class="btn btn-outline-primary btn-view" data-id="' . $r['id'] . '"><i class="fas fa-eye"></i></button>
      <button class="btn btn-outline-warning btn-edit" data-id="' . $r['id'] . '"><i class="fas fa-edit"></i></button>
      <button class="btn btn-outline-danger btn-delete" data-id="' . $r['id'] . '"><i class="fas fa-trash"></i></button>
    </div>'
  ];
}

echo json_encode([
  'draw'            => $draw,
  'recordsTotal'    => $total,
  'recordsFiltered' => $filtered,
  'data'            => $data
]);
