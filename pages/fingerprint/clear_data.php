<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

include '../../koneksi.php';

function sendSoapRequest($ip, $commKey) {
    // SELALU gunakan Value 1 untuk clear semua data
    $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<ClearData>
    <ArgComKey xsi:type="xsd:integer">'.$commKey.'</ArgComKey>
    <Arg>
        <Value xsi:type="xsd:integer">1</Value>
    </Arg>
</ClearData>';

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response && $httpCode == 200) {
            // Parse XML response
            $xml = simplexml_load_string($response);
            
            if ($xml !== false) {
                // Cari element Result dan Information
                $resultNode = $xml->xpath('//Result');
                $infoNode = $xml->xpath('//Information');
                
                if ($resultNode && count($resultNode) > 0 && $infoNode && count($infoNode) > 0) {
                    $resultValue = (string)$resultNode[0];
                    $infoText = (string)$infoNode[0];
                    
                    // Result=1 dan Information="Successfully!" berarti sukses
                    if ($resultValue == '1' && stripos($infoText, 'Successfully') !== false) {
                        return [
                            'status' => 'success', 
                            'message' => 'Semua data berhasil dihapus'
                        ];
                    }
                }
            }
            
            // Fallback: cek langsung di response body
            if (stripos($response, 'Successfully') !== false) {
                return [
                    'status' => 'success', 
                    'message' => 'Semua data berhasil dihapus'
                ];
            }
            
            return [
                'status' => 'error', 
                'message' => 'Gagal menghapus data. Response tidak dikenali.'
            ];
        } else {
            return [
                'status' => 'error', 
                'message' => "HTTP Error: $httpCode - $curlError"
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'error', 
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}

if ($_POST['action'] == 'clear_single') {
    $ip = $_POST['ip'];
    $key = $_POST['key'];
    
    $result = sendSoapRequest($ip, $key);
    echo json_encode($result);
    
} elseif ($_POST['action'] == 'clear_all_mesin') {
    $results = [];
    
    // Ambil semua mesin dari database
    $sql = "SELECT ip_address, comm_key FROM dbo.m_fingerprint";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $ip = $row['ip_address'];
            $key = $row['comm_key'];
            
            $result = sendSoapRequest($ip, $key);
            
            $results[] = [
                'ip' => $ip,
                'status' => $result['status'],
                'message' => $result['message']
            ];
            
            // Delay antara requests
            usleep(500000); // 0.5 second
        }
        sqlsrv_free_stmt($stmt);
    }
    
    echo json_encode(['status' => 'completed', 'results' => $results]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>