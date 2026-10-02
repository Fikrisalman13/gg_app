<?php
// Membuat folder penyimpanan jika belum ada
$root = realpath(__DIR__ . '/../../../..'); // gg_app/pages/si-iin/_shared → gg_app
$esignDir = $root . '/storage/esign';
if(!is_dir($esignDir)){
  @mkdir($esignDir, 0755, true);
}
