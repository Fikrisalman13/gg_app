<?php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$response = ['success' => false, 'message' => 'Unknown error'];

if (!isset($_SESSION['UserName'])) {
    $response['message'] = 'Silakan login terlebih dahulu.';
    echo json_encode($response);
    exit;
}

// Permission check: require CanEdit on MenuId 162
$groupId = isset($_SESSION['GroupId']) ? intval($_SESSION['GroupId']) : 0;
$username = $_SESSION['UserName'];
$canEdit = 0;
if ($groupId > 0) {
    $permSql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $permStmt = sqlsrv_query($conn, $permSql, [$groupId, 162]);
    if ($permStmt && $prow = sqlsrv_fetch_array($permStmt, SQLSRV_FETCH_ASSOC)) {
        $canEdit = intval($prow['CanEdit'] ?? 0);
    }
    if ($permStmt) sqlsrv_free_stmt($permStmt);
}
if ($canEdit !== 1) {
    $response['message'] = 'Akses ditolak. Anda tidak memiliki izin untuk melakukan aksi ini.';
    echo json_encode($response);
    exit;
}

try {
    // If GET request -> preview list of non-done issues (for modal)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sql = "SELECT issue_id, issue_name, issue_type, asset_id, status, created_by FROM dbo.issues WHERE status <> 'Done' ORDER BY created_at DESC";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) throw new Exception('Gagal mengambil daftar issues: ' . print_r(sqlsrv_errors(), true));

        $issues = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $issues[] = $row;
        }
        if ($stmt) sqlsrv_free_stmt($stmt);

        $response['success'] = true;
        $response['issues'] = $issues;
        echo json_encode($response);
        exit;
    }

    // POST: accept optional issue_ids[] to process only selected issues
    $selected = [];
    if (!empty($_POST['issue_ids']) && is_array($_POST['issue_ids'])) {
        foreach ($_POST['issue_ids'] as $v) {
            $selected[] = intval($v);
        }
    }

    // Fetch candidate issues (either selected subset or all non-done)
    if (!empty($selected)) {
        // build placeholders
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $sql = "SELECT issue_id, issue_type, asset_id, issue_name, status, created_by FROM dbo.issues WHERE issue_id IN ($placeholders) AND status <> 'Done'";
        $params = $selected;
    } else {
        $sql = "SELECT issue_id, issue_type, asset_id, issue_name, status, created_by FROM dbo.issues WHERE status <> 'Done'";
        $params = [];
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) throw new Exception('Gagal mengambil daftar issues: ' . print_r(sqlsrv_errors(), true));

    $issues = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $issues[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    if (empty($issues)) {
        $response['success'] = true;
        $response['message'] = 'Tidak ada issues yang perlu diproses.';
        echo json_encode($response);
        exit;
    }

    // Begin transaction
    sqlsrv_begin_transaction($conn);

    $updated = 0;
    $skipped = [];
    foreach ($issues as $iss) {
        $issue_id = $iss['issue_id'];
        $issue_type = isset($iss['issue_type']) ? $iss['issue_type'] : '';
        $asset_id = $iss['asset_id'];
        $issue_name = $iss['issue_name'];
        $status = $iss['status'];

        // Extra safety: re-check current status to avoid race conditions
        $chk = sqlsrv_query($conn, "SELECT status FROM dbo.issues WHERE issue_id = ?", [$issue_id]);
        if ($chk && $rowChk = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC)) {
            if ($rowChk['status'] === 'Done') {
                // already done, skip
                $skipped[] = ['issue_id' => $issue_id, 'reason' => 'Already Done'];
                if ($chk) sqlsrv_free_stmt($chk);
                continue;
            }
        }
        if ($chk) sqlsrv_free_stmt($chk);

        // TODO: add business-specific checks here (dependencies, approvals). If any check fails, push to skipped.
        // For now, we allow processing but leave a hook for future rules.

        $sqlUpd = "UPDATE dbo.issues SET status = 'Done', tanggal_selesai = GETDATE(), update_at = GETDATE(), update_by = ? WHERE issue_id = ?";
        $res = sqlsrv_query($conn, $sqlUpd, [$username, $issue_id]);
        if ($res === false) {
            throw new Exception('Gagal mengupdate issue #' . $issue_id . ': ' . print_r(sqlsrv_errors(), true));
        }

        // If maintenance type and has asset, update asset status to Used(1) and insert history
        if (trim($issue_type) === 'Maintenance' && !empty($asset_id)) {
            // Get current asset status name
            $sqlA = "SELECT a.id_status, s.nama_status FROM dbo.m_asset a LEFT JOIN dbo.m_status s ON a.id_status = s.id_status WHERE a.id_asset = ?";
            $stA = sqlsrv_query($conn, $sqlA, [$asset_id]);
            $currStatusName = null;
            if ($stA && $rowA = sqlsrv_fetch_array($stA, SQLSRV_FETCH_ASSOC)) {
                $currStatusName = $rowA['nama_status'];
            }
            if ($stA) sqlsrv_free_stmt($stA);

            $usedName = 'Used';
            $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
            sqlsrv_query($conn, $sqlHist, [$asset_id, $currStatusName, $usedName, $issue_name, 'status asset', $username]);

            $sqlAssetUpd = "UPDATE dbo.m_asset SET id_status = 1, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
            sqlsrv_query($conn, $sqlAssetUpd, [$username, $asset_id]);
        }

        $updated++;
    }

    // Commit transaction
    if (!sqlsrv_commit($conn)) {
        throw new Exception('Gagal commit transaksi: ' . print_r(sqlsrv_errors(), true));
    }

    $response['success'] = true;
    $response['message'] = "Berhasil menandai {$updated} issue sebagai Done.";
    if (!empty($skipped)) $response['skipped'] = $skipped;
} catch (Exception $e) {
    // Rollback if possible
    @sqlsrv_rollback($conn);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
sqlsrv_close($conn);
