<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Daif\ChromePdfBundle\Browser\ChromeFinder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * Decides whether functional tests that need a real Chrome/Chromium/Edge can run.
 *
 * Controlled by the CHROME_PDF_BROWSER_TESTS environment variable (see phpunit.xml):
 *  - "auto" (default): run when a browser is found, skip otherwise;
 *  - "1": a browser is required, the test fails when none is found (CI);
 *  - "0": always skip.
 *
 * These tests are also in the "browser" group: `phpunit --exclude-group browser`.
 */
final class ChromeAvailability
{
    public static function binaryOrSkip(): string
    {
        $mode = self::env('CHROME_PDF_BROWSER_TESTS');
        $mode = false === $mode || '' === $mode ? 'auto' : $mode;

        if ('0' === $mode || 'false' === strtolower($mode)) {
            TestCase::markTestSkipped('Browser tests disabled (CHROME_PDF_BROWSER_TESTS=0).');
        }

        $binary = self::find();

        if (null !== $binary) {
            return $binary;
        }

        if ('auto' !== $mode) {
            Assert::fail('CHROME_PDF_BROWSER_TESTS requires a browser but no Chrome/Chromium/Edge binary was found. Set CHROME_PATH.');
        }

        TestCase::markTestSkipped('No Chrome/Chromium/Edge binary found. Set CHROME_PATH to run browser tests.');
    }

    /**
     * Chrome's sandbox needs unprivileged user namespaces, usually unavailable in containers
     * and on recent Ubuntu CI runners. Override with CHROME_NO_SANDBOX=0|1.
     */
    public static function noSandbox(): bool
    {
        $value = self::env('CHROME_NO_SANDBOX');
        if (false !== $value && '' !== $value) {
            return filter_var($value, \FILTER_VALIDATE_BOOL);
        }

        return 'Linux' === \PHP_OS_FAMILY;
    }

    private static function find(): string|null
    {
        if (class_exists(ChromeFinder::class)) {
            try {
                $binary = (new ChromeFinder())->find();

                return is_file($binary) ? $binary : null;
            } catch (\Throwable) {
                return null;
            }
        }

        $binary = self::env('CHROME_PATH');

        return \is_string($binary) && is_file($binary) ? $binary : null;
    }

    private static function env(string $name): string|false
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) ? $value : false;
    }
}
