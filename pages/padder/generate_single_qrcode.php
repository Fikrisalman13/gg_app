<?php
// generate_single_qrcode.php - Generate QR Code untuk satu padder
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
session_start();

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Check permissions
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanView' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $result;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 57);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Get padder ID from URL
$padderId = $_GET['id'] ?? '';
if (empty($padderId)) {
    die("Padder ID tidak valid");
}

// Get padder data
function getPadderById($conn, $padderId) {
    $sql = "SELECT 
                padder_id,
                padder_name,
                status,
                remarks,
                created_at
            FROM dbo.pad_m_padder 
            WHERE padder_id = ?";
    
    $stmt = sqlsrv_query($conn, $sql, [$padderId]);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format datetime jika perlu
        if ($row['created_at'] instanceof DateTime) {
            $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
        }
        sqlsrv_free_stmt($stmt);
        return $row;
    }
    
    sqlsrv_free_stmt($stmt);
    return null;
}

$padder = getPadderById($conn, $padderId);
if (!$padder) {
    die("Padder tidak ditemukan");
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>QR Code - <?= htmlspecialchars($padder['padder_id']) ?></title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f5f5f5;
        }
        .qr-container {
            width: 300px;
            border: 2px solid #333;
            padding: 20px;
            background: white;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .label { 
            font-size: 12px; 
            margin-top: 10px;
            word-wrap: break-word;
        }
        .padder-id {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .detail-info {
            margin: 5px 0;
            text-align: left;
        }
        .print-controls {
            margin-bottom: 20px;
            text-align: center;
        }
        @media print {
            .no-print { display: none; }
            body { 
                background: white; 
                padding: 0;
                display: block;
            }
            .qr-container {
                border: 2px solid #000;
                margin: 0 auto;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<div class="qr-container">
    <div class="print-controls no-print">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <button onclick="window.close()" class="btn btn-secondary">Tutup</button>
    </div>

    <?php
    $padderCode = $padder['padder_id'];
    $qrCode = new TCPDF2DBarcode($padderCode, 'QRCODE,M');
    $qrPng = $qrCode->getBarcodePngData(6, 6, [0,0,0]);
    $base64 = base64_encode($qrPng);
    ?>
    
    <img src="data:image/png;base64,<?= $base64 ?>" alt="QR Code" width="200" height="200">
    <div class="label padder-id"><?= htmlspecialchars($padderCode) ?></div>
    <div class="label detail-info"><strong>Nama:</strong> <?= htmlspecialchars($padder['padder_name']) ?></div>
    <div class="label detail-info"><strong>Status:</strong> <?= htmlspecialchars($padder['status']) ?></div>
    <?php if (!empty($padder['remarks'])): ?>
        <div class="label detail-info"><strong>Remarks:</strong> <?= htmlspecialchars($padder['remarks']) ?></div>
    <?php endif; ?>
    <div class="label detail-info"><strong>Created:</strong> <?= htmlspecialchars($padder['created_at']) ?></div>
</div>

</body>
</html>