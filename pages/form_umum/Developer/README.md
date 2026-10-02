# Module Form Umum (IKS / IKP / IPC)

## Overview
Module Form Umum mengelola pengajuan Izin Keluar Pabrik (IKS), Izin Keluar Pribadi (IKP), dan Izin Pulang Cepat (IPC), termasuk alur persetujuan (TTD), verifikasi keamanan (Satpam), pencetakan QR Code / Barcode (Printer TM-U220 / browser print), dan rekapitulasi data.

## Short Barcode Token System
Untuk mendukung pencetakan barcode Code 39 pada printer dot-matrix / thermal hemat kertas (seperti Epson TM-U220 76mm):
1. **Masalah**: Nomor tiket asli (misal `IKS-2026-08-14-000123`) memiliki 21 karakter. Barcode Code 39 untuk 21 karakter sangat lebar sehingga terpotong saat dicetak.
2. **Solusi**: Dibuat tabel mapping `Form_Umum_Barcode_Token` di SQL Server yang memetakan tiket ke kode 6 karakter acak (Base32, misal `ZVCQWQ`).
3. **Pencetakan Barcode**: `generate_barcode_form_umum.php` menghasilkan SVG Code 39 ringkas dari token 6 karakter `ZVCQWQ`.
4. **Pemindaian (Scan)**: `scan_action.php` dan `scan_index.php` menerima input tiket asli (`IKS-...`) maupun kode token 6 karakter (`ZVCQWQ`), lalu menyelesaikan token tersebut kembali ke tiket asli sebelum memproses aksi keluar/kembali.

## File Structure
- `list_form.php`: Halaman utama daftar pengajuan Form Umum, cetak tiket, modal QR/Barcode.
- `generate_barcode_form_umum.php`: Endpoint generator SVG Barcode Code 39 ringkas.
- `barcode_token_helper.php`: Helper server-side untuk pembuatan, pemastian tabel DB, dan resolusi token barcode pendek.
- `scan_index.php`: Landing page pemindaian continuous scan untuk Satpam.
- `scan_action.php`: Handler aksi verifikasi keluar/kembali tiket.

## Verification
- Syntax PHP (`php -l`) & inline JavaScript (`node --check`).
- Runtime test script `scratch/test_short_barcode.php` memverifikasi auto-creation tabel DB, pembuatan token, dan resolusi tiket.
