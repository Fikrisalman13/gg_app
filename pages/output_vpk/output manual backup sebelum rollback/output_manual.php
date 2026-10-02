<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

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

function om_lookup_conn3(string $scan): array
{
    global $conn3;
    if (!isset($conn3) || !($conn3 instanceof PDO)) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php';
    }
    $sql = "SELECT h.productionhdid, r.productionrtgid, TRIM(h.prdnmbr) AS prdnmbr,
                   h.prodid, h.prdstduomid AS hd_prdstduomid, t.cuscolor, t.labeljual,
                   t.stdcutfg, r.prdqty, r.prduomid, r.prdstdqty,
                   r.prdstduomid AS r_prdstduomid, r.upddate AS source_upddate
            FROM pdproductionhd h
            LEFT JOIN smprodtechdata t ON t.prodid = h.prodid
            JOIN pdproductionrtg r ON r.productionhdid = h.productionhdid AND r.rtgseq = 1
            WHERE h.prdnmbr = :scan
            LIMIT 1";
    try {
        $stmt = $conn3->prepare($sql);
        $stmt->execute(['scan' => $scan]);
        return [$stmt->fetch(PDO::FETCH_ASSOC) ?: null, null];
    } catch (PDOException $e) {
        return [null, $e->getMessage()];
    }
}

function om_master(string $scan): ?array
{
    global $conn;
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM manual_output_master WHERE prdnmbr = ?", [$scan]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null;
    sqlsrv_free_stmt($stmt);
    return $row;
}

function om_cache_master(string $scan): array
{
    global $conn;
    $master = om_master($scan);
    if ($master) return [$master, null];
    [$source, $error] = om_lookup_conn3($scan);
    if ($error || !$source) return [null, $error ?: 'Data tidak ditemukan di conn3.'];
    $sql = "INSERT INTO manual_output_master
            (prdnmbr, prodid, prdstduomid, cuscolor, labeljual, stdcutfg, prdqty,
             prduomid, prdstdqty, r_prdstduomid, created_at, updated_at,
             productionhdid, productionrtgid, source_upddate, sisa_qty)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE(), ?, ?, ?, ?)";
    $params = [$scan, $source['prodid'], $source['hd_prdstduomid'], $source['cuscolor'],
        $source['labeljual'], $source['stdcutfg'], $source['prdqty'], $source['prduomid'],
        $source['prdstdqty'], $source['r_prdstduomid'], $source['productionhdid'],
        $source['productionrtgid'], $source['source_upddate'], $source['prdqty']];
    if (sqlsrv_query($conn, $sql, $params) === false) {
        $master = om_master($scan); // concurrent insert may have won
        return $master ? [$master, null] : [null, 'Gagal menyimpan cache master.'];
    }
    return [om_master($scan), null];
}

function om_row(int $id): ?array
{
    global $conn;
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM manual_output WHERE id=?", [$id]);
    return $stmt ? (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null) : null;
}

function om_active_shift(int $headerId): ?array
{
    global $conn;
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM manual_output_shift WHERE manual_output_id=? AND status_active=1 ORDER BY id DESC", [$headerId]);
    return $stmt ? (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null) : null;
}

function om_shift_total(int $headerId, int $excludeShiftId = 0): float
{
    global $conn;
    $sql = "SELECT COALESCE(SUM(meter),0) total FROM manual_output_shift WHERE manual_output_id=?" . ($excludeShiftId ? " AND id<>?" : "");
    $stmt = sqlsrv_query($conn, $sql, $excludeShiftId ? [$headerId,$excludeShiftId] : [$headerId]);
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    return (float)($row['total'] ?? 0);
}

function om_shift_history(int $headerId): array
{
    global $conn;
    $rows=[]; $stmt=sqlsrv_query($conn,"SELECT s.id,s.operator,s.meter,s.start_time,CASE WHEN s.finish_time IS NULL AND s.handover_from IS NULL THEN o.finish_time ELSE s.finish_time END finish_time,s.status_active,s.handover_from,m.kode_mesin FROM manual_output_shift s JOIN manual_output o ON o.id=s.manual_output_id LEFT JOIN manual_output_master_mesin m ON m.id=s.mesin_id WHERE s.manual_output_id=? ORDER BY s.id",[$headerId]);
    if($stmt) while($row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $rows[]=[
        'id'=>(int)$row['id'],'operator'=>$row['operator'],'mesin'=>$row['kode_mesin'] ?? '-','meter'=>(float)$row['meter'],
        'start_time'=>om_display_dt($row['start_time']),'finish_time'=>om_display_dt($row['finish_time']),
        'active'=>(bool)$row['status_active'],'handover_from'=>$row['handover_from'] ?? ''
    ];
    return $rows;
}

function om_shift_range(int $headerId): array
{
    global $conn;
    $stmt=sqlsrv_query($conn,"SELECT
        (SELECT TOP 1 start_time FROM manual_output_shift WHERE manual_output_id=? ORDER BY id ASC) first_start,
        (SELECT TOP 1 finish_time FROM manual_output_shift WHERE manual_output_id=? ORDER BY id DESC) last_finish",[$headerId,$headerId]);
    return $stmt ? (sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC) ?: []) : [];
}

function om_payload(array $header, array $master, ?array $shift = null): array
{
    $scan=(string)($header['prdnmbr'] ?? $master['prdnmbr']); $headerId=(int)($header['id'] ?? 0);
    $shiftId=(int)($shift['id'] ?? 0); $other=$headerId ? om_shift_total($headerId,$shiftId) : 0.0;
    $actualMeter=(float)($shift['meter'] ?? 0); $available=max(0,(float)$master['prdqty']-$other);
    $isFreshHandover=$shift && $actualMeter==0.0 && !empty($shift['handover_from']) && empty($shift['updated_at']);
    $isFreshDraft=$shift && !empty($header['status_draft']) && $actualMeter==0.0 && empty($shift['updated_at']);
    $formMeter=$shift ? (($isFreshHandover || $isFreshDraft) ? $available : $actualMeter) : $available;
    $total=$other+$actualMeter; $complete=$headerId>0 && $total>=(float)$master['prdqty']-0.00001;
    $range=$complete ? om_shift_range($headerId) : [];
    $infoStart=$complete ? ($range['first_start'] ?? '') : ($shift['start_time'] ?? date('Y-m-d H:i:s'));
    $infoFinish=$complete ? ($range['last_finish'] ?? '') : ($shift['finish_time'] ?? (($shift && empty($shift['handover_from'])) ? ($header['finish_time'] ?? '') : ''));
    return [
        'id'=>$headerId,'shift_id'=>$shiftId,'prdnmbr'=>$scan,'iso'=>$header['iso'] ?? substr($scan,0,8),'partai'=>$header['partai'] ?? substr($scan,8,8),
        'cuscolor'=>$header['cuscolor'] ?? $master['cuscolor'] ?? '','meter'=>$formMeter,'gol'=>$shift['gol'] ?? '','grey'=>$shift['grey'] ?? '',
        'tengah'=>$shift['tengah'] ?? '','ket'=>$shift['ket'] ?? '','start_time'=>om_dt($infoStart),
        'finish_time'=>om_dt($infoFinish),'stdcutfg'=>$header['stdcutfg'] ?? $master['stdcutfg'] ?? '',
        'op_mesin'=>$shift['operator'] ?? om_user(),'labeljual'=>$header['labeljual'] ?? $master['labeljual'] ?? '',
        'mesin_id'=>(int)($shift['mesin_id'] ?? $header['mesin_id'] ?? 0),'status_draft'=>!empty($header['status_draft']),
        'created_by'=>$header['created_by'] ?? om_user(),'current_operator'=>$shift['operator'] ?? om_user(),
        'row_version'=>isset($shift['row_version']) ? bin2hex($shift['row_version']) : '',
        'prdqty'=>(float)$master['prdqty'],'previous_qty'=>$other,'meter_actual'=>$actualMeter,'used_qty'=>$total,
        'remaining_qty'=>max(0,(float)$master['prdqty']-$total),'available_qty'=>$available,
        'uom'=>'METER','shifts'=>$headerId ? om_shift_history($headerId) : []
    ];
}

function om_qty_notice(array $data): string
{
    return 'Qty Pemartaian: '.number_format($data['prdqty'],4).' METER | Total Shift Sebelumnya: '.number_format($data['previous_qty'],4)
        .' METER | Meter Shift Ini: '.number_format($data['meter_actual'],4).' METER | Maksimal Shift Ini: '.number_format($data['available_qty'],4)
        .' METER | Total Terinput: '.number_format($data['used_qty'],4).' METER | Sisa Qty: '.number_format($data['remaining_qty'],4).' METER';
}

function om_table_rows_html(array $rows,string $userTheme): string
{
    ob_start();$no=1;
    foreach($rows as $row):
        $owned=trim((string)($row['current_operator'] ?? $row['op_mesin'] ?? $row['created_by'] ?? ''))===om_user();
        ?><tr>
            <td><?= $no++ ?></td><td><?= htmlspecialchars($row['prdnmbr'] ?? '') ?></td><td><?= htmlspecialchars($row['iso'] ?? '') ?></td><td><?= htmlspecialchars($row['partai'] ?? '') ?></td><td><?= htmlspecialchars($row['cuscolor'] ?? '') ?></td><td><?= htmlspecialchars((string)($row['meter'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['gol'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['grey'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['tengah'] ?? '')) ?></td><td><?= htmlspecialchars(om_display_dt($row['start_time'] ?? '')) ?></td><td><?= !empty($row['finish_time']) ? htmlspecialchars(om_display_dt($row['finish_time'])) : '-' ?></td><td><?= !empty($row['status_draft']) ? '<span class="badge badge-warning">Belum Berjalan</span>' : (!empty($row['finish_time']) ? '<span class="badge badge-success">Selesai</span>' : '<span class="badge badge-info">Berjalan</span>') ?></td><td><?= htmlspecialchars((string)($row['stdcutfg'] ?? '')) ?></td><td><?= htmlspecialchars($row['op_mesin'] ?? '') ?></td><td><?= htmlspecialchars($row['kode_mesin'] ?? '-') ?></td><td><?= htmlspecialchars($row['labeljual'] ?? '') ?></td><td><?= htmlspecialchars($row['ket'] ?? '') ?></td><td><?= htmlspecialchars($row['created_by'] ?? '') ?></td><td><?php if(!empty($row['finish_time'])): ?><button type="button" class="btn btn-sm btn-info btnEditRow" data-id="<?= (int)$row['id'] ?>" title="Detail"><i class="fas fa-eye"></i></button><?php elseif($owned): ?><button type="button" class="btn btn-sm btn-<?= htmlspecialchars($userTheme) ?> btnEditRow" data-id="<?= (int)$row['id'] ?>" title="Edit data"><i class="fas fa-edit"></i></button> <button type="button" class="btn btn-sm btn-danger btnDeleteRow" data-id="<?= (int)$row['id'] ?>" title="Hapus data"><i class="fas fa-trash"></i></button><?php else: ?><button type="button" class="btn btn-sm btn-secondary btnEditRow" data-id="<?= (int)$row['id'] ?>" title="Lihat / oper shift"><i class="fas fa-exchange-alt"></i></button><?php endif; ?></td>
        </tr><?php
    endforeach;
    return (string)ob_get_clean();
}

function om_history(string $scan,int $id,float $before,float $change,float $after,string $action,string $note=''): bool
{
    global $conn; return sqlsrv_query($conn,"INSERT INTO manual_output_qty_history (prdnmbr,manual_output_id,qty_before,qty_change,qty_after,action_type,created_by,note,created_at) VALUES (?,?,?,?,?,?,?,?,GETDATE())",[$scan,$id,$before,$change,$after,$action,om_user(),$note])!==false;
}

$action=$_GET['action'] ?? $_POST['action'] ?? '';
if($action==='lookup'){
    $scan=trim((string)($_GET['scan'] ?? $_POST['scan'] ?? ''));
    if(!in_array(strlen($scan),[15,16],true)) om_json(['success'=>false,'message'=>'Scan harus 15 atau 16 character.']);
    $scanStartedAt=date('Y-m-d H:i:s');
    [$master,$error]=om_cache_master($scan); if(!$master) om_json(['success'=>false,'message'=>$error ?: 'Master tidak ditemukan.']);
    $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WHERE prdnmbr=? AND meter IS NOT NULL ORDER BY id DESC",[$scan]);
    $header=$stmt ? sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC) : null;
    $created=false;
    if(!$header){
        if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai pencatatan scan.']);
        try{
            $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE prdnmbr=? AND meter IS NOT NULL ORDER BY id DESC",[$scan]);
            $header=$stmt ? sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC) : null;
            if(!$header){
                $sql="INSERT INTO manual_output (prdnmbr,prodid,iso,partai,cuscolor,meter,gol,grey,tengah,start_time,finish_time,stdcutfg,prdstduomid,op_mesin,labeljual,ket,created_at,created_by,current_operator,status_draft,mesin_id) OUTPUT INSERTED.id,INSERTED.start_time VALUES (?,?,?,?,?,0,NULL,NULL,NULL,?,NULL,?,?,?,?,NULL,GETDATE(),?,?,1,NULL)";
                $stmt=sqlsrv_query($conn,$sql,[$scan,$master['prodid'],substr($scan,0,8),substr($scan,8,8),$master['cuscolor'],$scanStartedAt,$master['stdcutfg'],$master['r_prdstduomid'],om_user(),$master['labeljual'],om_user(),om_user()]);
                if($stmt===false)throw new RuntimeException('Gagal membuat draft produksi.');
                $new=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC);$id=(int)($new['id']??0);$started=$new['start_time']??null;
                if(!$id||sqlsrv_query($conn,"INSERT INTO manual_output_shift (manual_output_id,prdnmbr,operator,meter,start_time,status_active,created_at) VALUES (?,?,?,0,?,1,GETDATE())",[$id,$scan,om_user(),$started])===false)throw new RuntimeException('Gagal membuat shift pertama.');
                if(!om_history($scan,$id,0,0,0,'SCAN_START','Draft otomatis saat scan'))throw new RuntimeException('Gagal menulis history scan.');
                $header=om_row($id);$created=true;
            }
            sqlsrv_commit($conn);
        }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
    }
    $shift=om_active_shift((int)$header['id']); $data=om_payload($header,$master,$shift);
    if($data['remaining_qty']<=0){om_json(['success'=>true,'mode'=>'readonly','title'=>'Produksi Complete','message'=>om_qty_notice($data),'data'=>$data]);}
    $owned=$shift && trim((string)$shift['operator'])===om_user();
    $isDraft=!empty($header['status_draft']);
    $title=$isDraft?'Lengkapi Output Manual':($owned?'Lanjutkan Produksi':'Oper Shift');
    $note=$isDraft?($created?' | Start otomatis tercatat. Draft sudah masuk ke list.':' | Draft ditemukan. Start tetap memakai scan pertama.'):($owned?' | Anda operator aktif.':' | Operator aktif: '.($shift['operator'] ?? '-'));
    om_json(['success'=>true,'mode'=>$owned?'edit':'handover','title'=>$title,'message'=>om_qty_notice($data).$note,'data'=>$data]);
}

if($action==='row'){
    $header=om_row((int)($_GET['id'] ?? 0)); if(!$header) om_json(['success'=>false,'message'=>'Data tidak ditemukan.']);
    $master=om_master(trim((string)$header['prdnmbr'])); if(!$master) om_json(['success'=>false,'message'=>'Master belum tersedia.']);
    $shift=om_active_shift((int)$header['id']); $data=om_payload($header,$master,$shift); $owned=$shift && trim((string)$shift['operator'])===om_user();
    $mode=$data['remaining_qty']<=0?'readonly':($owned?'edit':'handover');$isDraft=!empty($header['status_draft']);
    $title=$mode==='readonly'?'Produksi Complete':($isDraft?'Lengkapi Output Manual':($owned?'Edit Output Manual':'Lihat / Oper Shift'));
    $note=$isDraft?' | Draft scan. Lengkapi Mesin dan hasil produksi lalu Save.':($owned?' | Anda operator aktif.':' | Operator aktif: '.($shift['operator'] ?? '-'));
    om_json(['success'=>true,'mode'=>$mode,'title'=>$title,'message'=>om_qty_notice($data).$note,'data'=>$data]);
}

if($action==='handover'){
    $id=(int)($_POST['id'] ?? 0);$version=trim((string)($_POST['row_version'] ?? ''));
    if(!sqlsrv_begin_transaction($conn)) om_json(['success'=>false,'message'=>'Gagal memulai oper shift.']);
    try{
        $header=om_row($id);$master=$header?om_master($header['prdnmbr']):null;if(!$header||!$master)throw new RuntimeException('Data tidak ditemukan.');
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_shift WITH (UPDLOCK,HOLDLOCK) WHERE manual_output_id=? AND status_active=1",[$id]);$old=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;
        if(!$old||$version===''||!hash_equals(bin2hex($old['row_version']),$version))throw new RuntimeException('Data sudah berubah. Buka ulang modal.');
        $total=om_shift_total($id);$remaining=max(0,(float)$master['prdqty']-$total);if($remaining<=0)throw new RuntimeException('Produksi sudah complete.');
        if(trim((string)$old['operator'])!==om_user()){
            if(sqlsrv_query($conn,"UPDATE manual_output_shift SET status_active=0,finish_time=COALESCE(finish_time,GETDATE()),updated_at=GETDATE() WHERE id=?",[$old['id']])===false)throw new RuntimeException('Gagal menutup shift lama.');
            $stmt=sqlsrv_query($conn,"INSERT INTO manual_output_shift (manual_output_id,prdnmbr,operator,meter,gol,grey,tengah,ket,start_time,status_active,handover_from,mesin_id,created_at) OUTPUT INSERTED.id VALUES (?,?,?,0,?,?,?,?,GETDATE(),1,?,?,GETDATE())",[$id,$header['prdnmbr'],om_user(),$old['gol'],$old['grey'],$old['tengah'],$old['ket'],$old['operator'],$old['mesin_id']]);if($stmt===false)throw new RuntimeException('Gagal membuat shift baru.');
            if(sqlsrv_query($conn,"UPDATE manual_output SET current_operator=?,op_mesin=?,handover_by=?,handover_at=GETDATE(),finish_time=NULL,upd_at=GETDATE() WHERE id=?",[om_user(),om_user(),$old['operator'],$id])===false)throw new RuntimeException('Gagal update operator.');
            if(!om_history($header['prdnmbr'],$id,$total,0,$total,'HANDOVER',$old['operator'].' -> '.om_user()))throw new RuntimeException('Gagal menulis history.');
        }
        sqlsrv_commit($conn);om_json(['success'=>true,'message'=>'Oper shift berhasil. Meter otomatis menampilkan sisa '.number_format($remaining,4).' METER dan belum dihitung sampai Save.']);
    }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

if($action==='save'){
    $id=(int)($_POST['id'] ?? 0);$scan=trim((string)($_POST['prdnmbr'] ?? ''));$meterText=trim((string)($_POST['meter'] ?? ''));
    $mesinId=(int)($_POST['mesin_id'] ?? 0);$startText=trim((string)($_POST['start_time'] ?? ''));
    $start=DateTime::createFromFormat('Y-m-d H:i:s',$startText);$startValid=$start && $start->format('Y-m-d H:i:s')===$startText;
    if(!in_array(strlen($scan),[15,16],true)||!is_numeric($meterText)||(float)$meterText<0||$mesinId<=0||($id<=0&&!$startValid))om_json(['success'=>false,'message'=>'PRDNMBR, Mesin, Meter, atau waktu Start tidak valid.']);
    $meter=round((float)$meterText,4);if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai transaksi.']);
    try{
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_master WITH (UPDLOCK,HOLDLOCK) WHERE prdnmbr=?",[$scan]);$master=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$master)throw new RuntimeException('Master tidak ditemukan.');
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 id FROM manual_output_master_mesin WITH (UPDLOCK,HOLDLOCK) WHERE id=? AND status_active=1",[$mesinId]);if(!$stmt||!sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))throw new RuntimeException('Mesin tidak tersedia atau sudah nonaktif.');
        $input=[trim((string)($_POST['gol']??'')),trim((string)($_POST['grey']??'')),trim((string)($_POST['tengah']??'')),trim((string)($_POST['ket']??''))];
        if($id<=0){
            $stmt=sqlsrv_query($conn,"SELECT TOP 1 id FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE prdnmbr=? AND meter IS NOT NULL",[$scan]);if($stmt&&sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))throw new RuntimeException('PRDNMBR sudah memiliki data. Scan ulang.');
            if($meter>(float)$master['prdqty']+0.00001)throw new RuntimeException('Meter melebihi Qty Pemartaian.');
            $sql="INSERT INTO manual_output (prdnmbr,prodid,iso,partai,cuscolor,meter,gol,grey,tengah,start_time,finish_time,stdcutfg,prdstduomid,op_mesin,labeljual,ket,created_at,created_by,current_operator,status_draft,mesin_id) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,GETDATE(),?,?,?,?,?,GETDATE(),?,?,0,?)";
            $stmt=sqlsrv_query($conn,$sql,[$scan,$master['prodid'],substr($scan,0,8),substr($scan,8,8),$master['cuscolor'],$meter,$input[0]?:null,$input[1]?:null,$input[2]?:null,$startText,$master['stdcutfg'],$master['r_prdstduomid'],om_user(),$master['labeljual'],$input[3],om_user(),om_user(),$mesinId]);if($stmt===false)throw new RuntimeException('Gagal membuat header.');$new=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC);$id=(int)$new['id'];
            if(sqlsrv_query($conn,"INSERT INTO manual_output_shift (manual_output_id,prdnmbr,operator,meter,gol,grey,tengah,ket,start_time,finish_time,status_active,mesin_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,GETDATE(),1,?,GETDATE())",[$id,$scan,om_user(),$meter,$input[0]?:null,$input[1]?:null,$input[2]?:null,$input[3],$startText,$mesinId])===false)throw new RuntimeException('Gagal membuat detail shift.');$before=0;$actionType='SAVE_CREATE';
        }else{
            $header=om_row($id);if(!$header)throw new RuntimeException('Header tidak ditemukan.');
            $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_shift WITH (UPDLOCK,HOLDLOCK) WHERE manual_output_id=? AND status_active=1 AND operator=?",[$id,om_user()]);$shift=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$shift)throw new RuntimeException('Ambil alih shift sebelum mengedit.');
            $other=om_shift_total($id,(int)$shift['id']);$available=(float)$master['prdqty']-$other;if($meter>$available+0.00001)throw new RuntimeException('Meter shift melebihi sisa '.number_format(max(0,$available),4).'.');
            $before=(float)$shift['meter'];if(sqlsrv_query($conn,"UPDATE manual_output_shift SET meter=?,gol=?,grey=?,tengah=?,ket=?,mesin_id=?,finish_time=GETDATE(),updated_at=GETDATE() WHERE id=?",[$meter,$input[0]?:null,$input[1]?:null,$input[2]?:null,$input[3],$mesinId,$shift['id']])===false)throw new RuntimeException('Gagal update detail shift.');$actionType='SAVE_EDIT';
        }
        $total=om_shift_total($id);if(sqlsrv_query($conn,"UPDATE manual_output SET meter=?,gol=?,grey=?,tengah=?,ket=?,mesin_id=?,status_draft=0,finish_time=GETDATE(),upd_at=GETDATE() WHERE id=?",[$total,$input[0]?:null,$input[1]?:null,$input[2]?:null,$input[3],$mesinId,$id])===false)throw new RuntimeException('Gagal sinkron total header.');
        $complete=$total>=(float)$master['prdqty']-0.00001;
        if($complete&&sqlsrv_query($conn,"UPDATE manual_output_shift SET status_active=0,finish_time=COALESCE(finish_time,GETDATE()),updated_at=GETDATE() WHERE manual_output_id=? AND status_active=1",[$id])===false)throw new RuntimeException('Gagal menyelesaikan seluruh shift.');
        if(!om_history($scan,$id,$before,$meter-$before,$total,$actionType,'Shift '.om_user()))throw new RuntimeException('Gagal menulis history.');sqlsrv_commit($conn);om_json(['success'=>true,'message'=>$complete?'Data tersimpan. Qty complete. Semua shift selesai.':'Data shift tersimpan. Sisa '.number_format((float)$master['prdqty']-$total,4).' METER.']);
    }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

if($action==='delete'){
    $id=(int)($_POST['id']??0);if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai transaksi.']);
    try{$stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE id=? AND current_operator=?",[$id,om_user()]);$header=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$header)throw new RuntimeException('Data bukan milik operator aktif.');$total=om_shift_total($id);if(!om_history($header['prdnmbr'],$id,$total,-$total,0,'DELETE','Hapus seluruh shift'))throw new RuntimeException('Gagal menulis history.');if(sqlsrv_query($conn,"DELETE FROM manual_output_shift WHERE manual_output_id=?",[$id])===false||sqlsrv_query($conn,"DELETE FROM manual_output WHERE id=?",[$id])===false)throw new RuntimeException('Gagal menghapus data.');sqlsrv_commit($conn);om_json(['success'=>true,'message'=>'Header dan seluruh detail shift terhapus.']);}catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

$dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: date('Y-m-01');
$dateTo = trim((string)($_GET['date_to'] ?? '')) ?: date('Y-m-d');
$q = trim((string)($_GET['q'] ?? ''));
$allowedThemes = ['primary','secondary','success','danger','warning','info','light','dark','navy','olive','lime','fuchsia','maroon','blue','indigo','purple','pink','red','orange','yellow','green','teal','cyan','white','gray','gray-dark'];
$userTheme = strtolower((string)($_SESSION['Theme'] ?? 'primary'));
if (!in_array($userTheme, $allowedThemes, true)) $userTheme = 'primary';
$themeTextClass = in_array($userTheme, ['warning','light','lime','yellow','white'], true) ? 'text-dark' : 'text-white';
$rows = [];
$sql = "SELECT o.id,o.prdnmbr,o.iso,o.partai,o.cuscolor,o.meter,o.gol,o.grey,o.tengah,
        COALESCE(times.first_start,o.start_time) start_time,
        CASE WHEN o.status_draft=1 THEN NULL WHEN m.prdqty IS NOT NULL AND o.meter>=m.prdqty-0.00001 THEN COALESCE(times.last_finish,o.finish_time) END finish_time,
        o.stdcutfg,o.op_mesin,mm.kode_mesin,mm.nama_mesin,o.labeljual,o.ket,o.created_at,o.created_by,o.status_draft,o.current_operator
    FROM manual_output o
    LEFT JOIN manual_output_master m ON m.prdnmbr=o.prdnmbr
    LEFT JOIN manual_output_master_mesin mm ON mm.id=o.mesin_id
    OUTER APPLY (SELECT
        (SELECT TOP 1 s1.start_time FROM manual_output_shift s1 WHERE s1.manual_output_id=o.id ORDER BY s1.id ASC) first_start,
        (SELECT TOP 1 s2.finish_time FROM manual_output_shift s2 WHERE s2.manual_output_id=o.id ORDER BY s2.id DESC) last_finish
    ) times
    WHERE CAST(o.created_at AS date) BETWEEN ? AND ? AND (?='' OR o.prdnmbr LIKE ? OR o.cuscolor LIKE ? OR o.labeljual LIKE ? OR o.op_mesin LIKE ? OR o.ket LIKE ? OR mm.kode_mesin LIKE ? OR mm.nama_mesin LIKE ?) ORDER BY o.id DESC";
$like = '%' . $q . '%';
$stmt = sqlsrv_query($conn, $sql, [$dateFrom,$dateTo,$q,$like,$like,$like,$like,$like,$like,$like]);
if ($stmt !== false) { while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $row; sqlsrv_free_stmt($stmt); }
$tableRowsHtml=om_table_rows_html($rows,$userTheme);$tableSignature=sha1($tableRowsHtml);
if($action==='table_rows')om_json(['success'=>true,'signature'=>$tableSignature,'html'=>$tableRowsHtml]);
$machines=[];$machineStmt=sqlsrv_query($conn,"SELECT id,kode_mesin,nama_mesin,status_active FROM manual_output_master_mesin ORDER BY kode_mesin");
if($machineStmt)while($machine=sqlsrv_fetch_array($machineStmt,SQLSRV_FETCH_ASSOC))$machines[]=$machine;
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
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
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
        #outputTable thead th:first-child{width:54px!important;min-width:54px;text-align:center;padding-left:8px!important;padding-right:8px!important;background-image:none!important}
        #outputTable tbody td:first-child{width:54px!important;min-width:54px;text-align:center;padding-left:28px!important;padding-right:6px!important}
        #modalNotice{padding:14px 16px;border:0;border-radius:10px;background:linear-gradient(135deg,#e8f8fb,#f3fbfd);color:#173b45;box-shadow:inset 0 0 0 1px rgba(23,162,184,.16)}
        .qty-summary{display:grid;grid-template-columns:repeat(5,minmax(125px,1fr));gap:9px}.qty-metric{padding:10px 12px;background:#fff;border:1px solid #d9edf2;border-radius:8px}.qty-metric span{display:block;color:#63808a;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}.qty-metric strong{display:block;margin-top:3px;color:#123b46;font-size:1rem;white-space:nowrap}.qty-metric.remaining{background:#fff8e7;border-color:#f4d88a}.qty-status{margin-top:10px;font-size:.86rem;color:#47636b}@media(max-width:767px){.qty-summary{grid-template-columns:repeat(2,1fr)}}
        .scan-loading{position:fixed;inset:0;z-index:2060;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(7,20,35,.58);backdrop-filter:blur(5px);opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease}.scan-loading.show{opacity:1;visibility:visible}.scan-loading-card{min-width:290px;max-width:390px;padding:24px;border:1px solid rgba(255,255,255,.2);border-radius:18px;background:linear-gradient(145deg,rgba(255,255,255,.98),rgba(235,248,251,.96));box-shadow:0 24px 70px rgba(0,0,0,.28);text-align:center;transform:translateY(10px) scale(.97);transition:transform .25s ease}.scan-loading.show .scan-loading-card{transform:none}.scan-loader{position:relative;width:62px;height:62px;margin:0 auto 16px}.scan-loader:before,.scan-loader:after{content:"";position:absolute;border-radius:50%;inset:0}.scan-loader:before{border:5px solid #d9eef2;border-top-color:#087f8c;animation:scan-spin .8s linear infinite}.scan-loader:after{inset:14px;border:4px solid transparent;border-right-color:#0f4c81;animation:scan-spin .65s linear infinite reverse}.scan-loading-title{display:block;color:#123b46;font-size:1.05rem}.scan-loading-text{margin:5px 0 0;color:#62808a;font-size:.84rem}.scan-loading-dots:after{content:"";animation:scan-dots 1.4s steps(4,end) infinite}@keyframes scan-spin{to{transform:rotate(360deg)}}@keyframes scan-dots{0%{content:""}25%{content:"."}50%{content:".."}75%,100%{content:"..."}}@media(prefers-reduced-motion:reduce){.scan-loader:before,.scan-loader:after,.scan-loading-dots:after{animation:none}.scan-loading,.scan-loading-card{transition:none}}
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div id="scanLoading" class="scan-loading" role="status" aria-live="polite" aria-hidden="true">
    <div class="scan-loading-card">
        <div class="scan-loader" aria-hidden="true"></div>
        <strong class="scan-loading-title">Mengambil data produksi<span class="scan-loading-dots"></span></strong>
        <p class="scan-loading-text">Mohon tunggu. Data baru sedang dibaca dari ERP.</p>
    </div>
</div>
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
                    <a class="btn btn-success btn-sm mb-2 mb-md-0 mr-2" id="btnExportExcel" target="_blank" href="export_manual_output.php?q=<?= urlencode($q) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><i class="fas fa-file-excel"></i> Export Excel</a>
                    <a class="btn btn-danger btn-sm mb-2 mb-md-0" id="btnExportPdf" target="_blank" href="export_manual_output_pdf.php?q=<?= urlencode($q) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><i class="fas fa-file-pdf"></i> Export PDF</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="outputTable" class="table table-hover table-sm nowrap output-table" data-signature="<?= $tableSignature ?>" style="width:100%">
                        <thead class="thead-light"><tr><th>No</th><th>PRDNMBR</th><th>ISO</th><th>Partai</th><th>Warna</th><th>Meter</th><th>Gol</th><th>Grey</th><th>Tengah</th><th>Start</th><th>Finish</th><th>Status</th><th>STD Potong</th><th>OP Mesin</th><th>Mesin</th><th>L Jual</th><th>Ket</th><th>Created By</th><th>Aksi</th></tr></thead>
                        <tbody><?= $tableRowsHtml ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div></section></div>
</div>
<div class="modal fade" id="outputModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($userTheme) ?> <?= $themeTextClass ?>"><h5 class="modal-title" id="modalTitle">Output Manual</h5><button type="button" class="close <?= $themeTextClass ?>" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body"><div id="modalNotice" class="alert alert-info d-none"></div><form id="outputForm"><input type="hidden" name="prdnmbr" id="prdnmbr" value=""><input type="hidden" name="id" id="row_id"><input type="hidden" id="row_version"><ul class="nav nav-tabs mb-3" id="outputManualTab" role="tablist">
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
                            <div class="table-responsive mt-2"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Operator</th><th>Mesin</th><th>Meter</th><th>Start</th><th>Finish</th><th>Status</th></tr></thead><tbody id="shiftHistoryBody"><tr><td colspan="6" class="text-center text-muted">Belum ada riwayat shift</td></tr></tbody></table></div>
                        </div>
                        <div class="tab-pane fade" id="output-input" role="tabpanel" aria-labelledby="output-input-tab">
                            <div class="form-row">
                                <div class="form-group col-12"><label for="mesin_id">Mesin</label><select id="mesin_id" name="mesin_id" class="form-control" required><option value="">Pilih mesin</option><?php foreach($machines as $machine): ?><option value="<?= (int)$machine['id'] ?>" <?= $machine['status_active']?'':'disabled' ?>><?= htmlspecialchars($machine['kode_mesin'].(!empty($machine['nama_mesin'])?' — '.$machine['nama_mesin']:'')) ?><?= $machine['status_active']?'':' (Nonaktif)' ?></option><?php endforeach; ?></select></div>
                                <fieldset class="col-md-6 mb-3">
                                    <legend class="h6 font-weight-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-ruler-combined mr-1"></i> Quantity</legend>
                                    <div class="form-row">
                                        <div class="form-group col-sm-6 mb-sm-0"><label for="meter">Meter</label><input type="number" step="0.01" id="meter" name="meter" class="form-control"></div>
                                        <div class="form-group col-sm-6 mb-0"><label for="gol">Gol</label><input type="number" step="0.01" id="gol" name="gol" class="form-control"></div>
                                    </div>
                                </fieldset>
                                <fieldset class="col-md-6 mb-3">
                                    <legend class="h6 font-weight-bold text-info border-bottom pb-2 mb-3"><i class="fas fa-link mr-1"></i> Sambungan</legend>
                                    <div class="form-row">
                                        <div class="form-group col-sm-6 mb-sm-0"><label for="grey">Grey</label><input type="number" step="0.01" id="grey" name="grey" class="form-control"></div>
                                        <div class="form-group col-sm-6 mb-0"><label for="tengah">Tengah</label><input type="number" step="0.01" id="tengah" name="tengah" class="form-control"></div>
                                    </div>
                                </fieldset>
                                <div class="form-group col-12"><label for="ket">Keterangan</label><input type="text" id="ket" name="ket" class="form-control"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="btnHandover" style="display:none"><i class="fas fa-exchange-alt mr-1"></i>Ambil Alih Shift</button>
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
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script>
(function () {
    const scanInput = document.getElementById('scan_input');
    const searchText = document.getElementById('search_text');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    const btnSearch = document.getElementById('btnSearch');
    const btnExportExcel = document.getElementById('btnExportExcel');
    const btnExportPdf = document.getElementById('btnExportPdf');
    const btnSave = document.getElementById('btnSave');
    const btnHandover = document.getElementById('btnHandover');
    const modal = $('#outputModal');
    const machineSelect = $('#mesin_id');
    const notice = document.getElementById('modalNotice');
    machineSelect.select2({theme:'bootstrap4',placeholder:'Pilih mesin',allowClear:true,width:'100%',dropdownParent:modal});

    const outputTableElement = document.getElementById('outputTable');
    const outputTable = $('#outputTable').DataTable({
        order: [],
        responsive: {details: {type: 'column', target: 0}},
        columnDefs: [
            {className: 'dtr-control', orderable: false, responsivePriority: 1, targets: 0},
            {responsivePriority: 2, targets: 1},
            {responsivePriority: 2, targets: 11},
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
    let tableSignature = outputTableElement.dataset.signature || '';
    let tableRefreshBusy = false;

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, options);
        const text = await res.text();
        try { return JSON.parse(text); } catch (error) { return {success: false, message: text || 'Response bukan JSON'}; }
    }

    function toast(msg, icon = 'info') {
        if (window.Swal) {
            Swal.fire({icon, title: msg, timer: 2200, showConfirmButton: false});
            return;
        }
        alert(msg);
    }

    async function refreshOutputTable() {
        if (tableRefreshBusy) return;
        tableRefreshBusy = true;
        try {
            const params = new URLSearchParams({action:'table_rows',q:searchText.value.trim(),date_from:dateFrom.value,date_to:dateTo.value});
            const json = await fetchJson(`output_manual.php?${params.toString()}`, {credentials:'same-origin',cache:'no-store'});
            if (!json.success || json.signature === tableSignature) return;
            const rows = $('<tbody>').html(json.html || '').children('tr').toArray();
            outputTable.clear();
            if (rows.length) outputTable.rows.add(rows);
            outputTable.draw(false).responsive.recalc();
            tableSignature = json.signature || '';
        } finally {
            tableRefreshBusy = false;
        }
    }

    function setReadonly(flag, handover = false) {
        ['meter','gol','grey','tengah','ket'].forEach(id => {
            document.getElementById(id).readOnly = flag;
        });
        machineSelect.prop('disabled',flag).trigger('change.select2');
        btnSave.style.display = flag ? 'none' : 'inline-block';
        btnHandover.style.display = handover ? 'inline-block' : 'none';
    }

    function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
    function localTimestamp() {
        const d = new Date(), pad = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }
    function fillModal(data, title, message, mode) {
        document.getElementById('modalTitle').textContent = title || 'Output Manual';
        notice.classList.remove('d-none');
        const metrics = [['Qty Pemartaian',data.prdqty],['Shift Sebelumnya',data.previous_qty],['Meter Shift Ini',data.meter_actual],['Total Terinput',data.used_qty],['Sisa Qty',data.remaining_qty]];
        const statusText = (message || '').split(' | ').slice(-1)[0];
        const statusHtml = mode === 'readonly' ? '' : `<div class="qty-status"><i class="fas fa-info-circle mr-1"></i>${escapeHtml(statusText)}</div>`;
        notice.innerHTML = `<div class="qty-summary">${metrics.map((item,i)=>`<div class="qty-metric ${i===4?'remaining':''}"><span>${item[0]}</span><strong>${Number(item[1] || 0).toFixed(4)} <small>M</small></strong></div>`).join('')}</div>${statusHtml}`;
        document.getElementById('row_id').value = data.id || '';
        ['prdnmbr','iso','partai','cuscolor','meter','gol','grey','tengah','stdcutfg','op_mesin','labeljual','ket'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = data[id] ?? '';
        });
        document.getElementById('meter').max = data.available_qty ?? '';
        document.getElementById('meter').step = '0.0001';
        document.getElementById('row_version').value = data.row_version || '';
        machineSelect.val(data.mesin_id ? String(data.mesin_id) : '').trigger('change');
        const startValue = data.id ? (data.start_time ? data.start_time.replace('T',' ') : '') : localTimestamp();
        document.getElementById('start_time').value = startValue;
        document.getElementById('finish_time').value = data.finish_time ? data.finish_time.replace('T',' ') : '';
        const historyBody = document.getElementById('shiftHistoryBody');
        const shifts = Array.isArray(data.shifts) ? data.shifts : [];
        historyBody.innerHTML = shifts.length ? shifts.map(shift => `<tr><td>${escapeHtml(shift.operator || '')}</td><td>${escapeHtml(shift.mesin || '-')}</td><td>${Number(shift.meter || 0).toFixed(4)}</td><td>${escapeHtml(shift.start_time || '')}</td><td>${escapeHtml(shift.finish_time || '-')}</td><td>${shift.active ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Selesai</span>'}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted">Belum ada riwayat shift</td></tr>';
        setReadonly(mode === 'readonly' || mode === 'readonly_other' || mode === 'handover', mode === 'handover');
        modal.modal('show');
    }

    let lastLookup = '';
    let lookupBusy = false;

    modal.on('hidden.bs.modal', function () {
        lastLookup = '';
        scanInput.value = '';
        scanInput.focus();
    });

    function setScanLoading(show) {
        const loading = document.getElementById('scanLoading');
        loading.classList.toggle('show', show);
        loading.setAttribute('aria-hidden', show ? 'false' : 'true');
        scanInput.disabled = show;
        scanInput.setAttribute('aria-busy', show ? 'true' : 'false');
    }

    async function lookup(scan) {
        if (lookupBusy || scan === lastLookup) return;
        lookupBusy = true;
        lastLookup = scan;
        setScanLoading(true);
        try {
            const json = await fetchJson(`output_manual.php?action=lookup&scan=${encodeURIComponent(scan)}`, {credentials: 'same-origin'});
            if (!json.success) {
                lastLookup = '';
                toast(json.message || 'Gagal lookup', 'error');
                return;
            }
            fillModal(json.data || {}, json.title || 'Output Manual', json.message || '', json.mode || 'edit');
            refreshOutputTable();
        } catch (error) {
            lastLookup = '';
            toast('Gagal memproses hasil scan.', 'error');
        } finally {
            lookupBusy = false;
            setScanLoading(false);
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

    $('#outputTable').on('click', '.btnEditRow', async function () {
        const id = Number($(this).data('id') || 0);
        const json = await fetchJson(`output_manual.php?action=row&id=${id}`, {credentials: 'same-origin'});
        if (json.success) fillModal(json.data || {}, json.title, json.message, json.mode);
        else toast(json.message || 'Gagal membuka data', 'error');
    });

    $('#outputTable').on('click', '.btnDeleteRow', async function () {
        const id = Number($(this).data('id') || 0);
        const result = await Swal.fire({icon: 'warning', title: 'Hapus data?', text: 'Qty Meter akan tersedia kembali.', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal'});
        if (!result.isConfirmed) return;
        const form = new FormData(); form.append('action', 'delete'); form.append('id', id);
        const json = await fetchJson('output_manual.php', {method: 'POST', body: form, credentials: 'same-origin'});
        if (json.success) { toast(json.message, 'success'); await refreshOutputTable(); }
        else toast(json.message || 'Gagal menghapus data', 'error');
    });

    btnHandover.addEventListener('click', async function () {
        const result = await Swal.fire({icon: 'question', title: 'Ambil alih shift?', text: 'Hak edit berpindah ke user Anda.', showCancelButton: true, confirmButtonText: 'Ambil Alih', cancelButtonText: 'Batal'});
        if (!result.isConfirmed) return;
        const form = new FormData(); form.append('action', 'handover');
        form.append('id', document.getElementById('row_id').value);
        form.append('row_version', document.getElementById('row_version').value);
        const json = await fetchJson('output_manual.php', {method: 'POST', body: form, credentials: 'same-origin'});
        if (json.success) { toast(json.message, 'success'); const id = document.getElementById('row_id').value; const fresh = await fetchJson(`output_manual.php?action=row&id=${id}`); if (fresh.success) fillModal(fresh.data, fresh.title, fresh.message, fresh.mode); await refreshOutputTable(); }
        else toast(json.message || 'Oper shift gagal', 'error');
    });

    btnSave.addEventListener('click', async function () {
        const form = new FormData();
        form.append('action', 'save');
        form.append('id', document.getElementById('row_id').value);
        form.append('prdnmbr', document.getElementById('prdnmbr').value);
        ['mesin_id','meter','gol','grey','tengah','ket'].forEach(id => form.append(id, document.getElementById(id).value));
        form.append('start_time', document.getElementById('start_time').value);
        const json = await fetchJson('output_manual.php', {method: 'POST', body: form, credentials: 'same-origin'});
        if (json.success) {
            toast(json.message || 'Data tersimpan', 'success');
            modal.modal('hide');
            await refreshOutputTable();
        } else toast(json.message || 'Gagal menyimpan data', 'error');
    });

    btnSearch.addEventListener('click', function () {
        window.location = `output_manual.php?${buildFilterParams().toString()}`;
    });

    btnExportExcel.addEventListener('click', function () {
        this.href = `export_manual_output.php?${buildFilterParams().toString()}`;
    });

    btnExportPdf.addEventListener('click', function () {
        this.href = `export_manual_output_pdf.php?${buildFilterParams().toString()}`;
    });

    [searchText, dateFrom, dateTo].forEach(el => el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); btnSearch.click(); }
    }));

    setInterval(() => { if (!document.hidden) refreshOutputTable(); }, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshOutputTable(); });
})();
</script>
</body>
</html>


