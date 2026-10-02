<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sub_id = $_GET['sub_id'] ?? null;
$sub_name = $_GET['sub_name'] ?? null;

try {
    if ($sub_id) {
        // Get config for specific sub-category by ID
        $sql = "SELECT supports_asset, supports_client, asset_type 
                FROM dbo.issue_sub_category_config 
                WHERE sub_id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$sub_id]);
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo json_encode([
                'success' => true, 
                'config' => [
                    'supports_asset' => (bool)$row['supports_asset'],
                    'supports_client' => (bool)$row['supports_client'],
                    'asset_type' => $row['asset_type']
                ]
            ]);
        } else {
            // No config found, return defaults
            echo json_encode([
                'success' => true, 
                'config' => [
                    'supports_asset' => false,
                    'supports_client' => false,
                    'asset_type' => null
                ]
            ]);
        }
        
        if ($stmt) sqlsrv_free_stmt($stmt);
        
    } elseif ($sub_name) {
        // Get config for specific sub-category by name
        $sql = "SELECT c.supports_asset, c.supports_client, c.asset_type 
                FROM dbo.issue_sub_category_config c
                JOIN dbo.issue_sub_categories s ON c.sub_id = s.sub_id
                WHERE s.sub_name = ? AND s.is_active = 1";
        $stmt = sqlsrv_query($conn, $sql, [$sub_name]);
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo json_encode([
                'success' => true, 
                'config' => [
                    'supports_asset' => (bool)$row['supports_asset'],
                    'supports_client' => (bool)$row['supports_client'],
                    'asset_type' => $row['asset_type']
                ]
            ]);
        } else {
            // No config found, return defaults
            echo json_encode([
                'success' => true, 
                'config' => [
                    'supports_asset' => false,
                    'supports_client' => false,
                    'asset_type' => null
                ]
            ]);
        }
        
        if ($stmt) sqlsrv_free_stmt($stmt);
        
    } else {
        // Get all configs as a map: sub_name => config
        $sql = "SELECT s.sub_name, c.supports_asset, c.supports_client, c.asset_type
                FROM dbo.issue_sub_category_config c
                JOIN dbo.issue_sub_categories s ON c.sub_id = s.sub_id
                WHERE s.is_active = 1";
        $stmt = sqlsrv_query($conn, $sql);
        $configs = [];
        
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $configs[$row['sub_name']] = [
                    'supports_asset' => (bool)$row['supports_asset'],
                    'supports_client' => (bool)$row['supports_client'],
                    'asset_type' => $row['asset_type']
                ];
            }
            sqlsrv_free_stmt($stmt);
        }
        
        echo json_encode(['success' => true, 'configs' => $configs]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

sqlsrv_close($conn);
?>
