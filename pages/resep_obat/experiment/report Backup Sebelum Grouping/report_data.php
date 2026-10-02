<?php
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../../../../koneksi3.php';
require_once __DIR__ . '/../experiment_visibility_helper.php';

function reportHasView($conn): bool {
  $stmt = sqlsrv_query($conn, "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=212", [$_SESSION['GroupId'] ?? 0]);
  return $stmt && ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) && (int)($r['CanView'] ?? 0) === 1;
}
function reportDate($v): string {
  if ($v instanceof DateTimeInterface) return $v->format('d/m/Y');
  if (!$v) return '-';
  $ts = strtotime((string)$v);
  return $ts ? date('d/m/Y', $ts) : '-';
}
function reportNum($v): string { return number_format((float)($v ?? 0), 2, '.', ','); }
function reportBuildRows($conn, $conn3, array $filter, string $search = ''): array {
  $conditions = [];
  $params = [];
  resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
  if ($search !== '') {
    $conditions[] = "(e.no_cp LIKE ? OR e.kode_warna LIKE ? OR e.color_name LIKE ? OR e.mesin LIKE ? OR e.qc_keputusan LIKE ?)";
    $s = "%$search%"; array_push($params, $s, $s, $s, $s, $s);
  }
  foreach ([['filter_date_from','e.created_at >= ?'],['filter_date_to','e.created_at <= ?'],['filter_no_cp','e.no_cp LIKE ?'],['filter_kode_lab','e.kode_warna LIKE ?'],['filter_warna','e.color_name LIKE ?'],['filter_mesin','e.mesin LIKE ?'],['filter_keputusan','e.qc_keputusan = ?']] as $f) {
    [$key,$sql] = $f; $val = trim((string)($filter[$key] ?? ''));
    if ($val === '') continue;
    $conditions[] = $sql;
    $params[] = $key === 'filter_date_to' ? $val . ' 23:59:59' : (str_contains($sql, 'LIKE') ? "%$val%" : $val);
  }
  $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
  $stmt = sqlsrv_query($conn, "SELECT e.id,e.created_at,e.kode_warna,e.color_name,e.mesin,e.no_cp,e.plan_qty,e.qc_keputusan FROM dbo.resep_obat_experiment e $where ORDER BY e.created_at DESC", $params);
  $rows = []; $cpList = [];
  while ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; $cp = trim((string)($r['no_cp'] ?? '')); if ($cp !== '') $cpList[] = $cp; }
  if ($stmt) sqlsrv_free_stmt($stmt);
  $erp = [];
  if ($cpList) {
    try {
      $keys=[]; $pg=[]; foreach (array_values(array_unique($cpList)) as $i=>$cp) { $k=':cp_'.$i; $keys[]=$k; $pg[$k]=strtoupper($cp); }
      $ph=implode(',',$keys); $celupIds='838,842,555,556,559,809';
      $sql="WITH latest_cp AS (SELECT TRIM(CAST(prdnmbr AS TEXT)) cp_no,productionhdid,ROW_NUMBER() OVER (PARTITION BY UPPER(TRIM(CAST(prdnmbr AS TEXT))) ORDER BY prddate DESC NULLS LAST,productionhdid DESC) rn FROM pdproductionhd WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN ($ph)), latest AS (SELECT cp_no,productionhdid FROM latest_cp WHERE rn=1), celup AS (SELECT DISTINCT ON (l.cp_no) l.cp_no,r.starttime tgl_celup_padd FROM latest l JOIN pdproductionrtg r ON r.productionhdid=l.productionhdid WHERE r.rtgmsid IN ($celupIds) AND r.starttime IS NOT NULL ORDER BY l.cp_no,r.rtgseq ASC NULLS LAST,r.starttime ASC NULLS LAST,r.productionrtgid ASC), last_filled AS (SELECT DISTINCT ON (l.cp_no) l.cp_no,r.rtgseq,r.rtgmsid FROM latest l JOIN pdproductionrtg r ON r.productionhdid=l.productionhdid WHERE COALESCE(r.prdqty,0)>0 OR r.prduomid IS NOT NULL OR COALESCE(r.prdstdqty,0)>0 ORDER BY l.cp_no,r.rtgseq DESC NULLS LAST,r.productionrtgid DESC), posisi AS (SELECT l.cp_no,CASE WHEN lf.rtgmsid=692 THEN 'VERPACKING' WHEN COALESCE(next_m.rtgname,'')<>'' THEN next_m.rtgname WHEN COALESCE(first_m.rtgname,'')<>'' THEN first_m.rtgname ELSE 'PEMARTAIAN' END posisi_hari_ini FROM latest l LEFT JOIN last_filled lf ON lf.cp_no=l.cp_no LEFT JOIN pdproductionrtg next_r ON next_r.productionhdid=l.productionhdid AND next_r.rtgseq=lf.rtgseq+1 LEFT JOIN pdrtgms next_m ON next_m.rtgmsid=next_r.rtgmsid LEFT JOIN pdproductionrtg first_r ON first_r.productionhdid=l.productionhdid AND first_r.rtgmsid=409 LEFT JOIN pdrtgms first_m ON first_m.rtgmsid=first_r.rtgmsid), acc AS (SELECT DISTINCT ON (l.cp_no) l.cp_no,r.starttime acc_tgl,COALESCE(r.failmsid,0) failmsid,COALESCE(r.fgresult,'') fgresult,COALESCE(f.failcode,'') failcode,COALESCE(f.faildesc,'') faildesc FROM latest l JOIN pdproductionrtg r ON r.productionhdid=l.productionhdid AND r.rtgmsid=589 LEFT JOIN pdfailms f ON f.failmsid=r.failmsid ORDER BY l.cp_no,CASE WHEN r.failmsid IS NOT NULL AND r.failmsid<>0 THEN 0 ELSE 1 END ASC,r.starttime DESC NULLS LAST,r.productionrtgid DESC) SELECT l.cp_no,c.tgl_celup_padd,p.posisi_hari_ini,a.acc_tgl,a.failmsid,a.fgresult,a.failcode,a.faildesc FROM latest l LEFT JOIN celup c ON c.cp_no=l.cp_no LEFT JOIN posisi p ON p.cp_no=l.cp_no LEFT JOIN acc a ON a.cp_no=l.cp_no";
      $st=$conn3->prepare($sql); $st->execute($pg);
      while ($r=$st->fetch(PDO::FETCH_ASSOC)) { $fail=(int)($r['failmsid']??0); $fg=strtoupper(trim((string)($r['fgresult']??''))); $has=trim((string)($r['failcode']??''))!==''||trim((string)($r['faildesc']??''))!==''; $status='-'; if($fail!==0&&$has)$status='Fail'; elseif($fg==='P'&&$fail===0&&!$has)$status='Pass'; $erp[strtoupper(trim($r['cp_no']))]=['tgl_celup_padd'=>reportDate($r['tgl_celup_padd']??null),'posisi_hari_ini'=>trim((string)($r['posisi_hari_ini']??''))?:'-','acc_status'=>$status,'acc_tgl'=>reportDate($r['acc_tgl']??null)]; }
    } catch (Throwable $e) { error_log('report ERP error: '.$e->getMessage()); }
  }
  $out=[]; $accFilter=trim((string)($filter['filter_acc_status']??'')); $posFilter=strtoupper(trim((string)($filter['filter_posisi']??'')));
  foreach ($rows as $r) { $cp=strtoupper(trim((string)($r['no_cp']??''))); $i=$erp[$cp]??[]; $acc=$i['acc_status']??'-'; $pos=$i['posisi_hari_ini']??'-'; if($accFilter!==''&&$acc!==$accFilter) continue; if($posFilter!==''&&stripos($pos,$posFilter)===false) continue; $out[]=['id'=>(int)$r['id'],'tgl_match'=>reportDate($r['created_at']??null),'warna'=>$r['color_name']??'-','tgl_celup_padd'=>$i['tgl_celup_padd']??'-','mesin_paddry'=>$r['mesin']??'-','no_cp'=>$r['no_cp']??'-','kode_lab'=>$r['kode_warna']??'-','qty'=>reportNum($r['plan_qty']??0),'posisi_hari_ini'=>$pos,'acc_warna_r_status'=>$acc,'acc_warna_r_tgl'=>$i['acc_tgl']??'-','keputusan'=>trim((string)($r['qc_keputusan']??''))?:'-']; }
  return $out;
}
