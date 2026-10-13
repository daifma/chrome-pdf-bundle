<?php

namespace Daif\ChromePdfBundle\Browser;

use Daif\ChromePdfBundle\Exception\BrowserException;
use Symfony\Component\Filesystem\Path;

/**
 * Decides whether Chrome may load a URL.
 *
 *  - file:// URLs are only allowed inside the conversion working directory (when there is one) and
 *    inside the configured local file roots, which prevents local file disclosure through
 *    <iframe src="file:///etc/passwd">, <img>, CSS...
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
     * @var list<string>
     */
    private readonly array $localFileRoots;

    /**
     * @param list<string> $allowList
     * @param list<string> $denyList
     * @param list<string> $localFileRoots extra directories whose files may be loaded through file:// URLs
     */
    public function __construct(
        string|null $allowedDirectory = null,
        private readonly array $allowList = [],
        private readonly array $denyList = [],
        array $localFileRoots = [],
    ) {
        $this->allowedDirectory = null === $allowedDirectory ? null : self::normalizePath($allowedDirectory);
        $roots = [];
        foreach ($localFileRoots as $root) {
            // symlinks are resolved so a link inside a root cannot expose files outside of it
            $real = realpath($root);
            if (false !== $real && is_dir($real)) {
                $roots[] = self::normalizePath($real);
            }
        }
        $this->localFileRoots = $roots;

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
        $path = FileUrl::toPath($url);
        if (null === $path || str_contains($path, "\0")) {
            return false;
        }

        if (null !== $this->allowedDirectory && self::isInside(self::normalizePath($path), $this->allowedDirectory)) {
            return true;
        }

        if ([] === $this->localFileRoots) {
            return false;
        }

        $real = realpath($path);
        if (false === $real) {
            return false;
        }
        $real = self::normalizePath($real);
        foreach ($this->localFileRoots as $root) {
            if (self::isInside($real, $root)) {
                return true;
            }
        }

        return false;
    }

    private static function isInside(string $path, string $base): bool
    {
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
