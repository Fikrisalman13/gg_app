-- Script pembuatan tabel dbo.temp_compressor_v2
IF OBJECT_ID('dbo.temp_compressor_v2', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.temp_compressor_v2
    (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_temp_compressor_v2 PRIMARY KEY,
        Tanggal DATE NOT NULL,
        Weaving TINYINT NOT NULL,          -- 1 atau 2
        Compressor_No TINYINT NOT NULL,    -- 1, 2, atau 3
        Jam TIME(0) NOT NULL,              -- 06:30, 07:30, dst.
        PressureBar_P1       DECIMAL(10,2) NULL, -- Pressure (Bar) P1
        PressureBar_P2       DECIMAL(10,2) NULL, -- Pressure (Bar) P2
        Temperature_T1       DECIMAL(10,2) NULL, -- Temperature T1
        Temperature_T2       DECIMAL(10,2) NULL, -- Temperature T2
        Temperature_T3       DECIMAL(10,2) NULL, -- Temperature T3
        Dryer_C              DECIMAL(10,2) NULL, -- Dryer (°C)
        ArusListrik_A        DECIMAL(10,2) NULL, -- Arus Listrik (A)
        AirCooling_PressIn   DECIMAL(10,2) NULL, -- Air Cooling Press. IN
        AirCooling_PressOut  DECIMAL(10,2) NULL, -- Air Cooling Press. OUT
        AirCooling_TempIn    DECIMAL(10,2) NULL, -- Air Cooling Temp. IN
        AirCooling_TempOut   DECIMAL(10,2) NULL, -- Air Cooling Temp. OUT
        Pelaksana            NVARCHAR(255) NULL, -- Nama Petugas/Pelaksana
        Keterangan           NVARCHAR(255) NULL, -- Keterangan opsional
        CreatBy              NVARCHAR(100) NULL,
        CreatAt              DATETIME NULL CONSTRAINT DF_temp_compressor_v2_CreatAt DEFAULT GETDATE(),
        UpdateBy             NVARCHAR(100) NULL,
        UpdateAt             DATETIME NULL
    );

    CREATE UNIQUE INDEX UQ_temp_compressor_v2_sheet_jam
        ON dbo.temp_compressor_v2 (Tanggal, Weaving, Compressor_No, Jam);

    CREATE INDEX IX_temp_compressor_v2_sheet
        ON dbo.temp_compressor_v2 (Tanggal, Weaving, Compressor_No);
END;
