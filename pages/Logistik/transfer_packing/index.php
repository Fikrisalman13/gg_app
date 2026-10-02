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

    <!-- jQuery, Select2, Bootstrap JS -->
    <script src="/gg_app/pages/Logistik/transfer_packing/jquery-3.6.0.min.js"></script>
    <script src="/gg_app/pages/Logistik/transfer_packing/bootstrap.bundle.min.js"></script>
    <script src="/gg_app/pages/Logistik/transfer_packing/select2.min.js"></script>
    
    

    <script>

         $(document).ready(function () {
        $('#syncButton').on('click', function () {
            // Tampilkan pesan loading
            const button = $(this);
            button.prop('disabled', true).html('<i class="fas fa-sync-alt"></i> Syncing...');

            // Panggil sync_data.php menggunakan AJAX
            $.ajax({
                url: 'sync_data.php',
                type: 'GET',
                success: function (response) {
                    alert(response); // Tampilkan respon dari sync_data.php
                },
                error: function () {
                    alert('Terjadi kesalahan saat melakukan sinkronisasi.');
                },
                complete: function () {
                    // Kembalikan tombol ke keadaan semula
                    button.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Sync');
                }
            });
        });
    });
    $(document).ready(function () {
        // Validasi gudang tujuan
        $('#to_wrhsid').on('change', function () {
            const fromWrhsid = $('#from_wrhsid').val();
            const toWrhsid = $(this).val();

            if (fromWrhsid === toWrhsid) {
                alert('Gudang tujuan tidak boleh sama dengan gudang asal!');
                $(this).val('').trigger('change');
            }
        });

        // Inisialisasi Select2
        $('#packingno').select2({
            placeholder: "Pilih nomor packing",
            allowClear: true
        });

        // Fetch nomor packing berdasarkan gudang asal
        $('#from_wrhsid').on('change', function () {
            const fromWrhsid = $(this).val();
            const packingSelect = $('#packingno');

            if (!fromWrhsid) {
                packingSelect.empty().trigger('change');
                return;
            }

            $.ajax({
                url: 'get_packing_by_warehouse.php',
                type: 'GET',
                data: { wrhsid: fromWrhsid },
                dataType: 'json',
                success: function (response) {
                    if (response.error) {
                        alert(response.error);
                        packingSelect.empty().trigger('change');
                    } else {
                        packingSelect.empty();
                        response.forEach(packing => {
                            const option = new Option(packing.packingno, packing.packingno, false, false);
                            packingSelect.append(option);
                        });
                        packingSelect.trigger('change');
                    }
                },
                error: function () {
                    alert("Terjadi kesalahan saat mengambil data nomor packing.");
                    packingSelect.empty().trigger('change');
                }
            });
        });

        // Fetch detail nomor packing
        $('#packingno').on('change', function () {
            const packingNos = $(this).val();
            const tableBody = $('#resultTable tbody');
            const resultTable = $('#resultTable');

            if (!packingNos || packingNos.length === 0) {
                tableBody.html("<tr><td colspan='10' class='text-center text-danger'>Pilih nomor packing terlebih dahulu.</td></tr>");
                resultTable.hide();
                return;
            }

            tableBody.html("<tr><td colspan='10' class='text-center'>Loading...</td></tr>");
            resultTable.show();

            $.ajax({
                url: 'get_packing.php',
                type: 'GET',
                data: { packingno: packingNos.join(',') },
                dataType: 'json',
                success: function (response) {
                    if (response.error) {
                        tableBody.html("<tr><td colspan='10' class='text-center text-danger'>" + response.error + "</td></tr>");
                    } else {
                        tableBody.empty();
                        response.forEach(packing => {
                            tableBody.append(
                                `<tr>
                                    <td>${packing.packingno}</td>
                                    <td>${packing.no_do}</td>
                                    <td>${packing.custcode}</td>
                                    <td>${packing.custname}</td>
                                    <td>${packing.nobale}</td>
                                    <td>${packing.prodcode}</td>
                                    <td>${packing.prodname}</td>
                                    <td>${packing.prdnmbr}</td>
                                    <td>${packing.totalqty}</td>
                                    <td>${packing.uom}</td>
                                </tr>`
                            );
                        });
                    }
                },
                error: function () {
                    tableBody.html("<tr><td colspan='10' class='text-center text-danger'>Terjadi kesalahan dalam mengambil data bale.</td></tr>");
                }
            });
        });

        // Enable transfer button if packing is selected
        $('#packingno').on('change', function () {
            const packingNos = $(this).val();
            const transferButton = $('button[type="button"]');
            if (packingNos && packingNos.length > 0) {
                transferButton.prop('disabled', false);
            } else {
                transferButton.prop('disabled', true);
            }
        });
    });

    // PROSES TRANSFER
    function processTransfer() {
        // Ambil data packing dari tabel
        const packingData = [];
        $("#resultTable tbody tr").each(function () {
            const cells = $(this).find("td");
            packingData.push({
                packingno: cells.eq(0).text(),
                no_do: cells.eq(1).text(),
                custcode: cells.eq(2).text(),
                custname: cells.eq(3).text(),
                nobale: cells.eq(4).text(),
                prodcode: cells.eq(5).text(),
                prodname: cells.eq(6).text(),
                prdnmbr: cells.eq(7).text(),
                totalqty: cells.eq(8).text(),
                uom: cells.eq(9).text()
            });
        });

        if (packingData.length === 0) {
            alert("Tidak ada packing yang dipilih.");
            return;
        }

        // Ambil tanggal transaksi, No Rak, dan Deskripsi
        const transactionDate = $('#transdate').val();
        const rackNo = $('#rackno').val();
        const description = $('#deskripsi').val();

        if (!transactionDate) {
            alert("Tanggal transaksi harus diisi.");
            return;
        }

        // Kirim data packing ke server
        $.ajax({
            url: 'transfer_proses.php', 
            type: 'POST',
            data: {
                from_wrhsid: $('#from_wrhsid').val(),
                to_wrhsid: $('#to_wrhsid').val(),
                transdate: transactionDate,
                rackno: rackNo,
                deskripsi: description,
                packing_data: JSON.stringify(packingData)
            },
            success: function (response) {
                alert(response);
                location.reload(); // Reload the page after success
            },
            error: function () {
                alert("Terjadi kesalahan saat mengirim data transfer.");
            }
        });
    }
  
    </script>
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
<div class="container">
        <h2 class="text-center my-4 text-primary"> <i class="fa-solid fa-warehouse"></i> Transfer Packing Bale Gudang Titipan</h2>
        <form>
            <!-- Tanggal Transfer -->
            <div class="mb-3">
                <label for="transdate" class="form-label">Tanggal Transfer</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                    <input type="date" id="transdate" name="transdate" class="form-control" required>
                </div>
            </div>
            <!-- DARI GUDANG -->
            <div class="row">
                <div class="col-md-6">
                    <label for="from_wrhsid" class="form-label">Dari Gudang</label>
                    <select id="from_wrhsid" name="from_wrhsid" class="form-select" required>
                        <option value="">Pilih Gudang</option>
                        <?php
                        $result = $conn->query("SELECT wrhsid, wrhsname FROM warehouses");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['wrhsid']}'>{$row['wrhsname']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <!-- KE GUDANG -->
                <div class="col-md-6">
                    <label for="to_wrhsid" class="form-label">Ke Gudang</label>
                    <select id="to_wrhsid" name="to_wrhsid" class="form-select" required>
                        <option value="">Pilih Gudang</option>
                        <?php
                        $result = $conn->query("SELECT wrhsid, wrhsname FROM warehouses");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['wrhsid']}'>{$row['wrhsname']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <!-- NO PACKING -->
            <div class="mb-3">
                <label for="packingno" class="form-label">Nomor Packing</label>
                <select name="packingno[]" id="packingno" class="form-select" multiple="multiple" required></select>
            </div>
            
            <!-- NO RAK DAN DESKRPSI -->
            <div class="row">
                <div class="col-md-6">
                    <label for="rackno" class="form-label">No Rak</label>
                    <input type="text" id="rackno" name="rackno" class="form-control" placeholder="Masukkan no rak">
                </div>
                <div class="col-md-6">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-control" rows="1" placeholder="Deskripsi tambahan"></textarea>
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

            <!-- Tombol -->
            <div class="text-left mt-3">
                <button type="button" class="btn btn-primary" onclick="processTransfer()" disabled>
                    <i class="fas fa-paper-plane"></i> Transfer
                </button>
                </div>


                <div class="text-end mt-3">
    <button type="button" class="btn btn-primary" id="syncButton">
        <i class="fas fa-sync-alt"></i> Sync
    </button>
</div>
                <div class="text-end mt-3">
                    <a href="kirimbale.php" class="btn btn-primary"> Input Data kirim
                </a>
<div class="text-end mt-3">
                    <a href="reprint.php" class="btn btn-primary"> Reprint Barcode
                </a>
                <a href="lihat_data_transfer.php" class="btn btn-success">
                    <i class="fas fa-eye"></i> Lihat Data Transfer
                </a>
                <a href="input_packing_manual.php" class="btn btn-primary"> Input Data Packing Manual
                </a>
                    </div>
        </form>
    </div>
    </section>
</div>
<?php transferPackingFooter(); ?>
