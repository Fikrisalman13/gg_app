IF OBJECT_ID('dbo.knowledge_base_categories', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.knowledge_base_categories (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_knowledge_base_categories PRIMARY KEY,
        name NVARCHAR(150) NOT NULL,
        bagian_id INT NOT NULL,
        is_active BIT NOT NULL CONSTRAINT DF_kb_categories_is_active DEFAULT (1),
        created_at DATETIME2 NOT NULL CONSTRAINT DF_kb_categories_created_at DEFAULT (SYSDATETIME()),
        created_by INT NOT NULL,
        updated_at DATETIME2 NULL,
        updated_by INT NULL,
        CONSTRAINT UQ_kb_categories_bagian_name UNIQUE (bagian_id, name)
    );
END;

IF OBJECT_ID('dbo.knowledge_base_articles', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.knowledge_base_articles (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_knowledge_base_articles PRIMARY KEY,
        title NVARCHAR(200) NOT NULL,
        category_id INT NOT NULL,
        bagian_id INT NOT NULL,
        bagian_name_snapshot NVARCHAR(100) NULL,
        message_html NVARCHAR(MAX) NOT NULL,
        is_deleted BIT NOT NULL CONSTRAINT DF_kb_articles_is_deleted DEFAULT (0),
        deleted_at DATETIME2 NULL,
        deleted_by INT NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_kb_articles_created_at DEFAULT (SYSDATETIME()),
        created_by INT NOT NULL,
        updated_at DATETIME2 NULL,
        updated_by INT NULL,
        CONSTRAINT FK_kb_articles_category FOREIGN KEY (category_id) REFERENCES dbo.knowledge_base_categories(id)
    );
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_kb_articles_visibility' AND object_id = OBJECT_ID('dbo.knowledge_base_articles'))
BEGIN
    CREATE INDEX IX_kb_articles_visibility ON dbo.knowledge_base_articles (is_deleted, bagian_id, category_id, created_at DESC);
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_kb_categories_scope' AND object_id = OBJECT_ID('dbo.knowledge_base_categories'))
BEGIN
    CREATE INDEX IX_kb_categories_scope ON dbo.knowledge_base_categories (bagian_id, is_active, name);
END;
