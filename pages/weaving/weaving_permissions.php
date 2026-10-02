<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php';

if (!defined('WEAVING_MENU_FALLBACK_ID')) {
    define('WEAVING_MENU_FALLBACK_ID', 1323);
}

function weaving_menu_id($conn)
{
    static $menuId = null;
    if ($menuId !== null) return $menuId;

    $sql = "SELECT TOP 1 MenuId
            FROM dbo.SMMenu
            WHERE RTRIM(MenuUrl) = ?
               OR RTRIM(MenuUrl) = ?
            ORDER BY MenuId DESC";
    $stmt = sqlsrv_query($conn, $sql, ['/gg_app/pages/weaving/weaving.php', '/gg_app/pages/weaving/weaving.php/']);
    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $menuId = (int)$row['MenuId'];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    if ($menuId === null || $menuId <= 0) {
        $menuId = WEAVING_MENU_FALLBACK_ID;
    }
    return $menuId;
}

function weaving_permissions($conn)
{
    return getPermissions($conn, $_SESSION['GroupId'] ?? 0, weaving_menu_id($conn));
}

function weaving_can($permissions, $key)
{
    return !empty($permissions[$key]) && (int)$permissions[$key] === 1;
}

function weaving_require($conn, $key, $json = false)
{
    $permissions = weaving_permissions($conn);
    if (weaving_can($permissions, $key)) return $permissions;

    $messages = [
        'CanView' => 'Anda tidak memiliki hak melihat data.',
        'CanAdd' => 'Anda tidak memiliki hak menambah data.',
        'CanEdit' => 'Anda tidak memiliki hak mengubah data.',
        'CanDelete' => 'Anda tidak memiliki hak menghapus data.',
    ];
    $message = $messages[$key] ?? 'Anda tidak memiliki hak akses.';

    if ($json) {
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $_SESSION['error'] = $message;
    $target = $key === 'CanView' ? '/gg_app/index.php' : '/gg_app/pages/weaving/weaving.php';
    header('Location: ' . $target);
    exit;
}
