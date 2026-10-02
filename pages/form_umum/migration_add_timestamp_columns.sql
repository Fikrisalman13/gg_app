-- ============================================================================
-- Migration: Add Timestamp Columns for Unclosing and Closed Actions
-- Description: Menambahkan kolom untuk tracking waktu dan user yang melakukan
--              aksi Unclosing dan Closed pada form
-- Date: 2026-05-30
-- ============================================================================

-- Kolom untuk menyimpan timestamp aksi Unclosing
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'unclosing_at')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD unclosing_at DATETIME NULL;
    PRINT 'Column unclosing_at added successfully.';
END
ELSE
BEGIN
    PRINT 'Column unclosing_at already exists.';
END
GO

-- Kolom untuk menyimpan timestamp aksi Closed
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'closed_at')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD closed_at DATETIME NULL;
    PRINT 'Column closed_at added successfully.';
END
ELSE
BEGIN
    PRINT 'Column closed_at already exists.';
END
GO

-- Kolom untuk menyimpan user yang melakukan Unclosing
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'unclosing_by')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD unclosing_by VARCHAR(100) NULL;
    PRINT 'Column unclosing_by added successfully.';
END
ELSE
BEGIN
    PRINT 'Column unclosing_by already exists.';
END
GO

-- Kolom untuk menyimpan user yang melakukan Closed
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'closed_by')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD closed_by VARCHAR(100) NULL;
    PRINT 'Column closed_by added successfully.';
END
ELSE
BEGIN
    PRINT 'Column closed_by already exists.';
END
GO

PRINT '============================================================================';
PRINT 'Migration completed successfully!';
PRINT 'Added columns: unclosing_at, closed_at, unclosing_by, closed_by';
PRINT '============================================================================';
