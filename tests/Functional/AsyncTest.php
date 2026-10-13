<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Daif\ChromePdfBundle\ChromePdfFacadeInterface;
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Tests\Functional\Async\WebhookRecorder;

/**
 * generateAsync() through a real Messenger bus (synchronous handling) and a mocked webhook endpoint.
 */
final class AsyncTest extends AbstractChromePdfWebTestCase
{
    protected function setUp(): void
    {
        self::bootKernel(['test_case' => 'Async']);
    }

    public function testTheGeneratedPdfIsPostedToTheWebhook(): void
    {
        $this->pdf()->html()
            ->contentFile('content.html')
            ->fileName('report.pdf')
            ->webhookUrl('https://webhook.test/success')
            ->webhookExtraHeaders(['Authorization' => 'Bearer token'])
            ->generateAsync()
        ;

        $requests = $this->recorder()->requests;
        self::assertCount(1, $requests);
        [$request] = $requests;

        self::assertSame('POST', $request['method']);
        self::assertSame('https://webhook.test/success', $request['url']);
        self::assertSame('application/pdf', $request['headers']['content-type'] ?? null);
        self::assertSame('inline; filename=report.pdf', $request['headers']['content-disposition'] ?? null);
        self::assertSame('Bearer token', $request['headers']['authorization'] ?? null);
        self::assertStringStartsWith('%PDF-', $request['body']);
    }

    public function testANamedWebhookConfigurationIsUsed(): void
    {
        $this->pdf()->html()
            ->contentFile('content.html')
            ->webhookConfiguration('archive')
            ->generateAsync()
        ;

        $requests = $this->recorder()->requests;
        self::assertCount(1, $requests);
        self::assertSame('PUT', $requests[0]['method']);
        self::assertSame('https://webhook.test/archive', $requests[0]['url']);
        self::assertSame('secret', $requests[0]['headers']['x-token'] ?? null);
        self::assertStringStartsWith('%PDF-', $requests[0]['body']);
    }

    public function testAFailureIsPostedToTheErrorWebhook(): void
    {
        // The fake browser fails on this content, in the handler.
        $this->pdf()->html()
            ->contentFile('failing.html')
            ->webhookUrl('https://webhook.test/success')
            ->webhookErrorUrl('https://webhook.test/error', 'PATCH')
            ->generateAsync()
        ;

        $requests = $this->recorder()->requests;
        self::assertCount(1, $requests);
        self::assertSame('PATCH', $requests[0]['method']);
        self::assertSame('https://webhook.test/error', $requests[0]['url']);
        self::assertSame('application/json', $requests[0]['headers']['content-type'] ?? null);

        $payload = json_decode($requests[0]['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(500, $payload['status'] ?? null);
        self::assertStringStartsWith('The document could not be generated, see the server logs', (string) ($payload['message'] ?? ''));
        self::assertStringNotContainsString('Simulated browser failure', (string) ($payload['message'] ?? ''));
    }

    public function testTheEventsWebhookReceivesTheOutcome(): void
    {
        $this->pdf()->html()
            ->contentFile('content.html')
            ->webhookUrl('https://webhook.test/success')
            ->webhookEventsUrl('https://webhook.test/events')
            ->generateAsync()
        ;

        $requests = $this->recorder()->requests;
        self::assertCount(2, $requests);
        self::assertSame('https://webhook.test/events', $requests[1]['url']);

        $event = json_decode($requests[1]['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($event);
        self::assertSame('webhook.success', $event['type'] ?? null);
        self::assertIsString($event['correlationId'] ?? null);
        self::assertNotSame('', $event['correlationId']);
    }

    public function testFileBasedBuildersSurviveTheMessageRoundTrip(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $this->pdf()->merge()
            ->files('pdf/document.pdf', 'pdf/other_document.pdf')
            ->webhookUrl('https://webhook.test/merged')
            ->generateAsync()
        ;

        $requests = $this->recorder()->requests;
        self::assertCount(1, $requests);
        self::assertSame('https://webhook.test/merged', $requests[0]['url']);
        self::assertStringStartsWith('%PDF-', $requests[0]['body']);
    }

    public function testAWebhookUrlIsRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);

        $this->pdf()->html()->contentFile('content.html')->generateAsync();
    }

    private function pdf(): ChromePdfInterface
    {
        $facade = self::getContainer()->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        return $facade->pdf();
    }

    private function recorder(): WebhookRecorder
    {
        $recorder = self::getContainer()->get('test.webhook_recorder');
        self::assertInstanceOf(WebhookRecorder::class, $recorder);

        return $recorder;
    }
}
