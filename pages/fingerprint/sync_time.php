<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

// Pastikan timezone server
date_default_timezone_set('Asia/Jakarta');

// Ambil parameter POST
$ip  = $_POST['ip'] ?? '';
$key = $_POST['key'] ?? '0';

if (!$ip) {
    echo json_encode(['status' => 'error', 'message' => 'IP tidak boleh kosong']);
    exit;
}

// Fungsi untuk kirim SOAP
function sendSoapRequest($ip, $xml) {
    $newLine = "\r\n";
    $connect = fsockopen($ip, 80, $errno, $errstr, 2);
    if (!$connect) return false;

    fputs($connect, "POST /iWsService HTTP/1.0".$newLine);
    fputs($connect, "Content-Type: text/xml".$newLine);
    fputs($connect, "Content-Length: ".strlen($xml).$newLine.$newLine);
    fputs($connect, $xml.$newLine);

    $buffer = '';
    while ($response = fgets($connect, 1024)) {
        $buffer .= $response;
    }
    fclose($connect);
    return $buffer;
}

// 1️⃣ Ambil waktu mesin sebelum diubah (GetDate)
$getdate_xml = '<?xml version="1.0" encoding="utf-8"?>
<GetDate>
    <ArgComKey xsi:type="xsd:integer">'.$key.'</ArgComKey>
    <Arg><PIN xsi:type="xsd:integer">0</PIN></Arg>
</GetDate>';

$beforeBuffer = sendSoapRequest($ip, $getdate_xml);
$beforeTime = 'Unknown';
if ($beforeBuffer && preg_match("/<Date>(.*?)<\/Date>.*<Time>(.*?)<\/Time>/s", $beforeBuffer, $matches)) {
    $beforeTime = $matches[1] . ' ' . $matches[2];
}

// 2️⃣ Kirim waktu server ke mesin (SetDate)
$date = date("Y-m-d");
$time = date("H:i:s");

$setdate_xml = '<SetDate>
<ArgComKey xsi:type="xsd:integer">'.$key.'</ArgComKey>
<Arg>
<Date xsi:type="xsd:string">'.$date.'</Date>
<Time xsi:type="xsd:string">'.$time.'</Time>
</Arg>
</SetDate>';

$setBuffer = sendSoapRequest($ip, $setdate_xml);
$afterTime = $date . ' ' . $time;

// Ambil result dari mesin
$result = 'Success';
if ($setBuffer && preg_match("/<Information>(.*?)<\/Information>/", $setBuffer, $matches)) {
    $result = $matches[1];
}

echo json_encode([
    'status' => 'success',
    'message' => $result,
    'before_time' => $beforeTime,
    'after_time' => $afterTime
]);
