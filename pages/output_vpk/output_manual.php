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

function om_is_admin(): bool
{
    return (int)($_SESSION['GroupId'] ?? 0) === 1;
}

function om_csrf_token(): string
{
    if (empty($_SESSION['output_manual_csrf'])) {
        $_SESSION['output_manual_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['output_manual_csrf'];
}

function om_require_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(om_csrf_token(), $token)) {
        om_json(['success' => false, 'message' => 'Halaman sudah berubah. Muat ulang sebelum menyimpan.']);
    }
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
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM manual_output_shift WHERE manual_output_id=? AND status_active=1 AND is_cancelled=0 ORDER BY id DESC", [$headerId]);
    return $stmt ? (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null) : null;
}

function om_shift_total(int $headerId, int $excludeShiftId = 0): float
{
    global $conn;
    $sql = "SELECT COALESCE(SUM(meter),0) total FROM manual_output_shift WHERE manual_output_id=? AND is_correction_pending=0 AND is_cancelled=0" . ($excludeShiftId ? " AND id<>?" : "");
    $stmt = sqlsrv_query($conn, $sql, $excludeShiftId ? [$headerId,$excludeShiftId] : [$headerId]);
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    return (float)($row['total'] ?? 0);
}

function om_shift_history(int $headerId): array
{
    global $conn;
    $rows=[];    $stmt=sqlsrv_query($conn,"SELECT s.id,COALESCE(NULLIF(LTRIM(RTRIM(e.nama_lengkap)),''),s.operator) operator,s.meter,s.gol,s.grey,s.tengah,s.ket,s.start_time,CASE WHEN s.finish_time IS NULL AND s.handover_from IS NULL THEN o.finish_time ELSE s.finish_time END finish_time,s.status_active,s.is_correction_pending,s.is_cancelled,s.handover_from,m.kode_mesin FROM manual_output_shift s JOIN manual_output o ON o.id=s.manual_output_id LEFT JOIN manual_output_master_mesin m ON m.id=s.mesin_id LEFT JOIN SMUserMs su ON su.UserName=s.operator LEFT JOIN m_emp e ON e.id_emp=su.EmpId WHERE s.manual_output_id=? ORDER BY s.id",[$headerId]);
    if($stmt) while($row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $rows[]=[
        'id'=>(int)$row['id'],'operator'=>$row['operator'],'mesin'=>$row['kode_mesin'] ?? '-','meter'=>(float)$row['meter'],'gol'=>$row['gol'] ?? null,'grey'=>$row['grey'] ?? null,'tengah'=>$row['tengah'] ?? null,'ket'=>$row['ket'] ?? '',
        'start_time'=>om_display_dt($row['start_time']),'finish_time'=>om_display_dt($row['finish_time']),
        'active'=>(bool)$row['status_active'],'pending'=>(bool)$row['is_correction_pending'],'cancelled'=>(bool)$row['is_cancelled'],'handover_from'=>$row['handover_from'] ?? ''
    ];
    return $rows;
}

function om_operator_name(string $username): string
{
    global $conn;
    $username = trim($username);
    if ($username === '') return '';
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 NULLIF(LTRIM(RTRIM(e.nama_lengkap)), '') nama_lengkap FROM SMUserMs su LEFT JOIN m_emp e ON e.id_emp=su.EmpId WHERE su.UserName=?", [$username]);
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    if ($stmt) sqlsrv_free_stmt($stmt);
    return trim((string)($row['nama_lengkap'] ?? '')) ?: $username;
}

function om_shift_operators(int $headerId): string
{
    global $conn;
    $stmt = sqlsrv_query($conn, "SELECT COALESCE(NULLIF(LTRIM(RTRIM(e.nama_lengkap)),''),s.operator) operator FROM manual_output_shift s LEFT JOIN SMUserMs su ON su.UserName=s.operator LEFT JOIN m_emp e ON e.id_emp=su.EmpId WHERE s.manual_output_id=? ORDER BY s.id", [$headerId]);
    if ($stmt === false) return '';
    $operators = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $operator = trim((string)($row['operator'] ?? ''));
        if ($operator !== '' && !in_array($operator, $operators, true)) $operators[] = $operator;
    }
    sqlsrv_free_stmt($stmt);
    return implode(', ', $operators);
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
    $countedMeter=($shift && !empty($shift['is_correction_pending'])) ? 0.0 : $actualMeter;
    $total=$other+$countedMeter; $complete=$headerId>0 && $total>=(float)$master['prdqty']-0.00001;
    $range=$complete ? om_shift_range($headerId) : [];
    $infoStart=$complete ? ($range['first_start'] ?? '') : ($shift['start_time'] ?? date('Y-m-d H:i:s'));
    $infoFinish=$complete ? ($range['last_finish'] ?? '') : ($shift['finish_time'] ?? (($shift && empty($shift['handover_from'])) ? ($header['finish_time'] ?? '') : ''));
    $operators = $complete ? om_shift_operators($headerId) : om_operator_name((string)($shift['operator'] ?? om_user()));
    return [
        'id'=>$headerId,'shift_id'=>$shiftId,'prdnmbr'=>$scan,'iso'=>$header['iso'] ?? substr($scan,0,8),'partai'=>$header['partai'] ?? substr($scan,8,8),
        'cuscolor'=>$header['cuscolor'] ?? $master['cuscolor'] ?? '','meter'=>$formMeter,'gol'=>$shift['gol'] ?? '','grey'=>$shift['grey'] ?? '',
        'tengah'=>$shift['tengah'] ?? '','ket'=>$shift['ket'] ?? '','start_time'=>om_dt($infoStart),
        'finish_time'=>om_dt($infoFinish),'stdcutfg'=>$header['stdcutfg'] ?? $master['stdcutfg'] ?? '',
        'op_mesin'=>$operators,'labeljual'=>$header['labeljual'] ?? $master['labeljual'] ?? '',
        'mesin_id'=>(int)($shift['mesin_id'] ?? $header['mesin_id'] ?? 0),'status_draft'=>!empty($header['status_draft']),
        'created_by'=>$header['created_by'] ?? om_user(),'current_operator'=>$shift['operator'] ?? om_user(),
        'row_version'=>isset($shift['row_version']) ? bin2hex($shift['row_version']) : '',
        'can_rollback'=>$complete && !$header['is_cancelled'] && ($shift === null) && (om_is_admin() || trim((string)($header['current_operator'] ?? '')) === om_user()),
        'is_cancelled'=>!empty($header['is_cancelled']),'is_correction_pending'=>!empty($shift['is_correction_pending']),
        'can_cancel_shift'=>$shift && (om_is_admin() || trim((string)$shift['operator'])===om_user()),
        'is_admin'=>om_is_admin(),'can_delete_permanently'=>om_is_admin(),
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
            <td><?= $no++ ?></td><td><?= htmlspecialchars($row['prdnmbr'] ?? '') ?></td><td><?= htmlspecialchars($row['iso'] ?? '') ?></td><td><?= htmlspecialchars($row['partai'] ?? '') ?></td><td><?= htmlspecialchars($row['cuscolor'] ?? '') ?></td><td><?= (float)($row['meter'] ?? 0) == 0.0 ? '0' : htmlspecialchars((string)$row['meter']) ?></td><td><?= htmlspecialchars((string)($row['gol'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['grey'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['tengah'] ?? '')) ?></td><td><?= htmlspecialchars(om_display_dt($row['start_time'] ?? '')) ?></td><td><?= !empty($row['finish_time']) ? htmlspecialchars(om_display_dt($row['finish_time'])) : '-' ?></td><td><?= !empty($row['status_draft']) ? '<span class="badge badge-warning">Belum Berjalan</span>' : (!empty($row['finish_time']) ? '<span class="badge badge-success">Selesai</span>' : '<span class="badge badge-info">Berjalan</span>') ?></td><td><?= htmlspecialchars((string)($row['stdcutfg'] ?? '')) ?></td><td><?= htmlspecialchars($row['op_mesin'] ?? '') ?></td><td><?= htmlspecialchars($row['kode_mesin'] ?? '-') ?></td><td><?= htmlspecialchars($row['labeljual'] ?? '') ?></td><td><?= htmlspecialchars($row['ket'] ?? '') ?></td><td><?= htmlspecialchars($row['created_by'] ?? '') ?></td><td><?php if(!empty($row['finish_time'])): ?><button type="button" class="btn btn-sm btn-info btnEditRow" data-id="<?= (int)$row['id'] ?>" title="Detail"><i class="fas fa-eye"></i></button><?php elseif($owned): ?><button type="button" class="btn btn-sm btn-<?= htmlspecialchars($userTheme) ?> btnEditRow" data-id="<?= (int)$row['id'] ?>" title="Edit data"><i class="fas fa-edit"></i></button><?php else: ?><button type="button" class="btn btn-sm btn-secondary btnEditRow" data-id="<?= (int)$row['id'] ?>" title="Lihat / oper shift"><i class="fas fa-exchange-alt"></i></button><?php endif; ?><?php if(om_is_admin()): ?> <button type="button" class="btn btn-sm btn-danger btnDeletePermanentRow" data-id="<?= (int)$row['id'] ?>" title="Delete permanen" aria-label="Delete permanen"><i class="fas fa-trash" aria-hidden="true"></i></button><?php endif; ?></td>
        </tr><?php
    endforeach;
    return (string)ob_get_clean();
}

/** Writes audit within the caller's transaction; legacy callers leave shift_id null. */
function om_history(string $scan,int $id,float $before,float $change,float $after,string $action,string $note='',?int $shiftId=null): bool
{
    // Legacy connection shared by the caller's transaction.
    global $conn;
    return sqlsrv_query(
        $conn,
        "INSERT INTO manual_output_qty_history
         (prdnmbr,manual_output_id,qty_before,qty_change,qty_after,action_type,created_by,note,shift_id,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,GETDATE())",
        [$scan,$id,$before,$change,$after,$action,om_user(),$note,$shiftId]
    ) !== false;
}

$action=$_GET['action'] ?? $_POST['action'] ?? '';
if($action==='lookup'){
    $scan=trim((string)($_GET['scan'] ?? $_POST['scan'] ?? ''));
    if(!in_array(strlen($scan),[15,16],true)) om_json(['success'=>false,'message'=>'Scan harus 15 atau 16 character.']);
    $scanStartedAt=date('Y-m-d H:i:s');
    [$master,$error]=om_cache_master($scan); if(!$master) om_json(['success'=>false,'message'=>$error ?: 'Master tidak ditemukan.']);
    $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WHERE prdnmbr=? AND meter IS NOT NULL AND is_cancelled=0 ORDER BY id DESC",[$scan]);
    $header=$stmt ? sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC) : null;
    $created=false;
    if(!$header){
        if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai pencatatan scan.']);
        try{
            $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE prdnmbr=? AND meter IS NOT NULL AND is_cancelled=0 ORDER BY id DESC",[$scan]);
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
    $title=$isDraft?'Lengkapi Output Manual':($shift ? ($owned?'Lanjutkan Produksi':'Oper Shift') : 'Oper Shift');
    $note=$isDraft?($created?' | Start otomatis tercatat. Draft sudah masuk ke list.':' | Draft ditemukan. Start tetap memakai scan pertama.'):($shift ? ($owned?' | Anda operator aktif.':' | Operator aktif: '.($shift['operator'] ?? '-')) : ' | Tidak ada shift aktif. Klik Ambil Alih Shift untuk melanjutkan.');
    om_json(['success'=>true,'mode'=>$shift && $owned?'edit':'handover','title'=>$title,'message'=>om_qty_notice($data).$note,'data'=>$data]);
}

if($action==='row'){
    $header=om_row((int)($_GET['id'] ?? 0)); if(!$header) om_json(['success'=>false,'message'=>'Data tidak ditemukan.']);
    $master=om_master(trim((string)$header['prdnmbr'])); if(!$master) om_json(['success'=>false,'message'=>'Master belum tersedia.']);
    $shift=om_active_shift((int)$header['id']); $data=om_payload($header,$master,$shift); $owned=$shift && trim((string)$shift['operator'])===om_user();
    $canCorrect=!empty($shift['is_correction_pending']) && ($owned || om_is_admin());
    $mode=$canCorrect?'edit':($data['remaining_qty']<=0?'readonly':($shift ? ($owned?'edit':'handover') : 'handover'));$isDraft=!empty($header['status_draft']);
    $title=$canCorrect?'Koreksi Shift Rollback':($mode==='readonly'?'Produksi Complete':($isDraft?'Lengkapi Output Manual':($shift ? ($owned?'Edit Output Manual':'Lihat / Oper Shift') : 'Oper Shift')));
    $note=$canCorrect?' | Koreksi shift terakhir. Simpan untuk menyelesaikan ulang produksi.':($isDraft?' | Draft scan. Lengkapi Mesin dan hasil produksi lalu Save.':($shift ? ($owned?' | Anda operator aktif.':' | Operator aktif: '.($shift['operator'] ?? '-')) : ' | Tidak ada shift aktif. Klik Ambil Alih Shift untuk melanjutkan.'));
    om_json(['success'=>true,'mode'=>$mode,'title'=>$title,'message'=>om_qty_notice($data).$note,'data'=>$data]);
}

if($action==='handover'){
    om_require_csrf();
    $id=(int)($_POST['id'] ?? 0);$version=trim((string)($_POST['row_version'] ?? ''));
    if(!sqlsrv_begin_transaction($conn)) om_json(['success'=>false,'message'=>'Gagal memulai oper shift.']);
    try{
        $header=om_row($id);$master=$header?om_master($header['prdnmbr']):null;if(!$header||!$master)throw new RuntimeException('Data tidak ditemukan.');
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_shift WITH (UPDLOCK,HOLDLOCK) WHERE manual_output_id=? AND status_active=1 AND is_cancelled=0 ORDER BY id DESC",[$id]);$old=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;
        $total=om_shift_total($id);$remaining=max(0,(float)$master['prdqty']-$total);if($remaining<=0)throw new RuntimeException('Produksi sudah complete.');
        if($old){
            if($version===''||!hash_equals(bin2hex($old['row_version']),$version))throw new RuntimeException('Data sudah berubah. Buka ulang modal.');
            if(!empty($old['is_correction_pending']))throw new RuntimeException('Oper shift diblokir sampai koreksi shift terakhir disimpan.');
            if(trim((string)$old['operator'])===om_user())throw new RuntimeException('Anda sudah operator shift aktif.');
            if(sqlsrv_query($conn,"UPDATE manual_output_shift SET status_active=0,finish_time=COALESCE(finish_time,GETDATE()),updated_at=GETDATE() WHERE id=?",[$old['id']])===false)throw new RuntimeException('Gagal menutup shift lama.');
            $handoverFrom=(string)$old['operator'];$mesinId=$old['mesin_id'];$gol=$old['gol'];$grey=$old['grey'];$tengah=$old['tengah'];$ket=$old['ket'];
        }else{
            $handoverFrom='';$mesinId=$header['mesin_id'];$gol=$header['gol'];$grey=$header['grey'];$tengah=$header['tengah'];$ket=$header['ket'];
        }
        $stmt=sqlsrv_query($conn,"INSERT INTO manual_output_shift (manual_output_id,prdnmbr,operator,meter,gol,grey,tengah,ket,start_time,status_active,handover_from,mesin_id,created_at) VALUES (?,?,?,0,?,?,?,?,GETDATE(),1,?,?,GETDATE())",[$id,$header['prdnmbr'],om_user(),$gol,$grey,$tengah,$ket,$handoverFrom ?: null,$mesinId]);if($stmt===false)throw new RuntimeException('Gagal membuat shift baru.');
        if(sqlsrv_query($conn,"UPDATE manual_output SET current_operator=?,op_mesin=?,handover_by=?,handover_at=GETDATE(),finish_time=NULL,upd_at=GETDATE() WHERE id=?",[om_user(),om_user(),$handoverFrom ?: null,$id])===false)throw new RuntimeException('Gagal update operator.');
        if(!om_history($header['prdnmbr'],$id,$total,0,$total,'HANDOVER',$handoverFrom ? $handoverFrom.' -> '.om_user() : 'Shift baru: '.om_user()))throw new RuntimeException('Gagal menulis history.');
        sqlsrv_commit($conn);om_json(['success'=>true,'message'=>'Oper shift berhasil. Meter otomatis menampilkan sisa '.number_format($remaining,4).' METER dan belum dihitung sampai Save.']);
    }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

if($action==='save'){
    om_require_csrf();
    $id=(int)($_POST['id'] ?? 0);$scan=trim((string)($_POST['prdnmbr'] ?? ''));$meterText=trim((string)($_POST['meter'] ?? ''));
    $version=trim((string)($_POST['row_version'] ?? ''));
    $mesinId=(int)($_POST['mesin_id'] ?? 0);$startText=trim((string)($_POST['start_time'] ?? ''));
    $start=DateTime::createFromFormat('Y-m-d H:i:s',$startText);$startValid=$start && $start->format('Y-m-d H:i:s')===$startText;
    if(!in_array(strlen($scan),[15,16],true)||!is_numeric($meterText)||(float)$meterText<0||$mesinId<=0||($id<=0&&!$startValid))om_json(['success'=>false,'message'=>'No CP, Mesin, Meter, atau waktu Start tidak valid.']);
    $meter=round((float)$meterText,4);if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai transaksi.']);
    try{
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_master WITH (UPDLOCK,HOLDLOCK) WHERE prdnmbr=?",[$scan]);$master=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$master)throw new RuntimeException('Master tidak ditemukan.');
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 id FROM manual_output_master_mesin WITH (UPDLOCK,HOLDLOCK) WHERE id=? AND status_active=1",[$mesinId]);if(!$stmt||!sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))throw new RuntimeException('Mesin tidak tersedia atau sudah nonaktif.');
        $input=[trim((string)($_POST['gol']??'')),trim((string)($_POST['grey']??'')),trim((string)($_POST['tengah']??'')),trim((string)($_POST['ket']??''))];
        if($id<=0){
            $stmt=sqlsrv_query($conn,"SELECT TOP 1 id FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE prdnmbr=? AND meter IS NOT NULL",[$scan]);if($stmt&&sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))throw new RuntimeException('No CP sudah memiliki data. Scan ulang.');
            if($meter>(float)$master['prdqty']+0.00001)throw new RuntimeException('Meter melebihi Qty Pemartaian.');
            if($meter>=(float)$master['prdqty']-0.00001){foreach(['Gol'=>$input[0],'Grey'=>$input[1],'Tengah'=>$input[2]] as $label=>$value){if($value===''||!is_numeric($value)||(float)$value<0)throw new RuntimeException($label.' wajib terisi saat produksi Complete.');}}
            $sql="INSERT INTO manual_output (prdnmbr,prodid,iso,partai,cuscolor,meter,gol,grey,tengah,start_time,finish_time,stdcutfg,prdstduomid,op_mesin,labeljual,ket,created_at,created_by,current_operator,status_draft,mesin_id) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,GETDATE(),?,?,?,?,?,GETDATE(),?,?,0,?)";
            $stmt=sqlsrv_query($conn,$sql,[$scan,$master['prodid'],substr($scan,0,8),substr($scan,8,8),$master['cuscolor'],$meter,$input[0]?:null,$input[1]?:null,$input[2]?:null,$startText,$master['stdcutfg'],$master['r_prdstduomid'],om_user(),$master['labeljual'],$input[3],om_user(),om_user(),$mesinId]);if($stmt===false)throw new RuntimeException('Gagal membuat header.');$new=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC);$id=(int)$new['id'];
            if(sqlsrv_query($conn,"INSERT INTO manual_output_shift (manual_output_id,prdnmbr,operator,meter,gol,grey,tengah,ket,start_time,finish_time,status_active,mesin_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,GETDATE(),1,?,GETDATE())",[$id,$scan,om_user(),$meter,$input[0]?:null,$input[1]?:null,$input[2]?:null,$input[3],$startText,$mesinId])===false)throw new RuntimeException('Gagal membuat detail shift.');$before=0;$actionType='SAVE_CREATE';
        }else{
            $header=om_row($id);if(!$header)throw new RuntimeException('Header tidak ditemukan.');
            $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_shift WITH (UPDLOCK,HOLDLOCK) WHERE manual_output_id=? AND status_active=1",[$id]);$shift=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;
            if(!$shift || (!om_is_admin() && trim((string)$shift['operator'])!==om_user()))throw new RuntimeException('Ambil alih shift sebelum mengedit.');
            if($version===''||!hash_equals(bin2hex($shift['row_version']),$version))throw new RuntimeException('Data sudah berubah. Buka ulang modal.');
            $other=om_shift_total($id,(int)$shift['id']);$available=(float)$master['prdqty']-$other;if($meter>$available+0.00001)throw new RuntimeException('Meter shift melebihi sisa '.number_format(max(0,$available),4).'.');
            if($other+$meter>=(float)$master['prdqty']-0.00001){foreach(['Gol'=>$input[0],'Grey'=>$input[1],'Tengah'=>$input[2]] as $label=>$value){if($value===''||!is_numeric($value)||(float)$value<0)throw new RuntimeException($label.' wajib terisi saat produksi Complete.');}}
            $before=(float)$shift['meter'];if(sqlsrv_query($conn,"UPDATE manual_output_shift SET meter=?,gol=?,grey=?,tengah=?,ket=?,mesin_id=?,is_correction_pending=0,finish_time=GETDATE(),updated_at=GETDATE() WHERE id=?",[$meter,$input[0]?:null,$input[1]?:null,$input[2]?:null,$input[3],$mesinId,$shift['id']])===false)throw new RuntimeException('Gagal update detail shift.');$actionType='SAVE_EDIT';
        }
        $total=om_shift_total($id);$complete=$total>=(float)$master['prdqty']-0.00001;
        if($complete){foreach(['Gol'=>$input[0],'Grey'=>$input[1],'Tengah'=>$input[2]] as $label=>$value){if($value===''||!is_numeric($value)||(float)$value<0)throw new RuntimeException($label.' wajib terisi saat produksi Complete.');}}
        if(sqlsrv_query($conn,"UPDATE manual_output SET meter=?,gol=?,grey=?,tengah=?,ket=?,mesin_id=?,status_draft=0,finish_time=GETDATE(),upd_at=GETDATE() WHERE id=?",[$total,$input[0]?:null,$input[1]?:null,$input[2]?:null,$input[3],$mesinId,$id])===false)throw new RuntimeException('Gagal sinkron total header.');
        if($complete&&sqlsrv_query($conn,"UPDATE manual_output_shift SET status_active=0,finish_time=COALESCE(finish_time,GETDATE()),updated_at=GETDATE() WHERE manual_output_id=? AND status_active=1",[$id])===false)throw new RuntimeException('Gagal menyelesaikan seluruh shift.');
        if(!om_history($scan,$id,$before,$meter-$before,$total,$actionType,'Shift '.om_user()))throw new RuntimeException('Gagal menulis history.');sqlsrv_commit($conn);om_json(['success'=>true,'message'=>$complete?'Data tersimpan. Qty complete. Semua shift selesai.':'Data shift tersimpan. Sisa '.number_format((float)$master['prdqty']-$total,4).' METER.']);
    }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

if($action==='rollback'){
    om_require_csrf(); $id=(int)($_POST['id']??0); $reason=trim((string)($_POST['reason']??''));
    if($id<=0||$reason==='')om_json(['success'=>false,'message'=>'Alasan rollback wajib diisi.']);
    if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai transaksi.']);
    try{
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE id=? AND is_cancelled=0",[$id]);$header=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$header)throw new RuntimeException('Data tidak tersedia.');
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_shift WITH (UPDLOCK,HOLDLOCK) WHERE manual_output_id=? AND is_cancelled=0 ORDER BY id DESC",[$id]);$shift=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$shift||$shift['status_active']||$shift['is_correction_pending'])throw new RuntimeException('Hanya shift terakhir yang sudah selesai dapat di-rollback.');
        $master=om_master((string)$header['prdnmbr']);if(!$master||om_shift_total($id)<(float)$master['prdqty']-0.00001)throw new RuntimeException('Rollback hanya tersedia setelah produksi Complete.');
        if(!om_is_admin()&&trim((string)$shift['operator'])!==om_user())throw new RuntimeException('Rollback hanya untuk pemilik shift terakhir atau Administrator.');
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 id FROM manual_output_shift WHERE manual_output_id=? AND id>?",[$id,$shift['id']]);if($stmt&&sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))throw new RuntimeException('Sudah ada shift setelah shift terakhir.');
        $total=om_shift_total($id);if(sqlsrv_query($conn,"UPDATE manual_output_shift SET is_correction_pending=1,status_active=1,finish_time=NULL,updated_at=GETDATE() WHERE id=?",[$shift['id']])===false)throw new RuntimeException('Gagal menandai koreksi.');
        $after=om_shift_total($id);if(sqlsrv_query($conn,"UPDATE manual_output SET meter=?,finish_time=NULL,current_operator=?,op_mesin=?,upd_at=GETDATE() WHERE id=?",[$after,$shift['operator'],$shift['operator'],$id])===false)throw new RuntimeException('Gagal memperbarui header.');
        if(!om_history($header['prdnmbr'],$id,$total,-(float)$shift['meter'],$after,'ROLLBACK',$reason))throw new RuntimeException('Gagal menulis audit rollback.');sqlsrv_commit($conn);om_json(['success'=>true,'message'=>'Rollback aktif. Shift terakhir menunggu koreksi.']);
    }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

if($action==='cancel_shift'){
    om_require_csrf(); $id=(int)($_POST['id']??0); $reason=trim((string)($_POST['reason']??'')); $confirm=trim((string)($_POST['confirm_prdnmbr']??''));
    if($id<=0||$reason==='')om_json(['success'=>false,'message'=>'Alasan pembatalan wajib diisi.']);
    if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai transaksi.']);
    try{
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE id=? AND is_cancelled=0",[$id]);$header=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$header)throw new RuntimeException('Data tidak tersedia.');
        if(!hash_equals((string)$header['prdnmbr'],$confirm))throw new RuntimeException('Konfirmasi No CP tidak cocok.');
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output_shift WITH (UPDLOCK,HOLDLOCK) WHERE manual_output_id=? AND status_active=1 AND is_cancelled=0 ORDER BY id DESC",[$id]);$shift=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$shift)throw new RuntimeException('Tidak ada shift aktif untuk dibatalkan.');
        if(!om_is_admin()&&trim((string)$shift['operator'])!==om_user())throw new RuntimeException('Hanya pemilik shift aktif atau Administrator dapat membatalkan shift.');
        $before=om_shift_total($id);
        if(sqlsrv_query($conn,"UPDATE manual_output_shift SET is_cancelled=1,status_active=0,finish_time=COALESCE(finish_time,GETDATE()),updated_at=GETDATE() WHERE id=?",[$shift['id']])===false)throw new RuntimeException('Gagal membatalkan shift aktif.');
        $after=om_shift_total($id);
        if(sqlsrv_query($conn,"UPDATE manual_output SET meter=?,finish_time=NULL,current_operator=NULL,op_mesin=NULL,upd_at=GETDATE() WHERE id=?",[$after,$id])===false)throw new RuntimeException('Gagal memperbarui total produksi.');
        if(!om_history($header['prdnmbr'],$id,$before,-(float)$shift['meter'],$after,'CANCEL_SHIFT',$reason,(int)$shift['id']))throw new RuntimeException('Gagal menulis audit pembatalan.');sqlsrv_commit($conn);om_json(['success'=>true,'message'=>'Shift aktif dibatalkan. Shift pengguna lain tetap tersimpan; Oper Shift untuk melanjutkan produksi.']);
    }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

if($action==='delete'){
    om_require_csrf(); $id=(int)($_POST['id']??0); $reason=trim((string)($_POST['reason']??'')); $confirm=trim((string)($_POST['confirm_prdnmbr']??''));
    if(!om_is_admin())om_json(['success'=>false,'message'=>'Delete permanen hanya untuk Administrator.']);
    if($id<=0||$reason==='')om_json(['success'=>false,'message'=>'Alasan Delete permanen wajib diisi.']);
    if(!sqlsrv_begin_transaction($conn))om_json(['success'=>false,'message'=>'Gagal memulai transaksi.']);
    try{
        $stmt=sqlsrv_query($conn,"SELECT TOP 1 * FROM manual_output WITH (UPDLOCK,HOLDLOCK) WHERE id=?",[$id]);$header=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;if(!$header)throw new RuntimeException('Data tidak tersedia.');
        if(!hash_equals((string)$header['prdnmbr'],$confirm))throw new RuntimeException('Konfirmasi No CP tidak cocok.');
        if(sqlsrv_query($conn,"DELETE FROM manual_output_qty_history WHERE manual_output_id=?",[$id])===false)throw new RuntimeException('Gagal menghapus riwayat produksi.');
        if(sqlsrv_query($conn,"DELETE FROM manual_output_shift WHERE manual_output_id=?",[$id])===false)throw new RuntimeException('Gagal menghapus detail shift.');
        if(sqlsrv_query($conn,"DELETE FROM manual_output WHERE id=?",[$id])===false)throw new RuntimeException('Gagal menghapus header produksi.');
        sqlsrv_commit($conn);om_json(['success'=>true,'message'=>'Produksi dan seluruh riwayat shift sudah dihapus permanen.']);
    }catch(Throwable $e){sqlsrv_rollback($conn);om_json(['success'=>false,'message'=>$e->getMessage()]);}
}

$dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: date('Y-m-01\T00:00');
$dateTo = trim((string)($_GET['date_to'] ?? '')) ?: date('Y-m-d\TH:i');
$dateFromSql = DateTime::createFromFormat('!Y-m-d\TH:i', $dateFrom);
$dateToSql = DateTime::createFromFormat('!Y-m-d\TH:i', $dateTo);
if (!$dateFromSql || !$dateToSql || $dateFromSql > $dateToSql) {
    $dateFrom = date('Y-m-01\T00:00');
    $dateTo = date('Y-m-d\TH:i');
    $dateFromSql = DateTime::createFromFormat('!Y-m-d\TH:i', $dateFrom);
    $dateToSql = DateTime::createFromFormat('!Y-m-d\TH:i', $dateTo);
}
$dateFromValue = $dateFromSql->format('Y-m-d H:i:s');
// Include the entire selected minute, excluding the next minute.
$dateToValue = (clone $dateToSql)->modify('+1 minute')->format('Y-m-d H:i:s');
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['', 'complete', 'running', 'draft'], true)) $statusFilter = '';
$machineFilter = max(0, (int)($_GET['machine_id'] ?? 0));
$colorFilter = trim((string)($_GET['color'] ?? ''));
$allowedThemes = ['primary','secondary','success','danger','warning','info','light','dark','navy','olive','lime','fuchsia','maroon','blue','indigo','purple','pink','red','orange','yellow','green','teal','cyan','white','gray','gray-dark'];
$userTheme = strtolower((string)($_SESSION['Theme'] ?? 'primary'));
if (!in_array($userTheme, $allowedThemes, true)) $userTheme = 'primary';
$themeTextClass = in_array($userTheme, ['warning','light','lime','yellow','white'], true) ? 'text-dark' : 'text-white';
$baseWhere = ['o.is_cancelled=0', 'CASE WHEN o.status_draft=1 THEN o.start_time ELSE COALESCE(times.first_start,o.start_time) END >= ? AND CASE WHEN o.status_draft=1 THEN o.start_time ELSE COALESCE(times.first_start,o.start_time) END < ?'];
$baseParams = [$dateFromValue, $dateToValue];
if ($statusFilter === 'complete') $baseWhere[] = 'o.status_draft=0 AND m.prdqty IS NOT NULL AND o.meter>=m.prdqty-0.00001';
if ($statusFilter === 'running') $baseWhere[] = 'o.status_draft=0 AND (m.prdqty IS NULL OR o.meter<m.prdqty-0.00001)';
if ($statusFilter === 'draft') $baseWhere[] = 'o.status_draft=1';
if ($machineFilter > 0) { $baseWhere[] = 'o.mesin_id=?'; $baseParams[] = $machineFilter; }
if ($colorFilter !== '') { $baseWhere[] = 'o.cuscolor=?'; $baseParams[] = $colorFilter; }
if ($q !== '') { $baseWhere[] = '(o.prdnmbr LIKE ? OR o.cuscolor LIKE ? OR o.labeljual LIKE ? OR o.op_mesin LIKE ? OR e_operator.nama_lengkap LIKE ? OR o.ket LIKE ? OR mm.kode_mesin LIKE ? OR mm.nama_mesin LIKE ?)'; $like='%'.$q.'%'; array_push($baseParams,$like,$like,$like,$like,$like,$like,$like,$like); }
$fromSql = " FROM manual_output o LEFT JOIN manual_output_master m ON m.prdnmbr=o.prdnmbr LEFT JOIN manual_output_master_mesin mm ON mm.id=o.mesin_id LEFT JOIN SMUserMs su_operator ON su_operator.UserName=o.op_mesin LEFT JOIN m_emp e_operator ON e_operator.id_emp=su_operator.EmpId OUTER APPLY (SELECT (SELECT TOP 1 s1.start_time FROM manual_output_shift s1 WHERE s1.manual_output_id=o.id ORDER BY s1.id ASC) first_start,(SELECT TOP 1 s2.finish_time FROM manual_output_shift s2 WHERE s2.manual_output_id=o.id ORDER BY s2.id DESC) last_finish) times OUTER APPLY (SELECT CASE WHEN o.status_draft=0 AND m.prdqty IS NOT NULL AND o.meter>=m.prdqty-0.00001 THEN COALESCE(times.last_finish,o.finish_time) END finish_time) completed";
if ($action === 'table_data') {
    $draw=max(0,(int)($_GET['draw'] ?? 0));$start=max(0,(int)($_GET['start'] ?? 0));$length=min(100,max(10,(int)($_GET['length'] ?? 10)));
    $tableSearch=trim((string)($_GET['search']['value'] ?? ''));
    $baseWhereSql=implode(' AND ',$baseWhere);
    $totalStmt=sqlsrv_query($conn,"SELECT COUNT_BIG(1) total $fromSql WHERE $baseWhereSql",$baseParams);$totalRow=$totalStmt?sqlsrv_fetch_array($totalStmt,SQLSRV_FETCH_ASSOC):null;$total=(int)($totalRow['total'] ?? 0);
    $where=$baseWhere;$params=$baseParams;
    if($tableSearch!==''){ $where[]='(o.prdnmbr LIKE ? OR o.iso LIKE ? OR o.partai LIKE ? OR o.cuscolor LIKE ? OR o.op_mesin LIKE ? OR e_operator.nama_lengkap LIKE ? OR mm.kode_mesin LIKE ? OR o.labeljual LIKE ? OR o.ket LIKE ?)';$like='%'.$tableSearch.'%';array_push($params,$like,$like,$like,$like,$like,$like,$like,$like,$like); }
    $whereSql=implode(' AND ',$where);
    $countStmt=sqlsrv_query($conn,"SELECT COUNT_BIG(1) total $fromSql WHERE $whereSql",$params);$countRow=$countStmt?sqlsrv_fetch_array($countStmt,SQLSRV_FETCH_ASSOC):null;$filtered=(int)($countRow['total'] ?? 0);
    $sortMap=[1=>'o.prdnmbr',2=>'o.iso',3=>'o.partai',4=>'o.cuscolor',5=>'o.meter',6=>'o.gol',7=>'o.grey',8=>'o.tengah',9=>'COALESCE(times.first_start,o.start_time)',10=>'completed.finish_time',11=>'CASE WHEN o.status_draft=1 OR completed.finish_time IS NULL THEN 0 ELSE 1 END',12=>'o.stdcutfg',13=>"COALESCE(NULLIF(LTRIM(RTRIM(e_operator.nama_lengkap)),''),o.op_mesin)",14=>'mm.kode_mesin',15=>'o.labeljual',16=>'o.ket',17=>'o.created_by'];
    $sortIndex=(int)($_GET['order'][0]['column'] ?? 11);$sort=$sortMap[$sortIndex] ?? 'CASE WHEN o.status_draft=1 OR completed.finish_time IS NULL THEN 0 ELSE 1 END';$direction=strtolower((string)($_GET['order'][0]['dir'] ?? 'asc'))==='desc'?'DESC':'ASC';
    $data=[];$pageParams=array_merge($params,[$start,$length]);
    $pageSql="SELECT o.id,o.prdnmbr,o.iso,o.partai,o.cuscolor,o.meter,o.gol,o.grey,o.tengah,COALESCE(times.first_start,o.start_time) start_time,completed.finish_time,o.stdcutfg,o.op_mesin,COALESCE(NULLIF(LTRIM(RTRIM(e_operator.nama_lengkap)),''),o.op_mesin) operator_name,mm.kode_mesin,mm.nama_mesin,o.labeljual,o.ket,o.created_at,o.created_by,o.status_draft,o.current_operator $fromSql WHERE $whereSql ORDER BY $sort $direction,o.id DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $pageStmt=sqlsrv_query($conn,$pageSql,$pageParams);
    if($pageStmt!==false){$number=$start+1;while($row=sqlsrv_fetch_array($pageStmt,SQLSRV_FETCH_ASSOC)){$owned=trim((string)($row['current_operator']??$row['op_mesin']??$row['created_by']??''))===om_user();$status=!empty($row['status_draft'])?'<span class="badge badge-warning">Belum Berjalan</span>':(!empty($row['finish_time'])?'<span class="badge badge-success">Selesai</span>':'<span class="badge badge-info">Berjalan</span>');$actions=!empty($row['finish_time'])?'<button type="button" class="btn btn-sm btn-info btnEditRow" data-id="'.(int)$row['id'].'" title="Detail"><i class="fas fa-eye"></i></button>':($owned?'<button type="button" class="btn btn-sm btn-'.htmlspecialchars($userTheme).' btnEditRow" data-id="'.(int)$row['id'].'" title="Edit data"><i class="fas fa-edit"></i></button>':'<button type="button" class="btn btn-sm btn-secondary btnEditRow" data-id="'.(int)$row['id'].'" title="Lihat / oper shift"><i class="fas fa-exchange-alt"></i></button>');if(om_is_admin())$actions.=' <button type="button" class="btn btn-sm btn-danger btnDeletePermanentRow" data-id="'.(int)$row['id'].'" title="Delete permanen"><i class="fas fa-trash"></i></button>';$data[]=[(string)$number++,htmlspecialchars($row['prdnmbr']??''),htmlspecialchars($row['iso']??''),htmlspecialchars($row['partai']??''),htmlspecialchars($row['cuscolor']??''),number_format((float)($row['meter']??0),2,'.',''),htmlspecialchars((string)($row['gol']??'')),htmlspecialchars((string)($row['grey']??'')),htmlspecialchars((string)($row['tengah']??'')),htmlspecialchars(om_display_dt($row['start_time']??'')),htmlspecialchars(om_display_dt($row['finish_time']??'')),$status,htmlspecialchars((string)($row['stdcutfg']??'')),htmlspecialchars($row['operator_name']??$row['op_mesin']??''),htmlspecialchars($row['kode_mesin']??'-'),htmlspecialchars($row['labeljual']??''),htmlspecialchars($row['ket']??''),htmlspecialchars($row['created_by']??''),$actions];}sqlsrv_free_stmt($pageStmt);}
    $summary = ['morning'=>0.0,'afternoon'=>0.0,'night'=>0.0,'total'=>0.0];
    $summarySql = "SELECT
        COALESCE(SUM(CASE WHEN CONVERT(time,s.start_time)>='05:30:00' AND CONVERT(time,s.start_time)<'13:30:00' THEN s.meter ELSE 0 END),0) morning,
        COALESCE(SUM(CASE WHEN CONVERT(time,s.start_time)>='13:30:00' AND CONVERT(time,s.start_time)<'21:30:00' THEN s.meter ELSE 0 END),0) afternoon,
        COALESCE(SUM(CASE WHEN CONVERT(time,s.start_time)<'05:30:00' OR CONVERT(time,s.start_time)>='21:30:00' THEN s.meter ELSE 0 END),0) night
        $fromSql INNER JOIN manual_output_shift s ON s.manual_output_id=o.id
        WHERE $whereSql AND s.is_cancelled=0 AND s.meter>0";
    $summaryStmt=sqlsrv_query($conn,$summarySql,$params);
    if($summaryStmt!==false){$summaryRow=sqlsrv_fetch_array($summaryStmt,SQLSRV_FETCH_ASSOC) ?: [];sqlsrv_free_stmt($summaryStmt);$summary['morning']=(float)($summaryRow['morning']??0);$summary['afternoon']=(float)($summaryRow['afternoon']??0);$summary['night']=(float)($summaryRow['night']??0);$summary['total']=$summary['morning']+$summary['afternoon']+$summary['night'];}
    om_json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data,'summary'=>$summary]);
}
$tableRowsHtml='';$tableSignature='';
$machines=[];$machineStmt=sqlsrv_query($conn,"SELECT id,kode_mesin,nama_mesin,status_active FROM manual_output_master_mesin ORDER BY kode_mesin");
if($machineStmt)while($machine=sqlsrv_fetch_array($machineStmt,SQLSRV_FETCH_ASSOC))$machines[]=$machine;
$colors=[];$colorStmt=sqlsrv_query($conn,"SELECT DISTINCT cuscolor FROM manual_output WHERE is_cancelled=0 AND cuscolor IS NOT NULL AND LTRIM(RTRIM(cuscolor))<>'' ORDER BY cuscolor");
if($colorStmt)while($color=sqlsrv_fetch_array($colorStmt,SQLSRV_FETCH_ASSOC))$colors[]=(string)$color['cuscolor'];
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
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.css">
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
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
        .qty-summary{display:grid;grid-template-columns:repeat(5,minmax(125px,1fr));gap:9px}.qty-metric{padding:10px 12px;background:#fff;border:1px solid #d9edf2;border-radius:8px}.qty-metric span{display:block;color:#63808a;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}.qty-metric strong{display:block;margin-top:3px;color:#123b46;font-size:1rem;white-space:nowrap}.qty-metric.remaining{background:#fff8e7;border-color:#f4d88a}.qty-status{margin-top:10px;font-size:.86rem;color:#47636b}
        .output-shift-summary{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:12px;margin-top:16px}.output-shift-card{padding:13px 15px;border:1px solid #dfe5ec;border-radius:9px;background:#fff;box-shadow:0 4px 13px rgba(27,57,77,.06)}.output-shift-card span{display:block;color:#687585;font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}.output-shift-card strong{display:block;margin-top:4px;color:#173b45;font-size:1.15rem}.output-shift-card.morning{background:#fff8df;border-color:#f1cf76}.output-shift-card.morning strong{color:#986a00}.output-shift-card.afternoon{background:#fff0e8;border-color:#f0b28d}.output-shift-card.afternoon strong{color:#b64a14}.output-shift-card.night{background:#edf0ff;border-color:#bfc8f5}.output-shift-card.night strong{color:#39499b}.output-shift-card.total{background:#e8f8fb;border-color:#b8e2e9}.output-shift-card.total strong{color:#087f8c}.output-filter-panel{border:1px solid #dfe5ec;border-radius:8px;background:#f8fafc;padding:14px}.output-filter-grid{display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:12px;align-items:end}.output-filter-field{min-width:0}.output-filter-field label{display:block;margin:0 0 5px;color:#566372;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.035em}.output-filter-actions{display:flex;gap:8px;align-items:flex-end}.output-filter-note{margin:12px 0 0;color:#687585;font-size:.82rem}.output-filter-panel .select2-container{width:100%!important}.output-filter-panel .select2-selection--single{height:31px!important}.output-filter-panel .select2-selection__rendered{line-height:29px!important;font-size:.875rem}.output-filter-panel .select2-selection__arrow{height:100%!important;top:0!important;right:3px!important}@media(max-width:1199px){.output-filter-grid{grid-template-columns:repeat(3,minmax(180px,1fr))}}@media(max-width:767px){.qty-summary{grid-template-columns:repeat(2,1fr)}.output-shift-summary{grid-template-columns:repeat(2,1fr)}.output-filter-grid{grid-template-columns:1fr}.output-filter-actions{align-items:stretch}.output-filter-actions .btn{flex:1}}
        .scan-loading{position:fixed;inset:0;z-index:2060;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(7,20,35,.58);backdrop-filter:blur(5px);opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease}.scan-loading.show{opacity:1;visibility:visible}.scan-loading-card{min-width:290px;max-width:390px;padding:24px;border:1px solid rgba(255,255,255,.2);border-radius:18px;background:linear-gradient(145deg,rgba(255,255,255,.98),rgba(235,248,251,.96));box-shadow:0 24px 70px rgba(0,0,0,.28);text-align:center;transform:translateY(10px) scale(.97);transition:transform .25s ease}.scan-loading.show .scan-loading-card{transform:none}.scan-loader{position:relative;width:62px;height:62px;margin:0 auto 16px}.scan-loader:before,.scan-loader:after{content:"";position:absolute;border-radius:50%;inset:0}.scan-loader:before{border:5px solid #d9eef2;border-top-color:#087f8c;animation:scan-spin .8s linear infinite}.scan-loader:after{inset:14px;border:4px solid transparent;border-right-color:#0f4c81;animation:scan-spin .65s linear infinite reverse}.scan-loading-title{display:block;color:#123b46;font-size:1.05rem}.scan-loading-text{margin:5px 0 0;color:#62808a;font-size:.84rem}.scan-loading-dots:after{content:"";animation:scan-dots 1.4s steps(4,end) infinite}@keyframes scan-spin{to{transform:rotate(360deg)}}@keyframes scan-dots{0%{content:""}25%{content:"."}50%{content:".."}75%,100%{content:"..."}}@media(prefers-reduced-motion:reduce){.scan-loader:before,.scan-loader:after,.scan-loading-dots:after{animation:none}.scan-loading,.scan-loading-card{transition:none}}
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
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
            <div class="card-header bg-<?= htmlspecialchars($userTheme) ?> <?= $themeTextClass ?> d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-table mr-1"></i> View Data</strong>
                <a class="btn btn-success btn-sm ml-auto" id="exportExcel" target="_blank" href="export_manual_output.php?q=<?= urlencode($q) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&status=<?= urlencode($statusFilter) ?>&machine_id=<?= $machineFilter ?>&color=<?= urlencode($colorFilter) ?>"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            </div>
            <div class="card-body">
                <div class="output-filter-panel">
                    <div class="output-filter-grid" role="search" aria-label="Filter Output Manual">
                        <div class="output-filter-field"><label for="date_from">Start dari</label><input type="datetime-local" id="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                        <div class="output-filter-field"><label for="date_to">Start sampai</label><input type="datetime-local" id="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>"></div>
                        <div class="output-filter-field"><label for="status_filter">Status</label><select id="status_filter" class="form-control form-control-sm"><option value="" <?= $statusFilter===''?'selected':'' ?>>Semua Status</option><option value="complete" <?= $statusFilter==='complete'?'selected':'' ?>>Selesai</option><option value="running" <?= $statusFilter==='running'?'selected':'' ?>>Berjalan</option><option value="draft" <?= $statusFilter==='draft'?'selected':'' ?>>Belum Berjalan</option></select></div>
                        <div class="output-filter-field"><label for="machine_filter">Mesin</label><select id="machine_filter" class="form-control form-control-sm"><option value="0">Semua Mesin</option><?php foreach($machines as $machine): ?><option value="<?= (int)$machine['id'] ?>" <?= $machineFilter===(int)$machine['id']?'selected':'' ?>><?= htmlspecialchars($machine['kode_mesin'].(!empty($machine['nama_mesin'])?' — '.$machine['nama_mesin']:'')) ?></option><?php endforeach; ?></select></div>
                        <div class="output-filter-field"><label for="color_filter">Warna</label><select id="color_filter" class="form-control form-control-sm"><option value="">Semua Warna</option><?php foreach($colors as $color): ?><option value="<?= htmlspecialchars($color) ?>" <?= $colorFilter===$color?'selected':'' ?>><?= htmlspecialchars($color) ?></option><?php endforeach; ?></select></div>
                        <div class="output-filter-field"><label for="search_text">Pencarian</label><input type="text" id="search_text" class="form-control form-control-sm" value="<?= htmlspecialchars($q) ?>" placeholder="No CP atau data"></div>
                        <div class="output-filter-actions"><button class="btn btn-<?= htmlspecialchars($userTheme) ?> btn-sm" id="btnSearch"><i class="fas fa-search mr-1"></i>Cari</button><button class="btn btn-outline-secondary btn-sm" id="btnReset" type="button"><i class="fas fa-undo mr-1"></i>Reset</button></div>
                    </div>
                    <p class="output-filter-note mb-0"><i class="fas fa-info-circle mr-1"></i>Periode memakai waktu Start. Semua status tampil bila waktu Start masuk rentang filter.</p>
                </div>
                <div class="output-shift-summary" aria-live="polite">
                    <div class="output-shift-card morning"><span>Shift Pagi</span><strong id="output-summary-morning">0.00 M</strong></div>
                    <div class="output-shift-card afternoon"><span>Shift Siang</span><strong id="output-summary-afternoon">0.00 M</strong></div>
                    <div class="output-shift-card night"><span>Shift Malam</span><strong id="output-summary-night">0.00 M</strong></div>
                    <div class="output-shift-card total"><span>Total Meter</span><strong id="output-summary-total">0.00 M</strong></div>
                </div>
                <div class="table-responsive mt-3">
                    <table id="outputTable" class="table table-hover table-sm nowrap output-table" data-signature="<?= $tableSignature ?>" style="width:100%">
                        <thead class="thead-light"><tr><th>No</th><th>No CP</th><th>ISO</th><th>Partai</th><th>Warna</th><th>Meter</th><th>Gol</th><th>Grey</th><th>Tengah</th><th>Start</th><th>Finish</th><th>Status</th><th>STD Potong</th><th>OP Mesin</th><th>Mesin</th><th>L Jual</th><th>Ket</th><th>Created By</th><th>Aksi</th></tr></thead>
                        <tbody><?= $tableRowsHtml ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div></section></div>
</div>
<div class="modal fade" id="outputModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($userTheme) ?> <?= $themeTextClass ?>"><h5 class="modal-title" id="modalTitle">Output Manual</h5><button type="button" class="close <?= $themeTextClass ?>" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body"><div id="modalNotice" class="alert alert-info d-none"></div><form id="outputForm"><input type="hidden" name="csrf_token" id="om_csrf_token" value="<?= htmlspecialchars(om_csrf_token()) ?>"><input type="hidden" name="prdnmbr" id="prdnmbr" value=""><input type="hidden" name="id" id="row_id"><input type="hidden" id="row_version"><ul class="nav nav-tabs mb-3" id="outputManualTab" role="tablist">
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
                            <div id="completedHistoryInput" class="table-responsive d-none"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Operator</th><th>Mesin</th><th>Meter</th><th>Gol</th><th>Grey</th><th>Tengah</th><th>Keterangan</th><th>Start</th><th>Finish</th><th>Status</th></tr></thead><tbody id="completedHistoryInputBody"><tr><td colspan="10" class="text-center text-muted">Belum ada riwayat input</td></tr></tbody></table></div>
                            <div id="outputInputForm" class="form-row">
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
                <button type="button" class="btn btn-outline-danger mr-auto" id="btnCancelOutput" style="display:none"><i class="fas fa-ban mr-1"></i>Batalkan</button>
                <div class="d-flex">
                    <button type="button" class="btn btn-warning" id="btnHandover" style="display:none"><i class="fas fa-exchange-alt mr-1"></i>Ambil Alih Shift</button>
                    <button type="button" class="btn btn-outline-warning ml-2" id="btnRollback" style="display:none"><i class="fas fa-undo mr-1"></i>Rollback</button>
                    <button type="button" class="btn btn-secondary ml-2" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-<?= htmlspecialchars($userTheme) ?> ml-2" id="btnSave">Save</button>
                </div>
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
    const statusFilter = document.getElementById('status_filter');
    const machineFilter = document.getElementById('machine_filter');
    const colorFilter = document.getElementById('color_filter');
    const filterMachineSelect = $('#machine_filter');
    const filterColorSelect = $('#color_filter');
    const btnReset = document.getElementById('btnReset');
    const exportExcel = document.getElementById('exportExcel');
    const btnSearch = document.getElementById('btnSearch');
    const btnSave = document.getElementById('btnSave');
    const btnHandover = document.getElementById('btnHandover');
    const btnRollback = document.getElementById('btnRollback');
    const btnCancelOutput = document.getElementById('btnCancelOutput');
    let outputManualShifts = [];
    const modal = $('#outputModal');
    const machineSelect = $('#mesin_id');
    const notice = document.getElementById('modalNotice');
    machineSelect.select2({theme:'bootstrap4',placeholder:'Pilih mesin',allowClear:true,width:'100%',dropdownParent:modal.find('.modal-content')});
    filterMachineSelect.select2({theme:'bootstrap4',placeholder:'Semua Mesin',allowClear:false,width:'100%'});
    filterColorSelect.select2({theme:'bootstrap4',placeholder:'Semua Warna',allowClear:false,width:'100%'});

    const outputTableElement = document.getElementById('outputTable');
    const outputTable = $('#outputTable').DataTable({
        processing: true,
        serverSide: true,
        order: [[11, 'asc']],
        ajax: {url:'output_manual.php',type:'GET',data: data => Object.assign(data,{action:'table_data',q:searchText.value.trim(),date_from:dateFrom.value,date_to:dateTo.value,status:statusFilter.value,machine_id:machineFilter.value,color:colorFilter.value})},
        responsive: {details: {type: 'column', target: 0}},
        columnDefs: [
            {className: 'dtr-control', orderable: false, searchable: false, responsivePriority: 1, targets: 0},
            {responsivePriority: 2, targets: 1},
            {orderable:false, searchable:false, responsivePriority: 2, targets: 11},
            {responsivePriority: 3, targets: 9},
            {responsivePriority: 4, targets: 10},
            {orderable:false, searchable:false, targets:18}
        ],
        language: {
            processing: 'Memuat data...',lengthMenu: 'Tampilkan _MENU_ data per halaman',zeroRecords: 'Tidak ada data ditemukan',info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',infoEmpty: 'Tidak ada data tersedia',infoFiltered: '(disaring dari _MAX_ total data)',search: 'Cari:',paginate: {first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya'}
        }
    });
    outputTable.on('xhr.dt', function (event, settings, json) {
        const summary = json && json.summary ? json.summary : {};
        [['morning','output-summary-morning'],['afternoon','output-summary-afternoon'],['night','output-summary-night'],['total','output-summary-total']].forEach(([key,id]) => {
            document.getElementById(id).textContent = `${Number(summary[key] || 0).toFixed(2)} M`;
        });
    });

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

    function refreshOutputTable(resetPaging = false) {
        outputTable.ajax.reload(null, resetPaging);
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
        const displayTwoDecimal = value => Number(value || 0).toFixed(2);
        notice.innerHTML = `<div class="qty-summary">${metrics.map((item,i)=>`<div class="qty-metric ${i===4?'remaining':''}"><span>${item[0]}</span><strong>${displayTwoDecimal(item[1])} <small>M</small></strong></div>`).join('')}</div>${statusHtml}`;
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
        const completedHistoryInput = document.getElementById('completedHistoryInput');
        const completedHistoryInputBody = document.getElementById('completedHistoryInputBody');
        const outputInputForm = document.getElementById('outputInputForm');
        const inputTab = document.getElementById('output-input-tab');
        const shifts = Array.isArray(data.shifts) ? data.shifts : [];
        outputManualShifts = shifts;
        const modalOperators = [...new Set(shifts.filter(shift => !shift.cancelled && Number(shift.meter || 0) > 0).map(shift => String(shift.operator || '').trim()).filter(Boolean))];
        if (modalOperators.length) document.getElementById('op_mesin').value = modalOperators.join(' | ');
        const shiftStatus = shift => shift.cancelled ? '<span class="badge badge-danger">Dibatalkan</span>' : (shift.pending ? '<span class="badge badge-warning">Menunggu Koreksi</span>' : (shift.active ? '<span class="badge badge-info">Aktif</span>' : '<span class="badge badge-success">Selesai</span>'));
        const shiftDisplayValue = value => value === null || value === undefined || value === '' ? '-' : displayTwoDecimal(value);
        historyBody.innerHTML = shifts.length ? shifts.map(shift => `<tr><td>${escapeHtml(shift.operator || '')}</td><td>${escapeHtml(shift.mesin || '-')}</td><td>${shiftDisplayValue(shift.meter)}</td><td>${escapeHtml(shift.start_time || '')}</td><td>${escapeHtml(shift.finish_time || '-')}</td><td>${shiftStatus(shift)}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted">Belum ada riwayat shift</td></tr>';
        const isComplete = mode === 'readonly';
        inputTab.textContent = isComplete ? 'History Input' : 'Input';
        completedHistoryInput.classList.toggle('d-none', !isComplete);
        outputInputForm.classList.toggle('d-none', isComplete);
        completedHistoryInputBody.innerHTML = shifts.length ? shifts.map(shift => `<tr><td>${escapeHtml(shift.operator || '')}</td><td>${escapeHtml(shift.mesin || '-')}</td><td>${shiftDisplayValue(shift.meter)}</td><td>${shiftDisplayValue(shift.gol)}</td><td>${shiftDisplayValue(shift.grey)}</td><td>${shiftDisplayValue(shift.tengah)}</td><td>${escapeHtml(shift.ket || '')}</td><td>${escapeHtml(shift.start_time || '')}</td><td>${escapeHtml(shift.finish_time || '-')}</td><td>${shiftStatus(shift)}</td></tr>`).join('') : '<tr><td colspan="10" class="text-center text-muted">Belum ada riwayat input</td></tr>';
        setReadonly(mode === 'readonly' || mode === 'readonly_other' || mode === 'handover', mode === 'handover');
        btnRollback.style.display = data.can_rollback ? 'inline-block' : 'none';
        btnCancelOutput.style.display = data.can_cancel_shift ? 'inline-block' : 'none';
        modal.modal('show');
    }

    let lastLookup = '';
    let lookupBusy = false;

    modal.on('hidden.bs.modal', function () {
        lastLookup = '';
        scanInput.value = '';
        if (!document.querySelector('.swal2-container')) scanInput.focus();
    });

    function setScanLoading(show) {
        const loading = document.getElementById('scanLoading');
        loading.classList.toggle('show', show);
        loading.setAttribute('aria-hidden', show ? 'false' : 'true');
        scanInput.disabled = show;
        scanInput.setAttribute('aria-busy', show ? 'true' : 'false');
    }

    function promptOptions() {
        const options = {
            didOpen: () => {
                scanInput.disabled = true;
                window.setTimeout(() => Swal.getInput()?.focus(), 100);
            },
            willClose: () => { scanInput.disabled = lookupBusy; }
        };
        if (modal.hasClass('show')) options.target = document.getElementById('outputModal');
        return options;
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

    $('#outputTable').on('click', '.btnDeletePermanentRow', async function () {
        const id = Number($(this).data('id') || 0);
        const row = await fetchJson(`output_manual.php?action=row&id=${id}`, {credentials: 'same-origin'});
        if (!row.success) { toast(row.message || 'Gagal membuka data', 'error'); return; }
        await deletePermanent(id, String(row.data?.prdnmbr || ''));
    });

    btnHandover.addEventListener('click', async function () {
        const result = await Swal.fire({icon: 'question', title: 'Ambil alih shift?', text: 'Hak edit berpindah ke user Anda.', showCancelButton: true, confirmButtonText: 'Ambil Alih', cancelButtonText: 'Batal'});
        if (!result.isConfirmed) return;
        const form = new FormData(); form.append('action', 'handover');
        form.append('id', document.getElementById('row_id').value);
        form.append('row_version', document.getElementById('row_version').value);
        form.append('csrf_token', document.getElementById('om_csrf_token').value);
        const json = await fetchJson('output_manual.php', {method: 'POST', body: form, credentials: 'same-origin'});
        if (json.success) { toast(json.message, 'success'); const id = document.getElementById('row_id').value; const fresh = await fetchJson(`output_manual.php?action=row&id=${id}`); if (fresh.success) fillModal(fresh.data, fresh.title, fresh.message, fresh.mode); await refreshOutputTable(); }
        else toast(json.message || 'Oper shift gagal', 'error');
    });

    btnRollback.addEventListener('click', async function () {
        const result = await Swal.fire({...promptOptions(),icon:'warning',title:'Rollback shift terakhir?',input:'text',inputLabel:'Alasan rollback',inputValidator:value=>!value.trim()?'Alasan wajib diisi':undefined,showCancelButton:true,focusConfirm:false,confirmButtonText:'Rollback',cancelButtonText:'Batal',focusCancel:false});
        if (!result.isConfirmed) return;
        const form = new FormData(); form.append('action','rollback'); form.append('id',document.getElementById('row_id').value); form.append('reason',result.value.trim()); form.append('csrf_token',document.getElementById('om_csrf_token').value);
        const json = await fetchJson('output_manual.php',{method:'POST',body:form,credentials:'same-origin'});
        if(json.success){toast(json.message,'success');modal.modal('hide');await refreshOutputTable();}else toast(json.message||'Rollback gagal','error');
    });

    btnCancelOutput.addEventListener('click', async function () {
        const scan = document.getElementById('prdnmbr').value;
        const result = await Swal.fire({...promptOptions(),icon:'warning',title:'Batalkan shift aktif?',html:`Hanya shift aktif dibatalkan. Shift pengguna lain tetap tersimpan.<br>Ketik <strong>${escapeHtml(scan)}</strong> untuk konfirmasi.`,input:'text',inputPlaceholder:'No CP',inputValidator:value=>value.trim()!==scan?'No CP tidak cocok':undefined,showCancelButton:true,focusConfirm:false,confirmButtonText:'Batalkan shift aktif',cancelButtonText:'Batal',focusCancel:false});
        if (!result.isConfirmed) return;
        const reason = await Swal.fire({...promptOptions(),title:'Alasan pembatalan shift',input:'text',inputValidator:value=>!value.trim()?'Alasan wajib diisi':undefined,showCancelButton:true,focusConfirm:false,confirmButtonText:'Lanjut',cancelButtonText:'Batal',focusCancel:false});
        if (!reason.isConfirmed) return;
        const form = new FormData(); form.append('action','cancel_shift'); form.append('id',document.getElementById('row_id').value); form.append('confirm_prdnmbr',scan); form.append('reason',reason.value.trim()); form.append('csrf_token',document.getElementById('om_csrf_token').value);
        const json = await fetchJson('output_manual.php',{method:'POST',body:form,credentials:'same-origin'});
        if(json.success){toast(json.message,'success');modal.modal('hide');await refreshOutputTable();}else toast(json.message||'Pembatalan shift gagal','error');
    });

    async function deletePermanent(id, scan) {
        const confirm = await Swal.fire({...promptOptions(),icon:'error',title:'Delete permanen?',html:`Tindakan ini menghapus header, shift, dan riwayat.<br>Ketik <strong>${escapeHtml(scan)}</strong> untuk lanjut.`,input:'text',inputPlaceholder:'No CP',inputValidator:value=>value.trim()!==scan?'No CP tidak cocok':undefined,showCancelButton:true,focusConfirm:false,confirmButtonText:'Lanjut Delete',cancelButtonText:'Batal',focusCancel:false});
        if (!confirm.isConfirmed) return;
        const reason = await Swal.fire({...promptOptions(),icon:'warning',title:'Alasan Delete permanen',input:'text',inputValidator:value=>!value.trim()?'Alasan wajib diisi':undefined,showCancelButton:true,focusConfirm:false,confirmButtonText:'Delete permanen',cancelButtonText:'Batal',focusCancel:false});
        if (!reason.isConfirmed) return;
        const form = new FormData(); form.append('action','delete'); form.append('id',id); form.append('confirm_prdnmbr',scan); form.append('reason',reason.value.trim()); form.append('csrf_token',document.getElementById('om_csrf_token').value);
        const json = await fetchJson('output_manual.php',{method:'POST',body:form,credentials:'same-origin'});
        if(json.success){toast(json.message,'success');modal.modal('hide');await refreshOutputTable();}else toast(json.message||'Delete permanen gagal','error');
    }

    btnSave.addEventListener('click', async function () {
        const meter = document.getElementById('meter').value;
        const completing = Number(meter) >= Number(document.getElementById('meter').max || Infinity) - 0.00001;
        if (completing) {
            const invalidField = ['gol','grey','tengah'].map(id => document.getElementById(id)).find(field => field.value.trim() === '' || !Number.isFinite(Number(field.value)) || Number(field.value) < 0);
            if (invalidField) {
                toast('Gol, Grey, dan Tengah wajib terisi saat produksi Complete.', 'warning');
                invalidField.focus();
                return;
            }
        }
        const machineLabel = machineSelect.find('option:selected').text() || '-';
        const activeShift = outputManualShifts.find(shift => shift.active && !shift.cancelled);
        const confirmationOperator = activeShift && activeShift.operator ? activeShift.operator : document.getElementById('op_mesin').value;
        const result = await Swal.fire({
            icon: 'question', title: 'Simpan output?',
            html: `<div class="text-left"><strong>Produksi:</strong> ${escapeHtml(document.getElementById('prdnmbr').value)}<br><strong>Mesin:</strong> ${escapeHtml(machineLabel)}<br><strong>Operator:</strong> ${escapeHtml(confirmationOperator)}<br><strong>Meter:</strong> ${escapeHtml(meter)}<br><strong>Gol / Grey / Tengah:</strong> ${escapeHtml(document.getElementById('gol').value)} / ${escapeHtml(document.getElementById('grey').value)} / ${escapeHtml(document.getElementById('tengah').value)}</div>`,
            showCancelButton: true, confirmButtonText: 'Simpan', cancelButtonText: 'Batal', focusCancel: true
        });
        if (!result.isConfirmed) return;
        const form = new FormData();
        form.append('action', 'save');
        form.append('id', document.getElementById('row_id').value);
        form.append('prdnmbr', document.getElementById('prdnmbr').value);
        form.append('row_version', document.getElementById('row_version').value);
        form.append('csrf_token', document.getElementById('om_csrf_token').value);
        ['mesin_id','meter','gol','grey','tengah','ket'].forEach(id => form.append(id, document.getElementById(id).value));
        form.append('start_time', document.getElementById('start_time').value);
        const json = await fetchJson('output_manual.php', {method: 'POST', body: form, credentials: 'same-origin'});
        if (json.success) {
            toast(json.message || 'Data tersimpan', 'success');
            modal.modal('hide');
            await refreshOutputTable();
        } else toast(json.message || 'Gagal menyimpan data', 'error');
    });

    function currentFilters() {
        return new URLSearchParams({q: searchText.value.trim(), date_from: dateFrom.value, date_to: dateTo.value, status: statusFilter.value, machine_id: machineFilter.value, color: colorFilter.value});
    }
    btnReset.addEventListener('click', function () {
        const now = new Date(), pad = value => String(value).padStart(2, '0');
        const current = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
        dateFrom.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-01T00:00`;
        dateTo.value = current;
        statusFilter.value = '';
        filterMachineSelect.val('0').trigger('change');
        filterColorSelect.val('').trigger('change');
        searchText.value = '';
        btnSearch.click();
    });
    btnSearch.addEventListener('click', function () {
        refreshOutputTable(true);
    });
    exportExcel.addEventListener('click', function () {
        this.href = `export_manual_output.php?${currentFilters().toString()}`;
    });

    [searchText, dateFrom, dateTo, statusFilter, machineFilter, colorFilter].forEach(el => el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); btnSearch.click(); }
    }));
})();
</script>
</body>
</html>

