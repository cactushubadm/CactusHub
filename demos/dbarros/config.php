<?php
// D'barros Pizzas â€” configuraÃ§Ã£o do ambiente.
// Antes de publicar, troque a senha do gestor e confirme o domÃ­nio HTTPS.

define('DBARROS_SESSION_NAME', 'DBARROSSESSID');
define('DBARROS_GESTOR_USER', 'gestor');
// Senha inicial da demo: Dbarros@2026
// Gere outro hash com: php -r "echo password_hash('SUA-SENHA', PASSWORD_DEFAULT), PHP_EOL;"
define('DBARROS_GESTOR_PASSWORD_HASH', '$2y$12$QmIfZwGSxVECTxTODF5XBeC9KBDh5niaWQ5ibXx66rTPdxurbMQGq');
define('DBARROS_DATA_DIR', __DIR__ . '/data');

define('DBARROS_COOKIE_SECURE', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));

// Impressao automatica - Bematech MP-4200 TH
define('DBARROS_PRINTER_MODEL', 'Bematech MP-4200 TH');
define('DBARROS_PRINTER_PAPER_MM', 80);

