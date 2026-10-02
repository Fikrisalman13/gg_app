# Database Schema Update - Toleransi Cutting

## SQL Script untuk Tambah Kolom Toleransi

Jalankan script berikut di SQL Server untuk menambahkan kolom `toleransi` ke tabel `cl_cutting_piece`:

```sql
-- Tambah kolom toleransi ke cl_cutting_piece
ALTER TABLE cl_cutting_piece
ADD toleransi DECIMAL(10,3) DEFAULT 0.000;

-- Atau jika sudah ada kolom, update dengan default value
UPDATE cl_cutting_piece 
SET toleransi = 0.000 
WHERE toleransi IS NULL;
```

## Verifikasi Struktur Tabel

Pastikan tabel `cl_cutting_piece` memiliki struktur berikut:

```sql
-- Struktur cl_cutting_piece dengan kolom toleransi
CREATE TABLE cl_cutting_piece (
    id_piece INT PRIMARY KEY IDENTITY(1,1),
    id_header INT NOT NULL,
    piece_no INT,
    piece_code NVARCHAR(50),
    panjang_awal DECIMAL(10,3),
    panjang_akhir DECIMAL(10,3),
    susut DECIMAL(10,3),
    lebar_kain DECIMAL(10,3),
    standart_potong DECIMAL(10,3),
    min_potong DECIMAL(10,3),
    max_potong DECIMAL(10,3),
    satuan CHAR(1),
    toleransi DECIMAL(10,3) DEFAULT 0.000,  -- <-- KOLOM BARU
    created_by NVARCHAR(50),
    created_date DATETIME DEFAULT GETDATE(),
    updated_by NVARCHAR(50),
    updated_date DATETIME,
    FOREIGN KEY (id_header) REFERENCES cl_cutting_header(id_header)
);
```

## Penjelasan Kolom Toleransi

- **Nama Kolom**: `toleransi`
- **Tipe Data**: `DECIMAL(10,3)` (Mendukung hingga 10 digit, 3 digit di belakang koma)
- **Default Value**: `0.000`
- **Unit**: Meter (M) - sesuai standar satuan dalam sistem
- **Rentang**: 0.000 sampai 9999999.999

## Catatan Penting

1. Toleransi disimpan dalam satuan **Meter (M)**
2. Nilai toleransi ditambahkan ke std, max, min saat proses tambah/edit
3. Panjang Awal dan Panjang Akhir **TIDAK** dikonversi ke yard
4. **Hanya Std, Max, Min** yang dikonversi jika UOM CP = Yard dan Type Counter = Meter

## Operasi CRUD untuk Toleransi

### CREATE (Insert)
- Saat tambah piece di `tambahcutting.php`
- Nilai toleransi disimpan ke database di `save_cutting.php`

### READ (Select)
- Ditampilkan di tabel piece di `index.php` > tab Detail
- Ditampilkan di halaman `edit_detail.php` > tab Piece

### UPDATE (Edit)
- Di halaman `edit_detail.php` > tab Piece > klik Edit (✏️)
- Modal terbuka dengan field toleransi
- Klik Simpan → update via `save_edit_piece.php`

### DELETE
- Toleransi dihapus otomatis saat piece dihapus (via `delete_piece.php`)
- Atau bisa di-reset ke 0.000 saat edit piece

## Backward Compatibility

Jika tabel sudah ada tanpa kolom toleransi:
1. Jalankan: `ALTER TABLE cl_cutting_piece ADD toleransi DECIMAL(10,3) DEFAULT 0.000;`
2. Data lama akan mendapat nilai default 0.000
3. Tidak perlu migrasi data

## Testing Checklist

- [ ] Kolom toleransi sudah exist di database
- [ ] Insert piece dengan toleransi → berhasil
- [ ] Edit piece toleransi → berhasil
- [ ] Hapus piece → toleransi ikut terhapus
- [ ] Tampilan di detail cutting → toleransi muncul
- [ ] Konversi yard → Std/Max/Min dikonversi, Toleransi dalam meter
