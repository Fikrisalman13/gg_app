-- ================================================================
-- SCRIPT PEMBUATAN TABEL kwh_listrik2 UNTUK MICROSOFT SQL SERVER
-- Database: GG (atau database aktif yang digunakan)
-- ================================================================

USE [GG]
GO

SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[kwh_listrik2]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[kwh_listrik2](
        [id] [int] IDENTITY(1,1) NOT NULL,
        [tanggal] [date] NOT NULL,
        [tarif_per_kwh] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_tarif] DEFAULT ((1252.00)),

        -- 1. IPAB
        [kwh_hari_ini_ipab] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_hi_ipab] DEFAULT ((0)),
        [kwh_kemarin_ipab] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_km_ipab] DEFAULT ((0)),
        [kwh_ipab] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_kwh_ipab] DEFAULT ((0)),
        [biaya_ipab] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_biaya_ipab] DEFAULT ((0)),

        -- 2. JINENG
        [kwh_hari_ini_jineng] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_hi_jineng] DEFAULT ((0)),
        [kwh_kemarin_jineng] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_km_jineng] DEFAULT ((0)),
        [kwh_jineng] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_kwh_jineng] DEFAULT ((0)),
        [biaya_jineng] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_biaya_jineng] DEFAULT ((0)),

        -- 3. XINENG
        [kwh_hari_ini_xineng] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_hi_xineng] DEFAULT ((0)),
        [kwh_kemarin_xineng] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_km_xineng] DEFAULT ((0)),
        [kwh_xineng] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_kwh_xineng] DEFAULT ((0)),
        [biaya_xineng] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_biaya_xineng] DEFAULT ((0)),

        -- 4. 20TON L (20 TON LAMA DAN BARU)
        [kwh_hari_ini_20ton_l] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_hi_20ton_l] DEFAULT ((0)),
        [kwh_kemarin_20ton_l] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_km_20ton_l] DEFAULT ((0)),
        [kwh_20ton_l] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_kwh_20ton_l] DEFAULT ((0)),
        [biaya_20ton_l] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_biaya_20ton_l] DEFAULT ((0)),

        -- 5. (Opsional / Legacy) 20TON BARU
        [kwh_hari_ini_20ton_baru] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_hi_20ton_baru] DEFAULT ((0)),
        [kwh_kemarin_20ton_baru] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_km_20ton_baru] DEFAULT ((0)),
        [kwh_20ton_baru] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_kwh_20ton_baru] DEFAULT ((0)),
        [biaya_20ton_baru] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_biaya_20ton_baru] DEFAULT ((0)),

        -- 6. IPAL
        [kwh_hari_ini_ipal] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_hi_ipal] DEFAULT ((0)),
        [kwh_kemarin_ipal] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_km_ipal] DEFAULT ((0)),
        [kwh_ipal] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_kwh_ipal] DEFAULT ((0)),
        [biaya_ipal] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_biaya_ipal] DEFAULT ((0)),

        -- 7. 21TON ACTOM
        [kwh_hari_ini_21ton_actom] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_hi_21ton_actom] DEFAULT ((0)),
        [kwh_kemarin_21ton_actom] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_km_21ton_actom] DEFAULT ((0)),
        [kwh_21ton_actom] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_kwh_21ton_actom] DEFAULT ((0)),
        [biaya_21ton_actom] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_biaya_21ton_actom] DEFAULT ((0)),

        -- TOTAL DAN METADATA
        [total_kwh] [decimal](18, 3) NULL CONSTRAINT [DF_kwh_listrik2_total_kwh] DEFAULT ((0)),
        [total_biaya] [decimal](18, 2) NULL CONSTRAINT [DF_kwh_listrik2_total_biaya] DEFAULT ((0)),
        [ket] [nvarchar](255) NULL,
        [note] [nvarchar](max) NULL,
        [createby] [varchar](50) NULL,
        [createat] [datetime] NULL CONSTRAINT [DF_kwh_listrik2_createat] DEFAULT (getdate()),
        [updateby] [varchar](50) NULL,
        [updateat] [datetime] NULL,

        CONSTRAINT [PK_kwh_listrik2] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE UNIQUE NONCLUSTERED INDEX [IX_kwh_listrik2_tanggal] ON [dbo].[kwh_listrik2] ([tanggal] ASC);
    
    PRINT 'Tabel [dbo].[kwh_listrik2] berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Tabel [dbo].[kwh_listrik2] sudah ada.';
END
GO
