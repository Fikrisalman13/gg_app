-- ==============================================================================
-- DATABASE SCHEMA QUERY UNTUK MODUL TRACKING PO (PEMANTAUAN DOKUMEN KE DIREKTUR)
-- Target Database: Microsoft SQL Server (Database: [GG])
-- Keterangan: File ini HANYA berisi query DDL untuk dijalankan manual di SSMS.
--             JANGAN dijalankan otomatis oleh sistem sebelum direview.
-- ==============================================================================

USE [GG];
GO

-- ------------------------------------------------------------------------------
-- 1. TABEL HEADER: dbo.tracking_po_header
-- Menyimpan header batch pengiriman dokumen PO ke direktur
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tracking_po_header]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tracking_po_header] (
        [id]              BIGINT IDENTITY(1,1) NOT NULL,
        [tanggal_kirim]   DATE NULL,
        [diserahkan_oleh] NVARCHAR(200) NOT NULL,
        [dikirim_ke]      NVARCHAR(100) NULL,
        [alamat_kirim]    NVARCHAR(MAX) NULL,
        [created_by]      VARCHAR(100) NULL,
        [created_at]      DATETIME NOT NULL CONSTRAINT [DF_tracking_po_header_created_at] DEFAULT (GETDATE()),
        [updated_by]      VARCHAR(100) NULL,
        [updated_at]      DATETIME NULL,
        [is_deleted]      BIT NOT NULL CONSTRAINT [DF_tracking_po_header_is_deleted] DEFAULT (0),
        CONSTRAINT [PK_tracking_po_header] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    PRINT 'Tabel dbo.tracking_po_header berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.tracking_po_header sudah ada.';
END
GO

-- ------------------------------------------------------------------------------
-- 2. TABEL DETAIL: dbo.tracking_po_detail
-- Menyimpan detail per-PO yang diserahkan dalam batch pengiriman
-- Status: Tunggu Tanggal Penyerahan, Diserahkan, Sudah Kembali, Sudah TTD, Approved
-- Note: "Belum Kembali" bukan status di DB, melainkan status otomatis di UI
--       (Diserahkan yang tanggal_kirim < hari ini dan belum dikonfirmasi kembali)
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tracking_po_detail]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tracking_po_detail] (
        [id]              BIGINT IDENTITY(1,1) NOT NULL,
        [header_id]       BIGINT NOT NULL,
        [ponmbr]          VARCHAR(100) NOT NULL,
        [pohdid]          INT NULL,
        [povendorname]    NVARCHAR(255) NULL,
        [podate]          DATE NULL,
        [tanggal_kirim]   DATE NULL,
        [dikirim_ke]      NVARCHAR(100) NULL,
        [alamat_kirim]    NVARCHAR(MAX) NULL,
        [status]          VARCHAR(50) NOT NULL CONSTRAINT [DF_tracking_po_detail_status] DEFAULT ('Diserahkan'),
        [is_signed]       BIT NOT NULL CONSTRAINT [DF_tracking_po_detail_is_signed] DEFAULT (0),
        [tanggal_kembali] DATE NULL,
        [konfirmasi_oleh] NVARCHAR(200) NULL,
        [konfirmasi_at]   DATETIME NULL,
        [approved_by]     NVARCHAR(200) NULL,
        [approved_at]     DATETIME NULL,
        [catatan]         NVARCHAR(500) NULL,
        [created_at]      DATETIME NOT NULL CONSTRAINT [DF_tracking_po_detail_created_at] DEFAULT (GETDATE()),
        CONSTRAINT [PK_tracking_po_detail] PRIMARY KEY CLUSTERED ([id] ASC),
        CONSTRAINT [FK_tracking_po_detail_header] FOREIGN KEY ([header_id])
            REFERENCES [dbo].[tracking_po_header] ([id]) ON DELETE CASCADE
    );
    PRINT 'Tabel dbo.tracking_po_detail berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.tracking_po_detail sudah ada.';
END
GO

-- ------------------------------------------------------------------------------
-- 3. TABEL LOG: dbo.tracking_po_log
-- Log aktivitas perubahan status untuk audit trail
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tracking_po_log]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tracking_po_log] (
        [id]            BIGINT IDENTITY(1,1) NOT NULL,
        [detail_id]     BIGINT NOT NULL,
        [action]        VARCHAR(50) NOT NULL,
        [old_status]    VARCHAR(50) NULL,
        [new_status]    VARCHAR(50) NOT NULL,
        [performed_by]  NVARCHAR(200) NOT NULL,
        [performed_at]  DATETIME NOT NULL CONSTRAINT [DF_tracking_po_log_performed_at] DEFAULT (GETDATE()),
        [keterangan]    NVARCHAR(500) NULL,
        CONSTRAINT [PK_tracking_po_log] PRIMARY KEY CLUSTERED ([id] ASC),
        CONSTRAINT [FK_tracking_po_log_detail] FOREIGN KEY ([detail_id])
            REFERENCES [dbo].[tracking_po_detail] ([id]) ON DELETE CASCADE
    );
    PRINT 'Tabel dbo.tracking_po_log berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.tracking_po_log sudah ada.';
END
GO

-- ------------------------------------------------------------------------------
-- 4. INDEKS PERFORMA QUERY
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_tracking_po_detail_status' AND object_id = OBJECT_ID(N'[dbo].[tracking_po_detail]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tracking_po_detail_status]
    ON [dbo].[tracking_po_detail] ([status], [header_id])
    INCLUDE ([ponmbr], [is_signed]);
    PRINT 'Index IX_tracking_po_detail_status berhasil dibuat.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_tracking_po_header_tanggal' AND object_id = OBJECT_ID(N'[dbo].[tracking_po_header]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tracking_po_header_tanggal]
    ON [dbo].[tracking_po_header] ([is_deleted], [tanggal_kirim] DESC);
    PRINT 'Index IX_tracking_po_header_tanggal berhasil dibuat.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_tracking_po_log_detail' AND object_id = OBJECT_ID(N'[dbo].[tracking_po_log]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tracking_po_log_detail]
    ON [dbo].[tracking_po_log] ([detail_id], [performed_at] DESC);
    PRINT 'Index IX_tracking_po_log_detail berhasil dibuat.';
END
GO

PRINT '=== Schema Tracking PO selesai. ===';
GO

-- ------------------------------------------------------------------------------
-- 5. QUERY MIGRASI (Jika tabel sudah ada sebelumnya di server production)
-- ------------------------------------------------------------------------------
-- ALTER TABLE [dbo].[tracking_po_header] ALTER COLUMN [tanggal_kirim] DATE NULL;
-- IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[tracking_po_detail]') AND name = 'tanggal_kirim')
-- BEGIN
--     ALTER TABLE [dbo].[tracking_po_detail] ADD [tanggal_kirim] DATE NULL;
-- END
-- IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[tracking_po_header]') AND name = 'dikirim_ke')
-- BEGIN
--     ALTER TABLE [dbo].[tracking_po_header] ADD [dikirim_ke] NVARCHAR(100) NULL, [alamat_kirim] NVARCHAR(MAX) NULL;
-- END
-- IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[tracking_po_detail]') AND name = 'dikirim_ke')
-- BEGIN
--     ALTER TABLE [dbo].[tracking_po_detail] ADD [dikirim_ke] NVARCHAR(100) NULL, [alamat_kirim] NVARCHAR(MAX) NULL;
-- END
-- GO
