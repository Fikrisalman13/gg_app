# Dokumentasi Pengembangan - Resep Lipat

## Ringkasan
Modul `pages/resep_lipat` digunakan untuk mencari dan menampilkan **satu baris per resephdid** (info header saja) dari database ERP PostgreSQL melalui `koneksi3.php`, dengan halaman detail untuk melihat routing & material.

## File Modul

| File | Fungsi |
|---|---|
| `index.php` | Halaman utama, form filter, DataTables, multiple select, tombol Detail (redirect) |
| `detail.php` | Halaman detail: header info + routing groups dengan materials |
| `resep_lipat_serverside.php` | Endpoint AJAX DataTables server-side untuk query PostgreSQL (1 baris per resephdid) |
| `DOCUMENTATION.md` | Dokumentasi modul ini |

## Alur Kerja
1. User membuka `pages/resep_lipat/index.php`.
2. User mengisi minimal `Kode Warna Cust` (`cuscolor`).
3. Filter opsional:
   - `Kode Warna/Lab` (`colorcode`)
   - `Process` (`processcode`)
4. Klik tombol **Cari** untuk memuat data.
5. Hasil ditampilkan di tabel utama (1 baris per resephdid, urut `resepdate DESC`).
6. Klik tombol **Detail** untuk membuka halaman `detail.php?id=...` yang menampilkan:
   - Header info lengkap resep
   - Detail routing & materials (collapsible cards per routing group)
7. Klik tombol **← Kembali ke List** untuk kembali ke halaman utama.

## Koneksi Database
Modul ini menggunakan:

```php
require_once __DIR__ . '/../../koneksi3.php';
```

Variable koneksi: `$conn3` (PDO PostgreSQL).

## Query Utama

### Halaman Index (satu baris per resephdid)

Tabel yang digunakan:
- `pdresephd` alias `h`
- `pdcolorms` alias `c`
- `smprodtechdata` subquery alias `s`
- `pdresepstatus` alias `st`

> Tabel `pdresepdt` **tidak di-join** di halaman utama. Tujuannya agar 1 resephdid = 1 baris di list. Tabel `pdresepdt` di-join di halaman detail.

Filter wajib:
```sql
s.cuscolor = :cuscolor
```

Filter opsional:
```sql
c.colorcode ILIKE :colorcode
h.processcode ILIKE :processcode
```

### Halaman Detail (header)

Tabel yang digunakan:
- `pdresephd` alias `h`
- `pdcolorms` alias `c`
- `smprodtechdata` subquery alias `s`
- `pdresepstatus` alias `st`
- `incontracthd` alias `ct` (LEFT JOIN untuk CP No)

Query:
```sql
SELECT h.*, c.colorcode, c.colorname, s.cuscolor, 
       st.statuscode, st.statusdesc, ct.contnmbr
FROM pdresephd h
    INNER JOIN pdcolorms c ON h.colormsid = c.colormsid
    INNER JOIN (SELECT colormsid, MAX(cuscolor) AS cuscolor 
                FROM smprodtechdata GROUP BY colormsid) s 
        ON c.colormsid = s.colormsid
    LEFT JOIN pdresepstatus st ON h.resepstatusid = st.resepstatusid
    LEFT JOIN incontracthd ct ON h.sohdid = ct.sohdid
WHERE h.resephdid = :id
```

### Halaman Detail (routing & materials)

Tabel:
- `pdresepdt` alias `d`
- `pdrtgms` alias `r`

Query:
```sql
SELECT d.rtgseq, d.rtgdesc, d.materialseq,
       d.materialcode, d.materialname, d.qty, d.fgusedtype,
       r.rtgcode, r.rtgname
FROM pdresepdt d
    LEFT JOIN pdrtgms r ON d.rtgmsid = r.rtgmsid
WHERE d.resephdid = :id
ORDER BY d.rtgseq, d.materialseq
```

> **Grouping**: Routing dikelompokkan berdasarkan kombinasi `rtgseq + rtgdesc` (bukan hanya `rtgcode`), karena satu `rtgcode` bisa muncul di beberapa `rtgseq`/`rtgdesc` berbeda (contoh: `rtgcode=06158` muncul di `rtgseq=1,2` dengan `rtgdesc` berbeda).

## Kolom Tampilan - Halaman Index

| Kolom UI | Field |
|---|---|
| Select | checkbox berdasarkan `resephdid` + row index (komposit) |
| No | nomor urut DataTables |
| Kode Warna/Lab | `colorcode` |
| Nama Warna | `colorname` |
| Kode Warna Cust | `cuscolor` |
| Tgl Resep | `resepdate` |
| Tipe Resep | `reseptype` |
| Process | `processcode` |
| Kode Produk | `resepprodcode` |
| Nama Produk | `resepprodname` |
| Status | `statusdesc` |
| Aksi | Tombol Detail (redirect ke `detail.php?id=...`) |

## Kolom Tampilan - Halaman Detail

### Header Info (7 baris x 4 kolom)

| Baris | Kolom 1 | Kolom 2 | Kolom 3 | Kolom 4 |
|---|---|---|---|---|
| 1 | Resep No | `resepno` | Date | `resepdate` |
| 2 | Ver. | `resepseq` | Product | `resepprodname` + `resepprodcode` |
| 3 | Status | `statuscode` + `statusdesc` | Tipe Resep | `reseptype` |
| 4 | Greige | `kainprodname` + `kainprodcode` | SO No | `transnmbr` |
| 5 | CP No | `incontracthd.contnmbr` (LEFT JOIN) | Const. Greige | `contsgreige` |
| 6 | Gramature (gr/m) | `gramature` | Lot Greige | `lot` |
| 7 | Color | `colorname` + `colorcode` | Process | `processcode` |

### Detail Resep (per routing group)

| Judul Card | `{rtgcode} - {rtgname}` |
|---|---|
| Description | `rtgdesc` |
| Tabel: Material Code | `materialcode` |
| Tabel: Material Name | `materialname` |
| Tabel: Qty | `qty` |
| Tabel: Used Type | `fgusedtype` (mapping: G→gr/liter) |

### Mapping `fgusedtype`

| Code | Tampilan |
|---|---|
| `G` | gr/liter |
| `P` | % |
| `L` | liter |
| (lainnya) | nilai asli |

## Multiple Select
Checkbox sudah disiapkan untuk kebutuhan bulk action berikutnya.
Saat ini tombol **Bulk Action (coming soon)** hanya menampilkan SweetAlert informasi.

State selected rows disimpan di JavaScript object dengan key komposit (`resephdid` + `rowIndex`):
```js
var selectedRows = {};
```
Hal ini untuk mencegah duplikat ketika berpindah halaman (key komposit memastikan setiap baris punya identitas unik).

## Tombol Detail
Tombol **Detail** di halaman index akan redirect ke:
```
detail.php?id={resephdid}
```

Di halaman `detail.php`:
- Tampilkan header info lengkap (7 baris x 4 kolom)
- Tampilkan detail resep dalam collapsible cards (satu card per `rtgseq + rtgdesc`)
- Setiap card berisi description + tabel materials
- Tombol **← Kembali ke List** untuk kembali ke `index.php`

## Plugin Lokal yang Dipakai
Semua plugin dipanggil dari path lokal agar bisa offline:

| Plugin | Path |
|---|---|
| AdminLTE | `/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css` |
| FontAwesome | `/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css` |
| jQuery | loaded dari footer/header existing |
| DataTables JS | `/gg_app/plugins/js/datatables/jquery.dataTables.min.js` |
| DataTables Bootstrap JS | `/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js` |
| DataTables Responsive JS | `/gg_app/plugins/js/datatables/dataTables.responsive.min.js` |
| DataTables CSS | `/gg_app/plugins/css/dataTables.bootstrap5.min.css` |
| Responsive CSS | `/gg_app/plugins/css/responsive.bootstrap5.min.css` |
| SweetAlert2 | `/gg_app/plugins/js/notifikasi/sweetalert2@11.js` |

## Status Override (Manual)
Untuk modul Resep Lipat, status resep yang ditampilkan dapat di-override secara manual dari modul **Resep Obat** (`pages/resep_obat/input_resep.php`).

1. **Pilihan Status Manual**:
   - `Master Resep` (badge hijau/success)
   - `Shading` (badge kuning/warning)
   - `Top Paddry` (badge biru muda/info)
   - `Top CPB` (badge abu-abu/secondary)
2. **Koneksi Lintas Database**:
   - Data status manual disimpan di SQL Server (`dbo.resep_obat.status_resep_lipat`).
   - Data resep utama ditarik dari PostgreSQL ERP.
   - Sinkronisasi status dilakukan di PHP (`resep_lipat_serverside.php` dan `detail.php`) dengan mencocokkan `proint_resephdid` (Opsi B), dan fallback menggunakan `resep_no + resep_seq` (Opsi A) jika `proint_resephdid` kosong (untuk record lama).
   - Apabila tidak ditemukan status manual, sistem akan menampilkan status default dari ERP.

## Catatan Pengembangan Berikutnya
1. Tambahkan menu sidebar secara manual sesuai kebutuhan.
2. Implementasikan bulk action menggunakan data dari `selectedRows`.
3. Untuk filter dropdown/autocomplete (misal daftar processcode), dapat dibuat endpoint tambahan agar list pilihan tidak hardcoded.
4. Print/Export halaman detail (print preview, export PDF/Excel) - untuk pengembangan berikutnya.
