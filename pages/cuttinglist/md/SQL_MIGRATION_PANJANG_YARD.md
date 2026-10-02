# SQL Migration - Tambah Kolom Panjang Yard

## 📋 DESKRIPSI

Tambahkan 2 kolom baru di tabel `cl_cutting_piece` untuk menyimpan panjang awal dan panjang akhir dalam satuan Yard (jika type_counter = Yard).

---

## ✅ SQL MIGRATION SCRIPT

Jalankan di SQL Server Management Studio:

```sql
-- Tambahkan 2 kolom baru untuk panjang dalam satuan Yard
ALTER TABLE cl_cutting_piece
ADD panjang_awal_conv DECIMAL(10,3) DEFAULT 0.000,
    panjang_akhir_conv DECIMAL(10,3) DEFAULT 0.000;
```

---

## ✅ VERIFIKASI KOLOM SUDAH ADA

```sql
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'cl_cutting_piece'
ORDER BY ORDINAL_POSITION;
```

Pastikan ada kolom:
- ✅ panjang_awal_conv (DECIMAL(10,3))
- ✅ panjang_akhir_conv (DECIMAL(10,3))

---

## 📝 PENJELASAN LOGIC

### Data Disimpan Sebagai Berikut:

1. **panjang_awal, panjang_akhir** = Nilai input asli (tidak dikonversi)
   - Disimpan dalam unit yang diinput user

2. **panjang_awal_conv, panjang_akhir_conv** = Konversi ke Yard (hanya jika type_counter = Yard)
   - Jika type_counter = M: kedua kolom ini = 0
   - Jika type_counter = Y: konversi dari unit input ke Yard

### Contoh:

**Scenario: Panjang input 400 M, type_counter = Y**
- panjang_awal = 400 (M)
- panjang_awal_conv = 400 × 0.9144 = 365.76 (Y)

**Scenario: Panjang input 400 Y, type_counter = M**
- panjang_awal = 400 (Y)
- panjang_awal_conv = 0 (tidak ada konversi)

---

## 🔧 KETIKA DATA SUDAH ADA

Kolom baru akan otomatis terisi dengan 0.000 (default value).

Data lama akan ditampilkan dengan:
- `panjang_awal_conv = 0` jika belum diupdate
- Jika user edit piece, nilai akan dihitung ulang sesuai logic baru

---

**Status**: Ready for migration  
**Date**: December 23, 2025
