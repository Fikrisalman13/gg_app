# 📸 CONTOH INPUT/OUTPUT - Toleransi & Edit Features

## Scenario 1: Add Piece dengan Toleransi (Type Counter = M)

### Input User di Modal
```
Panjang Awal:   400
Panjang Akhir:  400
Lebar:          150
Std:            30
Max:            40
Min:            10
Toleransi:      30 (CM)
UOM:            M
Type Counter:   M (dari header)
```

### Proses Konversi
```
Type Counter = M:
├─ Toleransi(CM) = 30
├─ Konversi: 30 × 0.01 = 0.30 M
├─ STD + Tol = 30 + 0.30 = 30.30 M ✓
├─ MAX + Tol = 40 + 0.30 = 40.30 M ✓
└─ MIN + Tol = 10 + 0.30 = 10.30 M ✓
```

### Output di Tab Piece
```
| No | Piece | P.Awal | P.Akhir | Lebar | Std   | Max   | Min   | Tol | UOM | Action |
|----|-------|--------|---------|-------|-------|-------|-------|-----|-----|--------|
| 1  | 400   | 400    | 400     | 150   |30.30  |40.30  |10.30  | 30  | M   | E  D   |
```

✓ **Output Benar!** STD sudah 30.30 (bukan 30)

---

## Scenario 2: Add Piece dengan Toleransi (Type Counter = Y)

### Input User di Modal
```
Panjang Awal:   400
Panjang Akhir:  400
Lebar:          150
Std:            30
Max:            40
Min:            10
Toleransi:      30 (CM)
UOM:            Y
Type Counter:   Y (dari header)
```

### Proses Konversi
```
Type Counter = Y:
├─ Toleransi(CM) = 30
├─ Konversi: 30 × (0.01 / 0.9144) = 0.3281 Y
├─ STD + Tol = 30 + 0.3281 = 30.3281 Y ✓
├─ MAX + Tol = 40 + 0.3281 = 40.3281 Y ✓
└─ MIN + Tol = 10 + 0.3281 = 10.3281 Y ✓
```

### Output di Tab Piece
```
| No | Piece | P.Awal | P.Akhir | Lebar | Std     | Max     | Min     | Tol | UOM | Action |
|----|-------|--------|---------|-------|---------|---------|---------|-----|-----|--------|
| 1  | 400   | 400    | 400     | 150   |30.328   |40.328   |10.328   | 30  | Y   | E  D   |
```

✓ **Output Benar!** STD sudah 30.3281 (bukan 30)

---

## Scenario 3: Edit Piece (Change Toleransi)

### Initial State
```
Piece 1: STD=30.30, Max=40.30, Min=10.30, Tol=30
```

### User Action
1. Click Edit button pada Piece 1
2. Modal opens dengan:
   ```
   Std:        30        (dikonversi balik: 30.30 - 0.30)
   Max:        40        (dikonversi balik: 40.30 - 0.30)
   Min:        10        (dikonversi balik: 10.30 - 0.30)
   Toleransi:  30
   ```
3. User ubah: Toleransi 30 → 20
4. Click Apply

### Proses Kalkulasi Ulang
```
Toleransi lama: 30 → 0.30 M
Toleransi baru: 20 → 0.20 M

STD + Tol baru = 30 + 0.20 = 30.20 M ✓
MAX + Tol baru = 40 + 0.20 = 40.20 M ✓
MIN + Tol baru = 10 + 0.20 = 10.20 M ✓
```

### Output di Tab Piece (Updated)
```
| No | Piece | P.Awal | P.Akhir | Lebar | Std   | Max   | Min   | Tol | UOM | Action |
|----|-------|--------|---------|-------|-------|-------|-------|-----|-----|--------|
| 1  | 400   | 400    | 400     | 150   |30.20  |40.20  |10.20  | 20  | M   | E  D   |
                ↑                        ↑       ↑       ↑       ↑
                └────────────────────────┴───────┴───────┴───────┘
                        Updated with new tolerance
```

✓ **Edit Bekerja!** Row ter-update, tidak duplikat

---

## Scenario 4: Add Cacat & Edit

### Initial State
```
Piece 1: 400-400M

Cacat Tab: Empty
```

### Add Cacat
1. Click "Tambah Piece"
2. Fill: Std=30, Max=40, Min=10, Tol=30
3. Click "+ Tambah Cacat"
4. Fill cacat:
   ```
   Dari:   39
   Sampai: 49
   Kode:   KC001 (Kelim)
   ```
5. Click Apply

### Output di Tab Cacat
```
| Piece | No | Kode  | Nama              | Status | Dari | Sampai | Panjang | Action |
|-------|----|----- -|-------------------|--------|------|--------|---------|--------|
| 400   | 1  | KC001 | Cacat Kelim       | CACAT  | 39   | 49     | 10      | E  D   |
```

### Edit Cacat
1. Click Edit icon di Cacat Tab (baris Piece 400)
2. Modal opens dengan:
   - Piece 1 data loaded
   - Cacat 39-49 loaded
3. User ubah: Dari 39 → 40
4. Click Apply

### Output di Tab Cacat (Updated)
```
| Piece | No | Kode  | Nama              | Status | Dari | Sampai | Panjang | Action |
|-------|----|----- -|-------------------|--------|------|--------|---------|--------|
| 400   | 1  | KC001 | Cacat Kelim       | CACAT  | 40   | 49     | 9       | E  D   |
                                                    ↑              ↑
                                               Updated!      Auto-calculated
```

✓ **Cacat Edit Bekerja!** Dari berubah 39→40, Panjang auto-calc 10→9

---

## Scenario 5: Multiple Pieces dengan Cacat Berbeda

### Initial State
```
Piece 1: Std=30.30, Cacat di 39-49
Piece 2: Std=30.30, Cacat di 59-69
```

### Edit Piece 1
1. Click Edit pada Piece 1
2. Modal opens dengan:
   ```
   Piece Data: (Piece 1 data)
   Cacat: (Hanya cacat Piece 1: 39-49)  ← Benar!
   ```
3. Change: Toleransi 30 → 25
4. Click Apply

### Edit Piece 2
1. Click Edit pada Piece 2
2. Modal opens dengan:
   ```
   Piece Data: (Piece 2 data)
   Cacat: (Hanya cacat Piece 2: 59-69)  ← Benar!
   ```

✓ **Cacat Isolation Bekerja!** Setiap piece hanya load cacat-nya sendiri

---

## 🎯 Complete Flow: From Add to Process

### Step 1: Add Header
```
Input:
├─ CP No:        D25
├─ UOM CP:       M
├─ Type Counter: M
└─ Mesin:        Mesin 1
```

### Step 2: Add Piece 1
```
Input:
├─ Panjang Awal/Akhir: 400
├─ Std: 30, Max: 40, Min: 10
├─ Toleransi: 30 CM
└─ Cacat: 39-49

Output Tab Piece:
└─ STD=30.30, MAX=40.30, MIN=10.30 ✓

Output Tab Cacat:
└─ Piece=400, Dari=39, Sampai=49 ✓
```

### Step 3: Add Piece 2
```
Input:
├─ Panjang Awal/Akhir: 300
├─ Std: 25, Max: 35, Min: 8
├─ Toleransi: 25 CM
└─ Cacat: 59-69

Output Tab Piece:
└─ STD=25.25, MAX=35.25, MIN=8.25 ✓

Output Tab Cacat:
└─ Piece=300, Dari=59, Sampai=69 ✓
```

### Step 4: Save to Database
```
POST to save_cutting.php:
├─ Header: CP=D25, Type=M, UOM=M
├─ Piece 1:
│  ├─ panjang_awal: 400
│  ├─ panjang_akhir: 400
│  ├─ std: 30.30
│  ├─ max: 40.30
│  ├─ min: 10.30
│  ├─ toleransi: 30
│  └─ cacat: [{dari:39, sampai:49, kode:KC001}]
├─ Piece 2:
│  ├─ panjang_awal: 300
│  ├─ panjang_akhir: 300
│  ├─ std: 25.25
│  ├─ max: 35.25
│  ├─ min: 8.25
│  ├─ toleransi: 25
│  └─ cacat: [{dari:59, sampai:69, kode:KC001}]
└─ ... all data

DB Tables Populated:
├─ cl_cutting_header ✓
├─ cl_cutting_piece ✓
└─ cl_cutting_cacat ✓
```

---

## 🔍 Validation Results

### Test Case 1: M→M Tolerance Conversion
```
Input:    Tol=30 CM, Type=M
Expected: STD=30.30
Result:   30.30 ✓ PASS
```

### Test Case 2: Y→Y Tolerance Conversion
```
Input:    Tol=30 CM, Type=Y
Expected: STD≈30.3281
Result:   30.3281 ✓ PASS
```

### Test Case 3: Edit Piece
```
Action:   Edit Piece 1
Expected: Update row (tidak duplikat)
Result:   Row updated ✓ PASS
```

### Test Case 4: Edit Cacat
```
Action:   Edit Cacat dari Piece 1
Expected: Only Piece 1 cacat loaded
Result:   Only Piece 1 cacat loaded ✓ PASS
```

### Test Case 5: Calculate Panjang Cacat
```
Input:    Dari=39, Sampai=49
Expected: Panjang=10
Result:   10 ✓ AUTO-CALCULATED
```

---

**All features tested and working correctly! ✅**

---

**Last Updated**: December 24, 2025  
**Status**: Ready for Production
