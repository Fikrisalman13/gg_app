<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__DIR__, 2) . '/koneksi.php';
}
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak ditemukan']);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$form_type = trim($_POST['form_type'] ?? 'cash_on_delivery');
$isBpu = ($form_type === 'bukti_pengembalian_uang' || $form_type === 'bukti_pengembalian_barang');

require_once __DIR__ . '/helper_terbilang.php';
require_once __DIR__ . '/form_purchasing_issue_helper.php';

if ($isBpu) {
    // ══════════════════════════════════════════════════════════════════════════
    // FORM BUKTI PENGEMBALIAN UANG (BPU)
    // ══════════════════════════════════════════════════════════════════════════

    // 1. Verifikasi / Buat Tabel jika belum ada di database SQL Server [GG]
    $checkTableBpu = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_Pengembalian_Uang'");
    if ($checkTableBpu && !sqlsrv_fetch_array($checkTableBpu)) {
        $createHeaderSql = "
            CREATE TABLE [dbo].[Form_Purchasing_Pengembalian_Uang] (
                [id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY CLUSTERED,
                [ticket] VARCHAR(50) NOT NULL UNIQUE NONCLUSTERED,
                [rfp_no] VARCHAR(100) NULL,
                [tgl_rfp] DATE NULL,
                [tanggal] DATE NOT NULL DEFAULT (CAST(GETDATE() AS DATE)),
                [ref_po_no] VARCHAR(100) NULL,
                [supplier] NVARCHAR(255) NULL,
                [rekening_ac] VARCHAR(100) NULL,
                [bank] NVARCHAR(100) NULL,
                [currency] VARCHAR(10) NOT NULL DEFAULT ('IDR'),
                [payment_method] VARCHAR(100) NULL,
                [keterangan] NVARCHAR(MAX) NULL,
                [subtotal] DECIMAL(18, 2) NOT NULL DEFAULT (0.00),
                [terbilang] NVARCHAR(1000) NULL,
                [dibuat_oleh] NVARCHAR(100) NULL,
                [dicek_oleh] NVARCHAR(100) NULL,
                [nama_pemohon] NVARCHAR(100) NULL,
                [departemen] NVARCHAR(100) NULL,
                [status] VARCHAR(50) NOT NULL DEFAULT ('Pending'),
                [alasan_reject] NVARCHAR(1000) NULL,
                [lampiran_path] NVARCHAR(500) NULL,
                [ttd_dibuat_oleh] NVARCHAR(MAX) NULL,
                [ttd_dicek_oleh] NVARCHAR(MAX) NULL,
                [tgl_dicek] DATETIME NULL,
                [items_json] NVARCHAR(MAX) NULL,
                [created_by] VARCHAR(100) NULL,
                [created_at] DATETIME NOT NULL DEFAULT (GETDATE()),
                [updated_by] VARCHAR(100) NULL,
                [updated_at] DATETIME NULL,
                [is_deleted] BIT NOT NULL DEFAULT (0)
            )
        ";
        sqlsrv_query($conn, $createHeaderSql);
    }

    $checkDetailBpu = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_Pengembalian_Uang_Detail'");
    if ($checkDetailBpu && !sqlsrv_fetch_array($checkDetailBpu)) {
        $createDetailSql = "
            CREATE TABLE [dbo].[Form_Purchasing_Pengembalian_Uang_Detail] (
                [id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY CLUSTERED,
                [ticket] VARCHAR(50) NOT NULL,
                [row_no] INT NOT NULL DEFAULT (1),
                [keterangan] NVARCHAR(500) NOT NULL,
                [cd] VARCHAR(5) NOT NULL DEFAULT ('D'),
                [sifat] VARCHAR(5) NOT NULL DEFAULT ('+'),
                [currency] VARCHAR(10) NOT NULL DEFAULT ('IDR'),
                [dpp] DECIMAL(18, 2) NOT NULL DEFAULT (0.00),
                [created_at] DATETIME NOT NULL DEFAULT (GETDATE())
            )
        ";
        sqlsrv_query($conn, $createDetailSql);
    }

    // 2. Generate Ticket: BPU-YYMMDD-XXX
    $prefix = 'BPU-' . date('ymd') . '-';
    $sqlSeq = "SELECT TOP 1 ticket FROM dbo.Form_Purchasing_Pengembalian_Uang WHERE ticket LIKE ? ORDER BY ticket DESC";
    $stmtSeq = sqlsrv_query($conn, $sqlSeq, [$prefix . '%']);
    $nextSeq = 1;
    if ($stmtSeq && $row = sqlsrv_fetch_array($stmtSeq, SQLSRV_FETCH_ASSOC)) {
        $lastTicket = $row['ticket'];
        $parts = explode('-', $lastTicket);
        if (isset($parts[2])) {
            $nextSeq = intval($parts[2]) + 1;
        }
    }
    $ticket = $prefix . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);

    // 3. Tangkap Input Fields
    $rfp_no = trim($_POST['rfp_no'] ?? '');
    $tgl_rfp = !empty($_POST['tgl_rfp']) ? $_POST['tgl_rfp'] : null;
    $tanggal = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
    $ref_po_no = trim($_POST['ref_po_no'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $rekening_ac = trim($_POST['rekening_ac'] ?? '');
    $bank = trim($_POST['bank'] ?? '');
    $currency = trim($_POST['currency'] ?? 'IDR');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $items_json = $_POST['items_json'] ?? '[]';
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $terbilang = ($subtotal > 0) ? getTerbilangLengkapPHP($subtotal, $currency) : trim($_POST['terbilang'] ?? '');
    $dibuat_oleh = trim($_POST['dibuat_oleh'] ?? '');
    $dicek_oleh = trim($_POST['dicek_oleh'] ?? 'KABAG');
    $nama_pemohon = trim($_POST['nama_pemohon'] ?? ($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']));
    $departemen = trim($_POST['departemen'] ?? '');
    $created_by = $_SESSION['UserName'] ?? 'system';
    $ttd_dibuat_oleh = !empty($_POST['ttd_dibuat_oleh']) ? trim($_POST['ttd_dibuat_oleh']) : null;

    // 4. Handle Upload Lampiran (Simpan di C:\xampp\htdocs\gg_app\uploads\form_purchasing)
    $lampiranPath = null;
    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/form_purchasing/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if (in_array($ext, $allowed)) {
            $newFilename = 'BPU_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $newFilename;
            if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $destPath)) {
                $lampiranPath = 'uploads/form_purchasing/' . $newFilename;
            }
        }
    }

    // 5. Insert ke Form_Purchasing_Pengembalian_Uang
    $sqlInsert = "
        INSERT INTO dbo.Form_Purchasing_Pengembalian_Uang (
            ticket, rfp_no, tgl_rfp, tanggal, ref_po_no,
            supplier, rekening_ac, bank, currency, payment_method,
            keterangan, subtotal, terbilang, dibuat_oleh, dicek_oleh,
            nama_pemohon, departemen, status, lampiran_path, ttd_dibuat_oleh,
            items_json, created_by, created_at
        ) 
        OUTPUT INSERTED.id
        VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, 'Pending', ?, ?,
            ?, ?, GETDATE()
        )
    ";

    $params = [
        $ticket, $rfp_no, $tgl_rfp, $tanggal, $ref_po_no,
        $supplier, $rekening_ac, $bank, $currency, $payment_method,
        $keterangan, $subtotal, $terbilang, $dibuat_oleh, $dicek_oleh,
        $nama_pemohon, $departemen, $lampiranPath, $ttd_dibuat_oleh,
        $items_json, $created_by
    ];

    $stmt = sqlsrv_query($conn, $sqlInsert, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errMsg = 'Gagal menyimpan ke database';
        if (!empty($errors)) {
            $errMsg .= ': ' . $errors[0]['message'];
        }
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit;
    }

    $bpuId = null;
    if ($rowId = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $bpuId = $rowId['id'] ?? null;
    }
    sqlsrv_free_stmt($stmt);

    // Fallback ID jika OUTPUT INSERTED.id tidak tertangkap
    if (!$bpuId) {
        $stmtChk = sqlsrv_query($conn, "SELECT id FROM dbo.Form_Purchasing_Pengembalian_Uang WHERE ticket = ?", [$ticket]);
        if ($stmtChk && ($rChk = sqlsrv_fetch_array($stmtChk, SQLSRV_FETCH_ASSOC))) {
            $bpuId = $rChk['id'];
        }
        if ($stmtChk) {
            sqlsrv_free_stmt($stmtChk);
        }
    }

    // 6. Simpan baris detail ke Form_Purchasing_Pengembalian_Uang_Detail
    if (!empty($items_json)) {
        $parsedItems = json_decode($items_json, true);
        if (is_array($parsedItems)) {
            $rowNo = 1;
            foreach ($parsedItems as $item) {
                $sqlDet = "INSERT INTO dbo.Form_Purchasing_Pengembalian_Uang_Detail (ticket, row_no, keterangan, cd, sifat, currency, dpp, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
                $ket = trim($item['keterangan'] ?? '');
                $cd = trim($item['cd'] ?? 'D');
                $sifat = trim($item['sifat'] ?? '+');
                $curr = trim($item['curr'] ?? 'IDR');
                $dpp = floatval($item['dpp'] ?? 0);
                sqlsrv_query($conn, $sqlDet, [$ticket, $rowNo, $ket, $cd, $sifat, $curr, $dpp]);
                $rowNo++;
            }
        }
    }

    // 7. Integrasi otomatis ke Modul Issues Purchasing (Kategori 'Membuat Form' / Subkategori 'BPU', Status 'Done')
    $issueId = null;
    if (!empty($bpuId)) {
        $formDataForIssue = [
            'ticket' => $ticket,
            'ref_po_no' => $ref_po_no,
            'rfp_no' => $rfp_no,
            'supplier' => $supplier,
            'payment_method' => $payment_method,
            'subtotal' => $subtotal,
            'currency' => $currency,
            'keterangan' => $keterangan,
            'tanggal' => $tanggal
        ];
        $issueId = formPurchasingCreateIssue($conn, 'bukti_pengembalian_uang', $bpuId, $formDataForIssue, $created_by);
    }

    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'issue_id' => $issueId,
        'message' => 'Bukti Pengembalian Uang berhasil disimpan!'
    ]);
    exit;

} else {
    // ══════════════════════════════════════════════════════════════════════════
    // FORM PENGAJUAN CASH ON DELIVERY (COD)
    // ══════════════════════════════════════════════════════════════════════════

    // Generate Ticket: COD-YYMMDD-XXX
    $prefix = 'COD-' . date('ymd') . '-';
    $sqlSeq = "SELECT TOP 1 ticket FROM dbo.Form_Purchasing_COD WHERE ticket LIKE ? ORDER BY ticket DESC";
    $stmtSeq = sqlsrv_query($conn, $sqlSeq, [$prefix . '%']);
    $nextSeq = 1;
    if ($stmtSeq && $row = sqlsrv_fetch_array($stmtSeq, SQLSRV_FETCH_ASSOC)) {
        $lastTicket = $row['ticket'];
        $parts = explode('-', $lastTicket);
        if (isset($parts[2])) {
            $nextSeq = intval($parts[2]) + 1;
        }
    }
    $ticket = $prefix . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);

    // Capture input fields
    $no_receipt = trim($_POST['no_receipt'] ?? '');
    $tgl_receipt = !empty($_POST['tgl_receipt']) ? $_POST['tgl_receipt'] : null;
    $cash_bank_account = trim($_POST['cash_bank_account'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $dibayar_kepada = trim($_POST['dibayar_kepada'] ?? '');
    $rekening_ac = trim($_POST['rekening_ac'] ?? '');
    $bank = trim($_POST['bank'] ?? '');
    $currency = trim($_POST['currency'] ?? 'IDR');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $ref_po_no = trim($_POST['ref_po_no'] ?? '');
    $tgl_pengajuan = !empty($_POST['tgl_pengajuan']) ? $_POST['tgl_pengajuan'] : date('Y-m-d');
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $items_json = $_POST['items_json'] ?? '[]';
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $terbilang = ($subtotal > 0) ? getTerbilangLengkapPHP($subtotal, $currency) : trim($_POST['terbilang'] ?? '');
    $dibuat_oleh = trim($_POST['dibuat_oleh'] ?? '');
    $dicek_oleh = trim($_POST['dicek_oleh'] ?? '');
    $nama_pemohon = trim($_POST['nama_pemohon'] ?? ($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']));
    $departemen = trim($_POST['departemen'] ?? '');
    $created_by = $_SESSION['UserName'] ?? 'system';
    $ttd_dibuat_oleh = !empty($_POST['ttd_dibuat_oleh']) ? trim($_POST['ttd_dibuat_oleh']) : null;

    // Pastikan kolom tanda tangan ada di tabel jika tabel sudah terbuat sebelumnya
    $colCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Purchasing_COD' AND COLUMN_NAME = 'ttd_dibuat_oleh'");
    if ($colCheck && !sqlsrv_fetch_array($colCheck)) {
        sqlsrv_query($conn, "ALTER TABLE dbo.Form_Purchasing_COD ADD ttd_dibuat_oleh NVARCHAR(MAX) NULL, ttd_dicek_oleh NVARCHAR(MAX) NULL, tgl_dicek DATETIME NULL");
    }

    // Handle upload lampiran jika ada
    $lampiranPath = null;
    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($ext, $allowed)) {
            $newFilename = 'COD_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $newFilename;
            if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $destPath)) {
                $lampiranPath = 'uploads/' . $newFilename;
            }
        }
    }

    $sql = "INSERT INTO dbo.Form_Purchasing_COD (
                ticket, no_receipt, tgl_receipt, cash_bank_account, supplier, 
                dibayar_kepada, rekening_ac, bank, currency, keterangan, 
                ref_po_no, tgl_pengajuan, due_date, items_json, subtotal, 
                terbilang, dibuat_oleh, dicek_oleh, nama_pemohon, departemen, 
                status, lampiran_path, ttd_dibuat_oleh, created_by, created_at
            ) 
            OUTPUT INSERTED.id
            VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                'Pending', ?, ?, ?, GETDATE()
            )";

    $params = [
        $ticket, $no_receipt, $tgl_receipt, $cash_bank_account, $supplier,
        $dibayar_kepada, $rekening_ac, $bank, $currency, $keterangan,
        $ref_po_no, $tgl_pengajuan, $due_date, $items_json, $subtotal,
        $terbilang, $dibuat_oleh, $dicek_oleh, $nama_pemohon, $departemen,
        $lampiranPath, $ttd_dibuat_oleh, $created_by
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errMsg = 'Gagal menyimpan ke database';
        if (!empty($errors)) {
            $errMsg .= ': ' . $errors[0]['message'];
        }
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit;
    }

    $codId = null;
    if ($rowId = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $codId = $rowId['id'] ?? null;
    }
    sqlsrv_free_stmt($stmt);

    // Fallback ID jika OUTPUT INSERTED.id tidak tertangkap
    if (!$codId) {
        $stmtChk = sqlsrv_query($conn, "SELECT id FROM dbo.Form_Purchasing_COD WHERE ticket = ?", [$ticket]);
        if ($stmtChk && ($rChk = sqlsrv_fetch_array($stmtChk, SQLSRV_FETCH_ASSOC))) {
            $codId = $rChk['id'];
        }
        if ($stmtChk) {
            sqlsrv_free_stmt($stmtChk);
        }
    }

    // Simpan juga baris rincian ke Form_Purchasing_COD_Detail jika tabel tersedia
    if (!empty($items_json)) {
        $detailCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_COD_Detail'");
        if ($detailCheck && sqlsrv_fetch_array($detailCheck)) {
            $parsedItems = json_decode($items_json, true);
            if (is_array($parsedItems)) {
                $rowNo = 1;
                foreach ($parsedItems as $item) {
                    $sqlDet = "INSERT INTO dbo.Form_Purchasing_COD_Detail (ticket, row_no, keterangan, cd, sifat, currency, dpp, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
                    $ket = trim($item['keterangan'] ?? '');
                    $cd = trim($item['cd'] ?? 'D');
                    $sifat = trim($item['sifat'] ?? '+');
                    $curr = trim($item['curr'] ?? 'IDR');
                    $dpp = floatval($item['dpp'] ?? 0);
                    sqlsrv_query($conn, $sqlDet, [$ticket, $rowNo, $ket, $cd, $sifat, $curr, $dpp]);
                    $rowNo++;
                }
            }
        }
    }

    // Integrasi otomatis ke Modul Issues Purchasing (Kategori 'Membuat Form' / Subkategori 'Form COD', Status 'Done')
    $issueId = null;
    if (!empty($codId)) {
        $formDataForIssue = [
            'ticket' => $ticket,
            'ref_po_no' => $ref_po_no,
            'no_receipt' => $no_receipt,
            'supplier' => $supplier,
            'dibayar_kepada' => $dibayar_kepada,
            'subtotal' => $subtotal,
            'currency' => $currency,
            'keterangan' => $keterangan,
            'due_date' => $due_date,
            'tanggal' => $tgl_pengajuan
        ];
        $issueId = formPurchasingCreateIssue($conn, 'cash_on_delivery', $codId, $formDataForIssue, $created_by);
    }

    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'issue_id' => $issueId,
        'message' => 'Pengajuan Cash On Delivery berhasil disimpan!'
    ]);
    exit;
}
