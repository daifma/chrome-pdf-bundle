<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\UrlGeneratorAwareTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\WebhookConfigurationRegistryAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\EnumNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\VariableNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\WebhookNodeBuilder;

/**
 * Webhooks used by generateAsync(): once a Messenger worker generated the file, it is sent to the success URL;
 * on failure, a JSON body {status, message} is sent to the error URL (the success URL when not set).
 * Routes are resolved into absolute URLs when the method is called.
 *
 * @phpstan-type WebhookConfiguration array{
 *     config_name?: string,
 *     success?: array{
 *          url?: string,
 *          route?: string|array{0: string, 1?: array<string, mixed>},
 *          method?: 'PUT'|'PATCH'|'POST'|null
 *      },
 *     error?: array{
 *          url?: string,
 *          route?: string|array{0: string, 1?: array<string, mixed>},
 *          method?: 'PUT'|'PATCH'|'POST'|null
 *      },
 *     extra_http_headers?: array<string, string>,
 *     events?: array{
 *          url?: string,
 *          route?: string|array{0: string, 1?: array<string, mixed>}
 *      }
 *  }
 *
 * @package Behavior\\Async
 */
trait WebhookTrait
{
    use UrlGeneratorAwareTrait;
    use WebhookConfigurationRegistryAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * @param WebhookConfiguration $webhook
     *
     * @example webhook(['config_name' => 'my_config', 'success' => ['url' => 'https://my.webhook.url/success', 'method' => 'POST'], 'error' => ['route' => 'my_route_error', 'method' => 'POST'], 'events' => ['url' => 'https://my.webhook.url/events']])
     */
    #[WithConfigurationNode(new WebhookNodeBuilder('webhook', children: [
        new ScalarNodeBuilder('config_name', restrictTo: 'string'),
        new ArrayNodeBuilder('success', children: [
            new ScalarNodeBuilder('url', restrictTo: 'string'),
            new VariableNodeBuilder('route'),
            new EnumNodeBuilder('method', values: ['POST', 'PUT', 'PATCH']),
        ]),
        new ArrayNodeBuilder('error', children: [
            new ScalarNodeBuilder('url', restrictTo: 'string'),
            new VariableNodeBuilder('route'),
            new EnumNodeBuilder('method', values: ['POST', 'PUT', 'PATCH']),
        ]),
        new ArrayNodeBuilder('extra_http_headers', normalizeKeys: false, useAttributeAsKey: 'name', prototype: 'variable'),
        new ArrayNodeBuilder('events', children: [
            new ScalarNodeBuilder('url', restrictTo: 'string'),
            new VariableNodeBuilder('route'),
        ]),
    ]))]
    public function webhook(array $webhook): static
    {
        if ([] === $webhook) {
            foreach (['webhookUrl', 'webhookMethod', 'webhookErrorUrl', 'webhookErrorMethod', 'webhookExtraHttpHeaders', 'webhookEventsUrl'] as $key) {
                $this->getBodyBag()->unset($key);
            }

            return $this;
        }

        $this->webhookConfigurationValidator($webhook);

        if (isset($webhook['config_name'])) {
            $this->webhookConfiguration($webhook['config_name']);
        }

        if (isset($webhook['success']['route'])) {
            $route = (array) $webhook['success']['route'];
            $this->webhookRoute($route[0], $route[1] ?? [], $webhook['success']['method'] ?? null);
        }

        if (isset($webhook['error']['route'])) {
            $route = (array) $webhook['error']['route'];
            $this->webhookErrorRoute($route[0], $route[1] ?? [], $webhook['error']['method'] ?? null);
        }

        if (isset($webhook['events']['route'])) {
            $route = (array) $webhook['events']['route'];
            $this->webhookEventsRoute($route[0], $route[1] ?? []);
        }

        if (isset($webhook['success']['url'])) {
            $this->webhookUrl($webhook['success']['url'], $webhook['success']['method'] ?? null);
        }

        if (isset($webhook['error']['url'])) {
            $this->webhookErrorUrl($webhook['error']['url'], $webhook['error']['method'] ?? null);
        }

        if (isset($webhook['events']['url'])) {
            $this->webhookEventsUrl($webhook['events']['url']);
        }

        if (isset($webhook['extra_http_headers']) && [] !== $webhook['extra_http_headers']) {
            $this->webhookExtraHeaders($webhook['extra_http_headers']);
        }

        return $this;
    }

    /**
     * Sets the webhook for cases of success.
     * Optionally sets a custom HTTP method for such endpoint among : POST, PUT or PATCH.
     *
     * @param 'POST'|'PUT'|'PATCH'|null $method
     *
     * @example webhookUrl('https://my.webhook.url', 'PUT')
     */
    public function webhookUrl(string $url, string|null $method = null): static
    {
        self::validateWebhookMethod($method);

        $this->getBodyBag()->set('webhookUrl', $url);
        if ($method) {
            $this->getBodyBag()->set('webhookMethod', $method);
        }

        return $this;
    }

    /**
     * Sets the webhook for cases of error.
     * Optionally sets a custom HTTP method for such endpoint among : POST, PUT or PATCH.
     *
     * @param 'POST'|'PUT'|'PATCH'|null $method
     *
     * @example webhookErrorUrl('https://my.webhook.url', 'PUT')
     */
    public function webhookErrorUrl(string $url, string|null $method = null): static
    {
        self::validateWebhookMethod($method);

        $this->getBodyBag()->set('webhookErrorUrl', $url);
        if ($method) {
            $this->getBodyBag()->set('webhookErrorMethod', $method);
        }

        return $this;
    }

    /**
     * Sets the URL that will receive structured JSON event callbacks after each webhook operation.
     * When set, POST requests are sent with event type (`webhook.success` or `webhook.error`), `correlationId`, and `timestamp`.
     *
     * @example webhookEventsUrl('https://my.webhook.url/events')
     */
    public function webhookEventsUrl(string $url): static
    {
        $this->getBodyBag()->set('webhookEventsUrl', $url);

        return $this;
    }

    /**
     * Sets the webhook route with params for event callbacks.
     *
     * @param string               $route      #Route
     * @param array<string, mixed> $parameters
     *
     * @example webhookEventsRoute('my_route_events', ['foo' => 'bar'])
     */
    public function webhookEventsRoute(string $route, array $parameters = []): static
    {
        return $this->webhookEventsUrl($this->generateAbsoluteUrl($route, $parameters));
    }

    /**
     * Extra headers that will be provided to the webhook endpoint. May it either be Success or Error.
     *
     * @param array<string, string> $extraHttpHeaders
     *
     * @example webhookExtraHeaders(['Authorization' => 'Bearer my-secret-token','X-Custom-Header' => 'CustomValue'])
     */
    public function webhookExtraHeaders(array $extraHttpHeaders): static
    {
        $this->getBodyBag()->set('webhookExtraHttpHeaders', array_map(static fn (mixed $value): string => (string) $value, $extraHttpHeaders));

        return $this;
    }

    /**
     * Adds extra headers to the ones already provided to the webhook endpoint, preserving previously set values.
     *
     * @param array<string, string> $extraHttpHeaders
     *
     * @example addWebhookExtraHeaders(['X-Custom-Header' => 'CustomValue'])
     */
    public function addWebhookExtraHeaders(array $extraHttpHeaders): static
    {
        if ([] === $extraHttpHeaders) {
            return $this;
        }

        $current = $this->getBodyBag()->get('webhookExtraHttpHeaders', []);

        return $this->webhookExtraHeaders(array_merge($current, $extraHttpHeaders));
    }

    /**
     * Sets the webhook route with params and method for cases of success.
     *
     * @param string                    $route      #Route
     * @param array<string, mixed>      $parameters
     * @param 'PATCH'|'POST'|'PUT'|null $method
     *
     * @example webhookRoute('my_route_success', ['foo' => 'bar'], 'PUT')
     */
    public function webhookRoute(string $route, array $parameters = [], string|null $method = null): static
    {
        return $this->webhookUrl($this->generateAbsoluteUrl($route, $parameters), $method);
    }

    /**
     * Sets the webhook route with params and method for cases of error.
     *
     * @param string                    $route      #Route
     * @param array<string, mixed>      $parameters
     * @param 'PATCH'|'POST'|'PUT'|null $method
     *
     * @example webhookErrorRoute('my_route_error', ['foo' => 'bar'], 'PUT')
     */
    public function webhookErrorRoute(string $route, array $parameters = [], string|null $method = null): static
    {
        return $this->webhookErrorUrl($this->generateAbsoluteUrl($route, $parameters), $method);
    }

    /**
     * Providing an existing $name from the configuration file, it will correctly set both success and error webhook URLs as well as extra_http_headers if defined.
     *
     * @throws InvalidBuilderConfiguration if no webhook configuration has this name
     *
     * @example webhookConfiguration('my_webhook_config')
     */
    public function webhookConfiguration(string $name): static
    {
        $webhookConfiguration = $this->getWebhookConfigurationRegistry()->get($name);

        $result = $this
            ->webhookUrl(
                $webhookConfiguration['success']['url'],
                $webhookConfiguration['success']['method'],
            )
            ->webhookErrorUrl(
                $webhookConfiguration['error']['url'],
                $webhookConfiguration['error']['method'],
            )
        ;

        if (isset($webhookConfiguration['events']['url'])) {
            $result = $result->webhookEventsUrl($webhookConfiguration['events']['url']);
        }

        if (\array_key_exists('extra_http_headers', $webhookConfiguration)) {
            $result = $result->webhookExtraHeaders($webhookConfiguration['extra_http_headers']);
        }

        return $result;
    }

    /**
     * @param array<mixed> $webhook
     */
    private function webhookConfigurationValidator(array $webhook): void
    {
        if (!isset($webhook['config_name']) && !isset($webhook['success']['url']) && !isset($webhook['success']['route'])) {
            throw new InvalidBuilderConfiguration('Invalid webhook configuration : At least a "success" key is required.');
        }

        foreach (['success', 'error', 'events'] as $type) {
            $configuration = $webhook[$type] ?? null;
            if (!\is_array($configuration)) {
                continue;
            }

            if (isset($configuration['url'], $configuration['route'])) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid webhook configuration : You must provide "url" or "route" keys for "%s" configuration.', $type));
            }

            $method = $configuration['method'] ?? null;
            if (null !== $method && !\in_array($method, ['POST', 'PUT', 'PATCH'], true) && 'events' !== $type) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid webhook configuration : "POST" "PUT", "PATCH" are the only available methods for "%s" configuration.', $type));
            }

            $route = $configuration['route'] ?? null;
            if (null === $route || \is_string($route)) {
                continue;
            }

            if (!\is_array($route) || !\is_string($route[0] ?? null)) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid webhook configuration : You must provide a valid route name for "%s" configuration.', $type));
            }

            if (!\is_array($route[1] ?? [])) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid webhook configuration : You must provide valid route parameters for "%s" configuration.', $type));
            }
        }
    }

    private static function validateWebhookMethod(string|null $method): void
    {
        if (null !== $method && !\in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid webhook method "%s": "POST", "PUT" and "PATCH" are the only available methods.', $method));
        }
    }
}
