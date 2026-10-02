<?php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';

$q = trim($_GET['q'] ?? '');
$like = '%'.$q.'%';
$drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($drv === 'sqlsrv') {
	$st = $pdo->prepare("SELECT TOP 100 id, uomname FROM siin_uom WHERE uomname LIKE ? ORDER BY uomname ASC");
} else {
	$st = $pdo->prepare("SELECT id, uomname FROM siin_uom WHERE uomname LIKE ? ORDER BY uomname ASC LIMIT 100");
}
$st->execute([$like]);
$out=[];
foreach($st as $r){ $out[]=['id'=>(int)$r['id'],'text'=>$r['uomname']]; }
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
