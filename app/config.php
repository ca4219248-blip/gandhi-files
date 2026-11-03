<?php
declare(strict_types=1);

/**
 * Reads a value from the .env file in the project root, then from the real
 * environment. Returns $default when neither is set.
 */
function pra_env(string $key, ?string $default = null): ?string
{
    static $values = null;

    if ($values === null) {
        $values = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines === false ? [] : $lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    $value = trim($value, "\"'");
                }
                $values[$name] = $value;
            }
        }
    }

    if (isset($values[$key]) && $values[$key] !== '') {
        return $values[$key];
    }

    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }

    return $default;
}

return [
    'supabase_url'         => rtrim((string) pra_env('SUPABASE_URL', ''), '/'),
    'supabase_anon_key'    => (string) pra_env('SUPABASE_ANON_KEY', ''),
    'supabase_service_key' => (string) pra_env('SUPABASE_SERVICE_KEY', ''),
    'bucket'               => (string) pra_env('SUPABASE_BUCKET', 'pra-documents'),
    'site_name'            => (string) pra_env('SITE_NAME', 'Public Records Archive'),
    'site_tagline'         => (string) pra_env('SITE_TAGLINE', 'A sourced archive of public documents'),
    'site_url'             => rtrim((string) pra_env('SITE_URL', ''), '/'),
    'admin_url'            => rtrim((string) pra_env('ADMIN_URL', ''), '/'),
    'contact_email'        => (string) pra_env('CONTACT_EMAIL', ''),
    'per_page'             => max(1, (int) pra_env('PER_PAGE', '10')),
    'signed_url_ttl'       => max(60, (int) pra_env('SIGNED_URL_TTL', '900')),
    'max_upload_bytes'     => 25 * 1024 * 1024,
];
