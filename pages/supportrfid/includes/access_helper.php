<?php
function getUserGroup($username) {
    $groups = json_decode(file_get_contents(__DIR__ . '/../data/groups.json'), true);
    foreach ($groups as $g) {
        if (in_array($username, $g['members'])) {
            return $g['group_id'];
        }
    }
    return null;
}

function getAccessRights($groupId) {
    $access = json_decode(file_get_contents(__DIR__ . '/../data/access_rights.json'), true);
    return $access[$groupId] ?? ['menu' => [], 'buttons' => []];
}

function canAccessMenu($groupId, $menuId) {
    $rights = getAccessRights($groupId);
    return in_array($menuId, $rights['menu']);
}

function canAccessButton($groupId, $btnClass) {
    $rights = getAccessRights($groupId);
    return in_array($btnClass, $rights['buttons']);
}
?>
