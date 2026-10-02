# TROUBLESHOOTING - Process Error: Object

## 🔴 ERROR TERJADI
**Error**: "Process error: Object" saat melakukan proses cutting

---

## 🔍 PENYEBAB

Error ini terjadi karena **SQL Migration script belum dijalankan**. 

Sistem mencoba mengakses kolom-kolom baru di database:
- `standart_potong_conv`
- `min_potong_conv`
- `max_potong_conv`
- `toleransi_conv`
- `uom_counter`

Tapi kolom-kolom ini belum ada di tabel `cl_cutting_piece`.

---

## ✅ SOLUSI

### Step 1: Jalankan SQL Migration Script

**PENTING**: Jalankan di SQL Server Management Studio:

```sql
-- Rename kolom satuan menjadi uom_cp
EXEC sp_rename 'cl_cutting_piece.satuan', 'uom_cp', 'COLUMN';

-- Tambahkan 5 kolom baru
ALTER TABLE cl_cutting_piece
ADD standart_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    min_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    max_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    uom_counter CHAR(1) DEFAULT 'M',
    toleransi_conv DECIMAL(10,3) DEFAULT 0.000;
```

### Step 2: Verifikasi Kolom Sudah Ada

```sql
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'cl_cutting_piece'
ORDER BY ORDINAL_POSITION;
```

Pastikan ada kolom:
- ✅ uom_cp (renamed dari satuan)
- ✅ standart_potong_conv
- ✅ min_potong_conv
- ✅ max_potong_conv
- ✅ uom_counter
- ✅ toleransi_conv

### Step 3: Coba Proses Lagi

Refresh halaman dan coba process cutting lagi.

---

## 🛡️ BACKWARD COMPATIBILITY (Sudah Ada!)

Aplikasi sudah diupdate dengan fallback logic, jadi:
- ✅ Aplikasi tidak akan crash meski kolom belum ada
- ✅ Data lama tetap bisa diproses
- ✅ Fallback menggunakan: `original_value + tolerance`

**Tapi untuk hasil yang benar dengan konversi**, kolom-kolom baru HARUS ada.

---

## 📋 CHECKLIST SETELAH FIX

- [ ] SQL migration script dijalankan
- [ ] Verifikasi kolom ada di database
- [ ] Refresh browser (Ctrl+F5)
- [ ] Coba buat piece baru dengan konversi UOM
- [ ] Coba process cutting
- [ ] Lihat detail hasil cutting, pastikan benar

---

## 🔧 DEBUG INFO

Jika masih error setelah migration, buka browser console (F12) dan lihat:

```
console.log('Response text:', xhr.responseText);
```

Laporan error detail akan muncul di console untuk troubleshooting lebih lanjut.

---

## 📞 JIKA MASIH ERROR

1. Pastikan **SQL migration sudah berhasil** (no errors)
2. Cek **error log di database** untuk detail
3. Cek **browser console (F12)** untuk error detail
4. Pastikan **uom_cp sudah di-rename** dari satuan
5. Pastikan **5 kolom conv sudah ditambahkan**

---

**Status**: Fixed dengan backward compatibility  
**Date**: December 23, 2025
