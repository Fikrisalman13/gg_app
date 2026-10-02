<?php
session_start();
require_once __DIR__ . '/../includes/access_helper.php';

// ✅ Cek login
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$username = $_SESSION['username'];
$groupId = getUserGroup($username);

// 🔐 Hanya admin boleh
if ($groupId !== 'admin') {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit;
}

// 📁 Lokasi file JSON
$file = __DIR__ . '/../data/groups.json';
$groups = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

// === Tambah Group ===
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $newGroup = [
        "group_id" => strtolower(trim($_POST['group_id'])),
        "group_name" => trim($_POST['group_name']),
        "members" => []
    ];
    $groups[] = $newGroup;
    file_put_contents($file, json_encode($groups, JSON_PRETTY_PRINT));
    header("Location: user_group.php?status=added");
    exit;
}

// === Edit Group ===
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    foreach ($groups as &$g) {
        if ($g['group_id'] === $_POST['group_id']) {
            $g['group_name'] = trim($_POST['group_name']);
        }
    }
    file_put_contents($file, json_encode($groups, JSON_PRETTY_PRINT));
    header("Location: user_group.php?status=updated");
    exit;
}

// === Delete Group ===
if (isset($_GET['delete'])) {
    $groups = array_values(array_filter($groups, fn($g) => $g['group_id'] !== $_GET['delete']));
    file_put_contents($file, json_encode($groups, JSON_PRETTY_PRINT));
    header("Location: user_group.php?status=deleted");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen User Group</title>
<link rel="stylesheet" href="styleadmin.css">
<style>
body {
  font-family: 'Segoe UI', sans-serif;
  background: #f3f6fa;
}

/* === TABEL === */
table {
  border-collapse: collapse;
  width: 100%;
  background: white;
  margin-top: 20px;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
th, td {
  border-bottom: 1px solid #eee;
  padding: 10px;
  text-align: left;
}
th {
  background: #007bff;
  color: white;
}

/* === BUTTON === */
button, .icon-btn {
  cursor: pointer;
  border: none;
  background: none;
  transition: 0.2s;
}

.icon-btn {
  font-size: 20px;
  margin-right: 8px;
}

.icon-btn:hover {
  transform: scale(1.2);
}

/* Tombol merah */
.btn-red, a.delete {
  background: #dc3545;
  color: white !important;
  border-radius: 6px;
  padding: 6px 12px;
  text-decoration: none;
  transition: 0.2s;
}
.btn-red:hover, a.delete:hover {
  background: #b02a37;
}

/* Tombol popup close merah */
.close-popup.red {
  background: #dc3545;
  color: white;
  border-radius: 50%;
  width: 26px;
  height: 26px;
  text-align: center;
  line-height: 26px;
  font-weight: bold;
  position: absolute;
  top: 10px; right: 15px;
  cursor: pointer;
  transition: 0.2s;
}
.close-popup.red:hover {
  background: #b02a37;
}

/* === POPUP === */
.popup-overlay {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.3);
  align-items: center;
  justify-content: center;
  z-index: 999;
  pointer-events: none; /* blokir klik hanya saat tampil */
  transition: opacity 0.2s ease;
  opacity: 0;
}

.popup-overlay.show {
  display: flex;
  pointer-events: auto;
  opacity: 1;
}

.popup-bubble {
  background: white;
  border-radius: 15px;
  padding: 20px;
  width: 320px;
  position: relative;
  box-shadow: 0 8px 30px rgba(0,0,0,0.2);
  transform: scale(0.95);
  opacity: 0;
  transition: all 0.25s ease;
}

.popup-overlay.show .popup-bubble {
  transform: scale(1);
  opacity: 1;
}

/* === INPUT === */
.popup-bubble input[type=text],
.popup-bubble input[type=password] {
  width: 100%;
  padding: 8px;
  margin: 6px 0;
  border-radius: 6px;
  border: 1px solid #ccc;
}
.popup-bubble button {
  background: #007bff;
  color: white;
  border: none;
  border-radius: 6px;
  padding: 8px 14px;
  margin-top: 10px;
}
.popup-bubble button:hover {
  background: #0056b3;
}

/* === TOAST / NOTIFIKASI === */
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  background: #007bff;
  color: white;
  padding: 12px 18px;
  border-radius: 20px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.2);
  opacity: 0;
  transform: translateY(-20px);
  transition: all 0.3s ease;
  z-index: 2000;
}
.toast.show {
  opacity: 1;
  transform: translateY(0);
}
.toast.success { background: #28a745; }
.toast.error { background: #dc3545; }

/* Notifikasi gelembung (delete) */
.notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #ff4d4d;
    color: white;
    padding: 12px 16px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    display: none;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease forwards;
    z-index: 9999;
}
.notification.show { display: flex; }
@keyframes slideIn {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Atur ukuran dan gaya tombol untuk menyamakan ukuran kedua tombol */
.icon-btn, .btn-red {
  display: inline-block; /* Agar keduanya menjadi elemen inline dengan lebar yang sama */
  padding: 8px 16px; /* Padding yang sama untuk kedua tombol */
  text-align: center; /* Agar teks berada di tengah */
  font-size: 16px; /* Ukuran font yang konsisten */
  border-radius: 6px; /* Membuat sudut tombol melengkung */
  cursor: pointer; /* Tunjukkan kursor pointer saat tombol di-hover */
  transition: 0.2s; /* Animasi transisi saat hover */
}

.icon-btn.edit-btn {
  background-color: #007bff;
  color: white;
}

.icon-btn.edit-btn:hover {
  background-color: #0056b3;
}

.btn-red.delete-btn {
  background-color: #dc3545;
  color: white;
  text-decoration: none; /* Hilangkan garis bawah pada link */
}

.btn-red.delete-btn:hover {
  background-color: #b02a37;
}

/* Tabel grup responsif */
@media (max-width: 768px) {
    table {
        display: block;
        width: 100%;
        overflow-x: auto; /* scroll horizontal jika perlu */
        -webkit-overflow-scrolling: touch;
    }

    thead, tbody, th, td, tr {
        display: block; /* ubah baris jadi blok */
    }

    thead tr {
        display: none; /* sembunyikan header */
    }

    tbody tr {
        margin-bottom: 15px;
        border: 1px solid #ccc;
        padding: 10px;
        border-radius: 5px;
        background: #fff;
    }

    tbody td {
        display: flex;
        justify-content: space-between;
        padding: 6px 10px;
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
    }

    tbody td::before {
        content: attr(data-label); /* ambil label dari atribut data-label */
        font-weight: bold;
        width: 40%;
        display: inline-block;
    }

    /* Tombol aksi */
    tbody td button, tbody td a {
        flex: none;
        margin-left: 5px;
        padding: 6px 10px;
    }
}

@media (max-width: 768px) {
    table {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    thead, tbody, th, td, tr {
        display: block;
    }

    thead tr {
        display: none;
    }

    tbody tr {
        margin-bottom: 15px;
        border: 1px solid #ccc;
        padding: 10px;
        border-radius: 5px;
        background: #fff;
    }

    tbody td {
        display: flex;
        justify-content: space-between;
        padding: 6px 10px;
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
        flex-wrap: wrap; /* biar tidak melebihi */
    }

    tbody td::before {
        content: attr(data-label);
        font-weight: bold;
        width: 40%;
        display: inline-block;
        margin-right: 5px;
    }

    /* Kolom Aksi tetap inline, tidak stretch */
    tbody td[data-label="Aksi"] {
        display: inline-flex !important;
        justify-content: flex-start;
        gap: 5px;
        width: 100%;
        padding: 4px 0;
    }

    tbody td[data-label="Aksi"] button,
    tbody td[data-label="Aksi"] a {
        flex: none;          /* tidak stretch */
        padding: 4px 8px;    /* lebih compact */
        font-size: 14px;     /* ukuran tombol normal */
        margin: 0;
        display: inline-block;
    }
	
	tbody td[data-label="Aksi"] button,
tbody td[data-label="Aksi"] a {
    all: unset;                 /* reset semua style bawaan browser */
    display: inline-flex;       /* tetap inline-flex */
    align-items: center;        /* center vertikal */
    justify-content: center;    /* center horizontal */
    padding: 4px 8px;           /* padding sama */
    font-size: 14px;            /* ukuran font sama */
    border-radius: 4px;         /* radius sama */
    min-width: 50px;            /* lebar minimum sama */
    height: 32px;               /* tinggi tetap sama */
    box-sizing: border-box;     /* padding dihitung dalam height */
    cursor: pointer;
    text-align: center;
}

tbody td[data-label="Aksi"] button.edit-btn {
    background-color: #007bff;
    color: white;
}

tbody td[data-label="Aksi"] a.btn-red {
    background-color: #dc3545;
    color: white;
    text-decoration: none;      /* hilangkan underline */
}

 tbody td[data-label="Aksi"] {
        display: flex;               /* pastikan flex */
        justify-content: flex-end;   /* tombol ke kanan */
        gap: 5px;                    /* jarak antar tombol */
        width: 100%;
        padding: 4px 0;
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

<div class="content">
<div class="container">
  <div class="caption">
    <h2>🧩 Manajemen User Group</h2>
  </div>

  <div style="margin:10px 0;">
    <button class="icon-btn" id="openAddPopup" title="Tambah Group">➕</button>
  </div>

  <table>
    <thead>
        <tr>
            <th>ID Group</th>
            <th>Nama Group</th>
            <th>Jumlah Anggota</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($groups as $g): ?>
        <tr>
            <td data-label="ID Group"><?= htmlspecialchars($g['group_id']) ?></td>
            <td data-label="Nama Group"><?= htmlspecialchars($g['group_name']) ?></td>
            <td data-label="Jumlah Anggota"><?= count($g['members']) ?></td>
            <td data-label="Aksi">
                <div class="action-btns">
                    <button class="icon-btn edit-btn" data-id="<?= htmlspecialchars($g['group_id']) ?>" data-name="<?= htmlspecialchars($g['group_name']) ?>" title="Edit">✏️</button>
                    <a href="#" class="btn-red delete-btn" data-id="<?= htmlspecialchars($g['group_id']) ?>">🗑️</a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>
/* Tombol desktop & mobile */
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
}

.action-btns button.edit-btn { background-color: #007bff; color: white; }
.action-btns a.btn-red { background-color: #dc3545; color: white; text-decoration: none; }

/* Responsive mobile: ubah table jadi card-like */
@media (max-width: 768px) {
    table, thead, tbody, th, td, tr {
        display: block;
        width: 100%;
    }

    thead tr {
        display: none; /* sembunyikan header */
    }

    tbody tr {
        margin-bottom: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 10px;
    }

    tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
    }

    tbody td::before {
        content: attr(data-label);
        font-weight: bold;
        flex: 1;
    }

    .action-btns {
        display: flex;
        gap: 5px;
        justify-content: flex-end;
        flex: none;
    }
}
</style>

</div>
</div>

<!-- Popup Tambah -->
<div class="popup-overlay" id="addPopup">
  <div class="popup-bubble">
    <span class="close-popup red" onclick="closePopup('addPopup')">&times;</span>
    <h3>➕ Tambah Group Baru</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input type="text" name="group_id" placeholder="ID Group (tanpa spasi)" required>
      <input type="text" name="group_name" placeholder="Nama Group" required>
      <button type="submit">Simpan</button>
    </form>
  </div>
</div>

<!-- Popup Edit -->
<div class="popup-overlay" id="editPopup">
  <div class="popup-bubble">
    <span class="close-popup red" onclick="closePopup('editPopup')">&times;</span>
    <h3>✏️ Ubah Nama Group</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="group_id" id="editGroupId">
      <input type="text" name="group_name" id="editGroupName" required>
      <button type="submit">Update</button>
    </form>
  </div>
</div>

<!-- Popup Password -->
<div class="popup-overlay" id="passwordPopup">
  <div class="popup-bubble">
    <span class="close-popup red" onclick="closePopup('passwordPopup')">&times;</span>
    <h3>🔐 Verifikasi Akses</h3>
    <p>Masukkan password untuk mengubah grup <b>Administrator</b>:</p>
    <input type="password" id="adminPassword" placeholder="Password" required>
    <button id="confirmPasswordBtn">Lanjut</button>
  </div>
</div>

<!-- Notifikasi delete (global, di luar loop) -->
<div id="deleteNotification" class="notification">
  <span>Hapus grup ini?</span>
  <button id="confirmDelete">✅</button>
  <button id="cancelDelete">❌</button>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// helper popup open/close
function openPopup(id) {
  const popup = document.getElementById(id);
  if (!popup) return;
  popup.classList.add('show');
  popup.style.display = 'flex';
}
function closePopup(id) {
  const popup = document.getElementById(id);
  if (!popup) return;
  popup.classList.remove('show');
  // delay actual hide to allow transition; keep same timing as CSS
  setTimeout(() => { popup.style.display = 'none'; }, 220);
}

// open add popup
document.getElementById('openAddPopup')?.addEventListener('click', () => openPopup('addPopup'));

// edit buttons
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    const groupId = btn.dataset.id;
    const groupName = btn.dataset.name;

    if (groupId === 'admin') {
      // require password first
      askAdminPassword('edit', { id: groupId, name: groupName });
    } else {
      document.getElementById('editGroupId').value = groupId;
      document.getElementById('editGroupName').value = groupName;
      openPopup('editPopup');
    }
  });
});

// delete buttons
let currentDeleteId = null;
document.querySelectorAll('.delete-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const groupId = btn.dataset.id;

    if (groupId === 'admin') {
      // ask password first
      askAdminPassword('delete', { id: groupId });
    } else {
      currentDeleteId = groupId;
      showDeleteNotification();
    }
  });
});

// delete notification handlers
function showDeleteNotification() {
  const n = document.getElementById('deleteNotification');
  n.classList.add('show');
  n.style.display = 'flex';
}
function hideDeleteNotification() {
  const n = document.getElementById('deleteNotification');
  n.classList.remove('show');
  setTimeout(() => { n.style.display = 'none'; }, 250);
}
document.getElementById('confirmDelete').addEventListener('click', () => {
  if (currentDeleteId) {
    window.location.href = "?delete=" + encodeURIComponent(currentDeleteId);
  }
});
document.getElementById('cancelDelete').addEventListener('click', () => {
  currentDeleteId = null;
  hideDeleteNotification();
});

// close popup when click outside its bubble
document.querySelectorAll('.popup-overlay').forEach(p => {
  p.addEventListener('click', e => { if (e.target === p) closePopup(p.id); });
});

// toast
function showToast(msg, type="success") {
  const toast = document.getElementById("toast");
  toast.className = "toast " + (type === 'error' ? 'error' : '');
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
if (status === 'added') showToast('✅ Group berhasil ditambahkan');
else if (status === 'updated') showToast('✏️ Group berhasil diperbarui');
else if (status === 'deleted') showToast('🗑️ Group berhasil dihapus', 'error');

// password flow for admin-group actions
function askAdminPassword(action, data) {
  // open password popup
  openPopup('passwordPopup');
  const confirmBtn = document.getElementById('confirmPasswordBtn');
  const pwdInput = document.getElementById('adminPassword');

  // Hapus listener lama agar tidak dobel
  confirmBtn.onclick = null;
  pwdInput.onkeydown = null;

  // Fungsi verifikasi password
  const handleConfirm = () => {
    const val = pwdInput.value.trim();
    if (val === 'rahasiaIT') { // password benar
      closePopup('passwordPopup');
      pwdInput.value = '';

      if (action === 'edit') {
        document.getElementById('editGroupId').value = data.id;
        document.getElementById('editGroupName').value = data.name;
        openPopup('editPopup');
      } else if (action === 'delete') {
        currentDeleteId = data.id;
        showDeleteNotification();
      }
    } else {
      showToast('❌ Password salah', 'error');
      pwdInput.focus();
    }
  };

  // Klik tombol → konfirmasi
  confirmBtn.onclick = handleConfirm;

  // Tekan Enter → konfirmasi
  pwdInput.onkeydown = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleConfirm();
    }
  };

  // Fokus otomatis ke input password
  setTimeout(() => pwdInput.focus(), 100);
}
</script>
</body>
</html>
