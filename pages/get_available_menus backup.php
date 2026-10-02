<?php
session_start();
header('Content-Type: application/json');

// ===================================================
// 1. VALIDASI SESSION LOGIN
// ===================================================
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['error' => 'Session expired. Silakan login kembali.']);
    exit;
}

// ===================================================
// 2. KONEKSI DATABASE
// ===================================================
require '../koneksi.php';
if (!$conn) {
    echo json_encode(['error' => 'Koneksi database gagal.']);
    exit;
}

// ===================================================
// 3. VALIDASI PARAMETER
// ===================================================
$groupId = $_GET['groupId'] ?? 0;

if (!$groupId || !is_numeric($groupId)) {
    echo json_encode(['error' => 'Group ID tidak valid.']);
    exit;
}

// ===================================================
// 4. GET ALL AVAILABLE MENUS (MAIN MENU & SUBMENU)
// ===================================================
try {
    // Query untuk mendapatkan semua menu yang tersedia
    // Menu dengan ParentMenuId = NULL adalah main menu
    // Menu dengan ParentMenuId != NULL adalah submenu
    // CATATAN: Tabel Anda tidak punya kolom 'Active', jadi kita ambil semua menu
    $sql = "
        SELECT 
            m.MenuId,
            m.MenuName,
            m.ParentMenuId,
            m.MenuUrl,
            m.MenuIcon,
            pm.MenuName AS ParentMenuName,
            CASE 
                WHEN m.ParentMenuId IS NULL THEN 0 
                ELSE 1 
            END AS IsSubMenu,
            CASE 
                WHEN m.ParentMenuId IS NULL THEN m.MenuName
                ELSE pm.MenuName + ' > ' + m.MenuName
            END AS DisplayName,
            CASE 
                WHEN m.ParentMenuId IS NULL THEN 'Main Menu'
                ELSE 'Sub Menu'
            END AS MenuType
        FROM dbo.SMMenu m
        LEFT JOIN dbo.SMMenu pm ON m.ParentMenuId = pm.MenuId
        ORDER BY 
            CASE WHEN m.ParentMenuId IS NULL THEN 0 ELSE 1 END,
            COALESCE(pm.MenuId, m.MenuId),
            m.MenuId
    ";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        throw new Exception("Gagal mengambil data menu: " . print_r(sqlsrv_errors(), true));
    }
    
    $menus = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format tanggal jika perlu
        $createdDate = $row['CreatedDate'] ?? null;
        if ($createdDate instanceof DateTime) {
            $createdDate = $createdDate->format('Y-m-d H:i:s');
        }
        
        $menus[] = [
            'MenuId' => $row['MenuId'],
            'MenuName' => $row['MenuName'],
            'MenuUrl' => $row['MenuUrl'] ?? '',
            'ParentMenuId' => $row['ParentMenuId'],
            'ParentMenuName' => $row['ParentMenuName'] ?? '',
            'MenuIcon' => $row['MenuIcon'] ?? '',
            'IsSubMenu' => $row['IsSubMenu'],
            'DisplayName' => $row['DisplayName'],
            'MenuType' => $row['MenuType'],
            'Level' => $row['ParentMenuId'] ? 1 : 0
        ];
    }
    
    sqlsrv_free_stmt($stmt);
    
    // ===================================================
    // 5. GET ALREADY ASSIGNED MENUS FOR THIS GROUP
    // ===================================================
    // Periksa apakah tabel SMGroupTrustee ada dan strukturnya
    // Jika tidak ada, Anda perlu menyesuaikan dengan tabel yang ada
    $assignedMenus = [];
    
    // Cek dulu apakah tabel SMGroupTrustee ada
    $checkTableSql = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'SMGroupTrustee'";
    $checkTableStmt = sqlsrv_query($conn, $checkTableSql);
    
    if ($checkTableStmt !== false && sqlsrv_has_rows($checkTableStmt)) {
        // Jika tabel ada, ambil data assigned menus
        $assignedSql = "
            SELECT MenuId 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ?
        ";
        
        $assignedStmt = sqlsrv_query($conn, $assignedSql, [$groupId]);
        
        if ($assignedStmt !== false) {
            while ($row = sqlsrv_fetch_array($assignedStmt, SQLSRV_FETCH_ASSOC)) {
                $assignedMenus[] = $row['MenuId'];
            }
            sqlsrv_free_stmt($assignedStmt);
        }
    } else {
        // Jika tabel tidak ada, mungkin tabel hak akses memiliki nama berbeda
        // Coba alternatif nama tabel
        $alternativeTables = ['HakAksesGroup', 'GroupMenu', 'UserGroupMenu', 'GroupPermission'];
        $found = false;
        
        foreach ($alternativeTables as $tableName) {
            $checkAltSql = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName'";
            $checkAltStmt = sqlsrv_query($conn, $checkAltSql);
            
            if ($checkAltStmt !== false && sqlsrv_has_rows($checkAltStmt)) {
                // Ambil skema kolom untuk menentukan query
                $colSql = "
                    SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = '$tableName'
                ";
                $colStmt = sqlsrv_query($conn, $colSql);
                $columns = [];
                while ($col = sqlsrv_fetch_array($colStmt, SQLSRV_FETCH_ASSOC)) {
                    $columns[] = $col['COLUMN_NAME'];
                }
                
                // Cari kolom yang sesuai
                $groupIdCol = in_array('GroupId', $columns) ? 'GroupId' : 
                             (in_array('GroupID', $columns) ? 'GroupID' : 
                             (in_array('id_group', $columns) ? 'id_group' : ''));
                
                $menuIdCol = in_array('MenuId', $columns) ? 'MenuId' : 
                            (in_array('MenuID', $columns) ? 'MenuID' : 
                            (in_array('id_menu', $columns) ? 'id_menu' : ''));
                
                if ($groupIdCol && $menuIdCol) {
                    $assignedSql = "SELECT $menuIdCol FROM dbo.$tableName WHERE $groupIdCol = ?";
                    $assignedStmt = sqlsrv_query($conn, $assignedSql, [$groupId]);
                    
                    if ($assignedStmt !== false) {
                        while ($row = sqlsrv_fetch_array($assignedStmt, SQLSRV_FETCH_ASSOC)) {
                            $assignedMenus[] = $row[$menuIdCol];
                        }
                        sqlsrv_free_stmt($assignedStmt);
                        $found = true;
                        break;
                    }
                }
            }
        }
        
        if (!$found) {
            // Log untuk debugging
            error_log("Tabel hak akses group tidak ditemukan. Mengembalikan semua menu sebagai available.");
        }
    }
    
    // ===================================================
    // 6. FILTER OUT ALREADY ASSIGNED MENUS
    // ===================================================
    $availableMenus = [];
    foreach ($menus as $menu) {
        if (!in_array($menu['MenuId'], $assignedMenus)) {
            $availableMenus[] = $menu;
        }
    }
    
    // ===================================================
    // 7. KELOMPOKKAN MENU JIKA PERLU (UNTUK TAMPILAN YANG LEBIH BAIK)
    // ===================================================
    $groupedMenus = [
        'main_menus' => [],
        'sub_menus' => []
    ];
    
    foreach ($availableMenus as $menu) {
        if ($menu['IsSubMenu'] == 0) {
            $groupedMenus['main_menus'][] = $menu;
        } else {
            $groupedMenus['sub_menus'][] = $menu;
        }
    }
    
    // ===================================================
    // 8. RETURN JSON RESPONSE
    // ===================================================
    $response = [
        'success' => true,
        'data' => $availableMenus,
        'grouped' => $groupedMenus,
        'total_available' => count($availableMenus),
        'total_assigned' => count($assignedMenus),
        'total_all' => count($menus)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_available_menus.php: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}