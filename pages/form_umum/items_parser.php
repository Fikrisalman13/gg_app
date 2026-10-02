<?php

if (!function_exists('parseGudangTransaksi')) {
    function parseGudangTransaksi($text) {
        $result = ['gudang' => '-', 'transaksi' => '-', 'nomor_transaksi' => '-', 'jenis_transaksi' => '-'];
        $text = trim((string)$text);
        if ($text === '') return $result;

        $parts = array_map('trim', explode(',', $text));
        $gudang = [];
        $transaksi = [];
        foreach ($parts as $part) {
            if ($part === '') continue;
            if (preg_match('/^(Procurement|Sales|Cash Management)\s*[:\-]\s*(.*)$/i', $part, $mPart)) {
                $result['jenis_transaksi'] = trim($mPart[1]);
                $trxText = trim($mPart[2]);
                if (preg_match('/\(\s*No\.\s*Transaksi\s*[:\-]\s*(.*?)\)\s*$/i', $trxText, $mNo)) {
                    $result['nomor_transaksi'] = trim($mNo[1]);
                    $trxText = trim(preg_replace('/\(\s*No\.\s*Transaksi\s*[:\-]\s*(.*?)\)\s*$/i', '', $trxText));
                }
                $transaksi[] = $trxText;
            } else {
                $gudang[] = $part;
            }
        }

        if (!empty($gudang)) $result['gudang'] = implode(', ', $gudang);
        if (!empty($transaksi)) $result['transaksi'] = implode(', ', $transaksi);
        return $result;
    }
}

if (!function_exists('parseItemsFromGudangTransaksi')) {
    function parseItemsFromGudangTransaksi($value) {
        // Daftar key yang harus ada di setiap item
        $defaultKeys = [
            'buka_tgl'          => '',
            'request_gudang'    => 0,
            'request_transaksi' => 0,
            'gudang'            => '',
            'jenis_transaksi'   => '',
            'transaksi'         => '',
            'nomor_transaksi'   => '',
            'vendor_cust'       => '',
            'keterangan'        => '',
        ];

        try {
            // Input null atau string kosong setelah trim → return []
            if ($value === null) return [];
            $trimmed = trim((string)$value);
            if ($trimmed === '') return [];

            // Deteksi Legacy Format: tidak dimulai dengan '[' dan tidak dimulai dengan '{'
            if ($trimmed[0] !== '[' && $trimmed[0] !== '{') {
                $legacy = parseGudangTransaksi($trimmed);
                return [[
                    'buka_tgl'          => '',
                    'request_gudang'    => 0,
                    'request_transaksi' => 0,
                    'gudang'            => $legacy['gudang'],
                    'jenis_transaksi'   => $legacy['jenis_transaksi'] ?? '',
                    'transaksi'         => $legacy['transaksi'],
                    'nomor_transaksi'   => $legacy['nomor_transaksi'],
                    'vendor_cust'       => '',
                    'keterangan'        => '',
                ]];
            }

            // Coba json_decode
            $decoded = json_decode($trimmed, true);

            // json_decode gagal (JSON tidak valid) → fallback 1 item dengan gudang = $value
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [[
                    'buka_tgl'          => '',
                    'request_gudang'    => 0,
                    'request_transaksi' => 0,
                    'gudang'            => $trimmed,
                    'jenis_transaksi'   => '',
                    'transaksi'         => '',
                    'nomor_transaksi'   => '',
                    'vendor_cust'       => '',
                    'keterangan'        => '',
                ]];
            }

            // json_decode berhasil tapi hasilnya bukan array (misal object/string)
            if (!is_array($decoded)) {
                $legacy = parseGudangTransaksi($trimmed);
                return [[
                    'buka_tgl'          => '',
                    'request_gudang'    => 0,
                    'request_transaksi' => 0,
                    'gudang'            => $legacy['gudang'],
                    'jenis_transaksi'   => $legacy['jenis_transaksi'] ?? '',
                    'transaksi'         => $legacy['transaksi'],
                    'nomor_transaksi'   => $legacy['nomor_transaksi'],
                    'vendor_cust'       => '',
                    'keterangan'        => '',
                ]];
            }

            // json_decode menghasilkan array kosong → return []
            if (count($decoded) === 0) return [];

            // json_decode menghasilkan array valid dan tidak kosong
            // Pastikan setiap elemen memiliki semua key dengan default
            $items = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    // Elemen bukan array, skip atau jadikan fallback
                    $items[] = $defaultKeys;
                    continue;
                }
                $normalized = $defaultKeys;
                foreach ($defaultKeys as $key => $default) {
                    if (array_key_exists($key, $item)) {
                        $normalized[$key] = $item[$key];
                    }
                }
                $items[] = $normalized;
            }
            return $items;

        } catch (\Throwable $e) {
            // Semua exception/error di-catch, tidak boleh throw
            return [];
        }
    }
}
