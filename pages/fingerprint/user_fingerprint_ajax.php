<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

// ================= Helper ================= //
function getMachineInfo($conn, $mesinId) {
    $sql = "SELECT ip_address, comm_key FROM dbo.m_fingerprint WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$mesinId]);
    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        return [
            'ip_address' => $row['ip_address'],
            'comm_key' => $row['comm_key']
        ];
    }
    return false;
}

function sendSOAPRequest($ip, $xmlRequest, $action = "") {
    $headers = [
        "POST /iWsService HTTP/1.1",
        "Host: $ip",
        "Content-Type: text/xml; charset=utf-8",
        "Content-Length: " . strlen($xmlRequest),
        "SOAPAction: \"http://tempuri.org/$action\"",
        "Connection: close"
    ];
    $request = implode("\r\n", $headers) . "\r\n\r\n" . $xmlRequest;

    $fp = @fsockopen($ip, 80, $errno, $errstr, 10);
    if (!$fp) return ["success" => false, "message" => "Koneksi gagal: $errstr ($errno)"];

    fwrite($fp, $request);
    $response = stream_get_contents($fp);
    fclose($fp);
    return ["success" => true, "response" => $response];
}

// ================= Router ================= //
$action = $_POST['action'] ?? '';
if (!$action) {
    echo json_encode(['status'=>'error','message'=>'Action tidak ditemukan']); exit;
}

// ================= UPDATE USER (INCLUDING PASSWORD) ================= //
if ($action === 'update_user') {
    $userId = $_POST['user_id'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $name = $_POST['name'] ?? '';
    $privilege = $_POST['privilege'] ?? 0;
    $password = $_POST['password'] ?? '';
    
    if (empty($userId) || empty($pin) || empty($name)) {
        echo json_encode(['status'=>'error','message'=>'User ID, PIN dan Nama wajib diisi']);
        exit;
    }
    
    try {
        // Jika password kosong, jangan update password
        if (empty($password)) {
            $sql = "UPDATE dbo.user_fingerprint 
                    SET pin = ?, name = ?, privilege = ?, updated_at = GETDATE() 
                    WHERE id = ?";
            $params = [$pin, $name, $privilege, $userId];
        } else {
            $sql = "UPDATE dbo.user_fingerprint 
                    SET pin = ?, name = ?, privilege = ?, password = ?, updated_at = GETDATE() 
                    WHERE id = ?";
            $params = [$pin, $name, $privilege, $password, $userId];
        }
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            echo json_encode([
                'status' => 'success',
                'message' => 'User berhasil diupdate'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal mengupdate user: ' . print_r(sqlsrv_errors(), true)
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ================= LOAD USERS ================= //
if ($action === 'load') {
    $machineInfo = getMachineInfo($conn, $_POST['mesin_id'] ?? '');
    if (!$machineInfo) { echo json_encode(['status'=>'error','message'=>'Mesin tidak ditemukan']); exit; }

    $commKey = $machineInfo['comm_key'];
    $ip = $machineInfo['ip_address'];

    $xml = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetAllUserInfo xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
    </GetAllUserInfo>
  </soap:Body>
</soap:Envelope>';

    $result = sendSOAPRequest($ip, $xml, "GetAllUserInfo");
    $data = [];

    if ($result['success'] && preg_match_all(
        '/<Row>.*?<PIN>(\d+)<\/PIN>.*?<Name>(.*?)<\/Name>.*?<Password>(.*?)<\/Password>.*?<Privilege>(\d+)<\/Privilege>.*?<PIN2>(\d*)<\/PIN2>.*?<\/Row>/s',
        $result['response'], $matches, PREG_SET_ORDER
    )) {
        foreach ($matches as $m) {
            $data[] = [
                'PIN'       => $m[1],
                'Name'      => $m[2],
                'Password'  => $m[3],
                'Privilege' => $m[4],
                'PIN2'      => $m[5],
            ];
        }
    }
    echo json_encode(['data'=>$data]); exit;
}

// ================= ADD USER ================= //
if ($action === 'add') {
    $mesinId = $_POST['mesin_id'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $nama = $_POST['nama'] ?? '';
    if (!$mesinId || !$pin || !$nama) {
        echo json_encode(['status'=>'error','message'=>'Mesin, PIN, Nama wajib diisi']); exit;
    }
    
    $machineInfo = getMachineInfo($conn, $mesinId);
    if (!$machineInfo) { echo json_encode(['status'=>'error','message'=>'Mesin tidak ditemukan']); exit; }

    $commKey = $machineInfo['comm_key'];
    $ip = $machineInfo['ip_address'];

    $xml = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SetUserInfo xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">0</PIN>
        <Name xsi:type="xsd:string">'.htmlspecialchars($nama).'</Name>
        <Password xsi:type="xsd:string">'.($_POST['password'] ?? '').'</Password>
        <Privilege xsi:type="xsd:integer">'.($_POST['privilege'] ?? '0').'</Privilege>
        <Group xsi:type="xsd:string">0</Group>
        <Card xsi:type="xsd:integer">0</Card>
        <PIN2 xsi:type="xsd:integer">'.$pin.'</PIN2>
        <TZ1 xsi:type="xsd:integer">0</TZ1>
        <TZ2 xsi:type="xsd:integer">0</TZ2>
        <TZ3 xsi:type="xsd:integer">0</TZ3>
      </Arg>
    </SetUserInfo>
  </soap:Body>
</soap:Envelope>';

    $res = sendSOAPRequest($ip,$xml,"SetUserInfo");
    echo $res['success']
        ? json_encode(['status'=>'success','message'=>'User berhasil ditambahkan'])
        : json_encode(['status'=>'error','message'=>$res['message']]);
    exit;
}

// ================= DELETE USER ================= //
if ($action === 'delete') {
    $mesinId = $_POST['mesin_id'] ?? '';
    $pin     = $_POST['pin'] ?? '';

    if (!$mesinId || !$pin) {
        echo json_encode(['status'=>'error','message'=>'Mesin dan PIN wajib diisi']); 
        exit;
    }

    $machineInfo = getMachineInfo($conn, $mesinId);
    if (!$machineInfo) {
        echo json_encode(['status'=>'error','message'=>'Mesin tidak ditemukan']); 
        exit;
    }

    $commKey = $machineInfo['comm_key'];
    $ip = $machineInfo['ip_address'];

    $xml = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <DeleteUser xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">'.htmlspecialchars($pin).'</PIN>
      </Arg>
    </DeleteUser>
  </soap:Body>
</soap:Envelope>';

    $res = sendSOAPRequest($ip, $xml, "DeleteUser");

    echo $res['success']
        ? json_encode(['status'=>'success','message'=>'User berhasil dihapus'])
        : json_encode(['status'=>'error','message'=>$res['message']]);
    exit;
}

// ================= GET FINGERPRINT TEMPLATE ================= //
if ($action === 'get_fingerprint') {
    $mesinId = $_POST['mesin_id'] ?? '';
    $pin = $_POST['pin'] ?? '';

    if (!$mesinId || !$pin) {
        echo json_encode(['status'=>'error','message'=>'Mesin dan PIN wajib diisi']); 
        exit;
    }

    $machineInfo = getMachineInfo($conn, $mesinId);
    if (!$machineInfo) {
        echo json_encode(['status'=>'error','message'=>'Mesin tidak ditemukan']); 
        exit;
    }

    $commKey = $machineInfo['comm_key'];
    $ip = $machineInfo['ip_address'];

    $allTemplates = [];
    for ($currentFingerId = 0; $currentFingerId <= 9; $currentFingerId++) {
        $xml = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetUserTemplate xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">'.htmlspecialchars($pin).'</PIN>
        <FingerID xsi:type="xsd:integer">'.$currentFingerId.'</FingerID>
      </Arg>
    </GetUserTemplate>
  </soap:Body>
</soap:Envelope>';

        $result = sendSOAPRequest($ip, $xml, "GetUserTemplate");
        
        if ($result['success']) {
            if (preg_match_all(
                '/<Row>.*?<PIN>(\d+)<\/PIN>.*?<FingerID>(\d+)<\/FingerID>.*?<Size>(\d+)<\/Size>.*?<Valid>(\d+)<\/Valid>.*?<Template>(.*?)<\/Template>.*?<\/Row>/s',
                $result['response'], $matches, PREG_SET_ORDER
            )) {
                foreach ($matches as $m) {
                    if ($m[4] == '1' && !empty(trim($m[5]))) {
                        $allTemplates[] = [
                            'PIN' => $m[1],
                            'FingerID' => $m[2],
                            'Size' => $m[3],
                            'Valid' => $m[4],
                            'Template' => $m[5]
                        ];
                    }
                }
            }
        }
        usleep(50000); // delay
    }

    echo count($allTemplates) > 0
        ? json_encode(['status'=>'success','templates'=>$allTemplates,'message'=>count($allTemplates).' template sidik jari ditemukan'])
        : json_encode(['status'=>'error','templates'=>[],'message'=>'Tidak ada template sidik jari ditemukan']);
    exit;
}

// ================= DOWNLOAD USERS WITH FINGERPRINT ================= //
if ($action === 'download_with_fingerprint') {
    $mesinId = $_POST['mesin_id'] ?? '';
    $users   = $_POST['users'] ?? [];

    if (empty($mesinId) || empty($users)) {
        echo json_encode(['status'=>'error','message'=>'Data tidak lengkap!']);
        exit;
    }

    if (is_string($users)) {
        $users = json_decode($users, true);
    }

    if (!is_array($users) || count($users) === 0) {
        echo json_encode(['status'=>'error','message'=>'Tidak ada data user']);
        exit;
    }

    $machineInfo = getMachineInfo($conn, $mesinId);
    if (!$machineInfo) {
        echo json_encode(['status'=>'error','message'=>'Mesin tidak ditemukan']);
        exit;
    }

    $commKey = $machineInfo['comm_key'];
    $ip = $machineInfo['ip_address'];

    $inserted = 0; $updated = 0; $error = 0; $errorMsg = [];
    $total = count($users);

    // Mulai transaction
    if (sqlsrv_begin_transaction($conn) === false) {
        echo json_encode(['status'=>'error','message'=>'Gagal memulai transaction database']);
        exit;
    }

    foreach ($users as $index => $user) {
        $pin  = $user['PIN2'] ?? $user['PIN'] ?? '';
        $name = $user['Name'] ?? '';
        
        if (!$pin || !$name) {
            $error++; 
            $errorMsg[] = "Data ke-" . ($index + 1) . ": PIN atau Nama kosong";
            continue;
        }
        
        $password  = $user['Password'] ?? '';
        $privilege = $user['Privilege'] ?? 0;

        // --- Ambil template sidik jari ---
        $fingerprintData  = [];
        $fingerprintIndex = 0; // Menggunakan integer bitmask
        $templateSize     = 0;
        $hasFingerprint   = false;

        for ($fingerId = 0; $fingerId <= 9; $fingerId++) {
            $xml = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetUserTemplate xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">'.htmlspecialchars($pin).'</PIN>
        <FingerID xsi:type="xsd:integer">'.$fingerId.'</FingerID>
      </Arg>
    </GetUserTemplate>
  </soap:Body>
</soap:Envelope>';

            $result = sendSOAPRequest($ip, $xml, "GetUserTemplate");
            
            if ($result['success']) {
                // Parse XML response
                if (preg_match_all(
                    '/<Row>.*?<PIN>(\d+)<\/PIN>.*?<FingerID>(\d+)<\/FingerID>.*?<Size>(\d+)<\/Size>.*?<Valid>(\d+)<\/Valid>.*?<Template>(.*?)<\/Template>.*?<\/Row>/s',
                    $result['response'], $matches, PREG_SET_ORDER
                )) {
                    foreach ($matches as $m) {
                        if ($m[4] == '1' && !empty(trim($m[5]))) {
                            $template = trim($m[5]);
                            $size = (int)$m[3];
                            
                            // Simpan data template
                            $fingerprintData[] = [
                                'finger_id' => (int)$m[2],
                                'size' => $size,
                                'template' => $template,
                                'valid' => (int)$m[4]
                            ];
                            
                            // Set bitmask untuk fingerprint_index
                            $fingerprintIndex |= (1 << (int)$m[2]);
                            $templateSize += $size;
                            $hasFingerprint = true;
                        }
                    }
                }
            }
            usleep(100000); // delay 0.1s untuk stabilitas SOAP
        }

        // Konversi fingerprint data ke format binary
        $fpBinary = null;
        if ($hasFingerprint && !empty($fingerprintData)) {
            // Serialize data ke JSON kemudian konversi ke binary
            $jsonData = json_encode($fingerprintData);
            $fpBinary = $jsonData; // SQL Server akan handle konversi ke varbinary
        }

        // --- cek user di DB ---
        $checkSql = "SELECT id FROM dbo.user_fingerprint WHERE pin = ? AND mesin_id = ?";
        $checkParams = [$pin, $mesinId];
        $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
        
        if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
            // UPDATE existing user
            $updateSql = "UPDATE dbo.user_fingerprint 
                         SET name = ?, password = ?, privilege = ?, 
                             fingerprint_data = CONVERT(varbinary(max), ?), 
                             fingerprint_index = ?, template_size = ?, updated_at = GETDATE() 
                         WHERE pin = ? AND mesin_id = ?";
            
            $updateParams = [
                $name, 
                $password, 
                $privilege, 
                $fpBinary,  // fingerprint_data sebagai string, akan dikonversi ke varbinary
                $fingerprintIndex, // integer bitmask
                $templateSize, 
                $pin, 
                $mesinId
            ];
            
            $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
            
            if ($updateStmt) {
                $updated++;
            } else {
                $error++;
                $errorMsg[] = "PIN $pin: Gagal update - " . print_r(sqlsrv_errors(), true);
            }
        } else {
            // INSERT new user
            $insertSql = "INSERT INTO dbo.user_fingerprint 
                         (pin, name, password, privilege, fingerprint_data, 
                          fingerprint_index, template_size, mesin_id, created_at, updated_at)
                         VALUES (?, ?, ?, ?, CONVERT(varbinary(max), ?), ?, ?, ?, GETDATE(), GETDATE())";
            
            $insertParams = [
                $pin, 
                $name, 
                $password, 
                $privilege, 
                $fpBinary,  // fingerprint_data sebagai string, akan dikonversi ke varbinary
                $fingerprintIndex, // integer bitmask
                $templateSize, 
                $mesinId
            ];
            
            $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
            
            if ($insertStmt) {
                $inserted++;
            } else {
                $error++;
                $errorMsg[] = "PIN $pin: Gagal insert - " . print_r(sqlsrv_errors(), true);
            }
        }
    }

    // Commit atau rollback transaction
    if ($error > 0) {
        sqlsrv_rollback($conn);
        echo json_encode([
            'status'  => 'error',
            'message' => "Sync gagal. Inserted: $inserted, Updated: $updated, Error: $error dari $total user",
            'errors'  => array_slice($errorMsg, 0, 10) // Batasi output error
        ]);
    } else {
        sqlsrv_commit($conn);
        echo json_encode([
            'status'  => 'success',
            'message' => "Sync berhasil. Inserted: $inserted, Updated: $updated dari $total user. Template sidik jari: " . ($hasFingerprint ? 'Ada' : 'Tidak ada')
        ]);
    }
    exit;
}

// ================= UPLOAD USERS TO MACHINE ================= //
if ($action === 'upload_to_machine') {
    $users = $_POST['users'] ?? '';
    $targetMachineId = $_POST['target_machine_id'] ?? '';
    
    if (empty($users) || empty($targetMachineId)) {
        echo json_encode(['status'=>'error','message'=>'Data tidak lengkap!']);
        exit;
    }
    
    // Decode JSON ke array
    $users = json_decode($users, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status'=>'error','message'=>'Format data user tidak valid']);
        exit;
    }
    
    if (!is_array($users) || count($users) === 0) {
        echo json_encode(['status'=>'error','message'=>'Tidak ada data user untuk diupload']);
        exit;
    }
    
    // Ambil IP mesin tujuan
    $machineInfo = getMachineInfo($conn, $targetMachineId);
    if (!$machineInfo) {
        echo json_encode(['status'=>'error','message'=>'Mesin tujuan tidak ditemukan']);
        exit;
    }

    $commKey = $machineInfo['comm_key'];
    $targetIP = $machineInfo['ip_address'];
    
    $uploadedCount = 0;
    $errorCount = 0;
    $errorMessages = [];
    
    foreach ($users as $user) {
        $pin = $user['pin'] ?? '';
        $name = $user['name'] ?? '';
        
        if (empty($pin) || empty($name)) {
            $errorCount++;
            $errorMessages[] = "Data tidak lengkap: PIN atau Nama kosong";
            continue;
        }
        
        // PERBAIKAN: Ambil data lengkap user dari database termasuk password
        $userId = $user['id'] ?? '';
        $password = '';
        $privilege = 0;
        
        if (!empty($userId)) {
            $userSql = "SELECT password, privilege FROM dbo.user_fingerprint WHERE id = ?";
            $userStmt = sqlsrv_query($conn, $userSql, [$userId]);
            if ($userStmt && $userData = sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC)) {
                $password = $userData['password'] ?? '';
                $privilege = $userData['privilege'] ?? 0;
            }
        }
        
        // XML untuk upload user ke mesin - PERBAIKAN: Sertakan password dan privilege
        $xml = '<?xml version="1.0"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
            <SetUserInfo xmlns="http://tempuri.org/">
            <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
            <Arg>
                <PIN xsi:type="xsd:integer">0</PIN>
                <Name xsi:type="xsd:string">'.htmlspecialchars($name).'</Name>
                <Password xsi:type="xsd:string">'.htmlspecialchars($password).'</Password>
                <Privilege xsi:type="xsd:integer">'.(int)$privilege.'</Privilege>
                <Group xsi:type="xsd:string">0</Group>
                <Card xsi:type="xsd:integer">0</Card>
                <PIN2 xsi:type="xsd:integer">'.$pin.'</PIN2>
                <TZ1 xsi:type="xsd:integer">0</TZ1>
                <TZ2 xsi:type="xsd:integer">0</TZ2>
                <TZ3 xsi:type="xsd:integer">0</TZ3>
            </Arg>
            </SetUserInfo>
        </soap:Body>
        </soap:Envelope>';
        
        $res = sendSOAPRequest($targetIP, $xml, "SetUserInfo");
        
        if ($res['success']) {
            $uploadedCount++;
        } else {
            $errorCount++;
            $errorMessages[] = "PIN $pin: Gagal upload - " . $res['message'];
        }
    }
    
    if ($errorCount > 0) {
        echo json_encode([
            'status' => 'warning',
            'message' => "Upload selesai. Berhasil: $uploadedCount, Gagal: $errorCount",
            'errors' => $errorMessages
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => "Berhasil mengupload $uploadedCount user ke mesin"
        ]);
    }
    exit;
}

// ================= UPLOAD WITH FINGERPRINT TO MACHINE (FIXED RESPONSE PARSING) ================= //
if ($action === 'upload_with_fingerprint_to_machine') {
    $users = $_POST['users'] ?? '';
    $targetMachineId = $_POST['target_machine_id'] ?? '';
    
    if (empty($users) || empty($targetMachineId)) {
        echo json_encode(['status'=>'error','message'=>'Data tidak lengkap!']);
        exit;
    }
    
    $users = json_decode($users, true);
    if (!is_array($users) || count($users) === 0) {
        echo json_encode(['status'=>'error','message'=>'Tidak ada data user untuk diupload']);
        exit;
    }
    
    $machineInfo = getMachineInfo($conn, $targetMachineId);
    if (!$machineInfo) {
        echo json_encode(['status'=>'error','message'=>'Mesin tujuan tidak ditemukan']);
        exit;
    }

    $commKey = $machineInfo['comm_key'];
    $targetIP = $machineInfo['ip_address'];
    
    $results = [
        'user_success' => 0,
        'template_success' => 0,
        'template_attempted' => 0,
        'debug_info' => [],
        'errors' => []
    ];
    
    foreach ($users as $userIndex => $user) {
        $userId = $user['id'] ?? '';
        $pin = $user['pin'] ?? '';
        $name = $user['name'] ?? '';
        
        if (empty($pin) || empty($name)) {
            $results['errors'][] = "User #$userIndex: PIN atau Nama kosong";
            continue;
        }
        
        $userDebug = [
            'pin' => $pin,
            'user_id' => $userId,
            'steps' => []
        ];
        
        // Get user data from database
        $userSql = "SELECT * FROM dbo.user_fingerprint WHERE id = ?";
        $userStmt = sqlsrv_query($conn, $userSql, [$userId]);
        if (!$userStmt || !($userData = sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC))) {
            $results['errors'][] = "PIN $pin: Data tidak ditemukan di database";
            continue;
        }
        
        $userDebug['db_data_found'] = true;
        $userDebug['has_fingerprint_data'] = !empty($userData['fingerprint_data']);
        
        // Step 1: Delete existing user first (clean start)
        $userDebug['steps']['delete'] = 'starting';
        $xmlDelete = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <DeleteUser xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">'.$pin.'</PIN>
      </Arg>
    </DeleteUser>
  </soap:Body>
</soap:Envelope>';
        
        $deleteResult = sendSOAPRequest($targetIP, $xmlDelete, "DeleteUser");
        $userDebug['steps']['delete'] = $deleteResult['success'] ? 'success' : 'failed';
        if (!$deleteResult['success']) {
            $userDebug['steps']['delete_error'] = $deleteResult['message'];
        }
        
        // Wait after delete
        usleep(300000);
        
        // Step 2: Create user
        $userDebug['steps']['create_user'] = 'starting';
        $xmlUser = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SetUserInfo xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN>0</PIN>
        <Name>'.htmlspecialchars($userData['name']).'</Name>
        <Password>'.($userData['password'] ?? '').'</Password>
        <Privilege>'.($userData['privilege'] ?? 0).'</Privilege>
        <Group>0</Group>
        <Card>0</Card>
        <PIN2>'.$pin.'</PIN2>
        <TZ1>0</TZ1>
        <TZ2>0</TZ2>
        <TZ3>0</TZ3>
      </Arg>
    </SetUserInfo>
  </soap:Body>
</soap:Envelope>';
        
        $userResult = sendSOAPRequest($targetIP, $xmlUser, "SetUserInfo");
        
        if (!$userResult['success']) {
            $userDebug['steps']['create_user'] = 'failed';
            $userDebug['steps']['create_user_error'] = $userResult['message'];
            $results['errors'][] = "PIN $pin: Gagal buat user - " . $userResult['message'];
            $results['debug_info'][] = $userDebug;
            continue;
        }
        
        $userDebug['steps']['create_user'] = 'success';
        $results['user_success']++;
        
        // Wait before uploading templates
        usleep(500000);
        
        // Step 3: Upload fingerprint templates
        if (!empty($userData['fingerprint_data'])) {
            $fingerprintData = $userData['fingerprint_data'];
            
            // Handle varbinary data
            if (is_resource($fingerprintData)) {
                $fingerprintData = stream_get_contents($fingerprintData);
            }
            
            if (!empty($fingerprintData)) {
                // Try to detect if it's JSON format
                $userDebug['fingerprint_data_length'] = strlen($fingerprintData);
                $userDebug['fingerprint_data_sample'] = substr($fingerprintData, 0, 100);
                
                $templates = [];
                
                // Method 1: Try JSON decode first
                $jsonData = json_decode($fingerprintData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $userDebug['data_format'] = 'json';
                    $templates = $jsonData;
                } else {
                    // Method 2: Try as serialized PHP data
                    $unserialized = @unserialize($fingerprintData);
                    if ($unserialized !== false && is_array($unserialized)) {
                        $userDebug['data_format'] = 'serialized';
                        $templates = $unserialized;
                    } else {
                        // Method 3: Try as raw string with manual parsing
                        $userDebug['data_format'] = 'raw';
                        $userDebug['json_error'] = json_last_error_msg();
                        
                        // If it's a string that looks like JSON but has encoding issues
                        if (strpos($fingerprintData, '[{') !== false) {
                            // Try to fix encoding and decode again
                            $cleanedData = mb_convert_encoding($fingerprintData, 'UTF-8', 'UTF-8');
                            $templates = json_decode($cleanedData, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $userDebug['data_format'] = 'json_fixed';
                            }
                        }
                    }
                }
                
                $userDebug['template_count'] = count($templates);
                $userDebug['templates_found'] = [];
                
                if (!empty($templates) && is_array($templates)) {
                    $userDebug['steps']['upload_templates'] = 'starting';
                    
                    foreach ($templates as $index => $template) {
                        if (isset($template['valid']) && $template['valid'] == 1 && 
                            isset($template['template']) && !empty(trim($template['template']))) {
                            
                            $templateData = trim($template['template']);
                            $fingerId = $template['finger_id'] ?? $index;
                            $size = strlen($templateData);
                            
                            $userDebug['templates_found'][] = [
                                'finger_id' => $fingerId,
                                'size' => $size,
                                'template_preview' => substr($templateData, 0, 30) . '...'
                            ];
                            
                            // Format XML using the working format
                            $xmlTemplate = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SetUserTemplate xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">'.$pin.'</PIN>
        <FingerID xsi:type="xsd:integer">'.$fingerId.'</FingerID>
        <Size>'.$size.'</Size>
        <Valid>1</Valid>
        <Template>'.$templateData.'</Template>
      </Arg>
    </SetUserTemplate>
  </soap:Body>
</soap:Envelope>';
                            
                            $results['template_attempted']++;
                            $userDebug['steps']["template_$fingerId"] = 'uploading';
                            
                            $templateResult = sendSOAPRequest($targetIP, $xmlTemplate, "SetUserTemplate");
                            
                            if ($templateResult['success']) {
                                // Parse response - FIXED PARSING LOGIC
                                $response = $templateResult['response'];
                                $userDebug['steps']["template_$fingerId"] = 'response_received';
                                $userDebug['steps']["template_{$fingerId}_response"] = substr($response, 0, 200);
                                
                                // Check for success indicators - FIXED LOGIC
                                $success = false;
                                $resultCode = null;
                                $information = null;
                                
                                // Look for SetUserTemplateResult
                                if (preg_match('/<SetUserTemplateResult>(\d+)<\/SetUserTemplateResult>/', $response, $matches)) {
                                    $resultCode = $matches[1];
                                    $success = ($resultCode == '1');
                                }
                                
                                // Look for Information tag
                                if (preg_match('/<Information>(.*?)<\/Information>/', $response, $infoMatches)) {
                                    $information = $infoMatches[1];
                                    // If Information contains "Success" or "Successfully", consider it success
                                    if (stripos($information, 'success') !== false) {
                                        $success = true;
                                    }
                                }
                                
                                // If no specific tags found but response contains success indicators
                                if (!$success) {
                                    if (stripos($response, 'success') !== false) {
                                        $success = true;
                                    }
                                }
                                
                                if ($success) {
                                    $results['template_success']++;
                                    $userDebug['steps']["template_$fingerId"] = 'success';
                                    $userDebug['steps']["template_{$fingerId}_result"] = "Success - Info: " . ($information ?: 'No info');
                                } else {
                                    $errorMsg = "PIN $pin Finger $fingerId: Template mungkin gagal";
                                    if ($resultCode !== null) $errorMsg .= " (Result: $resultCode)";
                                    if ($information !== null) $errorMsg .= " (Info: $information)";
                                    $results['errors'][] = $errorMsg;
                                    $userDebug['steps']["template_$fingerId"] = 'failed';
                                    $userDebug['steps']["template_{$fingerId}_error"] = $errorMsg;
                                }
                            } else {
                                $errorMsg = "PIN $pin Finger $fingerId: " . $templateResult['message'];
                                $results['errors'][] = $errorMsg;
                                $userDebug['steps']["template_$fingerId"] = 'failed';
                                $userDebug['steps']["template_{$fingerId}_error"] = $errorMsg;
                            }
                            
                            // Delay between template uploads
                            usleep(300000);
                        }
                    }
                    
                    $userDebug['steps']['upload_templates'] = 'completed';
                    
                    // Refresh database after uploading templates
                    $xmlRefresh = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RefreshDB xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer">'.$commKey.'</ArgComKey>
    </RefreshDB>
  </soap:Body>
</soap:Envelope>';
                    
                    $refreshResult = sendSOAPRequest($targetIP, $xmlRefresh, "RefreshDB");
                    $userDebug['steps']['refresh_db'] = $refreshResult['success'] ? 'success' : 'failed';
                    
                    if (!$refreshResult['success']) {
                        $results['errors'][] = "PIN $pin: Gagal refresh database";
                    }
                    
                } else {
                    $userDebug['steps']['upload_templates'] = 'no_valid_templates';
                    $results['errors'][] = "PIN $pin: Tidak ada template yang valid ditemukan";
                }
            } else {
                $userDebug['steps']['upload_templates'] = 'empty_fingerprint_data';
                $results['errors'][] = "PIN $pin: Data fingerprint kosong";
            }
        } else {
            $userDebug['steps']['upload_templates'] = 'no_fingerprint_data';
            $results['errors'][] = "PIN $pin: Tidak ada data fingerprint di database";
        }
        
        $results['debug_info'][] = $userDebug;
    }
    
    // Final message - FIXED: Don't show warning if templates actually succeeded
    $message = "Upload selesai. User: {$results['user_success']}, " .
               "Template attempted: {$results['template_attempted']}, " .
               "Template success: {$results['template_success']}";
    
    // Check if we have template successes but also have errors due to parsing issues
    $hasRealErrors = false;
    $parsingErrors = [];
    
    foreach ($results['errors'] as $error) {
        // If error contains "Successfully" it's probably a parsing error, not real error
        if (stripos($error, 'successfully') !== false) {
            $parsingErrors[] = $error;
        } else {
            $hasRealErrors = true;
        }
    }
    
    if ($results['template_success'] > 0 && !$hasRealErrors) {
        // If we have template successes and no real errors, show success
        echo json_encode([
            'status' => 'success',
            'message' => $message . " - Template berhasil diupload",
            'details' => $results,
            'parsing_notes' => 'Beberapa response mungkin salah parsing tapi template berhasil diupload'
        ]);
    } else if (!empty($results['errors']) && $hasRealErrors) {
        echo json_encode([
            'status' => 'warning',
            'message' => $message,
            'details' => $results,
            'errors' => array_slice($results['errors'], 0, 10),
            'debug_info' => $results['debug_info']
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'details' => $results
        ]);
    }
    exit;
}

// ================= CHECK USER FINGERPRINT STATUS ================= //
if ($action === 'check_fingerprint_status') {
    $mesinId = $_POST['mesin_id'] ?? '';
    $pin = $_POST['pin'] ?? '';
    
    if (empty($mesinId) || empty($pin)) {
        echo json_encode(['status'=>'error','message'=>'Mesin dan PIN wajib diisi']);
        exit;
    }
    
    $machineInfo = getMachineInfo($conn, $mesinId);
    if (!$machineInfo) {
        echo json_encode(['status'=>'error','message'=>'Mesin tidak ditemukan']);
        exit;
    }

    $commKey = $machineInfo['comm_key'];
    $ip = $machineInfo['ip_address'];
    
    $hasFingerprint = false;
    $templateCount = 0;
    
    // Cek template untuk semua finger ID
    for ($fingerId = 0; $fingerId <= 9; $fingerId++) {
        $xml = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetUserTemplate xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">'.htmlspecialchars($pin).'</PIN>
        <FingerID xsi:type="xsd:integer">'.$fingerId.'</FingerID>
      </Arg>
    </GetUserTemplate>
  </soap:Body>
</soap:Envelope>';
        
        $result = sendSOAPRequest($ip, $xml, "GetUserTemplate");
        
        if ($result['success']) {
            if (preg_match_all(
                '/<Row>.*?<Valid>(\d+)<\/Valid>.*?<\/Row>/s',
                $result['response'], $matches
            )) {
                foreach ($matches[1] as $valid) {
                    if ($valid == '1') {
                        $hasFingerprint = true;
                        $templateCount++;
                    }
                }
            }
        }
        usleep(50000); // delay
    }
    
    echo json_encode([
        'status' => 'success',
        'has_fingerprint' => $hasFingerprint,
        'template_count' => $templateCount,
        'message' => $hasFingerprint ? "Memiliki $templateCount template sidik jari" : "Tidak memiliki sidik jari"
    ]);
    exit;
}

// ================= DOWNLOAD USERS TO DATABASE ================= //
elseif ($action == 'download') {
    $mesinId = $_POST['mesin_id'] ?? '';
    $users   = $_POST['users'] ?? [];

    if (empty($mesinId) || empty($users)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap!']);
        exit;
    }
    
    // Decode JSON ke array
    if (is_string($users)) {
        $users = json_decode($users, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['status' => 'error', 'message' => 'Format data user tidak valid']);
            exit;
        }
    }
    
    if (!is_array($users) || count($users) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada data user untuk disimpan']);
        exit;
    }

    try {
        // Mulai transaction
        if (sqlsrv_begin_transaction($conn) === false) {
            throw new Exception('Gagal memulai transaction');
        }
        
        $insertedCount = 0;
        $updatedCount  = 0;
        $errorCount    = 0;
        $errorMessages = [];
        $totalUsers    = count($users);
        
        foreach ($users as $user) {
            // pastikan field minimal ada
            $pin  = $user['PIN2'] ?? $user['PIN'] ?? $user['pin'] ?? '';
            $name = $user['Name'] ?? $user['name'] ?? '';
            if (empty($pin) || empty($name)) {
                $errorCount++;
                $errorMessages[] = "Data tidak lengkap: PIN atau Nama kosong";
                continue;
            }

            $password  = $user['Password'] ?? $user['password'] ?? '';
            $privilege = $user['Privilege'] ?? $user['privilege'] ?? 0;

            // cek apakah sudah ada
            $checkSql = "SELECT id FROM dbo.user_fingerprint WHERE pin=? AND mesin_id=?";
            $checkStmt = sqlsrv_query($conn, $checkSql, [$pin, $mesinId]);

            if ($checkStmt && sqlsrv_fetch($checkStmt)) {
                // update
                $updateSql = "UPDATE dbo.user_fingerprint
                              SET name=?, password=?, privilege=?, updated_at=GETDATE()
                              WHERE pin=? AND mesin_id=?";
                $updateStmt = sqlsrv_query($conn, $updateSql, [$name,$password,$privilege,$pin,$mesinId]);
                if ($updateStmt) {
                    $updatedCount++;
                } else {
                    $errorCount++;
                    $errorMessages[] = "PIN $pin: Gagal update - " . print_r(sqlsrv_errors(), true);
                }
            } else {
                // insert
                $insertSql = "INSERT INTO dbo.user_fingerprint
                              (pin, name, password, privilege, mesin_id, created_at, updated_at)
                              VALUES (?,?,?,?,?,GETDATE(),GETDATE())";
                $insertStmt = sqlsrv_query($conn, $insertSql, [$pin,$name,$password,$privilege,$mesinId]);
                if ($insertStmt) {
                    $insertedCount++;
                } else {
                    $errorCount++;
                    $errorMessages[] = "PIN $pin: Gagal insert - " . print_r(sqlsrv_errors(), true);
                }
            }
        }

        if ($errorCount > 0) {
            // rollback jika ada error
            sqlsrv_rollback($conn);
            echo json_encode([
                'status'  => 'error',
                'message' => "Sync gagal. Inserted: $insertedCount, Updated: $updatedCount, Error: $errorCount",
                'errors'  => $errorMessages
            ]);
        } else {
            sqlsrv_commit($conn);
            echo json_encode([
                'status'  => 'success',
                'message' => "Sync berhasil. Inserted: $insertedCount, Updated: $updatedCount dari total $totalUsers user"
            ]);
        }
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(['status'=>'error','message'=>'Exception: '.$e->getMessage()]);
    }
    exit;
}

// ================= DELETE MULTIPLE USERS ================= //
if ($action === 'delete_multiple') {
    $users = $_POST['users'] ?? '';
    
    if (empty($users)) {
        echo json_encode(['status'=>'error','message'=>'Tidak ada user yang dipilih']); 
        exit;
    }
    
    // Decode JSON ke array
    $users = json_decode($users, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status'=>'error','message'=>'Format data user tidak valid']);
        exit;
    }
    
    if (!is_array($users) || count($users) === 0) {
        echo json_encode(['status'=>'error','message'=>'Tidak ada data user untuk dihapus']);
        exit;
    }
    
    try {
        // Mulai transaction
        if (sqlsrv_begin_transaction($conn) === false) {
            throw new Exception('Gagal memulai transaction');
        }
        
        $deletedCount = 0;
        $errorCount = 0;
        $errorMessages = [];
        
        foreach ($users as $user) {
            $userId = $user['id'] ?? '';
            $pin = $user['pin'] ?? '';
            
            if (empty($userId) || empty($pin)) {
                $errorCount++;
                $errorMessages[] = "Data tidak lengkap: ID atau PIN kosong";
                continue;
            }
            
            $deleteSql = "DELETE FROM dbo.user_fingerprint WHERE id = ?";
            $deleteStmt = sqlsrv_query($conn, $deleteSql, [$userId]);
            
            if ($deleteStmt) {
                $deletedCount++;
            } else {
                $errorCount++;
                $errorMessages[] = "PIN $pin: Gagal dihapus - " . print_r(sqlsrv_errors(), true);
            }
        }
        
        if ($errorCount > 0) {
            // rollback jika ada error
            sqlsrv_rollback($conn);
            echo json_encode([
                'status' => 'error',
                'message' => "Hapus gagal. Berhasil: $deletedCount, Gagal: $errorCount",
                'errors' => $errorMessages
            ]);
        } else {
            sqlsrv_commit($conn);
            echo json_encode([
                'status' => 'success',
                'message' => "Berhasil menghapus $deletedCount user"
            ]);
        }
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(['status'=>'error','message'=>'Exception: '.$e->getMessage()]);
    }
    exit;
}

// ================= GET FINGERPRINT FROM DATABASE ================= //
if ($action === 'get_fingerprint_from_db') {
    $userId = $_POST['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['status'=>'error','message'=>'User ID tidak valid']);
        exit;
    }
    
    $sql = "SELECT uf.name, uf.fingerprint_data, uf.template_size 
            FROM dbo.user_fingerprint uf 
            WHERE uf.id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$userId]);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $templates = [];
        
        if (!empty($row['fingerprint_data'])) {
            try {
                $fingerprintData = $row['fingerprint_data'];
                if (is_resource($fingerprintData)) {
                    $fingerprintData = stream_get_contents($fingerprintData);
                }
                
                if (!empty($fingerprintData)) {
                    $decodedData = json_decode($fingerprintData, true);
                    if ($decodedData && is_array($decodedData)) {
                        $templates = $decodedData;
                    }
                }
            } catch (Exception $e) {
                // Error decoding
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'user_name' => $row['name'],
            'template_size' => $row['template_size'],
            'templates' => $templates,
            'message' => count($templates) . ' template ditemukan'
        ]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Data user tidak ditemukan']);
    }
    exit;
}

// ================= DELETE SINGLE USER ================= //
if ($action === 'delete_single') {
    $userId = $_POST['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['status'=>'error','message'=>'User ID tidak valid']);
        exit;
    }
    
    $deleteSql = "DELETE FROM dbo.user_fingerprint WHERE id = ?";
    $deleteStmt = sqlsrv_query($conn, $deleteSql, [$userId]);
    
    if ($deleteStmt) {
        echo json_encode([
            'status' => 'success',
            'message' => 'User berhasil dihapus dari database'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus user: ' . print_r(sqlsrv_errors(), true)
        ]);
    }
    exit;
}

echo json_encode(['status'=>'error','message'=>'Action tidak dikenali']);
?>