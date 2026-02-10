<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Enumeration\UserAgent;
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

        $this->getBodyBag()->set('extraHttpHeaders', $headers);

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

        $this->getBodyBag()->set('extraHttpHeaders', array_merge($current, $headers));

        return $this;
    }
}
