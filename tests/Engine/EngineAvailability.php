<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use Daif\ChromePdfBundle\Engine\BinaryFinder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * Decides whether tests needing a real qpdf, exiftool or LibreOffice can run: they are skipped
 * when the tool is missing, except with CHROME_PDF_ENGINE_TESTS=1 (CI) where they fail.
 */
final class EngineAvailability
{
    /**
     * @param 'qpdf'|'exiftool'|'soffice' $tool
     */
    public static function binaryOrSkip(string $tool): string
    {
        $finder = new BinaryFinder();
        if ($finder->isAvailable($tool)) {
            return $finder->find($tool);
        }

        $mode = $_SERVER['CHROME_PDF_ENGINE_TESTS'] ?? $_ENV['CHROME_PDF_ENGINE_TESTS'] ?? getenv('CHROME_PDF_ENGINE_TESTS');
        if ('1' === $mode || 'true' === $mode) {
            Assert::fail(\sprintf('CHROME_PDF_ENGINE_TESTS=1 requires %s but it was not found.', $tool));
        }

        TestCase::markTestSkipped(\sprintf('%s not found, install it to run this test.', $tool));
    }
}
