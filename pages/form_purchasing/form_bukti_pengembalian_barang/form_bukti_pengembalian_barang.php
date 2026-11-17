<?php
/**
 * Form Bukti Pengembalian Uang
 * Sesuai format dokumen SUM Support App.docx
 * Dimuat via AJAX ke dalam #modalFormIsian di list_form.php
 */

if (!isset($conn) || $conn === false) {
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (!file_exists($koneksiPath)) {
        $koneksiPath = dirname(__DIR__, 2) . '/koneksi.php';
    }
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    }
}

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? '');
$nik = '';
$departemen = '';
$bagian = '';
$jabatan = '';

if (!empty($username) && isset($conn) && $conn !== false) {
    $sql = "SELECT m_emp.nik, m_dept.dept, m_bag.bagian, m_jab.jabatan
            FROM dbo.m_emp
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag    ON m_subbag.id_bag  = m_bag.id_bag
            LEFT JOIN dbo.m_dept   ON m_bag.id_dept    = m_dept.id_dept
            LEFT JOIN dbo.m_jab    ON m_emp.id_jab     = m_jab.id_jab
            WHERE m_emp.nama_lengkap = ? OR m_emp.username = ?";
    $stmt = sqlsrv_query($conn, $sql, [$username, $username]);
    if ($stmt !== false) {
        $emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($emp) {
            $nik = $emp['nik'] ?? '';
            $departemen = $emp['dept'] ?? '';
            $bagian = $emp['bagian'] ?? '';
            $jabatan = $emp['jabatan'] ?? '';
        }
        sqlsrv_free_stmt($stmt);
    }
}

$isEdit = isset($_GET['edit']) && $_GET['edit'] == 1 && isset($_GET['ticket']);
$editTicket = $isEdit ? htmlspecialchars($_GET['ticket']) : '';
$tglHariIni = date('Y-m-d');
?>

<style>
    .bpu-doc-box {
        border: 2px solid #28a745;
        padding: 5px 12px;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 1px;
        display: inline-block;
        background: #fff;
        color: #28a745;
        border-radius: 4px;
    }
    .bpu-section-title {
        font-size: 0.88rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        padding-bottom: 4px;
        margin-bottom: 12px;
        margin-top: 8px;
    }
    .table-bpu-items th {
        background-color: #f1f3f5;
        font-size: 0.82rem;
        font-weight: 700;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #ced4da;
    }
    .table-bpu-items td {
        vertical-align: middle;
        padding: 6px;
        border: 1px solid #ced4da;
    }
    .subtotal-highlight-bpu {
        font-size: 1.15rem;
        font-weight: 800;
        color: #28a745;
    }
    .terbilang-box-bpu {
        background: #f8f9fa;
        border-left: 4px solid #28a745;
        padding: 8px 12px;
        font-style: italic;
        font-size: 0.88rem;
        color: #333;
    }

    /* Modal Rekening Vendor */
    .swal2-container {
        z-index: 99999 !important;
    }
    #modalPilihRekeningBpu .modal-dialog {
        max-width: 480px !important;
        width: 95% !important;
        margin: 1.75rem auto;
    }
    #modalRekeningListBpu {
        max-height: 60vh;
        overflow-y: auto;
        padding-right: 2px;
    }
    .rekening-card-bpu {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
        background: #fff;
        position: relative;
    }
    .rekening-card-bpu:hover {
        border-color: #28a745;
        background: #f2f9f4;
        box-shadow: 0 2px 8px rgba(40,167,69,0.12);
    }
    .rekening-card-bpu.selected {
        border-color: #28a745;
        background: #e8f5e9;
        box-shadow: 0 2px 10px rgba(40,167,69,0.18);
    }
    .rekening-card-bpu .rekening-bank {
        font-weight: 800;
        font-size: 0.95rem;
        color: #28a745;
    }
    .rekening-card-bpu .rekening-nmbr {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        color: #212529;
    }
    .rekening-card-bpu .rekening-atas-nama {
        font-size: 0.82rem;
        color: #495057;
    }
    .rekening-card-bpu .rekening-currency {
        font-size: 0.78rem;
        color: #6c757d;
    }
    .rekening-card-bpu .check-icon {
        position: absolute;
        top: 10px;
        right: 12px;
        font-size: 1.2rem;
        color: #28a745;
        display: none;
    }
    .rekening-card-bpu.selected .check-icon {
        display: block;
    }
    .rekening-card-bpu.is-default {
        border-color: #28a745;
        background: #fcfefc;
    }
    .rekening-card-bpu .badge-default {
        background: #28a745;
        color: #fff;
        font-size: 0.68rem;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 700;
        margin-left: 8px;
        vertical-align: middle;
        display: inline-flex;
        align-items: center;
        letter-spacing: 0.3px;
    }
</style>

<form method="POST" id="formBuktiPengembalianUang" enctype="multipart/form-data" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="bukti_pengembalian_uang">
    <input type="hidden" name="items_json" id="bpuItemsJson" value="">
    <input type="hidden" name="subtotal" id="bpuSubtotalInput" value="0">
    <input type="hidden" name="terbilang" id="bpuTerbilangInput" value="">
    <input type="hidden" name="nama_pemohon" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" name="departemen" value="<?= htmlspecialchars($departemen) ?>">

    <?php if ($isEdit): ?>
        <input type="hidden" name="ticket" value="<?= $editTicket ?>">
        <input type="hidden" name="action" value="update">
    <?php endif; ?>

    <!-- ── Header Banner Dokumen ─────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div>
            <h5 class="font-weight-bold text-dark mb-0">Bukti Pengembalian Uang</h5>
            <small class="text-muted">Formulir Pengembalian Uang / Refund Pembelian PO</small>
        </div>
        <div>
            <div class="bpu-doc-box">BUKTI PENGEMBALIAN UANG</div>
        </div>
    </div>

    <!-- ── Bagian 1: Referensi PO & RFP Info ─────────────────────── -->
    <div class="bpu-section-title">
        <i class="fas fa-file-invoice mr-1"></i> 1. Referensi PO &amp; Data RFP
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1 text-primary">
                    <i class="fas fa-barcode mr-1"></i> Refferensi PO No. <span class="text-danger">*</span>
                </label>
                <div class="input-group input-group-sm">
                    <input type="text" name="ref_po_no" id="bpuRefPoNo" class="form-control font-weight-bold"
                        placeholder="Contoh: POLD/2610/0069" required autocomplete="off">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info font-weight-bold" id="btnTarikPoBpu" title="Tarik data otomatis dari ERP">
                            <i class="fas fa-search"></i> Tarik Data PO
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted">Ketik No PO lalu tekan <strong>Tarik Data PO</strong> atau <strong>Enter</strong>.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">RFP NO</label>
                <input type="text" name="rfp_no" id="bpuRfpNo" class="form-control form-control-sm font-weight-bold text-primary"
                    placeholder="Contoh: 2610.2005">
                <small class="form-text text-muted">Terisi otomatis jika ada di ERP.</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Tgl RFP</label>
                <input type="date" name="tgl_rfp" id="bpuTglRfp" class="form-control form-control-sm">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Tanggal (Pengajuan) <span class="text-danger">*</span></label>
                <input type="date" name="tanggal" id="bpuTanggal" class="form-control form-control-sm bg-light"
                    value="<?= $tglHariIni ?>" required>
            </div>
        </div>
    </div>

    <!-- ── Bagian 2: Supplier & Rekening Penerima ─────────────────── -->
    <div class="bpu-section-title">
        <i class="fas fa-university mr-1"></i> 2. Supplier, Rekening &amp; Metode Pembayaran
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Supplier / Vendor <span class="text-danger">*</span></label>
                <input type="text" name="supplier" id="bpuSupplier" class="form-control form-control-sm"
                    placeholder="Contoh: TEKINDO PRATAMA" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Bank</label>
                <input type="text" name="bank" id="bpuBank" class="form-control form-control-sm"
                    placeholder="Contoh: BCA / Mandiri / BRI">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">A/C (No. Rekening)</label>
                <input type="text" name="rekening_ac" id="bpuRekeningAc" class="form-control form-control-sm"
                    placeholder="Contoh: 008-7879988">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Currency</label>
                <select name="currency" id="bpuCurrency" class="form-control form-control-sm">
                    <option value="IDR" selected>IDR (Rupiah)</option>
                    <option value="USD">USD (US Dollar)</option>
                    <option value="SGD">SGD (Singapore Dollar)</option>
                    <option value="EUR">EUR (Euro)</option>
                    <option value="JPY">JPY (Japanese Yen)</option>
                </select>
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Payment Method</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="payment_method" id="bpuPaymentMethod" class="form-control form-control-sm"
                        placeholder="Contoh: Payment Before Shipment / Account Payable / DP" list="bpuPaymentMethodList">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Pilihan Cepat">
                            Pilih
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow-sm">
                            <a class="dropdown-item bpu-opt-pmt font-weight-bold py-1" href="javascript:void(0)" data-value="Payment Before Shipment">
                                <i class="fas fa-check-circle mr-2 text-primary"></i> Payment Before Shipment
                            </a>
                            <a class="dropdown-item bpu-opt-pmt font-weight-bold py-1" href="javascript:void(0)" data-value="Account Payable">
                                <i class="fas fa-file-invoice mr-2 text-success"></i> Account Payable
                            </a>
                            <a class="dropdown-item bpu-opt-pmt font-weight-bold py-1" href="javascript:void(0)" data-value="DP">
                                <i class="fas fa-coins mr-2 text-warning"></i> DP (Down Payment)
                            </a>
                        </div>
                    </div>
                </div>
                <datalist id="bpuPaymentMethodList">
                    <option value="Payment Before Shipment">
                    <option value="Account Payable">
                    <option value="DP">
                </datalist>
                <small class="form-text text-muted">Otomatis terisi dari kolom fgpmttype database ERP (B/P/D).</small>
            </div>
        </div>
    </div>

    <!-- ── Bagian 3: Keterangan ──────────────────────────────────── -->
    <div class="bpu-section-title">
        <i class="fas fa-comment-alt mr-1"></i> 3. Keterangan (ISI MANUAL)
    </div>

    <div class="form-group mb-3">
        <label class="small font-weight-bold mb-1">Keterangan Pengembalian / Alasan Refund <span class="text-danger">*</span></label>
        <textarea name="keterangan" id="bpuKeterangan" class="form-control form-control-sm" rows="2"
            placeholder="Contoh: PENGEMBALIAN DANA DARI SUPPLIER KARENA STOK BARANG KOSONG REF POLD/2610/0069" required></textarea>
    </div>

    <!-- ── Bagian 4: Tabel Rincian Item / Pengembalian ────────────── -->
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div class="bpu-section-title mb-0 border-bottom-0 pb-0">
            <i class="fas fa-list-ol mr-1"></i> 4. Rincian Item / Pengembalian
        </div>
        <div class="d-flex align-items-center">
            <div class="btn-group btn-group-sm mr-2">
                <button type="button" class="btn btn-primary btn-sm" id="btnBpuAddRow">
                    <i class="fas fa-plus"></i> Tambah Baris
                </button>
            </div>
            <button type="button" class="btn btn-danger btn-sm" id="btnBpuDeleteAllRows" title="Clear semua baris">
                <i class="fas fa-trash-alt"></i> Delete All
            </button>
        </div>
    </div>

    <div class="table-responsive mt-2 mb-2">
        <table class="table table-bordered table-sm table-bpu-items mb-0" id="tableBpuItems">
            <thead>
                <tr>
                    <th style="width: 45px;">NO</th>
                    <th>NAMA PRODUCT / JASA <span class="text-danger">*</span></th>
                    <th style="width: 90px;">CURR</th>
                    <th style="width: 200px;">HARGA SATUAN (DPP) <span class="text-danger">*</span></th>
                    <th style="width: 50px;">AKSI</th>
                </tr>
            </thead>
            <tbody id="bpuItemsBody">
                <!-- Rows injected dynamically via JS -->
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa;">
                    <td colspan="3" class="text-right font-weight-bold align-middle pr-3">
                        Total:
                    </td>
                    <td colspan="2" class="align-middle">
                        <span id="labelBpuTotal" class="subtotal-highlight-bpu">Rp 0,00</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Terbilang Display -->
    <div class="mb-3">
        <div class="terbilang-box-bpu">
            <strong>Terbilang: </strong> <span id="labelBpuTerbilang">Nol rupiah</span>
        </div>
    </div>

    <!-- ── Bagian 5: Penandatanganan & Lampiran ───────────────────── -->
    <div class="bpu-section-title">
        <i class="fas fa-user-edit mr-1"></i> 5. Pembuat &amp; Lampiran Gambar
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Dibuat Oleh <span class="text-danger">*</span></label>
                <input type="text" name="dibuat_oleh" id="bpuDibuatOleh" class="form-control form-control-sm"
                    value="<?= htmlspecialchars($username ?: 'RIA') ?>" required>
                <small class="text-muted">Nama pembuat formulir</small>
            </div>
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Dicek Oleh</label>
                <input type="text" name="dicek_oleh" id="bpuDicekOleh" class="form-control form-control-sm bg-light"
                    value="KABAG" readonly>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Lampiran Gambar / Bukti Transfer / Nota <span class="text-danger">*</span></label>
                <div class="custom-file">
                    <input type="file" name="lampiran" class="custom-file-input" id="bpuLampiranFile" accept=".jpg,.jpeg,.png,.pdf">
                    <label class="custom-file-label" for="bpuLampiranFile">Pilih gambar / scan bukti...</label>
                </div>
                <small class="text-muted">Format yang didukung: JPG, PNG, JPEG, PDF. Disimpan di folder <code>uploads/form_purchasing/</code>.</small>
            </div>

            <!-- Tanda Tangan Pembuat -->
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Tanda Tangan Pembuat (Opsional)</label>
                <div class="border rounded p-1 bg-light" style="width: 100%; max-width: 320px;">
                    <canvas id="bpuTtdCanvas" width="310" height="110" style="background:#fff; border:1px dashed #ced4da; width:100%; touch-action:none;"></canvas>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">Tanda tangan di area kotak</small>
                        <button type="button" class="btn btn-xs btn-outline-danger" id="btnBpuClearTtd">Clear</button>
                    </div>
                </div>
                <input type="hidden" name="ttd_dibuat_oleh" id="bpuTtdInput" value="">
            </div>
        </div>
    </div>
</form>

<!-- Modal Pilih Rekening Vendor (Jika Vendor Punya > 1 Rekening) -->
<div class="modal fade" id="modalPilihRekeningBpu" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-success text-white py-2 px-3">
                <h6 class="modal-title font-weight-bold mb-0">
                    <i class="fas fa-university mr-2"></i> Pilih Rekening Vendor
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="font-size:1.3rem;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <p class="small text-muted mb-2" id="modalRekeningSubtitleBpu">
                    Vendor memiliki beberapa rekening. Silakan klik salah satu rekening yang akan digunakan:
                </p>
                <div id="modalRekeningListBpu">
                    <!-- Cards rekening di-inject via JS -->
                </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var $form = $('#formBuktiPengembalianUang');
    var $tbody = $('#bpuItemsBody');

    // ─── Canvas Tanda Tangan ──────────────────────────────────────
    var canvas = document.getElementById('bpuTtdCanvas');
    var ctx = canvas ? canvas.getContext('2d') : null;
    var drawing = false;

    if (canvas && ctx) {
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        function getCanvasPos(e) {
            var rect = canvas.getBoundingClientRect();
            var scaleX = canvas.width / rect.width;
            var scaleY = canvas.height / rect.height;
            var clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
            var clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
            return {
                x: (clientX - rect.left) * scaleX,
                y: (clientY - rect.top) * scaleY
            };
        }

        function startDraw(e) {
            drawing = true;
            var pos = getCanvasPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }

        function draw(e) {
            if (!drawing) return;
            var pos = getCanvasPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            e.preventDefault();
        }

        function stopDraw() {
            if (drawing) {
                drawing = false;
                $('#bpuTtdInput').val(canvas.toDataURL('image/png'));
            }
        }

        $(canvas).on('mousedown touchstart', startDraw);
        $(canvas).on('mousemove touchmove', draw);
        $(canvas).on('mouseup touchend touchcancel', stopDraw);

        $('#btnBpuClearTtd').on('click', function(e) {
            e.preventDefault();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            $('#bpuTtdInput').val('');
        });
    }

    // ─── Format Currency & Terbilang ──────────────────────────────
    function formatCurrency(num, curr) {
        var n = parseFloat(num) || 0;
        var c = (curr || $('#bpuCurrency').val() || 'IDR').toUpperCase().trim();
        if (c === 'IDR' || c === 'RP') {
            return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            return c + ' ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    function bilang(n) {
        n = Math.abs(n);
        var satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if (n < 12) return ' ' + satuan[Math.floor(n)];
        if (n < 20) return bilang(n - 10) + ' belas';
        if (n < 100) return bilang(Math.floor(n / 10)) + ' puluh' + bilang(n % 10);
        if (n < 200) return ' seratus' + bilang(n - 100);
        if (n < 1000) return bilang(Math.floor(n / 100)) + ' ratus' + bilang(n % 100);
        if (n < 2000) return ' seribu' + bilang(n - 1000);
        if (n < 1000000) return bilang(Math.floor(n / 1000)) + ' ribu' + bilang(n % 1000);
        if (n < 1000000000) return bilang(Math.floor(n / 1000000)) + ' juta' + bilang(n % 1000000);
        if (n < 1000000000000) return bilang(Math.floor(n / 1000000000)) + ' milyar' + bilang(n % 1000000000);
        return bilang(Math.floor(n / 1000000000000)) + ' triliun' + bilang(n % 1000000000000);
    }

    function getTerbilangLengkap(num, curr) {
        var n = parseFloat(num) || 0;
        if (n === 0) return 'Nol rupiah';
        var c = (curr || $('#bpuCurrency').val() || 'IDR').toUpperCase().trim();
        var bulat = Math.floor(n);
        var sen = Math.round((n - bulat) * 100);

        var txt = bilang(bulat).trim();
        txt = txt.charAt(0).toUpperCase() + txt.slice(1);

        if (c === 'IDR' || c === 'RP') {
            txt += ' rupiah';
            if (sen > 0) {
                txt += ' ' + bilang(sen).trim() + ' sen';
            }
        } else {
            txt += ' ' + c;
            if (sen > 0) {
                txt += ' and ' + bilang(sen).trim() + ' cents';
            }
        }
        return txt;
    }

    // ─── Tambah Baris ke Tabel Item ───────────────────────────────
    function addRow(data) {
        data = data || {};
        var rowCount = $tbody.find('tr').length + 1;
        var currVal = data.curr || $('#bpuCurrency').val() || 'IDR';
        var ketVal = data.keterangan || '';
        var dppVal = (data.dpp !== undefined && data.dpp !== null) ? data.dpp : '';

        var html = '<tr class="bpu-item-row">' +
            '<td class="text-center font-weight-bold row-no align-middle">' + rowCount + '</td>' +
            '<td>' +
                '<input type="text" class="form-control form-control-sm item-keterangan" value="' + $('<div>').text(ketVal).html() + '" placeholder="Nama product / jasa / item..." required>' +
            '</td>' +
            '<td>' +
                '<input type="text" class="form-control form-control-sm item-curr text-center font-weight-bold" value="' + currVal + '" maxlength="5">' +
            '</td>' +
            '<td>' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm item-dpp text-right font-weight-bold" value="' + dppVal + '" placeholder="0.00" required>' +
            '</td>' +
            '<td class="text-center align-middle">' +
                '<button type="button" class="btn btn-outline-danger btn-xs btn-remove-row" title="Hapus baris"><i class="fas fa-times"></i></button>' +
            '</td>' +
        '</tr>';

        $tbody.append(html);
        reindexRows();
        recalculateTotal();
    }

    function reindexRows() {
        $tbody.find('tr').each(function(idx) {
            $(this).find('.row-no').text(idx + 1);
        });
    }

    function recalculateTotal() {
        var total = 0;
        var items = [];

        $tbody.find('tr.bpu-item-row').each(function(idx) {
            var $tr = $(this);
            var ket = $tr.find('.item-keterangan').val().trim();
            var curr = $tr.find('.item-curr').val().trim();
            var dpp = parseFloat($tr.find('.item-dpp').val()) || 0;

            total += dpp;

            items.push({
                no: idx + 1,
                keterangan: ket,
                cd: 'D',
                sifat: '+',
                curr: curr,
                dpp: dpp
            });
        });

        var mainCurr = $('#bpuCurrency').val() || 'IDR';
        $('#labelBpuTotal').text(formatCurrency(total, mainCurr));
        $('#bpuSubtotalInput').val(total.toFixed(2));

        var terbilangTxt = getTerbilangLengkap(total, mainCurr);
        $('#labelBpuTerbilang').text(terbilangTxt);
        $('#bpuTerbilangInput').val(terbilangTxt);

        $('#bpuItemsJson').val(JSON.stringify(items));
    }

    // ─── Event Handlers Tabel ─────────────────────────────────────
    $('#btnBpuAddRow').on('click', function(e) {
        e.preventDefault();
        addRow();
    });

    $tbody.on('click', '.btn-remove-row', function(e) {
        e.preventDefault();
        if ($tbody.find('tr').length <= 1) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Minimal harus ada 1 baris item!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            return;
        }
        $(this).closest('tr').remove();
        reindexRows();
        recalculateTotal();
    });

    $('#btnBpuDeleteAllRows').on('click', function(e) {
        e.preventDefault();
        $tbody.empty();
        addRow({
            keterangan: '',
            curr: $('#bpuCurrency').val() || 'IDR',
            dpp: ''
        });
        recalculateTotal();
    });

    $tbody.on('input change', '.item-dpp, .item-keterangan, .item-curr', function() {
        recalculateTotal();
    });

    $('#bpuCurrency').on('change', function() {
        var newCurr = $(this).val();
        $tbody.find('.item-curr').val(newCurr);
        recalculateTotal();
    });

    $('#bpuLampiranFile').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || 'Pilih gambar / scan bukti...');
    });

    // Pilihan Cepat Payment Method
    $(document).on('click', '.bpu-opt-pmt', function(e) {
        e.preventDefault();
        var val = $(this).data('value');
        if (val) {
            $('#bpuPaymentMethod').val(val).trigger('input').trigger('change');
        }
    });

    // ─── Tarik Data PO dari ERP ───────────────────────────────────
    function fillBpuFields(d, selectedAccount) {
        // 1. Supplier
        if (d.supplier) $('#bpuSupplier').val(d.supplier);

        // 2. Rekening: gunakan selectedAccount jika ada, fallback ke field di d
        var bank       = (selectedAccount && selectedAccount.bank)       || d.bank       || '';
        var rekeningAc = (selectedAccount && selectedAccount.no_rekening) || d.rekening_ac || '';
        var currency   = (selectedAccount && selectedAccount.currency)    || d.currency   || '';
        var currencyName = (selectedAccount && selectedAccount.currency_name) || d.currency_name || '';

        if (bank)       $('#bpuBank').val(bank);
        if (rekeningAc) $('#bpuRekeningAc').val(rekeningAc);

        // 3. Currency
        var currToSet = currency || d.currency || '';
        if (currToSet) {
            if ($('#bpuCurrency option[value="' + currToSet + '"]').length === 0) {
                var currText = currToSet + (currencyName ? ' (' + currencyName + ')' : '');
                $('#bpuCurrency').append(new Option(currText, currToSet));
            }
            $('#bpuCurrency').val(currToSet).trigger('change');
        }

        // 4. Payment Method (Otomatis dari fgpmttype ERP)
        if (d.payment_method) {
            $('#bpuPaymentMethod').val(d.payment_method);
        }

        // 5. RFP NO & Tgl RFP
        if (d.rfp_no) $('#bpuRfpNo').val(d.rfp_no);
        if (d.tgl_rfp) $('#bpuTglRfp').val(d.tgl_rfp);

        // 6. Keterangan: dibiarkan default kosong sesuai permintaan agar diisi manual oleh user

        // 7. Rincian Item PO
        if (Array.isArray(d.items) && d.items.length > 0) {
            $tbody.empty();
            d.items.forEach(function(item) {
                addRow(item);
            });
        }
    }

    function showBankPickerModalBpu(d, poNumber) {
        var accounts = d.vendor_accounts || [];
        var supplierName = $('<div>').text(d.supplier || '').html();
        var $modalPilih = $('#modalPilihRekeningBpu');

        if (!$modalPilih.parent().is('body')) {
            $modalPilih.appendTo('body');
        }

        $modalPilih.off('hide.bs.modal hidden.bs.modal').on('hide.bs.modal hidden.bs.modal', function(e) {
            e.stopPropagation();
            if (e.type === 'hidden') {
                if ($('#modalFormIsian').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            }
        });

        $('#modalRekeningSubtitleBpu').html(
            '<i class="fas fa-exclamation-circle text-warning mr-1"></i>' +
            'Vendor <strong>' + supplierName + '</strong> memiliki ' +
            '<span class="badge badge-warning text-dark">' + accounts.length + ' rekening</span>.' +
            ' Klik card rekening yang akan digunakan:'
        );

        var html = '';
        $.each(accounts, function(i, acc) {
            var bankLabel    = $('<div>').text(acc.bank || '-').html();
            var nomorRek     = $('<div>').text(acc.no_rekening || '-').html();
            var atasNama     = $('<div>').text(acc.atas_nama || '-').html();
            var currLabel    = $('<div>').text(acc.currency || 'IDR').html();
            var defaultBadge = acc.is_default ? '<span class="badge-default"><i class="fas fa-check-circle mr-1"></i>DEFAULT</span>' : '';
            var cardClass    = 'rekening-card-bpu' + (acc.is_default ? ' is-default' : '');

            html += '<div class="' + cardClass + '" data-idx="' + i + '">' +
                '<i class="fas fa-check-circle check-icon"></i>' +
                '<div class="d-flex align-items-center mb-1">' +
                    '<span class="rekening-bank"><i class="fas fa-university mr-1"></i>' + bankLabel + '</span>' +
                    defaultBadge +
                    '<span class="ml-auto rekening-currency"><i class="fas fa-coins mr-1"></i>' + currLabel + '</span>' +
                '</div>' +
                '<div class="rekening-nmbr">' + nomorRek + '</div>' +
                '<div class="rekening-atas-nama"><i class="fas fa-user mr-1"></i>' + atasNama + '</div>' +
            '</div>';
        });

        $('#modalRekeningListBpu').html(html);

        $('#modalRekeningListBpu').off('click', '.rekening-card-bpu').on('click', '.rekening-card-bpu', function() {
            var idx = parseInt($(this).data('idx'));
            var selectedAcc = accounts[idx];

            $('.rekening-card-bpu').removeClass('selected');
            $(this).addClass('selected');

            setTimeout(function() {
                $modalPilih.modal('hide');
                fillBpuFields(d, selectedAcc);

                Swal.fire({
                    icon: 'success',
                    title: 'Rekening Dipilih',
                    html: '<b>' + $('<div>').text(selectedAcc.bank || '').html() + '</b> ' +
                          $('<div>').text(selectedAcc.no_rekening || '').html() +
                          '<br><small class="text-muted">a/n ' + $('<div>').text(selectedAcc.atas_nama || '').html() + '</small>',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500
                });
            }, 220);
        });

        $modalPilih.modal({ backdrop: true, keyboard: true });
    }

    function tarikDataPoBpu(poNumber) {
        poNumber = (poNumber || $('#bpuRefPoNo').val() || '').trim();
        if (!poNumber) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Silakan ketik Nomor PO terlebih dahulu.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            return;
        }

        var $btn = $('#btnTarikPoBpu');
        var originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menarik...');

        $.ajax({
            url: 'get_po_data.php',
            type: 'GET',
            data: { po: poNumber },
            dataType: 'json',
            success: function(resp) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                if (resp && resp.success && resp.data) {
                    var d = resp.data;
                    var accCount = d.vendor_account_count || 0;

                    if (accCount > 1) {
                        fillBpuFields(d, null);
                        showBankPickerModalBpu(d, poNumber);

                        Swal.fire({
                            icon: 'info',
                            title: 'Data PO Ditemukan',
                            html: 'Vendor <b>' + $('<div>').text(d.supplier || '').html() + '</b> memiliki <b>' + accCount + ' rekening</b>.<br><small class="text-muted">Silakan pilih rekening pada popup.</small>',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3500
                        });
                    } else {
                        var singleAcc = (d.vendor_accounts && d.vendor_accounts.length > 0) ? d.vendor_accounts[0] : null;
                        fillBpuFields(d, singleAcc);

                        Swal.fire({
                            icon: 'success',
                            title: 'Data PO Ditemukan!',
                            html: 'Data untuk PO <b>' + $('<div>').text(poNumber).html() + '</b> berhasil ditarik dari ERP.' +
                                  '<br><small class="text-muted">Supplier: ' + $('<div>').text(d.supplier || '').html() +
                                  (d.payment_method ? '<br>Payment Method: ' + $('<div>').text(d.payment_method).html() : '') +
                                  (d.rfp_no ? '<br>RFP NO: ' + $('<div>').text(d.rfp_no).html() : '') +
                                  '</small>',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Tidak Ditemukan',
                        text: (resp && resp.message) ? resp.message : 'Nomor PO tidak ditemukan di sistem ERP.'
                    });
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                var errTxt = 'Gagal menghubungi server untuk menarik data PO.';
                try {
                    var r = JSON.parse(xhr.responseText);
                    errTxt = r.message || errTxt;
                } catch(e) {}
                Swal.fire({
                    icon: 'error',
                    title: 'Error Tarik Data',
                    text: errTxt
                });
            }
        });
    }

    $('#btnTarikPoBpu').on('click', function(e) {
        e.preventDefault();
        tarikDataPoBpu();
    });

    $('#bpuRefPoNo').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            tarikDataPoBpu();
        }
    });

    // Inisialisasi baris awal jika kosong
    if ($tbody.find('tr').length === 0) {
        addRow({
            keterangan: '',
            cd: 'D',
            sifat: '+',
            curr: 'IDR',
            dpp: ''
        });
    }

    // Expose populate helper untuk edit mode
    window.populateBpuItems = function(items) {
        $tbody.empty();
        if (Array.isArray(items) && items.length > 0) {
            items.forEach(function(item) {
                addRow(item);
            });
        } else {
            addRow();
        }
    };

})();
</script>
