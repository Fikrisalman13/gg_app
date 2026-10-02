<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__.'/../../../koneksi.php';
require_once __DIR__.'/experiment_visibility_helper.php';
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['UserName'])){echo json_encode(['status'=>'error','message'=>'Unauthorized']);exit;}
$user=$_SESSION['UserName'];
$now=date('Y-m-d H:i:s');
$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;
$isPpc = resepUserIsPpc($conn);
$isKabag = resepUserIsKabag($conn);

function cleanHeaderNum($s){return ($s===''||$s===null)?0:floatval(str_replace(',','',$s));}
function cleanPriceNum($s){$v=str_replace(['Rp',' '],'',$s);$v=str_replace('.','',$v);$v=str_replace(',','.',$v);return floatval($v);}
function cleanUSNum($s){return floatval(str_replace(',','',$s??0));}
function fail($m){ echo json_encode(['status'=>'error','message'=>$m]); exit; }

$resep_id = $_POST['resep_id'] ?? '';
$group_id = $_POST['group_id'] ?? '';
$next_group_id = $_POST['next_group_id'] ?? '';
$kode_warna = trim($_POST['kode_warna'] ?? '');
if ($kode_warna === '') fail('Kode Warna wajib diisi');

$groupVals = [
  'soi'=>trim($_POST['soi']??''),
  'no_cp'=>trim($_POST['no_cp']??''),
  'kode_grey'=>$_POST['kode_grey']??'',
  'mesin'=>$_POST['mesin']??'',
  'kode_warna'=>$kode_warna,
  'color_name'=>$_POST['color_name']??'',
  'color_desc'=>$_POST['color_desc']??'',
  'resep_prod_code'=>$_POST['resepprodcode']??'',
  'resep_prod_name'=>$_POST['resepprodname']??'',
  'cus_color'=>$_POST['cus_color']??'',
  'proint_resephdid'=>($_POST['proint_resephdid']??'')!==''?(int)$_POST['proint_resephdid']:null,
];
$expVals = [
  'lot_no'=>$_POST['lot_no']??'',
  'weight'=>cleanUSNum($_POST['weight']??0),
  'plan_qty'=>cleanHeaderNum($_POST['plan_qty']??0),
  'vlot'=>cleanHeaderNum($_POST['vlot']??0),
  'experiment_status'=>$_POST['experiment_status']??'Draft',
  'experiment_note'=>$_POST['experiment_note']??'',
];
$autoProcessMessage = '';

if ($resep_id) {
  $stmt = sqlsrv_query($conn, "SELECT group_id, experiment_status FROM dbo.resep_obat_experiment WHERE id=?", [$resep_id]);
  $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
  if (!$row) fail('Data experiment tidak ditemukan');
  $group_id = $row['group_id'];
  if (!$group_id) fail('Group experiment tidak ditemukan');

  // SOI & No CP are now stored per-experiment, not per-group.
  $oldStatus = trim((string)($row['experiment_status'] ?? 'Draft'));
  if ($isPpc && $oldStatus === 'Approved' && trim((string)$groupVals['soi']) !== '' && trim((string)$groupVals['no_cp']) !== '') {
    $expVals['experiment_status'] = 'Process';
    $autoProcessMessage = 'Status otomatis berubah dari Approved menjadi Process karena SOI dan No CP sudah terisi.';
  }

  $statusClauseE = ($isAdmin || $isPpc || $isKabag) ? '' : "AND experiment_status <> 'Approved'";
  $newStatus = $expVals['experiment_status'];
  
  $wasInProcessClause = "";
  if (in_array($newStatus, ['Process', 'Prosess', 'Sukses', 'Success', 'Gagal', 'Lunas'], true)) {
      $wasInProcessClause = ", was_in_process=1";
  }
  
  $wasApprovedClause = "";
  if ($newStatus === 'Approved') {
      $wasApprovedClause = ", was_approved=1";
  }

  $sqlE = "UPDATE dbo.resep_obat_experiment SET lot_no=?,weight=?,plan_qty=?,vlot=?,experiment_status=?,experiment_note=?,updated_at=?,updated_by=?,kode_grey=?,mesin=?,kode_warna=?,color_name=?,color_desc=?,resep_prod_code=?,resep_prod_name=?,cus_color=?,proint_resephdid=?,no_cp=?,soi=? $wasInProcessClause $wasApprovedClause WHERE id=? $statusClauseE";
  $paramsE = [$expVals['lot_no'],$expVals['weight'],$expVals['plan_qty'],$expVals['vlot'],$expVals['experiment_status'],$expVals['experiment_note'],$now,$user,$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],$groupVals['no_cp'],$groupVals['soi'],$resep_id];
  $stmtE = sqlsrv_query($conn, $sqlE, $paramsE);
  if (!$stmtE) fail('Update experiment gagal: '.print_r(sqlsrv_errors(),true));
  sqlsrv_query($conn,"DELETE FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=?",[$resep_id]);
  $id = $resep_id;
} elseif ($next_group_id) {
  $group_id = (int)$next_group_id;
  $sg = sqlsrv_query($conn, "SELECT group_status FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
  $g = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
  if (!$g) fail('Group tidak ditemukan');
  if (!$isAdmin && !$isPpc && !$isKabag && ($g['group_status'] ?? '') === 'Approved') fail('Group sudah Approved');

  $sl = sqlsrv_query($conn, "SELECT TOP 1 experiment_seq FROM dbo.resep_obat_experiment WHERE group_id=? ORDER BY experiment_seq DESC, id DESC", [$group_id]);
  $lr = $sl ? sqlsrv_fetch_array($sl, SQLSRV_FETCH_ASSOC) : null;
  $nextSeq = $lr ? ((int)$lr['experiment_seq'] + 1) : 1;

  $wasInProcessVal = in_array($expVals['experiment_status'], ['Process', 'Prosess', 'Sukses', 'Success', 'Gagal', 'Lunas'], true) ? 1 : 0;
  $wasApprovedVal = ($expVals['experiment_status'] === 'Approved') ? 1 : 0;

  $sqlE = "INSERT INTO dbo.resep_obat_experiment (group_id,experiment_seq,experiment_status,experiment_note,no_cp,soi,kode_grey,mesin,kode_warna,color_name,color_desc,resep_prod_code,resep_prod_name,cus_color,proint_resephdid,lot_no,weight,plan_qty,vlot,created_at,created_by,was_in_process,was_approved) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $paramsE = [$group_id,$nextSeq,$expVals['experiment_status'],$expVals['experiment_note'],$groupVals['no_cp'],$groupVals['soi'],$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],$expVals['lot_no'],$expVals['weight'],$expVals['plan_qty'],$expVals['vlot'],$now,$user,$wasInProcessVal,$wasApprovedVal];
  $stmtE = sqlsrv_query($conn,$sqlE,$paramsE);
  if (!$stmtE) fail('Insert experiment gagal: '.print_r(sqlsrv_errors(),true));
  $eRow = sqlsrv_fetch_array($stmtE, SQLSRV_FETCH_ASSOC);
  $id = $eRow['id'] ?? null;
  if (!$id) fail('Gagal mendapatkan ID experiment baru');
} else {
  $dupStmt = sqlsrv_query($conn, "SELECT TOP 1 id, soi, no_cp, color_name, group_status FROM dbo.resep_obat_experiment_group WHERE kode_warna=? ORDER BY id DESC", [$kode_warna]);
  $dup = $dupStmt ? sqlsrv_fetch_array($dupStmt, SQLSRV_FETCH_ASSOC) : null;
  if ($dup) {
    $dupInfo = 'Kode Warna sudah ada (SOI: '.(($dup['soi'] ?? '') ?: '-').', No CP: '.(($dup['no_cp'] ?? '') ?: '-').', Status: '.(($dup['group_status'] ?? '') ?: '-').')';
    fail($dupInfo);
  }
  $sqlG = "INSERT INTO dbo.resep_obat_experiment_group (soi,no_cp,kode_grey,mesin,kode_warna,color_name,color_desc,resep_prod_code,resep_prod_name,cus_color,proint_resephdid,group_status,created_at,created_by) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $paramsG = [$groupVals['soi'],$groupVals['no_cp'],$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],'Draft',$now,$user];
  $stmtG = sqlsrv_query($conn,$sqlG,$paramsG);
  if (!$stmtG) fail('Insert group gagal: '.print_r(sqlsrv_errors(),true));
  $gRow = sqlsrv_fetch_array($stmtG, SQLSRV_FETCH_ASSOC);
  $group_id = $gRow['id'] ?? null;
  if (!$group_id) fail('Gagal mendapatkan ID group baru');

  $wasInProcessVal = in_array($expVals['experiment_status'], ['Process', 'Prosess', 'Sukses', 'Success', 'Gagal', 'Lunas'], true) ? 1 : 0;
  $wasApprovedVal = ($expVals['experiment_status'] === 'Approved') ? 1 : 0;

  $sqlE = "INSERT INTO dbo.resep_obat_experiment (group_id,experiment_seq,experiment_status,experiment_note,no_cp,soi,kode_grey,mesin,kode_warna,color_name,color_desc,resep_prod_code,resep_prod_name,cus_color,proint_resephdid,lot_no,weight,plan_qty,vlot,created_at,created_by,was_in_process,was_approved) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $paramsE = [$group_id,1,$expVals['experiment_status'],$expVals['experiment_note'],$groupVals['no_cp'],$groupVals['soi'],$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],$expVals['lot_no'],$expVals['weight'],$expVals['plan_qty'],$expVals['vlot'],$now,$user,$wasInProcessVal,$wasApprovedVal];
  $stmtE = sqlsrv_query($conn,$sqlE,$paramsE);
  if (!$stmtE) fail('Insert experiment gagal: '.print_r(sqlsrv_errors(),true));
  $eRow = sqlsrv_fetch_array($stmtE, SQLSRV_FETCH_ASSOC);
  $id = $eRow['id'] ?? null;
  if (!$id) fail('Gagal mendapatkan ID experiment baru');
}

$items=$_POST['items']??[]; $err='';
if(is_array($items)){
  $sqlD="INSERT INTO dbo.resep_obat_experiment_detail (id_resep_experiment,kode,name,category,receipe,uom,cf,uom_cf,std_price,total,created_at,created_by,price_satuan,price_source,is_manual,master_obat_id,codeprod_proint) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  foreach($items as $it){
    $kode=trim($it['kode']??''); if($kode==='') continue;
    $qty=cleanUSNum($it['receipe']??0); $price=cleanPriceNum($it['std_price']??0); $total=$qty*$price; $uom=strtoupper(trim($it['uom']??'')); if($uom==='GR'||$uom==='G/L')$total/=1000;
    $masterObatId = !empty($it['master_obat_id']) ? (int)$it['master_obat_id'] : null;
    $codeprodProint = trim($it['codeprod_proint'] ?? '');
    $paramsD=[$id,$kode,$it['name']??'',$it['category']??'',$qty,$it['uom']??'',cleanUSNum($it['cf']??0),$it['uom_cf']??'',$price,$total,$now,$user,$it['price_satuan']??'',$it['price_source']??'',1,$masterObatId,$codeprodProint];
    $sd=sqlsrv_query($conn,$sqlD,$paramsD); if(!$sd)$err.='Item '.$kode.' gagal: '.print_r(sqlsrv_errors(),true);
  }
}

/* ---- Handle Lampiran Upload ---- */
$lampiran_warning = '';
if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
  $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
  $allowed_ext  = ['pdf', 'jpg', 'jpeg', 'png'];
  $max_size = 5 * 1024 * 1024; // 5 MB

  $file_tmp  = $_FILES['lampiran']['tmp_name'];
  $file_name = basename($_FILES['lampiran']['name']);
  $file_size = $_FILES['lampiran']['size'];
  $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
  $finfo     = finfo_open(FILEINFO_MIME_TYPE);
  $file_mime = finfo_file($finfo, $file_tmp);
  finfo_close($finfo);

  if (!in_array($file_ext, $allowed_ext) || !in_array($file_mime, $allowed_types)) {
    $lampiran_warning = 'Format file tidak diizinkan. Hanya PDF, JPG, PNG.';
  } elseif ($file_size > $max_size) {
    $lampiran_warning = 'Ukuran file terlalu besar (maks 5 MB).';
  } else {
    $upload_dir = __DIR__ . '/uploads_lampiran/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // Hapus lampiran lama jika ada
    $stmtOld = sqlsrv_query($conn, "SELECT lampiran_path FROM dbo.resep_obat_experiment WHERE id=?", [$id]);
    $oldRow = $stmtOld ? sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC) : null;
    if (!empty($oldRow['lampiran_path'])) {
      $oldFile = $upload_dir . basename($oldRow['lampiran_path']);
      if (file_exists($oldFile)) @unlink($oldFile);
    }

    // Simpan file baru dengan nama unik
    $safe_name = 'resep_' . $id . '_' . time() . '.' . $file_ext;
    $dest = $upload_dir . $safe_name;
    if (move_uploaded_file($file_tmp, $dest)) {
      $rel_path = 'uploads_lampiran/' . $safe_name;
      $stmtL = sqlsrv_query($conn,
        "UPDATE dbo.resep_obat_experiment SET lampiran_path=?, lampiran_name=? WHERE id=?",
        [$rel_path, $file_name, $id]
      );
      if (!$stmtL) $lampiran_warning = 'Upload berhasil tapi gagal simpan ke DB.';
    } else {
      $lampiran_warning = 'Gagal menyimpan file.';
    }
  }
} elseif (isset($_POST['hapus_lampiran']) && $_POST['hapus_lampiran'] === '1') {
  // Hapus lampiran jika user klik hapus
  $stmtOld = sqlsrv_query($conn, "SELECT lampiran_path FROM dbo.resep_obat_experiment WHERE id=?", [$id]);
  $oldRow = $stmtOld ? sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC) : null;
  if (!empty($oldRow['lampiran_path'])) {
    $upload_dir = __DIR__ . '/uploads_lampiran/';
    $oldFile = $upload_dir . basename($oldRow['lampiran_path']);
    if (file_exists($oldFile)) @unlink($oldFile);
  }
  sqlsrv_query($conn, "UPDATE dbo.resep_obat_experiment SET lampiran_path=NULL, lampiran_name=NULL WHERE id=?", [$id]);
}

/* ---- Helper: parse numeric lab values ---- */
function labNum($v) {
  if ($v === '' || $v === null) return null;
  $c = str_replace([' ', ','], ['', '.'], (string)$v);
  return is_numeric($c) ? (float)$c : null;
}
function labDec($v) {
  $n = labNum($v);
  return [$n, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)];
}

/* ---- Save Lab Parameter (Mesin Lab) ---- */
$hasLabParam = trim($_POST['lab_machine_code'] ?? '') !== ''
    || trim($_POST['lab_infra_red'] ?? '') !== ''
    || trim($_POST['lab_tekanan_padder'] ?? '') !== ''
    || trim($_POST['lab_wpu'] ?? '') !== ''
    || trim($_POST['lab_speed'] ?? '') !== ''
    || trim($_POST['lab_fan1'] ?? '') !== ''
    || trim($_POST['lab_fan2'] ?? '') !== ''
    || trim($_POST['lab_temp_chamber_1'] ?? '') !== ''
    || trim($_POST['lab_temp_chamber_1_time'] ?? '') !== ''
    || trim($_POST['lab_temp_chamber_2'] ?? '') !== ''
    || trim($_POST['lab_temp_chamber_2_time'] ?? '') !== ''
    || trim($_POST['lab_lainnya'] ?? '') !== '';

if ($hasLabParam) {
    $lpExists = 0;
    $slp = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.resep_obat_experiment_lab_param WHERE id_resep_experiment=?", [$id]);
    if ($slp && $r = sqlsrv_fetch_array($slp, SQLSRV_FETCH_ASSOC)) $lpExists = (int)$r['id'];
    if ($slp) sqlsrv_free_stmt($slp);

    $lpVals = [
        'machine_code'        => trim($_POST['lab_machine_code'] ?? ''),
        'machine_name'        => trim($_POST['lab_machine_name'] ?? ''),
        'infra_red'           => labNum($_POST['lab_infra_red'] ?? null),
        'tekanan_padder'      => labNum($_POST['lab_tekanan_padder'] ?? null),
        'wpu'                 => labNum($_POST['lab_wpu'] ?? null),
        'speed'               => labNum($_POST['lab_speed'] ?? null),
        'fan1'                => labNum($_POST['lab_fan1'] ?? null),
        'fan2'                => labNum($_POST['lab_fan2'] ?? null),
        'temp_chamber_1'      => labNum($_POST['lab_temp_chamber_1'] ?? null),
        'temp_chamber_1_time' => labNum($_POST['lab_temp_chamber_1_time'] ?? null),
        'temp_chamber_2'      => labNum($_POST['lab_temp_chamber_2'] ?? null),
        'temp_chamber_2_time' => labNum($_POST['lab_temp_chamber_2_time'] ?? null),
        'lainnya'             => trim($_POST['lab_lainnya'] ?? ''),
    ];

    if ($lpExists) {
        $sqlLP = "UPDATE dbo.resep_obat_experiment_lab_param SET machine_code=?,machine_name=?,infra_red=?,tekanan_padder=?,wpu=?,speed=?,fan1=?,fan2=?,temp_chamber_1=?,temp_chamber_1_time=?,temp_chamber_2=?,temp_chamber_2_time=?,lainnya=?,updated_at=?,updated_by=? WHERE id=?";
        $paramsLP = [
            $lpVals['machine_code'], $lpVals['machine_name'],
            labDec($lpVals['infra_red']), labDec($lpVals['tekanan_padder']), labDec($lpVals['wpu']),
            labDec($lpVals['speed']), labDec($lpVals['fan1']), labDec($lpVals['fan2']),
            labDec($lpVals['temp_chamber_1']), labDec($lpVals['temp_chamber_1_time']),
            labDec($lpVals['temp_chamber_2']), labDec($lpVals['temp_chamber_2_time']),
            $lpVals['lainnya'], $now, $user, $lpExists
        ];
    } else {
        $sqlLP = "INSERT INTO dbo.resep_obat_experiment_lab_param (id_resep_experiment,machine_code,machine_name,infra_red,tekanan_padder,wpu,speed,fan1,fan2,temp_chamber_1,temp_chamber_1_time,temp_chamber_2,temp_chamber_2_time,lainnya,created_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $paramsLP = [
            $id, $lpVals['machine_code'], $lpVals['machine_name'],
            labDec($lpVals['infra_red']), labDec($lpVals['tekanan_padder']), labDec($lpVals['wpu']),
            labDec($lpVals['speed']), labDec($lpVals['fan1']), labDec($lpVals['fan2']),
            labDec($lpVals['temp_chamber_1']), labDec($lpVals['temp_chamber_1_time']),
            labDec($lpVals['temp_chamber_2']), labDec($lpVals['temp_chamber_2_time']),
            $lpVals['lainnya'], $now, $user
        ];
    }
    $stmtLP = sqlsrv_query($conn, $sqlLP, $paramsLP);
    if (!$stmtLP) {
        $err .= ' Lab Param gagal: ' . print_r(sqlsrv_errors(), true);
    }
    if ($stmtLP) sqlsrv_free_stmt($stmtLP);
}

/* ---- Save Lab Data (Delta L/A/B/E) ---- */
$hasLabData = trim($_POST['lab_delta_l'] ?? '') !== ''
    || trim($_POST['lab_delta_a'] ?? '') !== ''
    || trim($_POST['lab_delta_b'] ?? '') !== ''
    || trim($_POST['lab_delta_e'] ?? '') !== '';

if ($hasLabData) {
    $ldExists = 0;
    $sld = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.resep_obat_experiment_lab_data WHERE id_resep_experiment=?", [$id]);
    if ($sld && $r = sqlsrv_fetch_array($sld, SQLSRV_FETCH_ASSOC)) $ldExists = (int)$r['id'];
    if ($sld) sqlsrv_free_stmt($sld);

    $ldVals = [
        labNum($_POST['lab_delta_l'] ?? null),
        labNum($_POST['lab_delta_a'] ?? null),
        labNum($_POST['lab_delta_b'] ?? null),
        labNum($_POST['lab_delta_e'] ?? null),
    ];

    if ($ldExists) {
        $sqlLD = "UPDATE dbo.resep_obat_experiment_lab_data SET delta_l=?,delta_a=?,delta_b=?,delta_e=?,updated_at=?,updated_by=? WHERE id=?";
        $paramsLD = [...$ldVals, $now, $user, $ldExists];
    } else {
        $sqlLD = "INSERT INTO dbo.resep_obat_experiment_lab_data (id_resep_experiment,delta_l,delta_a,delta_b,delta_e,created_at,created_by) VALUES (?,?,?,?,?,?,?)";
        $paramsLD = [$id, ...$ldVals, $now, $user];
    }
    $stmtLD = sqlsrv_query($conn, $sqlLD, $paramsLD);
    if (!$stmtLD) {
        $err .= ' Lab Data gagal: ' . print_r(sqlsrv_errors(), true);
    }
    if ($stmtLD) sqlsrv_free_stmt($stmtLD);
}

echo json_encode(['status'=>'success','id'=>$id,'group_id'=>$group_id,'warning'=>$err,'lampiran_warning'=>$lampiran_warning,'auto_process_message'=>$autoProcessMessage]);

