<?php

/**
 * Look up unique sale labels for active-page production numbers in one PostgreSQL query.
 *
 * @param array<int, string> $productionNumbers Local No CP values.
 * @return array<string, string> Labels keyed by trimmed No CP; missing labels are omitted.
 */
function recipeSaleLabelsByProductionNumber(PDO $connection, array $productionNumbers): array
{
    $uniqueNumbers = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        $productionNumbers,
    ))));
    if (!$uniqueNumbers) {
        return [];
    }

    $placeholders = implode(", ", array_fill(0, count($uniqueNumbers), "?"));
    $statement = $connection->prepare(
        "SELECT
             production.prdnmbr,
             STRING_AGG(DISTINCT NULLIF(TRIM(product_tech.labeljual), ''), ' / ') AS label_jual
         FROM pdproductionhd production
         LEFT JOIN smprodtechdata product_tech
           ON product_tech.prodid = production.prodid
         WHERE production.prdnmbr IN ($placeholders)
         GROUP BY production.prdnmbr",
    );
    $statement->execute($uniqueNumbers);

    $labelsByProductionNumber = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $productionNumber = trim((string) ($row["prdnmbr"] ?? ""));
        $saleLabel = trim((string) ($row["label_jual"] ?? ""));
        if ($productionNumber !== "" && $saleLabel !== "") {
            $labelsByProductionNumber[$productionNumber] = $saleLabel;
        }
    }
    return $labelsByProductionNumber;
}

/**
 * Search sale labels only for production numbers present in local recipes.
 *
 * @param array<int, string> $productionNumbers Local No CP values.
 * @return array<int, string> Up to 30 unique matching labels.
 */
function recipeSearchLocalSaleLabels(
    PDO $connection,
    array $productionNumbers,
    string $search,
    int $limit = 30,
): array {
    $uniqueNumbers = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        $productionNumbers,
    ))));
    $search = trim($search);
    if (!$uniqueNumbers || mb_strlen($search) < 2) {
        return [];
    }

    $placeholders = implode(", ", array_fill(0, count($uniqueNumbers), "?"));
    $statement = $connection->prepare(
        "SELECT DISTINCT TRIM(product_tech.labeljual) AS label_jual
         FROM pdproductionhd production
         JOIN smprodtechdata product_tech
           ON product_tech.prodid = production.prodid
         WHERE production.prdnmbr IN ($placeholders)
           AND NULLIF(TRIM(product_tech.labeljual), '') IS NOT NULL
           AND TRIM(product_tech.labeljual) ILIKE ?
         ORDER BY label_jual
         LIMIT ?",
    );
    $position = 1;
    foreach ($uniqueNumbers as $productionNumber) {
        $statement->bindValue($position++, $productionNumber);
    }
    $statement->bindValue($position++, "%{$search}%");
    $statement->bindValue($position, max(1, min($limit, 30)), PDO::PARAM_INT);
    $statement->execute();
    return array_column($statement->fetchAll(PDO::FETCH_ASSOC), "label_jual");
}

/** @return array<int, string> Up to 30 unique matching sale labels. */
function recipeSearchSaleLabels(PDO $connection, string $search, int $limit = 30): array
{
    $search = trim($search);
    if (mb_strlen($search) < 2) {
        return [];
    }
    $statement = $connection->prepare(
        "SELECT DISTINCT TRIM(labeljual) AS label_jual
         FROM smprodtechdata
         WHERE NULLIF(TRIM(labeljual), '') IS NOT NULL
           AND TRIM(labeljual) ILIKE :search
         ORDER BY label_jual
         LIMIT :result_limit",
    );
    $statement->bindValue(':search', "%{$search}%");
    $statement->bindValue(':result_limit', max(1, min($limit, 30)), PDO::PARAM_INT);
    $statement->execute();
    return array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'label_jual');
}

/** @return array<int, string> Unique production numbers matching one exact sale label. */
function recipeProductionNumbersBySaleLabel(PDO $connection, string $saleLabel): array
{
    $saleLabel = trim($saleLabel);
    if ($saleLabel === '') {
        return [];
    }
    $statement = $connection->prepare(
        "SELECT DISTINCT production.prdnmbr
         FROM pdproductionhd production
         JOIN smprodtechdata product_tech ON product_tech.prodid = production.prodid
         WHERE TRIM(product_tech.labeljual) = :sale_label
           AND NULLIF(TRIM(production.prdnmbr), '') IS NOT NULL
         ORDER BY production.prdnmbr",
    );
    $statement->execute([':sale_label' => $saleLabel]);
    return array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'prdnmbr');
}

/**
 * Return local recipe No CP values matching one exact sale label.
 *
 * Intersects in PHP because source databases use separate connections and SQL Server has a 2,100-parameter limit.
 *
 * @param resource $sqlServerConnection SQLSRV connection.
 * @return array<int, string>
 */
function recipeLocalProductionNumbersBySaleLabel(
    $sqlServerConnection,
    PDO $postgresConnection,
    string $saleLabel,
): array {
    $matchingNumbers = array_fill_keys(array_map(
        static fn($value): string => trim((string) $value),
        recipeProductionNumbersBySaleLabel($postgresConnection, $saleLabel),
    ), true);
    if (!$matchingNumbers) {
        return [];
    }

    $statement = sqlsrv_query(
        $sqlServerConnection,
        "SELECT DISTINCT LTRIM(RTRIM(no_cp)) AS no_cp
         FROM dbo.resep_obat
         WHERE NULLIF(LTRIM(RTRIM(no_cp)), '') IS NOT NULL",
    );
    if ($statement === false) {
        throw new RuntimeException('Gagal membaca No CP resep lokal.');
    }

    $localMatches = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $productionNumber = trim((string) ($row['no_cp'] ?? ''));
        if (isset($matchingNumbers[$productionNumber])) {
            $localMatches[] = $productionNumber;
        }
    }
    return $localMatches;
}
