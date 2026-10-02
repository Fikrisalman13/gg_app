# Database Schema Update - cl_cutting_piece Table
## Kolom untuk UOM dan Konversi Standar/Min/Max/Toleransi

### SQL Migration Script

Jalankan script berikut untuk menambahkan kolom baru ke tabel `cl_cutting_piece`:

```sql
-- Step 1: Rename kolom satuan menjadi uom_cp
-- CATATAN: Tergantung SQL Server version, mungkin perlu SP_RENAME
EXEC sp_rename 'cl_cutting_piece.satuan', 'uom_cp', 'COLUMN';

-- Step 2: Tambahkan kolom baru SEBELUM created_by
ALTER TABLE cl_cutting_piece
ADD standart_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    min_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    max_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    uom_counter CHAR(1) DEFAULT 'M',
    toleransi_conv DECIMAL(10,3) DEFAULT 0.000;
```

### Penjelasan Kolom

**Kolom yang ada (diubah/diperluas)**:
- `standart_potong`: Nilai standar dengan toleransi yang sudah ditambahkan (dalam uom_cp)
- `min_potong`: Nilai minimum dengan toleransi yang sudah ditambahkan (dalam uom_cp)
- `max_potong`: Nilai maximum dengan toleransi yang sudah ditambahkan (dalam uom_cp)
- `uom_cp`: Satuan/UOM dari Cutting Piece (M=Meter, Y=Yard)
- `toleransi`: Toleransi dalam satuan asli/input

**Kolom baru yang ditambahkan**:
- `standart_potong_conv`: Standar setelah ditambah toleransi dan dikonversi ke uom_cp (dari uom_counter)
- `min_potong_conv`: Minimum setelah ditambah toleransi dan dikonversi ke uom_cp (dari uom_counter)
- `max_potong_conv`: Maximum setelah ditambah toleransi dan dikonversi ke uom_cp (dari uom_counter)
- `uom_counter`: Type Counter yang digunakan (M=Meter, Y=Yard) - dari header
- `toleransi_conv`: Toleransi setelah dikonversi sesuai uom_counter

### Logika Konversi

**Input dari User:**
- Standar, Min, Max, Toleransi dalam satuan yang dipilih dari UOM CP
- Contoh: User pilih UOM CP = Yard, input semua nilai dalam Yard

**Proses di Tambahuutting/Save:**

1. Ambil nilai standar, min, max, toleransi dari input
2. Tambahkan toleransi ke standar, min, max
   - Contoh: std=10.5, tol=0.5 → standart_potong=11.0
3. Konversi dari uom_counter (Type Counter) ke uom_cp:
   - Jika uom_counter = M (Meter) dan uom_cp = Y (Yard): bagi dengan 0.9144
   - Jika uom_counter = Y (Yard) dan uom_cp = M (Meter): kalikan dengan 0.9144
   - Jika sama: kalikan dengan 1 (tidak ada konversi)
4. Hasil konversi simpan di kolom _conv
5. Juga konversi toleransi dengan logika yang sama

**Kolom yang disimpan:**
- `standart_potong`: std + toleransi (dalam uom_cp)
- `min_potong`: min + toleransi (dalam uom_cp)
- `max_potong`: max + toleransi (dalam uom_cp)
- `toleransi`: toleransi asli dalam satuan input
- `standart_potong_conv`: (std + toleransi) dikonversi dari uom_counter ke uom_cp
- `min_potong_conv`: (min + toleransi) dikonversi dari uom_counter ke uom_cp
- `max_potong_conv`: (max + toleransi) dikonversi dari uom_counter ke uom_cp
- `toleransi_conv`: toleransi dikonversi sesuai uom_counter
- `uom_counter`: Type Counter dari header

### Contoh Data

**Skenario 1: UOM CP = Meter, Type Counter = Meter (sama)**
```
Input:
- standart_potong = 10.5 M
- min_potong = 8.0 M
- max_potong = 12.0 M
- toleransi = 0.5 M
- uom_cp = M
- type_counter = M

Hasil di database:
- standart_potong = 11.0 (10.5 + 0.5)
- min_potong = 8.5 (8.0 + 0.5)
- max_potong = 12.5 (12.0 + 0.5)
- toleransi = 0.5
- standart_potong_conv = 11.0 (11.0 * 1 = 11.0)
- min_potong_conv = 8.5 (8.5 * 1 = 8.5)
- max_potong_conv = 12.5 (12.5 * 1 = 12.5)
- toleransi_conv = 0.5
- uom_counter = M
```

**Skenario 2: UOM CP = Yard, Type Counter = Meter**
```
Input:
- standart_potong = 11.5 Yard
- min_potong = 9.0 Yard
- max_potong = 13.0 Yard
- toleransi = 0.5 Yard
- uom_cp = Y
- type_counter = M

Hasil di database:
- standart_potong = 12.0 (11.5 + 0.5)
- min_potong = 9.5 (9.0 + 0.5)
- max_potong = 13.5 (13.0 + 0.5)
- toleransi = 0.5
- standart_potong_conv = 10.97 (12.0 / 0.9144)
- min_potong_conv = 8.69 (9.5 / 0.9144)
- max_potong_conv = 12.33 (13.5 / 0.9144)
- toleransi_conv = 0.4572 (0.5 / 0.9144)
- uom_counter = M
```

### Untuk Panjang Awal dan Panjang Akhir

Ketika uom_counter = Yard, panjang_awal dan panjang_akhir juga dikonversi:
- Jika nilai dalam satuan lain, konversi sesuai type_counter
- Hasil tetap disimpan di kolom panjang_awal dan panjang_akhir yang sama

### Verifikasi Schema

```sql
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'cl_cutting_piece'
ORDER BY ORDINAL_POSITION;
```

Expected columns:
```
id_piece
id_header
piece_no
piece_code
panjang_awal
panjang_akhir
susut
lebar_kain
standart_potong
min_potong
max_potong
uom_cp (renamed from satuan)
toleransi
standart_potong_conv (NEW)
min_potong_conv (NEW)
max_potong_conv (NEW)
uom_counter (NEW)
toleransi_conv (NEW)
created_by
created_date
updated_by
updated_date
```
