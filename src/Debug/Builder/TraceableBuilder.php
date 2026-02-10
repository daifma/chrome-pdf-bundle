<?php

namespace Daif\ChromePdfBundle\Debug\Builder;

use Daif\ChromePdfBundle\Builder\BuilderFileInterface;
use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Symfony\Component\Stopwatch\Stopwatch;

final class TraceableBuilder implements BuilderFileInterface
{
    /**
     * @var list<array{'type': 'sync', 'time': float|null, 'memory': int|null, 'size': int<0, max>|null, 'fileName': string|null, 'calls': list<array{'method': string, 'class': class-string<BuilderInterface>, 'arguments': array<mixed>}>}>
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
        $response = $this->inner->generate();
        $swEvent?->stop();

        $this->files[] = [
            'type' => 'sync',
            'calls' => $this->calls,
            'time' => $swEvent?->getDuration(),
            'memory' => $swEvent?->getMemory(),
            'size' => $response->getContentLength(),
            'fileName' => $response->getFileName(),
        ];

        ++$this->totalGenerated;

        return $response;
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
     * @return list<array{'type': 'sync', 'time': float|null, 'memory': int|null, 'size': int<0, max>|null, 'fileName': string|null, 'calls': list<array{'class': class-string<BuilderInterface>, 'method': string, 'arguments': array<mixed>}>}>
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
