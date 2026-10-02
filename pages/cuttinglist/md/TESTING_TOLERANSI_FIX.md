# ✅ TESTING GUIDE - Toleransi Conversion Fix

## Pre-Test Checklist

1. **Clear Browser Cache**
   - Press: `Ctrl + Shift + Delete`
   - Or use Incognito Mode (Ctrl + Shift + N)

2. **Reload Page**
   - Press: `Ctrl + F5` (Hard Refresh)
   - Pastikan JavaScript terbaru ter-load

3. **Buka Console**
   - Press: `F12` → Console tab
   - Pastikan tidak ada error

---

## Test Case 1: Type Counter = METER

### Step 1: Input Form
```
CP No:            TEST01
UOM CP:           Meter
No Mesin Inspect: Mesin 2
Type Counter:     Meter ← PILIH INI
```

### Step 2: Add Piece
Klik **"+ Tambah Piece"**

Input di Modal:
```
Piece:       400
Panjang Awal:    400
Panjang Akhir:   400
Lebar:           150
Std:             30      ← INPUT
Max:             40      ← INPUT
Min:             10      ← INPUT
Toleransi:       30      ← INPUT (dalam CM!)
```

### Step 3: Expected Result
```
Tab Piece - Setelah Click "Apply":

| Std   | Max   | Min   | Tol |
|-------|-------|-------|-----|
| 30.30 | 40.30 | 10.30 | 30  |  ← BENAR!
```

**Calculation:**
- Toleransi 30 CM × 0.01 = 0.30 Meter
- Std: 30 + 0.30 = 30.30 ✅
- Max: 40 + 0.30 = 40.30 ✅
- Min: 10 + 0.30 = 10.30 ✅

---

## Test Case 2: Type Counter = YARD

### Step 1: Input Form
```
CP No:            TEST02
UOM CP:           Yard
No Mesin Inspect: Mesin 2
Type Counter:     Yard ← PILIH INI
```

### Step 2: Add Piece
Klik **"+ Tambah Piece"**

Input di Modal:
```
Piece:       400
Panjang Awal:    400
Panjang Akhir:   400
Lebar:           150
Std:             30      ← INPUT
Max:             40      ← INPUT
Min:             10      ← INPUT
Toleransi:       30      ← INPUT (dalam CM!)
```

### Step 3: Expected Result
```
Tab Piece - Setelah Click "Apply":

| Std     | Max     | Min     | Tol |
|---------|---------|---------|-----|
| 30.3281 | 40.3281 | 10.3281 | 30  |  ← BENAR!
```

**Calculation:**
- Toleransi 30 CM × (0.01 / 0.9144) = 0.3281 Yard
- Std: 30 + 0.3281 = 30.3281 ✅
- Max: 40 + 0.3281 = 40.3281 ✅
- Min: 10 + 0.3281 = 10.3281 ✅

---

## Test Case 3: No Toleransi

### Input di Modal
```
Piece:       400
Panjang Awal:    400
Panjang Akhir:   400
Lebar:           150
Std:             30
Max:             40
Min:             10
Toleransi:       0       ← KOSONG atau 0
Type Counter:    Meter
```

### Expected Result
```
| Std | Max | Min | Tol |
|-----|-----|-----|-----|
| 30  | 40  | 10  | 0   |  ← SAMA dengan input
```

---

## Test Case 4: Edit Piece & Change Toleransi

### Initial State
```
Piece: 400, Std=30.30, Max=40.30, Min=10.30, Tol=30, Type=Meter
```

### Step 1: Click Edit Button
Modal opens dengan:
```
Std:        30          ← Converted back (30.30 - 0.30)
Max:        40          ← Converted back (40.30 - 0.30)
Min:        10          ← Converted back (10.30 - 0.30)
Toleransi:  30          ← Original value
```

### Step 2: Change Toleransi
```
Toleransi: 30 → 20
```

### Step 3: Click Apply
Tab Piece updated:
```
| Std   | Max   | Min   | Tol |
|-------|-------|-------|-----|
| 30.20 | 40.20 | 10.20 | 20  |  ← BENAR! Tolerance diupdate
```

**Calculation:**
- New Toleransi: 20 CM × 0.01 = 0.20 Meter
- Std: 30 + 0.20 = 30.20 ✅

---

## Test Case 5: Multiple Pieces dengan Toleransi Berbeda

### Input 1
```
Piece: 400, Std: 30, Max: 40, Min: 10, Tol: 30, Type: Meter
```
Expected: Std=30.30, Max=40.30, Min=10.30

### Input 2
```
Piece: 300, Std: 25, Max: 35, Min: 8, Tol: 15, Type: Meter
```
Expected: Std=25.15, Max=35.15, Min=8.15

### Result di Tab Piece
```
| Piece | Std   | Max   | Min   | Tol |
|-------|-------|-------|-------|-----|
| 400   | 30.30 | 40.30 | 10.30 | 30  | ✅
| 300   | 25.15 | 35.15 | 8.15  | 15  | ✅
```

---

## Test Case 6: Save & Verify Database

### Setelah semua piece added:
Klik **"Save"** button

### Verify:
1. Tidak ada error di console
2. Data berhasil saved (check database atau success message)
3. Std/Max/Min values dengan toleransi terinput dengan benar

---

## Bug Verification (BEFORE vs AFTER)

### ❌ BEFORE FIX:
```
Input:  Std=30, Tol=30, Type=Meter
Bug:    Std = 30 + 30 = 60  ← WRONG!
Reason: Toleransi not converted (30 CM ditambah langsung tanpa konversi)
```

### ✅ AFTER FIX:
```
Input:  Std=30, Tol=30, Type=Meter
Fix:    Std = 30 + (30 × 0.01) = 30.30  ← CORRECT!
Reason: Toleransi converted first (30 CM → 0.30 M, then added)
```

---

## Troubleshooting

### Issue: Masih menunjukkan nilai lama (Std=60)
**Solution:**
1. Clear cache: `Ctrl + Shift + Delete`
2. Hard refresh: `Ctrl + F5`
3. Atau gunakan Incognito Mode

### Issue: Tidak ada perubahan setelah save
**Solution:**
1. Check browser console untuk error
2. Verifikasi file `js/cuttinglist.js` ter-load dengan benar
3. Refresh page dan coba lagi

### Issue: Edit piece modal shows wrong values
**Solution:**
1. Pastikan Type Counter dipilih dengan benar sebelum edit
2. Check console untuk nilai toleransi yang di-convert

---

## Pass/Fail Criteria

✅ **PASS** jika:
- Std/Max/Min correctly calculated dengan toleransi
- Meter: 30 + 0.30 = 30.30
- Yard: 30 + 0.3281 = 30.3281
- Edit piece menampilkan nilai original (reversed)
- Save menyimpan data dengan benar

❌ **FAIL** jika:
- Nilai tetap 60 (tidak dikonversi)
- Edit piece menampilkan nilai salah
- Error di console
- Data tidak tersimpan

---

**Test Date**: December 24, 2025  
**Status**: Ready for Testing
