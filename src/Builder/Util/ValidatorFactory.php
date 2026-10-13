<?php

namespace Daif\ChromePdfBundle\Builder\Util;

use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Formatter\PathHelper;
use Symfony\Component\HttpFoundation\Cookie;

class ValidatorFactory
{
    public static function range(string $value): void
    {
        // See https://regex101.com/r/XUK2Ip/1
        if (!preg_match('/^ *(\d+ *(- *\d+)? *, *)*\d+ *(- *\d+)? *$/', $value)) {
            throw new InvalidBuilderConfiguration('Invalid range values, the range value format need to look like e.g 1-20.');
        }
    }

    public static function waitDelay(string $value): void
    {
        if (!preg_match('/^\d+(s|ms)$/', $value)) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid value "%s" for "waitDelay", expected e.g. "500ms" or "2s".', $value));
        }
    }

    /**
     * @param list<array<string, mixed>|Cookie> $cookies
     */
    public static function cookies(array $cookies): void
    {
        foreach ($cookies as $cookie) {
            if ($cookie instanceof Cookie) {
                continue;
            }

            if (!\is_array($cookie)) { // @phpstan-ignore function.alreadyNarrowedType
                throw new InvalidBuilderConfiguration(\sprintf('Invalid cookie, expected an array or an instance of "%s", got "%s".', Cookie::class, get_debug_type($cookie)));
            }

            self::cookie($cookie);
        }
    }

    /**
     * @param array<string, mixed> $cookie
     */
    public static function cookie(array $cookie): void
    {
        $keys = array_keys($cookie);

        $fields = ['name', 'value', 'domain', 'path', 'secure', 'httpOnly', 'sameSite'];
        if ([] !== $unknown = array_diff($keys, $fields)) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid cookies schema: unknown key(s) "%s", allowed keys are "%s".', implode('", "', $unknown), implode('", "', $fields)));
        }

        $required = ['name', 'value', 'domain'];
        if ([] !== $missing = array_diff($required, $keys)) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid cookies schema: missing required key(s) "%s".', implode('", "', $missing)));
        }

        if (!\is_string($cookie['name']) || '' === $cookie['name']) {
            throw new InvalidBuilderConfiguration('Invalid cookies schema: "name" must be a non-empty string.');
        }

        if (!\is_scalar($cookie['value']) && !$cookie['value'] instanceof \Stringable) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid cookies schema: "value" of cookie "%s" must be a string.', $cookie['name']));
        }

        if (!\is_string($cookie['domain']) || '' === $cookie['domain']) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid cookies schema: "domain" of cookie "%s" must be a non-empty string.', $cookie['name']));
        }

        if (null !== ($cookie['sameSite'] ?? null) && (!\is_string($cookie['sameSite']) || !\in_array(strtolower($cookie['sameSite']), ['strict', 'lax', 'none'], true))) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid cookies schema: "sameSite" of cookie "%s" must be one of "Strict", "Lax" or "None".', $cookie['name']));
        }
    }

    /**
     * @param \SplFileInfo[] $files
     * @param string[]       $validExtensions
     */
    public static function filesExtension(array $files, array $validExtensions): void
    {
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                throw new InvalidBuilderConfiguration(\sprintf('The option "files" expects an array of "%s" instances, but got "%s".', \SplFileInfo::class, get_debug_type($file)));
            }

            $ext = PathHelper::extension($file->getPathname());
            if (!\in_array($ext, array_map('strtolower', $validExtensions), true)) {
                throw new InvalidBuilderConfiguration(\sprintf('The file extension "%s" is not valid in this context.', $ext));
            }
        }
    }

    /**
     * @param list<array{url: string, extraHttpHeaders?: array<string, string>}> $downloadFrom
     */
    public static function download(array $downloadFrom): void
    {
        foreach ($downloadFrom as $file) {
            if (!\is_array($file)) { // @phpstan-ignore function.alreadyNarrowedType
                throw new InvalidBuilderConfiguration('"url" is mandatory into "downloadFrom" array field.');
            }

            if (!\array_key_exists('url', $file)) {
                throw new InvalidBuilderConfiguration('"url" is mandatory into "downloadFrom" array field.');
            }

            if (!\is_string($file['url']) || '' === $file['url']) { // @phpstan-ignore function.alreadyNarrowedType
                throw new InvalidBuilderConfiguration('"url" is mandatory into "downloadFrom" array field.');
            }

            if (!preg_match('#^https?://#i', $file['url'])) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid "downloadFrom" URL "%s": only http:// and https:// URLs are supported.', $file['url']));
            }

            if ([] !== $unknown = array_diff(array_keys($file), ['url', 'extraHttpHeaders'])) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid "downloadFrom" entry: unknown key(s) "%s", allowed keys are "url" and "extraHttpHeaders".', implode('", "', $unknown)));
            }

            if (\array_key_exists('extraHttpHeaders', $file) && !\is_array($file['extraHttpHeaders'])) { // @phpstan-ignore function.alreadyNarrowedType, booleanAnd.alwaysFalse
                throw new InvalidBuilderConfiguration('"extraHttpHeaders" of a "downloadFrom" entry must be an array of headers.');
            }
        }
    }

    public static function splitSpan(string $value): void
    {
        if (preg_match('/([\d]+[-][\d]+)/', $value) !== 1 && preg_match('/(\d+)/', $value) !== 1) {
            throw new InvalidBuilderConfiguration('Invalid value, the range value format need to look like e.g 1-20 or as a single int value e.g 2.');
        }
    }

    public static function quality(int $value): void
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidBuilderConfiguration(\sprintf('The value "%s" must be between 0 and 100.', $value));
        }
    }

    public static function scale(float $value): void
    {
        // Chrome's Page.printToPDF only accepts a scale between 0.1 and 2.
        if ($value < 0.1 || $value > 2.0) {
            throw new InvalidBuilderConfiguration(\sprintf('The scale "%s" must be between 0.1 and 2.', $value));
        }
    }

    public static function positive(string $option, int|float $value, bool $strict = true): void
    {
        if (($strict && $value <= 0) || $value < 0 || !is_finite((float) $value)) {
            throw new InvalidBuilderConfiguration(\sprintf('The value "%s" of "%s" must be a %s number.', $value, $option, $strict ? 'strictly positive' : 'positive'));
        }
    }

    /**
     * @param array<mixed> $statusCodes
     */
    public static function httpStatusCodes(string $option, array $statusCodes): void
    {
        foreach ($statusCodes as $statusCode) {
            if (!\is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid HTTP status code "%s" for "%s", expected integers between 100 and 599 (e.g. 499 means the whole 400-499 range).', get_debug_type($statusCode) === 'int' ? $statusCode : get_debug_type($statusCode), $option));
            }
        }
    }

    /**
     * @param array<mixed> $features
     */
    public static function emulatedMediaFeatures(array $features): void
    {
        foreach ($features as $feature) {
            if (
                !\is_array($feature)
                || [] !== array_diff(array_keys($feature), ['name', 'value'])
                || !\is_string($feature['name'] ?? null) || '' === $feature['name']
                || !\is_string($feature['value'] ?? null)
            ) {
                throw new InvalidBuilderConfiguration('Invalid "emulatedMediaFeatures", expected a list of arrays like ["name" => "prefers-color-scheme", "value" => "dark"].');
            }
        }
    }

    public static function page(int $value): void
    {
        if ($value < 1) {
            throw new InvalidBuilderConfiguration(\sprintf('Page number must be greater than or equal to 1, %d given.', $value));
        }
    }

    public static function zoom(int $value): void
    {
        if ($value < 1 || $value > 100) {
            throw new InvalidBuilderConfiguration(\sprintf('Zoom value "%s" must be between 1 and 100.', $value));
        }
    }

    public static function bookmarkLevels(int $value): void
    {
        if ($value < -1) {
            throw new InvalidBuilderConfiguration(\sprintf('BookmarkLevels value "%s" must be greater than or equal to -1.', $value));
        }
    }

    public static function hexColor(string $value): void
    {
        if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid color "%s", expected a hexadecimal color like "#FF0000".', $value));
        }
    }

    /**
     * @param array<mixed> $bookmarks
     */
    public static function bookmarks(array $bookmarks): void
    {
        foreach ($bookmarks as $bookmark) {
            if (
                !\is_array($bookmark)
                || !\is_string($bookmark['title'] ?? null)
                || !\is_int($bookmark['page'] ?? null)
                || [] !== array_diff(array_keys($bookmark), ['title', 'page', 'children'])
            ) {
                throw new InvalidBuilderConfiguration('Invalid bookmark, expected an array like ["title" => "Introduction", "page" => 1, "children" => [...]].');
            }

            self::page($bookmark['page']);

            if (\array_key_exists('children', $bookmark)) {
                if (!\is_array($bookmark['children'])) {
                    throw new InvalidBuilderConfiguration('The "children" of a bookmark must be a list of bookmarks.');
                }

                self::bookmarks($bookmark['children']);
            }
        }
    }
}
