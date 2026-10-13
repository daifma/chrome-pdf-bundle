<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\ChromeFinder;
use Daif\ChromePdfBundle\Exception\BrowserException;
use PHPUnit\Framework\TestCase;

final class ChromeFinderTest extends TestCase
{
    public function testExplicitBinaryWinsOverEverything(): void
    {
        $finder = new ChromeFinder('Linux', self::env(['CHROME_PATH' => '/opt/env-chrome']), static fn (): bool => true);

        self::assertSame('/opt/my-chrome', $finder->find('/opt/my-chrome'));
    }

    public function testExplicitBinaryExpandsWindowsVariables(): void
    {
        $finder = new ChromeFinder('Windows', self::env(['ProgramFiles(x86)' => 'C:\Program Files (x86)']), static fn (): bool => false);

        self::assertSame('C:\Program Files (x86)\Google\Chrome\Application\chrome.exe', $finder->find('%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe'));
    }

    public function testChromePathEnvironmentVariable(): void
    {
        $finder = new ChromeFinder('Linux', self::env(['CHROME_PATH' => ' /opt/env-chrome ']), static fn (): bool => true);

        self::assertSame('/opt/env-chrome', $finder->find());
        self::assertSame('/opt/env-chrome', $finder->find(''), 'an empty explicit binary falls back to the environment');
    }

    public function testWindowsCandidatesUseExpandedEnvironment(): void
    {
        $env = self::env([
            'ProgramFiles' => 'D:\Apps',
            'ProgramFiles(x86)' => 'D:\Apps (x86)',
            'LOCALAPPDATA' => 'C:\Users\john\AppData\Local',
        ]);
        $finder = new ChromeFinder('Windows', $env, static fn (): bool => false);
        $candidates = $finder->getCandidates();

        self::assertSame('D:\Apps\Google\Chrome\Application\chrome.exe', $candidates[0]);
        self::assertContains('D:\Apps (x86)\Google\Chrome\Application\chrome.exe', $candidates);
        self::assertContains('C:\Users\john\AppData\Local\Google\Chrome\Application\chrome.exe', $candidates);
        self::assertContains('C:\Users\john\AppData\Local\Chromium\Application\chrome.exe', $candidates);
        self::assertContains('D:\Apps (x86)\Microsoft\Edge\Application\msedge.exe', $candidates);
        foreach ($candidates as $candidate) {
            self::assertStringNotContainsString('%', $candidate);
        }
    }

    public function testWindowsFallsBackToEdge(): void
    {
        $edge = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
        $finder = new ChromeFinder('Windows', self::env([]), static fn (string $path): bool => $edge === $path);

        self::assertSame($edge, $finder->find());
    }

    public function testWindowsSearchesPath(): void
    {
        $finder = new ChromeFinder('Windows', self::env(['PATH' => 'C:\Windows;C:\Tools\chrome\\']), static fn (string $path): bool => 'C:\Tools\chrome\chrome.exe' === $path);

        self::assertSame('C:\Tools\chrome\chrome.exe', $finder->find());
    }

    public function testLinuxOrder(): void
    {
        $existing = ['/usr/bin/chromium', '/usr/bin/google-chrome-stable'];
        $finder = new ChromeFinder('Linux', self::env(['PATH' => '/usr/local/sbin:/usr/bin']), static fn (string $path): bool => \in_array($path, $existing, true));

        self::assertSame('/usr/bin/google-chrome-stable', $finder->find());
        self::assertContains('/usr/bin/microsoft-edge', $finder->getCandidates());
        self::assertContains('/usr/bin/chromium-browser', $finder->getCandidates());
    }

    public function testMacBundles(): void
    {
        $finder = new ChromeFinder('Darwin', self::env(['HOME' => '/Users/john']), static fn (string $path): bool => '/Users/john/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge' === $path);

        self::assertSame('/Users/john/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge', $finder->find());
        self::assertSame('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', $finder->getCandidates()[0]);
    }

    public function testNotFound(): void
    {
        $finder = new ChromeFinder('Linux', self::env([]), static fn (): bool => false);

        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage('CHROME_PATH');

        $finder->find();
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
