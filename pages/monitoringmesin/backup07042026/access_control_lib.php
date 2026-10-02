<?php
declare(strict_types=1);

function mm_upload_access_storage_path(): string
{
    return __DIR__ . '/access_control_data.json';
}

function mm_upload_access_default_map(): array
{
    return [
        'global' => [],
    ];
}

function mm_upload_access_map(): array
{
    $map = mm_upload_access_default_map();
    $storagePath = mm_upload_access_storage_path();

    if (!is_file($storagePath)) {
        return $map;
    }

    $decoded = json_decode((string) file_get_contents($storagePath), true);
    if (!is_array($decoded)) {
        return $map;
    }

    $values = $decoded['global'] ?? [];
    if (!is_array($values)) {
        return $map;
    }

    $normalized = [];
    foreach ($values as $value) {
        $userId = trim((string) $value);
        if ($userId !== '') {
            $normalized[] = $userId;
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);
    $map['global'] = $normalized;

    return $map;
}

function mm_save_upload_access_map(array $map): void
{
    $normalized = array_values(array_unique(array_map(
        static fn($value): string => trim((string) $value),
        array_filter((array) ($map['global'] ?? []), static fn($value): bool => trim((string) $value) !== '')
    )));
    sort($normalized);

    $json = json_encode(['global' => $normalized], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Gagal mengubah data akses upload ke format JSON.');
    }

    $result = file_put_contents(mm_upload_access_storage_path(), $json . PHP_EOL, LOCK_EX);
    if ($result === false) {
        throw new RuntimeException('Gagal menyimpan data akses upload.');
    }
}

function mm_allowed_upload_user_ids(string $area = 'global'): array
{
    $map = mm_upload_access_map();
    return $map['global'] ?? [];
}

function mm_can_access_upload_area(string $area, string $userId): bool
{
    $candidates = [];

    foreach ([
        $userId,
        $_SESSION['UserId'] ?? '',
        $_SESSION['UserName'] ?? '',
    ] as $value) {
        $normalizedValue = strtolower(trim((string) $value));
        if ($normalizedValue !== '') {
            $candidates[] = $normalizedValue;
        }
    }

    $candidates = array_values(array_unique($candidates));
    if ($candidates === []) {
        return false;
    }

    $allowedUserIds = mm_allowed_upload_user_ids();
    $allowedLookup = array_map(static fn(string $value): string => strtolower(trim($value)), $allowedUserIds);

    foreach ($candidates as $candidate) {
        if (in_array($candidate, $allowedLookup, true)) {
            return true;
        }
    }

    return false;
}

function mm_add_upload_access_user(string $userName): void
{
    $userName = trim($userName);
    if ($userName === '') {
        throw new RuntimeException('UserName wajib diisi.');
    }

    $map = mm_upload_access_map();
    $map['global'][] = $userName;
    mm_save_upload_access_map($map);
}

function mm_remove_upload_access_user(string $userName): void
{
    $userName = trim($userName);
    $map = mm_upload_access_map();
    $map['global'] = array_values(array_filter(
        $map['global'] ?? [],
        static fn(string $existingUserName): bool => strtolower($existingUserName) !== strtolower($userName)
    ));
    mm_save_upload_access_map($map);
}
