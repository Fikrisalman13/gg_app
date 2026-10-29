# Rencana: Aktivitas Serah Terima Purchasing menjadi Issue

Status: implementasi sedang divalidasi. Migrasi DDL SQL Server `GG` dieksekusi setelah persetujuan eksplisit pengguna.

## Fitur Done oleh pembuat Issue 2026-10-27

### Keputusan dan batas scope

- Pengguna menyetujui checklist hijau seperti referensi `pages/issues/list_issues.php`.
- Checklist hanya tersedia untuk pembuat Issue yang sama; admin tidak memiliki bypass.
- Mutasi mengubah Issue aktif menjadi `Done`, mengisi `tanggal_selesai`, `update_by`, dan `update_at`. Issue lalu masuk Riwayat melalui filter status yang telah ada.
- Tidak ada perubahan pada Serah Terima Purchasing maupun `purchasing_issue_activity_links`.

### Triase Due Date form Tambah 2026-10-27

- Scope terbatas pada modal Tambah di `list_issues.php`: Due Date harus otomatis menjadi tanggal lokal saat tombol Tambah ditekan, namun elemen `input[type=date]` tetap dapat diedit pengguna.
- Tidak ada endpoint, SQL, status, akses, atau data tersimpan yang berubah. Form Edit tetap memuat Due Date Issue yang sudah ada dan tidak boleh ditimpa default.
- Risiko langsung: memakai UTC dapat mengisi tanggal yang salah di sekitar tengah malam. Default dihitung dari kalender lokal browser ketika form Tambah di-reset.
- [x] Validasi: `php -l` halaman lulus; fragmen helper tanggal lulus `node --check`; Chrome membuka modal Tambah dengan nilai `2026-10-27` (hari ini), lalu input dapat diubah menjadi `2026-10-28` tanpa submit. Modal dibatalkan dan Console bersih.

### Audit alur dan invariants

- Daftar aktif sudah memfilter status selain `Done`; Riwayat sudah memfilter `Done`, sehingga transisi tidak membutuhkan perubahan skema atau query scope.
- Tabel memiliki `row_version`, `tanggal_selesai`, `update_by`, dan `update_at`; `get_issue_detail.php` telah mengirimkan rowversion kanonis.
- Otorisasi tidak boleh hanya memakai tombol: endpoint memeriksa POST, login/menu `CanView`, CSRF, identitas profil aktif, pembuat tersimpan, status aktif, dan rowversion dalam transaksi.
- Retry atau dua klik: update dipredikatkan pada status aktif dan rowversion yang sama; satu request sukses, request berikutnya mendapat konflik tanpa mengubah data lagi.
- Identitas pembuat pada Issue berasal dari snapshot nama profil, sehingga server membandingkan nama profil aktif hasil lookup master, bukan nilai JavaScript atau input klien.

### Checklist kualitas sebelum edit

| Target | Tanggung jawab dan struktur | Kualitas yang diverifikasi |
| --- | --- | --- |
| `pages/Issues_purchasing/mark_issue_done.php` | Endpoint POST creator-only dengan CSRF, ownership, rowversion, transaksi, dan respons aman. | PHPDoc Bahasa Indonesia, SQL parameterized, semua statement dibebaskan, rollback semua kegagalan, request ID untuk kegagalan teknis, lint dan uji respons. |
| `pages/Issues_purchasing/issues_serverside.php` | Mengirim flag `can_mark_done` yang dihitung server untuk daftar aktif. | Tidak mengekspos identitas tambahan, query tetap bounded/paginated, lint dan respons DataTables. |
| `pages/Issues_purchasing/list_issues.php` | Merender tombol hijau, SweetAlert konfirmasi, penguncian klik, dan reload tabel. | Tidak ada ID duplikat, tombol scoped `data-action`, fokus/konsol/network diuji, Node check blok inline. |

### Implementasi dan validasi

- Endpoint baru `mark_issue_done.php` menerima `POST` saja. Ia menjalankan transaksi SQL Server, mengunci baris Issue, memeriksa pembuat dari profil aktif terhadap snapshot `created_by`, lalu hanya mengubah status aktif dengan `row_version` yang sama.
- `issues_serverside.php` menghitung flag `can_mark_done` di server. Daftar hanya merender checklist hijau untuk pembuat Issue aktif; hak Edit/Delete tidak dipakai sebagai bypass kepemilikan.
- `list_issues.php` mengambil detail terbaru terlebih dahulu, menampilkan konfirmasi SweetAlert, kemudian mengirim ID, rowversion, dan CSRF. Tombol dinonaktifkan saat proses dan tabel dimuat ulang setelah respons sukses.
- [x] PHP syntax: `php -l` lulus untuk endpoint baru, endpoint daftar, dan halaman daftar.
- [x] JavaScript syntax: fragmen handler checklist lulus `node --check`.
- [x] Static/source-quality: SQL terparameterisasi, transaksi/rollback diperiksa, PHPDoc Bahasa Indonesia tersedia, diff whitespace scoped serta scan trailing whitespace lulus.
- [x] Browser aman: daftar aktif memuat flag daftar dari server tanpa Console error; endpoint GET mengembalikan `405` + `Allow: POST`, dan POST tanpa CSRF mengembalikan `403` dengan pesan aman. POST valid-CSRF terhadap Issue aktif milik pembuat lain juga mendapat `403 Hanya pembuat Issue yang dapat menandai selesai.`; reload berikutnya Console bersih dan kedua Issue tersebut tetap tampil aktif. Pemindaian DOM menemukan tidak ada ID duplikat; pada sesi ini tidak ada tombol Done karena seluruh Issue aktif milik pembuat lain.
- [!] Uji sukses end-to-end POST perlu bukti request `200` yang secara pasti terkait tombol ini. Ketika verifikasi dilanjutkan, data uji `tessss` sudah tidak ada di daftar aktif maupun Riwayat sehingga transisi sukses tidak dapat diatribusikan dengan aman.

## Revisi bug penghapusan Issue aktivitas 2026-10-27

### Bukti dan akar masalah

- Pengguna melaporkan Hapus Issue menghasilkan respons aman dengan ID permintaan `897a43f52e7a3c6c0041503e`.
- Log aplikasi mencatat kegagalan endpoint pada tahap `delete`; tidak ada detail SQL yang ditampilkan kepada pengguna.
- Pemeriksaan read-only pada `dbo.purchasing_issue_activity_links` menemukan Issue `34` masih terhubung ke Header Serah Terima dan item PO.
- Migrasi menetapkan FK link ke Issue dengan perilaku `NO ACTION`. Endpoint lama menghapus `purchasing_issues` secara langsung, sehingga SQL Server menolak penghapusan Issue yang masih memiliki link.

### Keputusan perbaikan

- Hapus Issue yang dipilih menghapus link aktivitasnya dan Issue tersebut dalam satu transaksi SQL Server.
- Header, detail, dan item Serah Terima tidak dihapus atau dimodifikasi. Ini tetap memenuhi keputusan bahwa perubahan sumber tidak menyinkronkan snapshot Issue.
- Predicate `row_version` tetap menjadi syarat penghapusan. Jika versinya basi atau Issue tidak ada, transaksi di-rollback sehingga link tidak putus.

### Checklist kualitas sebelum edit

| Target | Tanggung jawab dan struktur | Kualitas yang diverifikasi |
| --- | --- | --- |
| `pages/Issues_purchasing/delete_issue.php` | Endpoint POST Hapus Issue; urutan transaksi: validasi akses/CSRF/rowversion, hapus link, hapus Issue, commit/rollback. Tidak menambah route atau UI baru. | Nama variabel eksplisit, satu statement per baris, PHPDoc fungsi tetap relevan, SQL parameterized, respons aman/log request ID, `php -l`, diff-check, dan uji endpoint/browser bila koneksi Chrome tersedia. |

### Audit delta

- Entry point mutasi hanya `delete_issue.php`; klien mengirim `issue_id`, `row_version`, dan CSRF melalui `list_issues.php`.
- Invariant: satu Issue aktivitas memiliki paling banyak satu link; Issue hanya boleh terhapus bila rowversion masih sesuai; sumber Serah Terima tetap ada.
- Risiko terukur: link dihapus terlebih dahulu tetapi delete Issue gagal. Mitigasi: kedua `DELETE` berada dalam satu transaksi dan semua cabang gagal melakukan rollback.
- Uji browser Chrome DevTools MCP masih blocked: proses Chrome lain memegang profil `chrome-devtools-mcp`. Tidak ada edit browser yang dilakukan.

### Validasi revisi delete

- [x] PHP syntax: `php -l pages/Issues_purchasing/delete_issue.php` lulus.
- [x] Static/source-quality: blok transaksi, parameter SQL, rollback semua jalur gagal, PHPDoc, dan format satu statement per baris diperiksa ulang.
- [x] Diff whitespace scoped: perbandingan terhadap baseline tidak melaporkan whitespace error; hanya peringatan normalisasi LF/CRLF dari Git. `task.md` global memiliki trailing whitespace warisan di luar scope.
- [x] Runtime/browser: Chrome DevTools MCP membuka daftar, memuat detail Issue aktivitas `34` dengan HTTP `200`, serta menampilkan konfirmasi Hapus. Pengguna kemudian mengonfirmasi penghapusan aktual berhasil pada 2026-10-27. Ini membuktikan jalur link aktivitas → Issue dapat dihapus; sumber Serah Terima tidak termasuk dalam operasi endpoint.
- [!] Konflik rowversion, kegagalan commit, dan rollback tidak diuji dengan fixture khusus agar tidak mengubah data bisnis tambahan.

## Revisi bug token multi-dokumen 2026-10-27

### Bukti dan akar masalah

- Pengguna melaporkan Simpan dua dokumen menghasilkan pesan `Permintaan dokumen tidak valid` sebelum transaksi database dimulai.
- Chrome DevTools MCP membuktikan satu baris awal memiliki hidden `items[0][activity_request_id]` dengan UUID valid.
- Setelah tombol **Tambah Baris Dokumen** diklik, kueri DOM untuk seluruh `.purchasing-activity-request` menghasilkan daftar kosong.
- `reindexRows()` memakai `.text(idx + 1)` pada sel `.row-num`. jQuery mengganti seluruh isi sel, sehingga hidden token di dalam sel ikut terhapus.

### Perbaikan dan audit delta

- Nomor baris dipisahkan ke elemen `.row-number`; reindex hanya memperbarui elemen angka itu dan tidak pernah mengganti isi sel pembungkus.
- Invariant: setiap baris dokumen memiliki tepat satu UUID yang valid; penambahan/penghapusan/reindex baris tidak mengubah nilai UUID pada baris yang tetap ada.
- Tidak ada perubahan endpoint, transaksi, hak akses, atau skema database.

### Checklist kualitas sebelum edit

| Target | Tanggung jawab dan struktur | Kualitas yang diverifikasi |
| --- | --- | --- |
| `pages/purchasing/purchasing.php` | Renderer baris dokumen dan reindex client-side; angka baris dan token idempoten dipisahkan agar tidak saling menimpa. | Nama class spesifik, markup/JS tetap mudah dibaca, tidak ada ID baru, Node check inline script, PHP lint, diff-check, dan Chrome test penambahan dua baris serta submit failure/success aman. |

### Validasi revisi token multi-dokumen

- [x] Repro browser sebelum perbaikan: satu token UUID ada pada baris pertama; klik Tambah Baris Dokumen menghapus seluruh token dari DOM.
- [x] Regression browser sesudah perbaikan: dua baris menghasilkan dua UUID valid pada `items[0][activity_request_id]` dan `items[1][activity_request_id]`; setelah baris pertama dihapus, token baris tersisa tetap ada dan dinormalisasi menjadi `items[0][activity_request_id]`.
- [x] PHP syntax: `php -l pages/purchasing/purchasing.php` lulus.
- [x] JavaScript syntax: fragmen fungsi `reindexRows()` yang diubah lulus `node --check`.
- [x] Static/source-quality: markup pemisah angka/token serta blok reindex diperiksa ulang; tidak ada perubahan route, endpoint, maupun query.
- [x] Diff whitespace scoped: perbandingan baseline tidak melaporkan whitespace error; hanya peringatan normalisasi LF/CRLF dari Git.
- [!] Simpan multi-dokumen sukses belum dieksekusi oleh agen karena akan membuat Header, Issue, dan link bisnis nyata. Pengujian pengguna diperlukan setelah reload halaman.

## Audit metadata 2026-10-27

- Read-only metadata SQL Server mengonfirmasi `purchasing_header.id` dan `purchasing_po_items.id` bertipe `bigint`, sementara `purchasing_issues.issue_id` bertipe `int`; `purchasing_issues.request_id` sudah `uniqueidentifier` dengan index unik filtered per pembuat.
- `purchasing_header` belum memiliki token idempoten sumber dan belum ada tabel link aktivitas, sehingga keduanya tetap diperlukan untuk retry aman.
- Query read-only mengonfirmasi kategori aktif `Serah Terima Dokumen` tersedia. Subkategori aktif yang tersedia adalah `Dokumen PO`, `Dokumen COD`, `Dokumen RFP`, `Dokumen Offset`, dan `Dokumen Kontrabon`.
- Koreksi rencana: integrasi melakukan lookup kategori/subkategori tersebut berdasarkan nama; tidak ada seed master kategori maupun subkategori yang diperlukan.
- Migrasi `20261027_add_issue_activity_integration.sql` telah dieksekusi pada SQL Server `GG`: kolom `activity_request_id` bertipe `uniqueidentifier` dan index unik filtered tersedia; tabel `purchasing_issue_activity_links` beserta key unik/FK tersedia. Tidak ada seed DML.
- Implementasi source: `action=add` kini mewajibkan CSRF dan token idempoten per baris; Header, Detail, PO Item, Issue, dan link dibuat pada transaksi yang sama. Pengujian sukses/rollback nyata belum dijalankan agar tidak membuat fixture database tanpa persetujuan khusus.

## Tujuan yang disepakati

Saat pengguna menyimpan Serah Terima Purchasing, sistem membuat Issue Purchasing sebagai snapshot aktivitas.

- Untuk Type `PO`, satu nomor PO menghasilkan satu Issue.
- Nama Issue PO: `Membuat Serah Terima Dokumen PO {nomor_po}`.
- Untuk Type selain PO, satu baris dokumen menghasilkan satu Issue dengan pola `Membuat Serah Terima Dokumen {type} {nomor_po}`.
- Kategori selalu `Serah Terima Dokumen`; subkategori dipetakan dari Type menjadi `Dokumen PO`, `Dokumen COD`, `Dokumen RFP`, `Dokumen Offset`, atau `Dokumen Kontrabon`.
- Type Issue: `Task`.
- Status awal: `In Progress`.
- Priority: `Medium`.
- Due Date: sama dengan Tanggal Serah Terima.
- Issue adalah catatan aktivitas pada saat dibuat. Edit atau hapus Serah Terima tidak memperbarui atau menghapus Issue.

## Pemetaan deskripsi

Setiap Issue akan menyimpan snapshot yang dapat dibaca, bukan JSON mentah.

```text
Nominal / Description: {nilai dari baris PO atau Description}
Nama Supplier: {supplier}
No. GRN: {no_grn atau -}
Uraian Berkas:
- {uraian lampiran 1}
- {uraian lampiran 2}
```

Untuk Type `PO`, Description/Nominal, Supplier, dan GRN diambil dari masing-masing `purchasing_po_items`; Uraian Berkas diambil dari lampiran pada header. Untuk Type lain, data diambil dari `purchasing_header` dan `purchasing_detail`.

Jika hasil Description melebihi batas kolom Issue (saat ini divalidasi maksimum 4.000 karakter), Simpan Serah Terima dibatalkan dengan pesan aman; data tidak dipotong diam-diam.

## Komponen dan perubahan yang direncanakan

| Lokasi | Perubahan |
| --- | --- |
| `pages/purchasing/purchasing.php` | Menambah token idempoten per baris dokumen pada form Tambah; mengirim token tersebut saat Simpan. Tidak mengubah UI data bisnis. |
| `pages/purchasing/purchasing_serverside.php` | Memvalidasi CSRF dan token idempoten pada `action=add`; menyimpan header/detail/PO, membuat Issue internal, dan membuat link sumber dalam satu transaksi SQL Server. |
| `pages/Issues_purchasing/issue_crud.php` atau helper baru di modul yang sama | Mengekstrak helper internal untuk validasi kategori, profil pembuat, konstruksi Description, dan insert Issue. Helper bukan endpoint HTTP dan tidak mem-bypass melalui route publik. |
| `pages/Issues_purchasing/Developer/` | Dokumentasi perilaku dan changelog setelah penerimaan. |
| Migrasi SQL Server versioned di modul Purchasing | Menambah metadata idempotensi sumber dan tabel link aktivitas. Tidak akan dijalankan dari web. |

## Migrasi SQL Server yang direncanakan

Sebelum menulis migrasi, metadata tipe kolom aktual akan diverifikasi melalui akses read-only. Targetnya:

1. Kolom token idempoten pada `dbo.purchasing_header` untuk mengenali retry dari baris form yang sama. Index unik bersifat filtered agar data lama tanpa token tetap kompatibel.
2. Tabel link, misalnya `dbo.purchasing_issue_activity_links`, berisi minimal:
   - identitas header Serah Terima;
   - identitas item PO bila sumbernya multi-PO;
   - `issue_id` yang dibuat;
   - waktu pembuatan.
3. Unique key untuk satu sumber menghasilkan tepat satu Issue dan unique key untuk satu Issue hanya dapat terhubung ke satu sumber.
4. Foreign key `NO ACTION`; tidak ada cascade delete karena Issue harus tetap menjadi catatan aktivitas ketika sumber dihapus secara soft-delete.

Migrasi akan memiliki precondition, rollback/forward-fix yang terdokumentasi, serta verifikasi metadata dan snapshot skema setelah eksekusi. Eksekusi DDL SQL Server akan menunggu persetujuan eksplisit terpisah untuk environment yang dituju.

## Alur transaksi dan otorisasi

1. Pengguna yang memiliki hak Tambah Serah Terima mengirim form dengan CSRF dan token idempoten.
2. Server menormalisasi setiap dokumen. Untuk PO multi-item, server memecahnya menjadi kandidat Issue per nomor PO.
3. Server memulai satu transaksi SQL Server dan menyimpan Header, Detail, serta PO Item.
4. Server menyelesaikan kategori/subkategori aktif berdasarkan nama yang disepakati. Jika konfigurasi `Serah Terima Dokumen` atau subkategori Type tidak tersedia/aktif, transaksi digagalkan dengan pesan aman.
5. Server membuat Issue melalui helper internal dengan profil pengguna penyimpan sebagai pembuat. Pembuatan otomatis diizinkan bagi pengguna yang memiliki hak Tambah Serah Terima meskipun tidak memiliki `CanAdd` menu Issue; bypass dibatasi di dalam helper yang dipanggil dari transaksi ini saja.
6. Server membuat link sumber-issue dan commit hanya jika semua dokumen dan Issue berhasil dibuat.
7. Jika salah satu validasi, insert, link, atau commit gagal, seluruh transaksi rollback. Respons tidak mengungkap SQL/stack trace dan menyertakan request ID untuk kegagalan teknis.
8. Retry dengan token sumber yang sama mengembalikan hasil idempoten tanpa membuat Header atau Issue baru.

## Batas scope

- Tidak mengubah Issue yang sudah dibuat ketika Serah Terima diedit, ditandatangani, atau dihapus.
- Tidak mengubah UI daftar Issues selain menampilkan hasil Issue yang memang baru dibuat.
- Tidak mengubah Dokumen PO lain (`purchasing_header`, `purchasing_detail`, `purchasing_po_items`) di luar metadata integrasi yang diperlukan.
- Tidak menjalankan DDL/DML uji pada database tanpa persetujuan environment yang eksplisit.

## Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Klik Simpan/retry membuat duplikasi | Token idempoten sumber + unique key database + link aktivitas. |
| Kategori/subkategori berubah atau nonaktif | Lookup server-side saat simpan; transaksi gagal aman, tanpa fallback ID tersembunyi. |
| Hak menu Issue tidak sama dengan hak Serah Terima | Helper internal dibatasi pada alur `action=add` yang telah terotorisasi; endpoint Issue publik tetap menegakkan `CanAdd`. |
| Issue gagal setelah Header tersimpan | Satu transaksi SQL Server untuk Header, Detail, PO Item, Issue, dan link. |
| Data sumber panjang | Tolak dengan label field yang jelas sebelum commit; tidak melakukan truncation diam-diam. |
| Edit/hapus sumber memutus riwayat | Link `NO ACTION`; Issue snapshot tetap ada sesuai keputusan bisnis. |

## Validasi sebelum review

- Metadata SQL Server: tabel/kolom/tipe/index/FK aktual sebelum migrasi dan sesudah migrasi.
- PHP lint pada setiap file PHP yang berubah; Node check pada script inline yang berubah.
- Test endpoint terautentikasi dengan fixture yang disetujui: PO tunggal, multi-PO, Type non-PO, kategori tidak tersedia, Description melewati batas, retry token sama, serta kegagalan insert yang memicu rollback.
- Browser: Simpan sukses, error aman, tombol anti-duplikat/loading, Console/Network bersih, dan daftar Issue menampilkan baris hasil.
- Audit keamanan: CSRF, hak Serah Terima, pembatasan helper internal, dan respons tanpa detail SQL.

## Urutan implementasi setelah persetujuan

1. Audit mendalam entrypoint mutasi, invariants, dan model retry; snapshot baseline semua file yang disentuh.
2. Verifikasi metadata read-only dan tulis migrasi versioned beserta rollback/forward-fix.
3. Minta/konfirmasi persetujuan eksekusi DDL untuk SQL Server environment yang tepat, lalu jalankan dan verifikasi metadata.
4. Implementasikan helper internal serta integrasi atomik `action=add`.
5. Tambahkan token idempoten dan penanganan UI request.
6. Jalankan validasi source, database, endpoint, dan browser menggunakan fixture yang disetujui.
7. Lakukan review, dokumentasi akhir, pembaruan memory, dan opsi commit terpisah.
