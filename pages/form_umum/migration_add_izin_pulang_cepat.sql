-- Migration: Form Izin Pulang Cepat (IPC)
-- Jalankan di database GG / database aplikasi.

IF OBJECT_ID('dbo.Form_Umum_Izin_Pulang_Cepat', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Form_Umum_Izin_Pulang_Cepat (
        id INT IDENTITY(1,1) PRIMARY KEY,
        ticket VARCHAR(50) NOT NULL UNIQUE,
        nik VARCHAR(50) NOT NULL,
        nama_pemohon VARCHAR(150) NOT NULL,
        departemen VARCHAR(150) NULL,
        bagian VARCHAR(150) NULL,
        jabatan VARCHAR(150) NULL,
        no_hp VARCHAR(50) NULL,
        tgl_pengajuan DATE NOT NULL,
        tanggal DATE NOT NULL,
        jam_pulang_normal VARCHAR(8) NOT NULL DEFAULT '16:15',
        jam_pulang_diminta VARCHAR(8) NOT NULL,
        alasan VARCHAR(100) NOT NULL,
        alasan_lain NVARCHAR(255) NULL,
        attachment_filename NVARCHAR(255) NULL,
        attachment_path NVARCHAR(500) NULL,
        attachment_size BIGINT NULL,
        attachment_uploaded_at DATETIME NULL,
        status_ticket VARCHAR(50) NOT NULL DEFAULT 'Pending',
        status_keluar VARCHAR(50) NOT NULL DEFAULT 'Belum Keluar',
        jam_keluar_real VARCHAR(8) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        created_by VARCHAR(150) NULL,
        updated_at DATETIME NULL,
        updated_by VARCHAR(150) NULL,
        rejected_by VARCHAR(150) NULL,
        rejection_reason NVARCHAR(MAX) NULL,
        rejection_date DATETIME NULL
    );
    PRINT 'Table Form_Umum_Izin_Pulang_Cepat created successfully.';
END
ELSE
BEGIN
    PRINT 'Table Form_Umum_Izin_Pulang_Cepat already exists.';
END
