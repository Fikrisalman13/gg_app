-- Migration: Add attachment columns to Form_Umum_Buka_Tanggal_Closingan
-- Date: 2026-05-30

-- Kolom untuk menyimpan nama file lampiran (original filename)
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'attachment_filename')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD attachment_filename VARCHAR(255) NULL;
    PRINT 'Column attachment_filename added successfully';
END
ELSE
BEGIN
    PRINT 'Column attachment_filename already exists';
END
GO

-- Kolom untuk menyimpan path file lampiran
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'attachment_path')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD attachment_path VARCHAR(500) NULL;
    PRINT 'Column attachment_path added successfully';
END
ELSE
BEGIN
    PRINT 'Column attachment_path already exists';
END
GO

-- Kolom untuk menyimpan ukuran file (dalam bytes)
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'attachment_size')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD attachment_size INT NULL;
    PRINT 'Column attachment_size added successfully';
END
ELSE
BEGIN
    PRINT 'Column attachment_size already exists';
END
GO

-- Kolom untuk menyimpan timestamp upload
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' 
               AND COLUMN_NAME = 'attachment_uploaded_at')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan 
    ADD attachment_uploaded_at DATETIME NULL;
    PRINT 'Column attachment_uploaded_at added successfully';
END
ELSE
BEGIN
    PRINT 'Column attachment_uploaded_at already exists';
END
GO

PRINT 'Migration completed successfully!';
