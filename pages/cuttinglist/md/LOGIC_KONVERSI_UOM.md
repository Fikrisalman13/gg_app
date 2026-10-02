# 🔧 DATABASE SAVE LOGIC - KONVERSI UOM

## Overview

Sistem penyimpanan data cutting dengan konversi UOM yang benar. Data disimpan di 2 kolom untuk setiap field:
- **Kolom asli** (tanpa suffix): Nilai dalam uom_cp
- **Kolom _conv** (dengan suffix _conv): Nilai dalam uom_counter

---

## Data Saved Structure

### 1. PANJANG (Panjang Awal & Akhir)

**Input**: Selalu dalam METER (absolut)
- `panjang_awal = 400` (selalu dalam Meter)
- `panjang_akhir = 350` (selalu dalam Meter)

**Disimpan ke 2 kolom:**
- `panjang_awal` = nilai asli (400)
- `panjang_awal_conv` = konversi ke uom_counter

**Logic Konversi:**
```
Jika type_counter = M:
  panjang_awal_conv = panjang_awal × 1 = 400

Jika type_counter = Y:
  panjang_awal_conv = panjang_awal / 0.9144 = 400 / 0.9144 = 437.44 Yard
```

**Table Entry:**
```
panjang_awal | panjang_awal_conv | uom_counter
400          | 400               | M (kalikan 1)
400          | 437.44            | Y (bagi 0.9144)
```

---

### 2. STANDAR/MIN/MAX (dengan Toleransi)

**Input**: Dalam uom_cp, SUDAH termasuk toleransi dari JavaScript
- `std = 30.30` (dalam uom_cp, sudah + toleransi)
- `min = 10.30` (dalam uom_cp, sudah + toleransi)
- `max = 40.30` (dalam uom_cp, sudah + toleransi)

**Disimpan ke 2 set kolom:**
1. `standart_potong` / `min_potong` / `max_potong` = nilai dalam uom_cp
2. `standart_potong_conv` / `min_potong_conv` / `max_potong_conv` = nilai dalam uom_counter

**Logic Konversi:**
```
Jika uom_cp = M dan type_counter = M:
  std_conv = std × 1 = 30.30

Jika uom_cp = M dan type_counter = Y:
  std_conv = std / 0.9144 = 30.30 / 0.9144 = 33.12 Y

Jika uom_cp = Y dan type_counter = M:
  std_conv = std × 0.9144 = 30.30 × 0.9144 = 27.71 M

Jika uom_cp = Y dan type_counter = Y:
  std_conv = std × 1 = 30.30
```

**Table Entry:**
```
uom_cp | type_counter | standart_potong | standart_potong_conv
M      | M            | 30.30           | 30.30
M      | Y            | 30.30           | 33.12
Y      | M            | 30.30           | 27.71
Y      | Y            | 30.30           | 30.30
```

---

### 3. TOLERANSI (dalam CM)

**Input**: Nilai dalam CM (30 jika user input 30 CM)
- `toleransi = 30` (INPUT ASLI, bukan hasil konversi)

**Disimpan ke 2 kolom:**
1. `toleransi` = nilai input asli dalam CM (30)
2. `toleransi_conv` = konversi dari CM ke uom_counter

**Logic Konversi:**
```
Dari CM ke satuan lain:
- 1 CM = 0.01 M
- 1 CM = (0.01 / 0.9144) Y = 0.010936 Y

Jika type_counter = M:
  toleransi_conv = toleransi × 0.01 = 30 × 0.01 = 0.30 M

Jika type_counter = Y:
  toleransi_conv = toleransi × (0.01 / 0.9144) = 30 × 0.010936 = 0.3281 Y
```

**Table Entry:**
```
toleransi_input | type_counter | toleransi | toleransi_conv
30 (CM)         | M            | 30        | 0.30
30 (CM)         | Y            | 30        | 0.3281
```

---

## Complete Example

### Input Data
```
User input di tambahcutting.php:
- CP No: D25
- UOM CP: Meter
- Type Counter: Yard
- Piece: 400
  - Panjang Awal: 400 M
  - Panjang Akhir: 350 M
  - Std: 30 (input asli)
  - Toleransi: 30 CM
  
JavaScript hitung:
  Tol konversi = 30 × 0.01 = 0.30 M
  Std dengan tol = 30 + 0.30 = 30.30 M
```

### Saved to Database
```
TABLE: cl_cutting_piece

panjang_awal     | 400         (Meter - nilai asli)
panjang_akhir    | 350         (Meter - nilai asli)
panjang_awal_conv| 437.44      (Yard = 400 / 0.9144)
panjang_akhir_conv| 382.64     (Yard = 350 / 0.9144)

standart_potong  | 30.30       (Meter - dalam uom_cp)
min_potong       | 10.30       (Meter)
max_potong       | 40.30       (Meter)

standart_potong_conv | 33.12   (Yard = 30.30 / 0.9144)
min_potong_conv      | 11.27   (Yard = 10.30 / 0.9144)
max_potong_conv      | 44.08   (Yard = 40.30 / 0.9144)

toleransi        | 30          (CM - nilai input asli)
toleransi_conv   | 0.3281      (Yard = 30 × 0.010936)

uom_cp           | M           (Meter)
uom_counter      | Y           (Yard)
```

---

## Process Cutting Logic

Ketika user klik "Processed", sistem harus menggunakan kolom yang tepat berdasarkan UOM:

### Case 1: uom_cp = M, type_counter = M
```
Gunakan kolom:
- panjang_akhir (bukan panjang_akhir_conv)
- standart_potong, min_potong, max_potong (bukan _conv)
- Mulai potong dari: panjang_akhir = 350
- Ukuran potong std = 30.30 M
```

### Case 2: uom_cp = M, type_counter = Y
```
Gunakan kolom:
- panjang_akhir_conv (konversi ke Yard)
- standart_potong_conv, min_potong_conv, max_potong_conv
- Mulai potong dari: panjang_akhir_conv = 382.64 Y
- Ukuran potong std = 33.12 Y
```

### Case 3: uom_cp = Y, type_counter = M
```
Gunakan kolom:
- panjang_akhir (asli dalam Meter)
- standart_potong, min_potong, max_potong
- Mulai potong dari: panjang_akhir (dalam M)
- Ukuran potong std = 30.30 M (setelah konversi Y ke M)
```

### Case 4: uom_cp = Y, type_counter = Y
```
Gunakan kolom:
- panjang_akhir_conv (konversi ke Yard)
- standart_potong_conv, min_potong_conv, max_potong_conv
- Mulai potong dari: panjang_akhir_conv = 382.64 Y
- Ukuran potong std = 33.12 Y
```

---

## Files to Update

1. ✅ **save_cutting.php** - DONE
   - Konversi panjang ke conv
   - Konversi std/min/max ke conv
   - Simpan toleransi asli dan conv

2. 🔄 **edit piece handler di cuttinglist.js** - TODO
   - Sama logic dengan save_cutting.php

3. 🔄 **process_cutting.php** - TODO
   - Gunakan kolom conv yang tepat berdasarkan UOM

---

**Status**: save_cutting.php DONE, waiting for JS edit & process_cutting.php  
**Date**: December 24, 2025
