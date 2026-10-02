<?php
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/WhatsappService.php';

class ReminderService
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function run(): void
    {
        $this->reminderKontrak();
        $this->reminderSertifikat();
        $this->reminderKendaraan();
        $this->reminderDynamic();
    }

    private function reminderDynamic(): void
    {
        // Ambil semua kategori dinamis beserta intervalnya
        $stmtCats = sqlsrv_query($this->conn, "SELECT id, category_name, reminder_interval FROM dr_categories");
        if (!$stmtCats) return;

        while ($cat = sqlsrv_fetch_array($stmtCats, SQLSRV_FETCH_ASSOC)) {
            $catId = $cat['id'];
            $interval = (int)$cat['reminder_interval'];
            $catName = strtoupper($cat['category_name']);

            // Cari dokumen yang mendekati masa expire sesuai interval kategori masing-masing
            $stmtDocs = sqlsrv_query(
                $this->conn,
                "SELECT * FROM dr_documents 
                 WHERE category_id = ? 
                   AND expire_date IS NOT NULL
                   AND DATEDIFF(DAY, CAST(GETDATE() AS DATE), CAST(expire_date AS DATE)) <= ?
                   AND expire_date >= CAST(GETDATE() AS DATE)",
                [$catId, $interval]
            );

            while ($r = sqlsrv_fetch_array($stmtDocs, SQLSRV_FETCH_ASSOC)) {
                $docId = (int)$r['id'];
                
                // Cegah pengiriman dobel di hari yang sama
                if ($this->alreadySent('dynamic_'.$catId, $docId)) {
                    continue;
                }

                // Cek karyawan aktif/resign
                $createdBy = $r['created_by'] ?? null;
                if ($createdBy && !$this->isUserActive($createdBy)) {
                    $this->log('dynamic_'.$catId, $docId, 'system', 'inactive_employee');
                    continue;
                }

                // Ambil nilai "Nomor Dokumen" atau field pertama untuk dicantumkan di pesan WA
                $docLabel = "Dokumen";
                $stmtVal = sqlsrv_query($this->conn, "SELECT TOP 1 field_value FROM dr_doc_values v JOIN dr_fields f ON v.field_id = f.id WHERE v.document_id = ? AND f.is_show_on_table = 1 ORDER BY f.sort_order ASC", [$docId]);
                if ($stmtVal && $rVal = sqlsrv_fetch_array($stmtVal, SQLSRV_FETCH_ASSOC)) {
                    $docLabel = $rVal['field_value'];
                }

                // Siapkan Pesan WA
                $waMsg = "🔔 REMINDER {$catName}\n" .
                         "Rincian: {$docLabel}\n" .
                         "Expire: {$r['expire_date']->format('d-m-Y')}\n\n" .
                         "Mohon segera ditindaklanjuti.\n" .
                         "Setelah diperpanjang, silakan update tanggal expire di:\n" .
                         "GG App\n" .
                         "http://192.168.7.184:8080/gg_app/login.php";

                // Kirim Email (Jika ada dan sistem email mendukung dynamic form)
                // Karena EmailService hardcoded kontrak/kendaraan, kita bisa buat fungsi umum jika ada, atau lewati dulu.
                if (!empty($r['email_reminder'])) {
                    // Opsional: EmailService::sendDynamic($r, $cat['category_name'])
                    // Untuk saat ini kita log saja atau kirim jika fungsi email dinamis disiapkan.
                    // $this->log('dynamic_'.$catId, $docId, 'email');
                }

                // Kirim WA
                if (!empty($r['no_whatsapp'])) {
                    WhatsappService::send($r['no_whatsapp'], $waMsg);
                    $this->log('dynamic_'.$catId, $docId, 'whatsapp');
                }
                
                // Opsional: Update status menjadi 'Reminder'
                sqlsrv_query($this->conn, "UPDATE dr_documents SET status = 'Reminder' WHERE id = ?", [$docId]);
            }
            
            // Opsional: Update status yang sudah lewat menjadi 'Expired' (Sekalian jalan)
            sqlsrv_query($this->conn, "UPDATE dr_documents SET status = 'Expired' WHERE category_id = ? AND expire_date < CAST(GETDATE() AS DATE)", [$catId]);
        }
    }

    private function reminderKontrak(): void
    {
        $this->process(
            'kontrak',
            'dr_kontrak',
            'reminder_interval_kontrak',
            fn($r) => EmailService::sendKontrak($r),
            fn($r) =>
                "🔔 REMINDER KONTRAK\n" .
                "No Kontrak: {$r['no_kontrak']}\n" .
                "Expire: {$r['expire_date']->format('d-m-Y')}\n\n" .
                "Mohon segera ditindaklanjuti.\n" .
                "Setelah diperpanjang, silakan update expired date di:\n" .
                "GG App\n" .
                "http://192.168.7.184:8080/gg_app/login.php"
        );
    }

    private function reminderSertifikat(): void
    {
        $this->process(
            'sertifikat',
            'dr_sertifikat',
            'reminder_interval_sertifikat',
            fn($r) => EmailService::sendSertifikat($r),
            fn($r) =>
                "📄 REMINDER SERTIFIKAT\n" .
                "{$r['nama_sertifikat']}\n" .
                "Expire: {$r['expire_date']->format('d-m-Y')}\n\n" .
                "Mohon segera ditindaklanjuti.\n" .
                "Setelah diperpanjang, silakan update expired date di:\n" .
                "GG App\n" .
                "http://192.168.7.184:8080/gg_app/login.php"
        );
    }

    private function reminderKendaraan(): void
    {
        $this->process(
            'kendaraan',
            'dr_surat_kendaraan',
            'reminder_interval_surat_kendaraan',
            fn($r) => EmailService::sendKendaraan($r),
            fn($r) =>
                "🚗 REMINDER KENDARAAN\n" .
                "{$r['no_polisi']}\n" .
                "Expire: {$r['expire_date']->format('d-m-Y')}\n\n" .
                "Mohon segera ditindaklanjuti.\n" .
                "Setelah diperpanjang, silakan update expired date di:\n" .
                "GG App\n" .
                "http://192.168.7.184:8080/gg_app/login.php"
        );
    }

    private function process(
        string $jenis,
        string $table,
        string $intervalKey,
        callable $emailCb,
        callable $waCb
    ): void {
        $interval = $this->getInterval($intervalKey);

        $stmt = sqlsrv_query(
            $this->conn,
            "SELECT *
             FROM {$table}
             WHERE expire_date IS NOT NULL
               AND DATEDIFF(
                     DAY,
                     CAST(GETDATE() AS DATE),
                     CAST(expire_date AS DATE)
                   ) <= ?
               AND expire_date >= CAST(GETDATE() AS DATE)",
            [$interval]
        );

        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($this->alreadySent($jenis, (int)$r['id'])) {
                continue;
            }

            // --- CEK STATUS KARYAWAN (AKTIF/RESIGN) ---
            $createdBy = $r['created_by'] ?? null;
            if ($createdBy && !$this->isUserActive($createdBy)) {
                $this->log($jenis, (int)$r['id'], 'system', 'inactive_employee');
                continue;
            }

            if (!empty($r['email_reminder'])) {
                $emailCb($r);
                $this->log($jenis, (int)$r['id'], 'email');
            }

            if (!empty($r['no_whatsapp'])) {
                WhatsappService::send($r['no_whatsapp'], $waCb($r));
                $this->log($jenis, (int)$r['id'], 'whatsapp');
            }
        }
    }

    /**
     * Cek apakah user (created_by) masih aktif di m_emp
     */
    private function isUserActive(string $username): bool
    {
        $sql = "SELECT e.aktif 
                FROM SMUserMs u 
                JOIN m_emp e ON u.EmpId = e.id_emp 
                WHERE u.UserName = ?";
        $stmt = sqlsrv_query($this->conn, $sql, [$username]);
        if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            return (int)$row['aktif'] === 1;
        }
        // Jika user tidak ditemukan di m_emp, anggap tidak aktif/tidak valid untuk dikirim reminder
        return false;
    }

    /**
     * Kirim Test Reminder (Manual dari Dev Menu)
     */
    public function sendTestReminder(string $jenis, int $id): array
    {
        $config = [
            'kontrak' => [
                'table' => 'dr_kontrak',
                'emailCb' => fn($r) => EmailService::sendKontrak($r),
                'waMsg' => fn($r) => "🔔 [TEST] REMINDER KONTRAK\nNo: {$r['no_kontrak']}\nExpire: " . $r['expire_date']->format('d-m-Y')
            ],
            'sertifikat' => [
                'table' => 'dr_sertifikat',
                'emailCb' => fn($r) => EmailService::sendSertifikat($r),
                'waMsg' => fn($r) => "📄 [TEST] REMINDER SERTIFIKAT\nName: {$r['nama_sertifikat']}\nExpire: " . $r['expire_date']->format('d-m-Y')
            ],
            'kendaraan' => [
                'table' => 'dr_surat_kendaraan',
                'emailCb' => fn($r) => EmailService::sendKendaraan($r),
                'waMsg' => fn($r) => "🚗 [TEST] REMINDER KENDARAAN\nPlate: {$r['no_polisi']}\nExpire: " . $r['expire_date']->format('d-m-Y')
            ],
        ];

        if (!isset($config[$jenis])) return ['status' => 'error', 'message' => 'Jenis dokumen tidak valid'];

        $conf = $config[$jenis];
        $stmt = sqlsrv_query($this->conn, "SELECT * FROM {$conf['table']} WHERE id = ?", [$id]);
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if (!$r) return ['status' => 'error', 'message' => 'Data tidak ditemukan'];

        // Cek Karyawan
        $createdBy = $r['created_by'] ?? null;
        if ($createdBy && !$this->isUserActive($createdBy)) {
            return ['status' => 'warning', 'message' => 'Karyawan ('.$createdBy.') sudah tidak aktif/resign'];
        }

        $sentCount = 0;
        if (!empty($r['email_reminder'])) {
            if ($conf['emailCb']($r)) $sentCount++;
        }
        if (!empty($r['no_whatsapp'])) {
            if (WhatsappService::send($r['no_whatsapp'], $conf['waMsg']($r))) {
                $sentCount++;
            }
        }

        return [
            'status' => 'success',
            'message' => "Test reminder terkirim ke " . ($sentCount > 0 ? $sentCount : "0") . " channel."
        ];
    }

    private function getInterval(string $key): int
    {
        $stmt = sqlsrv_query(
            $this->conn,
            "SELECT nilai FROM dr_reminder_interval WHERE kunci = ?",
            [$key]
        );
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return (int)($r['nilai'] ?? 7);
    }

    private function alreadySent(string $jenis, int $id): bool
    {
        $stmt = sqlsrv_query(
            $this->conn,
            "SELECT 1
             FROM dr_reminder_log
             WHERE jenis_dokumen = ?
               AND dokumen_id = ?
               AND reminder_date = CAST(GETDATE() AS DATE)",
            [$jenis, $id]
        );
        return sqlsrv_fetch_array($stmt) !== null;
    }

    private function log(string $jenis, int $id, string $channel, string $status = 'sent'): void
    {
        sqlsrv_query(
            $this->conn,
            "INSERT INTO dr_reminder_log
             (jenis_dokumen, dokumen_id, reminder_date, channel, status)
             VALUES (?, ?, CAST(GETDATE() AS DATE), ?, ?)",
            [$jenis, $id, $channel, $status]
        );
    }
}
