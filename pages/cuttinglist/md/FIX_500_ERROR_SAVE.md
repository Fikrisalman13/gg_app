# 🐛 500 Internal Server Error - save_cutting.php

## Masalah
Ketika click "Save" di tambahcutting.php, muncul error:
```
POST http://localhost:81/gg_app/pages/cuttinglist/save_cutting.php 500 (Internal Server Error)
```

## Kemungkinan Penyebab

### 1. Kolom Database Tidak Sesuai ❌ PALING MUNGKIN
Save_cutting.php mencoba insert ke kolom:
- `panjang_awal_conv`
- `panjang_akhir_conv`
- `created_date`

**Solusi:**
Pastikan kolom-kolom ini ada di tabel `cl_cutting_piece` dan `cl_cutting_cacat`.

```sql
-- Check if columns exist
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'cl_cutting_piece';
```

Jika kolom `panjang_awal_conv` dan `panjang_akhir_conv` tidak ada, jalankan:
```sql
ALTER TABLE cl_cutting_piece
ADD panjang_awal_conv DECIMAL(10,3) DEFAULT 0.000,
    panjang_akhir_conv DECIMAL(10,3) DEFAULT 0.000;
```

Jika kolom `created_date` tidak ada di `cl_cutting_cacat`, jalankan:
```sql
ALTER TABLE cl_cutting_cacat
ADD created_date DATETIME DEFAULT GETDATE();
```

---

### 2. Data Type Mismatch
Nilai yang dikirim tidak cocok dengan tipe kolom di database.

**Contoh:**
- Kolom expect INT tapi dikirim STRING
- Kolom expect DECIMAL tapi dikirim NULL

**Solusi:**
Check tipe data di database:
```sql
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'cl_cutting_piece'
ORDER BY ORDINAL_POSITION;
```

---

### 3. Koneksi Database Error
Database connection tidak aktif atau invalid.

**Solusi:**
1. Pastikan file `koneksi.php` tersedia
2. Test koneksi di file lain (misal index.php)
3. Check username/password SQL Server

---

### 4. Session UserName Tidak Tersedia
Ketika `$_SESSION['UserName']` tidak ada.

**Solusi:**
Pastikan user sudah login dan session active.

---

## Fixes Yang Sudah Diterapkan

✅ **Improvement 1: Error Handling**
```php
// BEFORE: print_r dengan array
throw new Exception('Insert gagal: ' . print_r(sqlsrv_errors(), true));

// AFTER: JSON encode dengan jelas
throw new Exception('Insert gagal: ' . json_encode(sqlsrv_errors()));
```

✅ **Improvement 2: Added created_date**
```php
// BEFORE: Missing created_date
INSERT INTO ... VALUES (?,?,?,...,?)

// AFTER: Added created_date
INSERT INTO ... VALUES (?,?,?,...,?,GETDATE())
```

✅ **Improvement 3: Null Safety**
```php
// BEFORE: Assume keys always exist
intval($p['piece_no'])

// AFTER: Use ?? for default
intval($p['piece_no'] ?? 1)
```

✅ **Improvement 4: Cacat Array Safety**
```php
// BEFORE: Direct loop, error if key missing
foreach ($p['cacat'] as $c)

// AFTER: Safe with default empty array
foreach ($p['cacat'] ?? [] as $c)
```

---

## How to Debug

### Step 1: Check Browser Console
- Press `F12`
- Go to Network tab
- Click "Save"
- Find POST request to save_cutting.php
- Check Response tab for error message

### Step 2: Check PHP Error Log
```
C:\xampp\apache\logs\error.log
atau
C:\xampp\php\logs\php_error.log
```

### Step 3: Test Database Connection
Buat file `test_db.php`:
```php
<?php
require_once 'koneksi.php';

$q = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM cl_cutting_header");
if ($q) {
    $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
    echo "Database OK: " . $r['total'] . " records";
} else {
    echo "Database ERROR: " . json_encode(sqlsrv_errors());
}
?>
```

---

## Checklist Sebelum Test

- [ ] Kolom `panjang_awal_conv` ada di `cl_cutting_piece`
- [ ] Kolom `panjang_akhir_conv` ada di `cl_cutting_piece`
- [ ] Kolom `created_date` ada di `cl_cutting_cacat`
- [ ] User sudah login (session active)
- [ ] Database connection working
- [ ] save_cutting.php file ter-update dengan improvements

---

## Workflow Debug

1. **Test Save**
   ```
   Buka F12 → Network tab
   Input data → Click "Save"
   Lihat response error message
   ```

2. **Lihat Exact Error**
   ```
   Response akan menunjukkan exact error seperti:
   "Column 'panjang_awal_conv' does not exist"
   atau
   "Conversion failed when converting string to float"
   ```

3. **Fix Accordingly**
   ```
   Jika column error: Jalankan ALTER TABLE
   Jika conversion error: Check data type
   Jika permission error: Check user privilege
   ```

---

## Next Steps

1. ✅ Update save_cutting.php (DONE)
2. ⏳ Run migration SQL (if columns missing)
3. ⏳ Test Save functionality
4. ⏳ Check error message di console
5. ⏳ Fix based on error message

---

**Status**: Fixes Applied, Ready for Testing  
**Date**: December 24, 2025
