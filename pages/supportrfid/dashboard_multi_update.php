<?php
require 'vendor/autoload.php';
require 'auth_check.php';
use MongoDB\Client;


ini_set('max_execution_time', 3000); // 300 detik = 5 menit
// 🔧 MongoDB Connection
$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$db = $client->api_sum;

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/includes/access_helper.php';

$username = $_SESSION['username'];
$groupId = getUserGroup($username);

// 🔐 Cek hak akses ke halaman (contoh: update_multi)
if (!canAccessMenu($groupId, 'updatemultimenu')) {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit;
}

// === VARIABEL DASAR ===
$cari = $_GET['cari'] ?? '';
$dataGabungan = [];
$selectedCollections = $_GET['collections'] ?? [];
if (!is_array($selectedCollections)) $selectedCollections = [$selectedCollections];

// === DETEKSI MODE OTOMATIS ===
$autoMode = 'baleno';
if (!empty($cari)) {
    $firstLine = explode("\n", trim($cari))[0];
    $firstLine = trim($firstLine);

    if (preg_match('/^\d+\.\d+$/', $firstLine)) {
        $autoMode = 'packingno';
    } elseif (preg_match('/[A-Za-z]\d{2,}/', $firstLine) && substr_count($firstLine, '.') >= 2) {
        $autoMode = 'batchno';
    } else {
        $autoMode = 'baleno';
    }
}

// === PROSES CARI DATA ===
if ($cari && !empty($selectedCollections)) {
    $cari = str_replace(';', ',', $cari);  // Mengganti ; dengan koma
    $keywords = preg_split('/[\n,]+/', trim($cari));  // Memisahkan kata kunci berdasarkan baris atau koma
    $keywords = array_filter(array_map('trim', $keywords));  // Menghapus spasi dan kata kosong

    foreach ($keywords as $kata) {
        $found = [];
        
        // Membuat regex dengan word boundaries \b dan case-insensitive (i flag)
        $regexPattern = "\\b" . preg_quote($kata, '/') . "\\b";

        foreach ($selectedCollections as $coll) {
            $collection = $db->$coll;
            $cursor = $collection->find([
                '$or' => [
                    ['baleno' => new MongoDB\BSON\Regex($regexPattern, 'i')],  // Regex untuk baleno (case-insensitive)
                    ['batchno' => new MongoDB\BSON\Regex($regexPattern, 'i')], // Regex untuk batchno (case-insensitive)
                    ['packingno' => new MongoDB\BSON\Regex($regexPattern, 'i')] // Regex untuk packingno (case-insensitive)
                ]
            ]);
            
            // Mengumpulkan hasil pencarian dari setiap koleksi
            foreach ($cursor as $doc) {
                $found[] = $doc;
            }
        }

        // Menambahkan data gabungan untuk keyword dan hasil yang ditemukan
        $dataGabungan[] = [
            'keyword' => $kata,
            'jumlah' => count($found),
            'contoh' => $found[0] ?? null  // Menyimpan contoh data pertama jika ada
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Multi Collection Update</title>


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

.label-title {
  font-size: 15px;
  color: #0d47a1;
  display: block;
  margin-bottom: 8px;
}

.collection-checkboxes {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
}

.collection-option {
  position: relative;
  background: #f5f9ff;
  border: 1px solid #bbdefb;
  border-radius: 8px;
  padding: 8px 14px 8px 38px;
  font-size: 14px;
  color: #1565c0;
  cursor: pointer;
  user-select: none;
  transition: all 0.25s ease;
  display: inline-flex;
  align-items: center;
}

.collection-option:hover {
  background: #e3f2fd;
  transform: translateY(-2px);
  box-shadow: 0 3px 6px rgba(21,101,192,0.15);
}

.collection-option input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.checkmark {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  height: 16px;
  width: 16px;
  border: 2px solid #64b5f6;
  border-radius: 4px;
  background-color: #fff;
  transition: all 0.25s ease;
}

.collection-option input:checked ~ .checkmark {
  background-color: #2196f3;
  border-color: #1e88e5;
}

.checkmark:after {
  content: "";
  position: absolute;
  display: none;
}

.collection-option input:checked ~ .checkmark:after {
  display: block;
}

.collection-option .checkmark:after {
  left: 4px;
  top: 0px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.option-text {
  font-weight: 500;
}

@media (max-width: 600px) {
  .collection-checkboxes {
    flex-direction: column;
    gap: 8px;
  }
}

.field-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.field-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.25s ease;
}

.field-input:focus {
    border-color: #42a5f5;
    box-shadow: 0 0 5px rgba(66,165,245,0.4);
    outline: none;
}

/* --- Area checkbox di kanan --- */
.checkbox-right {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

/* Label di atas checkbox */
.checkbox-label {
    font-size: 12px;
    color: #1565c0;
    font-weight: 500;
    text-align: center;
}

/* --- Checkbox modern style --- */
.checkbox-right input[type="checkbox"] {
    appearance: none;
    width: 20px;
    height: 20px;
    border: 2px solid #64b5f6;
    border-radius: 5px;
    background-color: #fff;
    cursor: pointer;
    position: relative;
    transition: all 0.25s ease;
}

.checkbox-right input[type="checkbox"]:hover {
    background-color: #e3f2fd;
    transform: scale(1.1);
    box-shadow: 0 2px 6px rgba(21,101,192,0.15);
}

/* Saat dicentang */
.checkbox-right input[type="checkbox"]:checked {
    background-color: #2196f3;
    border-color: #1e88e5;
}

/* Tanda centang putih */
.checkbox-right input[type="checkbox"]:checked::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* --- Responsive --- */
@media (max-width: 600px) {
    .field-wrapper {
        flex-direction: column;
        align-items: flex-start;
    }
    .checkbox-right {
        flex-direction: row;
        justify-content: flex-start;
        gap: 8px;
    }
    .checkbox-label {
        margin-bottom: 0;
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
    padding: 10px 0;
    font-size: 14px;
    display: flex;
    flex-wrap: wrap;  /* Mengatur agar elemen-elemen bisa dibungkus */
    align-items: center;
    gap: 8px;
}

/* Styling untuk keyword */
.keyword {
    font-weight: bold;
    font-size: 16px;
    
}

/* Styling untuk separator (Collection) */
.separator {
    font-size: 14px;
    color: #333;
}

/* Styling untuk collections */
.collections {
    font-size: 14px;
    color: #1976d2;
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


#globalInputModal {
    position: fixed;
    top: 60px;
    left: 50%;
    transform: translateX(-50%) translateY(-20px);
    background: #fff;
    border: 1px solid #ccc;
    padding: 20px 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.25);
    z-index: 1000;
    width: 90%;
    max-width: 600px;
    max-height: calc(100vh - 80px);
    border-radius: 8px;
    font-family: Arial, sans-serif;
    overflow-y: auto;

    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.3s ease, transform 0.3s ease;
  }

  #globalInputModal.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0);
  }

  #globalInputModal h3 {
    margin-top: 0;
    margin-bottom: 15px;
  }

  .global-input-grid {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 10px 20px;
    align-items: center;
  }

  .global-input-grid label {
    font-weight: bold;
    text-transform: capitalize;
  }

  .global-input-grid input[type="text"] {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color 0.2s;
  }

  .global-input-grid input[type="text"]:focus {
    border-color: #007BFF;
    outline: none;
  }

  .modal-buttons {
    margin-top: 20px;
    text-align: right;
  }

  .modal-buttons button {
    background-color: #007BFF;
    color: white;
    border: none;
    padding: 8px 14px;
    margin-left: 10px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.25s;
  }

  .modal-buttons button:hover {
    background-color: #0056b3;
  }

  .modal-buttons #closeGlobalInputBtn {
    background-color: #6c757d;
  }

  .modal-buttons #closeGlobalInputBtn:hover {
    background-color: #5a6268;
  }

  #modalOverlay {
    position: fixed;
    top:0; left:0; right:0; bottom:0;
    background: rgba(0,0,0,0.4);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.3s ease;
  }

  #modalOverlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
  }
  #popupSuccess {
  background: linear-gradient(90deg, #4caf50, #81c784);
}
#popupSuccess::before {
  content: '✔'; /* check icon */
  font-weight: bold;
  font-size: 16px;
}

.btn-edit-global {
  background-color: #ffc107; /* warna kuning */
  border: none;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  display: block;
  margin: 0 auto 15px auto; /* tengah + jarak bawah */
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  transition: background-color 0.3s ease;
}

.btn-edit-global:hover {
  background-color: #e0a800; /* lebih gelap saat hover */
}

</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="content">
<div class="container">
<h2>🛠️ Update Data Multi Collection</h2>
<!-- 🔍 FORM FILTER -->
<form method="get" style="margin-bottom:20px;" onsubmit="return validateCollections()">
   <label class="label-title"><b>Pilih Collection:</b></label>
   <div class="collection-checkboxes">
      <label class="collection-option">
        <input type="checkbox" name="collections[]" value="picking_process"
          <?= in_array('picking_process', $selectedCollections) ? 'checked' : '' ?>>
        <span class="checkmark"></span>
        <span class="option-text">Picking_Process</span>
      </label>

      <label class="collection-option">
        <input type="checkbox" name="collections[]" value="cp_batch_transfer_lists"
          <?= in_array('cp_batch_transfer_lists', $selectedCollections) ? 'checked' : '' ?>>
        <span class="checkmark"></span>
        <span class="option-text">Cp_Batch_Transfer_Lists</span>
      </label>
   </div>

   <label><b>Masukkan Baleno / Batchno / Packingno</b></label>
   <textarea name="cari" rows="3" placeholder="Pisahkan dengan koma atau baris baru"><?= htmlspecialchars($cari ?? '') ?></textarea>
   <button type="submit">🔍 Cari</button>
</form>

<!-- 🔔 Popup Peringatan -->
<div id="warningPopup" class="popup-warning">
  ⚠️ <span id="warningText">Silakan pilih minimal satu collection terlebih dahulu!</span>
</div>

<?php if ($cari && !empty($dataGabungan)) { ?>
    <hr>

<!-- === FORM UPDATE === -->
<form action="update_multi.php" method="post" id="updateForm">
    <input type="hidden" name="collections" value="<?= htmlspecialchars(implode(',', $selectedCollections)) ?>">
    <input type="hidden" name="update_mode" value="<?= htmlspecialchars($autoMode) ?>">

    <!-- Mode Update Berdasarkan -->
    <label for="update_mode"><b>Mode Update Berdasarkan</b></label>
    <select id="update_mode" name="update_mode" required>
        <option value="baleno" <?= ($autoMode == 'baleno') ? 'selected' : '' ?>>Baleno</option>
        <option value="batchno" <?= ($autoMode == 'batchno') ? 'selected' : '' ?>>Batch No</option>
        <option value="packingno" <?= ($autoMode == 'packingno') ? 'selected' : '' ?>>Packing No</option>
    </select>

<button type="button" id="openGlobalInputBtn" class="btn-edit-global" style="background: #ffc107;">
  ✏️ Edit Semua Data
</button>



    <!-- Menampilkan Hasil Pencarian -->
    <?php 
    $noResultData = []; // Menyimpan data no result
    foreach ($dataGabungan as $item) { 
        $uid = md5($item['keyword']);
        $displayKeyword = trim($item['keyword']) ?: '(tidak ada keyword)';
        $foundInCollections = [];
        $notFoundInCollections = [];

        // Memeriksa setiap koleksi yang dipilih
        foreach ($selectedCollections as $coll) {
            $collection = $db->$coll;
            
            // Gunakan regex agar hasil pencarian konsisten dengan pencarian utama
            $regexPattern = new MongoDB\BSON\Regex("\\b" . preg_quote($displayKeyword, '/') . "\\b", 'i');
            
            $cursor = $collection->find([
                '$or' => [
                    ['baleno' => $regexPattern],
                    ['batchno' => $regexPattern],
                    ['packingno' => $regexPattern]
                ]
            ]);
            
            $resultArray = iterator_to_array($cursor);
            if (count($resultArray) > 0) {
                $foundInCollections[] = $coll;
            } else {
                $notFoundInCollections[] = $coll;
            }
        }

        // Jika data tidak ditemukan di koleksi manapun, atau hanya di beberapa koleksi, simpan ke noResultData
        if (!empty($notFoundInCollections)) {
            $noResultData[] = [
                'keyword' => $displayKeyword, 
                'collections' => $notFoundInCollections  // Koleksi yang tidak menemukan data
            ];
        }
    ?>
    <div class="caption <?= ($item['jumlah'] == 0) ? 'no-result-theme' : '' ?>">
        <?php if ($item['jumlah'] > 0) { ?>
            🔹 <b><?= htmlspecialchars($displayKeyword) ?></b>
            <span>(<?= $item['jumlah'] ?> data ditemukan)</span>
            <button type="button" onclick="toggleData('<?= $uid ?>')">Tampilkan 1 Data</button>
        <?php } else { ?>
            🔴 <b><?= htmlspecialchars($displayKeyword) ?></b>
            <span class="no-result">No result</span>
        <?php } ?>
    </div>

    <!-- Menampilkan Data -->
    <?php 
    if ($item['contoh']) { 
        $contoh = (array) $item['contoh'];
        $fields = ['batchno', 'transferinbale', 'transferinitem', 'transferoutbale', 'transferoutitem', 'baleno', 'packingno'];
        if (in_array('picking_process', $selectedCollections) && count($selectedCollections) == 1) {
            $fields[] = 'status';
            $fields[] = 'balehdid'; 
        }
    ?>
    <div id="data-<?= $uid ?>" style="display:none;margin-top:10px;">
        <table>
            <?php foreach ($fields as $field) { ?>
            <tr>
                <td style="width:35%;"><b><?= htmlspecialchars($field) ?></b></td>
                <td style="vertical-align: middle;">
                    <div class="field-wrapper">
                        <input type="text" 
                               name="<?= $field ?>[<?= htmlspecialchars($uid) ?>]" 
                               value="<?= htmlspecialchars($contoh[$field] ?? '') ?>" 
                               class="field-input"
                               data-field="<?= htmlspecialchars($field) ?>"
                               data-uid="<?= htmlspecialchars($uid) ?>">

                        <div class="checkbox-right">
                            <span class="checkbox-label">Edit</span>
                            <input type="checkbox" 
                                   name="update_field[<?= htmlspecialchars($uid) ?>][]" 
                                   value="<?= $field ?>"
                                   class="edit-checkbox"
                                   data-field="<?= htmlspecialchars($field) ?>"
                                   data-uid="<?= htmlspecialchars($uid) ?>">
                        </div>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </table>
        <input type="hidden" name="keyword[]" value="<?= htmlspecialchars($item['keyword']) ?>">
    </div>
    <?php } // endif contoh ?>
    <?php } // endforeach dataGabungan ?>
    
    <button type="submit" class="update-btn">💾 Update Semua Data</button>
</form>

<!-- Notifikasi gelembung untuk data No Result -->
<?php if (!empty($noResultData)) { ?>
<div id="noResultNotification" class="no-result-bubble">
    <button id="closeNoResultBtn" class="close-btn">
        <span>&#10006;</span> <!-- Tombol close -->
    </button>
    <p><b>Data Tidak Ditemukan:</b></p>
    <ul>
        <?php foreach ($noResultData as $data) { ?>
            <li>
                <span class="keyword"><b><?= htmlspecialchars($data['keyword']) ?></b></span> 
                <span class="separator">- Collection -</span>
                <span class="collections"><?= implode(' | ', $data['collections']) ?></span>
            </li>
        <?php } ?>
    </ul>
    <button id="copyNoResultBtn">📋 Salin Semua Data</button>
</div>
<?php } ?>

<?php } // endif $cari && !empty($dataGabungan) ?>

</div>
</div>
<div id="globalInputModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <h3 id="modalTitle">Edit Semua Data</h3>
  <div class="global-input-grid">
    <?php 
    $globalFields = ['batchno', 'transferinbale', 'transferinitem', 'transferoutbale', 'transferoutitem', 'baleno', 'packingno'];
    if (in_array('picking_process', $selectedCollections) && count($selectedCollections) == 1) {
        $globalFields[] = 'status';
        $globalFields[] = 'balehdid';
    }
    foreach ($globalFields as $gf) {
        echo '<label for="global_'.$gf.'">'.htmlspecialchars($gf).'</label>';
        echo '<input type="text" id="global_'.$gf.'" name="global_'.$gf.'">';
    }
    ?>
  </div>
  <div class="modal-buttons">
    <button type="button" id="applyGlobalValues">Terapkan</button>
    <button type="button" id="closeGlobalInputBtn" style="background: #f44336;">Tutup</button>
	<div id="popupWarning" class="popup-warning" role="alert" aria-live="assertive" aria-atomic="true">
  Tidak Ada Data Yang Diperbarui!
</div>

  </div>
</div>
<div id="popupSuccess" class="popup-warning" style="display:none; background: #4caf50;">
  Data Yang Diisi Berhasil Diterapkan Dan Dicentang Ke Semua Field
</div>

<div id="modalOverlay"></div>

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
// Fungsi untuk menampilkan/menyembunyikan data berdasarkan ID
function toggleData(id){
    const el = document.getElementById('data-' + id);
    const btn = event.target;
    if (!el) return;
    const show = el.style.display === 'none' || el.style.display === ''; 
    el.style.display = show ? 'block' : 'none';
    el.style.animation = show ? 'fadeIn 0.4s' : '';
    btn.textContent = show ? 'Sembunyikan' : 'Tampilkan 1 Data';
}

// Validasi Koleksi
function validateCollections() {
  const checkboxes = document.querySelectorAll('input[name="collections[]"]:checked');
  if (checkboxes.length === 0) {
    showWarning("Silakan pilih minimal satu collection sebelum mencari data!");
    return false; // hentikan submit
  }
  return true;
}

// Menampilkan peringatan jika koleksi tidak dipilih
function showWarning(msg) {
  const popup = document.getElementById("warningPopup");
  const text = document.getElementById("warningText");
  text.textContent = msg;
  popup.style.display = "block";
  popup.classList.remove("fadeOut");

  setTimeout(() => {
    popup.classList.add("fadeOut");
    setTimeout(() => popup.style.display = "none", 400);
  }, 2500);
}

// === FUNGSI SALIN YANG KOMPATIBEL SEMUA PERANGKAT ===
document.getElementById('copyNoResultBtn')?.addEventListener('click', function() {
    const noResultText = Array.from(document.querySelectorAll('#noResultNotification ul li'))
        .map(li => li.querySelector('b').textContent)
        .join('\n');

    // Jika clipboard API didukung dan halaman aman (https)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(noResultText).then(() => {
            showCopySuccessNotification();
        }).catch(err => {
            console.warn("Clipboard API gagal, fallback digunakan:", err);
            fallbackCopyText(noResultText);
        });
    } else {
        // Gunakan metode fallback untuk HTTP atau mobile browser lama
        fallbackCopyText(noResultText);
    }
});

// === FUNGSI FALLBACK MENGGUNAKAN TEXTAREA TERSEMBUNYI ===
function fallbackCopyText(text) {
    const tempInput = document.createElement('textarea');
    tempInput.value = text;
    tempInput.style.position = 'fixed';
    tempInput.style.opacity = '0';
    document.body.appendChild(tempInput);
    tempInput.focus();
    tempInput.select();

    try {
        document.execCommand('copy');
        showCopySuccessNotification();
    } catch (err) {
        alert('❌ Gagal menyalin secara otomatis.\nSalin manual teks berikut:\n' + text);
    }

    document.body.removeChild(tempInput);
}

// === NOTIFIKASI BERHASIL SALIN ===
function showCopySuccessNotification() {
    const popup = document.createElement('div');
    popup.className = 'no-result-bubble';
    popup.style.backgroundColor = '#d4edda';
    popup.style.color = '#155724';
    popup.innerHTML = `<p>✅ Data berhasil disalin ke clipboard!</p>`;
    document.body.appendChild(popup);

    popup.animate(
        [{ opacity: 0, transform: 'translateY(-10px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 300, fill: 'forwards' }
    );

    setTimeout(() => {
        popup.animate(
            [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-10px)' }],
            { duration: 400, fill: 'forwards' }
        ).onfinish = () => popup.remove();
    }, 2000);
}


// Function untuk menutup notifikasi
document.getElementById('closeNoResultBtn')?.addEventListener('click', function() {
    const notification = document.getElementById('noResultNotification');
    
    // Menambahkan kelas 'fade-out' untuk animasi keluar
    notification.classList.add('fade-out');
    
    // Setelah animasi selesai, sembunyikan elemen
    setTimeout(function() {
        notification.style.display = 'none';  // Menyembunyikan notifikasi setelah animasi
    }, 400);  // Sesuaikan durasi dengan durasi animasi
});

document.addEventListener('DOMContentLoaded', () => {
  const openBtn  = document.getElementById('openGlobalInputBtn');
  const modal    = document.getElementById('globalInputModal');
  const overlay  = document.getElementById('modalOverlay');
  const closeBtn = document.getElementById('closeGlobalInputBtn');

  if (!openBtn || !modal || !overlay || !closeBtn) return;

  openBtn.addEventListener('click', () => {
    modal.classList.add('show');
    overlay.classList.add('show');
  });

  [closeBtn, overlay].forEach(el => el.addEventListener('click', () => {
    modal.classList.remove('show');
    overlay.classList.remove('show');
  }));
});


 document.getElementById('applyGlobalValues').addEventListener('click', function() {
    const globalFields = ['batchno', 'transferinbale', 'transferinitem', 'transferoutbale', 'transferoutitem', 'baleno', 'packingno', 'status', 'balehdid'];

    // Cek dulu apakah ada field yang diisi
    const anyFilled = globalFields.some(field => {
        const globalInput = document.getElementById('global_' + field);
        return globalInput && globalInput.value.trim() !== '';
    });

    if (!anyFilled) {
    const popup = document.getElementById('popupWarning');
    if (!popup) return alert('Harap isi minimal satu nilai global sebelum menerapkan.');

    popup.style.display = 'block';
    popup.classList.remove('fadeOut');

    // Setelah 2.5 detik hilangkan popup dengan animasi fade out
    setTimeout(() => {
        popup.classList.add('fadeOut');
        popup.addEventListener('animationend', () => {
            popup.style.display = 'none';
        }, { once: true });
    }, 2500);

    return; // jangan lanjut proses apply
}


    globalFields.forEach(field => {
        const globalInput = document.getElementById('global_' + field);
        if (!globalInput) return;
        const val = globalInput.value.trim();
        if (val === '') return;

        const inputs = document.querySelectorAll(`input.field-input[data-field="${field}"]`);
        inputs.forEach(input => {
            input.value = val;

            const uid = input.getAttribute('data-uid');
            const checkbox = document.querySelector(`input.edit-checkbox[data-field="${field}"][data-uid="${uid}"]`);
            if (checkbox) checkbox.checked = true;
        });
    });

  // Tampilkan popup sukses
const popupSuccess = document.getElementById('popupSuccess');
// Tampilkan popup sukses
popupSuccess.style.display = 'block';
popupSuccess.classList.remove('fadeOut');

// Durasi tampil notifikasi dalam milidetik (misal 4000ms = 4 detik)
const displayDuration = 4000;

// Setelah durasi tampil, mulai animasi fadeOut
setTimeout(() => {
    popupSuccess.classList.add('fadeOut');

    popupSuccess.addEventListener('animationend', () => {
        popupSuccess.style.display = 'none';
        popupSuccess.classList.remove('fadeOut');
    }, { once: true });
}, displayDuration);

    modal.classList.remove('show');
    overlay.classList.remove('show');
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
         // Ambil semua checkbox yang dicentang
const selectedCollections = Array.from(document.querySelectorAll('input[name="collections[]"]:checked'))
  .map(cb => 'collections[]=' + encodeURIComponent(cb.value))
  .join('&');

// Ambil nilai cari
const keyword = document.querySelector('textarea[name="cari"]').value.trim();

// Bangun URL lengkap
const newUrl = "?cari=" + encodeURIComponent(keyword) + (selectedCollections ? "&" + selectedCollections : "");

// Redirect
window.location.href = newUrl;

        }, 400);
      }, 700);
    };

    await simulate();
  });
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
      // Kirim data ke update_multi.php (stream mode)
      const response = await fetch('update_multi.php', {
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
</script>

</body>
</html>
