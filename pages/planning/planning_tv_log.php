<?php
const PLANNING_TV_LOG_MAX_BYTES = 5242880;
const PLANNING_TV_LOG_RETENTION_DAYS = 7;
function planning_tv_log(string $severity, string $action, array $context = []): string {
    $requestId=$context['request_id'] ?? bin2hex(random_bytes(8)); $logDir=dirname(__DIR__,2).'/logs';
    if(!is_dir($logDir) && !mkdir($logDir,0775,true) && !is_dir($logDir)) return $requestId;
    $logFile=$logDir.'/error-'.date('Y-m-d').'.log'; if(is_file($logFile) && filesize($logFile)>=PLANNING_TV_LOG_MAX_BYTES) return $requestId;
    planning_tv_cleanup_logs($logDir); $deviceId=(string)($context['device_id'] ?? ''); $reason=(string)($context['auth_reason'] ?? '');
    $dedupeFile=sys_get_temp_dir().'/planning-tv-'.hash('sha256',$deviceId.'|'.$action.'|'.$reason).'.dedupe';
    if(is_file($dedupeFile) && time()-filemtime($dedupeFile)<600) return $requestId; @touch($dedupeFile);
    $entry=['timestamp'=>date(DATE_ATOM),'severity'=>in_array($severity,['WARNING','ERROR','CRITICAL','SECURITY'],true)?$severity:'WARNING','request_id'=>$requestId,'module'=>'planning_tv','action'=>substr($action,0,80),'user'=>isset($_SESSION['UserName'])?substr((string)$_SESSION['UserName'],0,40):null,'message'=>substr((string)($context['message'] ?? 'Planning TV authentication event'),0,200),'source_file'=>basename((string)($context['source_file'] ?? 'unknown')),'source_line'=>(int)($context['source_line'] ?? 0),'context'=>['auth_reason'=>substr($reason,0,40),'device_id'=>$deviceId===''?null:substr($deviceId,0,8),'session_present'=>isset($_SESSION['UserName']),'token_present'=>!empty($_COOKIE['planning_tv_device']),'method'=>substr((string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),0,10),'path'=>substr((string)parse_url($_SERVER['REQUEST_URI'] ?? '',PHP_URL_PATH),0,160),'ip'=>substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45),'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,160)]];
    file_put_contents($logFile,json_encode($entry,JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX); return $requestId;
}
function planning_tv_cleanup_logs(string $logDir): void { $marker=$logDir.'/.planning-tv-cleanup'; if(is_file($marker)&&date('Y-m-d',filemtime($marker))===date('Y-m-d')) return; foreach(glob($logDir.'/error-*.log') ?: [] as $file){if(is_file($file)&&filemtime($file)<time()-(PLANNING_TV_LOG_RETENTION_DAYS*86400)) @unlink($file);} @touch($marker); }
