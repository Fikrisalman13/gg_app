-- SQL Server migration script for adding columns to dbo.Form_Pengajuan_Akses_Internet
-- This script is safe to run multiple times: it checks for column existence before adding.

-- NOTE: run this in SQL Server (SSMS) or via your SQL Server client. Do NOT use MySQL syntax/backticks.

IF OBJECT_ID('dbo.Form_Pengajuan_Akses_Internet', 'U') IS NULL
BEGIN
    PRINT 'Table dbo.Form_Pengajuan_Akses_Internet does not exist. Please verify the table name.';
    RETURN;
END

-- Add BIT flag for request_akses_internet
IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'request_akses_internet') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD request_akses_internet BIT NULL;
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD CONSTRAINT DF_Form_Pengajuan_Akses_Internet_request_akses_internet DEFAULT (0) FOR request_akses_internet;
    PRINT 'Added column request_akses_internet';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'akses_type') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD akses_type VARCHAR(20) NULL;
    PRINT 'Added column akses_type';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'akses_temporary_from') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD akses_temporary_from DATE NULL;
    PRINT 'Added column akses_temporary_from';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'akses_temporary_to') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD akses_temporary_to DATE NULL;
    PRINT 'Added column akses_temporary_to';
END

-- Bandwidth request flag and details
IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'request_tambah_bandwidth') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD request_tambah_bandwidth BIT NULL;
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD CONSTRAINT DF_Form_Pengajuan_Akses_Internet_request_tambah_bandwidth DEFAULT (0) FOR request_tambah_bandwidth;
    PRINT 'Added column request_tambah_bandwidth';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'bandwidth_type') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD bandwidth_type VARCHAR(20) NULL;
    PRINT 'Added column bandwidth_type';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'bandwidth_temporary_from') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD bandwidth_temporary_from DATE NULL;
    PRINT 'Added column bandwidth_temporary_from';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'bandwidth_temporary_to') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD bandwidth_temporary_to DATE NULL;
    PRINT 'Added column bandwidth_temporary_to';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'tambah_bandwidth') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD tambah_bandwidth INT NULL;
    PRINT 'Added column tambah_bandwidth';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'tambah_bandwidth_unit') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet
    ADD tambah_bandwidth_unit VARCHAR(10) NULL;
    PRINT 'Added column tambah_bandwidth_unit';
END

PRINT 'Migration script completed.';