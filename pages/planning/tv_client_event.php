<?php
session_start(); require_once __DIR__.'/planning_tv_auth.php'; header('Content-Type: application/json; charset=utf-8');
$raw=file_get_contents('php://input'); if(strlen($raw)>2048){http_response_code(413);echo json_encode(['ok'=>false]);exit;}
$input=json_decode($raw,true); $event=(string)($input['event']??''); $allowed=['page_load','pageshow_persisted','offline','online','ajax_unauthorized','unexpected_login_navigation'];
if(!in_array($event,$allowed,true)){http_response_code(422);echo json_encode(['ok'=>false]);exit;}
$auth=planning_tv_auth_result(); planning_tv_log($event==='ajax_unauthorized'?'SECURITY':'WARNING','client_'.$event,['auth_reason'=>$auth['reason'],'device_id'=>$auth['device_id']??'','message'=>'Planning TV browser diagnostic event','source_file'=>__FILE__,'source_line'=>__LINE__]); echo json_encode(['ok'=>true]);
