<?php
/**
 * gg_app/pages/si-iin/_shared/boot.php
 * Boot minimal tanpa output. Aman di-include berkali-kali.
 */
if (!defined('SIIN_BOOT')) {
  define('SIIN_BOOT', 1);
  // Waktu & session terserah file pemanggil; jangan session_start() di sini.
  // Pastikan koneksi & helper siap dipakai endpoint belakang layar.
  //require_once __DIR__ . '/koneksi_sqlite.php'; // koneksi + init + migrasi
  require_once __DIR__ . '/ensure_dirs.php';    // pastikan storage/esign tersedia
  require_once __DIR__ . '/siin_lib.php';       // helper (audit, ledger, save_esign, dll.)
  // Tidak ada echo/print di sini agar aman untuk header().
}
