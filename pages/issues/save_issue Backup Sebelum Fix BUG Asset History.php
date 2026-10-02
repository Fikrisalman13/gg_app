<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

// Ensure server uses Jakarta timezone for issue timestamps
date_default_timezone_set('Asia/Jakarta');

$response = [
    'success' => false,
    'message' => 'Unknown error'
];

if (!isset($_SESSION['UserName'])) {
    $response['message'] = "Silakan login terlebih dahulu";
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Common fields
    $id = $_POST['issue_id'] ?? null;
    $username = $_SESSION['UserName'];
    $namaLengkap = $_SESSION['NamaLengkap'] ?? $username; // Use existing session var
    
    // User Snapshot (from Session/DB or Form)
    // In strict backend, we might want to fetch these from DB based on current user to ensure validity,
    // but here we trust the form/process or just use session.
    // The form submits nama, jabatan, departemen, bagian.
    // We will use the form values or session if missing.
    
    $jabatan = $_POST['jabatan'] ?? '';
    $departemen = $_POST['departemen'] ?? '';
    $bagian = $_POST['bagian'] ?? '';

    if ($action === 'add') {
        try {
            // Support batch insert: accept `issue_names_json` (array of names) or fallback to `issue_name`.
            $issue_type = $_POST['issue_type'] ?? '';
            if (empty($issue_type)) throw new Exception("Issue Type wajib diisi.");

            $kategori = $_POST['kategori'] ?? '';
            $sub_kategori = $_POST['sub_kategori'] ?? '';
            $status = $_POST['status'] ?? 'To Do';
            $priority = $_POST['priority'] ?? 'Normal';
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $description = $_POST['description'] ?? '';
            $asset_id = !empty($_POST['asset_id']) ? $_POST['asset_id'] : null;
            $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
            
            // Handle file upload
            $attachment_path = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/issues_attachments/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $originalFileName = pathinfo($_FILES['attachment']['name'], PATHINFO_FILENAME);
                $fileExtension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $sanitizedName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalFileName);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = date('YmdHis') . '_' . $sanitizedName . '.' . $fileExtension;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newFileName)) {
                        $attachment_path = '/gg_app/uploads/issues_attachments/' . $newFileName;
                    } else {
                        throw new Exception("Gagal mengunggah lampiran.");
                    }
                } else {
                    throw new Exception("Ekstensi file lampiran tidak diizinkan.");
                }
            }
            
            // Auto-set tanggal_selesai if status is DONE
            // We'll prefer using SQL Server's GETDATE() to keep DB timestamps consistent
            $tanggal_selesai = null;
            $use_getdate_for_completion = false;
            if ($status === 'Done') {
                $use_getdate_for_completion = true;
            }

            $names = [];
            if (!empty($_POST['issue_names_json'])) {
                $decoded = json_decode($_POST['issue_names_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $n) {
                        $trim = trim((string)$n);
                        if ($trim !== '') $names[] = $trim;
                    }
                }
            }
            if (empty($names) && !empty($_POST['issue_name'])) {
                $names[] = trim($_POST['issue_name']);
            }

            if (empty($names)) throw new Exception("Minimal satu Issue Name harus diisi.");

            // Prepare two SQL variants: one using parameter for tanggal_selesai, one using GETDATE()
            $sql_param = "INSERT INTO dbo.issues (
                        issue_name, issue_type, kategori, sub_kategori, asset_id, client_id,
                        status, priority, due_date, description, tanggal_selesai,
                        created_at, created_by, update_at, update_by,
                        departemen, bagian, jabatan, attachment
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        GETDATE(), ?, NULL, NULL,
                        ?, ?, ?, ?
                    )";

            // When creating an issue that is already 'Done', store completion timestamp immediately using GETDATE()
            $sql_getdate = "INSERT INTO dbo.issues (
                        issue_name, issue_type, kategori, sub_kategori, asset_id, client_id,
                        status, priority, due_date, description, tanggal_selesai,
                        created_at, created_by, update_at, update_by,
                        departemen, bagian, jabatan, attachment
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, GETDATE(),
                        GETDATE(), ?, NULL, NULL,
                        ?, ?, ?, ?
                    )";
            // Helper: build note for asset_history based on sub_kategori
            $tintaSubKats = ['Isi Tinta Cair', 'Isi Tinta Serbuk'];
            $inserted = 0;
            foreach ($names as $issue_name) {
                if ($use_getdate_for_completion) {
                    $params = [
                        $issue_name, $issue_type, $kategori, $sub_kategori, $asset_id, $client_id,
                        $status, $priority, $due_date, $description,
                        $namaLengkap, // created_by
                        $departemen, $bagian, $jabatan, $attachment_path
                    ];
                    $stmt = sqlsrv_query($conn, $sql_getdate, $params);
                } else {
                    $params = [
                        $issue_name, $issue_type, $kategori, $sub_kategori, $asset_id, $client_id,
                        $status, $priority, $due_date, $description, $tanggal_selesai,
                        $namaLengkap, // created_by
                        $departemen, $bagian, $jabatan, $attachment_path
                    ];
                    $stmt = sqlsrv_query($conn, $sql_param, $params);
                }
                if ($stmt === false) {
                    throw new Exception("Database error on insert: " . print_r(sqlsrv_errors(), true));
                }
                
                // Asset History and Status Update Logic
                // Asset History and Status Update Logic
                // Refined: Only if issue_type == 'Maintenance'
                if (!empty($asset_id) && $issue_type === 'Maintenance') {
                    // Fetch current asset details
                    $sqlAsset = "SELECT a.id_status, s.nama_status FROM dbo.m_asset a 
                                 LEFT JOIN dbo.m_status s ON a.id_status = s.id_status 
                                 WHERE a.id_asset = ?";
                    $stmtAsset = sqlsrv_query($conn, $sqlAsset, [$asset_id]);
                    $currentStatusId = null;
                    $currentStatusName = '-';
                    
                    if ($stmtAsset && $rowAsset = sqlsrv_fetch_array($stmtAsset, SQLSRV_FETCH_ASSOC)) {
                        $currentStatusId = $rowAsset['id_status'];
                        $currentStatusName = $rowAsset['nama_status'];
                    }
                    if ($stmtAsset) sqlsrv_free_stmt($stmtAsset);

                    $maintenanceId = 6; // Maintenance
                    $usedId = 1;        // Used
                    $maintenanceName = 'Maintenance';
                    $usedName = 'Used';

                    // Prepare History Insert Statement
                    $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, GETDATE())";

                    // Scenario 1: Issue Created as NOT DONE (e.g. To Do/In Progress)
                    // Action: Change Asset to Maintenance (if not already)
                    if ($status !== 'Done') {
                        if ($currentStatusId != $maintenanceId) {
                            $note = in_array($sub_kategori, $tintaSubKats) ? $sub_kategori . ' - ' . $issue_name : $issue_name;
                            $changeType = 'status asset';
                            
                            $paramsHist = [$asset_id, $currentStatusName, $maintenanceName, $note, $changeType, $username];
                            sqlsrv_query($conn, $sqlHist, $paramsHist);

                            // Update Asset Status
                            // FIXED: update_at -> upddate, update_by -> upduser
                            $sqlUpdate = "UPDATE dbo.m_asset SET id_status = ?, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                            sqlsrv_query($conn, $sqlUpdate, [$maintenanceId, $username, $asset_id]);
                        }
                    } 
                    // Scenario 2: Issue Created DIRECTLY as DONE
                    else {
                        // History 1: Start Maintenance
                        $note1 = in_array($sub_kategori, $tintaSubKats) ? $sub_kategori . ' - ' . $issue_name : $issue_name;
                        $paramsHist1 = [$asset_id, $currentStatusName, $maintenanceName, $note1, 'status asset', $username];
                        sqlsrv_query($conn, $sqlHist, $paramsHist1);

                        // History 2: End Maintenance (Maintenance -> Used)
                        $note2 = in_array($sub_kategori, $tintaSubKats) ? $sub_kategori . ' - ' . $issue_name : $issue_name;
                        $paramsHist2 = [$asset_id, $maintenanceName, $usedName, $note2, 'status asset', $username];
                        sqlsrv_query($conn, $sqlHist, $paramsHist2);

                        // Update Asset Status to Used
                        $sqlUpdate = "UPDATE dbo.m_asset SET id_status = ?, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                        sqlsrv_query($conn, $sqlUpdate, [$usedId, $username, $asset_id]);
                    }
                }

                $inserted++;
            }

            $response['success'] = true;
            $response['message'] = "Berhasil menambahkan {$inserted} issue.";

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        
    } elseif ($action === 'edit') {
        try {
            if (empty($id)) throw new Exception("ID Issue tidak valid.");
            
            $issue_name = $_POST['issue_name'];
            $issue_type = $_POST['issue_type'];
            $kategori = $_POST['kategori'] ?? '';
            $sub_kategori = $_POST['sub_kategori'] ?? '';
            $status = $_POST['status'] ?? 'To Do';
            $priority = $_POST['priority'] ?? 'Normal';
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $description = $_POST['description'] ?? '';
            $asset_id = !empty($_POST['asset_id']) ? $_POST['asset_id'] : null;
            $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
            
            // Get current status to check if it's changing to DONE
            // Fixed: Added asset_id, issue_type, issue_name for revert logic
            $sqlCurrent = "SELECT status, tanggal_selesai, asset_id, issue_type, issue_name, attachment FROM dbo.issues WHERE issue_id = ?";
            $stmtCurrent = sqlsrv_query($conn, $sqlCurrent, [$id]);
            $currentStatus = null;
            $currentTanggalSelesai = null;
            $oldAttachment = null;
            
            if ($stmtCurrent && $rowCurrent = sqlsrv_fetch_array($stmtCurrent, SQLSRV_FETCH_ASSOC)) {
                $currentStatus = $rowCurrent['status'];
                $currentTanggalSelesai = $rowCurrent['tanggal_selesai'];
                
                // NEW: Fetch Old Asset and Type to handle Revert Logic
                $oldAssetId = $rowCurrent['asset_id'];
                $oldIssueType = trim($rowCurrent['issue_type']); // Trim whitespace
                $oldIssueName = $rowCurrent['issue_name'];
                $oldAttachment = $rowCurrent['attachment'];
            }
            if ($stmtCurrent) sqlsrv_free_stmt($stmtCurrent);

            // Handle file upload
            $attachment_path = $oldAttachment;
            $remove_attachment = !empty($_POST['remove_attachment']) ? true : false;
            
            if ($remove_attachment && $oldAttachment) {
                $oldFilePath = $_SERVER['DOCUMENT_ROOT'] . $oldAttachment;
                if (file_exists($oldFilePath)) @unlink($oldFilePath);
                $attachment_path = null;
            }

            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/issues_attachments/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                $originalFileName = pathinfo($_FILES['attachment']['name'], PATHINFO_FILENAME);
                $fileExtension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $sanitizedName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalFileName);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = date('YmdHis') . '_' . $sanitizedName . '.' . $fileExtension;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newFileName)) {
                        if ($oldAttachment && !$remove_attachment) {
                            $oldFilePath = $_SERVER['DOCUMENT_ROOT'] . $oldAttachment;
                            if (file_exists($oldFilePath)) @unlink($oldFilePath);
                        }
                        $attachment_path = '/gg_app/uploads/issues_attachments/' . $newFileName;
                    } else {
                        throw new Exception("Gagal mengunggah lampiran baru.");
                    }
                } else {
                    throw new Exception("Ekstensi file lampiran tidak diizinkan.");
                }
            }

            // --- LOGIC 1: Revert Old Asset Status (if changed) ---
            if (!empty($oldAssetId) && $oldIssueType === 'Maintenance' && $currentStatus !== 'Done') {
                $shouldRevert = false;

                // Condition A: Asset Changed or Removed (Compare as strings)
                if ((string)$asset_id !== (string)$oldAssetId) {
                    $shouldRevert = true;
                }
                // Condition B: Issue Type Changed from Maintenance to something else
                elseif ($issue_type !== 'Maintenance') {
                    $shouldRevert = true;
                }
                
                if ($shouldRevert) {
                    // Revert Old Asset to Used (1)
                    $sqlOldAsset = "SELECT a.id_status, s.nama_status FROM dbo.m_asset a LEFT JOIN dbo.m_status s ON a.id_status = s.id_status WHERE a.id_asset = ?";
                    $stmtOldA = sqlsrv_query($conn, $sqlOldAsset, [$oldAssetId]);
                    if ($stmtOldA && $rowOldA = sqlsrv_fetch_array($stmtOldA, SQLSRV_FETCH_ASSOC)) {
                         $currStatOld = $rowOldA['nama_status'];
                         
                         $noteOld = "Dibatalkan dari Issue: " . $oldIssueName;
                         // Truncate note if needed (assume 255 chars safe, but if column is shorter?)
                         // Usually TEXT or VARCHAR(MAX) or VARCHAR(255). 
                         
                         $sqlHistOld = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                         $stmtHist = sqlsrv_query($conn, $sqlHistOld, [$oldAssetId, $currStatOld, 'Used', $noteOld, 'status asset', $username]);
                         
                         if (!$stmtHist) {
                             // Log error and throw
                             throw new Exception("Gagal simpan history asset lama: " . print_r(sqlsrv_errors(), true));
                         }
                         
                         // Update Status
                         $sqlUpdOld = "UPDATE dbo.m_asset SET id_status = 1, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                         sqlsrv_query($conn, $sqlUpdOld, [$username, $oldAssetId]);
                    }
                }
            }
            
            // Auto-set tanggal_selesai if status changes to DONE
            // We'll use SQL GETDATE() when setting completion timestamp to ensure DB consistency
            $tanggal_selesai = $currentTanggalSelesai;
            $set_completion_to_getdate = false;
            
            // Asset Logic ONLY if type is Maintenance
            if ($issue_type === 'Maintenance') {
                    if ($status === 'Done' && $currentStatus !== 'Done') {
                    // Status changed to DONE, set completion date using DB GETDATE()
                    $set_completion_to_getdate = true;
                    
                    // Asset Logic: Set to Used (1)
                    if (!empty($asset_id) && $issue_type === 'Maintenance') {
                        // Get current asset status
                        $sqlA = "SELECT a.id_status, s.nama_status FROM dbo.m_asset a LEFT JOIN dbo.m_status s ON a.id_status = s.id_status WHERE a.id_asset = ?";
                        $stmtA = sqlsrv_query($conn, $sqlA, [$asset_id]);
                        if($stmtA && $rowA = sqlsrv_fetch_array($stmtA, SQLSRV_FETCH_ASSOC)){
                            $currStat = $rowA['nama_status'];
                            $usedName = 'Used';
                            
                            $tintaSubKats = ['Isi Tinta Cair', 'Isi Tinta Serbuk'];
                            $note = in_array($sub_kategori, $tintaSubKats) ? $sub_kategori . ' - ' . $issue_name : $issue_name;
                            $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                            sqlsrv_query($conn, $sqlHist, [$asset_id, $currStat, $usedName, $note, 'status asset', $username]);
                            
                            // FIXED: update_at -> upddate, update_by -> upduser
                            $sqlUpd = "UPDATE dbo.m_asset SET id_status = 1, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                            sqlsrv_query($conn, $sqlUpd, [$username, $asset_id]);
                        }
                    }
                    
                } elseif ($status !== 'Done' && $currentStatus === 'Done') {
                    // Status changed from DONE to something else, clear completion date
                    $tanggal_selesai = null;
                    
                    // Asset Logic: Revert to Maintenance (6)
                    if (!empty($asset_id) && $issue_type === 'Maintenance') {
                         // Get current asset status
                        $sqlA = "SELECT a.id_status, s.nama_status FROM dbo.m_asset a LEFT JOIN dbo.m_status s ON a.id_status = s.id_status WHERE a.id_asset = ?";
                        $stmtA = sqlsrv_query($conn, $sqlA, [$asset_id]);
                        if($stmtA && $rowA = sqlsrv_fetch_array($stmtA, SQLSRV_FETCH_ASSOC)){
                            $currStat = $rowA['nama_status'];
                            $maintName = 'Maintenance';
                            
                            $tintaSubKats = ['Isi Tinta Cair', 'Isi Tinta Serbuk'];
                            $note = in_array($sub_kategori, $tintaSubKats) ? $sub_kategori . ' - ' . $issue_name : $issue_name;
                            $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                            sqlsrv_query($conn, $sqlHist, [$asset_id, $currStat, $maintName, $note, 'status asset', $username]);
                            
                            // FIXED: update_at -> upddate, update_by -> upduser
                            $sqlUpd = "UPDATE dbo.m_asset SET id_status = 6, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                            sqlsrv_query($conn, $sqlUpd, [$username, $asset_id]);
                        }
                    }
                } elseif ($status !== 'Done' && !empty($asset_id)) {
                    // If not done, ensure it is in Maintenance (only if type is Maintenance)
                    if ($issue_type === 'Maintenance') {
                        $sqlA = "SELECT id_status FROM dbo.m_asset WHERE id_asset = ?";
                        $stmtA = sqlsrv_query($conn, $sqlA, [$asset_id]);
                        if($stmtA && $rowA = sqlsrv_fetch_array($stmtA, SQLSRV_FETCH_ASSOC)){
                            if ($rowA['id_status'] != 6) {
                                // Force to Maintenance: Add History first
                                $currStat = 'Used'; // Assume if not maintenance (6), it was likely used (1) or other. 
                                // Better: fetch actual name.
                                $sqlAName = "SELECT nama_status FROM dbo.m_status WHERE id_status = ?";
                                $stmtAName = sqlsrv_query($conn, $sqlAName, [$rowA['id_status']]);
                                if ($stmtAName && $rowAName = sqlsrv_fetch_array($stmtAName, SQLSRV_FETCH_ASSOC)) {
                                    $currStat = $rowAName['nama_status'];
                                }
                                
                                $maintName = 'Maintenance';
                                $tintaSubKats = ['Isi Tinta Cair', 'Isi Tinta Serbuk'];
                                $note = in_array($sub_kategori, $tintaSubKats) ? $sub_kategori . ' - ' . $issue_name : $issue_name;
                                $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                                sqlsrv_query($conn, $sqlHist, [$asset_id, $currStat, $maintName, $note, 'status asset', $username]);

                                // FIXED: update_at -> upddate, update_by -> upduser
                                 $sqlUpd = "UPDATE dbo.m_asset SET id_status = 6, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                                 sqlsrv_query($conn, $sqlUpd, [$username, $asset_id]);
                            }
                        }
                    }
                }
            } else {
                 // Non-Maintenance type, just handle date logic
                 if ($status === 'Done' && $currentStatus !== 'Done') {
                     $tanggal_selesai = date('Y-m-d H:i:s');
                 } elseif ($status !== 'Done' && $currentStatus === 'Done') {
                     $tanggal_selesai = null;
                 }
            }
            
            // Build update SQL with dynamic handling for tanggal_selesai (GETDATE / NULL / parameter)
            $tanggal_clause = '';
            $update_params = [];
            if ($set_completion_to_getdate) {
                $tanggal_clause = 'tanggal_selesai = GETDATE(),';
            } elseif ($tanggal_selesai === null) {
                $tanggal_clause = 'tanggal_selesai = NULL,';
            } else {
                $tanggal_clause = 'tanggal_selesai = ?,';
            }

            $sql_update = "UPDATE dbo.issues SET 
                        issue_name = ?, issue_type = ?, kategori = ?, sub_kategori = ?, 
                        asset_id = ?, client_id = ?,
                        status = ?, priority = ?, due_date = ?, description = ?, 
                        " . $tanggal_clause . "
                        update_at = GETDATE(), update_by = ?, attachment = ?
                    WHERE issue_id = ?";

            // Assemble parameters in correct order matching placeholders
            $update_params = [
                $issue_name, $issue_type, $kategori, $sub_kategori,
                $asset_id, $client_id,
                $status, $priority, $due_date, $description
            ];
            if (!$set_completion_to_getdate && $tanggal_selesai !== null) {
                $update_params[] = $tanggal_selesai;
            }
            $update_params[] = $namaLengkap; // update_by
            $update_params[] = $attachment_path; // attachment
            $update_params[] = $id; // WHERE issue_id

            $stmt = sqlsrv_query($conn, $sql_update, $update_params);
            
            if ($stmt === false) {
                throw new Exception("Database error: " . print_r(sqlsrv_errors(), true));
            }
            
            $response['success'] = true;
            $response['message'] = "Issue berhasil diperbarui.";
            
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        
    } elseif ($action === 'delete') {
         try {
            if (empty($id)) throw new Exception("ID Issue tidak valid.");
            
            // Get issue details before deleting to handle asset logic
            $sqlCheck = "SELECT issue_type, status, asset_id, issue_name FROM dbo.issues WHERE issue_id = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$id]);
            if ($stmtCheck && $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
                $delType = $rowCheck['issue_type'];
                $delStatus = $rowCheck['status'];
                $delAssetId = $rowCheck['asset_id'];
                $delName = $rowCheck['issue_name'];
                
                // If it was a 'Maintenance' issue and NOT Done, the asset is likely currently in Maintenance (6).
                // We should revert it to Used (1).
                if ($delType === 'Maintenance' && $delStatus !== 'Done' && !empty($delAssetId)) {
                    // Update asset status to Used (1)
                    $usedId = 1;
                    $sqlRevert = "UPDATE dbo.m_asset SET id_status = ?, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                    sqlsrv_query($conn, $sqlRevert, [$usedId, $username, $delAssetId]);
                    
                    // Optional: Log history that maintenance was cancelled/deleted?
                    // User said "Kembali Ke Semula", implying just state revert.
                }
            }
            
            $sql = "DELETE FROM dbo.issues WHERE issue_id = ?";
            $params = [$id];
            
            $stmt = sqlsrv_query($conn, $sql, $params);
             if ($stmt === false) {
                throw new Exception("Database error: " . print_r(sqlsrv_errors(), true));
            }
            
            $response['success'] = true;
            $response['message'] = "Issue berhasil dihapus.";
            
         } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
    } elseif ($action === 'mark_done') {
        try {
            if (empty($id)) throw new Exception("ID Issue tidak valid.");

            // 1. Validasi Issue & Permission (Creator Only)
            $sqlCheck = "SELECT created_by, status, issue_type, asset_id, issue_name, sub_kategori FROM dbo.issues WHERE issue_id = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$id]);
            
            $issueData = null;
            if ($stmtCheck && $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
                $issueData = $row;
            } else {
                throw new Exception("Issue tidak ditemukan.");
            }

            // Normalisasi comparison
            // created_by stores Nama Lengkap usually. 
            // Try matching against FullName OR UserName to be safe
            $creator = $issueData['created_by'];
            $currentUserFull = $_SESSION['NamaLengkap'] ?? '';
            $currentUserLogin = $_SESSION['UserName'] ?? '';
            
            // Check if user is CREATOR
            $isCreator = ($creator === $currentUserFull) || ($creator === $currentUserLogin);
            
            // Also allow if user has Global EDIT permission (optional, but good for Admin using this quick button)
            // Ideally we check DB permission, but here we can trust session or just restriction to Creator.
            // User Request: "User 1 yg membuat issues... akan Melihat Button... User 1 Tidak akan bisa merubah Status issues yg dibuat oleh orang lain"
            // So Strict Creator Check is safer to fulfill the request.
            
            if (!$isCreator) {
                // If not creator, check if Admin/Edit? 
                // Let's stick to strict Creator check for the "Quick Button" feature to ensure safety.
                // Or maybe the button won't appear, but if they hack the request?
                throw new Exception("Anda tidak memiliki akses untuk menyelesaikan issue ini (Bukan Pembuat).");
            }
            
            if ($issueData['status'] === 'Done') {
                throw new Exception("Issue sudah berstatus Done.");
            }

            // 2. Perform Update to Done
            // Update status = Done, tanggal_selesai = GETDATE()
            $sqlUpdate = "UPDATE dbo.issues SET status = 'Done', tanggal_selesai = GETDATE(), update_at = GETDATE(), update_by = ? WHERE issue_id = ?";
            $stmtUpd = sqlsrv_query($conn, $sqlUpdate, [$namaLengkap, $id]);
            
            if (!$stmtUpd) {
                throw new Exception("Gagal update status database.");
            }

            // 3. Handle Asset Logic (Maintenance -> Used)
            // Verify if it WAS Maintenance type
            $issueType = $issueData['issue_type'];
            $assetId = $issueData['asset_id'];
            $issueName = $issueData['issue_name'];

            if ($issueType === 'Maintenance' && !empty($assetId)) {
                // Set Asset to Used (1)
                $usedId = 1;
                $usedName = 'Used';
                
                // Get Current Asset Status Name for History
                $sqlA = "SELECT a.id_status, s.nama_status FROM dbo.m_asset a LEFT JOIN dbo.m_status s ON a.id_status = s.id_status WHERE a.id_asset = ?";
                $stmtA = sqlsrv_query($conn, $sqlA, [$assetId]);
                $currentAssetName = '-';
                if ($stmtA && $rowA = sqlsrv_fetch_array($stmtA, SQLSRV_FETCH_ASSOC)) {
                    $currentAssetName = $rowA['nama_status'];
                }

                // Add History
                $tintaSubKats = ['Isi Tinta Cair', 'Isi Tinta Serbuk'];
                $issueSubKat = $issueData['sub_kategori'] ?? '';
                $noteBase = in_array($issueSubKat, $tintaSubKats) ? $issueSubKat . ' - ' . $issueName : $issueName;
                $note = "Issues Done by Creator: " . $noteBase;
                $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                sqlsrv_query($conn, $sqlHist, [$assetId, $currentAssetName, $usedName, $note, 'status asset', $username]);

                // Update Asset Master
                $sqlAssetUpd = "UPDATE dbo.m_asset SET id_status = ?, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
                sqlsrv_query($conn, $sqlAssetUpd, [$usedId, $username, $assetId]);
            }

            $response['success'] = true;
            $response['message'] = "Issue berhasil ditandai sebagai Done.";

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
    }
}

echo json_encode($response);
sqlsrv_close($conn);
?>
