<?php

namespace Daif\ChromePdfBundle\Tests\Processor;

use Daif\ChromePdfBundle\Browser\StringChunk;
use Daif\ChromePdfBundle\Exception\ProcessorException;
use Daif\ChromePdfBundle\Processor\FileProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;

final class FileProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $processor = new FileProcessor(new Filesystem(), sys_get_temp_dir());
        $generator = $processor(null);

        $generator->send(new FirstChunk(0, 'a'));
        $generator->send(new DataChunk(1, 'b'));
        $generator->send(new LastChunk(2, 'c'));

        $return = $generator->getReturn();

        self::assertInstanceOf(\SplFileInfo::class, $return); // @phpstan-ignore staticMethod.alreadyNarrowedType
        // Read without SplFileObject: an open handle would block unlink() on Windows.
        self::assertSame('abc', file_get_contents($return->getPathname()));
        unlink($return->getPathname());
    }

    public function testFileNameCannotEscapeTheDirectory(): void
    {
        $directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_file_processor_'.bin2hex(random_bytes(4));
        $generator = (new FileProcessor(new Filesystem(), $directory))('..'.\DIRECTORY_SEPARATOR.'escaped.pdf');

        try {
            $this->expectException(ProcessorException::class);
            $this->expectExceptionMessage('points outside of the');

            $generator->send(new StringChunk('%PDF-1.7'));
        } finally {
            self::assertFileDoesNotExist(\dirname($directory).\DIRECTORY_SEPARATOR.'escaped.pdf');
            (new Filesystem())->remove($directory);
        }
    }
}
