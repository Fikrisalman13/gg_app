IF OBJECT_ID('dbo.temp_compressor', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.temp_compressor
    (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_temp_compressor PRIMARY KEY,
        Tanggal DATE NOT NULL,
        Jam TIME(0) NOT NULL,
        Compressor1_In_C DECIMAL(10,2) NULL,
        Compressor1_Out_C DECIMAL(10,2) NULL,
        Compressor2_In_C DECIMAL(10,2) NULL,
        Compressor2_Out_C DECIMAL(10,2) NULL,
        Amper            DECIMAL(10,2) NULL,
        PressureBar_P1   DECIMAL(10,2) NULL,
        PressureBar_P2   DECIMAL(10,2) NULL,
        Temperature_T1   DECIMAL(10,2) NULL,
        Temperature_T2   DECIMAL(10,2) NULL,
        Temperature_T3   DECIMAL(10,2) NULL,
        Dryer_C          DECIMAL(10,2) NULL,
        TekananAir_In    DECIMAL(10,2) NULL,
        TekananAir_Out   DECIMAL(10,2) NULL,
        Petugas NVARCHAR(255) NULL,
        Keterangan NVARCHAR(255) NULL,
        CreatBy NVARCHAR(100) NULL,
        CreatAt DATETIME NULL CONSTRAINT DF_temp_compressor_CreatAt DEFAULT GETDATE(),
        UpdateBy NVARCHAR(100) NULL,
        UpdateAt DATETIME NULL
    );

    CREATE INDEX IX_temp_compressor_tanggal_jam
        ON dbo.temp_compressor (Tanggal, Jam);
END
ELSE
BEGIN
    -- ALTER: tambah kolom baru jika belum ada
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'Amper')
        ALTER TABLE dbo.temp_compressor ADD Amper DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'PressureBar_P1')
        ALTER TABLE dbo.temp_compressor ADD PressureBar_P1 DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'PressureBar_P2')
        ALTER TABLE dbo.temp_compressor ADD PressureBar_P2 DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'Temperature_T1')
        ALTER TABLE dbo.temp_compressor ADD Temperature_T1 DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'Temperature_T2')
        ALTER TABLE dbo.temp_compressor ADD Temperature_T2 DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'Temperature_T3')
        ALTER TABLE dbo.temp_compressor ADD Temperature_T3 DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'Dryer_C')
        ALTER TABLE dbo.temp_compressor ADD Dryer_C DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'TekananAir_In')
        ALTER TABLE dbo.temp_compressor ADD TekananAir_In DECIMAL(10,2) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.temp_compressor') AND name = 'TekananAir_Out')
        ALTER TABLE dbo.temp_compressor ADD TekananAir_Out DECIMAL(10,2) NULL;
END;
