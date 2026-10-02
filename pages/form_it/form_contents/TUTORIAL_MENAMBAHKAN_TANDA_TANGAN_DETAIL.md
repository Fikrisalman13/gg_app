# Tutorial: Menambahkan Logic Tanda Tangan pada Halaman Detail

Tutorial ini menjelaskan cara menstandarisasi fitur tanda tangan pada halaman detail form. Gunakan detail form aktif yang jumlah dan urutan role-nya sama sebagai referensi; untuk lima role gunakan pola `detail_akses_internet.php` atau `detail_pengajuan_aplikasi.php`.

## 1. Persiapan Backend (PHP)

Tambahkan kode berikut di bagian atas file detail PHP setelah query data utama form.

### A. Cek Permission User (`User_TTD_Template`)
Cek hanya role yang memang dibutuhkan form, misalnya Pemohon, Atasan Pemohon, Petugas IT, Kabag IT, dan Kadept IT.

```php
// Permission check
$canSignPemohon = $canSignAtasan = $canSignPetugasIT = $canSignKabagIT = $canSignKadeptIT = false;

if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT DISTINCT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            elseif (strcasecmp($role, 'Atasan Pemohon') === 0) $canSignAtasan = true;
            elseif (strcasecmp($role, 'Petugas IT') === 0) $canSignPetugasIT = true;
            elseif (strcasecmp($role, 'Kabag IT') === 0) $canSignKabagIT = true;
            elseif (strcasecmp($role, 'Kadept IT') === 0) $canSignKadeptIT = true;
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

Gunakan tabel 4 atau 5 kolom sesuai alur persetujuan. Jangan menambah atau mengganti role hanya agar sama dengan file contoh. Untuk lima role standar aplikasi, urutannya: Pemohon, Atasan Pemohon, Petugas IT, Kabag IT, Kadept IT. Gunakan `table-layout: fixed` dan lebar kolom yang sama.

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

## 3. Modal & JavaScript

Gunakan komponen bersama:

```php
<?php include 'form_contents/components/signature_canvas.php'; ?>
```

Komponen menyediakan `#modalTtd`, `#ttdRoleCode`, canvas/upload template, dan event `ttdSaved`. Jangan menyalin modal canvas lengkap ke setiap detail.

Halaman detail tetap perlu menyediakan modal konfirmasi hapus atau konfirmasi SweetAlert, serta JavaScript untuk:
1. Mengirim `ticket` dan `role_code` ke `ttd_sign.php`.
2. Mengisi `#ttdRoleCode` lalu membuka `#modalTtd` bila respons berisi `need_template`.
3. Menangani event `ttdSaved` untuk memperbarui area role.
4. Mengirim penghapusan ke `delete_ttd.php`.
5. Menahan semua tombol tanda tangan ketika status `Ditolak`.

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
- Pilih referensi detail berdasarkan jumlah dan urutan role, bukan berdasarkan kemiripan nama form.
- Beri border penuh hanya pada tabel tanda tangan. Hindari selector global yang memberi border ke semua `<td>` tabel bersarang.
- Gunakan `table-layout: fixed`, tinggi area konsisten, dan `object-fit: contain` untuk gambar.
- Pemetaan `GroupRole` di `User_TTD_Template`, PHP, `ttd_sign.php`, dan `delete_ttd.php` harus persis sama.
- Setelah sign/delete, perbarui gambar, label nama, dan tombol tanpa memberi user lain hak menghapus tanda tangan.
