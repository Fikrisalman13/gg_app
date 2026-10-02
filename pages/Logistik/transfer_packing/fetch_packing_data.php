<?php
include 'db.php';

// Mengambil nilai packingno yang dipilih
if (isset($_POST['packingno'])) {
    $packingno = $_POST['packingno'];

    // Query untuk mengambil data berdasarkan packingno
    $query = "SELECT 
                packingno, 
                no_do, 
                custcode, 
                custname, 
                nobale, 
                prodcode, 
                prodname, 
                prdnmbr, 
                totalqty, 
                uom 
              FROM 
                whtrans_packing 
              WHERE 
                packingno IN ('" . implode("','", $packingno) . "')";

    $result = mysqli_query($conn, $query);

    // Memeriksa apakah query berhasil dan ada data
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
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
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='10'>No data found</td></tr>";
    }
}
?>
