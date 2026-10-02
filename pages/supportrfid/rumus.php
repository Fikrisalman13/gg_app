<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include 'sidebar.php'; ?>
<title>Rumus Concatenate Otomatis ✨</title>
<style>
 
    @keyframes fadeIn {
        from {opacity: 0; transform: translateY(10px);}
        to {opacity: 1; transform: translateY(0);}
    }

    @keyframes popIn {
        from {transform: scale(0.95); opacity: 0;}
        to {transform: scale(1); opacity: 1;}
    }

    h2 {
        text-align: center;
        color: #374151;
        margin-bottom: 20px;
    }
    textarea {
        width: 100%;
        height: 180px;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        resize: vertical;
        font-family: monospace;
        transition: all 0.3s;
    }
    textarea:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
        outline: none;
    }
    .controls {
        margin-top: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
    }
    select {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        font-size: 14px;
        background: #f9fafb;
        transition: 0.3s;
    }
    select:focus {
        border-color: #6366f1;
    }
    .checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
    }

    /* Custom Toggle Checkbox */
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0;
        right: 0; bottom: 0;
        background-color: #d1d5db;
        transition: 0.4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 18px; width: 18px;
        left: 3px; bottom: 3px;
        background-color: white;
        transition: 0.4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: #4f46e5;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }

    button {
        background: #4f46e5;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 10px;
        cursor: pointer;
        transition: 0.3s;
    }
    button:hover {
        background: #4338ca;
    }
    .copy-btn {
        background: #16a34a;
    }
    .copy-btn:hover {
        background: #15803d;
    }
    .output {
        margin-top: 25px;
        animation: fadeIn 0.5s ease;
    }

    /* Toast Notification */
    #toast {
        visibility: hidden;
        min-width: 240px;
        margin-left: -120px;
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 30px;
        padding: 14px;
        position: fixed;
        z-index: 1;
        left: 50%;
        bottom: 30px;
        font-size: 15px;
        opacity: 0;
        transition: opacity 0.5s, bottom 0.5s;
    }
    #toast.show {
        visibility: visible;
        opacity: 1;
        bottom: 50px;
    }
</style>
</head>
<body>
<div class="content">
<div class="container">
    <h2>✨ Rumus Concatenate Otomatis</h2>
    <form method="post">
        <textarea name="input_text" placeholder="Masukkan daftar kode..."><?php echo htmlspecialchars($_POST['input_text'] ?? ''); ?></textarea>

        <div class="controls">
            <div>
                <label>Pilih tanda kutip:</label>
                <select name="quote_type">
                    <option value="single" <?php if(($_POST['quote_type'] ?? '')==='single') echo 'selected'; ?>>Petik tunggal (')</option>
                    <option value="double" <?php if(($_POST['quote_type'] ?? '')==='double') echo 'selected'; ?>>Petik ganda (")</option>
                </select>
            </div>

            <div class="checkbox-wrapper">
                <label>Hapus duplikat</label>
                <label class="switch">
                    <input type="checkbox" name="remove_duplicates" <?php if(!empty($_POST['remove_duplicates'])) echo 'checked'; ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <br>
        <button type="submit">Process</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = trim($_POST['input_text'] ?? '');
        $quoteType = $_POST['quote_type'] ?? 'single';
        $removeDup = !empty($_POST['remove_duplicates']);

        if ($input !== '') {
            $quote = $quoteType === 'double' ? '"' : "'";
            $lines = preg_split('/\r\n|\r|\n/', $input);
            $lines = array_filter(array_map('trim', $lines), fn($v) => $v !== '');
            if ($removeDup) {
                $lines = array_unique($lines);
            }

            $quoted = array_map(fn($v) => $quote . $v . $quote, $lines);
            $result = implode(",\n", $quoted);

            echo "<div class='output'>";
            echo "<h3>Hasil:</h3>";
            echo "<textarea id='output' readonly>$result</textarea><br>";
            echo "<button class='copy-btn' type='button' onclick=\"copyResult()\">📋 Copy</button>";
            echo "</div>";

            echo "<script>showToast('Data berhasil digabung!');</script>";
        }
    }
    ?>
</div>
</div>
<div id="toast"></div>
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
<script>
function copyResult() {
    const output = document.getElementById('output');
    output.select();
    document.execCommand('copy');
    showToast('✅ Hasil disalin ke clipboard!');
}

// Notifikasi Gelembung
function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = "show";
    setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
}
</script>

</body>
</html>
