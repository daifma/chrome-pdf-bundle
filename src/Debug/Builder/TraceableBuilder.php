<?php

namespace Daif\ChromePdfBundle\Debug\Builder;

use Daif\ChromePdfBundle\Builder\BuilderAsyncInterface;
use Daif\ChromePdfBundle\Builder\BuilderFileInterface;
use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Symfony\Component\Stopwatch\Stopwatch;

final class TraceableBuilder implements BuilderFileInterface, BuilderAsyncInterface
{
    /**
     * @var list<array{'type': 'sync'|'async', 'time': float|null, 'memory': int|null, 'size': int<0, max>|null, 'fileName': string|null, 'contentType': string|null, 'calls': list<array{'method': string, 'class': class-string<BuilderInterface>, 'arguments': array<mixed>}>}>
     */
    private array $files = [];

    /**
     * @var list<array{'class': class-string<BuilderInterface>, 'method': string, 'arguments': array<mixed>}>
     */
    private array $calls = [];

    private int $totalGenerated = 0;

    private static int $count = 0;

    public function __construct(
        private readonly BuilderFileInterface $inner,
        private readonly Stopwatch|null $stopwatch,
    ) {
    }

    public function generate(): ChromePdfFileResult
    {
        $name = self::$count.'.'.$this->inner::class.'::'.__FUNCTION__;
        ++self::$count;

        $swEvent = $this->stopwatch?->start($name, 'chrome_pdf.generate');
        try {
            $response = $this->inner->generate();
        } finally {
            $swEvent?->stop();
        }

        $this->files[] = [
            'type' => 'sync',
            'calls' => $this->calls,
            'time' => $swEvent?->getDuration(),
            'memory' => $swEvent?->getMemory(),
            'size' => $response->getContentLength(),
            'fileName' => $response->getFileName(),
            'contentType' => $response->getContentType(),
        ];

        ++$this->totalGenerated;

        return $response;
    }

    public function generateAsync(): void
    {
        if (!$this->inner instanceof BuilderAsyncInterface) {
            throw new \LogicException(\sprintf('The inner builder of %s must implement %s.', self::class, BuilderAsyncInterface::class));
        }

        $name = self::$count.'.'.$this->inner::class.'::'.__FUNCTION__;
        ++self::$count;

        $swEvent = $this->stopwatch?->start($name, 'chrome_pdf.generate');
        try {
            $this->inner->generateAsync();
        } finally {
            $swEvent?->stop();
        }

        $this->files[] = [
            'type' => 'async',
            'calls' => $this->calls,
            'time' => $swEvent?->getDuration(),
            'memory' => $swEvent?->getMemory(),
            'size' => null,
            'fileName' => null,
            'contentType' => null,
        ];

        ++$this->totalGenerated;
    }

    /**
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->inner->$name(...$arguments);

        $this->calls[] = [
            'class' => $this->inner::class,
            'method' => $name,
            'arguments' => $arguments,
        ];

        if ($result === $this->inner) {
            return $this;
        }

        return $result;
    }

    /**
     * @return list<array{'type': 'sync'|'async', 'time': float|null, 'memory': int|null, 'size': int<0, max>|null, 'fileName': string|null, 'contentType': string|null, 'calls': list<array{'class': class-string<BuilderInterface>, 'method': string, 'arguments': array<mixed>}>}>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function getInner(): BuilderFileInterface
    {
        return $this->inner;
    }
}
