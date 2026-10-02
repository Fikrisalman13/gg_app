-- Tambah kolom waktu chamber ke master_mesin_lab
-- Waktu dalam satuan menit (decimal untuk presisi)

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.master_mesin_lab') AND name = 'temp_chamber_1_time')
BEGIN
    ALTER TABLE dbo.master_mesin_lab ADD temp_chamber_1_time DECIMAL(18,4) NULL;
    PRINT 'Added temp_chamber_1_time column';
END
ELSE
    PRINT 'Column temp_chamber_1_time already exists';

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.master_mesin_lab') AND name = 'temp_chamber_2_time')
BEGIN
    ALTER TABLE dbo.master_mesin_lab ADD temp_chamber_2_time DECIMAL(18,4) NULL;
    PRINT 'Added temp_chamber_2_time column';
END
ELSE
    PRINT 'Column temp_chamber_2_time already exists';
