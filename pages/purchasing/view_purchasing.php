<?php
session_start();
include_once file_exists(__DIR__ . '/../../koneksi.php') ? __DIR__ . '/../../koneksi.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = intval($_GET['id'] ?? 1);

// Ambil data dari Database SQL Server jika tabel sudah ada
$detail = null;
if (!empty($conn)) {
    $qCheckTable = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'purchasing_header'");
    if ($qCheckTable && sqlsrv_has_rows($qCheckTable)) {
        $qDoc = sqlsrv_query($conn, "SELECT * FROM dbo.purchasing_header WHERE id = ? AND is_deleted = 0", [$id]);
        if ($qDoc && ($dRow = sqlsrv_fetch_array($qDoc, SQLSRV_FETCH_ASSOC))) {
            $descList = [];
            $qDesc = sqlsrv_query($conn, "SELECT description FROM dbo.purchasing_detail WHERE header_id = ? ORDER BY row_order ASC", [$id]);
            if ($qDesc) {
                while ($dr = sqlsrv_fetch_array($qDesc, SQLSRV_FETCH_ASSOC)) {
                    $descList[] = $dr['description'];
                }
            }

            $poItems = [];
            if ($dRow['is_multi_po'] == 1) {
                $qPo = sqlsrv_query($conn, "SELECT item_no, no_po, description, vendor, no_grn FROM dbo.purchasing_po_items WHERE header_id = ? ORDER BY item_no ASC", [$id]);
                if ($qPo) {
                    while ($pr = sqlsrv_fetch_array($qPo, SQLSRV_FETCH_ASSOC)) {
                        $poItems[] = [
                            'no'     => $pr['item_no'],
                            'desc'   => $pr['description'],
                            'vendor' => $pr['vendor'],
                            'no_po'  => $pr['no_po'],
                            'no_grn' => $pr['no_grn']
                        ];
                    }
                }
            }

            $detail = [
                'id'              => $dRow['id'],
                'tanggal'         => $dRow['tanggal'] ? $dRow['tanggal']->format('Y-m-d') : date('Y-m-d'),
                'type'            => $dRow['type'],
                'type_keterangan' => $dRow['type_keterangan'] ?? '',
                'is_multi_po'     => ($dRow['is_multi_po'] == 1),
                'po_items'        => $poItems,
                'descriptions'    => $descList,
                'vendor'          => $dRow['vendor'],
                'no_po'           => $dRow['no_po'],
                'no_grn'          => $dRow['no_grn'],
                'pengirim'        => $dRow['pengirim'],
                'penerima'        => $dRow['penerima'],
                'status_ttd_raw'  => $dRow['status_ttd'],
                'signed_by'       => $dRow['signed_by'],
                'signed_at'       => $dRow['signed_at'] ? $dRow['signed_at']->format('d/m/Y H:i') : null,
                'signature_data'  => $dRow['signature_data'] ?? ''
            ];
        }
    }
}

// Fallback jika belum ada di database, ambil dari session
if (!$detail && !empty($_SESSION['purchasing_data']) && is_array($_SESSION['purchasing_data'])) {
    foreach ($_SESSION['purchasing_data'] as $item) {
        if ($item['id'] === $id) {
            $detail = $item;
            break;
        }
    }
}

// Fallback jika tidak ditemukan di session
if (!$detail) {
    $defaultList = [
        1 => [
            'id' => 1,
            'tanggal' => '2026-09-21',
            'type' => 'COD',
            'type_keterangan' => '',
            'is_multi_po' => false,
            'descriptions' => [
                'Pipot Volumettnc Rp.94.350',
                'PO Asli + SJ : 194/SJ/PG/07/2026',
                'Invoice + FP : 0400.2600.2809.81304 20/09',
                'Copy DO'
            ],
            'vendor' => 'Pridhana Eka',
            'no_po' => 'POLC/2607/0389',
            'no_grn' => 'GRNLC/2607/0755',
            'status_ttd_raw' => 'Sudah Ditandatangani'
        ],
        2 => [
            'id' => 2,
            'tanggal' => '2026-09-21',
            'type' => 'RFP',
            'type_keterangan' => '26.071.996',
            'is_multi_po' => false,
            'descriptions' => [
                'DP 50% Pembelian Carbon, Pasir dll Rp.69.791.255',
                'PO Asli + Performa Invoice'
            ],
            'vendor' => 'Ady Water',
            'no_po' => 'POLD/2607/0081',
            'no_grn' => '-',
            'status_ttd_raw' => 'Sudah Ditandatangani'
        ],
        3 => [
            'id' => 3,
            'tanggal' => '2026-09-21',
            'type' => 'OFFSET',
            'type_keterangan' => '',
            'is_multi_po' => false,
            'descriptions' => [
                'PO + Inv + Laporan Hasil Uji',
                'No : 1123/EX/VII/2026'
            ],
            'vendor' => 'BBT',
            'no_po' => 'POSD/2607/0004',
            'no_grn' => 'GRNSD/2607/0006',
            'status_ttd_raw' => 'Sudah Ditandatangani'
        ],
        4 => [
            'id' => 4,
            'tanggal' => '2026-09-21',
            'type' => 'Kontrabon',
            'type_keterangan' => '',
            'is_multi_po' => false,
            'descriptions' => [
                'SJ + SJ Dokumen Lampiran + Kwitansi',
                'Faktur Penjualan + FP=289023876 + PO'
            ],
            'vendor' => 'Meta Rupa Perkasa',
            'no_po' => 'POLC/2607/0471',
            'no_grn' => 'GRNLC/2607/0951',
            'status_ttd_raw' => 'Sudah Ditandatangani'
        ],
        5 => [
            'id' => 5,
            'tanggal' => '2026-09-21',
            'type' => 'PO',
            'type_keterangan' => 'Untuk dibuat Cek',
            'is_multi_po' => true,
            'po_items' => [
                ['no' => 1, 'desc' => 'Rp.25.000', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0671', 'no_grn' => '-'],
                ['no' => 2, 'desc' => 'Rp.25.001', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0672', 'no_grn' => '-'],
                ['no' => 3, 'desc' => 'Rp.25.002', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0673', 'no_grn' => '-'],
                ['no' => 4, 'desc' => 'Rp.25.003', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0674', 'no_grn' => '-'],
                ['no' => 5, 'desc' => 'Rp.25.004', 'vendor' => 'Intan Jaya Holis', 'no_po' => 'POLC/2606/0675', 'no_grn' => '-'],
            ],
            'descriptions' => ['Rp.25.000', 'Rp.25.001', 'Rp.25.002', 'Rp.25.003', 'Rp.25.004'],
            'vendor' => 'Intan Jaya Holis',
            'no_po' => 'POLC/2606/0671 - 0675',
            'no_grn' => '-',
            'status_ttd_raw' => 'Menunggu TTD'
        ]
    ];
    $detail = $defaultList[$id] ?? $defaultList[5];
}

$isMultiPo       = !empty($detail['is_multi_po']) && !empty($detail['po_items']);
$formattedTanggal = date('d/m/Y', strtotime($detail['tanggal']));
$typeName        = htmlspecialchars($detail['type']);
$typeKet         = htmlspecialchars($detail['type_keterangan'] ?? '');
$isSigned        = ($detail['status_ttd_raw'] ?? '') === 'Sudah Ditandatangani';

// Warna badge type
$typeBadgeMap = [
    'COD'       => 'badge-success',
    'RFP'       => 'badge-info',
    'OFFSET'    => 'badge-warning',
    'Kontrabon' => 'badge-secondary',
    'PO'        => 'badge-primary',
];
$typeBadge = $typeBadgeMap[$detail['type']] ?? 'badge-dark';

// Parsing Nama & Departemen Penerima Tanda Tangan
$targetSigner = !empty($detail['signed_by']) ? $detail['signed_by'] : ($detail['penerima'] ?? '');
$signerName = $targetSigner;
$signerDept = '';
if (preg_match('/^(.*?)\s*[\(\[]\s*(.*?)\s*[\)\]]$/', $targetSigner, $matches)) {
    $signerName = trim($matches[1]);
    $signerDept = trim($matches[2]);
}

// Helper: render GRN badges
function renderGrnBadges($grnStr) {
    $gVal = trim($grnStr ?? '-') ?: '-';
    if ($gVal === '-') return '<span class="text-muted font-italic small">-</span>';
    $out = '';
    foreach (array_filter(array_map('trim', explode(',', $gVal))) as $g) {
        $out .= '<span class="badge badge-secondary font-weight-normal mr-1 mb-1" style="font-size:11px;">' . htmlspecialchars($g) . '</span>';
    }
    return $out ?: '<span class="text-muted font-italic small">-</span>';
}

// Helper: render PO badge
function renderPoBadge($poStr) {
    $pVal = trim($poStr ?? '') ?: '';
    if ($pVal === '' || $pVal === '-') return '<span class="text-muted font-italic small">-</span>';
    $out = '';
    foreach (array_filter(array_map('trim', explode(',', $pVal))) as $p) {
        $out .= '<span class="badge badge-info font-weight-normal mr-1 mb-1" style="font-size:11px;">' . htmlspecialchars($p) . '</span>';
    }
    return $out;
}
?>

<style>
.detail-card { border-radius: 10px; border: 1px solid #e3e6f0; overflow: hidden; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.detail-card-header { background: linear-gradient(90deg, #f8f9fc 0%, #eef0f8 100%); border-bottom: 1px solid #e3e6f0; padding: 9px 16px; display: flex; align-items: center; justify-content: space-between; }
.detail-card-header h6 { margin: 0; font-size: 13px; font-weight: 700; color: #3a3f5c; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.info-item { display: flex; align-items: flex-start; padding: 9px 16px; border-bottom: 1px solid #f0f1f7; gap: 10px; }
.info-item:last-child, .info-item:nth-last-child(2):nth-child(odd) { border-bottom: none; }
.info-item .info-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px; }
.info-item .info-label { font-size: 11px; font-weight: 600; color: #9299b0; text-transform: uppercase; letter-spacing: 0.4px; line-height: 1.2; margin-bottom: 3px; }
.info-item .info-value { font-size: 13px; color: #3a3f5c; font-weight: 600; line-height: 1.4; }
.info-item .info-value.mono { font-family: monospace; }
.detail-table thead th { background: #4e73df; color: #fff; font-size: 11.5px; font-weight: 700; letter-spacing: 0.3px; padding: 8px 10px; }
.detail-table tbody td { font-size: 12.5px; padding: 7px 10px; vertical-align: middle; }
.detail-table tbody tr:hover { background-color: #f5f7ff; }
.sign-box { border: 1.5px dashed #d1d5e8; border-radius: 10px; min-height: 85px; display: flex; align-items: center; justify-content: center; background: #fff; padding: 10px; }
.status-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.status-chip.signed { background: #e6f9ee; color: #1a7a3e; border: 1.5px solid #84e0a8; }
.status-chip.pending { background: #fff8e1; color: #876a00; border: 1.5px solid #ffd54f; }
.col-divider { border-left: 1px solid #e9ecef; }
@media (max-width: 767px) {
  .info-grid { grid-template-columns: 1fr; }
  .col-divider { border-left: none; border-top: 1px solid #e9ecef; }
}
</style>

<div style="padding: 4px 2px;">

    <!-- ============================================================ -->
    <!-- HEADER INFORMASI RINGKAS                                     -->
    <!-- ============================================================ -->
    <div class="detail-card mb-3">
        <div class="detail-card-header">
            <h6><i class="fas fa-info-circle mr-2 text-primary"></i>Informasi Dokumen</h6>
            <span class="status-chip <?= $isSigned ? 'signed' : 'pending' ?>">
                <i class="fas <?= $isSigned ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                <?= $isSigned ? 'Sudah Ditandatangani' : 'Menunggu TTD' ?>
            </span>
        </div>
        <div class="info-grid">
            <!-- Kolom Kiri -->
            <div>
                <div class="info-item">
                    <div class="info-icon" style="background:#e8f4fd; color:#3498db;"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div class="info-label">Tanggal Serah Terima</div>
                        <div class="info-value text-primary"><?= $formattedTanggal ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon" style="background:#eee8fd; color:#7c4dff;"><i class="fas fa-tag"></i></div>
                    <div>
                        <div class="info-label">Type Dokumen</div>
                        <div class="info-value">
                            <span class="badge <?= $typeBadge ?> px-2 py-1" style="font-size:12px;"><?= $typeName ?></span>
                            <?php if ($typeKet): ?>
                                <span class="text-muted ml-1" style="font-size:12px;">— <?= $typeKet ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon" style="background:#e8fdf5; color:#27ae60;"><i class="fas fa-paper-plane"></i></div>
                    <div>
                        <div class="info-label">Pengirim</div>
                        <div class="info-value"><?= htmlspecialchars($detail['pengirim'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="info-item" style="border-bottom:none;">
                    <div class="info-icon" style="background:#fef3e8; color:#e67e22;"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="info-label">Penerima</div>
                        <div class="info-value"><?= htmlspecialchars($detail['penerima'] ?? '-') ?></div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan -->
            <div class="col-divider">
                <?php if ($isMultiPo): ?>
                <div class="info-item">
                    <div class="info-icon" style="background:#e8f4fd; color:#2980b9;"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="info-label">Total Dokumen PO</div>
                        <div class="info-value">
                            <span class="badge badge-primary px-2 py-1" style="font-size:12px;"><?= count($detail['po_items']) ?> Baris PO</span>
                        </div>
                    </div>
                </div>
                <div class="info-item" style="border-bottom:none;">
                    <div class="info-icon" style="background:#fdebe8; color:#e74c3c;"><i class="fas fa-building"></i></div>
                    <div>
                        <div class="info-label">Supplier Utama</div>
                        <div class="info-value"><?= htmlspecialchars($detail['vendor'] ?: ($detail['po_items'][0]['vendor'] ?? '-')) ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="info-item">
                    <div class="info-icon" style="background:#fdebe8; color:#e74c3c;"><i class="fas fa-building"></i></div>
                    <div>
                        <div class="info-label">Nama Vendor</div>
                        <div class="info-value"><?= htmlspecialchars($detail['vendor'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon" style="background:#e8f4fd; color:#2980b9;"><i class="fas fa-file-invoice"></i></div>
                    <div>
                        <div class="info-label">No. PO</div>
                        <div class="info-value mono"><?= renderPoBadge($detail['no_po'] ?? '') ?></div>
                    </div>
                </div>
                <div class="info-item" style="border-bottom:none;">
                    <div class="info-icon" style="background:#f0fde8; color:#27ae60;"><i class="fas fa-receipt"></i></div>
                    <div>
                        <div class="info-label">No. GRN</div>
                        <div class="info-value"><?= renderGrnBadges($detail['no_grn'] ?? '-') ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAMPILAN 1: MULTI-PO TABEL (Untuk dibuat Cek)               -->
    <!-- ============================================================ -->
    <?php if ($isMultiPo): ?>
    <div class="detail-card">
        <div class="detail-card-header">
            <h6><i class="fas fa-list-ol mr-2 text-primary"></i>Rincian Dokumen PO <span class="text-muted font-weight-normal">(Untuk dibuat Cek)</span></h6>
            <span class="badge badge-pill badge-primary px-3"><?= count($detail['po_items']) ?> Item PO</span>
        </div>
        <div class="table-responsive">
            <table class="table detail-table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width:5%">No</th>
                        <th style="width:28%">Description / Nominal</th>
                        <th style="width:25%">Nama Supplier</th>
                        <th class="text-center" style="width:22%">No. PO</th>
                        <th class="text-center" style="width:20%">No. GRN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail['po_items'] as $idx => $item): ?>
                    <tr>
                        <td class="text-center font-weight-bold text-muted"><?= $idx + 1 ?></td>
                        <td class="font-weight-bold text-dark"><?= htmlspecialchars($item['desc']) ?></td>
                        <td><?= htmlspecialchars($item['vendor']) ?></td>
                        <td class="text-center"><?= renderPoBadge($item['no_po'] ?? '') ?></td>
                        <td class="text-center"><?= renderGrnBadges($item['no_grn'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    // Filter descriptions yang merupakan uraian berkas lampiran (bukan desc sub-PO)
    $subPoDescSet = array_map(fn($pi) => strtolower(trim($pi['desc'] ?? '')), $detail['po_items']);
    $extraDescList = array_values(array_filter($detail['descriptions'] ?? [], fn($d) => !in_array(strtolower(trim($d)), $subPoDescSet) && trim($d) !== ''));
    ?>
    <?php if (!empty($extraDescList)): ?>
    <div class="detail-card">
        <div class="detail-card-header">
            <h6><i class="fas fa-paperclip mr-2 text-warning"></i>Uraian Berkas <span class="text-muted font-weight-normal">(Dokumen Lampiran)</span></h6>
            <span class="badge badge-pill badge-warning px-3"><?= count($extraDescList) ?> Uraian</span>
        </div>
        <div class="table-responsive">
            <table class="table detail-table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width:6%">No</th>
                        <th>Description (Uraian Dokumen / Berkas Lampiran)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($extraDescList as $idx => $desc): ?>
                    <tr>
                        <td class="text-center font-weight-bold text-muted"><?= $idx + 1 ?></td>
                        <td><?= htmlspecialchars($desc) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- TAMPILAN 2: DOKUMEN STANDAR                                  -->
    <!-- ============================================================ -->
    <?php else: ?>
    <div class="detail-card">
        <div class="detail-card-header">
            <h6><i class="fas fa-list-ul mr-2 text-primary"></i>Uraian Berkas <span class="text-muted font-weight-normal">/ Description</span></h6>
            <span class="badge badge-pill badge-primary px-3"><?= count($detail['descriptions'] ?? []) ?> Uraian</span>
        </div>
        <div class="table-responsive">
            <table class="table detail-table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width:6%">No</th>
                        <th>Description (Uraian Dokumen / Berkas Lampiran)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail['descriptions'] ?? [] as $idx => $desc): ?>
                    <tr>
                        <td class="text-center font-weight-bold text-muted"><?= $idx + 1 ?></td>
                        <td><?= htmlspecialchars($desc) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STATUS & TANDA TANGAN ACCOUNTING                            -->
    <!-- ============================================================ -->
    <div class="detail-card mb-0">
        <div class="detail-card-header">
            <h6><i class="fas fa-signature mr-2 text-success"></i>Status &amp; Tanda Tangan</h6>
        </div>
        <div style="padding: 16px 20px;">
            <div class="row align-items-center">
                <!-- Status verifikasi -->
                <div class="col-md-7 mb-3 mb-md-0">
                    <p class="mb-2 font-weight-bold text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Status Verifikasi</p>
                    <?php if ($isSigned): ?>
                        <div class="status-chip signed mb-2" style="display:inline-flex;">
                            <i class="fas fa-check-circle"></i> Sudah Diverifikasi &amp; Ditandatangani
                        </div>
                        <?php if (!empty($detail['signed_at'])): ?>
                        <p class="mb-0 text-muted small mt-1"><i class="fas fa-calendar-check mr-1"></i>Diverifikasi pada: <strong><?= htmlspecialchars($detail['signed_at']) ?></strong></p>
                        <?php else: ?>
                        <p class="mb-0 text-muted small mt-1"><i class="fas fa-calendar-check mr-1"></i>Tanggal: <strong><?= $formattedTanggal ?></strong></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-chip pending mb-2" style="display:inline-flex;">
                            <i class="fas fa-clock"></i> Menunggu Tanda Tangan
                        </div>
                        <p class="mb-0 text-muted small mt-1"><i class="fas fa-info-circle mr-1"></i>Berkas siap ditandatangani.</p>
                    <?php endif; ?>
                </div>

                <!-- Area tanda tangan -->
                <div class="col-md-5 text-center">
                    <p class="mb-2 font-weight-bold text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Tanda Tangan</p>
                    <div class="sign-box mb-2" style="min-width:180px; margin:0 auto; max-width:240px;">
                        <?php if (!empty($detail['signature_data'])): ?>
                            <img src="<?= htmlspecialchars($detail['signature_data']) ?>" alt="Tanda Tangan" style="max-height:70px; max-width:210px; object-fit:contain;">
                        <?php elseif ($isSigned): ?>
                            <div class="text-success text-center">
                                <i class="fas fa-check-circle" style="font-size:1.5rem;"></i>
                                <div class="font-weight-bold small mt-1">Sudah Ditandatangani</div>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small font-italic">Belum Ada Tanda Tangan</div>
                        <?php endif; ?>
                    </div>
                    <!-- Nama penerima di bawah tanda tangan -->
                    <div style="max-width:220px; margin:0 auto; text-align:center;">
                        <div class="font-weight-bold text-dark" style="font-size:13px;"><?= htmlspecialchars($signerName ?: ($detail['penerima'] ?? '-')) ?></div>
                        <div style="border-top:1.5px solid #6c757d; margin:4px auto; width:80%;"></div>
                        <div class="text-muted" style="font-size:11.5px; font-weight:500;"><?= htmlspecialchars($signerDept ?: 'Penerima') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
