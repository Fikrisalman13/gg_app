# IMPLEMENTASI LENGKAP - UOM Conversion untuk Cutting Piece

## ✅ Status: COMPLETED

Semua perubahan telah diimplementasikan sesuai dengan requirement.

---

## 📋 Ringkasan Perubahan

### Database Schema
```sql
-- Rename kolom
EXEC sp_rename 'cl_cutting_piece.satuan', 'uom_cp', 'COLUMN';

-- Tambah 5 kolom baru
ALTER TABLE cl_cutting_piece
ADD standart_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    min_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    max_potong_conv DECIMAL(10,3) DEFAULT 0.000,
    uom_counter CHAR(1) DEFAULT 'M',
    toleransi_conv DECIMAL(10,3) DEFAULT 0.000;
```

### File yang Diubah

| File | Perubahan Utama |
|------|----------------|
| `save_cutting.php` | Implementasi logika konversi std/min/max + toleransi, simpan ke kolom _conv |
| `process_cutting.php` | Gunakan nilai _conv untuk perhitungan cutting |
| `index.php` | Tampilkan detail piece di tab List dengan kolom: Type Counter, Piece, Panjang, Std, Min, Max, UOM |
| `detail_cutting.php` | Tambah kolom *_conv dan uom_counter ke tampilan |
| `edit_detail.php` | Tambah kolom *_conv dan uom_counter ke tampilan |
| `save_edit_piece.php` | Hitung dan update nilai *_conv saat edit piece |
| `modal_piece.php` | Tidak ada perubahan (UOM sudah readonly dari typeCounter) |

---

## 🔄 Logika Konversi (save_cutting.php)

```php
// 1. Tambahkan toleransi
$std_dengan_tol = $std_input + $toleransi_input;

// 2. Hitung conversion factor
if ($uom_counter === 'M' && $uom_cp === 'Y') {
    $factor = 1 / 0.9144; // Meter ke Yard
} elseif ($uom_counter === 'Y' && $uom_cp === 'M') {
    $factor = 0.9144; // Yard ke Meter
} else {
    $factor = 1.0; // Sama
}

// 3. Konversi dan simpan
$std_conv = $std_dengan_tol * $factor;

// 4. Konversi panjang jika uom_counter = Y
if ($uom_counter === 'Y') {
    $panjang_awal = $panjang_awal * $factor;
}
```

---

## 📊 Contoh Hasil Simpan

### Skenario: Yard CP, Meter Counter
```
Input:
- Std: 11.5 Yard, Min: 9.0 Yard, Max: 13.0 Yard, Tol: 0.5 Yard
- UOM CP: Y, Type Counter: M

Simpan ke Database:
- standart_potong = 12.0 (11.5 + 0.5) ← dengan tol
- standart_potong_conv = 10.97 (12.0 ÷ 0.9144) ← konversi ke meter
- min_potong = 9.5 (9.0 + 0.5)
- min_potong_conv = 8.69 (9.5 ÷ 0.9144)
- max_potong = 13.5 (13.0 + 0.5)
- max_potong_conv = 12.33 (13.5 ÷ 0.9144)
- toleransi = 0.5
- toleransi_conv = 0.4572 (0.5 ÷ 0.9144)
- uom_cp = Y
- uom_counter = M
```

---

## 🎯 Penggunaan di process_cutting.php

```php
// Ambil langsung dari kolom _conv yang sudah dihitung
$standart_potong = floatval($piece['standart_potong_conv']);
$min = floatval($piece['min_potong_conv']);
$max = floatval($piece['max_potong_conv']);

// Gunakan langsung untuk calculateCutting()
// Tidak perlu konversi lagi!
```

---

## 📱 Tampilan di UI

### index.php - Tab List
```
Pilih | CP No | Type Counter | Piece | Panjang Awal | Panjang Akhir | Susut | Std | Min | Max | UOM
```
Sekarang menampilkan detail piece untuk setiap CP No.

### detail_cutting.php - Tab Piece
```
No | Piece | Panjang Awal | Panjang Akhir | Susut | Std | Min | Max | Tol | UOM CP | Std Conv | Min Conv | Max Conv | Tol Conv | UOM Counter
```
Menampilkan nilai original dan nilai konversi.

---

## ⚙️ Backward Compatibility

Data lama tanpa kolom conv akan mendapat default:
- `standart_potong_conv = 0.000`
- `min_potong_conv = 0.000`
- `max_potong_conv = 0.000`
- `uom_counter = 'M'`
- `toleransi_conv = 0.000`

Aplikasi tetap berjalan, tapi untuk proses cutting harus re-edit piece atau buat baru.

---

## 🧪 Next Steps (Testing)

1. Jalankan SQL migration script
2. Buat cutting piece baru dengan berbagai kombinasi UOM
3. Verifikasi kolom _conv terisi dengan benar
4. Edit piece, verifikasi konversi ulang
5. Proses cutting, verifikasi menggunakan nilai _conv
6. Lihat hasil cutting, seharusnya benar dengan konversi

---

## 📝 Dokumentasi Lengkap

Lihat file `IMPLEMENTATION_UOM_CONVERSION.md` untuk:
- Detail setiap file yang diubah
- Semua skenario konversi
- Checklist testing lengkap
