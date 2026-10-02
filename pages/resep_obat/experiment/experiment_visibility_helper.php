<?php
function resepExperimentVisibilityScope($conn)
{
    if ((int)($_SESSION['GroupId'] ?? 0) === 1) return 'ALL';
    $username = $_SESSION['UserName'] ?? '';
    if ($username === '') return 'ALL';

    $stmt = sqlsrv_query($conn, "SELECT g.experiment_view_scope FROM dbo.resep_obat_group_members m INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id WHERE m.username=?", [$username]);
    $scopes = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $scopes[] = $row['experiment_view_scope'] ?? 'ALL';
        }
        sqlsrv_free_stmt($stmt);
    }
    if (in_array('PROCESS_ONLY', $scopes, true)) return 'PROCESS_ONLY';
    if (in_array('APPROVED_ONLY', $scopes, true)) return 'APPROVED_ONLY';
    return 'ALL';
}

function resepExperimentApplyVisibility(&$where, &$params, $alias, $conn)
{
    $scope = resepExperimentVisibilityScope($conn);
    if ($scope === 'APPROVED_ONLY') {
        $where[] = "$alias.was_approved = 1";
    } elseif ($scope === 'PROCESS_ONLY') {
        $where[] = "$alias.was_in_process = 1";
    }
}

function resepExperimentCanAccessStatus($conn, $status, $wasInProcess = 0, $wasApproved = 0)
{
    $scope = resepExperimentVisibilityScope($conn);
    if ($scope === 'APPROVED_ONLY') {
        return (int)$wasApproved === 1;
    } elseif ($scope === 'PROCESS_ONLY') {
        return (int)$wasInProcess === 1;
    }
    return true;
}

function resepUserIsPpc($conn)
{
    if ((int)($_SESSION['GroupId'] ?? 0) === 1) return false;
    $username = $_SESSION['UserName'] ?? '';
    if ($username === '') return false;

    $stmt = sqlsrv_query($conn, "SELECT TOP 1 g.id FROM dbo.resep_obat_group_members m INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id WHERE m.username=? AND g.role_type='PPC'", [$username]);
    $isPpc = $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return (bool)$isPpc;
}

function resepUserIsKabag($conn)
{
    if ((int)($_SESSION['GroupId'] ?? 0) === 1) return false;
    $username = $_SESSION['UserName'] ?? '';
    if ($username === '') return false;

    $stmt = sqlsrv_query($conn, "SELECT TOP 1 g.id FROM dbo.resep_obat_group_members m INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id WHERE m.username=? AND g.role_type='KABAG'", [$username]);
    $isKabag = $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return (bool)$isKabag;
}

function resepUserIsQc($conn)
{
    if ((int)($_SESSION['GroupId'] ?? 0) === 1) return false;
    $username = $_SESSION['UserName'] ?? '';
    if ($username === '') return false;

    $stmt = sqlsrv_query($conn, "SELECT TOP 1 g.id FROM dbo.resep_obat_group_members m INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id WHERE m.username=? AND g.role_type='QC'", [$username]);
    $isQc = $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return (bool)$isQc;
}
