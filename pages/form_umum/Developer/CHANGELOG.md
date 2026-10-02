# Changelog - Form Umum Module

## [Unreleased] - 2026-08-15

### Fixed
- Pemisahan role signing dan database UPSERT antara `Kadept` (universal untuk modul IKS/IKP/IPC) dengan `Kadept ACC` (khusus modul Buka Tanggal Closingan) pada `ttd_sign.php` dan `delete_ttd.php`.
- Menambahkan fallback toleransi pada `generate_pdf_buka_tanggal_closingan.php` agar PDF tetap merender tanda tangan pada tiket legacy.

## [Unreleased] - 2026-08-14

### Added
- Feature short barcode token 6-karakter Base32 (`Form_Umum_Barcode_Token`) untuk tiket IKS/IKP/IPC agar barcode Code 39 berukuran ringkas dan muat dicetak di printer dot-matrix Epson TM-U220 (76mm).
- Helper server-side `barcode_token_helper.php` untuk pembuatan token otomatis dan resolusi token ke tiket asli.
- Akses publik tanpa login (`$scanPublicAccess`) pada `scan_index.php` dan `scan_action.php` agar petugas Satpam dapat memindai dan mengonfirmasi status keluar/kembali tanpa kendala sesi login.
- Dokumentasi pengembangan module di `pages/form_umum/Developer/README.md`.

### Fixed
- Perbaikan generator SVG Code 39 (`generate_barcode_form_umum.php`) menggunakan tabel enkodifikasi 9-elemen standar ISO/IEC 16388 resmi, quiet zone (margin 20px), dan gap inter-karakter 2px sehingga 100% dapat di-scan oleh scanner optik, aplikasi kamera HP, scanner online, dan scanner fisik Satpam.
- Validasi client-side `scan_index.php` dan handler server-side `scan_action.php` untuk mendukung pemindaian langsung token barcode 6 karakter.
- Perbaikan pencatatan `created_by` pada pembuatan token barcode dengan membaca `$_SESSION['UserName']` / `$_SESSION['NamaLengkap']`.
