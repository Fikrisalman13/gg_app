<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../koneksi.php';

// Cek apakah request AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    die('Invalid request');
}

$action = $_REQUEST['action'] ?? '';
$username = $_SESSION['UserName'] ?? null;
$groupId = (int) ($_SESSION['GroupId'] ?? 0);
$isAdministratorGroup = ($groupId === 1);
$idDeptUser = null;

if ($username) {
    $stmtDeptUser = sqlsrv_query(
        $conn,
        "SELECT b.id_bag
         FROM dbo.SMUserMs u
         JOIN dbo.m_emp e ON u.EmpId = e.id_emp
         JOIN dbo.m_bag b ON e.id_bag = b.id_bag
         WHERE u.UserName = ?",
        [$username]
    );
    if ($stmtDeptUser && $rDeptUser = sqlsrv_fetch_array($stmtDeptUser, SQLSRV_FETCH_ASSOC)) {
        $idDeptUser = $rDeptUser['id_bag'];
    }
    if ($stmtDeptUser) {
        sqlsrv_free_stmt($stmtDeptUser);
    }
}

$bagianFilterSql = (!$isAdministratorGroup && $idDeptUser !== null) ? ' AND d.bagian_id = ?' : '';
$bagianFilterParams = (!$isAdministratorGroup && $idDeptUser !== null) ? [$idDeptUser] : [];

if ($action === 'get_form') {
    $categoryId = (int)($_GET['category_id'] ?? 0);
    if ($categoryId <= 0) {
        echo '<div class="alert alert-danger">Kategori tidak valid.</div>';
        exit;
    }

    // Ambil kolom-kolom dari database
    $stmt = sqlsrv_query($conn, "SELECT * FROM dr_fields WHERE category_id = ? ORDER BY sort_order ASC", [$categoryId]);
    if (!$stmt) {
        echo '<div class="alert alert-danger">Gagal mengambil struktur form.</div>';
        exit;
    }

    $html = '<div class="row">';
    
    // Looping setiap field dinamis/sistem
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $requiredAttr = ($row['is_required'] == 1) ? 'required' : '';
        $asterisk = ($row['is_required'] == 1) ? '<span class="text-danger">*</span>' : '';
        $colClass = !empty($row['grid_class']) ? $row['grid_class'] : 'col-md-6';
        
        $html .= '<div class="'.$colClass.'">';
        $html .= '<div class="form-group">';
        
        if ($row['field_name'] === 'system_bagian') {
            $html .= '<label class="font-weight-bold">Bagian ' . $asterisk . '</label>';
            $html .= '<select name="bagian_id" class="form-control" ' . $requiredAttr . '>';
            $html .= '<option value="">-- Pilih Bagian --</option>';
            $username = $_SESSION['UserName'] ?? '';
            if ($username) {
                $stmtBag = sqlsrv_query($conn, "SELECT b.id_bag, b.bagian FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp JOIN dbo.m_bag b ON e.id_bag = b.id_bag WHERE u.UserName = ?", [$username]);
                if ($stmtBag) {
                    while ($rb = sqlsrv_fetch_array($stmtBag, SQLSRV_FETCH_ASSOC)) {
                        $html .= '<option value="' . $rb['id_bag'] . '">' . htmlspecialchars($rb['bagian']) . '</option>';
                    }
                }
            }
            $html .= '</select>';
        } elseif ($row['field_name'] === 'system_expire_date') {
            $html .= '<label class="font-weight-bold text-danger">Expire ' . $asterisk . '</label>';
            $html .= '<input type="date" name="expire_date" class="form-control border-danger" ' . $requiredAttr . '>';
        } elseif ($row['field_name'] === 'system_file_dokumen') {
            $html .= '<label class="font-weight-bold">Upload Dokumen (PDF/JPG/PNG) ' . $asterisk . '</label>';
            $html .= '<input type="file" name="file_dokumen[]" class="form-control-file" accept="application/pdf,image/jpeg,image/png" multiple ' . $requiredAttr . '>';
            $html .= '<small class="text-muted">Bisa pilih lebih dari 1 file sekaligus.</small>';
        } elseif ($row['field_name'] === 'system_email_reminder') {
            $html .= '<label class="font-weight-bold">Email Reminder</label>';
            $html .= '<input type="email" name="email_reminder" class="form-control" placeholder="user@example.com">';
        } elseif ($row['field_name'] === 'system_no_whatsapp') {
            $html .= '<label class="font-weight-bold">No WA</label>';
            $html .= '<input type="text" name="no_whatsapp" class="form-control" placeholder="628xxxxxxx">';
        } else {
            $html .= '<label class="font-weight-bold">' . htmlspecialchars($row['field_label']) . ' ' . $asterisk . '</label>';
            if ($row['field_type'] === 'textarea') {
                $html .= '<textarea name="dynamic_'.htmlspecialchars($row['field_name']).'" class="form-control" rows="3" '.$requiredAttr.'></textarea>';
            } elseif ($row['field_type'] === 'date') {
                $html .= '<input type="date" name="dynamic_'.htmlspecialchars($row['field_name']).'" class="form-control" '.$requiredAttr.'>';
            } elseif ($row['field_type'] === 'number') {
                $html .= '<input type="number" name="dynamic_'.htmlspecialchars($row['field_name']).'" class="form-control" '.$requiredAttr.'>';
            } else {
                $html .= '<input type="text" name="dynamic_'.htmlspecialchars($row['field_name']).'" class="form-control" '.$requiredAttr.'>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }

    // Tutup div row
    $html .= '</div>';

    echo $html;
    exit;
} elseif ($action === 'save_visual_design') {
    $fields = $_POST['fields'] ?? [];
    if (empty($fields) || !is_array($fields)) {
        echo json_encode(['status' => 'error', 'message' => 'Data desain kosong atau tidak valid.']);
        exit;
    }

    sqlsrv_begin_transaction($conn);
    $sql = "UPDATE dr_fields SET sort_order = ?, grid_class = ? WHERE id = ?";
    foreach ($fields as $f) {
        $stmt = sqlsrv_query($conn, $sql, [(int)$f['sort_order'], $f['grid_class'], (int)$f['id']]);
        if (!$stmt) {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan desain form ke database.']);
            exit;
        }
    }
    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Desain form berhasil disimpan.']);
    exit;
} elseif ($action === 'save_document') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    if ($categoryId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Kategori tidak valid.']);
        exit;
    }

    $expireDate = trim($_POST['expire_date'] ?? '');
    $emailReminder = trim($_POST['email_reminder'] ?? '');
    $noWhatsapp = trim($_POST['no_whatsapp'] ?? '');
    $username = $_SESSION['UserName'] ?? 'system';
    
    // Hardcode bagian_id for now or fetch it from session if not passed
    $bagianId = $_POST['bagian_id'] ?? null;
    if (empty($bagianId)) {
        $stmtDept = sqlsrv_query($conn, "SELECT b.id_bag FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp JOIN dbo.m_bag b ON e.id_bag = b.id_bag WHERE u.UserName = ?", [$username]);
        if ($stmtDept && $rd = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
            $bagianId = $rd['id_bag'];
        }
    }

    if (empty($expireDate)) {
        echo json_encode(['status' => 'error', 'message' => 'Tanggal expire wajib diisi.']);
        exit;
    }

    // 1. Mulai Transaksi Database
    sqlsrv_begin_transaction($conn);

    // Get reminder_interval of the category
    $stmtCat = sqlsrv_query($conn, "SELECT reminder_interval FROM dr_categories WHERE id = ?", [$categoryId]);
    $rCat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC);
    $intervalDays = (int)($rCat['reminder_interval'] ?? 30);

    // Calculate status based on expireDate
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $newExpDate = new DateTime($expireDate);
    $newExpDate->setTime(0, 0, 0);
    
    $diff = $today->diff($newExpDate);
    $diffDays = (int)$diff->format("%r%a"); // include sign
    
    $newStatus = 'Aktif';
    if ($diffDays < 0) {
        $newStatus = 'Expired';
    } elseif ($diffDays <= $intervalDays) {
        $newStatus = 'Reminder';
    }

    // 2. Simpan ke dr_documents
    $sqlDoc = "INSERT INTO dr_documents (category_id, expire_date, status, bagian_id, created_by, email_reminder, no_whatsapp) 
               OUTPUT INSERTED.id 
               VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtDoc = sqlsrv_query($conn, $sqlDoc, [$categoryId, $expireDate, $newStatus, $bagianId, $username, $emailReminder, $noWhatsapp]);
    
    if (!$stmtDoc) {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan dokumen utama.', 'debug' => sqlsrv_errors()]);
        exit;
    }

    $rowDoc = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC);
    $documentId = $rowDoc['id'];

    // 3. Ambil konfigurasi dr_fields untuk mencocokkan inputan dinamis
    $stmtFields = sqlsrv_query($conn, "SELECT id, field_name FROM dr_fields WHERE category_id = ?", [$categoryId]);
    if ($stmtFields) {
        $sqlVal = "INSERT INTO dr_doc_values (document_id, field_id, field_value) VALUES (?, ?, ?)";
        while ($rf = sqlsrv_fetch_array($stmtFields, SQLSRV_FETCH_ASSOC)) {
            if (strpos($rf['field_name'], 'system_') === 0) continue;
            
            $fName = 'dynamic_' . $rf['field_name'];
            $fVal = isset($_POST[$fName]) ? trim($_POST[$fName]) : '';
            
            $stmtVal = sqlsrv_query($conn, $sqlVal, [$documentId, $rf['id'], $fVal]);
            if (!$stmtVal) {
                sqlsrv_rollback($conn);
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan nilai field dinamis.']);
                exit;
            }
        }
    }

    // 4. Proses Upload File (Multi-file)
    if (isset($_FILES['file_dokumen']) && !empty($_FILES['file_dokumen']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/dokumen_dinamis/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $files   = $_FILES['file_dokumen'];
        $count   = count($files['name']);
        $sqlFile = "INSERT INTO dr_doc_files (document_id, file_name, file_path) VALUES (?, ?, ?)";
        $uploadedCount = 0;
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                if ($files['error'][$i] == UPLOAD_ERR_INI_SIZE || $files['error'][$i] == UPLOAD_ERR_FORM_SIZE) {
                    sqlsrv_rollback($conn);
                    echo json_encode(['status' => 'error', 'message' => 'Ukuran file "' . htmlspecialchars(basename($files['name'][$i])) . '" terlalu besar.']);
                    exit;
                }
                continue;
            }
            
            $fileName   = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($files['name'][$i]));
            $uniqueName = time() . '_' . $i . '_' . $fileName;
            $dest       = $uploadDir . $uniqueName;
            
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                // Path relative to pages/dokumen_pengingat/
                $dbPath = 'uploads/dokumen_dinamis/' . $uniqueName;
                $stmtFile = sqlsrv_query($conn, $sqlFile, [$documentId, $uniqueName, $dbPath]);
                if (!$stmtFile) {
                    sqlsrv_rollback($conn);
                    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data file ke database.']);
                    exit;
                }
                $uploadedCount++;
            } else {
                sqlsrv_rollback($conn);
                echo json_encode(['status' => 'error', 'message' => 'Gagal memindahkan file yang diupload: ' . $fileName]);
                exit;
            }
        }
        
        if ($uploadedCount === 0) {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada file yang berhasil diupload. Coba periksa ukuran file Anda.']);
            exit;
        }
    } else {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'File dokumen wajib diupload.']);
        exit;
    }

    // 5. Commit Transaksi
    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Dokumen berhasil disimpan.']);
    exit;
} elseif ($action === 'delete_document') {
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
        exit;
    }

    // Ambil file path untuk dihapus fisik filenya
    $stmtFile = sqlsrv_query($conn, "SELECT file_path FROM dr_doc_files WHERE document_id = ?", [$id]);
    if ($stmtFile) {
        while ($rFile = sqlsrv_fetch_array($stmtFile, SQLSRV_FETCH_ASSOC)) {
            $path = __DIR__ . '/' . $rFile['file_path'];
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // Hapus data utama
    sqlsrv_query($conn, "DELETE FROM dr_doc_values WHERE document_id = ?", [$id]);
    sqlsrv_query($conn, "DELETE FROM dr_doc_files WHERE document_id = ?", [$id]);
    
    $stmtDel = sqlsrv_query($conn, "DELETE FROM dr_documents WHERE id = ?", [$id]);
    if ($stmtDel) {
        echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data.']);
    }
    exit;
} elseif ($action === 'get_detail') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = sqlsrv_query($conn, "SELECT d.*, c.category_name, b.bagian AS nama_bagian FROM dr_documents d JOIN dr_categories c ON d.category_id = c.id LEFT JOIN dbo.m_bag b ON d.bagian_id = b.id_bag WHERE d.id = ?$bagianFilterSql", array_merge([$id], $bagianFilterParams));
    $doc = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$doc) { echo "Data tidak ditemukan."; exit; }

    // Get values
    $values = [];
    $stmtV = sqlsrv_query($conn, "SELECT f.field_label, v.field_value FROM dr_doc_values v JOIN dr_fields f ON v.field_id = f.id WHERE v.document_id = ? ORDER BY f.sort_order ASC", [$id]);
    while($rv = sqlsrv_fetch_array($stmtV, SQLSRV_FETCH_ASSOC)) {
        $values[] = $rv;
    }

    // Get files
    $files = [];
    $stmtF = sqlsrv_query($conn, "SELECT * FROM dr_doc_files WHERE document_id = ?", [$id]);
    while($rf = sqlsrv_fetch_array($stmtF, SQLSRV_FETCH_ASSOC)) {
        $files[] = $rf;
    }

    // Render HTML Detail (Styled like hardcoded)
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-sm mb-0">';
    echo '<tr><th width="180" class="bg-light align-middle">Kategori</th><td class="align-middle">' . htmlspecialchars($doc['category_name']) . '</td></tr>';
    foreach ($values as $v) {
        echo '<tr><th class="bg-light align-middle">' . htmlspecialchars($v['field_label']) . '</th><td class="align-middle">' . nl2br(htmlspecialchars($v['field_value'])) . '</td></tr>';
    }
    echo '<tr><th class="bg-light align-middle">Expire</th><td class="align-middle">' . ($doc['expire_date'] ? $doc['expire_date']->format('d/m/Y') : '-') . '</td></tr>';
    $checkSF = sqlsrv_query($conn, "SELECT 1 FROM dr_fields WHERE category_id = ? AND field_name = 'system_bagian'", [$doc['category_id']]);
    if ($checkSF && sqlsrv_fetch_array($checkSF)) {
        echo '<tr><th class="bg-light align-middle">Bagian</th><td class="align-middle">' . htmlspecialchars($doc['nama_bagian'] ?? '-') . '</td></tr>';
    }
    echo '<tr><th class="bg-light align-middle">Email Reminder</th><td class="align-middle">' . htmlspecialchars($doc['email_reminder'] ?? '-') . '</td></tr>';
    echo '<tr><th class="bg-light align-middle">No WA</th><td class="align-middle">' . htmlspecialchars($doc['no_whatsapp'] ?? '-') . '</td></tr>';
    echo '<tr><th class="bg-light align-middle">Status</th><td class="align-middle">' . htmlspecialchars($doc['status']) . '</td></tr>';
    echo '<tr><th class="bg-light align-middle">Dokumen</th><td class="align-middle">';
    if (!empty($files)) {
        foreach ($files as $f) {
            echo '<div class="mb-2"><a href="javascript:void(0)" onclick="openPreview(\''.$f['file_path'].'\')" class="btn btn-sm btn-outline-primary shadow-sm"><i class="fas fa-file-pdf mr-1"></i> Lihat Dokumen</a></div>';
        }
    } else { echo '<span class="text-muted">Tidak ada file.</span>'; }
    echo '</td></tr>';
    echo '</table>';
    echo '</div>';

    // Cek riwayat perpanjangan
    $stmtHistory = sqlsrv_query($conn, "SELECT * FROM dr_document_renewals WHERE document_type = 'dynamic' AND document_id = ? ORDER BY created_at DESC", [$id]);
    $histories = [];
    if ($stmtHistory) {
        while($rh = sqlsrv_fetch_array($stmtHistory, SQLSRV_FETCH_ASSOC)) {
            $histories[] = $rh;
        }
    }
    
    if (count($histories) > 0) {
        echo '<div class="mt-4">';
        echo '<h6 class="font-weight-bold text-success"><i class="fas fa-history mr-1"></i> Riwayat Perpanjangan</h6>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered table-sm mb-0 text-center" style="font-size: 13px;">';
        echo '<thead class="bg-light"><tr><th>Tgl Perpanjangan</th><th>Oleh</th><th>Expire Lama</th><th>Expire Baru</th><th>Dokumen Lama</th><th>Catatan</th></tr></thead>';
        echo '<tbody>';
        foreach ($histories as $h) {
            $tglRenew = $h['created_at'] ? $h['created_at']->format('d/m/Y H:i') : '-';
            $oldExp = $h['old_expire_date'] ? $h['old_expire_date']->format('d/m/Y') : '-';
            $newExp = $h['new_expire_date'] ? $h['new_expire_date']->format('d/m/Y') : '-';
            $remarks = $h['remarks'] ? htmlspecialchars($h['remarks']) : '-';
            $creator = $h['created_by'] ? htmlspecialchars($h['created_by']) : '-';
            
            $oldFileLink = '-';
            if (!empty($h['old_file_path'])) {
                $paths = explode(',', $h['old_file_path']);
                $links = [];
                $dir = '';
                foreach ($paths as $idx => $p) {
                    $p = trim($p);
                    if ($p === '') continue;
                    
                    if (strpos($p, '/') === false && $dir !== '') {
                        $p = $dir . $p;
                    } else if ($idx === 0) {
                        $dir = dirname($p) . '/';
                    }
                    
                    $url = rawurlencode($p);
                    $url = str_replace('%2F', '/', $url);
                    $links[] = '<div class="mb-1"><a href="javascript:void(0)" onclick="openPreview(\''.$url.'\')" class="text-primary text-nowrap" style="font-size:12px;"><i class="fas fa-file-alt"></i> File '.($idx+1).'</a></div>';
                }
                $oldFileLink = implode('', $links);
            }

            echo '<tr>';
            echo '<td>' . $tglRenew . '</td>';
            echo '<td>' . $creator . '</td>';
            echo '<td class="text-danger"><del>' . $oldExp . '</del></td>';
            echo '<td class="text-success font-weight-bold">' . $newExp . '</td>';
            echo '<td>' . $oldFileLink . '</td>';
            echo '<td>' . $remarks . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    exit;
} elseif ($action === 'get_edit_form') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = sqlsrv_query($conn, "SELECT d.* FROM dr_documents d WHERE d.id = ?$bagianFilterSql", array_merge([$id], $bagianFilterParams));
    $doc = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$doc) { echo "Data tidak ditemukan."; exit; }

    $username = $_SESSION['UserName'] ?? 'system';

    $catId = $doc['category_id'];
    
    // Get current values
    $currValues = [];
    $stmtV = sqlsrv_query($conn, "SELECT field_id, field_value FROM dr_doc_values WHERE document_id = ?", [$id]);
    while($rv = sqlsrv_fetch_array($stmtV, SQLSRV_FETCH_ASSOC)) {
        $currValues[$rv['field_id']] = $rv['field_value'];
    }

    // Get dynamic fields
    $stmtFields = sqlsrv_query($conn, "SELECT * FROM dr_fields WHERE category_id = ? ORDER BY sort_order ASC", [$catId]);
    $html = '<div class="row">';
    while ($rf = sqlsrv_fetch_array($stmtFields, SQLSRV_FETCH_ASSOC)) {
        $val = $currValues[$rf['id']] ?? '';
        $gc = !empty($rf['grid_class']) ? $rf['grid_class'] : 'col-md-6';
        $html .= '<div class="' . htmlspecialchars($gc) . '">';
        $html .= '<div class="form-group">';
        
        if ($rf['field_name'] === 'system_bagian') {
            $html .= '<label class="font-weight-bold">Bagian <span class="text-danger">*</span></label>';
            $html .= '<select name="bagian_id" class="form-control" required>';
            $html .= '<option value="">-- Pilih Bagian --</option>';
            
            $idDeptData = $doc['bagian_id'] ?? null;
            $idDeptUser = null;
            $stmtDeptUser = sqlsrv_query($conn, "SELECT b.id_bag FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp JOIN dbo.m_bag b ON e.id_bag = b.id_bag WHERE u.UserName = ?", [$username]);
            if ($stmtDeptUser && $r = sqlsrv_fetch_array($stmtDeptUser, SQLSRV_FETCH_ASSOC)) {
                $idDeptUser = $r['id_bag'];
            }
            
            $deptIds = array_unique(array_filter([$idDeptUser, $idDeptData]));
            if ($deptIds) {
                $in = implode(',', array_fill(0, count($deptIds), '?'));
                $sqlBag = "SELECT id_bag, bagian FROM dbo.m_bag WHERE id_bag IN ($in) ORDER BY bagian";
                $stmtBag = sqlsrv_query($conn, $sqlBag, $deptIds);
                if ($stmtBag) {
                    while ($rb = sqlsrv_fetch_array($stmtBag, SQLSRV_FETCH_ASSOC)) {
                        $selected = ($rb['id_bag'] == $idDeptData) ? 'selected' : '';
                        $html .= '<option value="' . $rb['id_bag'] . '" ' . $selected . '>' . htmlspecialchars($rb['bagian']) . '</option>';
                    }
                }
            }
            $html .= '</select>';
        } elseif ($rf['field_name'] === 'system_expire_date') {
            $expDate = $doc['expire_date'] ? $doc['expire_date']->format('Y-m-d') : '';
            $html .= '<label class="font-weight-bold text-danger">Expire <span class="text-danger">*</span></label>';
            $html .= '<input type="date" name="expire_date" class="form-control border-danger" value="' . $expDate . '" required>';
        } elseif ($rf['field_name'] === 'system_file_dokumen') {
            $html .= '<label class="font-weight-bold">Tambah Dokumen Baru (PDF/JPG/PNG)</label>';
            $html .= '<input type="file" name="file_dokumen[]" class="form-control-file" accept="application/pdf,image/jpeg,image/png" multiple>';
            $html .= '<small class="text-muted">Kosongkan jika tidak ingin menambah file baru.</small>';
        } elseif ($rf['field_name'] === 'system_email_reminder') {
            $html .= '<label class="font-weight-bold">Email Reminder</label>';
            $html .= '<input type="email" name="email_reminder" class="form-control" value="' . htmlspecialchars($doc['email_reminder'] ?? '') . '">';
        } elseif ($rf['field_name'] === 'system_no_whatsapp') {
            $html .= '<label class="font-weight-bold">No WA</label>';
            $html .= '<input type="text" name="no_whatsapp" class="form-control" value="' . htmlspecialchars($doc['no_whatsapp'] ?? '') . '">';
        } else {
            $html .= '<label class="font-weight-bold">' . htmlspecialchars($rf['field_label']) . ($rf['is_required'] ? ' <span class="text-danger">*</span>' : '') . '</label>';
            $fName = 'dynamic_' . $rf['field_name'];
            if ($rf['field_type'] === 'textarea') {
                $html .= '<textarea name="' . $fName . '" class="form-control" ' . ($rf['is_required'] ? 'required' : '') . '>' . htmlspecialchars($val) . '</textarea>';
            } elseif ($rf['field_type'] === 'number') {
                $html .= '<input type="number" name="' . $fName . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . ($rf['is_required'] ? 'required' : '') . '>';
            } elseif ($rf['field_type'] === 'date') {
                $html .= '<input type="date" name="' . $fName . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . ($rf['is_required'] ? 'required' : '') . '>';
            } else {
                $html .= '<input type="text" name="' . $fName . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . ($rf['is_required'] ? 'required' : '') . '>';
            }
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    echo $html;
    exit;
} elseif ($action === 'update_document') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    if ($docId <= 0) { echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']); exit; }

    $expireDate = trim($_POST['expire_date'] ?? '');
    $emailReminder = trim($_POST['email_reminder'] ?? '');
    $noWhatsapp = trim($_POST['no_whatsapp'] ?? '');
    $username = $_SESSION['UserName'] ?? 'system';

    sqlsrv_begin_transaction($conn);

    // 1. Get reminder_interval & category first
    $stmtCat = sqlsrv_query($conn, "
        SELECT d.category_id, c.reminder_interval 
        FROM dr_documents d
        JOIN dr_categories c ON d.category_id = c.id
        WHERE d.id = ?
    ", [$docId]);
    $rCat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC);
    if (!$rCat) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Dokumen tidak ditemukan.']); exit; }
    $catId = (int)$rCat['category_id'];
    $intervalDays = (int)($rCat['reminder_interval'] ?? 30);

    // Calculate status based on new expireDate
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $newExpDate = new DateTime($expireDate);
    $newExpDate->setTime(0, 0, 0);
    
    $diff = $today->diff($newExpDate);
    $diffDays = (int)$diff->format("%r%a"); // include sign
    
    $newStatus = 'Aktif';
    if ($diffDays < 0) {
        $newStatus = 'Expired';
    } elseif ($diffDays <= $intervalDays) {
        $newStatus = 'Reminder';
    }

    // 2. Update dr_documents
    $bagianId = $_POST['bagian_id'] ?? null;
    if (empty($bagianId)) {
        $sqlDoc = "UPDATE dr_documents SET expire_date = ?, status = ?, email_reminder = ?, no_whatsapp = ?, updated_by = ?, updated_at = GETDATE() WHERE id = ?";
        $stmtDoc = sqlsrv_query($conn, $sqlDoc, [$expireDate, $newStatus, $emailReminder, $noWhatsapp, $username, $docId]);
    } else {
        $sqlDoc = "UPDATE dr_documents SET expire_date = ?, status = ?, email_reminder = ?, no_whatsapp = ?, bagian_id = ?, updated_by = ?, updated_at = GETDATE() WHERE id = ?";
        $stmtDoc = sqlsrv_query($conn, $sqlDoc, [$expireDate, $newStatus, $emailReminder, $noWhatsapp, $bagianId, $username, $docId]);
    }
    if (!$stmtDoc) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Gagal update data utama.']); exit; }

    $stmtFields = sqlsrv_query($conn, "SELECT id, field_name FROM dr_fields WHERE category_id = ?", [$catId]);
    while ($rf = sqlsrv_fetch_array($stmtFields, SQLSRV_FETCH_ASSOC)) {
        if (strpos($rf['field_name'], 'system_') === 0) continue;
        
        $fName = 'dynamic_' . $rf['field_name'];
        $fVal = isset($_POST[$fName]) ? trim($_POST[$fName]) : '';
        
        // Update or Insert? Better check if exists
        $check = sqlsrv_query($conn, "SELECT 1 FROM dr_doc_values WHERE document_id = ? AND field_id = ?", [$docId, $rf['id']]);
        if (sqlsrv_fetch_array($check)) {
            $sqlV = "UPDATE dr_doc_values SET field_value = ? WHERE document_id = ? AND field_id = ?";
            sqlsrv_query($conn, $sqlV, [$fVal, $docId, $rf['id']]);
        } else {
            $sqlV = "INSERT INTO dr_doc_values (document_id, field_id, field_value) VALUES (?, ?, ?)";
            sqlsrv_query($conn, $sqlV, [$docId, $rf['id'], $fVal]);
        }
    }

    // 3. Handle new files
    if (isset($_FILES['file_dokumen']) && !empty($_FILES['file_dokumen']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/dokumen_dinamis/';
        $files = $_FILES['file_dokumen'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $uniqueName = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($files['name'][$i]));
                if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $uniqueName)) {
                    sqlsrv_query($conn, "INSERT INTO dr_doc_files (document_id, file_name, file_path) VALUES (?, ?, ?)", [$docId, $uniqueName, 'uploads/dokumen_dinamis/' . $uniqueName]);
                }
            }
        }
    }

    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Dokumen berhasil diperbarui.']);
    exit;
} elseif ($action === 'global_search') {
    $keyword = trim($_POST['keyword'] ?? '');
    if (strlen($keyword) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Kata kunci terlalu pendek.']);
        exit;
    }

    $results = [];
    $likeKeyword = '%' . $keyword . '%';

    // Search globally across all dynamic dynamic categories, fields, and values
    $sqlDyn = "
        SELECT DISTINCT d.id, d.expire_date, c.category_name, c.color
        FROM dr_documents d
        JOIN dr_categories c ON d.category_id = c.id
        LEFT JOIN dr_doc_values v ON d.id = v.document_id
        WHERE (v.field_value LIKE ? OR c.category_name LIKE ?)$bagianFilterSql
    ";
    $stmtDyn = sqlsrv_query($conn, $sqlDyn, array_merge([$likeKeyword, $likeKeyword], $bagianFilterParams));
    if ($stmtDyn) {
        while ($row = sqlsrv_fetch_array($stmtDyn, SQLSRV_FETCH_ASSOC)) {
            $infoValues = [];
            $stmtVal = sqlsrv_query($conn, "SELECT field_value FROM dr_doc_values v JOIN dr_fields f ON v.field_id = f.id WHERE v.document_id = ? ORDER BY f.sort_order ASC", [$row['id']]);
            while($valRow = sqlsrv_fetch_array($stmtVal, SQLSRV_FETCH_ASSOC)) {
                $infoValues[] = $valRow['field_value'];
            }
            
            $results[] = [
                'id' => $row['id'],
                'kategori' => $row['category_name'],
                'identitas' => isset($infoValues[0]) ? $infoValues[0] : 'Dokumen #' . $row['id'],
                'info' => isset($infoValues[1]) ? $infoValues[1] : '-',
                'expire' => ($row['expire_date'] instanceof DateTimeInterface) ? $row['expire_date']->format('Y-m-d') : '-',
                'btn_class' => 'btn-detail-dynamic',
                'color' => $row['color']
            ];
        }
    }

    echo json_encode(['status' => 'success', 'data' => $results]);
    exit;
} elseif ($action === 'toggle_field_status') {
    $id = (int)($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = (int)($_POST['value'] ?? 0);

    if ($id <= 0 || !in_array($field, ['is_required', 'is_show_on_table'])) {
        echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid.']);
        exit;
    }

    $sql = "UPDATE dr_fields SET $field = ? WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$value, $id]);

    if ($stmt) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status di database.']);
    }
    exit;
} elseif ($action === 'renew_document') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    $docType = $_POST['document_type'] ?? 'dynamic';
    $newExpire = trim($_POST['new_expire_date'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $username = $_SESSION['UserName'] ?? 'system';

    if ($docId <= 0 || empty($newExpire)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
        exit;
    }

    sqlsrv_begin_transaction($conn);

    if ($docType === 'dynamic') {
        $stmtOld = sqlsrv_query($conn, "
            SELECT d.expire_date, c.reminder_interval 
            FROM dr_documents d
            JOIN dr_categories c ON d.category_id = c.id
            WHERE d.id = ?$bagianFilterSql
        ", array_merge([$docId], $bagianFilterParams));
        $rOld = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC);
        if (!$rOld) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Dokumen tidak ditemukan.']); exit; }
        
        $oldExpire = $rOld['expire_date'] ? $rOld['expire_date']->format('Y-m-d') : null;
        $intervalDays = (int)($rOld['reminder_interval'] ?? 30);
        
        $stmtFileOld = sqlsrv_query($conn, "SELECT file_path FROM dr_doc_files WHERE document_id = ?", [$docId]);
        $oldPaths = [];
        while ($rFileOld = sqlsrv_fetch_array($stmtFileOld, SQLSRV_FETCH_ASSOC)) {
            $oldPaths[] = $rFileOld['file_path'];
        }
        $oldFilePath = !empty($oldPaths) ? implode(',', $oldPaths) : null;
        
        // Simpan Riwayat
        $sqlLog = "INSERT INTO dr_document_renewals (document_type, document_id, old_expire_date, new_expire_date, old_file_path, remarks, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
        $stmtLog = sqlsrv_query($conn, $sqlLog, [$docType, $docId, $oldExpire, $newExpire, $oldFilePath, $remarks, $username]);
        if (!$stmtLog) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan riwayat perpanjangan.']); exit; }

        // Proses unggahan berkas baru (Mendukung multi-file)
        if (isset($_FILES['new_file']) && !empty($_FILES['new_file']['name'][0])) {
            $files = $_FILES['new_file'];
            $count = count($files['name']);
            
            // Bersihkan file lama dari daftar utama (file fisik tetap ada untuk histori)
            sqlsrv_query($conn, "DELETE FROM dr_doc_files WHERE document_id = ?", [$docId]);
            $sqlFile = "INSERT INTO dr_doc_files (document_id, file_path, file_name) VALUES (?, ?, ?)";
            
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($files['size'][$i] > 5 * 1024 * 1024) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Ukuran file ' . $files['name'][$i] . ' melebihi 5MB.']); exit; }
                
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Format file ' . $files['name'][$i] . ' tidak didukung.']); exit; }
                
                $fileName = uniqid('rnw_') . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $files['name'][$i]);
                $dest = $uploadDir . $fileName;
                
                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $dbFilePath = 'uploads/' . $fileName;
                    $stmtFile = sqlsrv_query($conn, $sqlFile, [$docId, $dbFilePath, $files['name'][$i]]);
                    if (!$stmtFile) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file perpanjangan.']); exit; }
                }
            }
        }

        // Calculate new status based on newExpire
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $newExpDate = new DateTime($newExpire);
        $newExpDate->setTime(0, 0, 0);
        
        $diff = $today->diff($newExpDate);
        $diffDays = (int)$diff->format("%r%a"); // include sign
        
        $newStatus = 'Aktif';
        if ($diffDays < 0) {
            $newStatus = 'Expired';
        } elseif ($diffDays <= $intervalDays) {
            $newStatus = 'Reminder';
        }

        // Perbarui tanggal expire di parent
        $sqlUpdate = "UPDATE dr_documents SET expire_date = ?, status = ?, updated_by = ?, updated_at = GETDATE() WHERE id = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$newExpire, $newStatus, $username, $docId]);
        if (!$stmtUpdate) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui tanggal expire.']); exit; }

        sqlsrv_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Dokumen berhasil diperpanjang.']);
        exit;
    } else {
        // Kategori Legacy (kendaraan, sertifikat, kontrak)
        $legacyMap = [
            'kendaraan' => ['table' => 'dr_surat_kendaraan', 'file_col' => 'file_kendaraan', 'dir' => 'kendaraan/uploads/kendaraan/', 'upd_col' => 'update_at'],
            'sertifikat' => ['table' => 'dr_sertifikat', 'file_col' => 'file_path', 'dir' => 'sertifikat/uploads/sertifikat/', 'upd_col' => 'updatedate'],
            'kontrak' => ['table' => 'dr_kontrak', 'file_col' => 'file_path', 'dir' => 'kontrak/uploads/kontrak/', 'upd_col' => 'updatedate']
        ];
        
        if (!array_key_exists($docType, $legacyMap)) {
            sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Tipe dokumen tidak valid.']); exit;
        }
        
        $cfg = $legacyMap[$docType];
        $tbl = $cfg['table'];
        $fcol = $cfg['file_col'];
        $fdir = $cfg['dir'];
        $updCol = $cfg['upd_col'];
        
        $stmtOld = sqlsrv_query($conn, "SELECT expire_date, $fcol FROM $tbl WHERE id = ?", [$docId]);
        $rOld = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC);
        if (!$rOld) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Dokumen tidak ditemukan.']); exit; }
        
        $oldExpire = $rOld['expire_date'] ? $rOld['expire_date']->format('Y-m-d') : null;
        $oldFilePath = null;
        if (!empty($rOld[$fcol])) {
            $filesArr = explode(',', $rOld[$fcol]);
            $pathsArr = array_map(function($f) use ($fdir) { return $fdir . trim($f); }, $filesArr);
            $oldFilePath = implode(',', $pathsArr);
        }
        
        // Simpan Riwayat
        $sqlLog = "INSERT INTO dr_document_renewals (document_type, document_id, old_expire_date, new_expire_date, old_file_path, remarks, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
        $stmtLog = sqlsrv_query($conn, $sqlLog, [$docType, $docId, $oldExpire, $newExpire, $oldFilePath, $remarks, $username]);
        if (!$stmtLog) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan riwayat perpanjangan.']); exit; }
        
        $newFileDb = $rOld[$fcol]; // Default ke file lama jika tidak ada upload baru
        
        // Proses unggahan berkas baru (Multi-file legacy)
        if (isset($_FILES['new_file']) && !empty($_FILES['new_file']['name'][0])) {
            $files = $_FILES['new_file'];
            $count = count($files['name']);
            
            $uploadDir = __DIR__ . '/' . $fdir;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $uploadedFiles = [];
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($files['size'][$i] > 5 * 1024 * 1024) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Ukuran file ' . $files['name'][$i] . ' melebihi 5MB.']); exit; }
                
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Format file ' . $files['name'][$i] . ' tidak didukung.']); exit; }
                
                $fileName = uniqid('rnw_') . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $files['name'][$i]);
                $dest = $uploadDir . $fileName;
                
                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $uploadedFiles[] = $fileName;
                }
            }
            if (!empty($uploadedFiles)) {
                $newFileDb = implode(',', $uploadedFiles);
            }
        }
        
        // Perbarui tanggal expire di tabel parent
        $sqlUpdate = "UPDATE $tbl SET expire_date = ?, updated_by = ?, $updCol = GETDATE(), $fcol = ? WHERE id = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$newExpire, $username, $newFileDb, $docId]);
        if (!$stmtUpdate) { sqlsrv_rollback($conn); echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui dokumen parent.']); exit; }

        sqlsrv_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Dokumen '.ucfirst($docType).' berhasil diperpanjang.']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali.']);
exit;
?>
