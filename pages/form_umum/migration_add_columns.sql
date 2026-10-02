-- ============================================
-- Migration: Add columns for dynamic approval flow
-- Run this in Navicat on the GG database
-- ============================================

-- 1. Add jenis_pengajuan column
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'jenis_pengajuan')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD jenis_pengajuan VARCHAR(20) NULL;
    PRINT 'Column jenis_pengajuan added successfully.';
END
ELSE
    PRINT 'Column jenis_pengajuan already exists.';

-- 2. Add is_revisi_harga column
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'is_revisi_harga')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD is_revisi_harga INT DEFAULT 0;
    PRINT 'Column is_revisi_harga added successfully.';
END
ELSE
    PRINT 'Column is_revisi_harga already exists.';
