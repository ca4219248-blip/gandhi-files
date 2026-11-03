<?php
declare(strict_types=1);

/** Escape for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['pra_csrf']) || !is_string($_SESSION['pra_csrf'])) {
        $_SESSION['pra_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pra_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if ($token === '' || empty($_SESSION['pra_csrf']) || !hash_equals((string) $_SESSION['pra_csrf'], $token)) {
        http_response_code(419);
        echo '<p>Session expired. Go back, reload the page and try again.</p>';
        exit;
    }
}

function flash(string $type, string $message): void
{
    if (!isset($_SESSION['pra_flash']) || !is_array($_SESSION['pra_flash'])) {
        $_SESSION['pra_flash'] = [];
    }
    $_SESSION['pra_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type: string, message: string}> */
function take_flashes(): array
{
    $items = isset($_SESSION['pra_flash']) && is_array($_SESSION['pra_flash']) ? $_SESSION['pra_flash'] : [];
    unset($_SESSION['pra_flash']);
    return $items;
}

function format_date(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($date);
    } catch (Exception $e) {
        return (string) $date;
    }
    return $dt->format('d M Y');
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($value);
    } catch (Exception $e) {
        return (string) $value;
    }
    return $dt->format('d M Y, H:i') . ' UTC';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'record';
    }
    return substr($text, 0, 80);
}

function excerpt(string $text, int $length = 220): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $length)) . '...';
    }
    if (strlen($text) <= $length) {
        return $text;
    }
    return rtrim(substr($text, 0, $length)) . '...';
}

function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function query(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function source_label(string $type): string
{
    $map = [
        'court_order'             => 'Court order or judgment',
        'cag_report'              => 'CAG report',
        'parliament_record'       => 'Parliament record',
        'government_notification' => 'Government notification',
        'official_report'         => 'Official report',
        'news_report'             => 'News report',
        'filing'                  => 'Public filing',
        'other'                   => 'Other public document',
    ];
    return $map[$type] ?? 'Public document';
}

function source_types(): array
{
    return [
        'court_order',
        'cag_report',
        'parliament_record',
        'government_notification',
        'official_report',
        'news_report',
        'filing',
        'other',
    ];
}

function human_size(int $bytes): string
{
    if ($bytes <= 0) {
        return '';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $index = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return round($value, $value >= 10 ? 0 : 1) . ' ' . $units[$index];
}

/** Absolute or relative URL for a path on the public site. */
function site_url(string $path, array $config): string
{
    $base = (string) ($config['site_url'] ?? '');
    if ($base !== '') {
        return $base . '/' . ltrim($path, '/');
    }
    return $path;
}

function admin_url(string $path, array $config): string
{
    $base = (string) ($config['admin_url'] ?? '');
    if ($base !== '') {
        return $base . '/' . ltrim($path, '/');
    }
    return 'admin/' . ltrim($path, '/');
}
