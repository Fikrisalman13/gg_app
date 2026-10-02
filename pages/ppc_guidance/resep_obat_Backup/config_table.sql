IF OBJECT_ID('dbo.resep_config', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.resep_config (
        id INT PRIMARY KEY IDENTITY(1,1),
        min_cost DECIMAL(18,2) NULL,
        max_cost DECIMAL(18,2) NULL,
        updated_at DATETIME,
        updated_by VARCHAR(50)
    );
    INSERT INTO dbo.resep_config (min_cost, max_cost, updated_at, updated_by) VALUES (NULL, NULL, GETDATE(), 'SYSTEM');
END
