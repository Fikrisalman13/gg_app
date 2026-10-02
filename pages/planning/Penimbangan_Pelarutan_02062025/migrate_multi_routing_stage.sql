IF OBJECT_ID('dbo.cpp_paddry_stage', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.cpp_paddry_stage (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        paddry_id BIGINT NOT NULL,
        cp_no NVARCHAR(50) NOT NULL,
        stage_no TINYINT NOT NULL,
        rtgmsid INT NOT NULL,
        rtgseq INT NULL,
        rtgname NVARCHAR(150) NOT NULL,
        start_at DATETIME NULL,
        finish_at DATETIME NULL,
        shift_start_id INT NULL,
        shift_start_name NVARCHAR(100) NULL,
        shift_end_id INT NULL,
        shift_end_name NVARCHAR(100) NULL,
        wheel_no NVARCHAR(50) NULL,
        wheel_msid INT NULL,
        lebar_kain DECIMAL(18,4) NULL,
        fgresult CHAR(1) NULL,
        fail_code NVARCHAR(50) NULL,
        fail_msid INT NULL,
        fail_desc NVARCHAR(255) NULL,
        keterangan_fail NVARCHAR(MAX) NULL,
        erp_productionhdid BIGINT NULL,
        erp_productionrtgid BIGINT NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_cpp_paddry_stage_created_at DEFAULT GETDATE(),
        created_by NVARCHAR(100) NOT NULL CONSTRAINT DF_cpp_paddry_stage_created_by DEFAULT 'SYSTEM',
        updated_at DATETIME NOT NULL CONSTRAINT DF_cpp_paddry_stage_updated_at DEFAULT GETDATE(),
        updated_by NVARCHAR(100) NOT NULL CONSTRAINT DF_cpp_paddry_stage_updated_by DEFAULT 'SYSTEM'
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_cpp_paddry_stage_paddry_rtgmsid'
      AND object_id = OBJECT_ID('dbo.cpp_paddry_stage')
)
BEGIN
    CREATE UNIQUE INDEX UX_cpp_paddry_stage_paddry_rtgmsid
        ON dbo.cpp_paddry_stage (paddry_id, rtgmsid);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_cpp_paddry_stage_cp_no'
      AND object_id = OBJECT_ID('dbo.cpp_paddry_stage')
)
BEGIN
    CREATE INDEX IX_cpp_paddry_stage_cp_no
        ON dbo.cpp_paddry_stage (cp_no);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_cpp_paddry_stage_paddry_stage_no'
      AND object_id = OBJECT_ID('dbo.cpp_paddry_stage')
)
BEGIN
    CREATE INDEX IX_cpp_paddry_stage_paddry_stage_no
        ON dbo.cpp_paddry_stage (paddry_id, stage_no);
END
GO

IF COL_LENGTH('dbo.cpp_paddry_downtime', 'paddry_stage_id') IS NULL
BEGIN
    ALTER TABLE dbo.cpp_paddry_downtime
        ADD paddry_stage_id BIGINT NULL;
END
GO

IF COL_LENGTH('dbo.cpp_paddry_downtime', 'created_at') IS NULL
BEGIN
    ALTER TABLE dbo.cpp_paddry_downtime
        ADD created_at DATETIME NULL;
END
GO

IF COL_LENGTH('dbo.cpp_paddry_downtime', 'updated_at') IS NULL
BEGIN
    ALTER TABLE dbo.cpp_paddry_downtime
        ADD updated_at DATETIME NULL;
END
GO

IF COL_LENGTH('dbo.cpp_paddry_downtime', 'created_by') IS NULL
BEGIN
    ALTER TABLE dbo.cpp_paddry_downtime
        ADD created_by NVARCHAR(100) NULL;
END
GO

IF COL_LENGTH('dbo.cpp_paddry_downtime', 'updated_by') IS NULL
BEGIN
    ALTER TABLE dbo.cpp_paddry_downtime
        ADD updated_by NVARCHAR(100) NULL;
END
GO

UPDATE dbo.cpp_paddry_downtime
SET created_at = ISNULL(created_at, waktu_start),
    updated_at = ISNULL(updated_at, waktu_stop),
    created_by = ISNULL(created_by, 'SYSTEM'),
    updated_by = ISNULL(updated_by, 'SYSTEM');
GO
