<?php 
include '../koneksi.php'; 
include '../includes/header.php'; 
include '../includes/sidebar.php'; 

// Proses jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Menangkap data dari form
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $telp = mysqli_real_escape_string($koneksi, $_POST['telp']);
    $level = mysqli_real_escape_string($koneksi, $_POST['level']);

    // Enkripsi password MD5
    $password_encrypted = password_hash($password, PASSWORD_DEFAULT);

    // Query untuk menambah data pengguna ke database
    $query = "INSERT INTO user (username, password, nama_lengkap, telp, level) 
              VALUES ('$username', '$password_encrypted', '$nama_lengkap', '$telp', '$level')";

    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Data pengguna berhasil ditambahkan'); window.location = '/dokumen_pengingat/pages/pengguna.php';</script>";
    } else {
        echo "<script>alert('Gagal menambahkan data pengguna');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengguna</title>
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
                    <h1 class="m-0">Tambah Pengguna</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="/dokumen_pengingat/pages/pengguna.php">Pengguna</a></li>
                        <li class="breadcrumb-item active">Tambah Pengguna</li>
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
                            <h3 class="card-title">Form Tambah Pengguna</h3>
                        </div>
                        <div class="card-body">
                            <form action="tambah_pengguna.php" method="POST">
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="form-group">
                                    <label for="nama_lengkap">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                                </div>
                                <div class="form-group">
                                    <label for="telp">Telepon</label>
                                    <input type="text" class="form-control" id="telp" name="telp" required>
                                </div>
                                <div class="form-group">
                                    <label for="level">Level</label>
                                    <select class="form-control" id="level" name="level" required>
                                        <option value="admin">Admin</option>
                                        <option value="user_kontrak">User Kontrak</option>
                                        <option value="user_sertifikat">User Sertifikat</option>
                                        <option value="user_surat_kendaraan">User Surat Kendaraan</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan</button>
                                <a href="/dokumen_pengingat/pages/pengguna.php" class="btn btn-secondary">Batal</a>
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
