<?php

session_start();
require_once __DIR__ . '/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method tidak valid.');
    }

    $kodePeriode = trim((string) ($_POST['kode_periode'] ?? ''));
    if ($kodePeriode === '') {
        throw new InvalidArgumentException('Kode periode wajib diisi.');
    }

    $gg = getSqlsrvConnection('gg');
    ensureTablesExist($gg);

    sqlsrv_begin_transaction($gg);

    $rekapCountBefore = getRekapCountByKode($gg, $kodePeriode);
    $materialCountBefore = getMaterialCountByKode($gg, $kodePeriode);

    $rawStmt = sqlsrvExecOrFail(
        $gg,
        "SELECT COUNT(*) AS total FROM dbo.mko_rawdata WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $rawRow = sqlsrv_fetch_array($rawStmt, SQLSRV_FETCH_ASSOC);
    $rawCountBefore = (int) ($rawRow['total'] ?? 0);

    if ($rawCountBefore <= 0 && $materialCountBefore <= 0 && $rekapCountBefore <= 0) {
        throw new RuntimeException('Kode periode tidak ditemukan pada ketiga tabel.');
    }

    sqlsrvExecOrFail($gg, "DELETE FROM dbo.MKO_rekap WHERE kode_periode = ?", [$kodePeriode]);
    sqlsrvExecOrFail($gg, "DELETE FROM dbo.MKO_materialobat WHERE kode_periode = ?", [$kodePeriode]);
    sqlsrvExecOrFail($gg, "DELETE FROM dbo.mko_rawdata WHERE kode_periode = ?", [$kodePeriode]);

    sqlsrv_commit($gg);

    jsonResponse([
        'success' => true,
        'message' => 'Data periode berhasil dihapus.',
        'kode_periode' => $kodePeriode,
        'deleted' => [
            'mko_rawdata' => $rawCountBefore,
            'MKO_materialobat' => $materialCountBefore,
            'MKO_rekap' => $rekapCountBefore,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($gg) && $gg) {
        @sqlsrv_rollback($gg);
    }

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
