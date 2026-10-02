<?php
// popup_result.php
// Pastikan variabel berikut sudah dikirim dari process_upload.php
// $success (boolean) dan $countUpdated (int)
?>

<?php
session_start();
$success = $_SESSION['update_success'] ?? false;
$countUpdated = $_SESSION['count_updated'] ?? 0;

// Hapus session supaya tidak muncul lagi saat refresh
unset($_SESSION['update_success'], $_SESSION['count_updated']);
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
function kembali(){
    const box = document.getElementById('popupBox');
    box.classList.add('fade-out');
    setTimeout(()=>window.history.back(),400);
}
setTimeout(()=>kembali(),3000);
</script>
</body>
</html>
