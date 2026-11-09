<?php

declare(strict_types=1);

function authActionUser(string $token, string $purpose): array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new HttpError('This link is invalid or expired. Request a new email.', 422);
    $pdo = db();
    $hash = hash('sha256', $token);
    $find = $pdo->prepare('SELECT user_id FROM auth_actions WHERE token_hash=? AND purpose=? AND expires_at>NOW()');
    $find->execute([$hash, $purpose]);
    $id = $find->fetchColumn();
    if (!$id) throw new HttpError('This link is invalid or expired. Request a new email.', 422);
    // All account mutations acquire the same user lock before changing action tokens.
    $find = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
    $find->execute([$id]);
    $user = $find->fetch();
    $find = $pdo->prepare('SELECT id FROM auth_actions WHERE token_hash=? AND purpose=? AND expires_at>NOW() FOR UPDATE');
    $find->execute([$hash, $purpose]);
    $action = $find->fetchColumn();
    if (!$user || !$action) throw new HttpError('This link is invalid or expired. Request a new email.', 422);
    $pdo->prepare('DELETE FROM auth_actions WHERE id=?')->execute([$action]);
    return $user;
}

function handleAuth(string $method, string $path): void
{
    if ($method === 'GET' && $path === 'auth/csrf') {
        jsonResponse(['csrf_token' => csrfToken()]);
    }
    if ($method === 'GET' && $path === 'auth/me') jsonResponse(currentUser(true));
    if ($method !== 'POST' || !str_starts_with($path, 'auth/')) return;
    rateLimit('auth-ip', clientIp(), 40, 900);
    $pdo = db();

    if ($path === 'auth/register') {
        rateLimit('register-ip', clientIp(), 5, 3600);
        $data = input();
        $email = emailField($data);
        $name = textField($data, 'name', 1, 120);
        $password = passwordField($data);
        $hash = hashPassword($password);
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT id FROM users WHERE email=? FOR UPDATE');
        $find->execute([$email]);
        if (!$find->fetchColumn()) {
            try {
                $pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)')->execute([$name, $email, $hash]);
                queueAction(['id' => $pdo->lastInsertId(), 'email' => $email], 'verify');
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) throw $exception;
            }
        }
        $pdo->commit();
        jsonResponse(['message' => 'If this address can be registered, a verification email will arrive shortly. Otherwise sign in or reset your password.'], 202);
    }

    if ($path === 'auth/login') {
        rateLimit('login-ip', clientIp(), 20, 900);
        $data = input();
        $email = emailField($data);
        $password = $data['password'] ?? '';
        if (!is_string($password) || strlen($password) > 1024 || str_contains($password, "\0")) throw new HttpError('Invalid credentials', 401);
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT * FROM users WHERE email=? FOR UPDATE');
        $find->execute([$email]);
        $user = $find->fetch();
        if (!$user) {
            // Equal-cost work for unknown addresses; do not disclose whether an account exists.
            hashPassword($password);
        }
        $valid = $user && password_verify($password, $user['password_hash']);
        if (!$valid || $user['locked_at'] !== null) {
            if ($user && $user['locked_at'] === null) {
                $failed = min(3, (int) $user['failed_login_attempts'] + 1);
                $pdo->prepare('UPDATE users SET failed_login_attempts=?,locked_at=IF(?=3,NOW(),NULL) WHERE id=?')->execute([$failed, $failed, $user['id']]);
                if ($failed === 3) {
                    $pdo->prepare('DELETE FROM auth_tokens WHERE user_id=?')->execute([$user['id']]);
                    $pdo->prepare('DELETE FROM auth_actions WHERE user_id=?')->execute([$user['id']]);
                    queueAction($user, 'reset');
                }
            }
            $pdo->commit();
            throw new HttpError('Unable to sign in. Check your details; after three incorrect passwords, reset your password by email.', 401);
        }
        if ($user['email_verified_at'] === null) {
            $pdo->commit();
            throw new HttpError('Verify your email before signing in. You can request another verification email.', 403);
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID, passwordOptions())) {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([hashPassword($password), $user['id']]);
        }
        $pdo->prepare('UPDATE users SET failed_login_attempts=0 WHERE id=?')->execute([$user['id']]);
        if ($user['role'] === 'admin') {
            rateLimit('admin-mail', (string) $user['id'], 3, 900);
            queueAction($user, 'admin_login');
            $pdo->commit();
            jsonResponse(['requires_email' => true, 'message' => 'Confirm this administrator sign-in using the link sent to your email.'], 202);
        }
        $result = startSession($user);
        $pdo->commit();
        jsonResponse($result);
    }

    if (in_array($path, ['auth/forgot-password', 'auth/resend-verification'], true)) {
        rateLimit('recovery-ip', clientIp(), 5, 900);
        $data = input();
        $email = emailField($data);
        rateLimit('recovery-email', $email, 3, 3600);
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT * FROM users WHERE email=? FOR UPDATE');
        $find->execute([$email]);
        $user = $find->fetch();
        if ($user && ($path === 'auth/forgot-password' || $user['email_verified_at'] === null)) {
            queueAction($user, $path === 'auth/forgot-password' ? 'reset' : 'verify');
        }
        $pdo->commit();
        jsonResponse(['message' => 'If this address matches an eligible account, an email will arrive shortly.'], 202);
    }

    if (in_array($path, ['auth/reset-password', 'auth/verify-email', 'auth/confirm-login'], true)) {
        rateLimit('action-ip', clientIp(), 15, 900);
        $data = input();
        $token = textField($data, 'token', 64, 64);
        $purpose = ['auth/reset-password' => 'reset', 'auth/verify-email' => 'verify', 'auth/confirm-login' => 'admin_login'][$path];
        $password = $purpose === 'reset' ? passwordField($data) : null;
        $hash = $password === null ? null : hashPassword($password);
        $pdo->beginTransaction();
        $user = authActionUser($token, $purpose);
        if ($purpose === 'reset') {
            if (password_verify($password, $user['password_hash'])) throw new HttpError('Choose a password different from your current password.', 422);
            $pdo->prepare('UPDATE users SET password_hash=?,failed_login_attempts=0,locked_at=NULL,email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?')->execute([$hash, $user['id']]);
            $pdo->prepare('DELETE FROM auth_tokens WHERE user_id=?')->execute([$user['id']]);
            $pdo->prepare('DELETE FROM auth_actions WHERE user_id=?')->execute([$user['id']]);
            $pdo->commit();
            cookieValue('nord_session', '', time() - 3600);
            jsonResponse(['message' => 'Password changed. Your account is unlocked. Sign in with the new password.', 'csrf_token' => csrfToken(true)]);
        }
        if ($purpose === 'verify') {
            $pdo->prepare('UPDATE users SET email_verified_at=NOW() WHERE id=?')->execute([$user['id']]);
            $pdo->commit();
            jsonResponse(['message' => 'Email verified. You can now sign in.']);
        }
        if ($user['locked_at'] !== null || $user['email_verified_at'] === null || $user['role'] !== 'admin') {
            throw new HttpError('Sign-in is unavailable. Reset your password if the account is locked.', 401);
        }
        $result = startSession($user);
        $pdo->commit();
        jsonResponse($result);
    }

    if ($path === 'auth/logout') {
        currentUser(true);
        $pdo->prepare('DELETE FROM auth_tokens WHERE token_hash=?')->execute([hash('sha256', $_COOKIE['nord_session'])]);
        cookieValue('nord_session', '', time() - 3600);
        jsonResponse(['message' => 'Logged out', 'csrf_token' => csrfToken(true)]);
    }
}
