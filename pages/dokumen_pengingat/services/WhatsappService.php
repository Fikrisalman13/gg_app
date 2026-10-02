<?php
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../includes/app_config.php';

class WhatsappService
{
    public static function send(string $phone, string $message): bool
    {
        global $conn;

        $phone = self::formatPhone($phone);

        $ch = curl_init(appConfig($conn, 'wa_api_url'));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [ 
                'Authorization: ' . trim(appConfig($conn, 'wa_api_key')),
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'target'  => $phone,
                'message' => $message,
                'delay'   => '2', // Tambahkan delay agar tidak dianggap spam
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Hindari masalah SSL certificate di server lokal
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        
        // Log untuk debug (bisa dicek di folder logs)
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents(
            $logDir . '/wa_debug.log',
            "[" . date('Y-m-d H:i:s') . "] Target: $phone | Status: $status | Res: $response | Err: $error\n",
            FILE_APPEND
        );

        // Fonnte biasanya mengembalikan JSON dengan field "status" true/false
        $resObj = json_decode((string)$response, true);
        return $status === 200 && ($resObj['status'] ?? false) === true;
    }

    private static function formatPhone(string $p): string
    {
        $p = preg_replace('/[^0-9]/', '', $p);
        if (str_starts_with($p, '0')) {
            $p = '62' . substr($p, 1);
        }
        return $p;
    }
}
