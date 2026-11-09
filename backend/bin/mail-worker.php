<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
appKey();
appUrl();
if (in_array('--retry', $argv, true)) {
    db()->exec('UPDATE email_outbox SET attempts=0,available_at=NOW() WHERE attempts>=6');
}
$once = in_array('--once', $argv, true);
$cycles = 0;
do {
    $pdo = db();
    $pdo->beginTransaction();
    $row = $pdo->query('SELECT id,payload,attempts FROM email_outbox WHERE available_at<=NOW() AND attempts<6 ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED')->fetch();
    if ($row) {
        try {
            sendMailPayload(openMail($row['payload']));
            $pdo->prepare('DELETE FROM email_outbox WHERE id=?')->execute([$row['id']]);
            $pdo->commit();
            echo "Mail job {$row['id']} delivered.\n";
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $delay = min(3600, 30 * (2 ** (int) $row['attempts']));
            $pdo->prepare('UPDATE email_outbox SET attempts=attempts+1,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id=?')->execute([$delay, $row['id']]);
            // Do not log recipient, SMTP credentials, body, reset link or exception arguments.
            error_log("Mail job {$row['id']} failed (" . get_class($exception) . '); retry scheduled.');
            if ($once) exit(1);
        }
    } else {
        $pdo->commit();
    }
    if (++$cycles % 30 === 0) {
        $pdo->exec('DELETE FROM auth_actions WHERE expires_at<NOW() LIMIT 1000');
        $pdo->exec('DELETE FROM auth_tokens WHERE expires_at<NOW() LIMIT 1000');
        $pdo->exec('DELETE FROM rate_limits WHERE reset_at<NOW() LIMIT 1000');
        $pdo->exec('DELETE FROM email_outbox WHERE created_at<DATE_SUB(NOW(),INTERVAL 1 DAY) LIMIT 1000');
    }
    if (!$once && !$row) sleep(2);
} while (!$once || $row);
