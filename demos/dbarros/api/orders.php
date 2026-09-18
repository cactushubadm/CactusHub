<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    db_require_auth();
    db_json_response(db_read_json('orders.json', []));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = db_request_json();
    $mesa = preg_replace('/[^0-9A-Za-z-]/', '', (string)($data['mesa'] ?? ''));
    $items = $data['items'] ?? null;
    $total = (float)($data['total'] ?? 0);
    if ($mesa === '' || !is_array($items) || !$items) {
        db_json_response(['ok'=>false,'error'=>'invalid_order'], 422);
    }

    $orders = db_read_json('orders.json', []);
    $next = 2400;
    foreach ($orders as $order) $next = max($next, (int)($order['number'] ?? 0));
    $next++;
    $record = [
        'id' => bin2hex(random_bytes(8)),
        'number' => $next,
        'mesa' => substr($mesa, 0, 8),
        'items' => $items,
        'total' => round($total, 2),
        'status' => 'received',
        'createdAt' => date(DATE_ATOM),
    ];
    $orders[] = $record;
    if (count($orders) > 500) $orders = array_slice($orders, -500);
    if (!db_write_json('orders.json', $orders)) db_json_response(['ok'=>false,'error'=>'write_failed'], 500);
    db_json_response(['ok'=>true,'order'=>$record], 201);
}

db_json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);
