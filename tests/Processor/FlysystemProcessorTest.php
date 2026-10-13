<?php

namespace Daif\ChromePdfBundle\Tests\Processor;

use Daif\ChromePdfBundle\Bridge\LeagueFlysystem\Processor\FlysystemProcessor;
use Daif\ChromePdfBundle\Browser\StringChunk;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class FlysystemProcessorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_flysystem_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new SymfonyFilesystem())->remove($this->directory);
    }

    public function testItWritesTheSingleChunk(): void
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->directory));

        $generator = (new FlysystemProcessor($filesystem))('out/file.pdf');
        $generator->send(new StringChunk('%PDF-content'));

        $reader = $generator->getReturn();

        self::assertSame('%PDF-content', $filesystem->read('out/file.pdf'));
        self::assertSame('%PDF-content', $reader());
    }
}
