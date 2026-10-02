<?php
session_start();
$_SESSION['UserName'] = 'IT1';
$_SESSION['Theme'] = 'primary';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'add_usage',
    'provider_id' => 2,
    'location_id' => 2,
    'month_name' => 'Januari',
    'total_data_used_gb' => 1,
    'total_usage_hours' => 1,
    'keterangan' => 'debug post'
];

include 'laporanpemakaianinternet.php';
