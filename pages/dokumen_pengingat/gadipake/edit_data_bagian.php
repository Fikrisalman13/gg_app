<?php
include '../koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil ID dari parameter URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Ambil data bagian berdasarkan ID
    $query = "SELECT * FROM bagian WHERE id = $id";
    $result = mysqli_query($koneksi, $query);
    $data = mysqli_fetch_assoc($result);

    // Jika data tidak ditemukan, kembali ke halaman bagian.php
    if (!$data) {
        echo "<script>alert('Data tidak ditemukan!'); window.location.href='/dokumen_pengingat/pages/bagian.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID tidak ditemukan!'); window.location.href='/dokumen_pengingat/pages/bagian.php';</script>";
    exit;
}

// Proses penyimpanan perubahan data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_bagian = mysqli_real_escape_string($koneksi, $_POST['nama_bagian']);
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $update_date = date('Y-m-d H:i:s'); // Tanggal saat ini

    // Update data bagian
    $queryUpdate = "UPDATE bagian SET nama_bagian = '$nama_bagian', keterangan = '$keterangan', update_at = '$update_date' WHERE id = $id";
    if (mysqli_query($koneksi, $queryUpdate)) {
        echo "<script>alert('Data berhasil diperbarui.'); window.location.href='/dokumen_pengingat/pages/bagian.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data: " . mysqli_error($koneksi) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Bagian</title>
    <link rel="stylesheet" href="path/to/your/styles.css">

    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="/dokumen_pengingat/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/dokumen_pengingat/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Data Bagian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dokumen_pengingat/index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="/dokumen_pengingat/bagian.php">Bagian</a></li>
                        <li class="breadcrumb-item active">Edit Data</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Form Edit Data Bagian</h3>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <div class="form-group">
                                    <label for="nama_bagian">Nama Bagian</label>
                                    <input type="text" name="nama_bagian" id="nama_bagian" class="form-control" value="<?php echo htmlspecialchars($data['nama_bagian']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="keterangan">Keterangan</label>
                                    <textarea name="keterangan" id="keterangan" class="form-control" rows="3"><?php echo htmlspecialchars($data['keterangan']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                <a href="bagian.php" class="btn btn-secondary">Batal</a>
                            </form>
                        </div><!-- /.card-body -->
                    </div><!-- /.card -->
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div><!-- /.content -->
</div>
</body>
</html>
<?php include '../includes/footer.php'; ?>
