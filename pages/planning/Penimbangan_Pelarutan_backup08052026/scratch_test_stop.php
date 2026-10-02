<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$_SESSION['UserName'] = 'SYSTEM';

$_POST = [
    'action' => 'stop',
    'id' => 2112,
    'rtgmsid' => 999,
    'shift_end_id' => '1',
    'shift_end_name' => 'SHIFT A',
    'lebar_kain' => '',
    'hasil_celup' => 'PASS',
];

ob_start();
include 'api_aktual_paddry.php';
$out = ob_get_clean();
echo "OUTPUT:\n" . $out;
