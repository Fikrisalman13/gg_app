<?php
ob_start(); // Mencegah output sebelum PDF dibuat

require 'vendor/autoload.php'; // Load Composer autoload
require 'db.php'; // Koneksi database

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

if (!isset($_GET['no_bale'])) {
    die("No Bale tidak dipilih.");
}

$noBaleList = $_GET['no_bale'];

// Inisialisasi TCPDF
$pdf = new TCPDF();
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetMargins(0, 0, 0);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

foreach ($noBaleList as $no_bale) {
    // Ambil data dari database
    $query = "SELECT * FROM bale_r WHERE no_bale = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $no_bale);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        continue; // Skip jika data tidak ditemukan
    }

    $data = $result->fetch_assoc();

    // Generate QR Code
    $qrCode = QrCode::create($no_bale)
        ->setEncoding(new Encoding('UTF-8'))
        ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
        ->setSize(150)
        ->setMargin(5);

    $writer = new PngWriter();
    $qrResult = $writer->write($qrCode);

    // Simpan QR Code sementara
    $qrOutput = 'temp_qr_' . $no_bale . '.png';
    $qrResult->saveToFile($qrOutput);

    // Tambah Halaman PDF (Landscape 2.15" x 3.161")
    $pdf->AddPage('L', array(54.6, 80.3));
    $pdf->SetFont('helvetica', '', 10);

    // QR Code di kiri atas tanpa margin
    $pdf->Image($qrOutput, 3, 3, 30, 30, 'PNG');

    // No Bale (Bold)
    $pdf->SetFont('helvetica', 'B', 10); // Bold
    $pdf->SetXY(37, 5);
    $pdf->Cell(0, 6, " " . $data['no_bale'], 0, 1);
  $pdf->SetXY(37, 1);
    $pdf->Cell(0, 6, " " . $data['packing_no'], 0, 1);
    // Kembalikan ke normal untuk teks lain
    $pdf->SetFont('helvetica', '', 10);

    // Data di samping QR Code
    $pdf->SetXY(35, 10);
    $pdf->Cell(0, 6, $data['QTY_batch'] . "                     Batch", 0, 1);
    $pdf->SetXY(35, 15);
    $pdf->Cell(0, 6, $data['QTY_Meter'] . "              Meter", 0, 1);
    $pdf->SetXY(35, 20);
    $pdf->Cell(0, 6, $data['QTY_yard'] . "              Yard", 0, 1);
    $pdf->SetXY(35, 25);
    $pdf->Cell(0, 6, $data['QTY_kg'] . "                  KG", 0, 1);

    // No CP, Prod Code, Nama Product di bawah QR Code
    $pdf->SetXY(35, 35);
    $pdf->Cell(0, 6, $data['no_cp'], 0, 1);
    $pdf->SetXY(5, 40);
    $pdf->Cell(0, 6, "" . $data['prod_cod'], 0, 1);
    $pdf->SetXY(5, 45);
    $pdf->Cell(0, 6, "" . $data['Nama_product'], 0, 1);

    // Hapus QR Code sementara
    unlink($qrOutput);
}

// Tampilkan PDF di browser
ob_end_clean(); // Bersihkan output sebelum kirim PDF
$pdf->Output('No_Bale_QR.pdf', 'I');
exit;
