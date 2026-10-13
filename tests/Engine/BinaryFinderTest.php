<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Exception\EngineException;
use PHPUnit\Framework\TestCase;

final class BinaryFinderTest extends TestCase
{
    public function testConfiguredBinaryWinsOverEverything(): void
    {
        $finder = new BinaryFinder('Linux', self::env(['QPDF_PATH' => '/opt/env/qpdf', 'PATH' => '/usr/bin']), static fn (): bool => true);

        self::assertSame('/opt/my/qpdf', $finder->find('qpdf', '/opt/my/qpdf'));
    }

    public function testConfiguredBinaryMustExist(): void
    {
        $finder = new BinaryFinder('Linux', self::env(['PATH' => '/usr/bin']), static fn (string $path): bool => '/usr/bin/qpdf' === $path);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('The configured qpdf binary "/opt/missing/qpdf" (daif_chrome_pdf.qpdf_binary) does not exist or is not executable.');

        $finder->find('qpdf', '/opt/missing/qpdf');
    }

    public function testConfiguredCommandNameIsSearchedInPath(): void
    {
        $finder = new BinaryFinder('Linux', self::env(['PATH' => '/usr/local/bin:/opt/tools']), static fn (string $path): bool => '/opt/tools/qpdf11' === $path);

        self::assertSame('/opt/tools/qpdf11', $finder->find('qpdf', 'qpdf11'));
    }

    public function testConfiguredBinaryExpandsWindowsVariables(): void
    {
        $finder = new BinaryFinder('Windows', self::env(['ProgramFiles' => 'D:\Apps']), static fn (string $path): bool => 'D:\Apps\LibreOffice\program\soffice.exe' === $path);

        self::assertSame('D:\Apps\LibreOffice\program\soffice.exe', $finder->find('soffice', '%ProgramFiles%\LibreOffice\program\soffice.exe'));
    }

    public function testEnvironmentVariable(): void
    {
        $finder = new BinaryFinder('Linux', self::env(['EXIFTOOL_PATH' => ' /opt/exiftool/exiftool ', 'PATH' => '/usr/bin']), static fn (): bool => true);

        self::assertSame('/opt/exiftool/exiftool', $finder->find('exiftool'));
        self::assertSame('/opt/exiftool/exiftool', $finder->find('exiftool', ''), 'an empty configured binary falls back to the environment');
    }

    public function testEnvironmentVariableMustExist(): void
    {
        $finder = new BinaryFinder('Linux', self::env(['LIBREOFFICE_PATH' => '/nope/soffice']), static fn (): bool => false);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('LIBREOFFICE_PATH');

        $finder->find('soffice');
    }

    public function testLinuxPathLookup(): void
    {
        $finder = new BinaryFinder('Linux', self::env(['PATH' => '/home/john/bin::/usr/bin']), static fn (string $path): bool => \in_array($path, ['/usr/bin/qpdf', '/usr/local/bin/qpdf'], true));

        self::assertSame('/usr/bin/qpdf', $finder->find('qpdf'));
        self::assertSame('/home/john/bin/qpdf', $finder->getCandidates('qpdf')[0]);
    }

    public function testLinuxLibreOfficeLocations(): void
    {
        $glob = static fn (string $pattern): array => '/opt/libreoffice*/program/soffice' === $pattern ? ['/opt/libreoffice24.8/program/soffice'] : [];
        $finder = new BinaryFinder('Linux', self::env([]), static fn (string $path): bool => '/opt/libreoffice24.8/program/soffice' === $path, $glob);

        self::assertSame('/opt/libreoffice24.8/program/soffice', $finder->find('soffice'));
        self::assertContains('/usr/bin/libreoffice', $finder->getCandidates('soffice'));
        self::assertContains('/usr/lib/libreoffice/program/soffice', $finder->getCandidates('soffice'));
    }

    public function testMacLocations(): void
    {
        $finder = new BinaryFinder('Darwin', self::env(['HOME' => '/Users/john']), static fn (string $path): bool => \in_array($path, ['/opt/homebrew/bin/exiftool', '/Users/john/Applications/LibreOffice.app/Contents/MacOS/soffice'], true));

        self::assertSame('/opt/homebrew/bin/exiftool', $finder->find('exiftool'));
        self::assertSame('/Users/john/Applications/LibreOffice.app/Contents/MacOS/soffice', $finder->find('soffice'));
        self::assertSame('/Applications/LibreOffice.app/Contents/MacOS/soffice', $finder->getCandidates('soffice')[0]);
        self::assertContains('/usr/local/bin/qpdf', $finder->getCandidates('qpdf'));
    }

    public function testWindowsPathLookupAddsExecutableExtensions(): void
    {
        $finder = new BinaryFinder('Windows', self::env(['Path' => 'C:\Windows;"C:\Tools\qpdf 11\bin\";']), static fn (string $path): bool => 'C:\Tools\qpdf 11\bin\qpdf.exe' === $path);

        self::assertSame('C:\Tools\qpdf 11\bin\qpdf.exe', $finder->find('qpdf'));
        self::assertSame(['C:\Windows\qpdf.exe', 'C:\Windows\qpdf.com', 'C:\Windows\qpdf.bat', 'C:\Windows\qpdf.cmd'], \array_slice($finder->getCandidates('qpdf'), 0, 4));
    }

    public function testWindowsQpdfInProgramFiles(): void
    {
        $glob = static fn (string $pattern): array => 'D:\Apps\qpdf*\bin\qpdf.exe' === $pattern ? ['D:\Apps\qpdf 11.9.1\bin\qpdf.exe'] : [];
        $finder = new BinaryFinder('Windows', self::env(['ProgramFiles' => 'D:\Apps\\']), static fn (string $path): bool => 'D:\Apps\qpdf 11.9.1\bin\qpdf.exe' === $path, $glob);

        self::assertSame('D:\Apps\qpdf 11.9.1\bin\qpdf.exe', $finder->find('qpdf'));
    }

    public function testWindowsLibreOfficeDefaultsWithoutEnvironment(): void
    {
        $finder = new BinaryFinder('Windows', self::env([]), static fn (string $path): bool => 'C:\Program Files\LibreOffice\program\soffice.exe' === $path);

        self::assertSame('C:\Program Files\LibreOffice\program\soffice.exe', $finder->find('soffice'));
        self::assertContains('C:\Program Files (x86)\LibreOffice\program\soffice.exe', $finder->getCandidates('soffice'));
        self::assertContains('C:\ProgramData\chocolatey\bin\soffice.exe', $finder->getCandidates('soffice'));
    }

    public function testWindowsExiftoolLocations(): void
    {
        $finder = new BinaryFinder('Windows', self::env(['ProgramFiles' => 'C:\Program Files']), static fn (string $path): bool => 'C:\Strawberry\perl\bin\exiftool' === $path);

        self::assertSame('C:\Strawberry\perl\bin\exiftool', $finder->find('exiftool'));
        self::assertContains('C:\Program Files\exiftool\exiftool.exe', $finder->getCandidates('exiftool'));
    }

    public function testWindowsPackageManagerShims(): void
    {
        $env = self::env([
            'ChocolateyInstall' => 'E:\choco',
            'LOCALAPPDATA' => 'C:\Users\john\AppData\Local',
            'USERPROFILE' => 'C:\Users\john',
        ]);
        $finder = new BinaryFinder('Windows', $env, static fn (string $path): bool => 'E:\choco\bin\exiftool.exe' === $path);

        self::assertSame('E:\choco\bin\exiftool.exe', $finder->find('exiftool'));
        $candidates = $finder->getCandidates('qpdf');
        self::assertContains('C:\Users\john\AppData\Local\Microsoft\WinGet\Links\qpdf.exe', $candidates);
        self::assertContains('C:\Users\john\scoop\shims\qpdf.exe', $candidates);
        foreach ($candidates as $candidate) {
            self::assertStringNotContainsString('%', $candidate);
            self::assertStringNotContainsString('/', $candidate);
        }
    }

    public function testNotFound(): void
    {
        $finder = new BinaryFinder('Linux', self::env([]), static fn (): bool => false);

        self::assertFalse($finder->isAvailable('qpdf'));

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('qpdf was not found, install it or set daif_chrome_pdf.qpdf_binary');

        $finder->find('qpdf');
    }

    public function testLibreOfficeNotFoundMessage(): void
    {
        $finder = new BinaryFinder('Windows', self::env([]), static fn (): bool => false);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('LibreOffice (soffice) was not found, install it or set daif_chrome_pdf.libreoffice_binary (or the LIBREOFFICE_PATH environment variable)');

        $finder->find('soffice');
    }

    public function testUnknownTool(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Unknown engine tool "pdftk"');

        (new BinaryFinder())->find('pdftk');
    }

    public function testResultIsCached(): void
    {
        $calls = 0;
        $finder = new BinaryFinder('Linux', self::env([]), static function (string $path) use (&$calls): bool {
            ++$calls;

            return '/usr/bin/qpdf' === $path;
        });

        $finder->find('qpdf');
        $count = $calls;
        self::assertSame('/usr/bin/qpdf', $finder->find('qpdf'));
        self::assertTrue($finder->isAvailable('qpdf'));
        self::assertSame($count, $calls);
    }

    /**
     * @param array<string, string> $variables
     *
     * @return \Closure(string): (string|false)
     */
    private static function env(array $variables): \Closure
    {
        return static fn (string $name): string|false => $variables[$name] ?? false;
    }
}
