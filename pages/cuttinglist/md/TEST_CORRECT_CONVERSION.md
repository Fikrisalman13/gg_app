# 🧪 Test Konversi yang Benar

## Instruksi Testing

### Langkah 1: Hapus Data Lama yang Salah
```sql
DELETE FROM cl_cutting_piece WHERE id_header = (SELECT id_header FROM cl_cutting_header ORDER BY id_header DESC LIMIT 1);
DELETE FROM cl_cutting_header WHERE id_header = (SELECT id_header FROM cl_cutting_header ORDER BY id_header DESC LIMIT 1);
```

### Langkah 2: Buat Cutting Baru Dengan Benar

Buka **tambahcutting.php**:
1. **CP No:** D25
2. **UOM CP:** **Pilih "M" (Meter)** ← PENTING!
3. **Type Counter:** **Pilih "Y" (Yard)** ← PENTING!
4. **Mesin:** Pilih yang ada

Klik **Tambah Piece**:
- Piece: 400
- Panjang Awal: 400
- Panjang Akhir: 350
- Lebar: 1
- **Std: 30** ← Input dalam Meter (absolut)
- Max: 40
- Min: 10
- Toleransi: 30 (CM)

Klik **Save**

### Langkah 3: Verifikasi Database

Jalankan query:
```sql
SELECT TOP 1
    id_piece,
    piece_code,
    standart_potong,
    standart_potong_conv,
    uom_cp,
    uom_counter
FROM cl_cutting_piece
ORDER BY id_piece DESC
```

**Hasil yang Diharapkan:**
```
id_piece        | 15 (atau nomor berikutnya)
piece_code      | 400
standart_potong | 30.3000  (dalam Meter, absolute)
standart_potong_conv | 30.3000  (×1, karena uom_cp=M)
uom_cp          | M
uom_counter     | Y
```

**Jika berbeda, ada yang masih salah!**

---

## Expected Result Explanation

### Kenapa standart_potong_conv = 30.3?

```
Input: 30.3 (SELALU dalam Meter - absolute)
UOM CP: M (Meter)

Konversi berdasarkan uom_cp saja:
  Jika uom_cp = M: standart_potong_conv = 30.3 × 1 = 30.3

Jadi TIDAK dikonversi dulu! Konversi ke Yard terjadi nanti saat Process.
```

### Process akan melakukan:

```
1. Ambil standart_potong_conv = 30.3 (dalam M)
2. Lihat uom_counter = Y
3. Konversi: 30.3 ÷ 0.9144 = 33.1265 Y
4. Gunakan 33.1265 untuk kalkulasi cutting
```

---

## Troubleshooting

Jika hasil tidak sesuai, berarti ada masalah di kode. Check:

1. **standart_potong_conv lebih besar dari 30.3?**
   - Berarti kode masih melakukan konversi saat save
   - Fix: pastikan uom_cp digunakan, bukan uom_counter

2. **standart_potong_conv lebih kecil dari 30.3?**
   - Berarti konversi salah arah
   - Fix: check division vs multiplication

3. **Data tidak tersimpan sama sekali?**
   - Check error message di save_cutting.php
   - Lihat server error log

---

## Summary

**Logika yang benar:**
- Input Std SELALU dalam Meter (absolute reference)
- Konversi HANYA berdasarkan `uom_cp` saat save
- `standart_potong_conv` dalam unit `uom_cp`
- Process melakukan konversi lagi jika `uom_counter ≠ uom_cp`

**Expected untuk test:**
- UOM CP = M, Type Counter = Y
- Input 30.3 M
- Simpan: standart_potong_conv = 30.3 (×1)
- Process akan konversi ke 33.1265 Y

**Siap test? Ikuti instruksi di atas!** ✅
