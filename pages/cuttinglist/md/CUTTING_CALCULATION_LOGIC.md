# Cutting List Calculation Logic dengan Toleransi

## 📌 Overview

Sistem cutting list menghitung berapa jumlah potongan (pcs) dari panjang kain total dengan mempertimbangkan:
- **Panjang awal & akhir**: SELALU dalam satuan METER (input user)
- **STD (Standard Potong)**: Panjang ideal per potongan
- **Toleransi**: Penambahan ke STD (dalam satuan CM)
- **MAX & MIN**: Batasan panjang potongan
- **UOM CP**: Unit of Measure untuk potongan (M atau Y)
- **Type Counter**: Satuan mesin/counter (M atau Y)
- **Data Cacat**: Range panjang kain yang rusak

---

## 🔢 Rumus Dasar Perhitungan

### Step 1: Konversi Toleransi
```
Toleransi (CM) → Konversi ke UOM CP
- Jika UOM CP = M: Toleransi(M) = Toleransi(CM) × 0.01
- Jika UOM CP = Y: Toleransi(Y) = Toleransi(CM) × (0.01 / 0.9144) = Toleransi(CM) × 0.010936
```

### Step 2: Hitung Panjang Potongan dengan Toleransi
```
Panjang Potongan = STD + Toleransi (dalam satuan UOM CP)

Contoh:
- STD = 30 M, Toleransi = 30 CM = 0.30 M
- Panjang Potongan = 30 + 0.30 = 30.30 M
```

### Step 3: Tentukan Unit Kalkulasi Berdasarkan Type Counter
```
Jika Type Counter = M:
  → Gunakan panjang_awal & panjang_akhir (dalam Meter)
  → Hitung potongan dalam Meter

Jika Type Counter = Y:
  → Konversi panjang_awal & panjang_akhir ke Yard
  → Konversi STD, MIN, MAX ke Yard
  → Hitung potongan dalam Yard
```

### Step 4: Kalkulasi Cutting (Sequential)
```
LOOP dari current_position = 0 sampai panjang_akhir:

  1. Tentukan end_cutting = min(current_position + Panjang_Potongan, panjang_akhir)
  
  2. CEK CACAT:
     - Jika ada range cacat dalam [current_position, end_cutting]
       → Potong sebelum cacat
       → Catat cacat sebagai rusak
       → Lanjut dari akhir cacat
     - Jika tidak ada cacat
       → Catat sebagai valid cutting
  
  3. Tentukan kategori:
     - Jika hasil_cutting = STD + Toleransi → A1 Standart
     - Jika hasil_cutting antara MIN dan (STD + Toleransi) → A1 Non Standart
     - Jika hasil_cutting < MIN dan >= 3 cm → A2
     - Jika hasil_cutting < 3 cm → BS KG (Bagus Kecil)
     - Jika ada cacat → status_cacat (dari data)
  
  4. current_position = end_cutting
```

---

## 📊 Contoh Kalkulasi Lengkap

### **Scenario 1: UOM CP = M, Type Counter = M**

**Input:**
- Panjang awal: 400 M
- Panjang akhir: 400 M
- STD: 30 M
- MIN: 10 M
- MAX: 40 M
- Toleransi: 30 CM = 0.30 M
- Cacat: (39 - 49 M)

**Kalkulasi:**
```
Panjang Potongan = 30 + 0.30 = 30.30 M

Cutting Loop:
0 - 30.30    → Cek cacat [0-30.30] vs (39-49) = TIDAK OVERLAP → Valid, 30.30 M
30.30 - 60.60 → Cek cacat [30.30-60.60] vs (39-49) = OVERLAP
              → Potong 30.30 - 39 = 8.70 M (< MIN, A2)
              → Cacat 39 - 49 = 10 M (DEFECT)
              → Lanjut dari 49

49 - 79.30   → Cek cacat [49-79.30] vs (39-49) = TIDAK OVERLAP → Valid, 30.30 M
79.30 - 109.60 → Valid, 30.30 M
...
370 - 400   → Valid, 30 M (sisa < STD)

TOTAL PCS:
- A1 Standart: 12 pcs
- A2: 1 pcs (8.70 M)
- DEFECT: 1 pcs (10 M, range 39-49)
```

---

### **Scenario 2: UOM CP = M, Type Counter = Y**

**Input:**
- Panjang awal: 400 M (input selalu M)
- Type Counter: Y (mesin dalam Yard)
- STD: 30 M
- Toleransi: 30 CM = 0.30 M

**Kalkulasi:**
```
Konversi ke Yard:
- panjang_awal_conv = 400 M × (1/0.9144) = 437.445 Y
- panjang_akhir_conv = 400 M × (1/0.9144) = 437.445 Y
- STD_conv = 30 M × (1/0.9144) = 32.8084 Y
- Panjang Potongan = 32.8084 + (0.30 × 1/0.9144) = 32.8084 + 0.3281 = 33.1365 Y

Cutting Loop dalam Yard:
0 - 33.1365 Y → Valid, 33.1365 Y
33.1365 - 66.273 Y → Valid, 33.1365 Y
... sampai 437.445 Y

TOTAL PCS: ~13 pcs (dalam Yard)
```

---

### **Scenario 3: UOM CP = Y, Type Counter = M**

**Input:**
- Panjang awal: 400 M (input selalu M, tapi UOM CP = Y)
- Type Counter: M (mesin dalam Meter)
- STD: 30 Y
- Toleransi: 30 CM = 0.010936 Y

**Kalkulasi:**
```
Panjang Potongan (dalam Yard):
= 30 Y + 0.010936 Y = 30.010936 Y

Konversi ke Meter untuk cutting:
= 30.010936 Y × 0.9144 = 27.432 M (untuk digunakan di mesin)

Cutting Loop dalam Meter:
0 - 27.432 M → Valid, 27.432 M
27.432 - 54.864 M → Valid, 27.432 M
... sampai 400 M

TOTAL PCS: ~14-15 pcs (dalam Meter, tapi dihitung dari Yard)
```

---

## 📋 Tabel Referensi Konversi

| Konversi | Faktor |
|----------|--------|
| 1 CM → M | × 0.01 |
| 1 CM → Y | × 0.010936 |
| 1 M → Y | × (1/0.9144) = 1.09361 |
| 1 Y → M | × 0.9144 |

---

## 🔑 Poin Penting

1. **Panjang input SELALU dalam Meter**
   - User selalu input dalam M, tidak peduli UOM CP atau Type Counter
   - Konversi hanya untuk kalkulasi internal

2. **STD + Toleransi = Panjang Potongan**
   - Toleransi HARUS ditambahkan ke STD
   - Toleransi dalam CM, konversi ke UOM CP terlebih dahulu

3. **Unit Kalkulasi tergantung Type Counter**
   - Type Counter M → Hitung dalam Meter
   - Type Counter Y → Hitung dalam Yard (konversi sebelumnya)

4. **Cacat dijital sebagai range [dari - sampai]**
   - Range cacat tidak boleh diubah
   - Jika potongan overlap dengan cacat, split menjadi bagian valid dan cacat

5. **Kategori potongan berdasarkan:**
   - `hasil_cutting == (STD + Toleransi)` → A1 Standart
   - `hasil_cutting < (STD + Toleransi)` dan `>= MIN` → A1 Non Standart
   - `hasil_cutting < MIN` dan `>= 3 cm` → A2
   - `hasil_cutting < 3 cm` → BS KG

---

**Last Updated**: December 24, 2025  
**Version**: 1.0
