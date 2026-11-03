<?php
declare(strict_types=1);

/**
 * Shared bootstrap for the public site and the admin panel.
 * Every entry point starts with: require __DIR__ . '/app/bootstrap.php';
 */

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/Helpers.php';

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

if ($config['supabase_url'] === '' || $config['supabase_anon_key'] === '') {
    http_response_code(500);
    echo '<h1>Configuration missing</h1>';
    echo '<p>Copy <code>.env.example</code> to <code>.env</code> and set '
        . '<code>SUPABASE_URL</code> and <code>SUPABASE_ANON_KEY</code>.</p>';
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();
}

$db   = new Supabase($config);
$auth = new Auth($db);
