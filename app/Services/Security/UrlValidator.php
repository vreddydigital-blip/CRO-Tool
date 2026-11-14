<?php

namespace App\Services\Security;

/**
 * Validates a user-supplied URL before any outbound request is made.
 *
 * Threat model: the crawler fetches arbitrary URLs supplied by users. Without
 * validation this is a textbook SSRF hole — a user could point the crawler at
 * 127.0.0.1, 169.254.169.254 (cloud metadata), or an internal RFC1918 host and
 * read the response back through the findings UI.
 *
 * Defence in depth:
 *  1. Scheme allow-list (http/https only).
 *  2. Reject credentials in the URL, non-standard ports.
 *  3. Resolve the hostname and reject if ANY resolved address is private,
 *     loopback, link-local, reserved, multicast or a metadata endpoint.
 *  4. Re-validate after every redirect (see SafeHttpClient) — a public host can
 *     redirect to 127.0.0.1, so the first check alone is not enough.
 */
class UrlValidator
{
    /** Ports we allow. Anything else is refused to avoid probing internal services. */
    private const ALLOWED_PORTS = [80, 443, 8080, 8443];

    /** Hostnames that must never be fetched, regardless of DNS. */
    private const BLOCKED_HOSTS = [
        'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback',
        'metadata', 'metadata.google.internal', 'instance-data',
    ];

    /** Cloud metadata IPs (AWS/GCP/Azure/Alibaba). */
    private const BLOCKED_IPS = [
        '169.254.169.254', '169.254.170.2', '100.100.100.200', 'fd00:ec2::254',
    ];

    public function __construct(private int $maxRedirects = 3) {}

    /**
     * @return array{ok: bool, url?: string, error?: string, resolved_ip?: string}
     */
    public function validate(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return $this->fail('URL is empty.');
        }

        if (strlen($url) > 2048) {
            return $this->fail('URL is longer than 2048 characters.');
        }

        // Reject control characters / whitespace that can smuggle a second URL.
        if (preg_match('/[\x00-\x1F\x7F\s]/', $url)) {
            return $this->fail('URL contains whitespace or control characters.');
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return $this->fail('URL could not be parsed or has no host.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $this->fail('Only http and https URLs are supported.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->fail('URLs containing credentials are not allowed.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array((int) $port, self::ALLOWED_PORTS, true)) {
            return $this->fail("Port {$port} is not allowed. Use 80, 443, 8080 or 8443.");
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return $this->fail('This host is not allowed.');
        }

        // A bare IP literal: check it directly, no DNS needed.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->checkIp($host, $url);
        }

        // Hostname must look like a real domain (at least one dot, valid chars).
        if (! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $host)) {
            return $this->fail('Hostname is not a valid public domain name.');
        }

        // Resolve every A/AAAA record and reject if any is unsafe.
        $ips = $this->resolve($host);

        if ($ips === []) {
            return $this->fail('Hostname could not be resolved.');
        }

        foreach ($ips as $ip) {
            $check = $this->checkIp($ip, $url);
            if (! $check['ok']) {
                return $check;
            }
        }

        return ['ok' => true, 'url' => $this->normalize($url), 'resolved_ip' => $ips[0]];
    }

    /**
     * Validate a redirect target. Same rules, but we also refuse to follow a
     * redirect that changes scheme from https to http (downgrade).
     */
    public function validateRedirect(string $fromUrl, string $toUrl): array
    {
        $from = parse_url($fromUrl);
        $to = parse_url($toUrl);

        if (($from['scheme'] ?? '') === 'https' && ($to['scheme'] ?? '') === 'http') {
            return $this->fail('Refusing to follow an HTTPS-to-HTTP redirect.');
        }

        return $this->validate($toUrl);
    }

    private function checkIp(string $ip, string $url): array
    {
        if (in_array($ip, self::BLOCKED_IPS, true)) {
            return $this->fail('This address is a cloud metadata endpoint and is blocked.');
        }

        // FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE covers RFC1918, loopback,
        // link-local (169.254/16), and reserved ranges for both v4 and v6.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $this->fail('This address is private, loopback, link-local or reserved and is blocked.');
        }

        return ['ok' => true, 'url' => $this->normalize($url), 'resolved_ip' => $ip];
    }

    /** @return string[] */
    private function resolve(string $host): array
    {
        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        // AAAA records (best effort; not all environments expose them).
        if (function_exists('dns_get_record')) {
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $rec) {
                    if (! empty($rec['ipv6'])) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /** Lowercase scheme+host, strip default port, drop fragment, keep path/query. */
    public function normalize(string $url): string
    {
        $p = parse_url($url);
        if ($p === false || empty($p['host'])) {
            return $url;
        }

        $scheme = strtolower($p['scheme'] ?? 'https');
        $host = strtolower($p['host']);
        $port = $p['port'] ?? null;

        $defaultPort = ($scheme === 'https') ? 443 : 80;
        $portPart = ($port && (int) $port !== $defaultPort) ? ':'.$port : '';

        $path = $p['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        $query = isset($p['query']) ? '?'.$p['query'] : '';

        return "{$scheme}://{$host}{$portPart}{$path}{$query}";
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
