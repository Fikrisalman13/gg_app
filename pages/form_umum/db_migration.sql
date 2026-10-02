-- Database Migrations for Form Umum (Global) Module
-- Run these queries in your SQL Server database (e.g. GG database)

-- 1. Table: Form_Umum_Buka_Tanggal_Closingan
IF OBJECT_ID('dbo.Form_Umum_Buka_Tanggal_Closingan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Form_Umum_Buka_Tanggal_Closingan (
        id INT IDENTITY(1,1) PRIMARY KEY,
        ticket VARCHAR(50) NOT NULL UNIQUE,
        nama_pemohon VARCHAR(100) NOT NULL,
        jabatan VARCHAR(100) NULL,
        departemen VARCHAR(100) NULL,
        bagian VARCHAR(100) NULL,
        tgl_pengajuan DATE NULL,
        request_gudang INT DEFAULT 0,
        request_transaksi INT DEFAULT 0,
        buka_tgl DATE NULL,
        gudang_transaksi NVARCHAR(MAX) NULL,
        vendor_cust NVARCHAR(255) NULL,
        keterangan NVARCHAR(MAX) NULL,
        status_ticket VARCHAR(50) DEFAULT 'Pending',
        kategori VARCHAR(100) DEFAULT 'Buka Tanggal Closingan',
        created_at DATETIME DEFAULT GETDATE(),
        created_by VARCHAR(100) NULL,
        updated_at DATETIME DEFAULT GETDATE(),
        updated_by VARCHAR(100) NULL,
        rejected_by VARCHAR(50) NULL,
        rejection_reason NVARCHAR(MAX) NULL,
        rejection_date DATETIME NULL
    );
    PRINT 'Table Form_Umum_Buka_Tanggal_Closingan created successfully.';
END
ELSE
BEGIN
    PRINT 'Table Form_Umum_Buka_Tanggal_Closingan already exists.';
END

-- 2. Table: User_TTD_Template_Umum
IF OBJECT_ID('dbo.User_TTD_Template_Umum', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.User_TTD_Template_Umum (
        Id INT IDENTITY(1,1) PRIMARY KEY,
        UserId INT NOT NULL,
        UserName VARCHAR(100) NOT NULL,
        GroupRole VARCHAR(100) NOT NULL,
        SignaturePath VARCHAR(255) NOT NULL,
        IsActive INT DEFAULT 1,
        CreatedAt DATETIME DEFAULT GETDATE(),
        UpdatedAt DATETIME NULL
    );
    PRINT 'Table User_TTD_Template_Umum created successfully.';
END
ELSE
BEGIN
    PRINT 'Table User_TTD_Template_Umum already exists.';
END

-- 3. Table: Form_Umum_TTD
IF OBJECT_ID('dbo.Form_Umum_TTD', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Form_Umum_TTD (
        Id INT IDENTITY(1,1) PRIMARY KEY,
        Ticket VARCHAR(50) NOT NULL,
        GroupRole VARCHAR(100) NOT NULL,
        SignaturePath VARCHAR(255) NOT NULL,
        SignedByUserId INT NOT NULL,
        SignedByUserName VARCHAR(100) NOT NULL,
        SignedAt DATETIME DEFAULT GETDATE()
    );
    PRINT 'Table Form_Umum_TTD created successfully.';
END
ELSE
BEGIN
    PRINT 'Table Form_Umum_TTD already exists.';
END
