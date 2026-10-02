<?php
session_start();
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['UserName'])) { http_response_code(401); exit('Unauthorized'); }

$queries = [];
$queries[] = "IF OBJECT_ID('dbo.resep_obat_experiment_group', 'U') IS NULL
CREATE TABLE dbo.resep_obat_experiment_group (
    id INT IDENTITY(1,1) PRIMARY KEY,
    soi VARCHAR(100) NULL,
    no_cp VARCHAR(50) NULL,
    kode_grey VARCHAR(50) NULL,
    mesin VARCHAR(100) NULL,
    kode_warna VARCHAR(50) NULL,
    color_name VARCHAR(150) NULL,
    color_desc VARCHAR(MAX) NULL,
    resep_prod_code VARCHAR(80) NULL,
    resep_prod_name VARCHAR(200) NULL,
    cus_color VARCHAR(100) NULL,
    proint_resephdid INT NULL,
    group_status VARCHAR(30) NOT NULL DEFAULT 'Draft',
    approved_experiment_id INT NULL,
    approved_at DATETIME NULL,
    approved_by VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    created_by VARCHAR(80) NULL,
    updated_at DATETIME NULL,
    updated_by VARCHAR(80) NULL
)";
$queries[] = "IF OBJECT_ID('dbo.resep_obat_experiment', 'U') IS NULL
CREATE TABLE dbo.resep_obat_experiment (
    id INT IDENTITY(1,1) PRIMARY KEY,
    group_id INT NULL,
    experiment_seq INT NOT NULL DEFAULT 1,
    experiment_status VARCHAR(30) NOT NULL DEFAULT 'Draft',
    experiment_note VARCHAR(MAX) NULL,
    no_cp VARCHAR(50) NULL,
    kode_grey VARCHAR(50) NULL,
    mesin VARCHAR(100) NULL,
    kode_warna VARCHAR(50) NULL,
    color_name VARCHAR(150) NULL,
    color_desc VARCHAR(MAX) NULL,
    resep_prod_code VARCHAR(80) NULL,
    resep_prod_name VARCHAR(200) NULL,
    cus_color VARCHAR(100) NULL,
    proint_resephdid INT NULL,
    lot_no VARCHAR(50) NULL,
    weight DECIMAL(18,4) NULL,
    plan_qty DECIMAL(18,4) NULL,
    vlot DECIMAL(18,4) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    created_by VARCHAR(80) NULL,
    updated_at DATETIME NULL,
    updated_by VARCHAR(80) NULL
)";
$queries[] = "IF OBJECT_ID('dbo.resep_obat_experiment_detail', 'U') IS NULL
CREATE TABLE dbo.resep_obat_experiment_detail (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_resep_experiment INT NOT NULL,
    kode VARCHAR(80) NULL,
    name VARCHAR(200) NULL,
    category VARCHAR(100) NULL,
    receipe DECIMAL(18,4) NULL,
    uom VARCHAR(30) NULL,
    cf DECIMAL(18,4) NULL,
    uom_cf VARCHAR(30) NULL,
    std_price DECIMAL(18,4) NULL,
    total DECIMAL(18,4) NULL,
    price_satuan VARCHAR(30) NULL,
    price_source VARCHAR(30) NULL,
    is_manual BIT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    created_by VARCHAR(80) NULL,
    updated_at DATETIME NULL,
    updated_by VARCHAR(80) NULL
)";
$queries[] = "IF OBJECT_ID('dbo.resep_obat_experiment_lab_param', 'U') IS NULL
CREATE TABLE dbo.resep_obat_experiment_lab_param (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_resep_experiment INT NOT NULL,
    machine_code VARCHAR(50) NULL,
    machine_name VARCHAR(200) NULL,
    infra_red DECIMAL(18,4) NULL,
    tekanan_padder DECIMAL(18,4) NULL,
    wpu DECIMAL(18,4) NULL,
    speed DECIMAL(18,4) NULL,
    fan1 DECIMAL(18,4) NULL,
    fan2 DECIMAL(18,4) NULL,
    temp_chamber_1 DECIMAL(18,4) NULL,
    temp_chamber_1_time DECIMAL(18,4) NULL,
    temp_chamber_2 DECIMAL(18,4) NULL,
    temp_chamber_2_time DECIMAL(18,4) NULL,
    lainnya VARCHAR(MAX) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    created_by VARCHAR(80) NULL,
    updated_at DATETIME NULL,
    updated_by VARCHAR(80) NULL
)";
$queries[] = "IF OBJECT_ID('dbo.resep_obat_experiment_lab_data', 'U') IS NULL
CREATE TABLE dbo.resep_obat_experiment_lab_data (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_resep_experiment INT NOT NULL,
    delta_l DECIMAL(18,4) NULL,
    delta_a DECIMAL(18,4) NULL,
    delta_b DECIMAL(18,4) NULL,
    delta_e DECIMAL(18,4) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    created_by VARCHAR(80) NULL,
    updated_at DATETIME NULL,
    updated_by VARCHAR(80) NULL
)";

$alterColumns = [
    ['resep_obat_experiment','group_id','INT NULL'],
    ['resep_obat_experiment','experiment_seq','INT NOT NULL CONSTRAINT DF_resep_obat_experiment_seq DEFAULT 1'],
    ['resep_obat_experiment','experiment_status',"VARCHAR(30) NOT NULL CONSTRAINT DF_resep_obat_experiment_status DEFAULT 'Draft'"],
    ['resep_obat_experiment','experiment_note','VARCHAR(MAX) NULL'],
    ['resep_obat_experiment_group','soi','VARCHAR(100) NULL'],
    ['master_mesin_lab','infra_red','DECIMAL(18,4) NULL'],
    ['master_mesin_lab','tekanan_padder','DECIMAL(18,4) NULL'],
    ['master_mesin_lab','wpu','DECIMAL(18,4) NULL'],
    ['master_mesin_lab','speed','DECIMAL(18,4) NULL'],
    ['master_mesin_lab','fan1','DECIMAL(18,4) NULL'],
    ['master_mesin_lab','fan2','DECIMAL(18,4) NULL'],
    ['master_mesin_lab','temp_chamber_1','DECIMAL(18,4) NULL'],
    ['master_mesin_lab','temp_chamber_2','DECIMAL(18,4) NULL'],
    ['resep_obat_experiment_lab_param','temp_chamber_1_time','DECIMAL(18,4) NULL'],
    ['resep_obat_experiment_lab_param','temp_chamber_2_time','DECIMAL(18,4) NULL'],
];
foreach ($alterColumns as $c) {
    $queries[] = "IF COL_LENGTH('dbo.{$c[0]}', '{$c[1]}') IS NULL ALTER TABLE dbo.{$c[0]} ADD {$c[1]} {$c[2]}";
}
$queries[] = "IF COL_LENGTH('dbo.resep_obat_experiment', 'no_cp') IS NOT NULL AND EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.resep_obat_experiment') AND name = 'no_cp' AND is_nullable = 0) ALTER TABLE dbo.resep_obat_experiment ALTER COLUMN no_cp VARCHAR(50) NULL";

foreach ($queries as $sql) {
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) { print_r(sqlsrv_errors()); exit; }
}

echo "Tabel Group Experiment, Experiment, dan Detail siap.";
