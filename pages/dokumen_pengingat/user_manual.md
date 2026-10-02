# Buku Panduan Penggunaan
## Sistem Pengingat Dokumen Dinamis V2 (Hybrid)

Sistem ini memungkinkan Administrator untuk membuat jenis/kategori dokumen baru secara mandiri tanpa memerlukan perubahan koding atau intervensi dari tim IT.

---

### A. Cara Membuat Kategori Dokumen Baru
Fitur ini digunakan jika perusahaan membutuhkan pelacakan dokumen baru (misalnya: *Izin Lingkungan*, *Sertifikat ISO*, *Kontrak Vendor*, dll).

1. Buka Halaman **Pengaturan & Pengingat**.
2. Scroll ke bagian bawah pada kotak **Sistem Dinamis V2**, lalu klik tombol **"Kelola Kategori & Kolom"**.
3. Di halaman Manajemen Kategori, klik tombol **"Tambah Kategori Baru"**.
4. Isi data berikut:
   - **Nama Kategori**: (Contoh: *Izin Lingkungan*)
   - **Ikon**: (Pilih ikon FontAwesome, contoh: `fas fa-leaf`)
   - **Warna Tema**: (Pilih warna latar untuk tab kategori tersebut)
   - **Interval Pengingat Default**: (Berapa hari sebelum *expire* sistem harus memberikan notifikasi kuning/warning).
5. Klik **Simpan Kategori**.

---

### B. Cara Mengatur Kolom Form (Field Management)
Setiap dokumen pasti memiliki kebutuhan isian (*field*) yang berbeda. Setelah membuat kategori, Anda wajib mendefinisikan kolom formnya.

1. Di halaman Manajemen Kategori, klik tombol **"Atur Kolom"** pada kategori yang diinginkan.
2. Anda akan melihat struktur data dari kategori tersebut.
3. Klik tombol **"Tambah Kolom Baru"**.
4. Lengkapi isian:
   - **Label Kolom**: Nama yang akan dibaca oleh user (Contoh: *Nama Instansi*).
   - **Nama Field**: Nama teknis untuk database, gunakan huruf kecil tanpa spasi (Contoh: `nama_instansi`).
   - **Tipe Data**:
     - *Text*: Untuk inputan pendek.
     - *Textarea*: Untuk inputan panjang (paragraf).
     - *Number*: Khusus angka.
     - *Date*: Khusus tanggal.
   - **Wajib Diisi**: Aktifkan jika user tidak boleh mengosongkan kolom ini.
   - **Tampil di Tabel**: Aktifkan jika Anda ingin kolom ini muncul di daftar utama pada halaman depan *Master Dokumen*.
5. Klik **Simpan Kolom**.
> **Catatan:** Sistem secara otomatis telah menambahkan kolom *Tanggal Expire* dan *Upload File (PDF)*. Anda tidak perlu membuat ulang kedua kolom tersebut.

---

### C. Cara Mengakses dan Menggunakan Modul Dokumen
Setelah kategori dan kolom siap, kategori tersebut akan langsung muncul di halaman utama.

1. Buka menu **Master Dokumen Reminder** dari Sidebar.
2. Anda akan melihat Tab baru sesuai dengan nama kategori yang dibuat (misal: Tab *Izin Lingkungan*), bersampingan dengan tab lama (Kendaraan, Kontrak, dll).
3. Klik Tab tersebut.
4. Klik **Tambah [Nama Kategori]** untuk memasukkan data baru. Form yang muncul akan **menyesuaikan secara otomatis** dengan kolom yang sudah Anda atur di tahap B.
5. Setelah data tersimpan, data tersebut akan dikelola menggunakan aturan Pengingat (Reminder) terpusat, lengkap dengan status Aktif, Reminder, dan Expired.

---

### D. Melihat Ringkasan di Dashboard
Halaman utama Dashboard (`index.php`) akan secara otomatis mengkalkulasi dan menampilkan jumlah dokumen aktif, mendekati *expire* (reminder), dan sudah *expire* untuk setiap kategori, baik kategori lama maupun kategori baru yang dibuat secara dinamis.
