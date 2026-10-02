# Sistem Multi-Stage Routing Paddry - Analisis & Panduan Pengembangan

Dokumen ini berisi analisis mendalam mengenai arsitektur baru sistem *Multi-Stage Routing* pada modul Eksekusi Celup Paddry dan berfungsi sebagai panduan (blueprint) untuk pengembangan serta pemeliharaan di masa mendatang.

---

## 1. Arsitektur Relasional (Database)

Sistem telah bermigrasi dari arsitektur penyimpanan tunggal/JSON menjadi arsitektur relasional menggunakan beberapa tabel utama di SQL Server untuk menangani multi-stage routing:

### 1.1 Tabel `dbo.cpp_paddry` (Tabel Induk)
Berfungsi menyimpan informasi *header* utama dari Production Header (CP), seperti nama mesin, nomor antrean, warna, dan material.

### 1.2 Tabel `dbo.cpp_paddry_stage` (Tabel Detail Routing)
Tabel baru yang bertugas menyimpan setiap tahapan routing secara terpisah. Setiap CP akan memiliki *multiple rows* di tabel ini.
Kolom-kolom kunci meliputi:
- `paddry_id`: Relasi (Foreign Key) ke tabel induk `cpp_paddry`.
- `cp_no`: Nomor dokumen CP.
- `stage_no` & `rtgseq`: Urutan tahapan pengerjaan (misal 1, 2, 3...).
- `rtgmsid` & `rtgname`: ID & Nama routing (misal: 876 - Penimbangan Obat Lab).
- `start_at` & `finish_at`: Waktu eksekusi lokal dari tahap tersebut.
- `fgresult`, `fail_code`: Status hasil (PASS/FAIL) dan kode jika gagal.

### 1.3 Tabel `dbo.cpp_paddry_downtime`
Pencatatan downtime tidak lagi secara eksklusif menempel pada CP utama saja, melainkan kini berhubungan langsung dengan tahapannya melalui kolom `paddry_stage_id`. 
Ini memastikan downtime bisa diisolasi dan dilaporkan spesifik per tahapan produksi.

---

## 2. Alur Proses (Workflow)

### 2.1 Resolusi Stage (Status)
Sistem menggunakan fungsi PHP `resolveStageView` yang membandingkan data aktual di lokal (`cpp_paddry_stage`) dengan data sinkronisasi di PostgreSQL (`pdproductionrtg`). Berdasarkan waktu mulai/selesai dan status kelulusannya (`fgresult`), tahapan akan mendapatkan salah satu dari status berikut:
- **waiting**: Belum ada waktu mulai/selesai.
- **running**: Terdapat waktu mulai namun belum ada waktu selesai.
- **done**: Sudah selesai dengan status "PASS" atau "P".
- **failed**: Sudah selesai namun berstatus "FAIL" atau "F".

### 2.2 Penentuan Current Stage (Blokir Logika)
Fungsi `determineCurrentStage` akan menentukan tahap mana yang sedang berjalan atau siap untuk dikerjakan selanjutnya dengan aturan:
1. Menemukan stage yang `failed`. Jika ada, seluruh proses terkunci.
2. Mencari stage yang `running`.
3. Memeriksa secara urut stage mana yang belum dikerjakan, dan memastikan stage *sebelumnya* sudah berstatus `done` (`is_passed`).
4. Jika user mencoba melewati (skip) tahapan, mereka akan dihadang oleh `blocked_message` ("Harus tembak routing X terlebih dahulu").

### 2.3 Sinkronisasi ERP (PostgreSQL)
Setiap aksi (Start, Stop, Downtime) yang dilakukan di Frontend akan langsung di sinkronisasikan ke `koneksi3.php` menggunakan fungsi `syncToERP`.
Sinkronisasi sekarang bersifat spesifik-tahap berdasarkan parameter `rtgmsid`, tidak lagi mengandalkan auto-discovery yang riskan. Backend mencari `productionrtgid` yang presisi di ERP.

---

## 3. Komponen UI (User Interface)

Perombakan antarmuka pada `index.php` secara otomatis menyesuaikan dengan tabel `stage`:
- **Stage Pill Navigasi (`stage-strip`)**: 
  Setiap *routing* ditampilkan sebagai pil (tab kecil). User dapat beralih untuk mengecek status dan waktu tiap tahapan.
- **Validasi Otoritas & Blocker**: 
  Tombol eksekusi (`btn-start`) divalidasi dengan dua gerbang:
  1. *Trustee check*: User Group harus diizinkan untuk `rtgmsid` yang aktif.
  2. *Sequence check*: Tahapan saat ini harus merupakan lanjutan valid dari tahapan sebelumnya.
- **Live Timers Ber-scope**:
  Timer dikelola secara unik per tahap (`timer_cp_rtgmsid`).

---

## 4. Panduan Pengembangan Kedepannya

### 4.1 Menambah Tahapan (Routing) Baru
Karena sistem kini sepenuhnya *database-driven* via relasional tabel `cpp_paddry_stage`, Anda **tidak perlu lagi merubah source code (hardcode JSON)** jika ada penambahan rtgmsid baru. Cukup pastikan script cronjob/filler yang mengisi (INSERT) data dari PostgreSQL ke SQL Server ikut menarik data stage baru tersebut ke dalam `cpp_paddry_stage`.

### 4.2 Manajemen Integritas Data Downtime
Saat menyisipkan atau mengekstrak data downtime, selalu gunakan patokan `paddry_stage_id` dan *bukan* hanya `paddry_id`. Hal ini berguna agar laporan variansi waktu bisa dipisahkan spesifik untuk setiap rtgmsid. Fungsi otomatis `stopOpenDowntimesForStage()` telah disiapkan untuk menutup otomatis downtime milik sebuah stage yang menggantung.

### 4.3 Fitur Rollback Tester
Logika pembatalan (`dev_rollback`) saat ini berjalan pada fungsi `resetStageLocalState()`. Fungsi ini akan mengenolkan (`NULL`) seluruh atribut *start*, *finish*, *user id*, dan status terkait pada ID Stage tersebut, serta membatalkan status ERP di tabel `pdproductionrtg`. 
Jika ada kolom baru yang ditambahkan di `cpp_paddry_stage` yang bersifat "hasil eksekusi", **wajib** dimasukkan ke dalam daftar set `NULL` di blok `resetStageLocalState`.

### 4.4 Modus Layar Penuh (Focus/TV Mode)
Pastikan selalu melakukan tes silang di TV/Fullscreen mode (`.focus-mode-active`) ketika mengedit antarmuka HTML dari kartu CP. Struktur UI stage-pill sangat memakan tempat di orientasi potrait/kotak, sesuaikan CSS *flex-wrap* jika tahapannya melonjak lebih dari 5 item di masa depan.
