<?php
session_start();
require_once __DIR__ . '/../includes/db_mongo.php';
require_once __DIR__ . '/../includes/access_helper.php';

// Cek login
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$username = $_SESSION['username'];
$groupId = getUserGroup($username);

// Hanya admin boleh akses halaman ini
if ($groupId !== 'admin') {
    echo "<h3 style='color:red;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3>";
    exit;
}

// === LOAD DATA JSON ===
$groupsFile = __DIR__ . '/../data/groups.json';
$groups = file_exists($groupsFile) ? json_decode(file_get_contents($groupsFile), true) : [];

// === HANDLE UPDATE GROUP USER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['new_group'])) {
    $targetUser = $_POST['username'];
    $newGroup = $_POST['new_group'];

    // Hapus user dari semua grup
    foreach ($groups as &$g) {
        $g['members'] = array_values(array_filter($g['members'], fn($u) => $u !== $targetUser));
    }

    // Tambahkan user ke grup baru
    foreach ($groups as &$g) {
        if ($g['group_id'] === $newGroup) {
            $g['members'][] = $targetUser;
        }
    }

    // Simpan perubahan
    file_put_contents($groupsFile, json_encode($groups, JSON_PRETTY_PRINT));

    echo "<script>alert('✅ Grup user berhasil diperbarui!'); window.location='manajemen_akses.php';</script>";
    exit;
}

// === AMBIL DATA USER DARI MONGODB ===
$users = $userCollection->find([], ['sort' => ['username' => 1]]);

// === FILTER SEARCH ===
$search = trim($_GET['search'] ?? '');
$filteredUsers = [];

foreach ($users as $u) {
    $uname = $u['username'] ?? '';
    $nama = $u['fullname'] ?? '';
    if ($search === '' || stripos($uname, $search) !== false || stripos($nama, $search) !== false) {
        $filteredUsers[] = $u;
    }
}

function findUserGroup($username, $groups) {
    foreach ($groups as $g) {
        if (in_array($username, $g['members'])) return $g['group_name'];
    }
    return 'Belum tergabung';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Akses User</title>
    <link rel="stylesheet" href="styleadmin.css">
    <style>
table {
    border-collapse: collapse;
    width: 100%;
    background: white;
    margin-top: 20px;
}
th, td {
    border: 1px solid #e0e0e0;
    padding: 10px;
    text-align: left;
}
th {
    background: #007bff;
    color: white;
}
input[type=text] {
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
input[type=submit], button {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    background: #007bff;
    color: white;
    cursor: pointer;
}
input[type=submit]:hover, button:hover {
    background: #0056b3;
}
a.delete {
    color: red;
    text-decoration: none;
}
a.delete:hover {
    text-decoration: underline;
}
.search-bar {
    margin-top: 15px;
    margin-bottom: 10px;
}
.search-bar form {
    display: flex;
    gap: 10px;
    align-items: center;
}
.search-bar input {
    flex: 1;
}
.search-bar button {
    background: #28a745;
}
.search-bar button:hover {
    background: #1e7e34;
}

/* Media query untuk perangkat dengan lebar kurang dari 768px (mobile) */
@media (max-width: 768px) {
  .search-bar {
    flex-direction: column; /* Ubah menjadi vertikal di mobile */
  }

  .search-bar form {
    flex-direction: column; /* Form menjadi vertikal pada mobile */
    width: 100%; /* Form menjadi penuh lebar */
  }

  .search-bar input[type="text"] {
    width: 100%; /* Input mengambil lebar penuh */
    font-size: 14px;
    padding: 12px 14px;
    margin-bottom: 10px; /* Memberikan jarak bawah pada input */
  }

  .search-bar button {
    font-size: 14px; /* Menyesuaikan ukuran font tombol di mobile */
    padding: 10px 12px;
    width: 100%; /* Tombol menjadi lebar penuh pada mobile */
  }

  .search-bar a {
    display: block; /* Link reset menjadi blok di mobile */
    margin-top: 10px;
  }
}
</style>
</head>
<?php
require '../auth_check.php';
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<body>
<?php include '../sidebar.php'; ?> 
    <div class="top-nav">
        <a href="../index.php" class="back">⬅ Kembali ke Dashboard</a>
    </div>
<div class="content">
<div class="container">
    <div class="caption">
        <h1>👥 Manajemen Akses User</h1>
    </div>

    <!-- 🔍 SEARCH FORM -->
    <div class="search-bar">
        <form method="GET" action="">
            <input type="text" name="search" placeholder="Cari username atau nama..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">🔍 Cari</button>
            <?php if ($search !== ''): ?>
                <a href="manajemen_akses.php" style="background:#dc3545;color:white;padding:6px 12px;border-radius:4px;text-decoration:none;">❌ Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Username</th>
                <th>Role</th>
                <th>Grup Saat Ini</th>
                <th>Ubah Grup</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (count($filteredUsers) === 0):
            ?>
                <tr><td colspan="5" style="text-align:center;color:#888;">Tidak ada data ditemukan.</td></tr>
            <?php
            else:
                $no = 1;
                foreach ($filteredUsers as $u):
                    $uname = $u['username'] ?? '-';
                    $role = $u['role'] ?? '-';
                    $currentGroup = findUserGroup($uname, $groups);
            ?>
            <tr>
    <td data-label="No"><?= $no++ ?></td>
    <td data-label="Username"><?= htmlspecialchars($uname) ?></td>
    <td data-label="Role"><?= htmlspecialchars($role) ?></td>
    <td data-label="Grup Saat Ini"><?= htmlspecialchars($currentGroup) ?></td>
    <td data-label="Ubah Grup">
        <form method="POST" style="display:flex; gap:5px;">
            <input type="hidden" name="username" value="<?= htmlspecialchars($uname) ?>">
            <select name="new_group">
                <?php foreach ($groups as $g): ?>
                    <option value="<?= $g['group_id'] ?>" <?= ($currentGroup === $g['group_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['group_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">💾 Simpan</button>
        </form>
    </td>
</tr>

            <?php endforeach; endif; ?>
        </tbody>
    </table>
	</div>
</div>
</body>
</html>

<style>
.content {
    margin-left: 240px;
    padding: 25px;
    animation: fadeIn 0.6s ease-in-out;
    margin-top: 33px;
}

/* Membuat tabel scrollable di layar kecil */
@media (max-width: 768px) {
    table {
        display: block;
        width: 100%;
        overflow-x: auto; /* scroll horizontal */
        -webkit-overflow-scrolling: touch; /* smooth scroll di iOS */
    }

    thead, tbody, th, td, tr {
        display: block; /* buat setiap baris sebagai blok */
    }

    thead tr {
        display: none; /* sembunyikan header di block mode */
    }

    tbody tr {
        margin-bottom: 15px;
        border: 1px solid #ccc;
        padding: 10px;
        border-radius: 5px;
    }

    tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center; /* vertikal align */
        padding: 6px 10px;
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
        flex-wrap: wrap; /* biar tombol bisa pindah baris */
    }

    tbody td::before {
        content: attr(data-label); /* ambil label dari atribut data-label */
        font-weight: bold;
        width: 40%;
        display: inline-block;
        margin-right: 10px;
    }

    /* Wrapper tombol supaya tetap rata kanan */
    .action-btns {
        display: flex;
        gap: 5px;
        justify-content: flex-end;
        flex: 1; /* ambil sisa lebar td */
        margin-top: 5px; /* jarak atas di mobile */
    }

    .action-btns button,
    .action-btns a {
        all: unset;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 8px;
        font-size: 14px;
        border-radius: 4px;
        height: 32px;
        min-width: 50px;
        box-sizing: border-box;
        cursor: pointer;
        text-align: center;
        color: white;
        text-decoration: none;
    }

    .action-btns button.edit-btn { background-color: #007bff; }
    .action-btns a.btn-red { background-color: #dc3545; }
	
	
}


</style>
