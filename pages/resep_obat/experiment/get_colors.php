<?php
require_once __DIR__ . '/../../../koneksi3.php';
header('Content-Type: application/json');

$searchTerm = trim((string) ($_GET['q'] ?? ''));
if ($searchTerm === '') {
  echo json_encode(['results' => []]);
  exit;
}

try {
  // Kode warna berasal dari master warna. Relasi produk hanya metadata agar warna tanpa produk FG tetap dapat dipilih.
  $statement = $conn3->prepare("
    SELECT
      c.colormsid,
      c.colorcode,
      c.colorname,
      c.colordesc,
      ARRAY_REMOVE(
        ARRAY_AGG(
          DISTINCT NULLIF(TRIM(td.cuscolor), '')
          ORDER BY NULLIF(TRIM(td.cuscolor), '')
        ),
        NULL
      ) AS cuscolor_choices
    FROM pdcolorms c
    LEFT JOIN smprodtechdata td ON td.colormsid = c.colormsid
    WHERE c.colorcode ILIKE :search_term
       OR c.colorname ILIKE :search_term
    GROUP BY c.colormsid, c.colorcode, c.colorname, c.colordesc
    ORDER BY c.colorcode
    LIMIT 30
  ");
  $statement->execute([':search_term' => '%' . $searchTerm . '%']);

  $results = [];
  while ($colorRow = $statement->fetch(PDO::FETCH_ASSOC)) {
    $choicesLiteral = trim((string) ($colorRow['cuscolor_choices'] ?? ''), '{}');
    $cusColorChoices = $choicesLiteral === '' ? [] : str_getcsv($choicesLiteral);
    $cusColorChoices = array_values(array_filter(
      array_map('trim', $cusColorChoices),
      static fn($value): bool => $value !== ''
    ));

    // Hindari auto-fill ambigu: hanya satu relasi Cus Color yang boleh diisikan otomatis.
    $cusColor = count($cusColorChoices) === 1 ? $cusColorChoices[0] : '';
    if (!$cusColorChoices && strpos((string) $colorRow['colordesc'], '/') !== false) {
      $cusColor = trim(explode('/', (string) $colorRow['colordesc'], 2)[0]);
    }

    $results[] = [
      'id' => $colorRow['colorcode'],
      'text' => $colorRow['colorcode'] . ' - ' . $colorRow['colorname'],
      'color_data' => [
        'colormsid' => $colorRow['colormsid'],
        'name' => $colorRow['colorname'],
        'desc' => $colorRow['colordesc'],
        'cus_color' => $cusColor,
        'cus_color_choices' => $cusColorChoices,
      ],
    ];
  }

  echo json_encode(['results' => $results]);
} catch (PDOException $exception) {
  http_response_code(500);
  echo json_encode([
    'results' => [],
    'error' => 'Pencarian kode warna gagal.',
  ]);
}
