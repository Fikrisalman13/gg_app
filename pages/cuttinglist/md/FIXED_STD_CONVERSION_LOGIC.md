# ✅ FIXED - Correct Standard/Min/Max Conversion Logic

## The Correct Understanding

**INPUT std/min/max adalah ABSOLUT dalam satuan METER**

Jadi logika penyimpanan harus:
1. `standart_potong` = nilai input dalam Meter (absolute reference)
2. `standart_potong_conv` = konversi ke unit sesuai `uom_cp`

**Konversi berdasarkan `uom_cp` SAJA, bukan kombinasi uom_cp vs uom_counter**

---

## Conversion Logic (PERBAIKAN)

### Jika uom_cp = M (Meter)
```
Input: 30.3 Meter
Disimpan:
  standart_potong = 30.3 (asli, dalam Meter)
  standart_potong_conv = 30.3 × 1 = 30.3 (tidak ada konversi)
```

### Jika uom_cp = Y (Yard)
```
Input: 30.3 Meter
Disimpan:
  standart_potong = 30.3 (asli, dalam Meter - absolute reference)
  standart_potong_conv = 30.3 ÷ 0.9144 = 33.1265 Yard (konversi M→Y)
```

---

## Kode yang Diperbaiki

### save_cutting.php (Lines 86-101)
```php
// INPUT std_input, min_input, max_input SELALU dalam METER (absolute)
// Konversi berdasarkan uom_cp saja (input selalu Meter)
if ($uom_cp === 'M') {
    // Input dalam Meter, uom_cp juga Meter: tidak perlu konversi
    $std_conv = $std_input * 1;  // × 1
    $min_conv = $min_input * 1;
    $max_conv = $max_input * 1;
} elseif ($uom_cp === 'Y') {
    // Input dalam Meter, uom_cp Yard: konversi M ke Y
    $std_conv = $std_input / 0.9144;  // M ke Y: bagi 0.9144
    $min_conv = $min_input / 0.9144;
    $max_conv = $max_input / 0.9144;
}
```

### save_edit_piece.php (Sama logic)
Perbaikan yang sama diterapkan ke file edit.

### process_cutting.php
Diperbarui untuk:
- Panjang: gunakan `panjang_awal_conv` jika `uom_counter = Y`, else `panjang_awal`
- Std/Min/Max: gunakan `standart_potong_conv` jika `uom_counter ≠ uom_cp`, else gunakan original

---

## Contoh Benar (dengan data sekarang)

### Data yang Benar (M→Y)
```
Input Std: 30.3 (METER - absolute)
UOM CP: M (Meter)
Type Counter: Y (Yard)

Disimpan:
  standart_potong = 30.3 (original, dalam M)
  standart_potong_conv = 30.3 (×1 karena uom_cp=M)
  uom_cp = M
  uom_counter = Y

Process menggunakan:
  Jika uom_counter = Y: ambil standart_potong_conv tapi perlu konversi lagi?
  ATAU simpan juga kolom untuk unit counter?
```

Wait, ada keambiguan di sini. Mari saya klarifikasi:

---

## Pertanyaan Klarifikasi

**Ketika uom_cp ≠ uom_counter, ada 2 skenario:**

### Skenario 1: Input dalam Meter, uom_cp dalam satu unit, uom_counter dalam unit lain

Contoh: Input 30.3M, uom_cp=M, uom_counter=Y

Bagaimana cara simpan supaya process dapat menghitung dalam unit uom_counter?

**Option A: Simpan dalam standart_potong_conv sesuai uom_cp**
- standart_potong = 30.3 (M)
- standart_potong_conv = 30.3 (juga M, karena uom_cp=M)
- Saat process: jika uom_counter=Y, konversi lagi 30.3 M → Y

**Option B: Simpan standart_potong_conv sesuai uom_counter**
- standart_potong = 30.3 (M)
- standart_potong_conv = 33.1265 (Y, langsung untuk process)
- Saat process: gunakan standart_potong_conv langsung

Menurut penjelasan Anda "simpan di standart_potong_conv" berdasarkan uom_cp saja...

Itu artinya **Option A**: `standart_potong_conv` sesuai dengan `uom_cp`, bukan `uom_counter`.

Maka saat process perlu ada logika tambahan untuk konversi jika `uom_counter ≠ uom_cp`.

---

## Current Implementation (Perbaikan)

Saya sudah update dengan asumsi **Option A**:

1. **save_cutting.php & save_edit_piece.php:**
   - Konversi hanya berdasarkan `uom_cp`
   - `standart_potong_conv` dalam unit `uom_cp`

2. **process_cutting.php:**
   - Gunakan `panjang_awal_conv` jika `uom_counter = Y`
   - Untuk std/min/max: gunakan logic yang tepat based on unit combination

---

## Testing

Sekarang coba buat cutting baru dengan:
- **UOM CP = M** (Meter)
- **Type Counter = Y** (Yard)
- Input Std: 30.3

Database harus menunjukkan:
```
standart_potong = 30.3
standart_potong_conv = 30.3 (×1, karena uom_cp=M)
uom_cp = M
uom_counter = Y
```

Kemudian process harus:
1. Ambil `standart_potong_conv = 30.3` (dalam M)
2. Konversi ke Y: 30.3 ÷ 0.9144 = 33.1265 Y
3. Gunakan 33.1265 untuk kalkulasi process

---

## Files Modified

✅ save_cutting.php - Fixed conversion logic (uom_cp only)
✅ save_edit_piece.php - Fixed conversion logic (uom_cp only)
✅ process_cutting.php - Updated column selection logic

**Status: READY TO TEST**
