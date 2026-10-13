<?php

namespace Daif\ChromePdfBundle\Tests\Builder\Util;

use Daif\ChromePdfBundle\Builder\Util\ZipArchiver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[RequiresPhpExtension('zip')]
final class ZipArchiverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/chrome_pdf_zip_test_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir([$this->directory.'/a', $this->directory.'/b']);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testDuplicateNamesAreSuffixed(): void
    {
        file_put_contents($this->directory.'/a/document.pdf', 'first');
        file_put_contents($this->directory.'/b/document.pdf', 'second');
        file_put_contents($this->directory.'/a/other.pdf', 'third');

        $target = $this->directory.'/documents.zip';
        ZipArchiver::create([$this->directory.'/a/document.pdf', $this->directory.'/b/document.pdf', $this->directory.'/a/other.pdf'], $target);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($target));
        self::assertSame(3, $zip->numFiles);
        self::assertSame('first', $zip->getFromName('document.pdf'));
        self::assertSame('second', $zip->getFromName('document_2.pdf'));
        self::assertSame('third', $zip->getFromName('other.pdf'));
        $zip->close();
    }
}
