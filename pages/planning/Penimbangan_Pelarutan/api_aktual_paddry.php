<?php
// Ensure server uses Jakarta timezone for issue timestamps
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../../koneksi.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$currUser = $_SESSION['UserName'] ?? 'SYSTEM';
$groupId = $_SESSION['GroupId'] ?? 0;

const TARGET_STAGE_RTGS = [
    876 => 'Penimbangan Obat Lab',
    877 => 'Pelarutan Obat Lab',
    878 => 'Penimbangan Obat La',
    879 => 'Pelarutan Obat La',
    880 => 'Pelarutan Obat Produksi',
    887 => 'Pelarutan Obat Produksi',
];

function jsonExit($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function sqlValue($value, $fallback = '')
{
    if ($value === null) {
        return $fallback;
    }
    return trim((string) $value);
}

function formatSqlsrvDateTime($value, $format = 'Y-m-d H:i:s')
{
    if ($value instanceof DateTimeInterface) {
        return $value->format($format);
    }
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return null;
}

function tableExists($conn, $tableName)
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID(?) AS oid", [$tableName]);
    if (!$stmt) {
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return !empty($row['oid']);
}

function columnExists($conn, $tableName, $columnName)
{
    $stmt = sqlsrv_query($conn, "SELECT COL_LENGTH(?, ?) AS col_len", [$tableName, $columnName]);
    if (!$stmt) {
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row && $row['col_len'] !== null;
}

function requireStageSchema($conn)
{
    if (!tableExists($conn, 'dbo.cpp_paddry_stage')) {
        return 'Tabel dbo.cpp_paddry_stage belum tersedia. Jalankan migrasi multi-routing paddry terlebih dahulu.';
    }
    if (!columnExists($conn, 'dbo.cpp_paddry_downtime', 'paddry_stage_id')) {
        return 'Kolom dbo.cpp_paddry_downtime.paddry_stage_id belum tersedia. Jalankan migrasi multi-routing paddry terlebih dahulu.';
    }
    return null;
}

function getAuthorizedRtgmsids($conn, $username)
{
    $groups = [];
    $stmtGroup = sqlsrv_query($conn, "SELECT group_name FROM planning_trustee_user_group WHERE username = ?", [$username]);
    if ($stmtGroup) {
        while ($row = sqlsrv_fetch_array($stmtGroup, SQLSRV_FETCH_ASSOC)) {
            $groupName = sqlValue($row['group_name']);
            if ($groupName !== '') {
                $groups[] = $groupName;
            }
        }
    }

    if (empty($groups)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($groups), '?'));
    $stmtTrustee = sqlsrv_query($conn, "SELECT rtgmsid FROM planning_trustee_group WHERE group_name IN ($placeholders)", $groups);
    $rtgmsids = [];
    if ($stmtTrustee) {
        while ($row = sqlsrv_fetch_array($stmtTrustee, SQLSRV_FETCH_ASSOC)) {
            $rtg = (int) ($row['rtgmsid'] ?? 0);
            if ($rtg > 0) {
                $rtgmsids[] = $rtg;
            }
        }
    }
    return array_values(array_unique($rtgmsids));
}

function fetchMachineNames(array $machineIds)
{
    $machineNames = [];
    if (empty($machineIds)) {
        return $machineNames;
    }

    try {
        include '../../../koneksi3.php';
        if (!isset($conn3)) {
            return $machineNames;
        }
        $placeholders = implode(',', array_fill(0, count($machineIds), '?'));
        $stmt = $conn3->prepare("SELECT facode, faname FROM famaster WHERE facode IN ($placeholders)");
        $stmt->execute(array_values($machineIds));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $machineNames[trim((string) $row['facode'])] = trim((string) $row['faname']);
        }
    } catch (Throwable $e) {
    }

    return $machineNames;
}

function fetchBreakTimes($conn)
{
    $breakTimes = [];
    $stmt = sqlsrv_query($conn, "SELECT break_time_name FROM dbo.ms_break_time");
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $name = strtoupper(sqlValue($row['break_time_name']));
            if ($name !== '') {
                $breakTimes[] = $name;
            }
        }
    }
    return $breakTimes;
}

function fetchPaddrySettings($conn)
{
    $settings = [];
    if (!tableExists($conn, 'dbo.cpp_paddry_setting')) {
        return $settings;
    }
    $stmt = sqlsrv_query($conn, "SELECT setting_code, setting_value FROM dbo.cpp_paddry_setting");
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $settings[$row['setting_code']] = $row['setting_value'];
        }
    }
    return $settings;
}

function erpStageIsFailed(array $stage)
{
    return strtoupper(trim((string) ($stage['fgresult'] ?? ''))) === 'F';
}

function erpStageIsCompleted(array $stage)
{
    $fgresult = strtoupper(trim((string) ($stage['fgresult'] ?? '')));
    $fgstatus = strtoupper(trim((string) ($stage['fgstatus'] ?? '')));
    $hasStart = !empty($stage['starttime']);
    $hasEnd = !empty($stage['endtime']);
    $prdqty = (float) ($stage['prdqty'] ?? 0);

    return $fgresult === 'P'
        || $fgstatus === 'C'
        || ($hasStart && $hasEnd && $prdqty != 0.0);
}

function erpStageIsTraversed(array $stage)
{
    return erpStageIsCompleted($stage) || erpStageIsFailed($stage);
}

function isBypassPreviousRoutingStage($rtgmsid)
{
    return in_array((int) $rtgmsid, [876, 878], true);
}

function getRequiredPreviousRoutingSets($rtgmsid)
{
    $rtgmsid = (int) $rtgmsid;
    if ($rtgmsid === 877) {
        return [[876]];
    }
    if ($rtgmsid === 879) {
        return [[878]];
    }
    if ($rtgmsid === 880 || $rtgmsid === 887) {
        return [[877, 879]];
    }
    return [];
}

function stageHasStartedForDependency(?array $stageView, ?array $erpStage)
{
    if ($stageView) {
        return !empty($stageView['start_at']) || !empty($stageView['is_running']) || !empty($stageView['is_passed']) || !empty($stageView['is_failed']);
    }

    if ($erpStage) {
        return !empty($erpStage['starttime']) || erpStageIsTraversed($erpStage);
    }

    return false;
}

function stageIsTraversedForDependency(?array $stageView, ?array $erpStage)
{
    if ($stageView) {
        return !empty($stageView['is_passed']) || !empty($stageView['is_failed']) || !empty($stageView['finish_at']);
    }

    if ($erpStage) {
        return erpStageIsTraversed($erpStage) || !empty($erpStage['endtime']);
    }

    return false;
}

function buildStageCompositeKey($rtgseq, $rtgmsid)
{
    return (int) ($rtgseq ?? 0) . '_' . (int) $rtgmsid;
}

function getStageCompositeKey(array $stage)
{
    return buildStageCompositeKey($stage['rtgseq'] ?? 0, $stage['rtgmsid'] ?? 0);
}

function indexStagesByCompositeKey(array $stages)
{
    $indexed = [];
    foreach ($stages as $stage) {
        $indexed[getStageCompositeKey($stage)] = $stage;
    }
    return $indexed;
}

function findLatestStageByRtgmsid(array $stages, $rtgmsid)
{
    $matches = [];
    foreach ($stages as $stage) {
        if ((int) ($stage['rtgmsid'] ?? 0) === (int) $rtgmsid) {
            $matches[] = $stage;
        }
    }
    if (empty($matches)) {
        return null;
    }

    usort($matches, function ($left, $right) {
        $leftSeq = (int) ($left['rtgseq'] ?? 0);
        $rightSeq = (int) ($right['rtgseq'] ?? 0);
        if ($leftSeq !== $rightSeq) {
            return $rightSeq <=> $leftSeq;
        }

        $leftStageNo = (int) ($left['stage_no'] ?? 0);
        $rightStageNo = (int) ($right['stage_no'] ?? 0);
        if ($leftStageNo !== $rightStageNo) {
            return $rightStageNo <=> $leftStageNo;
        }

        return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
    });

    return $matches[0];
}

function findStageByExpected(array $stages, array $expectedStage)
{
    $expectedKey = getStageCompositeKey($expectedStage);
    foreach ($stages as $stage) {
        if (getStageCompositeKey($stage) === $expectedKey) {
            return $stage;
        }
    }

    $expectedRtgmsid = (int) ($expectedStage['rtgmsid'] ?? 0);
    if ($expectedRtgmsid <= 0) {
        return null;
    }

    return findLatestStageByRtgmsid($stages, $expectedRtgmsid);
}

function sortStagesInDisplayOrder(array $stages)
{
    usort($stages, function ($left, $right) {
        $leftStageNo = (int) ($left['stage_no'] ?? 0);
        $rightStageNo = (int) ($right['stage_no'] ?? 0);
        if ($leftStageNo !== $rightStageNo) {
            return $leftStageNo <=> $rightStageNo;
        }

        $leftSeq = (int) ($left['rtgseq'] ?? 0);
        $rightSeq = (int) ($right['rtgseq'] ?? 0);
        if ($leftSeq !== $rightSeq) {
            return $leftSeq <=> $rightSeq;
        }

        return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
    });

    return $stages;
}

function findPreviousMatchingStage(array $orderedStages, array $targetStage, callable $predicate)
{
    $targetStageNo = (int) ($targetStage['stage_no'] ?? 0);
    $targetSeq = (int) ($targetStage['rtgseq'] ?? 0);
    $targetId = (int) ($targetStage['id'] ?? 0);
    $match = null;

    foreach ($orderedStages as $stage) {
        $stageNo = (int) ($stage['stage_no'] ?? 0);
        $stageSeq = (int) ($stage['rtgseq'] ?? 0);
        $stageId = (int) ($stage['id'] ?? 0);
        $isBefore = $stageNo < $targetStageNo
            || ($stageNo === $targetStageNo && $stageSeq < $targetSeq)
            || ($stageNo === $targetStageNo && $stageSeq === $targetSeq && $stageId < $targetId);
        if (!$isBefore) {
            continue;
        }
        if (!$predicate($stage)) {
            continue;
        }
        $match = $stage;
    }

    return $match;
}

function getDependencyBlockedMessage($rtgmsid, array $stageViews, array $erpStagesByRtg, ?array $targetStage = null)
{
    $requiredSets = getRequiredPreviousRoutingSets($rtgmsid);
    if (empty($requiredSets)) {
        return '';
    }

    $orderedStages = sortStagesInDisplayOrder($stageViews);

    foreach ($requiredSets as $requiredSet) {
        foreach ($requiredSet as $requiredRtgmsid) {
            if ($targetStage) {
                $requiredStageView = findPreviousMatchingStage($orderedStages, $targetStage, function ($stage) use ($requiredRtgmsid) {
                    return (int) ($stage['rtgmsid'] ?? 0) === (int) $requiredRtgmsid;
                });
            } else {
                $requiredStageView = findLatestStageByRtgmsid($stageViews, $requiredRtgmsid);
            }
            $requiredErpStage = $requiredStageView
                ? ($erpStagesByRtg[getStageCompositeKey($requiredStageView)] ?? findLatestStageByRtgmsid($erpStagesByRtg, $requiredRtgmsid))
                : findLatestStageByRtgmsid($erpStagesByRtg, $requiredRtgmsid);
            $isSatisfied = stageIsTraversedForDependency($requiredStageView, $requiredErpStage);
            if ($isSatisfied) {
                return '';
            }
        }
    }

    $requiredNames = [];
    foreach ($requiredSets[0] as $requiredRtgmsid) {
        $requiredRtgmsid = (int) $requiredRtgmsid;
        $requiredStageView = findLatestStageByRtgmsid($stageViews, $requiredRtgmsid);
        $requiredErpStage = findLatestStageByRtgmsid($erpStagesByRtg, $requiredRtgmsid);
        $requiredNames[] = trim((string) ((($requiredStageView['rtgname'] ?? null))
            ?: (($requiredErpStage['rtgname'] ?? null))
            ?: (TARGET_STAGE_RTGS[$requiredRtgmsid] ?? ('MSID ' . $requiredRtgmsid))));
    }

    if (count($requiredNames) === 1) {
        return 'Harus melewati routing ' . $requiredNames[0] . ' terlebih dahulu';
    }

    return 'Harus melewati routing ' . implode(' atau ', $requiredNames) . ' terlebih dahulu';
}

function getStageBlockedMessage(array $targetStage, array $stageViews, array $erpStagesByRtg)
{
    $orderedStages = sortStagesInDisplayOrder($stageViews);
    $rtgmsid = (int) ($targetStage['rtgmsid'] ?? 0);

    $previousSameRouting = findPreviousMatchingStage($orderedStages, $targetStage, function ($stage) use ($rtgmsid) {
        return (int) ($stage['rtgmsid'] ?? 0) === $rtgmsid;
    });
    if ($previousSameRouting) {
        $previousSameErpStage = $erpStagesByRtg[getStageCompositeKey($previousSameRouting)] ?? findLatestStageByRtgmsid($erpStagesByRtg, $rtgmsid);
        if (!stageIsTraversedForDependency($previousSameRouting, $previousSameErpStage)) {
            return 'Tahap ini tidak bisa dijalankan sebelum tahap sebelumnya selesai';
        }
    }

    return getDependencyBlockedMessage($rtgmsid, $stageViews, $erpStagesByRtg, $targetStage);
}

function usesRtgSeqOneMaterialSource($rtgmsid)
{
    return in_array((int) $rtgmsid, [876, 877, 878, 879, 880, 887], true);
}

function userHasSpecialTargetTrustee(array $authorizedRtgmsids)
{
    foreach ($authorizedRtgmsids as $rtgmsid) {
        if (usesRtgSeqOneMaterialSource((int) $rtgmsid)) {
            return true;
        }
    }
    return false;
}

function getCustomTargetExpectedStage(array $stageViews, array $erpStagesByRtg)
{
    $orderedStages = sortStagesInDisplayOrder($stageViews);

    foreach ($orderedStages as $stageView) {
        $rtgmsid = (int) ($stageView['rtgmsid'] ?? 0);
        if (!in_array($rtgmsid, [876, 877, 878, 879, 880, 887], true)) {
            continue;
        }
        if (stageHasStartedForDependency($stageView, null) && !stageIsTraversedForDependency($stageView, null)) {
            continue;
        }
        if (stageIsTraversedForDependency($stageView, null)) {
            continue;
        }

        $blockedMessage = getStageBlockedMessage($stageView, $stageViews, $erpStagesByRtg);
        if ($blockedMessage !== '') {
            continue;
        }

        return [
            'rtgmsid' => $rtgmsid,
            'rtgseq' => $stageView['rtgseq'] ?? null,
            'rtgname' => trim((string) ($stageView['rtgname'] ?? (TARGET_STAGE_RTGS[$rtgmsid] ?? ''))),
        ];
    }

    return null;
}

function findSpecialTrusteeSelectedStage(array $authorizedRtgmsids, array $stageViews, array $erpStagesByRtg)
{
    foreach ($stageViews as $stageView) {
        if (!in_array((int) ($stageView['rtgmsid'] ?? 0), $authorizedRtgmsids, true)) {
            continue;
        }
        if (!empty($stageView['is_running'])) {
            return $stageView;
        }
    }

    $candidates = [];
    foreach ($stageViews as $stageView) {
        $rtgmsid = (int) ($stageView['rtgmsid'] ?? 0);
        if (!in_array($rtgmsid, $authorizedRtgmsids, true)) {
            continue;
        }
        if (!empty($stageView['is_passed']) || !empty($stageView['is_failed']) || !empty($stageView['start_at'])) {
            continue;
        }
        if (getStageBlockedMessage($stageView, $stageViews, $erpStagesByRtg) !== '') {
            continue;
        }
        $candidates[] = $stageView;
    }

    if (!empty($candidates)) {
        usort($candidates, function ($left, $right) {
            $leftStageNo = (int) ($left['stage_no'] ?? 0);
            $rightStageNo = (int) ($right['stage_no'] ?? 0);
            if ($leftStageNo !== $rightStageNo) {
                return $rightStageNo <=> $leftStageNo;
            }

            $leftSeq = (int) ($left['rtgseq'] ?? 0);
            $rightSeq = (int) ($right['rtgseq'] ?? 0);
            if ($leftSeq !== $rightSeq) {
                return $rightSeq <=> $leftSeq;
            }

            return ((int) ($right['rtgmsid'] ?? 0)) <=> ((int) ($left['rtgmsid'] ?? 0));
        });
        return $candidates[0];
    }

    return null;
}

function erpStageNeedsInitialMaterialCompletion(array $stage)
{
    $prdqty = (float) ($stage['prdqty'] ?? 0);
    $prduomid = isset($stage['prduomid']) ? (int) $stage['prduomid'] : null;
    $prdstdqty = (float) ($stage['prdstdqty'] ?? 0);
    $prdstduomid = isset($stage['prdstduomid']) ? (int) $stage['prdstduomid'] : null;
    $fgresult = strtoupper(trim((string) ($stage['fgresult'] ?? '')));

    return $prdqty == 0.0 || empty($prduomid) || $prdstdqty == 0.0 || empty($prdstduomid) || $fgresult === '' || $fgresult === '0';
}

function fetchErpActiveRouting(array $cpNos)
{
    $result = [];
    if (empty($cpNos)) {
        return $result;
    }

    try {
        include '../../../koneksi3.php';
        if (!isset($conn3)) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($cpNos), '?'));
        $sql = "
            SELECT
                phd.prdnmbr,
                rtg.rtgmsid,
                ms.rtgname,
                rtg.rtgseq,
                rtg.starttime,
                rtg.endtime,
                rtg.fgresult,
                rtg.fgstatus
            FROM pdproductionhd phd
            INNER JOIN pdproductionrtg rtg ON rtg.productionhdid = phd.productionhdid
            LEFT JOIN pdrtgms ms ON ms.rtgmsid = rtg.rtgmsid
            WHERE phd.prdnmbr IN ($placeholders)
            ORDER BY phd.prdnmbr ASC, rtg.rtgseq ASC
        ";
        $stmt = $conn3->prepare($sql);
        $stmt->execute(array_values($cpNos));

        $routingsByCp = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cpNo = trim((string) $row['prdnmbr']);
            if ($cpNo === '')
                continue;
            $routingsByCp[$cpNo][] = $row;
        }

        foreach ($routingsByCp as $cpNo => $routings) {
            $currentStage = null;

            foreach ($routings as $rtg) {
                $fgstatus = strtoupper(trim((string) ($rtg['fgstatus'] ?? '')));
                $hasStart = !empty($rtg['starttime']);
                $hasEnd = !empty($rtg['endtime']);
                $isRunning = ($fgstatus === 'P' || ($hasStart && !$hasEnd));

                if ($isRunning) {
                    $currentStage = $rtg;
                    break;
                }

                // Stage fail dianggap sudah dilalui agar routing aktif bisa lanjut ke step berikutnya.
                if (erpStageIsTraversed($rtg)) {
                    continue;
                }

                // Jika statusnya waiting
                $currentStage = $rtg;
                break;
            }

            if ($currentStage === null && !empty($routings)) {
                $currentStage = end($routings);
            }

            if ($currentStage) {
                $result[$cpNo] = [
                    'rtgname' => trim((string) ($currentStage['rtgname'] ?? '')),
                    'rtgmsid' => (int) ($currentStage['rtgmsid'] ?? 0)
                ];
            }
        }
    } catch (Throwable $e) {
    }

    return $result;
}

function fetchErpStagesByCpNos(array $cpNos)
{
    $result = [];
    if (empty($cpNos)) {
        return $result;
    }

    try {
        include '../../../koneksi3.php';
        if (!isset($conn3)) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($cpNos), '?'));
        $targetIds = implode(',', array_map('intval', array_keys(TARGET_STAGE_RTGS)));
        $sql = "
            SELECT
                phd.prdnmbr,
                phd.productionhdid,
                rtg.productionrtgid,
                rtg.rtgseq,
                rtg.rtgmsid,
                COALESCE(ms.rtgname, '') AS rtgname,
                rtg.starttime,
                rtg.endtime,
                rtg.prdqty,
                rtg.prduomid,
                rtg.prdstdqty,
                rtg.prdstduomid,
                rtg.fgresult,
                rtg.fgstatus,
                rtg.failmsid,
                rtg.resultdesc
            FROM pdproductionhd phd
            INNER JOIN pdproductionrtg rtg ON rtg.productionhdid = phd.productionhdid
            LEFT JOIN pdrtgms ms ON ms.rtgmsid = rtg.rtgmsid
            WHERE phd.prdnmbr IN ($placeholders)
              AND rtg.rtgmsid IN ($targetIds)
            ORDER BY phd.prdnmbr ASC, rtg.rtgseq ASC
        ";
        $stmt = $conn3->prepare($sql);
        $stmt->execute(array_values($cpNos));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cpNo = trim((string) $row['prdnmbr']);
            if ($cpNo === '') {
                continue;
            }
            $result[$cpNo][] = [
                'cp_no' => $cpNo,
                'productionhdid' => (int) ($row['productionhdid'] ?? 0),
                'productionrtgid' => (int) ($row['productionrtgid'] ?? 0),
                'rtgseq' => isset($row['rtgseq']) ? (int) $row['rtgseq'] : null,
                'rtgmsid' => (int) ($row['rtgmsid'] ?? 0),
                'rtgname' => trim((string) ($row['rtgname'] ?? TARGET_STAGE_RTGS[(int) ($row['rtgmsid'] ?? 0)] ?? '')),
                'starttime' => formatSqlsrvDateTime($row['starttime']),
                'endtime' => formatSqlsrvDateTime($row['endtime']),
                'prdqty' => isset($row['prdqty']) ? (float) $row['prdqty'] : 0,
                'prduomid' => isset($row['prduomid']) ? (int) $row['prduomid'] : null,
                'prdstdqty' => isset($row['prdstdqty']) ? (float) $row['prdstdqty'] : 0,
                'prdstduomid' => isset($row['prdstduomid']) ? (int) $row['prdstduomid'] : null,
                'fgresult' => sqlValue($row['fgresult']),
                'fgstatus' => sqlValue($row['fgstatus']),
                'failmsid' => isset($row['failmsid']) ? (int) $row['failmsid'] : null,
                'resultdesc' => sqlValue($row['resultdesc']),
            ];
        }
    } catch (Throwable $e) {
    }

    return $result;
}

function fetchErpExpectedNextStages(array $cpNos)
{
    $result = [];
    if (empty($cpNos)) {
        return $result;
    }
    try {
        include '../../../koneksi3.php';
        if (!isset($conn3)) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($cpNos), '?'));
        $sql = "
            SELECT hd.prdnmbr, rtg.rtgmsid, ms.rtgname, rtg.prdqty, rtg.fgresult, rtg.endtime
            FROM pdproductionrtg rtg
            JOIN pdproductionhd hd ON hd.productionhdid = rtg.productionhdid
            LEFT JOIN pdrtgms ms ON ms.rtgmsid = rtg.rtgmsid
            WHERE hd.prdnmbr IN ($placeholders)
            ORDER BY hd.prdnmbr ASC, rtg.rtgseq ASC
        ";
        $stmt = $conn3->prepare($sql);
        $stmt->execute(array_values($cpNos));
        $allStages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($allStages as $row) {
            $cp = trim((string) $row['prdnmbr']);
            $grouped[$cp][] = $row;
        }

        foreach ($grouped as $cp => $stages) {
            foreach ($stages as $s) {
                if (!erpStageIsTraversed($s)) {
                    $result[$cp] = [
                        'rtgmsid' => (int) $s['rtgmsid'],
                        'rtgname' => trim((string) $s['rtgname'])
                    ];
                    break;
                }
            }
        }
    } catch (Throwable $e) {
    }
    return $result;
}

function fetchErpOrderedStagesForDisplay(array $cpNos)
{
    $result = [];
    if (empty($cpNos)) {
        return $result;
    }

    try {
        include '../../../koneksi3.php';
        if (!isset($conn3)) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($cpNos), '?'));
        $sql = "
            SELECT
                hd.prdnmbr,
                hd.fgstatus AS hd_fgstatus,
                rtg.rtgmsid,
                ms.rtgname,
                rtg.rtgseq,
                rtg.prdqty,
                rtg.prduomid,
                rtg.prdstdqty,
                rtg.prdstduomid,
                rtg.fgresult
            FROM pdproductionrtg rtg
            JOIN pdproductionhd hd ON hd.productionhdid = rtg.productionhdid
            LEFT JOIN pdrtgms ms ON ms.rtgmsid = rtg.rtgmsid
            WHERE hd.prdnmbr IN ($placeholders)
            ORDER BY hd.prdnmbr ASC, rtg.rtgseq ASC, rtg.rtgmsid ASC
        ";
        $stmt = $conn3->prepare($sql);
        $stmt->execute(array_values($cpNos));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cpNo = trim((string) ($row['prdnmbr'] ?? ''));
            if ($cpNo === '') {
                continue;
            }
            $result[$cpNo][] = [
                'rtgmsid' => (int) ($row['rtgmsid'] ?? 0),
                'rtgname' => trim((string) ($row['rtgname'] ?? '')),
                'rtgseq' => isset($row['rtgseq']) ? (int) $row['rtgseq'] : null,
                'hd_fgstatus' => sqlValue($row['hd_fgstatus']),
                'prdqty' => isset($row['prdqty']) ? (float) $row['prdqty'] : 0,
                'prduomid' => isset($row['prduomid']) ? (int) $row['prduomid'] : null,
                'prdstdqty' => isset($row['prdstdqty']) ? (float) $row['prdstdqty'] : 0,
                'prdstduomid' => isset($row['prdstduomid']) ? (int) $row['prdstduomid'] : null,
                'fgresult' => sqlValue($row['fgresult']),
            ];
        }
    } catch (Throwable $e) {
    }

    return $result;
}

function determineProcessExpectedStage($cpNo, array $stageViews, array $erpStagesByRtg, array $erpOrderedStages, $allowSpecialTargetFlow = true)
{
    if ($allowSpecialTargetFlow) {
        $customExpectedStage = getCustomTargetExpectedStage($stageViews, $erpStagesByRtg);
        if ($customExpectedStage) {
            return $customExpectedStage;
        }
    }

    foreach ($erpOrderedStages[$cpNo] ?? [] as $erpStage) {
        if (erpStageNeedsInitialMaterialCompletion($erpStage)) {
            return [
                'rtgmsid' => (int) ($erpStage['rtgmsid'] ?? 0),
                'rtgseq' => isset($erpStage['rtgseq']) ? (int) $erpStage['rtgseq'] : null,
                'rtgname' => trim((string) ($erpStage['rtgname'] ?? '')),
            ];
        }
    }

    return null;
}

function syncLocalStages($conn, array $cpRowsById, array $erpStagesByCp, $breakTimes, $currUser)
{
    if (empty($cpRowsById)) {
        return;
    }

    $paddryIds = array_keys($cpRowsById);
    $placeholders = implode(',', array_fill(0, count($paddryIds), '?'));
    $existing = [];
    $existingByProductionRtgId = [];
    $stmtExisting = sqlsrv_query($conn, "
        SELECT
            s.id,
            s.paddry_id,
            s.rtgmsid,
            s.rtgseq,
            s.erp_productionrtgid,
            s.start_at,
            s.finish_at,
            s.fgresult,
            (
                SELECT COUNT(1)
                FROM dbo.cpp_paddry_downtime d
                WHERE d.paddry_stage_id = s.id
            ) AS downtime_count
        FROM dbo.cpp_paddry_stage s
        WHERE s.paddry_id IN ($placeholders)
    ", array_values($paddryIds));
    if ($stmtExisting) {
        while ($row = sqlsrv_fetch_array($stmtExisting, SQLSRV_FETCH_ASSOC)) {
            $rowData = [
                'id' => (int) $row['id'],
                'paddry_id' => (int) $row['paddry_id'],
                'rtgmsid' => (int) ($row['rtgmsid'] ?? 0),
                'rtgseq' => isset($row['rtgseq']) ? (int) $row['rtgseq'] : null,
                'erp_productionrtgid' => isset($row['erp_productionrtgid']) ? (int) $row['erp_productionrtgid'] : null,
                'has_activity' => !empty($row['start_at']) || !empty($row['finish_at']) || trim((string) ($row['fgresult'] ?? '')) !== '' || (int) ($row['downtime_count'] ?? 0) > 0,
            ];
            $existing[(int) $row['paddry_id'] . '_' . buildStageCompositeKey($row['rtgseq'] ?? 0, $row['rtgmsid'] ?? 0)] = $rowData;
            $prodRtgId = (int) ($row['erp_productionrtgid'] ?? 0);
            if ($prodRtgId > 0) {
                if (!isset($existingByProductionRtgId[(int) $row['paddry_id']])) {
                    $existingByProductionRtgId[(int) $row['paddry_id']] = [];
                }
                if (!isset($existingByProductionRtgId[(int) $row['paddry_id']][$prodRtgId])) {
                    $existingByProductionRtgId[(int) $row['paddry_id']][$prodRtgId] = [];
                }
                $existingByProductionRtgId[(int) $row['paddry_id']][$prodRtgId][] = $rowData;
            }
        }
    }

    foreach ($cpRowsById as $paddryId => $cpRow) {
        $cpNo = trim((string) ($cpRow['cp_no'] ?? ''));
        if ($cpNo === '') {
            continue;
        }

        if (!empty($existingByProductionRtgId[$paddryId])) {
            foreach ($existingByProductionRtgId[$paddryId] as $prodRtgId => $rows) {
                if (count($rows) <= 1) {
                    continue;
                }

                usort($rows, function ($left, $right) {
                    $leftActivity = !empty($left['has_activity']) ? 1 : 0;
                    $rightActivity = !empty($right['has_activity']) ? 1 : 0;
                    if ($leftActivity !== $rightActivity) {
                        return $rightActivity <=> $leftActivity;
                    }
                    $leftSeq = (int) ($left['rtgseq'] ?? 0);
                    $rightSeq = (int) ($right['rtgseq'] ?? 0);
                    if ($leftSeq !== $rightSeq) {
                        return $leftSeq <=> $rightSeq;
                    }
                    return ((int) $left['id']) <=> ((int) $right['id']);
                });

                $keeper = array_shift($rows);
                foreach ($rows as $duplicateRow) {
                    if (!empty($duplicateRow['has_activity'])) {
                        continue;
                    }
                    sqlsrv_query($conn, "DELETE FROM dbo.cpp_paddry_stage WHERE id = ?", [(int) $duplicateRow['id']]);
                    $existingKey = (int) $duplicateRow['paddry_id'] . '_' . buildStageCompositeKey($duplicateRow['rtgseq'] ?? 0, $duplicateRow['rtgmsid'] ?? 0);
                    if (isset($existing[$existingKey]) && (int) $existing[$existingKey]['id'] === (int) $duplicateRow['id']) {
                        unset($existing[$existingKey]);
                    }
                }
                $existingByProductionRtgId[$paddryId][$prodRtgId] = array_merge([$keeper], array_values(array_filter($rows, function ($row) {
                    return !empty($row['has_activity']);
                })));
            }
        }

        $stagesToSync = $erpStagesByCp[$cpNo] ?? [];

        // Handle Breaktime virtual stage
        if (empty($stagesToSync)) {
            $isBt = false;
            $upperCp = strtoupper($cpNo);
            foreach ($breakTimes as $bt) {
                if (strpos($upperCp, $bt) !== false) {
                    $isBt = true;
                    break;
                }
            }
            if ($isBt) {
                $stagesToSync[] = [
                    'rtgmsid' => 999,
                    'rtgseq' => 1,
                    'rtgname' => 'BREAKTIME',
                    'productionhdid' => null,
                    'productionrtgid' => null
                ];
            }
        }

        if (empty($stagesToSync)) {
            continue;
        }

        $stageNo = 1;
        $matchedStageIds = [];
        $maxErpSeq = 0;
        foreach ($stagesToSync as $erpStage) {
            $maxErpSeq = max($maxErpSeq, (int) ($erpStage['rtgseq'] ?? 0));
            $key = (int) $paddryId . '_' . buildStageCompositeKey($erpStage['rtgseq'] ?? 0, $erpStage['rtgmsid'] ?? 0);
            $matchedExisting = null;
            $prodRtgId = (int) ($erpStage['productionrtgid'] ?? 0);
            if ($prodRtgId > 0 && !empty($existingByProductionRtgId[$paddryId][$prodRtgId])) {
                $candidates = $existingByProductionRtgId[$paddryId][$prodRtgId];
                usort($candidates, function ($left, $right) {
                    $leftActivity = !empty($left['has_activity']) ? 1 : 0;
                    $rightActivity = !empty($right['has_activity']) ? 1 : 0;
                    if ($leftActivity !== $rightActivity) {
                        return $rightActivity <=> $leftActivity;
                    }
                    return ((int) $left['id']) <=> ((int) $right['id']);
                });
                foreach ($candidates as $candidate) {
                    if (!in_array((int) $candidate['id'], $matchedStageIds, true)) {
                        $matchedExisting = $candidate;
                        break;
                    }
                }
            }
            if ($matchedExisting === null && isset($existing[$key]) && !in_array((int) $existing[$key]['id'], $matchedStageIds, true)) {
                $matchedExisting = $existing[$key];
            }

            if ($matchedExisting === null) {
                $insertSql = "
                    INSERT INTO dbo.cpp_paddry_stage (
                        paddry_id, cp_no, stage_no, rtgmsid, rtgseq, rtgname,
                        erp_productionhdid, erp_productionrtgid,
                        created_at, created_by, updated_at, updated_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, GETDATE(), ?)
                ";
                sqlsrv_query($conn, $insertSql, [
                    $paddryId,
                    $cpNo,
                    $stageNo,
                    $erpStage['rtgmsid'],
                    $erpStage['rtgseq'],
                    $erpStage['rtgname'],
                    $erpStage['productionhdid'],
                    $erpStage['productionrtgid'],
                    $currUser,
                    $currUser,
                ]);
            } else {
                $matchedStageIds[] = (int) $matchedExisting['id'];
                $updateSql = "
                    UPDATE dbo.cpp_paddry_stage
                    SET stage_no = ?,
                        rtgseq = ?,
                        rtgname = ?,
                        erp_productionhdid = ?,
                        erp_productionrtgid = ?,
                        updated_at = GETDATE(),
                        updated_by = ?
                    WHERE id = ?
                ";
                sqlsrv_query($conn, $updateSql, [
                    $stageNo,
                    $erpStage['rtgseq'],
                    $erpStage['rtgname'],
                    $erpStage['productionhdid'],
                    $erpStage['productionrtgid'],
                    $currUser,
                    $matchedExisting['id'],
                ]);
            }
            $stageNo++;
        }

        $legacyRows = [];
        foreach ($existing as $existingRow) {
            if ((int) $existingRow['paddry_id'] !== (int) $paddryId) {
                continue;
            }
            if (in_array((int) $existingRow['id'], $matchedStageIds, true)) {
                continue;
            }
            $legacyRows[] = $existingRow;
        }
        usort($legacyRows, function ($left, $right) {
            $leftActivity = !empty($left['has_activity']) ? 1 : 0;
            $rightActivity = !empty($right['has_activity']) ? 1 : 0;
            if ($leftActivity !== $rightActivity) {
                return $rightActivity <=> $leftActivity;
            }
            $leftSeq = (int) ($left['rtgseq'] ?? 0);
            $rightSeq = (int) ($right['rtgseq'] ?? 0);
            if ($leftSeq !== $rightSeq) {
                return $leftSeq <=> $rightSeq;
            }
            return ((int) $left['id']) <=> ((int) $right['id']);
        });

        $legacySeq = $maxErpSeq;
        foreach ($legacyRows as $legacyRow) {
            $legacySeq++;
            sqlsrv_query($conn, "
                UPDATE dbo.cpp_paddry_stage
                SET rtgseq = ?,
                    updated_at = GETDATE(),
                    updated_by = ?
                WHERE id = ?
            ", [$legacySeq, $currUser, (int) $legacyRow['id']]);
        }

        $stmtResequence = sqlsrv_query($conn, "
            SELECT id
            FROM dbo.cpp_paddry_stage
            WHERE paddry_id = ?
            ORDER BY
                CASE WHEN rtgseq IS NULL THEN 1 ELSE 0 END ASC,
                rtgseq ASC,
                rtgmsid ASC,
                id ASC
        ", [$paddryId]);
        if ($stmtResequence) {
            $newStageNo = 1;
            while ($row = sqlsrv_fetch_array($stmtResequence, SQLSRV_FETCH_ASSOC)) {
                sqlsrv_query($conn, "
                    UPDATE dbo.cpp_paddry_stage
                    SET stage_no = ?,
                        updated_at = GETDATE(),
                        updated_by = ?
                    WHERE id = ?
                ", [$newStageNo, $currUser, (int) $row['id']]);
                $newStageNo++;
            }
        }
    }
}

function fetchLocalStages($conn, array $paddryIds)
{
    $result = [];
    if (empty($paddryIds)) {
        return $result;
    }

    $holders = implode(',', array_fill(0, count($paddryIds), '?'));
    $sql = "
        SELECT
            id,
            paddry_id,
            cp_no,
            stage_no,
            rtgmsid,
            rtgseq,
            rtgname,
            start_at,
            finish_at,
            shift_start_id,
            shift_start_name,
            shift_end_id,
            shift_end_name,
            wheel_no,
            wheel_msid,
            lebar_kain,
            fgresult,
            fail_code,
            fail_msid,
            fail_desc,
            keterangan_fail,
            erp_productionhdid,
            erp_productionrtgid,
            origin_system
        FROM dbo.cpp_paddry_stage
        WHERE paddry_id IN ($holders)
        ORDER BY paddry_id ASC, stage_no ASC, rtgseq ASC, rtgmsid ASC
    ";
    $stmt = sqlsrv_query($conn, $sql, array_values($paddryIds));
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $paddryId = (int) $row['paddry_id'];
            $result[$paddryId][] = [
                'id' => (int) $row['id'],
                'paddry_id' => $paddryId,
                'cp_no' => sqlValue($row['cp_no']),
                'stage_no' => (int) ($row['stage_no'] ?? 0),
                'rtgmsid' => (int) ($row['rtgmsid'] ?? 0),
                'rtgseq' => isset($row['rtgseq']) ? (int) $row['rtgseq'] : null,
                'rtgname' => sqlValue($row['rtgname']),
                'start_at' => formatSqlsrvDateTime($row['start_at']),
                'finish_at' => formatSqlsrvDateTime($row['finish_at']),
                'shift_start_id' => $row['shift_start_id'],
                'shift_start_name' => sqlValue($row['shift_start_name']),
                'shift_end_id' => $row['shift_end_id'],
                'shift_end_name' => sqlValue($row['shift_end_name']),
                'wheel_no' => sqlValue($row['wheel_no']),
                'wheel_msid' => $row['wheel_msid'],
                'lebar_kain' => $row['lebar_kain'],
                'fgresult' => sqlValue($row['fgresult']),
                'fail_code' => sqlValue($row['fail_code']),
                'fail_msid' => $row['fail_msid'],
                'fail_desc' => sqlValue($row['fail_desc']),
                'keterangan_fail' => sqlValue($row['keterangan_fail']),
                'erp_productionhdid' => $row['erp_productionhdid'],
                'erp_productionrtgid' => $row['erp_productionrtgid'],
                'origin_system' => sqlValue($row['origin_system']),
            ];
        }
    }

    return $result;
}

function fetchDowntimeMetricsByStage($conn, array $stageIds)
{
    $metrics = [];
    if (empty($stageIds)) {
        return $metrics;
    }

    $holders = implode(',', array_fill(0, count($stageIds), '?'));
    $sql = "
        SELECT
            paddry_stage_id,
            id,
            kd_downtime,
            nm_downtime,
            waktu_start,
            waktu_stop,
            DATEDIFF(SECOND, waktu_start, ISNULL(waktu_stop, GETDATE())) AS duration_secs
        FROM dbo.cpp_paddry_downtime
        WHERE paddry_stage_id IN ($holders)
        ORDER BY paddry_stage_id ASC, id DESC
    ";
    $stmt = sqlsrv_query($conn, $sql, array_values($stageIds));
    if (!$stmt) {
        return $metrics;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stageId = (int) $row['paddry_stage_id'];
        if (!isset($metrics[$stageId])) {
            $metrics[$stageId] = [
                'active_downtime_id' => null,
                'active_downtime_name' => null,
                'active_downtime_code' => null,
                'active_downtime_start' => null,
                'total_downtime_seconds' => 0,
            ];
        }

        $metrics[$stageId]['total_downtime_seconds'] += (int) ($row['duration_secs'] ?? 0);
        if ($row['waktu_stop'] === null && $metrics[$stageId]['active_downtime_id'] === null) {
            $metrics[$stageId]['active_downtime_id'] = (int) $row['id'];
            $metrics[$stageId]['active_downtime_name'] = sqlValue($row['nm_downtime']);
            $metrics[$stageId]['active_downtime_code'] = sqlValue($row['kd_downtime']);
            $metrics[$stageId]['active_downtime_start'] = formatSqlsrvDateTime($row['waktu_start'], 'H:i:s');
        }
    }

    return $metrics;
}

function resolveStageView(array $localStage, ?array $erpStage = null, ?array $downtime = null)
{
    $isBreaktime = ((int) $localStage['rtgmsid'] === 999);
    $localFgresult = strtoupper($localStage['fgresult'] ?? '');
    $localStartAt = $localStage['start_at'] ?? null;
    $localFinishAt = $localStage['finish_at'] ?? null;

    if ($isBreaktime) {
        $fgresult = $localFgresult;
        $startAt = $localStartAt;
        $finishAt = $localFinishAt;
        $statusSource = 'local';
    } else {
        $erpFgresult = strtoupper($erpStage['fgresult'] ?? '');
        $erpStartAt = $erpStage['starttime'] ?? null;
        $erpFinishAt = $erpStage['endtime'] ?? null;

        // Jika tahap dimulai dari SupportApp tapi diselesaikan di ERP/Proint,
        // anggap status akhir mengikuti ERP agar proses bisa lanjut.
        if (!empty($localStartAt) && empty($localFinishAt) && !empty($erpFinishAt)) {
            $fgresult = $erpFgresult;
            $startAt = $erpStartAt ?: $localStartAt;
            $finishAt = $erpFinishAt;
            $statusSource = 'erp_finished_after_local_start';
        // Prioritaskan progres lokal saat operator sudah START namun belum STOP.
        // Ini mencegah UI lompat ke stage berikutnya saat ERP lebih dulu terlihat selesai.
        } elseif (!empty($localStartAt) && empty($localFinishAt)) {
            $fgresult = $localFgresult;
            $startAt = $localStartAt;
            $finishAt = null;
            $statusSource = 'local_running';
        } elseif (!empty($localFinishAt) || in_array($localFgresult, ['P', 'PASS', 'F', 'FAIL'], true)) {
            $fgresult = $localFgresult;
            $startAt = $localStartAt ?: $erpStartAt;
            $finishAt = $localFinishAt;
            $statusSource = 'local_finished';
        } else {
            $fgresult = $erpFgresult;
            $startAt = $erpStartAt;
            $finishAt = $erpFinishAt;
            $statusSource = 'erp';
        }
    }

    $prdqty = $erpStage ? (float) ($erpStage['prdqty'] ?? 0) : 0.0;
    $isPassed = ($fgresult === 'P' || $fgresult === 'PASS')
        && !empty($finishAt)
        && ($prdqty != 0.0 || $isBreaktime || $statusSource === 'local_finished');
    $isFailed = ($fgresult === 'F' || $fgresult === 'FAIL');
    $isRunning = !empty($startAt) && empty($finishAt) && !$isFailed;

    // Deteksi Origin System (Proint vs Dashboard)
    $origin = $localStage['origin_system'];
    if ($origin === '' && !empty($startAt)) {
        // Jika sudah start tapi origin kosong, berarti ditembak lewat Proint
        $origin = 'PROINT';
    }
    $isSupportAppFailed = !$isBreaktime && $localFgresult === 'F' && strtoupper($origin) === 'SUPPORTAPP';

    $status = 'waiting';
    if ($isSupportAppFailed || $isFailed) {
        $status = 'failed';
    } elseif ($isPassed) {
        $status = 'done';
    } elseif ($isRunning) {
        $status = 'running';
    }

    return [
        'id' => $localStage['id'],
        'stage_no' => (int) $localStage['stage_no'],
        'rtgmsid' => (int) $localStage['rtgmsid'],
        'rtgseq' => $localStage['rtgseq'] ?? ($erpStage['rtgseq'] ?? null),
        'rtgname' => $localStage['rtgname'] ?: ($erpStage['rtgname'] ?? ''),
        'start_at' => $startAt,
        'finish_at' => $finishAt,
        'shift_start_id' => $localStage['shift_start_id'],
        'shift_start_name' => $localStage['shift_start_name'],
        'shift_end_id' => $localStage['shift_end_id'],
        'shift_end_name' => $localStage['shift_end_name'],
        'wheel_no' => $localStage['wheel_no'],
        'wheel_msid' => $localStage['wheel_msid'],
        'lebar_kain' => $localStage['lebar_kain'],
        'fgresult' => $fgresult,
        'fail_code' => $localStage['fail_code'],
        'fail_msid' => $localStage['fail_msid'] ?: ($erpStage['failmsid'] ?? null),
        'fail_desc' => $localStage['fail_desc'] ?: ($erpStage['resultdesc'] ?? ''),
        'keterangan_fail' => $localStage['keterangan_fail'],
        'prdqty' => $prdqty,
        'is_passed' => $isPassed,
        'is_failed' => $isFailed,
        'is_support_app_failed' => $isSupportAppFailed,
        'is_running' => $isRunning,
        'status' => $status,
        'erp_productionhdid' => $localStage['erp_productionhdid'] ?: ($erpStage['productionhdid'] ?? null),
        'erp_productionrtgid' => $localStage['erp_productionrtgid'] ?: ($erpStage['productionrtgid'] ?? null),
        'active_downtime_id' => $downtime['active_downtime_id'] ?? null,
        'active_downtime_name' => $downtime['active_downtime_name'] ?? null,
        'active_downtime_code' => $downtime['active_downtime_code'] ?? null,
        'active_downtime_start' => $downtime['active_downtime_start'] ?? null,
        'total_downtime_seconds' => (int) ($downtime['total_downtime_seconds'] ?? 0),
        'origin_system' => $origin,
    ];
}

function determineCurrentStage(array $stages)
{
    $currentStage = null;
    $lastPassed = null;
    $blockedMessage = '';
    $hasFailed = false;
    $lastFailed = null;

    foreach ($stages as $idx => $stage) {
        if (!empty($stage['is_support_app_failed'])) {
            $hasFailed = true;
            $currentStage = $stage;
            $lastFailed = $stage;
            break;
        }
        if ($stage['is_running']) {
            $currentStage = $stage;
            break;
        }
        if ($stage['is_passed']) {
            $lastPassed = $stage;
            continue;
        }
        if ($stage['is_failed']) {
            $lastFailed = $stage;
            continue;
        }
        $currentStage = $stage;
        if ($idx > 0) {
            $prev = $stages[$idx - 1];
            if (!$prev['is_passed'] && !$prev['is_failed'] && empty($prev['is_support_app_failed'])) {
                $blockedMessage = 'Harus tembak routing ' . $prev['rtgname'] . ' terlebih dahulu';
            }
        }
        break;
    }

    if ($currentStage === null && !empty($stages)) {
        $currentStage = end($stages);
    }

    if ($currentStage && $currentStage['is_failed']) {
        $hasFailed = true;
    } elseif ($currentStage === null && $lastFailed !== null) {
        $hasFailed = true;
    }

    $completedCount = 0;
    foreach ($stages as $stage) {
        if ($stage['is_passed']) {
            $completedCount++;
        }
    }

    return [
        'current_stage' => $currentStage,
        'completed_count' => $completedCount,
        'total_count' => count($stages),
        'has_failed' => $hasFailed,
        'blocked_message' => $blockedMessage,
        'last_passed' => $lastPassed,
        'last_failed' => $lastFailed,
    ];
}

function syncToERP($actionType, $dataPayload)
{
    try {
        include '../../../koneksi3.php';
        if (!isset($conn3)) {
            return ['success' => false, 'message' => 'Koneksi ke koneksi3.php gagal.'];
        }

        $cpno = trim((string) ($dataPayload['cp_no'] ?? ''));
        $rtgmsid = (int) ($dataPayload['rtgmsid'] ?? 0);
        $username = $_SESSION['UserName'] ?? '';

        if ($cpno === '') {
            return ['success' => false, 'message' => 'CP No kosong.'];
        }
        if ($rtgmsid === 999) {
            return ['success' => true, 'message' => 'Breaktime skipped ERP sync.'];
        }
        if ($rtgmsid <= 0) {
            return ['success' => false, 'message' => 'Routing tujuan belum dipilih.'];
        }

        $stmtEmp = $conn3->prepare("SELECT empid FROM msuser WHERE userid = ?");
        $stmtEmp->execute([$username]);
        $empRow = $stmtEmp->fetch(PDO::FETCH_ASSOC);
        if (!$empRow || !$empRow['empid']) {
            return ['success' => false, 'message' => "User $username tidak valid di ERP."];
        }
        $empid = (int) $empRow['empid'];

        $stmtHd = $conn3->prepare("SELECT productionhdid FROM pdproductionhd WHERE prdnmbr = ?");
        $stmtHd->execute([$cpno]);
        $hdRow = $stmtHd->fetch(PDO::FETCH_ASSOC);
        if (!$hdRow) {
            return ['success' => false, 'message' => "CP $cpno tidak ditemukan di ERP."];
        }
        $productionhdid = (int) $hdRow['productionhdid'];

        $productionrtgid = isset($dataPayload['productionrtgid']) ? (int) $dataPayload['productionrtgid'] : 0;
        if ($productionrtgid > 0) {
            $stmtRtg = $conn3->prepare("SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND productionrtgid = ? AND rtgmsid = ?");
            $stmtRtg->execute([$productionhdid, $productionrtgid, $rtgmsid]);
            $rtgRow = $stmtRtg->fetch(PDO::FETCH_ASSOC);
        } else {
            $rtgseq = isset($dataPayload['rtgseq']) ? (int) $dataPayload['rtgseq'] : 0;
            if ($rtgseq > 0) {
                $stmtRtg = $conn3->prepare("SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND rtgseq = ? AND rtgmsid = ?");
                $stmtRtg->execute([$productionhdid, $rtgseq, $rtgmsid]);
            } else {
                $stmtRtg = $conn3->prepare("SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND rtgmsid = ? ORDER BY rtgseq DESC, productionrtgid DESC LIMIT 1");
                $stmtRtg->execute([$productionhdid, $rtgmsid]);
            }
            $rtgRow = $stmtRtg->fetch(PDO::FETCH_ASSOC);
        }
        if (!$rtgRow) {
            return ['success' => false, 'message' => "Tahapan (rtgmsid=$rtgmsid) tidak ditemukan untuk CP $cpno di ERP."];
        }
        $productionrtgid = (int) $rtgRow['productionrtgid'];

        $baseQty = 0.0;
        $baseUomId = null;
        $baseStdQty = 0.0;
        $baseStdUomId = null;
        if (usesRtgSeqOneMaterialSource($rtgmsid)) {
            $stmtBaseQty = $conn3->prepare("
                SELECT prdqty, prduomid, prdstdqty, prdstduomid
                FROM pdproductionrtg
                WHERE productionhdid = ? AND rtgseq = 1
                ORDER BY productionrtgid ASC
                LIMIT 1
            ");
            $stmtBaseQty->execute([$productionhdid]);
            $baseQtyRow = $stmtBaseQty->fetch(PDO::FETCH_ASSOC);
            $baseQty = ($baseQtyRow && isset($baseQtyRow['prdqty'])) ? (float) $baseQtyRow['prdqty'] : 0.0;
            $baseUomId = ($baseQtyRow && isset($baseQtyRow['prduomid'])) ? ((int) $baseQtyRow['prduomid'] ?: null) : null;
            $baseStdQty = ($baseQtyRow && isset($baseQtyRow['prdstdqty'])) ? (float) $baseQtyRow['prdstdqty'] : 0.0;
            $baseStdUomId = ($baseQtyRow && isset($baseQtyRow['prdstduomid'])) ? ((int) $baseQtyRow['prdstduomid'] ?: null) : null;
        }

        $wheelno = trim((string) ($dataPayload['wheelno'] ?? ''));
        $wheelmsid = isset($dataPayload['wheelmsid']) && $dataPayload['wheelmsid'] !== '' ? $dataPayload['wheelmsid'] : null;
        if (empty($wheelmsid) && $wheelno !== '') {
            $stmtW = $conn3->prepare("SELECT wheelmsid FROM pdwheelms WHERE wheelno = ?");
            $stmtW->execute([$wheelno]);
            $wheelRow = $stmtW->fetch(PDO::FETCH_ASSOC);
            if ($wheelRow) {
                $wheelmsid = $wheelRow['wheelmsid'] ?: null;
            }
        }
        if ($wheelno === '') {
            $wheelno = null;
        }

        $conn3->beginTransaction();
        $nowDt = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
        $nowDate = $nowDt->format('Y-m-d 00:00:00');
        $nowTime = $nowDt->format('Y-m-d H:i:s');
        $nowUpdTime = $nowDt->format('Y-m-d H:i:s.u');

        if ($actionType === 'START') {
            if (usesRtgSeqOneMaterialSource($rtgmsid) && ($baseQty == 0.0 || empty($baseUomId) || $baseStdQty == 0.0 || empty($baseStdUomId))) {
                return ['success' => false, 'message' => "Routing $rtgmsid tidak bisa start karena data prdqty/prduomid/prdstdqty/prdstduomid pada rtgseq 1 masih 0 atau NULL."];
            }

            $stmtRtgUpd = $conn3->prepare("
                UPDATE pdproductionrtg
                SET startdate = TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'),
                    starttime = TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'),
                    startshiftid = ?,
                    startinitiatorid = ?,
                    upddate = TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS.US'),
                    upduser = ?
                WHERE productionrtgid = ?
            ");
            $stmtRtgUpd->execute([$nowDate, $nowTime, $dataPayload['shiftmsid'], $empid, $nowUpdTime, $username, $productionrtgid]);

            $stmtSeq = $conn3->prepare("SELECT COALESCE(MAX(picseq), 0) + 1 FROM pdresultpic WHERE productionhdid = ? AND productionrtgid = ?");
            $stmtSeq->execute([$productionhdid, $productionrtgid]);
            $picseq = (int) $stmtSeq->fetchColumn();

            $stmtPic = $conn3->prepare("
                INSERT INTO pdresultpic (compid, productionhdid, productionrtgid, picseq, empid, upddate, upduser, updflag, resulthdid, fgpictype)
                VALUES (2, ?, ?, ?, ?, TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS.US'), ?, 'Y', NULL, NULL)
            ");
            $stmtPic->execute([$productionhdid, $productionrtgid, $picseq, $empid, $nowUpdTime, $username]);
        } elseif ($actionType === 'STOP') {
            $isFail = strtoupper((string) ($dataPayload['fgresult'] ?? 'PASS')) === 'FAIL' ? 'F' : 'P';
            $fgstatus = 'X';
            $failmsid = ($isFail === 'F' && !empty($dataPayload['failmsid'])) ? (int) $dataPayload['failmsid'] : null;
            $faildesc = $isFail === 'F' ? ($dataPayload['faildesc'] ?? null) : null;

            if (usesRtgSeqOneMaterialSource($rtgmsid)) {
                $prdqty = $baseQty;
                $prduomid = $baseUomId;
                $prdstdqty = $baseStdQty;
                $prdstduomid = $baseStdUomId;
            } else {
                $stmtPrev = $conn3->prepare("
                    SELECT prev.prdqty, prev.prduomid, prev.prdstdqty, prev.prdstduomid
                    FROM pdproductionrtg curr
                    JOIN pdproductionrtg prev
                      ON prev.productionhdid = curr.productionhdid
                     AND prev.rtgseq < curr.rtgseq
                    WHERE curr.productionrtgid = ?
                    ORDER BY prev.rtgseq DESC
                    LIMIT 1
                ");
                $stmtPrev->execute([$productionrtgid]);
                $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

                $prdqty = ($prev && isset($prev['prdqty'])) ? (float) $prev['prdqty'] : 0;
                $prduomid = ($prev && isset($prev['prduomid'])) ? (int) $prev['prduomid'] : null;
                $prdstdqty = ($prev && isset($prev['prdstdqty'])) ? (float) $prev['prdstdqty'] : 0;
                $prdstduomid = ($prev && isset($prev['prdstduomid'])) ? (int) $prev['prdstduomid'] : null;

                // Jika tahapan pertama (tidak ada prev) dan qty masih 0, ambil dari header atau rencana saat ini
                if ($prdqty == 0 || empty($prduomid)) {
                    $stmtCurr = $conn3->prepare("SELECT prdqty, prduomid, prdstdqty, prdstduomid FROM pdproductionrtg WHERE productionrtgid = ?");
                    $stmtCurr->execute([$productionrtgid]);
                    $currRtg = $stmtCurr->fetch(PDO::FETCH_ASSOC);
                    if ($currRtg) {
                        $prdqty = (float) ($currRtg['prdqty'] ?? $prdqty);
                        $prduomid = (int) ($currRtg['prduomid'] ?? $prduomid) ?: null;
                        $prdstdqty = (float) ($currRtg['prdstdqty'] ?? $prdstdqty);
                        $prdstduomid = (int) ($currRtg['prdstduomid'] ?? $prdstduomid) ?: null;
                    }
                }
            }

            $stmtRtgUpd = $conn3->prepare("
                UPDATE pdproductionrtg
                SET enddate = TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'),
                    endtime = TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'),
                    endshiftid = ?,
                    initiatorid = ?,
                    fgstatus = ?,
                    prdqty = ?,
                    prduomid = ?,
                    prdstdqty = ?,
                    prdstduomid = ?,
                    fgresult = ?,
                    passmsid = NULL,
                    failmsid = ?,
                    resultdesc = ?,
                    failactcreatedby = NULL,
                    upddate = TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS.US'),
                    upduser = ?
                WHERE productionrtgid = ?
            ");
            $stmtRtgUpd->execute([
                $nowDate,
                $nowTime,
                $dataPayload['shiftmsid'],
                $empid,
                $fgstatus,
                $prdqty,
                $prduomid,
                $prdstdqty,
                $prdstduomid,
                $isFail,
                $failmsid,
                $faildesc,
                $nowUpdTime,
                $username,
                $productionrtgid,
            ]);

            if ($isFail === 'F') {
                $stmtHdUpd = $conn3->prepare("
                    UPDATE pdproductionhd
                    SET fgstatus = 'F',
                        upddate = TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS.US'),
                        upduser = ?
                    WHERE productionhdid = ?
                ");
                $stmtHdUpd->execute([$nowUpdTime, $username, $productionhdid]);
            }

            $stmtSum = $conn3->prepare("
                INSERT INTO pdproductionsum (
                    productionrtgid, compid, wheelno, wheelmsid, lebarkain,
                    prdtemp, prdtempfinish, prdspeed, prdspeedfinish, cutfinish, cutwidthact, prdbar,
                    vlotact, sisalar, tottopping, sisasaturator, leftlisting, middlelisting, rightlisting,
                    leftlistingmiring, middlelistingmiring, rightlistingmiring, be, cutpcs, topping,
                    skewing, grademsid, fglar3ltr, uselar3ltr, toppinguom, upddate, upduser, updflag
                ) VALUES (
                    ?, 2, ?, ?, ?,
                    0, 0, 0, 0, 0, 0, 0,
                    0, 0, 0, 0, 0, 0, 0,
                    0, 0, 0, 0, 0, 0,
                    0, NULL, 'N', 'N', 'gr/lt', TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS.US'), ?, 'Y'
                )
            ");
            $stmtSum->execute([
                $productionrtgid,
                $wheelno,
                $wheelmsid,
                !empty($dataPayload['lebarkain']) ? (float) $dataPayload['lebarkain'] : 0,
                $nowUpdTime,
                $username,
            ]);
        } elseif ($actionType === 'DOWNTIME_STOP') {
            $stmtSeq = $conn3->prepare("SELECT COALESCE(MAX(downseq), 0) + 1 FROM pdresultdown WHERE productionhdid = ? AND productionrtgid = ?");
            $stmtSeq->execute([$productionhdid, $productionrtgid]);
            $downseq = (int) $stmtSeq->fetchColumn();

            $stmtDw = $conn3->prepare("
                INSERT INTO pdresultdown (
                    compid, productionhdid, productionrtgid, downseq, downtimemsid,
                    downstart, downend, downtime, downdesc, upddate, upduser, updflag
                ) VALUES (
                    2, ?, ?, ?, ?,
                    ?, ?, ?, ?, TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS.US'), ?, 'Y'
                )
            ");
            $stmtDw->execute([
                $productionhdid,
                $productionrtgid,
                $downseq,
                $dataPayload['downtimemsid'],
                $dataPayload['downstart'],
                $dataPayload['downend'],
                $dataPayload['downtime_duration'],
                $dataPayload['downdesc'],
                $nowUpdTime,
                $username,
            ]);
        }

        $conn3->commit();
        return [
            'success' => true,
            'productionhdid' => $productionhdid,
            'productionrtgid' => $productionrtgid,
        ];
    } catch (Throwable $e) {
        if (isset($conn3) && $conn3->inTransaction()) {
            $conn3->rollBack();
        }
        return ['success' => false, 'message' => 'ERP Sync Error: ' . $e->getMessage()];
    }
}

function processStopDowntimeERP($conn, $dtId, $ket, $currUser)
{
    $sql = "
        SELECT
            d.id,
            d.paddry_stage_id,
            d.paddry_id,
            d.kd_downtime,
            d.keterangan_downtime,
            d.waktu_start,
            GETDATE() AS waktu_stop_srv,
            DATEDIFF(SECOND, d.waktu_start, GETDATE()) AS durasi_detik,
            p.cp_no,
            s.rtgmsid,
            s.rtgseq,
            s.erp_productionrtgid
        FROM dbo.cpp_paddry_downtime d
        JOIN dbo.cpp_paddry p ON p.id = d.paddry_id
        LEFT JOIN dbo.cpp_paddry_stage s ON s.id = d.paddry_stage_id
        WHERE d.id = ?
    ";
    $stmtInfo = sqlsrv_query($conn, $sql, [$dtId]);
    $info = $stmtInfo ? sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC) : null;
    if (!$info) {
        return ['success' => false, 'message' => 'Data downtime tidak ditemukan'];
    }

    if ($ket === '') {
        $ket = sqlValue($info['keterangan_downtime']);
    }

    $stmtUpd = sqlsrv_query($conn, "UPDATE dbo.cpp_paddry_downtime SET waktu_stop = GETDATE(), keterangan_downtime = ?, updated_by = ?, updated_at = GETDATE() WHERE id = ?", [$ket, $currUser, $dtId]);
    if ($stmtUpd === false) {
        return ['success' => false, 'message' => 'Gagal update downtime lokal'];
    }

    $downtimemsid = 0;
    try {
        include '../../../koneksi3.php';
        if (isset($conn3)) {
            $stmtD = $conn3->prepare("SELECT downtimemsid FROM pddowntimems WHERE downcode = ?");
            $stmtD->execute([sqlValue($info['kd_downtime'])]);
            $rowD = $stmtD->fetch(PDO::FETCH_ASSOC);
            if ($rowD) {
                $downtimemsid = (int) $rowD['downtimemsid'];
            }
        }
    } catch (Throwable $e) {
    }

    $rtgmsid = (int) ($info['rtgmsid'] ?? 0);
    if ($rtgmsid <= 0) {
        return ['success' => false, 'message' => 'Routing stage downtime tidak ditemukan.'];
    }

    $syncRes = syncToERP('DOWNTIME_STOP', [
        'cp_no' => sqlValue($info['cp_no']),
        'rtgmsid' => $rtgmsid,
        'rtgseq' => isset($info['rtgseq']) ? (int) $info['rtgseq'] : null,
        'productionrtgid' => isset($info['erp_productionrtgid']) ? (int) $info['erp_productionrtgid'] : null,
        'downtimemsid' => $downtimemsid,
        'downstart' => $info['waktu_start']->format('H:i'),
        'downend' => $info['waktu_stop_srv']->format('H:i'),
        'downtime_duration' => floor(max(0, (int) $info['durasi_detik']) / 60),
        'downdesc' => $ket,
    ]);

    if (!$syncRes['success']) {
        file_put_contents('sync_error.log', date('Y-m-d H:i:s') . ' [ERP_SYNC_ERROR] Action: DOWNTIME_STOP | CP: ' . sqlValue($info['cp_no']) . ' | Msg: ' . $syncRes['message'] . PHP_EOL, FILE_APPEND);
        return ['success' => false, 'message' => $syncRes['message']];
    }

    return ['success' => true, 'message' => 'Downtime berhasil dihentikan'];
}

function stopOpenDowntimesForStage($conn, $stageId, $currUser)
{
    $errors = [];
    $stmt = sqlsrv_query($conn, "SELECT id FROM dbo.cpp_paddry_downtime WHERE paddry_stage_id = ? AND waktu_stop IS NULL", [$stageId]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $res = processStopDowntimeERP($conn, (int) $row['id'], '', $currUser);
            if (!$res['success']) {
                $errors[] = $res['message'];
            }
        }
    }
    return $errors;
}

function resetStageLocalState($conn, $stageId, $currUser)
{
    $sql = "
        UPDATE dbo.cpp_paddry_stage
        SET start_at = NULL,
            finish_at = NULL,
            shift_start_id = NULL,
            shift_start_name = NULL,
            shift_end_id = NULL,
            shift_end_name = NULL,
            wheel_no = NULL,
            wheel_msid = NULL,
            lebar_kain = NULL,
            fgresult = NULL,
            fail_code = NULL,
            fail_msid = NULL,
            fail_desc = NULL,
            keterangan_fail = NULL,
            updated_at = GETDATE(),
            updated_by = ?
        WHERE id = ?
    ";
    sqlsrv_query($conn, $sql, [$currUser, $stageId]);
}

function recalcHeaderTimes($conn, $paddryId, $currUser)
{
    $sql = "
        SELECT
            MIN(start_at) AS min_start,
            MAX(CASE WHEN fgresult = 'P' THEN finish_at END) AS max_finish
        FROM dbo.cpp_paddry_stage
        WHERE paddry_id = ?
    ";
    $stmt = sqlsrv_query($conn, $sql, [$paddryId]);
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    $start = $row && $row['min_start'] instanceof DateTimeInterface ? $row['min_start']->format('H:i:s') : null;
    $finish = $row && $row['max_finish'] instanceof DateTimeInterface ? $row['max_finish']->format('H:i:s') : null;

    sqlsrv_query($conn, "
        UPDATE dbo.cpp_paddry
        SET aktual_start = ?,
            aktual_finish = ?,
            update_by = ?,
            last_update = GETDATE()
        WHERE id = ?
    ", [$start, $finish, $currUser, $paddryId]);
}

$schemaError = requireStageSchema($conn);
if ($schemaError && !in_array($action, ['get_references'], true)) {
    jsonExit(['success' => false, 'message' => $schemaError]);
}

if ($action === 'get_data') {
    $periodDate = $_POST['period_date'] ?? date('Y-m-d');
    $sqlData = "
        SELECT
            p.id, p.seq_no, p.machine_id, p.cp_no, p.tgl_cp, p.label, p.cust_color, p.kode_lab, p.material_name, p.qty,
            p.vlot_resep, p.period_date, p.ket as plan_description, p.rencana_start, p.rencana_finish, p.aktual_start, p.aktual_finish,
            p.est_tmbng_plrtn_lalab, p.est_plrtn_prdks
        FROM dbo.cpp_paddry p
        WHERE p.period_date = ?
        ORDER BY p.machine_id ASC, p.seq_no ASC
    ";
    $stmt = sqlsrv_query($conn, $sqlData, [$periodDate]);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        $msg = 'Gagal mengambil data antrean paddry.';
        if (!empty($err)) {
            $msg .= ' (' . $err[0]['message'] . ')';
        }
        jsonExit(['success' => false, 'message' => $msg]);
    }

    $cpRows = [];
    $cpRowsById = [];
    $machineIds = [];
    $cpNos = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $cpRows[] = $row;
        $cpRowsById[(int) $row['id']] = $row;
        $machine = sqlValue($row['machine_id'], 'N/A');
        if ($machine !== '' && !in_array($machine, $machineIds, true)) {
            $machineIds[] = $machine;
        }
        $cpNo = sqlValue($row['cp_no']);
        if ($cpNo !== '' && !in_array($cpNo, $cpNos, true)) {
            $cpNos[] = $cpNo;
        }
    }

    $machineNames = fetchMachineNames($machineIds);
    $breakTimes = fetchBreakTimes($conn);
    $sysSettings = fetchPaddrySettings($conn);
    $enforceSequence = ($sysSettings['ENFORCE_SEQUENCE'] ?? '1') === '1';
    $showRollback = ($sysSettings['SHOW_ROLLBACK_BUTTON'] ?? '1') === '1';

    $authorizedRtgmsids = getAuthorizedRtgmsids($conn, $currUser);
    $hasSpecialTargetTrustee = userHasSpecialTargetTrustee($authorizedRtgmsids);

    $wheelReqs = [];
    $lebarReqs = [];
    $breakReqs = [];
    foreach ([876, 877, 878, 879, 880, 887] as $rtg) {
        $wheelReqs[$rtg] = ($sysSettings["REQ_WHEEL_$rtg"] ?? '1') === '1';
        $lebarReqs[$rtg] = ($sysSettings["REQ_LEBAR_$rtg"] ?? '1') === '1';
        $breakReqs[$rtg] = ($sysSettings["SHOW_BREAK_$rtg"] ?? '1') === '1';
    }

    $erpActiveRoutings = fetchErpActiveRouting($cpNos);
    $erpStagesByCp = fetchErpStagesByCpNos($cpNos);
    $erpExpectedStages = fetchErpExpectedNextStages($cpNos);
    $erpOrderedStagesForDisplay = fetchErpOrderedStagesForDisplay($cpNos);
    syncLocalStages($conn, $cpRowsById, $erpStagesByCp, $breakTimes, $currUser);

    $localStagesByPaddry = fetchLocalStages($conn, array_keys($cpRowsById));
    $allStageIds = [];
    foreach ($localStagesByPaddry as $stageRows) {
        foreach ($stageRows as $stageRow) {
            $allStageIds[] = $stageRow['id'];
        }
    }
    $downtimeMetricsByStage = fetchDowntimeMetricsByStage($conn, $allStageIds);

    $finalLists = [];
    $prevCpFirstStagePassedByMachine = [];
    $machineSeqCounter = [];

    foreach ($cpRows as $row) {
        $paddryId = (int) $row['id'];
        $cpNo = sqlValue($row['cp_no']);
        $rStart = $row['rencana_start'] ? $row['rencana_start']->format('H:i') : '-';
        $rFinish = $row['rencana_finish'] ? $row['rencana_finish']->format('H:i') : '-';
        if ($rStart === '00:00') {
            $rStart = '-';
        }
        if ($rFinish === '00:00') {
            $rFinish = '-';
        }

        $aStart = $row['aktual_start'] ? $row['aktual_start']->format('Y-m-d H:i:s') : null;
        $aFinish = $row['aktual_finish'] ? $row['aktual_finish']->format('Y-m-d H:i:s') : null;
        $machine = sqlValue($row['machine_id'], 'N/A');
        $machineText = $machineNames[$machine] ?? $machine;

        $isBreaktime = false;
        $upperCp = strtoupper($cpNo);
        foreach ($breakTimes as $bt) {
            if (strpos($upperCp, $bt) !== false) {
                $isBreaktime = true;
                break;
            }
        }

        $varianceStart = null;
        if ($row['aktual_start'] && $row['rencana_start'] && $rStart !== '-' && isset($row['period_date'])) {
            $plannedDT = new DateTime($row['period_date']->format('Y-m-d') . ' ' . $row['rencana_start']->format('H:i:s'));
            $varianceStart = ($row['aktual_start']->getTimestamp() - $plannedDT->getTimestamp()) / 60;
        }
        $varianceFinish = null;
        if ($row['aktual_finish'] && $row['rencana_finish'] && $rFinish !== '-' && isset($row['period_date'])) {
            $plannedDT = new DateTime($row['period_date']->format('Y-m-d') . ' ' . $row['rencana_finish']->format('H:i:s'));
            $varianceFinish = ($row['aktual_finish']->getTimestamp() - $plannedDT->getTimestamp()) / 60;
        }

        $stageViews = [];
        $localStages = $localStagesByPaddry[$paddryId] ?? [];
        $erpStageRows = $erpStagesByCp[$cpNo] ?? [];
        $erpStagesByRtg = indexStagesByCompositeKey($erpStageRows);
        foreach ($localStages as $localStage) {
            $stageViews[] = resolveStageView(
                $localStage,
                $erpStagesByRtg[getStageCompositeKey($localStage)] ?? null,
                $downtimeMetricsByStage[(int) $localStage['id']] ?? null
            );
        }

        $stageMeta = determineCurrentStage($stageViews);



        $isCrossCpBlocked = false;
        if ($enforceSequence && !$isBreaktime && isset($prevCpFirstStagePassedByMachine[$machine]) && !$prevCpFirstStagePassedByMachine[$machine]) {
            $isCrossCpBlocked = true;
        }

        if (!$isBreaktime) {
            $firstStagePassed = true;
            if (!empty($stageViews)) {
                $firstStagePassed = $stageViews[0]['is_passed'] || $stageViews[0]['is_failed'];
            }
            $prevCpFirstStagePassedByMachine[$machine] = $firstStagePassed;
        }

        $currentStage = $stageMeta['current_stage'];

        $planTimbangStr = $row['est_tmbng_plrtn_lalab'] ? $row['est_tmbng_plrtn_lalab']->format('H:i') : '-';
        if ($planTimbangStr === '00:00')
            $planTimbangStr = '-';

        $planPelarutanPrdksStr = $row['est_plrtn_prdks'] ? $row['est_plrtn_prdks']->format('H:i') : '-';
        if ($planPelarutanPrdksStr === '00:00')
            $planPelarutanPrdksStr = '-';

        foreach ($stageViews as &$stageView) {
            $rtgId = (int) $stageView['rtgmsid'];
            $stageView['plan_start'] = '-';
            $stageView['plan_finish'] = '-';

            if ($rtgId === 876 || $rtgId === 878) {
                $stageView['plan_start'] = $planTimbangStr;
            } elseif ($rtgId === 877 || $rtgId === 879) {
                $stageView['plan_finish'] = $planPelarutanPrdksStr;
            } elseif ($rtgId === 880 || $rtgId === 887) {
                $stageView['plan_start'] = $planPelarutanPrdksStr;
                $stageView['plan_finish'] = $rStart;
            }

            $stageView['stage_blocked_message'] = '';
            if (!$stageView['is_passed'] && !$stageView['is_failed'] && !$stageView['is_running']) {
                $stageView['stage_blocked_message'] = getStageBlockedMessage($stageView, $stageViews, $erpStagesByRtg);
            }
            $stageView['is_current'] = $currentStage && $stageView['id'] === $currentStage['id'];
            $stageView['is_locked'] = $stageView['stage_blocked_message'] !== '';
            if ($stageMeta['has_failed'] && !$stageView['is_failed'] && !$stageView['is_passed']) {
                $stageView['is_locked'] = true;
                if ($stageView['stage_blocked_message'] === '') {
                    $stageView['stage_blocked_message'] = 'Tahap ini tidak bisa dijalankan sebelum tahap sebelumnya selesai';
                }
            }
            if ($stageView['is_locked'] && $stageView['status'] === 'waiting') {
                $stageView['status'] = 'locked';
            }
        }
        unset($stageView);

        if (!isset($machineSeqCounter[$machine])) {
            $machineSeqCounter[$machine] = 1;
        }

        $activeErpRoutingName = $erpActive['rtgname'] ?? '-';

        $processExpectedStage = determineProcessExpectedStage($cpNo, $stageViews, $erpStagesByRtg, $erpOrderedStagesForDisplay, $hasSpecialTargetTrustee)
            ?: ($erpExpectedStages[$cpNo] ?? null);
        $headerFgstatus = strtoupper(trim((string) (($erpOrderedStagesForDisplay[$cpNo][0]['hd_fgstatus'] ?? ''))));
        $isHeaderFailed = ($headerFgstatus === 'F');

        $selectedStage = null;
        if ($currentStage && !empty($currentStage['is_running']) && in_array((int) ($currentStage['rtgmsid'] ?? 0), [876, 877, 878, 879, 880, 887, 999], true)) {
            $selectedStage = $currentStage;
        }
        if ($selectedStage === null && $hasSpecialTargetTrustee) {
            $selectedStage = findSpecialTrusteeSelectedStage($authorizedRtgmsids, $stageViews, $erpStagesByRtg);
        }
        if ($selectedStage === null && $processExpectedStage) {
            $matchedStage = findStageByExpected($stageViews, $processExpectedStage);
            if ($matchedStage) {
                $selectedStage = $matchedStage;
            }
        }

        if ($selectedStage) {
            $currentStage = $selectedStage;
            foreach ($stageViews as &$stageView) {
                $stageView['is_current'] = ((int) $stageView['id'] === (int) $selectedStage['id']);
                $stageView['is_locked'] = !$stageView['is_passed']
                    && !$stageView['is_failed']
                    && !$stageView['is_running']
                    && ($stageView['stage_blocked_message'] !== '');
                if ($stageMeta['has_failed'] && !$stageView['is_failed'] && !$stageView['is_passed']) {
                    $stageView['is_locked'] = true;
                    if ($stageView['stage_blocked_message'] === '') {
                        $stageView['stage_blocked_message'] = 'Tahap ini tidak bisa dijalankan sebelum tahap sebelumnya selesai';
                    }
                }
                if ($stageView['is_locked'] && $stageView['status'] === 'waiting') {
                    $stageView['status'] = 'locked';
                }
            }
            unset($stageView);
        } else {
            foreach ($stageViews as &$stageView) {
                $stageView['is_current'] = false;
                $stageView['is_locked'] = true;
                if ($stageView['stage_blocked_message'] === '') {
                    $stageView['stage_blocked_message'] = 'Tahap ini tidak bisa dijalankan sebelum tahap sebelumnya selesai';
                }
                if ($stageView['status'] === 'waiting') {
                    $stageView['status'] = 'locked';
                }
            }
            unset($stageView);
        }

        $selectedRtgmsid = $selectedStage ? (int) $selectedStage['rtgmsid'] : 0;
        $activeDowntimeId = $selectedStage['active_downtime_id'] ?? null;
        $activeDowntimeName = $selectedStage['active_downtime_name'] ?? null;
        $activeDowntimeStart = $selectedStage['active_downtime_start'] ?? null;
        $totalDowntimeSeconds = (int) ($selectedStage['total_downtime_seconds'] ?? 0);
        $hasRelatedAuthorizedRouting = $isBreaktime;
        if (!$isBreaktime && !empty($authorizedRtgmsids)) {
            foreach ($stageViews as $stageView) {
                if (in_array((int) ($stageView['rtgmsid'] ?? 0), $authorizedRtgmsids, true)) {
                    $hasRelatedAuthorizedRouting = true;
                    break;
                }
            }

            if (!$hasRelatedAuthorizedRouting && !empty($erpOrderedStagesForDisplay[$cpNo])) {
                foreach ($erpOrderedStagesForDisplay[$cpNo] as $erpDisplayStage) {
                    if (in_array((int) ($erpDisplayStage['rtgmsid'] ?? 0), $authorizedRtgmsids, true)) {
                        $hasRelatedAuthorizedRouting = true;
                        break;
                    }
                }
            }
        }
        $hasNoRelatedRouting = !$isBreaktime && !empty($authorizedRtgmsids) && !$hasRelatedAuthorizedRouting;
        $failedStageForNote = $stageMeta['last_failed'] ?? (($selectedStage && !empty($selectedStage['is_failed'])) ? $selectedStage : null);
        if (!$failedStageForNote && !empty($erpOrderedStagesForDisplay[$cpNo])) {
            $selectedStageKey = $selectedStage ? getStageCompositeKey($selectedStage) : '';
            $lastFailedErpStage = null;
            $selectedStageFound = false;

            foreach ($erpOrderedStagesForDisplay[$cpNo] as $erpDisplayStage) {
                $erpDisplayKey = getStageCompositeKey($erpDisplayStage);
                $fgresult = strtoupper(trim((string) ($erpDisplayStage['fgresult'] ?? '')));

                if ($fgresult === 'F' || $fgresult === 'FAIL') {
                    $lastFailedErpStage = $erpDisplayStage;
                }

                if ($selectedStageKey !== '' && $erpDisplayKey === $selectedStageKey) {
                    $selectedStageFound = true;
                    break;
                }
            }

            if ($lastFailedErpStage) {
                $failedStageForNote = $lastFailedErpStage;
            }
        }
        $routingStatusNote = '';
        if ($failedStageForNote) {
            $failedRtgName = $failedStageForNote['rtgname'] ?: 'Unknown';
            $isSameAsSelected = $selectedStage && (
                ((int) ($selectedStage['id'] ?? 0) > 0 && (int) ($selectedStage['id'] ?? 0) === (int) ($failedStageForNote['id'] ?? 0))
                || getStageCompositeKey($selectedStage) === getStageCompositeKey($failedStageForNote)
            );
            $routingStatusNote = ($isSameAsSelected ? 'Tidak bisa ditembak karena status Fail di routing ini: ' : 'Tidak bisa ditembak karena status Fail di routing sebelumnya: ') . $failedRtgName;
        }
        if ($routingStatusNote === '' && $hasNoRelatedRouting) {
            $routingStatusNote = 'Tidak bisa ditembak karena tidak ada routing terkait dengan grup Anda.';
        }

        if (
            $processExpectedStage
            && !$stageMeta['has_failed']
            && empty($selectedStage['is_running'])
            && (!$hasSpecialTargetTrustee || !isBypassPreviousRoutingStage($selectedRtgmsid))
        ) {
            $expectedRtgmsid = (int) $processExpectedStage['rtgmsid'];
            $expectedIsAuthorized = in_array($expectedRtgmsid, $authorizedRtgmsids, true);
            $expectedStageKey = getStageCompositeKey($processExpectedStage);
            $selectedStageKey = $selectedStage ? getStageCompositeKey($selectedStage) : '';

            // Jika stage yang diharapkan ERP BUKAN stage yang sedang terpilih di UI (atau bukan tahap pertama di UI jika belum ada yg jalan)
            if ($expectedStageKey !== $selectedStageKey && (!$hasSpecialTargetTrustee || $expectedIsAuthorized)) {
                $stageMeta['blocked_message'] = "Harus tembak routing " . ($processExpectedStage['rtgname'] ?: 'Unknown') . " terlebih dahulu";
                $stageMeta['expected_rtgname'] = $processExpectedStage['rtgname'];
            }
        }

        if ($selectedStage && $hasSpecialTargetTrustee && !$stageMeta['has_failed'] && empty($selectedStage['is_running'])) {
                $dependencyBlockedMessage = getStageBlockedMessage($selectedStage, $stageViews, $erpStagesByRtg);
                if ($dependencyBlockedMessage !== '') {
                    $stageMeta['blocked_message'] = $dependencyBlockedMessage;
                    $stageMeta['expected_rtgname'] = $selectedStage['rtgname'] ?? null;
                }
            }

        $finalLists[] = [
            'id' => $paddryId,
            'seq_no' => $machineSeqCounter[$machine]++,
            'machine_id' => $machine,
            'machine_name' => $machineText,
            'cp_no' => $cpNo,
            'label' => sqlValue($row['label']),
            'warna' => sqlValue($row['cust_color']),
            'kode_lab' => sqlValue($row['kode_lab']),
            'vlot_resep' => sqlValue($row['vlot_resep']),
            'material' => sqlValue($row['material_name']),
            'qty' => $row['qty'] ? number_format((float) $row['qty'], 2, '.', ',') : '-',
            'rencana_start' => $rStart,
            'rencana_finish' => $rFinish,
            'aktual_start' => $aStart,
            'aktual_finish' => $aFinish,
            'variance_start' => $varianceStart,
            'variance_finish' => $varianceFinish,
            'period_date' => $row['period_date'] ? $row['period_date']->format('Y-m-d') : null,
            'is_breaktime' => $isBreaktime,
            'stages' => $stageViews,
            'selected_stage_id' => $selectedStage['id'] ?? null,
            'current_stage_rtgmsid' => $selectedStage['rtgmsid'] ?? null,
            'current_stage_id' => $selectedStage['id'] ?? null,
            'current_stage_name' => $selectedStage['rtgname'] ?? $activeErpRoutingName,
            'routing_name' => $stageMeta['expected_rtgname'] ?? ($selectedStage['rtgname'] ?? $activeErpRoutingName),
            'routing_id' => $selectedStage['rtgmsid'] ?? null,
            'completed_stage_count' => $stageMeta['completed_count'],
            'total_stage_count' => $stageMeta['total_count'],
            'can_start' => $selectedStage ? (!$selectedStage['is_running'] && !$selectedStage['is_passed'] && !$stageMeta['has_failed'] && ($selectedStage['stage_blocked_message'] ?? '') === '' && !$isCrossCpBlocked) : false,
            'blocked_message' => $selectedStage['stage_blocked_message'] ?? $stageMeta['blocked_message'],
            'routing_status_note' => $routingStatusNote,
            'has_no_related_routing' => $hasNoRelatedRouting,
            'failed_stage_name' => $failedStageForNote['rtgname'] ?? null,
            'failed_stage_rtgmsid' => $failedStageForNote['rtgmsid'] ?? null,
            'is_header_failed' => $isHeaderFailed,
            'is_cross_cp_blocked' => $isCrossCpBlocked,
            'has_failed_stage' => $stageMeta['has_failed'],
            'active_stage_id' => $currentStage['id'] ?? null,
            'active_downtime_id' => $activeDowntimeId,
            'active_downtime_name' => $activeDowntimeName,
            'active_downtime_start' => $activeDowntimeStart,
            'total_downtime_seconds' => $totalDowntimeSeconds,
        ];
    }

    jsonExit([
        'success' => true,
        'data' => $finalLists,
        'server_now' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s'),
        'wheel_reqs' => $wheelReqs,
        'lebar_reqs' => $lebarReqs,
        'break_reqs' => $breakReqs,
        'enforce_sequence' => $enforceSequence,
        'show_rollback' => $showRollback
    ]);
}

if ($action === 'get_references') {
    $wheels = [];
    $delays = [];
    $shifts = [];
    $fails = [];

    try {
        include '../../../koneksi3.php';
        $stmtW = $conn3->query("SELECT wheelmsid, wheelno FROM pdwheelms ORDER BY wheelno ASC");
        while ($row = $stmtW->fetch(PDO::FETCH_ASSOC)) {
            $wheels[] = ['id' => $row['wheelmsid'], 'no' => trim((string) $row['wheelno'])];
        }

        $stmtD = $conn3->query("SELECT downtimemsid, downcode, downdesc FROM pddowntimems ORDER BY downcode ASC");
        while ($row = $stmtD->fetch(PDO::FETCH_ASSOC)) {
            $delays[] = ['id' => $row['downtimemsid'], 'code' => trim((string) $row['downcode']), 'name' => trim((string) $row['downdesc'])];
        }

        $sqlShift = "
            SELECT s.shiftmsid, s.shiftcode, s.shiftname, s.shiftgrpid, g.shiftgrpname
            FROM pdshiftms s
            LEFT JOIN pdshiftgrp g ON g.shiftgrpid = s.shiftgrpid
            ORDER BY s.shiftcode ASC, s.shiftname ASC
        ";
        $stmtS = $conn3->query($sqlShift);
        while ($row = $stmtS->fetch(PDO::FETCH_ASSOC)) {
            $shifts[] = ['id' => $row['shiftmsid'], 'name' => trim((string) $row['shiftname']), 'group' => trim((string) $row['shiftgrpname'])];
        }

        $stmtF = $conn3->query("SELECT failmsid, failcode, faildesc FROM pdfailms ORDER BY failcode ASC");
        while ($row = $stmtF->fetch(PDO::FETCH_ASSOC)) {
            $fails[] = ['id' => $row['failmsid'], 'code' => trim((string) $row['failcode']), 'name' => trim((string) $row['faildesc'])];
        }
    } catch (Throwable $e) {
    }

    jsonExit([
        'success' => true,
        'wheels' => $wheels,
        'delays' => $delays,
        'shifts' => $shifts,
        'fails' => $fails,
        'downtimes' => $delays,
    ]);
}

if ($action === 'get_downtime_history') {
    $stageId = (int) ($_GET['stage_id'] ?? 0);
    if ($stageId <= 0) {
        jsonExit(['success' => false, 'message' => 'Stage downtime tidak valid.']);
    }

    $sql = "
        SELECT
            id, kd_downtime, nm_downtime,
            FORMAT(waktu_start, 'HH:mm:ss') AS start_fmt,
            FORMAT(waktu_stop, 'HH:mm:ss') AS stop_fmt,
            DATEDIFF(SECOND, waktu_start, ISNULL(waktu_stop, GETDATE())) AS duration_secs,
            keterangan_downtime
        FROM dbo.cpp_paddry_downtime
        WHERE paddry_stage_id = ?
        ORDER BY waktu_start DESC
    ";
    $stmt = sqlsrv_query($conn, $sql, [$stageId]);
    $history = [];
    $totalSecs = 0;
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $history[] = $row;
            $totalSecs += (int) ($row['duration_secs'] ?? 0);
        }
    }

    jsonExit(['success' => true, 'data' => $history, 'total_secs' => $totalSecs]);
}

if ($action === 'start_downtime') {
    $paddryId = (int) ($_POST['id'] ?? 0);
    $stageId = (int) ($_POST['stage_id'] ?? 0);
    $kdDt = $_POST['kd_downtime'] ?? '';
    $nmDt = $_POST['nm_dt'] ?? '';
    $ket = $_POST['keterangan'] ?? '';
    if ($paddryId <= 0 || $stageId <= 0) {
        jsonExit(['success' => false, 'message' => 'Stage downtime tidak valid.']);
    }

    $stmtCheck = sqlsrv_query($conn, "SELECT id FROM dbo.cpp_paddry_downtime WHERE paddry_stage_id = ? AND waktu_stop IS NULL", [$stageId]);
    if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
        jsonExit(['success' => false, 'message' => 'Masih ada sesi downtime yang sedang berjalan pada stage ini.']);
    }

    $stmtInsert = sqlsrv_query(
        $conn,
        "INSERT INTO dbo.cpp_paddry_downtime (paddry_id, paddry_stage_id, kd_downtime, nm_downtime, waktu_start, keterangan_downtime, created_by, created_at) VALUES (?, ?, ?, ?, GETDATE(), ?, ?, GETDATE())",
        [$paddryId, $stageId, $kdDt, $nmDt, $ket, $currUser]
    );
    if ($stmtInsert === false) {
        jsonExit(['success' => false, 'message' => 'Gagal mencatat awal downtime: ' . sqlsrv_errors()[0]['message']]);
    }

    jsonExit(['success' => true, 'message' => 'Downtime dimulai.']);
}

if ($action === 'stop_downtime') {
    $dtId = (int) ($_POST['active_dt_id'] ?? 0);
    $ket = $_POST['keterangan'] ?? '';
    $res = processStopDowntimeERP($conn, $dtId, $ket, $currUser);
    if (!$res['success']) {
        jsonExit(['success' => true, 'message' => 'Downtime selesai.', 'sync_error' => $res['message']]);
    }
    jsonExit(['success' => true, 'message' => 'Downtime selesai.']);
}

if ($action === 'start' || $action === 'stop') {
    $id = (int) ($_POST['id'] ?? 0);
    $stageId = (int) ($_POST['stage_id'] ?? 0);
    $rtgmsid = (int) ($_POST['rtgmsid'] ?? 0);
    if ($id <= 0 || $rtgmsid <= 0) {
        jsonExit(['success' => false, 'message' => 'CP atau routing tidak valid.']);
    }

    if ($stageId > 0) {
        $stmtStage = sqlsrv_query($conn, "
            SELECT s.*, p.cp_no
            FROM dbo.cpp_paddry_stage s
            JOIN dbo.cpp_paddry p ON p.id = s.paddry_id
            WHERE s.id = ? AND s.paddry_id = ? AND s.rtgmsid = ?
        ", [$stageId, $id, $rtgmsid]);
    } else {
        $stmtStage = sqlsrv_query($conn, "
            SELECT TOP 1 s.*, p.cp_no
            FROM dbo.cpp_paddry_stage s
            JOIN dbo.cpp_paddry p ON p.id = s.paddry_id
            WHERE s.paddry_id = ? AND s.rtgmsid = ?
            ORDER BY s.stage_no DESC, s.rtgseq DESC, s.id DESC
        ", [$id, $rtgmsid]);
    }
    $stageRow = $stmtStage ? sqlsrv_fetch_array($stmtStage, SQLSRV_FETCH_ASSOC) : null;
    if (!$stageRow) {
        jsonExit(['success' => false, 'message' => 'Stage routing tidak ditemukan untuk CP ini.']);
    }

    $authorizedRtgmsids = getAuthorizedRtgmsids($conn, $currUser);
    $isBreaktimeCp = ($rtgmsid === 999);
    if (!$isBreaktimeCp && !empty($authorizedRtgmsids) && !in_array($rtgmsid, $authorizedRtgmsids, true)) {
        jsonExit(['success' => false, 'message' => 'Otoritas routing tidak sesuai dengan trustee user.']);
    }

    if ($isBreaktimeCp && !empty($authorizedRtgmsids)) {
        $canBreak = false;
        foreach ($authorizedRtgmsids as $r) {
            if (($sysSettings["SHOW_BREAK_$r"] ?? '1') === '1') {
                $canBreak = true;
                break;
            }
        }
        if (!$canBreak) {
            jsonExit(['success' => false, 'message' => 'Grup Anda tidak diizinkan untuk memulai atau menghentikan Breaktime.']);
        }
    }

    if (!$isBreaktimeCp && $action === 'start') {
        try {
            include '../../../koneksi3.php';
            if (isset($conn3)) {
                $stmtVal = $conn3->prepare("
                    SELECT rtg.rtgmsid, rtg.rtgseq, rtg.prdqty, rtg.fgresult, rtg.endtime, ms.rtgname 
                    FROM pdproductionrtg rtg
                    JOIN pdproductionhd hd ON hd.productionhdid = rtg.productionhdid
                    LEFT JOIN pdrtgms ms ON ms.rtgmsid = rtg.rtgmsid
                    WHERE hd.prdnmbr = ?
                    ORDER BY rtg.rtgseq ASC
                ");
                $stmtVal->execute([sqlValue($stageRow['cp_no'])]);
                $allErpStages = $stmtVal->fetchAll(PDO::FETCH_ASSOC);

                $targetFound = false;
                $expectedNextStage = null;

                foreach ($allErpStages as $s) {
                    if ((int) $s['rtgmsid'] === $rtgmsid) {
                        $targetFound = true;
                    }
                }

                foreach ($allErpStages as $s) {
                    if (!erpStageIsTraversed($s)) {
                        $expectedNextStage = $s;
                        break; // Ini adalah stage pertama yang belum selesai
                    }
                }

                if (!$targetFound) {
                    jsonExit(['success' => false, 'message' => 'Routing target tidak ditemukan di ERP.']);
                }

                $allowSpecialTrusteeBypass = userHasSpecialTargetTrustee($authorizedRtgmsids) && usesRtgSeqOneMaterialSource($rtgmsid);
                if (!$allowSpecialTrusteeBypass && !isBypassPreviousRoutingStage($rtgmsid) && $expectedNextStage && (int) $expectedNextStage['rtgmsid'] !== $rtgmsid) {
                    $expectedName = trim((string) ($expectedNextStage['rtgname'] ?: 'Unknown (MSID: ' . $expectedNextStage['rtgmsid'] . ')'));
                    jsonExit(['success' => false, 'message' => 'Harus tembak routing ' . $expectedName . ' terlebih dahulu']);
                }

                $erpStagesByRtg = indexStagesByCompositeKey($allErpStages);
                $localStageViews = [];
                $stmtLocalStages = sqlsrv_query($conn, "SELECT * FROM dbo.cpp_paddry_stage WHERE paddry_id = ? ORDER BY stage_no ASC, rtgseq ASC, rtgmsid ASC", [$id]);
                if ($stmtLocalStages) {
                    while ($localStageRow = sqlsrv_fetch_array($stmtLocalStages, SQLSRV_FETCH_ASSOC)) {
                        $localStageViews[] = resolveStageView(
                            $localStageRow,
                            $erpStagesByRtg[buildStageCompositeKey($localStageRow['rtgseq'] ?? 0, $localStageRow['rtgmsid'] ?? 0)] ?? null,
                            null
                        );
                    }
                }

                $targetStageView = null;
                foreach ($localStageViews as $localStageView) {
                    if ((int) ($localStageView['id'] ?? 0) === (int) $stageRow['id']) {
                        $targetStageView = $localStageView;
                        break;
                    }
                }
                $dependencyBlockedMessage = $targetStageView
                    ? getStageBlockedMessage($targetStageView, $localStageViews, $erpStagesByRtg)
                    : getDependencyBlockedMessage($rtgmsid, $localStageViews, $erpStagesByRtg);
                if ($dependencyBlockedMessage !== '') {
                    jsonExit(['success' => false, 'message' => $dependencyBlockedMessage]);
                }
            }
        } catch (Throwable $e) {
            // Jika gagal cek ERP karena koneksi3, biarkan lanjut tapi log errornya
            $syncError = "Gagal memvalidasi routing ERP: " . $e->getMessage();
            file_put_contents('sync_error.log', date('Y-m-d H:i:s') . ' [ERP_VAL_ERROR] CP: ' . sqlValue($stageRow['cp_no']) . ' | Msg: ' . $syncError . PHP_EOL, FILE_APPEND);
        }
    }

    if (!sqlsrv_begin_transaction($conn)) {
        jsonExit(['success' => false, 'message' => 'Gagal membuka transaksi.']);
    }

    $syncError = null;
    try {
        if ($action === 'start') {
            if ($stageRow['start_at'] !== null) {
                throw new RuntimeException('Stage ini sudah pernah dimulai.');
            }

            $noRoda = $_POST['no_roda'] ?? '';
            $ketDelay = $_POST['ket_delay'] ?? '';
            $shiftId = $_POST['shift_id'] ?? null;
            $shiftName = $_POST['shift_name'] ?? '';
            $wheelMsid = $_POST['wheel_msid'] ?? null;

            if ($noRoda === '')
                $noRoda = null;
            if ($wheelMsid === '')
                $wheelMsid = null;

            $stmtUpdHeader = sqlsrv_query($conn, "
                UPDATE dbo.cpp_paddry
                SET no_roda = ?,
                    ket_delay = ?,
                    shift_id = ?,
                    shift_name = ?,
                    update_by = ?,
                    last_update = GETDATE(),
                    aktual_start = CASE WHEN aktual_start IS NULL THEN CAST(GETDATE() AS TIME) ELSE aktual_start END
                WHERE id = ?
            ", [$noRoda, $ketDelay, $shiftId, $shiftName, $currUser, $id]);
            if ($stmtUpdHeader === false) {
                throw new RuntimeException(sqlsrv_errors()[0]['message']);
            }

            $stmtUpdStage = sqlsrv_query($conn, "
                UPDATE dbo.cpp_paddry_stage
                SET start_at = GETDATE(),
                    shift_start_id = ?,
                    shift_start_name = ?,
                    wheel_no = ?,
                    wheel_msid = ?,
                    origin_system = 'SupportApp',
                    updated_at = GETDATE(),
                    updated_by = ?
                WHERE id = ?
            ", [$shiftId, $shiftName, $noRoda, $wheelMsid, $currUser, (int) $stageRow['id']]);
            if ($stmtUpdStage === false) {
                throw new RuntimeException(sqlsrv_errors()[0]['message']);
            }

            $syncRes = syncToERP('START', [
                'cp_no' => sqlValue($stageRow['cp_no']),
                'rtgmsid' => $rtgmsid,
                'rtgseq' => isset($stageRow['rtgseq']) ? (int) $stageRow['rtgseq'] : null,
                'productionrtgid' => isset($stageRow['erp_productionrtgid']) ? (int) $stageRow['erp_productionrtgid'] : null,
                'shiftmsid' => $shiftId,
                'wheelno' => $noRoda,
                'wheelmsid' => $wheelMsid,
            ]);
        } else {
            // Jika belum dimulai secara lokal, cek apakah sudah dimulai di ERP (e.g., start via Proint)
            $effectiveStart = $stageRow['start_at'];
            if ($effectiveStart === null) {
                try {
                    include '../../../koneksi3.php';
                    if (isset($conn3)) {
                        $stmtErpStart = $conn3->prepare("
                            SELECT rtg.starttime
                            FROM pdproductionrtg rtg
                            JOIN pdproductionhd hd ON hd.productionhdid = rtg.productionhdid
                            WHERE hd.prdnmbr = ? AND rtg.rtgseq = ? AND rtg.rtgmsid = ?
                        ");
                        $stmtErpStart->execute([sqlValue($stageRow['cp_no']), (int) ($stageRow['rtgseq'] ?? 0), $rtgmsid]);
                        $erpRow = $stmtErpStart->fetch(PDO::FETCH_ASSOC);
                        if ($erpRow && !empty($erpRow['starttime'])) {
                            $effectiveStart = $erpRow['starttime'];
                            // Backfill start_at lokal agar sinkron dengan ERP
                            sqlsrv_query($conn, "UPDATE dbo.cpp_paddry_stage SET start_at = GETDATE(), updated_at = GETDATE(), updated_by = ? WHERE id = ?", [$currUser, (int) $stageRow['id']]);
                        }
                    }
                } catch (Throwable $e) {
                    // Jika gagal cek ERP, biarkan $effectiveStart tetap NULL (akan error di bawah)
                }
            }

            if ($effectiveStart === null) {
                throw new RuntimeException('Stage ini belum dimulai (Lokal & ERP).');
            }
            if ($stageRow['finish_at'] !== null) {
                throw new RuntimeException('Stage ini sudah selesai.');
            }

            $shiftEndId = $_POST['shift_end_id'] ?? null;
            if ($shiftEndId === '')
                $shiftEndId = null;

            $shiftEndName = $_POST['shift_end_name'] ?? '';

            $lebarKain = $_POST['lebar_kain'] ?? null;
            if ($lebarKain === '')
                $lebarKain = null;

            $hasilCelup = strtoupper((string) ($_POST['hasil_celup'] ?? 'PASS'));
            $failCode = $_POST['fail_code'] ?? '';
            $failDesc = $_POST['fail_desc'] ?? '';
            $ketFail = $_POST['ket_fail'] ?? '';
            $erpResultDesc = $hasilCelup === 'FAIL' ? $ketFail : $failDesc;

            $failMsid = $_POST['fail_msid'] ?? null;
            if ($failMsid === '')
                $failMsid = null;

            $wheelMsid = $_POST['wheel_msid'] ?? ($stageRow['wheel_msid'] ?? null);
            if ($wheelMsid === '')
                $wheelMsid = null;

            $syncErrors = stopOpenDowntimesForStage($conn, (int) $stageRow['id'], $currUser);

            $stmtUpdHeader = sqlsrv_query($conn, "
                UPDATE dbo.cpp_paddry
                SET shift_end_id = ?,
                    shift_end_name = ?,
                    lebar_kain = ?,
                    hasil_celup = ?,
                    fail_code = ?,
                    fail_desc = ?,
                    keterangan_fail = ?,
                    update_by = ?,
                    last_update = GETDATE()
                WHERE id = ?
            ", [$shiftEndId, $shiftEndName, $lebarKain, $hasilCelup, $failCode, $failDesc, $ketFail, $currUser, $id]);
            if ($stmtUpdHeader === false) {
                throw new RuntimeException(sqlsrv_errors()[0]['message']);
            }

            $stmtUpdStage = sqlsrv_query($conn, "
                UPDATE dbo.cpp_paddry_stage
                SET finish_at = GETDATE(),
                    shift_end_id = ?,
                    shift_end_name = ?,
                    lebar_kain = ?,
                    fgresult = ?,
                    fail_code = ?,
                    fail_msid = ?,
                    fail_desc = ?,
                    keterangan_fail = ?,
                    origin_system = 'SupportApp',
                    updated_at = GETDATE(),
                    updated_by = ?
                WHERE id = ?
            ", [$shiftEndId, $shiftEndName, $lebarKain, $hasilCelup === 'FAIL' ? 'F' : 'P', $failCode, $failMsid, $failDesc, $ketFail, $currUser, (int) $stageRow['id']]);
            if ($stmtUpdStage === false) {
                throw new RuntimeException(sqlsrv_errors()[0]['message']);
            }

            $syncRes = syncToERP('STOP', [
                'cp_no' => sqlValue($stageRow['cp_no']),
                'rtgmsid' => $rtgmsid,
                'rtgseq' => isset($stageRow['rtgseq']) ? (int) $stageRow['rtgseq'] : null,
                'productionrtgid' => isset($stageRow['erp_productionrtgid']) ? (int) $stageRow['erp_productionrtgid'] : null,
                'shiftmsid' => $shiftEndId,
                'fgresult' => $hasilCelup,
                'failmsid' => $failMsid,
                'faildesc' => $erpResultDesc,
                'lebarkain' => $lebarKain,
                'wheelno' => sqlValue($stageRow['wheel_no']),
                'wheelmsid' => $wheelMsid,
            ]);

            if (!empty($syncErrors)) {
                $syncError = implode(', ', $syncErrors);
            }
        }

        if (!$syncRes['success']) {
            throw new RuntimeException($syncRes['message']);
        }

        sqlsrv_query($conn, "
            UPDATE dbo.cpp_paddry_stage
            SET erp_productionhdid = ?, erp_productionrtgid = ?, updated_at = GETDATE(), updated_by = ?
            WHERE id = ?
        ", [$syncRes['productionhdid'] ?? null, $syncRes['productionrtgid'] ?? null, $currUser, (int) $stageRow['id']]);

        recalcHeaderTimes($conn, $id, $currUser);

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException('Gagal commit transaksi.');
        }
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        $syncError = $e->getMessage();
        file_put_contents('sync_error.log', date('Y-m-d H:i:s') . ' [PADDRY_STAGE_ERROR] Action: ' . strtoupper($action) . ' | CP: ' . sqlValue($stageRow['cp_no']) . ' | RTG: ' . $rtgmsid . ' | Msg: ' . $syncError . PHP_EOL, FILE_APPEND);
        jsonExit(['success' => false, 'message' => $syncError]);
    }

    jsonExit([
        'success' => true,
        'message' => 'Waktu ' . strtoupper($action) . ' berhasil direkam.',
        'sync_error' => $syncError,
    ]);
}

if ($action === 'dev_rollback') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonExit(['success' => false, 'message' => 'Invalid ID']);
    }

    $stmtStage = sqlsrv_query($conn, "
        SELECT TOP 1 s.*, p.cp_no
        FROM dbo.cpp_paddry_stage s
        JOIN dbo.cpp_paddry p ON p.id = s.paddry_id
        WHERE s.paddry_id = ? AND (s.start_at IS NOT NULL OR s.finish_at IS NOT NULL)
        ORDER BY s.stage_no DESC, s.rtgseq DESC, s.id DESC
    ", [$id]);
    $stageRow = $stmtStage ? sqlsrv_fetch_array($stmtStage, SQLSRV_FETCH_ASSOC) : null;
    if (!$stageRow) {
        jsonExit(['success' => false, 'message' => 'Belum ada stage yang bisa di-rollback.']);
    }

    if (!sqlsrv_begin_transaction($conn)) {
        jsonExit(['success' => false, 'message' => 'Gagal membuka transaksi SQL Server.']);
    }

    try {
        sqlsrv_query($conn, "DELETE FROM dbo.cpp_paddry_downtime WHERE paddry_stage_id = ?", [(int) $stageRow['id']]);
        resetStageLocalState($conn, (int) $stageRow['id'], $currUser);
        recalcHeaderTimes($conn, $id, $currUser);

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException('Gagal commit rollback SQL Server.');
        }
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        jsonExit(['success' => false, 'message' => $e->getMessage()]);
    }

    try {
        include '../../../koneksi3.php';
        if (isset($conn3)) {
            $stmtHd = $conn3->prepare("SELECT productionhdid FROM pdproductionhd WHERE prdnmbr = ?");
            $stmtHd->execute([sqlValue($stageRow['cp_no'])]);
            $hd = $stmtHd->fetch(PDO::FETCH_ASSOC);
            if ($hd) {
                $productionhdid = (int) $hd['productionhdid'];
                $conn3->beginTransaction();

                $erpProductionrtgid = isset($stageRow['erp_productionrtgid']) ? (int) $stageRow['erp_productionrtgid'] : 0;
                if ($erpProductionrtgid > 0) {
                    $stmtRtg = $conn3->prepare("SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND productionrtgid = ? AND rtgmsid = ?");
                    $stmtRtg->execute([$productionhdid, $erpProductionrtgid, (int) $stageRow['rtgmsid']]);
                } else {
                    $stmtRtg = $conn3->prepare("SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND rtgseq = ? AND rtgmsid = ?");
                    $stmtRtg->execute([$productionhdid, (int) ($stageRow['rtgseq'] ?? 0), (int) $stageRow['rtgmsid']]);
                }
                $rtg = $stmtRtg->fetch(PDO::FETCH_ASSOC);
                if ($rtg) {
                    $productionrtgid = (int) $rtg['productionrtgid'];
                    $conn3->prepare("DELETE FROM pdresultpic WHERE productionhdid = ? AND productionrtgid = ?")->execute([$productionhdid, $productionrtgid]);
                    $conn3->prepare("DELETE FROM pdproductionsum WHERE productionrtgid = ?")->execute([$productionrtgid]);
                    $conn3->prepare("DELETE FROM pdresultdown WHERE productionhdid = ? AND productionrtgid = ?")->execute([$productionhdid, $productionrtgid]);
                    $conn3->prepare("
                        UPDATE pdproductionrtg
                        SET startdate = NULL, starttime = NULL, enddate = NULL, endtime = NULL,
                            startshiftid = NULL, endshiftid = NULL, initiatorid = NULL, startinitiatorid = NULL,
                            fgstatus = 'O', prdqty = 0, prdstdqty = 0, prduomid = NULL, prdstduomid = NULL,
                            fgresult = NULL, failmsid = NULL, resultdesc = NULL, failactcreatedby = NULL
                        WHERE productionrtgid = ?
                    ")->execute([$productionrtgid]);
                    $conn3->prepare("UPDATE pdproductionhd SET fgstatus = 'U' WHERE productionhdid = ?")->execute([$productionhdid]);
                }

                $conn3->commit();
            }
        }
    } catch (Throwable $e) {
        if (isset($conn3) && $conn3->inTransaction()) {
            $conn3->rollBack();
        }
        jsonExit(['success' => false, 'message' => 'Rollback stage lokal berhasil, tapi gagal reset ERP: ' . $e->getMessage()]);
    }

    jsonExit(['success' => true, 'message' => 'Rollback stage terakhir berhasil.']);
}

if ($action === 'dev_full_rollback') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonExit(['success' => false, 'message' => 'Invalid ID']);
    }

    $stmtCp = sqlsrv_query($conn, "SELECT cp_no FROM dbo.cpp_paddry WHERE id = ?", [$id]);
    $rowCp = $stmtCp ? sqlsrv_fetch_array($stmtCp, SQLSRV_FETCH_ASSOC) : null;
    if (!$rowCp) {
        jsonExit(['success' => false, 'message' => 'Data paddry tidak ditemukan.']);
    }

    if (!sqlsrv_begin_transaction($conn)) {
        jsonExit(['success' => false, 'message' => 'Gagal membuka transaksi SQL Server.']);
    }

    try {
        sqlsrv_query($conn, "DELETE FROM dbo.cpp_paddry_downtime WHERE paddry_id = ?", [$id]);
        sqlsrv_query($conn, "UPDATE dbo.cpp_paddry_stage SET start_at = NULL, finish_at = NULL, shift_start_id = NULL, shift_start_name = NULL, shift_end_id = NULL, shift_end_name = NULL, wheel_no = NULL, wheel_msid = NULL, lebar_kain = NULL, fgresult = NULL, fail_code = NULL, fail_msid = NULL, fail_desc = NULL, keterangan_fail = NULL, updated_at = GETDATE(), updated_by = ? WHERE paddry_id = ?", [$currUser, $id]);
        sqlsrv_query($conn, "UPDATE dbo.cpp_paddry SET aktual_start = NULL, aktual_finish = NULL, shift_id = NULL, shift_name = NULL, shift_end_id = NULL, shift_end_name = NULL, hasil_celup = NULL, fail_code = NULL, fail_desc = NULL, keterangan_fail = NULL, update_by = ?, last_update = GETDATE() WHERE id = ?", [$currUser, $id]);
        sqlsrv_commit($conn);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        jsonExit(['success' => false, 'message' => $e->getMessage()]);
    }

    try {
        include '../../../koneksi3.php';
        if (isset($conn3)) {
            $stmtHd = $conn3->prepare("SELECT productionhdid FROM pdproductionhd WHERE prdnmbr = ?");
            $stmtHd->execute([sqlValue($rowCp['cp_no'])]);
            $hdRow = $stmtHd->fetch(PDO::FETCH_ASSOC);
            if ($hdRow) {
                $productionhdid = (int) $hdRow['productionhdid'];
                $conn3->beginTransaction();
                $conn3->prepare("DELETE FROM pdresultpic WHERE productionhdid = ? AND productionrtgid IN (SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND rtgmsid IN (876,877,878,879,880,887))")->execute([$productionhdid, $productionhdid]);
                $conn3->prepare("DELETE FROM pdproductionsum WHERE productionrtgid IN (SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND rtgmsid IN (876,877,878,879,880,887))")->execute([$productionhdid]);
                $conn3->prepare("DELETE FROM pdresultdown WHERE productionhdid = ? AND productionrtgid IN (SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND rtgmsid IN (876,877,878,879,880,887))")->execute([$productionhdid, $productionhdid]);
                $conn3->prepare("
                    UPDATE pdproductionrtg
                    SET startdate = NULL, starttime = NULL, enddate = NULL, endtime = NULL,
                        startshiftid = NULL, endshiftid = NULL, initiatorid = NULL, startinitiatorid = NULL,
                        fgstatus = 'O', prdqty = 0, prdstdqty = 0, prduomid = NULL, prdstduomid = NULL, fgresult = NULL, failmsid = NULL, resultdesc = NULL, failactcreatedby = NULL
                    WHERE productionhdid = ? AND rtgmsid IN (876,877,878,879,880,887)
                ")->execute([$productionhdid]);
                $conn3->prepare("UPDATE pdproductionhd SET fgstatus = 'U' WHERE productionhdid = ?")->execute([$productionhdid]);
                $conn3->commit();
            }
        }
    } catch (Throwable $e) {
        if (isset($conn3) && $conn3->inTransaction()) {
            $conn3->rollBack();
        }
        jsonExit(['success' => false, 'message' => 'Full rollback lokal berhasil, tapi gagal reset ERP: ' . $e->getMessage()]);
    }

    jsonExit(['success' => true, 'message' => 'Full rollback berhasil.']);
}

jsonExit(['success' => false, 'message' => 'Action tidak dikenali']);
