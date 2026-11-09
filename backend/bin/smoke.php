<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
if (env('ALLOW_SMOKE_TESTS') !== '1' || env('APP_ENV') === 'production') {
    fwrite(STDERR, "Smoke tests require ALLOW_SMOKE_TESTS=1 in a disposable non-production environment.\n");
    exit(1);
}
$base = rtrim(env('SMOKE_BASE_URL', 'http://web'), '/');
$mailpit = rtrim(env('MAILPIT_URL', 'http://mailpit:8025'), '/');
$suffix = bin2hex(random_bytes(8));
$email = "smoke-{$suffix}@example.test";
$adminEmail = "smoke-admin-{$suffix}@example.test";
$password = bin2hex(random_bytes(16));
$newPassword = bin2hex(random_bytes(16));
$slug = "smoke-{$suffix}";
$checks = 0;
$mailIds = [];

function check(bool $condition, string $description): void {
    global $checks;
    if (!$condition) throw new RuntimeException("Failed: {$description}");
    $checks++; echo "OK: {$description}\n";
}

final class SmokeClient {
    public array $cookies = [];
    public string $csrf = '';
    public array $lastHeaders = [];
    public function request(string $method, string $path, ?array $data = null, array $options = []): array {
        global $base;
        $mutation = !in_array($method, ['GET', 'HEAD'], true);
        if ($mutation && $this->csrf === '' && ($options['csrf'] ?? true)) $this->request('GET', '/api/auth/csrf');
        $headers = "Accept: application/json\r\nContent-Type: application/json\r\n";
        if ($mutation && ($options['csrf'] ?? true)) $headers .= "X-CSRF-Token: {$this->csrf}\r\n";
        if (isset($options['origin'])) $headers .= "Origin: {$options['origin']}\r\n";
        if ($this->cookies) $headers .= 'Cookie: ' . implode('; ', array_map(fn($key, $value) => "{$key}={$value}", array_keys($this->cookies), $this->cookies)) . "\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => $headers,
            'content' => $data === null ? '' : json_encode($data, JSON_THROW_ON_ERROR),
            'ignore_errors' => true, 'timeout' => 15,
        ]]);
        $body = file_get_contents($base . $path, false, $context);
        if ($body === false) throw new RuntimeException("Request failed: {$method} {$path}");
        $this->lastHeaders = http_get_last_response_headers() ?: [];
        foreach ($this->lastHeaders as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $cookie)) {
                if ($cookie[2] === '' || $cookie[2] === 'deleted') unset($this->cookies[$cookie[1]]);
                else $this->cookies[$cookie[1]] = $cookie[2];
            }
        }
        preg_match('/\s(\d{3})\s/', $this->lastHeaders[0] ?? '', $match);
        $payload = json_decode($body, true);
        if (is_array($payload) && isset($payload['csrf_token'])) $this->csrf = $payload['csrf_token'];
        return [(int) ($match[1] ?? 0), $payload, $body];
    }
}

function mailToken(string $email, string $subject): string {
    global $mailpit, $mailIds;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $result = json_decode((string) file_get_contents($mailpit . '/api/v1/search?query=' . rawurlencode('to:' . $email)), true);
        foreach ($result['messages'] ?? [] as $message) {
            if (isset($mailIds[$message['ID']])) continue;
            if (!str_contains(strtolower($message['Subject']), strtolower($subject))) continue;
            $mail = json_decode((string) file_get_contents($mailpit . '/api/v1/message/' . $message['ID']), true);
            $mailIds[$message['ID']] = true;
            if (preg_match('/#token=([a-f0-9]{64})/', $mail['Text'] ?? '', $match)) return $match[1];
        }
        usleep(200000);
    }
    throw new RuntimeException('Timed out waiting for local test email');
}

$public = new SmokeClient(); $customer = new SmokeClient(); $admin = new SmokeClient();
try {
    [$status, $health] = $public->request('GET', '/api/health');
    check($status === 200 && $health['status'] === 'ok', 'API connects to MySQL');
    if (env('SMOKE_SKIP_FRONTEND') !== '1') {
        [$status, , $html] = $public->request('GET', '/catalog');
        check($status === 200 && str_contains($html, '<div id="app">'), 'SPA deep link');
        check((bool) preg_match('/src="(\/assets\/[^\"]+\.js)"/', $html, $asset), 'Root asset URL');
        [$status] = $public->request('GET', $asset[1]);
        check($status === 200, 'Built JavaScript served');
        [$status] = $public->request('GET', '/.env.docker');
        check($status === 403 || $status === 404, 'Environment files inaccessible');
        $headers = strtolower(implode('\n', $public->lastHeaders));
        check(str_contains($headers, 'content-security-policy'), 'CSP header present');
    }
    [$status] = $public->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password], ['csrf' => false]);
    check($status === 403, 'Missing CSRF rejected');
    [$status] = $public->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password], ['origin' => 'https://evil.example']);
    check($status === 403, 'Cross-origin mutation rejected');

    [$status] = $customer->request('POST', '/api/auth/register', ['name' => 'Smoke Customer', 'email' => $email, 'password' => $password]);
    check($status === 202, 'Registration queues verification email');
    [$status] = $customer->request('POST', '/api/auth/register', ['name' => 'Duplicate', 'email' => $email, 'password' => $password]);
    check($status === 202, 'Duplicate registration uses generic response');
    [$status] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
    check($status === 403, 'Unverified account cannot sign in');
    $verification = mailToken($email, 'Verify');
    [$status] = $customer->request('POST', '/api/auth/verify-email', ['token' => $verification]);
    check($status === 200, 'Verification email link works');
    [$status] = $customer->request('POST', '/api/auth/verify-email', ['token' => $verification]);
    check($status === 422, 'Verification token cannot be reused');
    [$status, $auth] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
    check($status === 200 && isset($auth['user']) && !isset($auth['token']), 'Sign-in returns user without bearer token');
    $userId = $auth['user']['id'];
    $cookieHeaders = strtolower(implode('\n', $customer->lastHeaders));
    check(isset($customer->cookies['nord_session']) && str_contains($cookieHeaders, 'httponly') && str_contains($cookieHeaders, 'samesite=lax'), 'HttpOnly SameSite session cookie');
    $find = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $find->execute([$userId]);
    check(str_starts_with($find->fetchColumn(), '$argon2id$'), 'Password stored as Argon2id');
    [$status, $me] = $customer->request('GET', '/api/auth/me');
    check($status === 200 && $me['id'] === $userId, 'Cookie session accepted');
    [$status] = $customer->request('GET', '/api/admin/products');
    check($status === 403, 'Customer cannot access administration');

    db()->prepare('INSERT INTO users(name,email,password_hash,role,email_verified_at) VALUES(?,?,?,?,NOW())')
        ->execute(['Smoke Admin', $adminEmail, hashPassword($password), 'admin']);
    [$status, $auth] = $admin->request('POST', '/api/auth/login', ['email' => $adminEmail, 'password' => $password]);
    check($status === 202 && $auth['requires_email'] && !isset($admin->cookies['nord_session']), 'Administrator requires email confirmation');
    $adminConfirmation = mailToken($adminEmail, 'administrator');
    [$status] = $admin->request('POST', '/api/auth/confirm-login', ['token' => $adminConfirmation]);
    check($status === 200, 'Administrator email confirmation creates session');
    [$status] = $admin->request('POST', '/api/auth/confirm-login', ['token' => $adminConfirmation]);
    check($status === 422, 'Administrator confirmation single-use');
    $product = ['category_id' => null, 'name' => 'Smoke ' . $slug, 'slug' => $slug, 'description' => 'Temporary test',
        'short_description' => null, 'price' => 19.50, 'old_price' => null, 'stock' => 5, 'image_url' => null, 'is_active' => true, 'is_featured' => true];
    [$status, $created] = $admin->request('POST', '/api/admin/products', $product);
    check($status === 201 && isset($created['id']), 'Administrator creates product');
    $productId = $created['id'];
    [$status, $catalog] = $public->request('GET', '/api/products?search=' . $slug);
    check($status === 200 && $catalog['count'] === 1, 'Catalog search');
    [$status] = $customer->request('POST', '/api/favorites', ['product_id' => $productId]);
    check($status === 201, 'Add favorite');
    [$status, $favorites] = $customer->request('GET', '/api/favorites');
    check($status === 200 && $favorites[0]['product_id'] === $productId, 'Read favorite');
    [$status] = $customer->request('DELETE', '/api/favorites?product_id=' . $productId);
    check($status === 200, 'Remove favorite');
    $order = ['customer_name' => 'Smoke', 'customer_email' => $email, 'customer_phone' => '+380000000000', 'address' => 'Test address', 'city' => 'Kyiv',
        'items' => [['product_id' => $productId, 'quantity' => 2]], 'total' => 0.01];
    [$status] = $customer->request('POST', '/api/orders', $order);
    check($status === 201, 'Place order');
    [$status, $orders] = $customer->request('GET', '/api/orders');
    check($status === 200 && count($orders) === 1 && (float) $orders[0]['total'] === 39.0, 'Server calculates price');
    $invalid = $order; $invalid['items'][0]['quantity'] = 0;
    [$status] = $customer->request('POST', '/api/orders', $invalid);
    check($status === 422, 'Zero quantity rejected');
    $invalid['items'] = [['product_id' => $productId, 'quantity' => 2], ['product_id' => $productId, 'quantity' => 2]];
    [$status, , $body] = $customer->request('POST', '/api/orders', $invalid);
    check($status === 422 && !str_contains($body, 'SQLSTATE'), 'Duplicate stock checks aggregate quantities without leaking SQL');
    [$status, $detail] = $public->request('GET', '/api/products/' . $slug);
    check($status === 200 && $detail['stock'] === 3, 'Rejected orders preserve stock');

    // Exactly three consecutive wrong passwords: persistent account lock, not a browser timer.
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        [$status] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => 'incorrect']);
        check($status === 401, "Incorrect attempt {$attempt} rejected");
    }
    [$status] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
    check($status === 200, 'Correct password before third failure resets counter');
    $oldSession = clone $customer;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        [$status] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => 'incorrect']);
        check($status === 401, "Consecutive incorrect attempt {$attempt} rejected");
    }
    $find = db()->prepare('SELECT failed_login_attempts,locked_at FROM users WHERE id=?'); $find->execute([$userId]); $locked = $find->fetch();
    check((int) $locked['failed_login_attempts'] === 3 && $locked['locked_at'] !== null, 'Third failure persists account lock');
    [$status] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
    check($status === 401, 'Correct password cannot bypass lock');
    [$status] = $oldSession->request('GET', '/api/auth/me');
    check($status === 401, 'Lock revokes previous sessions');
    $reset = mailToken($email, 'Reset');
    $customer = new SmokeClient();
    [$status] = $customer->request('POST', '/api/auth/reset-password', ['token' => $reset, 'password' => $password]);
    check($status === 422, 'Reset requires a different password');
    [$status] = $customer->request('POST', '/api/auth/reset-password', ['token' => $reset, 'password' => $newPassword]);
    check($status === 200, 'Email reset changes password and unlocks account');
    [$status] = $customer->request('POST', '/api/auth/reset-password', ['token' => $reset, 'password' => $password]);
    check($status === 422, 'Reset token cannot be reused');
    [$status] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => $newPassword]);
    check($status === 200, 'New password signs in');
    $oldSession = clone $customer;
    [$status] = $customer->request('POST', '/api/auth/logout');
    check($status === 200, 'Logout');
    [$status] = $oldSession->request('GET', '/api/auth/me');
    check($status === 401, 'Logged-out cookie cannot be reused');
    [$status] = $public->request('POST', '/api/auth/forgot-password', ['email' => $email]);
    check($status === 202, 'Manual reset request queues email');
    $expiredReset = mailToken($email, 'Reset');
    db()->prepare('UPDATE auth_actions SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE user_id=? AND purpose=?')->execute([$userId, 'reset']);
    [$status] = $public->request('POST', '/api/auth/reset-password', ['token' => $expiredReset, 'password' => $password]);
    check($status === 422, 'Expired reset link rejected');
    [$status] = $customer->request('POST', '/api/auth/login', ['email' => $email, 'password' => $newPassword]);
    check($status === 200, 'Account stays unlocked after expired reset link');
    $oldSession = clone $customer;
    [$status] = $public->request('POST', '/api/auth/forgot-password', ['email' => $email]);
    check($status === 202, 'Replacement reset email requested');
    $replacement = mailToken($email, 'Reset');
    [$status] = $public->request('POST', '/api/auth/reset-password', ['token' => $replacement, 'password' => $password]);
    check($status === 200, 'Manual reset works for unlocked account');
    [$status] = $oldSession->request('GET', '/api/auth/me');
    check($status === 401, 'Manual password reset revokes all sessions');
    $find = db()->prepare('SELECT payload FROM email_outbox WHERE user_id=? ORDER BY id DESC LIMIT 1'); $find->execute([$userId]); $queued = $find->fetchColumn();
    // Worker may already have delivered; token lookup is solely through the local mailbox.
    if ($queued) check(!str_contains($queued, '#token='), 'Outbox payload encrypted');
    // Test the database-backed rate limiter using a unique test bucket only.
    rateLimit('smoke-' . $suffix, $email, 1, 60);
    try { rateLimit('smoke-' . $suffix, $email, 1, 60); check(false, 'Rate limit not enforced'); }
    catch (HttpError $exception) { check($exception->status === 429, 'Database rate limit enforced'); }

    $product['name'] = 'Updated Smoke';
    [$status] = $admin->request('PUT', '/api/admin/products/' . $productId, $product);
    check($status === 200, 'Administrator updates product');
    [$status] = $admin->request('DELETE', '/api/admin/products/' . $productId);
    check($status === 200, 'Administrator deletes product');
    echo "Passed {$checks} integration checks.\n";
} finally {
    db()->prepare('DELETE FROM orders WHERE customer_email=?')->execute([$email]);
    db()->prepare('DELETE FROM users WHERE email IN (?,?)')->execute([$email, $adminEmail]);
    db()->prepare('DELETE FROM products WHERE slug=?')->execute([$slug]);
    db()->prepare('DELETE FROM rate_limits WHERE bucket=?')->execute([hash('sha256', 'smoke-' . $suffix . '|' . $email)]);
    if ($mailIds) @file_get_contents($mailpit . '/api/v1/messages', false, stream_context_create(['http' => [
        'method' => 'DELETE', 'header' => "Content-Type: application/json\r\n", 'content' => json_encode(['IDs' => array_keys($mailIds)]), 'ignore_errors' => true, 'timeout' => 5,
    ]]));
    echo "Temporary test data removed.\n";
}
