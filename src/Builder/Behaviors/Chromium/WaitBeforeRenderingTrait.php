<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * @package Behavior\\Chromium\\WaitFor
 */
trait WaitBeforeRenderingTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Sets the duration (i.e., "1s", "2ms", etc.) to wait when loading an HTML
     * document before converting it to PDF.
     *
     * @example waitDelay('5s')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('wait_delay'))]
    public function waitDelay(string $delay): static
    {
        ValidatorFactory::waitDelay($delay);
        $this->getBodyBag()->set('waitDelay', $delay);

        return $this;
    }

    /**
     * Sets the JavaScript expression to wait before converting an HTML document to PDF until it returns true.
     *
     * For instance: "window.status === 'ready'".
     *
     * @example waitForExpression("window.globalVar === 'ready'")
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('wait_for_expression'))]
    public function waitForExpression(string $expression): static
    {
        $this->getBodyBag()->set('waitForExpression', $expression);

        return $this;
    }
}
