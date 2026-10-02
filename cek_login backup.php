<?php
session_start();
include 'koneksi.php'; // Pastikan koneksi ke SQL Server benar

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['UserName']) || !isset($_POST['UserPassword'])) {
        $_SESSION['login_error'] = "User ID atau Password tidak boleh kosong!";
        header("Location: /gg_app/login.php");
        exit();
    }

    $user_id  = trim($_POST['UserName']);
    $password = trim($_POST['UserPassword']);

    if (!$conn) {
        $_SESSION['login_error'] = "Koneksi ke database gagal!";
        header("Location: /gg_app/login.php");
        exit();
    }

    // Ambil user + nama lengkap + Theme
    $query = "SELECT u.UserId, u.UserName, u.UserPassword, u.GroupId, 
                     u.Theme, e.nama_lengkap
              FROM SMUserMs u
              LEFT JOIN m_emp e ON u.EmpId = e.id_emp
              WHERE u.UserName = ?";
    $params = array($user_id);
    $stmt   = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        $_SESSION['login_error'] = "SQL Error: " . print_r(sqlsrv_errors(), true);
        header("Location: /gg_app/login.php");
        exit();
    }

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (password_verify($password, $row['UserPassword'])) {
            $_SESSION['UserId']      = $row['UserId'];
            $_SESSION['UserName']    = $row['UserName'];
            $_SESSION['GroupId']     = $row['GroupId'];
            $_SESSION['NamaLengkap'] = $row['nama_lengkap'] ?? $row['UserName'];

            // Simpan Theme ke session (default 'primary' kalau null/kosong)
            $_SESSION['Theme'] = !empty($row['Theme']) ? $row['Theme'] : 'primary';

            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            header("Location: /gg_app/index.php");
            exit();
        } else {
            $_SESSION['login_error'] = "User ID atau Password salah!";
        }
    } else {
        $_SESSION['login_error'] = "User ID atau Password salah!";
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    header("Location: /gg_app/login.php");
    exit();
}
?>
