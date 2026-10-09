<?php
/**
 * get_po_data.php
 * Endpoint Read-Only untuk menarik data PO dari database ERP (PostgreSQL via koneksi3.php)
 * JAMINAN: 100% Read-Only, hanya menggunakan SELECT query.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$poNumber = trim($_GET['po'] ?? '');
if (empty($poNumber)) {
    echo json_encode(['success' => false, 'message' => 'Nomor PO tidak boleh kosong']);
    exit;
}

// Koneksi ke koneksi3.php (PostgreSQL ERP_Crystal_SUM)
$koneksi3Path = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php';
if (!file_exists($koneksi3Path)) {
    $koneksi3Path = dirname(__DIR__, 2) . '/koneksi3.php';
}

if (!file_exists($koneksi3Path)) {
    echo json_encode(['success' => false, 'message' => 'File koneksi3.php tidak ditemukan']);
    exit;
}

require_once $koneksi3Path;

if (!isset($conn3) || !$conn3) {
    echo json_encode(['success' => false, 'message' => 'Koneksi ke database ERP gagal']);
    exit;
}

try {
    // Memastikan sesi transaksi PostgreSQL berada dalam mode READ ONLY
    $conn3->exec("SET TRANSACTION READ ONLY");

    // 1. Ambil data Header PO dan Currency
    $sqlHd = "
        SELECT 
            h.pohdid,
            h.ponmbr,
            h.podate,
            h.povendorid,
            h.povendorname,
            h.podesc,
            h.pocurrid,
            COALESCE(c.currcode, 'IDR') AS currcode
        FROM prpohd h
        LEFT JOIN smcurrency c ON h.pocurrid = c.currid
        WHERE h.ponmbr = :ponmbr
        LIMIT 1
    ";
    $stmtHd = $conn3->prepare($sqlHd);
    $stmtHd->execute([':ponmbr' => $poNumber]);
    $header = $stmtHd->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo json_encode(['success' => false, 'message' => "Nomor PO '$poNumber' tidak ditemukan di database ERP."]);
        exit;
    }

    $pohdid = $header['pohdid'];

    // 2. Ambil data Rekening Vendor (semua rekening, untuk deteksi multi-rekening)
    $dibayarKepada = '';
    $rekeningAc = '';
    $bankName = '';
    $vendorCurrency = '';
    $vendorCurrencyName = '';
    $vendorAccounts = [];

    if (!empty($header['povendorid'])) {
        $sqlAcc = "
            SELECT
                va.vendoraccid,
                va.vendoraccnmbr,
                va.vendoraccname,
                b.bankname,
                c.currcode,
                c.currname,
                va.fgdefault
            FROM smvendoraccount va
            LEFT JOIN cmbank b ON va.vendorbankid = b.bankid
            LEFT JOIN smcurrency c ON va.vendoracccurrid = c.currid
            WHERE va.vendorid = :vendorid
            ORDER BY va.fgdefault DESC, va.vendoraccid ASC
        ";
        $stmtAcc = $conn3->prepare($sqlAcc);
        $stmtAcc->execute([':vendorid' => $header['povendorid']]);
        $allAccounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allAccounts as $acc) {
            $fgDef = strtoupper(trim((string)($acc['fgdefault'] ?? '')));
            $vendorAccounts[] = [
                'vendoraccid'  => $acc['vendoraccid'],
                'no_rekening'  => trim($acc['vendoraccnmbr'] ?? ''),
                'atas_nama'    => trim($acc['vendoraccname'] ?? ''),
                'bank'         => trim($acc['bankname'] ?? ''),
                'currency'     => trim($acc['currcode'] ?? ''),
                'currency_name'=> trim($acc['currname'] ?? ''),
                'is_default'   => ($fgDef === 'Y' || $acc['fgdefault'] == 1 || $acc['fgdefault'] === true || $acc['fgdefault'] === 't'),
            ];
        }

        // Jika hanya 1 rekening → langsung set field (behaviour lama)
        // Jika >1 rekening → field dibiarkan kosong, JS yang menangani via popup
        if (count($vendorAccounts) === 1) {
            $rekeningAc       = $vendorAccounts[0]['no_rekening'];
            $dibayarKepada    = $vendorAccounts[0]['atas_nama'];
            $bankName         = $vendorAccounts[0]['bank'];
            $vendorCurrency   = $vendorAccounts[0]['currency'];
            $vendorCurrencyName = $vendorAccounts[0]['currency_name'];
        } elseif (count($vendorAccounts) === 0 && isset($allAccounts[0])) {
            // fallback aman jika loop gagal
            $rekeningAc    = trim($allAccounts[0]['vendoraccnmbr'] ?? '');
            $dibayarKepada = trim($allAccounts[0]['vendoraccname'] ?? '');
            $bankName      = trim($allAccounts[0]['bankname'] ?? '');
            $vendorCurrency = trim($allAccounts[0]['currcode'] ?? '');
            $vendorCurrencyName = trim($allAccounts[0]['currname'] ?? '');
        }
        // Jika >1: $rekeningAc, $dibayarKepada, $bankName tetap kosong '' — diserahkan ke popup
    }

    // 3. Ambil data Penerimaan Barang (GRN / Receipt) yang berelasi dengan PO ini
    $sqlGrn = "
        SELECT 
            g.grnnmbr,
            g.grndate,
            g.vendorname
        FROM prgrndt gd
        INNER JOIN prgrnhd g ON gd.grnhdid = g.grnhdid
        WHERE gd.pohdid = :pohdid
        ORDER BY g.grndate DESC, g.grnhdid DESC
        LIMIT 1
    ";
    $stmtGrn = $conn3->prepare($sqlGrn);
    $stmtGrn->execute([':pohdid' => $pohdid]);
    $grn = $stmtGrn->fetch(PDO::FETCH_ASSOC);

    $noReceipt = $grn['grnnmbr'] ?? '';
    $tglReceipt = '';
    if (!empty($grn['grndate'])) {
        $tglReceipt = date('Y-m-d', strtotime($grn['grndate']));
    }

    // 3. Ambil data Detail Item PO (prpodt)
    $sqlDt = "
        SELECT 
            podtid,
            poseq,
            poprodname,
            podesc,
            poprice,
            ponetto,
            popcppn,
            poppn,
            popcpph21,
            popph21,
            popcpph22,
            popph22,
            popcpph23,
            popph23,
            popcpph4,
            popph4,
            popcpph26,
            popph26,
            pototalamount
        FROM prpodt
        WHERE pohdid = :pohdid
        ORDER BY poseq ASC
    ";
    $stmtDt = $conn3->prepare($sqlDt);
    $stmtDt->execute([':pohdid' => $pohdid]);
    $details = $stmtDt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $rowCounter = 1;
    $currencyCode = $header['currcode'] ?: 'IDR';

    // Rangkum item, PPN, dan seluruh jenis PPh (21, 23, 4(2), 22, 26)
    foreach ($details as $dt) {
        $dpp = floatval($dt['ponetto'] ?? $dt['poprice'] ?? 0);
        $itemDesc = trim($dt['poprodname'] ?? '') ?: trim($dt['podesc'] ?? '');

        // Baris Utama: Nilai DPP Pokok
        $items[] = [
            'no' => $rowCounter++,
            'keterangan' => $itemDesc,
            'cd' => 'D',
            'sifat' => '+',
            'curr' => $currencyCode,
            'dpp' => $dpp
        ];

        // Baris PPN (jika ada nilai PPN)
        $ppnVal = floatval($dt['poppn'] ?? 0);
        $ppnPerc = floatval($dt['popcppn'] ?? 0);
        if ($ppnVal > 0) {
            $ppnLabel = 'PPN';
            if ($ppnPerc > 0) {
                $ppnLabel .= ' ' . rtrim(rtrim(number_format($ppnPerc, 2, '.', ''), '0'), '.') . '%';
            }
            $items[] = [
                'no' => $rowCounter++,
                'keterangan' => $ppnLabel,
                'cd' => 'C',
                'sifat' => '+',
                'curr' => $currencyCode,
                'dpp' => $ppnVal
            ];
        }

        // Daftar jenis PPh yang didukung dari ERP:
        // PPh 21, PPh 23, PPh 4(2), PPh 22, PPh 26
        $pphConfig = [
            ['val' => 'popph21', 'perc' => 'popcpph21', 'label' => 'PPH 21'],
            ['val' => 'popph23', 'perc' => 'popcpph23', 'label' => 'PPH 23'],
            ['val' => 'popph4',  'perc' => 'popcpph4',  'label' => 'PPH 4(2)'],
            ['val' => 'popph22', 'perc' => 'popcpph22', 'label' => 'PPH 22'],
            ['val' => 'popph26', 'perc' => 'popcpph26', 'label' => 'PPH 26'],
        ];

        foreach ($pphConfig as $cfg) {
            $pphVal = floatval($dt[$cfg['val']] ?? 0);
            $pphPerc = floatval($dt[$cfg['perc']] ?? 0);
            if ($pphVal > 0) {
                $pphLabel = $cfg['label'];
                if ($pphPerc > 0) {
                    $pphLabel .= ' ' . rtrim(rtrim(number_format($pphPerc, 2, '.', ''), '0'), '.') . '%';
                }
                $items[] = [
                    'no' => $rowCounter++,
                    'keterangan' => $pphLabel,
                    'cd' => 'D',
                    'sifat' => '-',
                    'curr' => $currencyCode,
                    'dpp' => $pphVal
                ];
            }
        }
    }

    // Format tanggal PO
    $poDate = !empty($header['podate']) ? date('Y-m-d', strtotime($header['podate'])) : date('Y-m-d');
    $supplierName = trim($header['povendorname'] ?? '');

    // Keterangan PO
    $keteranganPo = trim($header['podesc'] ?? '');
    if (empty($keteranganPo) && !empty($details[0]['podesc'])) {
        $keteranganPo = trim($details[0]['podesc']);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'pohdid' => $pohdid,
            'ref_po_no' => $header['ponmbr'],
            'podate' => $poDate,
            'supplier'             => $supplierName,
            'currency'             => !empty($vendorCurrency) ? $vendorCurrency : $currencyCode,
            'currency_name'        => $vendorCurrencyName,
            'dibayar_kepada'       => $dibayarKepada,
            'bank'                 => $bankName,
            'rekening_ac'          => $rekeningAc,
            'no_receipt'           => $noReceipt,
            'tgl_receipt'          => $tglReceipt,
            'keterangan'           => $keteranganPo,
            'items'                => $items,
            'vendor_account_count' => count($vendorAccounts),
            'vendor_accounts'      => $vendorAccounts,
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error querying ERP database: ' . $e->getMessage()
    ]);
    exit;
}
