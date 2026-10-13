<?php

namespace Daif\ChromePdfBundle\Engine;

use Daif\ChromePdfBundle\Exception\EngineException;

/**
 * Locates the qpdf, exiftool and LibreOffice (soffice) executables.
 *
 * Order: configured binary, environment variable (QPDF_PATH, EXIFTOOL_PATH, LIBREOFFICE_PATH),
 * PATH, then well-known install locations of the current OS.
 */
final class BinaryFinder
{
    private const TOOLS = [
        'qpdf' => [
            'label' => 'qpdf',
            'env' => 'QPDF_PATH',
            'config' => 'qpdf_binary',
            'names' => ['qpdf'],
        ],
        'exiftool' => [
            'label' => 'exiftool',
            'env' => 'EXIFTOOL_PATH',
            'config' => 'exiftool_binary',
            'names' => ['exiftool'],
        ],
        'soffice' => [
            'label' => 'LibreOffice (soffice)',
            'env' => 'LIBREOFFICE_PATH',
            'config' => 'libreoffice_binary',
            'names' => ['soffice', 'libreoffice'],
        ],
    ];

    private const WINDOWS_EXTENSIONS = ['.exe', '.com', '.bat', '.cmd'];

    private readonly string $osFamily;

    /** @var \Closure(string): (string|false) */
    private readonly \Closure $env;

    /** @var \Closure(string): bool */
    private readonly \Closure $isExecutable;

    /** @var \Closure(string): list<string> */
    private readonly \Closure $glob;

    /** @var array<string, string> */
    private array $cache = [];

    /**
     * @param (callable(string): (string|false))|null $env          reads an environment variable
     * @param (callable(string): bool)|null           $isExecutable checks that a file exists and is executable
     * @param (callable(string): list<string>)|null   $glob         expands a wildcard pattern into existing paths
     */
    public function __construct(
        string|null $osFamily = null,
        callable|null $env = null,
        callable|null $isExecutable = null,
        callable|null $glob = null,
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
        $this->glob = null !== $glob ? $glob(...) : static fn (string $pattern): array => glob($pattern) ?: [];
    }

    /**
     * @param string $tool 'qpdf', 'exiftool' or 'soffice'
     *
     * @throws EngineException when the tool cannot be found
     */
    public function find(string $tool, string|null $configured = null): string
    {
        $definition = self::TOOLS[$tool] ?? throw new EngineException(\sprintf('Unknown engine tool "%s", expected one of "%s".', $tool, implode('", "', array_keys(self::TOOLS))));

        $cacheKey = $tool."\0".($configured ?? '');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        if (null !== $configured && '' !== trim($configured)) {
            $binary = $this->resolveExplicit(trim($configured));
            if (null === $binary) {
                throw new EngineException(\sprintf('The configured %s binary "%s" (daif_chrome_pdf.%s) does not exist or is not executable.', $definition['label'], $configured, $definition['config']));
            }

            return $this->cache[$cacheKey] = $binary;
        }

        $fromEnv = ($this->env)($definition['env']);
        if (\is_string($fromEnv) && '' !== trim($fromEnv)) {
            $binary = $this->resolveExplicit(trim($fromEnv));
            if (null === $binary) {
                throw new EngineException(\sprintf('The %s binary "%s" set in the %s environment variable does not exist or is not executable.', $definition['label'], trim($fromEnv), $definition['env']));
            }

            return $this->cache[$cacheKey] = $binary;
        }

        $candidates = $this->getCandidates($tool);
        foreach ($candidates as $candidate) {
            if (($this->isExecutable)($candidate)) {
                return $this->cache[$cacheKey] = $candidate;
            }
        }

        throw new EngineException(\sprintf('%s was not found, install it or set daif_chrome_pdf.%s (or the %s environment variable). Searched: %s', $definition['label'], $definition['config'], $definition['env'], implode(', ', $candidates)));
    }

    public function isAvailable(string $tool, string|null $configured = null): bool
    {
        try {
            $this->find($tool, $configured);

            return true;
        } catch (EngineException) {
            return false;
        }
    }

    /**
     * PATH entries first, then the well-known locations.
     *
     * @return list<string>
     */
    public function getCandidates(string $tool): array
    {
        $definition = self::TOOLS[$tool] ?? throw new EngineException(\sprintf('Unknown engine tool "%s", expected one of "%s".', $tool, implode('", "', array_keys(self::TOOLS))));

        $candidates = [];
        foreach ($definition['names'] as $name) {
            foreach ($this->pathCandidates($name) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        $wellKnown = match ($this->osFamily) {
            'Windows' => $this->getWindowsLocations($tool),
            'Darwin' => $this->getMacLocations($tool),
            default => $this->getUnixLocations($tool),
        };

        foreach ($wellKnown as $location) {
            if (str_contains($location, '*')) {
                foreach (($this->glob)($location) as $match) {
                    $candidates[] = $match;
                }
            } else {
                $candidates[] = $location;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * A bare command name ("qpdf") is looked up in PATH, anything else must be an existing file.
     */
    private function resolveExplicit(string $binary): string|null
    {
        $binary = $this->expand($binary);

        if (!str_contains($binary, '/') && !str_contains($binary, '\\')) {
            foreach ($this->pathCandidates($binary) as $candidate) {
                if (($this->isExecutable)($candidate)) {
                    return $candidate;
                }
            }

            return null;
        }

        return ($this->isExecutable)($binary) ? $binary : null;
    }

    /**
     * @return list<string>
     */
    private function pathCandidates(string $name): array
    {
        $windows = 'Windows' === $this->osFamily;
        $names = [$name];
        if ($windows && '' === pathinfo($name, \PATHINFO_EXTENSION)) {
            $names = array_map(static fn (string $extension): string => $name.$extension, self::WINDOWS_EXTENSIONS);
        }

        $candidates = [];
        foreach ($this->getPathDirectories() as $directory) {
            foreach ($names as $fileName) {
                $candidates[] = $windows ? rtrim($directory, '\\/').'\\'.$fileName : rtrim($directory, '/').'/'.$fileName;
            }
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private function getWindowsLocations(string $tool): array
    {
        $programFiles = array_values(array_unique(array_filter([
            $this->envDir('ProgramFiles') ?? 'C:\Program Files',
            $this->envDir('ProgramW6432'),
            $this->envDir('ProgramFiles(x86)') ?? 'C:\Program Files (x86)',
        ])));
        $chocolatey = ($this->envDir('ChocolateyInstall') ?? 'C:\ProgramData\chocolatey').'\bin';
        $localAppData = $this->envDir('LOCALAPPDATA');
        $userProfile = $this->envDir('USERPROFILE');

        $locations = [];
        switch ($tool) {
            case 'qpdf':
                foreach ($programFiles as $root) {
                    $locations[] = $root.'\qpdf*\bin\qpdf.exe';
                }
                break;
            case 'exiftool':
                $locations[] = 'C:\Strawberry\perl\bin\exiftool.bat';
                $locations[] = 'C:\Strawberry\perl\site\bin\exiftool.bat';
                $locations[] = 'C:\Strawberry\perl\bin\exiftool';
                $locations[] = 'C:\Strawberry\perl\site\bin\exiftool';
                foreach ($programFiles as $root) {
                    $locations[] = $root.'\exiftool\exiftool.exe';
                }
                break;
            case 'soffice':
                foreach ($programFiles as $root) {
                    $locations[] = $root.'\LibreOffice\program\soffice.exe';
                }
                break;
        }

        $executable = 'soffice' === $tool ? 'soffice.exe' : $tool.'.exe';
        $locations[] = $chocolatey.'\\'.$executable;
        if (null !== $localAppData) {
            $locations[] = $localAppData.'\Microsoft\WinGet\Links\\'.$executable;
        }
        if (null !== $userProfile) {
            $locations[] = $userProfile.'\scoop\shims\\'.$executable;
        }

        return $locations;
    }

    /**
     * @return list<string>
     */
    private function getMacLocations(string $tool): array
    {
        if ('soffice' === $tool) {
            $locations = ['/Applications/LibreOffice.app/Contents/MacOS/soffice'];
            $home = $this->envDir('HOME');
            if (null !== $home) {
                $locations[] = $home.'/Applications/LibreOffice.app/Contents/MacOS/soffice';
            }

            return [...$locations, '/opt/homebrew/bin/soffice', '/usr/local/bin/soffice'];
        }

        return ['/opt/homebrew/bin/'.$tool, '/usr/local/bin/'.$tool, '/opt/local/bin/'.$tool];
    }

    /**
     * @return list<string>
     */
    private function getUnixLocations(string $tool): array
    {
        if ('soffice' === $tool) {
            return [
                '/usr/bin/soffice',
                '/usr/local/bin/soffice',
                '/usr/bin/libreoffice',
                '/usr/local/bin/libreoffice',
                '/usr/lib/libreoffice/program/soffice',
                '/opt/libreoffice*/program/soffice',
                '/snap/bin/libreoffice',
            ];
        }

        return ['/usr/bin/'.$tool, '/usr/local/bin/'.$tool];
    }

    /**
     * @return list<string>
     */
    private function getPathDirectories(): array
    {
        $path = ($this->env)('PATH');
        if (!\is_string($path) || '' === $path) {
            // Windows exposes the variable as "Path"
            $path = ($this->env)('Path');
        }
        if (!\is_string($path) || '' === $path) {
            return [];
        }

        $separator = 'Windows' === $this->osFamily ? ';' : ':';

        $directories = array_map(static fn (string $dir): string => trim($dir, " \t\""), explode($separator, $path));

        return array_values(array_filter($directories, static fn (string $dir): bool => '' !== $dir));
    }

    private function envDir(string $name): string|null
    {
        $value = ($this->env)($name);

        return \is_string($value) && '' !== trim($value) ? rtrim(trim($value), '\\/') : null;
    }

    /**
     * Expands %VAR% (Windows) references.
     */
    private function expand(string $binary): string
    {
        return preg_replace_callback('/%([A-Za-z0-9_()]+)%/', function (array $matches): string {
            $value = ($this->env)($matches[1]);

            return \is_string($value) ? $value : $matches[0];
        }, $binary) ?? $binary;
    }
}
