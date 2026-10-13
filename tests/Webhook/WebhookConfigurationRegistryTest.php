<?php

namespace Daif\ChromePdfBundle\Tests\Webhook;

use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Webhook\WebhookConfigurationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class WebhookConfigurationRegistryTest extends TestCase
{
    public function testUrlsAndDefaults(): void
    {
        $registry = new WebhookConfigurationRegistry();
        $registry->add('default', [
            'success' => ['url' => 'https://example.com/success'],
            'extra_http_headers' => ['X-Token' => 'secret'],
        ]);

        self::assertSame([
            'success' => ['url' => 'https://example.com/success', 'method' => null],
            'error' => ['url' => 'https://example.com/success', 'method' => null],
            'extra_http_headers' => ['X-Token' => 'secret'],
        ], $registry->get('default'));
    }

    public function testRoutesAreGeneratedWithTheConfiguredRequestContext(): void
    {
        $routes = new RouteCollection();
        $routes->add('webhook_success', new Route('/webhook/{id}'));
        $routes->add('webhook_error', new Route('/webhook/error'));
        $urlGenerator = new UrlGenerator($routes, new RequestContext());
        $initialContext = $urlGenerator->getContext();

        $registry = new WebhookConfigurationRegistry($urlGenerator, RequestContext::fromUri('https://app.example.com/base'));
        $registry->add('default', [
            'success' => ['route' => ['webhook_success', ['id' => 42]], 'method' => 'PUT'],
            'error' => ['route' => ['webhook_error', []], 'method' => 'PATCH'],
            'events' => ['url' => 'https://example.com/events'],
        ]);

        self::assertSame([
            'success' => ['url' => 'https://app.example.com/base/webhook/42', 'method' => 'PUT'],
            'error' => ['url' => 'https://app.example.com/base/webhook/error', 'method' => 'PATCH'],
            'events' => ['url' => 'https://example.com/events'],
        ], $registry->get('default'));
        self::assertSame($initialContext, $urlGenerator->getContext());
    }

    public function testUnknownConfiguration(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);
        $this->expectExceptionMessage('Webhook configuration "missing" not found.');

        (new WebhookConfigurationRegistry())->get('missing');
    }

    public function testRouteWithoutRouter(): void
    {
        $registry = new WebhookConfigurationRegistry();
        $registry->add('default', ['success' => ['route' => ['webhook_success', []]]]);

        $this->expectException(InvalidBuilderConfiguration::class);
        $this->expectExceptionMessage('requires the router');

        $registry->get('default');
    }
}
