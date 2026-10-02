<?php
// accounts_action.php - Action handler for Cash/Bank Accounts
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'add') {
    $name = $_POST['AccountName'] ?? '';
    $balance = $_POST['InitialBalance'] ?? 0;
    $sponsorId = !empty($_POST['SponsorId']) ? $_POST['SponsorId'] : null;

    if (empty($name)) {
        $_SESSION['error'] = "Nama akun tidak boleh kosong!";
        header('Location: accounts.php');
        exit;
    }

    $sql = "INSERT INTO fin_cash_accounts (AccountName, SponsorId, CurrentBalance, CreatedBy, UpdatedBy) VALUES (?, ?, ?, ?, ?)";
    $params = [$name, $sponsorId, $balance, $userId, $userId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Akun kas berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambah akun.";
    }
} 
elseif ($action == 'edit') {
    $accountId = $_POST['AccountId'] ?? '';
    $name = $_POST['AccountName'] ?? '';
    $sponsorId = !empty($_POST['SponsorId']) ? $_POST['SponsorId'] : null;

    if (empty($accountId) || empty($name)) {
        $_SESSION['error'] = "Data tidak lengkap!";
        header('Location: accounts.php');
        exit;
    }

    $sql = "UPDATE fin_cash_accounts SET AccountName = ?, SponsorId = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE AccountId = ?";
    $params = [$name, $sponsorId, $userId, $accountId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Informasi akun berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui akun.";
    }
}
elseif ($action == 'topup') {
    $accountId = $_POST['AccountId'] ?? '';
    $amount = $_POST['Amount'] ?? 0;
    $note = $_POST['Note'] ?? 'Top-up Saldo';

    if (empty($accountId) || $amount <= 0) {
        $_SESSION['error'] = "Data top-up tidak valid!";
        header('Location: accounts.php');
        exit;
    }

    if (sqlsrv_begin_transaction($conn) === false) {
        $_SESSION['error'] = "Gagal memulai transaksi DB.";
        header('Location: accounts.php');
        exit;
    }

    try {
        // 1. Update Balance
        $sql1 = "UPDATE fin_cash_accounts SET CurrentBalance = CurrentBalance + ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE AccountId = ?";
        $stmt1 = sqlsrv_query($conn, $sql1, [$amount, $userId, $accountId]);

        // 2. Log to Ledger
        $sql2 = "INSERT INTO fin_ledger (AccountId, Type, Amount, Description, CreatedBy, UpdatedBy) VALUES (?, 'IN', ?, ?, ?, ?)";
        $stmt2 = sqlsrv_query($conn, $sql2, [$accountId, $amount, $note, $userId, $userId]);

        if ($stmt1 && $stmt2) {
            sqlsrv_commit($conn);
            $_SESSION['success'] = "Top-up saldo berhasil!";
        } else {
            sqlsrv_rollback($conn);
            $_SESSION['error'] = "Gagal memproses top-up.";
        }
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
header('Location: accounts.php');
exit;

