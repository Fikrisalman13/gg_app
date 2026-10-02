<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LOAD PHPMailer (MANUAL - TANPA COMPOSER)
|--------------------------------------------------------------------------
| Lokasi:
| pages/dokumen_pengingat/services/EmailService.php
| pages/dokumen_pengingat/libs/PHPMailer/src/
*/
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
|--------------------------------------------------------------------------
| CORE APP FILE
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../includes/app_config.php';

class EmailService
{
    /* =======================
       KONTRAK
    ======================= */
    public static function sendKontrak(array $d): bool
    {
        return self::sendGeneric(
            'Reminder Kontrak',
            [
                'Vendor'       => $d['nama_vendor'] ?? '-',
                'No Kontrak'   => $d['no_kontrak'] ?? '-',
                'Expire Date'  => self::fmtDate($d['expire_date'] ?? null),
            ],
            $d['email_reminder'] ?? ''
        );
    }

    /* =======================
       SERTIFIKAT
    ======================= */
    public static function sendSertifikat(array $d): bool
    {
        return self::sendGeneric(
            'Reminder Sertifikat',
            [
                'Lembaga'       => $d['nama_lembaga'] ?? '-',
                'Sertifikat'    => $d['nama_sertifikat'] ?? '-',
                'No Sertifikat' => $d['no_sertifikat'] ?? '-',
                'Expire Date'   => self::fmtDate($d['expire_date'] ?? null),
            ],
            $d['email_reminder'] ?? ''
        );
    }

    /* =======================
       SURAT KENDARAAN
    ======================= */
    public static function sendKendaraan(array $d): bool
    {
        return self::sendGeneric(
            'Reminder Surat Kendaraan',
            [
                'No Polisi'   => $d['no_polisi'] ?? '-',
                'Kendaraan'   => $d['nama_kendaraan'] ?? '-',
                'Pemilik'     => $d['nama_pemilik'] ?? '-',
                'Expire Date' => self::fmtDate($d['expire_date'] ?? null),
            ],
            $d['email_reminder'] ?? ''
        );
    }

    /* =======================
       CORE EMAIL
    ======================= */
    private static function sendGeneric(string $title, array $rows, string $to): bool
    {
        if ($to === '') {
            return false;
        }

        global $conn;

        $body = "<h3>{$title}</h3><table cellpadding='5'>";
        foreach ($rows as $k => $v) {
            $body .= "<tr>
                        <td><strong>{$k}</strong></td>
                        <td>: " . htmlspecialchars((string)$v) . "</td>
                      </tr>";
        }
        $body .= "</table>
            <p>
                Mohon segera ditindaklanjuti.<br>
                Setelah diperpanjang, silakan update expired date di:<br>
                <a href='http://192.168.7.184:8080/gg_app/login.php'>
                    GG App
                </a>
            </p>";

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = appConfig($conn, 'mail_host');
            $mail->SMTPAuth   = true;
            $mail->Username   = appConfig($conn, 'mail_username');
            $mail->Password   = appConfig($conn, 'mail_password');
            $mail->Port       = (int) appConfig($conn, 'mail_port', '587');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom(
                appConfig($conn, 'mail_from_email'),
                appConfig($conn, 'mail_from_name')
            );

            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $title;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('[EMAIL ERROR] ' . $e->getMessage());
            return false;
        }
    }

    private static function fmtDate($d): string
    {
        if ($d instanceof DateTime) {
            return $d->format('d-m-Y');
        }

        if (!$d) {
            return '-';
        }

        return date('d-m-Y', strtotime((string)$d));
    }
}
