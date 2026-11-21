-- =========================================================================
-- DDL Table Schema for PR Online (Purchase Request Online)
-- Target Database: GG (SQL Server - koneksi.php)
-- =========================================================================

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'pr_online_header')
BEGIN
    CREATE TABLE dbo.pr_online_header (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        pphdid INT NOT NULL,
        ppnmbr VARCHAR(100) NOT NULL UNIQUE,
        ppdate DATE NULL,
        ppdesc NVARCHAR(MAX) NULL,
        fgstatus VARCHAR(10) NULL,
        ppprorgid INT NULL,
        prorgname VARCHAR(150) NULL,
        created_by VARCHAR(100) NULL,
        created_at DATETIME DEFAULT GETDATE(),
        status_po VARCHAR(50) DEFAULT 'Pending',
        status_pengerjaan VARCHAR(50) DEFAULT 'Belum Dikerjakan',
        pic_name NVARCHAR(150) NULL,
        pic_username VARCHAR(100) NULL,
        assigned_at DATETIME NULL,
        no_po VARCHAR(100) NULL,
        po_date DATE NULL
    );

    CREATE INDEX IX_pr_online_header_ppnmbr ON dbo.pr_online_header(ppnmbr);
    CREATE INDEX IX_pr_online_header_ppdate ON dbo.pr_online_header(ppdate);
    CREATE INDEX IX_pr_online_header_status ON dbo.pr_online_header(status_pengerjaan);
END;

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'pr_online_detail')
BEGIN
    CREATE TABLE dbo.pr_online_detail (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        header_id BIGINT NOT NULL,
        pphdid INT NOT NULL,
        ppdtid INT NOT NULL,
        ppdtseq INT NULL,
        ppprodid INT NULL,
        ppprodcode VARCHAR(100) NULL,
        ppprodname NVARCHAR(255) NULL,
        ppproddesc NVARCHAR(MAX) NULL,
        ppqty DECIMAL(18, 4) DEFAULT 0,
        ppuomid INT NULL,
        uomname VARCHAR(50) NULL,
        ppprice DECIMAL(18, 4) DEFAULT 0,
        pptotalprice DECIMAL(18, 4) DEFAULT 0,
        created_at DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_pr_online_detail_header FOREIGN KEY (header_id) 
            REFERENCES dbo.pr_online_header(id) ON DELETE CASCADE
    );

    CREATE INDEX IX_pr_online_detail_header_id ON dbo.pr_online_detail(header_id);
    CREATE INDEX IX_pr_online_detail_ppdtid ON dbo.pr_online_detail(ppdtid);
END;
