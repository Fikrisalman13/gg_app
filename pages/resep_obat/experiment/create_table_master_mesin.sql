-- Tabel Master Mesin Lab
CREATE TABLE dbo.master_mesin_lab (
  id INT IDENTITY(1,1) PRIMARY KEY,
  kode_mesin VARCHAR(50) NOT NULL UNIQUE,
  nama_mesin VARCHAR(200) NOT NULL,
  status VARCHAR(20) DEFAULT 'Active', -- Active / Inactive
  infra_red DECIMAL(18,4) NULL,
  tekanan_padder DECIMAL(18,4) NULL,
  wpu DECIMAL(18,4) NULL,
  speed DECIMAL(18,4) NULL,
  fan1 DECIMAL(18,4) NULL,
  fan2 DECIMAL(18,4) NULL,
  temp_chamber_1 DECIMAL(18,4) NULL,
  temp_chamber_2 DECIMAL(18,4) NULL,
  created_at DATETIME DEFAULT GETDATE(),
  created_by VARCHAR(50),
  updated_at DATETIME,
  updated_by VARCHAR(50)
);

-- Index untuk performa pencarian
CREATE INDEX idx_master_mesin_lab_kode ON dbo.master_mesin_lab(kode_mesin);
CREATE INDEX idx_master_mesin_lab_status ON dbo.master_mesin_lab(status);
