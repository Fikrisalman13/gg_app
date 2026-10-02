<?php
include 'db.php'; // Koneksi ke database

// Handle delete action
if (isset($_GET['delete'])) {
    $packingno = $_GET['delete'];
    // Delete query untuk menghapus data berdasarkan packingno di tabel whtrans_packing
    $delete_query = "DELETE FROM whtrans_packing 
                     WHERE packingno = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("s", $packingno);
    if ($stmt->execute()) {
        echo "<script>alert('Data packing berhasil dihapus');</script>";
        echo "<script>window.location = 'lihat_data_transfer.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data packing');</script>";
    }
}

// Query untuk mengambil data transfer
$query = "SELECT
    a.transnmbr, 
    a.transdate, 
    a.from_wrhsid, 
    a.to_wrhsid, 

    b.packingno, 
    b.no_do, 
    b.custcode, 
    b.custname, 
    b.nobale, 
    b.prodcode, 
    b.prodname, 
    b.prdnmbr, 
    b.totalqty, 
    b.uom, 
    b.created_at,
    b.uom, 
    b.no_rak,
    b.deskripsi
FROM
    whtrans AS a
    INNER JOIN
    whtrans_packing AS b
    ON 
    a.transid = b.transid
ORDER BY
    a.transdate DESC";
$result = $conn->query($query);
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .table-container {
            overflow-x: auto;
        }
        .table th {
            background-color: #343a40;
            color: #fff;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .btn-action {
            margin-right: 5px;
        }
    </style>
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
<div class="container mt-5">
    <div class="text-center mb-4">
        <h2 class="text-primary"><i class="fa fa-database"></i> Data Transfer Berhasil</h2>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No Transaksi</th>
                            <th>Tgl Transfer</th>
                            <th>Dari Gudang</th>
                            <th>Ke Gudang</th>
                            <th>packingno</th>
                            <th>No DO</th>
                            <th>Kode Customer</th>
                            <th>Nama Customer</th>
                            <th>No Bale</th>
                            <th>Kode Produk</th>
                            <th>Nama Produk</th>
                            <th>No CP</th>
                            <th>Total Qty</th>
                            <th>Satuan</th>
                            <th>no rak</th>
                            <th>Deskripsi</th>
                            <th>Update</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                        <td>{$no}</td>
                                        <td>{$row['transnmbr']}</td>
                                        <td>{$row['transdate']}</td>
                                        <td>{$row['from_wrhsid']}</td>
                                        <td>{$row['to_wrhsid']}</td>
                                        
                                        <td>{$row['packingno']}</td>
                                        <td>{$row['no_do']}</td>
                                        <td>{$row['custcode']}</td>
                                        <td>{$row['custname']}</td>
                                        <td>{$row['nobale']}</td>
                                        <td>{$row['prodcode']}</td>
                                        <td>{$row['prodname']}</td>
                                        <td>{$row['prdnmbr']}</td>
                                        <td>{$row['totalqty']}</td>
                                        <td>{$row['uom']}</td>
                                        
                                        <td>{$row['no_rak']}</td>
                                        <td>{$row['deskripsi']}</td>
                                        <td>{$row['created_at']}</td>
                                        <td>
                                            <a href='edit_data_transfer.php?packingno={$row['packingno']}' 
                                               class='btn btn-warning btn-sm btn-action'>
                                               <i class='fa fa-edit'></i> Edit</a>
                                            <a href='?delete={$row['packingno']}' 
                                               class='btn btn-danger btn-sm btn-action' 
                                               onclick='return confirm(\"Apakah Anda yakin ingin menghapus data ini?\");'>
                                               <i class='fa fa-trash'></i> Hapus</a>
                                        </td>
                                      </tr>";
                                $no++;
                            }
                        } else {
                            echo "<tr><td colspan='19' class='text-center text-danger'>Tidak ada data transfer yang tersedia.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="index.php" class="btn btn-success"><i class="fa fa-arrow-left"></i> Kembali</a>
            </div>
        </div>
    </div>
</div>
    </section>
</div>
<?php transferPackingFooter(); ?>
