<?php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$normalized = str_replace('\\','/',$path);
$deny = [
    '#^/(?:storage|lib|tools|runtime)(?:/|$)#i',
    '#^/(?:config\.php|router\.php|README\.md|INICIAR_[^/]+\.bat|\.htaccess|php\.ini|\.env)#i',
    '#\.(?:bat|ps1|sqlite|log|ini|md)$#i',
    '#/\.#',
];
foreach($deny as $rule){if(preg_match($rule,$normalized)){http_response_code(404);header('Content-Type:text/plain; charset=utf-8');echo 'Não encontrado.';return true;}}
$file=__DIR__.$normalized;
if($normalized!=='/' && is_file($file)) return false;
if($normalized==='/' || $normalized==='') { require __DIR__.'/index.php'; return true; }
http_response_code(404);header('Content-Type:text/html; charset=utf-8');echo '<h1>404</h1><p>Página não encontrada.</p>';return true;
