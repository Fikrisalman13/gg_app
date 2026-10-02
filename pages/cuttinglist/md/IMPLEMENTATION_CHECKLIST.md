# CHECKLIST IMPLEMENTASI - UOM Conversion Cutting Piece

## ✅ Perubahan Database Schema
- [x] Rename kolom `satuan` → `uom_cp` (SQL: EXEC sp_rename)
- [x] Tambah kolom `standart_potong_conv`
- [x] Tambah kolom `min_potong_conv`
- [x] Tambah kolom `max_potong_conv`
- [x] Tambah kolom `uom_counter`
- [x] Tambah kolom `toleransi_conv`

## ✅ File save_cutting.php
- [x] Hapus logic konversi lama (yang di awal)
- [x] Implementasi logika konversi baru dengan toleransi
- [x] Hitung `std_dengan_tol = std + toleransi`
- [x] Hitung `conversion_factor` berdasarkan uom_counter dan uom_cp
- [x] Hitung kolom *_conv
- [x] Konversi panjang_awal dan panjang_akhir jika uom_counter = Y
- [x] INSERT ke 18 kolom termasuk 5 kolom baru
- [x] Parameter yang benar untuk sqlsrv_query

## ✅ File process_cutting.php
- [x] Query SELECT tambah kolom: standart_potong_conv, min_potong_conv, max_potong_conv, uom_counter, toleransi_conv
- [x] Gunakan nilai langsung dari kolom *_conv
- [x] Hapus logic konversi di proses
- [x] Tidak perlu tambah toleransi lagi (sudah di save)

## ✅ File index.php - Tab List
- [x] JOIN dengan cl_cutting_piece
- [x] SELECT kolom header dan piece
- [x] Tampilkan detail piece di setiap baris
- [x] Header: CP No, Type Counter, Piece Code, Panjang Awal, Panjang Akhir, Susut, Std, Min, Max, UOM CP
- [x] ORDER BY header.created_date DESC, piece.piece_no ASC
- [x] Handle baris tanpa piece (LEFT JOIN)

## ✅ File detail_cutting.php - Tab Piece
- [x] Tampilkan kolom lama: No, Piece, Panjang Awal, Panjang Akhir, Susut, Std, Min, Max, Tol, UOM CP
- [x] Tambah kolom baru: Std Conv, Min Conv, Max Conv, Tol Conv, UOM Counter
- [x] Tampilkan fallback 0.000 untuk kolom yang null
- [x] Wrapper div.table-responsive untuk horizontal scroll

## ✅ File edit_detail.php
- [x] Update query SELECT dengan kolom baru
- [x] Tampilkan kolom lama dan baru di tabel piece
- [x] Sama seperti detail_cutting.php
- [x] Wrapper div.table-responsive

## ✅ File save_edit_piece.php
- [x] Query header info untuk ambil uom_cp dan uom_counter
- [x] Hitung std_dengan_tol, min_dengan_tol, max_dengan_tol
- [x] Hitung conversion_factor
- [x] Hitung kolom *_conv
- [x] Konversi panjang_awal dan panjang_akhir jika uom_counter = Y
- [x] UPDATE 18 kolom termasuk 5 kolom conv
- [x] Parameter yang benar untuk sqlsrv_query

## ✅ File modal_piece.php
- [x] Tidak ada perubahan (UOM sudah readonly)
- [x] UOM diambil dari typeCounter di cuttinglist.js

## ✅ Dokumentasi
- [x] DATABASE_SCHEMA_PIECE_UPDATE.md
- [x] IMPLEMENTATION_UOM_CONVERSION.md
- [x] UOM_CONVERSION_SUMMARY.md
- [x] CHECKLIST ini

---

## 📝 Testing Instructions

### Database Migration
```sql
-- Jalankan sebelum testing
EXEC sp_rename 'cl_cutting_piece.satuan', 'uom_cp', 'COLUMN';

ALTER TABLE cl_cutting_piece
ADD standart_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    min_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    max_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    uom_counter CHAR(1) DEFAULT 'M',
    toleransi_conv DECIMAL(10,3) DEFAULT 0.000;
```

### Test Case 1: Meter → Meter (Tidak Ada Konversi)
1. Buka tambahcutting.php
2. Pilih UOM CP = Meter, Type Counter = Meter
3. Tambah piece:
   - Std: 10.5, Min: 8.0, Max: 12.0, Tol: 0.5
4. Save
5. Lihat detail_cutting.php
   - Std harus 11.0 (10.5 + 0.5)
   - Std Conv harus 11.0 (tidak berubah)
6. Proses, hasil harus benar

### Test Case 2: Meter → Yard (Konversi)
1. Buka tambahcutting.php
2. Pilih UOM CP = Yard, Type Counter = Meter
3. Tambah piece:
   - Std: 11.5, Min: 9.0, Max: 13.0, Tol: 0.5
4. Save
5. Lihat detail_cutting.php
   - Std harus 12.0 (11.5 + 0.5)
   - Std Conv harus ≈ 10.97 (12.0 ÷ 0.9144)
6. Proses, hasil harus benar dengan konversi

### Test Case 3: Edit Piece
1. Buka edit_detail.php dari piece yang sudah dibuat
2. Klik Edit di Tab Piece
3. Ubah nilai Std: 10.5 → 12.5
4. Save
5. Lihat kembali, Std harus 13.0 (12.5 + 0.5), Std Conv sesuai
6. Proses ulang, hasil harus benar

### Test Case 4: Tab List Menampilkan Detail
1. Buka index.php
2. Lihat tab List
3. Seharusnya menampilkan setiap piece dengan detail
4. Klik Detail, pastikan bisa membuka detailnya

---

## 🔍 Verification Query

```sql
-- Lihat struktur tabel
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'cl_cutting_piece'
ORDER BY ORDINAL_POSITION;

-- Lihat data cutting piece yang sudah disimpan
SELECT TOP 10
    id_piece, piece_code,
    standart_potong, standart_potong_conv, uom_counter,
    min_potong, min_potong_conv,
    max_potong, max_potong_conv,
    toleransi, toleransi_conv,
    uom_cp
FROM cl_cutting_piece
ORDER BY id_piece DESC;

-- Verifikasi konversi (Meter ke Yard)
-- Std Conv harus = Std / 0.9144
-- Contoh: Std=12.0, Std_Conv=10.97 (12.0/0.9144=13.10, tapi jika Yard ke Meter: 12.0*0.9144)
SELECT
    id_piece,
    standart_potong,
    standart_potong_conv,
    CAST(standart_potong / 0.9144 AS DECIMAL(10,3)) as calc_std_conv_m2y,
    CAST(standart_potong * 0.9144 AS DECIMAL(10,3)) as calc_std_conv_y2m,
    uom_counter,
    uom_cp
FROM cl_cutting_piece
WHERE uom_counter IS NOT NULL AND uom_counter != 'M'
ORDER BY id_piece DESC;
```

---

## 🎯 Final Checklist Before Go-Live

- [ ] SQL migration dijalankan
- [ ] Test Case 1 passed (Meter→Meter)
- [ ] Test Case 2 passed (Meter→Yard)
- [ ] Test Case 3 passed (Edit piece)
- [ ] Test Case 4 passed (List view)
- [ ] Verification query menunjukkan data benar
- [ ] Process cutting menggunakan nilai *_conv
- [ ] Hasil cutting benar dengan konversi
- [ ] Tidak ada error di browser console
- [ ] Tidak ada error di PHP error log
- [ ] Backward compatibility tested (data lama tetap berjalan)
- [ ] Performance OK (tidak ada slowdown)

---

## 📞 Troubleshooting

### Error: Unknown column 'standart_potong_conv'
**Solusi**: SQL migration belum dijalankan. Jalankan script ALTER TABLE.

### Std Conv = 0.000
**Solusi**: 
- Kolom nilai null/belum diisi. Re-edit piece untuk trigger perhitungan.
- Atau INSERT manual dengan UPDATE:
```sql
UPDATE cl_cutting_piece
SET standart_potong_conv = standart_potong * (CASE WHEN uom_counter='Y' AND uom_cp='M' THEN 0.9144 ELSE 1.0 END)
WHERE standart_potong_conv = 0.000;
```

### Tolerance tidak ditambahkan
**Solusi**: Pastikan di save_cutting.php dan save_edit_piece.php sudah ada logika:
```php
$std_dengan_tol = $standart_potong + $toleransi;
```

### Process cutting hasil salah
**Solusi**: Pastikan process_cutting.php menggunakan kolom *_conv, bukan kolom original:
```php
$standart_potong = floatval($piece['standart_potong_conv']);
```

---

## 📚 Related Files

- `DATABASE_SCHEMA_PIECE_UPDATE.md` - Detail schema dan logika konversi
- `IMPLEMENTATION_UOM_CONVERSION.md` - Dokumentasi implementasi lengkap
- `UOM_CONVERSION_SUMMARY.md` - Ringkasan singkat
- `CHECKLIST ini` - Testing dan verification

---

Status: **READY FOR TESTING**
Last Updated: December 23, 2025
