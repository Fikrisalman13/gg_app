<?php
session_start();
if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../../koneksi.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$currUser = $_SESSION['UserName'] ?? 'SYSTEM';
$groupId = $_SESSION['GroupId'] ?? 0;

function jsonExit($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if ($action === 'get_data') {
    $periodDate = $_POST['period_date'] ?? date('Y-m-d');
    
    // 1. Ambil nama grup mesin yang diikuti operator
    $userGroups = [];
    if ($groupId != 1) {
        $sqlGroups = "SELECT DISTINCT group_name FROM planning_user_group WHERE username = ?";
        $stmtG = sqlsrv_query($conn, $sqlGroups, [$currUser]);
        if ($stmtG) {
            while ($r = sqlsrv_fetch_array($stmtG, SQLSRV_FETCH_ASSOC)) {
                $grp = trim((string)($r['group_name'] ?? ''));
                if ($grp !== '') {
                    $userGroups[] = $grp;
                }
            }
        }
    }

    // 2. Fetch data antrean Larut Produksi
    $sqlData = "
        SELECT 
            id, seq_no, machine_id, cp_no, tgl_cp, label, cust_color, kode_lab, material_name, qty,
            vlot_resep,
            ket as plan_description,
            est_plrtn_prdks as rencana_start,
            rencana_start as rencana_finish,
            aktual_larut_prdks_start as aktual_start,
            aktual_larut_prdks_finish as aktual_finish 
        FROM dbo.cpp_paddry 
        WHERE period_date = ?
        ORDER BY machine_id ASC, seq_no ASC
    ";
    
    $params = [$periodDate];
    
    $stmt = sqlsrv_query($conn, $sqlData, $params);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        $errMsg = 'Gagal mengambil data antrean larut.';
        if (!empty($err)) {
            $errMsg .= ' (' . $err[0]['message'] . ')';
        }
        jsonExit(['success' => false, 'message' => $errMsg]);
    }

    $cplists = [];
    $machineIds = [];
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $cplists[] = $row;
        if (!empty($row['machine_id']) && !in_array($row['machine_id'], $machineIds)) {
            $machineIds[] = $row['machine_id'];
        }
    }
    
    // 3. Ambil Nama Mesin dari Database PostgreSQL (koneksi3)
    $machineNames = [];
    if (count($machineIds) > 0) {
        try {
            include '../../../koneksi3.php';
            $inClause = implode(',', array_fill(0, count($machineIds), '?'));
            $sqlFam = "SELECT facode, faname FROM famaster WHERE facode IN ($inClause)";
            $stmtFam = $conn3->prepare($sqlFam);
            $stmtFam->execute($machineIds);
            while ($fam = $stmtFam->fetch(PDO::FETCH_ASSOC)) {
                $machineNames[$fam['facode']] = trim((string)$fam['faname']);
            }
        } catch (Throwable $e) {
            // Abaikan jika error
        }
    }

    // 4. Ambil Daftar Break Time
    $breakTimes = [];
    $stmtBreak = sqlsrv_query($conn, "SELECT break_time_name FROM dbo.ms_break_time");
    if ($stmtBreak) {
        while ($btrow = sqlsrv_fetch_array($stmtBreak, SQLSRV_FETCH_ASSOC)) {
            $bname = trim((string)$btrow['break_time_name']);
            if ($bname !== '') {
                $breakTimes[] = strtoupper($bname);
            }
        }
    }

    $finalLists = [];
    foreach ($cplists as $row) {
        $r_start = $row['rencana_start'] ? $row['rencana_start']->format('H:i') : '-';
        $r_finish = $row['rencana_finish'] ? $row['rencana_finish']->format('H:i') : '-';
        
        if ($r_start === '00:00') $r_start = '-';
        if ($r_finish === '00:00') $r_finish = '-';
        
        $a_start = $row['aktual_start'] ? $row['aktual_start']->format('H:i:s') : null;
        $a_finish = $row['aktual_finish'] ? $row['aktual_finish']->format('H:i:s') : null;
        
        $machine = trim((string)($row['machine_id'] ?? 'N/A'));
        $machineText = isset($machineNames[$machine]) ? $machineNames[$machine] : $machine;

        $cpNo = trim((string)($row['cp_no'] ?? ''));
        $isBreaktime = false;
        $upCpNo = strtoupper($cpNo);
        foreach ($breakTimes as $bt) {
            if (strpos($upCpNo, $bt) !== false) {
                $isBreaktime = true;
                break;
            }
        }

        $varianceStart = null;
        if ($a_start && $row['rencana_start'] && $r_start !== '-') {
            $diffStart = strtotime(date('Y-m-d') . ' ' . $a_start) - strtotime(date('Y-m-d') . ' ' . $r_start);
            $varianceStart = $diffStart / 60;
        }

        $varianceFinish = null;
        if ($a_finish && $row['rencana_finish'] && $r_finish !== '-') {
            $diffFinish = strtotime(date('Y-m-d') . ' ' . $a_finish) - strtotime(date('Y-m-d') . ' ' . $r_finish);
            $varianceFinish = $diffFinish / 60;
        }

        $finalLists[] = [
            'id' => $row['id'],
            'seq_no' => $row['seq_no'],
            'machine_id' => $machine,
            'machine_name' => $machineText,
            'cp_no' => trim((string)($row['cp_no'] ?? '')),
            'label' => trim((string)($row['label'] ?? '')),
            'warna' => trim((string)($row['cust_color'] ?? '')),
            'kode_lab' => trim((string)($row['kode_lab'] ?? '')),
            'vlot_resep' => trim((string)($row['vlot_resep'] ?? '')),
            'material' => trim((string)($row['material_name'] ?? '')),
            'qty' => $row['qty'] ? number_format((float)$row['qty'], 2, '.', ',') : '-',
            'rencana_start' => $r_start,
            'rencana_finish' => $r_finish,
            'aktual_start' => $a_start,
            'aktual_finish' => $a_finish,
            'variance_start' => $varianceStart,
            'variance_finish' => $varianceFinish,
            'is_breaktime' => $isBreaktime
        ];
    }
    
    $filteredCps = [];
    if ($groupId != 1 && count($userGroups) > 0) {
        foreach ($finalLists as $cp) {
            $isMatch = false;
            foreach ($userGroups as $g) {
                if (stripos($g, $cp['machine_id']) !== false || stripos($cp['machine_id'], $g) !== false || stripos($cp['machine_name'], $g) !== false) {
                    $isMatch = true;
                    break;
                }
            }
            if ($isMatch) {
                $filteredCps[] = $cp;
            }
        }
    } else {
        $filteredCps = $finalLists;
    }

    jsonExit(['success' => true, 'data' => $filteredCps]);
}

if ($action === 'get_references') {
    $wheels = [];
    $delays = [];
    
    try {
        include '../../../koneksi3.php';
        $stmtW = $conn3->query("SELECT wheelno FROM pdwheelms ORDER BY wheelno ASC");
        while ($row = $stmtW->fetch(PDO::FETCH_ASSOC)) {
            $wheels[] = trim((string)$row['wheelno']);
        }
        $stmtD = $conn3->query("SELECT downcode, downdesc FROM pddowntimems ORDER BY downcode ASC");
        while ($row = $stmtD->fetch(PDO::FETCH_ASSOC)) {
            $delays[] = [
                'code' => trim((string)$row['downcode']),
                'name' => trim((string)$row['downdesc'])
            ];
        }
    } catch (Throwable $e) { }
    
    jsonExit(['success' => true, 'wheels' => $wheels, 'delays' => $delays]);
}

if ($action === 'start' || $action === 'stop') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonExit(['success' => false, 'message' => 'Invalid ID']);
    }

    $timeField = ($action === 'start') ? 'aktual_larut_prdks_start' : 'aktual_larut_prdks_finish';
    
    if (!sqlsrv_begin_transaction($conn)) {
        jsonExit(['success' => false, 'message' => 'Gagal membuka transaksi.']);
    }

    if ($action === 'start') {
        $noRoda = $_POST['no_roda'] ?? '';
        $ketDelay = $_POST['ket_delay'] ?? '';
        
        $sql = "
            UPDATE dbo.cpp_paddry
            SET $timeField = CAST(GETDATE() AS TIME),
                no_roda = ?,
                ket_delay = ?,
                update_by = ?,
                last_update = GETDATE()
            WHERE id = ?
        ";
        $params = [$noRoda, $ketDelay, $currUser, $id];
    } else {
        $sql = "
            UPDATE dbo.cpp_paddry
            SET $timeField = CAST(GETDATE() AS TIME),
                update_by = ?,
                last_update = GETDATE()
            WHERE id = ?
        ";
        $params = [$currUser, $id];
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        jsonExit(['success' => false, 'message' => 'Gagal mengupdate waktu larutProduksi ('.sqlsrv_errors()[0]['message'].')']);
    }
    
    if (!sqlsrv_commit($conn)) {
        sqlsrv_rollback($conn);
        jsonExit(['success' => false, 'message' => 'Gagal commit transaksi.']);
    }

    jsonExit(['success' => true, 'message' => 'Waktu LARUT '.strtoupper($action).' berhasil direkam.']);
}

jsonExit(['success' => false, 'message' => 'Action tidak dikenali']);
