IF OBJECT_ID('dbo.master_obat', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.master_obat (
        id INT IDENTITY(1,1) PRIMARY KEY,
        kode_obat VARCHAR(50),
        codeprod_proint VARCHAR(50),
        nama_obat VARCHAR(255),
        group_obat VARCHAR(100),
        created_at DATETIME,
        created_by VARCHAR(50),
        update_at DATETIME,
        update_by VARCHAR(50)
    );
END
