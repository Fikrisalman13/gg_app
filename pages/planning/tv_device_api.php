<?php
session_start();
require_once __DIR__ . '/planning_tv_auth.php';
header('Content-Type: application/json; charset=utf-8');
$action=$_GET['action'] ?? 'status';
if($action==='register') {
    $existing=planning_tv_authenticate();
    if($existing!==null) {
        echo json_encode(['ok'=>true,'device'=>$existing,'reused'=>true]);
        exit;
    }
    if(!isset($_SESSION['UserName'])) planning_tv_unauthorized();
    $input=json_decode(file_get_contents('php://input'),true) ?: [];
    echo json_encode(['ok'=>true,'device'=>planning_tv_register_device($_SESSION['UserName'],(string)($input['label'] ?? 'Smart TV')),'reused'=>false]);
    exit;
}
if($action==='revoke') { echo json_encode(['ok'=>planning_tv_revoke_current_device()]); exit; }
$device=planning_tv_authenticate(); echo json_encode(['ok'=>$device!==null,'device'=>$device]);
