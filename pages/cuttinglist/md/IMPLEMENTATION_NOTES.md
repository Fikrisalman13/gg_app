# Implementasi Fitur Cutting List - Dokumentasi Lengkap

## 📋 Fitur yang Diimplementasikan

### 1. ✅ Pengaturan Toleransi Cutting (Std, Max, Min)

**Deskripsi:**
- Menambahkan field toleransi di form input piece
- Toleransi ditambahkan ke nilai std, max, min secara otomatis
- Format: Jika toleransi = 0.30 dan std = 30, maka hasil = 30.30

**File yang Dimodifikasi:**
- [modal_piece.php](modal_piece.php) - Tambah input field toleransi
- [tambahcutting.php](tambahcutting.php) - Update tabel dengan kolom Tol
- [js/cuttinglist.js](js/cuttinglist.js) - Hitung nilai dengan toleransi

**Implementasi Detail:**
```javascript
// Di cuttinglist.js
const toleransi = parseFloat($('#toleransiPotong').val()) || 0;
const std = parseFloat($('#stdPotong').val());
const max = parseFloat($('#maxPotong').val());
const min = parseFloat($('#minPotong').val());

const stdTol = (std + toleransi).toFixed(3);  // 30 + 0.30 = 30.30
const maxTol = (max + toleransi).toFixed(3);
const minTol = (min + toleransi).toFixed(3);
```

---

### 2. ✅ Auto-Reload Setelah Process/Unprocess

**Deskripsi:**
- Ketika proses cutting atau unprocess berhasil, halaman langsung reload
- Data terbaru otomatis ditampilkan di index.php
- Tidak perlu manual refresh halaman

**File yang Dimodifikasi:**
- [index.php](index.php) - Update handler process/unprocess button

**Implementasi Detail:**
```javascript
success: function(response) {
    if (response.status === 'ok') {
        alert('Berhasil diproses: ' + response.processed + ' item');
        location.reload();  // Auto reload setelah proses
    }
},
// Untuk unprocess: delay 1 detik untuk ensure data terupdate
setTimeout(function() {
    location.reload();
}, 1000);
```

---

### 3. ✅ Edit/Hapus Piece & Cacat di Detail (Jika Status Unprocessed)

**Deskripsi:**
- Di tab Detail, jika status masih "unprocessed" (belum diproses), button hapus muncul
- Dapat menghapus piece (beserta cacat terkait) dan cacat secara individual
- Jika sudah "processed", button hapus tidak terlihat (read-only)

**File yang Dimodifikasi:**
- [detail_cutting.php](detail_cutting.php) - Tambah tombol hapus dengan kondisi status
- [delete_piece.php](delete_piece.php) - NEW - Handler hapus piece
- [delete_cacat.php](delete_cacat.php) - NEW - Handler hapus cacat

**Implementasi Detail:**

Di detail_cutting.php - Kondisional tombol hapus:
```php
<?php if (!$h['processed']): ?>
    <button class="btn btn-sm btn-danger btn-hapus-piece-detail" 
            data-piece-id="<?= $p['id_piece'] ?>">
        <i class="fas fa-trash"></i>
    </button>
<?php endif; ?>
```

JavaScript handler (di detail_cutting.php):
```javascript
$(document).on('click', '.btn-hapus-piece-detail', function() {
    const pieceId = $(this).data('piece-id');
    $.ajax({
        url: 'delete_piece.php',
        type: 'POST',
        data: { id_piece: pieceId },
        success: function(response) {
            if (response.status === 'ok') {
                alert('Piece berhasil dihapus');
                location.reload();
            }
        }
    });
});
```

---

### 4. ✅ Konversi Yard (UOM CP = Yard, Type Counter = Meter)

**Deskripsi:**
- Jika UOM CP = Yard dan Type Counter = Meter, nilai std, max, min, panjang_awal, panjang_akhir, dan panjang akhir konversi ke yard
- Rumus konversi: nilai_meter ÷ 0.9144 = nilai_yard
- Konversi dilakukan saat save data ke database

**File yang Dimodifikasi:**
- [save_cutting.php](save_cutting.php) - Tambah logic konversi sebelum insert

**Implementasi Detail:**
```php
// Di save_cutting.php
if ($data['uom_cp'] === 'Y' && $data['type_counter'] === 'M') {
    // Konversi meter ke yard: dibagi 0.9144
    $panjang_awal = $panjang_awal / 0.9144;
    $panjang_akhir = $panjang_akhir / 0.9144;
    $std = $std / 0.9144;
    $min = $min / 0.9144;
    $max = $max / 0.9144;
}
```

**Catatan:**
- Data yang disimpan di database sudah dalam satuan yard
- Proses cutting (process_cutting.php) menggunakan data yang sudah terkonversi
- Tidak perlu konversi tambahan di tahap proses karena data sudah konsisten

---

## 📁 File-File yang Dibuat/Dimodifikasi

### File Baru:
- [delete_piece.php](delete_piece.php) - Handler hapus piece + cacat terkait
- [delete_cacat.php](delete_cacat.php) - Handler hapus cacat

### File Dimodifikasi:
- [modal_piece.php](modal_piece.php) - Tambah field toleransi
- [tambahcutting.php](tambahcutting.php) - Update tabel dengan kolom toleransi
- [js/cuttinglist.js](js/cuttinglist.js) - Update proses piece dengan toleransi
- [index.php](index.php) - Improve reload setelah process/unprocess
- [detail_cutting.php](detail_cutting.php) - Tambah tombol hapus + script handler
- [save_cutting.php](save_cutting.php) - Tambah konversi yard

---

## 🔄 Flow Aplikasi

### Flow Tambah Data dengan Toleransi:
```
1. Buka tambahcutting.php
2. Isi CP No, UOM CP, Mesin, Type Counter
3. Klik "Tambah Piece"
4. Modal terbuka:
   - Isi Piece, Panjang Awal, Panjang Akhir, Lebar
   - Isi Std, Max, Min
   - Isi Toleransi (misal 0.30)
   - Sistem auto-hitung: Std + Tol = 30 + 0.30 = 30.30
5. Bisa tambah Cacat dengan kode dari master
6. Klik "Apply" → baris ditambah ke tabel dengan nilai toleransi
7. Klik "Save" → simpan ke database
   - Jika UOM=Y, TypeCounter=M → konversi ke yard
   - Data tersimpan dengan toleransi sudah ditambahkan
```

### Flow Process/Unprocess dengan Auto-Reload:
```
1. Di index.php, pilih CP No (status: Not Processed)
2. Klik "Processed" button
3. Sistem process cutting data
4. Alert sukses muncul
5. Halaman auto-reload (location.reload())
6. Status berubah ke "Processed" otomatis
7. Untuk unprocess: ada delay 1 detik sebelum reload
```

### Flow Edit/Hapus di Detail:
```
1. Di index.php, buka tab "Detail"
2. Pilih 1+ CP No, klik "Detail" tab
3. Muncul tab: Piece, Cacat, Hasil Proses Cutting
4. Jika status "unprocessed" (belum diproses):
   - Ada tombol hapus (🗑️) di setiap baris piece dan cacat
5. Klik tombol hapus → konfirmasi
6. Sistem hapus data via AJAX (delete_piece.php / delete_cacat.php)
7. Halaman auto-reload untuk tampilkan data terbaru
8. Jika sudah "processed" → tombol hapus tidak muncul (read-only)
```

### Flow Konversi Yard:
```
1. Isi form dengan:
   - Panjang Awal: 100 (meter)
   - Panjang Akhir: 50 (meter)
   - Std: 30 (meter)
   - UOM CP: Yard
   - Type Counter: Meter
2. Klik Save
3. save_cutting.php deteksi kondisi:
   - UOM CP = "Y" AND Type Counter = "M"
4. Konversi nilai:
   - Panjang Awal: 100 ÷ 0.9144 = 109.36 yard
   - Panjang Akhir: 50 ÷ 0.9144 = 54.68 yard
   - Std: 30 ÷ 0.9144 = 32.81 yard
5. Simpan ke database dalam satuan yard
6. Proses cutting menggunakan data yard (sudah terkonversi)
```

---

## 🧪 Testing Checklist

- [ ] Toleransi: Isi 0.30, cek apakah std menjadi 30.30 ✓
- [ ] Process: Klik process, cek apakah page reload otomatis ✓
- [ ] Unprocess: Klik unprocess, cek apakah page reload setelah 1 detik ✓
- [ ] Hapus Piece: Status unprocessed, tombol hapus muncul, klik hapus ✓
- [ ] Hapus Cacat: Status unprocessed, tombol hapus muncul, klik hapus ✓
- [ ] Readonly: Status processed, tombol hapus tidak muncul ✓
- [ ] Konversi: UOM=Yard, TypeCounter=Meter, cek konversi berhasil ✓

---

## 📝 Database Requirement

Pastikan tabel sudah memiliki kolom:

```sql
-- cl_cutting_header
- id_header (PK)
- cp_no
- type_counter (M/Y)
- uom_cp (M/Y)
- processed (0/1)
- created_by
- created_date
- updated_by
- updated_date

-- cl_cutting_piece
- id_piece (PK)
- id_header (FK)
- piece_no
- piece_code
- panjang_awal
- panjang_akhir
- susut
- lebar_kain
- standart_potong
- min_potong
- max_potong
- satuan (UOM)
- created_by
- created_date

-- cl_cutting_cacat
- id_cacat (PK)
- id_piece (FK)
- cacat_no
- kode_cacat
- nama_cacat
- status_cacat
- dari
- sampai
- panjang_cacat
- created_by
- created_date

-- cl_cutting_process
- id_process (PK)
- id_piece (FK)
- process_no
- start_pos
- end_pos
- hasil_cutting
- kategori
- created_by
- created_date

-- cl_cutting_summary
- id_summary (PK)
- id_piece (FK)
- kategori
- total_pcs
- total_panjang
- created_by
- created_date
```

---

## ⚠️ Catatan Penting

1. **Toleransi**: Disimpan langsung ke database (tidak ada kolom toleransi terpisah)
   - Nilai std/max/min di database sudah termasuk toleransi
   - Toleransi hanya untuk perhitungan di UI

2. **Konversi Yard**: 
   - Hanya berlaku saat save (save_cutting.php)
   - Tidak ada flag/kolom konversi di database
   - Data disimpan dalam satuan hasil konversi

3. **Auto-Reload**:
   - Menggunakan `location.reload()` standard browser
   - Unprocess dengan delay 1 detik untuk ensure update database
   - User akan melihat refresh halaman seketika

4. **Edit/Hapus Piece & Cacat**:
   - Hanya bisa dilakukan jika status = unprocessed
   - Hapus piece otomatis hapus cacat terkait (cascade)
   - Perubahan langsung reflect di UI setelah reload

---

## 🚀 Deployment Notes

1. Upload semua file yang termodifikasi
2. Pastikan file delete_piece.php dan delete_cacat.php sudah terupload
3. Test semua fitur di environment testing dulu
4. Clear browser cache jika ada masalah dengan JS lama

---

**Status**: ✅ COMPLETED - Semua fitur sudah diimplementasikan
**Last Updated**: 22 Desember 2025
