IF OBJECT_ID(N'dbo.Form_Serah_Terima_Aplikasi', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Form_Serah_Terima_Aplikasi (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ticket VARCHAR(50) NOT NULL,
        nama_pemohon NVARCHAR(150) NULL,
        jabatan NVARCHAR(150) NULL,
        departemen NVARCHAR(150) NULL,
        bagian NVARCHAR(150) NULL,
        tanggal_serah_terima DATE NOT NULL,
        tanggal_selesai DATE NOT NULL,
        diminta_oleh NVARCHAR(255) NOT NULL,
        nama_aplikasi NVARCHAR(255) NOT NULL,
        nama_modul NVARCHAR(MAX) NOT NULL,
        deskripsi_aplikasi NVARCHAR(MAX) NOT NULL,
        status_testing_it BIT NOT NULL CONSTRAINT DF_FormSerahTerima_StatusTestingIt DEFAULT (0),
        status_testing_user BIT NOT NULL CONSTRAINT DF_FormSerahTerima_StatusTestingUser DEFAULT (0),
        hasil_testing VARCHAR(20) NOT NULL,
        catatan_revisi NVARCHAR(MAX) NULL,
        status_penerimaan VARCHAR(20) NOT NULL,
        catatan_penerimaan NVARCHAR(MAX) NULL,
        status_ticket VARCHAR(50) NOT NULL CONSTRAINT DF_FormSerahTerima_StatusTicket DEFAULT ('Pending'),
        kategori NVARCHAR(100) NOT NULL CONSTRAINT DF_FormSerahTerima_Kategori DEFAULT ('Serah Terima Aplikasi'),
        rejected_by NVARCHAR(150) NULL,
        rejection_reason NVARCHAR(MAX) NULL,
        rejection_date DATETIME NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_FormSerahTerima_CreatedAt DEFAULT (GETDATE()),
        created_by NVARCHAR(150) NULL,
        updated_at DATETIME NULL,
        updated_by NVARCHAR(150) NULL
    );

    ALTER TABLE dbo.Form_Serah_Terima_Aplikasi
    ADD CONSTRAINT UQ_FormSerahTerima_Ticket UNIQUE (ticket);

    ALTER TABLE dbo.Form_Serah_Terima_Aplikasi
    ADD CONSTRAINT CK_FormSerahTerima_HasilTesting
    CHECK (hasil_testing IN ('sesuai', 'revisi'));

    ALTER TABLE dbo.Form_Serah_Terima_Aplikasi
    ADD CONSTRAINT CK_FormSerahTerima_StatusPenerimaan
    CHECK (status_penerimaan IN ('sesuai', 'catatan', 'perbaikan'));

    CREATE INDEX IX_FormSerahTerima_Tanggal
    ON dbo.Form_Serah_Terima_Aplikasi (tanggal_serah_terima DESC, created_at DESC);
END;
