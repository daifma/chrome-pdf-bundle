<?php

namespace Daif\ChromePdfBundle\Webhook;

use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * @internal
 *
 * @phpstan-import-type WebhookDefinition from WebhookConfigurationRegistryInterface
 */
final class WebhookConfigurationRegistry implements WebhookConfigurationRegistryInterface
{
    /**
     * @var array<string, array{success: WebhookDefinition, error?: WebhookDefinition, events?: WebhookDefinition, extra_http_headers?: array<string, mixed>}>
     */
    private array $definitions = [];

    /**
     * @var array<string, array{
     *      success: array{url: string, method: 'POST'|'PUT'|'PATCH'|null},
     *      error: array{url: string, method: 'POST'|'PUT'|'PATCH'|null},
     *      events?: array{url: string},
     *      extra_http_headers?: array<string, mixed>
     *  }>
     */
    private array $configurations = [];

    public function __construct(
        private readonly UrlGeneratorInterface|null $urlGenerator = null,
        private readonly RequestContext|null $requestContext = null,
    ) {
    }

    public function add(string $name, array $configuration): void
    {
        // Resolved lazily: routes may not be generated while the container is being built.
        $this->definitions[$name] = $configuration;
        unset($this->configurations[$name]);
    }

    public function get(string $name): array
    {
        if (isset($this->configurations[$name])) {
            return $this->configurations[$name];
        }

        $configuration = $this->definitions[$name] ?? throw new InvalidBuilderConfiguration(\sprintf('Webhook configuration "%s" not found.', $name));

        $urlGenerator = $this->urlGenerator;
        $previousContext = $urlGenerator?->getContext();
        if (null !== $urlGenerator && null !== $this->requestContext) {
            $urlGenerator->setContext($this->requestContext);
        }

        try {
            $success = [
                'url' => $this->resolveUrl($configuration['success']),
                'method' => $configuration['success']['method'] ?? null,
            ];
            $error = $success;

            if (isset($configuration['error'])) {
                $error = [
                    'url' => $this->resolveUrl($configuration['error']),
                    'method' => $configuration['error']['method'] ?? null,
                ];
            }

            $namedConfiguration = ['success' => $success, 'error' => $error];

            if (isset($configuration['events'])) {
                $namedConfiguration['events'] = ['url' => $this->resolveUrl($configuration['events'])];
            }

            if (isset($configuration['extra_http_headers']) && [] !== $configuration['extra_http_headers']) {
                $namedConfiguration['extra_http_headers'] = $configuration['extra_http_headers'];
            }
        } finally {
            if (null !== $urlGenerator && null !== $previousContext) {
                $urlGenerator->setContext($previousContext);
            }
        }

        return $this->configurations[$name] = $namedConfiguration;
    }

    /**
     * @param WebhookDefinition $definition
     *
     * @throws InvalidBuilderConfiguration
     */
    private function resolveUrl(array $definition): string
    {
        if (isset($definition['url'])) {
            return $definition['url'];
        }

        if (isset($definition['route'])) {
            if (null === $this->urlGenerator) {
                throw new InvalidBuilderConfiguration('A webhook "route" requires the router. Try to run "composer require symfony/routing".');
            }

            return $this->urlGenerator->generate($definition['route'][0], $definition['route'][1], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        throw new InvalidBuilderConfiguration('Invalid webhook configuration: a "url" or a "route" is required.');
    }
}
