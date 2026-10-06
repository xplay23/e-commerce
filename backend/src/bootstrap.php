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
    return $pdo;
}

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['message' => 'Invalid JSON body'], 400);
    }
    return $data;
}

function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) ? $matches[1] : null;
}

function currentUser(bool $required = false): ?array
{
    $token = bearerToken();
    if ($token === null) {
        if ($required) jsonResponse(['message' => 'Authentication required'], 401);
        return null;
    }

    $statement = db()->prepare(
        'SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at
         FROM auth_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > NOW() LIMIT 1',
    );
    $statement->execute([hash('sha256', $token)]);
    $user = $statement->fetch();
    if (!$user && $required) jsonResponse(['message' => 'Session expired'], 401);
    if ($user) $user['id'] = (string) $user['id'];
    return $user ?: null;
}

function requireAdmin(): array
{
    $user = currentUser(true);
    if (($user['role'] ?? null) !== 'admin') {
        jsonResponse(['message' => 'Administrator access required'], 403);
    }
    return $user;
}

function issueToken(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $days = max(1, (int) env('TOKEN_TTL_DAYS', '30'));
    $expiresAt = (new DateTimeImmutable())->modify("+{$days} days")->format('Y-m-d H:i:s');
    $statement = db()->prepare(
        'INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
    );
    $statement->execute([$userId, hash('sha256', $token), $expiresAt]);
    return $token;
}

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
    $required = ['name', 'slug', 'description', 'price', 'stock'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            jsonResponse(['message' => "Field '{$field}' is required"], 422);
        }
    }
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $data['slug'])) {
        jsonResponse(['message' => 'Slug may contain lowercase letters, numbers and hyphens'], 422);
    }
    if ((float) $data['price'] < 0 || (int) $data['stock'] < 0) {
        jsonResponse(['message' => 'Price and stock cannot be negative'], 422);
    }
    return $data;
}
