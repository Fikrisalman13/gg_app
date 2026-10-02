# Implementasi Logic Cutting dengan Toleransi

## 📋 File yang Telah Dibuat/Diupdate

### 1. **[CUTTING_CALCULATION_LOGIC.md](CUTTING_CALCULATION_LOGIC.md)** ✅ NEW
Dokumentasi lengkap tentang:
- Overview sistem cutting list
- Rumus dasar perhitungan
- Contoh kalkulasi untuk 4 scenario berbeda
- Tabel referensi konversi satuan
- Poin penting implementasi

### 2. **[includes/cutting_helper.php](includes/cutting_helper.php)** ✅ NEW
Helper function library dengan fungsi-fungsi:
- `convertToleranceCMtoUOM()` - Konversi toleransi dari CM ke satuan target
- `getConversionFactor()` - Hitung faktor konversi antar UOM
- `convertPanjang()` - Konversi panjang antara Meter dan Yard
- `determineCategory()` - Tentukan kategori potongan (A1, A2, BS KG, dll)
- `isRangeOverlap()` - Cek overlap antara 2 range
- `calculateCuttingWithTolerance()` - Hitung cutting dengan toleransi
- `calculateCuttingSummary()` - Hitung ringkasan per kategori
- `performCutting()` - **MAIN FUNCTION** - Orchestrate seluruh kalkulasi

### 3. **[test_cutting_calculation.php](test_cutting_calculation.php)** ✅ NEW
File test dengan 4 scenario:
- Scenario 1: UOM CP = M, Type Counter = M
- Scenario 2: UOM CP = M, Type Counter = Y
- Scenario 3: UOM CP = Y, Type Counter = M
- Scenario 4: UOM CP = Y, Type Counter = Y

Jalankan dengan:
```
php test_cutting_calculation.php
```

### 4. **[process_cutting.php](process_cutting.php)** ✅ UPDATED
Diupdate untuk menggunakan helper function:
- Include `cutting_helper.php`
- Ganti logic perhitungan dengan `performCutting()` function
- Hapus fungsi lama yang sudah dipindah ke helper

---

## 🔑 Logika Utama

### Input Data (Selalu dalam Meter):
- `panjang_awal` & `panjang_akhir`: SELALU dalam Meter
- Konversi hanya untuk kalkulasi internal

### Proses:
1. **Konversi Toleransi** (CM → UOM CP)
   ```
   Toleransi(M) = Toleransi(CM) × 0.01
   Toleransi(Y) = Toleransi(CM) × 0.010936
   ```

2. **Hitung Panjang Potongan dengan Toleransi**
   ```
   Panjang Potongan = STD + Toleransi
   ```

3. **Konversi ke Unit Kalkulasi** (berdasarkan Type Counter)
   ```
   Jika Type Counter = Y: Konversi M → Y
   Jika Type Counter = M: Gunakan nilai asli
   ```

4. **Kalkulasi Cutting Sequential**
   ```
   LOOP dari panjang_awal sampai panjang_akhir:
     - Tentukan end_pos = min(current_pos + Panjang Potongan, panjang_akhir)
     - Cek cacat dalam range [current_pos, end_pos]
     - Jika ada cacat: split dan catat
     - Tentukan kategori berdasarkan hasil_cutting vs STD/MIN
   ```

5. **Hitung Ringkasan**
   ```
   Jumlah PCS per kategori
   Total panjang per kategori
   ```

---

## 🎯 Hasil Output dari `performCutting()`

```php
[
    'config' => [
        'panjang_awal' => float,
        'panjang_akhir' => float,
        'std' => float,
        'toleransi_cm' => float,
        'std_dengan_tol' => float,
        'uom_cp' => string (M/Y),
        'type_counter' => string (M/Y),
        'conversion_factor' => float
    ],
    'cutting' => [
        [
            'start_pos' => float,
            'end_pos' => float,
            'hasil_cutting' => float,
            'kategori' => string
        ],
        // ... lebih banyak potongan
    ],
    'summary' => [
        'A1 Standart' => [
            'pcs' => int,
            'total_panjang' => float,
            'items' => array
        ],
        // ... kategori lainnya
    ],
    'total_pcs' => int,
    'total_panjang' => float
]
```

---

## ✅ Checklist Implementasi

- [x] Buat helper function library
- [x] Implement toleransi konversi (CM → UOM CP)
- [x] Implement conversion factor (UOM CP → Type Counter)
- [x] Implement cutting calculation dengan toleransi
- [x] Update `process_cutting.php` untuk menggunakan helper
- [x] Buat test file untuk verifikasi
- [x] Dokumentasi lengkap

---

## 🧪 Testing

Jalankan test file untuk memverifikasi:
```bash
cd c:/xampp/htdocs/gg_app/pages/cuttinglist
php test_cutting_calculation.php
```

Expected output: Detail cutting untuk 4 scenario dengan jumlah PCS dan kategori yang tepat.

---

## 📝 Catatan Penting

1. **Toleransi HARUS ditambahkan ke STD**
   - User input STD, MIN, MAX tanpa toleransi
   - Kalkulasi menambahkan toleransi secara otomatis

2. **Panjang input SELALU Meter**
   - Tidak peduli UOM CP atau Type Counter
   - Konversi hanya internal untuk kalkulasi

3. **Unit Kalkulasi tergantung Type Counter**
   - Bukan UOM CP
   - Jadi lebih fleksibel untuk mesin yang berbeda

4. **Cacat adalah range (dari-sampai)**
   - Input tetap dalam Meter di database
   - Dikonversi ke unit kalkulasi saat proses

5. **Kategori Potongan**
   - A1 Standart: Hasil = STD + Toleransi
   - A1 Non Standart: MIN ≤ Hasil < STD + Toleransi
   - A2: 3 cm ≤ Hasil < MIN
   - BS KG: Hasil < 3 cm
   - CACAT: Jika overlap dengan cacat range

---

**Status**: Implementation Complete ✅  
**Date**: December 24, 2025  
**Version**: 1.0
