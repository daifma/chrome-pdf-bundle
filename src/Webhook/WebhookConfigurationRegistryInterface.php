<?php

namespace Daif\ChromePdfBundle\Webhook;

use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;

/**
 * @phpstan-type WebhookDefinition array{url?: string, route?: array{0: string, 1: array<string|int, mixed>}, method?: 'POST'|'PUT'|'PATCH'|null}
 */
interface WebhookConfigurationRegistryInterface
{
    /**
     * @param array{success: WebhookDefinition, error?: WebhookDefinition, events?: WebhookDefinition, extra_http_headers?: array<string, mixed>} $configuration
     */
    public function add(string $name, array $configuration): void;

    /**
     * @return array{
     *     success: array{
     *         url: string,
     *         method: 'POST'|'PUT'|'PATCH'|null,
     *     },
     *     error: array{
     *         url: string,
     *         method: 'POST'|'PUT'|'PATCH'|null,
     *     },
     *     events?: array{
     *         url: string,
     *     },
     *     extra_http_headers?: array<string, mixed>
     * }
     *
     * @throws InvalidBuilderConfiguration if configuration not found
     */
    public function get(string $name): array;
}
