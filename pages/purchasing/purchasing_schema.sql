-- ==============================================================================
-- DATABASE SCHEMA QUERY UNTUK MODUL PURCHASING (SERAH TERIMA BERKAS / DOKUMEN)
-- Target Database: Microsoft SQL Server (Database: [GG])
-- Keterangan: File ini HANYA berisi query DDL untuk dijalankan manual di SSMS.
--             JANGAN dijalankan otomatis oleh sistem sebelum direview.
-- ==============================================================================

USE [GG];
GO

-- ------------------------------------------------------------------------------
-- 1. TABEL UTAMA: dbo.purchasing_header
-- Menyimpan induk data pengajuan serah terima berkas pembayaran / purchasing
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[purchasing_header]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[purchasing_header] (
        [id] BIGINT IDENTITY(1,1) NOT NULL,
        [tanggal] DATE NOT NULL CONSTRAINT [DF_purchasing_header_tanggal] DEFAULT (CAST(GETDATE() AS DATE)),
        [type] VARCHAR(50) NOT NULL,                    -- COD, PO, RFP, OFFSET, Kontrabon, Lainnya
        [type_keterangan] VARCHAR(100) NULL,            -- Keterangan tambahan (cth: 'Untuk dibuat Cek' atau no RFP '26.071.996')
        [is_multi_po] BIT NOT NULL CONSTRAINT [DF_purchasing_header_is_multi_po] DEFAULT (0),
        [no_po] VARCHAR(255) NULL,                      -- Nomor PO utama / gabungan
        [vendor] NVARCHAR(255) NULL,                    -- Nama vendor / supplier
        [no_grn] VARCHAR(500) NULL CONSTRAINT [DF_purchasing_header_no_grn] DEFAULT ('-'),
        [pengirim] NVARCHAR(200) NOT NULL,              -- Format: Nama Pembuat (Departemen)
        [penerima] NVARCHAR(200) NOT NULL,              -- Format: Nama Penerima (Departemen)
        [status_ttd] NVARCHAR(50) NOT NULL CONSTRAINT [DF_purchasing_header_status_ttd] DEFAULT ('Menunggu TTD'),
        [signed_by] NVARCHAR(200) NULL,                 -- Nama penerima yang menandatangani
        [signed_at] DATETIME NULL,                      -- Waktu penandatanganan
        [signature_data] NVARCHAR(MAX) NULL,            -- Data base64 gambar tanda tangan (opsional)
        [created_by] VARCHAR(100) NULL,                 -- Username pembuat
        [created_at] DATETIME NOT NULL CONSTRAINT [DF_purchasing_header_created_at] DEFAULT (GETDATE()),
        [updated_by] VARCHAR(100) NULL,
        [updated_at] DATETIME NULL,
        [is_deleted] BIT NOT NULL CONSTRAINT [DF_purchasing_header_is_deleted] DEFAULT (0),
        CONSTRAINT [PK_purchasing_header] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    PRINT 'Tabel dbo.purchasing_header berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.purchasing_header sudah ada.';
    -- Pastikan kolom signature_data ada jika tabel sudah terlanjur dibuat sebelumnya
    IF COL_LENGTH('dbo.purchasing_header', 'signature_data') IS NULL
    BEGIN
        ALTER TABLE [dbo].[purchasing_header] ADD [signature_data] NVARCHAR(MAX) NULL;
        PRINT 'Kolom [signature_data] berhasil ditambahkan ke dbo.purchasing_header.';
    END
END
GO

-- ------------------------------------------------------------------------------
-- 2. TABEL RINCIAN URAIAN BERKAS: dbo.purchasing_detail
-- Menyimpan baris-baris uraian berkas (description lines) per dokumen
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[purchasing_detail]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[purchasing_detail] (
        [id] BIGINT IDENTITY(1,1) NOT NULL,
        [header_id] BIGINT NOT NULL,
        [row_order] INT NOT NULL CONSTRAINT [DF_purchasing_detail_row_order] DEFAULT (1),
        [description] NVARCHAR(MAX) NOT NULL,
        [created_at] DATETIME NOT NULL CONSTRAINT [DF_purchasing_detail_created_at] DEFAULT (GETDATE()),
        CONSTRAINT [PK_purchasing_detail] PRIMARY KEY CLUSTERED ([id] ASC),
        CONSTRAINT [FK_purchasing_detail_header] FOREIGN KEY ([header_id]) 
            REFERENCES [dbo].[purchasing_header] ([id]) 
            ON DELETE CASCADE
    );
    PRINT 'Tabel dbo.purchasing_detail berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.purchasing_detail sudah ada.';
END
GO

-- ------------------------------------------------------------------------------
-- 3. TABEL RINCIAN MULTI-PO: dbo.purchasing_po_items
-- Khusus untuk tipe "PO - Untuk dibuat Cek" yang berisi beberapa baris PO rincian
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[purchasing_po_items]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[purchasing_po_items] (
        [id] BIGINT IDENTITY(1,1) NOT NULL,
        [header_id] BIGINT NOT NULL,
        [item_no] INT NOT NULL CONSTRAINT [DF_purchasing_po_items_item_no] DEFAULT (1),
        [no_po] VARCHAR(100) NOT NULL,
        [description] NVARCHAR(MAX) NULL,
        [vendor] NVARCHAR(255) NULL,
        [no_grn] VARCHAR(500) NULL CONSTRAINT [DF_purchasing_po_items_no_grn] DEFAULT ('-'),
        [created_at] DATETIME NOT NULL CONSTRAINT [DF_purchasing_po_items_created_at] DEFAULT (GETDATE()),
        CONSTRAINT [PK_purchasing_po_items] PRIMARY KEY CLUSTERED ([id] ASC),
        CONSTRAINT [FK_purchasing_po_items_header] FOREIGN KEY ([header_id]) 
            REFERENCES [dbo].[purchasing_header] ([id]) 
            ON DELETE CASCADE
    );
    PRINT 'Tabel dbo.purchasing_po_items berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.purchasing_po_items sudah ada.';
END
GO

-- ------------------------------------------------------------------------------
-- 4. INDEKS PERFORMA QUERY
-- Mempercepat polling notifikasi ke Penerima, filter tanggal, dan listing data
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_purchasing_header_penerima_status' AND object_id = OBJECT_ID(N'[dbo].[purchasing_header]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_purchasing_header_penerima_status]
    ON [dbo].[purchasing_header] ([penerima], [status_ttd], [is_deleted])
    INCLUDE ([id], [tanggal], [type], [pengirim], [vendor], [no_po], [created_at]);
    PRINT 'Index IX_purchasing_header_penerima_status berhasil dibuat.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_purchasing_header_tanggal' AND object_id = OBJECT_ID(N'[dbo].[purchasing_header]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_purchasing_header_tanggal]
    ON [dbo].[purchasing_header] ([is_deleted], [tanggal] DESC, [id] DESC);
    PRINT 'Index IX_purchasing_header_tanggal berhasil dibuat.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_purchasing_detail_header' AND object_id = OBJECT_ID(N'[dbo].[purchasing_detail]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_purchasing_detail_header]
    ON [dbo].[purchasing_detail] ([header_id], [row_order]);
    PRINT 'Index IX_purchasing_detail_header berhasil dibuat.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_purchasing_po_items_header' AND object_id = OBJECT_ID(N'[dbo].[purchasing_po_items]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_purchasing_po_items_header]
    ON [dbo].[purchasing_po_items] ([header_id], [item_no]);
    PRINT 'Index IX_purchasing_po_items_header berhasil dibuat.';
END
GO

-- ==============================================================================
-- 5. OPSIONAL: SEED DATA CONTOH AWAL (SESUAI CONTOH SPREADSHEET USER)
-- Jika ingin memasukkan data awal contoh, hilangkan komentar blok di bawah ini:
-- ==============================================================================
/*
BEGIN TRANSACTION;

-- 1. COD
INSERT INTO [dbo].[purchasing_header] ([tanggal], [type], [type_keterangan], [is_multi_po], [no_po], [vendor], [no_grn], [pengirim], [penerima], [status_ttd])
VALUES ('2026-09-21', 'COD', '', 0, 'POLC/2607/0389', 'Pridhana Eka', 'GRNLC/2607/0755', 'Fikri Salman Ramadhan (Information Technology)', 'Wulan Wulandari (Accounting)', 'Sudah Ditandatangani');
DECLARE @h1 BIGINT = SCOPE_IDENTITY();
INSERT INTO [dbo].[purchasing_detail] ([header_id], [row_order], [description]) VALUES
(@h1, 1, 'Pipot Volumettnc Rp.94.350'),
(@h1, 2, 'PO Asli + SJ : 194/SJ/PG/07/2026'),
(@h1, 3, 'Invoice + FP : 0400.2600.2809.81304 20/09'),
(@h1, 4, 'Copy DO');

-- 2. RFP
INSERT INTO [dbo].[purchasing_header] ([tanggal], [type], [type_keterangan], [is_multi_po], [no_po], [vendor], [no_grn], [pengirim], [penerima], [status_ttd])
VALUES ('2026-09-21', 'RFP', '26.071.996', 0, 'POLD/2607/0081', 'Ady Water', '-', 'Fikri Salman Ramadhan (Information Technology)', 'Wulan Wulandari (Accounting)', 'Sudah Ditandatangani');
DECLARE @h2 BIGINT = SCOPE_IDENTITY();
INSERT INTO [dbo].[purchasing_detail] ([header_id], [row_order], [description]) VALUES
(@h2, 1, 'DP 50% Pembelian Carbon, Pasir dll Rp.69.791.255'),
(@h2, 2, 'PO Asli + Performa Invoice');

-- 3. OFFSET
INSERT INTO [dbo].[purchasing_header] ([tanggal], [type], [type_keterangan], [is_multi_po], [no_po], [vendor], [no_grn], [pengirim], [penerima], [status_ttd])
VALUES ('2026-09-21', 'OFFSET', '', 0, 'POSD/2607/0004', 'BBT', 'GRNSD/2607/0006', 'Fikri Salman Ramadhan (Information Technology)', 'Wulan Wulandari (Accounting)', 'Sudah Ditandatangani');
DECLARE @h3 BIGINT = SCOPE_IDENTITY();
INSERT INTO [dbo].[purchasing_detail] ([header_id], [row_order], [description]) VALUES
(@h3, 1, 'PO + Inv + Laporan Hasil Uji'),
(@h3, 2, 'No : 1123/EX/VII/2026');

-- 4. Kontrabon
INSERT INTO [dbo].[purchasing_header] ([tanggal], [type], [type_keterangan], [is_multi_po], [no_po], [vendor], [no_grn], [pengirim], [penerima], [status_ttd])
VALUES ('2026-09-21', 'Kontrabon', '', 0, 'POLC/2607/0471', 'Meta Rupa Perkasa', 'GRNLC/2607/0951', 'Fikri Salman Ramadhan (Information Technology)', 'Wulan Wulandari (Accounting)', 'Sudah Ditandatangani');
DECLARE @h4 BIGINT = SCOPE_IDENTITY();
INSERT INTO [dbo].[purchasing_detail] ([header_id], [row_order], [description]) VALUES
(@h4, 1, 'SJ + SJ Dokumen Lampiran + Kwitansi'),
(@h4, 2, 'Inv + FP + PO Asli');

-- 5. PO Untuk dibuat Cek (Multi-PO)
INSERT INTO [dbo].[purchasing_header] ([tanggal], [type], [type_keterangan], [is_multi_po], [no_po], [vendor], [no_grn], [pengirim], [penerima], [status_ttd])
VALUES ('2026-09-21', 'PO', 'Untuk dibuat Cek', 1, 'POLC/2606/0671 - 0675', 'Intan Jaya Holis', '-', 'Fikri Salman Ramadhan (Information Technology)', 'Wulan Wulandari (Accounting)', 'Menunggu TTD');
DECLARE @h5 BIGINT = SCOPE_IDENTITY();
INSERT INTO [dbo].[purchasing_po_items] ([header_id], [item_no], [no_po], [description], [vendor], [no_grn]) VALUES
(@h5, 1, 'POLC/2606/0671', 'Rp.25.000', 'Intan Jaya Holis', '-'),
(@h5, 2, 'POLC/2606/0672', 'Rp.25.001', 'Intan Jaya Holis', '-'),
(@h5, 3, 'POLC/2606/0673', 'Rp.25.002', 'Intan Jaya Holis', '-'),
(@h5, 4, 'POLC/2606/0674', 'Rp.25.003', 'Intan Jaya Holis', '-'),
(@h5, 5, 'POLC/2606/0675', 'Rp.25.004', 'Intan Jaya Holis', '-');

INSERT INTO [dbo].[purchasing_detail] ([header_id], [row_order], [description]) VALUES
(@h5, 1, 'Rp.25.000'),
(@h5, 2, 'Rp.25.001'),
(@h5, 3, 'Rp.25.002'),
(@h5, 4, 'Rp.25.003'),
(@h5, 5, 'Rp.25.004');

COMMIT TRANSACTION;
PRINT 'Data seed purchasing berhasil di-insert.';
*/
GO
