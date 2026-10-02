# 🔧 FIX - Process Button HTML Response + Conversion Issue

## Issue 1: Fixed ✅ 

### Problem
When clicking "Processed" or "Unprocessed" buttons, response shows:
```
Raw response: <!DOCTYPE html>...header...{"status":"ok","processed":1}
```

### Root Cause
Files `process_cutting.php` and `unprocess_cutting.php` had:
```php
require_once '../../includes/header.php';  // ← This outputs HTML
```

### Solution Applied ✅
Removed `require_once '../../includes/header.php';` from both files.

**Result:** Now only JSON is returned, no HTML.

---

## Issue 2: Conversion Values Wrong

### Problem Report
```
Inputs: std=30.30, min=10.30, max=40.30 (labeled as Meter)
uom_cp = Yard
uom_counter = Yard
Expected: 30.30, 10.30, 40.30 (no conversion for Y→Y)
Actual: 27.7063, 9.4183, 36.8503 (Y×0.9144, wrong!)
```

### Root Cause Analysis

The conversion results (27.7063 = 30.30 × 0.9144) suggest:
- The system IS treating input as Yard
- But then CONVERTING to Meter (Y→M)
- When it should NOT convert (Y→Y)

**OR**

- The form shows "Meter" labels
- But user is supposed to input in Yard unit (since uom_cp=Y)
- Mismatch between label and actual unit

### Solution: Check Your Data

**Before going further, please verify:**

1. **What unit are you actually inputting in the form?**
   - When you enter "30.30" in the Std field
   - Are you thinking of it as Meter or Yard?
   - What does the form label currently say?

2. **What should the form labels say?**
   - If uom_cp = Meter, labels should say "Meter"
   - If uom_cp = Yard, labels should say "Yard"

### Likely Fix Needed

The form labels in `modal_piece.php` should be **DYNAMIC** based on `uom_cp`:

Instead of:
```html
<label>Std *</label>
```

Should be:
```html
<label>Std (uom_cp_unit) *</label>
```

Or in JavaScript:
```javascript
// When modal opens, update labels based on uom_cp
const uomCp = $('#uomCp').val();  // M or Y
const unitLabel = (uomCp === 'Y') ? 'Yard' : 'Meter';
$('#stdLabel').text('Std (' + unitLabel + ') *');
// ... same for all other fields
```

### Conversion Logic Check

The save_cutting.php logic for Y→Y SHOULD be:
```php
// If uom_cp = Y and uom_counter = Y
// No conversion needed
if ($uom_cp === 'Y' && $uom_counter === 'Y') {
    // std_conv should stay = std_input (no change)
    // This is already correct in code
}
```

### What the Code Currently Does

In `save_cutting.php` (lines 89-105):
```php
// Conversion jika uom_cp != uom_counter
if ($uom_cp === 'M' && $uom_counter === 'Y') {
    // M ke Y: bagi dengan 0.9144
    $std_conv = $std_input / 0.9144;
    ...
} elseif ($uom_cp === 'Y' && $uom_counter === 'M') {
    // Y ke M: kalikan dengan 0.9144
    $std_conv = $std_input * 0.9144;  ← HERE!
    ...
}
// Jika sama (M=M atau Y=Y): nilai conv = nilai asli
```

**AH! I see it now!**

If Y→M case (uom_cp='Y', uom_counter='M'), code does: `std_conv = std × 0.9144`

But in your case, you have Y→Y, so it should NOT enter either condition and should keep: `std_conv = std_input`

**UNLESS** the database shows uom_counter = M instead of Y!

---

## Diagnosis Steps

Run this SQL query to check what's actually stored:
```sql
SELECT TOP 5
    id_piece,
    standart_potong,
    standart_potong_conv,
    uom_cp,
    uom_counter
FROM cl_cutting_piece
ORDER BY id_piece DESC
```

### Expected Results for Y→Y case:
```
standart_potong = 30.30
standart_potong_conv = 30.30
uom_cp = Y
uom_counter = Y
```

### If you see this instead:
```
standart_potong = 30.30
standart_potong_conv = 27.7063  ← WRONG!
uom_cp = Y
uom_counter = M  ← Should be Y!
```

Then the issue is: **The type_counter value is being saved as M instead of Y**

---

## Action Items

1. ✅ **Fixed:** HTML in process/unprocess response
   - Removed header.php includes

2. ⏳ **Verify:** What data is actually stored
   - Run SQL query above
   - Check uom_counter values in database

3. ⏳ **Fix (if needed):** Form labels to show correct unit
   - Make labels dynamic based on uom_cp
   - User will know what unit to input in

4. ⏳ **Fix (if needed):** Correct any stored conversion values
   - If uom_counter saved as wrong value, need data correction

---

## Summary

- ✅ **Issue 1 (HTML response):** FIXED by removing header.php
- 🔍 **Issue 2 (Conversion):** Need to verify stored data

Please run the SQL query above and report:
1. What values you see in the database
2. What the uom_counter column shows (M or Y?)
3. Then we can fix the conversion issue exactly

---

**Status:**
- Process/Unprocess buttons: ✅ FIXED (will now show proper redirect without HTML)
- Conversion values: 🔍 INVESTIGATING (need data verification)

Once you provide the SQL results, we can fix the conversion issue.
