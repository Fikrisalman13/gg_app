<?php
session_start();
require('../../koneksi.php');
require('../../libs/fpdf.php');

// Ambil parameter
$contract_id = isset($_GET['contract_id']) ? $_GET['contract_id'] : '';
$nik = isset($_GET['nik']) ? $_GET['nik'] : '';

if (empty($contract_id) || empty($nik)) {
    die("Parameter tidak lengkap.");
}

// Query untuk mengambil data kontrak yang sudah ditandatangani
$sql = "SELECT 
            kk.*,
            emp.nik, emp.nama_lengkap, emp.Photo, emp.tmp_lahir, emp.tgl_lahir, 
            emp.kelamin, emp.alamat, emp.no_ktp,
            dept.dept, bag.bagian, sub.subbag, jab.jabatan, gol.golongan, gol.umr,
            tt.signature
        FROM kontrak_kerja kk
        LEFT JOIN m_emp emp ON kk.nik = emp.nik
        LEFT JOIN m_dept dept ON emp.id_dept = dept.id_dept
        LEFT JOIN m_bag bag ON emp.id_bag = bag.id_bag
        LEFT JOIN m_subbag sub ON emp.id_subbag = sub.id_subbag
        LEFT JOIN m_jab jab ON emp.id_jab = jab.id_jab
        LEFT JOIN m_gol gol ON emp.id_gol = gol.id_gol
        LEFT JOIN tanda_tangan tt ON emp.nik = tt.nik
        WHERE kk.id = ? AND kk.nik = ? AND kk.status_tanda_tangan = '1'";

$params = array($contract_id, $nik);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
    die("Data kontrak tidak ditemukan atau belum ditandatangani.");
}

// Tentukan jenis karyawan
$jenis_karyawan = (in_array($row['id_gol'] ?? '', ['2','7','8','10','11'])) ? 'Kontrak' : 'Staff';

// Fungsi format tanggal
function formatTanggal($tanggal) {
    if (!$tanggal) return '0000-00-00';
    if ($tanggal instanceof DateTime) {
        return $tanggal->format('d-m-Y');
    }
    $date = DateTime::createFromFormat('Y-m-d H:i:s.u', $tanggal);
    if (!$date) {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $tanggal);
    }
    return $date ? $date->format('d-m-Y') : 'Invalid Date';
}

// Fungsi untuk menampilkan tanda tangan
function displaySignatureFromFile($pdf, $signatureX, $signatureY, $signatureWidth, $signatureHeight, $signaturePath) {
    if (file_exists($signaturePath)) {
        $pdf->Image($signaturePath, $signatureX, $signatureY, $signatureWidth, $signatureHeight);
    }
}

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

// Buat PDF berdasarkan jenis karyawan
$pdf = new FPDF('P','mm','A4');
$pdf->AddPage();

if ($jenis_karyawan == 'Kontrak') {
    // Kode PDF untuk KONTRAK (sama seperti yang Anda berikan)
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PT. SURYA USAHA MANDIRI', 0, 1, 'L');
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell(0, 4, 'Jl. Tarajusari No.8, Desa Tarajusari, Kec. Banjaran, Kab. Bandung', 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'Perjanjian Kerja Untuk Waktu Tertentu', 0, 1, 'C');
    $pdf->SetFont('Times', 'I', 10);
    $pdf->Cell(0, 4, '' . htmlspecialchars($row['nomor_dokumen']), 0, 1, 'C');
    $pdf->Ln(2);

    // ... (salin semua kode PDF kontrak dari file yang Anda berikan)
    // Data Karyawan
    $pdf->SetFont('Times', '', 10);
    $teks = "Pada hari ini ..., tanggal ..., bertempat di PT. Surya Usaha Mandiri...";
    $pdf->MultiCell(0, 4, $teks, 0, 'J');
    // ... dan seterusnya sampai bagian tanda tangan

} else {
    // Kode PDF untuk STAFF (sama seperti yang Anda berikan)
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 4, 'PT. SURYA USAHA MANDIRI', 0, 1, 'L');
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell(0, 4, 'Jl. Tarajusari No.8, Desa Tarajusari, Kec. Banjaran, Kab. Bandung', 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Times', 'BU', 10);
    $pdf->Cell(0, 4, 'SURAT PERJANJIAN KERJA STAFF', 0, 1, 'C');
    $pdf->SetFont('Times', 'I', 10);
    $pdf->Cell(0, 4, '' . htmlspecialchars($row['nomor_dokumen']), 0, 1, 'C');
    $pdf->Ln(2);

    // ... (salin semua kode PDF staff dari file yang Anda berikan)
}

// Bagian Tanda Tangan (sama untuk kedua jenis)
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
$signaturePath1 = '../../includes/hendraginting.png';

// Tampilkan tanda tangan jika ada
if (!empty($row['signature'])) {
    $signatureX1 = $startX + (($colWidth - $signatureWidth) / 2);
    $signatureX2 = $startX + $spacing + (($colWidth - $signatureWidth) / 2);
    $signatureY = $pdf->GetY();

    displaySignatureFromFile($pdf, $signatureX1, $signatureY, $signatureWidth, $signatureHeight, $signaturePath1);
    displaySignature($pdf, $signatureX2, $signatureY, $signatureWidth, $signatureHeight, $row['signature']);
}

// Ruang untuk tanda tangan
$pdf->Ln(30);
$pdf->SetFont('Times', 'U', 10);
$pdf->Cell($spacing, 6, "HENDRA GINTING, SH", 0, 0, 'C');
$pdf->SetFont('Times', 'U', 10);
$pdf->Cell($spacing, 6, "" . htmlspecialchars($row['nama_lengkap']), 0, 1, 'C');

$pdf->SetFont('Times', '', 10);
$pdf->Cell($spacing, 2, "HRD", 0, 0, 'C');
$pdf->Cell($spacing, 2, "KARYAWAN", 0, 1, 'C');

// Output PDF
$filename = ($jenis_karyawan == 'Kontrak' ? 'kontrak_ttd_' : 'staff_ttd_') . $row['nik'] . '_' . $contract_id . '.pdf';
$pdf->Output('D', $filename);

// Tutup koneksi
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>