<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = env('FRONTEND_URL', 'http://localhost:5173');
if ($origin === $allowedOrigin) {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header('Access-Control-Allow-Credentials: true');
}
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// Keep the route after /api; the frontend and API share the same origin.
$apiPosition = strpos('/' . $requestPath . '/', '/api/');
$path = $apiPosition === false
    ? $requestPath
    : substr('/' . $requestPath, $apiPosition + strlen('/api/'));
$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);

try {
    appUrl();
    appKey();
    protectMutation();
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) rateLimit('api-write-ip', clientIp(), 120, 60);
    handleAuth($method, $path);
    if ($method === 'GET' && $path === 'health') {
        db()->query('SELECT 1');
        jsonResponse(['status' => 'ok']);
    }

    if (($path === 'chat/status' && $method === 'GET') || ($path === 'chat' && $method === 'POST')) {
        require dirname(__DIR__) . '/src/chat.php';
        if ($method === 'GET') jsonResponse(['available' => chatConfigured()]);
        rateLimit('chat-ip', clientIp(), 15, 60);
        rateLimit('chat-daily', clientIp(), 200, 86400);
        jsonResponse(chatAnswer(input()));
    }

    if ($method === 'GET' && $path === 'categories') {
        $rows = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
        foreach ($rows as &$row) $row['id'] = (string) $row['id'];
        jsonResponse($rows);
    }

    if ($method === 'GET' && ($path === 'products' || $path === 'products/featured')) {
        $where = ['p.is_active = 1'];
        $params = [];
        if ($path === 'products/featured') $where[] = 'p.is_featured = 1';
        if (!empty($_GET['category'])) {
            $where[] = 'c.slug = ?';
            $params[] = textField($_GET, 'category', 1, 140);
        }
        if (!empty($_GET['search'])) {
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            $term = '%' . textField($_GET, 'search', 1, 200) . '%';
            array_push($params, $term, $term);
        }
        if (isset($_GET['minPrice']) && $_GET['minPrice'] !== '') {
            $where[] = 'p.price >= ?';
            $params[] = moneyField($_GET['minPrice']);
        }
        if (isset($_GET['maxPrice']) && $_GET['maxPrice'] !== '') {
            $where[] = 'p.price <= ?';
            $params[] = moneyField($_GET['maxPrice']);
        }
        if (($_GET['inStock'] ?? '') === 'true') $where[] = 'p.stock > 0';

        $sorts = [
            'newest' => 'p.created_at DESC',
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'name_asc' => 'p.name ASC',
            'name_desc' => 'p.name DESC',
        ];
        $sort = isset($_GET['sort']) ? textField($_GET, 'sort', 1, 30) : 'newest';
        if (!isset($sorts[$sort])) throw new HttpError('Invalid sort order', 422);
        $order = $sorts[$sort];
        $page = integerValue($_GET['page'] ?? '1', 1, 10000);
        $limit = $path === 'products/featured' ? integerValue($_GET['limit'] ?? '4', 1, 12) : 12;
        $offset = ($page - 1) * $limit;
        $whereSql = implode(' AND ', $where);

        $countStatement = db()->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE {$whereSql}");
        $countStatement->execute($params);
        $count = (int) $countStatement->fetchColumn();

        $statement = db()->prepare(
            "SELECT p.*, c.name category_name, c.slug category_slug
             FROM products p LEFT JOIN categories c ON c.id=p.category_id
             WHERE {$whereSql} ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}",
        );
        $statement->execute($params);
        $items = array_map('productFromRow', $statement->fetchAll());
        jsonResponse(['items' => $items, 'count' => $count]);
    }

    if ($method === 'GET' && ($segments[0] ?? '') === 'products' && count($segments) === 2) {
        $statement = db()->prepare(
            'SELECT p.*, c.name category_name, c.slug category_slug
             FROM products p LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.slug=? AND p.is_active=1 LIMIT 1',
        );
        $statement->execute([$segments[1]]);
        $row = $statement->fetch();
        if (!$row) jsonResponse(['message' => 'Product not found'], 404);
        jsonResponse(productFromRow($row));
    }

    if ($path === 'favorites') {
        $user = currentUser(true);
        if ($method === 'GET') {
            $statement = db()->prepare(
                'SELECT p.*, c.name category_name, c.slug category_slug
                 FROM favorites f JOIN products p ON p.id=f.product_id
                 LEFT JOIN categories c ON c.id=p.category_id
                 WHERE f.user_id=? AND p.is_active=1 ORDER BY f.created_at DESC',
            );
            $statement->execute([$user['id']]);
            $products = array_map('productFromRow', $statement->fetchAll());
            jsonResponse(array_map(fn (array $product): array => ['product_id' => $product['id'], 'product' => $product], $products));
        }
        if ($method === 'POST') {
            rateLimit('favorites-ip', clientIp(), 60, 60);
            $data = input();
            $productId = positiveId($data['product_id'] ?? null);
            $find = db()->prepare('SELECT id FROM products WHERE id=? AND is_active=1');
            $find->execute([$productId]);
            if (!$find->fetchColumn()) throw new HttpError('Product not found', 404);
            $statement = db()->prepare('INSERT IGNORE INTO favorites(user_id,product_id) VALUES(?,?)');
            $statement->execute([$user['id'], $productId]);
            jsonResponse(['message' => 'Favorite added'], 201);
        }
        if ($method === 'DELETE') {
            $productId = positiveId($_GET['product_id'] ?? null);
            $statement = db()->prepare('DELETE FROM favorites WHERE user_id=? AND product_id=?');
            $statement->execute([$user['id'], $productId]);
            jsonResponse(['message' => 'Favorite removed']);
        }
    }

    if ($method === 'POST' && $path === 'orders') {
        rateLimit('orders-ip', clientIp(), 10, 600);
        $user = currentUser(false);
        $data = input();
        $items = orderItems($data['items'] ?? null);
        $validated = [];
        foreach (['customer_name' => 120, 'customer_email' => 190, 'customer_phone' => 50, 'address' => 500, 'city' => 120] as $field => $max) {
            $validated[$field] = textField($data, $field, 1, $max);
        }
        if (!filter_var($validated['customer_email'], FILTER_VALIDATE_EMAIL)) throw new HttpError('Invalid customer email', 422);
        $validated['comment'] = textField($data, 'comment', 0, 2000);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $resolved = [];
            $totalCents = 0;
            $find = $pdo->prepare('SELECT id,name,price,stock FROM products WHERE id=? AND is_active=1 FOR UPDATE');
            foreach ($items as $productId => $quantity) {
                $find->execute([$productId]);
                $product = $find->fetch();
                if (!$product || (int) $product['stock'] < $quantity) throw new HttpError('One or more products are unavailable', 422);
                $totalCents += moneyCents($product['price']) * $quantity;
                if ($totalCents > 999999999999) throw new HttpError('Order total is too large', 422);
                $resolved[] = [$product, $quantity];
            }
            $create = $pdo->prepare('INSERT INTO orders(user_id,customer_name,customer_email,customer_phone,address,city,comment,subtotal,total) VALUES(?,?,?,?,?,?,?,?,?)');
            $total = number_format($totalCents / 100, 2, '.', '');
            $create->execute([$user['id'] ?? null, $validated['customer_name'], $validated['customer_email'], $validated['customer_phone'], $validated['address'], $validated['city'], $validated['comment'] ?: null, $total, $total]);
            $orderId = (int) $pdo->lastInsertId();
            $addItem = $pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,price,quantity) VALUES(?,?,?,?,?)');
            $decrement = $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?');
            foreach ($resolved as [$product, $quantity]) {
                $addItem->execute([$orderId, $product['id'], $product['name'], $product['price'], $quantity]);
                $decrement->execute([$quantity, $product['id']]);
            }
            $pdo->commit();
            jsonResponse(['id' => (string) $orderId], 201);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    if ($method === 'GET' && $path === 'orders') {
        $user = currentUser(true);
        $statement = db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
        $statement->execute([$user['id']]);
        jsonResponse($statement->fetchAll());
    }

    if (($segments[0] ?? '') === 'admin') {
        requireAdmin();
        if ($method !== 'GET') rateLimit('admin-write-ip', clientIp(), 60, 60);
        if ($method === 'GET' && $path === 'admin/products') {
            $rows = db()->query('SELECT p.*,c.name category_name,c.slug category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC')->fetchAll();
            jsonResponse(array_map('productFromRow', $rows));
        }
        if ($method === 'POST' && $path === 'admin/products') {
            $data = validateProduct(input());
            $statement = db()->prepare('INSERT INTO products(category_id,name,slug,description,short_description,price,old_price,stock,image_url,is_active,is_featured) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            $statement->execute([$data['category_id'] ?: null, $data['name'], $data['slug'], $data['description'], $data['short_description'] ?? null, $data['price'], $data['old_price'] ?: null, $data['stock'], $data['image_url'] ?: null, !empty($data['is_active']), !empty($data['is_featured'])]);
            jsonResponse(['id' => (string) db()->lastInsertId()], 201);
        }
        if (count($segments) === 3 && $segments[1] === 'products') {
            $id = positiveId($segments[2]);
            if ($method === 'PUT') {
                $data = validateProduct(input());
                $statement = db()->prepare('UPDATE products SET category_id=?,name=?,slug=?,description=?,short_description=?,price=?,old_price=?,stock=?,image_url=?,is_active=?,is_featured=? WHERE id=?');
                $statement->execute([$data['category_id'] ?: null, $data['name'], $data['slug'], $data['description'], $data['short_description'] ?? null, $data['price'], $data['old_price'] ?: null, $data['stock'], $data['image_url'] ?: null, !empty($data['is_active']), !empty($data['is_featured']), $id]);
                jsonResponse(['message' => 'Product updated']);
            }
            if ($method === 'DELETE') {
                $statement = db()->prepare('DELETE FROM products WHERE id=?');
                $statement->execute([$id]);
                jsonResponse(['message' => 'Product deleted']);
            }
        }
    }

    jsonResponse(['message' => 'Route not found'], 404);
} catch (HttpError $exception) {
    if (db()->inTransaction()) db()->rollBack();
    jsonResponse(['message' => $exception->getMessage()], $exception->status);
} catch (Throwable $exception) {
    try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable) { /* Database unavailable. */ }
    $reference = bin2hex(random_bytes(8));
    error_log('Request ' . $reference . ' failed: ' . get_class($exception) . ' code=' . $exception->getCode());
    jsonResponse(['message' => 'Server error. Reference: ' . $reference], 500);
}
