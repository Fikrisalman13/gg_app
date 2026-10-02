<?php
session_start();
date_default_timezone_set('Asia/Jakarta'); // ✅ Pastikan waktu mengikuti WIB
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

$prodcode = isset($_GET['prodcode']) ? trim($_GET['prodcode']) : '';
$startdate = isset($_GET['startdate']) ? trim($_GET['startdate']) : '';
$enddate = isset($_GET['enddate']) ? trim($_GET['enddate']) : '';

if ($prodcode === '') {
    die("Kode produk tidak ditemukan!");
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Kartu_Stok_" . $prodcode . "_" . date('Ymd') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // Eksekusi DO block terlebih dahulu
    $doQuery = "
    DO $$
    DECLARE
    vStartTransDate TimeStamp;
    vEndTransDate TimeStamp;
    vProdCode Varchar(25);
    vProdId Int;
    vSaldoStartDate TimeStamp;
    vSaldoEndDate TimeStamp;
    vLastProcdate TimeStamp;

    Begin
    vProdCode = '$prodcode';
    vStartTransDate = '$startdate';
    vEndTransDate = '$enddate';

    vProdId = (Select ProdId From SMProduct Where ProdCode = vProdCode);
    
    DROP TABLE If Exists TmpDisplay;
    CREATE Temp TABLE TmpDisplay(
        Seq Int,
        ProdId Int,
        ProdCode  Varchar(25),	
        ProdName  Varchar(100),
        WrhsCode Varchar(25),
        WrhsName Varchar(80),
        TransNo Varchar(25),	
        TransDate TimeStamp,
        TransType Varchar(2),
        TransTypeName Varchar(30),
        FgINOut Varchar(1),
        INQty Numeric(19,4),
        INPrice Numeric(19,4),
        INQtyPrice Numeric(19,4),
        OutQty Numeric(19,4),
        OutPrice Numeric(19,4),
        OutQtyPrice Numeric(19,4),
        BalanceQty Numeric(19,4) default 0,
        BalancePrice Numeric(19,4) default 0,
        BalanceQtyPrice Numeric(19,4) default 0
    );

    DROP TABLE If exists TmpSaldoAwal;
    CREATE Temp TABLE TmpSaldoAwal(
        Seq Int,	
        ProdId Int,
        ProdCode Varchar(25),
        StartDate TimeStamp, 
        EndDate TimeStamp, 
        BalanceQty Numeric(19,4), 
        BalAdjPrice Numeric(19,4), 
        BalAdjQtyPrice Numeric(19,4)
    );

    Insert Into TmpSaldoAwal (Seq, ProdId, StartDate, EndDate, BalanceQty, BalAdjPrice, BalAdjQtyPrice)
    Select 1, WHAverageAll.ProdId, whprocessdateAll.StartDate, whprocessdateAll.EndDate, WHAverageAll.BalanceQty, WHAverageAll.BalAdjPrice, WHAverageAll.BalAdjQtyPrice
    From WHAverageAll
    Inner Join whprocessdateAll on whprocessdateAll.ProcessDateAllId = WHAverageAll.ProcessDateAllId
    Where WHAverageAll.ProdId = vProdId
      And whprocessdateAll.EndDate < vStartTransDate
    Order By whprocessdateAll.StartDate Desc, whprocessdateAll.EndDate Desc
    Limit 1;

    vLastProcdate = (Select EndDate From TmpSaldoAwal);
    vSaldoEndDate = cast(vStartTransDate + Cast('-1 Day' as Interval) as TimeStamp);

    IF (vLastProcdate is Not Null) THEN
        IF(vLastProcdate <> vSaldoEndDate) THEN
            vSaldoStartDate = cast(vLastProcdate + Cast('1 Day' as Interval) as TimeStamp);

            Insert Into TmpSaldoAwal (Seq, ProdId, StartDate, EndDate, BalanceQty, BalAdjQtyPrice)
            Select 2, vProdId, vSaldoStartDate, vSaldoEndDate, 
                    Coalesce(SUM(Case When WHTransMs.FgStatus = 'I'
                            Then Coalesce(WHTransDt.TransInStdQty,0)
                            Else -1 * Coalesce(WHTransDt.TransOutStdQty,0)
                    End),0),
                    Coalesce(SUM(Case When WHTransMs.FgStatus = 'I'
                            Then Coalesce(WHTransDt.TransQtyPrice,0)
                            Else -1 * Coalesce(WHTransDt.TransQtyPrice,0)
                    End),0)
            From WHTransHd
            Inner Join WHTransDt on WHTransHd.TransHdId = WHTransDt.TransHdId
            Inner Join WHTransMs on WHTransMs.TransCode = WHTransHd.TransdestType
            Where WHTransDt.TransProdId = vProdId
              And WHTransHd.TransdestDate Between vSaldoStartDate And vSaldoEndDate;
        END IF;
    END IF;

    IF Exists (Select 1 From TmpSaldoAwal Where Seq = 2) Then
        Insert Into TmpDisplay (Seq, ProdId, TransTypeName, FgINOut,
                    INQty,	INPrice, INQtyPrice, OutQty, OutPrice, OutQtyPrice)
        Select 1, TmpSaldoAwal.ProdId, 'Saldo Awal', 'I',
                SUM(Coalesce(TmpSaldoAwal.BalanceQty,0)), 
                case When SUM(Coalesce(TmpSaldoAwal.BalanceQty,0)) <> 0 
                    Then Round(SUM(Coalesce(TmpSaldoAwal.BalAdjQtyPrice,0)) / SUM(Coalesce(TmpSaldoAwal.BalanceQty,0)), 4)
                    Else 0
                End, 
                SUM(Coalesce(TmpSaldoAwal.BalAdjQtyPrice,0)), 0, 0, 0
        From TmpSaldoAwal	
        Inner Join SMProduct on SMProduct.ProdId = TmpSaldoAwal.ProdId
        Group By TmpSaldoAwal.ProdId;
     ELSE 
        Insert Into TmpDisplay (Seq, ProdId, TransTypeName, FgINOut,
                    INQty,	INPrice, INQtyPrice, OutQty, OutPrice, OutQtyPrice)
        Select 1, TmpSaldoAwal.ProdId, 'Saldo Awal', 'I',
                TmpSaldoAwal.BalanceQty, TmpSaldoAwal.BalAdjPrice, TmpSaldoAwal.BalAdjQtyPrice, 0, 0, 0
        From TmpSaldoAwal	
        Inner Join SMProduct on SMProduct.ProdId = TmpSaldoAwal.ProdId;
    END IF;

    Insert Into TmpDisplay (Seq, ProdId, wrhsCode, wrhsName, TransNo, TransDate, TransType, TransTypeName, FgINOut,
        INQty,	INPrice, INQtyPrice, OutQty, OutPrice, OutQtyPrice)
    Select ROW_NUMBER() Over(Order By WHTransHd.TransDestDate, WHTransDt.UpdDate, WHTransHd.TransDestNmbr) + 1 Seq,
        WHTransDt.TransProdId, WHWrhs.WrhsCode,WHWrhs.WrhsName,
        WHTransHd.TransDestNmbr, WHTransHd.TransDestDate, WHTransHd.TransDestType, WHTransMs.TransName, WHTransMs.FgStatus,
        Coalesce(WHTransDt.TransInStdQty,0), Case When WHTransMs.FgStatus = 'I' Then Coalesce(WHTransDt.TransPrice,0) Else 0 End, Case When WHTransMs.FgStatus = 'I' Then Coalesce(WHTransDt.TransQtyPrice,0) Else 0 End,
        Coalesce(WHTransDt.TransOutStdQty,0),  Case When WHTransMs.FgStatus = 'I' Then 0 Else Coalesce(WHTransDt.TransPrice,0) End, Case When WHTransMs.FgStatus = 'I' Then 0 Else Coalesce(WHTransDt.TransQtyPrice,0)  End
    From WHTransHd
    Inner Join WHTransDt on WHTransHd.TransHdId = WHTransDt.TransHdId
    Inner Join WHTransMs on WHTransMs.TransCode = WHTransHd.TransDestType
    Inner Join SMProduct on SMProduct.ProdId = WHTransDt.TransProdId
    Inner Join WHWrhs on WHWrhs.WrhsId = WHTransHd.TransDestWrhsId
    Where WHTransDt.TransProdId = vProdId
      And WHTransHd.TransDestDate Between vStartTransDate And vEndTransDate
    Order By WHTransHd.TransDestDate, WHTransDt.UpdDate, WHTransHd.TransDestNmbr;

    Update TmpDisplay
    Set Seq = case TransType			
                When '20' Then 2
                When '70' Then 2
                When '08' Then 3
                When '56' Then 3
                When '24' Then 4
                When '74' Then 4									
             End
    Where TransType in ('08', '20', '24', '56','70', '74');

    Update TmpDisplay
    Set Seq = (select Max(A.Seq) from TmpDisplay A) + (Seq - 1)
    Where TransType in ('08', '20', '24', '56','70', '74');

    Update TmpDisplay
    Set BalanceQty = Coalesce(INQty,0),
        BalancePrice = Coalesce(INPrice,0),
        BalanceQtyPrice = Coalesce(INQtyPrice,0)
    Where Seq = 1;

    Update TmpDisplay
    Set BalanceQty = Coalesce(INQty,0) - Coalesce(OutQty,0) + Coalesce((Select SUM(Coalesce(A.INQty,0) - Coalesce(A.OutQty,0)) From TmpDisplay A Where A.Seq <= TmpDisplay.Seq - 1),0),
        BalanceQtyPrice = Coalesce(INQtyPrice,0) - Coalesce(OutQtyPrice,0) + Coalesce((Select SUM(Coalesce(B.INQtyPrice,0) - Coalesce(B.OutQtyPrice,0)) From TmpDisplay B Where B.Seq <= TmpDisplay.Seq - 1),0)
    Where Seq > 1;

    Update TmpDisplay
    Set BalancePrice = case When BalanceQty = 0 
                                Then 0
                            Else Round(BalanceQtyPrice/ BalanceQty,4)
                        End  
    Where Seq > 1;

    Update TmpDisplay
    Set ProdCode = SMproduct.ProdCode,
        ProdName = SMProduct.ProdName
    From SMProduct
    Where SMProduct.ProdId = TmpDisplay.ProdId;

    END $$;
    ";

    // Eksekusi DO block
    $doStmt = $conn3->prepare($doQuery);
    $doStmt->execute();
    
    // Sekarang eksekusi SELECT query terpisah
    $selectQuery = "
    SELECT ProdCode as \"Kode Produk\", ProdName as \"Nama Produk\",
        WrhsCode as \"Kode Gudang\", WrhsName as \"Nama Gudang\",
        TransNo as \"No Bukti\", TransDate as \"Tanggal\", TransTypeName as \"Jenis Transaksi\", 
        INQty as \"Qty Masuk\", INPrice as \"Harga Satuan Masuk\", INQtyPrice as \"Total Masuk\", 
        OutQty as \"Qty Keluar\", OutPrice as \"Harga Satuan Keluar\", OutQtyPrice as \"Total Keluar\", 
        BalanceQty as \"Saldo Qty\", BalancePrice as \"Harga Satuan Saldo\", BalanceQtyPrice as \"Total Saldo\"
    FROM TmpDisplay 
    ORDER BY Seq
    ";
    
    $selectStmt = $conn3->prepare($selectQuery);
    $selectStmt->execute();
    $results = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error executing query: " . $e->getMessage());
}

// Fungsi untuk format angka universal (tanpa separator, titik sebagai desimal)
function formatNumber($angka) {
    return number_format($angka, 2, '.', '');
}

// Fungsi untuk format currency universal
function formatCurrency($angka) {
    return number_format($angka, 2, '.', '');
}

// Buat tabel HTML untuk diekspor
echo "<table border='1'>";
echo "<tr>
        <th colspan='17' style='background-color: #d9edf7; font-size: 16px; font-weight: bold;'>
            KARTU STOK - " . htmlspecialchars($prodcode) . "
        </th>
      </tr>";
echo "<tr>
        <th colspan='17' style='background-color: #f5f5f5;'>
            Periode: " . htmlspecialchars($startdate) . " s/d " . htmlspecialchars($enddate) . "
        </th>
      </tr>";

echo "<tr style='background-color: #f8f9fa; font-weight: bold;'>
        <th>No</th>
        <th>Tanggal</th>
        <th>No Bukti</th>
        <th>Jenis Transaksi</th>
        <th>Kode Gudang</th>
        <th>Nama Gudang</th>
        <th>Kode Produk</th>
        <th>Nama Produk</th>
        <th>Qty Masuk</th>
        <th>Harga Satuan Masuk</th>
        <th>Total Masuk</th>
        <th>Qty Keluar</th>
        <th>Harga Satuan Keluar</th>
        <th>Total Keluar</th>
        <th>Saldo Qty</th>
        <th>Harga Satuan Saldo</th>
        <th>Total Saldo</th>
      </tr>";

$no = 1;
foreach ($results as $row) {
    $tanggal = $row['Tanggal'] ? date("Y-m-d", strtotime($row['Tanggal'])) : '';
    
    echo "<tr>
            <td style='text-align: center;'>".$no++."</td>
            <td style='text-align: center;'>".$tanggal."</td>
            <td>".htmlspecialchars($row['No Bukti'])."</td>
            <td>".htmlspecialchars($row['Jenis Transaksi'])."</td>
            <td>".htmlspecialchars($row['Kode Gudang'])."</td>
            <td>".htmlspecialchars($row['Nama Gudang'])."</td>
            <td>".htmlspecialchars($row['Kode Produk'])."</td>
            <td>".htmlspecialchars($row['Nama Produk'])."</td>
            <td style='text-align: right;'>".formatNumber($row['Qty Masuk'])."</td>
            <td style='text-align: right;'>".formatCurrency($row['Harga Satuan Masuk'])."</td>
            <td style='text-align: right;'>".formatCurrency($row['Total Masuk'])."</td>
            <td style='text-align: right;'>".formatNumber($row['Qty Keluar'])."</td>
            <td style='text-align: right;'>".formatCurrency($row['Harga Satuan Keluar'])."</td>
            <td style='text-align: right;'>".formatCurrency($row['Total Keluar'])."</td>
            <td style='text-align: right;'>".formatNumber($row['Saldo Qty'])."</td>
            <td style='text-align: right;'>".formatCurrency($row['Harga Satuan Saldo'])."</td>
            <td style='text-align: right;'>".formatCurrency($row['Total Saldo'])."</td>
          </tr>";
}

// Hitung total
$totalInQty = array_sum(array_column($results, 'Qty Masuk'));
$totalInPrice = array_sum(array_column($results, 'Total Masuk'));
$totalOutQty = array_sum(array_column($results, 'Qty Keluar'));
$totalOutPrice = array_sum(array_column($results, 'Total Keluar'));

echo "<tr style='background-color: #e9ecef; font-weight: bold;'>
        <td colspan='8' style='text-align: center;'>TOTAL</td>
        <td style='text-align: right;'>".formatNumber($totalInQty)."</td>
        <td></td>
        <td style='text-align: right;'>".formatCurrency($totalInPrice)."</td>
        <td style='text-align: right;'>".formatNumber($totalOutQty)."</td>
        <td></td>
        <td style='text-align: right;'>".formatCurrency($totalOutPrice)."</td>
        <td colspan='3'></td>
      </tr>";

echo "</table>";

// Tambahkan informasi footer
echo "<div style='margin-top: 20px; font-size: 12px; color: #6c757d;'>
        <p>Dicetak pada: " . date('Y-m-d H:i:s') . "</p>
        <p>Oleh: " . htmlspecialchars($_SESSION['UserName']) . "</p>
      </div>";

// Tambahkan style untuk format universal
echo "<style>
        body {
            font-family: 'Arial', sans-serif;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        /* Style untuk Excel agar mengenali sebagai angka */
        .number {
            mso-number-format:'#,##0.00';
        }
      </style>";

// Tambahkan metadata untuk Excel
echo "
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
<!--[if gt ie 8]>
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<![endif]-->
";