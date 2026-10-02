<?php
ob_start(); // Memulai output buffering
include '../koneksi.php'; 
include '../includes/header.php'; 
include '../includes/sidebar.php'; 

// Ambil ID pengguna dari URL
$id = $_GET['id'];

// Query untuk mengambil data pengguna berdasarkan ID
$query = "SELECT * FROM user WHERE id = $id";
$result = mysqli_query($koneksi, $query);
$user = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $username = $_POST['username'];
    $password = $_POST['password'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $telp = $_POST['telp'];
    $level = $_POST['level'];

     // Enkripsi password
     $password_encrypted = password_hash($password, PASSWORD_DEFAULT);
    // Query untuk memperbarui data pengguna
    $update_query = "UPDATE user SET 
                     username = '$username', 
                     password = '$password_encrypted', 
                     nama_lengkap = '$nama_lengkap', 
                     telp = '$telp', 
                     level = '$level' 
                     WHERE id = $id";
    
    if (mysqli_query($koneksi, $update_query)) {
        header("Location: /dokumen_pengingat/pages/pengguna.php");
        echo "Data pengguna berhasil diperbarui.";
        
        exit();
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna</title>
    <link rel="stylesheet" href="path/to/your/styles.css">
    <link rel="stylesheet" href="/dokumen_pengingat/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/dokumen_pengingat/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Pengguna</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Form Edit Pengguna</h3>
                        </div>
                        <div class="card-body">
                            <form action="edit_pengguna.php?id=<?php echo $user['id']; ?>" method="POST">
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo $user['username']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" value="<?php echo $user['password']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="nama_lengkap">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo $user['nama_lengkap']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="telp">Telp</label>
                                    <input type="text" class="form-control" id="telp" name="telp" value="<?php echo $user['telp']; ?>" required>
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
                                <button type="submit" class="btn btn-primary">Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php include '../includes/footer.php'; ?>
<?php ob_end_flush(); // Mengakhiri output buffering ?>