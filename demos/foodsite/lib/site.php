<?php

declare(strict_types=1);

const SITE_ROOT = __DIR__ . '/..';
const SITE_DATA = SITE_ROOT . '/data/site.json';
const ADMIN_DATA = SITE_ROOT . '/data/admin.json';
const UPLOAD_DIR = SITE_ROOT . '/uploads';

function ensure_storage(): void {
    foreach ([dirname(SITE_DATA), UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
    if (!file_exists(ADMIN_DATA)) {
        file_put_contents(ADMIN_DATA, "{}\n", LOCK_EX);
    }
}

function read_json_file(string $path, array $fallback = []): array {
    if (!is_file($path)) return $fallback;
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') return $fallback;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function write_json_file(string $path, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) return false;
    return @rename($tmp, $path);
}

function load_site_config(): array {
    ensure_storage();
    return read_json_file(SITE_DATA, []);
}

function save_site_config(array $config): bool {
    ensure_storage();
    return write_json_file(SITE_DATA, $config);
}

function load_admin_config(): array {
    ensure_storage();
    return read_json_file(ADMIN_DATA, []);
}

function save_admin_config(array $config): bool {
    ensure_storage();
    return write_json_file(ADMIN_DATA, $config);
}

function valid_http_url(string $url): bool {
    if ($url === '') return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function valid_hex_color(string $value): bool {
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value);
}

function safe_text(mixed $value, int $max = 180): string {
    $text = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

/**
 * Fotos com posição definida no layout. A galeria horizontal é administrada
 * separadamente, podendo ter qualquer quantidade e ordem.
 */
function image_slot_labels(): array {
    return [
        'heroMain' => 'Topo — foto principal',
        'heroSmall' => 'Topo — foto secundária',
        'featureBanner' => 'Banner central',
        'aboutImage' => 'Seção Sobre a empresa',
        'showcase1' => 'Produtos — destaque 1',
        'showcase2' => 'Produtos — destaque 2',
        'showcase3' => 'Produtos — destaque 3',
        'showcase4' => 'Produtos — destaque 4',
        'showcase5' => 'Produtos — destaque 5',
        'showcase6' => 'Produtos — destaque 6',
    ];
}

function legacy_gallery_titles(): array {
    return [
        1 => 'Especial de Mignon',
        2 => 'Bauru Mignon',
        3 => 'X Calabresa',
        4 => 'Duplo Cheddar',
        5 => 'Bauru de Frango',
        6 => 'Especial de Picanha',
        7 => 'Bauru de Mignon',
        8 => 'Bauru de Mignon',
        9 => 'Bauru de Mignon',
        10 => 'Bauru de Mignon',
        11 => 'Bauru de Frango',
    ];
}

/**
 * Migra silenciosamente a faixa antiga strip1..strip11 para a galeria nova.
 * Se a chave gallery existir, inclusive vazia, ela é respeitada.
 */
function gallery_items(array $config): array {
    if (array_key_exists('gallery', $config) && is_array($config['gallery'])) {
        $items = [];
        foreach ($config['gallery'] as $item) {
            if (!is_array($item)) continue;
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($item['id'] ?? ''));
            $src = trim((string)($item['src'] ?? ''));
            if ($id === '' || $src === '') continue;
            $items[] = [
                'id' => $id,
                'src' => $src,
                'title' => substr(trim((string)($item['title'] ?? 'Foto do produto')), 0, 90),
            ];
        }
        return $items;
    }

    $items = [];
    $titles = legacy_gallery_titles();
    for ($i = 1; $i <= 11; $i++) {
        $src = trim((string)($config['images']['strip' . $i] ?? ''));
        if ($src === '') continue;
        $items[] = [
            'id' => 'legacy-strip-' . $i,
            'src' => $src,
            'title' => $titles[$i] ?? ('Foto ' . $i),
        ];
    }
    return $items;
}

function normalize_multi_upload(array $files): array {
    if (!isset($files['name']) || !is_array($files['name'])) return [];
    $result = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $result[] = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }
    return $result;
}

function save_uploaded_image(array $file, string $slot): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null, null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [false, null, 'Falha no upload da imagem.'];
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        return [false, null, 'A imagem deve ter no máximo 8 MB.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, null, 'Arquivo de upload inválido.'];
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
    } else {
        $mime = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: '') : '';
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        return [false, null, 'Formato não permitido. Use JPG, PNG ou WEBP.'];
    }

    ensure_storage();
    $safeSlot = preg_replace('/[^a-zA-Z0-9_-]/', '', $slot) ?: 'foto';
    $filename = $safeSlot . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        return [false, null, 'Não foi possível salvar a imagem. Verifique as permissões da pasta uploads.'];
    }
    @chmod($dest, 0644);
    return [true, 'uploads/' . $filename, null];
}

function config_references_path(array $config, string $path): bool {
    if ((string)($config['brand']['logo'] ?? '') === $path) return true;
    foreach (($config['images'] ?? []) as $src) {
        if ((string)$src === $path) return true;
    }
    foreach (gallery_items($config) as $item) {
        if ((string)($item['src'] ?? '') === $path) return true;
    }
    return false;
}

function remove_uploaded_path_if_unused(string $path, array $config): void {
    if (!str_starts_with($path, 'uploads/')) return;
    if (config_references_path($config, $path)) return;
    $relative = substr($path, strlen('uploads/'));
    if ($relative === '' || str_contains($relative, '/') || str_contains($relative, '\\')) return;
    $file = UPLOAD_DIR . '/' . $relative;
    if (is_file($file)) @unlink($file);
}
