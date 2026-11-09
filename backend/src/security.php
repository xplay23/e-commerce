<?php

declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

function appKey(): string
{
    $key = base64_decode(env('APP_KEY', ''), true);
    if ($key === false || strlen($key) !== 32) throw new RuntimeException('Configure a 32-byte APP_KEY');
    return $key;
}

function appUrl(): string
{
    $url = rtrim(env('APP_URL', 'http://localhost:8080'), '/');
    $parts = parse_url($url);
    if (!$parts || !isset($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass']) || !empty($parts['query']) || !empty($parts['fragment'])
        || !in_array($parts['path'] ?? '', ['', '/'], true)) {
        throw new RuntimeException('Invalid APP_URL');
    }
    if (env('APP_ENV') === 'production' && $parts['scheme'] !== 'https') throw new RuntimeException('Production requires HTTPS APP_URL');
    return $url;
}

function cookieValue(string $name, string $value, int $expires): void
{
    setcookie($name, $value, [
        'expires' => $expires, 'path' => '/api',
        'secure' => parse_url(appUrl(), PHP_URL_SCHEME) === 'https',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = $value;
}

function csrfToken(bool $rotate = false): string
{
    $context = $_COOKIE['nord_csrf_context'] ?? '';
    if ($rotate || !preg_match('/^[a-f0-9]{64}$/D', $context)) {
        $context = bin2hex(random_bytes(32));
        cookieValue('nord_csrf_context', $context, time() + 43200);
    }
    return hash_hmac('sha256', $context . '|' . ($_COOKIE['nord_session'] ?? ''), appKey());
}

function protectMutation(): void
{
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) return;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_unique([appUrl(), rtrim(env('FRONTEND_URL', appUrl()), '/')]);
    if (($origin !== '' && !in_array($origin, $allowed, true)) || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        throw new HttpError('Request origin is not allowed', 403);
    }
    $contentType = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);
    if ($contentType !== 'application/json') throw new HttpError('Use application/json', 415);
    if (!isset($_COOKIE['nord_csrf_context']) || !hash_equals(csrfToken(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        throw new HttpError('Security token expired. Reload the page and try again.', 403);
    }
}

function currentUser(bool $required = false): ?array
{
    $token = $_COOKIE['nord_session'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        if ($required) throw new HttpError('Authentication required', 401);
        return null;
    }
    $statement = db()->prepare(
        'SELECT u.id,u.name,u.email,u.phone,u.role,u.created_at FROM auth_tokens t
         JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.expires_at>NOW()
         AND t.last_seen_at>DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         AND u.locked_at IS NULL AND u.email_verified_at IS NOT NULL LIMIT 1'
    );
    $hash = hash('sha256', $token);
    $statement->execute([$hash]);
    $user = $statement->fetch();
    if (!$user) {
        cookieValue('nord_session', '', time() - 3600);
        if ($required) throw new HttpError('Session expired. Sign in again.', 401);
        return null;
    }
    db()->prepare('UPDATE auth_tokens SET last_seen_at=NOW() WHERE token_hash=?')->execute([$hash]);
    $user['id'] = (string) $user['id'];
    return $user;
}

function requireAdmin(): array
{
    $user = currentUser(true);
    if ($user['role'] !== 'admin') throw new HttpError('Administrator access required', 403);
    return $user;
}

function startSession(array $user): array
{
    $token = bin2hex(random_bytes(32));
    $seconds = $user['role'] === 'admin' ? 1800 : 43200;
    db()->prepare('DELETE FROM auth_tokens WHERE user_id=? AND (expires_at<NOW() OR last_seen_at<DATE_SUB(NOW(),INTERVAL 30 MINUTE))')->execute([$user['id']]);
    db()->prepare('INSERT INTO auth_tokens(user_id,token_hash,expires_at,last_seen_at) VALUES(?,?,?,NOW())')
        ->execute([$user['id'], hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + $seconds)]);
    cookieValue('nord_session', $token, time() + $seconds);
    return ['user' => publicUser($user), 'csrf_token' => csrfToken(true)];
}

function publicUser(array $user): array
{
    $result = array_intersect_key($user, array_flip(['id', 'name', 'email', 'phone', 'role', 'created_at']));
    $result['id'] = (string) $result['id'];
    return $result;
}

function clientIp(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // Only the explicitly trusted Caddy container may supply a forwarded client IP.
    if (env('TRUSTED_PROXY_HOST') === 'caddy' && in_array($remote, gethostbynamel('caddy') ?: [], true)) {
        $forwarded = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        $candidate = end($forwarded);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
    }
    return $remote;
}

function rateLimit(string $scope, string $identity, int $limit, int $seconds): void
{
    $key = hash('sha256', $scope . '|' . $identity);
    $statement = db()->prepare('INSERT INTO rate_limits(bucket,attempts,reset_at) VALUES(?,1,DATE_ADD(NOW(),INTERVAL ? SECOND))
        ON DUPLICATE KEY UPDATE attempts=IF(reset_at<=NOW(),1,attempts+1),reset_at=IF(reset_at<=NOW(),DATE_ADD(NOW(),INTERVAL ? SECOND),reset_at)');
    $statement->execute([$key, $seconds, $seconds]);
    $statement = db()->prepare('SELECT attempts,GREATEST(1,TIMESTAMPDIFF(SECOND,NOW(),reset_at)) retry_after FROM rate_limits WHERE bucket=?');
    $statement->execute([$key]);
    $row = $statement->fetch();
    if ((int) $row['attempts'] > $limit) {
        if (!headers_sent()) header('Retry-After: ' . $row['retry_after']);
        throw new HttpError('Too many requests. Please try again later.', 429);
    }
}
