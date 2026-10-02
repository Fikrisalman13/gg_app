SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.Form_Pengajuan_Aplikasi', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Form_Pengajuan_Aplikasi (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Form_Pengajuan_Aplikasi PRIMARY KEY,
        ticket VARCHAR(50) NOT NULL,
        nama_pemohon NVARCHAR(100) NOT NULL,
        jabatan NVARCHAR(100) NULL,
        departemen NVARCHAR(100) NULL,
        bagian NVARCHAR(100) NULL,
        tgl_pengajuan DATE NOT NULL,
        nama_aplikasi NVARCHAR(150) NOT NULL,
        latar_belakang_kendala NVARCHAR(MAX) NOT NULL,
        tujuan_pembuatan NVARCHAR(MAX) NOT NULL,
        gambaran_proses NVARCHAR(MAX) NOT NULL,
        fitur_utama NVARCHAR(MAX) NOT NULL,
        manfaat_diharapkan NVARCHAR(MAX) NOT NULL,
        lampiran_nama_asli NVARCHAR(255) NULL,
        lampiran_nama_file VARCHAR(255) NULL,
        lampiran_mime VARCHAR(150) NULL,
        lampiran_ukuran BIGINT NULL,
        lampiran_path NVARCHAR(500) NULL,
        status_ticket VARCHAR(50) NOT NULL CONSTRAINT DF_Form_Pengajuan_Aplikasi_Status DEFAULT ('Pending'),
        kategori VARCHAR(100) NOT NULL CONSTRAINT DF_Form_Pengajuan_Aplikasi_Kategori DEFAULT ('Pembuatan Aplikasi'),
        rejected_by NVARCHAR(100) NULL,
        rejection_reason NVARCHAR(MAX) NULL,
        rejection_date DATETIME NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_Form_Pengajuan_Aplikasi_CreatedAt DEFAULT (GETDATE()),
        created_by NVARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL CONSTRAINT DF_Form_Pengajuan_Aplikasi_UpdatedAt DEFAULT (GETDATE()),
        updated_by NVARCHAR(100) NULL,
        CONSTRAINT UQ_Form_Pengajuan_Aplikasi_Ticket UNIQUE (ticket),
        CONSTRAINT CK_Form_Pengajuan_Aplikasi_LampiranUkuran
            CHECK (lampiran_ukuran IS NULL OR (lampiran_ukuran >= 0 AND lampiran_ukuran <= 5242880))
    );
    CREATE INDEX IX_Form_Pengajuan_Aplikasi_List
        ON dbo.Form_Pengajuan_Aplikasi (tgl_pengajuan DESC, status_ticket, created_at DESC);
    CREATE INDEX IX_Form_Pengajuan_Aplikasi_CreatedBy
        ON dbo.Form_Pengajuan_Aplikasi (created_by, created_at DESC);
END;

COMMIT TRANSACTION;
