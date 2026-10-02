<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Guest';
$role = $_SESSION['role'] ?? '';

require_once __DIR__ . '/includes/access_helper.php';
$groupId = getUserGroup($username);

// === Tentukan base path dinamis ===
// Supaya link tetap benar meski sidebar dipanggil dari folder /admin/ atau lainnya
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath = rtrim(str_replace('/admin', '', $scriptDir), '/');
$basePath = $basePath ?: ''; // kosongkan kalau di root
?>

<!-- 🔝 TOPBAR -->
<div class="topbar">
    <div class="left">
        <button class="toggle-btn" onclick="toggleSidebar()" aria-label="Toggle Sidebar">☰</button>
        <span class="topbar-title">📦 Dashboard</span>
    </div>

    <div class="user-dropdown" id="userDropdown" onclick="toggleUserMenu(event)">
        <span class="user-info">
            👤 <?= htmlspecialchars($username) ?>
            <?php if ($role): ?>
                <span class="user-role">(<?= htmlspecialchars(ucfirst($role)) ?>)</span>
            <?php endif; ?>
        </span>
        <span class="dropdown-arrow">▼</span>
        <div class="user-menu" id="userMenu">
            <a href="<?= $basePath ?>/logout.php">🚪 Logout</a>
        </div>
    </div>
</div>

<!-- 📋 SIDEBAR -->
<div class="sidebar" id="sidebar">
  <h3 class="sidebar-title" style="font-size: 24px; color: #fff; font-weight: bold;">📦 Menu Utama</h3>

<?php if (canAccessMenu($groupId, 'updateGudangSubmenu')): ?>
    <!-- Menu Utama: Update Gudang -->
    <a href="javascript:void(0);" class="data-tooltip"
       onclick="toggleSubmenu('updateGudangSubmenu')" data-tooltip="Update Gudang">
        🚚 <span>Update Gudang</span>
    </a>

    <!-- Submenu untuk Picking Process dan CP Batch Transfer -->
    <div id="updateGudangSubmenu" class="submenu" style="display: none;">
        <?php if (canAccessMenu($groupId, 'updatepicking')): ?>
            <a href="<?= $basePath ?>/dashboard_picking.php"
               class="<?= ($currentPage == 'dashboard_picking.php') ? 'active' : '' ?>">
               📋 <span>Picking Process</span></a>
        <?php endif; ?>

        <?php if (canAccessMenu($groupId, 'updatecpbatch')): ?>
            <a href="<?= $basePath ?>/dashboard_cp_batch.php"
               class="<?= ($currentPage == 'dashboard_cp_batch.php') ? 'active' : '' ?>">
               🧾 <span>CP Batch Transfer</span></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Menu Lainnya -->
<?php if (canAccessMenu($groupId, 'updatemultimenu')): ?>
    <a href="<?= $basePath ?>/dashboard_multi_update.php"
       class="<?= ($currentPage == 'dashboard_multi_update.php') ? 'active' : '' ?>" data-tooltip="Multi Update">
       📝 <span>Multi Update</span></a>
<?php endif; ?>

<?php if (canAccessMenu($groupId, 'upload')): ?>
    <a href="<?= $basePath ?>/import_excel.php"
       class="<?= ($currentPage == 'import_excel.php') ? 'active' : '' ?>" data-tooltip="Upload Excel">
       📂 <span>Upload Data</span></a>
<?php endif; ?>

<?php if (canAccessMenu($groupId, 'riwayatSubmenu')): ?>
    <!-- Menu Utama: Riwayat -->
    <a href="javascript:void(0);" class="data-tooltip"
       onclick="toggleSubmenu('riwayatSubmenu')" data-tooltip="Riwayat">
        🕒 <span>Riwayat</span>
    </a>

    <!-- Submenu Riwayat -->
    <div id="riwayatSubmenu" class="submenu" style="display: none;">
        <?php if (canAccessMenu($groupId, 'riwayatgudang')): ?>
            <a href="<?= $basePath ?>/riwayat_update.php"
               class="<?= ($currentPage == 'riwayat_update.php') ? 'active' : '' ?>">
               📹 <span>Riwayat Update Gudang</span></a>
        <?php endif; ?>

        <?php if (canAccessMenu($groupId, 'riwayatmulti')): ?>
            <a href="<?= $basePath ?>/riwayat_update_multi.php"
               class="<?= ($currentPage == 'riwayat_update_multi.php') ? 'active' : '' ?>">
               📰 <span>Riwayat Multi Update</span></a>
        <?php endif; ?>

        <?php if (canAccessMenu($groupId, 'riwayatupload')): ?>
            <a href="<?= $basePath ?>/view_upload_data.php" class="<?= ($currentPage == 'view_upload_data.php') ? 'active' : '' ?>">
              📊<span>Riwayat Upload Data</span></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (canAccessMenu($groupId, 'toolsSubmenu')): ?>
    <!-- Menu Utama: Tools -->
    <a href="javascript:void(0);" class="data-tooltip"
       onclick="toggleSubmenu('tools')" data-tooltip="Tools">
        ✨ <span>Tools</span>
    </a>
	
	 <div id="tools" class="submenu" style="display: none;">
        <?php if (canAccessMenu($groupId, 'tools')): ?>
            <a href="<?= $basePath ?>/rumus.php"
               class="<?= ($currentPage == 'rumus.php') ? 'active' : '' ?>">
              ⚔️<span>Rumus Concatenate</span></a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<!-- Menu admin -->

<?php if (canAccessMenu($groupId, 'adminmenu')): ?>
    <a href="javascript:void(0);" class="data-tooltip"
       onclick="toggleSubmenu('managementadmin')" data-tooltip="Admin">
        👑 <span>Manajemen Admin</span>
    </a>
<?php endif; ?>	
	<div id="managementadmin" class="submenu" style="display: none;">


<?php if (canAccessMenu($groupId, 'userGroupMenu')): ?>
    <a href="<?= $basePath ?>/admin/user_group.php" class="<?= ($currentPage == 'user_group.php') ? 'active' : '' ?>">🧩 <span>User Group</span></a>
<?php endif; ?>

<?php if (canAccessMenu($groupId, 'manajemenAksesMenu')): ?>
    <a href="<?= $basePath ?>/admin/manajemen_akses.php" class="<?= ($currentPage == 'manajemen_akses.php') ? 'active' : '' ?>">👥 <span>Manajemen Akses</span></a>
<?php endif; ?>

<?php if (canAccessMenu($groupId, 'hakAksesMenu')): ?>
    <a href="<?= $basePath ?>/admin/hak_akses_group.php" class="<?= ($currentPage == 'hak_akses_group.php') ? 'active' : '' ?>">🛡️ <span>Hak Akses Group</span></a>
<?php endif; ?>
</div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>


<style>
/* === RESET === */
* { box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    margin: 0;
    padding: 0;
}

/* === TOPBAR === */
.topbar {
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    height: 60px;
    background: linear-gradient(135deg, #1976d2, #1565c0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 1001;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.topbar .left { display: flex; align-items: center; gap: 10px; }
.toggle-btn {
    background: rgba(255,255,255,0.15);
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
}
.toggle-btn:hover { background: rgba(255,255,255,0.25); transform: scale(1.05); }
.topbar-title { font-size: 19px; font-weight: 600; }

/* === USER DROPDOWN === */
.user-dropdown {
    position: relative;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    padding: 6px 12px;
    border-radius: 20px;
    background: rgba(255,255,255,0.15);
}
.user-dropdown:hover {
    background-color: #007bff; /* Ganti warna latar belakang saat hover */
    color: white;              /* Ganti warna teks saat hover */
    cursor: pointer;          /* Ganti cursor menjadi pointer saat hover */
}

.user-menu {
    display: none;
    position: absolute;
    right: 0; top: 50px;
    background: #fff;
    color: #333;
    border-radius: 8px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    overflow: hidden;
}
.user-menu a {
    display: block;
    padding: 10px 14px;
    color: #333;
    text-decoration: none;
}
.user-menu a:hover { background: #f1f1f1; color: #1976d2; }
/* === SIDEBAR === */
.sidebar {
    position: fixed;
    top: 60px;
    left: 0;
    width: 240px;
    height: calc(100% - 60px);
    background: linear-gradient(180deg, #1976d2, #0d47a1);
    color: white;
    transition: all 0.3s ease;
    overflow-y: auto;
    z-index: 1000;
}
.sidebar h3 {
    text-align: center;
    margin: 0;
    padding: 15px;
    font-size: 16px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}
.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255,255,255,0.9);
    padding: 12px 18px;
    text-decoration: none;
    font-size: 14px;
    border-left: 3px solid transparent;
    transition: all 0.3s ease;
    position: relative;
}
.sidebar a:hover {
    background: rgba(255,255,255,0.15);
    color: #fff;
    border-left-color: #fff;
}
.sidebar a.active {
    background: rgba(255,255,255,0.25);
    border-left-color: #fff;
    font-weight: bold;
}

/* === ICON-ONLY MODE (desktop only) === */
@media (min-width: 769px) {
    .sidebar.closed {
        width: 70px;
    }
    .sidebar.closed h3,
    .sidebar.closed a span {
        display: none;
    }
    .sidebar.closed a {
        justify-content: center;
        font-size: 18px;
        padding: 14px 0;
    }
    .sidebar.closed a::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 80px;
        background: rgba(0,0,0,0.85);
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        opacity: 0;
        pointer-events: none;
        font-size: 13px;
        white-space: nowrap;
        transition: all 0.2s ease;
        transform: translateY(-50%);
        top: 50%;
    }
    .sidebar.closed a:hover::after {
        opacity: 1;
        left: 75px;
    }
}

/* === MOBILE SIDEBAR SLIDE === */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 260px;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.4);
        z-index: 999;
        transition: opacity 0.3s ease;
    }
    .sidebar-overlay.open {
        display: block;
        opacity: 1;
    }
    .content { margin-left: 0; }
}

.topbar-title {
    font-size: 19px;
    font-weight: 600;
    white-space: nowrap;        /* ⛔ Cegah pindah baris */
    display: flex;
    align-items: center;
    gap: 6px;                   /* Spasi antara ikon 📦 dan teks */
}


/* === CONTENT === */
.content {
    margin-top: 60px;
    margin-left: 240px;
    transition: margin-left 0.3s ease;
}
.sidebar.closed ~ .content {
    margin-left: 80px;
}
/* Submenu */
.submenu {
    display: none; /* Mulai dengan submenu tersembunyi */
    opacity: 0;    /* Opacity 0 agar tidak terlihat */
    transform: translateY(-10px); /* Mulai sedikit terangkat */
    transition: all 0.3s ease-in-out; /* Transisi halus */
    margin-left: 20px;
}

/* Ketika submenu terlihat */
.submenu.show {
    display: block;  /* Menampilkan submenu */
    opacity: 1;      /* Opacity penuh */
    transform: translateY(0); /* Posisi normal */
}

/* Menambahkan animasi saat hover untuk submenu item */
.submenu a {
    transition: transform 0.3s ease;
}

.submenu a:hover {
    transform: translateX(5px);  /* Efek geser sedikit ke kanan saat hover */
}
/* Menu link tanpa submenu (tanpa panah) */
/* Menu link tanpa submenu (tanpa panah) */
a {
    text-decoration: none; /* Hilangkan garis bawah pada tautan */
    color: #333; /* Warna teks standar */
    display: inline-flex;
    align-items: center;
    padding: 10px 15px;
    font-size: 16px;
    transition: all 0.3s ease; /* Transisi halus untuk semua perubahan */
}

/* Menambahkan ikon panah ke menu yang memiliki submenu */
a[data-tooltip="Update Gudang"]::after,
a[data-tooltip="Riwayat"]::after,
a[data-tooltip="Admin"]::after,
a[data-tooltip="Tools"]::after {
    content: ' ✦';  /* Menambahkan ikon panah ke menu yang memiliki submenu */
    font-size: 12px; /* Ukuran ikon panah */
    margin-left: 5px; /* Jarak antara teks dan ikon */
    transition: transform 0.3s ease; /* Transisi halus saat ikon berubah */
}

/* Mengubah ikon panah menjadi panah atas saat submenu terbuka */
a[data-tooltip="Update Gudang"].open::after,
a[data-tooltip="Riwayat"].open::after,
a[data-tooltip="Admin"].open::after,
a[data-tooltip="Tools"].open::after
 {
    content: ' ▲';  /* Mengubah ikon menjadi panah atas saat submenu terbuka */
}



/* Efek hover untuk menu link */
a[data-tooltip="Update Gudang"]:hover,
a[data-tooltip="Riwayat"]:hover,
a[data-tooltip="Admin"]:hover,
a[data-tooltip="Tools"]:hover {
    transform: scale(1.05); /* Membesarkan elemen sedikit */
    text-shadow: 0 0 10px rgba(25, 118, 210, 0.7); /* Tambahkan efek cahaya pada teks */
}

/* Menambahkan transisi halus pada ikon */
a[data-tooltip="Update Gudang"] span,
a[data-tooltip="Riwayat"],
a[data-tooltip="Admin"],
a[data-tooltip="Tools"]:hover span {
    margin-left: 5px; /* Memberikan jarak antara ikon dan teks */
    transition: transform 0.3s ease; /* Efek transisi pada ikon */
}


</style>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleButton = document.querySelector('.toggle-sidebar-btn');
    const menuLinks = document.querySelectorAll(
        '[data-tooltip="Update Gudang"], [data-tooltip="Riwayat"], [data-tooltip="Admin"], [data-tooltip="Tools"]'
    );

    // ⚠️ Hentikan fungsi jika sidebar tidak ada
    if (!sidebar) {
        console.warn("⚠️ Elemen #sidebar tidak ditemukan.");
        return;
    }

    // 💻 Desktop collapse
    if (window.innerWidth > 768) {
        sidebar.classList.toggle('closed');
        const isCollapsed = sidebar.classList.contains('closed');
        localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');

        // Reset ikon saat collapse/expand
        menuLinks.forEach(link => link.classList.remove('open'));
        if (toggleButton) {
            toggleButton.classList.toggle('collapsed', isCollapsed);
            toggleButton.classList.toggle('expanded', !isCollapsed);
        }
    } 
    // 📱 Mobile slide
    else {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open');  // overlay mungkin null
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    }
}


function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
}

function toggleUserMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('userMenu');
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    const dropdown = document.getElementById('userDropdown');
    if (dropdown && !dropdown.contains(e.target)) menu.style.display = 'none';
});

// === Fungsi toggle submenu ===
function toggleSubmenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    const menuLink = document.querySelector(`[onclick="toggleSubmenu('${submenuId}')"]`);
    if (!submenu || !menuLink) return; // ⛔ Aman jika submenu disembunyikan

    const isOpen = localStorage.getItem(submenuId + 'State') === 'open';

    if (isOpen) {
        submenu.style.display = 'none';
        submenu.classList.remove('show');
        menuLink.classList.remove('open');
        localStorage.setItem(submenuId + 'State', 'closed');
    } else {
        submenu.style.display = 'block';
        setTimeout(() => submenu.classList.add('show'), 10);
        menuLink.classList.add('open');
        localStorage.setItem(submenuId + 'State', 'open');
    }
}

// === Saat halaman dimuat ===
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const submenuIds = ['updateGudangSubmenu', 'riwayatSubmenu', 'managementadmin', 'tools'];

    // 🔹 Set status sidebar (expanded / collapsed)
    const sidebarState = localStorage.getItem('sidebarState');
    if (sidebarState === 'collapsed') sidebar.classList.add('closed');

    // 🔹 Set setiap submenu sesuai localStorage
    submenuIds.forEach(id => {
        const submenu = document.getElementById(id);
        const menuLink = document.querySelector(`[onclick="toggleSubmenu('${id}')"]`);
        if (!submenu || !menuLink) return; // ✅ lewati jika menu tidak ada

        const isOpen = localStorage.getItem(id + 'State') === 'open';
        submenu.style.display = isOpen ? 'block' : 'none';
        submenu.classList.toggle('show', isOpen);
        menuLink.classList.toggle('open', isOpen);
    });
});

// === Menutup submenu saat klik di luar sidebar ===
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    const submenuIds = ['updateGudangSubmenu', 'riwayatSubmenu', 'managementadmin', 'tools'];
    const sidebarClicked = sidebar.contains(e.target);
    const menuLinks = document.querySelectorAll('[data-tooltip="Update Gudang"], [data-tooltip="Riwayat"], [data-tooltip="Admin"], [data-tooltip="Tools"]');
    
    submenuIds.forEach(id => {
        const submenu = document.getElementById(id);
        if (!submenu) return; // ✅ aman jika tidak ada
        if (!sidebarClicked && !submenu.contains(e.target)) {
            submenu.style.display = 'none';
            submenu.classList.remove('show');
            menuLinks.forEach(link => link.classList.remove('open'));
            localStorage.setItem(id + 'State', 'closed');
        }
    });
});

// Mencegah klik di dalam submenu menutupnya
document.querySelectorAll('.submenu').forEach(submenu => {
    submenu.addEventListener('click', e => e.stopPropagation());
});
</script>

