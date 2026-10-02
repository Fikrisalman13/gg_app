<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_POST['ip'])) {
    echo json_encode(['status' => 'Disconnected']);
    exit;
}

$ip = $_POST['ip'];

// Fungsi test koneksi mesin
function testMesinConnection($ip, $timeout = 5) {
    $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
    <GetDate><ArgComKey>0</ArgComKey><Arg><PIN>1</PIN></Arg></GetDate>';

    try {
        $url = "http://$ip/iWsService";
        $headers = [
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: \"\"",
            "Content-length: " . strlen($xmlRequest)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response && $httpCode == 200) {
            return ['status' => 'Connected', 'message' => 'OK'];
        } else {
            return ['status' => 'Disconnected', 'message' => $error ?: 'HTTP ' . $httpCode];
        }
    } catch (Exception $e) {
        return ['status' => 'Disconnected', 'message' => $e->getMessage()];
    }
}

// Test koneksi
$result = testMesinConnection($ip);

// Update database
$sql = "UPDATE dbo.m_fingerprint 
        SET status = ?, status_message = ?, UpdDate = GETDATE() 
        WHERE ip_address = ?";
$params = [$result['status'], $result['message'], $ip];
sqlsrv_query($conn, $sql, $params);

echo json_encode($result);
