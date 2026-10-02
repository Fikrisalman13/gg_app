<?php
require_once '../../config.php';
require_once '../../routeros_api.class.php';

$API = new RouterosAPI();
if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

    $data = $API->comm("/queue/simple/print");
    echo "<pre>";
    print_r($data);
    echo "</pre>";

    $API->disconnect();
} else {
    echo "Gagal konek Mikrotik.";
}
?>
