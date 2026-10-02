# ✅ TESTING CHECKLIST - PHASE 6

**Implementation Date:** December 24, 2025  
**Status:** Ready for User Testing  
**Prepared By:** Implementation Team

---

## Pre-Testing Verification ✅

- [x] All PHP files updated (no syntax errors)
- [x] Conversion logic implemented
- [x] Column selection logic implemented
- [x] Error handling improved
- [x] Documentation created
- [x] Database schema verified

---

## Test 1: M→M (Meter to Meter - No Conversion)

**Setup:**
- [ ] Create new cutting
- [ ] Set UOM CP: **Meter**
- [ ] Set Type Counter: **Meter**
- [ ] Add piece with:
  - Panjang Awal: 400 M
  - Panjang Akhir: 350 M
  - Std: 30 M
  - Toleransi: 30 CM

**Expected Database Values:**
```
panjang_awal = 400
panjang_awal_conv = 400 (no conversion)
standart_potong = 30.30
standart_potong_conv = 30.30 (no conversion)
toleransi = 30
toleransi_conv = 0.30 (30 × 0.01)
uom_cp = M
uom_counter = M
```

**Verification:**
- [ ] Values match expected (check database)
- [ ] No conversion performed (conv = original)
- [ ] Process uses panjang_awal column

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 2: M→Y (Meter to Yard - Convert)

**Setup:**
- [ ] Create new cutting
- [ ] Set UOM CP: **Meter**
- [ ] Set Type Counter: **Yard**
- [ ] Add piece with:
  - Panjang Awal: 400 M
  - Panjang Akhir: 350 M
  - Std: 30 M
  - Toleransi: 30 CM

**Expected Database Values:**
```
panjang_awal = 400
panjang_awal_conv = 437.44 (400 ÷ 0.9144)
panjang_akhir = 350
panjang_akhir_conv = 382.64 (350 ÷ 0.9144)
standart_potong = 30.30
standart_potong_conv = 33.12 (30.30 ÷ 0.9144)
min_potong = 10.30
min_potong_conv = 11.27 (10.30 ÷ 0.9144)
max_potong = 40.30
max_potong_conv = 44.08 (40.30 ÷ 0.9144)
toleransi = 30
toleransi_conv = 0.3281 (30 × 0.010936)
uom_cp = M
uom_counter = Y
```

**Verification:**
- [ ] panjang_awal_conv = panjang_awal ÷ 0.9144
- [ ] standart_potong_conv = standart_potong ÷ 0.9144
- [ ] toleransi_conv = toleransi × 0.010936
- [ ] All _conv values are greater (M→Y makes values larger)

**Process Test:**
- [ ] Click "Processed" button
- [ ] Process should use panjang_awal_conv (437.44)
- [ ] Process should use standart_potong_conv (33.12)
- [ ] Results should be in Yard units

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 3: Y→M (Yard to Meter - Convert)

**Setup:**
- [ ] Create new cutting
- [ ] Set UOM CP: **Yard**
- [ ] Set Type Counter: **Meter**
- [ ] Add piece with:
  - Panjang Awal: 400 Y
  - Panjang Akhir: 350 Y
  - Std: 30 Y
  - Toleransi: 30 CM

**Expected Database Values:**
```
panjang_awal = 400
panjang_awal_conv = 365.76 (400 × 0.9144)
panjang_akhir = 350
panjang_akhir_conv = 320.04 (350 × 0.9144)
standart_potong = 30.30
standart_potong_conv = 27.71 (30.30 × 0.9144)
min_potong = 10.30
min_potong_conv = 9.41 (10.30 × 0.9144)
max_potong = 40.30
max_potong_conv = 36.83 (40.30 × 0.9144)
toleransi = 30
toleransi_conv = 0.30 (30 × 0.01)
uom_cp = Y
uom_counter = M
```

**Verification:**
- [ ] panjang_awal_conv = panjang_awal × 0.9144
- [ ] standart_potong_conv = standart_potong × 0.9144
- [ ] toleransi_conv = toleransi × 0.01
- [ ] All _conv values are smaller (Y→M makes values smaller)

**Process Test:**
- [ ] Click "Processed" button
- [ ] Process should use panjang_awal (400, not converted)
- [ ] Process should use standart_potong (30.30, not converted)
- [ ] Results should be in Meter units

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 4: Y→Y (Yard to Yard - No Conversion)

**Setup:**
- [ ] Create new cutting
- [ ] Set UOM CP: **Yard**
- [ ] Set Type Counter: **Yard**
- [ ] Add piece with:
  - Panjang Awal: 400 Y
  - Panjang Akhir: 350 Y
  - Std: 30 Y
  - Toleransi: 30 CM

**Expected Database Values:**
```
panjang_awal = 400
panjang_awal_conv = 400 (no conversion)
standart_potong = 30.30
standart_potong_conv = 30.30 (no conversion)
toleransi = 30
toleransi_conv = 0.3281 (30 × 0.010936)
uom_cp = Y
uom_counter = Y
```

**Verification:**
- [ ] panjang values: original = converted (no conversion)
- [ ] std values: original = converted (no conversion)
- [ ] toleransi converted to Y unit (0.3281)

**Process Test:**
- [ ] Click "Processed" button
- [ ] Process should use panjang_awal_conv (same as panjang_awal)
- [ ] Process should use standart_potong_conv (same as standart_potong)
- [ ] Results should be in Yard units

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 5: Edit Piece Data

**Setup:**
- [ ] Create any cutting (from Test 1, 2, 3, or 4)
- [ ] Click Edit on a piece
- [ ] Modify:
  - Panjang Awal: Add 50
  - Panjang Akhir: Add 50
  - Std: Add 5

**Verification:**
- [ ] Database updates both original and _conv columns
- [ ] Conversion logic still applied correctly
- [ ] No double-addition of toleransi
- [ ] All values consistent with UOM combination

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 6: Error Handling

**Setup:**
- [ ] Create new cutting but:
  - [ ] Leave CP No empty → Should show error
  - [ ] Don't select mesin → Should show error
  - [ ] Add piece but leave piece_code empty → Should show error

**Verification:**
- [ ] Errors show as JSON messages
- [ ] Error messages are clear and helpful
- [ ] Data is not saved if validation fails
- [ ] Can retry after fixing errors

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 7: Process Calculation Verification

**For Each UOM Combination (4 tests):**

**Setup:**
- [ ] Have completed data from Tests 1-4
- [ ] Click "Processed" button

**Verification:**
- [ ] Process starts without error
- [ ] cl_cutting_process table gets filled
- [ ] cl_cutting_summary table gets filled
- [ ] Results show expected categories (A1 Standart, A1 Non Standart, A2, BS KG)
- [ ] Process runs with correct column set

**Data Integrity Checks:**
- [ ] Process uses correct panjang_awal (or panjang_awal_conv)
- [ ] Process uses correct std (or standart_potong_conv)
- [ ] Process results are in correct unit (M or Y based on type_counter)

**Status M→M:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED  
**Status M→Y:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED  
**Status Y→M:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED  
**Status Y→Y:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 8: Calculation Accuracy

**For M→Y Case (Use Test 2 data):**

**Manual Calculation:**
```
Start: 437.44 Yard (panjang_awal_conv)
End:   382.64 Yard (panjang_akhir_conv)
Std:   33.12 Y (standart_potong_conv)
Min:   11.27 Y (min_potong_conv)

Cutting:
437.44 - 382.64 = 54.8 Y available
54.8 ÷ 33.12 = 1.65 pieces (take 1 piece)

Piece 1: 437.44 - 33.12 = 404.32 Y (kategori?)
Piece 2: 404.32 - 33.12 = 371.2 Y
...

Verify each result matches expected calculation
```

**Database Verification:**
- [ ] cl_cutting_process has correct start_pos
- [ ] cl_cutting_process has correct end_pos
- [ ] cl_cutting_process has correct hasil_cutting
- [ ] cl_cutting_summary shows correct pcs count
- [ ] Categories assigned correctly

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 9: Multiple Pieces per Header

**Setup:**
- [ ] Create one header
- [ ] Add 3 different pieces (different panjang, std, tol)
- [ ] Process all pieces together

**Verification:**
- [ ] Each piece processes independently
- [ ] All pieces use correct columns (based on their UOM)
- [ ] All pieces show in cl_cutting_process
- [ ] Summary shows correct totals for each piece

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Test 10: Database Integrity

**Check Database Directly:**

```sql
-- Verify M→Y piece (Test 2)
SELECT 
    panjang_awal, panjang_awal_conv, 
    standart_potong, standart_potong_conv,
    uom_cp, uom_counter
FROM cl_cutting_piece
WHERE piece_code = 'YOUR_PIECE_CODE'

-- Verify conversion: panjang_awal_conv should be panjang_awal ÷ 0.9144
-- Verify UOM flags are correct
```

**Verification:**
- [ ] All pieces have both original and _conv values
- [ ] _conv values match conversion formula
- [ ] uom_cp and uom_counter flags are correct
- [ ] No NULL values in critical columns
- [ ] toleransi_conv is populated correctly

**Status:** ☐ PASSED / ☐ FAILED / ☐ NOT TESTED

---

## Summary of Results

| Test # | Name | Result | Date | Notes |
|--------|------|--------|------|-------|
| 1 | M→M | ☐ | | |
| 2 | M→Y | ☐ | | |
| 3 | Y→M | ☐ | | |
| 4 | Y→Y | ☐ | | |
| 5 | Edit Piece | ☐ | | |
| 6 | Error Handling | ☐ | | |
| 7 | Process Calculation | ☐ | | |
| 8 | Accuracy Check | ☐ | | |
| 9 | Multiple Pieces | ☐ | | |
| 10 | Database Integrity | ☐ | | |

---

## Overall Status

**All Tests Completed:** ☐ YES / ☐ NO / ☐ PARTIAL

**Overall Result:**
- ☐ ALL PASSED ✅
- ☐ SOME FAILED ⚠️
- ☐ MAJOR ISSUES 🔴

**Sign-Off:**
- Tester Name: _____________________
- Date: _____________________
- Comments: _____________________

---

## Issues Found (If Any)

### Issue #1
**Test:** ☐ Test 1 ☐ Test 2 ☐ Test 3 ☐ Test 4 ☐ Test 5 ☐ Test 6 ☐ Test 7 ☐ Test 8 ☐ Test 9 ☐ Test 10

**Description:**
```
[Describe what went wrong]
```

**Expected vs Actual:**
```
Expected: [what should happen]
Actual:   [what actually happened]
```

**Steps to Reproduce:**
```
[Clear steps to reproduce the issue]
```

**Browser Console Error (if any):**
```
[Paste any JavaScript errors]
```

---

### Issue #2
**Test:** ☐ Test 1 ☐ Test 2 ☐ Test 3 ☐ Test 4 ☐ Test 5 ☐ Test 6 ☐ Test 7 ☐ Test 8 ☐ Test 9 ☐ Test 10

**Description:**
```
[Describe what went wrong]
```

---

## Reference Documents

For detailed information, see:
- 📄 LOGIC_KONVERSI_UOM.md - Technical specifications
- 📄 QUICK_REFERENCE_PHASE6.md - Quick start guide
- 📄 README_PHASE6_COMPLETE.md - Complete overview
- 📄 VERIFICATION_REPORT_PHASE6.md - Quality report

---

**Good luck with testing! All code is ready and verified.** ✅
