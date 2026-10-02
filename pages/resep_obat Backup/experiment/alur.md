# Alur Aplikasi Resep Obat Experiment

Dokumen ini menjelaskan **alur/flowchart teknis** modul `pages/resep_obat/experiment`.

Untuk panduan **penggunaan UI** lihat [`cara penggunaan aplikasi.md`](./cara%20penggunaan%20aplikasi.md).

## Daftar Isi

1. [Konsep Data](#1-konsep-data)
2. [Arsitektur Modul](#2-arsitektur-modul)
3. [Alur Tambah Group Baru](#3-alur-tambah-group-baru)
4. [Alur Buat Experiment Berikutnya](#4-alur-buat-experiment-berikutnya)
5. [Alur Edit Experiment](#5-alur-edit-experiment)
6. [Alur Approve Experiment](#6-alur-approve-experiment)
7. [Alur Input Parameter Mesin Lab](#7-alur-input-parameter-mesin-lab)
8. [Alur Input Lab Data](#8-alur-input-lab-data)
9. [Alur Delete Experiment](#9-alur-delete-experiment)
10. [Alur Delete Group](#10-alur-delete-group)
11. [Alur Tampilan Info Group](#11-alur-tampilan-info-group)
12. [State Diagram Status](#12-state-diagram-status)
13. [Skema Database](#13-skema-database)
14. [Aturan Permission](#14-aturan-permission)
15. [Edge Cases & Invarian](#15-edge-cases--invarian)

---

## 1. Konsep Data

Modul ini mengelola **3 lapisan data**:

```text
Experiment Group
  ├─ Experiment #1
  │    ├─ Detail Resep (n baris)
  │    ├─ Parameter Mesin Lab (0/1 baris)
  │    └─ Lab Data (0/1 baris)
  ├─ Experiment #2
  │    ├─ Detail Resep
  │    ├─ Parameter Mesin Lab
  │    └─ Lab Data
  └─ Experiment #n
```

- **1 Group** = 1 rangkaian percobaan untuk 1 kode warna + 1 resep prod.
- **1 Group** punya N **Experiment** dengan `experiment_seq` urut (1, 2, 3, ...).
- Tiap **Experiment** bisa punya **1 row** Parameter Lab dan **1 row** Lab Data (relasi 1:1 ke experiment).
- **approved_experiment_id** di group = pointer ke satu experiment yang di-approve.

---

## 2. Arsitektur Modul

### File Utama

| File | Tipe | Fungsi |
| --- | --- | --- |
| `list_experiment.php` | View | Halaman utama daftar group (DataTables server-side) |
| `view_group.php` | View | Detail group + history experiment |
| `view_resep.php` | View | Detail satu experiment |
| `input_resep.php` | View/Form | Form tambah/edit experiment |
| `serverside_experiment_group.php` | Endpoint | JSON untuk DataTables list_experiment |
| `save_resep.php` | Endpoint | Simpan experiment (insert/update group + experiment + detail) |
| `copy_next_experiment.php` | Endpoint | (cadangan) Copy experiment |
| `approve_experiment.php` | Endpoint | Approve experiment + lock group |
| `delete_experiment.php` | Endpoint | Hapus experiment (+ group jika experiment terakhir) |
| `delete_group.php` | Endpoint | Hapus group + semua experiment + semua detail |
| `delete_resep.php` | Endpoint | (legacy) Hapus resep |
| `save_lab_param.php` | Endpoint | Simpan Parameter Mesin Lab |
| `save_lab_data.php` | Endpoint | Simpan Lab Data |
| `get_colors.php` | Endpoint | Select2 AJAX untuk kode warna |
| `get_grey_items.php` | Endpoint | Select2 AJAX untuk kode grey |
| `get_items.php` | Endpoint | Select2 AJAX untuk item resep |
| `get_item_price.php` | Endpoint | Harga item resep |
| `get_resep_prod_options.php` | Endpoint | (opsional) Select2 untuk resep prod |
| `serverside_resep_prod.php` | Endpoint | Modal pilih Resep Prod Code |
| `serverside_experiment.php` | Endpoint | (legacy) Server-side experiment |
| `get_machines.php` | Endpoint (shared) | Select2 mesin untuk lab param |
| `create_tables.php` | Schema | DDL semua tabel modul |
| `koneksi.php` | Shared | Koneksi SQL Server |

### Dependensi

- `AdminLTE-3.2.0` untuk layout, `select2`, `sweetalert2`, `datatables`.
- `koneksi.php` (SQL Server) sebagai koneksi database.
- `koneksi3.php` (di parent) untuk data mesin `famaster`.

---

## 3. Alur Tambah Group Baru

```mermaid
flowchart TD
    A[User buka list_experiment.php] --> B{Klik Tambah Experiment?}
    B -- Ya --> C[input_resep.php tanpa parameter]
    C --> D[User isi Kode Grey - Select2]
    D --> E[Otomatis terisi: Mesin, default Plan Qty, Vlot, Weight]
    E --> F[User pilih Kode Warna - Select2]
    F --> G[Otomatis terisi: Color Name, Description]
    G --> H[User klik tombol Resep Prod Code]
    H --> I[Modal Pilih Resep Prod Code]
    I --> J[User pilih salah satu]
    J --> K[Otomatis terisi: Resep Prod Code, Name, Cus Color]
    K --> L[User isi Detail Resep Manual]
    L --> M[User klik Simpan Experiment]
    M --> N[POST save_resep.php dengan FormData]
    N --> O{Sudah Approved?}
    O -- Ya --> X[Gagal: 403 locked]
    O -- Tidak --> P[INSERT resep_obat_experiment_group]
    P --> Q[INSERT resep_obat_experiment seq=1 status=Draft]
    Q --> R[INSERT resep_obat_experiment_detail per item]
    R --> S[Response success + group_id]
    S --> T[Redirect ke view_group.php]
```

### Query Penting di `save_resep.php`

```sql
-- Insert group
INSERT INTO dbo.resep_obat_experiment_group
  (soi, no_cp, kode_grey, mesin, kode_warna, color_name, color_desc,
   resep_prod_code, resep_prod_name, cus_color, proint_resephdid,
   group_status, created_at, created_by)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', GETDATE(), ?);

-- Insert experiment
INSERT INTO dbo.resep_obat_experiment
  (group_id, experiment_seq, experiment_status, experiment_note, kode_grey, ...,
   created_at, created_by)
VALUES (?, 1, 'Draft', ?, ?, ..., GETDATE(), ?);

-- Insert details (loop)
INSERT INTO dbo.resep_obat_experiment_detail
  (id_resep_experiment, kode, name, category, receipe, uom, cf, uom_cf,
   std_price, total, is_manual, price_satuan, price_source,
   created_at, created_by)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, GETDATE(), ?);
```

---

## 4. Alur Buat Experiment Berikutnya

```mermaid
flowchart TD
    A[User di list_experiment atau view_group] --> B{Klik + Exp?}
    B --> C[GET input_resep.php?next_group_id=X]
    C --> D[Server: load group by X]
    D --> E{Group status = Approved?}
    E -- Ya, bukan Admin --> F[Gagal: Group sudah Approved]
    E -- Tidak / Admin --> G[Server: load experiment terakhir in group]
    G --> H[experiment_seq baru = last+1 atau 1]
    H --> I[Server: copy detail resep dari experiment terakhir]
    I --> J[Tampilkan form dengan data template]
    J --> K[User edit data]
    K --> L{User klik Simpan?}
    L -- Tidak --> M[Batal: TIDAK insert ke DB]
    L -- Ya --> N[POST save_resep.php dengan next_group_id=X]
    N --> O[Server: cek group status lagi]
    O -- Locked --> X[Gagal]
    O -- OK --> P[INSERT experiment baru di group X dengan seq baru]
    P --> Q[INSERT detail resep dari form]
    Q --> R[Response + group_id]
    R --> S[Redirect view_group]
```

### Catatan Penting

- **Tidak ada insert draft** saat halaman dibuka. Template disimpan hanya di memory PHP session/request.
- Bila user menutup halaman / klik "Kembali", database tidak berubah.
- `experiment_seq` baru dihitung dari `MAX(experiment_seq) WHERE group_id = X + 1`.
- Bila `next_group_id` group sudah Approved, hanya Administrator yang boleh lanjut.

---

## 5. Alur Edit Experiment

```mermaid
flowchart TD
    A[User klik Edit di view_group/list] --> B[GET input_resep.php?resep_id=X]
    B --> C[Server: load experiment X + group + details]
    C --> D{isApproved?}
    D -- Ya, bukan Admin --> E[Form read-only semua field]
    D -- Ya, Admin --> F[Form editable semua field]
    D -- Tidak --> F
    F --> G[User edit]
    G --> H{User klik Simpan?}
    H -- Ya --> I[POST save_resep.php]
    I --> J{isApproved && !Admin?}
    J -- Ya --> X[Gagal: locked]
    J -- Tidak --> K[UPDATE group X, experiment X, detail X]
    K --> L[Response success]
    L --> M[Redirect view_group]
```

### Catatan

- Dropdown Status **tidak** menampilkan pilihan `Approved`.
- Status `Approved` hanya lewat tombol Approve.
- User biasa tidak bisa update status ke Approved lewat form.

---

## 6. Alur Approve Experiment

```mermaid
flowchart TD
    A[User di view_resep] --> B{Status = Approved?}
    B -- Ya --> X[Tombol Approve hidden]
    B -- Tidak --> C[Tombol Approve visible jika CanEdit]
    C --> D[Klik Approve]
    D --> E[Konfirmasi SweetAlert]
    E -- Batal --> X
    E -- Ya --> F[POST approve_experiment.php resep_id=X]
    F --> G[Server: cek status != Approved]
    G -- Sudah Approved --> H[Gagal]
    G -- Belum --> I[BEGIN TRANSACTION]
    I --> J[UPDATE experiment X SET experiment_status = Approved]
    J --> K[UPDATE group X SET group_status = Approved, approved_experiment_id = X]
    K --> L[COMMIT]
    L --> M[Reload halaman]
```

### Query

```sql
BEGIN TRAN;
UPDATE dbo.resep_obat_experiment
   SET experiment_status = 'Approved', updated_at = GETDATE(), updated_by = ?
 WHERE id = ?;
UPDATE dbo.resep_obat_experiment_group
   SET group_status = 'Approved',
       approved_experiment_id = ?,
       updated_at = GETDATE(), updated_by = ?
 WHERE id = ?;
COMMIT;
```

### Efek

- `experiment_status` = `Approved`.
- `group_status` = `Approved`.
- `approved_experiment_id` di group terisi experiment ini.
- Form Edit jadi read-only untuk user biasa.
- Tombol Delete experiment/group di-hide untuk user biasa.
- Tombol "+ Exp" di-hide untuk user biasa.

---

## 7. Alur Input Parameter Mesin Lab

```mermaid
flowchart TD
    A[User di view_resep] --> B{Klik Parameter Mesin Lab}
    B --> C[Modal #modalLabParam muncul]
    C --> D[Init Select2 mesin via ../get_machines.php]
    D --> E[User ketik / pilih mesin]
    E --> F[Select2 callback set #lab_machine_code dan #lab_machine_name]
    F --> G[User isi field numeric: Infra Red, WPU, dll]
    G --> H{User klik Simpan?}
    H -- Ya --> I[POST save_lab_param.php]
    I --> J{isApproved?}
    J -- Ya --> K[Gagal: locked]
    J -- Tidak --> L[UPSERT ke resep_obat_experiment_lab_param WHERE id_resep_experiment = X]
    L --> M[Response success]
    M --> N[Reload view_resep]
    H -- Tidak --> O[Tutup modal, tidak simpan]
```

### Tabel & Field

```sql
CREATE TABLE dbo.resep_obat_experiment_lab_param (
  id INT IDENTITY PRIMARY KEY,
  id_resep_experiment INT NOT NULL,
  machine_code VARCHAR(50),
  machine_name VARCHAR(200),
  infra_red DECIMAL(10,2),
  tekanan_padder DECIMAL(10,2),
  wpu DECIMAL(10,2),
  speed DECIMAL(10,2),
  fan1 DECIMAL(10,2),
  fan2 DECIMAL(10,2),
  temp_chamber_1 DECIMAL(10,2),
  temp_chamber_2 DECIMAL(10,2),
  lainnya NVARCHAR(MAX),
  created_at DATETIME,
  created_by VARCHAR(50),
  updated_at DATETIME,
  updated_by VARCHAR(50),
  CONSTRAINT FK_lab_param_experiment FOREIGN KEY (id_resep_experiment)
    REFERENCES dbo.resep_obat_experiment(id) ON DELETE CASCADE
);
```

### Source Mesin

`../get_machines.php` query ke `famaster` (koneksi3) dengan filter `facode LIKE 'FAM%'`. Response Select2 format:

```json
{
  "results": [
    { "id": "FAM0054", "text": "BAKAR BULU-2 (FAM0054)", "facode": "FAM0054", "faname": "BAKAR BULU-2" }
  ]
}
```

### Tampilan Ringkasan

```text
Mesin            : BAKAR BULU-2 (FAM0054)
Infra Red | Tekanan      | WPU
          | Padder       |
Speed     | Fan1         | Fan2
Temp      | Temp         |
Chamber 1 | Chamber 2    |
Lainnya   : ...
```

---

## 8. Alur Input Lab Data

```mermaid
flowchart TD
    A[User di view_resep] --> B{Klik Lab Data}
    B --> C[Modal #modalLabData muncul]
    C --> D[User isi Delta L/A/B/E + status manual]
    D --> E{User klik Simpan?}
    E -- Ya --> F[POST save_lab_data.php]
    F --> G{isApproved?}
    G -- Ya --> H[Gagal: locked]
    G -- Tidak --> I[UPSERT ke resep_obat_experiment_lab_data WHERE id_resep_experiment = X]
    I --> J[Response success]
    J --> K[Reload view_resep]
    E -- Tidak --> L[Tutup modal, tidak simpan]
```

### Tabel & Field

```sql
CREATE TABLE dbo.resep_obat_experiment_lab_data (
  id INT IDENTITY PRIMARY KEY,
  id_resep_experiment INT NOT NULL,
  delta_l DECIMAL(10,4),
  delta_a DECIMAL(10,4),
  delta_b DECIMAL(10,4),
  delta_e DECIMAL(10,4),
  status VARCHAR(20), -- Sukses/Gagal (manual, tidak auto)
  created_at DATETIME,
  created_by VARCHAR(50),
  updated_at DATETIME,
  updated_by VARCHAR(50),
  CONSTRAINT FK_lab_data_experiment FOREIGN KEY (id_resep_experiment)
    REFERENCES dbo.resep_obat_experiment(id) ON DELETE CASCADE
);
```

### Catatan

- Status `Sukses` / `Gagal` di tabel ini **tidak** mengubah `experiment_status` di `resep_obat_experiment`. User tetap harus update status lewat form Edit.
- Delta E **tidak** dihitung otomatis. User input manual (atau bisa dihitung di client-side nanti).

---

## 9. Alur Delete Experiment

```mermaid
flowchart TD
    A[User klik icon trash] --> B{CanDelete?}
    B -- Tidak --> X[Tombol hidden]
    B -- Ya --> C[isAdmin OR experiment.status != Approved?]
    C -- Tidak --> X
    C -- Ya --> D[Konfirmasi SweetAlert]
    D -- Batal --> Y
    D -- Ya --> E[POST delete_experiment.php resep_id=X]
    E --> F[Server: load experiment + group]
    F --> G{isAdmin?}
    G -- Tidak --> H{experiment.status = Approved OR group.status = Approved?}
    H -- Ya --> I[Gagal 403]
    H -- Tidak --> J
    G -- Ya --> J
    J --> K[BEGIN TRAN]
    K --> L[DELETE experiment]
    L --> M[Hitung sisa experiment in group]
    M --> N{Sisa = 0?}
    N -- Ya --> O[DELETE group]
    N -- Tidak --> P[Keep group]
    O --> Q[Response group_deleted = true]
    P --> R[Response group_deleted = false]
    Q --> S[Client redirect ke list_experiment]
    R --> T[Client redirect ke view_group]
```

### Cascading Delete

Foreign key `id_resep_experiment` di `resep_obat_experiment_detail`, `resep_obat_experiment_lab_param`, `resep_obat_experiment_lab_data` semua pakai `ON DELETE CASCADE`. Jadi sekali experiment dihapus, child rows otomatis terhapus.

---

## 10. Alur Delete Group

```mermaid
flowchart TD
    A[User klik trash di list_experiment] --> B{CanDelete?}
    B -- Tidak --> X
    B -- Ya --> C{isAdmin OR group.status != Approved && approved_experiment_id IS NULL?}
    C -- Tidak --> X
    C -- Ya --> D[Konfirmasi]
    D -- Batal --> Y
    D -- Ya --> E[POST delete_group.php group_id=X]
    E --> F[Server: load group + experiments]
    F --> G{isAdmin?}
    G -- Tidak --> H{approved_experiment_id NOT NULL?}
    H -- Ya --> I[Gagal 403]
    H -- Tidak --> J
    G -- Ya --> J
    J --> K[BEGIN TRAN]
    K --> L[Loop experiment in group: DELETE experiment]
    L --> M[FK CASCADE hapus detail + lab_param + lab_data]
    M --> N[DELETE group]
    N --> O[COMMIT]
    O --> P[Response success]
    P --> Q[Client reload DataTable]
```

---

## 11. Alur Tampilan Info Group

```mermaid
flowchart TD
    A[User buka view_group.php] --> B[Load semua experiment in group]
    B --> C{group.approved_experiment_id NOT NULL?}
    C -- Ya --> D[Cari experiment dengan id = approved_experiment_id]
    C -- Tidak --> E[displayExperiment = experiment terakhir]
    D --> F[displayInfo = data dari experiment tsb]
    E --> F
    F --> G[Tampilkan di card Info Group]
```

### Invarian

- `Info Group` di `view_group.php` **selalu menampilkan data dari experiment yang sudah di-approve**, kalau ada.
- Bila belum ada approved, tampilkan experiment **terbaru** (urut `experiment_seq` descending).
- Hal ini menjamin konsistensi: Info Group merefleksikan experiment final yang dipakai.

---

## 12. State Diagram Status

### Experiment

```mermaid
stateDiagram-v2
    [*] --> Draft: dibuat (tambah / next)
    Draft --> Gagal: edit / simpan status
    Draft --> Sukses: edit / simpan status
    Draft --> Approved: tombol Approve
    Gagal --> Draft: edit / simpan status
    Gagal --> Sukses: edit / simpan status
    Sukses --> Draft: edit / simpan status
    Sukses --> Gagal: edit / simpan status
    Sukses --> Approved: tombol Approve
    Approved --> [*]: (locked, hanya admin override)
```

### Group

```mermaid
stateDiagram-v2
    [*] --> Draft: group baru dibuat
    Draft --> Approved: experiment di-approve
    Approved --> [*]: locked (delete hanya admin)
```

---

## 13. Skema Database

```text
resep_obat_experiment_group
  id PK
  soi
  no_cp
  kode_grey
  mesin
  kode_warna
  color_name
  color_desc
  resep_prod_code
  resep_prod_name
  cus_color
  proint_resephdid        -- pointer ke proint untuk referensi
  group_status            -- Draft / Approved
  approved_experiment_id  -- FK ke resep_obat_experiment.id
  created_at, created_by
  updated_at, updated_by

resep_obat_experiment
  id PK
  group_id FK
  experiment_seq
  experiment_status       -- Draft / Gagal / Sukses / Approved
  experiment_note
  kode_grey, mesin, kode_warna, color_name, color_desc,
  resep_prod_code, resep_prod_name, cus_color, proint_resephdid,
  lot_no, weight, plan_qty, vlot
  created_at, created_by
  updated_at, updated_by

resep_obat_experiment_detail
  id PK
  id_resep_experiment FK  -- CASCADE
  kode, name, category
  receipe, uom, cf, uom_cf
  std_price, total
  is_manual               -- 1 = manual
  price_satuan, price_source
  created_at, created_by
  updated_at, updated_by

resep_obat_experiment_lab_param
  id PK
  id_resep_experiment FK UNIQUE -- CASCADE
  machine_code, machine_name
  infra_red, tekanan_padder, wpu, speed, fan1, fan2
  temp_chamber_1, temp_chamber_2, lainnya
  created_at, created_by
  updated_at, updated_by

resep_obat_experiment_lab_data
  id PK
  id_resep_experiment FK UNIQUE -- CASCADE
  delta_l, delta_a, delta_b, delta_e
  status (Sukses/Gagal manual)
  created_at, created_by
  updated_at, updated_by
```

### Relasi

```text
resep_obat_experiment_group 1 --- N resep_obat_experiment
resep_obat_experiment 1 --- 1 resep_obat_experiment_lab_param
resep_obat_experiment 1 --- 1 resep_obat_experiment_lab_data
resep_obat_experiment 1 --- N resep_obat_experiment_detail
resep_obat_experiment_group.approved_experiment_id --- 1 resep_obat_experiment.id
```

---

## 14. Aturan Permission

| Aksi | User Biasa | Administrator (GroupId=1) |
| --- | --- | --- |
| Lihat halaman | Butuh `CanView` | Butuh `CanView` |
| Tambah Group / Exp | Butuh `CanAdd` | Butuh `CanAdd` |
| Tambah Exp pada Group Approved | ❌ | ✅ (override) |
| Edit Experiment Draft/Gagal/Sukses | Butuh `CanEdit` | Butuh `CanEdit` |
| Edit Experiment Approved | ❌ | ✅ (override) |
| Delete Experiment Draft/Gagal/Sukses | Butuh `CanDelete` | Butuh `CanDelete` |
| Delete Experiment Approved | ❌ | ✅ (override) |
| Delete Group Draft | Butuh `CanDelete` | Butuh `CanDelete` |
| Delete Group Approved | ❌ | ✅ (override) |
| Approve Experiment | Butuh `CanEdit` | Butuh `CanEdit` |
| Input Lab Param / Data | User dengan `CanEdit` | Sama |
| Set Status ke Approved via form | ❌ (tidak ada di dropdown) | ❌ (tidak ada di dropdown) |

---

## 15. Edge Cases & Invarian

### Invarian

1. **1 group minimal 0 experiment**. Bila 0 experiment, group tidak ada artinya (biasanya langsung dibuat bersama experiment pertama).
2. **`experiment_seq` dalam 1 group harus unik dan urut 1, 2, 3, ...** tanpa gap.
3. **1 experiment hanya boleh punya 1 row** di `resep_obat_experiment_lab_param` dan `resep_obat_experiment_lab_data`. (Unique constraint di `id_resep_experiment`).
4. **`approved_experiment_id` di group harus valid** (merujuk ke experiment yang ada di group tsb). Foreign key constraint recommended.
5. **`group_status = Approved` iff `approved_experiment_id IS NOT NULL`**.
6. **Setelah approve, `experiment_status` harus `Approved`**.
7. **Total Cost / Meter** = `grand_total / plan_qty` (handle `plan_qty = 0` dengan return 0).
8. **Info Group** = data experiment approved (kalau ada) atau experiment terbaru.
9. **Status `Approved` TIDAK BISA di-set lewat form edit** (dropdown only Draft/Gagal/Sukses). Hanya lewat tombol Approve.

### Edge Cases yang ditangani

- **Batal "Buat Experiment Berikutnya"**: data template tidak tersimpan. ✅
- **Reload halaman "Buat Experiment Berikutnya"**: request ulang, tidak ada efek samping DB. ✅
- **Edit experiment approved oleh user biasa**: form read-only, tidak bisa save. ✅
- **Edit experiment approved oleh admin**: form editable, save berhasil. ✅
- **Delete experiment approved oleh user biasa**: tombol hidden. ✅
- **Delete experiment terakhir**: group ikut terhapus. ✅
- **Delete group approved**: tombol hidden untuk user biasa. ✅
- **Plan Qty = 0**: Total Cost / Meter tampil `Rp 0,00` (tidak divide by zero). ✅
- **Group tanpa approved_experiment_id**: status = "Draft" sampai experiment di-approve. ✅
- **Input lab param/data pada experiment approved**: form read-only, modal Simpan disabled. ✅
- **Select2 mesin di dalam modal**: pakai `dropdownParent: $('#modalLabParam')` agar dropdown tidak tertutup. ✅

### Edge Cases yang perlu monitoring

- **Concurrent edit** 2 user pada experiment yang sama: tidak ada locking. Last-write-wins.
- **Koneksi terputus saat simpan**: transaction rollback di SQL Server, tidak ada partial data.
- **Mesin tidak ditemukan di famaster**: Select2 empty state, user tidak bisa pilih.
- **Resep Prod Code tidak punya codeprod**: harga di detail resep kosong (`std_price = 0`).
