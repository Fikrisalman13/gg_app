<?php
session_start();
include_once file_exists(__DIR__ . '/../../koneksi.php') ? __DIR__ . '/../../koneksi.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include_once file_exists(__DIR__ . '/../../includes/permissions.php') ? __DIR__ . '/../../includes/permissions.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

header('Content-Type: application/json; charset=utf-8');

// =========================================================================
// HELPER: MENDAPATKAN IDENTITAS USER LOGIN LENGKAP
// =========================================================================
function getCurrentUserIdentity($conn) {
    $user = trim($_SESSION['UserName'] ?? '');
    $nama = trim($_SESSION['NamaLengkap'] ?? '');
    $dept = '';
    
    if (!empty($conn) && !empty($user)) {
        $q = sqlsrv_query($conn, "SELECT e.nama_lengkap, d.dept 
            FROM dbo.SMUserMs u 
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp 
            LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
            LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
            LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
            WHERE u.UserName = ?", [$user]);
        if ($q && ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))) {
            if (!empty($row['nama_lengkap'])) $nama = trim($row['nama_lengkap']);
            if (!empty($row['dept'])) $dept = trim($row['dept']);
        }
    }
    if (empty($nama)) $nama = $user;
    $formatted = !empty($dept) ? "{$nama} ({$dept})" : $nama;

    return [
        'user'      => $user,
        'nama'      => $nama,
        'dept'      => $dept,
        'formatted' => $formatted
    ];
}

$currentUser = getCurrentUserIdentity($conn);

// =========================================================================
// NOTE: Penerima tidak ditentukan saat input — diisi otomatis dari akun
// yang melakukan tanda tangan. Siapapun yang login bisa TTD.
// =========================================================================

// =========================================================================
// CEK APAKAH TABEL DATABASE SUDAH TERSEDIA DI SQL SERVER
// =========================================================================
$tableReady = false;
if (!empty($conn)) {
    $qCheck = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'purchasing_header'");
    if ($qCheck && sqlsrv_has_rows($qCheck)) {
        $tableReady = true;
    }
}

// Fallback session data jika tabel belum di-create oleh user di SSMS
$defaultData = [
    [
        'id' => 1,
        'tanggal' => '2026-09-21',
        'type' => 'COD',
        'type_keterangan' => '',
        'is_multi_po' => false,
        'descriptions' => [
            'Pipot Volumettnc Rp.94.350',
            'PO Asli + SJ : 194/SJ/PG/07/2026',
            'Invoice + FP : 0400.2600.2809.81304 20/09',
            'Copy DO'
        ],
        'vendor' => 'Pridhana Eka',
        'no_po' => 'POLC/2607/0389',
        'no_grn' => 'GRNLC/2607/0755',
        'pengirim' => 'Fikri Salman Ramadhan (Information Technology)',
        'penerima' => 'Wulan Wulandari (Accounting)',
        'status_ttd_raw' => 'Sudah Ditandatangani'
    ],
    [
        'id' => 2,
        'tanggal' => '2026-09-21',
        'type' => 'RFP',
        'type_keterangan' => '26.071.996',
        'is_multi_po' => false,
        'descriptions' => [
            'DP 50% Pembelian Carbon, Pasir dll Rp.69.791.255',
            'PO Asli + Performa Invoice'
        ],
        'vendor' => 'Ady Water',
        'no_po' => 'POLD/2607/0081',
        'no_grn' => '-',
        'pengirim' => 'Fikri Salman Ramadhan (Information Technology)',
        'penerima' => 'Wulan Wulandari (Accounting)',
        'status_ttd_raw' => 'Sudah Ditandatangani'
    ],
    [
        'id' => 3,
        'tanggal' => '2026-09-21',
        'type' => 'OFFSET',
        'type_keterangan' => '',
        'is_multi_po' => false,
        'descriptions' => [
            'PO + Inv + Laporan Hasil Uji',
            'No : 1123/EX/VII/2026'
        ],
        'vendor' => 'BBT',
        'no_po' => 'POSD/2607/0004',
        'no_grn' => 'GRNSD/2607/0006',
        'pengirim' => 'Fikri Salman Ramadhan (Information Technology)',
        'penerima' => 'Wulan Wulandari (Accounting)',
        'status_ttd_raw' => 'Sudah Ditandatangani'
    ],
    [
        'id' => 4,
        'tanggal' => '2026-09-21',
        'type' => 'Kontrabon',
        'type_keterangan' => '',
        'is_multi_po' => false,
        'descriptions' => [
            'SJ + SJ Dokumen Lampiran + Kwitansi',
            'Inv + FP + PO Asli'
        ],
        'vendor' => 'Meta Rupa Perkasa',
        'no_po' => 'POLC/2607/0471',
        'no_grn' => 'GRNLC/2607/0951',
        'pengirim' => 'Fikri Salman Ramadhan (Information Technology)',
        'penerima' => 'Wulan Wulandari (Accounting)',
        'status_ttd_raw' => 'Sudah Ditandatangani'
    ],
    [
        'id' => 5,
        'tanggal' => '2026-09-21',
        'type' => 'PO',
        'type_keterangan' => 'Untuk dibuat Cek',
        'is_multi_po' => true,
        'po_items' => [
            ['no' => 1, 'desc' => 'Rp.25.000', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0671', 'no_grn' => '-'],
            ['no' => 2, 'desc' => 'Rp.25.001', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0672', 'no_grn' => '-'],
            ['no' => 3, 'desc' => 'Rp.25.002', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0673', 'no_grn' => '-'],
            ['no' => 4, 'desc' => 'Rp.25.003', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0674', 'no_grn' => '-'],
            ['no' => 5, 'desc' => 'Rp.25.004', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0675', 'no_grn' => '-'],
        ],
        'descriptions' => ['Rp.25.000', 'Rp.25.001', 'Rp.25.002', 'Rp.25.003', 'Rp.25.004'],
        'vendor' => 'Intan Jaya Holis',
        'no_po' => 'POLC/2606/0671 - 0675',
        'no_grn' => '-',
        'pengirim' => 'Fikri Salman Ramadhan (Information Technology)',
        'penerima' => 'Wulan Wulandari (Accounting)',
        'status_ttd_raw' => 'Menunggu TTD'
    ],
    [
        'id' => 6,
        'tanggal' => '2026-09-07',
        'type' => 'RFP',
        'type_keterangan' => '2607.2106',
        'is_multi_po' => false,
        'descriptions' => [
            'PEMBAYARAN DP KE-3 30%, Rp.75,973,808',
            'BA + Inv + Kwitansi + PO + Foto',
            'FP = 0400.2600.7893.65654'
        ],
        'vendor' => 'GRAHAM BELACITRA',
        'no_po' => 'POLD/26061/0031',
        'no_grn' => 'GRNLD/2607/0013',
        'pengirim' => 'Fikri Salman Ramadhan (Information Technology)',
        'penerima' => 'Wulan Wulandari (Accounting)',
        'status_ttd_raw' => 'Sudah Ditandatangani'
    ]
];

if (!$tableReady) {
    if (!isset($_SESSION['purchasing_data']) || empty($_SESSION['purchasing_data']) || isset($_GET['reset_data'])) {
        $_SESSION['purchasing_data'] = $defaultData;
    }
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// =========================================================================
// ACTION: GET_PO_DETAIL (Koneksi ke ERP Proint via koneksi3.php)
// =========================================================================
if ($action === 'get_po_detail') {
    $noPo = trim($_POST['no_po'] ?? ($_GET['no_po'] ?? ''));
    if (empty($noPo)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor PO tidak boleh kosong!']);
        exit;
    }

    $koneksi3Path = file_exists(__DIR__ . '/../../koneksi3.php') ? __DIR__ . '/../../koneksi3.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php');
    if (!file_exists($koneksi3Path)) {
        echo json_encode(['status' => 'error', 'message' => 'File koneksi3.php tidak ditemukan!']);
        exit;
    }

    try {
        include_once $koneksi3Path;
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database Proint ERP gagal!']);
            exit;
        }

        $stmtHd = $conn3->prepare("
            SELECT h.pohdid, h.ponmbr, h.draftnmbr, h.povendorname
            FROM prpohd h
            WHERE UPPER(TRIM(h.ponmbr)) = UPPER(TRIM(:ponmbr)) 
               OR UPPER(TRIM(h.draftnmbr)) = UPPER(TRIM(:ponmbr))
               OR UPPER(h.ponmbr) LIKE UPPER(:ponmbr_like)
            ORDER BY 
                CASE WHEN UPPER(TRIM(h.ponmbr)) = UPPER(TRIM(:ponmbr)) THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmtHd->execute([
            ':ponmbr' => $noPo,
            ':ponmbr_like' => '%' . $noPo . '%'
        ]);
        $header = $stmtHd->fetch(PDO::FETCH_ASSOC);

        if ($header) {
            $pohdid = $header['pohdid'];

            // 1. Hitung TOTAL nilai pototalamount dari seluruh baris prpodt untuk pohdid ini
            $stmtSum = $conn3->prepare("
                SELECT 
                    SUM(COALESCE(NULLIF(pototalamount, 0), pototalamounthc, 0)) AS total_amount,
                    COUNT(*) AS total_items
                FROM prpodt
                WHERE pohdid = :pohdid
            ");
            $stmtSum->execute([':pohdid' => $pohdid]);
            $sumRow = $stmtSum->fetch(PDO::FETCH_ASSOC);

            // 2. Ambil SEMUA deskripsi produk (poprodname) dari seluruh baris prpodt
            $stmtProd = $conn3->prepare("
                SELECT poprodname 
                FROM prpodt 
                WHERE pohdid = :pohdid 
                ORDER BY podtid ASC
            ");
            $stmtProd->execute([':pohdid' => $pohdid]);
            $prodRows = $stmtProd->fetchAll(PDO::FETCH_COLUMN);

            // Gabungkan semua nama produk dengan koma, hilangkan yang kosong
            $prodNames = array_filter(array_map('trim', $prodRows), function($n) { return $n !== ''; });
            $prodNameCombined = implode(', ', $prodNames);
            $prodName = $prodNameCombined; // backward compat
            $stmtGrn = $conn3->prepare("
                SELECT DISTINCT gh.grnnmbr
                FROM prgrndt gd
                JOIN prgrnhd gh ON gd.grnhdid = gh.grnhdid
                WHERE gd.pohdid = :pohdid
                ORDER BY gh.grnnmbr ASC
            ");
            $stmtGrn->execute([':pohdid' => $pohdid]);
            $grnRows = $stmtGrn->fetchAll(PDO::FETCH_COLUMN);
            $grn = !empty($grnRows) ? implode(', ', $grnRows) : '-';

            $amount = (float)($sumRow['total_amount'] ?? 0);
            $formattedAmount = 'Rp.' . number_format($amount, 0, ',', '.');
            // Re-build descLine with the correct formattedAmount (computed after sum)
            $descLine = $prodNameCombined . ' ' . $formattedAmount;
            $vendorName = ucwords(strtolower(trim($header['povendorname'] ?? '')));

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'pohdid'               => $pohdid,
                    'ponmbr'               => $header['ponmbr'],
                    'prod_name'            => $prodName,
                    'prod_names_combined'  => $prodNameCombined,
                    'amount'               => $amount,
                    'formatted_amount'     => $formattedAmount,
                    'description_line'     => $descLine,
                    'vendor'               => $vendorName,
                    'vendor_raw'           => $header['povendorname'],
                    'no_grn'               => $grn,
                    'grn_list'             => $grnRows
                ]
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Nomor PO "' . htmlspecialchars($noPo) . '" tidak ditemukan di database Proint ERP.'
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Terjadi kendala saat membaca data PO: ' . $e->getMessage()
        ]);
        exit;
    }
}

// =========================================================================
// ACTION: GET_RFP_DETAIL (Koneksi ke ERP Proint via koneksi3.php)
// Mengambil data vendor + No. PO (dari reqdesc) + GRN sekaligus dalam 1 request
// =========================================================================
if ($action === 'get_rfp_detail') {
    $noRfp = trim($_POST['no_rfp'] ?? ($_GET['no_rfp'] ?? ''));
    if (empty($noRfp)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor RFP tidak boleh kosong!']);
        exit;
    }

    $koneksi3Path = file_exists(__DIR__ . '/../../koneksi3.php') ? __DIR__ . '/../../koneksi3.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php');
    if (!file_exists($koneksi3Path)) {
        echo json_encode(['status' => 'error', 'message' => 'File koneksi3.php tidak ditemukan!']);
        exit;
    }

    try {
        include_once $koneksi3Path;
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database Proint ERP gagal!']);
            exit;
        }

        $cleanRfp = trim($noRfp);
        $withDot = $cleanRfp;
        if (preg_match('/^(\d{4})(\d{4})$/', $cleanRfp, $m)) {
            $withDot = $m[1] . '.' . $m[2];
        }
        $noPrefix = trim(preg_replace('/^rfp[\s\.\-\/]*/i', '', $cleanRfp));
        $noPrefixWithDot = $noPrefix;
        if (preg_match('/^(\d{4})(\d{4})$/', $noPrefix, $m)) {
            $noPrefixWithDot = $m[1] . '.' . $m[2];
        }

        // -------------------------------------------------------
        // Step 1: Ambil data utama RFP (vendor + reqdesc)
        // -------------------------------------------------------
        $stmt = $conn3->prepare("
            SELECT 
                h.reqhdid,
                h.reqnmbr,
                h.draftnmbr,
                h.reqvendorid,
                h.reqdesc,
                v.vendorname,
                h.reqpaidtoaccname,
                h.reqpaidto
            FROM cmrequesthd h
            LEFT JOIN smvendor v ON h.reqvendorid = v.vendorid
            WHERE UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp1))
               OR UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp2))
               OR UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp3))
               OR UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp4))
               OR UPPER(TRIM(h.draftnmbr)) = UPPER(TRIM(:rfp1))
               OR UPPER(TRIM(h.draftnmbr)) = UPPER(TRIM(:rfp2))
               OR UPPER(h.reqnmbr) LIKE UPPER(:rfp_like)
            ORDER BY 
                CASE 
                    WHEN UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp1)) THEN 0 
                    WHEN UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp2)) THEN 1 
                    WHEN UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp3)) THEN 2 
                    WHEN UPPER(TRIM(h.reqnmbr)) = UPPER(TRIM(:rfp4)) THEN 3 
                    ELSE 4 
                END
            LIMIT 1
        ");
        $stmt->execute([
            ':rfp1' => $cleanRfp,
            ':rfp2' => $withDot,
            ':rfp3' => $noPrefix,
            ':rfp4' => $noPrefixWithDot,
            ':rfp_like' => '%' . $noPrefixWithDot . '%'
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Nomor RFP "' . htmlspecialchars($noRfp) . '" tidak ditemukan di database Proint ERP.'
            ]);
            exit;
        }

        // -------------------------------------------------------
        // Step 2: Tentukan nama vendor
        // -------------------------------------------------------
        $vendor = trim($row['vendorname'] ?? '');
        if (empty($vendor)) $vendor = trim($row['reqpaidtoaccname'] ?? '');
        if (empty($vendor)) {
            $vendor = trim($row['reqpaidto'] ?? '');
            if ($vendor === '-') $vendor = '';
        }
        $vendorFormatted = !empty($vendor) ? ucwords(strtolower($vendor)) : '';

        // -------------------------------------------------------
        // Step 3: Ekstrak No. PO dari reqdesc menggunakan regex
        // Pola: POLC/YYYY/NNNN, POLD/YYYY/NNNN, POSD/YYYY/NNNN, dst.
        // -------------------------------------------------------
        $reqdesc = trim($row['reqdesc'] ?? '');
        $poPattern = '/\b(POL[CD]|POSD|POIM|PO[A-Z]{0,3})\/(\d{4})\/(\d{4})\b/i';
        preg_match_all($poPattern, $reqdesc, $poMatches);

        // Buat daftar No. PO unik (uppercase untuk konsistensi)
        $extractedPoList = [];
        foreach ($poMatches[0] as $po) {
            $poUpper = strtoupper(trim($po));
            if (!in_array($poUpper, $extractedPoList)) {
                $extractedPoList[] = $poUpper;
            }
        }

        // -------------------------------------------------------
        // Step 4: Untuk setiap No. PO, cari di prpohd lalu ambil GRN
        // -------------------------------------------------------
        $allGrnList = [];
        $validPoList = []; // PO yang benar-benar ditemukan di prpohd

        foreach ($extractedPoList as $poNum) {
            $stmtPo = $conn3->prepare("
                SELECT pohdid, ponmbr, povendorname
                FROM prpohd
                WHERE UPPER(TRIM(ponmbr)) = UPPER(TRIM(:po))
                   OR UPPER(TRIM(draftnmbr)) = UPPER(TRIM(:po))
                LIMIT 1
            ");
            $stmtPo->execute([':po' => $poNum]);
            $poRow = $stmtPo->fetch(PDO::FETCH_ASSOC);

            if ($poRow) {
                $validPoList[] = $poRow['ponmbr']; // gunakan format resmi dari DB

                // Ambil semua GRN untuk PO ini
                $stmtGrn = $conn3->prepare("
                    SELECT DISTINCT gh.grnnmbr
                    FROM prgrndt gd
                    JOIN prgrnhd gh ON gd.grnhdid = gh.grnhdid
                    WHERE gd.pohdid = :pohdid
                    ORDER BY gh.grnnmbr ASC
                ");
                $stmtGrn->execute([':pohdid' => $poRow['pohdid']]);
                $grnRows = $stmtGrn->fetchAll(PDO::FETCH_COLUMN);

                foreach ($grnRows as $grn) {
                    if (!in_array($grn, $allGrnList)) {
                        $allGrnList[] = $grn;
                    }
                }
            } else {
                // PO tidak ditemukan di prpohd, tetap masukkan sebagai-adalah
                $validPoList[] = $poNum;
            }
        }

        // Gabungkan semua dengan koma
        $poCombined = implode(', ', $validPoList);
        $grnCombined = !empty($allGrnList) ? implode(', ', $allGrnList) : '-';

        echo json_encode([
            'status' => 'success',
            'data' => [
                'reqhdid'      => $row['reqhdid'],
                'reqnmbr'      => $row['reqnmbr'],
                'reqvendorid'  => $row['reqvendorid'],
                'vendor'       => $vendorFormatted,
                'vendor_raw'   => $vendor,
                'reqdesc'      => $reqdesc,
                'po_extracted' => $extractedPoList,   // daftar PO yang diekstrak dari reqdesc
                'po_list'      => $validPoList,        // daftar PO final (dari prpohd)
                'po_combined'  => $poCombined,         // gabungan untuk diisi ke field No. PO
                'grn_list'     => $allGrnList,         // daftar GRN dari semua PO
                'grn_combined' => $grnCombined         // gabungan untuk diisi ke field No. GRN
            ]
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Terjadi kendala saat membaca data RFP: ' . $e->getMessage()
        ]);
        exit;
    }
}

// =========================================================================
// ACTION: SEARCH_PO (Live Autocomplete dari ERP Proint)
// =========================================================================
if ($action === 'search_po') {
    $term = trim($_GET['term'] ?? ($_POST['term'] ?? ''));
    if (empty($term)) {
        echo json_encode(['status' => 'success', 'results' => []]);
        exit;
    }

    $koneksi3Path = file_exists(__DIR__ . '/../../koneksi3.php') ? __DIR__ . '/../../koneksi3.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php');
    if (!file_exists($koneksi3Path)) {
        echo json_encode(['status' => 'error', 'message' => 'File koneksi3.php tidak ditemukan!']);
        exit;
    }

    try {
        include_once $koneksi3Path;
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi Proint gagal']);
            exit;
        }

        $stmt = $conn3->prepare("
            SELECT DISTINCT ponmbr, povendorname, podate 
            FROM prpohd 
            WHERE UPPER(ponmbr) LIKE UPPER(:term) 
               OR UPPER(draftnmbr) LIKE UPPER(:term)
            ORDER BY podate DESC, ponmbr DESC
            LIMIT 15
        ");
        $stmt->execute([':term' => '%' . $term . '%']);
        $results = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => $r['ponmbr'],
                'text' => $r['ponmbr'] . ' - ' . ucwords(strtolower(trim($r['povendorname'] ?? '')))
            ];
        }
        echo json_encode(['status' => 'success', 'results' => $results]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// ACTION: GET_PO_KETERANGAN_LIST (Daftar unik type_keterangan khusus type PO)
// =========================================================================
if ($action === 'get_po_keterangan_list') {
    $list = ['Untuk dibuat Cek', 'Untuk di bayar'];
    if ($tableReady) {
        $qKet = sqlsrv_query($conn, "
            SELECT DISTINCT LTRIM(RTRIM(type_keterangan)) AS ket 
            FROM dbo.purchasing_header 
            WHERE type = 'PO' 
              AND type_keterangan IS NOT NULL 
              AND LTRIM(RTRIM(type_keterangan)) <> '' 
              AND is_deleted = 0
            ORDER BY ket ASC
        ");
        if ($qKet) {
            while ($rk = sqlsrv_fetch_array($qKet, SQLSRV_FETCH_ASSOC)) {
                $val = trim($rk['ket'] ?? '');
                if (!empty($val) && !in_array($val, $list, true)) {
                    $list[] = $val;
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'data' => array_values($list)]);
    exit;
}

// =========================================================================
// ACTION: ADD (Tambah Dokumen Baru)
// =========================================================================
if ($action === 'add') {
    $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $items = $_POST['items'] ?? [];
    $pengirim = trim($_POST['pengirim'] ?? $currentUser['formatted']);
    $penerima = ''; // Penerima dikosongkan — akan diisi otomatis saat tanda tangan
    
    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada item dokumen yang disimpan!']);
        exit;
    }

    if ($tableReady) {
        // --- SIMPAN KE DATABASE SQL SERVER ---
        sqlsrv_begin_transaction($conn);
        try {
            foreach ($items as $item) {
                $rawType = trim($item['type'] ?? 'PO');
                $typeKet = trim($item['type_keterangan'] ?? '');
                $isMultiPo = 0;
                $poItems = [];
                $descriptions = [];
                $vendor = '';
                $noPo = '';
                $noGrn = '-';

                if ($rawType === 'PO' || stripos($rawType, 'Untuk dibuat Cek') !== false || stripos($typeKet, 'Untuk dibuat Cek') !== false) {
                    $rawType = 'PO';
                    $isMultiPo = 1;
                    if (empty($typeKet)) {
                        $typeKet = 'Untuk dibuat Cek';
                    }
                }

                if ($isMultiPo && !empty($item['po_items']) && is_array($item['po_items'])) {
                    $subPoList = [];
                    $vendorsList = [];
                    $posList = [];
                    $grnsList = [];
                    foreach ($item['po_items'] as $sub) {
                        $subDesc = trim($sub['desc'] ?? '');
                        $subVendor = trim($sub['vendor'] ?? '');
                        $subPo = trim($sub['no_po'] ?? '');
                        $subGrn = trim($sub['no_grn'] ?? '-') ?: '-';

                        if (!empty($subDesc) || !empty($subPo)) {
                            $subPoList[] = [
                                'no' => count($subPoList) + 1,
                                'desc' => $subDesc,
                                'vendor' => $subVendor,
                                'no_po' => $subPo,
                                'no_grn' => $subGrn
                            ];
                            $descriptions[] = $subDesc;
                            if (!empty($subVendor) && !in_array($subVendor, $vendorsList)) $vendorsList[] = $subVendor;
                            if (!empty($subPo)) $posList[] = $subPo;
                            if (!empty($subGrn) && $subGrn !== '-') {
                                foreach (array_map('trim', explode(',', $subGrn)) as $gPiece) {
                                    if (!empty($gPiece) && $gPiece !== '-' && !in_array($gPiece, $grnsList)) {
                                        $grnsList[] = $gPiece;
                                    }
                                }
                            }
                        }
                    }
                    $poItems = $subPoList;
                    $vendor = implode(', ', $vendorsList);
                    $noPo = implode(', ', $posList);
                    $noGrn = !empty($grnsList) ? implode(', ', $grnsList) : '-';

                    // Gabungkan uraian berkas tambahan (Dokumen Lampiran) yang diinput manual
                    $extraDescs = $item['descriptions'] ?? [];
                    if (!is_array($extraDescs)) $extraDescs = [];
                    foreach ($extraDescs as $ed) {
                        $ed = trim($ed);
                        if (!empty($ed)) $descriptions[] = $ed;
                    }
                } else {
                    $descriptions = $item['descriptions'] ?? [];
                    if (!is_array($descriptions)) {
                        $descriptions = array_filter(array_map('trim', explode("\n", strval($descriptions))));
                    }
                    $vendor = trim($item['vendor'] ?? '');
                    $noPo = trim($item['no_po'] ?? '');
                    $noGrn = trim($item['no_grn'] ?? '-') ?: '-';
                }

                // Insert Header
                $sqlH = "INSERT INTO dbo.purchasing_header 
                         (tanggal, type, type_keterangan, is_multi_po, no_po, vendor, no_grn, pengirim, penerima, status_ttd, created_by)
                         OUTPUT INSERTED.id
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu TTD', ?)";
                $paramsH = [
                    $tanggal,
                    $rawType,
                    $typeKet,
                    $isMultiPo,
                    $noPo,
                    $vendor,
                    $noGrn,
                    $pengirim,
                    $penerima,
                    $currentUser['user']
                ];
                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                if (!$stmtH || !($rowH = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC))) {
                    throw new Exception("Gagal menyimpan header: " . print_r(sqlsrv_errors(), true));
                }
                $headerId = $rowH['id'];

                // Insert Descriptions (Detail)
                $rowOrder = 1;
                foreach ($descriptions as $desc) {
                    if (empty(trim($desc))) continue;
                    $sqlD = "INSERT INTO dbo.purchasing_detail (header_id, row_order, description) VALUES (?, ?, ?)";
                    $stmtD = sqlsrv_query($conn, $sqlD, [$headerId, $rowOrder++, trim($desc)]);
                    if (!$stmtD) throw new Exception("Gagal menyimpan detail: " . print_r(sqlsrv_errors(), true));
                }

                // Insert Multi-PO items jika ada
                if ($isMultiPo && !empty($poItems)) {
                    foreach ($poItems as $pItem) {
                        $sqlP = "INSERT INTO dbo.purchasing_po_items (header_id, item_no, no_po, description, vendor, no_grn) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmtP = sqlsrv_query($conn, $sqlP, [
                            $headerId,
                            $pItem['no'],
                            $pItem['no_po'],
                            $pItem['desc'],
                            $pItem['vendor'],
                            $pItem['no_grn']
                        ]);
                        if (!$stmtP) throw new Exception("Gagal menyimpan rincian PO: " . print_r(sqlsrv_errors(), true));
                    }
                }
            }

            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Data serah terima berhasil disimpan ke database!']);
            exit;
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
            exit;
        }
    } else {
        // --- SIMPAN KE SESSION (FALLBACK SEBELUM QUERY SQL DIJALANKAN) ---
        $maxId = 0;
        foreach ($_SESSION['purchasing_data'] as $d) {
            if ($d['id'] > $maxId) $maxId = $d['id'];
        }

        foreach ($items as $item) {
            $maxId++;
            $rawType = trim($item['type'] ?? 'PO');
            $typeKet = trim($item['type_keterangan'] ?? '');
            $isMultiPo = false;
            $poItems = [];
            $descriptions = [];
            $vendor = '';
            $noPo = '';
            $noGrn = '-';

            if ($rawType === 'PO' || stripos($rawType, 'Untuk dibuat Cek') !== false || stripos($typeKet, 'Untuk dibuat Cek') !== false) {
                $rawType = 'PO';
                $isMultiPo = true;
                if (empty($typeKet)) {
                    $typeKet = 'Untuk dibuat Cek';
                }
            }

            if ($isMultiPo && !empty($item['po_items']) && is_array($item['po_items'])) {
                $subPoList = [];
                $vendorsList = [];
                $posList = [];
                $grnsList = [];
                foreach ($item['po_items'] as $sub) {
                    $subDesc = trim($sub['desc'] ?? '');
                    $subVendor = trim($sub['vendor'] ?? '');
                    $subPo = trim($sub['no_po'] ?? '');
                    $subGrn = trim($sub['no_grn'] ?? '-') ?: '-';

                    if (!empty($subDesc) || !empty($subPo)) {
                        $subPoList[] = [
                            'no' => count($subPoList) + 1,
                            'desc' => $subDesc,
                            'vendor' => $subVendor,
                            'no_po' => $subPo,
                            'no_grn' => $subGrn
                        ];
                        $descriptions[] = $subDesc;
                        if (!empty($subVendor) && !in_array($subVendor, $vendorsList)) $vendorsList[] = $subVendor;
                        if (!empty($subPo)) $posList[] = $subPo;
                        if (!empty($subGrn) && $subGrn !== '-') {
                            foreach (array_map('trim', explode(',', $subGrn)) as $gPiece) {
                                if (!empty($gPiece) && $gPiece !== '-' && !in_array($gPiece, $grnsList)) {
                                    $grnsList[] = $gPiece;
                                }
                            }
                        }
                    }
                }
                $poItems = $subPoList;
                $vendor = implode(', ', $vendorsList);
                $noPo = implode(', ', $posList);
                $noGrn = !empty($grnsList) ? implode(', ', $grnsList) : '-';
            } else {
                $descriptions = $item['descriptions'] ?? [];
                if (!is_array($descriptions)) {
                    $descriptions = array_filter(array_map('trim', explode("\n", strval($descriptions))));
                }
                $vendor = trim($item['vendor'] ?? '');
                $noPo = trim($item['no_po'] ?? '');
                $noGrn = trim($item['no_grn'] ?? '-') ?: '-';
            }

            $_SESSION['purchasing_data'][] = [
                'id' => $maxId,
                'tanggal' => $tanggal,
                'type' => $rawType,
                'type_keterangan' => $typeKet,
                'is_multi_po' => $isMultiPo,
                'po_items' => $poItems,
                'descriptions' => $descriptions,
                'vendor' => $vendor,
                'no_po' => $noPo,
                'no_grn' => $noGrn,
                'pengirim' => $pengirim,
                'penerima' => $penerima,
                'status_ttd_raw' => 'Menunggu TTD'
            ];
        }

        echo json_encode(['status' => 'success', 'message' => 'Data serah terima berhasil disimpan!']);
        exit;
    }
}

// =========================================================================
// ACTION: EDIT (Perbarui Dokumen)
// =========================================================================
if ($action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid!']);
        exit;
    }

    $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $rawType = trim($_POST['type'] ?? 'PO');
    $typeKet = trim($_POST['type_keterangan'] ?? '');
    $pengirim = trim($_POST['pengirim'] ?? '');
    // Penerima tidak diambil dari POST — tidak boleh diubah via edit jika sudah ada TTD
    // Penerima hanya diisi otomatis saat tanda tangan

    $isMultiPo = false;
    if ($rawType === 'PO' || stripos($rawType, 'Untuk dibuat Cek') !== false || stripos($typeKet, 'Untuk dibuat Cek') !== false) {
        $rawType = 'PO';
        $isMultiPo = true;
        if (empty($typeKet)) {
            $typeKet = 'Untuk dibuat Cek';
        }
    }

    $subPoList = [];
    $descriptions = [];
    $vendor = '';
    $noPo = '';
    $noGrn = '-';

    if ($isMultiPo && !empty($_POST['po_items']) && is_array($_POST['po_items'])) {
        $vendorsList = [];
        $posList = [];
        $grnsList = [];
        foreach ($_POST['po_items'] as $sub) {
            $subDesc = trim($sub['desc'] ?? '');
            $subVendor = trim($sub['vendor'] ?? '');
            $subPo = trim($sub['no_po'] ?? '');
            $subGrn = trim($sub['no_grn'] ?? '-') ?: '-';

            if (!empty($subDesc) || !empty($subPo)) {
                $subPoList[] = [
                    'no' => count($subPoList) + 1,
                    'desc' => $subDesc,
                    'vendor' => $subVendor,
                    'no_po' => $subPo,
                    'no_grn' => $subGrn
                ];
                $descriptions[] = $subDesc;
                if (!empty($subVendor) && !in_array($subVendor, $vendorsList)) $vendorsList[] = $subVendor;
                if (!empty($subPo)) $posList[] = $subPo;
                if (!empty($subGrn) && $subGrn !== '-') {
                    foreach (array_map('trim', explode(',', $subGrn)) as $gPiece) {
                        if (!empty($gPiece) && $gPiece !== '-' && !in_array($gPiece, $grnsList)) {
                            $grnsList[] = $gPiece;
                        }
                    }
                }
            }
        }
        $vendor = implode(', ', $vendorsList);
        $noPo = implode(', ', $posList);
        $noGrn = !empty($grnsList) ? implode(', ', $grnsList) : '-';

        // Gabungkan uraian berkas tambahan (Dokumen Lampiran) yang diinput manual
        $extraDescsEdit = $_POST['descriptions'] ?? [];
        if (!is_array($extraDescsEdit)) $extraDescsEdit = [];
        foreach ($extraDescsEdit as $ed) {
            $ed = trim($ed);
            if (!empty($ed)) $descriptions[] = $ed;
        }
    } else {
        $vendor = trim($_POST['vendor'] ?? '');
        $noPo = trim($_POST['no_po'] ?? '');
        $noGrn = trim($_POST['no_grn'] ?? '-') ?: '-';
        $descRaw = trim($_POST['description'] ?? '');
        $descriptions = array_filter(array_map('trim', explode("\n", $descRaw)));
    }

    if ($tableReady) {
        sqlsrv_begin_transaction($conn);
        try {
            $sqlCheck = "SELECT status_ttd FROM dbo.purchasing_header WHERE id = ? AND is_deleted = 0";
            $stmtC = sqlsrv_query($conn, $sqlCheck, [$id]);
            if (!$stmtC || !($rowC = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC))) {
                throw new Exception("Data tidak ditemukan!");
            }

            $sqlUpdateH = "UPDATE dbo.purchasing_header SET 
                            tanggal = ?, type = ?, type_keterangan = ?, is_multi_po = ?, 
                            no_po = ?, vendor = ?, no_grn = ?, pengirim = ?,
                            updated_by = ?, updated_at = GETDATE()
                           WHERE id = ?";
            $paramsU = [
                $tanggal, $rawType, $typeKet, $isMultiPo ? 1 : 0,
                $noPo, $vendor, $noGrn, $pengirim,
                $currentUser['user'], $id
            ];
            $stmtU = sqlsrv_query($conn, $sqlUpdateH, $paramsU);
            if (!$stmtU) throw new Exception("Gagal update header: " . print_r(sqlsrv_errors(), true));

            // Replace Details
            sqlsrv_query($conn, "DELETE FROM dbo.purchasing_detail WHERE header_id = ?", [$id]);
            $order = 1;
            foreach ($descriptions as $desc) {
                if (empty(trim($desc))) continue;
                sqlsrv_query($conn, "INSERT INTO dbo.purchasing_detail (header_id, row_order, description) VALUES (?, ?, ?)", [$id, $order++, trim($desc)]);
            }

            // Replace Multi PO
            sqlsrv_query($conn, "DELETE FROM dbo.purchasing_po_items WHERE header_id = ?", [$id]);
            if ($isMultiPo && !empty($subPoList)) {
                foreach ($subPoList as $pItem) {
                    sqlsrv_query($conn, "INSERT INTO dbo.purchasing_po_items (header_id, item_no, no_po, description, vendor, no_grn) VALUES (?, ?, ?, ?, ?, ?)", [
                        $id, $pItem['no'], $pItem['no_po'], $pItem['desc'], $pItem['vendor'], $pItem['no_grn']
                    ]);
                }
            }

            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Data serah terima berhasil diperbarui!']);
            exit;
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    } else {
        $found = false;
        foreach ($_SESSION['purchasing_data'] as &$row) {
            if ($row['id'] === $id) {
                $found = true;
                $row['tanggal'] = $tanggal;
                $row['type'] = $rawType;
                $row['type_keterangan'] = $typeKet;
                $row['is_multi_po'] = $isMultiPo;
                $row['po_items'] = $subPoList;
                $row['descriptions'] = $descriptions;
                $row['vendor'] = $vendor;
                $row['no_po'] = $noPo;
                $row['no_grn'] = $noGrn;
                if (!empty($pengirim)) $row['pengirim'] = $pengirim;
                // penerima tidak diubah via edit — hanya diisi saat TTD
                break;
            }
        }
        if ($found) {
            echo json_encode(['status' => 'success', 'message' => 'Data serah terima berhasil diperbarui!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan!']);
        }
        exit;
    }
}

// =========================================================================
// ACTION: DELETE (Hapus Dokumen)
// =========================================================================
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid!']);
        exit;
    }

    if ($tableReady) {
        $sqlDel = "UPDATE dbo.purchasing_header SET is_deleted = 1, updated_by = ?, updated_at = GETDATE() WHERE id = ?";
        $stmtDel = sqlsrv_query($conn, $sqlDel, [$currentUser['user'], $id]);
        if ($stmtDel) {
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data dari database!']);
        }
        exit;
    } else {
        $initialCount = count($_SESSION['purchasing_data']);
        $_SESSION['purchasing_data'] = array_values(array_filter($_SESSION['purchasing_data'], function($r) use ($id) {
            return $r['id'] !== $id;
        }));

        if (count($_SESSION['purchasing_data']) < $initialCount) {
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan untuk dihapus!']);
        }
        exit;
    }
}

// =========================================================================
// ACTION: SIGN (Tanda Tangan Dokumen — Siapapun yang login bisa TTD)
// Penerima akan diisi otomatis dengan Nama (Departemen) akun yang TTD
// =========================================================================
if ($action === 'sign') {
    $id = intval($_POST['id'] ?? 0);
    $signatureData = trim($_POST['signature_data'] ?? '');

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid!']);
        exit;
    }

    // Nama penerima = nama (departemen) akun yang sedang login & TTD
    $penerimaOtomatis = $currentUser['formatted'];

    if ($tableReady) {
        // Cek dokumen ada
        $sqlDoc = "SELECT id, status_ttd FROM dbo.purchasing_header WHERE id = ? AND is_deleted = 0";
        $stmtDoc = sqlsrv_query($conn, $sqlDoc, [$id]);
        if (!$stmtDoc || !($doc = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Dokumen tidak ditemukan!']);
            exit;
        }

        if ($doc['status_ttd'] === 'Sudah Ditandatangani') {
            echo json_encode(['status' => 'error', 'message' => 'Dokumen ini sudah pernah ditandatangani!']);
            exit;
        }

        // Pastikan kolom signature_data ada
        $hasSigCol = false;
        $qCol = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.purchasing_header', 'signature_data') AS col_len");
        if ($qCol && ($colRow = sqlsrv_fetch_array($qCol, SQLSRV_FETCH_ASSOC))) {
            if (!is_null($colRow['col_len'])) $hasSigCol = true;
        }
        if (!$hasSigCol) {
            @sqlsrv_query($conn, "ALTER TABLE dbo.purchasing_header ADD [signature_data] NVARCHAR(MAX) NULL");
            $hasSigCol = true;
        }

        // Update: set status TTD, isi penerima dengan akun yang TTD, simpan signature
        if ($hasSigCol) {
            $sqlSign = "UPDATE dbo.purchasing_header SET 
                        status_ttd = 'Sudah Ditandatangani', 
                        penerima = ?,
                        signed_by = ?, 
                        signed_at = GETDATE(),
                        signature_data = ?
                        WHERE id = ?";
            $stmtSign = sqlsrv_query($conn, $sqlSign, [$penerimaOtomatis, $currentUser['formatted'], $signatureData, $id]);
        } else {
            $sqlSign = "UPDATE dbo.purchasing_header SET 
                        status_ttd = 'Sudah Ditandatangani', 
                        penerima = ?,
                        signed_by = ?, 
                        signed_at = GETDATE() 
                        WHERE id = ?";
            $stmtSign = sqlsrv_query($conn, $sqlSign, [$penerimaOtomatis, $currentUser['formatted'], $id]);
        }

        if ($stmtSign) {
            echo json_encode(['status' => 'success', 'message' => 'Tanda tangan berhasil! Penerima tercatat: ' . htmlspecialchars($penerimaOtomatis)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status tanda tangan di database!']);
        }
        exit;
    } else {
        // Fallback Session
        $found = false;
        foreach ($_SESSION['purchasing_data'] as &$row) {
            if ($row['id'] === $id) {
                $found = true;
                if (($row['status_ttd_raw'] ?? '') === 'Sudah Ditandatangani') {
                    echo json_encode(['status' => 'error', 'message' => 'Dokumen ini sudah pernah ditandatangani!']);
                    exit;
                }
                // Isi penerima dengan akun yang TTD
                $row['penerima'] = $penerimaOtomatis;
                $row['status_ttd_raw'] = 'Sudah Ditandatangani';
                $row['signed_by'] = $currentUser['formatted'];
                $row['signed_at'] = date('Y-m-d H:i:s');
                $row['signature_data'] = $signatureData;
                break;
            }
        }

        if (!$found) {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan!']);
            exit;
        }

        echo json_encode(['status' => 'success', 'message' => 'Tanda tangan berhasil! Penerima tercatat: ' . htmlspecialchars($penerimaOtomatis)]);
        exit;
    }
}

// =========================================================================
// ACTION: BATCH_SIGN (Tanda Tangan Massal / Batch Sign)
// =========================================================================
if ($action === 'batch_sign') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : explode(',', $ids);
        } else {
            $ids = [];
        }
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function($val) { return $val > 0; })));

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada dokumen valid yang dipilih untuk ditandatangani!']);
        exit;
    }

    $signatureData = trim($_POST['signature_data'] ?? '');
    $penerimaOtomatis = $currentUser['formatted'];

    if ($tableReady) {
        // Pastikan kolom signature_data ada
        $hasSigCol = false;
        $qCol = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.purchasing_header', 'signature_data') AS col_len");
        if ($qCol && ($colRow = sqlsrv_fetch_array($qCol, SQLSRV_FETCH_ASSOC))) {
            if (!is_null($colRow['col_len'])) $hasSigCol = true;
        }
        if (!$hasSigCol) {
            @sqlsrv_query($conn, "ALTER TABLE dbo.purchasing_header ADD [signature_data] NVARCHAR(MAX) NULL");
            $hasSigCol = true;
        }

        // Ambil ID yang valid (belum pernah ditandatangani dan is_deleted = 0)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sqlCheck = "SELECT id FROM dbo.purchasing_header WHERE id IN ($placeholders) AND is_deleted = 0 AND (status_ttd IS NULL OR status_ttd != 'Sudah Ditandatangani')";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, $ids);
        $validIds = [];
        if ($stmtCheck) {
            while ($rCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
                $validIds[] = intval($rCheck['id']);
            }
        }

        if (empty($validIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada dokumen yang memenuhi syarat untuk ditandatangani (mungkin sudah ditandatangani sebelumnya).']);
            exit;
        }

        $vPlaceholders = implode(',', array_fill(0, count($validIds), '?'));
        if ($hasSigCol) {
            $sqlBatch = "UPDATE dbo.purchasing_header SET 
                         status_ttd = 'Sudah Ditandatangani', 
                         penerima = ?,
                         signed_by = ?, 
                         signed_at = GETDATE(),
                         signature_data = ?
                         WHERE id IN ($vPlaceholders)";
            $params = array_merge([$penerimaOtomatis, $currentUser['formatted'], $signatureData], $validIds);
        } else {
            $sqlBatch = "UPDATE dbo.purchasing_header SET 
                         status_ttd = 'Sudah Ditandatangani', 
                         penerima = ?,
                         signed_by = ?, 
                         signed_at = GETDATE() 
                         WHERE id IN ($vPlaceholders)";
            $params = array_merge([$penerimaOtomatis, $currentUser['formatted']], $validIds);
        }

        $stmtBatch = sqlsrv_query($conn, $sqlBatch, $params);
        if ($stmtBatch) {
            echo json_encode([
                'status' => 'success', 
                'message' => count($validIds) . ' dokumen berhasil ditandatangani! Penerima tercatat: ' . htmlspecialchars($penerimaOtomatis),
                'signed_count' => count($validIds)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui tanda tangan massal di database!']);
        }
        exit;
    } else {
        // Fallback Session Data
        $updatedCount = 0;
        foreach ($_SESSION['purchasing_data'] as &$row) {
            if (in_array(intval($row['id']), $ids) && ($row['status_ttd_raw'] ?? '') !== 'Sudah Ditandatangani') {
                $row['penerima'] = $penerimaOtomatis;
                $row['status_ttd_raw'] = 'Sudah Ditandatangani';
                $row['signed_by'] = $currentUser['formatted'];
                $row['signed_at'] = date('Y-m-d H:i:s');
                $row['signature_data'] = $signatureData;
                $updatedCount++;
            }
        }
        if ($updatedCount === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada dokumen yang dapat ditandatangani (sudah TTD atau tidak ditemukan).']);
            exit;
        }

        echo json_encode([
            'status' => 'success', 
            'message' => $updatedCount . ' dokumen berhasil ditandatangani! Penerima tercatat: ' . htmlspecialchars($penerimaOtomatis),
            'signed_count' => $updatedCount
        ]);
        exit;
    }
}

// =========================================================================
// HELPER: RENDER SATU BARIS DATA PURCHASING DENGAN DESAIN RAPI & MODERN
// =========================================================================
function renderPurchasingRowItem($row, $descList, $poItems, $isMultiPo, $currentUser) {
    // 1. Render Type Badge
    $type = htmlspecialchars(trim($row['type'] ?? ''));
    $typeKet = trim($row['type_keterangan'] ?? '');
    $typeClass = 'badge-type-default';
    if (stripos($type, 'COD') !== false) $typeClass = 'badge-type-cod';
    elseif (stripos($type, 'PO') !== false) $typeClass = 'badge-type-po';
    elseif (stripos($type, 'RFP') !== false) $typeClass = 'badge-type-rfp';
    elseif (stripos($type, 'OFFSET') !== false) $typeClass = 'badge-type-offset';
    elseif (stripos($type, 'Kontrabon') !== false) $typeClass = 'badge-type-kontrabon';

    $typeHtml = '<span class="badge ' . $typeClass . '">' . $type . '</span>';
    if (!empty($typeKet)) {
        $typeHtml .= '<div class="mt-1"><span class="badge badge-type-sub">' . htmlspecialchars($typeKet) . '</span></div>';
    }

    // 2. Render Multi-item Rows (Description, Vendor, No PO, No GRN)
    if ($isMultiPo && !empty($poItems)) {
        $descHtml   = '<div class="multi-line-wrapper">';
        $vendorHtml = '<div class="multi-line-wrapper">';
        $poHtml     = '<div class="multi-line-wrapper">';
        $grnHtml    = '<div class="multi-line-wrapper">';

        foreach ($poItems as $idx => $pi) {
            $borderClass = ($idx > 0) ? 'border-top' : '';
            $num = $pi['no'] ?? ($idx + 1);
            $descVal = htmlspecialchars($pi['desc'] ?? '');
            $vendorVal = htmlspecialchars($pi['vendor'] ?? '');
            $poVal = htmlspecialchars($pi['no_po'] ?? '');
            $grnRaw = trim($pi['no_grn'] ?? '-') ?: '-';
            if ($grnRaw !== '-') {
                $grnBadges = [];
                foreach (array_map('trim', explode(',', $grnRaw)) as $gItem) {
                    if ($gItem !== '') {
                        $grnBadges[] = '<span class="badge-code badge-grn mb-1">' . htmlspecialchars($gItem) . '</span>';
                    }
                }
                $grnBadge = !empty($grnBadges) ? implode(' ', $grnBadges) : '<span class="text-muted small">-</span>';
            } else {
                $grnBadge = '<span class="text-muted small">-</span>';
            }

            $descHtml   .= "<div class='sub-row-item text-left {$borderClass}'><span class='item-num-badge'>{$num}</span><span class='sub-row-text font-weight-500'>{$descVal}</span></div>";
            $vendorHtml .= "<div class='sub-row-item text-left {$borderClass}'><span class='vendor-name font-weight-bold'>{$vendorVal}</span></div>";
            $poHtml     .= "<div class='sub-row-item sub-row-item-center {$borderClass}'><span class='badge-code badge-po'>{$poVal}</span></div>";
            $grnHtml    .= "<div class='sub-row-item sub-row-item-center {$borderClass}'>{$grnBadge}</div>";
        }

        $descHtml   .= '</div>';
        $vendorHtml .= '</div>';
        $poHtml     .= '</div>';
        $grnHtml    .= '</div>';
    } else {
        $descHtml = '<div class="multi-line-wrapper">';
        foreach ($descList as $idx => $desc) {
            $borderClass = ($idx > 0) ? 'border-top' : '';
            $num = $idx + 1;
            $descHtml .= "<div class='sub-row-item text-left {$borderClass}'><span class='item-num-badge'>{$num}</span><span class='sub-row-text'>{$desc}</span></div>";
        }
        $descHtml .= '</div>';

        $vendorText = htmlspecialchars($row['vendor'] ?? '');
        $vendorHtml = "<div class='sub-row-item text-left'><span class='vendor-name font-weight-bold'>{$vendorText}</span></div>";

        $noPoText = htmlspecialchars($row['no_po'] ?? '');
        $poHtml = (!empty($noPoText) && $noPoText !== '-')
            ? "<div class='sub-row-item sub-row-item-center'><span class='badge-code badge-po'>{$noPoText}</span></div>"
            : "<div class='sub-row-item sub-row-item-center text-muted small'>-</div>";

        $grnRaw = trim($row['no_grn'] ?? '-') ?: '-';
        if ($grnRaw !== '-') {
            $grnBadges = [];
            foreach (array_map('trim', explode(',', $grnRaw)) as $gItem) {
                if ($gItem !== '') {
                    $grnBadges[] = '<span class="badge-code badge-grn mb-1">' . htmlspecialchars($gItem) . '</span>';
                }
            }
            $grnVal = !empty($grnBadges) ? implode(' ', $grnBadges) : '<span class="text-muted small">-</span>';
        } else {
            $grnVal = '<span class="text-muted small">-</span>';
        }
        $grnHtml = "<div class='sub-row-item sub-row-item-center'>{$grnVal}</div>";
    }

    // 3. Status Tanda Tangan
    $statusTtd = $row['status_ttd'] ?? ($row['status_ttd_raw'] ?? 'Menunggu TTD');
    $isSigned = ($statusTtd === 'Sudah Ditandatangani');
    if ($isSigned) {
        $tandaTangan = '<span class="badge-status-signed"><i class="fas fa-check-circle mr-1 text-success"></i>Sudah TTD</span>';
    } else {
        $tandaTangan = '<span class="badge-status-pending"><i class="fas fa-clock mr-1 text-warning"></i>Belum TTD</span>';
    }

    // 4. Hak Akses Tanda Tangan: Siapapun yang login bisa TTD (penerima diisi otomatis dari akun yang TTD)
    $penerima = $row['penerima'] ?? '';
    $canSign = !$isSigned; // Semua user bisa TTD selama belum ditandatangani

    $signBtn = '';
    if ($isSigned) {
        $signBtn = '<button type="button" class="btn btn-secondary btn-xs" disabled title="Sudah Ditandatangani oleh: ' . htmlspecialchars($penerima) . '"><i class="fas fa-check"></i></button>';
    } else {
        $signBtn = '<button type="button" class="btn btn-primary btn-xs btn-signature" title="Tanda Tangan Dokumen (Penerima otomatis terisi dengan akun Anda)"><i class="fas fa-file-signature"></i></button>';
    }

    $editBtn = $isSigned 
        ? '<button type="button" class="btn btn-secondary btn-xs" disabled title="Dokumen yang sudah ditandatangani tidak dapat diedit"><i class="fas fa-edit"></i></button>'
        : '<button type="button" class="btn btn-warning btn-xs btn-edit" title="Edit Data"><i class="fas fa-edit"></i></button>';

    $aksi = '
        <div class="action-btn-group">
            <button type="button" class="btn btn-info btn-xs btn-detail" title="View Detail"><i class="fas fa-eye"></i></button>
            ' . $editBtn . '
            <button type="button" class="btn btn-danger btn-xs btn-delete" title="Hapus Data"><i class="fas fa-trash"></i></button>
            ' . $signBtn . '
        </div>
    ';

    $tglRaw = $row['tanggal'] ?? date('Y-m-d');
    $tglDisplay = is_object($tglRaw) ? $tglRaw->format('d/m/Y') : date('d/m/Y', strtotime($tglRaw));
    $tglRawStr = is_object($tglRaw) ? $tglRaw->format('Y-m-d') : $tglRaw;

    $signedAtRaw = $row['signed_at'] ?? null;
    if (!empty($signedAtRaw)) {
        $signedAtDate = is_object($signedAtRaw) ? $signedAtRaw->format('d/m/Y') : date('d/m/Y', strtotime($signedAtRaw));
        $signedAtRawStr = is_object($signedAtRaw) ? $signedAtRaw->format('Y-m-d') : date('Y-m-d', strtotime($signedAtRaw));
        $tglDiterimaHtml = '<span class="font-weight-bold text-dark text-nowrap">' . $signedAtDate . '</span>';
    } else {
        $signedAtRawStr = '';
        $tglDiterimaHtml = '<span class="badge badge-light border text-muted px-2 py-1 font-weight-normal"><i class="fas fa-clock mr-1 text-warning"></i>Belum Diterima</span>';
    }

    return [
        'id'                   => $row['id'],
        'tanggal'              => '<span class="font-weight-bold text-dark text-nowrap">' . $tglDisplay . '</span>',
        'tanggal_raw'          => $tglRawStr,
        'type'                 => $typeHtml,
        'type_raw'             => $row['type'],
        'type_keterangan'      => $typeKet,
        'is_multi_po'          => $isMultiPo,
        'po_items'             => $poItems,
        'description'          => $descHtml,
        'descriptions_raw'     => $descList,
        'vendor'               => $vendorHtml,
        'vendor_raw'           => $row['vendor'] ?? '',
        'no_po'                => $poHtml,
        'no_po_raw'            => $row['no_po'] ?? '',
        'no_grn'               => $grnHtml,
        'no_grn_raw'           => $row['no_grn'] ?? '',
        'pengirim'             => $row['pengirim'] ?? '',
        'penerima'             => $row['penerima'] ?? '',
        'tanda_tangan'         => $tandaTangan,
        'tanggal_diterima'     => $tglDiterimaHtml,
        'tanggal_diterima_raw' => $signedAtRawStr,
        'status_ttd_raw'       => $statusTtd,
        'signature_data'       => $row['signature_data'] ?? '',
        'can_sign'             => $canSign,
        'aksi'                 => $aksi
    ];
}

// =========================================================================
// QUERY DATATABLES (SERVER-SIDE)
// =========================================================================
$draw          = intval($_POST['draw'] ?? 1);
$start         = intval($_POST['start'] ?? 0);
$length        = intval($_POST['length'] ?? 10);
$search        = trim($_POST['search']['value'] ?? '');
$filterTanggal = trim($_POST['filterTanggal'] ?? '');
$filterType    = trim($_POST['filterType'] ?? '');
$filterStatus  = trim($_POST['filterStatus'] ?? '');

// Pengurutan / Sort Kolom dari DataTables (Ascending & Descending)
$orderColIdx  = intval($_POST['order'][0]['column'] ?? 3);
$orderDirRaw  = strtolower(trim($_POST['order'][0]['dir'] ?? 'desc'));
$orderDir     = ($orderDirRaw === 'asc') ? 'ASC' : 'DESC';
$orderColName = trim($_POST['columns'][$orderColIdx]['data'] ?? '');

switch ($orderColName) {
    case 'tanggal':
        $orderBySql = "h.tanggal {$orderDir}, h.id {$orderDir}";
        break;
    case 'type':
        $orderBySql = "h.type {$orderDir}, h.type_keterangan {$orderDir}, h.tanggal DESC, h.id DESC";
        break;
    case 'description':
        $orderBySql = "(SELECT TOP 1 d.description FROM dbo.purchasing_detail d WHERE d.header_id = h.id ORDER BY d.row_order ASC) {$orderDir}, h.tanggal DESC, h.id DESC";
        break;
    case 'vendor':
        $orderBySql = "h.vendor {$orderDir}, h.tanggal DESC, h.id DESC";
        break;
    case 'no_po':
        $orderBySql = "h.no_po {$orderDir}, h.tanggal DESC, h.id DESC";
        break;
    case 'no_grn':
        $orderBySql = "h.no_grn {$orderDir}, h.tanggal DESC, h.id DESC";
        break;
    case 'tanda_tangan':
        $orderBySql = "h.status_ttd {$orderDir}, h.tanggal DESC, h.id DESC";
        break;
    case 'tanggal_diterima':
        $orderBySql = "h.signed_at {$orderDir}, h.tanggal DESC, h.id DESC";
        break;
    default:
        if ($orderColIdx === 3) {
            $orderBySql = "h.tanggal {$orderDir}, h.id {$orderDir}";
        } elseif ($orderColIdx === 4) {
            $orderBySql = "h.type {$orderDir}, h.type_keterangan {$orderDir}, h.tanggal DESC, h.id DESC";
        } elseif ($orderColIdx === 5) {
            $orderBySql = "(SELECT TOP 1 d.description FROM dbo.purchasing_detail d WHERE d.header_id = h.id ORDER BY d.row_order ASC) {$orderDir}, h.tanggal DESC, h.id DESC";
        } elseif ($orderColIdx === 6) {
            $orderBySql = "h.vendor {$orderDir}, h.tanggal DESC, h.id DESC";
        } elseif ($orderColIdx === 7) {
            $orderBySql = "h.no_po {$orderDir}, h.tanggal DESC, h.id DESC";
        } elseif ($orderColIdx === 8) {
            $orderBySql = "h.no_grn {$orderDir}, h.tanggal DESC, h.id DESC";
        } elseif ($orderColIdx === 9) {
            $orderBySql = "h.status_ttd {$orderDir}, h.tanggal DESC, h.id DESC";
        } elseif ($orderColIdx === 10) {
            $orderBySql = "h.signed_at {$orderDir}, h.tanggal DESC, h.id DESC";
        } else {
            $orderBySql = "h.tanggal DESC, h.id DESC";
        }
        break;
}

$formattedData = [];
$totalRecords = 0;
$totalFiltered = 0;

if ($tableReady) {
    // --- QUERY REAL DARI SQL SERVER ---
    $qTotal = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM dbo.purchasing_header WHERE is_deleted = 0");
    if ($qTotal && ($tRow = sqlsrv_fetch_array($qTotal, SQLSRV_FETCH_ASSOC))) {
        $totalRecords = intval($tRow['total']);
    }

    $whereClauses = ["h.is_deleted = 0"];
    $queryParams  = [];

    if (!empty($search)) {
        $whereClauses[] = "(h.no_po LIKE ? OR h.vendor LIKE ? OR h.pengirim LIKE ? OR h.penerima LIKE ? OR h.type LIKE ? OR h.type_keterangan LIKE ?)";
        $searchParam = "%{$search}%";
        array_push($queryParams, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    }

    if (!empty($filterTanggal)) {
        $whereClauses[] = "h.tanggal = ?";
        $queryParams[] = $filterTanggal;
    }

    if (!empty($filterType)) {
        $whereClauses[] = "h.type = ?";
        $queryParams[] = $filterType;
    }

    if (!empty($filterStatus)) {
        $whereClauses[] = "h.status_ttd = ?";
        $queryParams[] = $filterStatus;
    }

    $whereSql = implode(' AND ', $whereClauses);

    $qFiltered = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM dbo.purchasing_header h WHERE {$whereSql}", $queryParams);
    if ($qFiltered && ($fRow = sqlsrv_fetch_array($qFiltered, SQLSRV_FETCH_ASSOC))) {
        $totalFiltered = intval($fRow['total']);
    }

    $sqlPage = "SELECT h.id, h.tanggal, h.type, h.type_keterangan, h.is_multi_po, 
                       h.no_po, h.vendor, h.no_grn, h.pengirim, h.penerima, 
                       h.status_ttd, h.signed_by, h.signed_at
                FROM dbo.purchasing_header h
                WHERE {$whereSql}
                ORDER BY {$orderBySql}
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $pageParams = array_merge($queryParams, [$start, $length]);
    $stmtPage = sqlsrv_query($conn, $sqlPage, $pageParams);

    if ($stmtPage) {
        while ($row = sqlsrv_fetch_array($stmtPage, SQLSRV_FETCH_ASSOC)) {
            $headerId = $row['id'];
            $isMultiPo = ($row['is_multi_po'] == 1);

            $descList = [];
            $qDesc = sqlsrv_query($conn, "SELECT description FROM dbo.purchasing_detail WHERE header_id = ? ORDER BY row_order ASC", [$headerId]);
            if ($qDesc) {
                while ($d = sqlsrv_fetch_array($qDesc, SQLSRV_FETCH_ASSOC)) {
                    $descList[] = $d['description'];
                }
            }

            $poItems = [];
            if ($isMultiPo) {
                $qPo = sqlsrv_query($conn, "SELECT item_no, no_po, description, vendor, no_grn FROM dbo.purchasing_po_items WHERE header_id = ? ORDER BY item_no ASC", [$headerId]);
                if ($qPo) {
                    while ($p = sqlsrv_fetch_array($qPo, SQLSRV_FETCH_ASSOC)) {
                        $poItems[] = [
                            'no'     => $p['item_no'],
                            'desc'   => $p['description'],
                            'vendor' => $p['vendor'],
                            'no_po'  => $p['no_po'],
                            'no_grn' => $p['no_grn']
                        ];
                    }
                }
            }

            $formattedData[] = renderPurchasingRowItem($row, $descList, $poItems, $isMultiPo, $currentUser);
        }
    }
} else {
    // --- FALLBACK QUERY DARI SESSION ---
    $allData       = $_SESSION['purchasing_data'] ?? [];
    $totalRecords  = count($allData);

    $filteredData = array_filter($allData, function($item) use ($search, $filterTanggal, $filterType, $filterStatus) {
        if (!empty($search)) {
            $matchSearch = false;
            $fields = [$item['vendor'] ?? '', $item['no_po'] ?? '', $item['type'] ?? '', $item['type_keterangan'] ?? '', $item['pengirim'] ?? '', $item['penerima'] ?? ''];
            if (!empty($item['descriptions']) && is_array($item['descriptions'])) {
                $fields = array_merge($fields, $item['descriptions']);
            }
            if (!empty($item['po_items']) && is_array($item['po_items'])) {
                foreach ($item['po_items'] as $pi) {
                    $fields[] = $pi['desc'] ?? '';
                    $fields[] = $pi['vendor'] ?? '';
                    $fields[] = $pi['no_po'] ?? '';
                }
            }
            foreach ($fields as $field) {
                if (stripos(strval($field), $search) !== false) {
                    $matchSearch = true;
                    break;
                }
            }
            if (!$matchSearch) return false;
        }

        if (!empty($filterTanggal) && ($item['tanggal'] ?? '') !== $filterTanggal) return false;
        if (!empty($filterType) && ($item['type'] ?? '') !== $filterType) return false;
        if (!empty($filterStatus) && ($item['status_ttd_raw'] ?? '') !== $filterStatus) return false;

        return true;
    });

    // Urutkan array pada mode session
    usort($filteredData, function($a, $b) use ($orderColName, $orderDir) {
        $key = ($orderColName === 'tanggal_diterima') ? 'signed_at' : (($orderColName === 'tanda_tangan') ? 'status_ttd_raw' : $orderColName);
        $valA = $a[$key] ?? ($a['tanggal'] ?? '');
        $valB = $b[$key] ?? ($b['tanggal'] ?? '');
        if ($valA == $valB) return 0;
        if ($orderDir === 'ASC') {
            return ($valA < $valB) ? -1 : 1;
        } else {
            return ($valA > $valB) ? -1 : 1;
        }
    });

    $totalFiltered = count($filteredData);
    $pagedData = array_slice(array_values($filteredData), $start, $length);

    foreach ($pagedData as $row) {
        $isMultiPo = !empty($row['is_multi_po']);
        $descList = $row['descriptions'] ?? [];
        $poItems = $row['po_items'] ?? [];
        $formattedData[] = renderPurchasingRowItem($row, $descList, $poItems, $isMultiPo, $currentUser);
    }
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data'            => $formattedData
], JSON_UNESCAPED_UNICODE);
