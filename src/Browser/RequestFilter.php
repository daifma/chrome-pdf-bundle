<?php

namespace Daif\ChromePdfBundle\Browser;

use Daif\ChromePdfBundle\Exception\BrowserException;
use Symfony\Component\Filesystem\Path;

/**
 * Decides whether Chrome may load a URL.
 *
 *  - file:// URLs are only allowed inside the conversion working directory (when there is one),
 *    which prevents local file disclosure through <iframe src="file:///etc/passwd">, <img>, CSS...
 *  - data:, blob: and about: URLs are always allowed unless they match the deny list.
 *  - Any other URL must match one of the allow list patterns (when the allow list is not empty)
 *    and must not match any deny list pattern.
 *
 * Patterns are complete PCRE regular expressions including delimiters, e.g. "#^https://intranet\.#".
 *
 * @internal
 */
final class RequestFilter
{
    private readonly string|null $allowedDirectory;

    /**
     * @param list<string> $allowList
     * @param list<string> $denyList
     */
    public function __construct(
        string|null $allowedDirectory = null,
        private readonly array $allowList = [],
        private readonly array $denyList = [],
    ) {
        $this->allowedDirectory = null === $allowedDirectory ? null : self::normalizePath($allowedDirectory);

        foreach ([...$allowList, ...$denyList] as $pattern) {
            if (false === @preg_match($pattern, '')) {
                throw new BrowserException(\sprintf('Invalid allow/deny list regular expression "%s" (patterns must include delimiters, e.g. "#^https://example\.com/#").', $pattern));
            }
        }
    }

    /**
     * @return string|null null when the URL is allowed, the reason why it is blocked otherwise
     */
    public function getBlockReason(string $url): string|null
    {
        $scheme = strtolower((string) strstr($url, ':', true));

        if ('file' === $scheme) {
            return $this->isAllowedFile($url) ? null : 'local files outside the conversion directory are not accessible';
        }

        if (!\in_array($scheme, ['data', 'blob', 'about'], true) && [] !== $this->allowList && !$this->matchesAny($url, $this->allowList)) {
            return 'the URL does not match the allow list';
        }

        if ($this->matchesAny($url, $this->denyList)) {
            return 'the URL matches the deny list';
        }

        return null;
    }

    public function isAllowed(string $url): bool
    {
        return null === $this->getBlockReason($url);
    }

    private function isAllowedFile(string $url): bool
    {
        if (null === $this->allowedDirectory) {
            return false;
        }

        $path = FileUrl::toPath($url);
        if (null === $path || str_contains($path, "\0")) {
            return false;
        }

        $path = self::normalizePath($path);
        $base = $this->allowedDirectory;

        // Windows paths (drive letter or UNC) are case-insensitive
        if (1 === preg_match('#^([A-Za-z]:|//)#', $base)) {
            $path = strtolower($path);
            $base = strtolower($base);
        }

        return $path === $base || str_starts_with($path, rtrim($base, '/').'/');
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $isUnc = str_starts_with($path, '//');
        $canonical = Path::canonicalize($path);

        // Path::canonicalize() collapses the leading "//" of UNC paths
        if ($isUnc && !str_starts_with($canonical, '//')) {
            $canonical = '/'.$canonical;
        }

        return rtrim($canonical, '/') ?: '/';
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $url, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (1 === preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }
}
