IF OBJECT_ID('dbo.air_dryer', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.air_dryer
    (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_air_dryer PRIMARY KEY,
        Tanggal DATE NOT NULL,
        Jam_Pengecekan TIME(0) NOT NULL,
        AirDryer1_Temp_In_C DECIMAL(10,2) NULL,
        AirDryer1_Temp_Out_C DECIMAL(10,2) NULL,
        AirDryer1_Tekanan_In_Bar DECIMAL(10,2) NULL,
        AirDryer1_Tekanan_Out_Bar DECIMAL(10,2) NULL,
        AirDryer2_Temp_In_C DECIMAL(10,2) NULL,
        AirDryer2_Temp_Out_C DECIMAL(10,2) NULL,
        AirDryer2_Tekanan_In_Bar DECIMAL(10,2) NULL,
        AirDryer2_Tekanan_Out_Bar DECIMAL(10,2) NULL,
        Petugas NVARCHAR(255) NULL,
        Keterangan NVARCHAR(255) NULL,
        CreatBy NVARCHAR(100) NULL,
        CreatAt DATETIME NULL CONSTRAINT DF_air_dryer_CreatAt DEFAULT GETDATE(),
        UpdateBy NVARCHAR(100) NULL,
        UpdateAt DATETIME NULL
    );

    CREATE INDEX IX_air_dryer_tanggal_jam
        ON dbo.air_dryer (Tanggal, Jam_Pengecekan);
END;

IF OBJECT_ID('dbo.air_dryer', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.air_dryer', 'Petugas') IS NULL
BEGIN
    ALTER TABLE dbo.air_dryer
        ADD Petugas NVARCHAR(100) NULL;
END;
