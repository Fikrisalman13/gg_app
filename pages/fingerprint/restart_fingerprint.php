<?php
session_start();
header('Content-Type: application/json');

$ip  = $_POST['ip'] ?? '';
$key = $_POST['key'] ?? '0';

if (!$ip) {
    echo json_encode(['status'=>'error','message'=>'IP tidak boleh kosong']);
    exit;
}

// SOAP Envelope untuk restart
$xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
               xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <Restart xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer">'.$key.'</ArgComKey>
    </Restart>
  </soap:Body>
</soap:Envelope>';

$headers = [
    "Content-type: text/xml;charset=\"utf-8\"",
    "Accept: text/xml",
    "Cache-Control: no-cache",
    "Pragma: no-cache",
    "SOAPAction: \"http://tempuri.org/Restart\"",
    "Content-length: " . strlen($xmlRequest)
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$ip/iWsService");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response && $httpCode == 200) {
    // Ambil result sederhana
    if (preg_match("/<Information>(.*?)<\/Information>/", $response, $matches)) {
        $result = $matches[1];
    } else {
        $result = 'Restart Sent';
    }
    echo json_encode(['status'=>'success','message'=>$result]);
} else {
    echo json_encode(['status'=>'error','message'=>"Koneksi gagal atau mesin tidak merespon. $curlError"]);
}
