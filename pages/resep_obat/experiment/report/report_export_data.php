<?php
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../../../../koneksi3.php';
require_once __DIR__ . '/../experiment_visibility_helper.php';

function report_export_fmt_date($v)
{
  if ($v instanceof DateTimeInterface)
    return $v->format('d/m/Y');
  if (!$v)
    return '-';
  $ts = strtotime((string) $v);
  return $ts ? date('d/m/Y', $ts) : '-';
}
function report_export_fmt_num($v)
{
  return number_format((float) ($v ?? 0), 2, '.', ',');
}
function report_export_can_view($conn)
{
  $stmt = sqlsrv_query($conn, "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=212", [$_SESSION['GroupId'] ?? 0]);
  return $stmt && ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) && (int) ($r['CanView'] ?? 0) === 1;
}
function report_export_rows($conn, $conn3, $filters = [])
{
  $conditions = [];
  $params = [];
  resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
  $search = trim($filters['search'] ?? '');
  if ($search !== '') {
    $conditions[] = "(e.no_cp LIKE ? OR e.kode_warna LIKE ? OR e.color_name LIKE ? OR e.cus_color LIKE ? OR e.mesin LIKE ? OR e.qc_keputusan LIKE ? OR e.qc_tindakan LIKE ? OR e.qc_catatan LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s, $s, $s, $s);
  }
  if (trim($filters['filter_date_from'] ?? '') !== '') {
    $conditions[] = 'e.created_at >= ?';
    $params[] = $filters['filter_date_from'];
  }
  if (trim($filters['filter_date_to'] ?? '') !== '') {
    $conditions[] = 'e.created_at <= ?';
    $params[] = $filters['filter_date_to'] . ' 23:59:59';
  }
  if (trim($filters['filter_created_by'] ?? '') !== '') {
    $createdByFilter = array_values(array_filter(array_map('trim', explode('|', (string) $filters['filter_created_by'])), fn($v) => $v !== ''));
    if ($createdByFilter) {
      $conditions[] = 'e.created_by IN (' . implode(',', array_fill(0, count($createdByFilter), '?')) . ')';
      array_push($params, ...$createdByFilter);
    }
  }
  $filterStatus = trim((string) ($filters['filter_status'] ?? ''));
  if ($filterStatus !== '') {
    $conditions[] = 'e.experiment_status = ?';
    $params[] = $filterStatus;
  }
  $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
  $sql = "SELECT e.id, e.created_at, e.created_by, e.cus_color, e.kode_warna, e.color_name, e.mesin, e.no_cp, e.experiment_seq, e.experiment_status, e.plan_qty, e.qc_keputusan, e.qc_tindakan, e.qc_catatan, pplan.tgl_planning
          FROM dbo.resep_obat_experiment e
          OUTER APPLY (
            SELECT TOP 1 p.period_date AS tgl_planning
            FROM dbo.cpp_paddry p
            WHERE UPPER(LTRIM(RTRIM(CAST(p.cp_no AS NVARCHAR(200))))) = UPPER(LTRIM(RTRIM(CAST(e.no_cp AS NVARCHAR(200)))))
            ORDER BY CASE WHEN p.period_date IS NULL THEN 1 ELSE 0 END ASC, p.period_date DESC, p.id DESC
          ) pplan
          $where ORDER BY e.cus_color ASC, e.created_at DESC, e.id DESC";
  $stmt = sqlsrv_query($conn, $sql, $params);
  $rows = [];
  $cpList = [];
  while ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
    $cp = trim((string) ($r['no_cp'] ?? ''));
    if ($cp !== '')
      $cpList[] = $cp;
  }
  if ($stmt)
    sqlsrv_free_stmt($stmt);
  $erp = [];
  if ($cpList) {
    try {
      $placeholders = [];
      $pgParams = [];
      foreach (array_values(array_unique($cpList)) as $i => $cp) {
        $k = ':cp_' . $i;
        $placeholders[] = $k;
        $pgParams[$k] = strtoupper($cp);
      }
      $ph = implode(',', $placeholders);
      $pgSql = "
        WITH latest_cp AS (
          SELECT TRIM(CAST(prdnmbr AS TEXT)) AS cp_no, productionhdid,
                 ROW_NUMBER() OVER (PARTITION BY UPPER(TRIM(CAST(prdnmbr AS TEXT))) ORDER BY prddate DESC NULLS LAST, productionhdid DESC) AS rn
          FROM pdproductionhd WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN ($ph)
        ), latest AS (SELECT cp_no, productionhdid FROM latest_cp WHERE rn = 1),
        celup AS (
          SELECT DISTINCT ON (l.cp_no) l.cp_no, r.starttime AS tgl_celup_padd
          FROM latest l JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid
          WHERE r.rtgmsid IN (838,842,555,556,559,809) AND r.starttime IS NOT NULL
          ORDER BY l.cp_no, r.rtgseq ASC NULLS LAST, r.starttime ASC NULLS LAST, r.productionrtgid ASC
        ), aktual AS (
          SELECT DISTINCT ON (l.cp_no) l.cp_no, r.prdqty AS aktual_qty
          FROM latest l JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid
          WHERE r.rtgmsid = 409 AND r.prdqty IS NOT NULL
          ORDER BY l.cp_no, r.rtgseq ASC NULLS LAST, r.productionrtgid ASC
        ), last_filled AS (
          SELECT DISTINCT ON (l.cp_no) l.cp_no, r.rtgseq, r.rtgmsid
          FROM latest l JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid
          WHERE COALESCE(r.prdqty,0) > 0 OR r.prduomid IS NOT NULL OR COALESCE(r.prdstdqty,0) > 0
          ORDER BY l.cp_no, r.rtgseq DESC NULLS LAST, r.productionrtgid DESC
        ), posisi AS (
          SELECT l.cp_no,
                 CASE WHEN lf.rtgmsid = 692 THEN 'VERPACKING'
                      WHEN COALESCE(next_m.rtgname,'') <> '' THEN next_m.rtgname
                      WHEN COALESCE(first_m.rtgname,'') <> '' THEN first_m.rtgname
                      ELSE 'PEMARTAIAN' END AS posisi_hari_ini
          FROM latest l
          LEFT JOIN last_filled lf ON lf.cp_no = l.cp_no
          LEFT JOIN pdproductionrtg next_r ON next_r.productionhdid = l.productionhdid AND next_r.rtgseq = lf.rtgseq + 1
          LEFT JOIN pdrtgms next_m ON next_m.rtgmsid = next_r.rtgmsid
          LEFT JOIN pdproductionrtg first_r ON first_r.productionhdid = l.productionhdid AND first_r.rtgmsid = 409
          LEFT JOIN pdrtgms first_m ON first_m.rtgmsid = first_r.rtgmsid
        ), acc AS (
          SELECT DISTINCT ON (l.cp_no) l.cp_no, r.starttime AS acc_tgl, COALESCE(r.failmsid,0) AS failmsid,
                 COALESCE(r.fgresult,'') AS fgresult, COALESCE(f.failcode,'') AS failcode, COALESCE(f.faildesc,'') AS faildesc
          FROM latest l JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid AND r.rtgmsid = 589
          LEFT JOIN pdfailms f ON f.failmsid = r.failmsid
          ORDER BY l.cp_no, CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 0 ELSE 1 END ASC, r.starttime DESC NULLS LAST, r.productionrtgid DESC
        )
        SELECT l.cp_no, c.tgl_celup_padd, ak.aktual_qty, p.posisi_hari_ini, a.acc_tgl, a.failmsid, a.fgresult, a.failcode, a.faildesc
        FROM latest l LEFT JOIN celup c ON c.cp_no = l.cp_no LEFT JOIN aktual ak ON ak.cp_no = l.cp_no LEFT JOIN posisi p ON p.cp_no = l.cp_no LEFT JOIN acc a ON a.cp_no = l.cp_no
      ";
      $pgStmt = $conn3->prepare($pgSql);
      $pgStmt->execute($pgParams);
      while ($r = $pgStmt->fetch(PDO::FETCH_ASSOC)) {
        $failmsid = (int) ($r['failmsid'] ?? 0);
        $fg = strtoupper(trim((string) ($r['fgresult'] ?? '')));
        $hasFail = trim((string) ($r['failcode'] ?? '')) !== '' || trim((string) ($r['faildesc'] ?? '')) !== '';
        $status = '-';
        if ($failmsid !== 0 && $hasFail)
          $status = 'Fail';
        elseif ($fg === 'P' && $failmsid === 0 && !$hasFail)
          $status = 'Pass';
        $erp[strtoupper(trim($r['cp_no']))] = ['tgl_celup_padd' => report_export_fmt_date($r['tgl_celup_padd'] ?? null), 'aktual_qty' => ($r['aktual_qty'] ?? null) === null ? '-' : report_export_fmt_num($r['aktual_qty']), 'posisi_hari_ini' => trim((string) ($r['posisi_hari_ini'] ?? '')) ?: '-', 'acc_status' => $status, 'acc_tgl' => report_export_fmt_date($r['acc_tgl'] ?? null)];
      }
    } catch (Throwable $e) {
      error_log('report export ERP error: ' . $e->getMessage());
    }
  }
  $out = [];
  foreach ($rows as $r) {
    $cp = strtoupper(trim((string) ($r['no_cp'] ?? '')));
    $info = $erp[$cp] ?? [];
    $status = trim((string) ($r['experiment_status'] ?? '')) ?: 'Draft';
    $out[] = [
      'tgl_match' => report_export_fmt_date($r['created_at'] ?? null),
      'label' => trim((string) ($r['cus_color'] ?? '')) ?: '-',
      'warna' => $r['color_name'] ?? '-',
      'status' => $status,
      'tgl_planning' => report_export_fmt_date($r['tgl_planning'] ?? null),
      'tgl_celup_padd' => $info['tgl_celup_padd'] ?? '-',
      'mesin_paddry' => preg_replace('/,\s*/', "\n", trim((string) ($r['mesin'] ?? ''))) ?: '-',
      'no_cp' => $cp !== '' ? trim((string) $r['no_cp']) : 'Tunggu CP',
      'experiment_seq' => (int) ($r['experiment_seq'] ?? 1),
      'created_by' => trim((string) ($r['created_by'] ?? '')) ?: '-',
      'kode_lab' => $r['kode_warna'] ?? '-',
      'kode_lab_warna' => trim(($r['kode_warna'] ?? '-') . "\n" . ($r['color_name'] ?? '-')),
      'qty' => report_export_fmt_num($r['plan_qty'] ?? 0),
      'aktual_qty' => $info['aktual_qty'] ?? '-',
      'posisi_hari_ini' => $info['posisi_hari_ini'] ?? '-',
      'acc_warna_r' => trim(($info['acc_status'] ?? '-') . ' ' . (($info['acc_tgl'] ?? '-') !== '-' ? '(' . $info['acc_tgl'] . ')' : '')),
      'keputusan' => trim((string) ($r['qc_keputusan'] ?? '')) ?: '-',
      'tindakan' => trim((string) ($r['qc_tindakan'] ?? '')) ?: '-',
      'qc_catatan' => trim((string) ($r['qc_catatan'] ?? '')) ?: '-'
    ];
  }
  return $out;
}
