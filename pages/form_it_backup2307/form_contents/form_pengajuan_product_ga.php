<?php
// Include necessary files - use absolute path using DOCUMENT_ROOT
// Note: If koneksi.php fails with die(), it will be caught by outer buffer
if (!isset($conn) || $conn === false) {
    // Use absolute path from document root (same as list_form.php)
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    } else {
        // Fallback: try relative path from form_contents location (3 levels up)
        // From pages/form_it/form_contents to root
        $fallbackPath = dirname(dirname(dirname(__DIR__))) . '/koneksi.php';
        if (file_exists($fallbackPath)) {
            require_once $fallbackPath;
        }
    }
}

// Ambil data username dari session
$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');

// Inisialisasi variabel default
$jabatan = '';
$departemen = '';
$bagian = '';

// Query untuk mengambil data jabatan, departemen, dan bagian
if (!empty($username) && isset($conn) && $conn !== false) {
    $sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
            FROM dbo.m_emp
            LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
            LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
            WHERE m_emp.nama_lengkap = ?";

    $params = array($username);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt !== false) {
        $data_emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($data_emp) {
            $jabatan = $data_emp['jabatan'] ?? '';
            $departemen = $data_emp['dept'] ?? '';
            $bagian = $data_emp['bagian'] ?? '';
        }
        sqlsrv_free_stmt($stmt);
    }
}
?>

<form method="POST" id="formPengajuanProductGA">
    <input type="hidden" name="form_type" value="pengajuan_product_ga">
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Form Pengajuan Product ke GA - Template akan dikembangkan kemudian
    </div>

    <div class="form-group">
        <label>Nama Pemohon</label>
        <input type="text" name="nama_pemohon" class="form-control" value="<?= htmlspecialchars($username) ?>" required readonly>
    </div>

    <div class="form-group">
        <label>Jabatan</label>
        <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($jabatan) ?>" readonly>
    </div>

    <div class="form-group">
        <label>Tanggal Pengajuan</label>
        <input type="text" name="tgl_pengajuan" class="form-control tgl-pengajuan" placeholder="DD-MM-YYYY" readonly>
    </div>

    <div class="form-group">
        <label>Departemen</label>
        <input type="text" name="departemen" class="form-control" value="<?= htmlspecialchars($departemen) ?>" readonly>
    </div>

    <div class="form-group">
        <label>Bagian</label>
        <input type="text" name="bagian" class="form-control" value="<?= htmlspecialchars($bagian) ?>" readonly>
    </div>

    <div class="form-group">
        <label>Nama Product</label>
        <input type="text" name="nama_product" class="form-control" required placeholder="Masukkan nama product">
    </div>

    <div class="form-group">
        <label>Jumlah</label>
        <input type="number" name="jumlah" class="form-control" required min="1" placeholder="Masukkan jumlah">
    </div>

    <div class="form-group">
        <label>Satuan</label>
        <select name="satuan" class="form-control" required>
            <option value="">-- Pilih Satuan --</option>
            <option value="Unit">Unit</option>
            <option value="Pack">Pack</option>
            <option value="Box">Box</option>
            <option value="Set">Set</option>
            <option value="Lainnya">Lainnya</option>
        </select>
    </div>

    <div class="form-group">
        <label>Spesifikasi</label>
        <textarea name="spesifikasi" class="form-control" rows="3" placeholder="Masukkan spesifikasi product"></textarea>
    </div>

    <div class="form-group">
        <label>Alasan Pengajuan</label>
        <textarea name="alasan" class="form-control" rows="3" required placeholder="Jelaskan alasan pengajuan product ini"></textarea>
    </div>

    <div class="form-group">
        <label>Keterangan</label>
        <textarea name="keterangan" class="form-control" rows="3"></textarea>
    </div>
</form>

<script>
// Set tanggal otomatis
$(document).ready(function() {
    var today = new Date();
    var day = today.getDate().toString().padStart(2, '0');
    var month = (today.getMonth() + 1).toString().padStart(2, '0');
    var year = today.getFullYear();
    var currentDate = day + '-' + month + '-' + year;
    $('.tgl-pengajuan').val(currentDate);
});
</script>

