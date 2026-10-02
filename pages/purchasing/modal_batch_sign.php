<?php
session_start();
include_once file_exists(__DIR__ . '/../../koneksi.php') ? __DIR__ . '/../../koneksi.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

// Helper Identitas User
function getBatchCurrentUser($conn) {
    $user = trim($_SESSION['UserName'] ?? '');
    $nama = trim($_SESSION['NamaLengkap'] ?? '');
    $dept = '';
    
    if (!empty($conn) && !empty($user)) {
        $q = sqlsrv_query($conn, "SELECT e.nama_lengkap, d.dept 
            FROM dbo.SMUserMs u 
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp 
            LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
            LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
            LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
            WHERE u.UserName = ?", [$user]);
        if ($q && ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))) {
            if (!empty($row['nama_lengkap'])) $nama = trim($row['nama_lengkap']);
            if (!empty($row['dept'])) $dept = trim($row['dept']);
        }
    }
    if (empty($nama)) $nama = $user ?: 'User Accounting';
    $formatted = !empty($dept) ? "{$nama} ({$dept})" : $nama;
    return [
        'user'      => $user,
        'nama'      => $nama,
        'dept'      => $dept,
        'formatted' => $formatted
    ];
}

$currentUser = getBatchCurrentUser($conn);

// Tangkap IDs
$rawIds = $_POST['ids'] ?? ($_GET['ids'] ?? []);
if (is_string($rawIds)) {
    $decoded = json_decode($rawIds, true);
    $rawIds = is_array($decoded) ? $decoded : explode(',', $rawIds);
}
$ids = array_values(array_unique(array_filter(array_map('intval', (array)$rawIds), function($v) { return $v > 0; })));

if (empty($ids)) {
    ?>
    <div class="p-5 text-center bg-white rounded-lg shadow-sm">
        <div class="text-warning mb-3" style="font-size: 2.8rem;"><i class="fas fa-exclamation-circle"></i></div>
        <h5 class="font-weight-bold text-dark">Tidak Ada Dokumen yang Dipilih</h5>
        <p class="text-muted small mb-0">Silakan centang minimal satu dokumen yang belum ditandatangani pada tabel list terlebih dahulu.</p>
    </div>
    <?php
    exit;
}

// Cek apakah tabel SQL Server siap
$tableReady = false;
if (!empty($conn)) {
    $qCheck = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'purchasing_header'");
    if ($qCheck && sqlsrv_has_rows($qCheck)) {
        $tableReady = true;
    }
}

$documents = [];

if ($tableReady) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, tanggal, type, type_keterangan, is_multi_po, vendor, no_po, no_grn, pengirim, penerima, status_ttd
            FROM dbo.purchasing_header 
            WHERE id IN ($placeholders) AND is_deleted = 0
            ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql, $ids);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Ambil deskripsi singkat
            $descText = '';
            $qDesc = sqlsrv_query($conn, "SELECT TOP 2 description FROM dbo.purchasing_detail WHERE header_id = ? ORDER BY row_order ASC", [$row['id']]);
            if ($qDesc) {
                $dArr = [];
                while ($dr = sqlsrv_fetch_array($qDesc, SQLSRV_FETCH_ASSOC)) {
                    $dArr[] = $dr['description'];
                }
                $descText = implode(', ', $dArr);
            }

            // Jika multi PO, ambil vendor/po pertama jika kosong di header
            if ($row['is_multi_po'] == 1 && empty($row['vendor'])) {
                $qPo = sqlsrv_query($conn, "SELECT TOP 1 vendor, no_po, no_grn, description FROM dbo.purchasing_po_items WHERE header_id = ? ORDER BY item_no ASC", [$row['id']]);
                if ($qPo && ($pr = sqlsrv_fetch_array($qPo, SQLSRV_FETCH_ASSOC))) {
                    if (empty($row['vendor'])) $row['vendor'] = $pr['vendor'];
                    if (empty($row['no_po'])) $row['no_po'] = $pr['no_po'];
                    if (empty($row['no_grn'])) $row['no_grn'] = $pr['no_grn'];
                    if (empty($descText)) $descText = $pr['description'];
                }
            }

            $tglStr = $row['tanggal'] ? $row['tanggal']->format('d/m/Y') : '-';
            $documents[] = [
                'id'              => $row['id'],
                'tanggal'         => $tglStr,
                'type'            => $row['type'] ?? '-',
                'type_keterangan' => $row['type_keterangan'] ?? '',
                'vendor'          => $row['vendor'] ?? '-',
                'no_po'           => $row['no_po'] ?? '-',
                'no_grn'          => $row['no_grn'] ?? '-',
                'description'     => $descText ?: '-',
                'status_ttd'      => $row['status_ttd'] ?? 'Menunggu TTD',
                'is_signed'       => (($row['status_ttd'] ?? '') === 'Sudah Ditandatangani')
            ];
        }
    }
} else {
    // Fallback Session Data
    $sessionData = $_SESSION['purchasing_data'] ?? [];
    foreach ($sessionData as $row) {
        if (in_array(intval($row['id']), $ids)) {
            $tglStr = !empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '-';
            $descList = $row['descriptions'] ?? [];
            $descText = !empty($descList) ? implode(', ', array_slice($descList, 0, 2)) : '-';
            $statusTtd = $row['status_ttd_raw'] ?? 'Menunggu TTD';
            $documents[] = [
                'id'              => $row['id'],
                'tanggal'         => $tglStr,
                'type'            => $row['type'] ?? '-',
                'type_keterangan' => $row['type_keterangan'] ?? '',
                'vendor'          => $row['vendor'] ?? '-',
                'no_po'           => $row['no_po'] ?? '-',
                'no_grn'          => $row['no_grn'] ?? '-',
                'description'     => $descText,
                'status_ttd'      => $statusTtd,
                'is_signed'       => ($statusTtd === 'Sudah Ditandatangani')
            ];
        }
    }
}

// Saring hanya yang belum ditandatangani
$documents = array_values(array_filter($documents, function($d) {
    return !$d['is_signed'];
}));

if (empty($documents)) {
    ?>
    <div class="p-5 text-center bg-white rounded-lg shadow-sm">
        <div class="text-success mb-3" style="font-size: 2.8rem;"><i class="fas fa-check-circle"></i></div>
        <h5 class="font-weight-bold text-dark">Semua Dokumen Sudah Ditandatangani</h5>
        <p class="text-muted small mb-0">Dokumen yang Anda pilih ternyata sudah ditandatangani sebelumnya.</p>
    </div>
    <?php
    exit;
}

// Helper badge type
$typeBadgeMap = [
    'COD'       => 'badge-success',
    'RFP'       => 'badge-info',
    'OFFSET'    => 'badge-warning',
    'Kontrabon' => 'badge-secondary',
    'PO'        => 'badge-primary',
];
?>

<style>
/* Desain Kartu & Kontainer Batch Modal */
.batch-modal-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.batch-modal-card-header {
    background: linear-gradient(90deg, #f8f9fc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.batch-modal-card-header h6 {
    margin: 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
}
.batch-modal-card-body {
    padding: 16px 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Tabel Dokumen Terpilih */
.batch-table-container {
    max-height: 380px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}
.batch-table-container::-webkit-scrollbar { width: 6px; }
.batch-table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.batch-table thead th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    z-index: 2;
    padding: 10px 12px;
    border-bottom: 1.5px solid #e2e8f0;
}
.batch-table tbody td {
    font-size: 12px;
    padding: 10px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}
.batch-table tbody tr:hover td {
    background-color: #f8faff;
}
.batch-table tbody tr.row-unchecked td {
    background-color: #fafafa !important;
    opacity: 0.55;
}

/* Badge PO & GRN */
.batch-po-badge {
    background: #e0f2fe;
    color: #0369a1;
    font-family: monospace;
    font-weight: 700;
    font-size: 11.5px;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
}
.batch-grn-badge {
    background: #f1f5f9;
    color: #475569;
    font-size: 10.5px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    display: inline-block;
    border: 1px solid #e2e8f0;
}

/* Panel Kanan (Verifikasi & Tanda Tangan) */
.batch-signer-chip {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 16px;
}
.batch-signer-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}
.batch-sig-preview-box {
    background: #ffffff;
    border: 1.5px dashed #cbd5e1;
    border-radius: 10px;
    height: 125px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.02);
}
.batch-canvas-box {
    border: 1.5px dashed #6366f1;
    border-radius: 10px;
    background: #ffffff;
    position: relative;
    height: 140px;
    overflow: hidden;
    margin-bottom: 8px;
}
.batch-canvas-box canvas {
    width: 100%;
    height: 140px;
    cursor: crosshair;
    display: block;
}
.batch-canvas-line {
    position: absolute;
    bottom: 24px;
    left: 10%;
    right: 10%;
    border-bottom: 1px dashed #cbd5e1;
    text-align: center;
    color: #94a3b8;
    font-size: 10px;
    pointer-events: none;
    letter-spacing: 0.5px;
}
</style>

<div class="row">
    <!-- ========================================== -->
    <!-- KOLOM KIRI: DAFTAR DOKUMEN YANG DIPILIH   -->
    <!-- ========================================== -->
    <div class="col-lg-7 col-md-12 mb-3 mb-lg-0">
        <div class="batch-modal-card">
            <!-- Header Kartu Kiri -->
            <div class="batch-modal-card-header">
                <div>
                    <h6><i class="fas fa-list-check text-primary mr-2"></i>Dokumen Terpilih</h6>
                    <small class="text-muted">Centang dokumen yang akan ditandatangani</small>
                </div>
                <div>
                    <span class="badge badge-primary px-3 py-1 font-weight-bold" id="batchBadgeSelectedCount" style="font-size: 11.5px; border-radius: 20px;">
                        <?= count($documents) ?> / <?= count($documents) ?> Dokumen Aktif
                    </span>
                </div>
            </div>

            <!-- Body Kartu Kiri: Tabel Dokumen -->
            <div class="batch-modal-card-body p-0">
                <div class="batch-table-container">
                    <table class="table mb-0 batch-table">
                        <thead>
                            <tr>
                                <th style="width: 42px;" class="text-center align-middle">
                                    <div class="custom-control custom-checkbox d-inline-block">
                                        <input type="checkbox" class="custom-control-input" id="batchCheckAllInModal" checked>
                                        <label class="custom-control-label" for="batchCheckAllInModal" title="Pilih/Batalkan Semua"></label>
                                    </div>
                                </th>
                                <th style="width: 135px;">No. PO &amp; GRN</th>
                                <th>Vendor &amp; Deskripsi</th>
                                <th style="width: 110px;" class="text-center">Tanggal &amp; Type</th>
                            </tr>
                        </thead>
                        <tbody id="batchModalTableBody">
                            <?php foreach ($documents as $doc): 
                                $tBadge = $typeBadgeMap[$doc['type']] ?? 'badge-secondary';
                            ?>
                            <tr id="batch_tr_<?= $doc['id'] ?>">
                                <td class="text-center align-middle">
                                    <div class="custom-control custom-checkbox d-inline-block">
                                        <input type="checkbox" class="custom-control-input batch-modal-item-chk" id="batch_chk_<?= $doc['id'] ?>" value="<?= $doc['id'] ?>" checked>
                                        <label class="custom-control-label" for="batch_chk_<?= $doc['id'] ?>"></label>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <div>
                                        <span class="batch-po-badge">
                                            <i class="fas fa-file-invoice mr-1 text-primary"></i><?= htmlspecialchars($doc['no_po']) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($doc['no_grn']) && $doc['no_grn'] !== '-'): ?>
                                    <div class="mt-1">
                                        <span class="batch-grn-badge">
                                            GRN: <?= htmlspecialchars($doc['no_grn']) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <div class="font-weight-bold text-dark text-truncate" style="max-width: 230px;" title="<?= htmlspecialchars($doc['vendor']) ?>">
                                        <?= htmlspecialchars($doc['vendor']) ?>
                                    </div>
                                    <div class="text-muted text-truncate" style="max-width: 230px; font-size: 11px;" title="<?= htmlspecialchars($doc['description']) ?>">
                                        <?= htmlspecialchars($doc['description']) ?>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge <?= $tBadge ?> px-2 py-1 mb-1 font-weight-bold d-inline-block" style="font-size: 10.5px;">
                                        <?= htmlspecialchars($doc['type']) ?>
                                    </span>
                                    <?php if (!empty($doc['type_keterangan'])): ?>
                                    <div class="text-muted small font-italic text-truncate" style="max-width: 95px; font-size: 10px;">
                                        <?= htmlspecialchars($doc['type_keterangan']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="text-muted mt-1" style="font-size: 11px;">
                                        <i class="far fa-calendar-alt mr-1"></i><?= htmlspecialchars($doc['tanggal']) ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-2 px-3 d-flex align-items-center justify-content-between bg-light border-top">
                    <span class="small text-muted font-italic">
                        <i class="fas fa-info-circle mr-1 text-primary"></i>Hapus centang untuk mengecualikan dokumen tertentu
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-xs px-2 py-1" id="btnToggleAllBatchRows" style="font-size: 11px;">
                        <i class="fas fa-check-double mr-1"></i> Centang / Batalkan Semua
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- KOLOM KANAN: PANEL TANDA TANGAN DIGITAL    -->
    <!-- ========================================== -->
    <div class="col-lg-5 col-md-12">
        <div class="batch-modal-card">
            <!-- Header Kartu Kanan -->
            <div class="batch-modal-card-header">
                <h6><i class="fas fa-signature mr-2 text-primary"></i>Verifikasi &amp; Tanda Tangan</h6>
                <span class="badge badge-light border text-muted px-2 py-1 small">
                    <i class="fas fa-lock mr-1 text-success"></i>Otorisasi
                </span>
            </div>

            <div class="batch-modal-card-body justify-content-between">
                <div>
                    <!-- Identitas User Login -->
                    <div class="batch-signer-chip">
                        <div class="batch-signer-avatar">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div style="line-height: 1.3; overflow: hidden;">
                            <div class="text-muted" style="font-size: 10px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Ditandatangani Oleh (Login)</div>
                            <div class="font-weight-bold text-dark text-truncate" style="font-size: 13px;" title="<?= htmlspecialchars($currentUser['formatted']) ?>">
                                <?= htmlspecialchars($currentUser['formatted']) ?>
                            </div>
                            <div class="text-success font-weight-bold" style="font-size: 11px;">
                                <i class="fas fa-check-circle mr-1"></i>Terverifikasi Otomatis
                            </div>
                        </div>
                    </div>

                    <!-- Area Tanda Tangan Tersimpan -->
                    <div id="batchSectionSavedSig" style="display: none;" class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small font-weight-bold text-muted text-uppercase" style="letter-spacing: .4px;">
                                <i class="fas fa-bookmark text-primary mr-1"></i> Tanda Tangan Tersimpan
                            </span>
                            <span class="badge badge-success px-2 py-0 font-weight-normal" style="font-size: 10.5px;">Siap Digunakan</span>
                        </div>
                        <div class="batch-sig-preview-box mb-2">
                            <img id="batchImgSavedSig" src="" alt="Tanda Tangan Tersimpan" style="max-height: 85px; max-width: 90%; object-fit: contain;">
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm btn-block font-weight-bold" id="btnBatchChangeSig">
                            <i class="fas fa-pen-nib mr-1"></i> Ganti / Gores Tanda Tangan Baru
                        </button>
                    </div>

                    <!-- Area Canvas Gambar Baru -->
                    <div id="batchSectionCanvasSig" style="display: none;" class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small font-weight-bold text-dark text-uppercase" style="letter-spacing: .4px;">
                                <i class="fas fa-pencil-alt text-primary mr-1"></i> Goreskan Tanda Tangan Anda
                            </span>
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 font-weight-bold" id="btnClearBatchCanvas" style="font-size: 11.5px;">
                                <i class="fas fa-eraser mr-1"></i> Reset Canvas
                            </button>
                        </div>
                        <div class="batch-canvas-box">
                            <canvas id="batchSignatureCanvas"></canvas>
                            <div class="batch-canvas-line">Tanda Tangan Accounting</div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="batchCheckSaveSig" checked>
                                <label class="custom-control-label small text-muted font-weight-500" for="batchCheckSaveSig">
                                    Simpan untuk TTD berikutnya
                                </label>
                            </div>
                            <button type="button" class="btn btn-link btn-sm text-secondary p-0 font-weight-500" id="btnBatchBackToSavedSig" style="display:none; font-size: 11.5px;">
                                <i class="fas fa-undo mr-1"></i> Pakai Tersimpan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tombol Submit Tanda Tangan Massal -->
                <div class="mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-success btn-block py-2 font-weight-bold shadow-sm" id="btnSubmitBatchSignAction" style="font-size: 13.5px; border-radius: 8px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                        <i class="fas fa-file-signature mr-2"></i> Tandatangani Sekarang (<span id="batchSubmitCount"><?= count($documents) ?></span> Dokumen)
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-block btn-sm mt-2 font-weight-500" data-dismiss="modal" style="border-radius: 8px;">
                        Tutup / Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Inisialisasi State & Canvas di dalam modal
    var canvasEl = document.getElementById('batchSignatureCanvas');
    var batchPad = null;
    var savedSignature = localStorage.getItem('purchasing_accounting_signature');

    var padInitializer = window.initSmoothSignaturePad || (typeof initSmoothSignaturePad === 'function' ? initSmoothSignaturePad : null);
    if (canvasEl && padInitializer) {
        batchPad = padInitializer(canvasEl);
    }

    // Tampilkan mode tersimpan atau mode canvas
    if (savedSignature) {
        $('#batchImgSavedSig').attr('src', savedSignature);
        $('#batchSectionSavedSig').show();
        $('#batchSectionCanvasSig').hide();
        $('#btnBatchBackToSavedSig').show();
    } else {
        $('#batchSectionSavedSig').hide();
        $('#batchSectionCanvasSig').show();
        $('#btnBatchBackToSavedSig').hide();
    }

    // Resize canvas saat modal terbuka penuh
    $('#modalBatchSignature').on('shown.bs.modal', function() {
        setTimeout(function() {
            if (batchPad) batchPad.resize();
        }, 100);
    });

    setTimeout(function() {
        if (batchPad) batchPad.resize();
    }, 150);

    // Ganti ke canvas
    $('#btnBatchChangeSig').on('click', function() {
        $('#batchSectionSavedSig').slideUp(180, function() {
            $('#batchSectionCanvasSig').slideDown(180, function() {
                if (batchPad) batchPad.resize();
            });
        });
    });

    // Kembali ke tanda tangan tersimpan
    $('#btnBatchBackToSavedSig').on('click', function() {
        $('#batchSectionCanvasSig').slideUp(180, function() {
            $('#batchSectionSavedSig').slideDown(180);
        });
    });

    // Hapus canvas
    $('#btnClearBatchCanvas').on('click', function() {
        if (batchPad) batchPad.clear();
    });

    // Update counter dan style baris saat checkbox modal berubah
    function updateBatchCounts() {
        var total = $('.batch-modal-item-chk').length;
        var checked = $('.batch-modal-item-chk:checked').length;

        $('#batchBadgeSelectedCount').text(checked + ' / ' + total + ' Dokumen Aktif');
        $('#batchSubmitCount').text(checked);

        // Update button status
        if (checked === 0) {
            $('#btnSubmitBatchSignAction').prop('disabled', true).addClass('disabled');
            $('#batchCheckAllInModal').prop('checked', false).prop('indeterminate', false);
        } else {
            $('#btnSubmitBatchSignAction').prop('disabled', false).removeClass('disabled');
            if (checked === total) {
                $('#batchCheckAllInModal').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#batchCheckAllInModal').prop('checked', false).prop('indeterminate', true);
            }
        }

        // Row highlighting
        $('.batch-modal-item-chk').each(function() {
            var tr = $(this).closest('tr');
            if ($(this).is(':checked')) {
                tr.removeClass('row-unchecked');
            } else {
                tr.addClass('row-unchecked');
            }
        });
    }

    // Event listener per checkbox baris
    $(document).on('change', '.batch-modal-item-chk', function() {
        updateBatchCounts();
    });

    // Master checkbox modal
    $('#batchCheckAllInModal, #btnToggleAllBatchRows').on('click change', function(e) {
        var targetState;
        if ($(this).is('button') || $(this).is('a')) {
            e.preventDefault();
            var anyUnchecked = $('.batch-modal-item-chk:not(:checked)').length > 0;
            targetState = anyUnchecked;
        } else {
            targetState = $(this).is(':checked');
        }
        $('.batch-modal-item-chk').prop('checked', targetState);
        updateBatchCounts();
    });

    // Tombol Eksekusi Tanda Tangan Massal
    $('#btnSubmitBatchSignAction').off('click').on('click', function() {
        var selectedIds = [];
        $('.batch-modal-item-chk:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });

        if (selectedIds.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Pilihan Kosong',
                text: 'Silakan centang minimal satu dokumen pada daftar untuk ditandatangani!'
            });
            return;
        }

        // Tentukan data tanda tangan
        var sigDataToUse = '';
        var isCanvasVisible = $('#batchSectionCanvasSig').is(':visible');

        if (isCanvasVisible) {
            if (!batchPad || batchPad.isEmpty()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tanda Tangan Kosong',
                    text: 'Silakan goreskan tanda tangan Anda pada canvas terlebih dahulu!'
                });
                return;
            }
            sigDataToUse = batchPad.toDataURL('image/png');

            if ($('#batchCheckSaveSig').is(':checked')) {
                localStorage.setItem('purchasing_accounting_signature', sigDataToUse);
            }
        } else {
            sigDataToUse = localStorage.getItem('purchasing_accounting_signature');
            if (!sigDataToUse) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tanda Tangan Tidak Ditemukan',
                    text: 'Tanda tangan tersimpan tidak ditemukan. Silakan buat tanda tangan baru pada canvas.'
                });
                $('#btnBatchChangeSig').trigger('click');
                return;
            }
        }

        // Konfirmasi sebelum eksekusi massal
        Swal.fire({
            title: 'Konfirmasi Tanda Tangan Massal',
            html: 'Anda akan menandatangani <b>' + selectedIds.length + ' dokumen</b> sekaligus secara resmi.<br>Lanjutkan proses verifikasi tanda tangan?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check-circle mr-1"></i> Ya, Tandatangani!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            // Tampilkan loading dialog
            Swal.fire({
                title: 'Memproses Tanda Tangan...',
                html: 'Menyimpan tanda tangan pada ' + selectedIds.length + ' dokumen...',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'purchasing_serverside.php',
                type: 'POST',
                data: {
                    action: 'batch_sign',
                    ids: selectedIds,
                    signature_data: sigDataToUse
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.status === 'success') {
                        $('#modalBatchSignature').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message || 'Tanda tangan massal berhasil diterapkan!',
                            timer: 2200,
                            showConfirmButton: true
                        });
                        // Reset selection & reload datatables
                        if (typeof clearAllSelections === 'function') {
                            clearAllSelections();
                        }
                        if (window.purchasingDataTable) {
                            window.purchasingDataTable.ajax.reload(null, false);
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: (res && res.message) ? res.message : 'Terjadi kesalahan saat memproses tanda tangan massal.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Koneksi',
                        text: 'Gagal menghubungi server: ' + (error || 'Terjadi kesalahan jaringan')
                    });
                }
            });
        });
    });

    updateBatchCounts();
})();
</script>
