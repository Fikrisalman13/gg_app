-- Multi-employee Izin Keluar Pabrik detail table.
-- Manual execution only after review.
IF OBJECT_ID('dbo.Form_Umum_Izin_Keluar_Pabrik_Detail', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Form_Umum_Izin_Keluar_Pabrik_Detail (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Form_Umum_Izin_Keluar_Pabrik_Detail PRIMARY KEY,
        ticket VARCHAR(30) NOT NULL,
        nik VARCHAR(30) NOT NULL,
        nama_pemohon NVARCHAR(100) NOT NULL,
        departemen NVARCHAR(100) NOT NULL,
        bagian NVARCHAR(100) NULL,
        jabatan NVARCHAR(100) NULL,
        no_hp VARCHAR(20) NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_Form_Umum_Izin_Keluar_Pabrik_Detail_created_at DEFAULT GETDATE(),
        created_by VARCHAR(100) NULL,
        updated_at DATETIME NULL,
        updated_by VARCHAR(100) NULL,
        CONSTRAINT UQ_Form_Umum_Izin_Keluar_Pabrik_Detail_ticket_nik UNIQUE (ticket, nik)
    );
    CREATE INDEX IX_Form_Umum_Izin_Keluar_Pabrik_Detail_ticket ON dbo.Form_Umum_Izin_Keluar_Pabrik_Detail (ticket);
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Form_Umum_Izin_Keluar_Pabrik_Detail_Ticket')
BEGIN
    ALTER TABLE dbo.Form_Umum_Izin_Keluar_Pabrik_Detail ADD CONSTRAINT FK_Form_Umum_Izin_Keluar_Pabrik_Detail_Ticket FOREIGN KEY (ticket) REFERENCES dbo.Form_Umum_Izin_Keluar_Pabrik (ticket);
END;
GO
