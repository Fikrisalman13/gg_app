<?php
session_start();
require 'vendor/autoload.php';
use MongoDB\Client;

$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$userCollection = $client->api_sum->user;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $hashed = md5($password);

        // 🔍 Cari user berdasarkan username
        $user = $userCollection->findOne(['username' => $username]);

        if ($user) {
            // Paksa ambil password sebagai string murni
            $storedPassword = '';
            if (is_object($user['password'])) {
                // Bisa jadi BSON type -> konversi manual
                $storedPassword = json_encode($user['password']);
                $storedPassword = preg_replace('/[^a-f0-9]/', '', $storedPassword); // ambil hex saja
            } else {
                $storedPassword = (string)$user['password'];
            }

            // Debug jika masih gagal:
            // echo "HASH INPUT: $hashed<br>HASH DB: $storedPassword"; exit;


            if (trim($storedPassword) === trim($hashed)) {
                // ✅ Login sukses
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = (string)$user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                $_SESSION['wrhsname'] = $user['wrhsname'] ?? '';
                $_SESSION['wrhscode'] = $user['wrhscode'] ?? '';
                $_SESSION['wrhsid'] = $user['wrhsid'] ?? '';

                header("Location: index.php");
                exit;
            } else {
                $error = "❌ Password salah untuk user <b>{$username}</b>.";
            }
        } else {
            $error = "❌ Username <b>{$username}</b> tidak ditemukan.";
        }
    } else {
        $error = "⚠️ Harap isi semua field.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login - Web Update Gudang</title>
<style>
body {
    background: #eef2f7;
    font-family: 'Segoe UI', sans-serif;
}
.login-box {
    width: 360px;
    margin: 120px auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    padding: 25px;
}
h2 { text-align: center; color: #1565c0; }
input {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
}
button {
    width: 100%;
    background: #1976d2;
    color: white;
    border: none;
    padding: 10px;
    border-radius: 6px;
    cursor: pointer;
}
button:hover { background: #125aa5; }
.error {
    color: red;
    text-align: center;
    margin-top: 10px;
    background: #ffeaea;
    padding: 8px;
    border-radius: 6px;
}
</style>
</head>
<body>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<div class="login-box">
    <h2>🔐 Login</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required autofocus>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Masuk</button>
        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
