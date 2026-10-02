<?php
session_start();
ob_start();

// Error reporting untuk development (nonaktifkan di production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// CEK LOGIN
if (!isset($_SESSION['UserName'])) {
    header('Location: ../login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// CEK PERMISSION
define('USER_FINGERPRINT_MENU_ID', 97);

/**
 * Check user permissions dengan caching
 */
function checkUserPermissions($conn, $groupId, $menuId) {
    static $permissionsCache = [];
    
    $cacheKey = $groupId . '_' . $menuId;
    if (isset($permissionsCache[$cacheKey])) {
        return $permissionsCache[$cacheKey];
    }
    
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId=? AND MenuId=?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    
    $permissions = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    
    $permissionsCache[$cacheKey] = $permissions;
    return $permissions;
}

$permissions = checkUserPermissions($conn, $_SESSION['GroupId'], USER_FINGERPRINT_MENU_ID);
if ($permissions['CanView'] != 1) {
    header('Location: ../dashboard.php');
    exit;
}

/**
 * Get all mesin dengan caching
 */
function getAllMesin($conn) {
    static $mesinCache = null;
    
    if ($mesinCache !== null) {
        return $mesinCache;
    }
    
    $sql = "SELECT * FROM dbo.m_fingerprint ORDER BY nama_mesin";
    $stmt = sqlsrv_query($conn, $sql);
    $mesin = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $mesin[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    $mesinCache = $mesin;
    return $mesin;
}

/**
 * Process fingerprint data secara efisien - OPTIMIZED VERSION
 */
function processFingerprintData($fingerprintData) {
    $result = [
        'has_fingerprint' => false,
        'fingerprint_count' => 0,
        'fingerprint_info' => 'Tidak ada'
    ];
    
    if (empty($fingerprintData)) {
        return $result;
    }
    
    try {
        // Konversi varbinary ke string jika perlu
        if (is_resource($fingerprintData)) {
            $fingerprintData = stream_get_contents($fingerprintData);
        }
        
        // Jika masih binary, coba decode sebagai string
        if (is_string($fingerprintData)) {
            // Coba decode sebagai JSON
            $decodedData = json_decode($fingerprintData, true);
            
            if ($decodedData !== null && is_array($decodedData)) {
                // Format JSON ditemukan
                $result['has_fingerprint'] = true;
                $result['fingerprint_count'] = count($decodedData);
                $result['fingerprint_info'] = $result['fingerprint_count'] . ' template';
            } else {
                // Cek jika data mengandung template fingerprint
                $dataLength = strlen($fingerprintData);
                
                // Jika data cukup panjang, kemungkinan ada template fingerprint
                if ($dataLength > 10) {
                    $result['has_fingerprint'] = true;
                    $result['fingerprint_count'] = 1;
                    $result['fingerprint_info'] = 'Binary data (' . $dataLength . ' bytes)';
                    
                    // Coba deteksi multiple templates
                    if (strpos($fingerprintData, '|') !== false || 
                        strpos($fingerprintData, ',') !== false ||
                        strpos($fingerprintData, ';') !== false) {
                        $templates = preg_split('/[|,;]/', $fingerprintData);
                        $validTemplates = array_filter($templates, function($template) {
                            return !empty(trim($template)) && strlen(trim($template)) > 5;
                        });
                        if (count($validTemplates) > 0) {
                            $result['fingerprint_count'] = count($validTemplates);
                            $result['fingerprint_info'] = $result['fingerprint_count'] . ' template';
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error processing fingerprint data: " . $e->getMessage());
        $result['fingerprint_info'] = 'Error decoding';
    }
    
    return $result;
}

/**
 * Get users from database dengan search dan pagination
 */
function getUsersFromDatabase($conn, $mesinId = null, $search = '', $start = 0, $length = 10) {
    // Validasi input
    $mesinId = $mesinId ? (int)$mesinId : null;
    $start = (int)$start;
    $length = (int)$length;
    
    $sql = "SELECT 
                uf.id, uf.pin, uf.name, uf.privilege, uf.password, uf.mesin_id, 
                uf.fingerprint_data, uf.template_size, 
                uf.created_at, uf.updated_at,
                mf.nama_mesin, mf.ip_address, mf.last_download
            FROM dbo.user_fingerprint uf
            INNER JOIN dbo.m_fingerprint mf ON uf.mesin_id = mf.id
            WHERE 1=1";
    
    $params = [];
    
    if ($mesinId) {
        $sql .= " AND uf.mesin_id = ?";
        $params[] = $mesinId;
    }
    
    if (!empty($search)) {
        $sql .= " AND (uf.pin LIKE ? OR uf.name LIKE ? OR mf.nama_mesin LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Hitung total records filtered
    $countSql = "SELECT COUNT(*) as total FROM ($sql) as subquery";
    $countStmt = sqlsrv_query($conn, $countSql, $params);
    $totalFiltered = 0;
    if ($countStmt && $row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = (int)$row['total'];
    }
    if ($countStmt) sqlsrv_free_stmt($countStmt);
    
    // Tambahkan sorting dan pagination
    $sql .= " ORDER BY uf.created_at DESC, mf.nama_mesin, uf.pin
              OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    $params[] = $start;
    $params[] = $length;
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $users = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format tanggal
            $row['created_at_formatted'] = $row['created_at'] ? 
                $row['created_at']->format('d-m-Y H:i:s') : '';
            $row['updated_at_formatted'] = $row['updated_at'] ? 
                $row['updated_at']->format('d-m-Y H:i:s') : '';
            $row['last_download_formatted'] = $row['last_download'] ? 
                $row['last_download']->format('d-m-Y H:i:s') : '';
            
            // Process fingerprint data
            $fingerprintInfo = processFingerprintData($row['fingerprint_data']);
            $row['has_fingerprint'] = $fingerprintInfo['has_fingerprint'];
            $row['fingerprint_count'] = $fingerprintInfo['fingerprint_count'];
            $row['fingerprint_info'] = $fingerprintInfo['fingerprint_info'];
            
            // Format template size
            $row['template_size_formatted'] = $row['template_size'] ? 
                number_format($row['template_size']) . ' bytes' : '-';
            
            // Format password (tampilkan sebagai asterisk atau teks biasa)
            $row['password_display'] = $row['password'] ? 
                '••••••••' : '-';
            
            $users[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return [
        'users' => $users,
        'totalFiltered' => $totalFiltered
    ];
}

/**
 * Get total users count
 */
function getTotalUsersCount($conn, $mesinId = null) {
    $sql = "SELECT COUNT(*) as total 
            FROM dbo.user_fingerprint uf
            WHERE 1=1";
    
    $params = [];
    
    if ($mesinId) {
        $sql .= " AND uf.mesin_id = ?";
        $params[] = $mesinId;
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $count = 0;
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $count = (int)$row['total'];
        sqlsrv_free_stmt($stmt);
    }
    
    return $count;
}

/**
 * Get statistics data
 */
function getStatistics($conn, $mesin) {
    $stats = [
        'total' => 0,
        'with_fingerprint' => 0,
        'per_mesin' => []
    ];
    
    // Total users with fingerprint
    $sql = "SELECT COUNT(*) as count 
            FROM dbo.user_fingerprint 
            WHERE fingerprint_data IS NOT NULL 
            AND DATALENGTH(fingerprint_data) > 10";
    
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['with_fingerprint'] = (int)$row['count'];
        sqlsrv_free_stmt($stmt);
    }
    
    // Count per mesin
    foreach ($mesin as $m) {
        $countSql = "SELECT COUNT(*) as total FROM dbo.user_fingerprint WHERE mesin_id = ?";
        $countStmt = sqlsrv_query($conn, $countSql, [$m['id']]);
        if ($countStmt && $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC)) {
            $stats['per_mesin'][$m['id']] = (int)$countRow['total'];
            sqlsrv_free_stmt($countStmt);
        } else {
            $stats['per_mesin'][$m['id']] = 0;
        }
    }
    
    // Total semua user
    $stats['total'] = array_sum($stats['per_mesin']);
    
    return $stats;
}

// Main processing
try {
    $mesin = getAllMesin($conn);
    
    // Validasi dan sanitize input
    $selectedMesinId = isset($_GET['mesin_id']) ? (int)$_GET['mesin_id'] : '';
    if ($selectedMesinId && !in_array($selectedMesinId, array_column($mesin, 'id'))) {
        $selectedMesinId = '';
    }
    
    // Jika request AJAX dari DataTables
    if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
        // Parameters untuk DataTables
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
        $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
        $search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
        
        // Get data dengan search dan pagination
        $result = getUsersFromDatabase($conn, $selectedMesinId, $search, $start, $length);
        $users = $result['users'];
        $totalFiltered = $result['totalFiltered'];
        
        $totalUsers = getTotalUsersCount($conn, $selectedMesinId);
        
        // Prepare response data
        $output = [
            'draw' => $draw,
            'recordsTotal' => $totalUsers,
            'recordsFiltered' => $totalFiltered,
            'data' => []
        ];
        
        foreach ($users as $i => $u) {
            $row = [];
            
            // Checkbox
            $row[] = '<input type="checkbox" class="user-checkbox" value="' . $u['id'] . '" 
                     data-pin="' . htmlspecialchars($u['pin']) . '" 
                     data-name="' . htmlspecialchars($u['name']) . '"
                     data-has-fingerprint="' . ($u['has_fingerprint'] ? '1' : '0') . '">';
            
            // No
            $row[] = $start + $i + 1;
            
            // PIN
            $row[] = htmlspecialchars($u['pin']);
            
            // Name
            $row[] = htmlspecialchars($u['name']);
            
            // Privilege
            $row[] = htmlspecialchars($u['privilege']);
            
            // Password
            $passwordCell = $u['password_display'];
            if ($u['password']) {
                $passwordCell .= '<button class="btn btn-xs btn-outline-secondary view-password-btn ml-1" 
                                data-password="' . htmlspecialchars($u['password']) . '"
                                title="Lihat Password">
                                <i class="fas fa-eye"></i>
                            </button>';
            }
            $row[] = $passwordCell;
            
            // Mesin
            $row[] = htmlspecialchars($u['nama_mesin']);
            
            // Fingerprint
            $fingerprintCell = '<span class="fingerprint-indicator ' . ($u['has_fingerprint'] ? 'has-fingerprint' : 'no-fingerprint') . '"></span>';
            $fingerprintCell .= $u['fingerprint_info'];
            if ($u['has_fingerprint']) {
                $fingerprintCell .= '<button class="btn btn-xs btn-outline-info view-fingerprint-btn ml-1" 
                                    data-user-id="' . $u['id'] . '" 
                                    data-pin="' . htmlspecialchars($u['pin']) . '"
                                    title="Lihat Detail Sidik Jari">
                                    <i class="fas fa-eye"></i>
                                </button>';
            }
            $row[] = $fingerprintCell;
            
            // Template Size
            $row[] = $u['template_size_formatted'];
            
            // Created
            $row[] = $u['created_at_formatted'];
            
            // Updated
            $row[] = $u['updated_at_formatted'];
            
            // Actions
            $actionCell = '';
            if ($permissions['CanEdit']) {
                $actionCell .= '<button class="btn btn-warning btn-sm edit-user mr-1" 
                              data-user-id="' . $u['id'] . '" 
                              data-pin="' . htmlspecialchars($u['pin']) . '"
                              data-name="' . htmlspecialchars($u['name']) . '"
                              data-privilege="' . htmlspecialchars($u['privilege']) . '"
                              data-password="' . htmlspecialchars($u['password']) . '"
                              title="Edit User">
                              <i class="fas fa-edit"></i>
                          </button>';
            }
            if ($permissions['CanDelete']) {
                $actionCell .= '<button class="btn btn-danger btn-sm delete-user" 
                              data-user-id="' . $u['id'] . '" 
                              data-pin="' . htmlspecialchars($u['pin']) . '"
                              title="Hapus dari Database">
                              <i class="fas fa-trash"></i>
                          </button>';
            }
            $row[] = $actionCell;
            
            $output['data'][] = $row;
        }
        
        // Clear any previous output
        ob_clean();
        
        // Set header dan output JSON
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($output);
        exit;
    }
    
    // Untuk non-AJAX request, get data biasa untuk statistik
    $totalUsers = getTotalUsersCount($conn, $selectedMesinId);
    $stats = getStatistics($conn, $mesin);
    
} catch (Exception $e) {
    // Log error
    error_log("Error in user_fingerprint_database: " . $e->getMessage());
    
    // Jika AJAX request, return error JSON
    if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Terjadi kesalahan saat memuat data'
        ]);
        exit;
    }
    
    // Untuk non-AJAX, set default values
    $users = [];
    $totalUsers = 0;
    $stats = ['total' => 0, 'with_fingerprint' => 0, 'per_mesin' => []];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Fingerprint Database</title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <style>
    .action-buttons { margin-bottom: 15px; }
    .table-checkbox { width: 40px; text-align: center; }
    .selected-count { 
        background-color: #e9ecef; 
        padding: 8px 15px; 
        border-radius: 4px; 
        margin-left: 10px; 
        display: inline-block; 
    }
    .badge-template { font-size: 0.75em; }
    .fingerprint-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .has-fingerprint { background-color: #28a745; }
    .no-fingerprint { background-color: #6c757d; }
    .dataTables_wrapper {
        position: relative;
    }
    .progress-container {
        margin: 10px 0;
    }
    .progress-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        font-size: 0.9em;
    }
    .password-field {
        font-family: monospace;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between">
        <h1>User Fingerprint Database</h1>
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="user_fingerprint.php">Fingerprint</a></li>
          <li class="breadcrumb-item active">Database</li>
        </ol>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">

        <!-- Statistik Cards -->
        <div class="row">
          <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3><?= number_format($stats['total']) ?></h3>
                <p>Total User</p>
              </div>
              <div class="icon"><i class="fas fa-users"></i></div>
              <a href="user_fingerprint_database.php" class="small-box-footer">Lihat Semua <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3><?= number_format($stats['with_fingerprint']) ?></h3>
                <p>Dengan Sidik Jari</p>
              </div>
              <div class="icon"><i class="fas fa-fingerprint"></i></div>
              <a href="user_fingerprint_database.php" class="small-box-footer">Lihat Detail <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <?php foreach ($mesin as $m): ?>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-<?= ($stats['per_mesin'][$m['id']] ?? 0) > 0 ? 'warning' : 'secondary' ?>">
              <div class="inner">
                <h3><?= number_format($stats['per_mesin'][$m['id']] ?? 0) ?></h3>
                <p><?= htmlspecialchars($m['nama_mesin']) ?></p>
              </div>
              <div class="icon"><i class="fas fa-desktop"></i></div>
              <a href="user_fingerprint_database.php?mesin_id=<?= $m['id'] ?>" class="small-box-footer">Detail <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Filter Form -->
        <form method="GET" class="mb-3">
          <div class="row">
            <div class="col-md-4">
              <label>Filter Mesin:</label>
              <select name="mesin_id" class="form-control select2bs4" onchange="this.form.submit()">
                <option value="">-- Semua Mesin --</option>
                <?php foreach ($mesin as $m): ?>
                  <option value="<?= $m['id'] ?>" <?= $selectedMesinId == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_mesin']) ?> (<?= $m['ip_address'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </form>

        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title">Data User di Database (Total: <?= number_format($totalUsers) ?>)</h3>
            <div class="card-tools">
              <span class="selected-count badge badge-light" id="selectedCount" style="display:none">0 dipilih</span>
            </div>
          </div>
          <div class="card-body">
            <!-- Action Buttons -->
            <div class="action-buttons" id="actionButtons" style="display:none">
              <?php if ($permissions['CanDelete']): ?>
              <button class="btn btn-danger btn-sm" id="deleteSelectedBtn">
                <i class="fas fa-trash"></i> Hapus User Terpilih
              </button>
              <?php endif; ?>
              <button class="btn btn-success btn-sm" id="uploadToMachineBtn">
                <i class="fas fa-upload"></i> Upload ke Mesin Lain
              </button>
              <button class="btn btn-info btn-sm" id="uploadWithFingerprintBtn">
                <i class="fas fa-fingerprint"></i> Upload + Sidik Jari
              </button>
              <button class="btn btn-secondary btn-sm" id="clearSelectionBtn">
                <i class="fas fa-times"></i> Batalkan Pilihan
              </button>
            </div>
            
            <div class="table-responsive">
              <table id="userTable" class="table table-hover table-sm w-100">
                <thead class="thead-light">
                  <tr>
                    <th class="table-checkbox">
                      <input type="checkbox" id="selectAll">
                    </th>
                    <th>No</th>
                    <th>PIN</th>
                    <th>Nama</th>
                    <th>Privilege</th>
                    <th>Password</th>
                    <th>Mesin</th>
                    <th>Sidik Jari</th>
                    <th>Template Size</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Data akan di-load via AJAX -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>
</div>

<!-- Modal Upload ke Mesin Lain -->
<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadModalLabel">Upload User ke Mesin</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="targetMachine">Pilih Mesin Tujuan:</label>
          <select id="targetMachine" class="form-control">
            <option value="">-- Pilih Mesin --</option>
            <?php foreach ($mesin as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mesin']) ?> (<?= $m['ip_address'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="includeFingerprint" checked>
            <label class="custom-control-label" for="includeFingerprint">Sertakan data sidik jari</label>
          </div>
          <small class="form-text text-muted" id="fingerprintInfo">
            Menyertakan template sidik jari akan membutuhkan waktu lebih lama
          </small>
        </div>
        <div class="selected-users-info">
          <p><strong>User yang akan diupload:</strong></p>
          <ul id="selectedUsersList" class="list-group"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="confirmUploadBtn">Upload</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Progress Upload -->
<div class="modal fade" id="progressModal" tabindex="-1" role="dialog" aria-labelledby="progressModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= $themeColor ?> text-white">
        <h5 class="modal-title" id="progressModalLabel">Upload dalam Progress</h5>
      </div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <div class="spinner-border text-primary mb-2" role="status">
            <span class="sr-only">Loading...</span>
          </div>
          <p id="progressStatus">Mempersiapkan upload...</p>
        </div>
        <div class="progress-container">
          <div class="progress-info">
            <span id="progressText">0%</span>
            <span id="progressDetail">0/0 user</span>
          </div>
          <div class="progress" style="height: 20px;">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
            </div>
          </div>
        </div>
        <div id="currentAction" class="text-center text-muted small mt-2">
          Menghubungi mesin tujuan...
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detail Sidik Jari -->
<div class="modal fade" id="fingerprintDetailModal" tabindex="-1" role="dialog" aria-labelledby="fingerprintDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= $themeColor ?> text-white">
        <h5 class="modal-title" id="fingerprintDetailModalLabel">Detail Sidik Jari</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="fingerprintDetailContent">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
          </div>
          <p>Memuat data sidik jari...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="editUserForm">
          <input type="hidden" id="editUserId" name="user_id">
          <div class="form-group">
            <label for="editPin">PIN:</label>
            <input type="text" class="form-control" id="editPin" name="pin" required>
          </div>
          <div class="form-group">
            <label for="editName">Nama:</label>
            <input type="text" class="form-control" id="editName" name="name" required>
          </div>
          <div class="form-group">
            <label for="editPrivilege">Privilege:</label>
            <select class="form-control" id="editPrivilege" name="privilege" required>
              <option value="0">User</option>
              <option value="1">Admin</option>
              <option value="2">Super Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label for="editPassword">Password:</label>
            <input type="text" class="form-control password-field" id="editPassword" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
            <small class="form-text text-muted">Password akan ditampilkan dalam bentuk teks biasa</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="confirmEditBtn">Update User</button>
      </div>
    </div>
  </div>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function(){
  'use strict';
  
  // Cache DOM elements
  const $selectAll = $('#selectAll');
  const $selectedCount = $('#selectedCount');
  const $actionButtons = $('#actionButtons');
  
  // State management
  let selectedUsers = [];
  let dataTable;
  
  // Inisialisasi DataTable dengan server-side processing
  function initializeDataTable() {
    const mesinId = '<?= $selectedMesinId ?>';
    
    dataTable = $('#userTable').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: 'user_fingerprint_database.php?ajax=true&mesin_id=' + mesinId,
        type: 'GET',
        dataType: 'json',
        error: function (xhr, error, thrown) {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal memuat data: ' + (thrown || 'Unknown error')
          });
        }
      },
      columns: [
        { 
          data: null,
          orderable: false,
          searchable: false,
          className: 'table-checkbox',
          render: function(data, type, row) {
            return row[0];
          }
        },
        { data: 1, className: 'dt-body-center' },
        { data: 2 },
        { data: 3 },
        { data: 4 },
        { data: 5 },
        { data: 6 },
        { data: 7 },
        { data: 8 },
        { data: 9 },
        { data: 10 },
        { 
          data: 11,
          orderable: false,
          searchable: false,
          className: 'text-center'
        }
      ],
      responsive: true,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      language: {
        processing: "Memproses...",
        lengthMenu: "Tampilkan _MENU_ data per halaman",
        zeroRecords: "Tidak ada data yang ditemukan",
        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
        infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
        infoFiltered: "(disaring dari _MAX_ total data)",
        search: "Cari:",
        paginate: {
          first: "Pertama",
          last: "Terakhir",
          next: "Berikutnya",
          previous: "Sebelumnya"
        }
      },
      order: [[1, 'asc']],
      drawCallback: function(settings) {
        $selectAll.prop('checked', false);
        selectedUsers = [];
        updateSelectedCount();
        
        // Update checkbox data attributes
        $('.user-checkbox').each(function() {
          const $checkbox = $(this);
          const $row = $checkbox.closest('tr');
          const pin = $row.find('td:eq(2)').text();
          const name = $row.find('td:eq(3)').text();
          const fingerprintCell = $row.find('td:eq(7)').html();
          const hasFingerprint = fingerprintCell.includes('has-fingerprint');
          
          $checkbox.data('pin', pin);
          $checkbox.data('name', name);
          $checkbox.data('has-fingerprint', hasFingerprint ? '1' : '0');
        });
      }
    });
  }
  
  $('.select2bs4').select2({ theme: 'bootstrap4' });

  // Progress tracking untuk upload
  let uploadProgress = {
    current: 0,
    total: 0,
    update: function(progress, detail, action) {
      $('#progressBar').css('width', progress + '%').attr('aria-valuenow', progress);
      $('#progressText').text(progress + '%');
      $('#progressDetail').text(detail);
      if (action) {
        $('#currentAction').text(action);
      }
    },
    start: function(totalUsers) {
      this.current = 0;
      this.total = totalUsers;
      $('#progressStatus').text('Memulai upload ' + totalUsers + ' user...');
      this.update(0, '0/' + totalUsers + ' user', 'Menghubungi mesin tujuan...');
      $('#progressModal').modal({backdrop: 'static', keyboard: false});
    },
    increment: function(current, action) {
      this.current = current;
      const progress = Math.round((current / this.total) * 100);
      const detail = current + '/' + this.total + ' user';
      this.update(progress, detail, action);
    },
    complete: function() {
      this.update(100, this.total + '/' + this.total + ' user', 'Upload selesai');
      $('#progressStatus').text('Upload berhasil!');
    }
  };

  // Optimized event handlers
  function updateSelectedCount() {
    const count = selectedUsers.length;
    const withFingerprint = selectedUsers.filter(user => user.has_fingerprint === '1').length;
    
    $selectedCount.text(`${count} dipilih (${withFingerprint} dengan sidik jari)`).toggle(count > 0);
    $actionButtons.toggle(count > 0);
    
    if (count > 0) {
      updateSelectedUsersList();
    }
  }
  
  function updateSelectedUsersList() {
    const withFingerprint = selectedUsers.filter(user => user.has_fingerprint === '1').length;
    const userList = $('#selectedUsersList');
    
    userList.empty();
    selectedUsers.forEach(user => {
      const fingerprintBadge = user.has_fingerprint === '1' ? 
        '<span class="badge badge-success ml-2">Sidik Jari</span>' : 
        '<span class="badge badge-secondary ml-2">Tanpa Sidik Jari</span>';
      
      userList.append(
        `<li class="list-group-item d-flex justify-content-between align-items-center">
          ${user.pin} - ${user.name} ${fingerprintBadge}
        </li>`
      );
    });
    
    // Update fingerprint info
    const $fingerprintInfo = $('#fingerprintInfo');
    if (withFingerprint > 0) {
      $fingerprintInfo.html(
        `<span class="text-success">
          <i class="fas fa-check-circle"></i> ${withFingerprint} user memiliki data sidik jari
        </span>`
      );
      $('#includeFingerprint').prop('disabled', false);
    } else {
      $fingerprintInfo.html(
        `<span class="text-warning">
          <i class="fas fa-exclamation-triangle"></i> Tidak ada user yang memiliki data sidik jari
        </span>`
      );
      $('#includeFingerprint').prop('checked', false).prop('disabled', true);
    }
  }
  
  // Event delegation untuk better performance
  $(document)
    .on('click', '#selectAll', function() {
      const isChecked = $(this).prop('checked');
      $('.user-checkbox').prop('checked', isChecked);
      
      if (isChecked) {
        selectedUsers = [];
        $('.user-checkbox').each(function() {
          selectedUsers.push({
            id: $(this).val(),
            pin: $(this).data('pin'),
            name: $(this).data('name'),
            has_fingerprint: $(this).data('has-fingerprint')
          });
        });
      } else {
        selectedUsers = [];
      }
      
      updateSelectedCount();
    })
    .on('change', '.user-checkbox', function() {
      const $this = $(this);
      const userData = {
        id: $this.val(),
        pin: $this.data('pin'),
        name: $this.data('name'),
        has_fingerprint: $this.data('has-fingerprint')
      };
      
      if ($this.prop('checked')) {
        selectedUsers.push(userData);
      } else {
        selectedUsers = selectedUsers.filter(user => user.id !== userData.id);
        $selectAll.prop('checked', false);
      }
      
      updateSelectedCount();
    })
    .on('click', '#clearSelectionBtn', function() {
      $('.user-checkbox, #selectAll').prop('checked', false);
      selectedUsers = [];
      updateSelectedCount();
    })
    .on('click', '.view-password-btn', function() {
      const password = $(this).data('password');
      Swal.fire({
        title: 'Password',
        html: `<div class="text-left">
                <p><strong>Password:</strong></p>
                <code class="password-field" style="font-size: 1.1em; background: #f8f9fa; padding: 10px; border-radius: 4px; display: block;">${password}</code>
              </div>`,
        icon: 'info',
        confirmButtonText: 'Tutup'
      });
    })
    .on('click', '.view-fingerprint-btn', function() {
      const userId = $(this).data('user-id');
      const pin = $(this).data('pin');
      
      $('#fingerprintDetailContent').html(`
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
          </div>
          <p>Memuat data sidik jari...</p>
        </div>
      `);
      
      $('#fingerprintDetailModal').modal('show');
      
      loadFingerprintData(userId, pin);
    })
    .on('click', '.edit-user', function() {
      const userId = $(this).data('user-id');
      const pin = $(this).data('pin');
      const name = $(this).data('name');
      const privilege = $(this).data('privilege');
      const password = $(this).data('password') || '';
      
      $('#editUserId').val(userId);
      $('#editPin').val(pin);
      $('#editName').val(name);
      $('#editPrivilege').val(privilege);
      $('#editPassword').val(password);
      
      $('#editUserModal').modal('show');
    })
    .on('click', '.delete-user', function() {
      const userId = $(this).data('user-id');
      const pin = $(this).data('pin');
      deleteSingleUser(userId, pin);
    });

  // Delete single user function
  function deleteSingleUser(userId, pin) {
    Swal.fire({
      title: 'Hapus User?',
      text: `User dengan PIN ${pin} akan dihapus dari database`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'user_fingerprint_ajax.php',
          type: 'POST',
          data: {
            action: 'delete_single',
            user_id: userId
          },
          success: function(response) {
            if (response.status === 'success') {
              Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: response.message
              }).then(() => {
                dataTable.ajax.reload();
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: response.message
              });
            }
          },
          error: function() {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'Terjadi kesalahan saat menghapus user'
            });
          }
        });
      }
    });
  }

  // Load fingerprint data
  function loadFingerprintData(userId, pin) {
    $.ajax({
      url: 'user_fingerprint_ajax.php',
      type: 'POST',
      data: {
        action: 'get_fingerprint_from_db',
        user_id: userId
      },
      success: function(response) {
        if (response.status === 'success') {
          let html = `<h6>User: ${pin} - ${response.user_name}</h6>`;
          html += `<p class="text-success"><i class="fas fa-check-circle"></i> ${response.templates.length} template sidik jari ditemukan</p>`;
          html += '<table class="table table-sm table-bordered">';
          html += '<thead><tr><th>Finger ID</th><th>Size</th><th>Valid</th><th>Template Preview</th></tr></thead><tbody>';
          
          response.templates.forEach(template => {
            const templatePreview = template.template.length > 30 ? 
              template.template.substring(0, 30) + '...' : template.template;
            
            html += `<tr>
              <td>${template.finger_id}</td>
              <td>${template.size} bytes</td>
              <td><span class="badge badge-success">Valid</span></td>
              <td><code style="cursor:pointer;" title="Full Template: ${template.template}">${templatePreview}</code></td>
            </tr>`;
          });
          
          html += '</tbody></table>';
          $('#fingerprintDetailContent').html(html);
        } else {
          $('#fingerprintDetailContent').html(`
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle"></i> ${response.message}
            </div>
          `);
        }
      },
      error: function() {
        $('#fingerprintDetailContent').html(`
          <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i> Gagal memuat data sidik jari
          </div>
        `);
      }
    });
  }

  // Event handler untuk edit user
  $('#confirmEditBtn').on('click', function() {
    const formData = {
      action: 'update_user',
      user_id: $('#editUserId').val(),
      pin: $('#editPin').val(),
      name: $('#editName').val(),
      privilege: $('#editPrivilege').val(),
      password: $('#editPassword').val()
    };
    
    if (!formData.pin || !formData.name) {
      Swal.fire({icon:'warning',title:'PIN dan Nama harus diisi!'});
      return;
    }
    
    $.ajax({
      url: 'user_fingerprint_ajax.php',
      type: 'POST',
      data: formData,
      success: function(response) {
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: response.message
          }).then(() => {
            $('#editUserModal').modal('hide');
            dataTable.ajax.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: response.message
          });
        }
      },
      error: function() {
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'Terjadi kesalahan saat mengupdate user'
        });
      }
    });
  });

  // Event handlers untuk action buttons
  $('#deleteSelectedBtn').on('click', function() {
    if (selectedUsers.length === 0) {
      Swal.fire({icon:'warning',title:'Pilih user terlebih dahulu!'});
      return;
    }
    
    Swal.fire({
      title: 'Hapus User?',
      html: `Anda akan menghapus <strong>${selectedUsers.length}</strong> user dari database.<br>Tindakan ini tidak dapat dibatalkan!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'user_fingerprint_ajax.php',
          type: 'POST',
          data: {
            action: 'delete_multiple',
            users: JSON.stringify(selectedUsers)
          },
          success: function(response) {
            if (response.status === 'success') {
              Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: response.message
              }).then(() => {
                dataTable.ajax.reload();
                selectedUsers = [];
                updateSelectedCount();
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                html: response.message + (response.errors ? '<br><small>' + response.errors.join('<br>') + '</small>' : '')
              });
            }
          },
          error: function() {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'Terjadi kesalahan saat menghapus user'
            });
          }
        });
      }
    });
  });

  $('#uploadToMachineBtn').on('click', function() {
    if (selectedUsers.length === 0) {
      Swal.fire({icon:'warning',title:'Pilih user terlebih dahulu!'});
      return;
    }
    
    $('#includeFingerprint').prop('checked', false);
    $('#uploadModal').modal('show');
  });

  $('#uploadWithFingerprintBtn').on('click', function() {
    if (selectedUsers.length === 0) {
      Swal.fire({icon:'warning',title:'Pilih user terlebih dahulu!'});
      return;
    }
    
    const withFingerprint = selectedUsers.filter(user => user.has_fingerprint === '1').length;
    if (withFingerprint === 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Tidak ada sidik jari',
        text: 'Tidak ada user yang memiliki data sidik jari untuk diupload'
      });
      return;
    }
    
    $('#includeFingerprint').prop('checked', true);
    $('#uploadModal').modal('show');
  });

  $('#confirmUploadBtn').on('click', function() {
    const targetMachineId = $('#targetMachine').val();
    const includeFingerprint = $('#includeFingerprint').prop('checked');
    
    if (!targetMachineId) {
      Swal.fire({icon:'warning',title:'Pilih mesin tujuan!'});
      return;
    }
    
    $('#uploadModal').modal('hide');
    
    const action = includeFingerprint ? 'upload_with_fingerprint_to_machine' : 'upload_to_machine';
    const title = includeFingerprint ? 'Upload User dengan Sidik Jari?' : 'Upload User?';
    const text = includeFingerprint ? 
      `Anda akan mengupload ${selectedUsers.length} user beserta data sidik jari ke mesin tujuan` :
      `Anda akan mengupload ${selectedUsers.length} user ke mesin tujuan`;
    
    Swal.fire({
      title: title,
      text: text,
      icon: 'info',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Upload!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        // Mulai proses upload dengan progress tracking
        startUploadProcess(action, targetMachineId, selectedUsers.length);
      }
    });
  });

  // Fungsi untuk memulai proses upload dengan progress
  function startUploadProcess(action, targetMachineId, totalUsers) {
    // Tampilkan modal progress
    uploadProgress.start(totalUsers);
    
    // Kirim request upload
    $.ajax({
      url: 'user_fingerprint_ajax.php',
      type: 'POST',
      data: {
        action: action,
        users: JSON.stringify(selectedUsers),
        target_machine_id: targetMachineId
      },
      success: function(response) {
        // Tutup modal progress
        setTimeout(() => {
          $('#progressModal').modal('hide');
        }, 1000);
        
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: response.message
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            html: response.message + (response.errors ? '<br><small>' + response.errors.slice(0, 3).join('<br>') + '</small>' : '')
          });
        }
      },
      error: function(xhr, status, error) {
        $('#progressModal').modal('hide');
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'Terjadi kesalahan saat mengupload user: ' + error
        });
      }
    });
    
    // Simulasi progress update (dalam implementasi real, ini akan dipanggil dari server via WebSocket atau polling)
    simulateProgressUpdate(totalUsers);
  }

  // Fungsi untuk mensimulasikan update progress (dalam implementasi real, ini akan diganti dengan update dari server)
  function simulateProgressUpdate(totalUsers) {
    let current = 0;
    const interval = setInterval(() => {
      current++;
      const actions = [
        'Menghubungi mesin...',
        'Mempersiapkan data user...',
        'Mengupload data user...',
        'Mengupload template sidik jari...',
        'Memverifikasi data...'
      ];
      const randomAction = actions[Math.floor(Math.random() * actions.length)];
      
      uploadProgress.increment(current, randomAction);
      
      if (current >= totalUsers) {
        uploadProgress.complete();
        clearInterval(interval);
      }
    }, 500);
  }

  // Initialize DataTable
  initializeDataTable();

  // Notifikasi session
  <?php if(isset($_SESSION['success'])): ?>
    Swal.fire({
      icon: 'success',
      title: 'Berhasil!',
      text: '<?= addslashes($_SESSION['success']) ?>',
      timer: 2000,
      showConfirmButton: false
    });
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>
  <?php if(isset($_SESSION['error'])): ?>
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: '<?= addslashes($_SESSION['error']) ?>',
      timer: 2000,
      showConfirmButton: false
    });
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>
});
</script>
</body>
</html>