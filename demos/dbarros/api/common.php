<?php
require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(DBARROS_SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => DBARROS_COOKIE_SECURE,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function db_auth(): bool {
    return !empty($_SESSION['db_gestor_auth']);
}

function db_require_auth(): void {
    if (!db_auth()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'unauthorized']);
        exit;
    }
}

function db_json_response($data, int $status=200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function db_read_json(string $name, $fallback) {
    $path = DBARROS_DATA_DIR . '/' . $name;
    if (!is_file($path)) return $fallback;
    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : $fallback;
}

function db_write_json(string $name, $data): bool {
    $path = DBARROS_DATA_DIR . '/' . $name;
    $tmp = $path . '.tmp';
    $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) return false;
    $ok = file_put_contents($tmp, $payload . "\n", LOCK_EX);
    if ($ok === false) return false;
    return rename($tmp, $path);
}

function db_request_json() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) db_json_response(['ok'=>false,'error'=>'invalid_json'], 400);
    return $data;
}
