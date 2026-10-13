<?php

namespace Daif\ChromePdfBundle\Tests\Messenger;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Util\DownloadGuard;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\RuntimeException;
use Daif\ChromePdfBundle\Formatter\AssetBaseDirFormatter;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentHandler;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentMessage;
use Daif\ChromePdfBundle\Tests\Functional\Debug\FakeBrowser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class GenerateDocumentHandlerTest extends TestCase
{
    /**
     * @var list<array{method: string, url: string, options: array<string, mixed>}>
     */
    private array $requests = [];

    private int $statusCode = 204;

    protected function setUp(): void
    {
        $this->requests = [];
        $this->statusCode = 204;
    }

    public function testItPostsTheDocumentToTheSuccessUrl(): void
    {
        $this->createHandler()($this->createMessage([
            'webhookUrl' => 'https://webhook.test/success',
            'webhookMethod' => 'PUT',
            'webhookExtraHttpHeaders' => ['X-Token' => 'secret'],
        ], 'report.pdf'));

        self::assertCount(1, $this->requests);
        [$request] = $this->requests;
        self::assertSame('PUT', $request['method']);
        self::assertSame('https://webhook.test/success', $request['url']);
        self::assertSame(FakeBrowser::PDF, $request['options']['body']);
        self::assertContains('Content-Type: application/pdf', $request['options']['headers']);
        self::assertContains('Content-Disposition: attachment; filename=report.pdf', $request['options']['headers']);
        self::assertContains('X-Token: secret', $request['options']['headers']);
    }

    public function testADefaultFileNameIsSent(): void
    {
        $this->createHandler()($this->createMessage(['webhookUrl' => 'https://webhook.test/success'], null));

        self::assertContains('Content-Disposition: attachment; filename=document.pdf', $this->requests[0]['options']['headers']);
    }

    public function testTraceableBuildersAreUnwrapped(): void
    {
        $handler = $this->createHandler(static fn (HtmlPdfBuilder $builder): object => new TraceableBuilder($builder, null));
        $handler($this->createMessage(['webhookUrl' => 'https://webhook.test/success'], null));

        self::assertSame(FakeBrowser::PDF, $this->requests[0]['options']['body']);
    }

    public function testAFailureIsSentToTheErrorUrl(): void
    {
        $message = new GenerateDocumentMessage(HtmlPdfBuilder::class, [
            'webhookUrl' => 'https://webhook.test/success',
            'webhookErrorUrl' => 'https://webhook.test/error',
            'webhookErrorMethod' => 'PATCH',
            'webhookEventsUrl' => 'https://webhook.test/events',
        ], null, 'inline', 'correlation');

        $this->createHandler()($message);

        self::assertCount(2, $this->requests);
        self::assertSame('PATCH', $this->requests[0]['method']);
        self::assertSame('https://webhook.test/error', $this->requests[0]['url']);
        $payload = json_decode($this->requests[0]['options']['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(400, $payload['status']);

        self::assertSame('https://webhook.test/events', $this->requests[1]['url']);
        $event = json_decode($this->requests[1]['options']['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($event);
        self::assertSame('webhook.error', $event['type']);
        self::assertSame('correlation', $event['correlationId']);
    }

    public function testTheErrorUrlDefaultsToTheSuccessUrl(): void
    {
        // @phpstan-ignore argument.type
        $this->createHandler()(new GenerateDocumentMessage(\stdClass::class, ['webhookUrl' => 'https://webhook.test/success'], null, 'inline'));

        self::assertCount(1, $this->requests);
        self::assertSame('https://webhook.test/success', $this->requests[0]['url']);
        $payload = json_decode($this->requests[0]['options']['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(500, $payload['status']);
        self::assertStringStartsWith('The document could not be generated', $payload['message']);
    }

    public function testAnUnreachableWebhookThrowsSoMessengerCanRetry(): void
    {
        $this->statusCode = 503;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned the HTTP status 503');

        $this->createHandler()($this->createMessage(['webhookUrl' => 'https://webhook.test/success'], null));
    }

    public function testAWebhookUrlIsRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);

        $this->createHandler()(new GenerateDocumentMessage(HtmlPdfBuilder::class, [], null, 'inline'));
    }

    /**
     * @param array<string, mixed> $webhook
     */
    private function createMessage(array $webhook, string|null $fileName): GenerateDocumentMessage
    {
        $builder = $this->createBuilder()->contentFile('files/content.html');

        return new GenerateDocumentMessage(HtmlPdfBuilder::class, $webhook + $builder->getBodyBag()->all(), $fileName, 'attachment');
    }

    private function createBuilder(): HtmlPdfBuilder
    {
        $builder = new HtmlPdfBuilder();
        $builder->setContainer(new ServiceLocator([
            'daif_chrome_pdf.browser' => static fn (): object => new FakeBrowser(),
            'asset_base_dir_formatter' => static fn (): object => new AssetBaseDirFormatter(__DIR__.'/../Fixtures', ['.']),
        ]));

        return $builder;
    }

    public function testABlockedWebhookUrlIsNotCalledNorRetried(): void
    {
        $handler = $this->createHandler(null, new DownloadGuard([], ['#^https://internal\\.#']));

        try {
            $handler(new GenerateDocumentMessage(HtmlPdfBuilder::class, ['webhookUrl' => 'https://internal.test/hook'], null, 'inline'));
            self::fail('Expected an UnrecoverableMessageHandlingException.');
        } catch (UnrecoverableMessageHandlingException $e) {
            self::assertStringContainsString('is not allowed', $e->getMessage());
        }
        self::assertSame([], $this->requests);
    }

    private function createHandler(\Closure|null $decorate = null, DownloadGuard|null $urlGuard = null): GenerateDocumentHandler
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('', ['http_code' => $this->statusCode]);
        });

        return new GenerateDocumentHandler(
            new ServiceLocator([
                HtmlPdfBuilder::class => function () use ($decorate): object {
                    $builder = $this->createBuilder();

                    return null !== $decorate ? $decorate($builder) : $builder;
                },
            ]),
            $httpClient,
            null,
            $urlGuard,
        );
    }
}
