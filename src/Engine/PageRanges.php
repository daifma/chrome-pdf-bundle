<?php

namespace Daif\ChromePdfBundle\Engine;

use Daif\ChromePdfBundle\Exception\EngineException;

/**
 * Parses Gotenberg-style page ranges ("1-3,5", "4-", "-2", "odd", "even", "l" or "z" for the last page)
 * into page numbers. Pages beyond the document are ignored.
 *
 * @internal
 */
final class PageRanges
{
    /**
     * @return list<list<int>> the pages of each comma separated range, ranges selecting no existing page are dropped
     */
    public static function parse(string $ranges, int $pageCount): array
    {
        $tokens = array_map('trim', explode(',', $ranges));
        if ([''] === $tokens) {
            throw new EngineException('The page ranges must not be empty.');
        }

        $result = [];
        foreach ($tokens as $token) {
            $pages = self::parseToken(strtolower($token), $pageCount, $ranges);
            if ([] !== $pages) {
                $result[] = $pages;
            }
        }

        return $result;
    }

    /**
     * @return list<int> unique pages, in selection order
     */
    public static function pages(string $ranges, int $pageCount): array
    {
        $pages = [];
        foreach (self::parse($ranges, $pageCount) as $range) {
            foreach ($range as $page) {
                $pages[$page] = $page;
            }
        }

        return array_values($pages);
    }

    /**
     * @param list<int> $pages
     *
     * @return string qpdf page range syntax, e.g. "1-3,5"
     */
    public static function toQpdf(array $pages): string
    {
        $parts = [];
        $start = $previous = null;
        foreach ($pages as $page) {
            if (null !== $previous && $previous + 1 === $page) {
                $previous = $page;
                continue;
            }
            if (null !== $start) {
                $parts[] = $start === $previous ? (string) $start : $start.'-'.$previous;
            }
            $start = $previous = $page;
        }
        if (null !== $start) {
            $parts[] = $start === $previous ? (string) $start : $start.'-'.$previous;
        }

        return implode(',', $parts);
    }

    /**
     * @return list<int>
     */
    private static function parseToken(string $token, int $pageCount, string $ranges): array
    {
        if ('odd' === $token || 'even' === $token) {
            return array_values(array_filter(range(1, max(1, $pageCount)), static fn (int $page): bool => $page <= $pageCount && ('odd' === $token) === (1 === $page % 2)));
        }

        if (1 !== preg_match('/^(\d+|[lz])?\s*(-)?\s*(\d+|[lz])?$/', $token, $matches) || '' === $token || '-' === $token) {
            throw new EngineException(\sprintf('Invalid page range "%s" in "%s", expected e.g. "1-3,5", "4-", "-2", "odd" or "even".', $token, $ranges));
        }

        $isRange = '-' === ($matches[2] ?? '');
        $start = self::page($matches[1] ?? '', $isRange ? 1 : null, $pageCount, $token, $ranges);
        $end = $isRange ? self::page($matches[3] ?? '', $pageCount, $pageCount, $token, $ranges) : $start;
        if (!$isRange && '' !== ($matches[3] ?? '')) {
            throw new EngineException(\sprintf('Invalid page range "%s" in "%s".', $token, $ranges));
        }

        if ($start <= $end) {
            return $start > $pageCount ? [] : range($start, min($end, $pageCount));
        }

        // descending range, e.g. "5-1"
        return $end > $pageCount ? [] : range(min($start, $pageCount), $end);
    }

    private static function page(string $value, int|null $default, int $pageCount, string $token, string $ranges): int
    {
        if ('' === $value) {
            return $default ?? throw new EngineException(\sprintf('Invalid page range "%s" in "%s".', $token, $ranges));
        }
        if ('l' === $value || 'z' === $value) {
            return max(1, $pageCount);
        }

        $page = (int) $value;
        if ($page < 1) {
            throw new EngineException(\sprintf('Invalid page range "%s" in "%s", pages start at 1.', $token, $ranges));
        }

        return $page;
    }
}
