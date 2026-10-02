<?php
session_start();
date_default_timezone_set('Asia/Jakarta'); // ✅ Pastikan waktu mengikuti WIB
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

$startdate_input = isset($_GET['startdate']) ? trim($_GET['startdate']) : date('Y-m-01');
$enddate_input   = isset($_GET['enddate'])   ? trim($_GET['enddate'])   : date('Y-m-d');
$ar_filter = isset($_GET['ar_filter']) ? trim($_GET['ar_filter']) : '';

// Validasi format tanggal sederhana (YYYY-MM-DD)
function validate_date_yyyy_mm_dd($d) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        $parts = explode('-', $d);
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    }
    return false;
}
if (!validate_date_yyyy_mm_dd($startdate_input)) $startdate_input = date('Y-m-01');
if (!validate_date_yyyy_mm_dd($enddate_input))   $enddate_input   = date('Y-m-d');

// Konversi ke format YYYYMMDD sesuai SQL asli
$startdate_sql = str_replace('-', '', $startdate_input);
$enddate_sql   = str_replace('-', '', $enddate_input);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Jurnal_Penjualan_" . date('Ymd') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // Mulai transaksi agar sementara tabel temp bisa dibuat dan di-drop dengan aman
    $conn3->beginTransaction();

    // Susun SQL multi-statement untuk Jurnal Penjualan
    $sql = "
    -- Hapus & buat TempDataInvoice dengan index
    DROP TABLE IF EXISTS TempDataInvoice;
    CREATE TABLE TempDataInvoice AS
    select ARPosting.arnmbr, ARPosting.ARDate, ARCRHd.arcrnmbr, ARCNDNHd.cndnnmbr, ARCNDNHd.fgtranssource, CMCRHd.CRNmbr, 
    cast (null as varchar(100)) as CR_No
    from ARPosting
    Left Outer Join ARCRDt on ARCRDt.arid =  ARPosting.arid
    Left Outer Join ARCRHd on ARCRHd.arcrhdid =  ARCRDt.arcrhdid
    Left Outer Join ARCNDNAlloc on ARCNDNAlloc.arid =  ARPosting.arid
    Left Outer Join ARCNDNHd on ARCNDNHd.cndnhdid =  ARCNDNAlloc.cndnhdid
    Left Outer Join ARCNDNSA on ARCNDNSA.cndnhdid =  ARCNDNHd.cndnhdid
    Left Outer Join INSATrans on INSATrans.satransid =  ARCNDNSA.satransid
    Left Outer Join CMCRHd on CMCRHd.crhdid =  INSATrans.crhdid
    where ARPosting.ARDate between '{$startdate_sql}' and '{$enddate_sql}'; 

    -- Update CR_No
    UPDATE TempDataInvoice
    SET CR_No = 
        CASE WHEN arcrnmbr IS NOT NULL THEN arcrnmbr
             ELSE CRNmbr
        END;

    -- Buat TempPenjualan
    DROP TABLE IF EXISTS TempPenjualan;
    CREATE TABLE TempPenjualan AS
    select distinct GLTransHd.TransDate, GLTransHd.TransDesc as Nmbr, GLTransDt.coacode, GLTransDt.coaname, 
    GLTransDt.dbhcamount as Debit_HC_Amount,GLTransDt.crhcamount as Credit_HC_Amount, SMCurrency.CurrCode,
    GLTransDt.transdbamount as Debit_Trans_Amount, GLTransDt.transcramount as Credit_Trans_Amount, TempDataInvoice.arnmbr,
    Cast('1 - Pengakuan Penjualan' as varchar(100)) as Keterangan
    from GLTransDt
    inner join GLTransHd on GLTransDt.transhdid = GLTransHd.transhdid
    Left Outer Join SMCurrency on SMCurrency.currid = GLTransDt.transcurrid
    Left Outer Join ARCRHd on ARCRHd.cmcrnmbr = GLTransHd.TransNmbr
    Left Outer Join ARCRDt on ARCRDt.arcrhdid =  ARCRHd.arcrhdid
    Left Outer Join TempDataInvoice on TempDataInvoice.arnmbr =  GLTransHd.TransDesc
    where jurnaltype like 'IN%' and TempDataInvoice.arnmbr =  GLTransHd.TransDesc;

    -- Buat TempPembayaran
    DROP TABLE IF EXISTS TempPembayaran;
    CREATE TABLE TempPembayaran AS
    select GLTransHd.TransDate, GLTransHd.TransNmbr as nmbr, GLTransDt.coacode, GLTransDt.coaname, 
    GLTransDt.dbhcamount as Debit_HC_Amount,GLTransDt.crhcamount as Credit_HC_Amount, SMCurrency.CurrCode,
    GLTransDt.transdbamount as Debit_Trans_Amount, GLTransDt.transcramount as Credit_Trans_Amount, TempDataInvoice.arnmbr,
    Cast('2 - Penerimaan' as varchar(100)) as Keterangan
    from GLTransDt
    inner join GLTransHd on GLTransDt.transhdid = GLTransHd.transhdid
    Left Outer Join SMCurrency on SMCurrency.currid = GLTransDt.transcurrid
    Left Outer Join ARCRHd on ARCRHd.cmcrnmbr = GLTransHd.TransNmbr
    Left Outer Join ARCRDt on ARCRDt.arcrhdid =  ARCRHd.arcrhdid
    Left Outer Join TempDataInvoice on TempDataInvoice.cr_no =  GLTransHd.TransNmbr
    where jurnaltype like 'CR%' and TempDataInvoice.cr_no =  GLTransHd.TransNmbr;

    -- Buat TempCNDN
    DROP TABLE IF EXISTS TempCNDN;
    CREATE TABLE TempCNDN AS
    select GLTransHd.TransDate, GLTransHd.TransDesc as Nmbr, GLTransDt.coacode, GLTransDt.coaname, 
    GLTransDt.dbhcamount as Debit_HC_Amount,GLTransDt.crhcamount as Credit_HC_Amount, SMCurrency.CurrCode,
    GLTransDt.transdbamount as Debit_Trans_Amount, GLTransDt.transcramount as Credit_Trans_Amount, TempDataInvoice.arnmbr, 
    Cast(NULL as varchar(100)) as Keterangan, ARCNDNHd.fgtranssource
    from GLTransDt
    inner join GLTransHd on GLTransDt.transhdid = GLTransHd.transhdid
    Left Outer Join SMCurrency on SMCurrency.currid = GLTransDt.transcurrid
    Left Outer Join ARCNDNHd on ARCNDNHd.transhdid = GLTransHd.transhdid
    Left Outer Join TempDataInvoice on TempDataInvoice.cndnnmbr =  GLTransHd.TransDesc
    where GLTransHd.transdate between '{$startdate_sql}' and '{$enddate_sql}' and 
    jurnaltype like 'AR%' and ARCNDNHd.fgtranssource in ('U','Z','G')
    and TempDataInvoice.cndnnmbr = GLTransHd.TransDesc ;

    -- Update Keterangan di TempCNDN
    UPDATE TempCNDN
    SET Keterangan = 
        CASE WHEN fgtranssource = 'U' THEN '3 - Deposit Customer'
             WHEN fgtranssource = 'Z' THEN '4 - Rate Difference General'
             WHEN fgtranssource = 'G' THEN '5 - General'
        END;
        
    -- Buat TempJurnal
    DROP TABLE IF EXISTS TempJurnal;
    CREATE TABLE TempJurnal AS
    select TempPenjualan.Keterangan, TempPenjualan.TransDate, TempPenjualan.Nmbr, TempPenjualan.coacode, TempPenjualan.coaname, 
    TempPenjualan.Debit_HC_Amount,TempPenjualan.Credit_HC_Amount, TempPenjualan.CurrCode,
    TempPenjualan.Debit_Trans_Amount, TempPenjualan.Credit_Trans_Amount, TempPenjualan.arnmbr
    from TempPenjualan

    union all

    select TempPembayaran.Keterangan, TempPembayaran.TransDate, TempPembayaran.nmbr, TempPembayaran.coacode, TempPembayaran.coaname, 
    TempPembayaran.Debit_HC_Amount,TempPembayaran.Credit_HC_Amount, TempPembayaran.CurrCode,
    TempPembayaran.Debit_Trans_Amount, TempPembayaran.Credit_Trans_Amount, TempPembayaran.arnmbr
    from TempPembayaran

    union all

    select TempCNDN.Keterangan, TempCNDN.TransDate, TempCNDN.Nmbr, TempCNDN.coacode, TempCNDN.coaname, 
    TempCNDN.Debit_HC_Amount,TempCNDN.Credit_HC_Amount, TempCNDN.CurrCode,
    TempCNDN.Debit_Trans_Amount, TempCNDN.Credit_Trans_Amount, TempCNDN.arnmbr
    from TempCNDN;
    ";

    // Eksekusi SQL multi-statement
    $conn3->exec($sql);

    // Query untuk mengambil semua data untuk export
    $select_query = "SELECT * FROM TempJurnal WHERE 1=1";
    if ($ar_filter) {
        $select_query .= " AND arnmbr ILIKE '%" . $ar_filter . "%'";
    }
    $select_query .= " ORDER BY arnmbr, keterangan, nmbr ASC";

    $stmt = $conn3->query($select_query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Commit
    $conn3->commit();

} catch (PDOException $e) {
    if ($conn3->inTransaction()) $conn3->rollBack();
    die("Error executing query: " . $e->getMessage());
}

// Hitung total untuk summary
$total_debit_hc = 0;
$total_credit_hc = 0;
$total_debit_trans = 0;
$total_credit_trans = 0;

if (!empty($results)) {
    foreach ($results as $row) {
        $total_debit_hc += floatval($row['debit_hc_amount'] ?? $row['Debit_HC_Amount'] ?? 0);
        $total_credit_hc += floatval($row['credit_hc_amount'] ?? $row['Credit_HC_Amount'] ?? 0);
        $total_debit_trans += floatval($row['debit_trans_amount'] ?? $row['Debit_Trans_Amount'] ?? 0);
        $total_credit_trans += floatval($row['credit_trans_amount'] ?? $row['Credit_Trans_Amount'] ?? 0);
    }
}

// Buat tabel HTML untuk diekspor
echo "<table border='1'>";
echo "<tr>
        <th colspan='12' style='background-color: #d9edf7; font-size: 16px; font-weight: bold;'>
            LAPORAN JURNAL PENJUALAN
        </th>
      </tr>";
echo "<tr>
        <th colspan='12' style='background-color: #f5f5f5;'>
            Periode: " . htmlspecialchars($startdate_input) . " s/d " . htmlspecialchars($enddate_input) . "
        </th>
      </tr>";

if ($ar_filter !== '') {
    echo "<tr>
            <th colspan='12' style='background-color: #f5f5f5;'>
                Filter AR Number: " . htmlspecialchars($ar_filter) . "
            </th>
          </tr>";
}

echo "<tr style='background-color: #f8f9fa; font-weight: bold;'>
        <th>No</th>
        <th>Keterangan</th>
        <th>Tanggal</th>
        <th>No. Bukti</th>
        <th>Kode Akun</th>
        <th>Nama Akun</th>
        <th>Currency</th>
        <th>Debit HC</th>
        <th>Credit HC</th>
        <th>Debit Trans</th>
        <th>Credit Trans</th>
        <th>AR Number</th>
      </tr>";

$no = 1;
foreach ($results as $row) {
    $keterangan = $row['keterangan'] ?? $row['Keterangan'] ?? '';
    $tanggal = !empty($row['transdate']) ? date("d/m/Y", strtotime($row['transdate'])) : '';
    
    echo "<tr>
            <td style='text-align: center;'>".$no++."</td>
            <td>".htmlspecialchars($keterangan)."</td>
            <td style='text-align: center;'>".$tanggal."</td>
            <td>".htmlspecialchars($row['nmbr'] ?? $row['Nmbr'] ?? $row['nmbr'])."</td>
            <td>".htmlspecialchars($row['coacode'] ?? '')."</td>
            <td>".htmlspecialchars($row['coaname'] ?? '')."</td>
            <td style='text-align: center;'>".htmlspecialchars($row['currcode'] ?? $row['CurrCode'] ?? '')."</td>
            <td style='text-align: right;'>".(is_numeric($row['debit_hc_amount'] ?? $row['Debit_HC_Amount'] ?? '') ? number_format($row['debit_hc_amount'] ?? $row['Debit_HC_Amount'],2) : '0.00')."</td>
            <td style='text-align: right;'>".(is_numeric($row['credit_hc_amount'] ?? $row['Credit_HC_Amount'] ?? '') ? number_format($row['credit_hc_amount'] ?? $row['Credit_HC_Amount'],2) : '0.00')."</td>
            <td style='text-align: right;'>".(is_numeric($row['debit_trans_amount'] ?? $row['Debit_Trans_Amount'] ?? '') ? number_format($row['debit_trans_amount'] ?? $row['Debit_Trans_Amount'],2) : '0.00')."</td>
            <td style='text-align: right;'>".(is_numeric($row['credit_trans_amount'] ?? $row['Credit_Trans_Amount'] ?? '') ? number_format($row['credit_trans_amount'] ?? $row['Credit_Trans_Amount'],2) : '0.00')."</td>
            <td>".htmlspecialchars($row['arnmbr'] ?? $row['Arnmbr'] ?? '')."</td>
          </tr>";
}

// Baris total
echo "<tr style='background-color: #e9ecef; font-weight: bold;'>
        <td colspan='7' style='text-align: center;'>TOTAL</td>
        <td style='text-align: right;'>".number_format($total_debit_hc, 2)."</td>
        <td style='text-align: right;'>".number_format($total_credit_hc, 2)."</td>
        <td style='text-align: right;'>".number_format($total_debit_trans, 2)."</td>
        <td style='text-align: right;'>".number_format($total_credit_trans, 2)."</td>
        <td></td>
      </tr>";

echo "</table>";

// Tambahkan informasi footer
echo "<div style='margin-top: 20px; font-size: 12px; color: #6c757d;'>
        <p>Dicetak pada: " . date('d/m/Y H:i:s') . "</p>
        <p>Oleh: " . htmlspecialchars($_SESSION['UserName']) . "</p>
        <p>Total Data: " . number_format(count($results)) . " records</p>
      </div>";

// Clean up temporary tables (optional)
try {
    $conn3->exec("DROP TABLE IF EXISTS TempDataInvoice, TempPenjualan, TempPembayaran, TempCNDN, TempJurnal;");
} catch (PDOException $e) {
    // Ignore cleanup errors
}