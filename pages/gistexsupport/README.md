# Gistex Support

Folder `gistexsupport/` isi menu input master dan transaksi buat kebutuhan Gistex.

## Menu

### `master_gistex`
Master produk.
- Field: `id`, `item`, `kode_produk`
- Fungsi: referensi produk untuk order item.

### `uom_gistex`
Master unit of measure.
- Field: `uomid`, `uomname`
- Fungsi: referensi UOM untuk order item.

### `packaged_gistex`
Master packaging.
- Field: `packaging`
- Fungsi: referensi packaging untuk order item.

### `orderitem_gistex`
Transaksi PO / order item.
- Field utama: `nomor_po`, `Description`, `Qty.Order`, `Packaging`, `Uom`
- `Description` ambil data dari `master_gistex.kode_produk`
- `Packaging` ambil data dari `packaged_gistex`
- `Uom` ambil data dari `uom_gistex.uomname`, simpan value `uomid`
- `id` dan `item` terisi otomatis dari master saat `Description` dipilih

## Flow Input

1. Isi `master_gistex`.
2. Isi `uom_gistex`.
3. Isi `packaged_gistex`.
4. Masuk tab `orderitem_gistex`.
5. Input `Nomor PO`.
6. Klik `Add Item` untuk buka modal item.
7. Pilih `Description`, `Qty.Order`, `Packaging`, dan `Uom`.
8. Simpan data.

## Aturan PO

- `nomor_po` hanya boleh dipakai sekali.
- Jika `nomor_po` sudah ada, save akan ditolak.

## Database

File SQL:
- `gistexsupport/gistexsupport_tables.sql`
- `gistexsupport/gistexsupport_alter.sql`

### Catatan schema
- `master_gistex.id` manual, bukan identity.
- Kolom audit tersedia di semua tabel:
  - `create_date`
  - `created_by`
  - `updatedate`
  - `updateby`
- `orderitem_gistex` punya kolom `nomor_po`.

## File penting

- `index.php` - tampilan semua tab
- `save_*.php` - insert data
- `update_*.php` - update data
- `delete_*.php` - hapus data
