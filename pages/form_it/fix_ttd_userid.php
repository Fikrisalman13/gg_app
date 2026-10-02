<?php
/**
 * Fix TTD UserId = 0
 * Script one-time: update UserId di User_TTD_Template yang masih 0
 * Cara kerja: cocokkan t.UserName ke m_emp.nama_lengkap, lalu ambil UserId dari SMUserMs
 *
 * HAPUS FILE INI SETELAH DIJALANKAN!
 */
session_start();
require_once '../../koneksi.php';

// Hanya boleh dijalankan oleh admin yang sudah login
if (!isset($_SESSION['UserId'])) {
    die('<h2 style="color:red">Unauthorized. Silakan login dulu.</h2>');
}

header('Content-Type: text/html; charset=utf-8');

echo '<html><head><meta charset="utf-8"><title>Fix TTD UserId</title>';
echo '<style>body{font-family:monospace;padding:20px;} .ok{color:green} .warn{color:orange} .err{color:red} table{border-collapse:collapse;width:100%} td,th{border:1px solid #ccc;padding:6px 10px;} th{background:#eee}</style>';
echo '</head><body>';
echo '<h2>🔧 Fix User_TTD_Template: UserId = 0</h2>';

// 1. Ambil semua record yang UserId = 0 atau NULL
$sqlBad = "SELECT t.Id, t.UserName, t.GroupRole, t.UserId, t.SignaturePath
           FROM dbo.User_TTD_Template t
           WHERE t.UserId = 0 OR t.UserId IS NULL
           ORDER BY t.UserName";

$stmtBad = sqlsrv_query($conn, $sqlBad);
if ($stmtBad === false) {
    echo '<p class="err">Gagal query: ' . print_r(sqlsrv_errors(), true) . '</p>';
    exit;
}

$badRows = [];
while ($row = sqlsrv_fetch_array($stmtBad, SQLSRV_FETCH_ASSOC)) {
    $badRows[] = $row;
}
sqlsrv_free_stmt($stmtBad);

if (empty($badRows)) {
    echo '<p class="ok">✅ Tidak ada data dengan UserId = 0. Semua sudah benar!</p>';
    echo '</body></html>';
    exit;
}

echo '<p>Ditemukan <strong>' . count($badRows) . '</strong> baris dengan UserId = 0:</p>';
echo '<table><tr><th>Id</th><th>UserName (tersimpan)</th><th>GroupRole</th><th>Aksi</th><th>Hasil</th></tr>';

$totalFixed = 0;
$totalFailed = 0;

foreach ($badRows as $r) {
    $id       = (int)$r['Id'];
    $userName = trim($r['UserName'] ?? '');
    $groupRole = $r['GroupRole'] ?? '';

    $statusHtml = '';
    $aksiHtml   = '';

    if ($userName === '') {
        $statusHtml = '<span class="err">❌ UserName kosong, tidak bisa dicocokkan</span>';
        $aksiHtml   = 'Skip';
        $totalFailed++;
    } else {
        // Cari UserId dari SMUserMs via m_emp.nama_lengkap cocok dengan UserName tersimpan
        $sqlFind = "SELECT TOP 1 u.UserId, u.UserName AS LoginName, e.nama_lengkap
                    FROM dbo.SMUserMs u
                    JOIN dbo.m_emp e ON u.EmpId = e.id_emp
                    WHERE e.nama_lengkap = ?";
        $stmtFind = sqlsrv_query($conn, $sqlFind, [$userName]);

        $foundUserId  = null;
        $foundLogin   = null;
        $foundName    = null;

        if ($stmtFind !== false && $fRow = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC)) {
            $foundUserId = (int)$fRow['UserId'];
            $foundLogin  = $fRow['LoginName'];
            $foundName   = $fRow['nama_lengkap'];
        }
        if ($stmtFind) sqlsrv_free_stmt($stmtFind);

        if ($foundUserId !== null && $foundUserId > 0) {
            // Update UserId
            $sqlUpd = "UPDATE dbo.User_TTD_Template SET UserId = ?, UpdatedAt = GETDATE() WHERE Id = ?";
            $stmtUpd = sqlsrv_query($conn, $sqlUpd, [$foundUserId, $id]);

            if ($stmtUpd !== false) {
                $totalFixed++;
                $aksiHtml   = "UPDATE UserId = {$foundUserId}";
                $statusHtml = '<span class="ok">✅ Berhasil → Login: <strong>' . htmlspecialchars($foundLogin) . '</strong></span>';
            } else {
                $totalFailed++;
                $errs = print_r(sqlsrv_errors(), true);
                $aksiHtml   = "UPDATE gagal";
                $statusHtml = '<span class="err">❌ DB error: ' . htmlspecialchars($errs) . '</span>';
            }
            if ($stmtUpd) sqlsrv_free_stmt($stmtUpd);
        } else {
            // Coba fuzzy: LIKE '%nama%'
            $nameParts = explode(' ', $userName);
            $firstWord = '%' . ($nameParts[0] ?? $userName) . '%';

            $sqlFuzz = "SELECT TOP 1 u.UserId, u.UserName AS LoginName, e.nama_lengkap
                        FROM dbo.SMUserMs u
                        JOIN dbo.m_emp e ON u.EmpId = e.id_emp
                        WHERE e.nama_lengkap LIKE ?";
            $stmtFuzz = sqlsrv_query($conn, $sqlFuzz, [$firstWord]);
            $fuzzUserId = null; $fuzzLogin = null; $fuzzName = null;
            if ($stmtFuzz !== false && $fR = sqlsrv_fetch_array($stmtFuzz, SQLSRV_FETCH_ASSOC)) {
                $fuzzUserId = (int)$fR['UserId'];
                $fuzzLogin  = $fR['LoginName'];
                $fuzzName   = $fR['nama_lengkap'];
            }
            if ($stmtFuzz) sqlsrv_free_stmt($stmtFuzz);

            if ($fuzzUserId !== null && $fuzzUserId > 0) {
                $totalFailed++;
                $aksiHtml   = 'Kemungkinan cocok (fuzzy) — tidak di-update otomatis';
                $statusHtml = '<span class="warn">⚠️ Kemungkinan: Login=<strong>' . htmlspecialchars($fuzzLogin) . '</strong> (emp: ' . htmlspecialchars($fuzzName) . '). Periksa manual.</span>';
            } else {
                $totalFailed++;
                $aksiHtml   = 'Tidak ditemukan';
                $statusHtml = '<span class="err">❌ Tidak ada akun login ditemukan untuk nama ini</span>';
            }
        }
    }

    echo '<tr>';
    echo '<td>' . $id . '</td>';
    echo '<td>' . htmlspecialchars($userName) . '</td>';
    echo '<td>' . htmlspecialchars($groupRole) . '</td>';
    echo '<td>' . $aksiHtml . '</td>';
    echo '<td>' . $statusHtml . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '<hr>';
echo '<p><strong>Ringkasan:</strong> ✅ Berhasil diperbaiki: <span class="ok">' . $totalFixed . '</span> | ❌ Gagal/Skip: <span class="err">' . $totalFailed . '</span></p>';
echo '<p class="err"><strong>⚠️ PENTING: Hapus file ini setelah selesai!</strong><br>';
echo 'Path: <code>c:\xampp\htdocs\gg_app\pages\form_it\fix_ttd_userid.php</code></p>';
echo '</body></html>';
