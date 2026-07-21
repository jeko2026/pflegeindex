<?php

namespace App\Support;

use Throwable;

final class HttpUrl
{
    public const MAX_LENGTH = 2000;

    public static function normalize(mixed $value, bool $requirePublicTarget = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === ''
            || strlen($url) > self::MAX_LENGTH
            || preg_match('/[\x00-\x20\x7F]/', $url) === 1
            || str_contains($url, '\\')
            || preg_match('/%(?![0-9A-Fa-f]{2})/', $url) === 1) {
            return null;
        }

        try {
            $parts = parse_url($url);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! is_string($parts['scheme'])
            || ! is_string($parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $host = self::unbracketedHost($parts['host']);

        if (! self::isValidHost($host) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        if ($requirePublicTarget && ! self::isPublicTarget($host)) {
            return null;
        }

        return strtolower($parts['scheme']).substr($url, strlen($parts['scheme']));
    }

    public static function isValid(mixed $value, bool $requirePublicTarget = false): bool
    {
        return self::normalize($value, $requirePublicTarget) !== null;
    }

    private static function unbracketedHost(string $host): string
    {
        return str_starts_with($host, '[') && str_ends_with($host, ']')
            ? substr($host, 1, -1)
            : $host;
    }

    private static function isValidHost(string $host): bool
    {
        if ($host === '' || preg_match('/[^\x00-\x7F]/', $host) === 1) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (strlen($host) > 253) {
            return false;
        }

        foreach (explode('.', rtrim($host, '.')) as $label) {
            if ($label === ''
                || strlen($label) > 63
                || preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicTarget(string $host): bool
    {
        $hostname = strtolower(rtrim($host, '.'));

        if ($hostname === 'localhost'
            || $hostname === 'localhost.localdomain'
            || str_ends_with($hostname, '.localhost')
            || str_ends_with($hostname, '.local')
            || str_ends_with($hostname, '.localdomain')
            || str_ends_with($hostname, '.internal')
            || str_ends_with($hostname, '.home.arpa')) {
            return false;
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            $packed = inet_pton($hostname);

            if ($packed === false || self::isNonPublicAddress($packed)) {
                return false;
            }

            return filter_var(
                $hostname,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
        }

        return preg_match('/^(?:0x[0-9a-f]+|[0-9]+|[0-9.]+)$/i', $hostname) !== 1;
    }

    private static function isNonPublicAddress(string $packed): bool
    {
        $networks = strlen($packed) === 4
            ? [
                ['0.0.0.0', 8],
                ['10.0.0.0', 8],
                ['100.64.0.0', 10],
                ['127.0.0.0', 8],
                ['169.254.0.0', 16],
                ['172.16.0.0', 12],
                ['192.0.0.0', 24],
                ['192.0.2.0', 24],
                ['192.168.0.0', 16],
                ['198.18.0.0', 15],
                ['198.51.100.0', 24],
                ['203.0.113.0', 24],
                ['224.0.0.0', 4],
                ['240.0.0.0', 4],
            ]
            : [
                ['::', 128],
                ['::1', 128],
                ['::ffff:0:0', 96],
                ['100::', 64],
                ['2001:db8::', 32],
                ['fc00::', 7],
                ['fe80::', 10],
                ['fec0::', 10],
                ['ff00::', 8],
            ];

        foreach ($networks as [$network, $prefix]) {
            if (self::matchesNetwork($packed, $network, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesNetwork(string $address, string $network, int $prefix): bool
    {
        $networkAddress = inet_pton($network);

        if ($networkAddress === false || strlen($address) !== strlen($networkAddress)) {
            return false;
        }

        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;

        if (substr($address, 0, $bytes) !== substr($networkAddress, 0, $bytes)) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits)) & 0xFF;

        return (ord($address[$bytes]) & $mask) === (ord($networkAddress[$bytes]) & $mask);
    }
}
