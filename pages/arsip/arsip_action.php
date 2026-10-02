<?php
header('Content-Type: application/json');
session_start();
include('../../koneksi.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login kembali.']);
    exit;
}

$action = $_POST['action'] ?? '';
$user = $_SESSION['UserName'];

switch ($action) {
    case 'add_arsip':
        $kode = $_POST['kode_arsip'] ?? '';
        $judul = $_POST['judul_arsip'] ?? '';
        $id_kat = $_POST['id_kategori'] ?? null;
        $id_rak = $_POST['id_rak'] ?? null;
        $id_pen = $_POST['id_penerbit'] ?? null;
        $id_sus = $_POST['id_penyusun'] ?? null;
        $tahun = $_POST['tahun_terbit'] ?? date('Y');
        $stok = $_POST['stok'] ?? 1;
        $ket = $_POST['keterangan'] ?? '';
        $cover = null;

        // Handle Upload (Optional)
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../../uploads/arsip/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            $fileExt = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
            $fileName = $kode . "_" . time() . "." . $fileExt;
            $targetFile = $targetDir . $fileName;
            
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $targetFile)) {
                $cover = "uploads/arsip/" . $fileName;
            }
        }

        $sql = "INSERT INTO arsip_data (kode_arsip, judul_arsip, id_kategori, id_rak, id_penerbit, id_penyusun, tahun_terbit, stok, stok_total, cover, keterangan, CreatedBy) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$kode, $judul, $id_kat, $id_rak, $id_pen, $id_sus, $tahun, $stok, $stok, $cover, $ket, $user];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Arsip berhasil ditambahkan.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan arsip.', 'errors' => sqlsrv_errors()]);
        }
        break;

    case 'edit_arsip':
        $id = $_POST['id_arsip'] ?? '';
        $judul = $_POST['judul_arsip'] ?? '';
        $id_kat = $_POST['id_kategori'] ?? null;
        $id_rak = $_POST['id_rak'] ?? null;
        $id_pen = $_POST['id_penerbit'] ?? null;
        $id_sus = $_POST['id_penyusun'] ?? null;
        $tahun = $_POST['tahun_terbit'] ?? date('Y');
        $stok_total = $_POST['stok'] ?? 1;
        $ket = $_POST['keterangan'] ?? '';

        // Handle Cover Upload (Optional)
        $sqlCoverPart = "";
        $params = [$judul, $id_kat, $id_rak, $id_pen, $id_sus, $tahun, $stok_total, $ket];
        
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../../uploads/arsip/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            // Delete old cover if exists
            $stmtOld = sqlsrv_query($conn, "SELECT cover FROM arsip_data WHERE id_arsip = ?", [$id]);
            if ($rowOld = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC)) {
                if ($rowOld['cover'] && file_exists("../../" . $rowOld['cover'])) {
                    unlink("../../" . $rowOld['cover']);
                }
            }

            $fileExt = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
            $fileName = "edit_" . $id . "_" . time() . "." . $fileExt;
            $targetFile = $targetDir . $fileName;
            
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $targetFile)) {
                $sqlCoverPart = ", cover = ?";
                $params[] = "uploads/arsip/" . $fileName;
            }
        }

        $params[] = $user;
        $params[] = $id;

        // When updating stok_total, we should also update stok?
        // User might be updating the total physical count.
        // Let's assume stok (available) = old_stok + (new_total - old_total)
        $stmtOld = sqlsrv_query($conn, "SELECT stok, stok_total FROM arsip_data WHERE id_arsip = ?", [$id]);
        $rowOld = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC);
        $diff = $stok_total - $rowOld['stok_total'];
        $new_stok_available = $rowOld['stok'] + $diff;

        // Add new_stok_available to params for update
        // Current params: [judul, kat, rak, pen, sus, tahun, stok_total, ket, ...cover, user, id]
        // We need to inject it correctly based on the SQL structure.
        
        $sql = "UPDATE arsip_data SET judul_arsip = ?, id_kategori = ?, id_rak = ?, id_penerbit = ?, id_penyusun = ?, tahun_terbit = ?, stok_total = ?, keterangan = ?, stok = $new_stok_available $sqlCoverPart, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_arsip = ?";
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Arsip berhasil diubah.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah arsip.', 'errors' => sqlsrv_errors()]);
        }
        break;

    case 'delete_arsip':
        $id = $_POST['id'] ?? '';
        
        // Optionally delete the cover file if exists
        $sqlSelect = "SELECT cover FROM arsip_data WHERE id_arsip = ?";
        $stmtSelect = sqlsrv_query($conn, $sqlSelect, [$id]);
        if ($row = sqlsrv_fetch_array($stmtSelect, SQLSRV_FETCH_ASSOC)) {
            if ($row['cover'] && file_exists("../../" . $row['cover'])) {
                unlink("../../" . $row['cover']);
            }
        }

        $sql = "DELETE FROM arsip_data WHERE id_arsip = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Arsip berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus arsip.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
        break;
}
?>
