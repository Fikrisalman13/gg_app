# 🎯 QUICK REFERENCE - Cutting List Calculation

## 1️⃣ Main Function

```php
require_once 'includes/cutting_helper.php';

$result = performCutting([
    'panjang_awal'  => 400,      // Meter (ALWAYS in Meter)
    'panjang_akhir' => 400,      // Meter
    'std'           => 30,       // dalam UOM CP
    'min'           => 10,       // dalam UOM CP
    'max'           => 40,       // dalam UOM CP
    'toleransi'     => 30,       // CM (penting!)
    'uom_cp'        => 'M',      // M atau Y
    'type_counter'  => 'M',      // M atau Y
    'cacat_list'    => [
        ['dari' => 39, 'sampai' => 49, 'status' => 'CACAT']
    ]
]);

echo "Total PCS: " . $result['total_pcs'];
echo "Total Panjang: " . $result['total_panjang'];

foreach ($result['summary'] as $kategori => $data) {
    echo "$kategori: {$data['pcs']} pcs\n";
}
```

---

## 2️⃣ Conversion Reference

### Toleransi Conversion (CM → UOM CP)
```
Jika UOM CP = M:
  Tol(M) = Tol(CM) × 0.01
  Contoh: 30 CM = 0.30 M

Jika UOM CP = Y:
  Tol(Y) = Tol(CM) × 0.010936
  Contoh: 30 CM = 0.3281 Y
```

### Unit Conversion (M ↔ Y)
```
M → Y: × (1 / 0.9144) = × 1.09361
Y → M: × 0.9144

Contoh:
  400 M = 400 × 1.09361 = 437.445 Y
  437.445 Y = 437.445 × 0.9144 = 400 M
```

---

## 3️⃣ UOM Combinations Matrix

| UOM CP | Type Counter | Unit Hitung | Conversion |
|--------|--------------|-------------|-----------|
| M | M | Meter | ✘ Tidak ada |
| M | Y | Yard | M → Y |
| Y | M | Meter | Y → M |
| Y | Y | Yard | ✘ Tidak ada |

---

## 4️⃣ Category Determination Logic

```
Diberikan:
  hasil = hasil potongan
  std_dengan_tol = standard + toleransi
  min = minimal potongan

Kategori:
  Jika hasil ≈ std_dengan_tol  → "A1 Standart"
  Jika min ≤ hasil < std_dengan_tol → "A1 Non Standart"
  Jika 3 ≤ hasil < min         → "A2"
  Jika hasil < 3               → "BS KG" (Bagus Kecil)
  Jika overlap cacat           → "CACAT" / status cacat
```

---

## 5️⃣ Formula Panjang Potongan

```
Panjang Potongan = STD + Toleransi (dalam UOM CP)

Contoh:
  STD = 30 M, Toleransi = 30 CM = 0.30 M
  Panjang Potong = 30 + 0.30 = 30.30 M
  
  STD = 30 Y, Toleransi = 30 CM = 0.3281 Y
  Panjang Potong = 30 + 0.3281 = 30.3281 Y
```

---

## 6️⃣ Cacat Detection Logic

```
Jika potongan range [start, end] overlap dengan cacat [dari, sampai]:
  1. Potong bagian sebelum cacat (start - dari)
  2. Catat cacat (dari - sampai)
  3. Lanjut dari akhir cacat (sampai)

Contoh:
  Potongan normal: [30.30 - 60.60]
  Cacat: [39 - 49]
  
  Hasil:
  → [30.30 - 39] = 8.70 M (A2, < MIN)
  → [39 - 49] = 10 M (CACAT)
  → [49 - 79.30] = 30.30 M (Normal, lanjut)
```

---

## 7️⃣ Common Mistakes to Avoid

❌ **JANGAN:**
- Input panjang dalam Yard kalau seharusnya Meter
- Toleransi dalam Meter/Yard (harus CM!)
- Campur UOM CP dengan Type Counter dalam kalkulasi
- Lupa converting cacat ke unit kalkulasi

✅ **SELALU:**
- Input panjang dalam Meter
- Toleransi dalam CM
- Type Counter tentukan unit kalkulasi (bukan UOM CP)
- Konversi cacat ke unit kalkulasi

---

## 8️⃣ Example: Dari 1000m, Potong 30m

### Scenario A: M → M
```
STD: 30 M, Tol: 30 CM = 0.30 M
Panjang Potong: 30.30 M
Dari 1000 M: 1000 ÷ 30.30 = 33 pcs
```

### Scenario B: M → Y (Input M, Hitung Y)
```
STD: 30 M, Tol: 30 CM = 0.30 M
Panjang Potong: 30.30 M
Konversi ke Y: 30.30 × 1.09361 = 33.1365 Y
Panjang total: 1000 × 1.09361 = 1093.61 Y
Dari 1093.61 Y: 1093.61 ÷ 33.1365 = 33 pcs
```

### Scenario C: Y → M (STD dalam Y, Hitung M)
```
STD: 30 Y, Tol: 30 CM = 0.3281 Y
Panjang Potong (Y): 30.3281 Y
Panjang Potong (M): 30.3281 × 0.9144 = 27.731 M
Dari 1000 M: 1000 ÷ 27.731 = 36 pcs
```

---

## 9️⃣ Output Structure

```php
[
    'config' => [
        'panjang_awal' => 400,
        'panjang_akhir' => 400,
        'std' => 30,
        'toleransi_cm' => 30,
        'std_dengan_tol' => 30.30,
        'uom_cp' => 'M',
        'type_counter' => 'M',
        'conversion_factor' => 1.0
    ],
    
    'cutting' => [
        [
            'start_pos' => 0,
            'end_pos' => 30.30,
            'hasil_cutting' => 30.30,
            'kategori' => 'A1 Standart'
        ],
        // ... more cuts
    ],
    
    'summary' => [
        'A1 Standart' => [
            'pcs' => 12,
            'total_panjang' => 363.6,
            'items' => [...]
        ],
        'A2' => [
            'pcs' => 1,
            'total_panjang' => 8.70,
            'items' => [...]
        ],
        'CACAT' => [
            'pcs' => 1,
            'total_panjang' => 10,
            'items' => [...]
        ]
    ],
    
    'total_pcs' => 14,
    'total_panjang' => 382.3
]
```

---

## 🔟 Database Integration

```php
// Di process_cutting.php
$config = [
    'panjang_awal' => $piece['panjang_awal'],
    'panjang_akhir' => $piece['panjang_akhir'],
    'std' => $piece['standart_potong'],
    'min' => $piece['min_potong'],
    'max' => $piece['max_potong'],
    'toleransi' => $piece['toleransi'], // Dalam CM
    'uom_cp' => $header['uom_cp'],
    'type_counter' => $header['type_counter'],
    'cacat_list' => $cacat_list // dari database
];

$result = performCutting($config);

// Save ke cl_cutting_process
foreach ($result['cutting'] as $idx => $item) {
    // INSERT INTO cl_cutting_process
    // (id_piece, process_no, start_pos, end_pos, hasil_cutting, kategori)
    // VALUES (?, ?, ?, ?, ?, ?)
}

// Save ke cl_cutting_summary
foreach ($result['summary'] as $kategori => $data) {
    // INSERT INTO cl_cutting_summary
    // (id_piece, kategori, total_pcs, total_panjang)
    // VALUES (?, ?, ?, ?)
}
```

---

## 📝 Input Format

### User Input (dalam form):
```javascript
{
    panjang_awal: 400,              // ✅ Meter
    panjang_akhir: 400,             // ✅ Meter
    std: 30,                        // ✅ dalam UOM CP
    min: 10,                        // ✅ dalam UOM CP
    max: 40,                        // ✅ dalam UOM CP
    toleransi: 30,                  // ✅ CM (bukan M/Y!)
    lebar: 150,                     // ✅ Lebar kain (cm)
    uom_cp: 'M',                    // ✅ M atau Y
    type_counter: 'M',              // ✅ M atau Y
    cacat: [
        {no: 1, dari: 39, sampai: 49, status: 'CACAT KELIM'}
    ]
}
```

---

## ⚡ Quick Test

```bash
# Run test file
cd c:/xampp/htdocs/gg_app/pages/cuttinglist
php test_cutting_calculation.php

# Output:
# === SCENARIO 1: M → M ===
# Input:
#   Panjang: 400 - 400 M
#   STD: 30 M, MIN: 10 M, MAX: 40 M
#   Toleransi: 30 CM
#   STD + Toleransi: 30.30 M
#   UOM CP: M, Type Counter: M
# 
# Hasil:
#   Total PCS: 13
#   Total Panjang: 400 M
# 
# Ringkasan per Kategori:
#   A1 Standart: 12 pcs, Total: 363.6 M
#   A2: 1 pcs, Total: 8.7 M
#   CACAT KELIM: 1 pcs, Total: 10 M
```

---

## 🎓 Learning Resources

1. **Detail Logic**: [CUTTING_CALCULATION_LOGIC.md](CUTTING_CALCULATION_LOGIC.md)
2. **Visual Examples**: [VISUAL_EXAMPLES_TOLERANSI.md](VISUAL_EXAMPLES_TOLERANSI.md)
3. **Full Guide**: [IMPLEMENTATION_NOTES_TOLERANSI.md](IMPLEMENTATION_NOTES_TOLERANSI.md)
4. **Source Code**: [includes/cutting_helper.php](includes/cutting_helper.php)

---

**Last Updated**: December 24, 2025  
**Version**: 1.0 - Quick Reference Card
