<?php

declare(strict_types=1);
require_once __DIR__ . '/../lib/site.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode(load_site_config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
