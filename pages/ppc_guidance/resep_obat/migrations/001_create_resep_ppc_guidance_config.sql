IF OBJECT_ID('dbo.resep_ppc_guidance_config', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.resep_ppc_guidance_config (
        config_key VARCHAR(100) NOT NULL PRIMARY KEY,
        config_value VARCHAR(255) NOT NULL,
        updated_at DATETIME2 NULL,
        updated_by VARCHAR(100) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1 FROM dbo.resep_ppc_guidance_config WHERE config_key = 'show_proint_metadata'
)
BEGIN
    INSERT INTO dbo.resep_ppc_guidance_config (config_key, config_value, updated_at, updated_by)
    VALUES ('show_proint_metadata', '0', SYSDATETIME(), 'migration');
END;
