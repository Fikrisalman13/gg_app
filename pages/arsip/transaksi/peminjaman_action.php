<?php
header('Content-Type: application/json');
session_start();
include('../../../koneksi.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login kembali.']);
    exit;
}

$action = $_POST['action'] ?? '';
$user = $_SESSION['UserName'];

// Helper function for safe JSON encoding
function safe_json($val) {
    if (is_string($val)) {
        return mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1, UTF-8, ASCII');
    }
    return $val;
}

switch ($action) {
    case 'add_peminjaman':
        $nama = $_POST['nama_peminjam'] ?? '';
        $tgl_pinjam = $_POST['tgl_pinjam'] ?? date('Y-m-d');
        $tgl_kembali_rencana = $_POST['tgl_kembali_rencana'] ?? null;
        $keterangan = $_POST['keterangan'] ?? '';
        $id_arsip = $_POST['id_arsip'] ?? '';

        if (empty($id_arsip)) {
            echo json_encode(['status' => 'error', 'message' => 'Silakan pilih setidaknya satu arsip.']);
            exit;
        }

        sqlsrv_begin_transaction($conn);
        $success = true;
        $errors = [];

        // Insert Peminjaman
        $sql1 = "INSERT INTO arsip_peminjaman (nama_peminjam, tgl_pinjam, tgl_kembali_rencana, status, keterangan, CreatedBy) OUTPUT INSERTED.id_peminjaman VALUES (?, ?, ?, 'Dipinjam', ?, ?)";
        $stmt1 = sqlsrv_query($conn, $sql1, [$nama, $tgl_pinjam, $tgl_kembali_rencana, $keterangan, $user]);
        
        // Get inserted ID
        $id_pinjam = null;
        if ($stmt1 && $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC)) {
            $id_pinjam = $row['id_peminjaman'];
        } else {
            $success = false;
            $errs = sqlsrv_errors();
            $errors[] = ["message" => "Gagal menyimpan data utama.", "db_error" => $errs];
        }

        // Insert Details
        if ($success && $id_pinjam) {
            $ids = is_array($id_arsip) ? $id_arsip : [$id_arsip];
            foreach ($ids as $aid) {
                if (empty($aid)) continue;
                
                // Check stock for each
                $stmtCheck = sqlsrv_query($conn, "SELECT stok FROM arsip_data WHERE id_arsip = ?", [$aid]);
                $sRow = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
                
                if (!$sRow) {
                    $success = false;
                    $errors[] = ["message" => "Arsip dengan ID $aid tidak ditemukan."];
                    break;
                }
                
                if ($sRow['stok'] <= 0) {
                    $success = false;
                    $errors[] = ["message" => "Stok untuk ID $aid tidak mencukupi."];
                    break;
                }

                // Insert Detail
                $sql2 = "INSERT INTO arsip_peminjaman_detail (id_peminjaman, id_arsip, CreatedBy) VALUES (?, ?, ?)";
                $stmt2 = sqlsrv_query($conn, $sql2, [$id_pinjam, $aid, $user]);
                
                // Reduce Stock
                $sql3 = "UPDATE arsip_data SET stok = stok - 1 WHERE id_arsip = ?";
                $stmt3 = sqlsrv_query($conn, $sql3, [$aid]);

                if (!$stmt2 || !$stmt3) {
                    $success = false;
                    $errors[] = ["message" => "Gagal menyimpan detail atau update stok.", "db_error" => sqlsrv_errors()];
                    break;
                }
            }
        }

        if ($success && $id_pinjam) {
            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Peminjaman berhasil dicatat.']);
        } else {
            sqlsrv_rollback($conn);
            $msg = count($errors) > 0 ? $errors[0]['message'] : 'Gagal mencatat peminjaman.';
            echo json_encode(['status' => 'error', 'message' => $msg, 'debug' => $errors]);
        }
        break;

    case 'kembalikan':
        $id = $_POST['id'] ?? '';
        $tgl_kembali = date('Y-m-d');

        sqlsrv_begin_transaction($conn);

        // Fetch tgl_kembali_rencana to calculate late days
        $sqlLate = "SELECT tgl_kembali_rencana FROM arsip_peminjaman WHERE id_peminjaman = ?";
        $stmtLate = sqlsrv_query($conn, $sqlLate, [$id]);
        $late_days = 0;
        if ($stmtLate && $rowLate = sqlsrv_fetch_array($stmtLate, SQLSRV_FETCH_ASSOC)) {
            $rencana = $rowLate['tgl_kembali_rencana'];
            if ($rencana) {
                $date_rencana = new DateTime($rencana->format('Y-m-d'));
                $date_actual = new DateTime($tgl_kembali);
                if ($date_actual > $date_rencana) {
                    $diff = $date_actual->diff($date_rencana);
                    $late_days = $diff->days;
                }
            }
        }

        // Update Status
        $sql1 = "UPDATE arsip_peminjaman SET status = 'Kembali', tgl_kembali = ?, late_days = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_peminjaman = ?";
        $stmt1 = sqlsrv_query($conn, $sql1, [$tgl_kembali, $late_days, $user, $id]);

        // Increase Stock
        $sql2 = "SELECT id_arsip FROM arsip_peminjaman_detail WHERE id_peminjaman = ?";
        $stmt2 = sqlsrv_query($conn, $sql2, [$id]);
        $successStock = true;
        while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            $sqlUp = "UPDATE arsip_data SET stok = stok + 1 WHERE id_arsip = ?";
            if (!sqlsrv_query($conn, $sqlUp, [$row['id_arsip']])) {
                $successStock = false;
                break;
            }
        }

        if ($stmt1 && $successStock) {
            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Arsip berhasil dikembalikan.']);
        } else {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengembalikan arsip.']);
        }
        break;

    case 'delete_peminjaman':
        $id = $_POST['id'] ?? '';
        
        // If status is still 'Dipinjam', increase stock back before deleting
        $sqlStatus = "SELECT status FROM arsip_peminjaman WHERE id_peminjaman = ?";
        $stmtStatus = sqlsrv_query($conn, $sqlStatus, [$id]);
        $status = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC)['status'];

        sqlsrv_begin_transaction($conn);

        if ($status == 'Dipinjam') {
            $sqlD = "SELECT id_arsip FROM arsip_peminjaman_detail WHERE id_peminjaman = ?";
            $stmtD = sqlsrv_query($conn, $sqlD, [$id]);
            while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
                sqlsrv_query($conn, "UPDATE arsip_data SET stok = stok + 1 WHERE id_arsip = ?", [$row['id_arsip']]);
            }
        }

        sqlsrv_query($conn, "DELETE FROM arsip_peminjaman_detail WHERE id_peminjaman = ?", [$id]);
        $stmtFinal = sqlsrv_query($conn, "DELETE FROM arsip_peminjaman WHERE id_peminjaman = ?", [$id]);

        if ($stmtFinal) {
            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Data peminjaman berhasil dihapus.']);
        } else {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data peminjaman.']);
        }
        break;
        
    case 'get_arsip_list':
        $sql = "SELECT id_arsip, kode_arsip, judul_arsip, stok FROM arsip_data WHERE stok > 0 ORDER BY judul_arsip ASC";
        $stmt = sqlsrv_query($conn, $sql);
        $data = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = [
                    'id_arsip' => $row['id_arsip'],
                    'kode' => $row['kode_arsip'],
                    'judul' => $row['judul_arsip'],
                    'stok' => $row['stok'],
                    'text' => "{$row['judul_arsip']} (Stok: {$row['stok']})"
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data arsip.']);
        }
        break;

    case 'get_edit_data':
        $id = $_POST['id'] ?? '';
        $sql = "SELECT * FROM arsip_peminjaman WHERE id_peminjaman = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['tgl_pinjam'] = $row['tgl_pinjam'] ? $row['tgl_pinjam']->format('Y-m-d') : '';
            $row['tgl_kembali_rencana'] = $row['tgl_kembali_rencana'] ? $row['tgl_kembali_rencana']->format('Y-m-d') : '';
            
            // Current Items
            $sqlItems = "SELECT a.id_arsip, a.judul_arsip, a.kode_arsip, a.stok FROM arsip_peminjaman_detail d JOIN arsip_data a ON d.id_arsip = a.id_arsip WHERE d.id_peminjaman = ?";
            $stmtItems = sqlsrv_query($conn, $sqlItems, [$id]);
            $items = [];
            while ($i = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                $items[] = $i;
            }
            $row['items'] = $items;
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
        }
        break;

    case 'edit_peminjaman':
        $id = $_POST['id_peminjaman'] ?? '';
        $nama = $_POST['nama_peminjam'] ?? '';
        $tgl_pinjam = $_POST['tgl_pinjam'] ?? date('Y-m-d');
        $tgl_kembali_rencana = $_POST['tgl_kembali_rencana'] ?? null;
        $keterangan = $_POST['keterangan'] ?? '';
        $new_ids = $_POST['id_arsip'] ?? []; // Array from table hidden inputs

        sqlsrv_begin_transaction($conn);
        $success = true;
        $errors = [];

        // 1. Update Base Info
        $sql1 = "UPDATE arsip_peminjaman SET nama_peminjam = ?, tgl_pinjam = ?, tgl_kembali_rencana = ?, keterangan = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_peminjaman = ?";
        $stmt1 = sqlsrv_query($conn, $sql1, [$nama, $tgl_pinjam, $tgl_kembali_rencana, $keterangan, $user, $id]);
        if (!$stmt1) {
            $success = false;
            $errors[] = ["message" => "Gagal update data utama.", "db_error" => sqlsrv_errors()];
        }

        if ($success) {
            // 2. Get Old IDs
            $old_ids = [];
            $sqlOld = "SELECT id_arsip FROM arsip_peminjaman_detail WHERE id_peminjaman = ?";
            $stmtOld = sqlsrv_query($conn, $sqlOld, [$id]);
            while ($oRow = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC)) {
                $old_ids[] = (string)$oRow['id_arsip'];
            }

            // 3. To DELETE (In Old, not in New)
            $to_delete = array_diff($old_ids, $new_ids);
            foreach ($to_delete as $did) {
                // Remove from detail
                sqlsrv_query($conn, "DELETE FROM arsip_peminjaman_detail WHERE id_peminjaman = ? AND id_arsip = ?", [$id, $did]);
                // Restore stock
                sqlsrv_query($conn, "UPDATE arsip_data SET stok = stok + 1 WHERE id_arsip = ?", [$did]);
            }

            // 4. To ADD (In New, not in Old)
            $to_add = array_diff($new_ids, $old_ids);
            foreach ($to_add as $aid) {
                // Check stock
                $sCheck = sqlsrv_query($conn, "SELECT stok FROM arsip_data WHERE id_arsip = ?", [$aid]);
                $sRow = sqlsrv_fetch_array($sCheck, SQLSRV_FETCH_ASSOC);
                if (!$sRow || $sRow['stok'] <= 0) {
                    $success = false;
                    $errors[] = ["message" => "Stok untuk ID $aid tidak mencukupi untuk item baru."];
                    break;
                }

                // Add to detail
                sqlsrv_query($conn, "INSERT INTO arsip_peminjaman_detail (id_peminjaman, id_arsip, CreatedBy) VALUES (?, ?, ?)", [$id, $aid, $user]);
                // Reduce stock
                sqlsrv_query($conn, "UPDATE arsip_data SET stok = stok - 1 WHERE id_arsip = ?", [$aid]);
            }
        }

        if ($success) {
            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Peminjaman berhasil diperbarui.']);
        } else {
            sqlsrv_rollback($conn);
            $msg = count($errors) > 0 ? $errors[0]['message'] : 'Gagal memperbarui peminjaman.';
            echo json_encode(['status' => 'error', 'message' => $msg, 'debug' => $errors]);
        }
        break;

    case 'get_employee_list':
        $sql = "SELECT id_emp, ISNULL(nama_lengkap, nik) AS FullName FROM dbo.m_emp WHERE ISNULL(aktif,0) = 1 ORDER BY FullName";
        $stmt = sqlsrv_query($conn, $sql);
        $data = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = [
                    'id' => $row['FullName'], // Using name as ID to minimize changes elsewhere, but ID is better
                    'text' => $row['FullName']
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data karyawan.']);
        }
        break;

    case 'get_peminjaman_detail':
        $id = $_POST['id'] ?? '';
        // Base Info
        $sql = "SELECT * FROM arsip_peminjaman WHERE id_peminjaman = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['tgl_pinjam_fmt'] = $row['tgl_pinjam'] ? $row['tgl_pinjam']->format('d F Y') : '-';
            $row['tgl_kembali_rencana_fmt'] = $row['tgl_kembali_rencana'] ? $row['tgl_kembali_rencana']->format('d F Y') : '-';
            $row['tgl_kembali_fmt'] = $row['tgl_kembali'] ? $row['tgl_kembali']->format('d F Y') : '-';
            $row['CreatedAt_fmt'] = $row['CreatedAt'] ? $row['CreatedAt']->format('d/m/Y H:i') : '-';
            
            // Items
            $sqlItems = "SELECT a.judul_arsip, a.kode_arsip, k.nama_kategori, r.nama_rak 
                        FROM arsip_peminjaman_detail d
                        JOIN arsip_data a ON d.id_arsip = a.id_arsip
                        LEFT JOIN arsip_kategori k ON a.id_kategori = k.id_kategori
                        LEFT JOIN arsip_rak r ON a.id_rak = r.id_rak
                        WHERE d.id_peminjaman = ?";
            $stmtItems = sqlsrv_query($conn, $sqlItems, [$id]);
            $items = [];
            while ($i = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                // Apply safe_json to item details
                $items[] = array_map('safe_json', $i);
            }
            $row['items'] = $items;
            
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Detail peminjaman tidak ditemukan.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
        break;
}
?>
