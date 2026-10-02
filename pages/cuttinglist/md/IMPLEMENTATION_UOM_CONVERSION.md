# Implementasi Perubahan Schema dan Logika Konversi UOM - Cutting Piece

## Ringkasan Perubahan

Implementasi ini mengubah sistem perhitungan untuk tabel `cl_cutting_piece` dengan:

1. **Rename kolom** `satuan` → `uom_cp` 
2. **Tambah kolom baru** untuk konversi standar/min/max/toleransi
3. **Implementasi logika konversi** dari uom_counter ke uom_cp
4. **Update proses cutting** untuk menggunakan nilai konversi

---

## Database Schema Changes

### SQL Migration Script

Jalankan script berikut di SQL Server:

```sql
-- Step 1: Rename kolom satuan menjadi uom_cp
EXEC sp_rename 'cl_cutting_piece.satuan', 'uom_cp', 'COLUMN';

-- Step 2: Tambahkan kolom baru SEBELUM created_by
ALTER TABLE cl_cutting_piece
ADD standart_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    min_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    max_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    uom_counter CHAR(1) DEFAULT 'M',
    toleransi_conv DECIMAL(10,3) DEFAULT 0.000;
```

### Struktur Tabel Final

```
cl_cutting_piece:
- id_piece
- id_header
- piece_no
- piece_code
- panjang_awal (dikonversi jika uom_counter = Y)
- panjang_akhir (dikonversi jika uom_counter = Y)
- susut
- lebar_kain
- standart_potong (std + toleransi dalam uom_cp)
- min_potong (min + toleransi dalam uom_cp)
- max_potong (max + toleransi dalam uom_cp)
- uom_cp (M atau Y) - RENAMED dari satuan
- toleransi (nilai toleransi asli)
- standart_potong_conv (konversi dari uom_counter ke uom_cp)
- min_potong_conv (konversi dari uom_counter ke uom_cp)
- max_potong_conv (konversi dari uom_counter ke uom_cp)
- toleransi_conv (toleransi konversi)
- uom_counter (dari Type Counter header)
- created_by
- created_date
- updated_by
- updated_date
```

---

## Logika Konversi

### Input User di Modal Piece

User input nilai standar, min, max, toleransi dalam satuan yang dipilih di `uom_cp`.

### Proses di save_cutting.php

1. **Ambil nilai input**: std, min, max, toleransi
2. **Tambahkan toleransi ke nilai**:
   - `std_dengan_tol = std + toleransi`
   - `min_dengan_tol = min + toleransi`
   - `max_dengan_tol = max + toleransi`
3. **Hitung conversion factor**:
   - Jika `uom_counter = M` dan `uom_cp = Y`: factor = 1/0.9144 (konversi Meter ke Yard)
   - Jika `uom_counter = Y` dan `uom_cp = M`: factor = 0.9144 (konversi Yard ke Meter)
   - Jika sama: factor = 1.0 (tidak ada konversi)
4. **Hitung nilai konversi**:
   - `std_conv = std_dengan_tol * factor`
   - `min_conv = min_dengan_tol * factor`
   - `max_conv = max_dengan_tol * factor`
   - `toleransi_conv = toleransi * factor`
5. **Konversi panjang jika uom_counter = Y**:
   - `panjang_awal = panjang_awal * factor`
   - `panjang_akhir = panjang_akhir * factor`
6. **Simpan ke database**:
   - Kolom standar/min/max: nilai dengan toleransi (dalam uom_cp)
   - Kolom *_conv: nilai konversi dari uom_counter ke uom_cp
   - `uom_counter`: Type Counter dari header

---

## File-File yang Diubah

### 1. save_cutting.php
**Perubahan**: 
- Hapus logic konversi di awal (yang lama)
- Implementasi logic konversi baru dengan perhitungan toleransi
- Tambah 5 kolom baru ke INSERT statement
- Konversi panjang_awal dan panjang_akhir jika uom_counter = Y

**Kolom yang disimpan**:
- `standart_potong`, `min_potong`, `max_potong`: nilai + toleransi
- `standart_potong_conv`, `min_potong_conv`, `max_potong_conv`: nilai konversi
- `toleransi_conv`: toleransi konversi
- `uom_counter`: type_counter dari header
- `uom_cp`: uom_cp dari header (renamed dari satuan)

### 2. index.php
**Perubahan**:
- Ubah query SQL untuk JOIN dengan cl_cutting_piece
- Tampilkan detail piece di tab List
- Menambahkan kolom: Type Counter, Piece Code, Panjang Awal, Panjang Akhir, Susut, Std, Min, Max, UOM

**Tampilan**:
```
Pilih | CP No | Type Counter | Piece | Panjang Awal | Panjang Akhir | Susut | Std | Min | Max | UOM | Created Date | Created By
```

### 3. detail_cutting.php
**Perubahan**:
- Tambah kolom baru ke tampilan piece table
- Menampilkan: Std Conv, Min Conv, Max Conv, Tol Conv, UOM Counter

**Tampilan piece**:
```
No | Piece | Panjang Awal | Panjang Akhir | Susut | Std | Min | Max | Tol | UOM CP | Std Conv | Min Conv | Max Conv | Tol Conv | UOM Counter
```

### 4. edit_detail.php
**Perubahan**:
- Update query untuk ambil kolom baru
- Tambah kolom baru ke tampilan piece table (sama dengan detail_cutting.php)
- Menampilkan semua kolom conv dan uom_counter untuk referensi

### 5. process_cutting.php
**Perubahan**:
- Hapus logic konversi di proses (yang lama)
- Ambil nilai dari kolom *_conv yang sudah dihitung
- Query piece dengan kolom: standart_potong_conv, min_potong_conv, max_potong_conv

**Logika**:
```php
// Sebelum (lama):
$standart_potong = floatval($piece['standart_potong']) + $toleransi;
if ($header['uom_cp'] === 'Y' && $header['type_counter'] === 'M') {
    $standart_potong = $standart_potong * 0.9144;
}

// Sesudah (baru):
$standart_potong = floatval($piece['standart_potong_conv']);
// Sudah siap pakai, tidak perlu konversi lagi
```

### 6. save_edit_piece.php
**Perubahan**:
- Ambil info header untuk uom_cp dan uom_counter
- Hitung toleransi dan nilai konversi (sama dengan save_cutting.php)
- Update 5 kolom baru ke database

---

## Contoh Skenario

### Skenario 1: Meter → Meter (Sama)
```
User Input:
- UOM CP: Meter
- Type Counter: Meter
- Std: 10.5 M, Min: 8.0 M, Max: 12.0 M
- Toleransi: 0.5 M

Hasil di Database:
- standart_potong = 11.0 (10.5 + 0.5)
- min_potong = 8.5 (8.0 + 0.5)
- max_potong = 12.5 (12.0 + 0.5)
- toleransi = 0.5
- standart_potong_conv = 11.0 (11.0 * 1)
- min_potong_conv = 8.5 (8.5 * 1)
- max_potong_conv = 12.5 (12.5 * 1)
- toleransi_conv = 0.5
- uom_counter = M
- uom_cp = M
```

### Skenario 2: Yard → Meter (Konversi)
```
User Input:
- UOM CP: Yard
- Type Counter: Meter
- Std: 11.5 Yard, Min: 9.0 Yard, Max: 13.0 Yard
- Toleransi: 0.5 Yard
- Panjang Awal: 30.3 Yard, Panjang Akhir: 27.71 Yard

Konversi: 1 Yard = 0.9144 Meter, factor = 1/0.9144 = 1.0936

Hasil di Database:
- standart_potong = 12.0 (11.5 + 0.5)
- min_potong = 9.5 (9.0 + 0.5)
- max_potong = 13.5 (13.0 + 0.5)
- toleransi = 0.5
- standart_potong_conv = 10.97 (12.0 / 0.9144)
- min_potong_conv = 8.69 (9.5 / 0.9144)
- max_potong_conv = 12.33 (13.5 / 0.9144)
- toleransi_conv = 0.4572 (0.5 / 0.9144)
- uom_counter = M
- uom_cp = Y
- panjang_awal = 27.71 (30.3 * 0.9144)
- panjang_akhir = 25.32 (27.71 * 0.9144)
```

### Skenario 3: Meter → Yard (Konversi Sebaliknya)
```
User Input:
- UOM CP: Meter
- Type Counter: Yard
- Std: 10.5 M, Min: 8.0 M, Max: 12.0 M
- Toleransi: 0.5 M
- Panjang Awal: 30 M, Panjang Akhir: 27.5 M

Konversi: factor = 0.9144

Hasil di Database:
- standart_potong = 11.0 (10.5 + 0.5)
- min_potong = 8.5 (8.0 + 0.5)
- max_potong = 12.5 (12.0 + 0.5)
- toleransi = 0.5
- standart_potong_conv = 10.06 (11.0 * 0.9144)
- min_potong_conv = 7.77 (8.5 * 0.9144)
- max_potong_conv = 11.43 (12.5 * 0.9144)
- toleransi_conv = 0.4572 (0.5 * 0.9144)
- uom_counter = Y
- uom_cp = M
- panjang_awal = 27.43 (30 * 0.9144)
- panjang_akhir = 25.15 (27.5 * 0.9144)
```

---

## Testing Checklist

- [ ] Database migration script berjalan tanpa error
- [ ] Kolom `uom_cp` ada (renamed dari satuan)
- [ ] 5 kolom conv baru ada di tabel
- [ ] Tambah piece → standart_potong_conv, min_potong_conv, max_potong_conv terisi dengan benar
- [ ] Toleransi ditambahkan ke std/min/max
- [ ] Konversi meter↔yard bekerja dengan benar
- [ ] index.php tab List menampilkan detail piece
- [ ] detail_cutting.php menampilkan semua kolom conv
- [ ] edit_detail.php menampilkan semua kolom conv
- [ ] edit piece → nilai conv dihitung ulang
- [ ] process_cutting.php menggunakan kolom _conv
- [ ] Hasil cutting benar menggunakan nilai _conv
- [ ] Data lama yang tidak punya kolom conv, aplikasi tetap berjalan (fallback ke default)

---

## Backward Compatibility

Untuk data lama yang belum punya kolom conv:

- `standart_potong_conv` akan default 0.000 (bisa UPDATE)
- `min_potong_conv` akan default 0.000 (bisa UPDATE)
- `max_potong_conv` akan default 0.000 (bisa UPDATE)
- `uom_counter` akan default 'M'
- `toleransi_conv` akan default 0.000

Aplikasi akan tetap berjalan dengan fallback, tapi untuk proses cutting harus re-edit piece atau buat cutting baru.

---

## Catatan Penting

1. **Toleransi**: Sudah diproses di save_cutting.php dan save_edit_piece.php, tidak perlu di-add lagi di process_cutting.php
2. **Konversi**: Menggunakan standar industri 1 Yard = 0.9144 Meter (exactamente)
3. **UOM Counter**: Diambil dari `type_counter` di header
4. **Panjang Awal/Akhir**: Dikonversi JIKA `uom_counter = Y`, untuk hasil yang konsisten
5. **Perhitungan Cutting**: Sekarang murni menggunakan nilai *_conv yang sudah siap pakai
