SET XACT_ABORT ON;
BEGIN TRANSACTION;
IF OBJECT_ID('dbo.knowledge_base_article_bagian', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.knowledge_base_article_bagian (
        article_id INT NOT NULL,
        bagian_id INT NOT NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_kb_article_bagian_created_at DEFAULT (SYSDATETIME()),
        created_by INT NOT NULL,
        CONSTRAINT PK_kb_article_bagian PRIMARY KEY (article_id, bagian_id),
        CONSTRAINT FK_kb_article_bagian_article FOREIGN KEY (article_id) REFERENCES dbo.knowledge_base_articles(id),
        CONSTRAINT FK_kb_article_bagian_bagian FOREIGN KEY (bagian_id) REFERENCES dbo.m_bag(id_bag)
    );
END;
INSERT INTO dbo.knowledge_base_article_bagian (article_id, bagian_id, created_by)
SELECT a.id, a.bagian_id, a.created_by
FROM dbo.knowledge_base_articles a
WHERE NOT EXISTS (SELECT 1 FROM dbo.knowledge_base_article_bagian v WHERE v.article_id = a.id AND v.bagian_id = a.bagian_id);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_kb_article_bagian_bagian' AND object_id = OBJECT_ID('dbo.knowledge_base_article_bagian'))
    CREATE INDEX IX_kb_article_bagian_bagian ON dbo.knowledge_base_article_bagian (bagian_id, article_id);
COMMIT TRANSACTION;
