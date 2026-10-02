<?php
require 'vendor/autoload.php';
use MongoDB\Client;

ini_set('max_execution_time', 0);
set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

// === Setup koneksi MongoDB ===
$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$dbName = "api_sum";
$collectionName = "cp_batch_transfer_lists";
$collection = $client->$dbName->$collectionName;

// === Fungsi kirim progress realtime ke browser ===
function sendProgress($percent, $message) {
    echo "data: " . json_encode(["percent" => $percent, "message" => $message]) . "\n\n";
    @ob_flush();
    @flush();
}

// === Jika request POST, jalankan update ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $userSession = $_SESSION['username'] ?? 'Unknown';

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $updateMode    = $_POST['update_mode'] ?? 'baleno';
    $balenoList    = $_POST['baleno'] ?? [];
    $batchnoList   = $_POST['batchno'] ?? [];
    $packingnoList = $_POST['packingno'] ?? [];
    $wrhsnameList  = $_POST['wrhsname'] ?? [];
    $wrhscodeList  = $_POST['wrhscode'] ?? [];
    $wrhsrunidList = $_POST['wrhsrunid'] ?? [];
    $wrhsidList    = $_POST['wrhsid'] ?? [];

    $countUpdated = 0;
    $logItems = [];

    $rowCount = max(count($balenoList), count($batchnoList), count($packingnoList));

    sendProgress(0, "Menyiapkan data...");
    usleep(200000);

    for ($i = 0; $i < $rowCount; $i++) {
        $baleno    = trim($balenoList[$i] ?? '');
        $batchno   = trim($batchnoList[$i] ?? '');
        $packingno = trim($packingnoList[$i] ?? '');

        if ($baleno === '' && $batchno === '' && $packingno === '') continue;

        // Tentukan mode pencarian
        switch ($updateMode) {
            case 'packingno':
                if ($packingno === '') continue 2;
                $identifier = $packingno;
                $searchCriteria = ['packingno' => $packingno];
                break;
            case 'batchno':
                if ($batchno === '') continue 2;
                $identifier = $batchno;
                $searchCriteria = ['batchno' => $batchno];
                break;
            case 'baleno':
            default:
                if ($baleno === '') continue 2;
                $identifier = $baleno;
                $searchCriteria = ['baleno' => $baleno];
                break;
        }

        $updateFields = [
            'wrhsname'  => trim($wrhsnameList[$i] ?? ''),
            'wrhscode'  => trim($wrhscodeList[$i] ?? ''),
            'wrhsrunid' => trim($wrhsrunidList[$i] ?? ''),
            'wrhsid'    => (int)($wrhsidList[$i] ?? 0),
        ];

        // Ambil data lama sebelum update
        $foundDocs = iterator_to_array($collection->find($searchCriteria), false);
        $foundCount = count($foundDocs);
        if ($foundCount === 0) continue;

        try {
            $result = $collection->updateMany($searchCriteria, ['$set' => $updateFields]);
            $modified = $result->getModifiedCount();
        } catch (Exception $e) {
            error_log("Update error: " . $e->getMessage());
            continue;
        }

        if ($modified <= 0) continue;

        $countUpdated += $modified;

        // Log data
        $logItems[] = [
            'identifier'    => $identifier,
            'update_mode'   => $updateMode,
            'total_updated' => $modified,
            'updated_from'  => [
                'wrhsname' => $foundDocs[0]['wrhsname'] ?? '-',
                'wrhscode' => $foundDocs[0]['wrhscode'] ?? '-',
            ],
            'updated_to'    => $updateFields,
            'timestamp'     => date('Y-m-d H:i:s'),
            'ip_address'    => $_SERVER['REMOTE_ADDR'],
            'user_session'  => $userSession,
            'description'   => "Perubahan lokasi gudang ($collectionName / mode: $updateMode) - $modified data diupdate",
        ];

        // === Progress realtime ===
        $percent = round((($i + 1) / $rowCount) * 100);
        sendProgress($percent, "Berhasil update {$countUpdated} data sejauh ini...");
        usleep(150000);
    }

    // === Simpan log ke file (AMAN dan pasti jalan) ===
    if (!empty($logItems)) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $logFile = $logDir . '/update_log_' . date('Y-m-d') . '.json';

        $fp = fopen($logFile, 'a');
        if ($fp) {
            foreach ($logItems as $log) {
                fwrite($fp, json_encode($log, JSON_UNESCAPED_UNICODE) . PHP_EOL);
            }
            fclose($fp);
        }
    }
		
		if ($countUpdated > 0) {
    sendProgress(100, "✅ Selesai! Total {$countUpdated} data berhasil diupdate 🎉");
		}
		else {
        sendProgress(100, "⚠️ Tidak ada data yang diupdate! Pastikan data valid atau belum sesuai kondisi update.");
    }
    exit;
}

?>


<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $success ? 'Berhasil' : 'Gagal' ?> Update</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    margin: 0;
}
.popup {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    padding: 35px 45px;
    text-align: center;
    animation: fadeIn 0.5s ease-out, bounce 1.2s ease-in-out;
    max-width: 350px;
}
.popup.success .icon { color: #4CAF50; }
.popup.error .icon { color: #e53935; }
.popup .icon {
    font-size: 55px;
    margin-bottom: 10px;
}
.popup h2 {
    margin: 10px 0 5px;
    color: #2e7d32;
}
.popup.error h2 { color: #c62828; }
.popup p {
    color: #555;
    font-size: 15px;
    margin: 6px 0 18px;
}
.popup button {
    background: linear-gradient(90deg, #43a047, #66bb6a);
    border: none;
    color: white;
    padding: 10px 22px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.popup.error button {
    background: linear-gradient(90deg, #e53935, #ef5350);
}
.popup button:hover {
    transform: scale(1.05);
}
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}
@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-15px); }
    60% { transform: translateY(-8px); }
}
.fade-out {
    animation: fadeOut 0.5s forwards;
}
@keyframes fadeOut {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.8); }
}
</style>
</head>
<body>
<div class="popup <?= $success ? 'success' : 'error' ?>" id="popupBox">
    <div class="icon"><?= $success ? '✅' : '❌' ?></div>
    <h2><?= $success ? 'Berhasil!' : 'Tidak Ada Data Diupdate' ?></h2>
    <p>
        <?= $success 
            ? "$countUpdated data berhasil diupdate 🎉"
            : "Semua data sudah sesuai, tidak ada perubahan yang disimpan." ?>
    </p>
    <button onclick="kembali()">Kembali</button>
</div>

<script>
function kembali() {
    const box = document.getElementById('popupBox');
    if (box) box.classList.add('fade-out');

    setTimeout(() => {
        try {
            // Jika opener ada (dibuka via window.open dari dashboard), gunakan postMessage
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({ action: 'refreshResultData' }, '*');
                // tutup popup (jika ini popup terpisah)
                // window.close(); // uncomment jika ingin menutup window popup
                // kembali ke halaman sebelumnya (history.back) jika masih di same tab
                window.history.back();
                return;
            }

            // Jika opener tidak ada (sama tab), fallback ke returnUrl yang disimpan
            const returnUrl = sessionStorage.getItem('dashboardReturnUrl') || document.referrer || '/';
            // Simpan flag agar dashboard mem-trigger partial refresh (agar restore state + partial refresh)
            sessionStorage.setItem('forcePartialRefresh', '1');

            // Simpan form state di localStorage (jika ingin dipulihkan)
            try {
                const inputs = {};
                document.querySelectorAll("input, select, textarea").forEach(el => {
                    if (el.name || el.id) inputs[el.name || el.id] = el.value;
                });
                localStorage.setItem('dashboardFormState', JSON.stringify(inputs));
            } catch(e){ console.warn('Save form state failed', e); }

            // Gunakan replace agar tidak membuat history tambahan
            window.location.replace(returnUrl);
        } catch (e) {
            console.error('kembali() error', e);
            // fallback: just back
            window.history.back();
        }
    }, 400);
}

// Auto-run after 3s
setTimeout(kembali, 3000);
</script>

</body>
</html>
