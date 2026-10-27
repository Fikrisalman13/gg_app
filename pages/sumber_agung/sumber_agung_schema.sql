-- ==============================================================================
-- DATABASE SCHEMA UNTUK MODUL SUMBER AGUNG (TITIPAN DO)
-- Target Database: Microsoft SQL Server (Database: [GG])
-- Keterangan: File ini HANYA berisi query DDL untuk dijalankan manual di SSMS.
--             JANGAN dijalankan otomatis oleh sistem sebelum direview.
-- ==============================================================================

USE [GG];
GO

-- ------------------------------------------------------------------------------
-- 1. TABEL UTAMA: dbo.sa_titipan
-- Menyimpan data titipan yang dipilih dari ERP PostgreSQL
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[sa_titipan]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[sa_titipan] (
        [id]                BIGINT IDENTITY(1,1) NOT NULL,
        -- Data Tanggal Kirim dari input modal
        [tgl_kirim]         DATE NULL,
        -- Data dari ERP (snapshot)
        [no_titipan]        NVARCHAR(100) NULL,
        [tgl_titipan]       DATE NULL,
        [no_do]             NVARCHAR(100) NULL,
        [tgl_do]            DATE NULL,
        [cust_id]           NVARCHAR(50) NULL,
        [cust_name]         NVARCHAR(255) NULL,
        [no_sc]             NVARCHAR(100) NULL,
        [tgl_sc]            DATE NULL,
        [product_code]      NVARCHAR(100) NULL,
        [product_name]      NVARCHAR(255) NULL,
        [label_jual]        NVARCHAR(100) NULL,
        [cust_color]        NVARCHAR(100) NULL,
        [do_qty]            DECIMAL(18,4) NULL,
        [qty_kirim]         DECIMAL(18,4) NULL,
        [uom]               NVARCHAR(50) NULL,
        [ket_titipan_1]     NVARCHAR(500) NULL,
        [ket_titipan_2]     NVARCHAR(500) NULL,
        [dodesc]            NVARCHAR(500) NULL,
        -- Dokumen Upload
        [packinglist_file]  NVARCHAR(500) NULL,
        [packinglist_type]  VARCHAR(10) NULL,
        [packinglist_at]    DATETIME NULL,
        [packinglist_by]    NVARCHAR(200) NULL,
        [suratjalan_file]   NVARCHAR(500) NULL,
        [suratjalan_type]   VARCHAR(10) NULL,
        [suratjalan_at]     DATETIME NULL,
        [suratjalan_by]     NVARCHAR(200) NULL,
        -- Status & Publish
        [is_published]      BIT NOT NULL CONSTRAINT [DF_sa_titipan_is_published] DEFAULT (0),
        [published_at]      DATETIME NULL,
        [published_by]      NVARCHAR(200) NULL,
        [is_viewed]         BIT NOT NULL CONSTRAINT [DF_sa_titipan_is_viewed] DEFAULT (0),
        [viewed_at]         DATETIME NULL,
        [viewed_by]         NVARCHAR(200) NULL,
        -- Audit
        [created_by]        VARCHAR(100) NULL,
        [created_at]        DATETIME NOT NULL CONSTRAINT [DF_sa_titipan_created_at] DEFAULT (GETDATE()),
        [updated_by]        VARCHAR(100) NULL,
        [updated_at]        DATETIME NULL,
        [is_deleted]        BIT NOT NULL CONSTRAINT [DF_sa_titipan_is_deleted] DEFAULT (0),
        CONSTRAINT [PK_sa_titipan] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    PRINT 'Tabel dbo.sa_titipan berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel dbo.sa_titipan sudah ada.';
END
GO

-- ------------------------------------------------------------------------------
-- 2. INDEKS PERFORMA QUERY
-- ------------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_sa_titipan_published' AND object_id = OBJECT_ID(N'[dbo].[sa_titipan]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_sa_titipan_published]
    ON [dbo].[sa_titipan] ([is_published], [is_deleted])
    INCLUDE ([no_titipan], [no_sc], [no_do]);
    PRINT 'Index IX_sa_titipan_published berhasil dibuat.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = N'IX_sa_titipan_no_titipan' AND object_id = OBJECT_ID(N'[dbo].[sa_titipan]'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_sa_titipan_no_titipan]
    ON [dbo].[sa_titipan] ([no_titipan], [is_deleted]);
    PRINT 'Index IX_sa_titipan_no_titipan berhasil dibuat.';
END
GO

-- ------------------------------------------------------------------------------
-- 3. QUERY UNTUK MENAMBAH MENU DI SIDEBAR
-- (Sesuaikan MenuId agar tidak bentrok dengan yang sudah ada)
-- Jalankan setelah menentukan MenuId dan ParentMenuId yang tepat.
-- ------------------------------------------------------------------------------
-- CONTOH: (Pastikan MenuId belum dipakai)
-- INSERT INTO dbo.SMMenu (MenuId, MenuName, MenuUrl, MenuIcon, ParentMenuId)
-- VALUES (XXX, 'Sumber Agung', '/gg_app/pages/sumber_agung/sumber_agung.php', 'fas fa-truck-loading', NULL);
--
-- INSERT INTO dbo.SMGroupTrustee (GroupId, MenuId, CanView, CanAdd, CanEdit, CanDelete)
-- VALUES (1, XXX, 1, 1, 1, 1);  -- Admin
-- VALUES (YYY, XXX, 1, 1, 1, 0); -- User Group lain yang perlu akses

-- ------------------------------------------------------------------------------
-- ------------------------------------------------------------------------------
-- 4. PEMBUATAN AKUN SUMBER AGUNG MENGGUNAKAN DUMMY KARYAWAN
-- Tanpa mengubah struktur tabel dbo.SMUserMs (EmpId tetap NOT NULL)
-- ------------------------------------------------------------------------------

-- Langkah 4A: Tambah data dummy karyawan untuk Sumber Agung di dbo.m_emp (jika belum ada)
IF NOT EXISTS (SELECT 1 FROM [dbo].[m_emp] WHERE [nik] = 'SA001')
BEGIN
    INSERT INTO [dbo].[m_emp] ([nik], [nama_lengkap], [aktif], [upddate], [upduser])
    VALUES ('SA001', 'Sumber Agung (Customer)', 1, GETDATE(), 'admin');
    PRINT 'Data dummy karyawan Sumber Agung (Customer) berhasil ditambahkan ke dbo.m_emp.';
END
ELSE
BEGIN
    PRINT 'Data dummy karyawan Sumber Agung sudah ada di dbo.m_emp.';
END
GO

-- Langkah 4B: Perbaiki typo nama UserGroup dari 'Web Potal' menjadi 'Web Portal' (jika masih typo)
UPDATE [dbo].[SMUserGroup] 
SET [GroupName] = 'Web Portal', 
    [GroupDesc] = 'Akun portal untuk customer eksternal'
WHERE [GroupId] = 117 OR [GroupName] = 'Web Potal';
PRINT 'Nama Group Web Portal berhasil diperbarui.';
GO

-- CATATAN:
-- Setelah langkah 4A & 4B, Anda bisa membuat user melalui form web:
-- URL: /gg_app/pages/add_usermanager.php
-- Username   : Sumber Agung
-- Password   : (Password yang Anda tentukan, misal: 123)
-- Employee   : Pilih "Sumber Agung (Customer)"
-- User Group : Pilih "Web Portal" (atau Web Potal)
-- Klik Save. User akan otomatis tersimpan normal tanpa kendala validasi!

PRINT '=== Schema Sumber Agung selesai. ===';
GO
