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
     * By default, the conversion starts once the "load" event is fired. Call skipNetworkIdleEvent(false) to also wait
     * for the network to be idle (no connection for 500ms), which ensures that the majority of the page is rendered
     * but often significantly slows down the conversion. (default true).
     *
     * When set to false, it takes precedence over skipNetworkAlmostIdleEvent.
     *
     * @example skipNetworkIdleEvent(false)
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('skip_network_idle_event'))]
    public function skipNetworkIdleEvent(bool $bool = true): static
    {
        $this->getBodyBag()->set('skipNetworkIdleEvent', $bool);

        return $this;
    }

    /**
     * Call skipNetworkAlmostIdleEvent(false) to wait for the network to be almost idle (at most 2 open connections
     * for 500ms) before the conversion. Useful for pages with long-polling or analytics connections. (default true).
     *
     * @example skipNetworkAlmostIdleEvent(false)
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('skip_network_almost_idle_event'))]
    public function skipNetworkAlmostIdleEvent(bool $bool = true): static
    {
        $this->getBodyBag()->set('skipNetworkAlmostIdleEvent', $bool);

        return $this;
    }
}
