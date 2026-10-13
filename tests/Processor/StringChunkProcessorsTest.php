<?php

namespace Daif\ChromePdfBundle\Tests\Processor;

use Daif\ChromePdfBundle\Browser\StringChunk;
use Daif\ChromePdfBundle\Processor\ChainProcessor;
use Daif\ChromePdfBundle\Processor\FileProcessor;
use Daif\ChromePdfBundle\Processor\InMemoryProcessor;
use Daif\ChromePdfBundle\Processor\NullProcessor;
use Daif\ChromePdfBundle\Processor\TempfileProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * ChromePdfFileResult sends the whole document as a single StringChunk (first and last at once).
 */
final class StringChunkProcessorsTest extends TestCase
{
    public function testAllProcessorsHandleASingleChunk(): void
    {
        $directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_processors_'.bin2hex(random_bytes(4));

        try {
            $generator = (new ChainProcessor([
                new InMemoryProcessor(),
                new NullProcessor(),
                new TempfileProcessor(),
                new FileProcessor(new Filesystem(), $directory),
            ]))('document.pdf');
            $generator->send(new StringChunk('%PDF-1.7'));

            [$inMemory, $null, $resource, $file] = $generator->getReturn();

            self::assertSame('%PDF-1.7', $inMemory);
            self::assertNull($null);
            self::assertIsResource($resource);
            self::assertSame('%PDF-1.7', stream_get_contents($resource));
            self::assertSame(realpath($directory.\DIRECTORY_SEPARATOR.'document.pdf'), $file->getRealPath());
            self::assertSame('%PDF-1.7', file_get_contents($file->getPathname()));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    public function testFileProcessorWithoutFileNameDoesNotAssumeAPdfExtension(): void
    {
        $generator = (new FileProcessor(new Filesystem(), sys_get_temp_dir()))(null);
        $generator->send(new StringChunk("\x89PNG"));

        $file = $generator->getReturn();

        try {
            self::assertSame('', $file->getExtension());
            self::assertSame("\x89PNG", file_get_contents($file->getPathname()));
        } finally {
            unlink($file->getPathname());
        }
    }
}
