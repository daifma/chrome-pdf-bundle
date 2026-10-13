<?php

namespace Daif\ChromePdfBundle\Tests\Messenger;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Messenger\AsyncGenerator;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AsyncGeneratorTest extends TestCase
{
    public function testItDispatchesTheBuilderState(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };

        $correlationId = (new AsyncGenerator($bus))->dispatch(HtmlPdfBuilder::class, ['webhookUrl' => 'https://webhook.test'], 'report.pdf', 'attachment');

        self::assertCount(1, $bus->messages);
        $message = $bus->messages[0];
        self::assertInstanceOf(GenerateDocumentMessage::class, $message);
        self::assertSame(HtmlPdfBuilder::class, $message->builderClass);
        self::assertSame(['webhookUrl' => 'https://webhook.test'], $message->bodyBag);
        self::assertSame('report.pdf', $message->fileName);
        self::assertSame('attachment', $message->disposition);
        self::assertSame($correlationId, $message->correlationId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $correlationId);
    }

    public function testTheMessageIsSerializable(): void
    {
        $message = new GenerateDocumentMessage(HtmlPdfBuilder::class, ['webhookUrl' => 'https://webhook.test', 'landscape' => true], null, 'inline', 'abc');

        self::assertEquals($message, unserialize(serialize($message)));
    }
}
