<?php
session_start();
ob_start();
include '../../koneksi.php';   // dipakai untuk permission check (asumsi SQL Server / conn)
include '../../koneksi3.php';  // dipakai untuk koneksi PostgreSQL via PDO sebagai $conn3
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Permission Check (menggunakan $conn, seperti pola di projectmu)
$groupId = $_SESSION['GroupId'];
$menuId  = 133; // Sesuaikan dengan menu ID untuk jurnal penjualan
if (!$conn) {
    die("Koneksi permission (conn) gagal.");
}
$sqlPerm = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmtPerm = sqlsrv_query($conn, $sqlPerm, array($groupId, $menuId));
$permissions = ($stmtPerm && $rowPerm = sqlsrv_fetch_array($stmtPerm, SQLSRV_FETCH_ASSOC)) ? $rowPerm : [];
sqlsrv_free_stmt($stmtPerm);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

// Default tanggal (jika tidak di-submit)
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

// Konfigurasi pagination untuk performa
$records_per_page = isset($_GET['records_per_page']) ? (int)$_GET['records_per_page'] : 100;
if ($records_per_page > 1000) $records_per_page = 1000; // Batasi maksimal
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

$results = [];
$total_records = 0;
$errorMsg = null;
$execution_time = 0;
$query_time = 0;

// FLAG: Cek apakah form sudah disubmit (ada parameter filter)
$form_submitted = isset($_GET['startdate']) || isset($_GET['enddate']) || isset($_GET['ar_filter']);

// Pastikan koneksi PostgreSQL ($conn3) ada
if (!$conn3) {
    $errorMsg = "Koneksi ke database PostgreSQL gagal. Periksa \$conn3.";
} elseif ($form_submitted) {
    // HANYA jalankan query jika form sudah disubmit
    try {
        $start_time = microtime(true);
        
        // Mulai transaksi agar sementara tabel temp bisa dibuat dan di-drop dengan aman
        $conn3->beginTransaction();

        // Buat temporary table sesuai script SQL yang diberikan
        $sql = "
        -- Hapus & buat TempDataInvoice dengan index
        DROP TABLE IF EXISTS TempDataInvoice;
        CREATE TEMPORARY TABLE TempDataInvoice (
            arnmbr varchar(100),
            ARDate date,
            arcrnmbr varchar(100),
            cndnnmbr varchar(100),
            fgtranssource varchar(10),
            CRNmbr varchar(100),
            CR_No varchar(100)
        );
        
        CREATE INDEX idx_temp_arnmbr ON TempDataInvoice (arnmbr);
        CREATE INDEX idx_temp_date ON TempDataInvoice (ARDate);
        
        -- Insert data ke TempDataInvoice
        INSERT INTO TempDataInvoice
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
        CREATE TEMPORARY TABLE TempPenjualan (
            TransDate date,
            Nmbr varchar(100),
            coacode varchar(50),
            coaname varchar(200),
            Debit_HC_Amount numeric(18,2),
            Credit_HC_Amount numeric(18,2),
            CurrCode varchar(10),
            Debit_Trans_Amount numeric(18,2),
            Credit_Trans_Amount numeric(18,2),
            arnmbr varchar(100),
            Keterangan varchar(100)
        );
        
        CREATE INDEX idx_penjualan_arnmbr ON TempPenjualan (arnmbr);
        
        INSERT INTO TempPenjualan
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
        CREATE TEMPORARY TABLE TempPembayaran (
            TransDate date,
            nmbr varchar(100),
            coacode varchar(50),
            coaname varchar(200),
            Debit_HC_Amount numeric(18,2),
            Credit_HC_Amount numeric(18,2),
            CurrCode varchar(10),
            Debit_Trans_Amount numeric(18,2),
            Credit_Trans_Amount numeric(18,2),
            arnmbr varchar(100),
            Keterangan varchar(100)
        );
        
        CREATE INDEX idx_pembayaran_arnmbr ON TempPembayaran (arnmbr);
        
        INSERT INTO TempPembayaran
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
        CREATE TEMPORARY TABLE TempCNDN (
            TransDate date,
            Nmbr varchar(100),
            coacode varchar(50),
            coaname varchar(200),
            Debit_HC_Amount numeric(18,2),
            Credit_HC_Amount numeric(18,2),
            CurrCode varchar(10),
            Debit_Trans_Amount numeric(18,2),
            Credit_Trans_Amount numeric(18,2),
            arnmbr varchar(100),
            Keterangan varchar(100),
            fgtranssource varchar(10)
        );
        
        CREATE INDEX idx_cndn_arnmbr ON TempCNDN (arnmbr);
        
        INSERT INTO TempCNDN
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
        CREATE TEMPORARY TABLE TempJurnal (
            Keterangan varchar(100),
            TransDate date,
            Nmbr varchar(100),
            coacode varchar(50),
            coaname varchar(200),
            Debit_HC_Amount numeric(18,2),
            Credit_HC_Amount numeric(18,2),
            CurrCode varchar(10),
            Debit_Trans_Amount numeric(18,2),
            Credit_Trans_Amount numeric(18,2),
            arnmbr varchar(100)
        );
        
        CREATE INDEX idx_jurnal_arnmbr ON TempJurnal (arnmbr);
        CREATE INDEX idx_jurnal_date ON TempJurnal (TransDate);
        
        INSERT INTO TempJurnal
        select TempPenjualan.Keterangan, TempPenjualan.TransDate, TempPenjualan.Nmbr, TempPenjualan.coacode, TempPenjualan.coaname, 
        TempPenjualan.Debit_HC_Amount,TempPenjualan.Credit_HC_Amount, TempPenjualan.CurrCode,
        TempPenjualan.Debit_Trans_Amount, TempPenjualan.Credit_Trans_Amount, TempPenjualan.arnmbr
        from TempPenjualan

        union 

        select TempPembayaran.Keterangan, TempPembayaran.TransDate, TempPembayaran.nmbr, TempPembayaran.coacode, TempPembayaran.coaname, 
        TempPembayaran.Debit_HC_Amount,TempPembayaran.Credit_HC_Amount, TempPembayaran.CurrCode,
        TempPembayaran.Debit_Trans_Amount, TempPembayaran.Credit_Trans_Amount, TempPembayaran.arnmbr
        from TempPembayaran

        union

        select TempCNDN.Keterangan, TempCNDN.TransDate, TempCNDN.Nmbr, TempCNDN.coacode, TempCNDN.coaname, 
        TempCNDN.Debit_HC_Amount,TempCNDN.Credit_HC_Amount, TempCNDN.CurrCode,
        TempCNDN.Debit_Trans_Amount, TempCNDN.Credit_Trans_Amount, TempCNDN.arnmbr
        from TempCNDN;
        ";

        // Eksekusi SQL multi-statement
        $conn3->exec($sql);
        $query_time = microtime(true) - $start_time;

        // Query untuk mengambil total records (cepat)
        $count_query = "SELECT COUNT(*) as total FROM TempJurnal WHERE 1=1";
        if ($ar_filter) {
            $count_query .= " AND arnmbr ILIKE '%" . $ar_filter . "%'";
        }
        
        $stmt_count = $conn3->query($count_query);
        $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

        // Query untuk mengambil data dengan pagination
        $select_query = "SELECT * FROM TempJurnal WHERE 1=1";
        if ($ar_filter) {
            $select_query .= " AND arnmbr ILIKE '%" . $ar_filter . "%'";
        }
        $select_query .= " ORDER BY arnmbr, keterangan, nmbr ASC LIMIT {$records_per_page} OFFSET {$offset}";

        $stmt = $conn3->query($select_query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Commit
        $conn3->commit();

        // Hitung waktu eksekusi total
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 3);

    } catch (PDOException $e) {
        if ($conn3->inTransaction()) $conn3->rollBack();
        $errorMsg = "Error executing query: " . $e->getMessage();
    }
}

// Hitung total untuk summary (hitung dari data yang ditampilkan saja untuk performa)
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

// Hitung total halaman
$total_pages = ceil($total_records / $records_per_page);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

// Generate parameter URL untuk pagination
$url_params = http_build_query([
    'startdate' => $startdate_input,
    'enddate' => $enddate_input,
    'ar_filter' => $ar_filter,
    'records_per_page' => $records_per_page
]);
?>

    <style>
        .table-sm th, .table-sm td { 
            padding: 0.5rem;
            font-size: 0.875rem;
        }
       
        .keterangan-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .execution-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .summary-card .card-body {
            padding: 0.75rem;
        }
        .pagination-custom .page-link {
            color: #495057;
        }
        .pagination-custom .page-item.active .page-link {
            background-color: <?php 
                $colorMap = [
                    'primary' => '#007bff',
                    'success' => '#28a745', 
                    'info' => '#17a2b8',
                    'warning' => '#ffc107',
                    'danger' => '#dc3545',
                    'secondary' => '#6c757d'
                ];
                echo $colorMap[$themeColor] ?? '#007bff';
            ?>;
            border-color: <?php echo $colorMap[$themeColor] ?? '#007bff'; ?>;
        }
        .records-per-page {
            max-width: 120px;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .stats-badge {
            font-size: 0.7rem;
        }
        .welcome-message {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid <?php echo $colorMap[$themeColor] ?? '#007bff'; ?>;
        }
    </style>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Jurnal Penjualan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Jurnal Penjualan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">               
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title">Laporan Jurnal Penjualan</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="form-inline mb-3" id="filterForm">
                                    <div class="form-group mr-2">
                                        <label for="startdate" class="mr-2">Tanggal Mulai:</label>
                                        <input type="date" id="startdate" name="startdate" class="form-control form-control-sm" 
                                               value="<?= htmlspecialchars($startdate_input) ?>" required>
                                    </div>
                                    
                                    <div class="form-group mr-2">
                                        <label for="enddate" class="mr-2">Tanggal Akhir:</label>
                                        <input type="date" id="enddate" name="enddate" class="form-control form-control-sm" 
                                               value="<?= htmlspecialchars($enddate_input) ?>" required>
                                    </div>
                                    
                                    <div class="form-group mr-2">
                                        <label for="ar_filter" class="mr-2">Filter AR Number:</label>
                                        <input type="text" id="ar_filter" name="ar_filter" class="form-control form-control-sm" 
                                               value="<?= htmlspecialchars($ar_filter) ?>" placeholder="Masukkan AR Number">
                                    </div>
                                    
                                    <div class="form-group mr-2">
                                        <label for="records_per_page" class="mr-2">Show:</label>
                                        <select id="records_per_page" name="records_per_page" class="form-control form-control-sm records-per-page">
                                            <option value="50" <?= $records_per_page == 50 ? 'selected' : '' ?>>50</option>
                                            <option value="100" <?= $records_per_page == 100 ? 'selected' : '' ?>>100</option>
                                            <option value="250" <?= $records_per_page == 250 ? 'selected' : '' ?>>250</option>
                                            <option value="500" <?= $records_per_page == 500 ? 'selected' : '' ?>>500</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm mr-1" id="submitBtn">
                                        <i class="fas fa-search"></i> Tampilkan
                                    </button>
                                    <a href="?" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </form>

                                <?php if ($errorMsg) : ?>
                                    <div class="alert alert-danger">
                                        <?= htmlspecialchars($errorMsg) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$form_submitted): ?>
                                   
                                <?php elseif (!empty($results)) : ?>
                                   
                                    <!-- Toolbar -->
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <a href="export_excel_jurnal_penjualan.php?startdate=<?= urlencode($startdate_input) ?>&enddate=<?= urlencode($enddate_input) ?>&ar_filter=<?= urlencode($ar_filter) ?>" 
                                               class="btn btn-success btn-sm mr-1">
                                               <i class="fas fa-file-excel"></i> Export to Excel
                                            </a>
                                        </div>
                                        
                                        <?php if ($total_pages > 1) : ?>
                                        <div class="d-flex align-items-center">
                                            <span class="mr-2">Halaman <?= $page ?> dari <?= $total_pages ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Data Table -->
                                    <div class="table-responsive">                 
                                        <table id="jurnalTable" class="table table-hover table-sm table-striped">
                                            <thead class="thead-light">
                                                <tr class="text-center align-middle">
                                                    <th width="50">No</th>
                                                    <th width="150">Keterangan</th>
                                                    <th width="100">Tanggal</th>
                                                    <th width="120">No. Bukti</th>
                                                    <th width="100">Kode Akun</th>
                                                    <th>Nama Akun</th>
                                                    <th width="80">Currency</th>
                                                    <th width="120">Debit HC</th>
                                                    <th width="120">Credit HC</th>
                                                    <th width="120">Debit Trans</th>
                                                    <th width="120">Credit Trans</th>
                                                    <th width="120">AR Number</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $no = $offset + 1;
                                            foreach ($results as $row) {
                                                $keterangan = $row['keterangan'] ?? $row['Keterangan'] ?? '';
                                                $keterangan_class = '';
                                                if (strpos($keterangan, '1 -') !== false) $keterangan_class = 'bg-primary';
                                                if (strpos($keterangan, '2 -') !== false) $keterangan_class = 'bg-success';
                                                if (strpos($keterangan, '3 -') !== false) $keterangan_class = 'bg-info';
                                                if (strpos($keterangan, '4 -') !== false) $keterangan_class = 'bg-warning';
                                                if (strpos($keterangan, '5 -') !== false) $keterangan_class = 'bg-secondary';
                                                
                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";
                                                echo "<td><span class='badge keterangan-badge {$keterangan_class}'>".htmlspecialchars($keterangan)."</span></td>";
                                                echo "<td class='text-center'>".(!empty($row['transdate']) ? date("d/m/Y", strtotime($row['transdate'])) : '-')."</td>";
                                                echo "<td><small>".htmlspecialchars($row['nmbr'] ?? $row['Nmbr'] ?? $row['nmbr'])."</small></td>";
                                                echo "<td><code>".htmlspecialchars($row['coacode'] ?? '')."</code></td>";
                                                echo "<td><small>".htmlspecialchars($row['coaname'] ?? '')."</small></td>";
                                                echo "<td class='text-center'><span class='badge bg-light text-dark'>".htmlspecialchars($row['currcode'] ?? $row['CurrCode'] ?? '')."</span></td>";
                                                echo "<td class='text-right'><small>".(is_numeric($row['debit_hc_amount'] ?? $row['Debit_HC_Amount'] ?? '') ? number_format($row['debit_hc_amount'] ?? $row['Debit_HC_Amount'],2) : '0.00')."</small></td>";
                                                echo "<td class='text-right'><small>".(is_numeric($row['credit_hc_amount'] ?? $row['Credit_HC_Amount'] ?? '') ? number_format($row['credit_hc_amount'] ?? $row['Credit_HC_Amount'],2) : '0.00')."</small></td>";
                                                echo "<td class='text-right'><small>".(is_numeric($row['debit_trans_amount'] ?? $row['Debit_Trans_Amount'] ?? '') ? number_format($row['debit_trans_amount'] ?? $row['Debit_Trans_Amount'],2) : '0.00')."</small></td>";
                                                echo "<td class='text-right'><small>".(is_numeric($row['credit_trans_amount'] ?? $row['Credit_Trans_Amount'] ?? '') ? number_format($row['credit_trans_amount'] ?? $row['Credit_Trans_Amount'],2) : '0.00')."</small></td>";
                                                echo "<td><small>".htmlspecialchars($row['arnmbr'] ?? $row['Arnmbr'] ?? '')."</small></td>";
                                                echo "</tr>";
                                            }
                                            ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="font-weight-bold" style="background-color: #f8f9fa;">
                                                    <td colspan="7" class="text-right"><small>Total Halaman Ini:</small></td>
                                                    <td class="text-right"><small><?= number_format($total_debit_hc, 2) ?></small></td>
                                                    <td class='text-right'><small><?= number_format($total_credit_hc, 2) ?></small></td>
                                                    <td class='text-right'><small><?= number_format($total_debit_trans, 2) ?></small></td>
                                                    <td class='text-right'><small><?= number_format($total_credit_trans, 2) ?></small></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1) : ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <small>Menampilkan <?= $offset + 1 ?> sampai <?= min($offset + $records_per_page, $total_records) ?> dari <?= number_format($total_records) ?> data</small>
                                        </div>
                                        <nav>
                                            <ul class="pagination pagination-sm pagination-custom">
                                                <!-- First Page -->
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="?<?= $url_params ?>&page=1" aria-label="First">
                                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                                    </a>
                                                </li>
                                                
                                                <!-- Previous Page -->
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="?<?= $url_params ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                                
                                                <!-- Page Numbers -->
                                                <?php
                                                $start_page = max(1, $page - 2);
                                                $end_page = min($total_pages, $page + 2);
                                                
                                                for ($i = $start_page; $i <= $end_page; $i++) : 
                                                ?>
                                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                        <a class="page-link" href="?<?= $url_params ?>&page=<?= $i ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <!-- Next Page -->
                                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="?<?= $url_params ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                                
                                                <!-- Last Page -->
                                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="?<?= $url_params ?>&page=<?= $total_pages ?>" aria-label="Last">
                                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                    <?php endif; ?>

                                <?php elseif ($form_submitted && empty($results)) : ?>
                                    <div class="alert alert-warning">
                                        Data tidak ditemukan untuk periode <?= htmlspecialchars($startdate_input) ?> hingga <?= htmlspecialchars($enddate_input) ?>
                                        <?php if ($ar_filter !== '') : ?>
                                            dengan filter AR Number: <strong><?= htmlspecialchars($ar_filter) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-border text-<?php echo htmlspecialchars($themeColor);?>" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <p class="mt-2">Memproses data...</p>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        // Show loading on form submit
        $('#filterForm').on('submit', function() {
            $('#loadingOverlay').show();
        });
        
        // Initialize DataTable only if there are results
        <?php if (!empty($results)): ?>
        $("#jurnalTable").DataTable({
            responsive: true,
            destroy: true,
            autoWidth: false,
            ordering: true,
            paging: false, // We use custom pagination
            searching: true,
            info: false,
            dom: 'lrtip', // Minimal DOM elements
            language: {
                search: "Cari:",
                zeroRecords: "Data tidak ditemukan"
            }
        });
        <?php endif; ?>
        
        // Hide loading when page fully loaded
        $(window).on('load', function() {
            $('#loadingOverlay').hide();
        });
    });
    
    // Auto-hide loading after 5 seconds (safety net)
    setTimeout(function() {
        $('#loadingOverlay').hide();
    }, 5000);
</script>
