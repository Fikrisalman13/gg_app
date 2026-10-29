# Issues Purchasing

**Status:** Accepted read-only list/history/detail slice. Authenticated active/history APIs return HTTP 200 with valid empty DataTables JSON for the current user context; pages render settled empty states. Detail ID 1 returns safe 404; populated records were unavailable. Source-quality audit remains incomplete. See [active task ledger](task_issues_purchasing.md). No DDL or database writes executed.

## Entry and authorization

- Standalone URL: `/gg_app/pages/Issues_purchasing/list_issues.php`.
- Every page/API/export/download must authenticate and check `MenuId = 1329` server-side.
- Mutations require per-action permission and CSRF validation. Hidden controls are not authorization.
- No global menu/router or shared auth helper changes approved.

## Data and workflow target

- Dedicated SQL Server objects under `dbo.purchasing_*`; shared employee/user masters read-only where required.
- No Project, asset/client, printer/ink, or Form IT assignment behavior.
- Category/subcategory linked by stable IDs; FK delete behavior `NO ACTION`; reject referenced deletes.
- Types: `Task`, `Support Activities`. Statuses: `To Do`, `In Progress`, `Done`.
- Create only `To Do`/`In Progress`; creator may quick-complete own issue. `CanEdit` may set any valid status, completion timestamp synchronized.
- No bulk Done or admin timestamp editor. Batch creation atomic/idempotent. `CanDelete` applies to all statuses; attachment cleanup must be safe.

## Approved completion scope

List/history/detail, Purchasing category reads, templates, issue writes and bounded issue export are in approved plan. Current recovery slice restores read-only list/history/detail. No DDL, attachments, or write/test requests without separate exact approval.

SQL Server `$conn` via `koneksi.php`. Purchasing and authorization metadata were inspected using approved bounded reads. No DDL or database writes executed. Authenticated active/history list API and empty-state browser behavior verified; unauthenticated API returns 401 and invalid scope returns 400. No populated records, CRUD mutation, category/template write, or export workflow has been runtime-verified. Source-quality gate remains incomplete.

## Fidelity UI diterima 2026-10-24

Daftar aktif dan riwayat memakai komponen bersama `issue_ui.js` dan `issue_ui.css` untuk badge berikon, tombol aksi ber-tooltip, SweetAlert, detail terstruktur, dan DataTables responsif. Detail menampilkan Nama, Jabatan, Departemen, dan Bagian dari respons detail yang telah ada. Pola ini tetap Purchasing-only: tidak menambah Project, Asset, Client, Lampiran, Export PDF, atau Mark All Done. Validasi browser tidak melakukan DML; hapus template aktual serta detail riwayat berisi data belum diuji karena fixture tidak tersedia.

## Mutasi dan template tervalidasi 2026-10-24

Detail Issue mengirim `row_version` sebagai `0x` diikuti 16 karakter heksadesimal; token ini wajib dikirim kembali saat Update atau Delete untuk pemeriksaan konkurensi. Setelah Create Issue baru berhasil, pengguna dapat memilih menyimpan konfigurasi sebagai template pribadi melalui modal terpisah. Modal itu hanya dibuka setelah SweetAlert selesai agar fokus tidak berada di dalam shell beratribut `aria-hidden`.

Kategori tanpa subkategori memang ditolak oleh constraint database. UI dan validator server sekarang menegakkan aturan yang sama: setelah kategori dipilih, pengguna wajib memilih subkategori aktif yang merupakan pasangan kategori tersebut. Pesan validasi menjelaskan field yang perlu dilengkapi tanpa menampilkan detail SQL maupun ID internal.

## Template dan referensi kategori

Memilih template pribadi mengisi `Issue Name` menggunakan `template_name`, lalu melengkapi tipe, prioritas, status, deskripsi, kategori, dan subkategori sesuai data template. Pada Kelola Kategori, badge penggunaan membedakan jumlah `Issue` dari jumlah `Template`. Keduanya tetap dihitung oleh aturan hapus kategori/subkategori karena masing-masing masih menjadi referensi yang valid.

Modal Pilih Template menyediakan pencarian langsung pada nama, tipe, prioritas, status, dan deskripsi. Input memakai `type="text"` dengan `inputmode="search"`, sehingga hanya tombol reset kustom yang terlihat ketika terdapat kata kunci. Template lama yang belum memiliki subkategori tetap dapat dipilih, tetapi pengguna diarahkan untuk memilih subkategori valid sebelum menyimpan Issue.

## Export PDF dan Riwayat

Daftar aktif serta riwayat memiliki tombol Export PDF. Keduanya mengirim Type dan rentang tanggal aktif ke `export_issues_pdf.php`; daftar aktif juga mengirim Status aktif. Endpoint menerima scope `active` atau `history` saja, memeriksa `CanView`, memvalidasi semua filter, dan membatasi PDF hingga 5.000 Issue. Filter dan status selalu dipaksakan kembali di server, bukan hanya dari UI.

Pada Riwayat, pengguna dengan `CanEdit` dapat membuka modal Ubah untuk Issue Done, sedangkan pengguna dengan `CanDelete` dapat meminta Hapus. Modal mengambil detail terkini agar `row_version` tidak stale. Simpan dan Hapus tetap memakai endpoint mutasi yang sama dengan daftar aktif: POST, CSRF, validasi server, izin aksi, serta respons konflik `409` bila Issue berubah lebih dahulu.
