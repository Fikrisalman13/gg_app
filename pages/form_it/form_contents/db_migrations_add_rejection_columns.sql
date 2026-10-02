-- Migration: Add rejection columns to pengajuan tables
-- Adds: rejected_by INT, rejection_reason NVARCHAR(MAX), rejection_date DATETIME
-- Safe to run multiple times; checks if columns exist before altering
SET NOCOUNT ON;

BEGIN TRANSACTION;
BEGIN TRY

-- Form_Pengajuan_Akses_Internet
IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'rejected_by') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet ADD rejected_by INT NULL;
    PRINT 'Added rejected_by to Form_Pengajuan_Akses_Internet';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'rejection_reason') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet ADD rejection_reason NVARCHAR(MAX) NULL;
    PRINT 'Added rejection_reason to Form_Pengajuan_Akses_Internet';
END

IF COL_LENGTH('dbo.Form_Pengajuan_Akses_Internet', 'rejection_date') IS NULL
BEGIN
    ALTER TABLE dbo.Form_Pengajuan_Akses_Internet ADD rejection_date DATETIME NULL;
    PRINT 'Added rejection_date to Form_Pengajuan_Akses_Internet';
END

-- Form_Pengajuan_CCTV
IF OBJECT_ID('dbo.Form_Pengajuan_CCTV', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.Form_Pengajuan_CCTV', 'rejected_by') IS NULL
    BEGIN
        ALTER TABLE dbo.Form_Pengajuan_CCTV ADD rejected_by INT NULL;
        PRINT 'Added rejected_by to Form_Pengajuan_CCTV';
    END
    IF COL_LENGTH('dbo.Form_Pengajuan_CCTV', 'rejection_reason') IS NULL
    BEGIN
        ALTER TABLE dbo.Form_Pengajuan_CCTV ADD rejection_reason NVARCHAR(MAX) NULL;
        PRINT 'Added rejection_reason to Form_Pengajuan_CCTV';
    END
    IF COL_LENGTH('dbo.Form_Pengajuan_CCTV', 'rejection_date') IS NULL
    BEGIN
        ALTER TABLE dbo.Form_Pengajuan_CCTV ADD rejection_date DATETIME NULL;
        PRINT 'Added rejection_date to Form_Pengajuan_CCTV';
    END
END

-- Form_Pengajuan_Barang
IF OBJECT_ID('dbo.Form_Pengajuan_Barang', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.Form_Pengajuan_Barang', 'rejected_by') IS NULL
    BEGIN
        ALTER TABLE dbo.Form_Pengajuan_Barang ADD rejected_by INT NULL;
        PRINT 'Added rejected_by to Form_Pengajuan_Barang';
    END
    IF COL_LENGTH('dbo.Form_Pengajuan_Barang', 'rejection_reason') IS NULL
    BEGIN
        ALTER TABLE dbo.Form_Pengajuan_Barang ADD rejection_reason NVARCHAR(MAX) NULL;
        PRINT 'Added rejection_reason to Form_Pengajuan_Barang';
    END
    IF COL_LENGTH('dbo.Form_Pengajuan_Barang', 'rejection_date') IS NULL
    BEGIN
        ALTER TABLE dbo.Form_Pengajuan_Barang ADD rejection_date DATETIME NULL;
        PRINT 'Added rejection_date to Form_Pengajuan_Barang';
    END
END

COMMIT TRANSACTION;
PRINT 'Migration completed successfully.';

END TRY
BEGIN CATCH
    ROLLBACK TRANSACTION;
    DECLARE @ErrMsg NVARCHAR(4000) = ERROR_MESSAGE();
    DECLARE @ErrNum INT = ERROR_NUMBER();
    PRINT 'Migration failed: ' + COALESCE(@ErrMsg, 'Unknown error');
    RAISERROR('Migration failed: %s', 16, 1, @ErrMsg);
END CATCH;

SET NOCOUNT OFF;
