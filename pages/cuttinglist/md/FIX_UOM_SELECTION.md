# 🎯 FIX - Wrong UOM Selection

## The Problem

Your database shows:
```
uom_cp = Y (Yard)      ← This is WRONG - should be M
uom_counter = M        ← This is WRONG - should be Y
standart_potong_conv = 27.7063  ← Wrong: Y×0.9144 (Y→M)
                                   Should be: 33.1265 (M÷0.9144)
```

## Why It's Wrong

**Your requirement:**
- Input values in **Meter** (30.30 M)
- Calculate in **Yard** (33.1265 Y)

**But you're selecting:**
- UOM CP = Y (means input in Yard)
- Type Counter = M (means calculate in Meter)

**This is reversed!**

## The Fix - Next Time You Add a Cutting

### Step 1: Select Correct UOM CP
Open tambahcutting.php → Look for "UOM CP" dropdown
```
Select: M (Meter)  ← Because your input values are in METER
```

### Step 2: Select Correct Type Counter  
Look for "Type Counter" dropdown
```
Select: Y (Yard)  ← Because you want calculations in YARD
```

### Result
- standart_potong = 30.3000 (input in M)
- standart_potong_conv = 33.1265 (converted to Y)
- uom_cp = M
- uom_counter = Y
✅ CORRECT!

---

## For Existing Wrong Data

If you have wrong data already saved in the database, we have two options:

### Option A: Delete and Re-enter (Recommended)
1. Delete the wrong cuttings
2. Create new ones with correct UOM selections (M and Y)
3. Done!

### Option B: Fix Database Data (If Many Records)
If you have many wrong records, I can create a SQL script to fix them.

---

## Visual Guide - Form Selection

### In tambahcutting.php Form:

```
┌─────────────────────────────────────┐
│ CP No: [D25]                        │
│                                     │
│ UOM CP: [M - Meter]   ← SELECT THIS │
│         (Your input unit)           │
│                                     │
│ Type Counter: [Y - Yard] ← OR THIS  │
│              (Calculation unit)     │
│                                     │
│ [Save] [Cancel]                     │
└─────────────────────────────────────┘
```

### Then in Piece Modal:

```
Panjang Awal: 400 (in Meter)
Std: 30 (in Meter)
...
Toleransi: 30 (in CM)
```

### Result in Database:

```
standart_potong = 30.30 (M)
standart_potong_conv = 33.1265 (Y)  ← Converted from M to Y!
uom_cp = M
uom_counter = Y
```

---

## Summary

| Setting | Current | Should Be | Notes |
|---------|---------|-----------|-------|
| UOM CP | Y | **M** | Your input unit is Meter |
| Type Counter | M | **Y** | Your calculation unit is Yard |
| Result | WRONG | CORRECT | Conversion will be M→Y |

---

**Action: When creating next cutting, select M for UOM CP and Y for Type Counter!**
