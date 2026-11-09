<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$email = strtolower(trim($argv[1] ?? ''));
$name = trim($argv[2] ?? 'NORD Admin');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($name) > 120) {
    fwrite(STDERR, "Usage: php bin/create-admin.php admin@example.com [name]\n");
    exit(1);
}

$password = getenv('ADMIN_PASSWORD');
if ($password === false) {
    $interactive = stream_isatty(STDIN);
    fwrite(STDERR, "Administrator password (8–128 characters): ");
    if ($interactive) system('stty -echo');
    try {
        $password = rtrim((string) fgets(STDIN), "\r\n");
    } finally {
        if ($interactive) system('stty echo');
        fwrite(STDERR, "\n");
    }
}
try {
    passwordField(['password' => $password]);
} catch (HttpError $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

try {
    $statement = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $statement->execute([$name, $email, hashPassword($password), 'admin']);
    $adminId = db()->lastInsertId();
    queueAction(['id' => $adminId, 'email' => $email], 'verify');
    echo "Administrator created: {$email}\n";
} catch (PDOException $exception) {
    if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) throw $exception;
    fwrite(STDERR, "This email already exists. Existing users are never changed by this command.\n");
    exit(1);
}
