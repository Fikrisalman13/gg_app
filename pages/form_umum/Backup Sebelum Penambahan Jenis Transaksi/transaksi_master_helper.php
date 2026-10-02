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
    $result = ['Procurement' => [], 'Sales' => []];

    if (!ensureTransaksiMasterTable($conn)) {
        return $defaults;
    }

    $whereActive = $onlyActive ? "WHERE is_active = 1" : "";
    $sql = "SELECT jenis, nama_transaksi
            FROM dbo.Form_Umum_Master_Transaksi_Closingan
            $whereActive
            ORDER BY CASE WHEN jenis = 'Procurement' THEN 1 WHEN jenis = 'Sales' THEN 2 ELSE 3 END, nama_transaksi";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) return $defaults;

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $jenis = trim((string)($row['jenis'] ?? ''));
        $nama = trim((string)($row['nama_transaksi'] ?? ''));
        if ($jenis === '' || $nama === '') continue;
        if (!isset($result[$jenis])) $result[$jenis] = [];
        $result[$jenis][] = $nama;
    }
    sqlsrv_free_stmt($stmt);

    $hasData = false;
    foreach ($result as $items) {
        if (!empty($items)) {
            $hasData = true;
            break;
        }
    }

    return $hasData ? $result : $defaults;
}
