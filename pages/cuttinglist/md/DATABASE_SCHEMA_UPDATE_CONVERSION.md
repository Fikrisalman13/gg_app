# Database Schema Update - cl_cutting_process Table

## Tambahkan kolom untuk konversi hasil cutting dan UOM

Jalankan SQL query berikut untuk menambahkan kolom baru:

```sql
ALTER TABLE cl_cutting_process
ADD uom_hasil_cutting CHAR(1) DEFAULT 'M',
    hasil_cutting_conv DECIMAL(10,3) DEFAULT 0.000,
    uom_conversi CHAR(1) DEFAULT 'M';
```

## Penjelasan Kolom Baru:

- **uom_hasil_cutting**: UOM dari hasil_cutting awal (berisi Type Counter: M atau Y)
  - M = Meter (perhitungan dilakukan dalam meter)
  - Y = Yard (jika di masa depan mendukung yard calculation)

- **hasil_cutting_conv**: Nilai hasil_cutting yang sudah dikonversi ke UOM CP
  - Digunakan untuk ditampilkan di UI
  - Jika UOM CP = Yard dan Type Counter = Meter: hasil_cutting / 0.9144
  - Jika UOM CP = Meter: hasil_cutting (sama dengan original)

- **uom_conversi**: UOM dari hasil_cutting_conv (berisi UOM CP: M atau Y)
  - M = Meter
  - Y = Yard

## Contoh Data:

Jika UOM CP = Yard dan Type Counter = Meter:
- hasil_cutting = 27.71 (dalam meter, dari perhitungan 30.3 * 0.9144)
- hasil_cutting_conv = 30.3 (konversi ke yard untuk tampilan)
- uom_conversi = Y

Jika UOM CP = Meter:
- hasil_cutting = 27.71
- hasil_cutting_conv = 27.71 (sama karena tidak perlu konversi)
- uom_conversi = M

## Update Data yang Sudah Ada:

Jika sudah ada data di cl_cutting_process, jalankan:

```sql
UPDATE cl_cutting_process
SET uom_hasil_cutting = 'M',
    hasil_cutting_conv = hasil_cutting,
    uom_conversi = 'M'
WHERE uom_hasil_cutting IS NULL;
```

## Verifikasi:

```sql
SELECT TOP 10 
    id_process, id_piece, hasil_cutting, uom_hasil_cutting, 
    hasil_cutting_conv, uom_conversi
FROM cl_cutting_process
ORDER BY created_date DESC;
```
