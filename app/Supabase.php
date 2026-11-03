<?php
declare(strict_types=1);

class SupabaseException extends RuntimeException
{
}

/**
 * Minimal Supabase client (REST + Storage) using cURL, so the project has no
 * Composer dependency and runs on any shared PHP host.
 */
class Supabase
{
    private string $url;
    private string $anonKey;
    private string $serviceKey;
    private string $bucket;

    public function __construct(array $config)
    {
        $this->url        = (string) $config['supabase_url'];
        $this->anonKey    = (string) $config['supabase_anon_key'];
        $this->serviceKey = (string) $config['supabase_service_key'];
        $this->bucket     = (string) $config['bucket'];
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    public function hasServiceKey(): bool
    {
        return $this->serviceKey !== '';
    }

    /**
     * @param  array<int, string> $extraHeaders
     * @return array{status: int, body: mixed, headers: array<string, string>}
     */
    public function request(string $method, string $path, ?string $body = null, bool $service = false, array $extraHeaders = []): array
    {
        $key = $service ? $this->serviceKey : $this->anonKey;
        if ($key === '') {
            throw new SupabaseException('This operation needs a Supabase key that is not configured.');
        }

        $headers = array_merge([
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
        ], $extraHeaders);

        $responseHeaders = [];
        $ch = curl_init($this->url . $path);
        if ($ch === false) {
            throw new SupabaseException('Could not start the HTTP request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $message = curl_error($ch);
            throw new SupabaseException('Network error talking to Supabase: ' . $message);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if ($decoded === null) {
                $decoded = $raw;
            }
        }

        if ($status >= 400) {
            $message = 'Request failed';
            if (is_array($decoded)) {
                foreach (['message', 'error_description', 'msg', 'error'] as $field) {
                    if (isset($decoded[$field]) && is_string($decoded[$field])) {
                        $message = $decoded[$field];
                        break;
                    }
                }
            } elseif (is_string($decoded) && $decoded !== '') {
                $message = $decoded;
            }
            throw new SupabaseException('Supabase responded ' . $status . ': ' . $message);
        }

        return ['status' => $status, 'body' => $decoded, 'headers' => $responseHeaders];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, count: ?int}
     */
    public function select(string $table, string $query, bool $service = false, bool $withCount = false): array
    {
        $extra = $withCount ? ['Prefer: count=exact'] : [];
        $response = $this->request('GET', '/rest/v1/' . $table . '?' . $query, null, $service, $extra);

        $rows = is_array($response['body']) ? $response['body'] : [];
        $count = null;
        if (isset($response['headers']['content-range'])) {
            $range = $response['headers']['content-range'];
            $slash = strrpos($range, '/');
            if ($slash !== false) {
                $total = substr($range, $slash + 1);
                if ($total !== '*' && $total !== '') {
                    $count = (int) $total;
                }
            }
        }

        return ['rows' => $rows, 'count' => $count];
    }

    public function insert(string $table, array $row, bool $service = true): array
    {
        $payload = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new SupabaseException('Could not encode the record.');
        }
        $response = $this->request('POST', '/rest/v1/' . $table, $payload, $service, [
            'Content-Type: application/json',
            'Prefer: return=representation',
        ]);
        return is_array($response['body']) ? $response['body'] : [];
    }

    public function update(string $table, string $query, array $row, bool $service = true): array
    {
        $payload = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new SupabaseException('Could not encode the record.');
        }
        $response = $this->request('PATCH', '/rest/v1/' . $table . '?' . $query, $payload, $service, [
            'Content-Type: application/json',
            'Prefer: return=representation',
        ]);
        return is_array($response['body']) ? $response['body'] : [];
    }

    public function delete(string $table, string $query, bool $service = true): void
    {
        $this->request('DELETE', '/rest/v1/' . $table . '?' . $query, null, $service);
    }

    /** Upload a file into the private storage bucket. */
    public function uploadObject(string $objectPath, string $contents, string $mime = 'application/pdf'): void
    {
        $this->request('POST', '/storage/v1/object/' . $this->bucket . '/' . ltrim($objectPath, '/'), $contents, true, [
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ]);
    }

    public function removeObject(string $objectPath): void
    {
        if ($objectPath === '') {
            return;
        }
        $this->request('DELETE', '/storage/v1/object/' . $this->bucket . '/' . ltrim($objectPath, '/'), null, true);
    }

    /** Short lived signed URL for a private object. */
    public function signedUrl(string $objectPath, int $ttl = 900): ?string
    {
        if ($objectPath === '' || !$this->hasServiceKey()) {
            return null;
        }
        $response = $this->request(
            'POST',
            '/storage/v1/object/sign/' . $this->bucket . '/' . ltrim($objectPath, '/'),
            json_encode(['expiresIn' => $ttl]),
            true,
            ['Content-Type: application/json']
        );
        $body = $response['body'];
        if (!is_array($body) || !isset($body['signedURL']) || !is_string($body['signedURL'])) {
            return null;
        }
        return $this->url . '/storage/v1' . $body['signedURL'];
    }

    /** Public URL, only useful when the bucket is public. */
    public function publicUrl(string $objectPath): string
    {
        return $this->url . '/storage/v1/object/public/' . $this->bucket . '/' . ltrim($objectPath, '/');
    }
}
