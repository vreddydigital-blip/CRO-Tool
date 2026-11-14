<?php

namespace App\Services\Security;

/**
 * Minimal HTTP client built on cURL that enforces SSRF rules on every hop.
 *
 * We do NOT use Laravel's Http facade here because we need to inspect each
 * redirect Location header and re-validate it before following. cURL's own
 * FOLLOWLOCATION would follow a redirect to 127.0.0.1 without us seeing it.
 */
class SafeHttpClient
{
    public function __construct(
        private UrlValidator $validator,
        private int $timeoutSeconds = 10,
        private int $connectTimeoutSeconds = 5,
        private int $maxBytes = 2_000_000,
        private int $maxRedirects = 3,
        private string $userAgent = 'CROIntelligenceBot/1.0 (+https://cro-intelligence.example/bot)',
    ) {}

    /**
     * @return array{ok: bool, status?: int, body?: string, headers?: array, final_url?: string, error?: string, time_ms?: int, bytes?: int}
     */
    public function get(string $url, array $options = []): array
    {
        $current = $url;
        $redirects = 0;
        $start = microtime(true);

        while (true) {
            $check = $redirects === 0
                ? $this->validator->validate($current)
                : $this->validator->validateRedirect($url, $current);

            if (! $check['ok']) {
                return ['ok' => false, 'error' => $check['error']];
            }

            $result = $this->request($current, $options);

            if (! $result['ok']) {
                return $result;
            }

            $status = $result['status'];

            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = $this->headerValue($result['headers'], 'location');

                if (! $location) {
                    return ['ok' => false, 'error' => "HTTP {$status} with no Location header."];
                }

                $redirects++;
                if ($redirects > $this->maxRedirects) {
                    return ['ok' => false, 'error' => 'Too many redirects.'];
                }

                $current = $this->absolutize($current, $location);
                continue;
            }

            $result['final_url'] = $current;
            $result['time_ms'] = (int) round((microtime(true) - $start) * 1000);
            $result['bytes'] = strlen($result['body'] ?? '');

            return $result;
        }
    }

    private function request(string $url, array $options): array
    {
        $ch = curl_init();

        $headers = [
            'User-Agent: '.$this->userAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: identity',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false, // we follow manually, with validation
            CURLOPT_TIMEOUT => $options['timeout'] ?? $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => $this->maxBytes,
            CURLOPT_ENCODING => '',
        ]);

        // Pin the connection to the IP we validated, so DNS rebinding between
        // our check and cURL's own resolution cannot redirect us internally.
        $host = parse_url($url, PHP_URL_HOST);
        $resolved = $this->validator->validate($url);
        if ($resolved['ok'] && ! empty($resolved['resolved_ip']) && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $port = parse_url($url, PHP_URL_PORT) ?: (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);
            curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$resolved['resolved_ip']}"]);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            return ['ok' => false, 'error' => "Request failed: {$error}"];
        }

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        if (strlen($body) > $this->maxBytes) {
            $body = substr($body, 0, $this->maxBytes);
        }

        return [
            'ok' => true,
            'status' => $status,
            'body' => $body,
            'headers' => $this->parseHeaders($rawHeaders),
        ];
    }

    private function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
        return $headers;
    }

    private function headerValue(array $headers, string $name): ?string
    {
        return $headers[strtolower($name)] ?? null;
    }

    private function absolutize(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $p = parse_url($base);
        $root = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');

        if (str_starts_with($location, '//')) {
            return ($p['scheme'] ?? 'https').':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $root.$location;
        }

        $dir = rtrim(dirname($p['path'] ?? '/'), '/');

        return $root.$dir.'/'.$location;
    }
}
