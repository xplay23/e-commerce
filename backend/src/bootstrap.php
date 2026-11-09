<?php

declare(strict_types=1);

function loadEnv(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'nord_store'),
    );
    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    if (strlen($raw) > 65536) throw new HttpError('Request body is too large', 413);
    $data = json_decode($raw, true, 32);
    if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) {
        throw new HttpError('Invalid JSON object', 400);
    }
    return $data;
}

require __DIR__ . '/security.php';
require __DIR__ . '/validation.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/auth.php';

function productFromRow(array $row): array
{
    $row['id'] = (string) $row['id'];
    $row['category_id'] = $row['category_id'] === null ? null : (string) $row['category_id'];
    $row['price'] = (float) $row['price'];
    $row['old_price'] = $row['old_price'] === null ? null : (float) $row['old_price'];
    $row['stock'] = (int) $row['stock'];
    $row['is_active'] = (bool) $row['is_active'];
    $row['is_featured'] = (bool) $row['is_featured'];
    if (isset($row['category_name'])) {
        $row['category'] = $row['category_id'] === null ? null : [
            'id' => $row['category_id'],
            'name' => $row['category_name'],
            'slug' => $row['category_slug'],
            'description' => null,
            'image_url' => null,
            'created_at' => $row['created_at'],
        ];
        unset($row['category_name'], $row['category_slug']);
    }
    $row['images'] = [];
    return $row;
}

function validateProduct(array $data): array
{
    $slug = textField($data, 'slug', 1, 200);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) throw new HttpError('Invalid product slug', 422);
    $image = textField($data, 'image_url', 0, 2048);
    if ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || parse_url($image, PHP_URL_SCHEME) !== 'https')) {
        throw new HttpError('Image URL must use HTTPS', 422);
    }
    foreach (['is_active', 'is_featured'] as $flag) {
        if (isset($data[$flag]) && !is_bool($data[$flag])) throw new HttpError("Invalid {$flag}", 422);
    }
    return [
        'name' => textField($data, 'name', 1, 180), 'slug' => $slug,
        'description' => textField($data, 'description', 1, 20000),
        'short_description' => textField($data, 'short_description', 0, 1000) ?: null,
        'category_id' => empty($data['category_id']) ? null : positiveId($data['category_id']),
        'price' => moneyField($data['price'] ?? null),
        'old_price' => ($data['old_price'] ?? '') === '' || $data['old_price'] === null ? null : moneyField($data['old_price']),
        'stock' => integerValue($data['stock'] ?? null, 0, 2147483647),
        'image_url' => $image ?: null,
        'is_active' => $data['is_active'] ?? false, 'is_featured' => $data['is_featured'] ?? false,
    ];
}
