<?php
// koneksi_sqlite.php - Koneksi SQLite + init schema + migrasi ringan + seed
date_default_timezone_set('Asia/Jakarta');

$dbFile   = __DIR__ . '/siin_local.db';
$needInit = !file_exists($dbFile);

try {
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die('Gagal membuka SQLite: ' . $e->getMessage());
}

// Foreign keys
$pdo->exec('PRAGMA foreign_keys = ON;');

// ===================================
// 1) INIT (jika DB baru dibuat)
// ===================================
if ($needInit) {
    // MASTER
    $pdo->exec("
        CREATE TABLE siin_uom (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          uomname TEXT UNIQUE NOT NULL
        );
        CREATE TABLE siin_category (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name TEXT UNIQUE NOT NULL
        );
        CREATE TABLE siin_product (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          prod_code TEXT UNIQUE,
          prod_name TEXT NOT NULL,
          category_id INTEGER,
          uom_id INTEGER,
          stock INTEGER NOT NULL DEFAULT 0,
          FOREIGN KEY (category_id) REFERENCES siin_category(id),
          FOREIGN KEY (uom_id) REFERENCES siin_uom(id)
        );
    ");

    // IR
    $pdo->exec("
        CREATE TABLE siin_ir (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          ir_date    TEXT NOT NULL,          -- YYYY-MM-DD
          request_no TEXT UNIQUE NOT NULL,
          descr      TEXT,
          status     TEXT NOT NULL DEFAULT 'Approved', -- open|Approved|outstanding|closed
          upddate    TEXT NOT NULL,
          upduser    TEXT
        );
        CREATE TABLE siin_ir_item (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          ir_id      INTEGER NOT NULL,
          product_id INTEGER NOT NULL,
          qty        INTEGER NOT NULL,
          uom_id     INTEGER,
          note       TEXT,
          FOREIGN KEY (ir_id) REFERENCES siin_ir(id) ON DELETE CASCADE,
          FOREIGN KEY (product_id) REFERENCES siin_product(id),
          FOREIGN KEY (uom_id) REFERENCES siin_uom(id)
        );
    ");

    // HT
    $pdo->exec("
        CREATE TABLE siin_ht (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          ht_date  TEXT NOT NULL,  -- YYYY-MM-DD
          ir_no    TEXT NOT NULL,  -- refer ke siin_ir.request_no
          emp_name TEXT,
          dept_name TEXT,
          descr    TEXT,
          status   TEXT NOT NULL DEFAULT 'open', -- open|Approved|unApproved
          sign_name TEXT,
          sign_image_path TEXT, -- simpan path file di disk
          upddate  TEXT NOT NULL,
          upduser  TEXT
        );
        CREATE TABLE siin_ht_item (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          ht_id     INTEGER NOT NULL,
          product_id INTEGER NOT NULL,
          qty        INTEGER NOT NULL,
          uom_id     INTEGER,
          FOREIGN KEY (ht_id) REFERENCES siin_ht(id) ON DELETE CASCADE,
          FOREIGN KEY (product_id) REFERENCES siin_product(id),
          FOREIGN KEY (uom_id) REFERENCES siin_uom(id)
        );
    ");

    // LOGS
    $pdo->exec("
        CREATE TABLE siin_stock_ledger (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          trx_time       TEXT NOT NULL,
          product_id     INTEGER NOT NULL,
          qty_in         INTEGER NOT NULL DEFAULT 0,
          qty_out        INTEGER NOT NULL DEFAULT 0,
          balance_after  INTEGER NOT NULL,
          ref_type       TEXT NOT NULL,  -- IR|HT|HT_UNAPPROVE|MANUAL
          ref_id         INTEGER,
          note           TEXT,
          upduser        TEXT,
          FOREIGN KEY (product_id) REFERENCES siin_product(id)
        );
        CREATE TABLE siin_audit_log (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          event_time TEXT NOT NULL,
          actor      TEXT,
          action     TEXT NOT NULL,   -- IR_CREATE|IR_UPDATE|HT_CREATE|HT_APPROVE|...
          ref_type   TEXT NOT NULL,   -- gunakan 'siin_ir' / 'siin_ht'
          ref_id     INTEGER,
          payload_json TEXT
        );
    ");

    // INDEXES
    $pdo->exec("
        CREATE INDEX idx_product_code   ON siin_product(prod_code);
        CREATE INDEX idx_ir_reqno       ON siin_ir(request_no);
        CREATE INDEX idx_ir_status      ON siin_ir(status);
        CREATE INDEX idx_ht_irno        ON siin_ht(ir_no);
        CREATE INDEX idx_ht_status      ON siin_ht(status);
        CREATE INDEX idx_ledger_ref     ON siin_stock_ledger(ref_type, ref_id);
        CREATE INDEX idx_ledger_product ON siin_stock_ledger(product_id);
    ");

    // SEED
    $pdo->exec("
        INSERT INTO siin_uom(uomname) VALUES ('PCS'), ('SET');
        INSERT INTO siin_category(name) VALUES ('perlengkapan printer'), ('perlengkapan PC');
        INSERT INTO siin_product(prod_code, prod_name, category_id, uom_id, stock)
        VALUES
          ('TNT-001','tinta',
            (SELECT id FROM siin_category WHERE name='perlengkapan printer'),
            (SELECT id FROM siin_uom WHERE uomname='PCS'), 10),
          ('RAM-001','ram',
            (SELECT id FROM siin_category WHERE name='perlengkapan PC'),
            (SELECT id FROM siin_uom WHERE uomname='PCS'), 5);
    ");
}

// ===================================
// 2) MIGRASI RINGAN
// ===================================

// a) pastikan kolom status di IR ada
$cols = $pdo->query("PRAGMA table_info('siin_ir')")->fetchAll();
$hasStatus=false;
foreach($cols as $c){ if(strcasecmp($c['name'],'status')===0){ $hasStatus=true; break; } }
if(!$hasStatus){
    $pdo->exec("ALTER TABLE siin_ir ADD COLUMN status TEXT NOT NULL DEFAULT 'Approved'");
    $pdo->exec("UPDATE siin_ir SET status='Approved' WHERE status IS NULL OR TRIM(status)=''");
}

// b) kolom tanda tangan (path+name) di HT
$cols = $pdo->query("PRAGMA table_info('siin_ht')")->fetchAll();
$hasSignName=false;$hasSignPath=false;
foreach($cols as $c){
  if(strcasecmp($c['name'],'sign_name')===0) $hasSignName=true;
  if(strcasecmp($c['name'],'sign_image_path')===0) $hasSignPath=true;
}
if(!$hasSignName) $pdo->exec("ALTER TABLE siin_ht ADD COLUMN sign_name TEXT");
if(!$hasSignPath) $pdo->exec("ALTER TABLE siin_ht ADD COLUMN sign_image_path TEXT");

// c) index penting
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ir_reqno    ON siin_ir(request_no)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ir_status   ON siin_ir(status)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ht_irno     ON siin_ht(ir_no)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ht_status   ON siin_ht(status)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ledger_ref  ON siin_stock_ledger(ref_type, ref_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_code ON siin_product(prod_code)");
