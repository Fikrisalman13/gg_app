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

        $summarySql = "SELECT
            COUNT(DISTINCT t.ponmbr) AS total_po,
            COUNT(DISTINCT CASE WHEN t.cp_status = 'V' THEN t.ponmbr END) AS paid_po,
            COUNT(DISTINCT CASE WHEN (t.cp_status IS NULL OR t.cp_status != 'V') THEN t.ponmbr END) AS unpaid_po,
            COUNT(DISTINCT CASE WHEN t.grnnmbr IS NOT NULL AND TRIM(t.grnnmbr) != '' THEN t.ponmbr END) AS grn_po,
            COUNT(DISTINCT CASE WHEN (t.grnnmbr IS NULL OR TRIM(t.grnnmbr) = '') THEN t.ponmbr END) AS not_arrived_po,
            COUNT(*) AS total_rows
        FROM ($baseSql) t";

        $stmt = $conn3->prepare($summarySql);
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'ok',
            'summary' => [
                'total_po'       => (int)($summary['total_po'] ?? 0),
                'paid_po'        => (int)($summary['paid_po'] ?? 0),
                'unpaid_po'      => (int)($summary['unpaid_po'] ?? 0),
                'approved_po'    => (int)($summary['paid_po'] ?? 0),
                'grn_po'         => (int)($summary['grn_po'] ?? 0),
                'not_arrived_po' => (int)($summary['not_arrived_po'] ?? 0),
                'total_rows'     => (int)($summary['total_rows'] ?? 0),
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
        if ($conn) {
            $qMetode = @sqlsrv_query($conn, "SELECT no_po, metode_bayar FROM dbo.tracking_po_online_metode_bayar");
            if ($qMetode !== false) {
                while ($rm = sqlsrv_fetch_array($qMetode, SQLSRV_FETCH_ASSOC)) {
                    $metodeMap[trim($rm['no_po'])] = trim($rm['metode_bayar'] ?? '');
                }
            }
        }

        $data = [];
        $no = 1;
        foreach ($rows as $r) {
            $noPo = trim($r['No PO'] ?? '');
            $metodeBayar = $metodeMap[$noPo] ?? ''; // Default kosong (-)

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

            $statusBayarLabel = ($statusCp === 'V') ? 'Sudah Dibayar' : 'Belum Dibayar';

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
                // Status boolean flags
                'is_approved'      => ($statusCp === 'V'),
                'is_paid'          => ($statusCp === 'V'),
                'is_unpaid'        => ($statusCp !== 'V'),
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
