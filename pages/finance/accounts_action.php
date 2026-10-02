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

    sqlsrv_begin_transaction($conn);
    
    $sql = "INSERT INTO fin_cash_accounts (AccountName, SponsorId, CurrentBalance, CreatedBy, UpdatedBy) VALUES (?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS AccountId;";
    $params = [$name, $sponsorId, $balance, $userId, $userId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $ledgerSuccess = true;
    if ($stmt && $balance > 0) {
        sqlsrv_next_result($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $newAccountId = $row['AccountId'];
        
        $sqlLedger = "INSERT INTO fin_ledger (AccountId, Type, Amount, Description, CreatedBy, UpdatedBy) VALUES (?, 'IN', ?, 'Saldo Awal', ?, ?)";
        $stmtLedger = sqlsrv_query($conn, $sqlLedger, [$newAccountId, $balance, $userId, $userId]);
        if (!$stmtLedger) $ledgerSuccess = false;
    }

    if ($stmt && $ledgerSuccess) {
        sqlsrv_commit($conn);
        $_SESSION['success'] = "Akun kas berhasil ditambahkan!";
    } else {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Gagal menambah akun atau mencatat saldo awal.";
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
elseif ($action == 'transfer') {
    $sourceId = $_POST['SourceAccountId'] ?? '';
    $destId = $_POST['DestAccountId'] ?? '';
    $amount = $_POST['Amount'] ?? 0;
    $note = !empty($_POST['Note']) ? $_POST['Note'] : 'Transfer Antar Akun';

    if (empty($sourceId) || empty($destId) || $amount <= 0) {
        $_SESSION['error'] = "Data transfer tidak valid!";
        header('Location: accounts.php');
        exit;
    }

    if ($sourceId == $destId) {
        $_SESSION['error'] = "Akun sumber dan tujuan tidak boleh sama!";
        header('Location: accounts.php');
        exit;
    }

    // Check balance of source
    $checkSql = "SELECT CurrentBalance, AccountName FROM fin_cash_accounts WHERE AccountId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$sourceId]);
    $source = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);

    if ($source['CurrentBalance'] < $amount) {
        $_SESSION['error'] = "Saldo " . $source['AccountName'] . " tidak mencukupi! (Sisa: Rp " . number_format($source['CurrentBalance'], 0, ',', '.') . ")";
        header('Location: accounts.php');
        exit;
    }

    $destStmt = sqlsrv_query($conn, "SELECT AccountName FROM fin_cash_accounts WHERE AccountId = ?", [$destId]);
    $dest = sqlsrv_fetch_array($destStmt, SQLSRV_FETCH_ASSOC);

    sqlsrv_begin_transaction($conn);

    try {
        // 1. Subtract from source
        $updSource = sqlsrv_query($conn, "UPDATE fin_cash_accounts SET CurrentBalance = CurrentBalance - ?, UpdatedAt = GETDATE() WHERE AccountId = ?", [$amount, $sourceId]);
        
        // 2. Add to dest
        $updDest = sqlsrv_query($conn, "UPDATE fin_cash_accounts SET CurrentBalance = CurrentBalance + ?, UpdatedAt = GETDATE() WHERE AccountId = ?", [$amount, $destId]);

        // 3. Ledger Source (OUT)
        $descSource = $note . " (Ke: " . $dest['AccountName'] . ")";
        $logSource = sqlsrv_query($conn, "INSERT INTO fin_ledger (AccountId, Type, Amount, Description, CreatedBy, UpdatedBy) VALUES (?, 'OUT', ?, ?, ?, ?)", [$sourceId, $amount, $descSource, $userId, $userId]);

        // 4. Ledger Dest (IN)
        $descDest = $note . " (Dari: " . $source['AccountName'] . ")";
        $logDest = sqlsrv_query($conn, "INSERT INTO fin_ledger (AccountId, Type, Amount, Description, CreatedBy, UpdatedBy) VALUES (?, 'IN', ?, ?, ?, ?)", [$destId, $amount, $descDest, $userId, $userId]);

        if ($updSource && $updDest && $logSource && $logDest) {
            sqlsrv_commit($conn);
            $_SESSION['success'] = "Transfer saldo sebesar Rp " . number_format($amount, 0, ',', '.') . " berhasil!";
        } else {
            sqlsrv_rollback($conn);
            $_SESSION['error'] = "Gagal memproses transfer saldo.";
        }
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
elseif ($action == 'delete') {
    $accountId = $_GET['id'] ?? '';
    
    // Security Check: Only Administrator (GroupId 1)
    if (!isset($_SESSION['GroupId']) || $_SESSION['GroupId'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki izin untuk menghapus akun!";
        header('Location: accounts.php');
        exit;
    }

    if (empty($accountId)) {
        $_SESSION['error'] = "ID Akun tidak ditemukan!";
        header('Location: accounts.php');
        exit;
    }

    // Check if account has ledger entries other than 'Saldo Awal'
    $checkSql = "SELECT COUNT(*) as TotalEntries, 
                        SUM(CASE WHEN Description = 'Saldo Awal' THEN 1 ELSE 0 END) as InitialBalanceEntries 
                 FROM fin_ledger WHERE AccountId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$accountId]);
    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);

    // Allow delete only if:
    // 1. Total entries = 0
    // 2. Total entries = 1 AND it is 'Saldo Awal'
    if ($row && ($row['TotalEntries'] == 0 || ($row['TotalEntries'] == 1 && $row['InitialBalanceEntries'] == 1))) {
        // Delete history first (if any)
        sqlsrv_query($conn, "DELETE FROM fin_ledger WHERE AccountId = ?", [$accountId]);
        
        // Delete account
        $sql = "DELETE FROM fin_cash_accounts WHERE AccountId = ?";
        $stmt = sqlsrv_query($conn, $sql, [$accountId]);

        if ($stmt) {
            $_SESSION['success'] = "Akun berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus akun.";
        }
    } else {
        $_SESSION['error'] = "Akun tidak bisa dihapus karena sudah memiliki histori transaksi riil.";
    }
}
header('Location: accounts.php');
exit;

