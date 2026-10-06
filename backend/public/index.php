<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = env('FRONTEND_URL', 'http://localhost:5173');
if ($origin === $allowedOrigin) {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
}
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// The API can be deployed in a subdirectory (for example
// /e-commerce-vue/backend/api). Keep only the part after /api so route
// matching does not depend on the domain or deployment directory.
$apiPosition = strpos('/' . $requestPath . '/', '/api/');
$path = $apiPosition === false
    ? $requestPath
    : substr('/' . $requestPath, $apiPosition + strlen('/api/'));
$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);

try {
    if ($method === 'GET' && $path === 'health') {
        db()->query('SELECT 1');
        jsonResponse(['status' => 'ok']);
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
            $params[] = $_GET['category'];
        }
        if (!empty($_GET['search'])) {
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            $term = '%' . $_GET['search'] . '%';
            array_push($params, $term, $term);
        }
        if (isset($_GET['minPrice']) && $_GET['minPrice'] !== '') {
            $where[] = 'p.price >= ?';
            $params[] = (float) $_GET['minPrice'];
        }
        if (isset($_GET['maxPrice']) && $_GET['maxPrice'] !== '') {
            $where[] = 'p.price <= ?';
            $params[] = (float) $_GET['maxPrice'];
        }
        if (($_GET['inStock'] ?? '') === 'true') $where[] = 'p.stock > 0';

        $sorts = [
            'newest' => 'p.created_at DESC',
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'name_asc' => 'p.name ASC',
            'name_desc' => 'p.name DESC',
        ];
        $order = $sorts[$_GET['sort'] ?? 'newest'] ?? $sorts['newest'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = $path === 'products/featured' ? min(12, max(1, (int) ($_GET['limit'] ?? 4))) : 12;
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

    if ($method === 'POST' && $path === 'auth/register') {
        $data = input();
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL) || strlen((string) ($data['password'] ?? '')) < 8 || trim((string) ($data['name'] ?? '')) === '') {
            jsonResponse(['message' => 'Provide a name, valid email and password of at least 8 characters'], 422);
        }
        $statement = db()->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
        try {
            $statement->execute([trim($data['name']), strtolower(trim($data['email'])), password_hash($data['password'], PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) jsonResponse(['message' => 'Email is already registered'], 409);
            throw $exception;
        }
        $id = (int) db()->lastInsertId();
        $user = ['id' => (string) $id, 'name' => trim($data['name']), 'email' => strtolower(trim($data['email'])), 'phone' => null, 'role' => 'customer'];
        jsonResponse(['token' => issueToken($id), 'user' => $user], 201);
    }

    if ($method === 'POST' && $path === 'auth/login') {
        $data = input();
        $statement = db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
        $statement->execute([strtolower(trim((string) ($data['email'] ?? '')))]);
        $user = $statement->fetch();
        if (!$user || !password_verify((string) ($data['password'] ?? ''), $user['password_hash'])) {
            jsonResponse(['message' => 'Invalid email or password'], 401);
        }
        unset($user['password_hash']);
        $user['id'] = (string) $user['id'];
        jsonResponse(['token' => issueToken((int) $user['id']), 'user' => $user]);
    }

    if ($method === 'GET' && $path === 'auth/me') {
        $user = currentUser(true);
        $user['id'] = (string) $user['id'];
        jsonResponse($user);
    }

    if ($method === 'POST' && $path === 'auth/logout') {
        currentUser(true);
        $statement = db()->prepare('DELETE FROM auth_tokens WHERE token_hash=?');
        $statement->execute([hash('sha256', (string) bearerToken())]);
        jsonResponse(['message' => 'Logged out']);
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
            $data = input();
            $statement = db()->prepare('INSERT IGNORE INTO favorites(user_id,product_id) VALUES(?,?)');
            $statement->execute([$user['id'], $data['product_id'] ?? 0]);
            jsonResponse(['message' => 'Favorite added'], 201);
        }
        if ($method === 'DELETE') {
            $productId = $_GET['product_id'] ?? 0;
            $statement = db()->prepare('DELETE FROM favorites WHERE user_id=? AND product_id=?');
            $statement->execute([$user['id'], $productId]);
            jsonResponse(['message' => 'Favorite removed']);
        }
    }

    if ($method === 'POST' && $path === 'orders') {
        $user = currentUser(false);
        $data = input();
        $items = $data['items'] ?? [];
        foreach (['customer_name', 'customer_email', 'customer_phone', 'address', 'city'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') jsonResponse(['message' => "Field '{$field}' is required"], 422);
        }
        if (!is_array($items) || count($items) === 0) jsonResponse(['message' => 'Cart is empty'], 422);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $resolved = [];
            $total = 0.0;
            $find = $pdo->prepare('SELECT id,name,price,stock FROM products WHERE id=? AND is_active=1 FOR UPDATE');
            foreach ($items as $item) {
                $quantity = max(1, (int) ($item['quantity'] ?? 0));
                $find->execute([$item['product_id'] ?? 0]);
                $product = $find->fetch();
                if (!$product || (int) $product['stock'] < $quantity) throw new RuntimeException('One or more products are unavailable');
                $total += (float) $product['price'] * $quantity;
                $resolved[] = [$product, $quantity];
            }
            $create = $pdo->prepare('INSERT INTO orders(user_id,customer_name,customer_email,customer_phone,address,city,comment,subtotal,total) VALUES(?,?,?,?,?,?,?,?,?)');
            $create->execute([$user['id'] ?? null, $data['customer_name'], $data['customer_email'], $data['customer_phone'], $data['address'], $data['city'], $data['comment'] ?? null, $total, $total]);
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
            jsonResponse(['message' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not create order'], 422);
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
            $id = (int) $segments[2];
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
} catch (PDOException $exception) {
    $message = env('APP_ENV') === 'development' ? $exception->getMessage() : 'Database request failed';
    jsonResponse(['message' => $message], 500);
} catch (Throwable $exception) {
    jsonResponse(['message' => env('APP_ENV') === 'development' ? $exception->getMessage() : 'Server error'], 500);
}
