-- create_tables_v2.sql
IF OBJECT_ID('dbo.resep_obat_v2', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.resep_obat_v2 (
        id INT IDENTITY(1,1) PRIMARY KEY,
        no_cp VARCHAR(50) NULL,
        kode_warna VARCHAR(50) NULL,
        lot_no VARCHAR(50) NULL,
        weight DECIMAL(18, 2) NULL,
        plan_qty DECIMAL(18, 2) NULL,
        created_at DATETIME NULL DEFAULT GETDATE(),
        created_by VARCHAR(50) NULL,
        updated_at DATETIME NULL,
        updated_by VARCHAR(50) NULL,
        color_name VARCHAR(100) NULL,
        color_desc VARCHAR(255) NULL,
        kode_grey VARCHAR(50) NULL,
        vlot DECIMAL(10, 2) NULL,
        resep_prod_code VARCHAR(100) NULL,
        resep_prod_name VARCHAR(255) NULL,
        resep_no VARCHAR(50) NULL,
        resep_seq INT NULL,
        resep_date DATETIME NULL,
        resep_type VARCHAR(50) NULL,
        is_manual INT NULL DEFAULT 0,
        no_cp_resep VARCHAR(50) NULL,
        no_so VARCHAR(50) NULL,
        rtg_code VARCHAR(255) NULL,
        rtg_name VARCHAR(255) NULL,
        status_desc VARCHAR(100) NULL,
        cus_color VARCHAR(255) NULL,
        speed DECIMAL(18, 2) NULL,
        temperature DECIMAL(18, 2) NULL,
        nilai_l DECIMAL(18, 4) NULL,
        nilai_a DECIMAL(18, 4) NULL,
        nilai_b DECIMAL(18, 4) NULL,
        lampiran_path VARCHAR(255) NULL,
        lampiran_pdf_path VARCHAR(500) NULL,
        machine_code VARCHAR(50) NULL,
        machine_name VARCHAR(255) NULL,
        temperature_ch2 DECIMAL(10, 2) NULL,
        lebar_kain DECIMAL(10, 2) NULL,
        mesin VARCHAR(50) NULL,
        status_resep_lipat VARCHAR(30) NULL,
        proint_resephdid INT NULL,
        source_experiment_id INT NULL
    );
    PRINT 'Table resep_obat_v2 created successfully.';
END
ELSE
BEGIN
    PRINT 'Table resep_obat_v2 already exists.';
END

IF OBJECT_ID('dbo.resep_obat_detail_v2', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.resep_obat_detail_v2 (
        id INT IDENTITY(1,1) PRIMARY KEY,
        id_resep INT NOT NULL,
        table_index INT NULL DEFAULT 1,
        bonno VARCHAR(50) NULL,
        rtg_code VARCHAR(50) NULL,
        rtg_name VARCHAR(100) NULL,
        vlot DECIMAL(18, 2) NULL,
        kode VARCHAR(50) NULL,
        name VARCHAR(100) NULL,
        receipe DECIMAL(18, 3) NULL,
        uom VARCHAR(10) NULL,
        cc VARCHAR(50) NULL,
        std_price DECIMAL(18, 2) NULL,
        total DECIMAL(18, 2) NULL,
        created_at DATETIME NULL DEFAULT GETDATE(),
        created_by VARCHAR(50) NULL,
        updated_at DATETIME NULL,
        updated_by VARCHAR(50) NULL,
        category VARCHAR(100) NULL,
        cf DECIMAL(18, 6) NULL,
        uom_cf VARCHAR(20) NULL,
        price_satuan VARCHAR(20) NULL,
        price_source VARCHAR(20) NULL,
        is_manual INT NULL DEFAULT 0,
        CONSTRAINT FK_resep_detail_v2_header FOREIGN KEY (id_resep) REFERENCES dbo.resep_obat_v2(id) ON DELETE CASCADE
    );
    PRINT 'Table resep_obat_detail_v2 created successfully.';
END
ELSE
BEGIN
    PRINT 'Table resep_obat_detail_v2 already exists.';
END

IF OBJECT_ID('dbo.resep_obat_machines_v2', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.resep_obat_machines_v2 (
        id INT IDENTITY(1,1) PRIMARY KEY,
        id_resep INT NOT NULL,
        machine_code VARCHAR(50) NULL,
        machine_name VARCHAR(255) NULL,
        speed DECIMAL(10, 2) NULL,
        temperature DECIMAL(10, 2) NULL,
        temperature_ch2 DECIMAL(10, 2) NULL,
        temperature_ch3 DECIMAL(10, 2) NULL,
        temperature_ch4 DECIMAL(10, 2) NULL,
        temperature_ch5 DECIMAL(10, 2) NULL,
        temperature_ch6 DECIMAL(10, 2) NULL,
        temperature_ch7 DECIMAL(10, 2) NULL,
        temperature_ch8 DECIMAL(10, 2) NULL,
        temperature_ch9 DECIMAL(10, 2) NULL,
        temperature_ch10 DECIMAL(10, 2) NULL,
        temperature_ch11 DECIMAL(10, 2) NULL,
        temperature_ch12 DECIMAL(10, 2) NULL,
        lebar_kain DECIMAL(10, 2) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        created_by VARCHAR(50) NULL,
        update_at DATETIME NOT NULL DEFAULT GETDATE(),
        update_by VARCHAR(50) NULL,
        CONSTRAINT FK_resep_machines_v2_header FOREIGN KEY (id_resep) REFERENCES dbo.resep_obat_v2(id) ON DELETE CASCADE
    );
    PRINT 'Table resep_obat_machines_v2 created successfully.';
END
ELSE
BEGIN
    PRINT 'Table resep_obat_machines_v2 already exists.';
END
