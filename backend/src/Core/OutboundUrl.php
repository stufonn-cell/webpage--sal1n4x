<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

/**
 * Guard for addresses the server itself calls (the RIPS validator), against
 * server-side request forgery. Only http(s) without credentials, and never a
 * host that resolves to link-local, cloud metadata, multicast, broadcast or
 * "this network" addresses. Loopback and private networks stay allowed on
 * purpose: the Ministry's validator runs as a Docker API on the practice's
 * own infrastructure (https://localhost:9443 by default).
 */
final class OutboundUrl
{
    private const BLOCKED_HOSTS = [
        'metadata', 'metadata.google.internal', 'metadata.goog', 'metadata.azure.internal',
        'instance-data', 'instance-data.ec2.internal',
    ];

    /** [network, prefix length] blocked for IPv4. */
    private const BLOCKED_V4 = [
        ['0.0.0.0', 8],          // "this network"
        ['169.254.0.0', 16],     // link-local, includes 169.254.169.254 (AWS, GCP, Azure metadata)
        ['100.100.100.200', 32], // Alibaba Cloud metadata
        ['224.0.0.0', 4],        // multicast
        ['240.0.0.0', 4],        // reserved and broadcast
    ];

    /** [network, prefix length] blocked for IPv6. */
    private const BLOCKED_V6 = [
        ['::', 128],             // unspecified
        ['fe80::', 10],          // link-local
        ['ff00::', 8],           // multicast
        ['fd00:ec2::254', 128],  // AWS metadata over IPv6
    ];

    /**
     * Returns the reason a URL must not be called, written for people, or
     * null when it is acceptable. $resolver(host) returns the host's IPs and
     * exists for tests; by default DNS is queried.
     */
    public static function problem(string $url, ?callable $resolver = null): ?string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'The address must start with http:// or https://.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return "The address can't include a user name or password.";
        }

        $host = rtrim($host, '.');
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return "That address points to a reserved network location and can't be used.";
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : ($resolver !== null ? $resolver($host) : self::resolve($host));

        if ($addresses === []) {
            return "We couldn't find that address. Check that it is written correctly.";
        }

        foreach ($addresses as $address) {
            if (self::isBlockedIp((string) $address)) {
                return "That address points to a reserved network location and can't be used.";
            }
        }

        return null;
    }

    public static function isBlockedIp(string $ip): bool
    {
        $binary = @inet_pton($ip);
        if ($binary === false) {
            return true;
        }

        if (strlen($binary) === 16) {
            // IPv4 written as IPv6 (::ffff:a.b.c.d) or through NAT64 (64:ff9b::a.b.c.d).
            $prefix = substr($binary, 0, 12);
            if ($prefix === str_repeat("\0", 10) . "\xff\xff" || $prefix === "\x00\x64\xff\x9b" . str_repeat("\0", 8)) {
                return self::isBlockedIp((string) inet_ntop(substr($binary, 12)));
            }

            return self::matchesAny($binary, self::BLOCKED_V6);
        }

        return self::matchesAny($binary, self::BLOCKED_V4);
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        $records = @dns_get_record($host, DNS_AAAA);
        foreach (is_array($records) ? $records : [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = (string) $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    private static function matchesAny(string $binary, array $networks): bool
    {
        foreach ($networks as [$network, $bits]) {
            $base = inet_pton($network);
            if ($base === false || strlen($base) !== strlen($binary)) {
                continue;
            }

            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;

            if (substr($binary, 0, $bytes) !== substr($base, 0, $bytes)) {
                continue;
            }
            if ($rest === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($binary[$bytes]) & $mask) === (ord($base[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
