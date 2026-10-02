<?php
require 'auth_check.php'; // pastikan session sudah dimulai
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/includes/access_helper.php';

ini_set('max_execution_time', 0); // 0 = tanpa batas waktu
set_time_limit(0); // alternatif

$username = $_SESSION['username'] ?? '';
$groupId = getUserGroup($username);

// 🔐 Cek hak akses ke halaman (contoh: upload)
if (!canAccessMenu($groupId, 'upload')) {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit; // hentikan eksekusi halaman
}

// ✅ Hanya jika lolos cek akses, include sidebar
include BASE_PATH . '/sidebar.php';
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<div class="content">
  <h2>📂 Upload Data Ke Picking_Process</h2>

  <form id="uploadForm" enctype="multipart/form-data" class="upload-form">
    <div class="form-header">
      <label for="excelFile">Pilih file Excel (.xls / .xlsx):</label>
      <div class="form-actions">
        <a href="template_import.xlsx" class="download-template" download>
          📄 Unduh Template
        </a>
      </div>
    </div>
    <input type="file" name="excelFile" id="excelFile" accept=".xls,.xlsx" required>
  </form>

  <div id="previewArea"></div>
</div>

<style>
:root {
  --primary: #1976d2;
  --primary-dark: #0d47a1;
  --border: #e0e0e0;
  --shadow: rgba(0, 0, 0, 0.1);
}

body {
  background: linear-gradient(135deg, #e3f2fd, #f9fbe7);
  font-family: "Segoe UI", sans-serif;
  margin: 0;
  padding: 0;
}

.content {
  padding: 25px;
  min-height: calc(100vh - 60px);
  animation: fadeIn 0.5s ease;
}

h2 {
  color: var(--primary);
  margin-bottom: 18px;
}

/* === Upload Form === */
.upload-form {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 6px var(--shadow);
  transition: all 0.3s ease;
}
.upload-form:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 22px rgba(25, 118, 210, 0.25);
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
}
.form-actions {
  display: flex;
  gap: 10px;
}
.download-template {
  background: linear-gradient(135deg, #4caf50, #388e3c);
  color: white;
  text-decoration: none;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.25s ease;
  box-shadow: 0 3px 8px rgba(76,175,80,0.3);
}
.download-template:hover {
  transform: scale(1.05);
  background: linear-gradient(135deg, #66bb6a, #43a047);
}

.upload-form input[type="file"] {
  display: block;
  margin-top: 10px;
  padding: 10px;
  border: 1px solid var(--border);
  border-radius: 8px;
  width: 100%;
  background: #fafafa;
  cursor: pointer;
  transition: all 0.3s ease;
}
.upload-form input[type="file"]:hover {
  border-color: var(--primary);
  background: #f1f8ff;
}

/* === Preview Area === */
#previewArea {
  margin-top: 25px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* === Card === */
.preview-card {
  background: #fff;
  border: 1px solid #dce4f0;
  border-radius: 14px;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
  overflow: hidden;
  animation: fadeSlideIn 0.5s ease forwards;
  transition: all 0.35s ease;
}
.preview-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: 0 12px 28px rgba(25, 118, 210, 0.28);
}

/* === Card Header === */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: white;
  padding: 14px 18px;
  font-weight: 600;
}
.card-actions button {
  background: rgba(255,255,255,0.15);
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 12px;
  margin-left: 8px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.25s ease;
}
.card-actions button:hover {
  background: rgba(255,255,255,0.3);
  transform: scale(1.05);
}

/* === Table Wrapper === */
.table-wrapper {
  overflow-x: auto;
  overflow-y: auto;
  max-height: 420px;
  padding: 14px;
  scrollbar-width: thin;
  scrollbar-color: #90caf9 #f1f8ff;
}
.table-wrapper::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.table-wrapper::-webkit-scrollbar-thumb {
  background: #90caf9;
  border-radius: 4px;
}
.table-wrapper::-webkit-scrollbar-track {
  background: #f1f8ff;
}

/* === Table === */
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  min-width: 700px;
}
th, td {
  padding: 8px 10px;
  border: 1px solid var(--border);
  text-align: left;
  white-space: nowrap;
}
th {
  background: var(--primary);
  color: white;
  position: sticky;
  top: 0;
}
tr:nth-child(even) { background: #f9f9f9; }

/* === Loading Card === */
.loading {
  background: #e3f2fd;
  color: var(--primary-dark);
  padding: 14px;
  border-radius: 6px;
  font-weight: 500;
  text-align: center;
}

/* === Animations === */
@keyframes fadeSlideIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ==== Mobile === */
@media (max-width: 768px) {
    .content {
        padding: 80px 16px 60px 16px !important;
        background: linear-gradient(180deg, #e3f2fd, #ffffff);
    }
    .form-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .download-template { width: 100%; text-align: center; }
}

button.delete {
  background: #e53935;
  color: white;
  border: none;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;
}
button.delete:hover { background: #b71c1c; }

/* ==== FIX POSISI DI MOBILE (lebih turun dari topbar) ==== */
@media (max-width: 768px) {
    .content {
        margin: 0 !important;
        padding: 50px 16px 60px 16px !important; /* ⬅️ dari 80px jadi 100px */
        background: linear-gradient(180deg, #e3f2fd, #ffffff);
    }

    .container {
        margin-top: 30px !important; /* ⬅️ tambah jarak */
        padding: 18px !important;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }

    h2 {
        margin-top: 10px !important; /* ⬅️ turun sedikit lagi */
        font-size: 18px;
        text-align: center;
        line-height: 1.4;
        color: #1976d2;
    }
}

 h2 {
       
        font-size: 25px;
        text-align: center;

    }

	
	
</style>

<script>
const excelInput = document.getElementById('excelFile');
const previewArea = document.getElementById('previewArea');

excelInput.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('action', 'preview');
  formData.append('excelFile', file);

  previewArea.innerHTML = `
    <div class="preview-card">
      <div class="card-header">📊 Membaca file Excel...</div>
      <div class="loading">🔄 Mohon tunggu, sedang memproses data...</div>
    </div>
  `;

  const res = await fetch('process_upload.php', { method: 'POST', body: formData });
  const html = await res.text();

  previewArea.innerHTML = `
    <div class="preview-card">
      <div class="card-header">
        📋 Preview Data
        <div class="card-actions">
          <button id="cancelPreview" class="delete">✖️ Cancel</button>
          <button id="confirmImport">✅️ Import</button>
        </div>
      </div>
      <div class="table-wrapper">${html}</div>
    </div>
  `;

  document.getElementById('confirmImport').addEventListener('click', async () => {
    const btn = document.getElementById('confirmImport');
    btn.disabled = true;
    btn.textContent = 'Mengimpor...';
    const res = await fetch('process_upload.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'import' })
    });
    const text = await res.text();
    previewArea.innerHTML = text;
  });

  document.getElementById('cancelPreview').addEventListener('click', async () => {
    await fetch('process_upload.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'cancel' })
    });
    previewArea.innerHTML = '';
    excelInput.value = '';
  });
});
</script>
