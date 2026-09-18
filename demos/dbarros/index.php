<?php
require_once __DIR__ . '/config.php';

session_name(DBARROS_SESSION_NAME);
session_set_cookie_params([
    'httponly'=>true,
    'secure'=>DBARROS_COOKIE_SECURE,
    'samesite'=>'Lax',
    'path'=>'/'
]);
session_start();

function base_path(): string {
    $script = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return $script === '/' || $script === '.' ? '' : rtrim($script, '/');
}

function public_url(string $path=''): string {
    $base = base_path();
    return $base . ($path ? '/' . ltrim($path, '/') : '/');
}

function read_data(string $name, $fallback) {
    $path = DBARROS_DATA_DIR . '/' . $name;
    if (!is_file($path)) return $fallback;
    $d = json_decode(file_get_contents($path) ?: '', true);
    return is_array($d) ? $d : $fallback;
}

function render_view(string $file, array $vars=[]): void {
    $path = __DIR__ . '/views/' . $file;
    if (!is_file($path)) { http_response_code(500); echo 'View ausente.'; exit; }
    $html = file_get_contents($path);
    $base = base_path();
    $boot = [
        'basePath' => $base,
        'mesa' => $vars['mesa'] ?? null,
        'catalog' => $vars['catalog'] ?? null,
        'delivery' => $vars['delivery'] ?? null,
        'isGestor' => !empty($_SESSION['db_gestor_auth']),
        'demoMode' => !empty($vars['demoMode']),
    ];
    $inject = '<script>window.DBARROS_BASE_PATH=' . json_encode($base, JSON_UNESCAPED_SLASHES) . ';window.DBARROS_BOOTSTRAP=' . json_encode($boot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . ';</script>';
    $html = str_replace('__BASE__', $base, $html);
    $html = str_replace('</head>', $inject . '</head>', $html);
    echo $html;
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function render_login(?string $error=null): void {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $action = htmlspecialchars(public_url('gestor'), ENT_QUOTES, 'UTF-8');
    $errorHtml = $error ? '<div class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>' : '';
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#171210"><title>D\'barros | Acesso do Gestor</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#171210;color:#fff;font-family:Inter,sans-serif}.box{width:min(430px,100%);padding:30px;border-radius:24px;background:#211914;border:1px solid rgba(255,255,255,.09);box-shadow:0 30px 90px rgba(0,0,0,.35)}.brand{font-family:"Barlow Condensed";font-size:38px;font-weight:800;line-height:.9}.brand span{display:block;margin-top:8px;font:700 11px Inter;letter-spacing:4px;color:#d96d22}.k{display:block;margin:24px 0 7px;color:#d9cfc8;font-size:11px;font-weight:700}input{width:100%;height:48px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:#171210;color:#fff;padding:0 13px;outline:none}button{width:100%;height:50px;margin-top:18px;border:0;border-radius:12px;background:#d96d22;color:#fff;font-weight:900;cursor:pointer}.error{padding:10px 12px;margin-top:16px;border-radius:10px;background:#4a201d;color:#ffd8d3;font-size:11px}.note{margin-top:15px;color:#9f938b;font-size:10px;line-height:1.55}.back{display:block;margin-top:17px;text-align:center;color:#d6cbc4;font-size:10px;text-decoration:none}</style></head><body><main class="box"><div class="brand">D\'BARROS<span>PAINEL DO GESTOR</span></div>' . $errorHtml . '<form method="post" action="' . $action . '"><input type="hidden" name="csrf" value="' . $token . '"><label class="k">USUÁRIO</label><input name="username" autocomplete="username" required><label class="k">SENHA</label><input type="password" name="password" autocomplete="current-password" required><button type="submit">Entrar no painel</button></form><p class="note">Área protegida por sessão. Troque a senha inicial no arquivo <b>config.php</b> antes de publicar.</p><a class="back" href="' . htmlspecialchars(public_url(), ENT_QUOTES, 'UTF-8') . '">← Voltar ao site</a></main></body></html>';
    exit;
}

$route = $_GET['route'] ?? 'site';

if ($route === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . public_url('gestor'));
    exit;
}


if ($route === 'demo') {
    header('Cache-Control: no-store');
    render_view('demo.html', ['demoMode'=>true]);
}

if ($route === 'demo_gestor') {
    header('Cache-Control: no-store');
    render_view('gestor.html', [
        'catalog'=>read_data('catalog.json', []),
        'delivery'=>read_data('delivery.json', []),
        'demoMode'=>true,
    ]);
}

if ($route === 'demo_qrs') {
    header('Cache-Control: no-store');
    render_view('qrs-mesas.html', ['demoMode'=>true]);
}

if ($route === 'demo_comanda') {
    $pdf = __DIR__ . '/private/comanda-exemplo-80mm.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="comanda-dbarros-demo-80mm.pdf"');
    header('Content-Length: ' . filesize($pdf));
    readfile($pdf); exit;
}

if ($route === 'gestor') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SESSION['db_gestor_auth'])) {
        $csrf = $_POST['csrf'] ?? '';
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) render_login('Sessão expirada. Tente novamente.');
        if ($user === DBARROS_GESTOR_USER && password_verify($pass, DBARROS_GESTOR_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['db_gestor_auth'] = true;
            header('Location: ' . public_url('gestor'));
            exit;
        }
        render_login('Usuário ou senha inválidos.');
    }
    if (empty($_SESSION['db_gestor_auth'])) render_login();
    header('Cache-Control: no-store');
    render_view('gestor.html', [
        'catalog'=>read_data('catalog.json', []),
        'delivery'=>read_data('delivery.json', []),
    ]);
}

if ($route === 'qrs') {
    if (empty($_SESSION['db_gestor_auth'])) { header('Location: '.public_url('gestor')); exit; }
    header('Cache-Control: no-store');
    render_view('qrs-mesas.html');
}

if ($route === 'comanda') {
    if (empty($_SESSION['db_gestor_auth'])) { header('Location: '.public_url('gestor')); exit; }
    $pdf = __DIR__ . '/private/comanda-exemplo-80mm.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="comanda-dbarros-80mm.pdf"');
    header('Content-Length: ' . filesize($pdf));
    readfile($pdf); exit;
}

if ($route === 'cardapio_impressao') {
    if (empty($_SESSION['db_gestor_auth'])) { header('Location: '.public_url('gestor')); exit; }
    $html = file_get_contents(__DIR__ . '/private/cardapio-impressao.html');
    $base = base_path();
    $html = str_replace('assets/', ltrim($base . '/assets/', '/') , $html);
    echo $html; exit;
}

if ($route === 'salao') {
    $mesa = preg_replace('/[^0-9A-Za-z-]/', '', (string)($_GET['mesa'] ?? ''));
    if ($mesa === '') { header('Location: '.public_url()); exit; }
    render_view('salao.html', ['mesa'=>$mesa]);
}

render_view('site.html');
