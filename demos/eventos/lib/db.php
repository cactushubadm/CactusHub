<?php
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    global $config;
    $db = $config['database'] ?? [];
    if (($db['driver'] ?? 'sqlite') !== 'sqlite') {
        throw new RuntimeException('Esta distribuição utiliza SQLite.');
    }
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('A extensão pdo_sqlite do PHP não está habilitada. Use INICIAR_CACTUS_EVENTOS.bat para configurar o PHP portátil automaticamente.');
    }
    $path = (string)($db['path'] ?? (__DIR__ . '/../storage/cactus-eventos.sqlite'));
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta do banco de dados.');
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 8000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    return $pdo;
}
