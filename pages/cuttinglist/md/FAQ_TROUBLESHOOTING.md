# ❓ FAQ & Troubleshooting - Cutting List Calculation

## ❓ FAQ - Pertanyaan Umum

### Q1: Toleransi itu apa sih?
**A:** Toleransi adalah kelonggaran/rentang dari standard potongan. Contoh:
- Standard potong: 30 meter
- Toleransi: 30 cm = 0.30 meter
- Potongan aktual: 30 ± 0.30 meter = 29.70 - 30.30 meter

Dalam sistem kami, potongan diambil sebesar STD + Toleransi = 30.30 meter.

---

### Q2: Berapa banyak kain yang terbuang?
**A:** Terbuang adalah semua potongan yang kategorinya bukan "A1 Standart":
- A1 Non Standart: Masih bagus, tapi tidak standar (bisa dijual dengan harga sedikit lebih murah)
- A2: Kecil-kecilan, bisa dikombinasi atau dijual murah
- BS KG (Bagus Kecil): Sangat kecil, biasanya tidak bisa dijual
- CACAT: Rusak, tidak bisa dijual

```
Contoh:
Total: 400 M
- A1 Standart: 363.6 M (90.9%)
- A1 Non Standart: 0 M
- A2: 8.7 M (2.2%)
- CACAT: 10 M (2.5%)
- BS KG: 17.7 M (4.4%)
─────────
Total: 400 M (100%)
```

---

### Q3: Kenapa potongan di cacat 39-49 menjadi A2, bukan dibuang?
**A:** Karena potongan sebelum cacat (0-39 = 8.70 M) masih bisa dipakai, hanya saja di bawah minimum 10 meter. Potongan kecil ini dikategorikan A2 yang mungkin bisa:
- Dikombinasi dengan potongan lain
- Dijual terpisah dengan harga lebih murah
- Dibuang jika tidak ada pembeli

Bagian cacat (39-49 = 10 M) tetap dibuang sebagai cacat.

---

### Q4: Panjang input harus dalam Meter?
**A:** **YA, SELALU dalam Meter!** Tidak peduli:
- UOM CP-nya Yard
- Type Counter-nya Yard
- Sistem internal akan konversi otomatis

Ini untuk konsistensi data di database. User bisa input dalam unit apapun, tapi backend selalu simpan dalam Meter.

---

### Q5: Toleransi harus dalam CM?
**A:** **YA, HARUS dalam CM!** Karena:
- User lebih familiar dengan CM (contoh: toleransi 30 cm, bukan 0.30 meter)
- Lebih presisi untuk nilai kecil
- Sistem akan konversi otomatis ke unit yang tepat

---

### Q6: Kenapa Type Counter itu penting?
**A:** Type Counter menentukan **unit perhitungan akhir**:
- Type Counter = M → Hitung dalam Meter
- Type Counter = Y → Hitung dalam Yard

Contoh:
```
Panjang: 400 M = 437.445 Y
STD: 30 M = 32.808 Y
Panjang Potong (M): 30.30 M
Panjang Potong (Y): 33.137 Y

Jika Type Counter = M:
  400 M ÷ 30.30 M = 13.2 potongan

Jika Type Counter = Y:
  437.445 Y ÷ 33.137 Y = 13.2 potongan

Hasil sama, tapi unit perhitungan berbeda!
(Perbedaan bisa signifikan untuk kain sangat panjang)
```

---

### Q7: Bagian cacat overlap dengan potongan, yang mana didahulukan?
**A:** Cacat selalu **didahulukan** (diprioritaskan). Jadi:
1. Potongan dipotong hingga bertemu cacat
2. Bagian sebelum cacat diambil (jika panjangnya >= MIN)
3. Bagian cacat ditandai sebagai rusak
4. Lanjut dari akhir cacat

Contoh:
```
Potongan normal: [30.30 - 60.60]
Cacat: [39 - 49]

Hasil:
[30.30 - 39] = 8.70 M  → A2 (< MIN)
[39 - 49] = 10 M       → CACAT
[49 - 79.30] = 30.30 M → A1 Standart (lanjut normal)
```

---

### Q8: Kenapa hasil pcs berbeda di 4 scenario UOM?
**A:** Karena perbedaan faktor konversi antar unit. Contoh 400 meter:
```
M → M:  400 M ÷ 30.30 M = 13.2 pcs
M → Y:  437.445 Y ÷ 33.137 Y = 13.2 pcs ✓ Sama
Y → M:  400 M ÷ 27.731 M = 14.4 pcs ✗ Beda!
Y → Y:  437.445 Y ÷ 30.3281 Y = 14.4 pcs ✓ Konsisten

Perbedaan terjadi karena:
- Scenario 1-2: STD dalam Meter, sama-sama ~30.30 M
- Scenario 3-4: STD dalam Yard, sama-sama ~30.33 Y = 27.73 M

Jadi semakin panjang, semakin banyak potongan untuk scenario Y!
```

---

## 🐛 Troubleshooting

### Problem 1: "Total PCS tidak sesuai ekspektasi"

**Diagnosis:**
```
Expected: 1000 ÷ 30 = 33 pcs
Actual: 32 pcs

Penyebab: Tidak account toleransi!
Panjang Potong = 30 + 0.30 = 30.30 M
1000 ÷ 30.30 = 32.9 → 32 potongan + sisa
```

**Solusi:**
✅ Selalu hitung dengan: STD + Toleransi, bukan hanya STD

---

### Problem 2: "Hasil cutting berbeda di 2 system"

**Diagnosis:**
```
System A: 14 pcs (UOM CP = Y)
System B: 13 pcs (UOM CP = M)

Penyebab: Berbeda type_counter atau uom_cp
```

**Solusi:**
✅ Pastikan UOM CP dan Type Counter sama di kedua system
✅ Cek konversi faktor: 1 Y = 0.9144 M

---

### Problem 3: "Cacat tidak terdeteksi"

**Diagnosis:**
```
Input cacat: 39-49 M
Tidak muncul di hasil

Kemungkinan penyebab:
1. Cacat belum disimpan ke database
2. Cacat range tidak overlap dengan potongan
3. Data cacat tidak ter-query dengan benar
```

**Solusi:**
1. ✅ Verifikasi cacat sudah di `cl_cutting_cacat`
2. ✅ Cek overlap: apakah ada potongan yang melewati 39-49?
   - Jika semua potongan < 39 atau > 49 → tidak ada overlap
3. ✅ Debug query: `SELECT * FROM cl_cutting_cacat WHERE id_piece = ?`

---

### Problem 4: "Total panjang tidak match dengan input"

**Diagnosis:**
```
Input: 400 M
Output: 399.98 M (selisih 0.02 M)

Penyebab: Floating point precision
```

**Solusi:**
✅ Normal! Selisih < 0.1% adalah acceptable
✅ Hanya perlu rounding ke 2 desimal saat display

---

### Problem 5: "Kategori A1 Standart tidak muncul"

**Diagnosis:**
```
Semua potongan jadi A1 Non Standart

Penyebab: Hasil cutting tidak exact = STD + Toleransi
```

**Solusi:**
✅ Logic menggunakan precision 3 desimal
✅ Harus exact match (atau sangat dekat, within 0.001)

```php
// Logic di code:
if (abs($hasil - $std_rounded) < 0.001) {
    return "A1 Standart";
}

// Jadi jika:
STD + TOL = 30.300
Hasil = 30.301 → masih A1 Standart
Hasil = 30.310 → A1 Non Standart
```

---

### Problem 6: "Helper function tidak ditemukan"

**Error:**
```
Fatal error: Call to undefined function performCutting()
```

**Solusi:**
✅ Pastikan include path benar:
```php
// Letakkan di awal process_cutting.php
require_once 'includes/cutting_helper.php';

// ATAU dengan full path
require_once __DIR__ . '/includes/cutting_helper.php';
```

✅ Cek file `includes/cutting_helper.php` ada dan tidak kosong

---

### Problem 7: "Hasil cutting tidak sesuai manual calculation"

**Debug Steps:**
1. ✅ Ambil output dari `performCutting()` - lihat `config` section
2. ✅ Verifikasi toleransi conversion benar
3. ✅ Verifikasi conversion factor benar
4. ✅ Manual hitung ulang dengan nilai dari `config`
5. ✅ Bandingkan dengan hasil `cutting` array

**Contoh Debug:**
```php
$result = performCutting($config);
echo "Config: " . json_encode($result['config'], JSON_PRETTY_PRINT);
echo "Cutting: " . json_encode($result['cutting'][0], JSON_PRETTY_PRINT);

// Verify manual:
$tol_converted = 30 * 0.01; // 0.30 M
$panjang_potong = 30 + 0.30; // 30.30 M
echo "Expected: " . $panjang_potong;
echo "Actual: " . $result['cutting'][0]['hasil_cutting'];
```

---

### Problem 8: "Performa lambat untuk data besar"

**Diagnosis:**
```
200 piece x 50 potongan/piece x 10 cacat/piece
= 100,000 loop iterations
```

**Solusi:**
✅ Optimize database query (add indexes)
✅ Batch insert ke database (jika banyak cutting)
✅ Proses per-batch, bukan sekaligus semua piece

```php
// Batch size 10 piece per transaction
for ($i = 0; $i < count($ids); $i += 10) {
    $batch = array_slice($ids, $i, 10);
    processBatch($batch);
}
```

---

## 🔍 Verification Checklist

Sebelum production, verifikasi:

- [ ] **Input Data**
  - [ ] Panjang selalu dalam Meter
  - [ ] Toleransi selalu dalam CM
  - [ ] STD/MIN/MAX dalam UOM CP
  - [ ] UOM CP & Type Counter valid (M atau Y)

- [ ] **Output Data**
  - [ ] Total panjang ≈ panjang input (tolerance ±0.1%)
  - [ ] Total PCS ≥ 1
  - [ ] Kategori valid (A1/A2/BS/CACAT)
  - [ ] Summary sum up benar

- [ ] **Cacat Handling**
  - [ ] Cacat stored dengan benar
  - [ ] Overlap detection bekerja
  - [ ] Cacat marked dengan status_cacat

- [ ] **Database**
  - [ ] `cl_cutting_process` populated
  - [ ] `cl_cutting_summary` populated
  - [ ] `cl_cutting_header.processed = 1`
  - [ ] No orphaned records

- [ ] **Edge Cases**
  - [ ] Panjang < STD (hasil 1 potongan, sisa)
  - [ ] Panjang = STD (hasil 1 potongan, exact)
  - [ ] Panjang > STD x 100 (many cuts)
  - [ ] Multiple cacat ranges
  - [ ] Cacat di awal/akhir kain

---

## 📊 Expected Behavior

### Cutting Distribution (Typical)
```
Dari 1000 M dengan STD 30:
- A1 Standart: 80-90% (ideal)
- A1 Non Standart: 0-5% (normal)
- A2: 2-5% (sisa kecil)
- BS KG: 0-2% (sangat kecil)
- CACAT: Tergantung quality kain
```

### Performance (Typical)
```
Data: 100 piece, 50 potongan/piece
Database: MS SQL Server
Time: < 5 detik per 100 piece
```

---

## 🆘 Escalation Path

Jika masalah tidak terselesaikan:

1. **Gather info:**
   - Config input (UOM CP, Type Counter, toleransi)
   - Expected vs Actual result
   - Error message (jika ada)

2. **Check:**
   - [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Lihat contoh
   - [VISUAL_EXAMPLES_TOLERANSI.md](VISUAL_EXAMPLES_TOLERANSI.md) - Lihat diagram
   - [CUTTING_CALCULATION_LOGIC.md](CUTTING_CALCULATION_LOGIC.md) - Lihat logika

3. **Debug:**
   - Run `test_cutting_calculation.php`
   - Bandingkan output dengan documented examples
   - Check database query results

4. **Contact Support:**
   - Siapkan: Config + Expected Output + Actual Output
   - Referensi file: `includes/cutting_helper.php`

---

## 📚 Documentation Map

```
QUICK_REFERENCE.md                    ← START HERE (Quick lookup)
  ↓
VISUAL_EXAMPLES_TOLERANSI.md          ← Understand with examples
  ↓
CUTTING_CALCULATION_LOGIC.md          ← Deep dive logic
  ↓
includes/cutting_helper.php           ← Source code
  ↓
process_cutting.php                   ← Integration
  ↓
test_cutting_calculation.php          ← Testing & verification
```

---

**Last Updated**: December 24, 2025  
**Version**: 1.0
