<?php

function getDefaultTransaksiClosinganOptions() {
    return [
        'Procurement' => [
            'Delete Transaksi',
            'Penyesuaian TOP',
            'Receipt Backdate',
            'Revisi Harga',
            'Revisi Product',
            'Revisi ID PPN',
            'Revisi Tgl PO',
            'Revisi Tgl GRN',
            'Revisi Qty'
        ],
        'Sales' => [
            'Delete Transaksi',
            'Penyesuaian TOP',
            'Revisi ID Cust',
            'Revisi Harga',
            'Revisi Product',
            'Revisi Tgl',
            'Revisi Qty'
        ],
        'Cash Management' => [
            'Ubah COA',
            'Ubah Tanggal',
            'Ubah Deskripsi',
            'Ubah Kurs'
        ]
    ];
}

function ensureTransaksiMasterTable($conn) {
    if (!isset($conn) || $conn === false) return false;

    $sql = "
        IF OBJECT_ID('dbo.Form_Umum_Master_Transaksi_Closingan', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.Form_Umum_Master_Transaksi_Closingan (
                id INT IDENTITY(1,1) PRIMARY KEY,
                jenis NVARCHAR(50) NOT NULL,
                nama_transaksi NVARCHAR(255) NOT NULL,
                is_active BIT NOT NULL DEFAULT(1),
                created_at DATETIME NOT NULL DEFAULT(GETDATE()),
                created_by NVARCHAR(255) NULL,
                updated_at DATETIME NULL,
                updated_by NVARCHAR(255) NULL
            );
            CREATE UNIQUE INDEX UX_Form_Umum_Master_Transaksi_Closingan_Jenis_Nama
            ON dbo.Form_Umum_Master_Transaksi_Closingan (jenis, nama_transaksi);
        END
    ";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) return false;
    sqlsrv_free_stmt($stmt);
    return true;
}

function seedDefaultTransaksiMaster($conn, $createdBy = 'system') {
    if (!ensureTransaksiMasterTable($conn)) return false;

    $defaults = getDefaultTransaksiClosinganOptions();
    $sql = "IF NOT EXISTS (
                SELECT 1 FROM dbo.Form_Umum_Master_Transaksi_Closingan
                WHERE jenis = ? AND nama_transaksi = ?
            )
            INSERT INTO dbo.Form_Umum_Master_Transaksi_Closingan
                (jenis, nama_transaksi, is_active, created_at, created_by)
            VALUES
                (?, ?, 1, GETDATE(), ?)";

    foreach ($defaults as $jenis => $items) {
        foreach ($items as $item) {
            $params = [$jenis, $item, $jenis, $item, $createdBy];
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
    return true;
}

function getTransaksiClosinganOptions($conn, $onlyActive = true) {
    $defaults = getDefaultTransaksiClosinganOptions();
    $result = [
        'Procurement' => [],
        'Sales' => [],
        'Cash Management' => []
    ];

    if (!isset($conn) || $conn === false) {
        return $defaults;
    }

    $sql = "SELECT jenis, nama_transaksi, is_active
            FROM dbo.Form_Umum_Master_Transaksi_Closingan
            ORDER BY CASE 
                WHEN jenis = 'Procurement' THEN 1 
                WHEN jenis = 'Sales' THEN 2 
                WHEN jenis = 'Cash Management' THEN 3 
                ELSE 4 
            END, nama_transaksi";

    $stmt = @sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        return $defaults;
    }

    $seenJenisInDb = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $jenis = trim((string)($row['jenis'] ?? ''));
        $nama = trim((string)($row['nama_transaksi'] ?? ''));
        $isActive = !empty($row['is_active']);
        if ($jenis === '' || $nama === '') continue;

        $seenJenisInDb[$jenis] = true;
        if (!isset($result[$jenis])) {
            $result[$jenis] = [];
        }
        if (!$onlyActive || $isActive) {
            $result[$jenis][] = $nama;
        }
    }
    sqlsrv_free_stmt($stmt);

    // Fallback hanya jika suatu jenis sama sekali belum pernah terdaftar di database
    foreach ($defaults as $j => $opts) {
        if (!isset($seenJenisInDb[$j])) {
            $result[$j] = $opts;
        }
    }

    return $result;
}

function validateClosinganItem(array $item, int $index = 0): array {
    $itemNum = $index + 1;
    $bukaTgl = trim((string)($item['buka_tgl'] ?? ''));
    if ($bukaTgl === '') {
        return ['valid' => false, 'message' => "Buka Tgl pada item #{$itemNum} wajib diisi!"];
    }

    $reqGudang = !empty($item['request_gudang']);
    $reqTransaksi = !empty($item['request_transaksi']);

    if (!$reqGudang && !$reqTransaksi) {
        return ['valid' => false, 'message' => "Item #{$itemNum}: Pilih setidaknya satu permintaan (Closingan Gudang atau Closingan Transaksi)!"];
    }

    if ($reqGudang) {
        $gudang = trim((string)($item['gudang'] ?? ''));
        if ($gudang === '') {
            return ['valid' => false, 'message' => "Item #{$itemNum}: Gudang wajib dipilih jika Closingan Gudang dicentang!"];
        }
    }

    if ($reqTransaksi) {
        $jenis = trim((string)($item['jenis_transaksi'] ?? ''));
        $allowedJenis = ['Procurement', 'Sales', 'Cash Management'];
        if (!in_array($jenis, $allowedJenis, true)) {
            return ['valid' => false, 'message' => "Item #{$itemNum}: Jenis Transaksi harus Procurement, Sales, atau Cash Management!"];
        }

        $transaksi = trim((string)($item['transaksi'] ?? ''));
        if ($transaksi === '') {
            return ['valid' => false, 'message' => "Item #{$itemNum}: Transaksi wajib dipilih jika Closingan Transaksi dicentang!"];
        }

        $nomorTrans = trim((string)($item['nomor_transaksi'] ?? ''));
        if ($nomorTrans === '') {
            return ['valid' => false, 'message' => "Item #{$itemNum}: Nomor Transaksi wajib diisi!"];
        }

        // Vendor/Cust wajib untuk Procurement & Sales, opsional untuk Cash Management
        if ($jenis !== 'Cash Management') {
            $vendorCust = trim((string)($item['vendor_cust'] ?? ''));
            if ($vendorCust === '') {
                return ['valid' => false, 'message' => "Item #{$itemNum}: Vendor/Cust wajib diisi untuk jenis {$jenis}!"];
            }
        }
    }

    return ['valid' => true, 'message' => ''];
}

function validateAllClosinganItems(array $items): array {
    if (empty($items)) {
        return ['valid' => false, 'message' => 'Minimal satu item harus diisi!'];
    }
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            return ['valid' => false, 'message' => 'Format item tidak valid!'];
        }
        $res = validateClosinganItem($item, $idx);
        if (!$res['valid']) {
            return $res;
        }
    }
    return ['valid' => true, 'message' => ''];
}

