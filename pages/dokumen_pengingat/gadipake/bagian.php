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
    <title>Bagian</title>
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
                    <h1 class="m-0">Bagian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dokumen_pengingat/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Bagian</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row mb-3">
                <!-- Tombol untuk menambah bagian -->
                <div class="col-12">
                    <a href="/dokumen_pengingat/pages/tambah_data_bagian.php" class="btn btn-success">Tambah Data Bagian</a>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Bagian</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama Bagian</th>
                                        <th>Keterangan</th>
                                        <th>Create Date</th>
                                        <th>Update Date</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Query untuk mengambil data dari tabel bagian
                                    $query = "SELECT id, nama_bagian, keterangan, create_date, update_at FROM bagian";
                                    $result = mysqli_query($koneksi, $query);

                                    if ($result && mysqli_num_rows($result) > 0) {
                                        $no = 1; // Nomor urut
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo "<tr>";
                                            echo "<td>{$no}</td>";
                                            echo "<td>{$row['nama_bagian']}</td>";
                                            echo "<td>{$row['keterangan']}</td>";
                                            echo "<td>{$row['create_date']}</td>";
                                            echo "<td>{$row['update_at']}</td>";
                                            echo "<td>
                                                    <a href='/dokumen_pengingat/pages/edit_data_bagian.php?id={$row['id']}' class='btn btn-primary btn-sm' title='Edit Dokumen'>
                                                        <i class='fas fa-edit'></i>
                                                    </a>
                                                    <a href='/dokumen_pengingat/pages/hapus_data_bagian.php?id={$row['id']}' class='btn btn-danger btn-sm' title='Hapus Dokumen' onclick='return confirm(\"Yakin ingin menghapus?\")'>
                                                        <i class='fas fa-trash'></i>
                                                    </a>
                                                </td>";
                                            echo "</tr>";
                                            $no++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='6'>Tidak ada data bagian.</td></tr>";
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
