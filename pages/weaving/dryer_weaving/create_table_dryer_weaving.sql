IF OBJECT_ID('dbo.dryer_weaving', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.dryer_weaving
    (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_dryer_weaving PRIMARY KEY,
        Tanggal DATE NOT NULL,
        Dryer_No VARCHAR(20) NOT NULL,
        Ct_No VARCHAR(20) NOT NULL,
        Jam TIME(0) NOT NULL,
        Kategori NVARCHAR(50) NOT NULL,
        Item_Key VARCHAR(100) NOT NULL,
        Item_Check NVARCHAR(200) NOT NULL,
        Standar NVARCHAR(100) NULL,
        Nilai NVARCHAR(50) NULL,
        Petugas NVARCHAR(100) NULL,
        Shift NVARCHAR(50) NULL,
        Keterangan NVARCHAR(255) NULL,
        CreatBy NVARCHAR(100) NULL,
        CreatAt DATETIME NULL CONSTRAINT DF_dryer_weaving_CreatAt DEFAULT GETDATE(),
        UpdateBy NVARCHAR(100) NULL,
        UpdateAt DATETIME NULL
    );

    CREATE INDEX IX_dryer_weaving_sheet
        ON dbo.dryer_weaving (Tanggal, Dryer_No, Ct_No, Jam, Item_Key);
END;
