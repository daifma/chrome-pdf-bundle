<?php

namespace Daif\ChromePdfBundle\Formatter;

/**
 * Cross-platform path helpers: both "/" and "\" are treated as separators,
 * whatever the OS PHP is currently running on.
 *
 * @internal
 */
final class PathHelper
{
    /**
     * Returns the trailing name component of a path (like basename()) but
     * works with Windows-style paths (C:\dir\file.png) on any OS, and ignores
     * query strings / fragments of URLs.
     */
    public static function basename(string $path): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            $urlPath = parse_url($path, \PHP_URL_PATH);
            $path = \is_string($urlPath) ? rawurldecode($urlPath) : '';
        }

        $path = rtrim(str_replace('\\', '/', $path), '/');
        $position = strrpos($path, '/');

        return false === $position ? $path : substr($path, $position + 1);
    }

    /**
     * Whether the path is absolute on any OS: POSIX ("/x"), Windows drive ("C:\x", "C:/x"), UNC ("\\server\share")
     * or URL / stream wrapper ("https://", "phar://"). Symfony's Path::isAbsolute() only knows the current OS.
     */
    public static function isAbsolute(string $path): bool
    {
        if ('' === $path) {
            return false;
        }

        if ('/' === $path[0] || '\\' === $path[0]) {
            return true;
        }

        if (preg_match('#^[a-z]:(?:[/\\\\]|$)#i', $path)) {
            return true;
        }

        return 1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $path);
    }

    /**
     * Returns the lowercase extension of a path (without the dot), handling Windows-style paths.
     */
    public static function extension(string $path): string
    {
        $basename = self::basename($path);
        $position = strrpos($basename, '.');

        return false === $position ? '' : strtolower(substr($basename, $position + 1));
    }
}
