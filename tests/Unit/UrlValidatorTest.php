<?php

namespace Tests\Unit;

use App\Services\Security\UrlValidator;
use PHPUnit\Framework\TestCase;

class UrlValidatorTest extends TestCase
{
    private UrlValidator $v;

    protected function setUp(): void
    {
        parent::setUp();
        $this->v = new UrlValidator();
    }

    public static function blockedUrls(): array
    {
        return [
            'localhost'            => ['http://localhost/admin'],
            'loopback ipv4'        => ['http://127.0.0.1/'],
            'loopback alt'         => ['http://127.1.2.3/'],
            'private 10.x'         => ['http://10.0.0.5/'],
            'private 192.168'      => ['http://192.168.1.1/'],
            'private 172.16'       => ['http://172.16.0.1/'],
            'link-local metadata'  => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback'        => ['http://[::1]/'],
            'file scheme'          => ['file:///etc/passwd'],
            'gopher scheme'        => ['gopher://example.com/'],
            'no scheme'            => ['example.com'],
            'ftp scheme'           => ['ftp://example.com/'],
            'bad port'             => ['http://example.com:22/'],
            'empty'                => [''],
            'internal tld'         => ['http://server.internal/'],
            'metadata hostname'    => ['http://metadata.google.internal/'],
        ];
    }

    /** @dataProvider blockedUrls */
    public function test_it_blocks_unsafe_urls(string $url): void
    {
        $result = $this->v->validate($url);
        $this->assertFalse($result['ok'], "Expected {$url} to be rejected");
        $this->assertNotEmpty($result['error']);
    }

    public function test_it_accepts_a_normal_public_url(): void
    {
        $result = $this->v->validate('https://example.com/pricing');
        $this->assertTrue($result['ok']);
        $this->assertSame('https://example.com/pricing', $result['url']);
    }

    public function test_it_normalises_scheme_and_host_case(): void
    {
        $result = $this->v->validate('HTTPS://Example.COM/Path');
        $this->assertTrue($result['ok']);
        $this->assertStringStartsWith('https://example.com', $result['url']);
    }
}
