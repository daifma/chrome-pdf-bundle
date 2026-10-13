<?php

namespace Daif\ChromePdfBundle\Browser;

use Daif\ChromePdfBundle\Exception\BrowserException;

/**
 * Converts between local filesystem paths and file:// URLs, for POSIX and Windows paths
 * regardless of the OS PHP is running on.
 *
 *  - /tmp/a b/index.html          => file:///tmp/a%20b/index.html
 *  - C:\Users\x\Temp\index.html   => file:///C:/Users/x/Temp/index.html
 *  - \\server\share\dir\index.html => file://server/share/dir/index.html
 *
 * @internal
 */
final class FileUrl
{
    public static function fromPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        // UNC path: \\server\share\...
        if (str_starts_with($normalized, '//')) {
            $parts = explode('/', substr($normalized, 2), 2);
            if ('' === $parts[0]) {
                throw new BrowserException(\sprintf('Invalid UNC path "%s".', $path));
            }

            return 'file://'.$parts[0].'/'.self::encodeSegments($parts[1] ?? '');
        }

        // Windows drive path: C:\... or C:/...
        if (1 === preg_match('#^([A-Za-z]):/(.*)$#s', $normalized, $matches)) {
            return 'file:///'.strtoupper($matches[1]).':/'.self::encodeSegments($matches[2]);
        }

        if (str_starts_with($normalized, '/')) {
            return 'file:///'.self::encodeSegments(substr($normalized, 1));
        }

        throw new BrowserException(\sprintf('Cannot build a file URL from the relative path "%s".', $path));
    }

    /**
     * Returns the local path designated by a file:// URL, with forward slashes:
     * "/tmp/x", "C:/Users/x" or "//server/share/x". Returns null if the URL is not a file URL.
     */
    public static function toPath(string $url): string|null
    {
        if (1 !== preg_match('#^file:(.*)$#is', $url, $matches)) {
            return null;
        }

        $rest = $matches[1];
        // strip query string and fragment, they are not part of the path
        $rest = preg_replace('/[?#].*$/s', '', $rest) ?? $rest;
        $rest = str_replace('\\', '/', $rest);

        if (str_starts_with($rest, '//')) {
            $rest = substr($rest, 2);
            $slash = strpos($rest, '/');
            $host = false === $slash ? $rest : substr($rest, 0, $slash);
            $path = false === $slash ? '/' : substr($rest, $slash);

            if ('' !== $host && 'localhost' !== strtolower($host)) {
                return '//'.rawurldecode($host).rawurldecode($path);
            }
            $rest = $path;
        }

        $path = rawurldecode($rest);

        // /C:/x => C:/x
        if (1 === preg_match('#^/+([A-Za-z])[:|](/.*)?$#s', $path, $drive)) {
            return strtoupper($drive[1]).':'.($drive[2] ?? '/');
        }

        return '/'.ltrim($path, '/');
    }

    private static function encodeSegments(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
