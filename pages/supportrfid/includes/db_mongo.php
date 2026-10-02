<?php
require_once __DIR__ . '/../vendor/autoload.php';
use MongoDB\Client;

$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$userCollection = $client->api_sum->user;
?>
