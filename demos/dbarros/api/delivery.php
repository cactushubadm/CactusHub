<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    db_json_response(db_read_json('delivery.json', []));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    db_require_auth();
    $data = db_request_json();
    if (!db_write_json('delivery.json', $data)) db_json_response(['ok'=>false,'error'=>'write_failed'], 500);
    db_json_response(['ok'=>true]);
}

db_json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);
