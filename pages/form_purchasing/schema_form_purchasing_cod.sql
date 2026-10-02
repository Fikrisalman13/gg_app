-- ==============================================================================
-- DATABASE SCHEMA QUERY: MODUL FORM PURCHASING (PENGAJUAN CASH ON DELIVERY)
-- Standar Dokumen: SUM-FM-PB-004
-- Target Database: Microsoft SQL Server (Database: [GG])
-- Keterangan    : File ini berisi skema tabel Header dan Detail untuk modul
--                 Pengajuan Cash On Delivery (COD).
-- 
-- PERHATIAN:
-- JANGAN DIJALANKAN OTOMATIS OLEH SISTEM.
-- Jalankan query ini secara manual di SQL Server Management Studio (SSMS),
-- HeidiSQL, DBeaver, atau tool database pilihan Anda.
-- ==============================================================================

USE [GG];
GO

-- ==============================================================================
-- 1. TABEL UTAMA (HEADER): dbo.Form_Purchasing_COD
-- Menyimpan data induk formulir Pengajuan Cash On Delivery (SUM-FM-PB-004)
-- ==============================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Form_Purchasing_COD]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[Form_Purchasing_COD] (
        [id] BIGINT IDENTITY(1,1) NOT NULL,
        
        -- Nomor Tiket Unik (Format: COD-YYMMDD-XXX)
        [ticket] VARCHAR(50) NOT NULL,
        
        -- Data Receipt / GRN dari ERP Crystal (prgrndt / prgrnhd)
        [no_receipt] VARCHAR(100) NULL,                     -- Contoh: GRNSP/2609/0155
        [tgl_receipt] DATE NULL,                            -- Contoh: 2026-09-19
        
        -- Data Akun & Supplier
        [cash_bank_account] VARCHAR(100) NULL,              -- No Rekening Bank / Cash Account
        [supplier] NVARCHAR(255) NULL,                      -- Nama Supplier / Vendor dari ERP
        [dibayar_kepada] NVARCHAR(255) NULL,                -- Nama penerima pembayaran
        [rekening_ac] VARCHAR(100) NULL,                    -- Nomor Rekening Tujuan Transfer
        [bank] NVARCHAR(100) NULL,                          -- Nama Bank Penerima (BCA, Mandiri, dll)
        
        -- Keterangan & Mata Uang
        [currency] VARCHAR(10) NOT NULL CONSTRAINT [DF_Form_COD_currency] DEFAULT ('IDR'),
        [keterangan] NVARCHAR(MAX) NULL,                     -- Keterangan pengajuan / Deskripsi PO
        [ref_po_no] VARCHAR(100) NULL,                      -- Referensi PO No. (Contoh: POSP/2609/0087)
        
        -- Tanggal Pengajuan & Jatuh Tempo
        [tgl_pengajuan] DATE NOT NULL CONSTRAINT [DF_Form_COD_tgl_pengajuan] DEFAULT (CAST(GETDATE() AS DATE)),
        [due_date] DATE NULL,                               -- Tanggal jatuh tempo / pembayaran
        
        -- Nilai Total & Terbilang
        [subtotal] DECIMAL(18, 2) NOT NULL CONSTRAINT [DF_Form_COD_subtotal] DEFAULT (0.00),
        [terbilang] NVARCHAR(1000) NULL,                    -- Contoh: Tujuh Ratus Delapan Puluh Lima Ribu...
        
        -- Otorisasi & Personil
        [dibuat_oleh] NVARCHAR(100) NULL,                   -- Nama pembuat formulir
        [dicek_oleh] NVARCHAR(100) NULL,                    -- Nama pemeriksa (Spv / Purchasing)
        [nama_pemohon] NVARCHAR(100) NULL,                  -- Nama pemohon pengajuan
        [departemen] NVARCHAR(100) NULL,                    -- Departemen pemohon (Purchasing, dll)
        
        -- Status Workflow
        -- Pending: Menunggu persetujuan
        -- Approved: Telah disetujui
        -- Ditolak: Ditolak dengan alasan
        -- Selesai: Telah diproses pembayaran
        [status] VARCHAR(50) NOT NULL CONSTRAINT [DF_Form_COD_status] DEFAULT ('Pending'),
        [alasan_reject] NVARCHAR(1000) NULL,                -- Alasan jika pengajuan ditolak
        
        -- Lampiran Pendukung
        [lampiran_path] NVARCHAR(500) NULL,                 -- Path file dokumen lampiran (PDF / Gambar)
        
        -- Tanda Tangan Digital (Base64 PNG Image)
        [ttd_dibuat_oleh] NVARCHAR(MAX) NULL,               -- Tanda tangan digital pembuat formulir
        [ttd_dicek_oleh] NVARCHAR(MAX) NULL,                -- Tanda tangan digital pemeriksa (KABAG)
        [tgl_dicek] DATETIME NULL,                          -- Waktu penandatanganan oleh pemeriksa
        
        -- Payload JSON Baris Rincian (untuk reload form secara cepat)
        [items_json] NVARCHAR(MAX) NULL,
        
        -- Audit Trail & Log
        [created_by] VARCHAR(100) NULL,
        [created_at] DATETIME NOT NULL CONSTRAINT [DF_Form_COD_created_at] DEFAULT (GETDATE()),
        [updated_by] VARCHAR(100) NULL,
        [updated_at] DATETIME NULL,
        [is_deleted] BIT NOT NULL CONSTRAINT [DF_Form_COD_is_deleted] DEFAULT (0),
        
        -- Primary Key & Unique Constraints
        CONSTRAINT [PK_Form_Purchasing_COD] PRIMARY KEY CLUSTERED ([id] ASC),
        CONSTRAINT [UQ_Form_Purchasing_COD_ticket] UNIQUE NONCLUSTERED ([ticket] ASC)
    );
    PRINT 'Tabel dbo.Form_Purchasing_COD berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.Form_Purchasing_COD sudah ada. Melakukan verifikasi dan sinkronisasi kolom...';
    
    -- Verifikasi penambahan kolom jika tabel sebelumnya sudah dibuat secara parsial
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'no_receipt') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [no_receipt] VARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'tgl_receipt') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [tgl_receipt] DATE NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'cash_bank_account') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [cash_bank_account] VARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'supplier') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [supplier] NVARCHAR(255) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'dibayar_kepada') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [dibayar_kepada] NVARCHAR(255) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'rekening_ac') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [rekening_ac] VARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'bank') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [bank] NVARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'currency') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [currency] VARCHAR(10) NOT NULL CONSTRAINT [DF_Form_COD_currency_alt] DEFAULT ('IDR');
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'keterangan') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [keterangan] NVARCHAR(MAX) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'ref_po_no') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [ref_po_no] VARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'tgl_pengajuan') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [tgl_pengajuan] DATE NOT NULL CONSTRAINT [DF_Form_COD_tgl_pengajuan_alt] DEFAULT (CAST(GETDATE() AS DATE));
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'due_date') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [due_date] DATE NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'subtotal') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [subtotal] DECIMAL(18, 2) NOT NULL CONSTRAINT [DF_Form_COD_subtotal_alt] DEFAULT (0.00);
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'terbilang') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [terbilang] NVARCHAR(1000) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'dibuat_oleh') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [dibuat_oleh] NVARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'dicek_oleh') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [dicek_oleh] NVARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'nama_pemohon') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [nama_pemohon] NVARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'departemen') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [departemen] NVARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'status') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [status] VARCHAR(50) NOT NULL CONSTRAINT [DF_Form_COD_status_alt] DEFAULT ('Pending');
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'alasan_reject') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [alasan_reject] NVARCHAR(1000) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'lampiran_path') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [lampiran_path] NVARCHAR(500) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'ttd_dibuat_oleh') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [ttd_dibuat_oleh] NVARCHAR(MAX) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'ttd_dicek_oleh') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [ttd_dicek_oleh] NVARCHAR(MAX) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'tgl_dicek') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [tgl_dicek] DATETIME NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'items_json') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [items_json] NVARCHAR(MAX) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'created_by') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [created_by] VARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'created_at') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [created_at] DATETIME NOT NULL CONSTRAINT [DF_Form_COD_created_at_alt] DEFAULT (GETDATE());
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'updated_by') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [updated_by] VARCHAR(100) NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'updated_at') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [updated_at] DATETIME NULL;
        
    IF COL_LENGTH('dbo.Form_Purchasing_COD', 'is_deleted') IS NULL
        ALTER TABLE [dbo].[Form_Purchasing_COD] ADD [is_deleted] BIT NOT NULL CONSTRAINT [DF_Form_COD_is_deleted_alt] DEFAULT (0);

    PRINT 'Verifikasi kolom dbo.Form_Purchasing_COD selesai.';
END
GO

-- ==============================================================================
-- 2. TABEL RINCIAN (DETAIL): dbo.Form_Purchasing_COD_Detail
-- Menyimpan baris rincian item (DPP, PPN 11%, PPh 23 2%, dll)
-- ==============================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Form_Purchasing_COD_Detail]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[Form_Purchasing_COD_Detail] (
        [id] BIGINT IDENTITY(1,1) NOT NULL,
        
        -- Relasi ke Nomor Tiket Header
        [ticket] VARCHAR(50) NOT NULL,
        
        -- Urutan Baris (1, 2, 3, ...)
        [row_no] INT NOT NULL CONSTRAINT [DF_Form_COD_Detail_row_no] DEFAULT (1),
        
        -- Keterangan / Deskripsi Item
        [keterangan] NVARCHAR(500) NOT NULL,
        
        -- Posisi Akuntansi: C (Credit) atau D (Debit)
        [cd] VARCHAR(5) NOT NULL CONSTRAINT [DF_Form_COD_Detail_cd] DEFAULT ('D'),
        
        -- Sifat Operasi Aritmatika: '+' (Penambah) atau '-' (Pengurang)
        [sifat] VARCHAR(5) NOT NULL CONSTRAINT [DF_Form_COD_Detail_sifat] DEFAULT ('+'),
        
        -- Mata Uang Item
        [currency] VARCHAR(10) NOT NULL CONSTRAINT [DF_Form_COD_Detail_currency] DEFAULT ('IDR'),
        
        -- Nilai Nominal DPP / Nilai Baris
        [dpp] DECIMAL(18, 2) NOT NULL CONSTRAINT [DF_Form_COD_Detail_dpp] DEFAULT (0.00),
        
        -- Timestamp
        [created_at] DATETIME NOT NULL CONSTRAINT [DF_Form_COD_Detail_created_at] DEFAULT (GETDATE()),
        
        CONSTRAINT [PK_Form_Purchasing_COD_Detail] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    PRINT 'Tabel dbo.Form_Purchasing_COD_Detail berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.Form_Purchasing_COD_Detail sudah ada.';
END
GO

-- ==============================================================================
-- 3. INDEXES UNTUK OPTIMASI PERFORMA PENCARIAN & QUERY
-- ==============================================================================
-- Index pada tabel Header
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Form_Purchasing_COD_ref_po_no' AND object_id = OBJECT_ID('dbo.Form_Purchasing_COD'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_Form_Purchasing_COD_ref_po_no] 
    ON [dbo].[Form_Purchasing_COD] ([ref_po_no] ASC);
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Form_Purchasing_COD_tgl_pengajuan' AND object_id = OBJECT_ID('dbo.Form_Purchasing_COD'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_Form_Purchasing_COD_tgl_pengajuan] 
    ON [dbo].[Form_Purchasing_COD] ([tgl_pengajuan] DESC);
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Form_Purchasing_COD_status' AND object_id = OBJECT_ID('dbo.Form_Purchasing_COD'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_Form_Purchasing_COD_status] 
    ON [dbo].[Form_Purchasing_COD] ([status] ASC);
END
GO

-- Index pada tabel Detail
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Form_Purchasing_COD_Detail_ticket' AND object_id = OBJECT_ID('dbo.Form_Purchasing_COD_Detail'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_Form_Purchasing_COD_Detail_ticket] 
    ON [dbo].[Form_Purchasing_COD_Detail] ([ticket] ASC, [row_no] ASC);
END
GO

-- ==============================================================================
-- 4. VIEW REKAP / LAPORAN: dbo.vw_Form_Purchasing_COD
-- View siap pakai untuk pelaporan rekap dan monitoring pengajuan COD
-- ==============================================================================
IF OBJECT_ID('dbo.vw_Form_Purchasing_COD', 'V') IS NOT NULL
    DROP VIEW dbo.vw_Form_Purchasing_COD;
GO

CREATE VIEW [dbo].[vw_Form_Purchasing_COD] AS
SELECT 
    h.id,
    h.ticket,
    h.no_receipt,
    h.tgl_receipt,
    h.cash_bank_account,
    h.supplier,
    h.dibayar_kepada,
    h.rekening_ac,
    h.bank,
    h.currency,
    h.ref_po_no,
    h.tgl_pengajuan,
    h.due_date,
    h.subtotal,
    h.terbilang,
    h.dibuat_oleh,
    h.dicek_oleh,
    h.nama_pemohon,
    h.departemen,
    h.status,
    h.alasan_reject,
    h.lampiran_path,
    h.ttd_dibuat_oleh,
    h.ttd_dicek_oleh,
    h.tgl_dicek,
    h.created_by,
    h.created_at,
    h.updated_by,
    h.updated_at,
    (SELECT COUNT(1) FROM dbo.Form_Purchasing_COD_Detail d WHERE d.ticket = h.ticket) AS total_items
FROM [dbo].[Form_Purchasing_COD] h
WHERE h.is_deleted = 0;
GO

PRINT '==============================================================================';
PRINT 'SKEMA DATABASE FORM PURCHASING COD BERHASIL DISIAPKAN SECARA LENGKAP!';
PRINT '==============================================================================';
