-- ==============================================================================
-- DATABASE SCHEMA: FORM PURCHASING - BUKTI PENGEMBALIAN UANG
-- Sesuai format dokumen: SUM Support App.docx
-- Target Database: Microsoft SQL Server (Database: [GG])
-- ==============================================================================

USE [GG];
GO

-- 1. TABEL UTAMA (HEADER): dbo.Form_Purchasing_Pengembalian_Uang
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Form_Purchasing_Pengembalian_Uang]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[Form_Purchasing_Pengembalian_Uang] (
        [id] BIGINT IDENTITY(1,1) NOT NULL,
        
        -- Nomor Tiket Unik (Format: BPU-YYMMDD-XXX)
        [ticket] VARCHAR(50) NOT NULL,
        
        -- Data RFP dari ERP Crystal (cmtttap / cmrequesthd)
        [rfp_no] VARCHAR(100) NULL,                         -- Contoh: 2610.2005
        [tgl_rfp] DATE NULL,                                -- Contoh: 2026-11-03
        
        -- Tanggal Pengajuan
        [tanggal] DATE NOT NULL CONSTRAINT [DF_Form_BPU_tanggal] DEFAULT (CAST(GETDATE() AS DATE)),
        
        -- Referensi PO No
        [ref_po_no] VARCHAR(100) NULL,                      -- Contoh: POLD/2610/0069
        
        -- Data Supplier & Rekening
        [supplier] NVARCHAR(255) NULL,                      -- Contoh: TEKINDO PRATAMA
        [rekening_ac] VARCHAR(100) NULL,                    -- Nomor Rekening
        [bank] NVARCHAR(100) NULL,                          -- Bank (BCA, dll)
        [currency] VARCHAR(10) NOT NULL CONSTRAINT [DF_Form_BPU_currency] DEFAULT ('IDR'),
        [payment_method] VARCHAR(100) NULL,                 -- Payment Before Shipment / AP / DP
        
        -- Keterangan (Isi Manual)
        [keterangan] NVARCHAR(MAX) NULL,
        
        -- Total & Terbilang
        [subtotal] DECIMAL(18, 2) NOT NULL CONSTRAINT [DF_Form_BPU_subtotal] DEFAULT (0.00),
        [terbilang] NVARCHAR(1000) NULL,
        
        -- Otorisasi & Personil
        [dibuat_oleh] NVARCHAR(100) NULL,
        [dicek_oleh] NVARCHAR(100) NULL,
        [nama_pemohon] NVARCHAR(100) NULL,
        [departemen] NVARCHAR(100) NULL,
        
        -- Status Workflow
        [status] VARCHAR(50) NOT NULL CONSTRAINT [DF_Form_BPU_status] DEFAULT ('Pending'),
        [alasan_reject] NVARCHAR(1000) NULL,
        
        -- Lampiran Gambar / Dokumen (disimpan di uploads/form_purchasing/)
        [lampiran_path] NVARCHAR(500) NULL,
        
        -- Tanda Tangan Digital (Base64 PNG)
        [ttd_dibuat_oleh] NVARCHAR(MAX) NULL,
        [ttd_dicek_oleh] NVARCHAR(MAX) NULL,
        [tgl_dicek] DATETIME NULL,
        
        -- Payload JSON Baris Rincian Item
        [items_json] NVARCHAR(MAX) NULL,
        
        -- Audit Trail & Log
        [created_by] VARCHAR(100) NULL,
        [created_at] DATETIME NOT NULL CONSTRAINT [DF_Form_BPU_created_at] DEFAULT (GETDATE()),
        [updated_by] VARCHAR(100) NULL,
        [updated_at] DATETIME NULL,
        [is_deleted] BIT NOT NULL CONSTRAINT [DF_Form_BPU_is_deleted] DEFAULT (0),
        
        CONSTRAINT [PK_Form_Purchasing_Pengembalian_Uang] PRIMARY KEY CLUSTERED ([id] ASC),
        CONSTRAINT [UQ_Form_Purchasing_Pengembalian_Uang_ticket] UNIQUE NONCLUSTERED ([ticket] ASC)
    );
    PRINT 'Tabel dbo.Form_Purchasing_Pengembalian_Uang berhasil dibuat.';
END
GO

-- 2. TABEL RINCIAN (DETAIL): dbo.Form_Purchasing_Pengembalian_Uang_Detail
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Form_Purchasing_Pengembalian_Uang_Detail]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[Form_Purchasing_Pengembalian_Uang_Detail] (
        [id] BIGINT IDENTITY(1,1) NOT NULL,
        [ticket] VARCHAR(50) NOT NULL,
        [row_no] INT NOT NULL CONSTRAINT [DF_Form_BPU_Detail_row_no] DEFAULT (1),
        [keterangan] NVARCHAR(500) NOT NULL,
        [cd] VARCHAR(5) NOT NULL CONSTRAINT [DF_Form_BPU_Detail_cd] DEFAULT ('D'),
        [sifat] VARCHAR(5) NOT NULL CONSTRAINT [DF_Form_BPU_Detail_sifat] DEFAULT ('+'),
        [currency] VARCHAR(10) NOT NULL CONSTRAINT [DF_Form_BPU_Detail_currency] DEFAULT ('IDR'),
        [dpp] DECIMAL(18, 2) NOT NULL CONSTRAINT [DF_Form_BPU_Detail_dpp] DEFAULT (0.00), -- Harga Satuan
        [created_at] DATETIME NOT NULL CONSTRAINT [DF_Form_BPU_Detail_created_at] DEFAULT (GETDATE()),
        
        CONSTRAINT [PK_Form_Purchasing_Pengembalian_Uang_Detail] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    PRINT 'Tabel dbo.Form_Purchasing_Pengembalian_Uang_Detail berhasil dibuat.';
END
GO

-- 3. INDEXES
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Form_BPU_ref_po_no' AND object_id = OBJECT_ID('dbo.Form_Purchasing_Pengembalian_Uang'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_Form_BPU_ref_po_no] 
    ON [dbo].[Form_Purchasing_Pengembalian_Uang] ([ref_po_no] ASC);
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Form_BPU_tanggal' AND object_id = OBJECT_ID('dbo.Form_Purchasing_Pengembalian_Uang'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_Form_BPU_tanggal] 
    ON [dbo].[Form_Purchasing_Pengembalian_Uang] ([tanggal] DESC);
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Form_BPU_Detail_ticket' AND object_id = OBJECT_ID('dbo.Form_Purchasing_Pengembalian_Uang_Detail'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_Form_BPU_Detail_ticket] 
    ON [dbo].[Form_Purchasing_Pengembalian_Uang_Detail] ([ticket] ASC, [row_no] ASC);
END
GO
