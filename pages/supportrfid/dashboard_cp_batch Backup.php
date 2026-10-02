<?php
require 'vendor/autoload.php';
use MongoDB\Client;
require_once __DIR__ . '/role.php';
require 'auth_check.php';

ini_set('max_execution_time', 3000); // 300 detik = 5 menit


// 🔗 Koneksi ke MongoDB
$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$collection = $client->api_sum->cp_batch_transfer_lists;

//hak akses
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/includes/access_helper.php';

$username = $_SESSION['username'];
$groupId = getUserGroup($username);

// 🔐 Cek hak akses ke halaman (contoh: update_multi)
if (!canAccessMenu($groupId, 'updatecpbatch')) {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit;
}


// 🧭 Variabel awal
$cari = $_GET['cari'] ?? '';
$selectedWarehouse = $_GET['wrhsname'] ?? '';
$dataGabungan = [];
// 📦 Semua daftar gudang
$allWarehouses = [
    ["wrhscode" => "103",   "wrhsname" => "GUDANG KAIN JADI 2",         "wrhsrunid" => "03", "wrhsid" => 350], 
    ["wrhscode" => "193",   "wrhsname" => "GUDANG SAMPLE SUM",          "wrhsrunid" => "12", "wrhsid" => 314],
    ["wrhscode" => "111",   "wrhsname" => "GUDANG EX-WARNA",            "wrhsrunid" => "10", "wrhsid" => 361],
    ["wrhscode" => "103B",  "wrhsname" => "GUDANG JADI B GRADE",        "wrhsrunid" => "03", "wrhsid" => 352],
    ["wrhscode" => "112",   "wrhsname" => "GUDANG TRANSIT VERPACKING",  "wrhsrunid" => "10", "wrhsid" => 362],
    ["wrhscode" => "190",   "wrhsname" => "GUDANG SAMPLE",              "wrhsrunid" => "11", "wrhsid" => 371],
    ["wrhscode" => "103A",  "wrhsname" => "GUDANG KAIN JADI 1",         "wrhsrunid" => "03", "wrhsid" => 351],
    ["wrhscode" => "103C",  "wrhsname" => "GUDANG KAIN JADI 3",         "wrhsrunid" => "03", "wrhsid" => 353],
    ["wrhscode" => "173",   "wrhsname" => "GUDANG SBY BUNDLING",        "wrhsrunid" => "17", "wrhsid" => 391],
    ["wrhscode" => "172-C", "wrhsname" => "GUDANG SBY 27",              "wrhsrunid" => "17", "wrhsid" => 411],
    ["wrhscode" => "172-B", "wrhsname" => "GUDANG SBY 22",              "wrhsrunid" => "17", "wrhsid" => 410],
    ["wrhscode" => "172-A", "wrhsname" => "GUDANG TRANSIT SBY",         "wrhsrunid" => "17", "wrhsid" => 409],
    ["wrhscode" => "172",   "wrhsname" => "GUDANG SBY",                 "wrhsrunid" => "17", "wrhsid" => 310],
];


// 🔍 Proses pencarian data
if ($cari) {
    $cari = str_replace(';', ',', $cari);  
    $keywords = preg_split('/[\n,]+/', trim($cari));  
    $keywords = array_filter(array_map('trim', $keywords), fn($k) => strlen($k) > 0);

    foreach ($keywords as $kata) {
        // Exact match regex, case-insensitive
        $regexPattern = '^' . preg_quote($kata, '/') . '$';

       // Tentukan field berdasarkan hak akses grup
if (canAccessButton($groupId, 'search')) {
    // Grup ini punya izin 'search'
    $fields = ['baleno', 'batchno', 'packingno'];
    $regexPattern = "\\b" . preg_quote($kata, '/') . "\\b";
} else {
    // Grup tanpa izin 'search' hanya bisa filter batchno & packingno
    $fields = ['batchno', 'packingno'];
}

        // Build query $or dinamis
        $orQuery = array_map(fn($f) => [$f => new MongoDB\BSON\Regex($regexPattern, 'i')], $fields);

        $cursor = $collection->find(['$or' => $orQuery]);
        $hasil = iterator_to_array($cursor);

        $dataGabungan[] = [
            'keyword' => $kata,
            'jumlah'  => count($hasil),
            'contoh'  => $hasil[0] ?? null
        ];
    }
}





// pastikan $cari sudah dideklarasikan sebelumnya, contoh:
// $cari = $_GET['cari'] ?? '';

/**
 * Tentukan autoMode default dan deteksi jika ada input pencarian.
 * Letakkan ini sebelum penggunaan $autoMode di HTML.
 */
$autoMode = 'baleno'; // default

if (!empty($cari)) {
    $firstLine = explode("\n", trim($cari))[0];
    $firstLine = trim($firstLine);

    // Deteksi packingno contoh: 2509.10026  (angka.titikangka)
    if (preg_match('/^\d+\.\d+$/', $firstLine)) {
        $autoMode = 'packingno';
    }
    // Deteksi batchno: mengandung huruf + beberapa titik (contoh: D25I0597.01.0204.025)
    elseif (preg_match('/[A-Za-z]\d{2,}/', $firstLine) && substr_count($firstLine, '.') >= 2) {
        $autoMode = 'batchno';
    }
    // Sisanya dianggap baleno (contoh: "112 AR")
    else {
        $autoMode = 'baleno';
    }
}



?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Data Gudang Cp Batch Transfer Lists</title>

<style>

/* ====== FIX MARGIN DAN POSISI DI MOBILE ====== */
@media (max-width: 768px) {
    body {
        background: linear-gradient(180deg, #e3f2fd, #ffffff);
        overflow-x: hidden;
    }

    .content {
        margin: 0 !important;
        padding: 80px 16px 60px 16px !important; /* 🔹 Turunkan container */
    }

    .container {
        margin-top: 20px !important; /* 🔹 Tambah jarak dari atas */
        padding: 18px !important;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        animation: fadeIn 0.4s ease;
    }

    h2 {
        margin-top: 0;
        font-size: 18px;
        text-align: center;
        line-height: 1.4;
        color: #1976d2;
    }

    .caption {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
        gap: 6px;
    }

    .caption button {
        width: 100%;
        padding: 8px;
        font-size: 13px;
        border-radius: 6px;
    }

    /* Tabel jadi scrollable, biar tidak pecah di HP */
    .container table {
        display: block;
        width: 100%;
        overflow-x: auto;
        border-collapse: collapse;
    }

    table td {
        word-break: break-all;
        white-space: normal;
    }
}

/* ======== GLOBAL ======== */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #e0f7fa, #e3f2fd);
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}
.content {
    margin-left: 240px;
    padding: 25px;
    animation: fadeIn 0.6s ease-in-out;
}
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    max-width: 900px;
    margin: auto;
    animation: slideUp 0.8s ease;
}
h2 {
    text-align: center;
    color: #1976d2;
    margin-bottom: 20px;
}

/* ======== INPUT & BUTTON ======== */
textarea, select, input[type=text] {
    width: 100%;
    padding: 10px;
    margin-top: 6px;
    border: 1px solid #cfd8dc;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}
textarea:focus, select:focus, input[type=text]:focus {
    border-color: #2196F3;
    box-shadow: 0 0 6px rgba(33,150,243,0.4);
    outline: none;
}
button {
    background: linear-gradient(90deg, #2196F3, #42a5f5);
    color: white;
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
}
button:hover {
    background: linear-gradient(90deg, #1976d2, #2196F3);
    transform: translateY(-2px);
}

/* ======== CAPTION (DATA INFO) ======== */
.caption {
    background: #f8fbff;
    border-left: 5px solid #2196F3;
    padding: 12px 14px;
    margin-top: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    animation: fadeIn 0.5s ease;
}
.caption:hover {
    transform: scale(1.01);
    transition: transform 0.2s ease-in-out;
}
.no-result-theme {
    background-color: #f44336;
    color: white;
    font-weight: bold;
    padding: 12px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: shake 0.4s ease;
}
.no-result {
    color: #fff;
    font-weight: bold;
}

/* ======== TABLE STYLE ======== */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    animation: fadeIn 0.5s ease;
}
table td {
    border-bottom: 1px solid #e0e0e0;
    padding: 6px;
    transition: background 0.2s ease;
}
table tr:hover td {
    background: #f1f9ff;
}

/* ======== BUTTON UPDATE ======== */
.update-btn {
    margin-top: 25px;
    width: 100%;
    font-size: 16px;
    background: linear-gradient(90deg, #4CAF50, #66bb6a);
    box-shadow: 0 4px 10px rgba(76,175,80,0.3);
}
.update-btn:hover {
    background: linear-gradient(90deg, #43a047, #4caf50);
}

/* ======== ANIMATIONS ======== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes shake {
    0% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    50% { transform: translateX(4px); }
    75% { transform: translateX(-2px); }
    100% { transform: translateX(0); }
}

/* ======== RESPONSIVE ======== */
@media (max-width: 768px) {
    .content { margin-left: 0; padding: 15px; }
    .container { padding: 15px; }
    button { width: 100%; margin-top: 10px; }
}
/* === RESPONSIVE ENHANCEMENT UNTUK MOBILE === */
@media (max-width: 768px) {
    body {
        overflow-x: hidden;
        background: linear-gradient(180deg, #e3f2fd, #ffffff);
    }

    .content {
        margin: 0 !important;
        padding: 20px 15px 60px 15px !important; /* 🔹 Tambah padding bawah agar tidak nempel */
    }

    .container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 20px auto !important; /* 🔹 Tambah jarak dari atas */
        padding: 18px !important;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        background: #fff;
        animation: fadeIn 0.4s ease;
    }

    h2 {
        font-size: 18px !important;
        text-align: center;
        margin-bottom: 15px;
        line-height: 1.4;
    }

    textarea, select, input[type="text"] {
        width: 100% !important;
        font-size: 14px;
        border-radius: 8px;
    }

    button {
        width: 100%;
        margin-top: 10px;
        font-size: 14px;
        padding: 10px;
        border-radius: 8px;
    }

    /* === MODE CARD VIEW UNTUK DATA TABLE === */
    table {
        display: none; /* 🔹 Sembunyikan tabel di mobile */
    }

    /* Gaya untuk card view */
    .data-card {
        display: block;
        background: #f9fbff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        padding: 12px 15px;
        margin-bottom: 12px;
        border-left: 4px solid #1976d2;
        animation: slideUp 0.4s ease;
    }

    .data-card h4 {
        color: #1976d2;
        font-size: 15px;
        margin: 0 0 6px 0;
    }

    .data-card p {
        margin: 4px 0;
        font-size: 13px;
        color: #333;
        line-height: 1.4;
    }

    .caption {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
        text-align: left;
    }

    .caption button {
        width: auto;
        padding: 6px 10px;
        font-size: 13px;
    }
}

/* === ANIMASI === */
@keyframes slideUp {
    from { transform: translateY(10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* ==== FIX POSISI DI MOBILE (lebih turun dari topbar) ==== */
@media (max-width: 768px) {
    .content {
        margin: 0 !important;
        padding: 50px 16px 60px 16px !important; /* ⬅️ dari 80px jadi 100px */
        background: linear-gradient(180deg, #e3f2fd, #ffffff);
    }

    .container {
        margin-top: 30px !important; /* ⬅️ tambah jarak */
        padding: 18px !important;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }

    h2 {
        margin-top: 10px !important; /* ⬅️ turun sedikit lagi */
        font-size: 18px;
        text-align: center;
        line-height: 1.4;
        color: #1976d2;
    }
}

/* 🌐 Gelembung Notifikasi */
.no-result-bubble {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #fdecea, #f8d7da);
    color: #721c24;
    padding: 18px 20px 22px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    z-index: 9999;
    max-width: 320px;
    width: calc(100% - 40px);
    font-family: 'Segoe UI', Tahoma, sans-serif;
    animation: fadeIn 0.4s ease-out;
    transition: all 0.4s ease;
    overflow: hidden;
}

/* 📝 Judul */
.no-result-bubble h4 {
    margin: 0 0 8px;
    font-size: 15px;
    color: #5c1a1a;
}

/* 📋 Daftar item */
.no-result-bubble ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
}
.no-result-bubble li {
    padding: 3px 0;
    font-size: 14px;
}

/* 📎 Tombol Copy */
#copyNoResultBtn {
    background: linear-gradient(90deg, #1976d2, #42a5f5);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 14px;
    width: 100%;
    font-weight: 600;
    letter-spacing: 0.3px;
    transition: all 0.25s ease;
    box-shadow: 0 3px 6px rgba(25,118,210,0.25);
}
#copyNoResultBtn:hover {
    background: linear-gradient(90deg, #1565c0, #1e88e5);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 6px 12px rgba(25,118,210,0.35);
}

/* ❌ Tombol Close */
.close-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #fff;
    border: 2px solid #f5b7b1;
    color: #a71d2a;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    cursor: pointer;
    transition: all 0.35s ease;
    z-index: 10000;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.close-btn:hover {
    background: #ffebee;
    border-color: #ef5350;
    color: #d32f2f;
    box-shadow: 0 4px 8px rgba(211,47,47,0.2);
}

/* 📱 Responsif */
@media (max-width: 600px) {
    .no-result-bubble {
        top: 12px;
        right: 12px;
        padding: 14px 16px 18px;
        max-width: 260px;
        font-size: 14px;
    }
    .close-btn {
        top: 6px;
        right: 6px;
        width: 24px;
        height: 24px;
        font-size: 15px;
    }
    #copyNoResultBtn {
        font-size: 13.5px;
        padding: 8px;
    }
}

/* ✨ Animasi masuk & keluar */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes fadeOutUp {
    from { opacity: 1; transform: translateY(0) scale(1); }
    to { opacity: 0; transform: translateY(-20px) scale(0.95); }
}

/* Saat keluar */
.fade-out {
    animation: fadeOutUp 0.4s forwards ease-in-out;
}
/* 🌐 Gelembung Notifikasi */
.no-result-bubble {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #fdecea, #f8d7da);
    color: #721c24;
    padding: 18px 20px 22px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    z-index: 9999;
    max-width: 320px;
    width: calc(100% - 40px);
    font-family: 'Segoe UI', Tahoma, sans-serif;
    animation: fadeIn 0.4s ease-out;
    transition: all 0.4s ease;
    overflow: hidden;
    max-height: 70vh;  /* Batasi tinggi notifikasi */
    display: flex;
    flex-direction: column;
}

/* Agar konten dalam notifikasi dapat digulir */
.no-result-bubble ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
    overflow-y: auto;  /* Aktifkan scroll jika data terlalu banyak */
    max-height: 60vh;  /* Batasi tinggi bagian list */
    padding-right: 10px;  /* Agar scroll tidak tersembunyi */
}

.no-result-bubble li {
    padding: 3px 0;
    font-size: 14px;
}

/* Tombol Copy */
#copyNoResultBtn {
    background: linear-gradient(90deg, #1976d2, #42a5f5);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 14px;
    width: 100%;
    font-weight: 600;
    letter-spacing: 0.3px;
    transition: all 0.25s ease;
    box-shadow: 0 3px 6px rgba(25,118,210,0.25);
}

#copyNoResultBtn:hover {
    background: linear-gradient(90deg, #1565c0, #1e88e5);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 6px 12px rgba(25,118,210,0.35);
}

/* Tombol Close */
.close-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #fff;
    border: 2px solid #f5b7b1;
    color: #a71d2a;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    cursor: pointer;
    transition: all 0.35s ease;
    z-index: 10000;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.close-btn:hover {
    background: #ffebee;
    border-color: #ef5350;
    color: #d32f2f;
    box-shadow: 0 4px 8px rgba(211,47,47,0.2);
}

/* Animasi masuk & keluar */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes fadeOutUp {
    from { opacity: 1; transform: translateY(0) scale(1); }
    to { opacity: 0; transform: translateY(-20px) scale(0.95); }
}

.fade-out {
    animation: fadeOutUp 0.4s forwards ease-in-out;
}

/* Responsif */
@media (max-width: 600px) {
    .no-result-bubble {
        top: 12px;
        right: 12px;
        padding: 14px 16px 18px;
        max-width: 260px;
        font-size: 14px;
    }

    .close-btn {
        top: 6px;
        right: 6px;
        width: 24px;
        height: 24px;
        font-size: 15px;
    }

    #copyNoResultBtn {
        font-size: 13.5px;
        padding: 8px;
    }
}


.popup-warning {
  display: none;
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(90deg, #ff9800, #ffb74d);
  color: #fff;
  padding: 12px 20px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  font-size: 14px;
  font-weight: 500;
  z-index: 2000;
  animation: fadeInDown 0.4s ease;
}
@keyframes fadeInDown {
  from { opacity: 0; transform: translate(-50%, -20px); }
  to { opacity: 1; transform: translate(-50%, 0); }
}
.fadeOut {
  animation: fadeOutUp 0.4s forwards;
}
@keyframes fadeOutUp {
  from { opacity: 1; transform: translate(-50%, 0); }
  to { opacity: 0; transform: translate(-50%, -20px); }
}


</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="content">
    <div class="container">
        <h2>🛠️ Update Cp Batch Transfer Lists</h2>

        <!-- === FORM PENCARIAN === -->
        <form method="get">
		
            <label><b>Masukkan<?php if (canAccessButton($groupId, 'search')): ?> Baleno /<?php endif; ?> Batchno / Packingno</b></label>
            <textarea name="cari" rows="3" placeholder="Pisahkan dengan koma atau baris baru"><?= htmlspecialchars($cari ?? '') ?></textarea>
            <button type="submit">🔎 Cari</button>
        </form>

        <?php if ($cari && $dataGabungan): ?>
            <hr>

            <!-- === FORM UPDATE === -->
            <form action="update_batch.php" method="post" id="updateForm" onsubmit="setReturnInfo();">

                  <label for="update_mode"><b>Mode Update Berdasarkan</b></label>
<select id="update_mode" name="update_mode" required 
    <?php if (!canAccessButton($groupId, 'tampilkan')): ?>readonly style="pointer-events:none;opacity:0.6"<?php endif; ?>>


    <?php if (canAccessButton($groupId, 'search')): ?>
        <option value="baleno"   <?= ($autoMode == 'baleno') ? 'selected' : '' ?>>Baleno</option>
    <?php endif; ?>
    <option value="batchno"  <?= ($autoMode == 'batchno') ? 'selected' : '' ?>>Batch No</option>
    <option value="packingno" <?= ($autoMode == 'packingno') ? 'selected' : '' ?>>Packing No</option>
</select>
               <label for="wrhsname"><b>Pilih Gudang</b></label>
<select id="wrhsname" name="wrhsname">
    <option value="">-- Pilih Gudang --</option>
    <?php if (canAccessButton($groupId, 'onlysby')): ?>
        <?php foreach ($allWarehouses as $w): ?>
            <?php if (in_array($w['wrhscode'], ['172','172-A','172-B','172-C','173'])): ?>
                <option value="<?= htmlspecialchars($w['wrhsname']) ?>"
                        data-wrhscode="<?= htmlspecialchars($w['wrhscode']) ?>"
                        data-wrhsrunid="<?= htmlspecialchars($w['wrhsrunid']) ?>"
                        data-wrhsid="<?= htmlspecialchars($w['wrhsid']) ?>">
                    <?= htmlspecialchars($w['wrhsname']) ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($allWarehouses as $w): ?>
            <option value="<?= htmlspecialchars($w['wrhsname']) ?>"
                    data-wrhscode="<?= htmlspecialchars($w['wrhscode']) ?>"
                    data-wrhsrunid="<?= htmlspecialchars($w['wrhsrunid']) ?>"
                    data-wrhsid="<?= htmlspecialchars($w['wrhsid']) ?>">
                <?= htmlspecialchars($w['wrhsname']) ?>
            </option>
        <?php endforeach; ?>
    <?php endif; ?>
</select>

<!-- Input hidden untuk submit -->
<input type="hidden" name="wrhscode" id="wrhscode">
<input type="hidden" name="wrhsrunid" id="wrhsrunid">
<input type="hidden" name="wrhsid" id="wrhsid">



 <!-- === DATA === -->
                <?php $noResultData = []; ?>
                <?php foreach ($dataGabungan as $item): ?>
                    <?php $uid = md5($item['keyword']); $displayKeyword = trim($item['keyword']) ?: '(tidak ada keyword)'; ?>
                    <div class="caption <?= ($item['jumlah'] == 0) ? 'no-result-theme' : ''; ?>">
                        <?php if ($item['jumlah'] > 0): ?>
                            🔹 <b><?= htmlspecialchars($displayKeyword) ?></b>
                            <span>(<?= $item['jumlah'] ?> data ditemukan)</span>
							 <!-- Menambahkan wrhsname jika ada -->
            <?php if (isset($item['contoh']['wrhsname'])): ?>
                <span><?= htmlspecialchars($item['contoh']['wrhsname']) ?></span>
            <?php endif; ?>
			<?php if (canAccessButton($groupId, 'tampilkan')): ?>
                            <button type="button" data-toggle="<?= $uid ?>">Tampilkan 1 Data</button>
							<?php endif; ?>
                        <?php else: ?>
                            🔴 <b><?= htmlspecialchars($displayKeyword) ?></b>
                            <span class="no-result">No result</span>
                            <?php $noResultData[] = $displayKeyword; // Menyimpan data no result ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($item['contoh']): ?>
                        <div id="data-<?= $uid ?>" style="display:none; margin-top:10px; animation: fadeIn 0.5s;">
                            <input type="hidden" name="baleno[]" value="<?= htmlspecialchars($item['contoh']['baleno'] ?? '') ?>">
                            <input type="hidden" name="batchno[]" value="<?= htmlspecialchars($item['contoh']['batchno'] ?? '') ?>">
                            <input type="hidden" name="packingno[]" value="<?= htmlspecialchars($item['contoh']['packingno'] ?? '') ?>">
                            <input type="hidden" name="wrhsname[]" value="">
                            <input type="hidden" name="wrhscode[]" value="">
                            <input type="hidden" name="wrhsrunid[]" value="">
                            <input type="hidden" name="wrhsid[]" value="">

                            <table>
                                <?php foreach ($item['contoh'] as $key => $value): if ($key == '_id') continue; ?>
                                    <tr>
                                        <td style="width:30%;"><b><?= htmlspecialchars($key) ?></b></td>
                                        <td><input type="text" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($value) ?>" readonly></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
<?php if (canAccessButton($groupId, 'update-btn')): ?>
                <button type="submit" class="update-btn">💾 Update Semua Data</button>
				<?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>
<!-- Notifikasi gelembung untuk data No Result -->
<?php if (!empty($noResultData)): ?>
    <div id="noResultNotification" class="no-result-bubble">
        <button id="closeNoResultBtn" class="close-btn">
            <span>&#10006;</span> <!-- Tombol close berupa X -->
        </button>
        <p><b>Data Tidak Ditemukan:</b></p>
        <ul>
            <?php foreach ($noResultData as $data): ?>
                <li><?= htmlspecialchars($data) ?></li>
            <?php endforeach; ?>
        </ul>
        <button id="copyNoResultBtn">📋 Salin Semua Data</button>
    </div>
<?php endif; ?>


<!-- 🔔 Popup Peringatan -->
<div id="warningPopup" class="popup-warning" style="display: none;">
  ⚠️ <span id="warningText">Silakan pilih gudang terlebih dahulu!</span>
</div>
<!-- === Popup Loading Bubble Realtime === -->
<div id="loadingPopup" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
  <div style="background:#fff; padding:30px 40px; border-radius:20px; text-align:center; width:300px;">
    <h3 id="loadingText">Menyiapkan proses...</h3>
    <div style="width:100%; height:10px; background:#ddd; border-radius:10px; overflow:hidden;">
      <div id="progressBar" style="height:100%; width:0%; background:linear-gradient(90deg,#4CAF50,#8BC34A); transition:width .3s;"></div>
    </div>
    <p id="progressPercent" style="margin-top:10px;">0%</p>
  </div>
</div>

<script>
function toggleData(id, btnElement) {
    const el = document.getElementById('data-' + id);
    if (!el) return;

    const isHidden = el.style.display === 'none' || el.style.display === '';
    el.style.display = isHidden ? 'block' : 'none';
    el.style.animation = isHidden ? 'fadeIn 0.4s ease' : '';
    btnElement.textContent = isHidden ? 'Sembunyikan' : 'Tampilkan 1 Data';
}

// 🔹 Listener universal agar tetap berfungsi di mobile
document.addEventListener('click', function(e) {
    if (e.target && e.target.matches('button[data-toggle]')) {
        const id = e.target.getAttribute('data-toggle');
        toggleData(id, e.target);
    }
});




document.getElementById('updateForm')?.addEventListener('submit', function() {
    const select = document.getElementById('wrhsname');
    const wrhsname  = select.value;
    const wrhscode  = select.options[select.selectedIndex].dataset.wrhscode || '';
    const wrhsrunid = select.options[select.selectedIndex].dataset.wrhsrunid || '';
    const wrhsid    = select.options[select.selectedIndex].dataset.wrhsid || '';

    document.querySelectorAll('input[name="wrhsname[]"]').forEach(el => el.value = wrhsname);
    document.querySelectorAll('input[name="wrhscode[]"]').forEach(el => el.value = wrhscode);
    document.querySelectorAll('input[name="wrhsrunid[]"]').forEach(el => el.value = wrhsrunid);
    document.querySelectorAll('input[name="wrhsid[]"]').forEach(el => el.value = wrhsid);
});

// === COPY NO RESULT ===
document.getElementById('copyNoResultBtn')?.addEventListener('click', function() {
    const noResultItems = Array.from(document.querySelectorAll('#noResultNotification ul li'))
        .map(li => li.textContent.trim())
        .join('\n');

    // Fallback untuk browser tanpa dukungan clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(noResultItems).then(() => {
            showCopySuccessNotification();
            if (navigator.vibrate) navigator.vibrate(50); // 🔸 Vibrate di HP
        }).catch(err => {
            console.error('Clipboard gagal:', err);
            fallbackCopyText(noResultItems);
        });
    } else {
        fallbackCopyText(noResultItems);
    }
});

// === FALLBACK COPY (untuk iOS Safari lama) ===
function fallbackCopyText(text) {
    const tempInput = document.createElement('textarea');
    tempInput.value = text;
    tempInput.style.position = 'fixed';
    tempInput.style.opacity = '0';
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    showCopySuccessNotification();
    if (navigator.vibrate) navigator.vibrate(50);
}

// === POPUP NOTIFIKASI BERHASIL ===
function showCopySuccessNotification() {
    const popup = document.createElement('div');
    popup.className = 'no-result-bubble show';
    popup.innerHTML = `<p>✅ Data No Result berhasil disalin!</p>`;
    document.body.appendChild(popup);

    // Efek fade in/out
    popup.style.opacity = '0';
    setTimeout(() => popup.style.opacity = '1', 50);

    setTimeout(() => {
        popup.classList.add('fade-out');
        popup.style.opacity = '0';
        setTimeout(() => popup.remove(), 400);
    }, 2500);
}

// === CLOSE BUTTON HANDLER ===
document.getElementById('closeNoResultBtn')?.addEventListener('click', function() {
    const notification = document.getElementById('noResultNotification');
    if (!notification) return;

    notification.classList.add('fade-out');
    setTimeout(() => {
        notification.style.display = 'none';
    }, 400);
});

document.addEventListener('DOMContentLoaded', function() {
    const updateForm = document.getElementById('updateForm');
    if (!updateForm) return; // aman jika form belum ada

    updateForm.addEventListener('submit', function (e) {
        const wrhsSelect = document.getElementById('wrhsname');
        const warningPopup = document.getElementById('warningPopup');

        if (!wrhsSelect.value) {
            e.preventDefault();
            warningPopup.style.display = 'block';
            setTimeout(() => warningPopup.style.display = 'none', 3000);
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const wrhsSelect = document.getElementById('wrhsname');
    if (!wrhsSelect) return; // aman kalau elemen belum ada

    wrhsSelect.addEventListener('change', function() {
        const option = this.selectedOptions[0];
        document.getElementById('wrhscode').value = option.dataset.wrhscode || '';
        document.getElementById('wrhsrunid').value = option.dataset.wrhsrunid || '';
        document.getElementById('wrhsid').value = option.dataset.wrhsid || '';
    });
});
// ======= Helpers: simpan & restore form state =======
function saveFormState(storageKey = 'dashboardFormState') {
    try {
        const inputs = {};
        document.querySelectorAll("input, select, textarea").forEach(el => {
            if (el.name || el.id) {
                inputs[el.name || el.id] = el.value;
            }
        });
        localStorage.setItem(storageKey, JSON.stringify(inputs));
    } catch(e) { console.error('saveFormState error', e); }
}

function restoreFormState(storageKey = 'dashboardFormState') {
    try {
        const saved = localStorage.getItem(storageKey);
        if (!saved) return;
        const data = JSON.parse(saved);
        for (const key in data) {
            const el = document.querySelector(`[name="${key}"]`) || document.getElementById(key);
            if (el) el.value = data[key];
        }
        // Hapus cache setelah restore
        localStorage.removeItem(storageKey);
    } catch(e) { console.error('restoreFormState error', e); }
}

// ======= Simpan current URL & optionally form state ketika user akan buka halaman update =======
// Gunakan ini saat klik tombol yang membawa ke update page.
// Contoh: sebelum form submit atau sebelum window.open(), panggil setReturnInfo().
function setReturnInfo() {
    try {
        // Simpan current URL agar update_baleno bisa kembali ke sini
        sessionStorage.setItem('dashboardReturnUrl', window.location.href);

        // Simpan form state (agar dapat dipulihkan setelah reload)
        saveFormState('dashboardFormState');
    } catch(e) { console.error('setReturnInfo error', e); }
}

// Jika kamu gunakan <a href="update_batch.php"> atau form submit,
// panggil setReturnInfo() pada onclick atau onsubmit.
// Contoh: <button onclick="setReturnInfo(); location.href='update_batch.php'">Update</button>

// ======= Partial refresh function (sesuaikan kebutuhanmu) =======
function refreshResultSection() {
    const resultContainer = document.getElementById('resultContainer');
    if (!resultContainer) return;

    // Kita fetch ulang halaman lalu ambil #resultContainer dari respon
    fetch(window.location.href, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(res => res.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newResult = doc.getElementById('resultContainer');
        if (newResult) {
            resultContainer.innerHTML = newResult.innerHTML;
            console.log('Result section refreshed (partial).');
        } else {
            console.warn('Tidak menemukan #resultContainer pada fetch result.');
        }
    })
    .catch(err => console.error('refreshResultSection error', err));
}

// ======= Terima pesan dari popup (postMessage) =======
window.addEventListener('message', (event) => {
    // (!) Untuk keamanan, jika perlu cek event.origin
    if (!event.data) return;
    if (event.data.action === 'refreshResultData') {
        console.log('Received refreshResultData from popup.');
        refreshResultSection();
    }
});

// ======= Saat load, kalau ada returnUrl flag (artinya redirect dilakukan), restore form state then partial refresh =======
window.addEventListener('load', () => {
    // Restore form fields if saved
    restoreFormState('dashboardFormState');

    // Jika ada flag bahwa update page meminta refresh via sessionStorage
    if (sessionStorage.getItem('forcePartialRefresh') === '1') {
        sessionStorage.removeItem('forcePartialRefresh');
        console.log('Detected forcePartialRefresh -> running partial refresh.');
        refreshResultSection();
    }
});

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('updateForm');
  if (!form) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault(); // 🔒 Hentikan form agar tidak redirect

    const popup = document.getElementById('loadingPopup');
    const text  = document.getElementById('loadingText');
    const bar   = document.getElementById('progressBar');
    const pct   = document.getElementById('progressPercent');

    // 🔹 Tampilkan popup loading
    popup.style.display = 'flex';
    text.textContent = 'Menyiapkan data...';
    bar.style.width = '0%';
    pct.textContent = '0%';

    const formData = new FormData(form);

    try {
      // Kirim data ke update_batch.php (stream mode)
      const response = await fetch('update_batch.php', {
        method: 'post',
        body: formData,
      });

      if (!response.ok) throw new Error('Gagal mengirim data ke server');

      const reader = response.body.getReader();
      const decoder = new TextDecoder('utf-8');
      let buffer = '';

      // 🔄 Baca data streaming realtime
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        // Pisahkan berdasarkan newline (tiap progress dikirim seperti "data: {...}\n\n")
        const parts = buffer.split('\n\n');
        buffer = parts.pop(); // simpan sisa data yang belum lengkap

        for (const part of parts) {
          if (part.startsWith('data:')) {
            const jsonString = part.replace('data:', '').trim();
            try {
              const data = JSON.parse(jsonString);

              // Update progress bar & teks
              bar.style.width = data.percent + '%';
              pct.textContent = data.percent + '%';
              text.textContent = data.message;

              // Jika sudah selesai (100%)
              if (data.percent >= 100) {
                setTimeout(() => {
                  popup.style.display = 'none';

                  const bubble = document.createElement('div');
                  bubble.textContent = data.message;

                  // 🎨 Tentukan warna berdasarkan isi pesan
                  let bgColor = 'rgba(46, 204, 113, 0.95)'; // hijau default (sukses)
                  if (data.message.includes('⚠️')) {
                    bgColor = 'rgba(241, 196, 15, 0.95)'; // kuning peringatan
                  } else if (data.message.includes('❌')) {
                    bgColor = 'rgba(231, 76, 60, 0.95)'; // merah error
                  }

                  Object.assign(bubble.style, {
                    position: 'fixed',
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%)',
                    background: bgColor,
                    color: '#fff',
                    padding: '20px 30px',
                    borderRadius: '25px',
                    fontSize: '18px',
                    fontWeight: 'bold',
                    boxShadow: '0 4px 10px rgba(0,0,0,0.2)',
                    zIndex: '9999',
                    opacity: '0',
                    transition: 'opacity 0.4s ease',
                  });
                  document.body.appendChild(bubble);

                  // Fade in
                  setTimeout(() => (bubble.style.opacity = '1'), 100);

                  // Hilang & reload
                  setTimeout(() => {
                    bubble.style.opacity = '0';
                    setTimeout(() => {
                      bubble.remove();
                      window.location.reload();
                    }, 400);
                  }, 2000);
                }, 800);
              }
            } catch (err) {
              console.error('Parse error:', err, part);
            }
          }
        }
      }
    } catch (err) {
      console.error(err);
      text.textContent = '❌ Terjadi kesalahan, coba lagi.';
      bar.style.background = '#f44336';
      setTimeout(() => (popup.style.display = 'none'), 2000);
    }

    return false; // ⛔ Pastikan tidak redirect
  });
});
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("form");
  const textarea = form?.querySelector("textarea[name='cari']");
  if (!form || !textarea) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const rawInput = textarea.value.trim();
    if (!rawInput) return alert("Masukkan kata kunci terlebih dahulu!");

    const keywords = rawInput.split(/[\n,]+/).map(k => k.trim()).filter(Boolean);
    if (keywords.length === 0) return alert("Masukkan minimal satu kata kunci!");

    // === Popup loading elegan ===
    const popup = document.createElement("div");
    popup.innerHTML = `
      <div id="popupOverlay" style="
        position:fixed;inset:0;
        background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);
        display:flex;align-items:center;justify-content:center;
        z-index:9999;font-family:'Segoe UI',Roboto,sans-serif;">
        <div id="popupBox" style="
          background:#fff;padding:35px 45px;border-radius:20px;
          width:340px;text-align:center;
          box-shadow:0 8px 20px rgba(0,0,0,0.2);
          transition:transform 0.4s ease;">
          <div id="popupIcon" style="font-size:38px;margin-bottom:8px;">🔍</div>
          <div id="popupText" style="
            font-size:17px;font-weight:600;color:#333;margin-bottom:15px;">
            Mempersiapkan pencarian...
          </div>
          <div style="
            width:100%;height:12px;background:#e5e5e5;border-radius:20px;
            overflow:hidden;margin-top:10px;">
            <div id="popupBar" style="
              width:0%;height:100%;
              background:linear-gradient(90deg,#007bff,#4ca1ff);
              transition:width 0.35s ease,background 0.35s ease;"></div>
          </div>
          <div id="popupPercent" style="
            margin-top:10px;font-size:14px;color:#555;">0%</div>
        </div>
      </div>`;
    document.body.appendChild(popup);

    const bar = document.getElementById("popupBar");
    const text = document.getElementById("popupText");
    const percentText = document.getElementById("popupPercent");
    const icon = document.getElementById("popupIcon");

    const total = keywords.length;
    let percent = 0;

    // Simulasikan progress
    const simulate = async () => {
      for (let i = 0; i < total; i++) {
        const keyword = keywords[i];
        const nextPercent = Math.round(((i + 1) / total) * 100);

        text.innerHTML = `Mencari data: <b>${keyword}</b> (${i + 1}/${total})`;
        icon.textContent = "⏳";

        while (percent < nextPercent) {
          percent++;
          bar.style.width = percent + "%";
          percentText.textContent = percent + "%";
          if (percent >= 80) {
            bar.style.background = "linear-gradient(90deg,#28a745,#6fdc6f)";
          }
          await new Promise(r => setTimeout(r, 25 + Math.random() * 40));
        }

        // jeda antar keyword
        await new Promise(r => setTimeout(r, 400 + Math.random() * 800));
      }

      // Selesai
      percent = 100;
      bar.style.width = "100%";
      bar.style.background = "linear-gradient(90deg,#28a745,#6fdc6f)";
      percentText.textContent = "100%";
      text.textContent = "✅ Pencarian selesai, menampilkan hasil...";
      icon.textContent = "🎉";

      // Tunggu sebentar, lalu redirect pakai GET
      setTimeout(() => {
        popup.style.opacity = "0";
        popup.style.transition = "opacity 0.4s ease";

        // Encode pencarian ke URL
        const query = encodeURIComponent(rawInput);
        const baseUrl = window.location.pathname; // tanpa query lama
        const newUrl = `${baseUrl}?cari=${query}`;

        setTimeout(() => {
          window.location.href = newUrl;
        }, 400);
      }, 700);
    };

    await simulate();
  });
});

</script>


</body>
</html>


