<?php
/**
 * Backend API Tracking PO Online
 * Mengambil data pengadaan online dari database ERP PostgreSQL (koneksi3.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';  // SQL Server GG (READ/WRITE)
require_once $rootPath . '/koneksi3.php'; // PostgreSQL ERP (READ-ONLY)

header('Content-Type: application/json; charset=utf-8');

// Cek autentikasi login
if (empty($_SESSION['UserName'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesi login tidak valid atau telah berakhir.'
    ]);
    exit;
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? 'load_data');

// Helper untuk format tanggal aman
function formatTgl($dateStr) {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    return $time ? date('d/m/Y', $time) : '-';
}

// Helper untuk format angka
function formatQty($val) {
    if ($val === null || $val === '') return '-';
    $num = (float)$val;
    return (floor($num) == $num) ? number_format($num, 0, ',', '.') : number_format($num, 2, ',', '.');
}

// Helper untuk format uang
function formatRupiah($val) {
    if ($val === null || $val === '') return '-';
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

try {
    if ($action === 'get_summary') {
        // Parameter filter tanggal
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        $whereClause = "WHERE prpohd.poorgid = '22'";
        $params = [];

        if (!empty($dateFrom)) {
            $whereClause .= " AND CAST(prpohd.podate AS DATE) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $whereClause .= " AND CAST(prpohd.podate AS DATE) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        $baseSql = "SELECT
            prpohd.ponmbr,
            prgrnhd.grnnmbr,
            pay.cpnmbr,
            pay.cp_status
        FROM
            prpohd
            LEFT JOIN prpodt ON prpohd.pohdid = prpodt.pohdid
            LEFT JOIN prgrndt ON prpodt.podtid = prgrndt.podtid
            LEFT JOIN prgrnhd ON prgrndt.grnhdid = prgrnhd.grnhdid
            LEFT JOIN LATERAL (
                SELECT 
                    STRING_AGG(DISTINCT sub.cpnmbr, ', ') AS cpnmbr,
                    MAX(sub.cpdate) AS cpdate,
                    CASE 
                        WHEN BOOL_OR(sub.cp_status = 'V') THEN 'V'
                        WHEN BOOL_OR(sub.cp_status = 'O') THEN 'O'
                        ELSE MAX(sub.cp_status)
                    END AS cp_status
                FROM (
                    -- 1. Pembayaran AP Invoice setelah penerimaan barang (GRN -> APPosting -> CMCP)
                    SELECT 
                        cp.cpnmbr,
                        cp.cpdate,
                        cp.fgstatus AS cp_status
                    FROM prgrndt gd
                    JOIN apposting ap ON ap.grnhdid = gd.grnhdid
                    JOIN cmcpdt cpd ON cpd.apid = ap.apid
                    JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
                    WHERE gd.pohdid = prpohd.pohdid
                      AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> ''

                    UNION ALL

                    -- 2. Pembayaran Uang Muka / Langsung PO (CMTTTAP -> CMCP)
                    SELECT 
                        COALESCE(cp.cpnmbr, t.cpnmbr) AS cpnmbr,
                        COALESCE(cp.cpdate, t.cpdate) AS cpdate,
                        cp.fgstatus AS cp_status
                    FROM cmtttap t
                    LEFT JOIN cmcphd cp ON cp.cphdid = t.cphdid
                    WHERE t.pohdid = prpohd.pohdid
                      AND ((t.cpnmbr IS NOT NULL AND TRIM(t.cpnmbr) <> '') OR (cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> ''))

                    UNION ALL

                    -- 3. Pembayaran via Settlement Uang Muka Impor (PASettlement -> CMCP)
                    SELECT 
                        cp.cpnmbr,
                        cp.cpdate,
                        cp.fgstatus AS cp_status
                    FROM pasettlementdt sdt
                    JOIN pasettlementhd sh ON sh.settleid = sdt.settleid
                    JOIN cmrequesthd req ON req.reqhdid = sh.rfphdid
                    JOIN cmcpdt cpd ON cpd.cphdid = req.cphdid
                    JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
                    WHERE sdt.pohdid = prpohd.pohdid
                      AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> ''
                ) sub
            ) pay ON TRUE
        $whereClause";

        // Ambil daftar PO yang dibatalkan dari SQL Server GG
        $batalPos = [];
        if ($conn) {
            $ensureTableBatal = "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'tracking_po_online_batal')
            BEGIN
                CREATE TABLE dbo.tracking_po_online_batal (
                    no_po VARCHAR(100) NOT NULL PRIMARY KEY,
                    alasan_batal NVARCHAR(500) NOT NULL,
                    batal_by VARCHAR(100) NULL,
                    batal_at DATETIME DEFAULT GETDATE(),
                    updated_at DATETIME DEFAULT GETDATE()
                );
            END";
            @sqlsrv_query($conn, $ensureTableBatal);

            $qBatal = @sqlsrv_query($conn, "SELECT no_po FROM dbo.tracking_po_online_batal");
            if ($qBatal !== false) {
                while ($rb = sqlsrv_fetch_array($qBatal, SQLSRV_FETCH_ASSOC)) {
                    $batalPos[trim($rb['no_po'])] = true;
                }
            }
        }

        $summarySql = "SELECT DISTINCT
            t.ponmbr,
            t.cp_status,
            (CASE WHEN t.grnnmbr IS NOT NULL AND TRIM(t.grnnmbr) != '' THEN 1 ELSE 0 END) AS is_grn
        FROM ($baseSql) t";

        $stmt = $conn3->prepare($summarySql);
        $stmt->execute($params);
        $poRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPo = count($poRows);
        $paidPo = 0;
        $unpaidPo = 0;
        $grnPo = 0;
        $notArrivedPo = 0;
        $batalPo = 0;

        foreach ($poRows as $p) {
            $poNmbr = trim($p['ponmbr'] ?? '');
            $isBatal = isset($batalPos[$poNmbr]);
            $isPaid = ($p['cp_status'] === 'V');
            $isGrn = ((int)$p['is_grn'] === 1);

            if ($isBatal) {
                $batalPo++;
            } elseif ($isPaid) {
                $paidPo++;
            } else {
                $unpaidPo++;
            }

            if ($isGrn) {
                $grnPo++;
            } else {
                $notArrivedPo++;
            }
        }

        echo json_encode([
            'status' => 'ok',
            'summary' => [
                'total_po'       => $totalPo,
                'paid_po'        => $paidPo,
                'unpaid_po'      => $unpaidPo,
                'approved_po'    => $paidPo,
                'grn_po'         => $grnPo,
                'not_arrived_po' => $notArrivedPo,
                'batal_po'       => $batalPo,
                'total_rows'     => $totalPo,
            ]
        ]);
        exit;
    }

    if ($action === 'update_metode_bayar') {
        $noPos = $_POST['no_pos'] ?? [];
        $metodeBayar = trim($_POST['metode_bayar'] ?? '');
        $currentUserName = trim($_SESSION['UserName'] ?? '');

        if (!is_array($noPos) || empty($noPos)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Tidak ada nomor PO yang dipilih.'
            ]);
            exit;
        }

        // Pastikan tabel di SQL Server GG sudah ada
        $ensureTable = "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'tracking_po_online_metode_bayar')
        BEGIN
            CREATE TABLE dbo.tracking_po_online_metode_bayar (
                no_po VARCHAR(100) NOT NULL PRIMARY KEY,
                metode_bayar VARCHAR(50) NOT NULL DEFAULT '',
                updated_by VARCHAR(100) NULL,
                updated_at DATETIME DEFAULT GETDATE()
            );
        END";
        if ($conn) {
            @sqlsrv_query($conn, $ensureTable);
        }

        $validCount = 0;
        foreach ($noPos as $rawPo) {
            $poNmbr = trim($rawPo);
            if (empty($poNmbr)) continue;

            if ($metodeBayar === '' || strtolower($metodeBayar) === 'reset' || strtolower($metodeBayar) === 'kosong') {
                // Hapus atau reset record dari database lokal
                $delSql = "DELETE FROM dbo.tracking_po_online_metode_bayar WHERE no_po = ?";
                $stmtDel = sqlsrv_query($conn, $delSql, [$poNmbr]);
                if ($stmtDel !== false) {
                    $validCount++;
                }
            } else {
                // Upsert ke database lokal
                $upsertSql = "MERGE dbo.tracking_po_online_metode_bayar AS target
                USING (SELECT ? AS no_po, ? AS metode_bayar, ? AS updated_by) AS source
                ON (target.no_po = source.no_po)
                WHEN MATCHED THEN
                    UPDATE SET target.metode_bayar = source.metode_bayar, target.updated_by = source.updated_by, target.updated_at = GETDATE()
                WHEN NOT MATCHED THEN
                    INSERT (no_po, metode_bayar, updated_by, updated_at) VALUES (source.no_po, source.metode_bayar, source.updated_by, GETDATE());";
                $stmtUp = sqlsrv_query($conn, $upsertSql, [$poNmbr, $metodeBayar, $currentUserName]);
                if ($stmtUp !== false) {
                    $validCount++;
                }
            }
        }

        $labelAction = ($metodeBayar === '' || strtolower($metodeBayar) === 'reset' || strtolower($metodeBayar) === 'kosong') ? 'dikosongkan / direset' : "diatur ke '$metodeBayar'";
        echo json_encode([
            'status' => 'ok',
            'message' => "Metode pembayaran untuk $validCount PO berhasil $labelAction.",
            'updated_count' => $validCount
        ]);
        exit;
    }

    if ($action === 'cancel_po') {
        $noPo        = trim($_POST['no_po'] ?? '');
        $alasanBatal = trim($_POST['alasan_batal'] ?? '');
        $currentUser = trim($_SESSION['UserName'] ?? '');

        if (empty($noPo)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Nomor PO tidak valid.'
            ]);
            exit;
        }

        if (empty($alasanBatal)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Alasan pembatalan wajib diisi.'
            ]);
            exit;
        }

        $ensureTable = "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'tracking_po_online_batal')
        BEGIN
            CREATE TABLE dbo.tracking_po_online_batal (
                no_po VARCHAR(100) NOT NULL PRIMARY KEY,
                alasan_batal NVARCHAR(500) NOT NULL,
                batal_by VARCHAR(100) NULL,
                batal_at DATETIME DEFAULT GETDATE(),
                updated_at DATETIME DEFAULT GETDATE()
            );
        END";
        if ($conn) {
            @sqlsrv_query($conn, $ensureTable);
        }

        $upsertSql = "MERGE dbo.tracking_po_online_batal AS target
        USING (SELECT ? AS no_po, ? AS alasan_batal, ? AS batal_by) AS source
        ON (target.no_po = source.no_po)
        WHEN MATCHED THEN
            UPDATE SET target.alasan_batal = source.alasan_batal, target.batal_by = source.batal_by, target.updated_at = GETDATE()
        WHEN NOT MATCHED THEN
            INSERT (no_po, alasan_batal, batal_by, batal_at, updated_at) VALUES (source.no_po, source.alasan_batal, source.batal_by, GETDATE(), GETDATE());";

        $stmt = sqlsrv_query($conn, $upsertSql, [$noPo, $alasanBatal, $currentUser]);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $msg = isset($errs[0]['message']) ? $errs[0]['message'] : 'Gagal menyimpan pembatalan ke database.';
            echo json_encode([
                'status'  => 'error',
                'message' => $msg
            ]);
            exit;
        }

        echo json_encode([
            'status'  => 'ok',
            'message' => "PO $noPo berhasil dibatalkan."
        ]);
        exit;
    }

    if ($action === 'uncancel_po') {
        $noPo = trim($_POST['no_po'] ?? '');
        if (empty($noPo)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Nomor PO tidak valid.'
            ]);
            exit;
        }

        $delSql = "DELETE FROM dbo.tracking_po_online_batal WHERE no_po = ?";
        $stmtDel = sqlsrv_query($conn, $delSql, [$noPo]);
        if ($stmtDel === false) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Gagal memulihkan status PO.'
            ]);
            exit;
        }

        echo json_encode([
            'status'  => 'ok',
            'message' => "Status PO $noPo berhasil dipulihkan."
        ]);
        exit;
    }

    if ($action === 'load_data') {
        $dateFrom           = trim($_GET['date_from'] ?? '');
        $dateTo             = trim($_GET['date_to'] ?? '');
        $filterStatus       = trim($_GET['filter_status'] ?? '');
        $filterStatusDatang = trim($_GET['filter_status_datang'] ?? '');
        $filterStatusBayar  = trim($_GET['filter_status_bayar'] ?? '');
        $filterMetodeBayar  = trim($_GET['filter_metode_bayar'] ?? '');

        // Fallback untuk backward compatibility parameter lama filter_status
        if (empty($filterStatusDatang) && empty($filterStatusBayar) && !empty($filterStatus)) {
            if ($filterStatus === 'Approved' || $filterStatus === 'approved') {
                $filterStatusBayar = 'Approved';
            } elseif ($filterStatus === 'Sudah Dibayar' || $filterStatus === 'paid') {
                $filterStatusBayar = 'Sudah Dibayar';
            } elseif ($filterStatus === 'Sudah Datang' || $filterStatus === 'grn') {
                $filterStatusDatang = 'Sudah Datang';
            } elseif ($filterStatus === 'Belum Datang' || $filterStatus === 'not_arrived') {
                $filterStatusDatang = 'Belum Datang';
            }
        }

        $whereClause = "WHERE prpohd.poorgid = '22'";
        $params = [];

        if (!empty($dateFrom)) {
            $whereClause .= " AND CAST(prpohd.podate AS DATE) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $whereClause .= " AND CAST(prpohd.podate AS DATE) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        // Filter Status Kedatangan (GRN)
        if ($filterStatusDatang === 'Sudah Datang' || $filterStatusDatang === 'grn') {
            $whereClause .= " AND prgrnhd.grnnmbr IS NOT NULL AND TRIM(prgrnhd.grnnmbr) != ''";
        } elseif ($filterStatusDatang === 'Belum Datang' || $filterStatusDatang === 'not_arrived') {
            $whereClause .= " AND (prgrnhd.grnnmbr IS NULL OR TRIM(prgrnhd.grnnmbr) = '')";
        }

        // Filter Status Pembayaran (CP / Kas Bank)
        if ($filterStatusBayar === 'Sudah Dibayar' || $filterStatusBayar === 'paid' || $filterStatusBayar === 'Approved' || $filterStatusBayar === 'approved') {
            $whereClause .= " AND pay.cp_status = 'V'";
        } elseif ($filterStatusBayar === 'Belum Dibayar' || $filterStatusBayar === 'unpaid') {
            $whereClause .= " AND (pay.cp_status IS NULL OR pay.cp_status != 'V')";
        }

        $sql = "SELECT
            prpohd.ponmbr AS \"No PO\", 
            prpohd.podate AS \"Tgl PO\", 
            smvendor.vendorname AS \"Nama Supplier\", 
            prpohd.poupto AS \"Nama Toko Online\", 
            prpphd.ppnmbr AS \"No PR\", 
            prpphd.ppdate AS \"Tgl PR\", 
            prpodt.poprodcode AS \"Kode Produk\", 
            prpodt.poprodname AS \"Nama Produk\", 
            prpodt.poqty AS \"Qty PO\", 
            smuom.uomname AS \"Satuan\", 
            prpodt.poprice AS \"Harga Satuan\", 
            prpodt.poqtyprice AS \"Total Harga\", 
            prpodt.pototalamounthc AS \"Total Amount\", 
            prpohd.podesc AS \"Keterangan\", 
            prpohd.fgstatus AS \"Status PO\", 
            prgrnhd.grnnmbr AS \"No. GRN\", 
            prgrnhd.grndate AS \"Tgl GRN\", 
            prgrndt.grnqty AS \"Qty GRN\", 
            whwrhs.wrhsname AS \"Nama Gudang\", 
            prpohd.createdby AS \"Pembuat\", 
            pay.reqnmbr AS \"No. RFP\", 
            pay.reqdate AS \"Tgl RFP\", 
            pay.cpnmbr AS \"No CP\", 
            pay.cpdate AS \"Tgl CP\", 
            pay.cp_status AS \"Status CP\"
        FROM
            prpohd
            LEFT JOIN prpodt ON prpohd.pohdid = prpodt.pohdid
            LEFT JOIN smvendor ON prpohd.povendorid = smvendor.vendorid
            LEFT JOIN prpphd ON prpodt.popphdid = prpphd.pphdid
            LEFT JOIN prgrndt ON prpodt.podtid = prgrndt.podtid
            LEFT JOIN prgrnhd ON prgrndt.grnhdid = prgrnhd.grnhdid
            LEFT JOIN smuom ON prpodt.pouomid = smuom.uomid
            LEFT JOIN whwrhs ON prpohd.powrhsid = whwrhs.wrhsid
            LEFT JOIN LATERAL (
                SELECT 
                    STRING_AGG(DISTINCT sub.cpnmbr, ', ') AS cpnmbr,
                    MAX(sub.cpdate) AS cpdate,
                    CASE 
                        WHEN BOOL_OR(sub.cp_status = 'V') THEN 'V'
                        WHEN BOOL_OR(sub.cp_status = 'O') THEN 'O'
                        ELSE MAX(sub.cp_status)
                    END AS cp_status,
                    STRING_AGG(DISTINCT sub.reqnmbr, ', ') AS reqnmbr,
                    MAX(sub.reqdate) AS reqdate
                FROM (
                    -- 1. Pembayaran AP Invoice setelah penerimaan barang (GRN -> APPosting -> CMCP)
                    SELECT 
                        cp.cpnmbr,
                        cp.cpdate,
                        cp.fgstatus AS cp_status,
                        NULL::varchar AS reqnmbr,
                        NULL::timestamp AS reqdate
                    FROM prgrndt gd
                    JOIN apposting ap ON ap.grnhdid = gd.grnhdid
                    JOIN cmcpdt cpd ON cpd.apid = ap.apid
                    JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
                    WHERE gd.pohdid = prpohd.pohdid
                      AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> ''

                    UNION ALL

                    -- 2. Pembayaran Uang Muka / Langsung PO (CMTTTAP -> CMCP)
                    SELECT 
                        COALESCE(cp.cpnmbr, t.cpnmbr) AS cpnmbr,
                        COALESCE(cp.cpdate, t.cpdate) AS cpdate,
                        cp.fgstatus AS cp_status,
                        t.reqnmbr,
                        t.reqdate
                    FROM cmtttap t
                    LEFT JOIN cmcphd cp ON cp.cphdid = t.cphdid
                    WHERE t.pohdid = prpohd.pohdid

                    UNION ALL

                    -- 3. Pembayaran via Settlement Uang Muka Impor (PASettlement -> CMCP)
                    SELECT 
                        cp.cpnmbr,
                        cp.cpdate,
                        cp.fgstatus AS cp_status,
                        req.reqnmbr,
                        req.reqdate
                    FROM pasettlementdt sdt
                    JOIN pasettlementhd sh ON sh.settleid = sdt.settleid
                    JOIN cmrequesthd req ON req.reqhdid = sh.rfphdid
                    JOIN cmcpdt cpd ON cpd.cphdid = req.cphdid
                    JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
                    WHERE sdt.pohdid = prpohd.pohdid
                      AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> ''
                ) sub
            ) pay ON TRUE
        $whereClause
        ORDER BY
            prpohd.ponmbr ASC,
            prpodt.podtid ASC";

        $stmt = $conn3->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ambil pemetaan Metode Pembayaran dari database SQL Server GG
        $metodeMap = [];
        $batalMap  = [];
        if ($conn) {
            $qMetode = @sqlsrv_query($conn, "SELECT no_po, metode_bayar FROM dbo.tracking_po_online_metode_bayar");
            if ($qMetode !== false) {
                while ($rm = sqlsrv_fetch_array($qMetode, SQLSRV_FETCH_ASSOC)) {
                    $metodeMap[trim($rm['no_po'])] = trim($rm['metode_bayar'] ?? '');
                }
            }

            // Ambil pemetaan PO Batal
            $ensureTableBatal = "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'tracking_po_online_batal')
            BEGIN
                CREATE TABLE dbo.tracking_po_online_batal (
                    no_po VARCHAR(100) NOT NULL PRIMARY KEY,
                    alasan_batal NVARCHAR(500) NOT NULL,
                    batal_by VARCHAR(100) NULL,
                    batal_at DATETIME DEFAULT GETDATE(),
                    updated_at DATETIME DEFAULT GETDATE()
                );
            END";
            @sqlsrv_query($conn, $ensureTableBatal);

            $qBatal = @sqlsrv_query($conn, "SELECT no_po, alasan_batal, batal_by, CONVERT(VARCHAR(19), batal_at, 120) AS batal_at FROM dbo.tracking_po_online_batal");
            if ($qBatal !== false) {
                while ($rb = sqlsrv_fetch_array($qBatal, SQLSRV_FETCH_ASSOC)) {
                    $batalMap[trim($rb['no_po'])] = [
                        'alasan_batal' => trim($rb['alasan_batal'] ?? ''),
                        'batal_by'     => trim($rb['batal_by'] ?? ''),
                        'batal_at'     => trim($rb['batal_at'] ?? '')
                    ];
                }
            }
        }

        $data = [];
        $no = 1;
        foreach ($rows as $r) {
            $noPo = trim($r['No PO'] ?? '');
            $metodeBayar = $metodeMap[$noPo] ?? ''; // Default kosong (-)
            $isBatal = isset($batalMap[$noPo]);
            $batalInfo = $isBatal ? $batalMap[$noPo] : null;

            // Filter Status Bayar jika Batal / Sudah Dibayar / Belum Dibayar
            if ($filterStatusBayar === 'Batal') {
                if (!$isBatal) continue;
            } elseif ($filterStatusBayar === 'Sudah Dibayar' || $filterStatusBayar === 'paid' || $filterStatusBayar === 'Approved' || $filterStatusBayar === 'approved') {
                if ($isBatal) continue;
            } elseif ($filterStatusBayar === 'Belum Dibayar' || $filterStatusBayar === 'unpaid') {
                if ($isBatal) continue;
            }

            // Filter Metode Pembayaran jika dipilih pengguna
            if ($filterMetodeBayar === 'Kosong' || $filterMetodeBayar === 'belum_diset') {
                if ($metodeBayar !== '') continue;
            } elseif (!empty($filterMetodeBayar)) {
                if ($metodeBayar !== $filterMetodeBayar) continue;
            }

            $statusPo = trim($r['Status PO'] ?? '');
            $statusCp = trim($r['Status CP'] ?? '');
            $noCp     = trim($r['No CP'] ?? '');
            $noGrn    = trim($r['No. GRN'] ?? '');

            if ($isBatal) {
                $statusBayarLabel = 'Batal';
            } else {
                $statusBayarLabel = ($statusCp === 'V') ? 'Sudah Dibayar' : 'Belum Dibayar';
            }

            $data[] = [
                'no'               => $no++,
                'no_po'            => $noPo,
                'tgl_po'           => formatTgl($r['Tgl PO']),
                'tgl_po_raw'       => $r['Tgl PO'] ? substr($r['Tgl PO'], 0, 10) : '',
                'nama_supplier'    => trim($r['Nama Supplier'] ?? '-'),
                'nama_toko_online' => trim($r['Nama Toko Online'] ?? '-'),
                'no_pr'            => trim($r['No PR'] ?? '-'),
                'tgl_pr'           => formatTgl($r['Tgl PR']),
                'tgl_pr_raw'       => $r['Tgl PR'] ? substr($r['Tgl PR'], 0, 10) : '',
                'kode_produk'      => trim($r['Kode Produk'] ?? '-'),
                'nama_produk'      => trim($r['Nama Produk'] ?? '-'),
                'qty_po'           => formatQty($r['Qty PO']),
                'qty_po_raw'       => (float)($r['Qty PO'] ?? 0),
                'satuan'           => trim($r['Satuan'] ?? '-'),
                'harga_satuan'     => formatRupiah($r['Harga Satuan']),
                'harga_satuan_raw' => (float)($r['Harga Satuan'] ?? 0),
                'total_harga'      => formatRupiah($r['Total Harga']),
                'total_harga_raw'  => (float)($r['Total Harga'] ?? 0),
                'total_amount'     => formatRupiah($r['Total Amount']),
                'total_amount_raw' => (float)($r['Total Amount'] ?? 0),
                'keterangan'       => trim($r['Keterangan'] ?? '-'),
                'metode_bayar'     => $metodeBayar,
                'status_po'        => $statusPo,
                'no_grn'           => $noGrn !== '' ? $noGrn : '-',
                'tgl_grn'          => formatTgl($r['Tgl GRN']),
                'tgl_grn_raw'      => $r['Tgl GRN'] ? substr($r['Tgl GRN'], 0, 10) : '',
                'qty_grn'          => formatQty($r['Qty GRN']),
                'qty_grn_raw'      => (float)($r['Qty GRN'] ?? 0),
                'nama_gudang'      => trim($r['Nama Gudang'] ?? '-'),
                'pembuat'          => trim($r['Pembuat'] ?? '-'),
                'no_rfp'           => trim($r['No. RFP'] ?? '-'),
                'tgl_rfp'          => formatTgl($r['Tgl RFP']),
                'tgl_rfp_raw'      => $r['Tgl RFP'] ? substr($r['Tgl RFP'], 0, 10) : '',
                'no_cp'            => $noCp !== '' ? $noCp : '-',
                'tgl_cp'           => formatTgl($r['Tgl CP']),
                'tgl_cp_raw'       => $r['Tgl CP'] ? substr($r['Tgl CP'], 0, 10) : '',
                'status_cp'        => $statusCp,
                'status_bayar'     => $statusBayarLabel,
                'is_batal'         => $isBatal,
                'alasan_batal'     => $batalInfo ? $batalInfo['alasan_batal'] : '',
                'batal_by'         => $batalInfo ? $batalInfo['batal_by'] : '',
                'batal_at'         => $batalInfo && !empty($batalInfo['batal_at']) ? date('d/m/Y H:i', strtotime($batalInfo['batal_at'])) : '',
                // Status boolean flags
                'is_approved'      => ($statusCp === 'V' && !$isBatal),
                'is_paid'          => ($statusCp === 'V' && !$isBatal),
                'is_unpaid'        => ($statusCp !== 'V' && !$isBatal),
                'is_arrived'       => ($noGrn !== ''),
                'is_not_arrived'   => ($noGrn === ''),
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'data'   => $data,
            'count'  => count($data)
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Aksi tidak dikenali.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan pada database: ' . $e->getMessage()
    ]);
}
