# Changelog Form IT

## 2026-07-31

- Integrasikan Form Pemindahan Kamera CCTV dan Penambahan Gudang Baru ERP.
- Perbaiki penyimpanan TTD Pemohon setelah create untuk kedua form baru.
- Jadikan `list_form.php` pemilik tunggal submit AJAX; hapus native submit handler lokal.
- Perbaiki scope JavaScript agar tombol Detail dan Delete aktif sejak halaman dimuat, tanpa membuka Edit lebih dahulu.
- Ubah kegagalan endpoint TTD menjadi error nyata, bukan sukses palsu.
- Validasi PHP lint, seluruh inline JavaScript tersentuh, focused source self-check, dan diff check.
- Browser Console, Network, duplicate DOM ID, serta dua theme belum diverifikasi.
