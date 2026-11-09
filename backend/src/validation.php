<?php

declare(strict_types=1);

function textField(array $data, string $key, int $min, int $max): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value) || !preg_match('//u', $value) || str_contains($value, "\0")) {
        throw new HttpError("Invalid {$key}", 422);
    }
    $value = trim($value);
    $length = preg_match_all('/./us', $value);
    if ($length < $min || $length > $max) throw new HttpError("Invalid length for {$key}", 422);
    return $value;
}

function emailField(array $data): string
{
    $email = strtolower(textField($data, 'email', 1, 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpError('Provide a valid email address', 422);
    return $email;
}

function passwordField(array $data): string
{
    $password = $data['password'] ?? null;
    if (!is_string($password) || strlen($password) > 1024 || str_contains($password, "\0") || !preg_match('//u', $password)) {
        throw new HttpError('Invalid password', 422);
    }
    $length = preg_match_all('/./us', $password);
    if ($length < 8 || $length > 128 || preg_match('/^(.)\1+$/us', $password)
        || in_array(strtolower($password), ['password123456789', '123456789012345', 'qwertyuiopasdfgh', 'correcthorsebatterystaple'], true)) {
        throw new HttpError('Use 8–128 characters and avoid common passwords', 422);
    }
    return $password;
}

function hashPassword(string $password): string
{
    if (!defined('PASSWORD_ARGON2ID')) throw new RuntimeException('Argon2id support is required');
    return password_hash($password, PASSWORD_ARGON2ID, passwordOptions());
}

function passwordOptions(): array
{
    return ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];
}

function positiveId(mixed $value): string
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $value)) {
        throw new HttpError('Invalid identifier', 422);
    }
    return (string) $value;
}

function integerValue(mixed $value, int $min, int $max): int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+$/D', (string) $value)
        || strlen((string) $value) > 10 || (int) $value < $min || (int) $value > $max) {
        throw new HttpError('Invalid integer value', 422);
    }
    return (int) $value;
}

function moneyField(mixed $value): string
{
    if ((!is_string($value) && !is_int($value) && !is_float($value))
        || !preg_match('/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D', (string) $value)) {
        throw new HttpError('Invalid price: use a non-negative amount with at most two decimals', 422);
    }
    return number_format((float) $value, 2, '.', '');
}

function moneyCents(string $amount): int
{
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
}

function orderItems(mixed $items): array
{
    if (!is_array($items) || !array_is_list($items) || count($items) === 0 || count($items) > 100) {
        throw new HttpError('Cart must contain between 1 and 100 items', 422);
    }
    $quantities = [];
    foreach ($items as $item) {
        if (!is_array($item)) throw new HttpError('Invalid cart item', 422);
        $id = positiveId($item['product_id'] ?? null);
        $quantities[$id] = ($quantities[$id] ?? 0) + integerValue($item['quantity'] ?? null, 1, 9999);
        if ($quantities[$id] > 9999) throw new HttpError('Quantity is too large', 422);
    }
    ksort($quantities, SORT_NUMERIC); // Consistent row lock order prevents cart deadlocks.
    return $quantities;
}
