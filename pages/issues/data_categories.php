<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? '';
    // Used for Manage Page and for Dropdown population
    if ($type === 'category') {
                $sql = "SELECT category_id, category_name,
                                             (
                                                 SELECT COUNT(*) FROM dbo.issues i
                                                 WHERE i.kategori = c.category_name
                                                 OR i.sub_kategori IN (SELECT sub_name FROM dbo.issue_sub_categories WHERE category_id = c.category_id)
                                             ) AS issue_count
                                FROM dbo.issue_categories c
                                WHERE is_active = 1
                                ORDER BY category_name";
        $stmt = sqlsrv_query($conn, $sql);
        $data = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            // Ensure integer
            $row['issue_count'] = isset($row['issue_count']) ? (int)$row['issue_count'] : 0;
            $data[] = $row;
        }
        echo json_encode($data);
    } 
    elseif ($type === 'sub') {
        $catId = $_GET['category_id'] ?? 0;
        $sql = "SELECT s.sub_id, s.sub_name, c.config_id, c.supports_asset, c.supports_client, c.asset_type,
                       ISNULL((SELECT COUNT(*) FROM dbo.issues i WHERE i.sub_kategori = s.sub_name AND i.kategori = cat.category_name), 0) AS issue_count
                FROM dbo.issue_sub_categories s
                LEFT JOIN dbo.issue_sub_category_config c ON s.sub_id = c.sub_id
                LEFT JOIN dbo.issue_categories cat ON s.category_id = cat.category_id
                WHERE s.category_id = ? AND (s.is_active = 1 OR s.is_active IS NULL)
                ORDER BY s.sub_name";
        $stmt = sqlsrv_query($conn, $sql, [$catId]);
        $data = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $row['issue_count'] = isset($row['issue_count']) ? (int)$row['issue_count'] : 0;
            $data[] = $row;
        }
        echo json_encode($data);
    }
    // New: Fetch All Nested for JS initialization (replacing the hardcoded array)
    elseif ($type === 'all_nested') {
        $sql = "SELECT c.category_name, s.sub_name 
                FROM dbo.issue_categories c
                LEFT JOIN dbo.issue_sub_categories s ON c.category_id = s.category_id
                WHERE c.is_active = 1 AND (s.is_active = 1 OR s.is_active IS NULL)
                ORDER BY c.category_name, s.sub_name";
        $stmt = sqlsrv_query($conn, $sql);
        $tree = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $cat = $row['category_name'];
            $sub = $row['sub_name'];
            if (!isset($tree[$cat])) {
                $tree[$cat] = [];
            }
            if ($sub) {
                $tree[$cat][] = $sub;
            }
        }
        echo json_encode($tree);
    }
    elseif ($type === 'issues_by_sub') {
        // Return issues that reference a given sub_id (matching by name + category)
        $subId = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : 0;
        if ($subId <= 0) {
            echo json_encode(['success'=>false,'message'=>'Invalid sub id']);
            exit;
        }
        // Get sub name and category
        $sq = "SELECT s.sub_name, c.category_name FROM dbo.issue_sub_categories s LEFT JOIN dbo.issue_categories c ON s.category_id = c.category_id WHERE s.sub_id = ?";
        $st = sqlsrv_query($conn, $sq, [$subId]);
        if ($st === false || ($info = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) === null) {
            echo json_encode(['success'=>false,'message'=>'Sub kategori tidak ditemukan']);
            exit;
        }
        $subName = $info['sub_name'];
        $catName = $info['category_name'];

        // If DataTables server-side params present, handle pagination, search, ordering
        $isDataTables = isset($_GET['draw']);
        if ($isDataTables) {
            $draw = intval($_GET['draw']);
            $start = intval($_GET['start'] ?? 0);
            $length = intval($_GET['length'] ?? 10);
            $searchValue = trim($_GET['search']['value'] ?? '');

            // Base count
            $countSql = "SELECT COUNT(*) AS cnt FROM dbo.issues WHERE sub_kategori = ? AND kategori = ?";
            $countStmt = sqlsrv_query($conn, $countSql, [$subName, $catName]);
            $recordsTotal = 0;
            if ($countStmt && ($c = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) {
                $recordsTotal = (int)$c['cnt'];
            }

            // Build filtering
            $where = "sub_kategori = ? AND kategori = ?";
            $params = [$subName, $catName];
            if ($searchValue !== '') {
                $where .= " AND (issue_name LIKE ? OR issue_type LIKE ? OR created_by LIKE ? OR status LIKE ? OR priority LIKE ? )";
                $like = '%' . $searchValue . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }

            // Ordering
            $orderSql = "ORDER BY created_at DESC";
            if (!empty($_GET['order'][0]['column'])) {
                $colIdx = intval($_GET['order'][0]['column']);
                $dir = strtoupper($_GET['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
                // Map column index to actual column names (index based on frontend table)
                $colMap = [0 => 'issue_id', 1 => 'issue_name', 2 => 'issue_type', 3 => 'created_by', 4 => 'status'];
                if (isset($colMap[$colIdx])) {
                    $orderSql = "ORDER BY " . $colMap[$colIdx] . " " . $dir;
                }
            }

            // Paging (OFFSET FETCH)
            $pageSql = "OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

            $sql = "SELECT issue_id, issue_name, issue_type, created_by, status, priority, CONVERT(VARCHAR(50), created_at, 120) as created_at
                    FROM dbo.issues
                    WHERE " . $where . " " . $orderSql . " " . $pageSql;

            // Add paging params
            $params[] = $start;
            $params[] = $length;

            $stmt = sqlsrv_query($conn, $sql, $params);
            $data = [];
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $r;
            }

            // recordsFiltered: if search applied, run count with same where
            $recordsFiltered = $recordsTotal;
            if ($searchValue !== '') {
                $countSql2 = "SELECT COUNT(*) AS cnt FROM dbo.issues WHERE " . $where;
                // count params equal to params without offset/length
                $countParams = array_slice($params, 0, count($params) - 2);
                $cst = sqlsrv_query($conn, $countSql2, $countParams);
                if ($cst && ($cc = sqlsrv_fetch_array($cst, SQLSRV_FETCH_ASSOC))) {
                    $recordsFiltered = (int)$cc['cnt'];
                }
            }

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
                'sub_name' => $subName,
                'category_name' => $catName
            ]);
            exit;
        }

        // Fallback: return full list (legacy)
        $sql = "SELECT issue_id, issue_name, issue_type, created_by, status, priority, CAST(created_at AS VARCHAR(50)) as created_at
                FROM dbo.issues
                WHERE sub_kategori = ? AND kategori = ?
                ORDER BY created_at DESC";
        $stmt = sqlsrv_query($conn, $sql, [$subName, $catName]);
        $rows = [];
        while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $rows[] = $r;
        }
        echo json_encode(['success'=>true,'data'=>$rows,'sub_name'=>$subName,'category_name'=>$catName]);
        exit;
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $resp = ['success'=>false, 'message'=>'Invalid request'];
    
    try {
        if ($action === 'add_cat') {
            $name = trim($_POST['name']);
            if(!$name) throw new Exception("Nama wajib diisi");
            $sq = "INSERT INTO dbo.issue_categories (category_name) VALUES (?)";
            if(sqlsrv_query($conn, $sq, [$name])) $resp=['success'=>true, 'message'=>'Kategori ditambahkan'];
            else throw new Exception("Gagal simpan");
        }
        elseif ($action === 'edit_cat') {
             $id = $_POST['id']; $name = trim($_POST['name']);
             if(!$name) throw new Exception("Nama wajib diisi");
             $sq = "UPDATE dbo.issue_categories SET category_name = ? WHERE category_id = ?";
             if(sqlsrv_query($conn, $sq, [$name, $id])) $resp=['success'=>true, 'message'=>'Kategori diupdate'];
             else throw new Exception("Gagal update");
        }
        elseif ($action === 'del_cat') {
             $id = $_POST['id'];
               $id = intval($id);
               if($id <= 0) throw new Exception("ID tidak valid");
                    // Check if any issues reference this category OR any of its subcategories
                    $catNameSql = "SELECT category_name FROM dbo.issue_categories WHERE category_id = ?";
                    $stc = sqlsrv_query($conn, $catNameSql, [$id]);
                    $catName = null;
                    if($stc && ($r0 = sqlsrv_fetch_array($stc, SQLSRV_FETCH_ASSOC))) $catName = $r0['category_name'];
                    if(!$catName) throw new Exception("Kategori tidak ditemukan");

                    $checkSql = "SELECT COUNT(*) as cnt FROM dbo.issues WHERE kategori = ? OR sub_kategori IN (SELECT sub_name FROM dbo.issue_sub_categories WHERE category_id = ?)";
                    $chk = sqlsrv_query($conn, $checkSql, [$catName, $id]);
                    $cnt = 0;
                    if($chk && ($r = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC))) $cnt = (int)$r['cnt'];
                    if($cnt > 0) {
                        throw new Exception("Kategori tidak dapat dihapus karena ada " . $cnt . " issue yang menggunakan kategori/sub-kategori di dalamnya.");
                    }
               // No referencing issues; safe to delete (or soft-delete)
               $sq = "DELETE FROM dbo.issue_categories WHERE category_id = ?";
               if(sqlsrv_query($conn, $sq, [$id])) $resp=['success'=>true, 'message'=>'Kategori dihapus'];
               else throw new Exception("Gagal hapus");
        }
        elseif ($action === 'add_sub') {
             $catId = $_POST['category_id']; $name = trim($_POST['name']);
             $s_asset = isset($_POST['supports_asset']) ? (int)$_POST['supports_asset'] : 0;
             $s_client = isset($_POST['supports_client']) ? (int)$_POST['supports_client'] : 0;
             $a_type = $_POST['asset_type'] ?? 'all';

             if(!$name) throw new Exception("Nama wajib diisi");
             
             // Insert to sub_categories and get ID
             $sq = "INSERT INTO dbo.issue_sub_categories (category_id, sub_name) VALUES (?, ?); SELECT SCOPE_IDENTITY() as id";
             $stmt = sqlsrv_query($conn, $sq, [$catId, $name]);
             
             if($stmt === false) throw new Exception("Gagal simpan sub kategori: " . print_r(sqlsrv_errors(), true));
             
             sqlsrv_next_result($stmt); 
             $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC); 
             $newId = $row['id'];
             
             if($newId) {
                 $sq2 = "INSERT INTO dbo.issue_sub_category_config (sub_id, supports_asset, supports_client, asset_type) VALUES (?, ?, ?, ?)";
                 sqlsrv_query($conn, $sq2, [$newId, $s_asset, $s_client, $a_type]);
                 $resp=['success'=>true, 'message'=>'Sub kategori ditambahkan'];
             } else {
                 throw new Exception("Gagal mendapatkan ID sub kategori baru");
             }
        }
        elseif ($action === 'edit_sub') {
             $id = $_POST['id']; $name = trim($_POST['name']);
             $s_asset = isset($_POST['supports_asset']) ? (int)$_POST['supports_asset'] : 0;
             $s_client = isset($_POST['supports_client']) ? (int)$_POST['supports_client'] : 0;
             $a_type = $_POST['asset_type'] ?? 'all';

             if(!$name) throw new Exception("Nama wajib diisi");
             
             $sq = "UPDATE dbo.issue_sub_categories SET sub_name = ? WHERE sub_id = ?";
             if(sqlsrv_query($conn, $sq, [$name, $id])) {
                 // Upsert config
                 $sq2 = "IF EXISTS (SELECT 1 FROM dbo.issue_sub_category_config WHERE sub_id = ?)
                        BEGIN
                            UPDATE dbo.issue_sub_category_config 
                            SET supports_asset = ?, supports_client = ?, asset_type = ?, updated_at = GETDATE()
                            WHERE sub_id = ?
                        END
                        ELSE
                        BEGIN
                            INSERT INTO dbo.issue_sub_category_config (sub_id, supports_asset, supports_client, asset_type) 
                            VALUES (?, ?, ?, ?)
                        END";
                 // Params order: check_id, update_vals(3), where_id, insert_vals(4)
                 $params = [$id, $s_asset, $s_client, $a_type, $id, $id, $s_asset, $s_client, $a_type];
                 sqlsrv_query($conn, $sq2, $params);
                 
                 $resp=['success'=>true, 'message'=>'Sub kategori diupdate'];
             }
             else throw new Exception("Gagal update");
        }
        elseif ($action === 'del_sub') {
               $id = intval($_POST['id']);
               if($id <= 0) throw new Exception("ID tidak valid");
               // Get sub name and category to match issues
               $sSql = "SELECT s.sub_name, c.category_name FROM dbo.issue_sub_categories s LEFT JOIN dbo.issue_categories c ON s.category_id = c.category_id WHERE s.sub_id = ?";
               $sSt = sqlsrv_query($conn, $sSql, [$id]);
               if($sSt === false || ($sInfo = sqlsrv_fetch_array($sSt, SQLSRV_FETCH_ASSOC)) === null) throw new Exception("Sub kategori tidak ditemukan");
               $subName = $sInfo['sub_name'];
               $catName = $sInfo['category_name'];
               $checkSql = "SELECT COUNT(*) as cnt FROM dbo.issues WHERE sub_kategori = ? AND kategori = ?";
               $ch = sqlsrv_query($conn, $checkSql, [$subName, $catName]);
               $cnt = 0;
               if($ch && ($rr = sqlsrv_fetch_array($ch, SQLSRV_FETCH_ASSOC))) $cnt = (int)$rr['cnt'];
               if($cnt > 0) {
                  throw new Exception("Sub kategori tidak dapat dihapus karena digunakan oleh " . $cnt . " issue.");
               }
               $sq = "DELETE FROM dbo.issue_sub_categories WHERE sub_id = ?";
               if(sqlsrv_query($conn, $sq, [$id])) $resp=['success'=>true, 'message'=>'Sub kategori dihapus'];
               else throw new Exception("Gagal hapus");
        }
    } catch (Exception $e) {
        $resp['message'] = $e->getMessage();
    }
    echo json_encode($resp);
}

sqlsrv_close($conn);
?>
