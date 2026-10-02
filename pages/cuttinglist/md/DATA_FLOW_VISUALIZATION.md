# DATA FLOW & CONVERSION VISUALIZATION
## UOM Conversion Implementation

---

## 📊 Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER INPUT (Modal Piece)                     │
│  Std: 11.5 Y | Min: 9.0 Y | Max: 13.0 Y | Tol: 0.5 Y           │
│  UOM CP: Yard | Type Counter: Meter                            │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│           SAVE_CUTTING.PHP - Processing Logic                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. ADD TOLERANCE:                                             │
│     std_dengan_tol = 11.5 + 0.5 = 12.0                         │
│     min_dengan_tol = 9.0 + 0.5 = 9.5                           │
│     max_dengan_tol = 13.0 + 0.5 = 13.5                         │
│                                                                 │
│  2. DETERMINE CONVERSION FACTOR:                              │
│     uom_counter = 'M' (Meter)                                  │
│     uom_cp = 'Y' (Yard)                                        │
│     M → Y: factor = 1 / 0.9144 = 1.0936                        │
│                                                                 │
│  3. CALCULATE CONVERSION:                                      │
│     std_conv = 12.0 ÷ 0.9144 = 10.97 M                         │
│     min_conv = 9.5 ÷ 0.9144 = 8.69 M                           │
│     max_conv = 13.5 ÷ 0.9144 = 12.33 M                         │
│     toleransi_conv = 0.5 ÷ 0.9144 = 0.4572 M                   │
│                                                                 │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│               DATABASE - cl_cutting_piece                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  COLUMN                      │ VALUE           │ MEANING        │
│  ─────────────────────────────────────────────────────────────│
│  standart_potong             │ 12.0            │ With tolerance │
│  standart_potong_conv        │ 10.97 M         │ Converted      │
│  min_potong                  │ 9.5             │ With tolerance │
│  min_potong_conv             │ 8.69 M          │ Converted      │
│  max_potong                  │ 13.5            │ With tolerance │
│  max_potong_conv             │ 12.33 M         │ Converted      │
│  toleransi                   │ 0.5 Y           │ Original       │
│  toleransi_conv              │ 0.4572 M        │ Converted      │
│  uom_cp                      │ Y               │ Yard (original)│
│  uom_counter                 │ M               │ Meter (counter)│
│                                                                 │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│          DISPLAY (detail_cutting.php, index.php)               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Original Value (Yard)   │ Converted Value (Meter)             │
│  ──────────────────────────────────────────────────────────────│
│  Std: 12.0 Y             │ Std Conv: 10.97 M                   │
│  Min: 9.5 Y              │ Min Conv: 8.69 M                    │
│  Max: 13.5 Y             │ Max Conv: 12.33 M                   │
│  Tol: 0.5 Y              │ Tol Conv: 0.4572 M                  │
│                                                                 │
│  UOM CP: Yard | UOM Counter: Meter                             │
│                                                                 │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│        PROCESS_CUTTING.PHP - Use Conv Values                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  $std = 10.97 M          (from standart_potong_conv)           │
│  $min = 8.69 M           (from min_potong_conv)                │
│  $max = 12.33 M          (from max_potong_conv)                │
│                                                                 │
│  // No conversion needed! Already converted!                   │
│  $result = calculateCutting($std, $min, $max, ...);            │
│                                                                 │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│              FINAL RESULT - cl_cutting_process                 │
│        Calculations done with correct converted values        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Skenario Konversi

### Skenario 1: Meter → Meter (NO CONVERSION)
```
INPUT          PROCESSING           DATABASE STORE
──────────────────────────────────────────────────────
Std: 10.5 M    + Tol 0.5          standart_potong = 11.0
               = 11.0             standart_potong_conv = 11.0 (×1)
                                  uom_counter = M
                                  uom_cp = M

PROCESS        CALCULATION          RESULT
──────────────────────────────────────────────────────
$std = 11.0    Calculate with      Hasil cutting
(from conv)    meter values        (in meter)
```

### Skenario 2: Meter → Yard (CONVERSION)
```
INPUT          PROCESSING           DATABASE STORE
──────────────────────────────────────────────────────
Std: 11.5 Y    + Tol 0.5          standart_potong = 12.0 Y
               = 12.0 Y           standart_potong_conv = 10.97 M
               ÷ 0.9144           (12.0 ÷ 0.9144)
               = 10.97 M          uom_counter = M
                                  uom_cp = Y

PROCESS        CALCULATION          RESULT
──────────────────────────────────────────────────────
$std = 10.97   Calculate with      Hasil cutting
(from conv)    meter values        (in meter)
```

### Skenario 3: Yard → Meter (REVERSE CONVERSION)
```
INPUT          PROCESSING           DATABASE STORE
──────────────────────────────────────────────────────
Std: 10.5 M    + Tol 0.5          standart_potong = 11.0 M
               = 11.0 M           standart_potong_conv = 10.06 M
               × 0.9144           (11.0 × 0.9144)
               = 10.06 M          uom_counter = Y
                                  uom_cp = M

PROCESS        CALCULATION          RESULT
──────────────────────────────────────────────────────
$std = 10.06   Calculate with      Hasil cutting
(from conv)    meter values        (in meter)
```

---

## 📐 Conversion Factor Calculation

```
User selects: UOM CP (dari Cutting Piece) vs Type Counter (dari Header)

Factor Selection Logic:
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  IF uom_counter = 'M' AND uom_cp = 'Y':                   │
│      Meter → Yard: factor = 1 / 0.9144 = 1.0936           │
│                                                             │
│  ELSE IF uom_counter = 'Y' AND uom_cp = 'M':              │
│      Yard → Meter: factor = 0.9144                        │
│                                                             │
│  ELSE (M→M or Y→Y):                                        │
│      No conversion: factor = 1.0                           │
│                                                             │
└─────────────────────────────────────────────────────────────┘

Conversion Formula:
  Conv_Value = Original_Value × Factor
  
Examples:
  12.0 Y ÷ 0.9144 = 10.97 M    (Y→M: multiply factor 1/0.9144)
  11.0 M × 0.9144 = 10.06 M    (M→Y: multiply factor 0.9144)
  10.5 M × 1.0 = 10.5 M        (M→M: no change)
```

---

## 📋 Toleransi Integration

```
OLD LOGIC (Proses):
┌────────────────────────────────────┐
│ Save: std = 10.5                   │
│       tol = 0.5                    │
│ Process: std_with_tol = 10.5 + 0.5 │ ← ADDED HERE
│          = 11.0                    │
└────────────────────────────────────┘

NEW LOGIC (Save + Proses):
┌────────────────────────────────────┐
│ Save: std_dengan_tol = 10.5 + 0.5  │ ← ADDED HERE
│       = 11.0                       │
│       saved to: standart_potong    │
│ Process: std = 11.0 (from column)  │
│          No need to add again!      │
└────────────────────────────────────┘

BENEFIT:
  ✓ Tolerance calculation consistent
  ✓ Single source of truth
  ✓ Process logic simpler
  ✓ No double calculation
```

---

## 🔗 Database Column Mapping

```
ORIGINAL COLUMNS (From User Input):
  piece_input_std     → user input di modal
  piece_input_min     → user input di modal
  piece_input_max     → user input di modal
  toleransi           → user input di modal

CALCULATED & STORED COLUMNS:
  standart_potong     = piece_input_std + toleransi
  min_potong          = piece_input_min + toleransi
  max_potong          = piece_input_max + toleransi
  toleransi           = toleransi (original value)

CONVERTED COLUMNS:
  standart_potong_conv = (piece_input_std + tol) × factor
  min_potong_conv      = (piece_input_min + tol) × factor
  max_potong_conv      = (piece_input_max + tol) × factor
  toleransi_conv       = toleransi × factor

METADATA:
  uom_cp              = user selected UOM (Y/M)
  uom_counter         = from header type_counter (Y/M)

USED IN PROCESS:
  process uses: standart_potong_conv, min_potong_conv, max_potong_conv
  No more calculation needed!
```

---

## 🔍 Query Verification

```sql
-- 1. Check if conversion is correct (Meter → Yard)
SELECT
    id_piece,
    piece_code,
    standart_potong,                           -- Original with tol
    CAST(standart_potong / 0.9144 AS DECIMAL(10,3)) as expected_conv,  -- Expected
    standart_potong_conv,                      -- Actual stored
    CASE 
        WHEN ABS(standart_potong / 0.9144 - standart_potong_conv) < 0.01 
        THEN 'OK' 
        ELSE 'ERROR' 
    END as status,
    uom_counter,
    uom_cp
FROM cl_cutting_piece
WHERE uom_counter = 'M' AND uom_cp = 'Y'
ORDER BY id_piece DESC;

-- 2. Check if tolerance was added
SELECT
    id_piece,
    piece_code,
    ISNULL(@original_std, standart_potong - (SELECT toleransi FROM cl_cutting_piece)) as std_without_tol,
    standart_potong as std_with_tol,
    toleransi as tol,
    standart_potong - toleransi as calculated_original
FROM cl_cutting_piece
ORDER BY id_piece DESC;

-- 3. Check all conversion columns
SELECT
    id_piece,
    piece_code,
    standart_potong,
    standart_potong_conv,
    min_potong,
    min_potong_conv,
    max_potong,
    max_potong_conv,
    toleransi,
    toleransi_conv,
    uom_counter,
    uom_cp
FROM cl_cutting_piece
WHERE standart_potong_conv IS NOT NULL AND standart_potong_conv != 0
ORDER BY id_piece DESC;
```

---

## 📊 Example Query Results

```
Skenario: Y→M Conversion
─────────────────────────────────────────────────────────────
id_piece │ piece_code │ std    │ std_conv │ tol │ tol_conv │ counter │ cp
─────────┼────────────┼────────┼──────────┼─────┼──────────┼─────────┼──
123      │ A1         │ 12.0   │ 10.97    │ 0.5 │ 0.4572   │ M       │ Y
124      │ A2         │ 9.5    │ 8.69     │ 0.4 │ 0.3658   │ M       │ Y
125      │ A3         │ 13.5   │ 12.33    │ 0.6 │ 0.5486   │ M       │ Y

Skenario: M→M (No Conversion)
─────────────────────────────────────────────────────────────
id_piece │ piece_code │ std    │ std_conv │ tol │ tol_conv │ counter │ cp
─────────┼────────────┼────────┼──────────┼─────┼──────────┼─────────┼──
126      │ B1         │ 11.0   │ 11.0     │ 0.5 │ 0.5      │ M       │ M
127      │ B2         │ 8.5    │ 8.5      │ 0.3 │ 0.3      │ M       │ M
128      │ B3         │ 12.5   │ 12.5     │ 0.4 │ 0.4      │ M       │ M
```

---

## 🎯 Key Points

1. **Two-Stage Calculation:**
   - Stage 1 (Save): Add tolerance + Convert to target UOM
   - Stage 2 (Process): Use ready-made values, no recalculation

2. **Single Source of Truth:**
   - Kolom `*_conv` adalah "the truth"
   - Process hanya membaca dari sini
   - Tidak ada double-calculation

3. **Backward Compatible:**
   - Kolom lama tetap ada (standart_potong, etc)
   - Kolom conv default ke 0, tidak error
   - Data lama bisa di-update dengan migration script

4. **Conversion Standard:**
   - Always use: 1 Yard = 0.9144 Meter
   - No hardcoding UOM factor, always calculate from header
   - Support both directions: M↔Y seamlessly

---

Last Updated: December 23, 2025
