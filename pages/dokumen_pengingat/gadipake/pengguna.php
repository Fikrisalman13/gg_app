<?php 
include '../koneksi.php'; 
include '../includes/header.php'; 
include '../includes/sidebar.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengguna</title>
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
                    <h1 class="m-0">Pengguna</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dokumen_pengingat/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Pengguna</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row mb-3">
                <!-- Tombol untuk menambah pengguna -->
                <div class="col-12">
                    <a href="/dokumen_pengingat/pages/tambah_pengguna.php" class="btn btn-success">Tambah Data Pengguna</a>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Pengguna</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Nama Lengkap</th>
                                        <th>Telp</th>
                                        <th>Level</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Query untuk mengambil data dari tabel pengguna
                                    $query = "SELECT id, username, password, nama_lengkap, telp, level FROM user";
                                    $result = mysqli_query($koneksi, $query);

                                    if ($result && mysqli_num_rows($result) > 0) {
                                        $no = 1; // Nomor urut
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo "<tr>";
                                            echo "<td>{$no}</td>";
                                            echo "<td>{$row['username']}</td>";
                                            echo "<td>******</td>"; // Menyembunyikan password
                                            echo "<td>{$row['nama_lengkap']}</td>";
                                            echo "<td>{$row['telp']}</td>";
                                            echo "<td>{$row['level']}</td>";
                                            echo "<td>
                                                    <a href='/dokumen_pengingat/pages/edit_pengguna.php?id={$row['id']}' class='btn btn-primary btn-sm' title='Edit Dokumen'>
                                                        <i class='fas fa-edit'></i>
                                                    </a>
                                                    <a href='/dokumen_pengingat/pages/hapus_pengguna.php?id={$row['id']}' class='btn btn-danger btn-sm' title='Hapus Dokumen' onclick='return confirm(\"Yakin ingin menghapus?\")'>
                                                        <i class='fas fa-trash'></i>
                                                    </a>
                                                </td>";
                                            echo "</tr>";
                                            $no++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='7'>Tidak ada data pengguna.</td></tr>";
                                    }

                                    // Tutup koneksi jika diperlukan
                                    mysqli_close($koneksi);
                                    ?>
                                </tbody>
                            </table>
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