<?php

declare(strict_types=1);

namespace Daif\ChromePdfBundle\Tests\Builder\Result;

use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Daif\ChromePdfBundle\Exception\ProcessorException;
use Daif\ChromePdfBundle\Processor\InMemoryProcessor;
use Daif\ChromePdfBundle\Processor\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ChromePdfFileResultTest extends TestCase
{
    private function getChromePdfFileResult(
        string|null $data = null,
        ProcessorInterface|null $processor = null,
        string $disposition = HeaderUtils::DISPOSITION_ATTACHMENT,
    ): ChromePdfFileResult {
        if (null === $data) {
            $data = 'firstsecondlast';
        }

        if (null === $processor) {
            $processor = new class implements ProcessorInterface {
                public function __invoke(string|null $fileName): \Generator
                {
                    $chunk = yield;

                    return 'finished';
                }
            };
        }

        return new ChromePdfFileResult($data, $processor, $disposition, 'test.pdf');
    }

    public function testProcessorIsCalledWithEveryChunkOnProcess(): void
    {
        $fileResult = $this->getChromePdfFileResult();

        $result = $fileResult->process();
        self::assertSame('finished', $result);
    }

    public function testProcessorIsCalledWithEveryChunkOnStream(): void
    {
        $fileResult = $this->getChromePdfFileResult(disposition: HeaderUtils::DISPOSITION_ATTACHMENT);

        $streamResponse = $fileResult->stream();

        $headers = $streamResponse->headers->all();

        self::assertArrayHasKey('x-accel-buffering', $headers);
        self::assertSame('no', $headers['x-accel-buffering'][0]);

        self::assertArrayHasKey('content-disposition', $headers);
        self::assertSame('attachment; filename=test.pdf', $headers['content-disposition'][0]);

        ob_start();
        $streamResponse->sendContent();

        $output = ob_get_clean();

        self::assertSame('firstsecondlast', $output);
    }

    public function testCannotProcessedAnAlreadyProcessedQuery(): void
    {
        $fileResult = $this->getChromePdfFileResult();

        $result = $fileResult->process();
        self::assertSame('finished', $result);

        self::expectException(ProcessorException::class);
        self::expectExceptionMessage('Already processed query.');
        $fileResult->process();
    }

    public function testCanChangeProcessorOnTheFly(): void
    {
        $fileResult = $this->getChromePdfFileResult();
        $fileResult->processor(new InMemoryProcessor());
        $fileResult->processor(new InMemoryProcessor());

        self::addToAssertionCount(1);
    }

    public function testCannotChangeProcessorOnTheFlyIfAlreadyProcessed(): void
    {
        $fileResult = $this->getChromePdfFileResult();

        $fileResult->process();

        self::expectException(ProcessorException::class);
        self::expectExceptionMessage('Already processed query.');
        $fileResult->processor(new InMemoryProcessor());
    }

    public function testCanChangeDispositionOnTheFly(): void
    {
        $fileResult = $this->getChromePdfFileResult();
        $fileResult->setDisposition(HeaderUtils::DISPOSITION_ATTACHMENT);
        $fileResult->setDisposition(HeaderUtils::DISPOSITION_INLINE);

        self::addToAssertionCount(1);
    }

    public function testCannotChangeDispositionOnTheFlyIfAlreadyProcessed(): void
    {
        $fileResult = $this->getChromePdfFileResult();

        $fileResult->process();

        self::expectException(ProcessorException::class);
        self::expectExceptionMessage('Already processed query.');
        $fileResult->setDisposition(HeaderUtils::DISPOSITION_ATTACHMENT);
    }
}
