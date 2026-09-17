<?php

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function money(float $value): string
{
    global $config;
    return ($config['currency_symbol'] ?? 'R$') . ' ' . number_format($value, 2, ',', '.');
}

function dt_br(?string $value): string
{
    if (!$value) return '';
    try { return (new DateTime($value))->format('d/m/Y \à\s H:i'); }
    catch (Throwable $e) { return $value; }
}

function date_br(?string $value): string
{
    if (!$value) return '';
    try { return (new DateTime($value))->format('d/m/Y'); }
    catch (Throwable $e) { return $value; }
}


function db_now_utc(): string { return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); }
function db_plus_utc(string $modifier): string { $d=new DateTime('now',new DateTimeZone('UTC'));$d->modify($modifier);return $d->format('Y-m-d H:i:s'); }
function dt_db_br(?string $value): string
{
    if(!$value)return '';
    global $config;
    try{$d=new DateTime($value,new DateTimeZone('UTC'));$d->setTimezone(new DateTimeZone($config['timezone']??'America/Sao_Paulo'));return $d->format('d/m/Y \à\s H:i');}catch(Throwable $e){return (string)$value;}
}

function payment_base_url(): string
{
    global $config;
    $base = trim(app_setting('app_base_url', ''));
    if ($base !== '') return rtrim($base, '/');
    return rtrim(trim((string)($config['payment']['base_url'] ?? '')), '/');
}

function base_url(string $path = ''): string
{
    $base = payment_base_url();
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8088';
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $dir = dirname($script);
        if (str_contains($dir, '/admin')) $dir = dirname($dir);
        $base = rtrim($scheme . '://' . $host . ($dir === '/' || $dir === '.' ? '' : $dir), '/');
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function request_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'local'), 0, 80);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?: '';
    return trim($text, '-') ?: 'evento';
}

function unique_event_slug(string $title, ?int $ignoreId = null): string
{
    $base = slugify($title); $slug = $base; $n = 2;
    while (true) {
        $sql = 'SELECT id FROM events WHERE slug=?'; $args = [$slug];
        if ($ignoreId) { $sql .= ' AND id<>?'; $args[] = $ignoreId; }
        $st = db()->prepare($sql); $st->execute($args);
        if (!$st->fetch()) return $slug;
        $slug = $base . '-' . $n++;
    }
}

function event_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM events WHERE id=?'); $st->execute([$id]);
    return $st->fetch() ?: null;
}

function event_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM events WHERE slug=? AND status="published"'); $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function ticket_types(int $eventId, bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM ticket_types WHERE event_id=?';
    if ($onlyActive) $sql .= ' AND active=1';
    $sql .= ' ORDER BY sort_order,id';
    $st = db()->prepare($sql); $st->execute([$eventId]);
    return $st->fetchAll();
}

function app_setting(string $key, string $default = ''): string
{
    try {
        $st = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? $default : (string)$v;
    } catch (Throwable $e) { return $default; }
}

function set_app_setting(string $key, string $value, bool $secret = false): void
{
    db()->prepare('INSERT INTO settings(setting_key,setting_value,is_secret,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value,is_secret=excluded.is_secret,updated_at=CURRENT_TIMESTAMP')
        ->execute([$key, $value, $secret ? 1 : 0]);
}

function ensure_brand_defaults(): void
{
    global $config;
    $pub = is_array($config['public'] ?? null) ? $config['public'] : [];
    $defaults = [
        'site_name' => (string)($config['site_name'] ?? 'SUA MARCA'),
        'site_tagline' => (string)($config['site_tagline'] ?? 'Seu evento. Sua experiência. Sua marca.'),
        'site_city_state' => 'SUA CIDADE / UF',
        'site_since' => 'DESDE 20XX',
        'site_description' => 'Eventos, ingressos online e Lista VIP em uma experiência digital completa.',
        'hero_kicker' => 'SUA CIDADE • UF',
        'hero_title' => 'Sua próxima noite',
        'hero_highlight' => 'começa aqui.',
        'hero_text' => 'Shows, festas e experiências com venda de ingressos online, Lista VIP e entrada digital.',
        'about_kicker' => 'SOBRE O SEU ESPAÇO',
        'about_title' => 'Conte a história',
        'about_highlight' => 'da sua marca.',
        'about_text' => 'Use este espaço para apresentar sua casa, bar, balada, arena, festival ou projeto de eventos. Troque o texto e as fotos pelo painel administrativo.',
        'inside_kicker' => 'SUA EXPERIÊNCIA',
        'inside_title' => 'Mostre o que faz',
        'inside_highlight' => 'seu lugar único.',
        'inside_text' => 'Inclua uma foto ampla do ambiente, palco, pista, camarotes ou qualquer elemento que represente sua experiência.',
        'gallery_title' => 'Suas noites.',
        'gallery_highlight' => 'Suas fotos.',
        'gallery_text' => 'Substitua estas imagens por fotos reais dos seus eventos, artistas, público e estrutura.',
        'location_title' => 'Nos vemos',
        'location_highlight' => 'no seu evento.',
        'programming_text' => 'Datas e horários conforme a programação de cada evento.',
        'address' => (string)($config['address'] ?? 'COLOQUE SEU ENDEREÇO AQUI'),
        'maps_url' => (string)($config['maps_url'] ?? ''),
        'instagram_url' => (string)($config['instagram_url'] ?? ''),
        'brand_logo' => 'assets/media/placeholder-logo.svg',
        'hero_image' => 'assets/media/placeholder-hero.svg',
        'about_image' => 'assets/media/placeholder-about.svg',
        'interior_image' => 'assets/media/placeholder-interior.svg',
        'gallery_image_1' => 'assets/media/placeholder-gallery-1.svg',
        'gallery_image_2' => 'assets/media/placeholder-gallery-2.svg',
        'location_image' => 'assets/media/placeholder-location.svg',
        'brand_primary' => '#d6a35a',
        'brand_primary_2' => '#efc67d',
        'brand_rust' => '#9a4f2e',
        'brand_night' => '#100908',
        'app_base_url' => (string)($pub['base_url'] ?? ''),
        'contact_phone' => (string)($config['contact_phone'] ?? ''),
        'contact_whatsapp' => (string)($config['contact_whatsapp'] ?? ''),
        'contact_instagram' => (string)($config['contact_instagram'] ?? '@seuinstagram'),
        'support_email' => (string)($pub['support_email'] ?? ''),
        'privacy_email' => (string)($pub['privacy_email'] ?? ''),
        'legal_name' => (string)($pub['legal_name'] ?? ''),
        'legal_document' => (string)($pub['legal_document'] ?? ''),
        'legal_address' => (string)($pub['legal_address'] ?? ''),
        'mail_transport' => (string)($config['smtp']['transport'] ?? 'phpmail'),
        'smtp_from_email' => (string)($config['smtp']['from_email'] ?? ''),
        'smtp_from_name' => (string)($config['smtp']['from_name'] ?? 'SUA MARCA'),
        'smtp_reply_to' => (string)($config['smtp']['reply_to'] ?? ''),
        'terms_version' => '2026-09-10',
        'privacy_version' => '2026-09-10',
        'cancellation_version' => '2026-09-10',
    ];
    foreach ($defaults as $key=>$value) {
        if (app_setting($key, '') === '' && $value !== '') set_app_setting($key, $value, false);
    }
}

function site_name(): string
{
    global $config;
    return app_setting('site_name', (string)($config['site_name'] ?? 'SUA MARCA'));
}

function site_tagline(): string
{
    global $config;
    return app_setting('site_tagline', (string)($config['site_tagline'] ?? 'Seu evento. Sua experiência. Sua marca.'));
}

function brand_logo(): string { return app_setting('brand_logo','assets/media/placeholder-logo.svg'); }
function brand_media(string $key, string $fallback): string { return app_setting($key,$fallback); }

function sanitize_hex_color(string $value, string $fallback): string
{
    $value=trim($value);
    return preg_match('/^#[0-9a-fA-F]{6}$/',$value) ? strtolower($value) : $fallback;
}

function brand_css_vars(): string
{
    $primary=sanitize_hex_color(app_setting('brand_primary','#d6a35a'),'#d6a35a');
    $primary2=sanitize_hex_color(app_setting('brand_primary_2','#efc67d'),'#efc67d');
    $rust=sanitize_hex_color(app_setting('brand_rust','#9a4f2e'),'#9a4f2e');
    $night=sanitize_hex_color(app_setting('brand_night','#100908'),'#100908');
    return ':root{--gold:'.$primary.';--gold-2:'.$primary2.';--rust:'.$rust.';--night:'.$night.';}';
}

function save_uploaded_brand_image(array $file, string $prefix): string
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return '';
    if ((int)($file['size']??0) > 6*1024*1024) throw new RuntimeException('Cada imagem deve ter no máximo 6 MB.');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime])) throw new RuntimeException('Imagem inválida. Use JPG, PNG ou WEBP.');
    $dir=dirname(__DIR__).'/assets/uploads';
    if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta de uploads.');
    $prefix=preg_replace('/[^a-z0-9_-]/i','-',strtolower($prefix)) ?: 'brand';
    $name=$prefix.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.'.$allowed[$mime];
    if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Não foi possível salvar a imagem.');
    return 'assets/uploads/'.$name;
}

function delete_uploaded_asset_if_owned(string $path): void
{
    if($path!=='' && str_starts_with($path,'assets/uploads/')) @unlink(dirname(__DIR__).'/'.$path);
}

function payment_mode(): string
{
    global $config;
    $mode = app_setting('payment_mode', (string)($config['payment']['mode'] ?? 'manual'));
    return in_array($mode, ['manual','mercadopago'], true) ? $mode : 'manual';
}

function mp_access_token(): string
{
    global $config;
    $env = trim((string)($config['payment']['mercadopago_access_token'] ?? ''));
    return $env !== '' ? $env : trim(app_setting('mp_access_token', ''));
}

function mp_webhook_secret(): string
{
    global $config;
    $env = trim((string)($config['payment']['mercadopago_webhook_secret'] ?? ''));
    return $env !== '' ? $env : trim(app_setting('mp_webhook_secret', ''));
}

function order_number(): string
{
    return 'EVT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function order_public_token(): string { return bin2hex(random_bytes(24)); }
function ticket_code(): string { return strtoupper('EV-' . bin2hex(random_bytes(5))); }

function status_label(string $status): string
{
    return match ($status) {
        'published'=>'Publicado','draft'=>'Rascunho','closed'=>'Encerrado',
        'pending'=>'Pendente','paid'=>'Pago','review'=>'Em revisão','cancelled'=>'Cancelado','refunded'=>'Reembolsado','expired'=>'Expirado',
        'active'=>'Ativo','checked_in'=>'Entrou','removed'=>'Removido','sent'=>'Enviado','failed'=>'Falha',
        'valid'=>'Válido','used'=>'Utilizado','invalid'=>'Inválido','cancelled_ticket'=>'Cancelado',
        default=>ucfirst($status),
    };
}

function normalize_document(string $value): string { return preg_replace('/\D+/', '', $value) ?: ''; }
function normalize_phone(string $value): string { return preg_replace('/\D+/', '', $value) ?: ''; }

function ensure_installed(): void
{
    try {
        $count = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($count === 0 && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') redirect(base_url('install.php'));
    } catch (Throwable $e) {
        if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') redirect(base_url('install.php'));
    }
}

function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
{
    try {
        $adminId = isset($_SESSION['admin']['id']) ? (int)$_SESSION['admin']['id'] : null;
        db()->prepare('INSERT INTO audit_logs(admin_id,action,entity_type,entity_id,metadata,ip_address) VALUES(?,?,?,?,?,?)')
            ->execute([$adminId,$action,$entityType,$entityId,$metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,request_ip()]);
    } catch (Throwable $ignored) {}
}

function event_sales_open(array $event): bool
{
    if (($event['status'] ?? '') !== 'published') return false;
    $now = time();
    if (!empty($event['sales_start']) && strtotime((string)$event['sales_start']) > $now) return false;
    if (!empty($event['sales_end']) && strtotime((string)$event['sales_end']) < $now) return false;
    if (!empty($event['event_date']) && strtotime((string)$event['event_date']) < $now) return false;
    return true;
}

function ticket_available(array $type): int { return max(0, (int)$type['stock'] - (int)$type['sold']); }

function release_order_inventory(int $orderId): void
{
    $pdo = db();
    $st = $pdo->prepare('SELECT status,inventory_released FROM orders WHERE id=?'); $st->execute([$orderId]);
    $order = $st->fetch(); if (!$order || (int)$order['inventory_released'] === 1) return;
    $items = $pdo->prepare('SELECT ticket_type_id,quantity FROM order_items WHERE order_id=?'); $items->execute([$orderId]);
    foreach ($items->fetchAll() as $item) {
        $pdo->prepare('UPDATE ticket_types SET sold=MAX(0,sold-?) WHERE id=?')->execute([(int)$item['quantity'],(int)$item['ticket_type_id']]);
    }
    $pdo->prepare('UPDATE orders SET inventory_released=1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$orderId]);
}

function release_expired_orders(): int
{
    try {
        $st = db()->query("SELECT id FROM orders WHERE status='pending' AND expires_at IS NOT NULL AND datetime(expires_at)<=datetime('now') LIMIT 100");
        $ids = array_map(fn($r)=>(int)$r['id'], $st->fetchAll());
        $count = 0;
        foreach ($ids as $id) {
            $pdo = db(); $pdo->beginTransaction();
            try {
                $check = $pdo->prepare("SELECT status FROM orders WHERE id=?"); $check->execute([$id]);
                if ($check->fetchColumn() === 'pending') {
                    release_order_inventory($id);
                    $pdo->prepare("UPDATE orders SET status='expired',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
                    $count++;
                }
                $pdo->commit();
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
        }
        return $count;
    } catch (Throwable $e) { return 0; }
}

function mark_order_paid(int $orderId, ?string $paymentId = null): bool
{
    $pdo = db(); $pdo->beginTransaction();
    $issued = false;
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id=?'); $st->execute([$orderId]); $order = $st->fetch();
        if (!$order) throw new RuntimeException('Pedido não encontrado.');
        if ($order['status'] === 'paid') { $pdo->commit(); return true; }
        if ($order['status'] === 'review') { $pdo->commit(); return false; }
        if (in_array($order['status'], ['cancelled','refunded'], true)) throw new RuntimeException('Pedido cancelado/reembolsado não pode ser pago.');

        // If the reservation expired and inventory was released, re-reserve atomically.
        // If stock is no longer available, preserve the approved payment for manual review
        // and DO NOT oversell or issue a ticket.
        if ((int)$order['inventory_released'] === 1) {
            $items = $pdo->prepare('SELECT ticket_type_id,quantity FROM order_items WHERE order_id=?'); $items->execute([$orderId]);
            $rows = $items->fetchAll();
            foreach ($rows as $item) {
                $reserve = $pdo->prepare('UPDATE ticket_types SET sold=sold+? WHERE id=? AND (stock-sold)>=?');
                $reserve->execute([(int)$item['quantity'],(int)$item['ticket_type_id'],(int)$item['quantity']]);
                if ($reserve->rowCount() !== 1) {
                    // Roll back any reservations made in this transaction, then flag review.
                    $pdo->rollBack();
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE orders SET status='review',requires_review=1,review_reason=?,external_payment_id=COALESCE(?,external_payment_id),paid_at=COALESCE(paid_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE id=?")
                        ->execute(['Pagamento aprovado após expiração, porém o estoque foi vendido a outro cliente. Reembolsar ou regularizar manualmente.',$paymentId,$orderId]);
                    $pdo->commit();
                    audit_log('order.review_required','order',$orderId,['payment_id'=>$paymentId,'reason'=>'stock_unavailable_after_expiration']);
                    queue_order_status_email($orderId,'review');
                    return false;
                }
            }
        }

        $pdo->prepare("UPDATE orders SET status='paid',requires_review=0,review_reason=NULL,inventory_released=0,external_payment_id=COALESCE(?,external_payment_id),paid_at=COALESCE(paid_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$paymentId,$orderId]);

        $countSt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE order_id=?'); $countSt->execute([$orderId]);
        if ((int)$countSt->fetchColumn() === 0) {
            $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $items->execute([$orderId]);
            foreach ($items->fetchAll() as $item) {
                for ($i=0; $i<(int)$item['quantity']; $i++) {
                    $pdo->prepare('INSERT INTO tickets(order_id,order_item_id,code,holder_name,status) VALUES(?,?,?, ?,"valid")')
                        ->execute([$orderId,$item['id'],ticket_code(),$order['buyer_name']]);
                }
            }
        }
        $pdo->commit();
        $issued = true;
        audit_log('order.paid','order',$orderId,['payment_id'=>$paymentId]);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    if ($issued) queue_order_tickets_email($orderId);
    return $issued;
}

function cancel_order(int $orderId, string $newStatus = 'cancelled'): void
{
    if (!in_array($newStatus, ['cancelled','expired'], true)) $newStatus = 'cancelled';
    $pdo = db(); $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM orders WHERE id=?'); $st->execute([$orderId]); $status = $st->fetchColumn();
        if (!$status) throw new RuntimeException('Pedido não encontrado.');
        if (in_array($status, ['paid','refunded'], true)) throw new RuntimeException('Pedido pago deve ser reembolsado, não cancelado.');
        if (!in_array($status, ['cancelled','expired'], true)) release_order_inventory($orderId);
        $pdo->prepare('UPDATE orders SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$newStatus,$orderId]);
        $pdo->commit(); audit_log('order.'.$newStatus,'order',$orderId);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function refund_order(int $orderId): void
{
    $pdo = db(); $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT status,inventory_released FROM orders WHERE id=?');$st->execute([$orderId]);$order=$st->fetch();
        if (!$order || !in_array($order['status'],['paid','review'],true)) throw new RuntimeException('Somente pedidos pagos ou em revisão podem ser reembolsados.');
        if ((int)$order['inventory_released'] === 0) release_order_inventory($orderId);
        $pdo->prepare("UPDATE tickets SET status='invalid' WHERE order_id=? AND status IN ('valid','used')")->execute([$orderId]);
        $pdo->prepare("UPDATE orders SET status='refunded',requires_review=0,review_reason=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$orderId]);
        $pdo->commit(); audit_log('order.refunded','order',$orderId);
        queue_order_status_email($orderId,'refunded');
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function vip_people_count(int $eventId): int
{
    $st = db()->prepare("SELECT COALESCE(SUM(1+companions),0) FROM vip_entries WHERE event_id=? AND status!='removed'"); $st->execute([$eventId]);
    return (int)$st->fetchColumn();
}

function vip_capacity_remaining(array $event): ?int
{
    $cap = (int)($event['vip_capacity'] ?? 0); if ($cap <= 0) return null;
    return max(0, $cap - vip_people_count((int)$event['id']));
}

function http_json_request(string $method, string $url, array $headers = [], ?array $payload = null): array
{
    $bodyData = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>10]);
        if ($bodyData !== null) curl_setopt($ch,CURLOPT_POSTFIELDS,$bodyData);
        $body = curl_exec($ch);
        if ($body === false) { $err=curl_error($ch); curl_close($ch); throw new RuntimeException('Falha HTTP: '.$err); }
        $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
    } else {
        $opts=['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'timeout'=>25]];
        if ($bodyData !== null) $opts['http']['content']=$bodyData;
        $body=@file_get_contents($url,false,stream_context_create($opts));
        if ($body === false) throw new RuntimeException('Falha HTTP ao acessar o provedor de pagamento.');
        $code=0; foreach ($http_response_header ?? [] as $line) if (preg_match('#HTTP/\S+\s+(\d+)#',$line,$m)) {$code=(int)$m[1];break;}
    }
    $data=json_decode((string)$body,true); if (!is_array($data)) $data=[];
    if ($code<200 || $code>=300) throw new RuntimeException((string)($data['message'] ?? ('HTTP '.$code)));
    return $data;
}

function mp_request(string $method, string $endpoint, ?array $payload = null): array
{
    $token = mp_access_token();
    if ($token === '') throw new RuntimeException('Access Token do Mercado Pago não configurado.');
    return http_json_request($method,'https://api.mercadopago.com'.$endpoint,[
        'Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json'
    ],$payload);
}

function mp_refund_payment(string $paymentId, string $idempotencyKey): array
{
    $token = mp_access_token();
    if ($token === '') throw new RuntimeException('Access Token do Mercado Pago não configurado.');
    if ($paymentId === '') throw new RuntimeException('ID do pagamento do Mercado Pago não disponível.');
    return http_json_request('POST','https://api.mercadopago.com/v1/payments/'.rawurlencode($paymentId).'/refunds',[
        'Authorization: Bearer '.$token,
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Idempotency-Key: '.$idempotencyKey,
    ],null);
}

function validate_mp_payment_for_order(array $payment, array $order): bool
{
    if ((string)($payment['status'] ?? '') !== 'approved') return false;
    if ((string)($payment['external_reference'] ?? '') !== (string)($order['id'] ?? '')) return false;
    $currency = strtoupper((string)($payment['currency_id'] ?? 'BRL'));
    if ($currency !== 'BRL') return false;
    $paid = (float)($payment['transaction_amount'] ?? -1);
    $expected = (float)($order['total'] ?? 0);
    return abs($paid - $expected) < 0.01;
}

function verify_mp_signature(string $xSignature, string $xRequestId, string $dataId, string $secret): bool
{
    if ($secret === '') return false;
    $ts='';$v1='';
    foreach (explode(',',$xSignature) as $part) {
        [$k,$v]=array_pad(explode('=',trim($part),2),2,'');
        if ($k==='ts') $ts=$v; elseif ($k==='v1') $v1=$v;
    }
    if ($v1==='') return false;
    $dataId = ctype_alnum($dataId) ? strtolower($dataId) : $dataId;
    $manifest='';
    if ($dataId!=='') $manifest.='id:'.$dataId.';';
    if ($xRequestId!=='') $manifest.='request-id:'.$xRequestId.';';
    if ($ts!=='') $manifest.='ts:'.$ts.';';
    $calc=hash_hmac('sha256',$manifest,$secret);
    return hash_equals($calc,$v1);
}

function webhook_log(string $provider, ?string $externalId, ?string $type, bool $valid, int $httpStatus, string $payload, ?string $error = null): void
{
    try { db()->prepare('INSERT INTO webhook_logs(provider,external_id,event_type,signature_valid,http_status,payload,error_message) VALUES(?,?,?,?,?,?,?)')
        ->execute([$provider,$externalId,$type,$valid?1:0,$httpStatus,substr($payload,0,20000),$error]); } catch(Throwable $ignored){}
}

function month_short_br(?string $value): string
{
    if (!$value) return '';
    try { $m=(int)(new DateTime($value))->format('n'); } catch(Throwable $e){return '';}
    return [1=>'JAN',2=>'FEV',3=>'MAR',4=>'ABR',5=>'MAI',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SET',10=>'OUT',11=>'NOV',12=>'DEZ'][$m] ?? '';
}

function weekday_br(?string $value): string
{
    if (!$value) return '';
    try { $w=(int)(new DateTime($value))->format('w'); } catch(Throwable $e){return '';}
    return ['DOM','SEG','TER','QUA','QUI','SEX','SÁB'][$w] ?? '';
}


function ticket_payload(string $code): string
{
    return 'CACTUS:TICKET:' . strtoupper(trim($code));
}

function normalize_ticket_code(string $raw): string
{
    $raw = strtoupper(trim($raw));
    if (str_starts_with($raw,'CACTUS:TICKET:')) $raw = substr($raw,14);
    return preg_replace('/[^A-Z0-9\-]/','',$raw) ?: '';
}

function ticket_info_by_code(string $raw): ?array
{
    $code = normalize_ticket_code($raw);
    if ($code === '') return null;
    $st = db()->prepare('SELECT t.*,o.buyer_name,o.order_number,o.status order_status,e.title event_title,e.event_date,oi.ticket_name FROM tickets t JOIN orders o ON o.id=t.order_id JOIN events e ON e.id=o.event_id JOIN order_items oi ON oi.id=t.order_item_id WHERE upper(t.code)=upper(?) LIMIT 1');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

function checkin_ticket(string $raw, ?int $adminId = null): array
{
    $code = normalize_ticket_code($raw);
    if ($code === '') return ['ok'=>false,'message'=>'Código vazio ou inválido.'];
    $pdo = db(); $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT t.*,o.buyer_name,o.order_number,o.status order_status,e.title event_title,e.event_date,oi.ticket_name FROM tickets t JOIN orders o ON o.id=t.order_id JOIN events e ON e.id=o.event_id JOIN order_items oi ON oi.id=t.order_item_id WHERE upper(t.code)=upper(?) LIMIT 1');
        $st->execute([$code]); $ticket = $st->fetch();
        if (!$ticket) { $pdo->rollBack(); return ['ok'=>false,'message'=>'Ingresso não localizado.','code'=>$code]; }
        if ($ticket['order_status'] !== 'paid') { $pdo->rollBack(); return ['ok'=>false,'message'=>'O pedido deste ingresso não está pago.','ticket'=>$ticket]; }
        if ($ticket['status'] === 'used') { $pdo->rollBack(); return ['ok'=>false,'message'=>'Ingresso já utilizado em '.dt_db_br($ticket['checked_in_at']).'.','ticket'=>$ticket,'already_used'=>true]; }
        if ($ticket['status'] !== 'valid') { $pdo->rollBack(); return ['ok'=>false,'message'=>'Ingresso inválido/cancelado para entrada.','ticket'=>$ticket]; }
        $up=$pdo->prepare("UPDATE tickets SET status='used',checked_in_at=CURRENT_TIMESTAMP,checked_in_by=? WHERE id=? AND status='valid'");
        $up->execute([$adminId,$ticket['id']]);
        if ($up->rowCount() !== 1) throw new RuntimeException('O ingresso foi alterado por outra operação. Tente novamente.');
        $pdo->prepare('INSERT INTO checkin_logs(ticket_id,admin_id,action,ticket_code,metadata) VALUES(?,?,?,?,?)')
            ->execute([$ticket['id'],$adminId,'checkin',$ticket['code'],json_encode(['event'=>$ticket['event_title'],'order'=>$ticket['order_number']],JSON_UNESCAPED_UNICODE)]);
        $pdo->commit();
        $ticket['status']='used'; $ticket['checked_in_at']=date('Y-m-d H:i:s');
        audit_log('ticket.checkin','ticket',(int)$ticket['id'],['code'=>$ticket['code']]);
        return ['ok'=>true,'message'=>'Entrada liberada.','ticket'=>$ticket];
    } catch(Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return ['ok'=>false,'message'=>$e->getMessage()]; }
}

function undo_ticket_checkin(int $ticketId, ?int $adminId = null): void
{
    $pdo=db();$pdo->beginTransaction();
    try{
        $st=$pdo->prepare("SELECT * FROM tickets WHERE id=? AND status='used'");$st->execute([$ticketId]);$t=$st->fetch();
        if(!$t) throw new RuntimeException('Ingresso utilizado não encontrado.');
        $pdo->prepare("UPDATE tickets SET status='valid',checked_in_at=NULL,checked_in_by=NULL WHERE id=?")->execute([$ticketId]);
        $pdo->prepare('INSERT INTO checkin_logs(ticket_id,admin_id,action,ticket_code,metadata) VALUES(?,?,?,?,?)')->execute([$ticketId,$adminId,'undo',$t['code'],null]);
        $pdo->commit();audit_log('ticket.checkin_undo','ticket',$ticketId,['code'=>$t['code']]);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function legal_version(string $kind): string
{
    $allowed=['terms','privacy','cancellation'];
    if(!in_array($kind,$allowed,true)) return '1';
    return app_setting($kind.'_version','2026-09-07');
}

function record_legal_acceptance(int $orderId, bool $marketing): void
{
    db()->prepare('INSERT OR REPLACE INTO legal_acceptances(order_id,terms_version,privacy_version,cancellation_version,marketing_opt_in,ip_address,user_agent,created_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')
        ->execute([$orderId,legal_version('terms'),legal_version('privacy'),legal_version('cancellation'),$marketing?1:0,request_ip(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
}

function cron_token(): string
{
    $v=app_setting('cron_token','');
    if($v===''){ $v=bin2hex(random_bytes(24)); set_app_setting('cron_token',$v,true); }
    return $v;
}

function queue_email(string $to, string $name, string $subject, string $html, string $text='', ?string $dedupeKey=null): bool
{
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)) return false;
    try{
        db()->prepare('INSERT INTO email_queue(dedupe_key,to_email,to_name,subject,html_body,text_body,status,next_attempt_at) VALUES(?,?,?,?,?,?,"pending",CURRENT_TIMESTAMP)')
            ->execute([$dedupeKey,$to,$name,$subject,$html,$text]);
        return true;
    }catch(PDOException $e){
        // Unique dedupe key: an already queued/sent transactional message is considered success.
        if($dedupeKey!=='' && str_contains(strtolower($e->getMessage()),'unique')) return true;
        return false;
    }
}

function html_email_shell(string $title, string $body): string
{
    $logo=base_url(brand_logo());
    $name=site_name();
    return '<!doctype html><html><body style="margin:0;background:#16110f;font-family:Arial,sans-serif;color:#241812"><table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:30px 12px"><table width="600" style="max-width:600px;background:#fff;border-radius:18px;overflow:hidden"><tr><td style="background:#17110f;padding:24px;text-align:center"><img src="'.e($logo).'" alt="'.e($name).'" style="width:220px;max-width:80%;max-height:120px;object-fit:contain"></td></tr><tr><td style="padding:32px"><h1 style="margin:0 0 18px;font-size:26px">'.e($title).'</h1>'.$body.'<p style="margin-top:30px;color:#796b62;font-size:12px">'.e($name).' • mensagem transacional automática • Plataforma Cactus Eventos</p></td></tr></table></td></tr></table></body></html>';
}

function order_mail_context(int $orderId): ?array
{
    $st=db()->prepare('SELECT o.*,e.title event_title,e.event_date,e.venue FROM orders o JOIN events e ON e.id=o.event_id WHERE o.id=?');$st->execute([$orderId]);
    $o=$st->fetch();if(!$o)return null;
    $st=db()->prepare('SELECT t.code,oi.ticket_name FROM tickets t JOIN order_items oi ON oi.id=t.order_item_id WHERE t.order_id=? ORDER BY t.id');$st->execute([$orderId]);
    $o['tickets']=$st->fetchAll();return $o;
}

function queue_order_tickets_email(int $orderId, bool $force=false): bool
{
    $o=order_mail_context($orderId);if(!$o||$o['status']!=='paid')return false;
    $link=base_url('ticket.php?token='.urlencode($o['public_token']));
    $codes='';foreach($o['tickets'] as $t){$codes.='<li><b>'.e($t['ticket_name']).'</b> — <code>'.e($t['code']).'</code></li>';}
    $body='<p>Olá, '.e($o['buyer_name']).'. Seu pagamento foi confirmado.</p><p><b>'.e($o['event_title']).'</b><br>'.e(dt_br($o['event_date'])).' • '.e($o['venue']).'</p><p><a href="'.e($link).'" style="display:inline-block;background:#d6a35a;color:#1b110b;text-decoration:none;padding:14px 20px;border-radius:999px;font-weight:bold">ABRIR MEUS INGRESSOS</a></p><p>Seus códigos:</p><ul>'.$codes.'</ul><p>Na entrada, apresente o QR Code de cada ingresso. Cada QR é válido uma única vez.</p>';
    $dedupe=$force?'tickets-'.$orderId.'-'.bin2hex(random_bytes(3)):'tickets-'.$orderId;
    $ok=queue_email($o['buyer_email'],$o['buyer_name'],'Seus ingressos • '.$o['event_title'],html_email_shell('Ingressos confirmados',$body),'Seus ingressos: '.$link,$dedupe);
    return $ok;
}

function queue_order_status_email(int $orderId, string $status): bool
{
    $o=order_mail_context($orderId);if(!$o)return false;
    if($status==='refunded'){
        $title='Pedido reembolsado';$msg='<p>Olá, '.e($o['buyer_name']).'. O pedido <b>'.e($o['order_number']).'</b> foi registrado como reembolsado. Os ingressos vinculados não são mais válidos.</p>';
    }elseif($status==='review'){
        $title='Pagamento em revisão';$msg='<p>Olá, '.e($o['buyer_name']).'. Identificamos o pagamento do pedido <b>'.e($o['order_number']).'</b>, mas a reserva do ingresso havia expirado. A equipe responsável pelo evento fará a conferência antes da emissão. Nenhum ingresso foi emitido duplicadamente.</p>';
    }else{
        $title='Atualização do pedido';$msg='<p>O pedido <b>'.e($o['order_number']).'</b> foi atualizado para '.e(status_label($status)).'.</p>';
    }
    return queue_email($o['buyer_email'],$o['buyer_name'],$title.' • '.site_name(),html_email_shell($title,$msg),strip_tags($msg),'status-'.$status.'-'.$orderId);
}

function process_email_queue(int $limit=20): array
{
    if(!function_exists('send_transactional_message')) return ['sent'=>0,'failed'=>0,'error'=>'Mailer não carregado'];
    $limit=max(1,min(100,$limit));
    $rows=db()->query("SELECT * FROM email_queue WHERE status IN ('pending','failed') AND attempts<max_attempts AND (next_attempt_at IS NULL OR datetime(next_attempt_at)<=datetime('now')) ORDER BY id LIMIT ".$limit)->fetchAll();
    $sent=0;$failed=0;
    foreach($rows as $row){
        try{
            send_transactional_message($row['to_email'],$row['to_name']??'',$row['subject'],$row['html_body'],$row['text_body']??'');
            db()->prepare("UPDATE email_queue SET status='sent',attempts=attempts+1,sent_at=CURRENT_TIMESTAMP,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$row['id']]);
            if(preg_match('/^tickets-(\d+)(?:-|$)/',(string)($row['dedupe_key']??''),$m)){db()->prepare('UPDATE orders SET email_sent_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$m[1]]);}
            $sent++;
        }catch(Throwable $e){
            $attempt=(int)$row['attempts']+1;$delay=min(3600,60*(2**min(5,$attempt-1)));
            $next=db_plus_utc('+'.$delay.' seconds');
            db()->prepare("UPDATE email_queue SET status='failed',attempts=?,next_attempt_at=?,last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$attempt,$next,substr($e->getMessage(),0,1000),$row['id']]);
            $failed++;
        }
    }
    return ['sent'=>$sent,'failed'=>$failed];
}

function production_checklist(): array
{
    $base=payment_base_url();$mode=payment_mode();
    $items=[];
    $items[]=['PHP 8.2+', version_compare(PHP_VERSION,'8.2.0','>='), PHP_VERSION];
    $items[]=['SQLite / PDO', extension_loaded('pdo_sqlite'), extension_loaded('pdo_sqlite')?'pdo_sqlite carregado':'extensão ausente'];
    $items[]=['OpenSSL', extension_loaded('openssl'), extension_loaded('openssl')?'carregado':'extensão ausente'];
    $httpOk=function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN);
    $items[]=['HTTP externo / Mercado Pago', $httpOk, function_exists('curl_init')?'cURL disponível':($httpOk?'allow_url_fopen disponível':'indisponível')];
    $items[]=['HTTPS / URL pública',str_starts_with($base,'https://'),$base?:'não configurada'];
    $items[]=['Identidade visual', site_name()!=='SUA MARCA' && !str_contains(brand_logo(),'placeholder'), site_name().' / '.brand_logo()];
    $items[]=['Mercado Pago', $mode!=='mercadopago' || (mp_access_token()!=='' && mp_webhook_secret()!==''), $mode==='mercadopago'?'Checkout Pro':'modo manual'];
    $items[]=['E-mail transacional', email_transport_configured(), email_transport_detail()];
    $items[]=['E-mail de suporte', filter_var(app_setting('support_email',''),FILTER_VALIDATE_EMAIL)!==false, app_setting('support_email','não configurado')];
    $items[]=['Dados legais', app_setting('legal_name','')!=='' && app_setting('legal_document','')!=='', app_setting('legal_name','não configurado')];
    $items[]=['Cron', cron_token()!=='', 'script pronto — agendar no cPanel'];
    $storage=dirname(__DIR__).'/storage';
    $uploads=dirname(__DIR__).'/assets/uploads';
    $items[]=['Storage gravável',is_dir($storage)&&is_writable($storage),$storage];
    $items[]=['Uploads graváveis',is_dir($uploads)&&is_writable($uploads),$uploads];
    return $items;
}
