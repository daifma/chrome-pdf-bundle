<?php

namespace Daif\ChromePdfBundle;

use Psr\Container\ContainerInterface;

final class ChromePdfFacade implements ChromePdfFacadeInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function pdf(): ChromePdfInterface
    {
        return $this->container->get(ChromePdfInterface::class);
    }

    public function screenshot(): ChromeScreenshotInterface
    {
        return $this->container->get(ChromeScreenshotInterface::class);
    }
}
