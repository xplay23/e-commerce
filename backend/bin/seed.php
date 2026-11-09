<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if (db()->query('SELECT COUNT(*) FROM products')->fetchColumn() > 0) {
    fwrite(STDERR, "Catalog is not empty. Demo seed refused to protect existing products.\n");
    exit(1);
}

db()->exec(file_get_contents(dirname(__DIR__) . '/database/seed.sql'));
echo "Demo catalog created. No administrator or default password was added.\n";
