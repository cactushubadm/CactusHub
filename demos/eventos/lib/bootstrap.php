<?php
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_start([
        'cookie_httponly'=>true,
        'cookie_samesite'=>'Lax',
        'cookie_secure'=>$secure,
        'use_strict_mode'=>true,
        'gc_maxlifetime'=>7200,
    ]);
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/auth.php';
try { migrate_database(db()); } catch (Throwable $e) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'diagnostico.php') {
        http_response_code(500);
        exit('Erro ao preparar o banco: ' . e($e->getMessage()) . ' — abra diagnostico.php para verificar o ambiente.');
    }
}
try { ensure_brand_defaults(); } catch (Throwable $e) { /* não bloqueia o site por defaults */ }
release_expired_orders();
ensure_installed();
