# MKO-ACCWARNA

Modul ini dipakai untuk:

1. Tarik raw data routing dari database `ERP_Crystal_SUM_Demo`
2. Simpan hasilnya ke `GG.dbo.mko_rawdata`
3. Ambil `prdnmbr` unik dari raw data per kode periode
4. Tarik detail material obat tanpa tabel temporary
5. Simpan hasilnya ke `GG.dbo.MKO_materialobat`

## File utama

- `index.php`: halaman input filter dan monitoring hasil
- `fetch_rawdata.php`: proses tarik raw data
- `fetch_materialobat.php`: proses tarik material obat
- `functions.php`: koneksi, helper query, setup tabel, generator kode periode
- `run_setup.php`: setup manual tabel jika ingin dijalankan langsung

## Kode periode

Format kode periode:

`periode_YYYYMMDD_YYYYMMDD_01`

Jika periode yang sama ditarik lagi, nomor belakang otomatis naik menjadi `02`, `03`, dan seterusnya.
