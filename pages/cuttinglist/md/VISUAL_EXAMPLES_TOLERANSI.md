# Visual Example - Cutting List Calculation dengan Toleransi

## 📊 SCENARIO 1: UOM CP = M, Type Counter = M (Meter ke Meter)

**Konfigurasi:**
- Panjang: 400 M (awal) - 400 M (akhir)
- STD: 30 M
- MIN: 10 M
- MAX: 40 M
- Toleransi: 30 CM = 0.30 M
- Cacat: 39 - 49 M

**Kalkulasi:**

```
STEP 1: Konversi Toleransi
Toleransi(M) = 30 CM × 0.01 = 0.30 M

STEP 2: Panjang Potongan dengan Toleransi
Panjang Potong = 30 + 0.30 = 30.30 M

STEP 3: Unit Kalkulasi
Type Counter = M → Hitung dalam Meter (Tidak perlu konversi)

STEP 4: Cutting Loop
```

**Visualisasi Cutting:**

```
Panjang Kain: 0 ─────────────────────────────────────────────────────── 400 M

Potongan 1:   [0 ─────────────── 30.30] → 30.30 M = A1 Standart
Potongan 2:   [30.30 ─────────────── 60.60] → 30.30 M = A1 Standart
Potongan 3:   [60.60 ─────────────── 90.90] → 30.30 M = A1 Standart
...
Potongan 10:  [273 ─────────────── 303.30] → 30.30 M = A1 Standart
Potongan 11:  [303.30 ─────────────── 333.60] → 30.30 M = A1 Standart
Potongan 12:  [333.60 ─────────────── 363.90] → 30.30 M = A1 Standart

                    CACAT!
                    ↓
Potongan 13:  [363.90 ─── 39] Sebelum cacat = 8.70 M = A2 (< MIN 10)
              [39 ─────── 49] CACAT = 10 M = CACAT KELIM
              [49 ─────────────── 79.30] Lanjut = 30.30 M = A1 Standart
```

**Hasil:**
- Total PCS: 13 potongan
- A1 Standart: 12 pcs (30.30 M each)
- A2: 1 pcs (8.70 M)
- CACAT: 1 pcs (10 M)

---

## 📊 SCENARIO 2: UOM CP = M, Type Counter = Y (Meter input, Yard calculation)

**Konfigurasi:**
- Panjang: 400 M (input, ALWAYS M) 
- STD: 30 M
- Toleransi: 30 CM
- Type Counter: Y (Counter/Mesin dalam Yard)
- UOM CP: M (Potongan dalam Meter)

**Kalkulasi:**

```
STEP 1: Konversi Toleransi
Toleransi(M) = 30 CM × 0.01 = 0.30 M

STEP 2: Panjang Potongan dengan Toleransi
Panjang Potong = 30 + 0.30 = 30.30 M

STEP 3: Unit Kalkulasi
Type Counter = Y, UOM CP = M
Conversion Factor = 1 / 0.9144 ≈ 1.09361

Konversi ke Yard:
- Panjang Awal: 400 M × (1/0.9144) = 437.445 Y
- Panjang Potong: 30.30 M × (1/0.9144) = 33.1365 Y

STEP 4: Cutting Loop dalam YARD
```

**Visualisasi Cutting (dalam Yard):**

```
Panjang Kain (Y): 0 ───────────────────────────────────── 437.445 Y

Potongan 1:   [0 ─────── 33.1365] = 33.1365 Y → A1 Standart
Potongan 2:   [33.1365 ─────── 66.273] = 33.1365 Y → A1 Standart
...
Potongan 13:  [403 ─────── 436.1365] = 33.1365 Y → A1 Standart
Potongan 14:  [436.1365 ─────── 437.445] = 1.3085 Y → BS KG (< 3 cm)
```

**Hasil:**
- Total PCS: 14 potongan
- A1 Standart: 13 pcs
- BS KG: 1 pcs (sisa kecil)

---

## 📊 SCENARIO 3: UOM CP = Y, Type Counter = M (Yard input, Meter calculation)

**Konfigurasi:**
- Panjang: 400 M (input, ALWAYS M)
- STD: 30 Y (Potongan dalam Yard!)
- MIN: 10 Y
- Toleransi: 30 CM
- Type Counter: M (Counter/Mesin dalam Meter)
- UOM CP: Y (Potongan dalam Yard)

**Kalkulasi:**

```
STEP 1: Konversi Toleransi
Toleransi(Y) = 30 CM × 0.010936 ≈ 0.3281 Y

STEP 2: Panjang Potongan dengan Toleransi
Panjang Potong = 30 + 0.3281 = 30.3281 Y

STEP 3: Unit Kalkulasi
Type Counter = M, UOM CP = Y
Conversion Factor = 0.9144

Konversi ke Meter:
- Panjang Potong: 30.3281 Y × 0.9144 = 27.731 M

STEP 4: Cutting Loop dalam METER
```

**Visualisasi Cutting (dalam Meter, tapi dihitung dari Yard):**

```
Panjang Kain (M): 0 ─────────────────────────────────────── 400 M

Potongan 1:   [0 ─────── 27.731] = 27.731 M → A1 Standart (dalam terms of Y)
Potongan 2:   [27.731 ─────── 55.462] = 27.731 M → A1 Standart
...
Potongan 14:  [344.234 ─────── 371.965] = 27.731 M → A1 Standart
Potongan 15:  [371.965 ─────── 400] = 28.035 M → A1 Non Standart (sedikit > STD)
```

**Hasil:**
- Total PCS: 15 potongan
- A1 Standart: 14 pcs (27.731 M each)
- A1 Non Standart: 1 pcs (28.035 M)

---

## 📊 SCENARIO 4: UOM CP = Y, Type Counter = Y (Yard ke Yard)

**Konfigurasi:**
- Panjang: 400 M (input, ALWAYS M, tapi display 437.445 Y)
- STD: 30 Y
- Type Counter: Y
- UOM CP: Y

**Kalkulasi:**

```
STEP 1: Konversi Toleransi
Toleransi(Y) = 30 CM × 0.010936 = 0.3281 Y

STEP 2: Panjang Potongan dengan Toleransi
Panjang Potong = 30 + 0.3281 = 30.3281 Y

STEP 3: Unit Kalkulasi
Type Counter = Y, UOM CP = Y
Conversion Factor = 1.0 (No conversion needed)

Konversi:
- Panjang Awal: 400 M × (1/0.9144) = 437.445 Y

STEP 4: Cutting Loop dalam YARD
```

**Visualisasi Cutting (dalam Yard):**

```
Panjang Kain (Y): 0 ───────────────────────────────────── 437.445 Y

Potongan 1:   [0 ─────── 30.3281] = 30.3281 Y → A1 Standart
Potongan 2:   [30.3281 ─────── 60.6562] = 30.3281 Y → A1 Standart
...
Potongan 14:  [394.265 ─────── 424.593] = 30.3281 Y → A1 Standart
Potongan 15:  [424.593 ─────── 437.445] = 12.852 Y → A1 Non Standart
```

**Hasil:**
- Total PCS: 15 potongan
- A1 Standart: 14 pcs
- A1 Non Standart: 1 pcs (sisa)

---

## 🎯 Perbedaan Utama 4 Scenario

| Scenario | UOM CP | Type Counter | Unit Hitung | Conversion | Total PCS |
|----------|--------|--------------|-------------|-----------|-----------|
| 1 | M | M | Meter | Tidak ada | 13 |
| 2 | M | Y | Yard | M → Y | 14 |
| 3 | Y | M | Meter | Y → M | 15 |
| 4 | Y | Y | Yard | Tidak ada | 15 |

---

## 📝 Penjelasan Perbedaan Total PCS

**Scenario 1 (M→M)**: 13 pcs
- Potongan: 30.30 M
- Hitung: 400 ÷ 30.30 = 13.20 → 13 potongan utuh

**Scenario 2 (M→Y)**: 14 pcs
- Panjang dalam Y: 437.445 Y
- Potongan: 33.1365 Y
- Hitung: 437.445 ÷ 33.1365 = 13.20 → 13 potongan + 1 sisa kecil

**Scenario 3 (Y→M)**: 15 pcs
- Panjang: 400 M
- Potongan: 27.731 M
- Hitung: 400 ÷ 27.731 = 14.42 → 14 potongan + 1 sisa

**Scenario 4 (Y→Y)**: 15 pcs
- Panjang dalam Y: 437.445 Y
- Potongan: 30.3281 Y
- Hitung: 437.445 ÷ 30.3281 = 14.42 → 14 potongan + 1 sisa

---

## 🔑 Hal Penting

1. **Panjang input SELALU Meter** → Harus dikonversi jika Type Counter = Y
2. **Toleransi dalam CM** → Konversi ke unit sebelum ditambah STD
3. **Unit kalkulasi = Type Counter** → Bukan UOM CP!
4. **Cacat adalah range** → Dipotong dari potongan yang overlap
5. **Total PCS bisa berbeda** → Tergantung unit hitung (M vs Y) karena faktor konversi

---

**Last Updated**: December 24, 2025  
**Version**: 1.0
