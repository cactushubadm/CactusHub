<?php
require __DIR__.'/../lib/bootstrap.php';
require_admin();

$path = $config['database']['path'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Banco não encontrado.');
}

$tmp = dirname($path).'/backup-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    // Garante que transações no WAL sejam incorporadas antes do snapshot.
    db()->exec('PRAGMA wal_checkpoint(FULL)');
    $quoted = db()->quote($tmp);
    db()->exec('VACUUM INTO '.$quoted);
    if (!is_file($tmp)) {
        throw new RuntimeException('Não foi possível gerar o arquivo de backup.');
    }

    audit_log('backup.download');
    $name = 'cactus-eventos-backup-'.date('Ymd-His').'.sqlite';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('Content-Length: '.filesize($tmp));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($tmp);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Falha ao gerar backup: '.e($e->getMessage()));
} finally {
    if (isset($tmp) && is_file($tmp)) {
        @unlink($tmp);
    }
}
exit;
