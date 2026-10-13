<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Daif\ChromePdfBundle\Engine\BinaryFinder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * Decides whether tests that need qpdf, exiftool or LibreOffice can run.
 *
 * They are skipped when the binary is not found, unless CHROME_PDF_ENGINE_TESTS=1 (CI) where they fail.
 */
final class EngineAvailability
{
    /**
     * @param 'qpdf'|'exiftool'|'soffice' ...$tools
     */
    public static function requireOrSkip(string ...$tools): void
    {
        $finder = new BinaryFinder();
        $missing = array_values(array_filter($tools, static fn (string $tool): bool => !$finder->isAvailable($tool)));

        if ([] === $missing) {
            return;
        }

        $message = \sprintf('Missing binaries: %s. Install them or set QPDF_PATH, EXIFTOOL_PATH, LIBREOFFICE_PATH.', implode(', ', $missing));

        if (self::required()) {
            Assert::fail('CHROME_PDF_ENGINE_TESTS=1 requires the engine binaries. '.$message);
        }

        TestCase::markTestSkipped($message);
    }

    private static function required(): bool
    {
        $value = $_SERVER['CHROME_PDF_ENGINE_TESTS'] ?? $_ENV['CHROME_PDF_ENGINE_TESTS'] ?? getenv('CHROME_PDF_ENGINE_TESTS');

        return \is_string($value) && filter_var($value, \FILTER_VALIDATE_BOOL);
    }
}
