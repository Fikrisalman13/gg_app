<?php

include('db.php');  // Menghubungkan dengan database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $from_wrhsid = $_POST['from_wrhsid'];
    $to_wrhsid = $_POST['to_wrhsid'];
    $transaction_date = $_POST['transdate'];
    $deskripsi = $_POST['deskripsi'];
    $packing_data = json_decode($_POST['packing_data'], true);  // Decoding bale data yang dikirimkan dalam format JSON
    $rackno = $_POST['rackno'];
    
    // Mulai transaksi
    $conn->begin_transaction();

    try {
        // Generate nomor transaksi
        $transnmbr = "TR" . date("YmdHis");  // Generate example transaction number based on date-time format

        // Insert transfer record into 'whtrans' table
        $stmt = $conn->prepare("INSERT INTO whtrans (transnmbr, transdate, from_wrhsid, to_wrhsid) VALUES ( ?, ?, ?, ?)");
        $stmt->bind_param("sssi", $transnmbr, $transaction_date, $from_wrhsid, $to_wrhsid);
        $stmt->execute();

        // Mendapatkan transfer_id dari record yang baru saja dimasukkan
        $transfer_id = $conn->insert_id;

        // Counter jumlah data yang berhasil dimasukkan
        $successful_inserts = 0;

        // Insert packing data into 'whtrans_packing' table
        foreach ($packing_data as $packing) {
            $stmt = $conn->prepare("INSERT INTO whtrans_packing (transid, packingno, wrhsid, no_do, custcode, custname, nobale, prodcode, prodname, prdnmbr, totalqty, uom, created_at, no_rak, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");

            // Simpan timestamp dalam variabel
            $created_at = date("Y-m-d H:i:s");

            $stmt->bind_param(
                "isssssssssissss", 
                $transfer_id, 
                $packing['packingno'], 
                $from_wrhsid, 
                $packing['no_do'], 
                $packing['custcode'], 
                $packing['custname'], 
                $packing['nobale'], 
                $packing['prodcode'], 
                $packing['prodname'], 
                $packing['prdnmbr'], 
                $packing['totalqty'], 
                $packing['uom'],
                $created_at, 
                $rackno,  // Menyimpan rackno ke no_rak
                $deskripsi  // Menyimpan deskripsi ke deskripsi
            );

            $stmt->execute();
            $successful_inserts++;
        }

        // Commit transaksi setelah data berhasil disimpan
        $conn->commit();

        // Menampilkan notifikasi sukses dengan format HTML
        echo "Transfer Bale berhasil! Nomor Transaksi: " . $transnmbr;
    } catch (Exception $e) {
        // Rollback jika terjadi error
        $conn->rollback();
        echo "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>
