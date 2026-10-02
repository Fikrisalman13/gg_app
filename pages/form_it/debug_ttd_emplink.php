<?php
/**
 * DEBUG: Cek apakah SMUserMs.EmpId bisa diakses dari koneksi GG
 * Hapus file ini setelah selesai debugging!
 */
session_start();
require_once '../../koneksi.php';

if (!isset($_SESSION['UserId'])) {
    die('<h2 style="color:red">Unauthorized. Silakan login dulu.</h2>');
}

header('Content-Type: text/html; charset=utf-8');
echo '<html><head><meta charset="utf-8"><title>Debug TTD EmpLink</title>';
echo '<style>body{font-family:monospace;padding:20px;font-size:13px;} .ok{color:green} .err{color:red} .warn{color:orange} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;}</style>';
echo '</head><body>';
echo '<h2>🔍 Debug: SMUserMs EmpId Link Check (koneksi ke DB: GG)</h2>';

// Test 1: Cek apakah SMUserMs bisa diakses
echo '<h3>Test 1: Akses tabel SMUserMs (cari ga_2)</h3>';
$sql1 = "SELECT TOP 1 UserId, UserName, EmpId FROM dbo.SMUserMs WHERE UserName = 'ga_2'";
$stmt1 = sqlsrv_query($conn, $sql1);
if ($stmt1 === false) {
    echo '<p class="err">❌ Query gagal: <pre>' . print_r(sqlsrv_errors(), true) . '</pre></p>';
} else {
    $r1 = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
    if ($r1) {
        echo '<p class="ok">✅ ga_2 ditemukan: UserId=' . $r1['UserId'] . ', EmpId=' . $r1['EmpId'] . '</p>';
    } else {
        echo '<p class="err">❌ ga_2 tidak ditemukan di SMUserMs dalam database GG</p>';
        echo '<p class="warn">Kemungkinan SMUserMs ada di database HRIS, bukan GG.</p>';
    }
    sqlsrv_free_stmt($stmt1);
}

// Test 2: Cek apakah m_emp punya Agni
echo '<h3>Test 2: Akses m_emp (cari Agni Listiani)</h3>';
$sql2 = "SELECT TOP 1 id_emp, nama_lengkap, aktif FROM dbo.m_emp WHERE nama_lengkap LIKE '%Agni%'";
$stmt2 = sqlsrv_query($conn, $sql2);
if ($stmt2 === false) {
    echo '<p class="err">❌ Query gagal: <pre>' . print_r(sqlsrv_errors(), true) . '</pre></p>';
} else {
    $r2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
    if ($r2) {
        echo '<p class="ok">✅ Ditemukan: id_emp=' . $r2['id_emp'] . ', nama=' . htmlspecialchars($r2['nama_lengkap']) . ', aktif=' . $r2['aktif'] . '</p>';
        $agniEmpId = $r2['id_emp'];

        // Test 3: Cek query yang persis sama seperti di ttd_user.php (ADD action)
        echo '<h3>Test 3: Query ADD — SMUserMs WHERE EmpId = ' . $agniEmpId . '</h3>';
        $sql3 = "SELECT TOP 1 UserId, UserName FROM dbo.SMUserMs WHERE EmpId = ?";
        $stmt3 = sqlsrv_query($conn, $sql3, [$agniEmpId]);
        if ($stmt3 === false) {
            echo '<p class="err">❌ Query gagal: <pre>' . print_r(sqlsrv_errors(), true) . '</pre></p>';
        } else {
            $r3 = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC);
            if ($r3) {
                echo '<p class="ok">✅ Ditemukan: UserId=' . $r3['UserId'] . ', UserName=' . htmlspecialchars($r3['UserName']) . '</p>';
                echo '<p class="ok">✅ Tidak ada bug penghitungan — data terlink dengan benar.</p>';
                echo '<p class="warn">⚠️ Berarti UserId=0 terjadi SEBELUM EmpId diisi di SMUserMs. Data sudah benar sekarang.</p>';
            } else {
                echo '<p class="err">❌ Tidak ada hasil! EmpId=' . $agniEmpId . ' tidak ditemukan di SMUserMs.EmpId</p>';
                echo '<p class="err">🐛 INI BUGNYA: koneksi GG tidak bisa melihat EmpId di SMUserMs atau nilainya tidak cocok.</p>';
            }
            sqlsrv_free_stmt($stmt3);
        }
    } else {
        echo '<p class="err">❌ Agni tidak ditemukan di m_emp</p>';
    }
    sqlsrv_free_stmt($stmt2);
}

// Test 4: Cek User_TTD_Template untuk Agni sekarang
echo '<h3>Test 4: Status User_TTD_Template sekarang</h3>';
$sql4 = "SELECT t.Id, t.UserId, t.UserName, t.GroupRole, u.UserName AS LoginName
          FROM dbo.User_TTD_Template t
          LEFT JOIN dbo.SMUserMs u ON t.UserId = u.UserId AND t.UserId > 0
          WHERE t.UserName LIKE '%Agni%'";
$stmt4 = sqlsrv_query($conn, $sql4);
if ($stmt4 === false) {
    echo '<p class="err">❌ Query gagal: <pre>' . print_r(sqlsrv_errors(), true) . '</pre></p>';
} else {
    echo '<table border="1" cellpadding="6" style="border-collapse:collapse">';
    echo '<tr><th>Id</th><th>UserId</th><th>UserName</th><th>GroupRole</th><th>LoginName</th></tr>';
    $found = false;
    while ($r4 = sqlsrv_fetch_array($stmt4, SQLSRV_FETCH_ASSOC)) {
        $found = true;
        $loginClass = $r4['LoginName'] ? 'ok' : 'err';
        echo '<tr>';
        echo '<td>' . $r4['Id'] . '</td>';
        echo '<td>' . $r4['UserId'] . '</td>';
        echo '<td>' . htmlspecialchars($r4['UserName']) . '</td>';
        echo '<td>' . htmlspecialchars($r4['GroupRole']) . '</td>';
        echo '<td class="' . $loginClass . '">' . ($r4['LoginName'] ?? '❌ NULL') . '</td>';
        echo '</tr>';
    }
    if (!$found) echo '<tr><td colspan="5" style="color:red">Tidak ada data untuk Agni</td></tr>';
    echo '</table>';
    sqlsrv_free_stmt($stmt4);
}

echo '<hr><p class="err"><strong>⚠️ Hapus file ini setelah selesai!</strong><br>';
echo 'Path: <code>c:\xampp\htdocs\gg_app\pages\form_it\debug_ttd_emplink.php</code></p>';
echo '</body></html>';
