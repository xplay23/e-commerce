<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
$pdo = db();
if ((int) $pdo->query("SELECT GET_LOCK('nord_schema_migration', 30)")->fetchColumn() !== 1) throw new RuntimeException('Migration lock unavailable');
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version varchar(100) PRIMARY KEY, applied_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $version = '20261104_security';
    $query = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?');
    $query->execute([$version]);
    if (!$query->fetchColumn()) {
        $columns = [
            'users' => [
                'failed_login_attempts' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'locked_at' => 'timestamp NULL DEFAULT NULL',
                'email_verified_at' => 'timestamp NULL DEFAULT NULL',
            ],
            'auth_tokens' => ['last_seen_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ];
        foreach ($columns as $table => $definitions) {
            foreach ($definitions as $column => $definition) {
                $check = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
                $check->execute([$table, $column]);
                if (!$check->fetchColumn()) $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/security.sql'));
        // Existing users must verify ownership by email. Revoke legacy bearer sessions.
        $pdo->exec('DELETE FROM auth_tokens');
        $pdo->prepare('INSERT INTO schema_migrations(version) VALUES(?)')->execute([$version]);
        echo "Applied {$version}\n";
    } else {
        echo "Schema is current.\n";
    }
} finally {
    $pdo->query("SELECT RELEASE_LOCK('nord_schema_migration')");
}
