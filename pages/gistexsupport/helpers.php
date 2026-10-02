<?php
function gistexCurrentUser() {
    foreach (['fullName','FullName','username','Username','user_name','nama','Nama','name','Name','nik'] as $key) {
        if (!empty($_SESSION[$key])) return (string)$_SESSION[$key];
    }
    return 'system';
}

function gistexDate($value) {
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d H:i:s');
    return (string)($value ?? '');
}
