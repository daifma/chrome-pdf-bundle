<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;

/**
 * @package Behavior\\FailOn
 */
trait FailOnTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Makes the generation fail (BrowserException) if the HTTP status code of the main page is not acceptable.
     * A code like 499 means the whole 400-499 range. (default: disabled). (overrides any previous configuration).
     *
     * @param list<int<100, 599>> $statusCodes
     *
     * @example failOnHttpStatusCodes([401, 403, 599])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('fail_on_http_status_codes', prototype: 'integer'))]
    public function failOnHttpStatusCodes(array $statusCodes): static
    {
        ValidatorFactory::httpStatusCodes('failOnHttpStatusCodes', $statusCodes);

        if ([] === $statusCodes) {
            $this->getBodyBag()->unset('failOnHttpStatusCodes');

            return $this;
        }

        $this->getBodyBag()->set('failOnHttpStatusCodes', array_values(array_unique($statusCodes)));

        return $this;
    }

    /**
     * Makes the generation fail (BrowserException) if the HTTP status code from at least one resource is not acceptable.
     * A code like 499 means the whole 400-499 range. (default: disabled). (overrides any previous configuration).
     *
     * @param list<int<100, 599>> $statusCodes
     *
     * @example failOnResourceHttpStatusCodes([401, 403, 599])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('fail_on_resource_http_status_codes', prototype: 'integer'))]
    public function failOnResourceHttpStatusCodes(array $statusCodes): static
    {
        ValidatorFactory::httpStatusCodes('failOnResourceHttpStatusCodes', $statusCodes);

        if ([] === $statusCodes) {
            $this->getBodyBag()->unset('failOnResourceHttpStatusCodes');

            return $this;
        }

        $this->getBodyBag()->set('failOnResourceHttpStatusCodes', array_values(array_unique($statusCodes)));

        return $this;
    }

    /**
     * Exclude resources from failOnResourceHttpStatusCodes checks based on their hostname.
     *
     * A match happens if the hostname equals the domain or is a subdomain of it
     * (e.g., browser.sentry-cdn.com matches sentry-cdn.com).
     *
     * Values are normalized (trimmed, lowercased) and may be provided as:
     *
     * example.com
     * .example.com or *.example.com
     * example.com:443 (port is ignored)
     * https://example.com/path (scheme/path are ignored)
     *
     * @param list<string> $domains
     *
     * @example ignoreResourceHttpStatusDomains(['sentry-cdn.com', 'analytics.example.com'])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('ignore_resource_http_status_domains', prototype: 'variable'))]
    public function ignoreResourceHttpStatusDomains(array $domains): static
    {
        $normalized = [];
        foreach ($domains as $domain) {
            if (!\is_string($domain)) { // @phpstan-ignore function.alreadyNarrowedType
                throw new InvalidBuilderConfiguration(\sprintf('Invalid domain for "ignoreResourceHttpStatusDomains", expected a string, got "%s".', get_debug_type($domain)));
            }

            $host = self::normalizeDomain($domain);
            if ('' !== $host) {
                $normalized[$host] = $host;
            }
        }

        if ([] === $normalized) {
            $this->getBodyBag()->unset('ignoreResourceHttpStatusDomains');

            return $this;
        }

        $this->getBodyBag()->set('ignoreResourceHttpStatusDomains', array_values($normalized));

        return $this;
    }

    /**
     * Makes the generation fail (BrowserException) if Chromium fails to load at least one resource. (default false).
     *
     * @example failOnResourceLoadingFailed() // is same as `->failOnResourceLoadingFailed(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('fail_on_resource_loading_failed'))]
    public function failOnResourceLoadingFailed(bool $bool = true): static
    {
        $this->getBodyBag()->set('failOnResourceLoadingFailed', $bool);

        return $this;
    }

    /**
     * Makes the generation fail (BrowserException) if there are exceptions in the Chromium console. (default false).
     *
     * @example failOnConsoleExceptions() // is same as `->failOnConsoleExceptions(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('fail_on_console_exceptions'))]
    public function failOnConsoleExceptions(bool $bool = true): static
    {
        $this->getBodyBag()->set('failOnConsoleExceptions', $bool);

        return $this;
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if (str_contains($domain, '://')) {
            $domain = (string) parse_url($domain, \PHP_URL_HOST);
        }

        $domain = (string) preg_replace('#[/?\#].*$#', '', $domain);

        if (str_starts_with($domain, '[')) { // IPv6 literal
            $end = strpos($domain, ']');

            return false === $end ? $domain : substr($domain, 0, $end + 1);
        }

        $domain = (string) preg_replace('#:\d*$#', '', $domain);

        return trim(ltrim($domain, '*.'), '.');
    }
}
