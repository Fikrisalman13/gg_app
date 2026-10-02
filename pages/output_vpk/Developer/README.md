# Output VPK Module

## Purpose & Scope
Modul **Output VPK** mengelola laporan dan data hasil produksi untuk departemen Dyeing / Finishing, mencakup:
- **Laporan LKP Output Dyeing (Routing)**: Filter tanggal dan routing (maksimal 5 routing) dengan query teroptimasi dari database PostgreSQL ERP (`koneksi3.php`).
- **Laporan Tarikan Susut Verpacking**: Menghitung kuantiti greige, hasil per grade (A1, A2, A3, B1, B2, B3, C1), total hasil meter, qty susut meter, dan persentase susut.
- **Export Excel**:
  - Export Detail Susut (`export_hasil_produksi_susut_excel.php`)
  - Export Summary Susut per Tanggal (`export_hasil_produksi_susut_summary_excel.php`)
  - Export Routing (`export_routing_692_excel.php`)

## Main Flow & Files
- `laporan_hasil_produksi_susut.php`: Halaman UI filter dan tabel laporan tarikan susut.
- `hasil_produksi_susut_data.php`: Library pemrosesan query dan kalkulasi susut per nomor produksi maupun summary per tanggal.
- `export_hasil_produksi_susut_excel.php`: Generator Excel detail susut per nomor produksi.
- `export_hasil_produksi_susut_summary_excel.php`: Generator Excel summary susut per tanggal (group header 2 baris).
- `laporan_routing_692.php`: Halaman UI filter dan tabel laporan routing dyeing.
- `routing_692_data.php`: Library pemrosesan query laporan routing.
- `export_routing_692_excel.php`: Generator Excel laporan routing.

## Database Dependencies
- PostgreSQL environment configured by `koneksi3.php` via `$conn3`.
- SQL Server `GG` via `koneksi.php`/`$conn` for Output Packing snapshots and schedule status.
- Tabel ERP utama: `PDProductionHd`, `PDProductionRtg`, `PDRtgMs`, `PDResultHd`, `PDResultDt`, `PDResultMat`, `SMUOM`, `SMProdEquivalent`, `SMProdTechdata`, `PDGradeMs`, `PDColorMs`.

## Otomatisasi Output Packing
- Halaman `auto_packing_settings.php` hanya untuk pemegang `CanEdit` Menu Output Packing (`MenuId` 67).
- Snapshot memakai fungsi summary Tarikan Susut yang sama: `qty = greige_meter`, `qty_a1 = total_a1_meter` untuk tanggal target.
- Jadwal aktif dan jam disimpan di `dbo.output_vpk_auto_config`; status akhir per tanggal ada di `dbo.output_vpk_auto_run`.
- `auto_packing_worker.php` hanya dapat dijalankan dari PHP CLI. Jadwalkan Windows Task Scheduler setiap 5 menit. Worker memproses kemarin setelah jam konfigurasi tercapai.
- Snapshot tidak menimpa row `packing_output` yang sudah ada. Status `success` dan `empty` terminal; status `failed` dapat dipulihkan lewat Tarik Sekarang.
- Konsistensi tanggal lama belum unik; worker memakai lock SQL Server dan cek row tanggal sebelum insert. Unique index tidak ditambahkan sesuai keputusan. Input manual lama tetap dapat membuat duplikat.
- Kegagalan penting dicatat sanitasi ke `logs/error-YYYY-MM-DD.log`; log dibatasi retensi tujuh hari dan tidak masuk Git.
- Nonaktifkan dari halaman jadwal atau Task Scheduler untuk menghentikan automation. Tidak ada backfill otomatis.

## Verification
- Syntax check: `php -l`
- Standalone / inline JS: `node --check`
- Runtime query accuracy: query grouping dicocokkan dengan data aktual produksi.

## Output Manual compatibility
- Main route: `output_manual.php`; export: `export_manual_output.php`.
- SQL Server `GG` schema additive applied 2026-09-14: `manual_output.is_cancelled` defaults to `0`; `manual_output_shift.is_correction_pending` and `manual_output_shift.is_cancelled` default to `0`; quantity history accepts nullable `shift_id`, `before_snapshot`, and `after_snapshot`.
- No existing column, route, or other page was renamed, removed, or redirected.
- Rollback and active-shift cancellation are active only in Output VPK main. The legacy `manual_output.is_cancelled` filter remains only to preserve existing cancelled headers.
- Rollback requires owner of last completed shift or GroupId `1`, mandatory reason, and no later shift. Pending correction blocks handover; Save correction restores valid Meter calculation.
- Active-shift cancellation requires reason and exact PRDNMBR confirmation. Owner can cancel own active shift; GroupId `1` can cancel any active shift. The shift is soft-cancelled and displayed as Dibatalkan; other shifts remain unchanged. Header total excludes cancelled/pending shifts and stays Berjalan without active shift until Oper Shift starts a new one.
- Delete permanen is visible and accepted only for GroupId `1`. It requires exact PRDNMBR plus reason, then atomically deletes audit history, all shifts, and header. It cannot be recovered from application.
- Main write actions use session CSRF tokens and shift `row_version`; a stale modal must reload before write.
- Export excludes cancelled records, limits each sheet query to 10,000 rows, and prefixes formula-like text before XLSX output.
- Complete detail lists unique shift operators in shift order; shift values remain per-shift snapshots. Meter is summed only by existing shift calculation; Gol/Grey/Tengah are not summed.

## Focused validation status
- PHP lint and focused active-shift cancellation static checks passed for main page.
- Inline JavaScript syntax passed with `node --check`.
- Browser, Console, Network, and write-action runtime tests remain manual-user testing because browser automation was declined and no approved test record was provided.
