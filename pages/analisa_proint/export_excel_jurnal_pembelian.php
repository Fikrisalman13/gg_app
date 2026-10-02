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
$grn_filter = isset($_GET['grn_filter']) ? trim($_GET['grn_filter']) : '';

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
header("Content-Disposition: attachment; filename=Jurnal_Pembelian_" . date('Ymd') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // Mulai transaksi agar sementara tabel temp bisa dibuat dan di-drop dengan aman
    $conn3->beginTransaction();

    // Susun SQL multi-statement (mengganti hardcoded dates dengan parameter tanggal)
    $sql = "
    -- Hapus & buat TempDataPurchaseInvoice
    DROP TABLE IF EXISTS TempDataPurchaseInvoice;
    CREATE TABLE TempDataPurchaseInvoice AS
    select PRGRNHd.GRNnmbr, PRGRNHd.GRNDate, APCNDNHd.cndnnmbr, APCNDNHd.fgtranssource, CMCPHd.CPNmbr, cast (null as varchar(100)) as GLTransNmbr
    from PRGRNHd
    Left Outer Join APPosting on APPosting.apnmbr = PRGRNHd.grnnmbr
    Left Outer Join APCNDNAlloc on APCNDNAlloc.apid =  APPosting.apid
    Left Outer Join APCNDNHd on APCNDNHd.cndnhdid =  APCNDNAlloc.cndnhdid
    Left Outer Join CMTTTAP on CMTTTAP.apid =  APPosting.apid
    Left Outer Join CMCPHd on CMCPHd.cphdid =  CMTTTAP.cphdid
    where PRGRNHd.GRNDate between '{$startdate_sql}' and '{$enddate_sql}' and CMCPHd.CPNmbr is not null 
    
    union
    select distinct PRGRNHd.GRNnmbr, PRGRNHd.GRNDate, APCNDNHd.cndnnmbr, APCNDNHd.fgtranssource, CMCPHd.CPNmbr, cast (null as varchar(100)) as GLTransNmbr 
    from PRGRNHd
    Left Outer Join PRGRNDt on PRGRNDt.grnhdid = PRGRNHd.grnhdid
    Left Outer Join APPosting on APPosting.apnmbr = PRGRNHd.grnnmbr
    Left Outer Join APCNDNAlloc on APCNDNAlloc.apid =  APPosting.apid
    Left Outer Join APCNDNHd on APCNDNHd.cndnhdid =  APCNDNAlloc.cndnhdid
    Left Outer Join PRPOHd on PRPOHd.pohdid = PRGRNDt.pohdid
    Left Outer Join PRPOTOP on PRPOTOP.pohdid = PRPOHd.pohdid
    Left Outer Join CMTTTAP on CMTTTAP.potopid =  PRPOTOP.potopid
    Left Outer Join CMCPHd on CMCPHd.cphdid =  CMTTTAP.cphdid
    Left Outer Join PASettlementDt on PASettlementDt.pohdid =  PRPOHd.pohdid
    Left Outer Join PASettlementHd on PASettlementHd.settleid =  PASettlementDt.settleid
    where PRGRNHd.GRNDate between '{$startdate_sql}' and '{$enddate_sql}' and CMCPHd.CPNmbr is not null
    
    union
    select distinct PRGRNHd.GRNnmbr, PRGRNHd.GRNDate, APCNDNHd.cndnnmbr, APCNDNHd.fgtranssource, cast (null as varchar(100)) as CPNmbr, PASettlementHd.GLTransNmbr 
    from PRGRNHd
    Left Outer Join PRGRNDt on PRGRNDt.grnhdid = PRGRNHd.grnhdid
    Left Outer Join APPosting on APPosting.apnmbr = PRGRNHd.grnnmbr
    Left Outer Join APCNDNAlloc on APCNDNAlloc.apid =  APPosting.apid
    Left Outer Join APCNDNHd on APCNDNHd.cndnhdid =  APCNDNAlloc.cndnhdid
    Left Outer Join PRPOHd on PRPOHd.pohdid = PRGRNDt.pohdid
    Left Outer Join PRPOTOP on PRPOTOP.pohdid = PRPOHd.pohdid
    Left Outer Join CMTTTAP on CMTTTAP.potopid =  PRPOTOP.potopid
    Left Outer Join CMCPHd on CMCPHd.cphdid =  CMTTTAP.cphdid
    Left Outer Join PASettlementDt on PASettlementDt.pohdid =  PRPOHd.pohdid
    Left Outer Join PASettlementHd on PASettlementHd.settleid =  PASettlementDt.settleid
    where PRGRNHd.GRNDate between '{$startdate_sql}' and '{$enddate_sql}' and CMCPHd.CPNmbr is not null and PASettlementHd.rfphdid is not null
    
    union
    select PRGRNHd.GRNnmbr, PRGRNHd.GRNDate, cast (null as varchar(100)) as cndnnmbr, cast (null as varchar(100)) as fgtranssource, cast (null as varchar(100)) as CPNmbr, cast (null as varchar(100)) as GLTransNmbr
    from PRGRNHd
    Left Outer Join APPosting on APPosting.apnmbr = PRGRNHd.grnnmbr
    Left Outer Join APCNDNAlloc on APCNDNAlloc.apid =  APPosting.apid
    Left Outer Join APCNDNHd on APCNDNHd.cndnhdid =  APCNDNAlloc.cndnhdid
    Left Outer Join CMTTTAP on CMTTTAP.apid =  APPosting.apid
    Left Outer Join CMCPHd on CMCPHd.cphdid =  CMTTTAP.cphdid
    where PRGRNHd.GRNDate between '{$startdate_sql}' and '{$enddate_sql}' 
    ;

    -- TempPembelian
    DROP TABLE IF EXISTS TempPembelian;
    CREATE TABLE TempPembelian AS
    select distinct GlTransHd.Transhdid as id, GLTransHd.TransDate, GLTransHd.TransDesc as Nmbr, GLTransDt.coacode, GLTransDt.coaname, 
    GLTransDt.dbhcamount as Debit_HC_Amount,GLTransDt.crhcamount as Credit_HC_Amount, SMCurrency.CurrCode,
    GLTransDt.transdbamount as Debit_Trans_Amount, GLTransDt.transcramount as Credit_Trans_Amount, TempDataPurchaseInvoice.GRNnmbr,
    Cast('1 - Pengakuan Pembelian' as varchar(100)) as Keterangan
    from GLTransDt
    inner join GLTransHd on GLTransDt.transhdid = GLTransHd.transhdid
    Left Outer Join SMCurrency on SMCurrency.currid = GLTransDt.transcurrid
    Left Outer Join TempDataPurchaseInvoice on TempDataPurchaseInvoice.grnnmbr =  GLTransHd.TransDesc
    where ((jurnaltype like 'PR%') or (jurnaltype like 'PA%'))and TempDataPurchaseInvoice.grnnmbr =  GLTransHd.TransDesc
    order by id;

    -- TempPembayaran
    DROP TABLE IF EXISTS TempPembayaran;
    CREATE TABLE TempPembayaran AS
    select GlTransHd.Transhdid as id, GLTransHd.TransDate, GLTransHd.TransNmbr as nmbr, GLTransDt.coacode, GLTransDt.coaname, 
    GLTransDt.dbhcamount as Debit_HC_Amount,GLTransDt.crhcamount as Credit_HC_Amount, SMCurrency.CurrCode,
    GLTransDt.transdbamount as Debit_Trans_Amount, GLTransDt.transcramount as Credit_Trans_Amount, TempDataPurchaseInvoice.GRNnmbr,
    Cast('2 - Pembayaran' as varchar(100)) as Keterangan
    from GLTransDt
    inner join GLTransHd on GLTransDt.transhdid = GLTransHd.transhdid
    Left Outer Join SMCurrency on SMCurrency.currid = GLTransDt.transcurrid
    Left Outer Join TempDataPurchaseInvoice on TempDataPurchaseInvoice.cpnmbr =  GLTransHd.TransNmbr
    where jurnaltype like 'CP%' and TempDataPurchaseInvoice.cpnmbr =  GLTransHd.TransNmbr
    order by id;

    -- TempCNDN
    DROP TABLE IF EXISTS TempCNDN;
    CREATE TABLE TempCNDN AS
    select GlTransHd.Transhdid as id, GLTransHd.TransDate, GLTransHd.TransDesc as Nmbr, GLTransDt.coacode, GLTransDt.coaname, 
    GLTransDt.dbhcamount as Debit_HC_Amount,GLTransDt.crhcamount as Credit_HC_Amount, SMCurrency.CurrCode,
    GLTransDt.transdbamount as Debit_Trans_Amount, GLTransDt.transcramount as Credit_Trans_Amount, TempDataPurchaseInvoice.GRNnmbr, 
    Cast(NULL as varchar(100)) as Keterangan, APCNDNHd.fgtranssource
    from GLTransDt
    inner join GLTransHd on GLTransDt.transhdid = GLTransHd.transhdid
    Left Outer Join SMCurrency on SMCurrency.currid = GLTransDt.transcurrid
    Left Outer Join APCNDNHd on APCNDNHd.transhdid = GLTransHd.transhdid
    Left Outer Join TempDataPurchaseInvoice on TempDataPurchaseInvoice.cndnnmbr =  GLTransHd.TransDesc
    where jurnaltype like 'AP%' and APCNDNHd.fgtranssource in ('U','S','G')
    and TempDataPurchaseInvoice.cndnnmbr = GLTransHd.TransDesc;

    -- update TempCNDN Keterangan
    UPDATE TempCNDN
    SET Keterangan = CASE
        WHEN fgtranssource = 'U' THEN '3 - Deposit Supplier'
        WHEN fgtranssource = 'S' THEN '4 - Rate Difference General'
        WHEN fgtranssource = 'G' THEN '5 - General'
        ELSE Keterangan
    END;

    -- TempRateDiff
    DROP TABLE IF EXISTS TempRateDiff;
    CREATE TABLE TempRateDiff AS
    select GlTransHd.Transhdid as id, GLTransHd.TransDate, GLTransHd.TransNmbr as Nmbr, GLTransDt.coacode, GLTransDt.coaname, 
    GLTransDt.dbhcamount as Debit_HC_Amount,GLTransDt.crhcamount as Credit_HC_Amount, SMCurrency.CurrCode,
    GLTransDt.transdbamount as Debit_Trans_Amount, GLTransDt.transcramount as Credit_Trans_Amount, TempDataPurchaseInvoice.GRNnmbr, 
    Cast('4 - Rate Difference General' as varchar(100)) as Keterangan
    from GLTransDt
    inner join GLTransHd on GLTransDt.transhdid = GLTransHd.transhdid
    Left Outer Join SMCurrency on SMCurrency.currid = GLTransDt.transcurrid
    Left Outer Join APCNDNHd on APCNDNHd.transhdid = GLTransHd.transhdid
    Left Outer Join TempDataPurchaseInvoice on TempDataPurchaseInvoice.GLTransNmbr =  GLTransHd.TransNmbr
    where  TempDataPurchaseInvoice.GLTransNmbr = GLTransHd.TransNmbr;

    -- TempJurnal (gabungan)
    DROP TABLE IF EXISTS TempJurnal;
    CREATE TABLE TempJurnal AS
    select TempPembelian.Keterangan, TempPembelian.TransDate, TempPembelian.Nmbr, TempPembelian.coacode, TempPembelian.coaname, 
    TempPembelian.Debit_HC_Amount,TempPembelian.Credit_HC_Amount, TempPembelian.CurrCode,
    TempPembelian.Debit_Trans_Amount, TempPembelian.Credit_Trans_Amount, TempPembelian.grnnmbr
    from TempPembelian

    union all

    select TempPembayaran.Keterangan, TempPembayaran.TransDate, TempPembayaran.Nmbr, TempPembayaran.coacode, TempPembayaran.coaname, 
    TempPembayaran.Debit_HC_Amount,TempPembayaran.Credit_HC_Amount, TempPembayaran.CurrCode,
    TempPembayaran.Debit_Trans_Amount, TempPembayaran.Credit_Trans_Amount, TempPembayaran.Grnnmbr
    from TempPembayaran

    union all

    select TempCNDN.Keterangan, TempCNDN.TransDate, TempCNDN.Nmbr, TempCNDN.coacode, TempCNDN.coaname, 
    TempCNDN.Debit_HC_Amount,TempCNDN.Credit_HC_Amount, TempCNDN.CurrCode,
    TempCNDN.Debit_Trans_Amount, TempCNDN.Credit_Trans_Amount, TempCNDN.Grnnmbr
    from TempCNDN

    union all

    select TempRateDiff.Keterangan, TempRateDiff.TransDate, TempRateDiff.Nmbr, TempRateDiff.coacode, TempRateDiff.coaname, 
    TempRateDiff.Debit_HC_Amount,TempRateDiff.Credit_HC_Amount, TempRateDiff.CurrCode,
    TempRateDiff.Debit_Trans_Amount, TempRateDiff.Credit_Trans_Amount, TempRateDiff.Grnnmbr
    from TempRateDiff;
    ";

    // Eksekusi SQL multi-statement
    $conn3->exec($sql);

    // Query untuk mengambil semua data untuk export
    $select_query = "SELECT * FROM TempJurnal WHERE 1=1";
    if ($grn_filter) {
        $select_query .= " AND grnnmbr ILIKE '%" . $grn_filter . "%'";
    }
    $select_query .= " ORDER BY grnnmbr, keterangan, nmbr ASC";

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
            LAPORAN JURNAL PEMBELIAN
        </th>
      </tr>";
echo "<tr>
        <th colspan='12' style='background-color: #f5f5f5;'>
            Periode: " . htmlspecialchars($startdate_input) . " s/d " . htmlspecialchars($enddate_input) . "
        </th>
      </tr>";

if ($grn_filter !== '') {
    echo "<tr>
            <th colspan='12' style='background-color: #f5f5f5;'>
                Filter GRN: " . htmlspecialchars($grn_filter) . "
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
        <th>GRN Number</th>
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
            <td>".htmlspecialchars($row['grnnmbr'] ?? $row['Grnnmbr'] ?? '')."</td>
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
    $conn3->exec("DROP TABLE IF EXISTS TempDataPurchaseInvoice, TempPembelian, TempPembayaran, TempCNDN, TempRateDiff, TempJurnal;");
} catch (PDOException $e) {
    // Ignore cleanup errors
}