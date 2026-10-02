<?php
session_start();
require('../../koneksi.php');
require('../../libs/fpdf.php'); // Sesuaikan dengan lokasi FPDF

header('Content-Type: application/json'); // Tambahkan header JSON

// Fungsi untuk generate nomor dokumen kontrak
function generateContractNumber() {
    global $conn;
    $year = date('Y');
    $month = date('m');

    $sql = "SELECT TOP 1 nomor_dokumen FROM kontrak_kerja 
            WHERE YEAR(tanggal_dokumen) = ? AND MONTH(tanggal_dokumen) = ? 
            ORDER BY id DESC";
    
    $stmt = sqlsrv_query($conn, $sql, array($year, $month));

    if ($stmt === false) {
        die(json_encode(["success" => false, "message" => "Error SQL: " . print_r(sqlsrv_errors(), true)]));
    }

    $last_number = 0;
    if (sqlsrv_has_rows($stmt) && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        // Ambil nomor terakhir dan ekstrak 4 digit pertama (misalnya dari 0001/PKWT-SUM/III/2025)
        preg_match('/^(\d{4})/', $row['nomor_dokumen'], $matches);
        $last_number = isset($matches[1]) ? (int) $matches[1] : 0;
    }

    sqlsrv_free_stmt($stmt); // Bebaskan resource query

    // Generate nomor baru
    $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    $month_formatted = str_pad($month, 2, '0', STR_PAD_LEFT);
    return $new_number . '/SPK-SUM/PERS/' . $month_formatted . '/' . $year;

}

function tanggalIndo($tanggal) {
    if (!$tanggal) return '-';
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

    $timestamp = is_numeric($tanggal) ? $tanggal : strtotime($tanggal);
    if (!$timestamp) return '-';

    $tgl = date('d', $timestamp);
    $bln = $bulan[date('F', $timestamp)];
    $thn = date('Y', $timestamp);

    return "$tgl $bln $thn";
}




// Ambil data NIK dari URL
$nik = isset($_GET['nik']) ? $_GET['nik'] : '';

// Get dates from GET parameters
$tgl_mulai_raw = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-d');
$tgl_selesai_raw = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d', strtotime('+3 months'));

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
            h.signature 
        FROM
            dbo.m_emp AS a
            LEFT JOIN dbo.m_dept AS b ON a.id_dept = b.id_dept
            LEFT JOIN dbo.m_bag AS c ON a.id_bag = c.id_bag
            LEFT JOIN dbo.m_subbag AS d ON a.id_subbag = d.id_subbag
            LEFT JOIN dbo.kontrak_kerja AS f ON a.nik = f.nik
            LEFT JOIN dbo.m_jab AS e ON a.id_jab = e.id_jab
            LEFT JOIN dbo.m_gol AS g ON a.id_gol = g.id_gol
            LEFT JOIN dbo.tanda_tangan AS h ON a.nik = h.nik
         WHERE
                a.aktif = '1' 
                AND
                a.id_gol IN ('1','3')
                AND
                a.nik = ?";

$stmt = sqlsrv_query($conn, $sql, array($nik));

$response = [];

if ($stmt === false) {
    $response['success'] = false;
    $response['message'] = 'Terjadi kesalahan saat mengambil data: ' . print_r(sqlsrv_errors(), true);
    echo json_encode($response);
    exit();
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

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($row) {
    $contract_number = generateContractNumber();

    $pdf = new FPDF('P','mm','A4');
    $pdf->AddPage();
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PT. SURYA USAHA MANDIRI', 0, 1, 'L');
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell(0, 4, 'Jl. Tarajusari No.8, Desa Tarajusari, Kec. Banjaran, Kab. Bandung', 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'SURAT PERJANJIAN KERJA STAFF', 0, 1, 'C');
    $pdf->SetFont('Times', 'I', 10);
    $pdf->Cell(0, 4, '' . $contract_number, 0, 1, 'C');
    $pdf->Ln(2);

    // Data Karyawan
    $pdf->SetFont('Times', '', 10);
    $teks = "Pada hari ini $hari, tanggal $tanggalIndonesia bertempat di PT. Surya Usaha Mandiri, Jl. Tarajusari No.8, Desa Tarajusari, Kec. Banjaran, Kab. Bandung, kami yang bertanda tangan dibawah ini:";
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
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'OBJEK DAN SIFAT PEKERJAAN', 0, 1, 'C');
    $pdf->Ln(2);

    $pdf->SetFont('Times', '', 10);
    $sifatperjanjian = [
    'Yang dimaksud perjanjian kerja ini adalah Perjanjian Kerja Waktu Tertentu.',
    'Perjanjian kerja ini dapat diputus lebih awal (sebelum jangka waktu perjanjian kerja berakhir) oleh PIHAK PERTAMA tergantung kondisi dan kebutuhan PIHAK PERTAMA.',
    'Apabila PIHAK KEDUA akan berhenti bekerja baik sebelum masa kontrak kerja berakhir, maupun disesuaikan ketika kontrak berakhir, maka tetap harus mengajukan pengunduran diri dengan memenuhi syarat sebagai berikut :'
    
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

$odspitem = [
    [" a. Mengajukan pengunduran diri secara tertulis kepada PIHAK PERTAMA selambat-lambatnya:
    - 30 (tiga puluh) hari untuk level Operator, ADM, Staff, Kasie;
    - 60 (enam puluh) hari untuk level Kabag;
    - 90 (sembilan puluh) hari untuk level Kadept dan GM;
    Sebelum tanggal mulai pengunduran diri."],
    [" b. PIHAK KEDUA tetap melaksanakan kewajibannya dengan baik sampai tanggal pengunduran diri."],
    [" c. Jika PIHAK KEDUA keluar bekerja tidak prosedural, artinya PIHAK KEDUA secara sukarela melepaskan haknya untuk memperoleh upah terakhir, surat keterangan kerja, dll."]

];
// Set ukuran dan indentasi
$colWidth = 180; // Lebar kolom penuh
$rowHeight = 4;  // Tinggi baris
$indent = 5;    // Indentasi awal

foreach ($odspitem as $or) {
    // Buat indentasi awal dengan Cell kosong
    $pdf->Cell($indent, $rowHeight, '', 0, 0);
    
    // Cetak teks dalam MultiCell agar bisa pindah ke baris berikutnya jika panjang
    $pdf->MultiCell($colWidth, $rowHeight, $or[0], 0, 'J');
}

$sifatperjanjian = [
    'PARA PIHAK memahami dan saling mengerti bahwa perjanjian kerja ini dibuat dan disepakati sesuai kondisi dan ketentuan yang berlaku sebagaimana diatur dalam peraturan perundang-undangan dibidang ketenagakerjaan yang berlaku pada umumnya dan khususnya peraturan perusahaan yang mengikat.'
    
    ];
    $no = 4; // Mulai dari nomor 1
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
  
$pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 2', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'LOKASI DAN JENIS PEKERJAAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $lokasidanpekerjaan = [
        'PIHAK PERTAMA dengan ini menyatakan bersedia untuk menerima dan mempekerjakan PIHAK KEDUA dan PIHAK KEDUA dengan ini menyatakan bersedia untuk bekerja di perusahaan PIHAK PERTAMA dan bersedia ditempatkan pada:'
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
    $pdf->Cell($indent, 4, '', 0, 0);
    $pdf->Cell($labelWidth, 4, 'Atau order lain yang mungkin timbul pada saat perjanjian kerja waktu tertentu ini masih masih berlangsung.', 0, 0);
    $pdf->Ln(4);
        
$lokasidanpekerjaan = [
    'PIHAK KEDUA menyatakan bersedia dan sanggup menerima/melaksanakan pekerjaan tersebut dengan sebaik-baiknya dan apabila dipandang perlu PIHAK PERTAMA dapat menempatkan atau memindahkan PIHAK KEDUA dari satu Departemen/bagian ke Departemen/bagian yang lain tanpa meminta persetujuan PIHAK KEDUA.',
    'PIHAK KEDUA dengan ini menyatakan akan tunduk dan patuh terhadap segala tata tertib, dan sistem kerja yang berlaku di lingkungan perusahaan PIHAK PERTAMA.'
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


    // Tanggal otomatis dengan format Bahasa Indonesia
    $tanggalMulai   = tanggalIndo(date('Y-m-d')); // Hari ini


    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 3', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'JANGKA WAKTU PERJANJIAN KERJA', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);

    $tgl_mulai_indo = tanggalIndo($tgl_mulai_raw);
    $tgl_selesai_indo = tanggalIndo($tgl_selesai_raw);

    $jangkawaktuperjanjian = [
        "Perjanjian kerja ini mulai berlaku terhitung sejak tanggal $tgl_mulai_indo sampai dengan tanggal $tgl_selesai_indo atau selesainya pekerjaan yang ditentukan oleh PIHAK PERTAMA.",
        "Pada saat berakhirnya waktu perjanjian ini, pemberian uang kompensasi dan besaran kompensasi semata-mata berdasarkan kebijakan dan kemampuan perusahaan."
    ];

    $no = 1;
    foreach ($jangkawaktuperjanjian as $text) {
        $numbering = $no . ")   ";
        $indentWidth = $pdf->GetStringWidth($numbering);
        $pdf->Cell($indentWidth, 4, $numbering, 0, 0, 'L');
        $pdf->MultiCell(0, 4, $text, 0, 'J');
        $no++;
    }
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 4', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'GAJI, FASILITAS DAN ASURANSI', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $upah =[
        'Untuk pekerjaan sebagaimana diatur dalam perjanjian kerja ini, PIHAK KEDUA akan mendapatkan hak berupa gaji dari PIHAK PERTAMA sesuai dengan kesepakatan antara PIHAK PERTAMA dan PIHAK KEDUA.',
        'PIHAK PERTAMA dapat mengenakan denda atau ganti rugi pada PIHAK KEDUA dengan memotong langsung gaji yang diterima PIHAK KEDUA apabila PIHAK KEDUA melakukan pelanggaran/kesalahan yang merugikan PIHAK PERTAMA, dengan ditandatanganinya perjanjian ini secara otomatis PIHAK KEDUA telah memberi kuasa terhadap PIHAK PERTAMA untuk melakukan pemotongan gaji, yang mana kuasa tersebut tidak dapat dicabut kembali dan PARA PIHAK sepakat untuk mengabaikan pasal-pasal yang berkaitan dengan pemberian kuasa di KUH Perdata.',
        'Pajak atas gaji yang diterima PIHAK KEDUA menjadi beban dan tanggung jawab PIHAK KEDUA. PIHAK KEDUA wajib membuat dan menyerahkan salinan NPWP kepada PIHAK PERTAMA untuk keperluan pajak atas gaji PIHAK KEDUA.',
        'Gaji dibayarkan setiap awal bulan dan ditransfer ke Rekening Bank yang ditunjuk PIHAK PERTAMA atas nama PIHAK KEDUA.',
        'Gaji bulan pertama bekerja atau bulan terakhir bekerja akan dibayarkan secara proporsional sesuai dengan kehadiran PIHAK KEDUA (bila mulai bekerja atau berhenti bekerja tidak sesuai periode upah).',
        'Bahwa sistem perhitungan gaji tersebut berdasarkan hari kerja dari setiap tanggal 23 sampai dengan tanggal 22 bulan berikutnya (30 hari).',
        'Fasilitas dan Asuransi yang diberikan oleh PIHAK PERTAMA kepada PIHAK KEDUA yakni : BPJS Ketenagakerjaan, BPJS Kesehatan/ Fasilitas Kesehatan, Fasilitas makan siang di  tempat yang telah disediakan. Fasilitas-fasilitas tersebut akan diberikan sesuai kebijakan dan kemampuan perusahaan.',
        'PIHAK KEDUA akan menerima Tunjangan Hari Raya yang perhitungan dan besarannya sesuai kebijakan perusahaan.'
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
    
    $pdf->Ln(2);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 5', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'HARI DAN JAM KERJA', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $jamkerja = [
        "PIHAK PERTAMA memperkerjakan PIHAK KEDUA dengan waktu kerja sebagai berikut:\n" .
        "- Senin - Jumat : Pkl. 07.20 - 16.15 WIB\n" .
        "- Sabtu              : Pkl. 07.20 - 12.15 WIB (Tanpa Istirahat)\n" .
        "- Istirahat diatur secara bergilir selama 40 menit dan khusus untuk hari Jumat istirahat selama 1 jam 15 menit.",
        
        "Tanpa mengesampingkan ketentuan jam kerja di atas, PIHAK KEDUA bersedia bekerja dengan sistem shift sesuai kebutuhan PIHAK PERTAMA.",
        
        "Dalam kondisi tertentu, PIHAK PERTAMA dapat mengubah hari, jam kerja atau hari libur sesuai kebutuhan operasional."
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
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'HAK DAN KEWAJIBAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $hdk =[
        'PIHAK PERTAMA memberitahukan kepada PIHAK KEDUA ketentuan-ketentuan yang berlaku dalam tata tertib, prosedur, syarat atau aturan kerja yang terkait dengan tugas/pekerjaan PIHAK KEDUA dan PIHAK KEDUA wajib mempelajari, memahami serta melaksanakan ketentuan-ketentuan yang terkandung didalamnya.',
        'PIHAK KEDUA bersedia untuk mematuhi segala bentuk peraturan tata tertib, prosedur, syarat atau aturan kerja yang terkait dengan tugas/pekerjaan yang diberikan oleh PIHAK PERTAMA dan siap menerima sanksi yang diberikan oleh PIHAK PERTAMA apabila ternyata PIHAK KEDUA tidak mematuhi hal-hal yang telah disepakati dalam perjanjian kerja ini.',
        'PIHAK KEDUA wajib melaksanakan instruksi yang diberikan PIHAK PERTAMA, tugas dan kewajibannya dengan sungguh-sungguh, penuh tanggung jawab sesuai prosedur kerja yang berlaku dan apabila berhalangan hadir kerja diwajibkan meminta ijin atau menginformasikan kepada atasan atau HRD kemudian memberikan keterangan tertulis ditunjang bukti terkait kepada PIHAK PERTAMA sesuai prosedur/ketentuan yang berlaku',
        'Penyerahan keterangan tertulis dan bukti terkait tersebut diserahkan oleh PIHAK KEDUA kepada PIHAK PERTAMA selambat-lambatnya pada saat PIHAK KEDUA masuk bekerja kembali.',
        'PIHAK KEDUA wajib mengikuti pelatihan, program pengembangan karyawan, atau kegiatan perusahaan resmi lainnya yang diadakan PIHAK PERTAMA.',
        'Dalam arti seluas-luasnya PIHAK KEDUA akan tunduk dan patuh atas segala ketentuan dan tata tertib perusahaan serta perintah PIHAK PERTAMA'
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
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 7', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'BERAKHIRNYA HUBUNGAN KERJA', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $hbk = [
        'Dengan berakhirnya jangka waktu perjanjian kerja ini, maka berakhir pula hubungan kerja antara PIHAK PERTAMA dan PIHAK KEDUA, tidak ada kewajiban PIHAK PERTAMA untuk mempekerjakan kembali PIHAK KEDUA kecuali atas kesepakatan kembali dari kedua belah pihak melalui perjanjian kerja yang baru.',
        'Dalam kondisi yang disebutkan dibawah ini, perjanjian kerja dapat diakhiri setiap saat secara sepihak oleh PIHAK PERTAMA apabila :'
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
        ["(a) Kebutuhan pekerjaan menurut PIHAK PERTAMA tidak ada atau pekerjaan selesai sebelum jangka waktu perjanjian kerja ini berakhir;"],
        ["(b) PIHAK KEDUA melanggar kesepakatan yang tertuang dalam perjanjian kerja ini;"],
        ["(c) PIHAK KEDUA melakukan tindakan pelanggaran berat atau pelanggaran lainnya sebagaimana diatur dalam peraturan perusahaan atau perundang-undangan yang berlaku dibidang ketenagakerjaan;"],
        ["(d) PIHAK PERTAMA menilai PIHAK KEDUA tidak cakap/terampil dalam menjalankan pekerjaan yang diberikan oleh PIHAK PERTAMA;"],
        ["(e) Mangkir atau tidak masuk kerja dengan alasan yang tidak dapat diterima PIHAK PERTAMA selama 5 (lima) hari berturut-turut atau tidak berturut-turut selama dalam jangka waktu perjanjian kerja;"],
        ["(f) PIHAK KEDUA melakukan tindakan yang mengakibatkan kerugian materiil maupun imateriil pada PIHAK PERTAMA;"],
        ["(g) PIHAK KEDUA tidak bersedia menerima mutasi;"],
        ["(h) PIHAK KEDUA menyalah gunakan jabatannya, menyabotase sistem dan atau data perusahaan, membawa senjata tajam, obat terlarang, mabuk, berjudi, merokok dan tidur pada saat jam kerja dalam lingkungan perusahaan;"],
        ["(i) Membujuk atau berbuat sendiri dalam lingkungan perusahaan yang bertentangan dengan peraturan perundang-undangan maupun peraturan perusahaan yang berlaku;"],
        ["(j) PIHAK KEDUA mendapat peringatan tertulis sebanyak 2 (dua) kali berturut-turut dari PIHAK PERTAMA;"],  
        ["(k) PIHAK KEDUA memasuki usia pensiun;"]      
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
        'Jika terjadi pemutusan hubungan kerja sesuai ayat 1 (satu) dan 2 (dua) tersebut diatas maka PIHAK PERTAMA tidak berkewajiban membayarkan uang kompensasi dalam bentuk apapun dengan berakhirnya hubungan kerja ini kepada PIHAK KEDUA.'
        
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
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'ITIKAD BAIK', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $pdf->MultiCell(0, 4, 'PARA PIHAK menjamin bahwa masing-masing PIHAK akan melaksanakan Perjanjian ini dengan itikad baik dan jujur serta mematuhi sepenuhnya prinsip-prinsip Etika Bisnis. Tidak satupun ketentuan dan atau penafsiran atas ketentuan dalam Perjanjian ini atau ketidakjelasan dalam Perjanjian ini akan digunakan oleh SATU PIHAK untuk mengambil keuntungan secara tidak wajar dan mengakibatkan kerugian bagi PIHAK lainnya dan tidak satupun ketentuan dalam Perjanjian ini dimaksud untuk memberikan keuntungan secara tidak wajar kepada salah SATU PIHAK.', 0, 'J');
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 9', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'KERAHASIAAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);

    $ll = [
        "Tanpa persetujuan tertulis dari PIHAK lainnya, PARA PIHAK dilarang memberitahukan, membuka, atau memberikan informasi, rahasia dagang, ketentuan, dan/atau yang sejenisnya yang menyangkut isi atau yang berhubungan dengan Perjanjian ini kepada PIHAK lain di luar Perjanjian ini, baik yang berupa badan hukum maupun perseorangan, kecuali:\n" .
        "a. Instansi pemerintah yang berwenang mengatur atau mengeluarkan izin tentang hal-hal yang diperjanjikan dalam Perjanjian ini.\n" .
        "b. Diperintahkan oleh badan peradilan atau instansi pemerintah lainnya yang berhubungan dalam penegakan hukum secara tertulis, resmi, dan merupakan putusan final.\n" .
        "c. Menurut peraturan perundang-undangan yang berlaku di Indonesia, informasi tersebut harus diberikan kepada pihak lain yang disebut secara jelas dalam peraturan perundang-undangan tersebut.",
        
        "PIHAK KEDUA wajib menjaga kerahasiaan atas segala informasi, baik yang tertulis maupun lisan, yang dapat mengakibatkan kerugian PIHAK PERTAMA kecuali telah mendapat persetujuan tertulis dari PIHAK PERTAMA."
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
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 10', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'PENYELESAIAN PERSELISIHAN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);
    $pdf->MultiCell(0, 4, 'Jika terjadi perselisihan berkaitan dengan perjanjian kerja waktu tertentu ini, maka Para Pihak akan menyelesaikan setiap perselisihan yang timbul dengan cara musyawarah dan mufakat, jika tidak ditemukan kata mufakat, maka Para Pihak sepakat menyelesaikan masalah tersebut melalui Dinas Ketenagakerjaan setempat dan/ atau Pengadilan Hubungan Industrial setempat.', 0, 'J');
    $pdf->Ln(2);

    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PASAL 11', 0, 1, 'C');
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'LAIN-LAIN', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Times', '', 10);

    $lila = [
    'Dalam perjanjian kerja ini PIHAK KEDUA sepakat untuk menerima Peraturan Perusahaan (PP) atau Perjanjian Kerja Bersama (PKB) atau peraturan lain yang berlaku di perusahaan merupakan satu kesatuan yang tidak terpisahkan dari perjanjian ini.',
    'Perjanjian kerja ini menghapuskan semua masa kerja yang ada sebelumnya.',
    'Apabila terdapat kekeliruan dalam perjanjian kerja ini dikemudian hari dapat dilakukan perbaikan dengan persetujuan PARA PIHAK.',
    'Hal-hal lain yang belum diatur dalam perjanjian kerja ini diberlakukan dan dituangkan dalam amandemen atau addendum perjanjian kerja dengan merujuk pada peraturan perundang-undangan yang berlaku dibidang ketenagakerjaan dan merupakan bagian yang tidak terpisahkan dari perjanjian kerja waktu tertentu ini.',
    'Apabila jangka waktu perjanjian kerja ini sudah habis, namun PIHAK PERTAMA masih mempekerjakan PIHAK KEDUA dan belum dibuat/ ditandatangani perpanjangan/ perjanjian kerja yang baru, maka hubungan kerja antara PIHAK PERTAMA dan PIHAK KEDUA dianggap harian lepas/ bersifat insidentil dan gaji yang dibayarkan hanyalah berdasarkan kebijakan PIHAK PERTAMA.',
    'Perjanjian ini mengesampingkan ketentuan PP No. 35 tahun 2021, tentang Perjanjian Kerja Waktu Tertentu, alih daya, waktu kerja dan waktu istirahat, dan Pemutusan Hubungan Kerja, khususnya tentang jangka waktu perjanjian sebagaimana yang dimaksud dalam Pasal 4, Pasal 5, dan aturan tetang kompensasi, pesangon, dll. Kecuali terhadap hal-hal yang telah secara tegas dituangkan dalam perjanjian ini.',
    'Segala keluh kesah yang berhubungan dengan Hubungan Kerja ini disampaikan kepada manajemen perusahaan dan tidak di umbar ke media sosial yang dapat menyebabkan pencemaran nama baik atau perbuatan tidak menyenangkan bagi perusahaan, pimpinan atau rekan sekerja. Pelanggaran ini terhadap ketentuan dapat menyebabkan pelaku dituntut dimuka hukum sesuai peraturan perundangan-undangan yang berlaku.'
    
    ];
    $no = 1; // Mulai dari nomor 1
    foreach ($lila as $text) {
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
    $pdf->MultiCell(0, 4, 'Demikian Perjanjian Kerja Waktu Tertentu ini dibuat dan ditandatangani pada hari dan tanggal tersebut pada awal Perjanjian, bermaterai cukup, mempunyai kekuatan hukum dan pembuktian yang sama bagi PARA PIHAK.', 0, 'J');
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

    // Tanda tangan
$spacing = 100;
// Ruang untuk tanda tangan
$pdf->Ln(30);
$pdf->SetFont('Times', 'U', 10);
$pdf->Cell($spacing, 6, "HENDRA GINTING, SH", 0, 0, 'C');
$pdf->SetFont('Times', 'U', 10);
$pdf->Cell($spacing, 6, "".htmlspecialchars($row['nama_lengkap']), 0, 1, 'C');

$pdf->SetFont('Times', '', 10);
$pdf->Cell($spacing, 2, "HRD", 0, 0, 'C');
$pdf->Cell($spacing, 2, "KARYAWAN", 0, 1, 'C');
    
    
    // Generate Output PDF
    $pdf_output = $pdf->Output('S');

    if ($pdf_output === false) {
        $response['success'] = false;
        $response['message'] = 'Gagal menghasilkan file PDF.';
        echo json_encode($response);
        exit();
    }

    // Insert ke Database
    $tanggal_dokumen = date('Y-m-d'); // Tanggal saat ini
    $insert_sql = "INSERT INTO kontrak_kerja (nik, nama_lengkap, nomor_dokumen, tanggal_dokumen, file_pdf, status_tanda_tangan) 
                   OUTPUT INSERTED.id
                   VALUES (?, ?, ?, ?, CONVERT(VARBINARY(MAX), ?), 0)";
    
    $insert_stmt = sqlsrv_query($conn, $insert_sql, array(
        $row['nik'], 
        $row['nama_lengkap'], 
        $contract_number, 
        $tanggal_dokumen,
        $pdf_output
    ));

    if ($insert_stmt === false || !($row_id = sqlsrv_fetch_array($insert_stmt, SQLSRV_FETCH_ASSOC))) {
        $response['success'] = false;
        $response['message'] = 'Gagal menyimpan data kontrak: ' . print_r(sqlsrv_errors(), true);
        echo json_encode($response);
        exit();
    }

    $last_id = $row_id['id'];

    // Simpan ke kontrak_kerja_perjanjian
    $insert_perjanjian_sql = "INSERT INTO kontrak_kerja_perjanjian (id_kontrak, tgl_mulai, tgl_selesai, created_at) VALUES (?, ?, ?, GETDATE())";
    $insert_perjanjian_stmt = sqlsrv_query($conn, $insert_perjanjian_sql, array($last_id, $tgl_mulai_raw, $tgl_selesai_raw));
    
    if ($insert_perjanjian_stmt === false) {
        $response['success'] = false;
        $response['message'] = 'Gagal menyimpan data perjanjian: ' . print_r(sqlsrv_errors(), true);
        echo json_encode($response);
        exit();
    }

    // Sukses
    $response['success'] = true;
    $response['message'] = 'PDF berhasil digenerate dan disimpan dengan nomor dokumen: ' . $contract_number;
} else {
    $response['success'] = false;
    $response['message'] = 'Data tidak ditemukan.';
}

sqlsrv_free_stmt($stmt);
echo json_encode($response);
?>
