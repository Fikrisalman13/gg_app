# Cara Penggunaan Aplikasi Resep Obat Experiment

Dokumen ini menjelaskan langkah-langkah penggunaan aplikasi modul **Resep Obat Experiment** oleh user (operator/developer) dari sisi antarmuka.

Lokasi file aplikasi: `pages/resep_obat/experiment/`

## Daftar Isi

1. [Akses Awal](#1-akses-awal)
2. [Halaman Daftar Group Experiment](#2-halaman-daftar-group-experiment)
3. [Tambah Group Experiment Baru](#3-tambah-group-experiment-baru)
4. [Lihat Detail Group](#4-lihat-detail-group)
5. [Lihat Detail Experiment](#5-lihat-detail-experiment)
6. [Buat Experiment Berikutnya](#6-buat-experiment-berikutnya)
7. [Edit Experiment](#7-edit-experiment)
8. [Hapus Experiment](#8-hapus-experiment)
9. [Hapus Group](#9-hapus-group)
10. [Approve Experiment](#10-approve-experiment)
11. [Input Parameter Mesin Lab](#11-input-parameter-mesin-lab)
12. [Input Lab Data](#12-input-lab-data)
13. [Hak Akses dan Role](#13-hak-akses-dan-role)
14. [Tanya Jawab Singkat](#14-tanya-jawab-singkat)

---

## 1. Akses Awal

1. Login ke aplikasi `gg_app` lewat `/gg_app/login.php`.
2. Pastikan Anda memiliki hak akses untuk menu **Resep Obat Experiment** (MenuId `212`) di grup Anda.
3. Buka menu **Resep Obat Experiment** dari sidebar.
4. Anda akan diarahkan ke halaman **Daftar Experiment** (`list_experiment.php`).

---

## 2. Halaman Daftar Group Experiment

Halaman ini (`list_experiment.php`) adalah halaman utama. Yang ditampilkan adalah **daftar Group**, bukan daftar experiment.

### Elemen yang tersedia

| Elemen | Fungsi |
| --- | --- |
| **Start Date** | Filter tanggal mulai dibuat group |
| **End Date** | Filter tanggal akhir dibuat group |
| **Tombol Filter** | Terapkan filter tanggal |
| **Tabel Group** | Daftar group experiment |
| **Tombol Tambah Experiment** | Membuat group baru (perlu `CanAdd`) |

### Kolom Tabel

| Kolom | Keterangan |
| --- | --- |
| No | Nomor urut |
| SOI | Sales Order Information dari group |
| No CP | Nomor CP group |
| Kode Warna | Kode warna yang dipakai group |
| Color Name | Nama warna |
| Resep Prod Code | Kode resep produksi |
| Cus Color | Kode warna customer |
| Total Exp | Jumlah experiment dalam group |
| Status | Status group / status experiment yang sudah di-approve |
| Aksi | Tombol Detail Group, Buat Exp Berikutnya, Hapus Group |

### Status

- `Draft` (abu-abu) → group baru, belum ada experiment di-approve.
- `Gagal` (merah) → experiment terakhir berstatus Gagal.
- `Sukses` (hijau) → experiment terakhir berstatus Sukses tapi belum di-approve.
- `Approved EXP #n` (biru) → group sudah memiliki experiment approved ke-n.

---

## 3. Tambah Group Experiment Baru

> Butuh permission `CanAdd`.

### Langkah

1. Di halaman **Daftar Experiment**, klik tombol **Tambah Experiment** (hijau, kanan atas).
2. Anda akan masuk ke halaman form (`input_resep.php`).
3. Isi **Informasi Dasar**:
   - **Kode Grey**: ketik minimal 1 karakter, pilih dari Select2.
   - **Mesin**: otomatis terisi dari Kode Grey.
   - **Kode Warna**: ketik minimal 2 karakter, pilih dari Select2.
   - **Color Name**, **Description**: otomatis dari Kode Warna.
   - **Resep Prod Code**: klik tombol di kanan input, pilih dari modal yang muncul.
   - **Resep Prod Name**, **Cus Color**: otomatis dari pilihan Resep Prod.
   - **Lot No**, **Plan Qty**, **Weight**, **Vlot**: isi manual. Weight dan Vlot bisa dihitung otomatis dari Plan Qty dan Kode Grey.
   - **Catatan**: catatan bebas.
4. Isi **Detail Resep Manual**:
   - Klik **Tambah Item** untuk menambah baris.
   - Pilih **Kode Item** dari Select2 (ketik min 1 karakter).
   - **Name**, **Category**, **Uom**, **Uom Cf** otomatis terisi.
   - Isi **Qty** dan **Cf** (otomatis dari Vlot jika Vlot diubah).
   - **Price** otomatis terisi jika kode item punya `codeprod`.
   - **Total** otomatis terhitung.
5. Klik **Simpan Experiment**.
6. Sistem akan membuat:
   - 1 baris baru di `resep_obat_experiment_group`.
   - 1 baris baru di `resep_obat_experiment` dengan `experiment_seq = 1` berstatus `Draft`.
   - Beberapa baris di `resep_obat_experiment_detail`.
7. Setelah simpan, Anda akan diarahkan ke halaman **Detail Group**.

### Yang TIDAK Tampil Saat Tambah Baru

- **SOI** dan **No CP** belum ada (baru di-generate setelah group terbentuk, bila perlu).
- **Status** tidak ditampilkan, otomatis `Draft`.

---

## 4. Lihat Detail Group

1. Dari **Daftar Experiment**, klik tombol **Detail Group** (icon layer) pada baris group.
2. Halaman `view_group.php` akan terbuka.

### Isi halaman

- **Info Group** (kiri-atas): data utama group.
  - Data yang tampil = data dari **experiment yang sudah di-approve** kalau ada.
  - Kalau belum ada yang di-approve, data diambil dari **experiment terbaru**.
- **History Experiment** (bawah): tabel berisi semua experiment dalam group.
  - Kolom: Urutan, Status, Total Item, Grand Total, **Total Cost / Meter**, Created At, Aksi.
  - **Total Cost / Meter** = `grand_total / plan_qty`.

### Aksi dari Detail Group

- Klik **Buat Experiment Berikutnya** untuk menambah experiment baru.
- Klik icon **mata** pada kolom Aksi untuk melihat detail experiment.
- Klik icon **edit** untuk edit experiment (tidak untuk yang sudah di-approve, kecuali Administrator).
- Klik icon **trash** untuk hapus experiment (tidak untuk yang sudah di-approve, kecuali Administrator).

---

## 5. Lihat Detail Experiment

1. Buka halaman **Detail Group** atau halaman **Daftar Experiment**.
2. Klik tombol view (icon mata) untuk experiment yang ingin dilihat.
3. Halaman `view_resep.php` akan terbuka.

### Isi halaman

- **Info Resep** (kiri): SOI, No CP, Urutan Eksperimen, Status, Kode Grey, Mesin, Kode Warna, Color Name, Description, Resep Prod Code, Resep Prod Name, Cus Color, Plan Qty, Weight, Vlot, Catatan.
- **Parameter Mesin Lab**: ringkasan 3 kolom (Mesin, Infra Red, Tekanan Padder, WPU, Speed, Fan1, Fan2, Temp Chamber 1, Temp Chamber 2, Lainnya).
- **Lab Data**: ringkasan 4 nilai (Delta L, Delta A, Delta B, Delta E).
- **Detail Resep**: tabel item resep lengkap.
- **Cost Summary Per Meter**: ringkasan biaya per meter.

### Toolbar (kanan atas)

| Tombol | Kapan Muncul | Fungsi |
| --- | --- | --- |
| **Print** | Selalu | Cetak halaman |
| **Parameter Mesin Lab** | Selalu | Buka modal input parameter |
| **Lab Data** | Selalu | Buka modal input lab data |
| **Approve** | `CanEdit` + belum approved | Approve experiment ini |
| (Edit, Delete, Detail Group, Kembali) | — | (sudah dihilangkan dari toolbar, navigasi lewat breadcrumb / menu) |

---

## 6. Buat Experiment Berikutnya

> Butuh permission `CanAdd`.
> Group yang sudah Approved tidak bisa menambah experiment baru (kecuali Administrator).

### Langkah

1. Buka halaman **Detail Group**.
2. Klik tombol **+ Exp** (hijau) pada baris group di **Daftar Experiment**, atau klik **Buat Experiment Berikutnya** di halaman detail group.
3. Anda akan masuk ke `input_resep.php?next_group_id=...`.
4. Sistem akan otomatis:
   - Mengambil data **experiment terakhir** dalam group sebagai template.
   - Menentukan `experiment_seq` baru (terakhir + 1).
   - Meng-copy detail resep dari experiment terakhir.
5. Semua data dapat diedit.
6. Klik **Simpan Experiment** untuk menyimpan.
7. Bila batal/tutup halaman, **tidak ada data yang tersimpan** (template tidak insert ke database).

### Catatan

- Sistem TIDAK membuat duplikat draft saat halaman dibuka.
- `experiment_status` otomatis `Draft` saat experiment baru dibuat.

---

## 7. Edit Experiment

> Butuh permission `CanEdit`.
> Experiment yang sudah di-approve **tidak bisa diedit** oleh user biasa.
> Administrator (`GroupId = 1`) bisa override.

### Langkah

1. Buka halaman **Daftar Experiment** atau **Detail Group**.
2. Klik icon **edit** (kuning) pada baris experiment.
3. Halaman `input_resep.php?resep_id=...` akan terbuka.
4. Ubah data yang diperlukan.
5. Klik **Simpan Experiment**.
6. Status yang bisa dipilih di dropdown: `Draft`, `Gagal`, `Sukses`.
   - `Approved` **tidak ditampilkan** di dropdown, hanya bisa via tombol Approve.

---

## 8. Hapus Experiment

> Butuh permission `CanDelete`.
> Experiment Approved tidak bisa dihapus (kecuali Administrator).

### Langkah

1. Buka halaman **Daftar Experiment** atau **Detail Group**.
2. Klik icon **trash** (merah) pada baris experiment.
3. Konfirmasi: klik **Ya, Hapus**.
4. Sistem akan:
   - Menghapus experiment.
   - Menghapus detail resep yang terkait.
   - Jika experiment yang dihapus adalah **experiment terakhir** dalam group, group juga ikut terhapus.

---

## 9. Hapus Group

> Butuh permission `CanDelete`.
> Group yang sudah punya experiment approved **tidak bisa dihapus** (kecuali Administrator).

### Langkah

1. Buka halaman **Daftar Experiment**.
2. Klik icon **trash** pada baris group.
3. Konfirmasi: klik **Ya, Hapus**.
4. Sistem akan menghapus:
   - Semua experiment dalam group.
   - Semua detail resep.
   - Semua parameter lab dan lab data.
   - Group-nya sendiri.

---

## 10. Approve Experiment

> Butuh permission `CanEdit`.

### Langkah

1. Buka halaman **Detail Experiment**.
2. Klik tombol **Approve** (hijau) di toolbar.
3. Konfirmasi: klik **Ya, Approve**.
4. Sistem akan:
   - Set `experiment_status` ke `Approved`.
   - Set `group_status` ke `Approved`.
   - Set `approved_experiment_id` di group ke experiment ini.
   - Mengunci group & experiment dari edit/delete normal.

### Catatan

- Hanya experiment **terbaru** yang masuk akal untuk di-approve (meski secara teknis bisa approve experiment urutan manapun).
- Setelah approve, group TIDAK bisa ditambah experiment baru (kecuali Administrator).
- Administrator bisa tetap edit/delete experiment approved sebagai override.

---

## 11. Input Parameter Mesin Lab

> Hanya untuk experiment yang **belum di-approve**.
> Untuk experiment approved, modal hanya read-only.

### Langkah

1. Buka **Detail Experiment**.
2. Klik tombol **Parameter Mesin Lab** di toolbar.
3. Modal terbuka. Isi field:
   - **Mesin**: pilih dari Select2 (AJAX ke `../get_machines.php`, sumber: `famaster` dengan `facode LIKE FAM%`).
   - **Infra Red** (%): persen infrared.
   - **Tekanan Padder**: tekanan padder.
   - **WPU** (%): persen WPU.
   - **Speed**: kecepatan mesin.
   - **Fan1** (%): fan 1.
   - **Fan2** (%): fan 2.
   - **Temp Chamber 1** (°C): suhu chamber 1.
   - **Temp Chamber 2** (°C): suhu chamber 2.
   - **Lainnya**: catatan tambahan (multiline).
4. Klik **Simpan**.
5. Sistem menyimpan ke `resep_obat_experiment_lab_param`.
6. Halaman akan reload, ringkasan parameter akan muncul.

### Tampilan Ringkasan

Setelah simpan, ringkasan tampil dalam card dengan 3 kolom:

```text
Mesin           : <Nama> (<Kode>)
Infra Red       | Tekanan      | WPU
                | Padder       |
Speed           | Fan1         | Fan2
Temp Chamber 1  | Temp Chamber 2 |
Lainnya         : ...
```

### Catatan

- Mesin adalah Select2 dengan `dropdownParent` di modal, sehingga dropdown tidak tertutup.
- Data lama (kalau ada) akan otomatis ter-select saat modal dibuka.
- Field `Mesin` dan field numerik disimpan di tabel berbeda dari `resep_obat_experiment_detail`.

---

## 12. Input Lab Data

> Hanya untuk experiment yang **belum di-approve**.

### Langkah

1. Buka **Detail Experiment**.
2. Klik tombol **Lab Data** di toolbar.
3. Modal terbuka. Isi field:
   - **Delta L**
   - **Delta A**
   - **Delta B**
   - **Delta E**
   - **Status Sukses/Gagal**: dipilih manual oleh user (tidak otomatis).
4. Klik **Simpan**.
5. Sistem menyimpan ke `resep_obat_experiment_lab_data`.
6. Halaman akan reload, ringkasan lab data akan muncul.

### Catatan

- Status `Sukses` / `Gagal` di sini **tidak otomatis mengubah** `experiment_status`. User tetap harus update status experiment secara manual di halaman Edit.

---

## 13. Hak Akses dan Role

Hak akses mengikuti tabel `dbo.SMGroupTrustee` dengan `MenuId = 212`.

| Permission | Konstanta | Fungsi |
| --- | --- | --- |
| CanView | PVIEW | Lihat halaman dan data |
| CanAdd | PADD | Tambah group / experiment baru |
| CanEdit | PEDIT | Edit experiment & Approve |
| CanDelete | PDEL | Hapus experiment / group |

### Role Administrator (`GroupId = 1`)

Administrator punya **override**:

- Bisa **edit** experiment yang sudah di-approve.
- Bisa **delete** experiment / group yang sudah di-approve.
- Bisa **tambah** experiment baru pada group yang sudah di-approve.
- Status Approve hanya melalui tombol Approve, tidak via dropdown.

### Role User Biasa

- Tidak bisa edit/delete experiment approved.
- Tidak bisa tambah experiment baru pada group approved.

---

## 14. Tanya Jawab Singkat

**Q: Kenapa tombol Edit / Delete tidak muncul?**
A: Anda tidak punya permission `CanEdit` / `CanDelete` di menu 212, atau experiment sudah di-approve.

**Q: Kenapa pilihan status `Approved` tidak ada di dropdown?**
A: Status `Approved` hanya di-set lewat tombol **Approve** di toolbar halaman Detail Experiment. Tujuannya supaya proses approve melalui satu jalur yang konsisten (yang juga mengunci group).

**Q: Apakah data Parameter Lab / Lab Data auto-set status Sukses/Gagal?**
A: Tidak. Status `Sukses`/`Gagal` di Lab Data hanya catatan. Status experiment tetap dipilih manual lewat form Edit.

**Q: Kalau saya buka "Buat Experiment Berikutnya" lalu batal, apakah data tersimpan?**
A: Tidak. Template tidak di-insert ke database sampai Anda klik **Simpan Experiment**.

**Q: Kenapa di Daftar Experiment kolom Last Exp tidak ada?**
A: Kolom `Last Exp` memang sengaja dihilangkan. Status cukup menampilkan `Approved EXP #n` (kalau sudah approved) atau status experiment terbaru.

**Q: Bisa approve experiment urutan ke-2 atau ke-3?**
A: Bisa secara teknis, tapi biasanya yang di-approve adalah experiment terbaru. Sistem tidak membatasi ini.

**Q: Mengapa halaman tidak menampilkan tombol "Kembali" di toolbar?**
A: Tombol Kembali dihilangkan dari toolbar. Navigasi balik bisa lewat breadcrumb (Beranda / Experiment) atau menu sidebar.

---

Dokumen ini hanya menjelaskan **penggunaan aplikasi** dari sisi UI. Untuk alur/flowchart internal dan teknis, baca [`alur.md`](./alur.md).
