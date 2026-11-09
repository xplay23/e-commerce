<?php

declare(strict_types=1);

function sealMail(array $payload): string
{
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), 'aes-256-gcm', appKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) throw new RuntimeException('Mail encryption failed');
    return base64_encode($iv . $tag . $encrypted);
}

function openMail(string $sealed): array
{
    $raw = base64_decode($sealed, true);
    if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Invalid mail payload');
    $json = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', appKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($json === false) throw new RuntimeException('Mail authentication failed');
    return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
}

function queueAction(array $user, string $purpose): void
{
    $token = bin2hex(random_bytes(32));
    $minutes = $purpose === 'verify' ? 60 : ($purpose === 'admin_login' ? 10 : 20);
    db()->prepare('DELETE FROM auth_actions WHERE user_id=? AND purpose=?')->execute([$user['id'], $purpose]);
    db()->prepare('INSERT INTO auth_actions(user_id,token_hash,purpose,expires_at) VALUES(?,?,?,?)')
        ->execute([$user['id'], hash('sha256', $token), $purpose, gmdate('Y-m-d H:i:s', time() + $minutes * 60)]);
    $route = ['reset' => 'reset-password', 'verify' => 'verify-email', 'admin_login' => 'confirm-login'][$purpose];
    $subject = ['reset' => 'Reset your NORD password', 'verify' => 'Verify your NORD email', 'admin_login' => 'Confirm your NORD administrator sign-in'][$purpose];
    // Fragment tokens are never sent in HTTP URLs, access logs or Referer headers.
    $link = appUrl() . '/' . $route . '#token=' . $token;
    $body = "{$subject}\n\nOpen this link:\n{$link}\n\nThis single-use link expires in {$minutes} minutes.\n"
        . ($purpose === 'reset' ? "After three incorrect passwords, sign-in is blocked until you reset your password.\n" : '')
        . "If you did not request this, do not share the link.\n";
    $payload = sealMail(['to' => $user['email'], 'subject' => $subject, 'body' => $body]);
    db()->prepare('INSERT INTO email_outbox(user_id,payload) VALUES(?,?)')->execute([$user['id'], $payload]);
}

function sendMailPayload(array $payload): void
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = env('SMTP_HOST', '');
    $mailer->Port = (int) env('SMTP_PORT', '587');
    $encryption = env('SMTP_ENCRYPTION', 'tls');
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) throw new RuntimeException('Invalid SMTP encryption setting');
    if ($encryption === 'none' && env('APP_ENV') === 'production') throw new RuntimeException('Production SMTP requires TLS');
    $mailer->SMTPSecure = $encryption === 'none' ? '' : $encryption;
    $mailer->SMTPAutoTLS = $encryption !== 'none';
    $mailer->SMTPAuth = env('SMTP_USERNAME', '') !== '';
    $mailer->Username = env('SMTP_USERNAME', '');
    $mailer->Password = env('SMTP_PASSWORD', '');
    $mailer->Timeout = 10;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom(env('MAIL_FROM', 'noreply@nord.test'), 'NORD');
    $mailer->addAddress($payload['to']);
    $mailer->Subject = $payload['subject'];
    $mailer->Body = $payload['body'];
    $mailer->send();
}
