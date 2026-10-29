# Changelog Issues Purchasing

## 2026-10-24

- Diterima pengguna: fidelity UI daftar aktif dan riwayat mengikuti pola `pages/issues` untuk badge berikon, aksi ber-tooltip, SweetAlert, detail terstruktur, dan DataTables responsif.
- Tetap membatasi fitur pada domain Purchasing; tidak menambahkan fitur IT-only.
- Tidak ada perubahan skema atau data database, serta tidak ada commit Git.

## 2026-10-24 — Perbaikan mutasi dan template

- Diterima pengguna: `get_issue_detail.php` menserialisasi `row_version` SQL Server menjadi token `0x` heksadesimal yang kompatibel dengan Update dan Delete.
- Create Issue baru menawarkan penyimpanan template pribadi setelah notifikasi SweetAlert selesai dan aksesibilitas shell dipulihkan.
- Browser menguji Create, Edit, dan Delete terkontrol; data uji dibersihkan kembali dan Console bersih.
- Tidak ada DDL atau commit Git. Ketidakselarasan kategori tanpa subkategori dicatat sebagai keputusan bisnis/migrasi terpisah.

## 2026-10-26 - Template dan counter kategori

- Diterima pengguna: memilih template sekarang mengisi `Issue Name` dari nama template.
- Tombol `Daftar aktif` pada Riwayat diposisikan melalui `card-tools`, sehingga terlihat pada header bertema dan dapat menavigasi kembali ke daftar aktif.
- Counter Kategori/Sub Kategori memisahkan referensi `Issue` dan `Template`; contoh data `tes` terbukti terdiri dari 1 Issue dan 2 Template, bukan tiga Issue.
- Tidak ada DDL, perubahan data, atau commit Git.

## 2026-10-26 - Pencarian template dan validasi kategori

- Diterima pengguna: modal Pilih Template memiliki pencarian nama, tipe, prioritas, status, dan deskripsi; tombol reset hanya satu, tanpa tombol clear bawaan browser yang duplikat.
- Subkategori wajib dipilih setelah kategori dipilih. Create, Edit, dan Save Template kini menjelaskan kesalahan pilihan rutin dengan label field, tanpa mengekspos pesan SQL atau ID internal kepada pengguna.
- Tidak ada DDL, perubahan data, staging, atau commit Git.

## 2026-10-26 - Export PDF dan aksi riwayat

- Diterima pengguna: Daftar Issues Purchasing dan Riwayat Issues Purchasing kini menyediakan Export PDF sesuai filter halaman dan scope server-side masing-masing.
- Endpoint PDF memakai `CanView`, validasi filter, query terparameterisasi, escaping HTML, mPDF, logging kegagalan aman, dan batas 5.000 baris.
- Riwayat kini menampilkan Ubah/Hapus sesuai hak akses. Kedua aksi memakai detail terbaru, CSRF, dan `row_version`; tidak ada perubahan pada aturan mutasi server.
- Tidak ada DDL, DML, staging, atau commit Git. Download file PDF aktual belum dapat diinspeksi oleh Chrome DevTools karena endpoint mPDF dibatalkan tooling sebagai download.
