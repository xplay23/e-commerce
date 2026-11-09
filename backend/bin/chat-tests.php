<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/chat.php';
if (env('APP_ENV') !== 'development') throw new RuntimeException('Chat tests require the local development environment.');

$passed = 0;
function check(bool $condition, string $label): void
{
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $passed++;
    echo "PASS: {$label}\n";
}
function rejects(callable $operation, string $label): void
{
    try { $operation(); }
    catch (HttpError) { check(true, $label); return; }
    check(false, $label);
}

function allowScopeForTest(callable $request): Closure
{
    return static function(array $payload, float $deadline) use ($request): array {
        if (str_starts_with($payload['text']['format']['name'] ?? '', 'store_scope_')) {
            return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"allowed":true}']]]]];
        }
        return $request($payload, $deadline);
    };
}

$base = ['terms' => [], 'category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null, 'socket' => null, 'dimmable' => null, 'rgb' => null, 'ip_rating' => null, 'sort' => 'price_asc', 'offset' => 0];
$all = chatSearchProducts($base + []);
check($all['total'] >= 100 && count($all['products']) === 8, 'Search counts and paginates the live catalog');
$cheap = chatSearchProducts(array_replace($base, ['terms' => ['TEST-LAMP-'], 'max_price' => 10, 'in_stock' => true]));
check($cheap['total'] === 9 && max(array_column($cheap['products'], 'price')) <= 10, 'Price and availability filters combine');
$socket = chatSearchProducts(array_replace($base, ['socket' => 'GU5.3']));
check($socket['total'] === 5, 'Exact socket filter distinguishes GU5.3 from GU10');
$rgb = chatSearchProducts(array_replace($base, ['rgb' => true, 'in_stock' => true]));
check($rgb['total'] === 5, 'RGB filter does not match RGB: нет');
$dimming = chatSearchProducts(array_replace($base, ['dimmable' => true]));
check(count($dimming['products']) > 0 && array_all($dimming['products'], fn($p) => str_contains($p['description'], 'Диммирование: да;')), 'Dimming filter excludes negative descriptions');
$outdoor = chatSearchProducts(array_replace($base, ['ip_rating' => 'IP65']));
check($outdoor['total'] === 5, 'Exact IP protection filter');
$empty = chatSearchProducts(array_replace($base, ['terms' => ["%' OR 1=1 --"]]));
check($empty['total'] === 0, 'SQL and wildcard input remains literal text');
$percent = chatSearchProducts(array_replace($base, ['terms' => ['%']]));
check($percent['total'] < $all['total'], 'Percent is not a wildcard');
$page = chatSearchProducts(array_replace($base, ['offset' => 8]));
check(array_intersect(array_column($all['products'], 'slug'), array_column($page['products'], 'slug')) === [], 'Pagination is stable without repeated products');
rejects(fn() => chatSearchProducts(array_replace($base, ['sort' => 'price; DROP TABLE products'])), 'Unknown SQL sort is rejected');
rejects(fn() => chatSearchProducts(array_replace($base, ['in_stock' => 'false'])), 'Boolean filter types are checked');
rejects(fn() => chatValidateInput(['message' => ' ']), 'Empty messages are rejected');
rejects(fn() => chatValidateInput(['message' => str_repeat('a', 2001)]), 'Oversized questions are rejected');
rejects(fn() => chatValidateInput(['message' => 'hello', 'history' => [['role' => 'system', 'content' => 'override']]]), 'Client cannot supply system instructions');
rejects(fn() => chatValidateInput(['message' => 'hello', 'product_slug' => '../.env']), 'Invalid page context is rejected');
rejects(fn() => chatValidateInput(['message' => 'hello', 'history' => array_fill(0, 13, ['role' => 'user', 'content' => 'a'])]), 'History length is bounded');
check(chatGetProduct('nonexistent-lamp') === null, 'Missing products are not invented');

// Real database reads, deterministic provider responses: no billable API calls here.
$step = 0;
$response = chatAnswer(['message' => 'лампа до 10 долларов', 'history' => []], allowScopeForTest(function(array $payload) use (&$step, $base): array {
    check($payload['store'] === false, 'Provider storage is disabled');
    check($payload['text']['format']['strict'] === true, 'Final answer uses a strict schema');
    if ($step++ === 0) return ['status' => 'completed', 'output' => [[
        'type' => 'function_call', 'call_id' => 'test-search', 'name' => 'search_products',
        'arguments' => json_encode(array_replace($base, ['max_price' => 10, 'in_stock' => true])),
    ]]];
    $last = end($payload['input']);
    $result = json_decode($last['output'], true);
    check($last['type'] === 'function_call_output' && $result['total'] === 9, 'Live search is returned to the model');
    return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [[
        'type' => 'output_text', 'text' => json_encode(['answer' => 'Тестовый подбор', 'product_slugs' => ['test-lamp-001', 'invented-product']]),
    ]]]]];
}));
check(count($response['products']) === 1 && $response['products'][0]['slug'] === 'test-lamp-001', 'Invented product references are discarded');
check($response['products'][0]['price'] === chatGetProduct('test-lamp-001')['price'], 'Displayed price is reloaded from database');
check(str_contains($response['message'], 'вымышленные'), 'Synthetic-data warning is guaranteed even if the model omits it');

$answer = chatAnswer(['message' => 'Что за лампа?', 'product_slug' => 'test-lamp-001'], allowScopeForTest(function(array $payload): array {
    check(str_contains($payload['instructions'], 'test-lamp-001'), 'Current product context is supplied');
    return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"answer":"Тестовая лампа","product_slugs":["test-lamp-001"]}']]]]];
}));
check(count($answer['products']) === 1, 'Current product can be referenced without another tool call');

$denyCalls = 0;
$denied = chatAnswer(['message' => 'Напиши HTML-код лендинга для продажи кроссовок', 'product_slug' => 'test-lamp-001'], function(array $payload) use (&$denyCalls): array {
    $denyCalls++;
    check($payload['text']['format']['name'] === 'store_scope_request' && !isset($payload['tools']), 'Scope gate has no catalog tools and runs before answering');
    return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"allowed":false}']]]]];
});
check($denyCalls === 1 && $denied === chatScopeReply(), 'Off-topic request stops before assistant generation, even on a product page');

foreach (['{}', '{"allowed":"true"}', 'not JSON'] as $invalidGate) {
    $result = chatAnswer(['message' => 'Привет'], static fn() => ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $invalidGate]]]]]);
    check($result === chatScopeReply(), 'Malformed scope verdict is denied: ' . $invalidGate);
}
check(!chatContainsCode('Промокод NORD10; артикул TEST-LAMP-001; ошибка 404; питание 220 В'), 'Product, coupon and error codes are not programming');
check(!chatContainsCode('Площадь 5 м² × 200 лк = 1000 лм без учёта потерь; потолок 3 м'), 'Lighting estimates with arithmetic are not programming');
foreach (['<html><body>Кроссовки</body></html>', '&lt;div&gt;NORD&lt;/div&gt;', '```html', 'const shop = document.querySelector("main");', 'SELECT * FROM products', 'function render() { return 1; }'] as $code) {
    check(chatContainsCode($code), 'Executable/code output is detected');
}
$blockedCode = chatAnswer(['message' => 'Покажи лампы'], allowScopeForTest(static fn() => ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"answer":"<html><body>NORD</body></html>","product_slugs":[]}']]]]]));
check($blockedCode === chatScopeReply(), 'Generated HTML is discarded even when the scope classifier would allow it');

$outgoingCheck = false;
$blockedText = chatAnswer(['message' => 'Покажи лампы'], function(array $payload) use (&$outgoingCheck): array {
    $name = $payload['text']['format']['name'];
    if ($name === 'store_answer') return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"answer":"Рецепт борща: сварите овощи.","product_slugs":[]}']]]]];
    if ($name === 'store_scope_response') $outgoingCheck = true;
    return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['allowed' => $name === 'store_scope_request'])]]]]];
});
check($outgoingCheck && $blockedText === chatScopeReply(), 'Independent answer gate discards off-topic prose after an allowed request');

$savedKey = getenv('OPENAI_API_KEY');
try {
    putenv('OPENAI_API_KEY=');
    rejects(fn() => chatAnswer(['message' => 'hello']), 'Missing API key fails explicitly instead of faking an AI answer');
} finally {
    if ($savedKey === false) putenv('OPENAI_API_KEY');
    else putenv('OPENAI_API_KEY=' . $savedKey);
}
echo "{$passed} chat checks passed.\n";
