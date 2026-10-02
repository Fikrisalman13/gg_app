# Changelog - Output VPK

## [Accepted] - 2026-08-21
### Added
- **Export Summary per Tanggal** (`export_hasil_produksi_susut_summary_excel.php`):
  - Ekspor Excel dengan grouping tanggal selesai verpacking (format `DD-MON-YY`).
  - Header 2 baris (Greige Asal, A1, A2, A3, B1, B2, B3, C1, Meter, Susut, Persentase Susut).
  - Format angka 4 desimal (`#,##0.0000`) dan persentase (`0.00%`).
  - Baris Grand Total di bagian bawah tabel.
- Fungsi backend `hasilProduksiSusutFetchSummaryByDate()` di `hasil_produksi_susut_data.php`.
- Tombol **Export Summary** berdampingan dengan **Export Detail** di UI `laporan_hasil_produksi_susut.php`.
- Summary card **Total Meter A1** pada halaman laporan tarikan susut.

### Changed
- Filter rentang tanggal pada laporan tarikan susut diperluas menjadi maksimal 31 hari.
- Penghapusan pembatasan data limit 1000 agar summary dan data lengkap tanpa terpotong.
- Optimasi temporary table query `TmpFilteredHd` untuk mencegah timeout eksekusi query.
