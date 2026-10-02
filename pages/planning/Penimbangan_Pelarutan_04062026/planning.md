# Final Plan: Integrasi Data ke PostgreSQL (koneksi3)

Dokumen ini adalah versi final dari struktur data dan logika untuk proses _Start_, _Stop_, dan _Downtime_ dari aplikasi Paddry ke database PostgreSQL (`koneksi3.php`), berdasarkan skema asli tabel ERP.

## 🔑 Alur Pencarian Kunci Relasi (Foreign Keys)
Setiap kali tombol Start/Stop/Downtime ditekan, backend PHP akan melakukan:
1. `SELECT productionhdid FROM pdproductionhd WHERE prdnmbr = ?` (menggunakan `cp_no`).
2. Jika ada, `SELECT productionrtgid FROM pdproductionrtg WHERE productionhdid = ? AND rtgmsid = ?` (menggunakan ID routing dari grup saat ini).
3. `SELECT empid FROM muser WHERE ...` (Berdasarkan `$_SESSION['UserName']`).

---

## 🟢 1. Aksi: START CELUP

**1A. Update Tabel `pdproductionrtg`**
Kondisi: `productionrtgid` = ?

| Kolom PostgreSQL | Tipe Data | Nilai yang Diisi |
| :--- | :--- | :--- |
| `startdate` | `timestamp` | Tanggal hari ini (`Y-m-d 00:00:00`) |
| `starttime` | `timestamp` | Waktu saat ini (`Y-m-d H:i:s`) |
| `startshiftid` | `integer` | `shiftmsid` (dari UI) |
| `startinitiatorid`| `integer` | `empid` (User Login) |

**1B. Insert Tabel `pdresultpic`**
Mencatat siapa yang bertugas di routing ini.

| Kolom PostgreSQL | Tipe Data | Nilai yang Diisi |
| :--- | :--- | :--- |
| `compid` | `integer` | `2` |
| `productionhdid` | `integer` | Kunci Relasi |
| `productionrtgid`| `integer` | Kunci Relasi |
| `picseq` | `smallint`| _Auto (SELECT COALESCE(MAX(picseq), 0) + 1 ...)_ |
| `empid` | `integer` | `empid` (User Login) |
| `upddate` | `timestamp` | Waktu saat ini |
| `upduser` | `varchar` | `empid` |
| `updflag` | `varchar` | `'Y'` |
| `resulthdid` | `integer` | `NULL` |
| `fgpictype` | `varchar` | `NULL` |
> *Catatan: Kolom `resultpicid` (Primary Key) tidak di-insert dan dibiarkan otomatis diisi oleh sequence bawaan PostgreSQL.*

---

## 🛑 2. Aksi: STOP CELUP (Finish)

**2A. Update Tabel `pdproductionrtg`**
Kondisi: `productionrtgid` = ?

| Kolom PostgreSQL | Tipe Data | Nilai yang Diisi |
| :--- | :--- | :--- |
| `enddate` | `timestamp` | Tanggal hari ini (`Y-m-d 00:00:00`) |
| `endtime` | `timestamp` | Waktu saat ini (`Y-m-d H:i:s`) |
| `endshiftid` | `integer` | `shiftmsid` (dari UI) |
| `initiatorid` | `integer` | `empid` (User Login) |
| `fgstatus` | `varchar` | `'x'` |
| `prdqty` | `numeric` | `3119` (Sesuai kesepakatan hardcode) |
| `prduomid` | `integer` | `83` |
| `prdstdqty` | `numeric` | `3119` |
| `prdstduomid` | `integer` | `83` |
| `fgresult` | `varchar` | `'P'` (Pass) / `'F'` (Fail) dari UI |
| `failmsid` | `integer` | `failmsid` dari UI (Hanya jika Fail) |
| `resultdesc` | `text` | Keterangan Fail (Hanya jika Fail) |
| `upddate` | `timestamp` | Waktu saat ini |
| `upduser` | `varchar` | `empid` |

**2B. Insert Tabel `pdproductionsum`**

| Kolom PostgreSQL | Tipe Data | Nilai yang Diisi |
| :--- | :--- | :--- |
| `productionrtgid`| `integer` | Kunci Relasi |
| `compid` | `integer` | `2` |
| `wheelno` | `varchar` | Nomor Mesin (dari UI) |
| `wheelmsid` | `integer` | `wheelmsid` (Diambil dari koneksi3 berdasarkan `wheelno`) |
| `lebarkain` | `numeric` | Inputan UI |
| `prdtemp`, `prdtempfinish`, `prdspeed`, `prdspeedfinish`, `cutfinish`, `cutwidthact`, `prdbar`, `vlotact`, `sisalar`, `tottopping`, `sisasaturator`, `leftlisting`, `middlelisting`, `rightlisting`, `leftlistingmiring`, `middlelistingmiring`, `rightlistingmiring` | `numeric` | `0` |
| `be` | `smallint`| `0` |
| `cutpcs`, `topping`, `skewing` | `varchar` | `NULL` |
| `grademsid` | `integer` | `NULL` |
| `fglar3ltr`, `uselar3ltr` | `varchar` | `'N'` |
| `toppinguom` | `varchar` | `'gr/lt'` |
| `upddate` | `timestamp` | Waktu saat ini |
| `upduser` | `varchar` | `empid` |
| `updflag` | `varchar` | `'Y'` |
> *Catatan: Kolom `productionsumid` diabaikan agar menggunakan default sequence PostgreSQL.*

---

## ⏸️ 3. Aksi: DOWNTIME (Start & Stop)

Downtime baru di-insert ke PostgreSQL **hanya ketika downtime tersebut di-stop** (karena kita baru tahu durasi total `downstart` sampai `downend`).

**3A. Insert Tabel `pdresultdown`**

| Kolom PostgreSQL | Tipe Data | Nilai yang Diisi |
| :--- | :--- | :--- |
| `compid` | `integer` | `2` |
| `productionhdid` | `integer` | Kunci Relasi |
| `productionrtgid`| `integer` | Kunci Relasi |
| `downseq` | `smallint`| _Auto (SELECT COALESCE(MAX(downseq), 0) + 1 ...)_ |
| `downtimemsid` | `integer` | ID delay dari UI |
| `downstart` | `varchar` | Waktu Start Downtime |
| `downend` | `varchar` | Waktu Stop Downtime |
| `downtime` | `numeric` | Total durasi (Kolom `downtime` di database, bukan durasi hitungan UI) |
| `downdesc` | `text` | Keterangan |
| `upddate` | `timestamp` | Waktu saat ini |
| `upduser` | `varchar` | `empid` |
| `updflag` | `varchar` | `'Y'` |

---

## 🚀 Langkah Implementasi Berikutnya (Execution)
Jika Anda sudah menyetujui _final plan_ ini, saya akan mulai menulis kode di dalam `api_aktual_paddry.php` untuk:
1. Memperbarui method `get_references` agar mengirim MSID ke frontend.
2. Memperbarui JS di `index.php` agar mengirimkan `rtgmsid`, `failmsid`, dll ke backend.
3. Menulis fungsi `sync_to_erp()` menggunakan `PDO` untuk melakukan insert/update berantai ke `koneksi3.php` sesuai dengan skema di atas tanpa merusak data yang sudah ada.
