<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Enumeration\UserAgent;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * @package Behavior\\Http\\CustomHeaders
 */
trait CustomHttpHeadersTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Override the default User-Agent HTTP header.
     *
     * @param UserAgent::*|string $userAgent
     *
     * @example userAgent(UserAgent::AndroidChrome)
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('user_agent', restrictTo: 'string'))]
    public function userAgent(string $userAgent): static
    {
        if ('' === trim($userAgent)) {
            throw new InvalidBuilderConfiguration('The user agent cannot be empty.');
        }

        $this->getBodyBag()->set('userAgent', $userAgent);

        return $this;
    }

    /**
     * Sets extra HTTP headers that Chromium will send when loading the HTML document. (overrides any previous headers).
     *
     * @param array<string, string> $headers
     *
     * @example extraHttpHeaders(['MyHeader' => 'MyValue'])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('extra_http_headers', normalizeKeys: false, useAttributeAsKey: 'name', prototype: 'variable'))]
    public function extraHttpHeaders(array $headers): static
    {
        if ([] === $headers) {
            $this->getBodyBag()->unset('extraHttpHeaders');

            return $this;
        }

        $this->getBodyBag()->set('extraHttpHeaders', self::normalizeHttpHeaders($headers));

        return $this;
    }

    /**
     * Adds extra HTTP headers that Chromium will send when loading the HTML document.
     *
     * @param array<string, string> $headers
     *
     * @example addExtraHttpHeaders(['MyHeader' => 'MyValue'])
     */
    public function addExtraHttpHeaders(array $headers): static
    {
        if ([] === $headers) {
            return $this;
        }

        $current = $this->getBodyBag()->get('extraHttpHeaders', []);

        $this->getBodyBag()->set('extraHttpHeaders', array_merge($current, self::normalizeHttpHeaders($headers)));

        return $this;
    }

    /**
     * @param array<mixed> $headers
     *
     * @return array<string, string>
     */
    private static function normalizeHttpHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (!\is_string($name) || '' === $name || (!\is_scalar($value) && !$value instanceof \Stringable)) {
                throw new InvalidBuilderConfiguration('Invalid extra HTTP headers, expected an array like ["MyHeader" => "MyValue"].');
            }

            $normalized[$name] = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $normalized;
    }
}
