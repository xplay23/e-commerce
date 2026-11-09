<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
putenv('APP_KEY=' . base64_encode(random_bytes(32)));
putenv('APP_ENV=development');
putenv('APP_URL=http://localhost:8080');
ob_start();
$checks = 0;
function assertSecurity(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function rejected(callable $action, int $status): bool {
    try { $action(); return false; } catch (HttpError $error) { return $error->status === $status; }
}
$password = 'A unique long 🛒 passphrase';
$hash = hashPassword(passwordField(['password' => $password]));
assertSecurity(str_starts_with($hash, '$argon2id$') && password_verify($password, $hash), 'Argon2id Unicode password');
assertSecurity(!password_verify($password . 'x', $hash), 'Wrong password rejected');
assertSecurity(rejected(fn() => passwordField(['password' => 'short']), 422), 'Short password rejected');
assertSecurity(rejected(fn() => passwordField(['password' => str_repeat('x', 20)]), 422), 'Repeated password rejected');
assertSecurity(rejected(fn() => passwordField(['password' => ['invalid']]), 422), 'Non-string password rejected');
assertSecurity(rejected(fn() => textField(['name' => str_repeat('я', 121)], 'name', 1, 120), 422), 'Unicode field length enforced');
assertSecurity(rejected(fn() => integerValue(-1, 1, 9999), 422), 'Negative quantity rejected');
assertSecurity(rejected(fn() => integerValue(1.5, 1, 9999), 422), 'Fractional quantity rejected');
assertSecurity(rejected(fn() => integerValue('1e3', 1, 9999), 422), 'Exponential quantity rejected');
assertSecurity(rejected(fn() => moneyField('NaN'), 422), 'NaN price rejected');
assertSecurity(rejected(fn() => moneyField('1.999'), 422), 'Fractional cents rejected');
assertSecurity(moneyCents('19.50') === 1950, 'Integer cent calculation');
$items = orderItems([['product_id' => '2', 'quantity' => 2], ['product_id' => '2', 'quantity' => 3]]);
assertSecurity($items === [2 => 5], 'Duplicate products aggregated');
assertSecurity(rejected(fn() => orderItems([['product_id' => '2', 'quantity' => 0]]), 422), 'Zero quantity rejected');
$mail = ['to' => 'test@example.test', 'body' => 'token=not-public'];
$sealed = sealMail($mail);
assertSecurity(openMail($sealed) === $mail && !str_contains($sealed, $mail['body']), 'Mail queue authenticated encryption');
$raw = base64_decode($sealed); $raw[30] = chr(ord($raw[30]) ^ 1);
$tamperRejected = false;
try { openMail(base64_encode($raw)); } catch (RuntimeException) { $tamperRejected = true; }
assertSecurity($tamperRejected, 'Tampered mail rejected');
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['CONTENT_TYPE'] = 'application/json';
$_COOKIE = []; $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
$csrf = csrfToken(); $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;
protectMutation(); assertSecurity(true, 'Valid CSRF accepted');
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'forged';
assertSecurity(rejected(fn() => protectMutation(), 403), 'Forged CSRF rejected');
$_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf; $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
assertSecurity(rejected(fn() => protectMutation(), 403), 'Foreign origin rejected');
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080'; $_COOKIE['nord_session'] = bin2hex(random_bytes(32));
assertSecurity(rejected(fn() => protectMutation(), 403), 'CSRF bound to session');
putenv('APP_ENV=production');
assertSecurity(rejected(fn() => throw new HttpError('test', 403), 403), 'Typed application errors');
$httpRejected = false;
try { appUrl(); } catch (RuntimeException) { $httpRejected = true; }
assertSecurity($httpRejected, 'HTTP production rejected');
ob_end_clean();
echo "Passed {$checks} security unit checks.\n";
