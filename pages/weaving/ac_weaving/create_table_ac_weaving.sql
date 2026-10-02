IF OBJECT_ID('dbo.ac_weaving', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ac_weaving
    (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ac_weaving PRIMARY KEY,
        Mesin VARCHAR(30) NOT NULL,
        Tanggal DATE NOT NULL,
        Jam TIME(0) NOT NULL,
        Aktual_Check TIME(0) NOT NULL,
        Pb1_Dew_Point DECIMAL(10,2) NULL,
        Humidity DECIMAL(10,2) NULL,
        Amper DECIMAL(10,2) NULL,
        Differential_Best_Air DECIMAL(10,2) NULL,
        Petugas NVARCHAR(255) NULL,
        Shift NVARCHAR(50) NULL,
        Keterangan NVARCHAR(MAX) NULL,
        CreatBy NVARCHAR(100) NULL,
        CreatAt DATETIME NULL CONSTRAINT DF_ac_weaving_CreatAt DEFAULT GETDATE(),
        UpdateBy NVARCHAR(100) NULL,
        UpdateAt DATETIME NULL
    );

    CREATE INDEX IX_ac_weaving_tanggal_jam_mesin
        ON dbo.ac_weaving (Tanggal, Jam, Mesin);
END;
