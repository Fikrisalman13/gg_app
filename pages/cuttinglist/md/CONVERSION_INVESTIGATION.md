# 🔍 Conversion Issue Investigation

## Problem Statement

When processing a piece with:
- Input: std=30.30, min=10.30, max=40.30 (user says "Meter")
- UOM CP: Yard
- Type Counter: Yard

Getting converted values:
- Std Conv: 27.7063 Y (WRONG - should be 30.30)
- Min Conv: 9.4183 Y (WRONG - should be 10.30)
- Max Conv: 36.8503 Y (WRONG - should be 40.30)

## Analysis

### The Calculation
```
27.7063 = 30.30 × 0.9144  ← This is Y→M conversion (wrong direction)
9.4183 = 10.30 × 0.9144   ← This is Y→M conversion (wrong direction)
36.8503 = 40.30 × 0.9144  ← This is Y→M conversion (wrong direction)
```

Expected (if no conversion needed for Y→Y):
```
30.30 should stay 30.30
10.30 should stay 10.30  
40.30 should stay 40.30
```

OR if input is Meter and needs M→Y conversion:
```
30.30 ÷ 0.9144 = 33.1265 Y ← This is what user expects
10.30 ÷ 0.9144 = 11.2639 Y
40.30 ÷ 0.9144 = 44.0506 Y
```

### Root Cause Possibilities

**Issue 1: Form Display vs Actual Input Unit**
- Form might show "Meter" labels but users set uom_cp = Yard
- System treats input values as being IN THE UOM_CP UNIT
- So if user enters 30.30 with uom_cp=Y, system thinks it's 30.30 Yard
- But form label says "Meter"

**Issue 2: Conversion Direction in Code**
- The conversion logic might be backwards
- If uom_cp = Y and uom_counter = Y: should be NO conversion (×1)
- But code might be doing Y→M conversion (×0.9144)

**Issue 3: Display vs Storage**
- Displayed values might be in uom_cp unit
- But code might be assuming they're in a different unit

## What We Need to Check

1. **What unit are the input values in?**
   - Are they typed as entered, or converted before saving?
   
2. **What does uom_cp represent?**
   - The unit used for INPUT (what user types)
   - If uom_cp = Y, values should be entered in Yard

3. **What does uom_counter represent?**
   - The unit used for CALCULATION
   - If uom_counter = Y, calculations should be in Yard

4. **Form Labels**
   - Should form labels change based on uom_cp?
   - If uom_cp = Y, should labels say "Yard" not "Meter"?

## Solution Path

### If user input values are labeled "Meter" but uom_cp = Y:
- **Fix:** Form labels should be dynamic based on uom_cp
- Update tambahcutting.php to show correct unit labels

### If conversion formula is wrong for Y→Y:
- **Fix:** Ensure Y→Y case uses no conversion (×1)
- Check save_cutting.php conversion logic

### If values are stored in wrong unit:
- **Fix:** Verify what's stored in standart_potong vs standart_potong_conv
- Check database directly

## Questions for User

1. **When you set uom_cp = Yard, what unit are you typing in the form?**
   - If you type 30.30, does that mean 30.30 Yard or 30.30 Meter?

2. **When you set uom_counter = Yard, do you expect calculations in Yard?**

3. **Are the form labels supposed to change based on uom_cp?**
   - Currently they might be hardcoded to "Meter"

## Next Investigation

Need to:
1. Check if form labels are dynamic (based on uom_cp)
2. Verify what unit the conversion assumes for input values
3. Check if Y→Y conversion is incorrectly applied
4. Determine if input values should auto-convert or stay as-is

---

**The issue is likely a mismatch between:**
- What the form displays (labels say "Meter")  
- What the system expects (uom_cp = Yard means Yard input)
- What gets calculated (Y→Y should be no conversion)
