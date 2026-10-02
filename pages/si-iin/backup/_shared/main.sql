/*
 Navicat Premium Data Transfer

 Source Server         : siin_local
 Source Server Type    : SQLite
 Source Server Version : 3035005 (3.35.5)
 Source Schema         : main

 Target Server Type    : SQLite
 Target Server Version : 3035005 (3.35.5)
 File Encoding         : 65001

 Date: 11/11/2025 11:53:54
*/

PRAGMA foreign_keys = false;

-- ----------------------------
-- Table structure for _siin_ir_item_old_20251111
-- ----------------------------
DROP TABLE IF EXISTS "_siin_ir_item_old_20251111";
CREATE TABLE "_siin_ir_item_old_20251111" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "ir_id" INTEGER NOT NULL,
  "product_id" INTEGER NOT NULL,
  "qty" INTEGER NOT NULL,
  "uom_id" INTEGER,
  "note" TEXT,
  FOREIGN KEY ("ir_id") REFERENCES "siin_ir" ("id") ON DELETE CASCADE ON UPDATE NO ACTION,
  FOREIGN KEY ("product_id") REFERENCES "siin_product" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION,
  FOREIGN KEY ("uom_id") REFERENCES "siin_uom" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION
);

-- ----------------------------
-- Records of _siin_ir_item_old_20251111
-- ----------------------------

-- ----------------------------
-- Table structure for siin_audit_log
-- ----------------------------
DROP TABLE IF EXISTS "siin_audit_log";
CREATE TABLE "siin_audit_log" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "event_time" TEXT NOT NULL,
  "actor" TEXT,
  "action" TEXT NOT NULL,
  "ref_type" TEXT NOT NULL,
  "ref_id" INTEGER,
  "payload_json" TEXT
);

-- ----------------------------
-- Records of siin_audit_log
-- ----------------------------
INSERT INTO "siin_audit_log" VALUES (9, '2025-11-10 14:07:24', 'IT1', 'IR_CREATE', 'siin_ir', 3, '{"mode":"create","id":"","ir_date":"10-11-2025","request_no":"123","descr":"","items":[{"prod_code":"2","prod_name":"2","qty":"2","uom_id":"1","note":""}]}');
INSERT INTO "siin_audit_log" VALUES (10, '2025-11-10 14:11:14', 'IT1', 'HT_CREATE', 'siin_ht', 2, '{"mode":"create","id":"","ht_date":"10-11-2025","ir_no":"123","emp_name":"","dept_name":"","descr":"","prod_code":["2"],"prod_name":["2"],"uom_id":["PCS"],"qty":["1"]}');
INSERT INTO "siin_audit_log" VALUES (11, '2025-11-10 14:16:29', 'IT1', 'HT_APPROVE', 'siin_ht', 2, '{"sign_name":"sidik","sign_image_path":"storage\/esign\/HT-2-20251110_141629.png"}');
INSERT INTO "siin_audit_log" VALUES (12, '2025-11-10 14:41:51', 'IT1', 'HT_UNAPPROVE', 'siin_ht', 2, '[]');
INSERT INTO "siin_audit_log" VALUES (13, '2025-11-10 14:44:55', 'IT1', 'HT_DELETE', 'siin_ht', 2, '[]');
INSERT INTO "siin_audit_log" VALUES (14, '2025-11-10 14:47:19', 'IT1', 'HT_CREATE', 'siin_ht', 3, '{"mode":"create","id":"","ht_date":"10-11-2025","ir_no":"123","emp_name":"","dept_name":"","descr":"","prod_code":["2"],"prod_name":["2"],"uom_id":["1"],"qty":["1"]}');
INSERT INTO "siin_audit_log" VALUES (15, '2025-11-10 14:47:28', 'IT1', 'HT_APPROVE', 'siin_ht', 3, '{"sign_name":"sidik","sign_image_path":"storage\/esign\/HT-3-20251110_144728.png"}');
INSERT INTO "siin_audit_log" VALUES (16, '2025-11-10 14:49:03', 'IT1', 'HT_UNAPPROVE', 'siin_ht', 3, '[]');
INSERT INTO "siin_audit_log" VALUES (17, '2025-11-10 14:49:25', 'IT1', 'HT_APPROVE', 'siin_ht', 3, '{"sign_name":"asd","sign_image_path":"storage\/esign\/HT-3-20251110_144925.png"}');
INSERT INTO "siin_audit_log" VALUES (18, '2025-11-10 14:49:28', 'IT1', 'HT_UNAPPROVE', 'siin_ht', 3, '[]');
INSERT INTO "siin_audit_log" VALUES (19, '2025-11-10 14:49:49', 'IT1', 'HT_APPROVE', 'siin_ht', 3, '{"sign_name":"AS","sign_image_path":"storage\/esign\/HT-3-20251110_144949.png"}');
INSERT INTO "siin_audit_log" VALUES (20, '2025-11-10 14:51:22', 'IT1', 'HT_CREATE', 'siin_ht', 4, '{"mode":"create","id":"","ht_date":"10-11-2025","ir_no":"123","emp_name":"","dept_name":"","descr":"123","prod_code":["2"],"prod_name":["2"],"uom_id":["1"],"qty":["1"]}');
INSERT INTO "siin_audit_log" VALUES (21, '2025-11-10 14:51:34', 'IT1', 'HT_APPROVE', 'siin_ht', 4, '{"sign_name":"asd","sign_image_path":"storage\/esign\/HT-4-20251110_145134.png"}');
INSERT INTO "siin_audit_log" VALUES (22, '2025-11-10 14:53:35', 'IT1', 'HT_UNAPPROVE', 'siin_ht', 3, '[]');
INSERT INTO "siin_audit_log" VALUES (23, '2025-11-10 14:53:52', 'IT1', 'HT_DELETE', 'siin_ht', 3, '[]');
INSERT INTO "siin_audit_log" VALUES (24, '2025-11-10 15:20:17', 'IT1', 'HT_UNAPPROVE', 'siin_ht', 4, '[]');
INSERT INTO "siin_audit_log" VALUES (25, '2025-11-10 15:21:17', 'IT1', 'HT_APPROVE', 'siin_ht', 4, '{"signer":"sidik","esign":"C:\\xampp\\htdocs\\gg_app\\pages\\si-iin\\ht\/..\/..\/..\/esign\/ht_4.png"}');
INSERT INTO "siin_audit_log" VALUES (26, '2025-11-10 15:26:22', 'IT1', 'HT_UNAPPROVE', 'siin_ht', 4, '[]');
INSERT INTO "siin_audit_log" VALUES (27, '2025-11-10 15:46:28', 'IT1', 'HT_APPROVE', 'siin_ht', 4, '{"signer":"sidik","esign":"C:\\xampp\\htdocs\\gg_app\\pages\\si-iin\\ht\/..\/..\/..\/esign\/ht_4.png"}');
INSERT INTO "siin_audit_log" VALUES (28, '2025-11-10 16:25:03', 'IT1', 'HT_UNAPPROVE', 'siin_ht', 4, '[]');
INSERT INTO "siin_audit_log" VALUES (29, '2025-11-11 10:21:53', 'IT1', 'IR_DELETE', 'siin_ir', 3, '{"request_no":"123"}');
INSERT INTO "siin_audit_log" VALUES (30, '2025-11-11 10:22:19', 'IT1', 'IR_CREATE', 'siin_ir', 4, '{"mode":"create","id":"","ir_date":"11-11-2025","request_no":"asd","descr":"","items":[{"prod_code":"1","prod_name":"1","qty":"4","uom_id":"1","note":""}]}');
INSERT INTO "siin_audit_log" VALUES (31, '2025-11-11 10:22:48', 'IT1', 'IR_UPDATE', 'siin_ir', 4, '{"mode":"edit","id":"4","ir_date":"11-11-2025","request_no":"asd","descr":"","items":[{"prod_code":"1","prod_name":"1","qty":"4","uom_id":"1","note":""},{"prod_code":"2","prod_name":"2","qty":"1","uom_id":"1","note":""}]}');
INSERT INTO "siin_audit_log" VALUES (32, '2025-11-11 10:52:32', 'IT1', 'IR_DELETE', 'siin_ir', 4, '{"request_no":"asd"}');
INSERT INTO "siin_audit_log" VALUES (33, '2025-11-11 10:52:46', 'IT1', 'IR_CREATE', 'siin_ir', 5, '{"mode":"create","id":"","ir_date":"11-11-2025","request_no":"123","descr":"","items":[{"prod_code":"2","prod_name":"2","qty":"2","uom_id":"1","note":""}]}');
INSERT INTO "siin_audit_log" VALUES (34, '2025-11-11 11:07:12', 'IT1', 'IR_UPDATE', 'siin_ir', 5, '{"mode":"edit","id":"5","ir_date":"11-11-2025","request_no":"123","descr":"","items":[{"prod_code":"2","prod_name":"2","qty":"2","uom_id":"1","note":""},{"prod_code":"1","prod_name":"1","qty":"1","uom_id":"1","note":""}]}');

-- ----------------------------
-- Table structure for siin_category
-- ----------------------------
DROP TABLE IF EXISTS "siin_category";
CREATE TABLE "siin_category" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  UNIQUE ("name" ASC)
);

-- ----------------------------
-- Records of siin_category
-- ----------------------------
INSERT INTO "siin_category" VALUES (1, 'perlengkapan printer');
INSERT INTO "siin_category" VALUES (2, 'perlengkapan PC');

-- ----------------------------
-- Table structure for siin_ht
-- ----------------------------
DROP TABLE IF EXISTS "siin_ht";
CREATE TABLE "siin_ht" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "ht_date" TEXT NOT NULL,
  "ir_no" TEXT NOT NULL,
  "emp_name" TEXT,
  "dept_name" TEXT,
  "descr" TEXT,
  "status" TEXT NOT NULL DEFAULT 'open',
  "sign_name" TEXT,
  "sign_image_path" TEXT,
  "upddate" TEXT NOT NULL,
  "upduser" TEXT,
  "no_grn" TEXT
);

-- ----------------------------
-- Records of siin_ht
-- ----------------------------

-- ----------------------------
-- Table structure for siin_ht_item
-- ----------------------------
DROP TABLE IF EXISTS "siin_ht_item";
CREATE TABLE "siin_ht_item" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "ht_id" INTEGER NOT NULL,
  "product_id" INTEGER NOT NULL,
  "qty" INTEGER NOT NULL,
  "uom_id" INTEGER,
  FOREIGN KEY ("ht_id") REFERENCES "siin_ht" ("id") ON DELETE CASCADE ON UPDATE NO ACTION,
  FOREIGN KEY ("product_id") REFERENCES "siin_product" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION,
  FOREIGN KEY ("uom_id") REFERENCES "siin_uom" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION
);

-- ----------------------------
-- Records of siin_ht_item
-- ----------------------------

-- ----------------------------
-- Table structure for siin_ir
-- ----------------------------
DROP TABLE IF EXISTS "siin_ir";
CREATE TABLE "siin_ir" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "ir_date" TEXT NOT NULL,
  "request_no" TEXT NOT NULL,
  "descr" TEXT,
  "status" TEXT NOT NULL DEFAULT 'Approved',
  "upddate" TEXT NOT NULL,
  "upduser" TEXT,
  UNIQUE ("request_no" ASC)
);

-- ----------------------------
-- Records of siin_ir
-- ----------------------------
INSERT INTO "siin_ir" VALUES (5, '2025-11-11', '123', '', 'Approved', '2025-11-11 11:07:12', 'IT1');

-- ----------------------------
-- Table structure for siin_ir_item
-- ----------------------------
DROP TABLE IF EXISTS "siin_ir_item";
CREATE TABLE "siin_ir_item" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "ir_id" INTEGER NOT NULL,
  "product_id" INTEGER NOT NULL,
  "qty" INTEGER NOT NULL,
  "uom_id" INTEGER,
  "note" TEXT,
  "qty_out" INTEGER,
  FOREIGN KEY ("ir_id") REFERENCES "siin_ir" ("id") ON DELETE CASCADE ON UPDATE NO ACTION,
  FOREIGN KEY ("product_id") REFERENCES "siin_product" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION,
  FOREIGN KEY ("uom_id") REFERENCES "siin_uom" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION
);

-- ----------------------------
-- Records of siin_ir_item
-- ----------------------------
INSERT INTO "siin_ir_item" VALUES (8, 5, 2, 2, 1, '', NULL);
INSERT INTO "siin_ir_item" VALUES (9, 5, 1, 1, 1, '', NULL);

-- ----------------------------
-- Table structure for siin_product
-- ----------------------------
DROP TABLE IF EXISTS "siin_product";
CREATE TABLE "siin_product" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "prod_code" TEXT,
  "prod_name" TEXT NOT NULL,
  "category_id" INTEGER,
  "uom_id" INTEGER,
  "stock" INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY ("category_id") REFERENCES "siin_category" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION,
  FOREIGN KEY ("uom_id") REFERENCES "siin_uom" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION,
  UNIQUE ("prod_code" ASC)
);

-- ----------------------------
-- Records of siin_product
-- ----------------------------
INSERT INTO "siin_product" VALUES (1, 'TNT-001', 'tinta', 1, 1, 11);
INSERT INTO "siin_product" VALUES (2, 'RAM-001', 'ram', 2, 1, 4);

-- ----------------------------
-- Table structure for siin_stock_ledger
-- ----------------------------
DROP TABLE IF EXISTS "siin_stock_ledger";
CREATE TABLE "siin_stock_ledger" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "trx_time" TEXT NOT NULL,
  "product_id" INTEGER NOT NULL,
  "qty_in" INTEGER NOT NULL DEFAULT 0,
  "qty_out" INTEGER NOT NULL DEFAULT 0,
  "balance_after" INTEGER NOT NULL,
  "ref_type" TEXT NOT NULL,
  "ref_id" INTEGER,
  "note" TEXT,
  "upduser" TEXT,
  FOREIGN KEY ("product_id") REFERENCES "siin_product" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION
);

-- ----------------------------
-- Records of siin_stock_ledger
-- ----------------------------
INSERT INTO "siin_stock_ledger" VALUES (7, '2025-11-10 14:07:24', 2, 2, 0, 7, 'IR', 3, 'IR 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (8, '2025-11-10 14:16:29', 2, 0, 1, 6, 'HT', 2, 'HT 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (9, '2025-11-10 14:47:28', 2, 0, 1, 5, 'HT', 3, 'HT 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (10, '2025-11-10 14:49:03', 2, 1, 0, 6, 'HT_UNAPPROVE', 3, 'UNAPPROVE HT 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (11, '2025-11-10 14:49:25', 2, 0, 1, 5, 'HT', 3, 'HT 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (12, '2025-11-10 14:49:49', 2, 0, 1, 4, 'HT', 3, 'HT 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (13, '2025-11-10 14:51:34', 2, 0, 1, 3, 'HT', 4, 'HT 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (14, '2025-11-11 10:21:53', 2, 0, 3, 2, 'IR', 3, 'DEL IR 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (15, '2025-11-11 10:22:19', 1, 4, 0, 14, 'IR', 4, 'IR asd', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (16, '2025-11-11 10:22:48', 2, 1, 0, 3, 'IR', 4, 'ADJ IR asd', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (17, '2025-11-11 10:52:32', 1, 0, 4, 10, 'IR', 4, 'DEL IR asd', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (18, '2025-11-11 10:52:32', 2, 0, 1, 2, 'IR', 4, 'DEL IR asd', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (19, '2025-11-11 10:52:46', 2, 2, 0, 4, 'IR', 5, 'IR 123', 'IT1');
INSERT INTO "siin_stock_ledger" VALUES (20, '2025-11-11 11:07:12', 1, 1, 0, 11, 'IR', 5, 'ADJ IR 123', 'IT1');

-- ----------------------------
-- Table structure for siin_uom
-- ----------------------------
DROP TABLE IF EXISTS "siin_uom";
CREATE TABLE "siin_uom" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "uomname" TEXT NOT NULL,
  UNIQUE ("uomname" ASC)
);

-- ----------------------------
-- Records of siin_uom
-- ----------------------------
INSERT INTO "siin_uom" VALUES (1, 'PCS');
INSERT INTO "siin_uom" VALUES (2, 'SET');

-- ----------------------------
-- Table structure for sqlite_sequence
-- ----------------------------
DROP TABLE IF EXISTS "sqlite_sequence";
CREATE TABLE "sqlite_sequence" (
  "name",
  "seq"
);

-- ----------------------------
-- Records of sqlite_sequence
-- ----------------------------
INSERT INTO "sqlite_sequence" VALUES ('siin_uom', 2);
INSERT INTO "sqlite_sequence" VALUES ('siin_category', 2);
INSERT INTO "sqlite_sequence" VALUES ('siin_product', 3);
INSERT INTO "sqlite_sequence" VALUES ('siin_ir', 5);
INSERT INTO "sqlite_sequence" VALUES ('_siin_ir_item_old_20251111', 3);
INSERT INTO "sqlite_sequence" VALUES ('siin_stock_ledger', 20);
INSERT INTO "sqlite_sequence" VALUES ('siin_audit_log', 34);
INSERT INTO "sqlite_sequence" VALUES ('siin_ht', 4);
INSERT INTO "sqlite_sequence" VALUES ('siin_ht_item', 4);
INSERT INTO "sqlite_sequence" VALUES ('siin_ir_item', 9);

-- ----------------------------
-- Auto increment value for _siin_ir_item_old_20251111
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 3 WHERE name = '_siin_ir_item_old_20251111';

-- ----------------------------
-- Auto increment value for siin_audit_log
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 34 WHERE name = 'siin_audit_log';

-- ----------------------------
-- Auto increment value for siin_category
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 2 WHERE name = 'siin_category';

-- ----------------------------
-- Auto increment value for siin_ht
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 4 WHERE name = 'siin_ht';

-- ----------------------------
-- Indexes structure for table siin_ht
-- ----------------------------
CREATE INDEX "idx_ht_irno"
ON "siin_ht" (
  "ir_no" ASC
);
CREATE INDEX "idx_ht_status"
ON "siin_ht" (
  "status" ASC
);

-- ----------------------------
-- Auto increment value for siin_ht_item
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 4 WHERE name = 'siin_ht_item';

-- ----------------------------
-- Auto increment value for siin_ir
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 5 WHERE name = 'siin_ir';

-- ----------------------------
-- Indexes structure for table siin_ir
-- ----------------------------
CREATE INDEX "idx_ir_reqno"
ON "siin_ir" (
  "request_no" ASC
);
CREATE INDEX "idx_ir_status"
ON "siin_ir" (
  "status" ASC
);

-- ----------------------------
-- Auto increment value for siin_ir_item
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 9 WHERE name = 'siin_ir_item';

-- ----------------------------
-- Auto increment value for siin_product
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 3 WHERE name = 'siin_product';

-- ----------------------------
-- Indexes structure for table siin_product
-- ----------------------------
CREATE INDEX "idx_product_code"
ON "siin_product" (
  "prod_code" ASC
);

-- ----------------------------
-- Auto increment value for siin_stock_ledger
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 20 WHERE name = 'siin_stock_ledger';

-- ----------------------------
-- Indexes structure for table siin_stock_ledger
-- ----------------------------
CREATE INDEX "idx_ledger_product"
ON "siin_stock_ledger" (
  "product_id" ASC
);
CREATE INDEX "idx_ledger_ref"
ON "siin_stock_ledger" (
  "ref_type" ASC,
  "ref_id" ASC
);

-- ----------------------------
-- Auto increment value for siin_uom
-- ----------------------------
UPDATE "sqlite_sequence" SET seq = 2 WHERE name = 'siin_uom';

PRAGMA foreign_keys = true;
