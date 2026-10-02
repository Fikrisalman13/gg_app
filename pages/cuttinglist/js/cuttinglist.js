// ======================================================
// CUTTINGlist.js — FINAL FIX (NO CACAT PER PIECE)
// ======================================================

let pieceCounter = 1;
let cacatCounter = 1;

/* ===============================
   OPEN MODAL TAMBAH PIECE
================================ */
$('#btnAddPiece').on('click', function () {
    $('#modalPiece').modal('show');

    $('#pieceNo').val(pieceCounter);
    $('#uomPiece').val($('#uomCp').val());

    // reset hanya saat buka modal baru
    $('#pieceValue').val('');
    $('#tableCacatInput tbody').html(
        '<tr><td colspan="7" class="text-center">No data</td></tr>'
    );
    cacatCounter = 1;
});

/* ===============================
   TAMBAH CACAT (MASTER KODE)
================================ */
$('#btnAddCacat').on('click', function () {

    if (
        $('#tableCacatInput tbody tr').length === 1 &&
        $('#tableCacatInput tbody tr td').length === 1
    ) {
        $('#tableCacatInput tbody').empty();
    }

    let opt = '<option value="">- pilih -</option>';
    MASTER_KODE_CACAT.forEach(d => {
        opt += `<option value="${d.kode_defect}"
                    data-nama="${d.nama_defect}"
                    data-status="${d.status_defect}">
                    ${d.kode_defect} - ${d.nama_defect}
                </option>`;
    });

    $('#tableCacatInput tbody').append(`
        <tr>
            <td class="text-center">${cacatCounter}</td>
            <td>
                <select class="form-control form-control-sm kode select-cacat-kode" style="width: 100%;">
                    ${opt}
                </select>
            </td>
            <td>
                <input class="form-control form-control-sm nama" readonly>
            </td>
            <td>
                <input class="form-control form-control-sm status" readonly>
            </td>
            <td>
                <input type="number" step="0.001" class="form-control form-control-sm dari" placeholder="0.000">
            </td>
            <td>
                <input type="number" step="0.001" class="form-control form-control-sm sampai" placeholder="0.000">
            </td>
            <td>
                <input class="form-control form-control-sm panjang" readonly>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger btn-hapus-cacat">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `);

    // Initialize Select2 for this row (with check)
    if (typeof $.fn.select2 !== 'undefined') {
        $('#tableCacatInput tbody tr:last .select-cacat-kode').select2({
            placeholder: 'Pilih kode cacat',
            allowClear: true,
            width: '100%'
        });
    }

    cacatCounter++;
});

/* ===============================
   HAPUS BARIS CACAT
================================ */
$(document).on('click', '.btn-hapus-cacat', function () {
    $(this).closest('tr').remove();
    
    // Jika sudah kosong, tambah placeholder
    if ($('#tableCacatInput tbody tr').length === 0) {
        $('#tableCacatInput tbody').html(
            '<tr><td colspan="8" class="text-center">No data</td></tr>'
        );
    }
});

/* ===============================
   AUTO FILL NAMA & STATUS (KODE)
   - Juga support fill dari Nama/Status
================================ */
$(document).on('change', '.kode', function () {
    const opt = $(this).find(':selected');
    const row = $(this).closest('tr');

    const nama = opt.data('nama') || '';
    const status = opt.data('status') || '';

    row.find('.nama').val(nama);
    row.find('.status').val(status);
});

/* ===============================
   AUTO FILL DARI NAMA (optional)
================================ */
$(document).on('change', '.nama', function () {
    const namaVal = $(this).val().trim();
    const row = $(this).closest('tr');

    // Cari kode yang sesuai dengan nama ini
    const matching = MASTER_KODE_CACAT.find(d => d.nama_defect === namaVal);
    if (matching) {
        row.find('.kode').val(matching.kode_defect).trigger('change');
    }
});

/* ===============================
   HITUNG PANJANG CACAT OTOMATIS
================================ */
$(document).on('input', '.dari, .sampai', function () {
    const row = $(this).closest('tr');
    const dari = parseFloat(row.find('.dari').val()) || 0;
    const sampai = parseFloat(row.find('.sampai').val()) || 0;

    const panjang = sampai - dari;
    row.find('.panjang').val(panjang > 0 ? panjang.toFixed(3) : '0.000');
});

/* ===============================
   EDIT PIECE
================================ */
$(document).on('click', '.btn-edit-piece', function () {
    const row = $(this).closest('tr');
    const pieceCode = row.data('piece');
    
    // Load data dari row ke modal
    $('#pieceNo').val(row.find('td:eq(0)').text());
    $('#pieceValue').val(row.find('td:eq(1)').text());
    $('#panjangAwal').val(row.find('td:eq(2)').text());
    $('#panjangAkhir').val(row.find('td:eq(3)').text());
    $('#lebarKain').val(row.find('td:eq(4)').text());
    
    // Get original values (sebelum toleransi)
    const stdTol = parseFloat(row.find('td:eq(5)').text());
    const maxTol = parseFloat(row.find('td:eq(6)').text());
    const minTol = parseFloat(row.find('td:eq(7)').text());
    const toleransiOriginal = parseFloat(row.find('td:eq(8)').text());
    const uomCp = $('#uomCp').val().trim();
    
    // Convert back to original std/max/min (remove toleransi)
    // Konversi toleransi dari CM ke UOM sesuai UOM CP
    let toleransiKonversi = toleransiOriginal * 0.01; // Default: konversi ke Meter
    if (uomCp === 'Y') {
        // Jika UOM CP Yard: konversi CM ke Yard
        toleransiKonversi = toleransiOriginal * (0.01 / 0.9144);
    }
    
    const stdOrig = (stdTol - toleransiKonversi).toFixed(3);
    const maxOrig = (maxTol - toleransiKonversi).toFixed(3);
    const minOrig = (minTol - toleransiKonversi).toFixed(3);
    
    $('#stdPotong').val(stdOrig);
    $('#maxPotong').val(maxOrig);
    $('#minPotong').val(minOrig);
    $('#toleransiPotong').val(toleransiOriginal);
    
    // Load cacat yang terkait piece ini
    $('#tableCacatInput tbody').html(
        '<tr><td colspan="8" class="text-center">No data</td></tr>'
    );
    cacatCounter = 1;
    
    $('#tableCacatAll tbody tr').each(function () {
        if ($(this).data('piece') === pieceCode) {
            const dari = $(this).find('td:eq(5)').text();
            const sampai = $(this).find('td:eq(6)').text();
            const kode = $(this).find('td:eq(2)').text();
            const nama = $(this).find('td:eq(3)').text();
            const status = $(this).find('td:eq(4)').text();
            
            // Populate cacat input
            if (cacatCounter === 1 && $('#tableCacatInput tbody tr td').eq(0).text() === 'No data') {
                $('#tableCacatInput tbody').empty();
            }
            
            let opt = '<option value="">- pilih -</option>';
            MASTER_KODE_CACAT.forEach(d => {
                opt += `<option value="${d.kode_defect}"
                            data-nama="${d.nama_defect}"
                            data-status="${d.status_defect}">
                            ${d.kode_defect} - ${d.nama_defect}
                        </option>`;
            });

            $('#tableCacatInput tbody').append(`
                <tr>
                    <td class="text-center">${cacatCounter}</td>
                    <td>
                        <select class="form-control form-control-sm kode select-cacat-kode" style="width: 100%;">
                            ${opt}
                        </select>
                    </td>
                    <td>
                        <input class="form-control form-control-sm nama" readonly>
                    </td>
                    <td>
                        <input class="form-control form-control-sm status" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.001" class="form-control form-control-sm dari" placeholder="0.000">
                    </td>
                    <td>
                        <input type="number" step="0.001" class="form-control form-control-sm sampai" placeholder="0.000">
                    </td>
                    <td>
                        <input class="form-control form-control-sm panjang" readonly>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger btn-hapus-cacat">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            // Fill cacat data
            $('#tableCacatInput tbody tr:last .kode').val(kode).trigger('change');
            $('#tableCacatInput tbody tr:last .dari').val(dari);
            $('#tableCacatInput tbody tr:last .sampai').val(sampai);
            $('#tableCacatInput tbody tr:last .panjang').val($(this).find('td:eq(7)').text());
            
            // Initialize Select2 (with check)
            if (typeof $.fn.select2 !== 'undefined') {
                $('#tableCacatInput tbody tr:last .select-cacat-kode').select2({
                    placeholder: 'Pilih kode cacat',
                    allowClear: true,
                    width: '100%'
                });
            }
            
            cacatCounter++;
        }
    });
    
    // Mark this row for update (not insert)
    $('#btnApplyPiece').data('editMode', true);
    $('#btnApplyPiece').data('editPiece', pieceCode);
    
    $('#modalPiece').modal('show');
});

/* ===============================
   APPLY PIECE (dengan update support)
================================ */
$('#btnApplyPiece').on('click', function () {

    const pieceVal = $('#pieceValue').val().trim();

    if (!pieceVal) {
        alert('Piece wajib diisi');
        return;
    }

    if (
        !$('#panjangAwal').val() ||
        !$('#panjangAkhir').val() ||
        !$('#lebarKain').val() ||
        !$('#stdPotong').val() ||
        !$('#maxPotong').val() ||
        !$('#minPotong').val()
    ) {
        alert('Semua field piece wajib diisi');
        return;
    }

    /* === HITUNG NILAI DENGAN TOLERANSI === */
    // Toleransi input dalam CM, harus dikonversi dulu sesuai UOM CP (satuan input std/min/max)
    const toleransiCM = parseFloat($('#toleransiPotong').val()) || 0;
    const uomCp = $('#uomCp').val().trim(); // M atau Y - UNIT DARI STD/MIN/MAX INPUT
    
    // Konversi toleransi dari CM ke UOM CP
    let toleransiKonversi = toleransiCM * 0.01; // Default: konversi ke Meter (CM * 0.01)
    if (uomCp === 'Y') {
        // Jika UOM CP Yard: konversi CM ke Yard (30 CM = 0.3281 Y)
        toleransiKonversi = toleransiCM * (0.01 / 0.9144);
    }
    // Else: uomCp === 'M', gunakan default (CM * 0.01)
    
    const std = parseFloat($('#stdPotong').val());
    const max = parseFloat($('#maxPotong').val());
    const min = parseFloat($('#minPotong').val());

    const stdTol = (std + toleransiKonversi).toFixed(4);
    const maxTol = (max + toleransiKonversi).toFixed(4);
    const minTol = (min + toleransiKonversi).toFixed(4);

    const isEditMode = $(this).data('editMode');
    const editPiece = $(this).data('editPiece');

    if (isEditMode && editPiece) {
        // UPDATE MODE - ganti row yang ada
        $('#tablePiece tbody tr').each(function () {
            if ($(this).data('piece') === editPiece) {
                $(this).html(`
                    <td class="text-center">${$(this).find('td:eq(0)').text()}</td>
                    <td class="text-center">${pieceVal}</td>
                    <td>${$('#panjangAwal').val()}</td>
                    <td>${$('#panjangAkhir').val()}</td>
                    <td>${$('#lebarKain').val()}</td>
                    <td>${stdTol}</td>
                    <td>${maxTol}</td>
                    <td>${minTol}</td>
                    <td class="text-center">${$('#toleransiPotong').val() || '0.00'}</td>
                    <td class="text-center">${$('#uomPiece').val()}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-info btn-edit-piece" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-hapus-piece" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `);
                // Simpan nilai original (tanpa tol) di data attribute
                $(this).data('std-orig', std.toFixed(4));
                $(this).data('min-orig', min.toFixed(4));
                $(this).data('max-orig', max.toFixed(4));
                return false;
            }
        });
        
        // Update cacat yang terkait
        $('#tableCacatAll tbody tr').each(function () {
            if ($(this).data('piece') === editPiece) {
                $(this).remove();
            }
        });
        
        // Masukkan cacat yang baru
        let cacatNo = 1;
        $('#tableCacatInput tbody tr').each(function () {
            if ($(this).find('td').length === 1) return;

            const kode = $(this).find('.kode').val();
            const dari = $(this).find('.dari').val();
            const sampai = $(this).find('.sampai').val();

            if (!kode || !dari || !sampai) return;

            $('#tableCacatAll tbody').append(`
                <tr data-piece="${editPiece}" data-cacat-no="${cacatNo}">
                    <td class="text-center">${editPiece}</td>
                    <td class="text-center">${cacatNo}</td>
                    <td>${kode}</td>
                    <td>${$(this).find('.nama').val()}</td>
                    <td class="text-center">${$(this).find('.status').val()}</td>
                    <td>${dari}</td>
                    <td>${sampai}</td>
                    <td>${$(this).find('.panjang').val()}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-info btn-edit-cacat" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-hapus-cacat-all" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);

            cacatNo++;
        });
        
        // Reset edit mode
        $('#btnApplyPiece').data('editMode', false);
        $('#btnApplyPiece').data('editPiece', null);
    } else {
        // INSERT MODE - tambah row baru
        /* === INSERT KE TAB PIECE === */
        $('#tablePiece tbody').append(`
            <tr data-piece="${pieceVal}">
                <td class="text-center">${pieceCounter}</td>
                <td class="text-center">${pieceVal}</td>
                <td>${$('#panjangAwal').val()}</td>
                <td>${$('#panjangAkhir').val()}</td>
                <td>${$('#lebarKain').val()}</td>
                <td>${stdTol}</td>
                <td>${maxTol}</td>
                <td>${minTol}</td>
                <td class="text-center">${$('#toleransiPotong').val() || '0.00'}</td>
                <td class="text-center">${$('#uomPiece').val()}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-info btn-edit-piece" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-hapus-piece" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `);
        // Simpan nilai original (tanpa tol) di data attribute
        $('#tablePiece tbody tr').last().data('std-orig', std.toFixed(4));
        $('#tablePiece tbody tr').last().data('min-orig', min.toFixed(4));
        $('#tablePiece tbody tr').last().data('max-orig', max.toFixed(4));;

        /* === INSERT KE TAB CACAT === */
        let cacatNo = 1;

        $('#tableCacatInput tbody tr').each(function () {
            if ($(this).find('td').length === 1) return;

            const kode = $(this).find('.kode').val();
            const dari = $(this).find('.dari').val();
            const sampai = $(this).find('.sampai').val();

            if (!kode || !dari || !sampai) return;

            $('#tableCacatAll tbody').append(`
                <tr data-piece="${pieceVal}" data-cacat-no="${cacatNo}">
                    <td class="text-center">${pieceVal}</td>
                    <td class="text-center">${cacatNo}</td>
                    <td>${kode}</td>
                    <td>${$(this).find('.nama').val()}</td>
                    <td class="text-center">${$(this).find('.status').val()}</td>
                    <td>${dari}</td>
                    <td>${sampai}</td>
                    <td>${$(this).find('.panjang').val()}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-info btn-edit-cacat" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-hapus-cacat-all" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);

            cacatNo++;
        });

        pieceCounter++;
    }

    /* === RESET SESUAI REQUIREMENT === */
    $('#pieceNo').val(pieceCounter);
    $('#pieceValue').val('');

    $('#tableCacatInput tbody').html(
        '<tr><td colspan="8" class="text-center">No data</td></tr>'
    );
    cacatCounter = 1;
if (document.activeElement) {
    document.activeElement.blur();
}
$('#modalPiece').modal('hide');

});

/* ===============================
   DELETE PIECE
================================ */
$(document).on('click', '.btn-hapus-piece', function () {
    if (!confirm('Hapus piece ini dan semua cacat terkait?')) {
        return;
    }
    
    const row = $(this).closest('tr');
    const pieceCode = row.data('piece');
    
    // Hapus dari tab Piece
    row.remove();
    
    // Hapus semua cacat terkait dari tab Cacat
    $('#tableCacatAll tbody tr').each(function () {
        if ($(this).data('piece') === pieceCode) {
            $(this).remove();
        }
    });
});

/* ===============================
   EDIT CACAT (dari tab Cacat)
================================ */
$(document).on('click', '.btn-edit-cacat', function () {
    const row = $(this).closest('tr');
    const pieceCode = row.data('piece');
    const cacatNo = row.data('cacat-no');
    const dari = row.find('td:eq(5)').text();
    const sampai = row.find('td:eq(6)').text();
    const kode = row.find('td:eq(2)').text();
    const nama = row.find('td:eq(3)').text();
    const status = row.find('td:eq(4)').text();
    
    // Load piece ke modal
    $('#pieceValue').val(pieceCode);
    
    // Find piece data dan load ke form
    $('#tablePiece tbody tr').each(function () {
        if ($(this).data('piece') === pieceCode) {
            $('#pieceNo').val($(this).find('td:eq(0)').text());
            $('#panjangAwal').val($(this).find('td:eq(2)').text());
            $('#panjangAkhir').val($(this).find('td:eq(3)').text());
            $('#lebarKain').val($(this).find('td:eq(4)').text());
            
            const stdTol = parseFloat($(this).find('td:eq(5)').text());
            const maxTol = parseFloat($(this).find('td:eq(6)').text());
            const minTol = parseFloat($(this).find('td:eq(7)').text());
            const toleransiOriginal = parseFloat($(this).find('td:eq(8)').text());
            const uomCp = $('#uomCp').val().trim();
            
            // Konversi toleransi dari CM ke UOM sesuai UOM CP
            let toleransiKonversi = toleransiOriginal * 0.01; // Default: konversi ke Meter
            if (uomCp === 'Y') {
                // Jika UOM CP Yard: konversi CM ke Yard
                toleransiKonversi = toleransiOriginal * (0.01 / 0.9144);
            }
            
            const stdOrig = (stdTol - toleransiKonversi).toFixed(3);
            const maxOrig = (maxTol - toleransiKonversi).toFixed(3);
            const minOrig = (minTol - toleransiKonversi).toFixed(3);
            
            $('#stdPotong').val(stdOrig);
            $('#maxPotong').val(maxOrig);
            $('#minPotong').val(minOrig);
            $('#toleransiPotong').val(toleransiOriginal);
            
            return false;
        }
    });
    
    // Load cacat yang terkait piece ini
    $('#tableCacatInput tbody').html(
        '<tr><td colspan="8" class="text-center">No data</td></tr>'
    );
    cacatCounter = 1;
    
    $('#tableCacatAll tbody tr').each(function () {
        if ($(this).data('piece') === pieceCode) {
            const cacatDari = $(this).find('td:eq(5)').text();
            const cacatSampai = $(this).find('td:eq(6)').text();
            const cacatKode = $(this).find('td:eq(2)').text();
            const cacatNama = $(this).find('td:eq(3)').text();
            const cacatStatus = $(this).find('td:eq(4)').text();
            
            if (cacatCounter === 1 && $('#tableCacatInput tbody tr td').eq(0).text() === 'No data') {
                $('#tableCacatInput tbody').empty();
            }
            
            let opt = '<option value="">- pilih -</option>';
            MASTER_KODE_CACAT.forEach(d => {
                opt += `<option value="${d.kode_defect}"
                            data-nama="${d.nama_defect}"
                            data-status="${d.status_defect}">
                            ${d.kode_defect} - ${d.nama_defect}
                        </option>`;
            });

            $('#tableCacatInput tbody').append(`
                <tr>
                    <td class="text-center">${cacatCounter}</td>
                    <td>
                        <select class="form-control form-control-sm kode select-cacat-kode" style="width: 100%;">
                            ${opt}
                        </select>
                    </td>
                    <td>
                        <input class="form-control form-control-sm nama" readonly>
                    </td>
                    <td>
                        <input class="form-control form-control-sm status" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.001" class="form-control form-control-sm dari" placeholder="0.000">
                    </td>
                    <td>
                        <input type="number" step="0.001" class="form-control form-control-sm sampai" placeholder="0.000">
                    </td>
                    <td>
                        <input class="form-control form-control-sm panjang" readonly>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger btn-hapus-cacat">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            $('#tableCacatInput tbody tr:last .kode').val(cacatKode).trigger('change');
            $('#tableCacatInput tbody tr:last .dari').val(cacatDari);
            $('#tableCacatInput tbody tr:last .sampai').val(cacatSampai);
            $('#tableCacatInput tbody tr:last .panjang').val($(this).find('td:eq(7)').text());
            
            // Initialize Select2 (with check)
            if (typeof $.fn.select2 !== 'undefined') {
                $('#tableCacatInput tbody tr:last .select-cacat-kode').select2({
                    placeholder: 'Pilih kode cacat',
                    allowClear: true,
                    width: '100%'
                });
            }
            
            cacatCounter++;
        }
    });
    
    // Mark for cacat edit
    $('#btnApplyPiece').data('editMode', true);
    $('#btnApplyPiece').data('editPiece', pieceCode);
    
    $('#modalPiece').modal('show');
});

/* ===============================
   DELETE CACAT (dari tab Cacat)
================================ */
$(document).on('click', '.btn-hapus-cacat-all', function () {
    if (!confirm('Hapus cacat ini?')) {
        return;
    }
    
    $(this).closest('tr').remove();
});

/* ===============================
   CANCEL HEADER
================================ */
$('#btnCancelAll').on('click', function () {
    if (confirm('Batalkan perubahan? Data tidak akan disimpan.')) {
        window.location.href = 'index.php';
    }
});
/* ===============================
   SAVE ALL TO DATABASE
================================ */
$('#btnSaveAll').on('click', function () {

    if (!$('#cpNo').val() || !$('#uomCp').val() || !$('#mesinInspect').val()) {
        alert('CP No, UOM CP, dan Mesin wajib diisi');
        return;
    }

    if ($('#tablePiece tbody tr').length === 0) {
        alert('Minimal 1 piece wajib ditambahkan');
        return;
    }

    let pieces = [];

    $('#tablePiece tbody tr').each(function (i) {
        const pieceCode = $(this).find('td:eq(1)').text();
        let cacatList = [];

        $('#tableCacatAll tbody tr').each(function () {
            if ($(this).find('td:eq(0)').text() === pieceCode) {
                const kode = $(this).find('td:eq(2)').text().trim();
                const dari = $(this).find('td:eq(5)').text().trim();
                const sampai = $(this).find('td:eq(6)').text().trim();

                // hanya masukkan jika tidak kosong
                if (kode && dari && sampai) {
                    cacatList.push({
                        no: $(this).find('td:eq(1)').text(),
                        kode: kode,
                        nama: $(this).find('td:eq(3)').text().trim(),
                        status: $(this).find('td:eq(4)').text().trim(),
                        dari: dari,
                        sampai: sampai,
                        panjang: $(this).find('td:eq(7)').text().trim()
                    });
                }
            }
        });

        /* === AMBIL NILAI ASLI DARI DATA ATTRIBUTE === */
        // Saat Apply piece, nilai original std/min/max disimpan di data attribute
        // Saat save, ambil dari sana untuk ensure presisi
        let stdValue = $(this).data('std-orig');
        let minValue = $(this).data('min-orig');
        let maxValue = $(this).data('max-orig');
        
        // Jika tidak ada (data lama), fallback ke table value (tapi ini kurang presisi)
        if (!stdValue) {
            stdValue = parseFloat($(this).find('td:eq(5)').text()).toFixed(4);
        }
        if (!minValue) {
            minValue = parseFloat($(this).find('td:eq(7)').text()).toFixed(4);
        }
        if (!maxValue) {
            maxValue = parseFloat($(this).find('td:eq(6)').text()).toFixed(4);
        }

        pieces.push({
            piece_no: i + 1,
            piece_code: pieceCode,
            panjang_awal: $(this).find('td:eq(2)').text(),
            panjang_akhir: $(this).find('td:eq(3)').text(),
            susut: parseFloat($(this).find('td:eq(2)').text()) -
                   parseFloat($(this).find('td:eq(3)').text()),
            lebar: $(this).find('td:eq(4)').text(),
            std: stdValue,
            max: maxValue,
            min: minValue,
            toleransi: $(this).find('td:eq(8)').text(),
            uom: $(this).find('td:eq(9)').text(),
            cacat: cacatList
        });
    });

    const payload = {
        cp_no: $('#cpNo').val(),
        type_counter: $('#typeCounter').val(),
        uom_cp: $('#uomCp').val(),
        id_mesin: $('#mesinInspect').val(),
        pieces: pieces
    };

    $.post('save_cutting.php', {
        payload: JSON.stringify(payload)
    })
    .done(function(response) {
        try {
            const result = typeof response === 'string' ? JSON.parse(response) : response;
            if (result.status === 'ok') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Data berhasil disimpan',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'index.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: result.error || 'Unknown error',
                    confirmButtonText: 'OK'
                });
            }
        } catch(e) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error parsing response: ' + e.message,
                confirmButtonText: 'OK'
            });
            console.error('Response:', response);
        }
    })
    .fail(function(xhr) {
        let errorMsg = 'Gagal simpan';
        try {
            const response = JSON.parse(xhr.responseText);
            errorMsg = response.error || errorMsg;
        } catch(e) {
            errorMsg = xhr.statusText || 'Network error';
        }
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: errorMsg,
            confirmButtonText: 'OK'
        });
        console.error('XHR:', xhr);
    });
});
