<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;

/**
 * @package Behavior\\Performance
 */
trait PerformanceModeTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * ChromePdfFacade, by default, waits for the network idle event to ensure that the majority of the page is rendered during
     * conversion. However, this often significantly slows down the conversion process. Setting this form field to true
     * can greatly enhance the conversion speed.
     *
     * @example skipNetworkIdleEvent() // is same as `->skipNetworkIdleEvent(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('skip_network_idle_event'))]
    public function skipNetworkIdleEvent(bool $bool = true): static
    {
        $this->getBodyBag()->set('skipNetworkIdleEvent', $bool);

        return $this;
    }
}
