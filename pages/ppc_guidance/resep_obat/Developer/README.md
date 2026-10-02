# Audit Detail Resep

## Purpose

Halaman `debug_detail_resep.php` membandingkan detail resep lokal lama dengan material aktual ProInt berdasarkan No CP. Audit bersifat read-only dan hanya mencakup kategori master obat lokal `DISPERSE` serta `REACTIVE`.

## Main Flow

1. Header lokal dibaca dari `dbo.resep_obat`.
2. Detail lokal dibaca dari `dbo.resep_obat_detail`.
3. Kandidat Bon dicari melalui `pdproductionhd` dan `pdbonreq`.
4. Material aktual dibaca dari `pdproductionmat` berdasarkan `productionhdid + productionrtgid`.
5. Item ProInt dipetakan lewat `dbo.resep_master_obat.codeprod_proint`.
6. Detail diklasifikasikan sebagai cocok, selisih nilai, hanya lokal, atau hanya ProInt.

## Files

- `debug_detail_resep.php`: halaman filter, ringkasan, dan modal perbandingan.
- `debug_detail_resep_api.php`: endpoint JSON berpaginasi.
- `debug_detail_resep_compare.php`: query dan pembanding bersama.
- `export_debug_detail_resep.php`: ekspor Excel dua sheet.

## Database Dependencies

- SQL Server `$conn`: `dbo.resep_obat`, `dbo.resep_obat_detail`, `dbo.resep_master_obat`.
- PostgreSQL `$conn3`: `pdproductionhd`, `pdbonreq`, `pdrtgms`, `pdproductionmat`, `smuom`.

## Matching Rules

Routing lokal dipakai bila tersedia. Jika routing kosong dan CP hanya memiliki satu Bon, Bon tersebut dipakai. Jika CP memiliki beberapa Bon, status `PERLU PILIH BON`; pengguna memilih Bon hanya untuk tampilan sesi dan pilihan tidak disimpan.

Toleransi Qty dan CF adalah `0.0001`. UOM dibandingkan setelah trim dan uppercase.

## Current State

Audit dan ekspor tersedia. Data tidak diperbaiki otomatis.

## Known Issues

- Resep lama tanpa routing dan multi-Bon membutuhkan pilihan manual.
- Ringkasan halaman menunjukkan halaman aktif; jumlah total lokal menunjukkan seluruh hasil filter.

## Verification

Run `php -l` pada empat file PHP audit dan buka halaman memakai sesi login. Uji CP multi-Bon `D26G0002.01.0101`, lalu cocokkan file Excel dengan modal perbandingan.

## Konfigurasi Tampilan Metadata ProInt

PPC Guidance memakai `dbo.resep_ppc_guidance_config` untuk pengaturan khusus modul. Key `show_proint_metadata` mengendalikan tampilan Resep Prod Code/Name, Resep No/Date/Type, No CP Resep, No SO, Routing, dan Status Desc pada Input, View/print PDF, serta export Excel biasa. Nilai `0` menyembunyikan metadata; nilai `1` menampilkannya. Data tetap diambil dan disimpan. Debug audit, Experiment, dan modul di luar `pages/ppc_guidance/resep_obat` tidak membaca setting ini.
