<?php

namespace Daif\ChromePdfBundle\Tests\Processor;

use AsyncAws\S3\Result\CompleteMultipartUploadOutput;
use AsyncAws\S3\Result\CreateMultipartUploadOutput;
use AsyncAws\S3\Result\UploadPartOutput;
use AsyncAws\S3\S3Client;
use Daif\ChromePdfBundle\Bridge\AsyncAws\Processor\AsyncAwsS3MultiPartProcessor;
use Daif\ChromePdfBundle\Browser\StringChunk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AsyncAwsS3MultiPartProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function provideContents(): iterable
    {
        yield 'small document' => ['%PDF-small', [10]];
        yield 'empty document still uploads one part' => ['', [0]];
        yield 'document bigger than the minimal part size' => [str_repeat('a', 6 * 1024 * 1024), [6 * 1024 * 1024]];
    }

    /**
     * @param list<int> $expectedPartSizes
     */
    #[DataProvider('provideContents')]
    public function testSingleStringChunkIsUploaded(string $content, array $expectedPartSizes): void
    {
        $client = $this->createClient();

        $partSizes = [];
        $client->method('uploadPart')->willReturnCallback(function (array $input) use (&$partSizes): UploadPartOutput {
            self::assertSame('bucket', $input['Bucket']);
            self::assertSame('file.pdf', $input['Key']);
            self::assertSame('upload-id', $input['UploadId']);
            self::assertSame(\count($partSizes) + 1, $input['PartNumber']);
            $partSizes[] = \strlen($input['Body']);

            $output = $this->createMock(UploadPartOutput::class);
            $output->method('getEtag')->willReturn('etag-'.$input['PartNumber']);

            return $output;
        });

        $completed = $this->createMock(CompleteMultipartUploadOutput::class);
        $client->expects(self::once())->method('completeMultipartUpload')->willReturnCallback(static function (array $input) use ($completed, $expectedPartSizes): CompleteMultipartUploadOutput {
            self::assertCount(\count($expectedPartSizes), $input['MultipartUpload']['Parts']);
            self::assertSame(['PartNumber' => 1, 'ETag' => 'etag-1'], $input['MultipartUpload']['Parts'][0]);

            return $completed;
        });
        $client->expects(self::never())->method('abortMultipartUpload');

        $generator = (new AsyncAwsS3MultiPartProcessor($client, 'bucket'))('file.pdf');
        $generator->send(new StringChunk($content));

        self::assertSame($completed, $generator->getReturn());
        self::assertSame($expectedPartSizes, $partSizes);
    }

    public function testUploadIsAbortedOnFailure(): void
    {
        $client = $this->createClient();
        $client->method('uploadPart')->willThrowException(new \RuntimeException('network down'));
        $client->expects(self::once())->method('abortMultipartUpload');

        $generator = (new AsyncAwsS3MultiPartProcessor($client, 'bucket'))('file.pdf');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('network down');

        $generator->send(new StringChunk('%PDF'));
    }

    private function createClient(): S3Client&MockObject
    {
        $multipart = $this->createMock(CreateMultipartUploadOutput::class);
        $multipart->method('getUploadId')->willReturn('upload-id');

        $client = $this->createMock(S3Client::class);
        $client->method('createMultipartUpload')->willReturn($multipart);

        return $client;
    }
}
