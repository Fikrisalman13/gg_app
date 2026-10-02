<?php include 'db.php'; ?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<!-- Bootstrap CSS -->
    <link href="/gg_app/pages/Logistik/transfer_packing/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="/gg_app/pages/Logistik/transfer_packing/select2.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .form-label {
            font-weight: bold;
        }
        #resultTable {
            margin-top: 20px;
        }
        table thead th {
            background-color: #0d6efd;
            color: white;
            text-align: center;
        }
        .btn {
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .select2-container .select2-selection--multiple {
            border-radius: 0.5rem;
        }
    </style>
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
<div class="container">
    <h2 class="text-center my-4 text-primary"> <i class="fa-solid fa-warehouse"></i> Kirim Packing Bale Gudang Titipan</h2>
    <form>
        <!-- Tanggal Transfer -->
        <div class="mb-3">
            <label for="tanggal_kirim" class="form-label">Tanggal kirim </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                <input type="date" id="tanggal_kirim" name="tanggal_kirim" class="form-control" required>
            </div>
        </div>
        
        <!-- Nomor Packing -->
        <div class="mb-3">
            <label for="packingno" class="form-label">Nomor Packing</label>
            <select name="packingno[]" id="packingno" class="form-select" multiple="multiple" required></select>
        </div>

        <!-- No Surat Jalan -->
        <div class="row">
            <div class="col-md-6">
                <label for="suratjalan" class="form-label">No surat jalan</label>
                <input type="text" id="suratjalan" name="suratjalan" class="form-control" placeholder="Masukkan no suratjalan">
            </div>
        </div>

        <!-- Tabel Hasil -->
        <div id="bale_data">
            <table class="table table-striped table-hover table-bordered text-center" id="resultTable" style="display: none;">
                <thead>
                    <tr>
                        <th>No Packing</th>
                        <th>No DO</th>
                        <th>Cust Code</th>
                        <th>Cust Name</th>
                        <th>No Bale</th>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>No CP</th>
                        <th>Qty</th>
                        <th>UOM</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Tombol Kirim -->
        <div class="text-left mt-3">
            <button type="button" class="btn btn-primary" onclick="processTransfer()" enable>
                <i class="fas fa-paper-plane"></i> Kirim
            </button>
        </div>
        <div class="text-end mt-3">
            <a href="index.php" class="btn btn-primary"> kembali
                </a>
            <a href="lihat_data_kirim.php" class="btn btn-success">
                <i class="fas fa-eye"></i> Lihat Data kirim
            </a>
        </div>
    </form>
</div>
<!-- jQuery, Select2, Bootstrap JS -->
<script src="/gg_app/pages/Logistik/transfer_packing/jquery-3.6.0.min.js"></script>
 <script src="/gg_app/pages/Logistik/transfer_packing/bootstrap.bundle.min.js"></script>
    <script src="/gg_app/pages/Logistik/transfer_packing/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Inisialisasi Select2 untuk dropdown packingno
    $('#packingno').select2();

    // Mengambil data packingno dari database dan mengisi dropdown
    $.ajax({
        url: 'fetch_packing_no.php',
        type: 'GET',
        success: function(response) {
            // Mengisi dropdown dengan data packingno
            $('#packingno').html(response);
        }
    });

    // Ketika packingno dipilih
    $('#packingno').on('change', function() {
        let selectedPackingno = $(this).val();

        if (selectedPackingno) {
            // Fetch data berdasarkan packingno yang dipilih
            $.ajax({
                url: 'fetch_packing_data.php',
                type: 'POST',
                data: { packingno: selectedPackingno },
                success: function(response) {
                    // Tampilkan tabel jika ada data
                    if (response) {
                        $('#resultTable').show();
                        $('#resultTable tbody').html(response);
                    } else {
                        $('#resultTable').hide();
                    }
                }
            });
        } else {
            $('#resultTable').hide();
        }
    });
});

// Fungsi untuk memproses kirim
function processTransfer() {
    // Ambil nilai dari form
    var tanggal_kirim = $('#tanggal_kirim').val();
    var suratJalan = $('#suratjalan').val();
    var packingNo = $('#packingno').val();

    // Cek apakah semua data sudah diisi
    if (!tanggal_kirim || !suratJalan || packingNo.length === 0) {
        alert('Harap lengkapi semua data.');
        return;
    }

    // Matikan tombol untuk mencegah klik ganda
    $('button[type="button"]').prop('disabled', true);

    // Kirim data ke PHP menggunakan AJAX
    $.ajax({
        url: 'process_transfer.php',
        type: 'POST',
        data: {
            tanggal_kirim: tanggal_kirim,
            suratjalan: suratJalan,
            packingno: packingNo
        },
        success: function(response) {
            // Jika berhasil, tampilkan pesan dan arahkan ke kirimbale.php
            if (response === 'success') {
                alert('Data berhasil dikirim!');
                window.location.href = 'kirimbale.php'; // Redirect ke kirimbale.php
            } else {
                alert('Terjadi kesalahan: ' + response);
                $('button[type="button"]').prop('disabled', false); // Aktifkan kembali tombol jika error
            }
        },
        error: function() {
            alert('Terjadi kesalahan pada permintaan.');
            $('button[type="button"]').prop('disabled', false); // Aktifkan kembali tombol jika error
        }
    });
}
</script>
    </section>
</div>
<?php transferPackingFooter(); ?>
