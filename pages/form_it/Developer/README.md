# Modul Form IT

## Tujuan

Modul mengelola pengajuan layanan IT, persetujuan tanda tangan, penolakan, detail, dan PDF. Dokumen ini fokus pada flow Pembuatan Aplikasi, Pemindahan Kamera CCTV, dan Penambahan Gudang Baru ERP.

## Form Pengajuan Pembuatan Aplikasi

- Tiket `APP-YYYYMMDD-NNN`.
- Persetujuan: Pemohon, Atasan Pemohon, Petugas IT, Kabag IT, Kadept IT.
- Tabel `Form_Pengajuan_Aplikasi`.

## Form Pemindahan Kamera CCTV

- Form type `pemindahan_kamera_cctv`; tiket `CCTV-P-DDMMYYYY-NNN`.
- Menyimpan data pemohon, area, alasan, data pengerjaan Petugas CCTV, dan lampiran sebelum/setelah.
- Tabel `Form_Pemindahan_CCTV`.
- File khusus: `form_contents/form_pemindahankameracctv.php`, `detail_pemindahan_cctv.php`, dan branch dual-table pada `generate_pdf_cctv.php`.
- Upload maksimum 5 MB; extension dan MIME server wajib cocok.

## Form Penambahan Gudang Baru ERP

- Form type `penambahan_gudang_baru_erp`; tiket `GDG-DDMMYYYY-NNN`.
- Menyimpan identitas pemohon, nama gudang, user akses, dan alasan.
- Persetujuan: Pemohon, Atasan Pemohon, satu slot Kabag/Kadept IT, dan Direksi.
- Tabel `Form_Penambahan_Gudang_Baru_ERP`.
- File khusus: `form_contents/form_penambahan_gudang_baru_erp.php`, `detail_penambahan_gudang_baru_erp.php`, dan `generate_pdf_penambahan_gudang_baru_erp.php`.

## Integrasi Bersama

`load_form.php`, `load_data.php`, `list_form.php`, dan `list_form_serverside.php` menangani routing, daftar, visibility, detail, edit, dan PDF. `proses_simpan.php`, `proses_update.php`, `proses_delete.php`, dan `proses_reject.php` menangani lifecycle. `get_ttd_count.php`, `ttd_sign.php`, dan `ttd_save_template.php` menangani role, progres, dan transisi status.

## Database

Koneksi memakai `koneksi.php` dan `$conn` untuk SQL Server `GG`. Metadata live diverifikasi 31 Juli 2026:

- `Form_Pemindahan_CCTV`: 22 kolom lengkap, unique ticket, 0 row saat audit.
- `Form_Penambahan_Gudang_Baru_ERP`: 19 kolom lengkap, unique ticket, 3 row saat audit.
- Tidak dibutuhkan migration atau DDL runtime.
- TTD memakai `Form_Pengajuan_Barang_TTD` berdasarkan `Ticket`.

## Keputusan Integrasi

- Folder `form_it_teman` tidak disalin penuh; backup dan file stale tidak masuk modul aktif.
- Kredensial lokal dibuang; endpoint aktif memakai `koneksi.php`.
- `ALTER TABLE` saat request dibuang karena schema live lengkap.
- Prefix `CCTV-P-` diperiksa sebelum `CCTV-` agar tabel tidak salah.
- DOM ID detail baru memakai namespace fitur; header modal memakai variable theme existing.
- Tombol Simpan di `list_form.php` menjadi satu-satunya pemilik submit dua form baru; form content hanya menginisialisasi field.
- Form bertanda tangan menyimpan data utama via AJAX, lalu menghubungkan TTD Pemohon memakai ticket hasil create.
- Kegagalan penyimpanan TTD tidak boleh dianggap sukses; UI menampilkan bahwa pengajuan tersimpan tetapi TTD perlu diulang lewat Detail.

## Status Saat Ini

Static integration, PHP lint, database metadata, bounded row checks, routing, dan risk scan selesai. Tiga tiket Gudang ERP existing tidak memerlukan migrasi data.

## Masalah Diketahui

- Pemindahan CCTV masih 0 row; flow penuh perlu tiket uji.
- Browser verification masih perlu dijalankan untuk bukti click, Console, Network, duplicate DOM ID, dan dua theme.
- Read-only source self-check dan JavaScript syntax gate tidak menggantikan verifikasi browser.
- Write-side API check belum dijalankan agar database bersama tidak berubah tanpa data uji.
- Script migration legacy di root modul masih memiliki koneksi lama; script tersebut bukan endpoint runtime integrasi.

## Verifikasi

- Jalankan `php -l`, `git diff --check`, dan scan kredensial/DDL runtime.
- Ekstrak setiap inline `<script>` file tersentuh dan jalankan `node --check`; `list_form.php` harus menghasilkan enam blok valid.
- Focused self-check wajib menemukan selector GDG/CCTV-P, route GDG, modal dependency guard, tanpa native submit lokal, dan tanpa false-success TTD.
- Uji create, edit, detail, role TTD, reject, delete, PDF, theme, duplicate ID, Console, dan Network.
- Gunakan tiket uji khusus untuk write-side verification.
