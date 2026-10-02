IF OBJECT_ID('dbo.data_bale_upload', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.data_bale_upload (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        balehdid NVARCHAR(100) NOT NULL,
        photo_barang NVARCHAR(255) NULL,
        photo_packinglist NVARCHAR(255) NULL,
        created_by NVARCHAR(100) NULL,
        updated_by NVARCHAR(100) NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_data_bale_upload_created_at DEFAULT SYSDATETIME(),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_data_bale_upload_updated_at DEFAULT SYSDATETIME()
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = 'UQ_data_bale_upload_balehdid' AND object_id = OBJECT_ID('dbo.data_bale_upload')
)
BEGIN
    ALTER TABLE dbo.data_bale_upload
    ADD CONSTRAINT UQ_data_bale_upload_balehdid UNIQUE (balehdid);
END;
GO
