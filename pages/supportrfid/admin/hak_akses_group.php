<?php
session_start();
require_once __DIR__ . '/../includes/access_helper.php';

// ✅ Gunakan CSS tema lama
echo '<link rel="stylesheet" href="styleadmin.css">';

// 🔒 Cek login
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$username = $_SESSION['username'];
$groupId = getUserGroup($username);

// 🔐 Hanya admin boleh mengakses halaman ini
if ($groupId !== 'admin') {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit;
}

// 🔗 File JSON
$groupFile = __DIR__ . '/../data/groups.json';
$accessFile = __DIR__ . '/../data/access_rights.json';

// 📂 Load data
$groups = file_exists($groupFile) ? json_decode(file_get_contents($groupFile), true) : [];
$accessRights = file_exists($accessFile) ? json_decode(file_get_contents($accessFile), true) : [];

// 📋 Struktur menu (parent + child)
$availableMenus = [
    "updateGudangSubmenu" => [
        "label" => "🚚 Update Gudang Menu",
        "children" => [
            "updatepicking" => "📋 Picking Process",
            "updatecpbatch" => "🧾 CP Batch Transfer List"
        ]
    ],
    "updatemultimenu" => [
        "label" => "📝 Multi Update",
        "children" => []
    ],
    "upload" => [
        "label" => "📂 Upload Data",
        "children" => []
    ],
    "riwayatSubmenu" => [
        "label" => "🕒 Semua Riwayat Menu",
        "children" => [
            "riwayatgudang" => "📹 Riwayat Update Gudang",
            "riwayatmulti" => "📰 Riwayat Multi Edit",
            "riwayatupload" => "🗂️ Riwayat Upload Data"
			]
        ],
		
		 "toolsSubmenu" => [
        "label" => "✨ Tools",
        "children" => [
            "tools" => "⚔️ Rumus Concatenate"
        ]
    ],
    "adminmenu" => [
        "label" => "👑 Menu Admin",
        "children" => [
            "manajemenAksesMenu" => "👥 Manajemen Akses",
            "userGroupMenu" => "🧩 User Group",
            "hakAksesMenu" => "🛡️ Hak Akses Group"
        ]
    ]
];

// Tombol akses
$availableButtons = [
    "update-btn" => "💾 Update Semua Data",
    "tampilkan" => "👁️ Tampilkan Data",
    "delete" => "🗑️ Hapus Data"
];

// Tombol akses
$availableButtons2 = [
	"search" => "🕵🏻 Advance Search",
    "onlysby" => "👁️ List Gd Hanya SBY"
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id']) && isset($_POST['save_access'])) {
    $gid = $_POST['group_id'];
    $menus = $_POST['menus'] ?? [];
    $buttons = $_POST['buttons'] ?? [];

    $accessRights[$gid] = [
        "menu" => $menus,
        "buttons" => $buttons
    ];

    file_put_contents($accessFile, json_encode($accessRights, JSON_PRETTY_PRINT));

    // redirect dengan status agar notifikasi tampil via JS
    header("Location: hak_akses_group.php?status=saved");
    exit;
}

// 🧭 Tentukan grup aktif
$selectedGroup = $_POST['group_id'] ?? ($groups[0]['group_id'] ?? '');
$currentAccess = $selectedGroup && isset($accessRights[$selectedGroup])
    ? $accessRights[$selectedGroup]
    : ['menu' => [], 'buttons' => []];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Hak Akses Group</title>
  <style>
    /* ==== Notifikasi gelembung ==== */
    .notification-bubble {
      position: fixed;
      bottom: 25px;
      right: 25px;
      background: #4CAF50;
      color: #fff;
      padding: 12px 18px;
      border-radius: 10px;
      box-shadow: 0 3px 12px rgba(0,0,0,0.25);
      font-weight: 500;
      font-size: 15px;
      opacity: 0;
      transform: translateY(30px);
      animation: slideUp 0.4s ease forwards;
      z-index: 9999;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .notification-bubble.error {
      background: #e53935;
    }
    @keyframes slideUp {
      to { opacity: 1; transform: translateY(0); }
    }

    /* ==== Gaya checkbox dan fieldset ==== */
    label.checkbox-block {
      background: #f8fbff;
      border-radius: 8px;
      padding: 6px 10px;
      border: 1px solid #e0e0e0;
      display: block;
      margin-bottom: 6px;
      transition: all 0.25s ease;
      cursor: pointer;
    }
    label.checkbox-block:hover {
      background: #e3f2fd;
      transform: translateY(-2px);
      box-shadow: 0 3px 8px rgba(21,101,192,0.15);
    }
    .submenu-container {
      margin-left: 25px;
      padding-left: 10px;
      border-left: 2px dashed #ccc;
      display: none;
      transition: all 0.3s ease;
    }
    .submenu-container.visible {
      display: block;
    }
    legend {
      font-weight: bold;
      margin-bottom: 6px;
    }
  </style>
</head>
<?php
require '../auth_check.php';
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<body>
<?php include '../sidebar.php'; ?> <!-- Sidebar utama -->

<div class="content">
<div class="container">
  <h2>🛡️ Hak Akses Group</h2>
  <div class="caption">
    <span>Atur hak akses menu dan tombol untuk setiap grup pengguna.</span>
  </div>

  <form method="POST" style="margin-top:20px;">
    <label for="group_id"><b>Pilih Group:</b></label>
    <select name="group_id" id="group_id" onchange="this.form.submit()">
      <option value="">-- Pilih Group --</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?= htmlspecialchars($g['group_id']) ?>" <?= ($selectedGroup === $g['group_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($g['group_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($selectedGroup): ?>
  <form method="POST" style="margin-top:20px;">
    <input type="hidden" name="group_id" value="<?= htmlspecialchars($selectedGroup) ?>">
    <input type="hidden" name="save_access" value="1">

    <!-- 📋 Menu Access -->
    <fieldset style="margin-top:20px;">
      <legend>📋 Menu yang Bisa Diakses</legend>

      <?php foreach ($availableMenus as $parentId => $parentData): ?>
        <?php
          $isParentChecked = in_array($parentId, $currentAccess['menu']);
          $hasChildren = !empty($parentData['children']);
        ?>
        <label class="checkbox-block">
          <input type="checkbox" class="parent-menu" name="menus[]" value="<?= $parentId ?>" <?= $isParentChecked ? 'checked' : '' ?>>
          <?= $parentData['label'] ?>
        </label>

        <?php if ($hasChildren): ?>
          <div class="submenu-container <?= $isParentChecked ? 'visible' : '' ?>" data-parent="<?= $parentId ?>">
            <?php foreach ($parentData['children'] as $childId => $childLabel): ?>
              <label class="checkbox-block" style="background:#fff;">
                <input type="checkbox" class="child-menu" name="menus[]" value="<?= $childId ?>"
                  data-parent="<?= $parentId ?>"
                  <?= in_array($childId, $currentAccess['menu']) ? 'checked' : '' ?>>
                <?= $childLabel ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </fieldset>

 <!-- ✨ availableButtons2 -->
    <fieldset style="margin-top:25px;">
      <legend>✨ Advance Setting</legend>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px;">
        <?php foreach ($availableButtons2 as $class => $label): ?>
          <label class="checkbox-block">
            <input type="checkbox" name="buttons[]" value="<?= $class ?>" <?= in_array($class, $currentAccess['buttons']) ? 'checked' : '' ?>>
            <?= $label ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>



    <!-- 🖱️ Tombol Access -->
    <fieldset style="margin-top:25px;">
      <legend>🖱️ Tombol yang Bisa Digunakan</legend>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px;">
        <?php foreach ($availableButtons as $class => $label): ?>
          <label class="checkbox-block">
            <input type="checkbox" name="buttons[]" value="<?= $class ?>" <?= in_array($class, $currentAccess['buttons']) ? 'checked' : '' ?>>
            <?= $label ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <button type="submit" class="update-btn">💾 Simpan Hak Akses</button>
  </form>
  <?php else: ?>
    <div class="caption" style="margin-top:20px;">
      <span><i>Silakan pilih grup terlebih dahulu untuk menampilkan daftar hak akses.</i></span>
    </div>
  <?php endif; ?>
</div>
</div>

<!-- Elemen notifikasi -->
<div id="notif" class="notification-bubble" style="display:none;"></div>



<!-- 🔒 POPUP PASSWORD -->
<div id="passwordModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>🔐 Konfirmasi Password</h3>
      <button type="button" id="closeModalBtn" class="close-btn">&times;</button>
    </div>
    <p class="modal-desc">Masukkan password untuk mengubah hak akses <b>admin</b>:</p>

    <input type="password" id="adminPassword" placeholder="Masukkan password Anda..." />

    <p id="errorMsg" class="error-msg">❗ Password salah!</p>

    <div class="modal-buttons">
      <button type="button" id="confirmPasswordBtn" class="confirm-btn">✅ Konfirmasi</button>
      <button type="button" id="cancelPasswordBtn" class="cancel-btn">❌ Batal</button>
    </div>
  </div>
</div>

<style>
/* 🌫️ Overlay gelap dengan blur lembut */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(5px);
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.3s ease;
}

/* 💬 Kotak popup */
.modal-content {
  background: linear-gradient(145deg, #ffffff, #f5f7fa);
  padding: 30px 25px;
  border-radius: 12px;
  width: 340px;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
  text-align: center;
  animation: slideUp 0.35s ease;
  position: relative;
}

/* 🔠 Header */
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.modal-header h3 {
  margin: 0;
  font-size: 1.2em;
  color: #1a237e;
}
.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5em;
  cursor: pointer;
  color: #999;
}
.close-btn:hover {
  color: #333;
}

/* ✍️ Input password */
.modal-content input {
  width: 100%;
  padding: 10px;
  margin-top: 15px;
  border: 1px solid #cfd8dc;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: 0.2s;
}
.modal-content input:focus {
  border-color: #2196f3;
  box-shadow: 0 0 4px rgba(33, 150, 243, 0.3);
}

/* 🧾 Deskripsi */
.modal-desc {
  color: #444;
  font-size: 0.95em;
  margin-bottom: 8px;
}

/* ❌ Error */
.error-msg {
  color: #f44336;
  display: none;
  margin-top: 8px;
  font-size: 0.9em;
}

/* 🔘 Tombol */
.modal-buttons {
  margin-top: 18px;
  display: flex;
  gap: 12px;
  justify-content: center;
}
.modal-buttons button {
  padding: 8px 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  font-size: 0.95em;
  transition: 0.25s;
}
.confirm-btn {
  background: linear-gradient(90deg, #4caf50, #66bb6a);
  color: white;
}
.confirm-btn:hover {
  background: linear-gradient(90deg, #43a047, #388e3c);
}
.cancel-btn {
  background: linear-gradient(90deg, #f44336, #e57373);
  color: white;
}
.cancel-btn:hover {
  background: linear-gradient(90deg, #d32f2f, #c62828);
}

/* 🎞️ Animasi */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slideUp {
  from { transform: translateY(25px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
</style>


<script>
// ============================================
// 🔐 PASSWORD POPUP UNTUK GROUP ADMINISTRATOR
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form input[name="save_access"]')?.closest('form');
  const saveBtn = form?.querySelector('.update-btn');
  const selectedGroup = "<?= htmlspecialchars($selectedGroup) ?>";

  const modal = document.getElementById('passwordModal');
  const confirmBtn = document.getElementById('confirmPasswordBtn');
  const cancelBtn = document.getElementById('cancelPasswordBtn');
  const closeBtn = document.getElementById('closeModalBtn'); // ✅ tambahkan ini
  const errorMsg = document.getElementById('errorMsg');
  const passwordField = document.getElementById('adminPassword');

  // 🔢 Tekan Enter untuk konfirmasi password
  passwordField.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      confirmBtn.click();
    }
  });

  if (saveBtn && form) {
    saveBtn.addEventListener('click', function (e) {
      e.preventDefault(); // cegah submit langsung

      // tampilkan popup jika group administrator
      if (selectedGroup === 'admin') {
        modal.style.display = 'flex';
        passwordField.value = '';
        passwordField.focus();
      } else {
        form.submit(); // langsung submit jika bukan admin
      }
    });
  }

  confirmBtn.addEventListener('click', function () {
    if (passwordField.value === 'rahasiaIT') {
      modal.style.display = 'none';
      form.submit(); // lanjut submit
    } else {
      errorMsg.style.display = 'block';
      setTimeout(() => errorMsg.style.display = 'none', 2000);
      passwordField.focus();
    }
  });

  cancelBtn.addEventListener('click', function () {
    modal.style.display = 'none';
  });

  // ✅ Tambahkan fungsi tombol X
  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      modal.style.display = 'none';
    });
  }

  // klik area luar untuk menutup
  modal.addEventListener('click', function (e) {
    if (e.target === modal) modal.style.display = 'none';
  });
});



// === Notifikasi gelembung ===
function showNotification(msg, type='success') {
  const notif = document.getElementById('notif');
  notif.textContent = msg;
  notif.className = 'notification-bubble ' + (type === 'error' ? 'error' : '');
  notif.style.display = 'flex';
  notif.style.opacity = '1';

  setTimeout(() => {
    notif.style.transition = 'opacity 0.5s ease';
    notif.style.opacity = '0';
    setTimeout(() => notif.style.display = 'none', 500);
  }, 3000);
}

// Tampilkan notifikasi dari URL (setelah redirect)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('status') === 'saved') {
  showNotification('✅ Hak akses berhasil disimpan!');
  window.history.replaceState({}, document.title, 'hak_akses_group.php'); // bersihkan query
}

// parent checked -> tampilkan / sembunyikan child
document.querySelectorAll('.parent-menu').forEach(parent => {
    parent.addEventListener('change', () => {
        const parentId = parent.value;
        const submenu = document.querySelector(`.submenu-container[data-parent="${parentId}"]`);
        if (submenu) {
            submenu.classList.toggle('visible', parent.checked);
            submenu.querySelectorAll('.child-menu').forEach(child => child.checked = parent.checked);
        }
    });
});
</script>


</body>
</html>
