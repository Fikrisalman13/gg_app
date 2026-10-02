IF OBJECT_ID('dbo.upload_pengebalan_hd', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.upload_pengebalan_hd (
        hdid INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        wrhsid NVARCHAR(25) NOT NULL,
        wrhsname NVARCHAR(100) NOT NULL,
        total_pcs INT NOT NULL CONSTRAINT DF_upload_pengebalan_hd_total_pcs DEFAULT (0),
        total_m DECIMAL(19,4) NOT NULL CONSTRAINT DF_upload_pengebalan_hd_total_m DEFAULT (0),
        total_yard DECIMAL(19,4) NOT NULL CONSTRAINT DF_upload_pengebalan_hd_total_yard DEFAULT (0),
        photo_barang NVARCHAR(255) NULL,
        photo_packinglist NVARCHAR(255) NULL,
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_upload_pengebalan_hd_status DEFAULT ('DRAFT'),
        created_by NVARCHAR(100) NULL,
        updated_by NVARCHAR(100) NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_upload_pengebalan_hd_created_at DEFAULT SYSDATETIME(),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_upload_pengebalan_hd_updated_at DEFAULT SYSDATETIME()
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_upload_pengebalan_hd_wrhsid_created_at'
      AND object_id = OBJECT_ID('dbo.upload_pengebalan_hd')
)
BEGIN
    CREATE INDEX IX_upload_pengebalan_hd_wrhsid_created_at
        ON dbo.upload_pengebalan_hd (wrhsid, created_at DESC);
END;
GO

IF OBJECT_ID('dbo.upload_pengebalan_dt', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.upload_pengebalan_dt (
        dtid INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        hdid INT NOT NULL,
        seq INT NOT NULL,
        source_balehdid NVARCHAR(100) NOT NULL,
        source_baleprodid NVARCHAR(100) NOT NULL,
        batchno NVARCHAR(100) NOT NULL,
        qtym DECIMAL(19,4) NOT NULL CONSTRAINT DF_upload_pengebalan_dt_qtym DEFAULT (0),
        qtyyard DECIMAL(19,4) NOT NULL CONSTRAINT DF_upload_pengebalan_dt_qtyyard DEFAULT (0),
        qtykg DECIMAL(19,4) NOT NULL CONSTRAINT DF_upload_pengebalan_dt_qtykg DEFAULT (0),
        balenmbr NVARCHAR(100) NOT NULL,
        baledesc NVARCHAR(255) NULL,
        baledate DATE NULL,
        prodcode NVARCHAR(100) NOT NULL,
        prodname NVARCHAR(255) NOT NULL,
        created_by NVARCHAR(100) NULL,
        updated_by NVARCHAR(100) NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_upload_pengebalan_dt_created_at DEFAULT SYSDATETIME(),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_upload_pengebalan_dt_updated_at DEFAULT SYSDATETIME()
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_upload_pengebalan_dt_hd'
)
BEGIN
    ALTER TABLE dbo.upload_pengebalan_dt
    ADD CONSTRAINT FK_upload_pengebalan_dt_hd
        FOREIGN KEY (hdid) REFERENCES dbo.upload_pengebalan_hd (hdid)
        ON DELETE CASCADE;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_upload_pengebalan_dt_hdid_prodcode_batchno'
      AND object_id = OBJECT_ID('dbo.upload_pengebalan_dt')
)
BEGIN
    CREATE INDEX IX_upload_pengebalan_dt_hdid_prodcode_batchno
        ON dbo.upload_pengebalan_dt (hdid, prodcode, balenmbr, batchno);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UQ_upload_pengebalan_dt_unique_line'
      AND object_id = OBJECT_ID('dbo.upload_pengebalan_dt')
)
BEGIN
    CREATE UNIQUE INDEX UQ_upload_pengebalan_dt_unique_line
        ON dbo.upload_pengebalan_dt (hdid, source_balehdid, source_baleprodid, batchno);
END;
GO
