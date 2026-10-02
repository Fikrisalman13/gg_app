IF OBJECT_ID('dbo.ccirl', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ccirl
    (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ccirl PRIMARY KEY,
        Tanggal DATE NOT NULL,
        Compressor_No VARCHAR(20) NOT NULL,
        Jam TIME(0) NOT NULL,
        Item_Key VARCHAR(100) NOT NULL,
        Item_Check NVARCHAR(255) NOT NULL,
        Nilai NVARCHAR(50) NULL,
        Petugas NVARCHAR(100) NULL,
        Keterangan NVARCHAR(255) NULL,
        CreatBy NVARCHAR(100) NULL,
        CreatAt DATETIME NULL CONSTRAINT DF_ccirl_CreatAt DEFAULT GETDATE(),
        UpdateBy NVARCHAR(100) NULL,
        UpdateAt DATETIME NULL
    );

    CREATE INDEX IX_ccirl_sheet
        ON dbo.ccirl (Tanggal, Compressor_No, Jam, Item_Key);
END;
