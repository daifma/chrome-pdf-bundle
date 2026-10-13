<?php

namespace Daif\ChromePdfBundle\Engine;

use Daif\ChromePdfBundle\Browser\WorkingDirectory;
use Daif\ChromePdfBundle\Exception\BrowserException;
use Daif\ChromePdfBundle\Exception\EngineException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs an engine command and turns failures into EngineException.
 *
 * @internal
 */
final class ProcessRunner
{
    private const MAX_OUTPUT_LENGTH = 2000;

    public function __construct(
        private readonly string $tool,
        private readonly LoggerInterface|null $logger,
        private readonly int $timeout,
    ) {
        if ($timeout <= 0) {
            throw new EngineException(\sprintf('The %s timeout must be a positive number of milliseconds, %d given.', $tool, $timeout));
        }
    }

    /**
     * @param list<string>               $command
     * @param list<int>                  $successCodes
     * @param list<string>|null          $displayCommand command logged instead of $command (hides secrets)
     * @param array<string, string>|null $env            variables added to the inherited environment
     *
     * @return string standard output
     */
    public function run(array $command, array $successCodes = [0], string|null $cwd = null, array|null $displayCommand = null, array|null $env = null): string
    {
        $this->logger?->debug('Running {tool}: {command}', ['tool' => $this->tool, 'command' => implode(' ', $displayCommand ?? $command)]);

        $process = new Process($command, $cwd, $env, null, $this->timeout / 1000);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new EngineException(\sprintf('%s timed out after %d ms.', $this->tool, $this->timeout), 0, $e);
        } catch (ProcessRuntimeException $e) {
            throw new EngineException(\sprintf('Unable to run %s: %s', $this->tool, $e->getMessage()), 0, $e);
        }

        $exitCode = $process->getExitCode() ?? -1;
        $errorOutput = trim($process->getErrorOutput());

        if (!\in_array($exitCode, $successCodes, true)) {
            $details = '' !== $errorOutput ? $errorOutput : trim($process->getOutput());

            throw new EngineException(\sprintf('%s failed with exit code %d: %s', $this->tool, $exitCode, self::truncate('' !== $details ? $details : '(no output)')));
        }

        if ('' !== $errorOutput) {
            $this->logger?->info('{tool} reported: {output}', ['tool' => $this->tool, 'output' => self::truncate($errorOutput)]);
        }

        return $process->getOutput();
    }

    public static function createWorkingDirectory(string|null $tempDir): WorkingDirectory
    {
        try {
            return WorkingDirectory::create($tempDir);
        } catch (BrowserException $e) {
            throw new EngineException($e->getMessage(), 0, $e);
        }
    }

    private static function truncate(string $output): string
    {
        return \strlen($output) > self::MAX_OUTPUT_LENGTH ? substr($output, 0, self::MAX_OUTPUT_LENGTH).'...' : $output;
    }
}
