<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    db_json_response(db_read_json('catalog.json', []));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    db_require_auth();
    $data = db_request_json();
    if (!array_is_list($data)) db_json_response(['ok'=>false,'error'=>'catalog_must_be_list'], 422);
    if (!db_write_json('catalog.json', $data)) db_json_response(['ok'=>false,'error'=>'write_failed'], 500);
    db_json_response(['ok'=>true,'count'=>count($data)]);
}

db_json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);
