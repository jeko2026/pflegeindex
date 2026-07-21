<?php

namespace Tests\Unit;

use App\Support\HttpUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpUrlTest extends TestCase
{
    #[DataProvider('validUrlProvider')]
    public function test_it_accepts_and_normalizes_absolute_http_urls(string $input, string $expected): void
    {
        $this->assertSame($expected, HttpUrl::normalize($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function validUrlProvider(): iterable
    {
        yield 'https with path and query' => ['https://www.example.de/pflege?city=Potsdam&page=2#kontakt', 'https://www.example.de/pflege?city=Potsdam&page=2#kontakt'];
        yield 'http remains allowed' => ['http://pflege.example.de', 'http://pflege.example.de'];
        yield 'outer whitespace is trimmed' => ['  HTTPS://xn--bcher-kva.example/path  ', 'https://xn--bcher-kva.example/path'];
        yield 'literal public IPv6' => ['https://[2606:4700:4700::1111]/', 'https://[2606:4700:4700::1111]/'];
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_it_rejects_invalid_or_unsafe_url_syntax(mixed $value): void
    {
        $this->assertNull(HttpUrl::normalize($value));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidUrlProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'array' => [['https://example.de']];
        yield 'relative' => ['/kontakt'];
        yield 'missing scheme' => ['example.de/kontakt'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/html,hello'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.de/file'];
        yield 'mailto' => ['mailto:info@example.de'];
        yield 'missing host' => ['https:///kontakt'];
        yield 'credentials' => ['https://user:secret@example.de/'];
        yield 'control character' => ["https://example.de/line\nbreak"];
        yield 'backslash ambiguity' => ['https://example.de\\@127.0.0.1/'];
        yield 'malformed percent encoding' => ['https://example.de/%zz'];
        yield 'malformed IPv6' => ['https://[::1/path'];
        yield 'raw unicode IDN' => ['https://bücher.example/'];
        yield 'too long' => ['https://example.de/'.str_repeat('a', HttpUrl::MAX_LENGTH)];
    }

    #[DataProvider('privateTargetProvider')]
    public function test_server_fetch_policy_rejects_local_and_private_targets(string $url): void
    {
        $this->assertTrue(HttpUrl::isValid($url));
        $this->assertFalse(HttpUrl::isValid($url, requirePublicTarget: true));
    }

    /** @return iterable<string, array{string}> */
    public static function privateTargetProvider(): iterable
    {
        yield 'localhost' => ['http://localhost/'];
        yield 'localhost subdomain' => ['http://service.localhost/'];
        yield 'local hostname' => ['http://router.local/'];
        yield 'loopback IPv4' => ['http://127.0.0.1/'];
        yield 'private IPv4' => ['http://10.0.0.1/'];
        yield 'link local and metadata IPv4' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'private IPv4 172' => ['http://172.16.0.1/'];
        yield 'private IPv4 192' => ['http://192.168.1.1/'];
        yield 'carrier grade NAT IPv4' => ['http://100.64.0.1/'];
        yield 'documentation IPv4' => ['http://192.0.2.1/'];
        yield 'unspecified IPv4' => ['http://0.0.0.0/'];
        yield 'multicast IPv4' => ['http://224.0.0.1/'];
        yield 'loopback IPv6' => ['http://[::1]/'];
        yield 'unique local IPv6' => ['http://[fd00::1]/'];
        yield 'link local IPv6' => ['http://[fe80::1]/'];
        yield 'multicast IPv6' => ['http://[ff02::1]/'];
        yield 'IPv4 mapped IPv6' => ['http://[::ffff:127.0.0.1]/'];
        yield 'decimal IPv4 form' => ['http://2130706433/'];
    }

    public function test_server_fetch_policy_accepts_a_public_target_without_resolving_dns(): void
    {
        $this->assertTrue(HttpUrl::isValid('https://www.example.de/path', requirePublicTarget: true));
        $this->assertTrue(HttpUrl::isValid('https://8.8.8.8/', requirePublicTarget: true));
    }
}
