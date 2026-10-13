<?php

namespace Daif\ChromePdfBundle\Browser;

use Daif\ChromePdfBundle\Exception\BrowserException;

/**
 * Locates a Chrome / Chromium / Microsoft Edge executable.
 *
 * Order: explicit binary, CHROME_PATH environment variable, then OS specific candidates.
 * chrome-php's AutoDiscover is not used: on Windows it returns the unexpanded
 * "%ProgramFiles(x86)%\..." literal and it relies on shell commands.
 *
 * @internal
 */
final class ChromeFinder
{
    private readonly string $osFamily;

    /** @var \Closure(string): (string|false) */
    private readonly \Closure $env;

    /** @var \Closure(string): bool */
    private readonly \Closure $isExecutable;

    /**
     * @param (callable(string): (string|false))|null $env          reads an environment variable
     * @param (callable(string): bool)|null           $isExecutable checks that a file exists and is executable
     */
    public function __construct(
        string|null $osFamily = null,
        callable|null $env = null,
        callable|null $isExecutable = null,
    ) {
        $this->osFamily = $osFamily ?? \PHP_OS_FAMILY;
        $this->env = null !== $env ? $env(...) : static function (string $name): string|false {
            $value = getenv($name);
            if (false === $value && isset($_SERVER[$name]) && \is_string($_SERVER[$name])) {
                return $_SERVER[$name];
            }

            return $value;
        };
        $windows = 'Windows' === $this->osFamily;
        $this->isExecutable = null !== $isExecutable ? $isExecutable(...) : static fn (string $path): bool => is_file($path) && ($windows || is_executable($path));
    }

    public function find(string|null $explicitBinary = null): string
    {
        if (null !== $explicitBinary && '' !== trim($explicitBinary)) {
            return $this->expand($explicitBinary);
        }

        $fromEnv = ($this->env)('CHROME_PATH');
        if (\is_string($fromEnv) && '' !== trim($fromEnv)) {
            return $this->expand(trim($fromEnv));
        }

        $candidates = $this->getCandidates();
        foreach ($candidates as $candidate) {
            if (($this->isExecutable)($candidate)) {
                return $candidate;
            }
        }

        throw new BrowserException(\sprintf('Unable to find a Chrome, Chromium or Microsoft Edge executable. Set the "chrome_binary" option or the CHROME_PATH environment variable. Searched: %s', implode(', ', $candidates)));
    }

    /**
     * @return list<string>
     */
    public function getCandidates(): array
    {
        return match ($this->osFamily) {
            'Windows' => $this->getWindowsCandidates(),
            'Darwin' => $this->getMacCandidates(),
            default => $this->getUnixCandidates(),
        };
    }

    /**
     * @return list<string>
     */
    private function getWindowsCandidates(): array
    {
        $programFiles = $this->envDir('ProgramFiles') ?? 'C:\Program Files';
        $programFilesX86 = $this->envDir('ProgramFiles(x86)') ?? 'C:\Program Files (x86)';
        $localAppData = $this->envDir('LOCALAPPDATA');

        $roots = array_values(array_unique(array_filter([$programFiles, $programFilesX86, $localAppData])));

        $candidates = [];
        foreach (['Google\Chrome\Application\chrome.exe', 'Chromium\Application\chrome.exe', 'Microsoft\Edge\Application\msedge.exe'] as $suffix) {
            foreach ($roots as $root) {
                $candidates[] = $root.'\\'.$suffix;
            }
        }

        foreach ($this->getPathDirectories() as $directory) {
            foreach (['chrome.exe', 'chromium.exe', 'msedge.exe'] as $name) {
                $candidates[] = rtrim($directory, '\\/').'\\'.$name;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    private function getMacCandidates(): array
    {
        $bundles = [
            'Google Chrome.app/Contents/MacOS/Google Chrome',
            'Chromium.app/Contents/MacOS/Chromium',
            'Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
        ];

        $candidates = [];
        $home = $this->envDir('HOME');
        foreach ($bundles as $bundle) {
            $candidates[] = '/Applications/'.$bundle;
            if (null !== $home) {
                $candidates[] = $home.'/Applications/'.$bundle;
            }
        }

        return [...$candidates, ...$this->getUnixCandidates()];
    }

    /**
     * @return list<string>
     */
    private function getUnixCandidates(): array
    {
        $names = ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'microsoft-edge', 'microsoft-edge-stable'];
        $directories = [...$this->getPathDirectories(), '/usr/bin', '/usr/local/bin', '/snap/bin', '/opt/google/chrome'];

        $candidates = [];
        foreach ($names as $name) {
            foreach ($directories as $directory) {
                $candidates[] = rtrim($directory, '/').'/'.$name;
            }
        }
        $candidates[] = '/opt/google/chrome/chrome';
        $candidates[] = '/opt/microsoft/msedge/msedge';

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    private function getPathDirectories(): array
    {
        $path = ($this->env)('PATH');
        if (!\is_string($path) || '' === $path) {
            return [];
        }

        $separator = 'Windows' === $this->osFamily ? ';' : ':';

        return array_values(array_filter(array_map('trim', explode($separator, $path)), static fn (string $dir): bool => '' !== $dir));
    }

    private function envDir(string $name): string|null
    {
        $value = ($this->env)($name);

        return \is_string($value) && '' !== trim($value) ? rtrim(trim($value), '\\/') : null;
    }

    /**
     * Expands %VAR% (Windows) references in a configured binary path.
     */
    private function expand(string $binary): string
    {
        return preg_replace_callback('/%([A-Za-z0-9_()]+)%/', function (array $matches): string {
            $value = ($this->env)($matches[1]);

            return \is_string($value) ? $value : $matches[0];
        }, $binary) ?? $binary;
    }
}
