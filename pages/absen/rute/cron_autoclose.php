<?php
/**
 * /gg_app/pages/rute/cron_autoclose.php
 * Jalankan via cron / Task Scheduler untuk auto-close rute overdue.
 */

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ====== KONFIG (via ENV lebih aman) ======
$ENDPOINT = getenv('AUTO_CLOSE_ENDPOINT') ?: 'https://your-domain/gg_app/pages/rute/api1.php';
$CRON_KEY = getenv('AUTO_CLOSE_KEY') ?: ''; // wajib diisi lewat ENV
$INSECURE = (getenv('CRON_INSECURE_SSL') === '1'); // set 1 hanya jika pakai self-signed cert

if ($CRON_KEY === '') {
  fwrite(STDERR, "[ERR] ENV AUTO_CLOSE_KEY belum diset\n");
  exit(2);
}

// ====== LOCK (hindari overlap run) ======
$lockFile = sys_get_temp_dir() . '/cron_autoclose_' . substr(sha1($ENDPOINT),0,8) . '.lock';
$fp = @fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
  // proses lain sedang jalan
  exit(0);
}

// ====== Request ke endpoint ======
$payload = http_build_query([
  'action'   => 'autoclose_overdue',
  'cron_key' => $CRON_KEY,
], '', '&');

$ch = curl_init($ENDPOINT);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT        => 60,
  CURLOPT_USERAGENT      => 'gg-autoclose-cron/1.0',
  CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);

if ($INSECURE) {
  // gunakan hanya jika benar-benar perlu (self-signed)
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}

$resp = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ====== Evaluasi hasil ======
$now = date('Y-m-d H:i:s');

if ($err || $code !== 200) {
  file_put_contents('php://stderr', "[$now] FAIL http:$code err:$err resp:$resp\n");
  exit(1);
}

$data = json_decode($resp, true);
if (!is_array($data)) {
  file_put_contents('php://stderr', "[$now] FAIL invalid JSON resp:$resp\n");
  exit(1);
}
if (empty($data['success'])) {
  $msg = isset($data['error']) ? (string)$data['error'] : 'unknown';
  file_put_contents('php://stderr', "[$now] FAIL api error: $msg resp:$resp\n");
  exit(1);
}

$closed = isset($data['closedIds']) && is_array($data['closedIds']) ? count($data['closedIds']) : 0;
echo "[$now] OK closed {$closed} route(s)\n";
