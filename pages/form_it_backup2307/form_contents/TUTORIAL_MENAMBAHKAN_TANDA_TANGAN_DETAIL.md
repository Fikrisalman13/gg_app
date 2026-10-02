# Tutorial: Menambahkan Logic Tanda Tangan pada Halaman Detail

Tutorial ini menjelaskan cara menstandarisasi fitur tanda tangan pada halaman detail form (contoh: `detail_form_baru.php`), agar sesuai dengan template `detail_cctv.php` atau `detail_perubahan_data_database.php`.

## 1. Persiapan Backend (PHP)

Tambahkan kode berikut di bagian atas file detail PHP anda (setelah query data utama form).

### A. Cek Permission User (`User_TTD_Template`)
Cek apakah user yang login memiliki hak akses sebagai 'Pemohon', 'Atasan Pemohon', 'Kabag IT', dll.

```php
// Permission check
$canSignPemohon = $canSignAtasan = $canSignKabagIT = $canSignKadeptIT = $canSignDireksi = false;

if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT DISTINCT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            elseif (strcasecmp($role, 'Atasan Pemohon') === 0) $canSignAtasan = true;
            elseif (strcasecmp($role, 'Kabag IT') === 0) $canSignKabagIT = true;
            elseif (strcasecmp($role, 'Kadept IT') === 0) $canSignKadeptIT = true;
            elseif (strcasecmp($role, 'Direksi') === 0) $canSignDireksi = true;
        }
    }
}
```

### B. Ambil Data Tanda Tangan (`Form_Pengajuan_Barang_TTD`)
Ambil data tanda tangan yang sudah tersimpan untuk tiket ini.

```php
// Ambil TTD yang sudah ada
$sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttd = [];
if ($stmtTtd && sqlsrv_has_rows($stmtTtd)) {
    while ($rowTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
        $role = $rowTtd['GroupRole'];
        $ttd[$role] = [
            'SignaturePath' => $rowTtd['SignaturePath'],
            'SignedByUserName' => $rowTtd['SignedByUserName'],
            'SignedByUserId' => $rowTtd['SignedByUserId']
        ];
    }
}

// Helper Functions
function isTtdByCurrentUser($ttdArray, $currentUserId) {
    if (!isset($ttdArray['SignaturePath']) || empty($ttdArray['SignaturePath'])) return false;
    if (!isset($ttdArray['SignedByUserId'])) return false;
    return $ttdArray['SignedByUserId'] == $currentUserId;
}
function isTtdExists($ttdArray) {
    return isset($ttdArray['SignaturePath']) && !empty($ttdArray['SignaturePath']);
}
```

## 2. Struktur Tampilan (HTML Template)

Gunakan struktur tabel 4 kolom standar berikut di dalam `detail_...php`. Sesuaikan kolom sesuai kebutuhan (misal `Diajukan`, `Diketahui`, `Mengetahui`, `Disetujui`).

### A. Tabel Tanda Tangan

```html
<div class="signature-wrapper">
<table class="signature-table" style="width:100%;border-collapse:collapse;" border="1">
  <!-- HEADER KOLOM -->
  <tr style="text-align:center;font-size:14px;font-weight:bold;">
    <td style="width:25%;padding:4px;">Diajukan oleh,</td>
    <td style="width:25%;padding:4px;">Diketahui oleh,</td>
    <td style="width:25%;padding:4px;">Mengetahui,</td>
    <td style="width:25%;padding:4px;">Disetujui oleh,</td>
  </tr>
  
  <!-- BARIS TANDA TANGAN -->
  <tr style="height:120px;text-align:center;vertical-align:middle;">
    
    <!-- CONTOH KOLOM 1: PEMOHON -->
    <td style="padding:6px;vertical-align:middle;">
      <!-- Area Gambar -->
      <div id="ttd-area-pemohon">
        <?php if (isset($ttd['Pemohon'])): ?>
            <img src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60" alt="TTD Pemohon">
        <?php endif; ?>
      </div>

      <!-- Logic Tombol -->
      <?php if ($canSignPemohon && !$isRejected): ?>
        <?php if (isTtdByCurrentUser($ttd['Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
          <!-- Jika user ini yang tanda tangan -> Tombol Hapus -->
          <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="pemohon">Hapus Tanda Tangan</button>
        <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
          <!-- Jika orang lain yang tanda tangan -> Disabled -->
          <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
        <?php else: ?>
          <!-- Belum ada -> Tombol Sign -->
          <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="pemohon">Tanda Tangan</button>
        <?php endif; ?>
      <?php endif; ?>
    </td>

    <!-- CONTOH KOLOM LAIN (SESUAIKAN LOGIC ROLE NYA) -->
    <!-- ... -->
    
  </tr>

  <!-- BARIS NAMA -->
  <tr style="text-align:center;font-weight:bold;font-size:14px;">
    <td style="padding:4px;" id="ttd-label-pemohon">
       <!-- Tampilkan Nama User jika ada, Jika tidak tampilkan Role sebagai placeholder -->
       <?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?>
    </td>
    <!-- ... -->
  </tr>
</table>
</div>
```

## 3. Modal & Javascript

Pastikan anda menyertakan:
1.  **Modal Konfirmasi Hapus**: `#modalKonfirmasiHapusTtd`
2.  **Modal Sukses Hapus**: `#modalSuksesHapusTtd`
3.  **Modal Canvas / Upload**: `#modalTtd` (dengan `<canvas id="ttdCanvas">`)

Dan Script JS untuk handle logic (mirip di `detail_cctv.php`):

```javascript
// Sign Button
$(document).on('click', '.btn-ttd', function(){
    var roleCode = $(this).data('role');
    // ... logic post ke ttd_sign.php ...
});

// Delete Button
$(document).on('click', '.btn-delete-ttd', function(){
    // ... logic post ke delete_ttd.php ...
});

// Update Area Function
function updateTtdArea(roleCode, imgUrl, name, signedByUserId){
    // Update Gambar
    // Update Label Nama
    // Update Tombol ('Hapus' vs 'Sudah Ditandatangani' vs 'Tanda Tangan')
}
```

## 4. File Pendukung Server-side
Pastikan file berikut sudah ada dan `ticket` prefix serta `role` mapping nya sudah sesuai:
- `ttd_sign.php` : Handle simpan gambar / status tanda tangan.
- `delete_ttd.php` : Handle hapus row di database.
- `ttd_save_template.php`: Handle simpan template tanda tangan user.

---
**Tips:**
- Selalu copy paste struktur tabel HTML dari `detail_cctv.php` untuk memastikan ukuran kolom dan border konsisten.
- Perhatikan pemetaan nama Role (`GroupRole`) di database (`User_TTD_Template`) dan di kode PHP (`strcasecmp`) harus persis sama.
