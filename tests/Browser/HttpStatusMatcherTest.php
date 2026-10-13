<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\HttpStatusMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpStatusMatcherTest extends TestCase
{
    /**
     * @return iterable<string, array{int, list<int>, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'exact' => [404, [404], true];
        yield 'not listed' => [403, [404, 500], false];
        yield 'X99 range lower bound' => [400, [499], true];
        yield 'X99 range upper bound' => [499, [499], true];
        yield 'X99 range middle' => [418, [499], true];
        yield 'outside X99 range' => [500, [499], false];
        yield 'below X99 range' => [399, [499], false];
        yield '599 range' => [503, [599], true];
        yield 'empty list' => [500, [], false];
        yield 'success code' => [200, [499, 599], false];
    }

    /**
     * @param list<int> $codes
     */
    #[DataProvider('provideStatuses')]
    public function testMatches(int $status, array $codes, bool $expected): void
    {
        self::assertSame($expected, HttpStatusMatcher::matches($status, $codes));
    }

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function provideHosts(): iterable
    {
        yield 'same host' => ['example.com', ['example.com'], true];
        yield 'subdomain' => ['cdn.example.com', ['example.com'], true];
        yield 'case insensitive' => ['CDN.Example.COM', ['example.com'], true];
        yield 'suffix is not a subdomain' => ['badexample.com', ['example.com'], false];
        yield 'wildcard entry' => ['a.example.com', ['*.example.com'], true];
        yield 'URL entry with port' => ['example.com', ['https://example.com:8443/'], true];
        yield 'other domain' => ['example.org', ['example.com'], false];
        yield 'empty host' => ['', ['example.com'], false];
    }

    /**
     * @param list<string> $domains
     */
    #[DataProvider('provideHosts')]
    public function testHostMatchesDomains(string $host, array $domains, bool $expected): void
    {
        self::assertSame($expected, HttpStatusMatcher::hostMatchesDomains($host, $domains));
    }
}
