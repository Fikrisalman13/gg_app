<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../koneksi.php');
require_once('../../libs/fpdf.php'); // Sesuaikan dengan lokasi FPDF

// Ambil NIK dan ID Kontrak dari parameter GET
$selected_nik = isset($_GET['nik']) ? $_GET['nik'] : '';
$selected_id  = isset($_GET['id_kontrak']) ? $_GET['id_kontrak'] : '';

if (empty($selected_nik)) {
    if (isset($return_blob)) return null;
    die("NIK tidak ditemukan.");
}

// Query untuk mengambil data berdasarkan NIK
$sql = "SELECT
            a.nik,
            a.nama_lengkap,
            b.dept,
            c.bagian,
            d.subbag,
            e.jabatan,
            g.golongan,
            a.tmp_lahir,
            a.tgl_lahir,
            a.kelamin,
            a.alamat,
            a.no_ktp,
            g.umr,
            h.signature,
            f.nomor_dokumen,
            f.tanggal_dokumen,
            p.tgl_mulai,
            p.tgl_selesai
        FROM
            dbo.m_emp AS a
            LEFT JOIN dbo.m_dept AS b ON a.id_dept = b.id_dept
            LEFT JOIN dbo.m_bag AS c ON a.id_bag = c.id_bag
            LEFT JOIN dbo.m_subbag AS d ON a.id_subbag = d.id_subbag
            LEFT JOIN dbo.kontrak_kerja AS f ON a.nik = f.nik
            LEFT JOIN dbo.kontrak_kerja_perjanjian AS p ON f.id = p.id_kontrak
            LEFT JOIN dbo.m_jab AS e ON a.id_jab = e.id_jab
            LEFT JOIN dbo.m_gol AS g ON a.id_gol = g.id_gol
            LEFT JOIN dbo.tanda_tangan AS h ON a.nik = h.nik
         WHERE
                a.aktif = '1' 
                " . (empty($selected_id) ? "AND a.id_gol IN ('2','7','8','10','11')" : "") . "
                AND
                a.nik = ?
                " . (!empty($selected_id) ? "AND f.id = ?" : "");

$params = array($selected_nik);
if (!empty($selected_id)) {
    $params[] = $selected_id;
}
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
    if (isset($return_blob)) return null;
    die("Data tidak ditemukan.");
}
//FUGSI UBAH FORMAT TGL DARI DB
function formatTanggal($tanggal) {
    if (!$tanggal) {
        return '0000-00-00'; // Jika NULL, kembalikan default
    }

    // Jika berupa objek DateTime (SQLSRV sering mengembalikan sebagai object)
    if ($tanggal instanceof DateTime) {
        return $tanggal->format('d-m-Y'); // Atau gunakan 'Y-m-d' sesuai kebutuhan
    }

    // Jika berupa string (konversi manual)
    $date = DateTime::createFromFormat('Y-m-d H:i:s.u', $tanggal);
    if (!$date) {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $tanggal); // Jika tanpa microsecond
    }
    if ($date) {
        return $date->format('d-m-Y'); // Ubah ke format yang diinginkan
    }

    return 'Invalid Date';
}

// Ambil hari dan tanggal saat ini
$hariIni = date('l'); // Mengambil nama hari dalam bahasa Inggris
$tanggalIni = date('d F Y'); // Format tanggal: 13 Februari 2025

// Konversi nama hari ke bahasa Indonesia
$hariIndonesia = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$bulanIndonesia = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];

// Konversi hari dan bulan ke bahasa Indonesia
$hari = $hariIndonesia[$hariIni] ?? $hariIni;

$tanggalArray = explode(' ', $tanggalIni);
$tanggal = $tanggalArray[0];
$bulan = $bulanIndonesia[$tanggalArray[1]] ?? $tanggalArray[1];
$tahun = $tanggalArray[2];

$tanggalIndonesia = "$tanggal $bulan $tahun"; // Format: 13 Februari 2025

//AKHIR FUNGSI TGL

// Buat instance FPDF
$pdf = new FPDF('P','mm','A4');
    $pdf->AddPage();
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PT. SURYA USAHA MANDIRI', 0, 1, 'L');
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell(0, 4, 'Jl. Tarajusari No.8, Desa Tarajusari, Kec. Banjaran, Kab. Bandung', 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'Perjanjian Kerja Untuk Waktu Tertentu', 0, 1, 'C');
    $pdf->SetFont('Times', 'I', 10);
    $pdf->Cell(0, 4, '' .htmlspecialchars($row['nomor_dokumen']), 0, 1, 'C');
    $pdf->Ln(2);

    // Data Karyawan
    $pdf->SetFont('Times', '', 10);
    $teks = "Pada hari ini $hari, tanggal $tanggalIndonesia, bertempat di PT. Surya Usaha Mandiri, Jl. Tarajusari No.8, Desa Tarajusari, Kec. Banjaran, Kab. Bandung, kami yang bertanda tangan di bawah ini :";
    $pdf->MultiCell(0, 4, $teks, 0, 'J');
    $pdf->Ln(2);

    $pdf->Cell(40, 4, 'I.  Nama', 0, 0);
    $pdf->Cell(0, 4, ' : Hendra Ginting, SH.', 0, 1);
    $pdf->Cell(40, 4, '    Jabatan', 0, 0);
    $pdf->Cell(0, 4, ' : KADEPT HRD', 0, 1);
    $pdf->Ln(2);

    $pdf->Write(4, 'Dalam hal ini bertindak untuk dan atas nama PT. Surya Usaha Mandiri, Jl. Tarajusari No.8, Desa Tarajusari, Kec. Banjaran, Kab. Bandung, sebagai Pemberi Kerja/Perusahaan selanjutnya disebut ');
    $pdf->SetFont('Times', 'B', 10); // Set font Bold
    $pdf->Write(4, 'PIHAK PERTAMA.');
    $pdf->SetFont('Times', '', 10);
    $pdf->Ln(6);

    $pdf->Cell(40, 4, 'II. NIK', 0, 0);
    $pdf->Cell(0, 4, ': ' . htmlspecialchars($row['nik']), 0, 1, 'L');

    $pdf->Cell(40, 4, '     Nama', 0, 0);
    $pdf->Cell(0, 4, ': ' . htmlspecialchars($row['nama_lengkap']), 0, 1);

    $pdf->Cell(40, 4, '     No. KTP', 0, 0);
    $pdf->Cell(0, 4, ': ' . htmlspecialchars($row['no_ktp']), 0, 1);

    $pdf->Cell(40, 4, '     Tempat Lahir', 0, 0);
    $pdf->Cell(0, 4, ': ' . htmlspecialchars($row['tmp_lahir']), 0, 1);

    
    $tgl_lahir = isset($row['tgl_lahir']) ? formatTanggal($row['tgl_lahir']) : '0000-00-00';
    $pdf->Cell(40, 4, '     Tanggal Lahir', 0, 0);
    $pdf->Cell(0, 4, ': ' . $tgl_lahir, 0, 1);
    
    $pdf->Cell(40, 4, '     Jenis Kelamin', 0, 0);
    $pdf->Cell(0, 4, ': ' . htmlspecialchars($row['kelamin']== 'L' ? 'Laki-laki' : 'Perempuan'), 0, 1);

    $pdf->Cell(40, 4, '     Alamat', 0, 0);
    $pdf->MultiCell(0, 4, ': ' . htmlspecialchars($row['alamat']), 0, 'L');
    $pdf->Write(4, 'Bertindak untuk dan atas nama diri sendiri, sebagai Penerima Kerja/Pekerja selanjutnya disebut ');
    $pdf->SetFont('Times', 'B', 10); // Set font Bold
    $pdf->Write(4, 'PIHAK KEDUA.');
    $pdf->SetFont('Times', '', 10);
    $pdf->Ln(6);

    $pdf->MultiCell(0, 4, 'Bahwa dengan itikad baik PIHAK PERTAMA dan PIHAK KEDUA yang secara bersama-sama selanjutnya disebut "PARA PIHAK" telah setuju dan sepakat mengadakan Perjanjian Kerja untuk selanjutnya disebut juga dengan "Perjanjian Kerja Waktu Tertentu" yang mengikat PARA PIHAK sesuai ketentuan-ketentuan sebagaimana tercantum dalam pasal-pasal sebagai berikut:', 0, 'J');
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 1', 0, 1, 'C');
    $pdf->Cell(0, 4, 'SIFAT PERJANJIAN', 0, 1, 'C');
    $pdf->Ln(2);

    $pdf->SetFont('Times', '', 10);
    $sifatperjanjian = [
    'Yang dimaksud perjanjian kerja ini adalah Perjanjian Kerja Waktu Tertentu.',
    'Perjanjian Kerja ini dapat diputus lebih awal, sebelum jangka waktu perjanjian kerja berakhir, oleh PIHAK PERTAMA tergantung pada kondisi dan kebutuhan PIHAK PERTAMA.',
    'PARA PIHAK memahami, saling mengerti dan menyepakati bahwa perjanjian kerja ini berlaku sebagai undang-undang bagi kedua belah pihak. PIHAK KEDUA juga wajib mematuhi peraturan perusahaan PT. Surya Usaha Mandiri.'
    ];
    
$no = 1; // Mulai dari nomor 1
foreach ($sifatperjanjian as $text) {
    $indent = "   "; // Spasi untuk indentasi awal
    $numbering = $no . ")   "; // Nomor
    $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
    
    // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
    $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
    
    // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
    $pdf->MultiCell(0, 4, $text, 0, 'J');

    $no++; // Tambah nomor
}
$pdf->Ln(2);
  
$lokasidanpekerjaan = [
'PIHAK PERTAMA dengan ini menyatakan bersedia untuk menerima dan mempekerjakan PIHAK KEDUA dan PIHAK KEDUA menyatakan bersedia untuk bekerja di perusahaan PIHAK PERTAMA dan bersedia ditempatkan pada.'
];

$pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 2', 0, 1, 'C');
    $pdf->Cell(0, 4, 'LOKASI DAN PEKERJAAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $lokasidanpekerjaan = [
        'PIHAK PERTAMA dengan ini menyatakan bersedia untuk menerima dan mempekerjakan PIHAK KEDUA dan PIHAK KEDUA menyatakan bersedia untuk bekerja di perusahaan PIHAK PERTAMA dan bersedia ditempatkan pada:'
        
        ];

        $no = 1; // Mulai dari nomor 1
        foreach ($lokasidanpekerjaan as $text) {
            $indent = "   "; // Spasi untuk indentasi awal
            $numbering = $no . ")   "; // Nomor
            $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
            
            // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
            $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
            
            // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
            $pdf->MultiCell(0, 4, $text, 0, 'J');
        
            $no++; // Tambah nomor
        }
       

        // Lebar tetap untuk label dan indentasi
        $indent = 5.5;       // Indentasi awal (dalam satuan width)
        $labelWidth = 35;  // Lebar label setelah indentasi
        $indentWidth = $labelWidth + $indent; // Lebar indentasi agar MultiCell tetap sejajar
        $valueWidth = 0;   // Lebar fleksibel untuk teks panjang

        $pdf->Cell($indent, 4, '', 0, 0); // Indentasi awal
        $pdf->Cell($labelWidth, 4, 'Departemen', 0, 0);
        $pdf->Cell(5, 4, ':', 0, 0);
        $pdf->MultiCell($valueWidth, 4, html_entity_decode($row['dept']), 0, 'L');

        $pdf->Cell($indent, 4, '', 0, 0);
        $pdf->Cell($labelWidth, 4, 'Bagian/Sub.Bagian', 0, 0);
        $pdf->Cell(5, 4, ':', 0, 0);
        $pdf->MultiCell($valueWidth, 4, html_entity_decode($row['bagian']) . '/ ' . html_entity_decode($row['subbag']), 0, 'L');

        $pdf->Cell($indent, 4, '', 0, 0);
        $pdf->Cell($labelWidth, 4, 'Jabatan', 0, 0);
        $pdf->Cell(5, 4, ':', 0, 0);
        $pdf->MultiCell($valueWidth, 4, html_entity_decode($row['jabatan']), 0, 'L');

        $pdf->Cell($indent, 4, '', 0, 0);
        $pdf->Cell($labelWidth, 4, 'Yang akan mengerjakan order kain dengan merk/jenis sebagai berikut :', 0, 0);
        $pdf->Ln(4);
    
// Data dalam bentuk array (3 kolom)
$orderkain = [
    ["1. Unione", "11. Marylan Twill", "21. Sumtex Oxford"],
    ["2. Espero Tropical", "12. Verlando Basic ", "22. Denier"],
    ["3. Granmax Tropical", "13. Marylad Tropical", "23. AY02-692"],
    ["4. Sumtex Ribstop", "14. Medalion", "24. AY01-705"],
    ["5. Pelangi", "15. Celebrity", "25. AY01-781"],
    ["6. Sumtex Japan Drill", "16. Fit USA Drill", "26. AY01-787"],
    ["7. Sumtex Potensha", "17. Anekatex", ""],
    ["8. Sumtex Potensha Cele", "18. Granmax Japan Matt", ""],
    ["9. Ixten American Drill", "19. Premium SUM", ""],
    ["10. Verlando Twill", "20. Inma Sliver", ""]
];

// Set lebar kolom
$colWidth = 60; // Lebar masing-masing kolom
$rowHeight = 4; // Tinggi baris
$indent = 5;   // Lebar indentasi

foreach ($orderkain as $or) {
    $pdf->Cell($indent, $rowHeight, '', 0, 0); // Indentasi awal
    $pdf->Cell($colWidth, $rowHeight, $or[0], 0, 0); // Kolom 1
    $pdf->Cell($colWidth, $rowHeight, $or[1], 0, 0); // Kolom 2
    $pdf->Cell($colWidth, $rowHeight, $or[2], 0, 1); // Kolom 3 (baris baru)
}

        
$lokasidanpekerjaan = [
    'PIHAK KEDUA menyatakan bersedia dan sanggup melaksanakan pekerjaan tersebut dengan sebaik-baiknya dan apabila dipandang perlu PIHAK PERTAMA dapat menempatkan atau memindahkan PIHAK KEDUA dari satu Departemen/bagian ke Departemen/bagian lain dan PIHAK KEDUA wajib menerima pemindahan tersebut dan pemindahan tersebut tanpa perlu meminta persetujuan PIHAK KEDUA.',
    'PIHAK PERTAMA dapat memberikan tugas lain kepada PIHAK KEDUA untuk mengerjakan order diluar jenis/merk diatas yang mungkin muncul belakangan dalam batas hubungan kerja dan jenis pekerjaan yang berkaitan dengan Perjanjian Kerja Waktu Tertentu ini.',
    'PIHAK KEDUA dengan ini menyatakan akan tunduk dan patuh terhadap segala tata tertib dan sistem kerja yang berlaku di lingkungan perusahaan PIHAK PERTAMA.'
    ];
    $no = 2; // Mulai dari nomor 1
    foreach ($lokasidanpekerjaan as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
        
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
    
        $no++; // Tambah nomor
    }
    $pdf->Ln(2);

function tanggalIndo($tanggal) {
    $bulan = [
        'January'   => 'Januari',
        'February'  => 'Februari',
        'March'     => 'Maret',
        'April'     => 'April',
        'May'       => 'Mei',
        'June'      => 'Juni',
        'July'      => 'Juli',
        'August'    => 'Agustus',
        'September' => 'September',
        'October'   => 'Oktober',
        'November'  => 'November',
        'December'  => 'Desember'
    ];

    $tgl = date('d', strtotime($tanggal));
    $bln = $bulan[date('F', strtotime($tanggal))];
    $thn = date('Y', strtotime($tanggal));

    return "$tgl $bln $thn";
}

    // Tanggal otomatis dengan format Bahasa Indonesia
    $tanggalMulai   = tanggalIndo(date('Y-m-d')); // Hari ini

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 3', 0, 1, 'C');
    $pdf->Cell(0, 4, 'JANGKA WAKTU PERJANJIAN KERJA', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    
    // Fallback logic for older records (hardcoded dates as requested)
    $tgl_mulai_src = !empty($row['tgl_mulai']) ? $row['tgl_mulai'] : '2025-10-23';
    $tgl_selesai_src = !empty($row['tgl_selesai']) ? $row['tgl_selesai'] : '2026-03-17';

    $tgl_mulai_indo = $tgl_mulai_src ? tanggalIndo($tgl_mulai_src instanceof DateTime ? $tgl_mulai_src->format('Y-m-d') : $tgl_mulai_src) : '...';
    $tgl_selesai_indo = $tgl_selesai_src ? tanggalIndo($tgl_selesai_src instanceof DateTime ? $tgl_selesai_src->format('Y-m-d') : $tgl_selesai_src) : '...';

    $jangkawaktuperjanjian = [
        "Perjanjian kerja ini mulai berlaku tanggal $tgl_mulai_indo sampai dengan tanggal $tgl_selesai_indo atau selesainya pekerjaan yang ditentukan oleh PIHAK PERTAMA.",
        "Pada saat berakhirnya waktu perjanjian ini, pemberian uang kompensasi dan besaran kompensasi sematamata berdasarkan kebijakan dan kemampuan perusahaan."
    ];
    $no = 1; // Mulai dari nomor 1
    foreach ($jangkawaktuperjanjian as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
        
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
    
        $no++; // Tambah nomor
    }
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 4', 0, 1, 'C');
    $pdf->Cell(0, 4, 'UPAH DAN CARA PEMBAYARAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $upah =[
        'Untuk pekerjaan sebagaimana diatur dalam perjanjian kerja ini, PIHAK KEDUA akan mendapatkan hak dari PIHAK PERTAMA sebagai berikut :'
    ];
    $no = 1; // Mulai dari nomor 1
    foreach ($upah as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
        
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
    
        $no++; // Tambah nomor
    }
    
    $indent = 5.5;       // Indentasi awal (dalam satuan width)
    $labelWidth = 35;    // Lebar label setelah indentasi
    $indentWidth = $labelWidth + $indent; // Lebar indentasi agar MultiCell tetap sejajar
    $valueWidth = 0;     // Lebar fleksibel untuk teks panjang
    
    $pdf->Cell($indent, 4, '', 0, 0); // Indentasi awal
    $pdf->Cell($labelWidth, 4, 'Upah', 0, 0);
    $pdf->Cell(10, 4, ': Rp.', 0, 0); // Menyesuaikan lebar agar konsisten
    $pdf->MultiCell($valueWidth, 4, number_format($row['umr'], 2, ',', '.'), 0, 'L');

    
    $pdf->Cell($indent, 4, '', 0, 0); // Indentasi awal
    $pdf->Cell($labelWidth, 4, 'Grand Total', 0, 0);
    $pdf->Cell(10, 4, ': Rp.', 0, 0);
    $pdf->SetFont('Times', 'B', 10); // Set Bold untuk Grand Total
    $pdf->MultiCell($valueWidth, 4, number_format($row['umr'], 2, ',', '.'), 0, 'L');

    $pdf->SetFont('Times', '', 10); // Kembalikan ke font normal
    
    $pdf->Cell($indent, 4, '', 0, 0); // Indentasi awal
    $pdf->Cell($labelWidth, 4, '(Net Income Tax)', 0, 0);
    $pdf->MultiCell(50, 4, '', 0, 'C'); // Gunakan lebar minimal 50

    $upah =[
        'PIHAK PERTAMA dapat mengenakan denda atau ganti rugi pada PIHAK KEDUA dengan memotong langsung upah yang diterima PIHAK KEDUA apabila PIHAK KEDUA melakukan pelanggaran/kesalahan yang merugikan PIHAK PERTAMA dengan ditandatanganinya perjanjian ini secara otomatis PIHAK KEDUA telah memberi kuasa pemotongan upah kepada PIHAK PERTAMA, yang mana kuasa tersebut tidak dapat dicabut kembali dan PARA PIHAK sepakat untuk mengabaikan pasal-pasal yang berkaitan dengan pemberian kuasa di KUH Perdata.',
        'Pajak atas upah yang diterima oleh PIHAK KEDUA menjadi beban dan tanggung jawab PIHAK KEDUA.',
        'PIHAK KEDUA wajib membuat dan menyerahkan salinan NPWP kepada PIHAK PERTAMA untuk keperluan pajak atas upah PIHAK KEDUA apabila diminta PIHAK PERTAMA.',
        'Upah dibayarkan setiap awal bulan dan ditransfer ke Rekening Bank yang ditunjuk PIHAK PERTAMA atas nama PIHAK KEDUA.'
    ];
    $no = 2; // Mulai dari nomor 1
    foreach ($upah as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
        
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
    
        $no++; // Tambah nomor
    }

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 5', 0, 1, 'C');
    $pdf->Cell(0, 4, 'HARI DAN JAM KERJA', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $jamkerja =[
        'PIHAK PERTAMA mempekerjakan PIHAK KEDUA selama 40 jam dalam seminggu dimana hari kerja dapat diatur dalam suatu grup kerja dan PIHAK KEDUA bersedia bekerja sistem shift sesuai yang ditetapkan PIHAK PERTAMA.',
        'Apabila diperlukan menurut PIHAK PERTAMA, PIHAK KEDUA bersedia bekerja lembur setiap diperlukan karena tuntutan pekerjaan yang harus diselesaikan.',
        'Dalam kondisi tertentu PIHAK PERTAMA dapat merubah hari atau jam kerja atau hari libur sesuai kebutuhan operasional'
    ];
    $no = 1; // Mulai dari nomor 1
    foreach ($jamkerja as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
        
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
    
        $no++; // Tambah nomor
    }
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 6', 0, 1, 'C');
    $pdf->Cell(0, 4, 'HAK DAN KEWAJIBAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $hdk =[
        'PIHAK PERTAMA memberitahukan kepada PIHAK KEDUA ketentuan-ketentuan yang berlaku dalam tata tertib, prosedur, syarat atau aturan kerja yang terkait dengan tugas/pekerjaan PIHAK KEDUA dan PIHAK KEDUA wajib mempelajari, memahami serta melaksanakan ketentuan-ketentuan yang terkandung didalamnya.',
        'PIHAK KEDUA bersedia untuk mematuhi segala bentuk peraturan tata tertib, prosedur, syarat atau aturan kerja yang terkait dengan tugas/pekerjaan yang diberikan oleh PIHAK PERTAMA dan siap menerima sanksi yang diberikan oleh PIHAK PERTAMA apabila ternyata PIHAK KEDUA tidak mematuhi hal-hal yang telah disepakati dalam perjanjian kerja ini, Peraturan Perusahaan atau tata tertib/norma yang berlaku di perusahaan dalam arti yang seluas luasnya.',
        'PIHAK KEDUA wajib melaksanakan instruksi yang diberikan PIHAK PERTAMA, tugas dan kewajibannya dengan sungguh-sungguh, penuh tanggung jawab sesuai prosedur kerja yang berlaku, apabila berhalangan hadir bekerja maka diwajibkan meminta ijin atau memberi informasi dan memberikan keterangan tertulis ditunjang bukti terkait kepada PIHAK PERTAMA sesuai prosedur/ketentuan yang berlaku.',
        'Penyerahan keterangan tertulis dan bukti terkait tersebut diserahkan oleh PIHAK KEDUA kepada PIHAK PERTAMA selambat-lambatnya pada saat PIHAK KEDUA masuk bekerja kembali.',
        'PIHAK KEDUA wajib mengikuti pelatihan, program pengembangan karyawan atau kegiatan perusahaan resmi lainnya yang diadakan PIHAK PERTAMA.',
        'PIHAK KEDUA berhak mendapatkan upah sebagaimana yang telah ditentukan dalam perjanjian kerja ini.',
        'Jika PIHAK KEDUA mengajukan pengunduran diri, maka harus memenuhi syarat:'

    ];
    $no = 1; // Mulai dari nomor 1
    foreach ($hdk as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 40, 0, 'L');
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
        $no++; // Tambah nomor
    }

    $hhdkitem = [
        ["(a) Mengajukan pengunduran diri secara tertulis kepada PIHAK PERTAMA selambat-lambatnya 30 (tiga puluh) hari sebelum tanggal pengunduran diri;"],
        ["(b) Jika PIHAK KEDUA ingin mengundurkan diri sesuai waktu perjanjian ini, PIHAK KEDUA tetap wajib mengundurkan diri 30 hari sebelumnya;"],
        ["(c) PIHAK KEDUA tetap melaksanakan kewajibannya sampai tanggal pengunduran diri;"],
        ["(d) Jika PIHAK KEDUA keluar bekerja tidak prosedural, artinya PIHAK KEDUA secara sukarela melepaskan haknya untuk memperoleh upah terakhir, surat keterangan kerja, dll."],
    ];
    // Set ukuran dan indentasi
    $colWidth = 180; // Lebar kolom penuh
    $rowHeight = 4;  // Tinggi baris
    $indent = 5;    // Indentasi awal
    
    foreach ($hhdkitem as $or) {
        // Buat indentasi awal dengan Cell kosong
        $pdf->Cell($indent, $rowHeight, '', 0, 0);
        
        // Cetak teks dalam MultiCell agar bisa pindah ke baris berikutnya jika panjang
        $pdf->MultiCell($colWidth, $rowHeight, $or[0], 0, 'J');
    }
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 7', 0, 1, 'C');
    $pdf->Cell(0, 4, 'BERAKHIRNYA HUBUNGAN KERJA', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $hbk = [
        'Dengan berakhirnya jangka waktu perjanjian kerja ini, maka berakhir pula hubungan kerja antara PIHAK PERTAMA dan PIHAK KEDUA, tidak ada kewajiban PIHAK PERTAMA untuk mempekerjakan kembali PIHAK KEDUA kecuali jika memang dikehendaki oleh PIHAK PERTAMA dan PIHAK KEDUA bersedia dipekerjakan kembali melalui perjanjian kerja yang baru.',
        'Perjanjian Kerja Waktu Tertentu ini dapat juga diakhiri setiap saat secara sepihak oleh PIHAK PERTAMA apabila :'
    ];
    $no = 1; // Mulai dari nomor 1
    foreach ($hbk as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 40, 0, 'J');
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
        $no++; // Tambah nomor
    }

    $hbkitem = [
        ["(a) Kebutuhan pekerjaan menurut PIHAK PERTAMA tidak ada dan atau pekerjaan selesai sebelum jangka waktu perjanjian kerja ini berakhir;"],
        ["(b) PIHAK KEDUA melanggar kesepakatan yang tertuang dalam perjanjian kerja ini;"],
        ["(c) PIHAK KEDUA melakukan tindakan pelanggaran berat atau pelanggaran lain sebagaimana diatur dalam Peraturan Perusahaan atau perundang-undangan yang berlaku dibidang ketenagakerjaan;"],
        ["(d) PIHAK PERTAMA menilai PIHAK KEDUA tidak cakap/terampil dalam menjalankan pekerjaan atau peraturan tata tertib yang diberikan oleh PIHAK PERTAMA;"],
        ["(e) Mangkir atau tidak masuk kerja dengan alasan yang tidak dapat diterima PIHAK PERTAMA selama 5 (lima) hari atau lebih, berturut-turut atau tidak berturut-turut atau sering terlambat selama dalam jangka waktu perjanjian kerja;"],
        ["(f) PIHAK KEDUA melakukan tindakan yang mengakibatkan kerugian materiil maupun imateriil pada PIHAK PERTAMA;"],
        ["(g) PIHAK KEDUA tidak bersedia menerima mutasi;"],
        ["(h) PIHAK KEDUA membawa senjata tajam, obat terlarang, mabuk, berjudi, tidur pada saat jam kerja, merokok, mengancam atasan dan melakukan tindakan asusila dalam lingkungan perusahaan;"],
        ["(i) Membujuk atau berbuat sendiri pelanggaran perundang-undangan, peraturan perusahaan atau tata tertib/norma yang berlaku di perusahaan;"],
        ["(j) PIHAK KEDUA mendapat peringatan tertulis sebanyak 3 (tiga) kali berturut-turut dari PIHAK PERTAMA;"]        
    ];
    // Set ukuran dan indentasi
    $colWidth = 180; // Lebar kolom penuh
    $rowHeight = 4;  // Tinggi baris
    $indent = 5;    // Indentasi awal
    
    foreach ($hbkitem as $or) {
        // Buat indentasi awal dengan Cell kosong
        $pdf->Cell($indent, $rowHeight, '', 0, 0);
        
        // Cetak teks dalam MultiCell agar bisa pindah ke baris berikutnya jika panjang
        $pdf->MultiCell($colWidth, $rowHeight, $or[0], 0, 'J');
    }
    

    $hbk = [
        'Jika terjadi pemutusan hubungan kerja sesuai ayat 1 (satu) dan 2 (dua) tersebut diatas maka PIHAK PERTAMA tidak berkewajiban membayarkan kompensasi, uang pesangon, penghargaan masa kerja dan penggantian hak atau kompensasi dalam bentuk apapun dengan berakhirnya hubungan kerja ini kepada PIHAK KEDUA.'
        
    ];
    $no = 3; // Mulai dari nomor 1
    foreach ($hbk as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 40, 0, 'J');
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
        $no++; // Tambah nomor
    }
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 8', 0, 1, 'C');
    $pdf->Cell(0, 4, 'PENYELESAIAN PERSELISIHAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $pdf->MultiCell(0, 4, 'Jika terjadi perselisihan berkaitan dengan perjanjian kerja waktu tertentu ini, maka PARA PIHAK akan menyelesaikan setiap perselisihan yang timbul dengan cara musyawarah dan mufakat, jika tidak ditemukan kata mufakat, maka PARA PIHAK sepakat menyelesaikan masalah tersebut melalui Dinas Ketenagakerjaan setempat dan/atau Pengadilan Hubungan Industrial.', 0, 'J');
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 9', 0, 1, 'C');
    $pdf->Cell(0, 4, 'LAIN-LAIN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);

    $ll = [
    'PIHAK PERTAMA tidak bertanggung jawab atas janji-janji lisan yang diberikan oleh siapapun juga yang bertentangan dengan ketentuan yang tercantum dalam perjanjian kerja ini.',
    'Perjanjian kerja ini meniadakan semua perjanjian kerja, perundingan, janji, dan/atau persetujuan antara PARA PIHAK yang dibuat sebelum tanggal perjanjian kerja ini ditandatangani dan apabila terdapat kekeliruan dalam perjanjian kerja ini dikemudian hari dapat dilakukan perbaikan dengan persetujuan PARA PIHAK.',
    'Hal-hal lain yang belum diatur dalam perjanjian kerja ini diberlakukan dan dituangkan dalam amandemen atau perjanjian kerja atau Peraturan Perusahaan dengan merujuk pada peraturan perundang-undangan yang berlaku dibidang ketenagakerjaan dan merupakan bagian yang tidak terpisahkan dari perjanjian kerja waktu tertentu ini.',
    'Apabila jangka waktu perjanjian kerja ini sudah habis, namun PIHAK PERTAMA masih mempekerjakan PIHAK KEDUA dan belum dibuat/ditandatangani perpanjangan/perjanjian kerja yang baru, maka hubungan kerja antara PIHAK PERTAMA dan PIHAK KEDUA dianggap harian lepas/bersifat insidentil dan upah yang dibayarkan hanyalah berdasarkan kebijakan PIHAK PERTAMA.',
    'Perjanjian ini mengesampingkan ketentuan PP No. 35 tahun 2021, tentang Perjanjian Kerja Waktu Tertentu, alih daya, waktu kerja dan waktu istirahat, dan Pemutusan Hubungan Kerja, pasal 4 ayat (2), dan PIHAK PERTAMA dan PIHAK KEDUA bersepakat bahwa apabila dilakukan perpanjangan dan atau pembaharuan Perjanjian Kerja Waktu Tertentu (PKWT) lebih dari satu kali atau lebih dari waktu yang ditentukan maka ketentuan PP No. 35 tahun 2021 pasal 8 ayat (1), (2) tidak lagi mempunyai kekuatan mengikat dan demikian juga PIHAK PERTAMA dan PIHAK KEDUA pada saat Perjanjian Kerja Waktu Tertentu berakhir maka PIHAK PERTAMA tidak berkewajiban memberikan uang pesangon, penghargaan masa kerja, uang penggantian hak dan kompensasi lain dalam bentuk apapun kepada PIHAK KEDUA dan PIHAK KEDUA tidak akan menuntut uang pesangon, uang perhargaan masa kerja, uang penggantian hak dan kompensasi lain dalam bentuk apapun kepada PIHAK PERTAMA.',
    'Segala keluh kesah yang berhubungan dengan Hubungan Kerja ini disampaikan kepada manajemen perusahaan dan tidak diumbar ke media sosial yang dapat menyebabkan pencemaran nama baik atau perbuatan tidak menyenangkan bagi perusahaan, pimpinan atau rekan sekerja. Pelanggaran ini dapat menyebabkan pelaku dituntut dimuka hukum sesuai peraturan perundangan-undangan yang berlaku.'
    ];
    $no = 1; // Mulai dari nomor 1
    foreach ($ll as $text) {
        $indent = "   "; // Spasi untuk indentasi awal
        $numbering = $no . ")   "; // Nomor
        $indentWidth = $pdf->GetStringWidth($numbering); // Lebar nomor
        // Tampilkan nomor sebagai cell agar tidak masuk ke dalam MultiCell
        $pdf->Cell($indentWidth, 4, $numbering, 40, 0, 'J');
        // MultiCell untuk teks dengan indentasi pada baris kedua dan seterusnya
        $pdf->MultiCell(0, 4, $text, 0, 'J');
        $no++; // Tambah nomor
    }
    $pdf->Ln(2);
    $pdf->MultiCell(0, 4, 'Demikian Perjanjian Kerja Waktu Tertentu ini dibuat dan ditandatangani pada hari dan tanggal tersebut pada awal Perjanjian, bermaterai cukup, mempunyai kekuatan hukum dan pembuktian.', 0, 'J');
    $pdf->Ln(2);

// Tanda tangan
$spacing = 100;
$colWidth = 100;
$signatureWidth = 60;
$signatureHeight = 25;
$startX = $pdf->GetX();
$startY = $pdf->GetY();

// Header tanda tangan
$pdf->Cell($spacing, 6, "PIHAK PERTAMA", 0, 0, 'C');
$pdf->Cell($spacing, 6, "PIHAK KEDUA", 0, 1, 'C');

// Path tanda tangan dari server
$signaturePath1 = '../../includes/hendraginting.png'; // Path tanda tangan PIHAK PERTAMA
// Fungsi untuk menampilkan tanda tangan dari file lokal
function displaySignatureFromFile($pdf, $signatureX, $signatureY, $signatureWidth, $signatureHeight, $signaturePath) {
    if (file_exists($signaturePath)) {
        $pdf->Image($signaturePath, $signatureX, $signatureY, $signatureWidth, $signatureHeight);
    } else {
        error_log("File signature tidak ditemukan: " . $signaturePath);
    }
}

// Fungsi menampilkan tanda tangan
function displaySignature($pdf, $signatureX, $signatureY, $signatureWidth, $signatureHeight, $signatureData) {
    if (!empty($signatureData)) {
        $data = explode(',', $signatureData);
        if (count($data) == 2) {
            $image_data = base64_decode($data[1]);
            $temp_image = tempnam(sys_get_temp_dir(), 'sig') . '.png';
            file_put_contents($temp_image, $image_data);

            $pdf->Image($temp_image, $signatureX, $signatureY, $signatureWidth, $signatureHeight);
            unlink($temp_image);
        }
    }
}


// Tampilkan tanda tangan jika ada
if (!empty($row['signature'])) {
    $signatureX1 = $startX + (($colWidth - $signatureWidth) / 2); // Posisi X tengah PIHAK PERTAMA
    $signatureX2 = $startX + $spacing + (($colWidth - $signatureWidth) / 2); // Posisi X tengah PIHAK KEDUA
    $signatureY = $pdf->GetY();

    displaySignatureFromFile($pdf, $signatureX1, $signatureY, $signatureWidth, $signatureHeight, $signaturePath1);
    displaySignature($pdf, $signatureX2, $signatureY, $signatureWidth, $signatureHeight, $row['signature']);
}

// Ruang untuk tanda tangan
$pdf->Ln(30);
$pdf->SetFont('Times', 'U', 10);
$pdf->Cell($spacing, 6, "HENDRA GINTING, SH", 0, 0, 'C');
$pdf->SetFont('Times', 'U', 10);
$pdf->Cell($spacing, 6, "".htmlspecialchars($row['nama_lengkap']), 0, 1, 'C');

$pdf->SetFont('Times', '', 10);
$pdf->Cell($spacing, 2, "HRD", 0, 0, 'C');
$pdf->Cell($spacing, 2, "KARYAWAN", 0, 1, 'C');


// Output PDF
if (isset($return_blob) && $return_blob) {
    return $pdf->Output('S');
} else {
    $pdf->Output('D', 'kontrak_ttd' . $row['nik'] . '.pdf');
}

// Tutup koneksi (Hanya jika tidak sedang di-include)
if (!isset($return_blob)) {
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
}
?>