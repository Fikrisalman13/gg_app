Ringkasan perubahan form `form_pengajuan_akses_internet.php`

- Nama tabel target di DB: `Form_Pengajuan_Akses_Internet`

- Kolom yang terlihat di tabel (sesuai attachment) — pastikan kolom ini ada:
  - `ticket`
  - `nama_pemohon`
  - `jabatan`
  - `departemen`
  - `area`
  - `tgl_pengajuan`
  - `jenis_permintaan`
  - `akses_type`
  - `durasi_temporary`
  - `keterangan`
  - `status_ticket`
  - `kategori`
  - `created_at`
  - `created_by`
  - `updated_at`
  - `updated_by`

- Kolom tambahan yang ditambahkan untuk fitur baru (bandwidth + flags):
  - `request_akses_internet` (TINYINT / BIT)
  - `akses_temporary_from` (DATE)
  - `akses_temporary_to` (DATE)
  - `request_tambah_bandwidth` (TINYINT / BIT)
  - `bandwidth_type` (VARCHAR, Temporary/Permanent)
  - `bandwidth_temporary_from` (DATE)
  - `bandwidth_temporary_to` (DATE)
  - `tambah_bandwidth` (INT)
  - `tambah_bandwidth_unit` (VARCHAR)

- SQL migration file: `db_migrations_add_akses_internet.sql` (mengandung contoh ALTER TABLE untuk MySQL dan SQL Server menggunakan tabel `Form_Pengajuan_Akses_Internet`).

Contoh pengolahan POST (PHP) sebelum menyimpan ke DB (sesuaikan driver `sqlsrv` jika dipakai):

1. Parsing tanggal dari format `DD-MM-YYYY` ke `Y-m-d`:
   $from = null; $to = null;
   if (!empty($_POST['akses_temporary_from'])) {
       $d = DateTime::createFromFormat('d-m-Y', $_POST['akses_temporary_from']);
       if ($d) { $from = $d->format('Y-m-d'); }
   }

2. Checkbox -> boolean:
   $reqAkses = !empty($_POST['request_akses_internet']) ? 1 : 0;
   $reqBw = !empty($_POST['request_tambah_bandwidth']) ? 1 : 0;

3. Contoh INSERT (SQL Server style with parameter placeholders for `sqlsrv`):
   $sql = "INSERT INTO dbo.Form_Pengajuan_Akses_Internet (nama_pemohon, jabatan, departemen, area, tgl_pengajuan, jenis_permintaan, request_akses_internet, akses_type, akses_temporary_from, akses_temporary_to, request_tambah_bandwidth, bandwidth_type, bandwidth_temporary_from, bandwidth_temporary_to, tambah_bandwidth, tambah_bandwidth_unit, keterangan, created_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

Pastikan menyesuaikan bagian `created_at`, `created_by` dan kolom lain sesuai kebutuhan aplikasi Anda.
