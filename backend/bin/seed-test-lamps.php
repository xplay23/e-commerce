<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';
$dataset = require dirname(__DIR__) . '/database/test-lamps.php';
$dryRun = in_array('--dry-run', $argv, true);
$pdo = db();
$lock = $pdo->query("SELECT GET_LOCK('nord-seed-test-lamps', 10)")->fetchColumn();
if ((int) $lock !== 1) throw new RuntimeException('Another lamp seed is running.');

try {
    $pdo->beginTransaction();
    $findCategory = $pdo->prepare('SELECT id FROM categories WHERE slug=?');
    $insertCategory = $pdo->prepare('INSERT INTO categories(name,slug,description) VALUES(?,?,?)');
    $findProduct = $pdo->prepare('SELECT id FROM products WHERE slug=?');
    $insertProduct = $pdo->prepare('INSERT INTO products(category_id,name,slug,description,short_description,price,old_price,stock,image_url,is_active,is_featured) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $categoryIds = [];
    $categoriesAdded = $added = $skipped = 0;
    foreach ($dataset['categories'] as $category) {
        $findCategory->execute([$category['slug']]);
        $id = $findCategory->fetchColumn();
        if ($id === false) {
            if (!$dryRun) {
                $insertCategory->execute([$category['name'], $category['slug'], $category['description']]);
                $id = $pdo->lastInsertId();
            }
            $categoriesAdded++;
        }
        $categoryIds[$category['slug']] = $id;
    }
    foreach ($dataset['products'] as $product) {
        $findProduct->execute([$product['slug']]);
        if ($findProduct->fetchColumn() !== false) {
            $skipped++;
            continue;
        }
        if (!$dryRun) {
            $insertProduct->execute([
                $categoryIds[$product['category_slug']], $product['name'], $product['slug'],
                $product['description'], $product['short_description'], $product['price'],
                $product['old_price'], $product['stock'], $product['image_url'],
                (int) $product['is_active'], (int) $product['is_featured'],
            ]);
        }
        $added++;
    }
    if ($dryRun) $pdo->rollBack();
    else $pdo->commit();
    echo json_encode([
        'dry_run' => $dryRun, 'products_added' => $added,
        'products_skipped' => $skipped, 'categories_added' => $categoriesAdded,
        'total_products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
} finally {
    $pdo->query("SELECT RELEASE_LOCK('nord-seed-test-lamps')");
}
