<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\LogicException;
use Daif\ChromePdfBundle\Webhook\WebhookConfigurationRegistryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class WebhookTest extends AbstractBuilderTestCase
{
    public function testWebhookUrls(): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)
            ->webhookUrl('https://example.com/success', 'PUT')
            ->webhookErrorUrl('https://example.com/error')
            ->webhookEventsUrl('https://example.com/events')
            ->webhookExtraHeaders(['Authorization' => 'Bearer x'])
            ->addWebhookExtraHeaders(['X-Id' => '1'])
        ;

        self::assertSame([
            'webhookUrl' => 'https://example.com/success',
            'webhookMethod' => 'PUT',
            'webhookErrorUrl' => 'https://example.com/error',
            'webhookEventsUrl' => 'https://example.com/events',
            'webhookExtraHttpHeaders' => ['Authorization' => 'Bearer x', 'X-Id' => '1'],
        ], $builder->getBodyBag()->all());

        $builder->webhook([]);
        self::assertSame([], $builder->getBodyBag()->all());
    }

    public function testWebhookRoutesAreResolvedIntoAbsoluteUrls(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->method('generate')->willReturnCallback(static function (string $name, array $parameters, int $type): string {
            self::assertSame(UrlGeneratorInterface::ABSOLUTE_URL, $type);

            return 'https://example.com/'.$name.'?'.http_build_query($parameters);
        });
        $this->extraServices['router'] = $router;

        $builder = $this->createBuilder(MergePdfBuilder::class)->webhook([
            'success' => ['route' => ['webhook_success', ['id' => 1]], 'method' => 'PATCH'],
            'error' => ['route' => 'webhook_error'],
            'events' => ['route' => 'webhook_events'],
            'extra_http_headers' => ['X-Id' => '1'],
        ]);

        self::assertSame([
            'webhookUrl' => 'https://example.com/webhook_success?id=1',
            'webhookMethod' => 'PATCH',
            'webhookErrorUrl' => 'https://example.com/webhook_error?',
            'webhookEventsUrl' => 'https://example.com/webhook_events?',
            'webhookExtraHttpHeaders' => ['X-Id' => '1'],
        ], $builder->getBodyBag()->all());
    }

    public function testWebhookConfiguration(): void
    {
        $registry = $this->createMock(WebhookConfigurationRegistryInterface::class);
        $registry->method('get')->with('default')->willReturn([
            'success' => ['url' => 'https://example.com/success', 'method' => 'POST'],
            'error' => ['url' => 'https://example.com/error', 'method' => null],
            'events' => ['url' => 'https://example.com/events'],
            'extra_http_headers' => ['X-Id' => '1'],
        ]);
        $this->extraServices['webhook_configuration_registry'] = $registry;

        $builder = $this->createBuilder(HtmlPdfBuilder::class)->webhook(['config_name' => 'default', 'error' => ['url' => 'https://example.com/other-error']]);

        self::assertSame([
            'webhookUrl' => 'https://example.com/success',
            'webhookMethod' => 'POST',
            'webhookErrorUrl' => 'https://example.com/other-error',
            'webhookEventsUrl' => 'https://example.com/events',
            'webhookExtraHttpHeaders' => ['X-Id' => '1'],
        ], $builder->getBodyBag()->all());
    }

    public function testWebhookConfigurationRequiresTheRegistry(): void
    {
        $this->expectException(LogicException::class);

        $this->createBuilder(HtmlPdfBuilder::class)->webhookConfiguration('default');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidWebhooks(): iterable
    {
        yield 'no success' => [['error' => ['url' => 'https://example.com']], 'At least a "success" key is required.'];
        yield 'url and route' => [['success' => ['url' => 'https://example.com', 'route' => 'home']], 'You must provide "url" or "route" keys for "success" configuration.'];
        yield 'method' => [['success' => ['url' => 'https://example.com', 'method' => 'GET']], '"POST" "PUT", "PATCH" are the only available methods for "success" configuration.'];
        yield 'route name' => [['success' => ['route' => [1]]], 'You must provide a valid route name for "success" configuration.'];
    }

    /**
     * @param array<string, mixed> $webhook
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidWebhooks')]
    public function testInvalidWebhook(array $webhook, string $message): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);
        $this->expectExceptionMessage($message);

        $this->createBuilder(HtmlPdfBuilder::class)->webhook($webhook);
    }

    public function testInvalidWebhookMethod(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->webhookUrl('https://example.com', 'GET'); // @phpstan-ignore argument.type
    }
}
