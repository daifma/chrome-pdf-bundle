<?php

namespace Daif\ChromePdfBundle\Builder\Result;

use Daif\ChromePdfBundle\Browser\StringChunk;
use Daif\ChromePdfBundle\Exception\ProcessorException;
use Daif\ChromePdfBundle\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @template-covariant TProcessorResult of mixed = mixed
 */
class ChromePdfFileResult
{
    private bool $processed = false;

    /**
     * @param ProcessorInterface<TProcessorResult> $processor
     */
    public function __construct(
        private readonly string $data,
        private ProcessorInterface $processor,
        private string $disposition,
        private readonly string|null $fileName = null,
    ) {
    }

    /**
     * @template TNewProcessorResult of mixed = mixed
     *
     * @param ProcessorInterface<TNewProcessorResult> $processor
     *
     * @phpstan-assert ProcessorInterface<TNewProcessorResult> $this->processor
     *
     * @phpstan-this-out self<TNewProcessorResult>
     */
    public function processor(ProcessorInterface $processor): self
    {
        if ($this->processed) {
            throw new ProcessorException('Already processed query.');
        }

        $this->processor = $processor;

        return $this;
    }

    public function setDisposition(string $disposition): self
    {
        if ($this->processed) {
            throw new ProcessorException('Already processed query.');
        }

        $this->disposition = $disposition;

        return $this;
    }

    /**
     * @return non-negative-int
     */
    public function getContentLength(): int
    {
        return \strlen($this->data);
    }

    public function getFileName(): string|null
    {
        return $this->fileName;
    }

    public function getStatusCode(): int
    {
        return 200;
    }

    /**
     * @return TProcessorResult
     */
    public function process(): mixed
    {
        if ($this->processed) {
            throw new ProcessorException('Already processed query.');
        }

        $this->processed = true;

        $generator = ($this->processor)($this->fileName);
        $generator->send(new StringChunk($this->data));

        return $generator->getReturn();
    }

    public function stream(): StreamedResponse
    {
        if ($this->processed) {
            throw new ProcessorException('Already processed query.');
        }

        $this->processed = true;

        $headers = [
            'Content-Type' => ['application/pdf'],
            'Content-Length' => [(string) \strlen($this->data)],
            'X-Accel-Buffering' => ['no'],
        ];

        if ($this->fileName) {
            $headers['Content-Disposition'] = [HeaderUtils::makeDisposition($this->disposition, $this->fileName)];
        }

        return new StreamedResponse(
            function (): void {
                $generator = ($this->processor)($this->fileName);
                $chunk = new StringChunk($this->data);
                $generator->send($chunk);
                echo $this->data;
                flush();
            },
            200,
            $headers,
        );
    }
}
