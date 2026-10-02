<?php declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../services/ReminderService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/WhatsappService.php';

$reminder = new ReminderService($conn);
$reminder->run();

echo "CRON OK " . date('Y-m-d H:i:s') . PHP_EOL;


file_put_contents(
    __DIR__ . '/../logs/reminder.log',
    "[" . date('Y-m-d H:i:s') . "] Reminder executed\n",
    FILE_APPEND
);

echo "CRON OK " . date('Y-m-d H:i:s') . PHP_EOL;
