# Resep Obat Experiment

Modul `pages/resep_obat/experiment` digunakan untuk membuat dan melacak percobaan resep obat.

## Konsep Group Experiment

Data menggunakan konsep:

```text
Experiment Group
  ├─ Experiment #1
  ├─ Experiment #2
  └─ Experiment #3
```

`Experiment Group` menyimpan data umum untuk satu rangkaian percobaan:

- SOI
- No CP
- Kode Grey
- Mesin
- Kode Warna
- Color Name
- Description
- Resep Prod Code
- Resep Prod Name
- Cus Color
- Status Group
- Approved Experiment

Setiap group memiliki satu atau lebih `Experiment` dengan urutan otomatis.

## Experiment Sequence

Setiap percobaan mempunyai `experiment_seq`:

- Experiment pertama: `EXP #1`
- Experiment berikutnya: `EXP #2`, `EXP #3`, dst.

Urutan dibuat otomatis berdasarkan group.

## Status Experiment

Status yang digunakan:

- `Draft`
- `Gagal`
- `Sukses`
- `Approved`

Jika experiment diapprove:

- Status experiment menjadi `Approved`.
- Status group menjadi `Approved`.
- `approved_experiment_id` di group terisi.
- Group/experiment dikunci dari edit normal.
- User `GroupId = 1` / Administrator tetap bisa edit dan delete sebagai override.

## Alur Buat Group Baru

1. User buka `list_experiment.php`.
2. Klik `Tambah Group Experiment`.
3. User isi Informasi Dasar dan Detail Resep Manual.
4. Pada form tambah baru:
   - SOI tidak ditampilkan.
   - No CP tidak ditampilkan.
   - Status tidak ditampilkan dan otomatis tersimpan `Draft`.
5. Saat simpan:
   - Sistem membuat `resep_obat_experiment_group`.
   - Sistem membuat `resep_obat_experiment` dengan `experiment_seq = 1`.
   - Detail resep tersimpan ke `resep_obat_experiment_detail`.

## Alur Buat Experiment Berikutnya

1. User buka detail group `view_group.php` atau `list_experiment.php`.
2. Klik `Buat Experiment Berikutnya` / `Exp`.
3. Sistem membuka `input_resep.php?next_group_id=...`.
4. Sistem menampilkan data experiment terakhir sebagai template.
5. Data baru **belum diinsert** sampai user klik `Simpan Experiment`.
6. Jika user batal/tutup halaman, tidak ada draft duplikat di database.
7. Saat simpan:
   - Sistem membuat experiment baru pada group yang sama.
   - `experiment_seq` dibuat otomatis dari urutan terakhir + 1.
   - Detail resep tersimpan dari form.

## List Experiment

`list_experiment.php` menampilkan daftar Group, bukan daftar semua experiment.

Kolom utama:

- SOI
- No CP
- Kode Warna
- Color Name
- Resep Prod Code
- Cus Color
- Total Exp
- Status
- Aksi

Jika group sudah memiliki approved experiment, status ditampilkan sebagai:

```text
Approved EXP #1
```

## View Group

`view_group.php` menampilkan:

- Informasi Group
- Total experiment
- Status group
- Approved experiment
- History experiment per urutan

Info Group memakai data:

1. Experiment yang sudah approved jika ada.
2. Jika belum ada approved, memakai experiment terbaru.

## View Experiment

`view_resep.php` menampilkan detail satu experiment:

- SOI
- No CP
- Urutan Eksperimen
- Status
- Informasi dasar
- Detail resep manual
- Cost Summary Per Meter
- Ringkasan Parameter Mesin Lab
- Ringkasan Lab Data
- Tombol `Parameter Mesin Lab`
- Tombol `Lab Data`
- Tombol Approve jika belum approved
- Tombol Delete jika user punya `CanDelete` dan experiment belum approved

## Parameter Mesin Lab

Parameter Mesin Lab disimpan per experiment pada tabel:

```text
resep_obat_experiment_lab_param
```

Field utama:

- `machine_code`
- `machine_name`
- `infra_red`
- `tekanan_padder`
- `wpu`
- `speed`
- `fan1`
- `fan2`
- `temp_chamber_1`
- `temp_chamber_2`
- `lainnya`

`machine_code` dan `machine_name` dipilih dengan Select2 AJAX memakai endpoint existing:

```text
../get_machines.php
```

Endpoint tersebut mengambil data dari `famaster` via `koneksi3.php` dan filter `facode LIKE FAM%`.

Behavior:

- Dibuka sebagai popup modal dari `view_resep.php`, bukan halaman baru.
- Jika belum ada data, modal menjadi input baru.
- Jika sudah ada data, modal memuat data lama dan simpan akan update record yang sama.
- Jika experiment sebelumnya dalam group punya data, data tersebut dipakai sebagai template tampilan.
- Template tidak langsung disimpan ke database sampai user klik Simpan.

## Lab Data

Lab Data disimpan per experiment pada tabel:

```text
resep_obat_experiment_lab_data
```

Field utama:

- `delta_l`
- `delta_a`
- `delta_b`
- `delta_e`

Lab Data hanya menyimpan hasil ukur. Sistem belum otomatis menentukan `Sukses`/`Gagal` dari nilai Delta E atau toleransi tertentu. Status experiment tetap dipilih manual oleh user.

Behavior:

- Dibuka sebagai popup modal dari `view_resep.php`, bukan halaman baru.
- Jika belum ada data, modal menjadi input baru.
- Jika sudah ada data, modal memuat data lama dan simpan akan update record yang sama.
- Jika experiment sebelumnya dalam group punya data, data tersebut dipakai sebagai template tampilan.
- Template tidak langsung disimpan ke database sampai user klik Simpan.

## Delete Experiment / Group

Delete diproteksi permission `CanDelete` dari menu 212.

Aturan delete user biasa:

- Tombol hanya muncul jika user punya `CanDelete`.
- Experiment `Approved` tidak bisa dihapus.
- Group `Approved` tidak bisa dihapus.
- Detail resep akan ikut terhapus.
- Jika experiment yang dihapus adalah experiment terakhir dalam group, group akan ikut terhapus.

Administrator (`GroupId = 1`) tetap bisa edit/delete data approved sebagai override.

## Database Utama

- `resep_obat_experiment_group`
- `resep_obat_experiment`
- `resep_obat_experiment_detail`
- `resep_obat_experiment_lab_param`
- `resep_obat_experiment_lab_data`

Semua tabel wajib memiliki audit column:

- `created_at`
- `created_by`
- `updated_at`
- `updated_by`
