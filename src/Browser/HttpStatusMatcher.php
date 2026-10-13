<?php

namespace Daif\ChromePdfBundle\Browser;

/**
 * Matches HTTP status codes against a list using Gotenberg semantics:
 * a code ending with "99" (e.g. 499) matches the whole range (400-499).
 *
 * @internal
 */
final class HttpStatusMatcher
{
    /**
     * @param list<int> $codes
     */
    public static function matches(int $status, array $codes): bool
    {
        foreach ($codes as $code) {
            $code = (int) $code;
            if ($status === $code) {
                return true;
            }

            if (99 === $code % 100 && $status >= $code - 99 && $status <= $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $host is one of $domains or a subdomain of one of them.
     *
     * @param list<string> $domains
     */
    public static function hostMatchesDomains(string $host, array $domains): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if ('' === $host) {
            return false;
        }

        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));
            // accept "https://example.com:8080/" or "*.example.com" style entries
            if (str_contains($domain, '://')) {
                $domain = (string) parse_url($domain, \PHP_URL_HOST);
            }
            $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;
            $domain = trim($domain, '.*');

            if ('' === $domain) {
                continue;
            }

            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
