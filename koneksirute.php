<?php
// app/config.php
declare(strict_types=1);

// ---------- DB CONFIG (ubah sesuai lingkunganmu) ----------
const DB_HOST = '192.168.7.11';
const DB_PORT = 1433;
const DB_NAME = 'GG';
const DB_USER = 'sa';
const DB_PASS = 'rahasiaIT2020';

// ---------- No-cache ----------
function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
  header('Expires: 0');
}

// ---------- JSON ----------
function json_out(array $data): never {
  header('Content-Type: application/json; charset=utf-8');
  nocache();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- PDO ----------
function pdo(): PDO {
  $dsn = "sqlsrv:Server=".DB_HOST.",".DB_PORT.";Database=".DB_NAME;
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
