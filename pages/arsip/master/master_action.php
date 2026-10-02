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

switch ($action) {
    case 'get_kategori_list':
        $sql = "SELECT id_kategori, nama_kategori FROM arsip_kategori ORDER BY nama_kategori ASC";
        $stmt = sqlsrv_query($conn, $sql);
        $data = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data kategori.']);
        }
        break;

    case 'get_penerbit_list':
        $sql = "SELECT id_penerbit, nama_penerbit FROM arsip_penerbit ORDER BY nama_penerbit ASC";
        $stmt = sqlsrv_query($conn, $sql);
        $data = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $row;
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data penerbit.']);
        break;

    case 'get_penyusun_list':
        $sql = "SELECT id_penyusun, nama_penyusun FROM arsip_penyusun ORDER BY nama_penyusun ASC";
        $stmt = sqlsrv_query($conn, $sql);
        $data = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $row;
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data penyusun.']);
        break;

    case 'get_rak_by_kategori':
        $id_kategori = $_POST['id_kategori'] ?? '';
        $sql = "SELECT id_rak, nama_rak FROM arsip_rak WHERE id_kategori = ? ORDER BY nama_rak ASC";
        $stmt = sqlsrv_query($conn, $sql, [$id_kategori]);
        $data = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $row;
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data rak.']);
        break;

    case 'add_kategori':
        $nama = $_POST['nama_kategori'] ?? '';
        $sql = "INSERT INTO arsip_kategori (nama_kategori, CreatedBy) VALUES (?, ?)";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $user]);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Kategori berhasil ditambahkan.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan kategori.', 'errors' => sqlsrv_errors()]);
        }
        break;

    case 'edit_kategori':
        $id = $_POST['id_kategori'] ?? '';
        $nama = $_POST['nama_kategori'] ?? '';
        $sql = "UPDATE arsip_kategori SET nama_kategori = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_kategori = ?";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $user, $id]);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Kategori berhasil diubah.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah kategori.']);
        }
        break;

    case 'delete_kategori':
        $id = $_POST['id'] ?? '';
        $sql = "DELETE FROM arsip_kategori WHERE id_kategori = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Kategori berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus kategori. Mungkin data masih digunakan di tabel lain.']);
        }
        break;

    case 'add_rak':
        $nama = $_POST['nama_rak'] ?? '';
        $id_kategori = $_POST['id_kategori'] ?? null;
        $sql = "INSERT INTO arsip_rak (nama_rak, id_kategori, CreatedBy) VALUES (?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $id_kategori, $user]);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Rak berhasil ditambahkan.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan rak.']);
        }
        break;

    case 'edit_rak':
        $id = $_POST['id_rak'] ?? '';
        $nama = $_POST['nama_rak'] ?? '';
        $id_kategori = $_POST['id_kategori'] ?? null;
        $sql = "UPDATE arsip_rak SET nama_rak = ?, id_kategori = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_rak = ?";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $id_kategori, $user, $id]);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Rak berhasil diubah.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah rak.']);
        }
        break;

    case 'delete_rak':
        $id = $_POST['id'] ?? '';
        $sql = "DELETE FROM arsip_rak WHERE id_rak = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Rak berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus rak.']);
        }
        break;

    // Cases for Penerbit, Instansi, Penyusun follow the same pattern...
    case 'add_penerbit':
        $nama = $_POST['nama_penerbit'] ?? '';
        $ket = $_POST['keterangan'] ?? '';
        $sql = "INSERT INTO arsip_penerbit (nama_penerbit, keterangan, CreatedBy) VALUES (?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $ket, $user]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Penerbit berhasil ditambahkan.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan penerbit.']);
        break;

    case 'edit_penerbit':
        $id = $_POST['id_penerbit'] ?? '';
        $nama = $_POST['nama_penerbit'] ?? '';
        $ket = $_POST['keterangan'] ?? '';
        $sql = "UPDATE arsip_penerbit SET nama_penerbit = ?, keterangan = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_penerbit = ?";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $ket, $user, $id]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Penerbit berhasil diubah.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah penerbit.']);
        break;

    case 'delete_penerbit':
        $id = $_POST['id'] ?? '';
        $sql = "DELETE FROM arsip_penerbit WHERE id_penerbit = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Penerbit berhasil dihapus.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus penerbit.']);
        break;

    case 'add_instansi':
        $nama = $_POST['nama_instansi'] ?? '';
        $kota = $_POST['kota'] ?? '';
        $sql = "INSERT INTO arsip_instansi (nama_instansi, kota, CreatedBy) VALUES (?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $kota, $user]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Instansi berhasil ditambahkan.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan instansi.']);
        break;

    case 'edit_instansi':
        $id = $_POST['id_instansi'] ?? '';
        $nama = $_POST['nama_instansi'] ?? '';
        $kota = $_POST['kota'] ?? '';
        $sql = "UPDATE arsip_instansi SET nama_instansi = ?, kota = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_instansi = ?";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $kota, $user, $id]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Instansi berhasil diubah.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah instansi.']);
        break;

    case 'delete_instansi':
        $id = $_POST['id'] ?? '';
        $sql = "DELETE FROM arsip_instansi WHERE id_instansi = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Instansi berhasil dihapus.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus instansi.']);
        break;

    case 'add_penyusun':
        $nama = $_POST['nama_penyusun'] ?? '';
        $ket = $_POST['keterangan'] ?? '';
        $sql = "INSERT INTO arsip_penyusun (nama_penyusun, keterangan, CreatedBy) VALUES (?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $ket, $user]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Penyusun berhasil ditambahkan.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan penyusun.']);
        break;

    case 'edit_penyusun':
        $id = $_POST['id_penyusun'] ?? '';
        $nama = $_POST['nama_penyusun'] ?? '';
        $ket = $_POST['keterangan'] ?? '';
        $sql = "UPDATE arsip_penyusun SET nama_penyusun = ?, keterangan = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE id_penyusun = ?";
        $stmt = sqlsrv_query($conn, $sql, [$nama, $ket, $user, $id]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Penyusun berhasil diubah.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah penyusun.']);
        break;

    case 'delete_penyusun':
        $id = $_POST['id'] ?? '';
        $sql = "DELETE FROM arsip_penyusun WHERE id_penyusun = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt) echo json_encode(['status' => 'success', 'message' => 'Penyusun berhasil dihapus.']);
        else echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus penyusun.']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
        break;
}
?>
