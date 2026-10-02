# Analisis Sistem Dokumen Pengingat (Revisi V2)

File ini berisi analisis mendalam tentang alur kerja aplikasi `Dokumen_pengingat` saat ini, kelemahannya, serta rancangan pengembangan untuk menjadikannya dinamis (seperti Form Builder) dan perbaikan sistem *reminder* sesuai permintaan terbaru.

---

## 1. Alur Aplikasi Saat Ini

Sistem saat ini dibangun dengan pendekatan *hardcoded* untuk 3 entitas dokumen secara spesifik: **Kontrak**, **Sertifikat**, dan **Surat Kendaraan**.

- **Dashboard (`index.php`)**: Menampilkan ringkasan status dokumen (Aktif, Reminder, Kadaluarsa, Total). Modul ini melakukan *query* ke 3 tabel yang berbeda secara terpisah (`dr_kontrak`, `dr_sertifikat`, `dr_surat_kendaraan`).
- **Modul CRUD Tersendiri**: Terdapat folder fisik terpisah (`kendaraan/`, `kontrak/`, `sertifikat/`) yang di dalamnya menyimpan logika *Create, Read, Update, Delete* (CRUD) yang hampir identik.
- **Sistem Pengingat (`cron/` & `services/`)**: File `reminder_runner.php` mengeksekusi `ReminderService.php`. Di dalamnya, terdapat 3 *method* berbeda yang mengecek interval kadaluarsa terhadap tabel masing-masing.
- **Input Kontak Notifikasi**: Pada saat menambah dokumen, pengguna diminta untuk menginput `email_reminder` dan `no_whatsapp` secara manual di setiap *record*.

---

## 2. Kekurangan Sistem Saat Ini

1. **Sangat Tidak Dinamis (Sulit Dikembangkan)**: Jika perusahaan butuh pengingat baru, *programmer* harus melakukan banyak langkah repetitif: membuat tabel baru, folder CRUD baru, memodifikasi `index.php`, `ReminderService.php`, dan lain-lain.
2. **Duplikasi Kode (*Code Smell: WET*)**: Karena 3 jenis dokumen dipisah foldernya, perbaikan satu bug harus dilakukan di 3 tempat berbeda.
3. **Risiko Karyawan Resign (Status Tidak Aktif)**: Karena nomor WA dan email melekat langsung pada data dokumen, jika karyawan yang bersangkutan *resign*, *reminder* akan tetap terkirim ke nomor pribadi mereka.
4. **Kesulitan Testing**: Mengetes berjalannya *reminder* harus menunggu tanggal *expired* dokumen mendekati hari H, yang menyulitkan proses *debugging*.
5. **Nomor Pengirim (Sender) Menggunakan Nomor Pribadi Dev/Admin**: Saat ini WA API yang digunakan adalah nomor pribadi yang dijadikan *bot*. Ini mengganggu privasi dan tidak profesional.

---

## 3. Rancangan Sistem Dinamis (V2)

Untuk mengatasi keterbatasan sistem *hardcoded*, sistem akan diubah menjadi **Dynamic Document Builder**.

### A. Arsitektur Database (Usulan)
1.  **`dr_categories`**: Menyimpan kategori dokumen (Kendaraan, Sertifikat, Kontrak, Legal, dll).
2.  **`dr_fields`**: Menyimpan definisi field untuk setiap kategori (Label, Tipe: text/date/file, Required).
3.  **`dr_documents`**: Tabel utama (Metadata: id, category_id, expire_date, status, created_by).
4.  **`dr_doc_values`**: Tabel EAV (Entity-Attribute-Value) untuk menyimpan data dari field dinamis.
5.  **`dr_doc_files`**: Tabel khusus lampiran (Mendukung multiple upload per dokumen).

### B. Daftar Referensi Dokumen (Enterprise Standard)
Berdasarkan riset, berikut dokumen yang akan diakomodasi oleh sistem dinamis:
- **Legal & Perizinan**: NIB, SIUP, IMB, Akta Notaris, Izin Lingkungan, Domisili.
- **SDM (HR)**: Kontrak Kerja (PKWT), Paspor/KITAS, Sertifikasi K3, SIO (Surat Izin Operator).
- **Aset & Fasilitas**: STNK, BPKB, Sertifikat Tanah, Kalibrasi Mesin, Polis Asuransi Aset.
- **Komersial**: MOU, Kontrak Vendor, Perjanjian Sewa, Lisensi Software.

### C. Pengaturan Dinamis (`pengaturan_pengingat.php`)
Halaman pengaturan saat ini masih *hardcoded* untuk 3 jenis dokumen. Pengembangan baru akan mencakup:
1.  **Daftar Interval Per Kategori**: Menampilkan form input jumlah hari pengingat secara otomatis berdasarkan kategori yang ada di database.
2.  **Konfigurasi API Terpusat**: Mempertahankan pengaturan SMTP dan WhatsApp API namun dengan integrasi yang lebih baik ke sistem pengiriman pesan dinamis.

### D. Keunggulan Sistem Baru
- **Scalable**: Bisa menambah jenis dokumen baru (misal: "Izin Ekspor") tanpa perlu membuat file PHP baru.
- **Centralized**: Semua pengingat dikelola oleh satu *cron job* yang sama.
- **Customizable**: Setiap departemen bisa memiliki field data yang berbeda-beda sesuai kebutuhan.

---

## 4. Langkah Implementasi
1.  **Fase 1**: Migrasi database ke struktur dinamis (Tetap mempertahankan data lama).
2.  **Fase 2**: Pembuatan modul "Form Builder" untuk Admin.
3.  **Fase 3**: Pembaruan halaman `master_dokumen.php` agar merender tabel secara dinamis berdasarkan kategori yang ada.
4.  **Fase 4**: Integrasi pengingat otomatis (Email/WA) yang lebih cerdas.
Hybrid (Dua Jalur)
Untuk menjaga keamanan data lama, kita tidak akan melakukan migrasi. Sistem akan berjalan dalam dua jalur secara berdampingan.

#### 1. Jalur Legacy (Tetap Seperti Sekarang)
- Data **Kendaraan, Kontrak, dan Sertifikat** tetap berada di tabel aslinya.
- Folder lama tetap berfungsi normal.
- Keuntungan: **Zero Risk** (Tidak ada resiko data hilang atau sistem lama error).

#### 2. Jalur Dinamis (Versi Baru - Form Builder)
- Jalur ini digunakan untuk semua jenis pengingat **BARU** di masa depan.
- **`dr_master_kategori`**: Tempat mendaftarkan kategori baru (misal: "Izin Kerja", "Pajak PBB", dll).
- **`dr_dokumen_universal`**: Satu tabel tunggal untuk menampung semua data dari kategori dinamis tersebut.
- **Modul `universal/`**: Satu folder CRUD yang tampilannya berubah-ubah sesuai kategori yang dipilih.

#### 3. Integrasi Monitoring (ReminderService)
- `ReminderService.php` akan dimodifikasi agar melakukan pengecekan secara paralel:
  - Cek 3 Tabel Lama (Logika yang sudah ada).
  - Cek 1 Tabel Universal Baru (Logika dinamis).
- Dengan begitu, notifikasi dari kedua versi tetap terkirim secara bersamaan tanpa bentrok.

### B. Validasi Status Karyawan Aktif (DONE)
- [x] Sistem sudah mengecek `m_emp` dan `SMUserMs` berdasarkan `created_by`.
- [x] Berlaku untuk kedua jalur (Legacy & Dinamis).

### C. Dev Menu & Testing (DONE)
- [x] `dev_reminder_test.php` sudah bisa menampilkan data dari semua tabel.

### D. Solusi WhatsApp Pengirim (PENDING)
- Disarankan menggunakan **Telegram Bot** untuk versi gratis yang tidak butuh nomor standby.
