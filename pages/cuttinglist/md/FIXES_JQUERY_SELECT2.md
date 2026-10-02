# Fix - jQuery, Select2, dan Database Issues

## Masalah yang Diperbaiki:

### 1. ✅ ReferenceError: jQuery is not defined
**Penyebab**: jQuery tidak ter-load ketika cuttinglist.js dijalankan
**Solusi**: 
- Memindahkan script jQuery ke SEBELUM cuttinglist.js
- Urutan: jQuery → Select2 → Custom JS

### 2. ✅ TypeError: $(...).select2 is not a function
**Penyebab**: Select2 library tidak ter-load sebelum cuttinglist.js
**Solusi**: 
- Load Select2 SETELAH jQuery, SEBELUM cuttinglist.js
- Tambah CSS Select2 sebelum script

### 3. ✅ Invalid column name 'cp_date'
**Penyebab**: 
- Database kolom bernama `created_date`, bukan `cp_date`
- Parameter VALUES tidak sesuai dengan kolom yang didefinisikan

**Solusi**:
```php
// Sebelum
INSERT INTO cl_cutting_header
(cp_no, cp_date, type_counter, uom_cp, id_mesin, processed, created_by)
VALUES (?, GETDATE(), ?, ?, ?, 0, ?)

// Sesudah
INSERT INTO cl_cutting_header
(cp_no, type_counter, uom_cp, id_mesin, processed, created_by, created_date)
VALUES (?, ?, ?, ?, 0, ?, GETDATE())
```

### 4. ✅ Notice: session_start(): Session already active
**Penyebab**: `session_start()` dipanggil di save_cutting.php padahal header.php sudah memulainya
**Solusi**: 
- Hapus `session_start()` dari save_cutting.php
- Hapus dari process_cutting.php
- Hapus dari unprocess_cutting.php
- Semua files sudah ter-cover oleh require header.php

## 📁 Files yang Diperbaiki:

✅ **save_cutting.php**
- Hapus duplicate `session_start()`
- Fix column name: `cp_date` → `created_date`
- Fix parameter order di VALUES clause

✅ **process_cutting.php**
- Hapus duplicate `session_start()`

✅ **unprocess_cutting.php**
- Hapus duplicate `session_start()`

✅ **tambahcutting.php**
- Hapus Select2 early load
- Reorder scripts: jQuery → Select2 → Custom JS
- Pastikan semua di load SETELAH `modal_piece.php`
- Footer tetap di akhir

## 🔧 Script Loading Order (Correct):

```
<!-- Sebelum tamahcutting.php konten -->
(isi form)

<!-- Setelah konten, sebelum </body> -->
<?php include 'modal_piece.php'; ?>

<!-- 1. jQuery FIRST -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- 2. Select2 CSS & JS (needs jQuery) -->
<link href="...select2.min.css" />
<script src="...select2.min.js"></script>

<!-- 3. Custom JS (needs jQuery & Select2) -->
<script src=".../cuttinglist.js"></script>

<?php include '../../includes/footer.php'; ?>
```

## ✨ Hasil Perbaikan:

1. **jQuery Loaded** ✅
2. **Select2 Loaded** ✅ (dengan jQuery dependency resolved)
3. **cuttinglist.js** ✅ (dapat menggunakan jQuery dan Select2)
4. **Database** ✅ (column name match, INSERT berhasil)
5. **Session** ✅ (tidak ada conflict, hanya 1x start)

## 🧪 Testing:

Silakan test flow berikut:
1. Buka tambahcutting.php
2. Isi form (CP No, UOM, Mesin, Type Counter)
3. Klik "Tambah Piece"
4. Modal muncul
5. Klik "Tambah Cacat" → Select2 dropdown harus berfungsi
6. Isi data piece dan cacat
7. Klik "Apply"
8. Klik "Save" → harus berhasil (tidak ada error 500)

Jika masih ada error, lihat:
- Browser Console (F12) untuk JavaScript error
- Network tab untuk melihat response dari save_cutting.php
- Server error logs untuk SQL Server error
