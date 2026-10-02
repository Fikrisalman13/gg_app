<?php
/* ----------------------------------------------------
 * PERMISSION SYSTEM (Reusable)
 * ---------------------------------------------------- */

date_default_timezone_set('Asia/Jakarta');

/**
 * Ambil izin akses user terhadap menu tertentu
 */
function getPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

    $permissions = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];

    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    return $permissions;
}

/**
 * Ambil hak akses menu tertentu milik user yang sedang login
 */
function userPermissions($conn, $menuId)
{
    return getPermissions($conn, $_SESSION['GroupId'], $menuId);
}

/** BLOCK JIKA TIDAK BOLEH VIEW */
function requireView($conn, $menuId)
{
    $p = userPermissions($conn, $menuId);
    if ($p['CanView'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki akses untuk melihat halaman ini.";
        header("Location: ../index.php");
        exit;
    }
}

/** BLOCK JIKA TIDAK BOLEH ADD */
function requireAdd($conn, $menuId)
{
    $p = userPermissions($conn, $menuId);
    if ($p['CanAdd'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
        header("Location: {$_SERVER['HTTP_REFERER']}");
        exit;
    }
}

/** BLOCK JIKA TIDAK BOLEH EDIT */
function requireEdit($conn, $menuId)
{
    $p = userPermissions($conn, $menuId);
    if ($p['CanEdit'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
        header("Location: {$_SERVER['HTTP_REFERER']}");
        exit;
    }
}

/** BLOCK JIKA TIDAK BOLEH DELETE */
function requireDelete($conn, $menuId)
{
    $p = userPermissions($conn, $menuId);
    if ($p['CanDelete'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
        header("Location: {$_SERVER['HTTP_REFERER']}");
        exit;
    }
}
